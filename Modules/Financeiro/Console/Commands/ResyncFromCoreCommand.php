<?php

declare(strict_types=1);

namespace Modules\Financeiro\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Models\Concerns\BusinessScopeImpl;
use Modules\Financeiro\Models\Titulo;
use Modules\Financeiro\Models\TituloBaixa;
use Modules\Financeiro\Services\TituloAutoService;

/**
 * Re-sincroniza fin_titulos inflados com o `transactions.final_total` JÁ
 * CORRIGIDO do core (UltimatePOS) — resíduo do incidente `num_uf`.
 *
 * CONTEXTO (incidente ROTA LIVRE biz=4, maio/2026):
 *  - Bug histórico do parser pt-BR `num_uf` (fix #2279) gravava
 *    `transactions.final_total` 10×/100×/1000× inflado quando o valor entrava
 *    formatado en-US ("80.00" → 8000) — pior ainda com desconto.
 *  - O `TituloAutoService` (bridge) COPIA `final_total` cru pro
 *    `fin_titulos.valor_total`. Logo, toda venda inflada virou título inflado.
 *  - O incidente #2280 corrigiu o CORE (`sells:final-total-audit --apply`) via
 *    `UPDATE` direto — que NÃO dispara Observer. Resultado: o core ficou certo,
 *    mas o ESPELHO `fin_titulos` ficou congelado no valor podre. Larissa vê
 *    "a receber R$ 50,8M" enquanto o core diz ~R$ 396k.
 *
 * O QUE FAZ: pra cada título venda/compra cujo `valor_total` diverge (acima de
 * --ratio) do `final_total` corrigido do core, re-espelha o core:
 *   1. Estorna (APPEND-ONLY, nunca delete — TECH-0002) as baixas-lixo do título
 *      (valor_baixa > core * ratio — impossível matematicamente).
 *   2. Seta `valor_total = core final_total` (+ metadata.valor_total_antigo).
 *   3. Recalcula valor_aberto + status via TituloAutoService::recalcularTitulo
 *      (preserva 'cancelado').
 *   4. Grava trilha em activity_log (paridade com sells:final-total-audit).
 *
 * DEPENDÊNCIA DE ORDEM: rode `php artisan sells:final-total-audit --business=N`
 * ANTES — ele conserta o CORE. Este comando só ESPELHA o core já corrigido.
 * Se o core ainda estiver podre, valor_total == final_total (ambos lixo) e o
 * título nem aparece como candidato (não há divergência) — é problema do core.
 *
 * SEGURO: DRY-RUN por padrão (apenas lista impacto). `--apply` exige `--business`.
 * Idempotente: re-rodar não re-estorna (baixa já estornada é ignorada) nem
 * re-mexe (valor_total já == core deixa de ser candidato). Reversível via
 * `metadata.resync_from_core` + `metadata.valor_total_antigo`.
 *
 * Uso:
 *   php artisan financeiro:resync-from-core --business=4            # DRY-RUN
 *   php artisan financeiro:resync-from-core --business=4 --detail
 *   php artisan financeiro:resync-from-core --business=4 --apply
 *
 * Refs: #2279 (fix num_uf), #2280 (correção core 16 vendas), ADR 0093 (Tier 0),
 * RUNBOOK-bridge-sells-titulos-backfill.md, agent financeiro-bridge-auditor.
 */
class ResyncFromCoreCommand extends Command
{
    protected $signature = 'financeiro:resync-from-core
        {--business= : ID do business (obrigatório — Tier 0 IRREVOGÁVEL ADR 0093)}
        {--ratio=1.5 : valor_total / core final_total acima do qual considera inflado (default 1.5)}
        {--since= : Data mínima YYYY-MM-DD (emissao do título) (opcional)}
        {--limit= : Máximo de títulos por rodada (opcional)}
        {--apply : Aplica a correção (default DRY-RUN seguro)}
        {--detail : Lista cada título processado}';

    protected $description = 'Re-sincroniza fin_titulos inflados (incidente num_uf) com o final_total corrigido do core. DRY-RUN por padrão.';

