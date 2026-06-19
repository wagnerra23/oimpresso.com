<?php

declare(strict_types=1);

namespace Modules\PaymentGateway\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Models\ContaBancaria;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\PaymentGateway\Models\PaymentGatewayCredential;
use Modules\PaymentGateway\Services\Drivers\InterDriver;

/**
 * US-PG-008 — Importa boletos pagos no Inter como título recebido + baixa no
 * Financeiro do oimpresso.
 *
 * Caso WR2 (biz=1): emite boletos pelo sistema legado (WR), não pelo oimpresso.
 * Os pagamentos entram no Inter mas não existem no Financeiro. Este comando
 * puxa do Inter (GET /cobranca/v3/cobrancas, situacao=RECEBIDO) e cria no
 * Financeiro título `receber`+`quitado` + baixa — espelhando o listener
 * OnCobrancaPagaCreateFinanceiroTitulo.
 *
 * Idempotente: dedup por metadata->inter_ref (codigoSolicitacao/nossoNumero).
 * Re-run NÃO duplica. Multi-tenant Tier 0 (biz=1).
 *
 * Uso:
 *   php artisan paymentgateway:inter-importar-recebimentos --dry-run        # só lista
 *   php artisan paymentgateway:inter-importar-recebimentos --conta=12        # importa
 */
class InterImportarRecebimentosCommand extends Command
{
    protected $signature = 'paymentgateway:inter-importar-recebimentos
                            {--business=1 : business_id (default 1 = WR2)}
                            {--days=60 : Janela de pagamentos a importar (dias atrás)}
                            {--conta= : ID da fin_contas_bancarias destino da baixa}
                            {--dry-run : Só lista o que importaria, sem gravar}';

    protected $description = 'Importa boletos pagos no Inter como título recebido + baixa no Financeiro (US-PG-008)';

    public function handle(InterDriver $driver): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $businessId = (int) $this->option('business');
        $days = max(1, (int) $this->option('days'));

        if ($dryRun) {
            $this->warn('[dry-run] Nada será gravado no Financeiro.');
        }

        // SUPERADMIN: comando CLI sem sessão web; resolve a credencial Inter do business_id passado via --business (default 1 = WR2).
        $cred = PaymentGatewayCredential::withoutGlobalScopes()
            ->where('business_id', $businessId)
            ->where('gateway_key', 'inter')
            ->where('ativo', true)
            ->orderByDesc('id')
            ->first();

        if (! $cred) {
            $this->error("Sem credencial Inter ativa pra biz={$businessId}.");

            return self::FAILURE;
        }

        $ini = new \DateTimeImmutable("-{$days} days");
        $fim = new \DateTimeImmutable('today');

