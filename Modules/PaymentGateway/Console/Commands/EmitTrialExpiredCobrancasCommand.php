<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use App\Account;
use App\Business;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\PaymentGateway\Contracts\PaymentGatewayContract;
use Modules\PaymentGateway\Dto\EmitirCobrancaInput;
use Modules\PaymentGateway\Models\Cobranca;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\Superadmin\Entities\Subscription;

/**
 * Cron diário — emite cobrança PIX Automático pra Subscriptions waiting com
 * trial expirado e sem cobrança ativa.
 *
 * ADR 0170 Onda 5.B SIMPLIFICADA — par do BusinessAutoSubscriptionObserver.
 * Fluxo end-to-end:
 *   1. Wagner cadastra Business → Observer cria Subscription waiting + trial
 *   2. Cron diário (este command) → trial expirado → emite cobrança
 *   3. Tenant autoriza mandato BCB → CobrancaPaga → Subscription approved
 *
 * Schedule (SuperadminServiceProvider em env=live):
 *   $schedule->command('paymentgateway:emit-trial-expired')->daily();
 *
 * Idempotência:
 *   - Cobrança usa idempotency_key 'onda5b-sub-{subscription_id}-{YYYY-MM}'
 *     (mensal — Subscription nasce 1x, mas se trial+cobrança+vencida+nova_cobrança
 *     ocorrer no mesmo mês, evita dupla emissão).
 *   - Skip Subscriptions que já têm cobrança em status emitida|paga este mês.
 *
 * Multi-tenant Tier 0:
 *   - Cobranca.business_id = 1 (Wagner emite)
 *   - Subscription.business_id = <tenant> (cross-tenant withoutGlobalScopes)
 *   - Business resolução pra payer data: withoutGlobalScopes obrigatório
 */
class EmitTrialExpiredCobrancasCommand extends Command
{
    protected $signature = 'paymentgateway:emit-trial-expired
                            {--dry-run : Só lista candidatas, não emite}';

    protected $description = 'Emite cobrança PIX Automático pra Subscriptions waiting com trial expirado (Onda 5.B daily cron)';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[dry-run] Nenhuma cobrança será emitida.');
        }

        // 1. Pré-condições Wagner biz=1
        $credencial = PaymentGatewayCredential::query()
            ->withoutGlobalScopes()
            ->where('business_id', 1)
            ->where('driver', 'bcb_pix')
            ->where('ativo', true)
            ->first();

        if (!$credencial) {
            $this->error('Sem credencial BCB ativa em biz=1. Cadastre em /paymentgateway/credenciais.');

            return self::FAILURE;
        }

        $contaBancaria = ContaBancaria::query()
            ->withoutGlobalScopes()
            ->where('business_id', 1)
            ->where('rb_gateway_credential_id', $credencial->id)
            ->where('ativo_para_boleto', true)
            ->first();

        if (!$contaBancaria) {
            $this->error('Sem ContaBancaria vinculada à credencial BCB. Configure em /financeiro/contas.');

            return self::FAILURE;
        }

        $account = Account::find($contaBancaria->account_id);
        if (!$account) {
            $this->error('Account UltimatePOS não encontrada (ContaBancaria #' . $contaBancaria->id . ').');

            return self::FAILURE;
        }

        // 2. Candidatas: Subscription waiting + trial expirado + sem cobrança ativa
        $today = now()->toDateString();
        $candidates = Subscription::query()
            ->where('status', 'waiting')
            ->whereNotNull('trial_end_date')
            ->whereDate('trial_end_date', '<=', $today)
            ->where('paid_via', 'paymentgateway_pix_automatico')
            ->orderBy('id')
            ->get();

        $this->line(sprintf('%d Subscription(s) waiting com trial expirado.', $candidates->count()));

        $emitted = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($candidates as $subscription) {
            $hasCobrancaAtiva = Cobranca::query()
                ->withoutGlobalScopes()
                ->where('origem_type', 'subscription_license')
                ->where('origem_id', $subscription->id)
                ->whereIn('status', ['emitida', 'paga'])
                ->whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->exists();

            if ($hasCobrancaAtiva) {
                $this->line(sprintf('  Sub #%d biz=%d: já tem cobrança ativa este mês — skip', $subscription->id, $subscription->business_id));
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf('  Sub #%d biz=%d: WOULD emit cobrança PIX Automático', $subscription->id, $subscription->business_id));
                $emitted++;
                continue;
            }

            try {
                DB::transaction(function () use ($subscription, $account): void {
                    $tenant = Business::withoutGlobalScopes()->find($subscription->business_id);
                    if (!$tenant) {
                        throw new \RuntimeException('Business tenant #' . $subscription->business_id . ' não encontrado');
                    }

                    $package = $subscription->package;
                    $valorCentavos = (int) round(((float) $subscription->package_price) * 100);
                    if ($valorCentavos <= 0 && $package) {
                        $valorCentavos = (int) round(((float) $package->price) * 100);
                    }
                    if ($valorCentavos <= 0) {
                        throw new \RuntimeException('Valor cobrança = 0 (Subscription #' . $subscription->id . ')');
                    }

                    $input = new EmitirCobrancaInput(
                        businessId: 1,
                        contactId: 0,
                        valorCentavos: $valorCentavos,
                        vencimento: new \DateTimeImmutable('+7 days'),
                        descricao: 'Mensalidade SaaS — ' . ($package->name ?? 'Premium') . ' — ' . $tenant->name,
                        idempotencyKey: 'onda5b-sub-' . $subscription->id . '-' . now()->format('Y-m'),
                        origemType: 'subscription_license',
                        origemId: $subscription->id,
                        meta: ['target_business_id' => $subscription->business_id, 'auto_onboarding' => true],
                    );

                    $result = app(PaymentGatewayContract::class)
                        ->for($account)
                        ->emitirPixAutomatico($input);

                    $subscription->payment_transaction_id = (string) $result->cobrancaId;
                    $subscription->save();
                });

                $emitted++;
                $this->line(sprintf('  Sub #%d biz=%d: ✓ cobrança emitida', $subscription->id, $subscription->business_id));

                Log::info('[onda5b] trial expirado — cobrança PIX Automático emitida', [
                    'subscription_id' => $subscription->id,
                    'business_id'     => $subscription->business_id,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf('  Sub #%d biz=%d: FALHOU — %s', $subscription->id, $subscription->business_id, $e->getMessage()));
                Log::error('[onda5b] trial expirado — falha emitir cobrança', [
                    'subscription_id' => $subscription->id,
                    'business_id'     => $subscription->business_id,
                    'exception'       => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d candidatas · %s %d · skipped %d · failed %d',
            $candidates->count(),
            $dryRun ? 'WOULD emit' : 'emitted',
            $emitted,
            $skipped,
            $failed,
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