    public function handle(TituloAutoService $service): int
    {
        $businessId = (int) $this->option('business');
        if ($businessId <= 0) {
            $this->error('--business=ID obrigatório (Tier 0 IRREVOGÁVEL — ADR 0093)');

            return self::FAILURE;
        }

        $ratio = (float) ($this->option('ratio') ?: 1.5);
        if ($ratio < 1.0) {
            $this->error('--ratio deve ser >= 1.0 (razão valor_total / core).');

            return self::FAILURE;
        }

        $since = $this->option('since');
        if ($since !== null && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $since)) {
            $this->error('--since deve ser YYYY-MM-DD (ex: 2026-05-01)');

            return self::FAILURE;
        }

        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $apply = (bool) $this->option('apply');
        $detail = (bool) $this->option('detail');

        $this->info(($apply ? '' : '[DRY-RUN] ')."Resync fin_titulos ← core (business={$businessId}, ratio={$ratio}x)");
        $this->line('  Lembrete: rode `sells:final-total-audit --business='.$businessId.'` ANTES (conserta o core).');

        // Candidatos: título venda/compra cujo valor_total diverge (inflado) do
        // final_total corrigido do core. JOIN por origem_id + business_id (Tier 0).
        $query = DB::table('fin_titulos as ft')
            ->join('transactions as t', function ($join) {
                $join->on('t.id', '=', 'ft.origem_id')
                    ->on('t.business_id', '=', 'ft.business_id');
            })
            ->where('ft.business_id', $businessId)
            ->whereIn('ft.origem', ['venda', 'compra'])
            ->whereNull('ft.parcela_numero')
            ->whereNull('ft.deleted_at')
            ->where('t.final_total', '>', 0)
            ->whereRaw('ft.valor_total > t.final_total * ?', [$ratio])
            ->select(
                'ft.id',
                'ft.numero',
                'ft.origem',
                'ft.status',
                'ft.valor_total',
                'ft.valor_aberto',
                't.final_total as core_total',
            );

        if ($since) {
            $query->where('ft.emissao', '>=', $since);
        }

        $query->orderBy('ft.id', 'asc');

        if ($limit) {
            $query->limit($limit);
        }

        $candidatos = $query->get();
        $total = $candidatos->count();

        if ($total === 0) {
            $this->info('Nada a re-sincronizar — nenhum título inflado vs core.');

            return self::SUCCESS;
        }

        $this->warn(sprintf('%d título(s) inflado(s) detectado(s):', $total));

        $somaAntiga = 0.0;
        $somaNova = 0.0;
        $tableRows = [];

        foreach ($candidatos as $c) {
            $coreTotal = (float) $c->core_total;
            $valorTotal = (float) $c->valor_total;
            $garbage = $this->baixasLixo($businessId, (int) $c->id, $coreTotal, $ratio);
            $somaGarbage = (float) $garbage->sum('valor_baixa');

            $somaAntiga += $valorTotal;
            $somaNova += $coreTotal;

            if ($detail || ! $apply) {
                $tableRows[] = [
                    'id' => $c->id,
                    'numero' => $c->numero,
                    'origem' => $c->origem,
                    'status' => $c->status,
                    'valor_atual' => number_format($valorTotal, 2, ',', '.'),
                    'core' => number_format($coreTotal, 2, ',', '.'),
                    'baixas_lixo' => $garbage->count().' (R$ '.number_format($somaGarbage, 2, ',', '.').')',
                ];
            }
        }

        if (! $apply) {
            $this->table(
                ['id', 'numero', 'origem', 'status', 'valor_atual', 'core', 'baixas_lixo'],
                $tableRows,
            );
            $this->info('[DRY-RUN] valor_total somado: R$ '.number_format($somaAntiga, 2, ',', '.').' → R$ '.number_format($somaNova, 2, ',', '.'));
            $this->info('[DRY-RUN] Re-rode com --apply pra corrigir.');

            return self::SUCCESS;
        }

        $corrigidos = 0;
        $baixasEstornadas = 0;