        try {
            $itens = $driver->listarCobrancasPagas($cred, $ini, $fim);
        } catch (\Throwable $e) {
            $this->error('Falha ao listar cobranças no Inter: ' . $e->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf('%d cobrança(s) RECEBIDA(s) no Inter (últimos %dd).', count($itens), $days));

        $contaId = $this->resolveConta($businessId, $this->option('conta'));
        if (! $dryRun && $contaId === null) {
            $this->warn('Nenhuma conta bancária resolvida — títulos serão criados SEM baixa. Use --conta=ID pra vincular a conta Inter.');
        }

        $importados = 0;
        $pulados = 0;
        $erros = 0;

        foreach ($itens as $item) {
            $cob = $item['cobranca'] ?? $item;
            $codigo = (string) ($cob['codigoSolicitacao'] ?? '');
            $seuNumero = (string) ($cob['seuNumero'] ?? '');
            $nossoNumero = (string) ($item['boleto']['nossoNumero'] ?? $cob['nossoNumero'] ?? '');
            $valor = (float) ($cob['valorNominal'] ?? 0);
            $pagadorNome = $cob['pagador']['nome'] ?? null;
            $dataPagRaw = $cob['dataSituacao'] ?? $cob['dataHoraSituacao'] ?? null;
            $pagaEm = $dataPagRaw ? \Carbon\Carbon::parse((string) $dataPagRaw) : now();

            $refId = $codigo !== '' ? $codigo : ($nossoNumero !== '' ? $nossoNumero : $seuNumero);
            if ($refId === '' || $valor <= 0) {
                continue;
            }

            // SUPERADMIN: CLI sem sessão; dedup do título importado filtrando pelo business_id do --business.
            $jaExiste = Titulo::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('metadata->inter_ref', $refId)
                ->exists();

            if ($jaExiste) {
                $pulados++;
                continue;
            }

            if ($dryRun) {
                $importados++;
                $this->line(sprintf(
                    '  [dry-run] importaria: R$ %s · seuNumero=%s · nossoNumero=%s · pagador=%s · pago em %s',
                    number_format($valor, 2, ',', '.'),
                    $seuNumero ?: '-',
                    $nossoNumero ?: '-',
                    $pagadorNome ?? '?',
                    $pagaEm->format('d/m/Y'),
                ));
                continue;
            }

            try {
                DB::transaction(function () use ($businessId, $refId, $seuNumero, $nossoNumero, $valor, $pagadorNome, $pagaEm, $contaId): void {
                    $titulo = Titulo::create([
                        'business_id'       => $businessId,
                        'numero'            => substr('INT-' . ($nossoNumero ?: $seuNumero ?: $refId), 0, 20),
                        'tipo'              => 'receber',
                        'status'            => $contaId ? 'quitado' : 'aberto',
                        'cliente_descricao' => $pagadorNome,
                        'valor_total'       => $valor,
                        'valor_aberto'      => $contaId ? 0 : $valor,
                        'moeda'             => 'BRL',
                        'emissao'           => $pagaEm->format('Y-m-d'),
                        'vencimento'        => $pagaEm->format('Y-m-d'),
                        'competencia_mes'   => $pagaEm->format('Y-m'),
                        'origem'            => 'manual',
                        'observacoes'       => 'Recebimento Inter importado (boleto pago) · seuNumero ' . ($seuNumero ?: '-'),
                        'metadata'          => [
                            'source'            => 'inter_import',
                            'inter_ref'         => $refId,
                            'inter_seu_numero'  => $seuNumero,
                            'inter_nosso_numero' => $nossoNumero,
                        ],
                        'created_by'        => 1,
                    ]);

                    if ($contaId !== null) {
                        TituloBaixa::create([
                            'business_id'       => $businessId,
                            'titulo_id'         => $titulo->id,
                            'conta_bancaria_id' => $contaId,
                            'valor_baixa'       => $valor,
                            'juros'             => 0,
                            'multa'             => 0,
                            'desconto'          => 0,
                            'data_baixa'        => $pagaEm->format('Y-m-d'),
                            'meio_pagamento'    => 'boleto',
                            'idempotency_key'   => $this->buildIdempotencyKey($refId),
                            'observacoes'       => 'Auto-baixa import Inter · ' . $refId,
                            'created_by'        => 1,
                        ]);
                    }
                });

                $importados++;
                $this->info(sprintf('  ✓ importado: R$ %s seuNumero=%s', number_format($valor, 2, ',', '.'), $seuNumero ?: '-'));
            } catch (\Throwable $e) {
                $erros++;
                $this->warn(sprintf('  ✗ falha ref=%s: %s', $refId, substr($e->getMessage(), 0, 120)));
                Log::warning('paymentgateway.inter.import.falha', [
                    'business_id' => $businessId,
                    'inter_ref'   => $refId,
                    'error'       => substr($e->getMessage(), 0, 200),
                ]);
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '✓ Resumo: %d %s · %d já existentes (pulados) · %d erro(s)',
            $importados,
            $dryRun ? 'a importar' : 'importados',
            $pulados,
            $erros,
        ));

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve a conta bancária destino da baixa.
     * Prioridade: --conta=ID > credencial.conta_bancaria_id > primeira ativo_para_boleto.
     */
    private function resolveConta(int $businessId, ?string $contaOption): ?int
    {
        if ($contaOption !== null && $contaOption !== '') {
            $conta = ContaBancaria::where('business_id', $businessId)->where('id', (int) $contaOption)->first();

            return $conta ? (int) $conta->id : null;
        }

        $conta = ContaBancaria::where('business_id', $businessId)
            ->where('ativo_para_boleto', true)
            ->orderBy('id')
            ->first();

        return $conta ? (int) $conta->id : null;
    }

    /**
     * UUID determinístico baseado na referência Inter — idempotência da baixa
     * via UNIQUE (business_id, idempotency_key). Espelha OnCobrancaPagaCreate...
     */
    private function buildIdempotencyKey(string $refId): string
    {
        $hash = md5('inter.import.' . $refId);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }
}
