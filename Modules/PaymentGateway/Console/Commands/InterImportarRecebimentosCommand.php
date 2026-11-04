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
use Modules\PaymentGateway\Services\BaixaDeBoletoMigrado;
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
 * Sabor Brasil (2026-11-04) — antes de criar título novo, o recebimento:
 *   - é pulado se a cobrança foi emitida pelo oimpresso (o listener da CobrancaPaga já baixa);
 *   - baixa o título que já existe, quando ele veio do Delphi com o nosso número
 *     (BaixaDeBoletoMigrado). Ambíguo ou com diferença acima de 10% fica para conferência.
 * `--pendencias` lista no fim os boletos ainda em aberto e vencidos, por banco, para o
 * cliente conferir (inclui BB e Santander, que não têm API de consulta aqui).
 * Janelas de até 90 dias por chamada ao Inter (pedidos longos, como o acerto pré-migração,
 * são divididos).
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
                            {--dry-run : Só lista o que importaria, sem gravar}
                            {--pendencias : No fim, lista os boletos em aberto e vencidos por banco}';

    protected $description = 'Importa boletos pagos no Inter como título recebido + baixa no Financeiro (US-PG-008)';

    public function handle(InterDriver $driver, BaixaDeBoletoMigrado $migrado): int
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

        $fim = new \DateTimeImmutable('today');

        try {
            $itens = [];
            for ($ate = $fim, $resta = $days; $resta > 0; $resta -= 90) {
                $de = $ate->modify('-' . (min(90, $resta) - 1) . ' days');
                array_push($itens, ...$driver->listarCobrancasPagas($cred, $de, $ate));
                $ate = $de->modify('-1 day');
            }
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
        $baixados = 0;
        $conferir = [];

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

            // Cobrança emitida pelo oimpresso: quem baixa é o listener da CobrancaPaga.
            if ($this->emitidaPeloOimpresso($businessId, $codigo, $nossoNumero)) {
                $pulados++;
                continue;
            }

            // Título que já existe (migrado do Delphi): baixa ele em vez de criar outro.
            $rec = [
                'ref' => $refId, 'seu_numero' => $seuNumero, 'nosso_numero' => $nossoNumero, 'banco' => '077',
                'valor_nominal' => $valor,
                'valor_pago' => (float) ($cob['valorTotalRecebido'] ?? $cob['valorTotalRecebimento'] ?? $valor),
                'vencimento' => isset($cob['dataVencimento']) ? (string) $cob['dataVencimento'] : null,
                'pago_em' => $pagaEm->format('Y-m-d'),
                'pagador_doc' => isset($cob['pagador']['cpfCnpj']) ? (string) $cob['pagador']['cpfCnpj'] : null,
            ];
            if ($dryRun) {
                DB::beginTransaction();
            }
            try {
                $r = $migrado->baixar($businessId, $rec, $contaId);
            } finally {
                if ($dryRun) {
                    DB::rollBack();
                }
            }
            if ($r['resultado'] === BaixaDeBoletoMigrado::BAIXADO) {
                $baixados++;
                $this->line(sprintf('  %s baixa título #%d · seuNumero=%s · juros %s · desconto %s',
                    $dryRun ? '[dry-run]' : '✓', $r['titulo_id'], $seuNumero ?: '-',
                    number_format($r['juros'], 2, ',', '.'), number_format($r['desconto'], 2, ',', '.')));
                continue;
            }
            if ($r['resultado'] !== BaixaDeBoletoMigrado::NENHUM) {
                $conferir[] = [$r['resultado'], $seuNumero ?: '-', $nossoNumero ?: '-', number_format($valor, 2, ',', '.'), $r['titulo_id'] ?? '-'];
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
            '✓ Resumo: %d título(s) existente(s) %s · %d %s · %d já existentes (pulados) · %d para conferir · %d erro(s)',
            $baixados,
            $dryRun ? 'a baixar' : 'baixados',
            $importados,
            $dryRun ? 'a importar' : 'importados',
            $pulados,
            count($conferir),
            $erros,
        ));
        if ($conferir !== []) {
            $this->warn('Recebimentos que não foram baixados nem importados (conferir à mão):');
            $this->table(['motivo', 'seuNumero', 'nossoNumero', 'valor', 'título'], $conferir);
        }
        if ($this->option('pendencias')) {
            $this->listarPendencias($businessId);
        }

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function emitidaPeloOimpresso(int $businessId, string $codigo, string $nossoNumero): bool
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('cobrancas')) {
            return false;
        }

        return DB::table('cobrancas')->where('business_id', $businessId)
            ->where(function ($w) use ($codigo, $nossoNumero) {
                $w->when($codigo !== '', fn ($q) => $q->orWhere('gateway_external_id', $codigo))
                    ->when($nossoNumero !== '', fn ($q) => $q->orWhere('nosso_numero', $nossoNumero));
            })
            ->when($codigo === '' && $nossoNumero === '', fn ($q) => $q->whereRaw('1 = 0'))
            ->exists();
    }

    /** Boletos a receber ainda em aberto e vencidos, por banco — a lista que vai ao cliente conferir. */
    private function listarPendencias(int $businessId): void
    {
        $linhas = DB::table('fin_titulos')->where('business_id', $businessId)
            ->where('tipo', 'receber')->whereIn('status', ['aberto', 'parcial'])->whereNull('deleted_at')
            ->whereDate('vencimento', '<', now()->toDateString())
            ->orderBy('vencimento')
            ->get(['numero', 'cliente_descricao', 'vencimento', 'valor_aberto', 'forma_pagamento', 'metadata'])
            ->filter(fn ($l) => $l->forma_pagamento === 'boleto'
                || ! empty(json_decode((string) $l->metadata, true)['boleto_nosso_numero'] ?? null))
            ->values();

        $this->newLine();
        $this->warn(sprintf('%d boleto(s) em aberto e vencido(s):', $linhas->count()));
        $porBanco = $linhas->groupBy(fn ($l) => (string) (json_decode((string) $l->metadata, true)['boleto_banco'] ?? 'sem banco'));
        foreach ($porBanco as $banco => $grupo) {
            $this->line(sprintf('  banco %s: %d', $banco, $grupo->count()));
        }
        $this->table(['título', 'cliente', 'vencimento', 'em aberto', 'banco'], $linhas->map(fn ($l) => [
            $l->numero, $l->cliente_descricao, $l->vencimento, number_format((float) $l->valor_aberto, 2, ',', '.'),
            (string) (json_decode((string) $l->metadata, true)['boleto_banco'] ?? '-'),
        ])->all());
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