        foreach ($candidatos as $c) {
            $coreTotal = (float) $c->core_total;

            DB::transaction(function () use ($service, $businessId, $c, $coreTotal, $ratio, &$corrigidos, &$baixasEstornadas, $detail) {
                /** @var Titulo|null $titulo */
                $titulo = Titulo::query()
                    ->withoutGlobalScope(BusinessScopeImpl::class)
                    ->where('business_id', $businessId)
                    ->where('id', (int) $c->id)
                    ->lockForUpdate()
                    ->first();

                if (! $titulo) {
                    return;
                }

                $valorAntigo = (float) $titulo->valor_total;

                // 1) Estorna baixas-lixo (append-only — TECH-0002, nunca delete).
                $garbage = $this->baixasLixo($businessId, (int) $titulo->id, $coreTotal, $ratio);
                foreach ($garbage as $b) {
                    TituloBaixa::query()
                        ->withoutGlobalScope(BusinessScopeImpl::class)
                        ->create([
                            'business_id' => $businessId,
                            'titulo_id' => (int) $titulo->id,
                            'conta_bancaria_id' => $b->conta_bancaria_id,
                            'valor_baixa' => -1 * (float) $b->valor_baixa,
                            'data_baixa' => now()->toDateString(),
                            'meio_pagamento' => $b->meio_pagamento,
                            'idempotency_key' => 'resync_estorno_'.$b->id,
                            'estorno_de_id' => (int) $b->id,
                            'observacoes' => 'Estorno resync: baixa-lixo do incidente num_uf (#2279/#2280)',
                            'created_by' => 1,
                        ]);
                    $baixasEstornadas++;
                }

                // 2) valor_total = core (+ trilha pra rollback).
                $metadata = $titulo->metadata ?? [];
                $metadata['resync_from_core'] = now()->toIso8601String();
                $metadata['valor_total_antigo'] = $valorAntigo;
                $titulo->metadata = $metadata;
                $titulo->valor_total = $coreTotal;
                $titulo->save();

                // 3) Recalcula valor_aberto + status (canônico; preserva cancelado).
                $service->recalcularTitulo($titulo);

                // 4) Trilha activity_log (paridade com sells:final-total-audit).
                DB::table('activity_log')->insert([
                    'log_name' => 'financeiro-resync-from-core',
                    'description' => 'valor_total re-sincronizado com final_total corrigido do core (incidente num_uf #2279/#2280)',
                    'subject_type' => Titulo::class,
                    'subject_id' => (int) $titulo->id,
                    'causer_type' => null,
                    'causer_id' => null,
                    'properties' => json_encode([
                        'business_id' => $businessId,
                        'attributes' => ['valor_total' => $coreTotal],
                        'old' => ['valor_total' => $valorAntigo],
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $corrigidos++;

                if ($detail) {
                    $this->line(sprintf(
                        '  ✓ %s (#%d) R$ %s → R$ %s',
                        $c->numero,
                        $c->id,
                        number_format($valorAntigo, 2, ',', '.'),
                        number_format($coreTotal, 2, ',', '.'),
                    ));
                }
            });
        }

        $this->info("✓ Resync concluído em business={$businessId}");
        $this->info("  Títulos corrigidos: {$corrigidos}");
        $this->info("  Baixas-lixo estornadas: {$baixasEstornadas}");

        return self::SUCCESS;
    }

    /**
     * Baixas-lixo de um título: ativas (não-estorno), ainda não estornadas, com
     * valor_baixa impossível (> core * ratio). Idempotente — exclui as que já
     * têm estorno apontando (re-run não re-estorna).
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function baixasLixo(int $businessId, int $tituloId, float $coreTotal, float $ratio): \Illuminate\Support\Collection
    {
        return DB::table('fin_titulo_baixas as b')
            ->where('b.business_id', $businessId)
            ->where('b.titulo_id', $tituloId)
            ->whereNull('b.estorno_de_id')
            ->whereRaw('b.valor_baixa > ?', [$coreTotal * $ratio])
            ->whereNotExists(function ($q) use ($businessId) {
                $q->select(DB::raw('1'))
                    ->from('fin_titulo_baixas as e')
                    ->whereColumn('e.estorno_de_id', 'b.id')
                    ->where('e.business_id', $businessId);
            })
            ->select('b.id', 'b.valor_baixa', 'b.conta_bancaria_id', 'b.meio_pagamento')
            ->get();
    }
}
