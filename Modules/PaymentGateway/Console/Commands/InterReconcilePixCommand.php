<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;
use Modules\PaymentGateway\Services\ReconciliarCobrancaService;

/**
 * Polling de reconciliação PIX Inter — fallback do webhook.
 *
 * Por que existe: o Inter NÃO empurra confirmação de pagamento sozinho. O
 * webhook (`InterPixWebhookController`) só funciona depois de cadastrar a URL
 * no Inter (PUT /pix/v2/webhook) + mTLS + URL pública HTTPS. Enquanto isso (e
 * como rede de segurança permanente caso o webhook caia/atrase), este comando
 * PERGUNTA ao Inter, de tempos em tempos, quais cobranças PIX emitidas já foram
 * pagas — via GET /pix/v2/cob/{txid} — e reconcilia.
 *
 * Fluxo por credencial Inter ativa:
 *   1. Lista Cobrancas tipo pix_cob/pix_cobv ainda 'emitida' na janela de N dias
 *   2. Pra cada uma, consulta o status no Inter (InterDriver::consultarPixCob)
 *   3. Se status='paga' → ReconciliarCobrancaService::marcarPaga (mesma lógica
 *      do webhook: marca paga + quita título + dispara CobrancaPaga)
 *
 * Idempotência: só processa status='emitida' (já-pagas saem da query). A
 * reconciliação em si é idempotente (listener Financeiro checa origem_id).
 *
 * Multi-tenant Tier 0 (ADR 0093): console sem session() → withoutGlobalScopes +
 * business_id explícito propagado da credencial pra reconciliação.
 *
 * Schedule (app/Console/Kernel.php):
 *   $schedule->command('paymentgateway:inter-reconcile-pix')
 *       ->everyTenMinutes()->withoutOverlapping();
 *
 * Uso manual:
 *   php artisan paymentgateway:inter-reconcile-pix --dry-run
 *   php artisan paymentgateway:inter-reconcile-pix --business=1 --days=3
 */
class InterReconcilePixCommand extends Command
{
    protected $signature = 'paymentgateway:inter-reconcile-pix
                            {--business= : Reconcilia só este business_id (default: todos com credencial Inter ativa)}
                            {--days=7 : Janela de cobranças emitidas a verificar (dias atrás)}
                            {--limit=200 : Máximo de cobranças consultadas por credencial}
                            {--dry-run : Só lista o que reconciliaria, sem marcar paga}';

    protected $description = 'Polling de reconciliação: consulta o Inter pelas cobranças PIX emitidas e marca as pagas (fallback do webhook)';

    public function handle(InterDriver $driver, ReconciliarCobrancaService $reconciliador): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $days = max(1, (int) $this->option('days'));
        $limit = max(1, (int) $this->option('limit'));
        $businessFilter = $this->option('business') !== null ? (int) $this->option('business') : null;

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma cobrança será marcada paga.');
        }

        // SUPERADMIN: cron/CLI sem sessão web; varre credenciais Inter ativas de todos os tenants (ou do --business filtrado) pra polling de reconciliação.
        $credsQuery = PaymentGatewayCredential::query()
            ->withoutGlobalScopes()
            ->where('gateway_key', 'inter')
            ->where('ativo', true);

        if ($businessFilter !== null) {
            $credsQuery->where('business_id', $businessFilter);
        }

        $creds = $credsQuery->orderBy('id')->get();

        if ($creds->isEmpty()) {
            $this->warn('Nenhuma credencial Inter ativa encontrada' . ($businessFilter !== null ? " pra biz={$businessFilter}." : '.'));

            return self::SUCCESS;
        }

        $checked = 0;
        $paid = 0;
        $errors = 0;

        foreach ($creds as $cred) {
            // SUPERADMIN: CLI sem sessão; lista cobranças PIX emitidas filtrando pelo business_id da credencial sendo reconciliada.
            $cobrancas = Cobranca::query()
                ->withoutGlobalScopes()
                ->where('business_id', $cred->business_id)
                ->where('payment_gateway_credential_id', $cred->id)
                ->whereIn('tipo', ['pix_cob', 'pix_cobv'])
                ->where('status', 'emitida')
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('id')
                ->limit($limit)
                ->get();

            if ($cobrancas->isEmpty()) {
                continue;
            }

            $this->line(sprintf(
                'Credencial #%d biz=%d (%s): %d cobrança(s) PIX emitida(s) na janela %dd.',
                $cred->id,
                $cred->business_id,
                $cred->ambiente,
                $cobrancas->count(),
                $days,
            ));

            foreach ($cobrancas as $cobranca) {
                $checked++;

                try {
                    $status = $driver->consultarPixCob($cobranca, $cred);
                } catch (\Throwable $e) {
                    $errors++;
                    $this->warn(sprintf('  cobrança #%d (txid=%s): consultar falhou — %s', $cobranca->id, $cobranca->gateway_external_id, $e->getMessage()));
                    Log::warning('paymentgateway.inter.reconcile.consultar_falhou', [
                        'cobranca_id'   => $cobranca->id,
                        'business_id'   => $cred->business_id,
                        'credential_id' => $cred->id,
                        'error'         => substr($e->getMessage(), 0, 200),
                    ]);
                    continue;
                }

                if ($status->status !== 'paga') {
                    continue;
                }

                $valorPago = $status->valorPagoCentavos ?? (int) $cobranca->valor_centavos;
                $pagaEm = $status->pagaEm ?? new \DateTimeImmutable();

                if ($dryRun) {
                    $paid++;
                    $this->line(sprintf('  [dry-run] cobrança #%d biz=%d → WOULD marcar paga (R$ %s)', $cobranca->id, $cred->business_id, number_format($valorPago / 100, 2, ',', '.')));
                    continue;
                }

                DB::transaction(function () use ($reconciliador, $cobranca, $cred, $valorPago, $pagaEm): void {
                    $reconciliador->marcarPaga(
                        cobranca: $cobranca,
                        businessId: (int) $cred->business_id,
                        valorPagoCentavos: (int) $valorPago,
                        pagaEm: $pagaEm,
                        formaPagamento: 'pix',
                    );
                });

                $paid++;
                $this->info(sprintf('  ✓ cobrança #%d biz=%d marcada paga (R$ %s)', $cobranca->id, $cred->business_id, number_format($valorPago / 100, 2, ',', '.')));
                Log::info('paymentgateway.inter.reconcile.paga', [
                    'cobranca_id'   => $cobranca->id,
                    'business_id'   => $cred->business_id,
                    'credential_id' => $cred->id,
                    'valor_centavos' => $valorPago,
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d credencial(is) · %d cobrança(s) consultada(s) · %s %d paga(s) · %d erro(s)',
            $creds->count(),
            $checked,
            $dryRun ? 'WOULD mark' : 'marcou',
            $paid,
            $errors,
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
