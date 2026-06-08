<?php

declare(strict_types=1);

namespace Modules\Jana\Services;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * BriefDiarioService — fundação do JANA Pro (US-COPI-201, ADR 0140).
 *
 * Agrega 5 fontes do business em snapshot imutável que alimenta:
 *   - BriefDiarioAgent (laravel/ai HasTools — ADR 0141) — gera narrativa markdown
 *   - BriefDiarioJob — schedule 8h BRT cron, envia WhatsApp+email
 *   - Page Inertia /copiloto/admin/jana-pro — preview admin
 *
 * Multi-tenant Tier 0 ([ADR 0093](../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) —
 * recebe `$businessId` explícito constructor, nunca usa session (Job
 * assíncrono não tem session user). Cada source filtra explícito.
 *
 * Cada fonte degrada graciosamente se tabela ausente / módulo
 * desinstalado / dados zerados — consumidor sempre recebe shape estável
 * com `null` em campos faltantes, NUNCA fabrica dados (anti-pattern §Anti
 * da skill `ticket-triage`).
 *
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 * @see memory/requisitos/Copiloto/JANA-PRO-PRODUCT-PLAN.md
 */
class BriefDiarioService
{
    public function __construct(
        private readonly int $businessId,
    ) {
    }

    /**
     * Snapshot completo (5 fontes + metadata). Estrutura JSON estável —
     * mudanças exigem bump version + new Pest test.
     */
    public function snapshot(): array
    {
        // D9.a Observability — span zero-cost quando OTel disabled (default).
        return OtelHelper::spanBiz('jana.brief_diario.snapshot', function () {
            return [
                'generated_at' => now()->toIso8601String(),
                'business_id' => $this->businessId,
                'version' => '0.1.0',
                'sources' => [
                    'vendas' => $this->vendasPeriodo(),
                    'inadimplencia' => $this->inadimplenciaBuckets(),
                    'tickets' => $this->ticketsPriorizados(),
                    'nfe' => $this->nfeStatus(),
                    'oportunidades' => $this->oportunidadesUpsell(),
                ],
            ];
        }, ['business_id' => $this->businessId, 'version' => '0.1.0']);
    }

    /**
     * Source 1: VENDAS últimos 7d vs 7d anteriores + mês corrente.
     *
     * Returns:
     *  - hoje, ontem, semana_atual, semana_anterior, delta_pct
     *  - mes_corrente, mes_anterior, delta_mes_pct
     *  - ticket_medio_atual, ticket_medio_anterior
     */
    public function vendasPeriodo(): array
    {
        try {
            if (! Schema::hasTable('transactions')) {
                return $this->emptySource('table_missing');
            }

            $hoje = $this->somaVendas(now()->startOfDay(), now()->endOfDay());
            $ontem = $this->somaVendas(
                now()->subDay()->startOfDay(),
                now()->subDay()->endOfDay()
            );
            $semanaAtual = $this->somaVendas(
                now()->subDays(7)->startOfDay(),
                now()->endOfDay()
            );
            $semanaAnt = $this->somaVendas(
                now()->subDays(14)->startOfDay(),
                now()->subDays(7)->endOfDay()
            );
            $mesAtual = $this->somaVendas(
                now()->startOfMonth(),
                now()->endOfDay()
            );
            $mesAnt = $this->somaVendas(
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth()
            );

            $deltaSem = $semanaAnt['total'] > 0
                ? round(100 * ($semanaAtual['total'] - $semanaAnt['total']) / $semanaAnt['total'], 1)
                : null;
            $deltaMes = $mesAnt['total'] > 0
                ? round(100 * ($mesAtual['total'] - $mesAnt['total']) / $mesAnt['total'], 1)
                : null;

            // PROJEÇÃO FECHAMENTO MÊS (US-COPI-202c — fix Wagner 2026-05-12)
            // Problema do delta_mes_pct cru: compara mês incompleto (ex: dia 12)
            // com mês completo anterior — gera falso alarme "-26,8%". A projeção
            // normaliza pelo ritmo diário decorrido, dando comparação justa.
            $diasDecorridos = (int) now()->day;
            $diasNoMes = (int) now()->daysInMonth;
            $diasRestantes = max(0, $diasNoMes - $diasDecorridos);
            $ritmoDiario = $diasDecorridos > 0
                ? round($mesAtual['total'] / $diasDecorridos, 2)
                : 0.0;
            $projecaoFechamento = round($ritmoDiario * $diasNoMes, 2);
            $deltaMesProjetadoPct = $mesAnt['total'] > 0 && $projecaoFechamento > 0
                ? round(100 * ($projecaoFechamento - $mesAnt['total']) / $mesAnt['total'], 1)
                : null;

            return [
                'ok' => true,
                'hoje' => $hoje,
                'ontem' => $ontem,
                'semana_atual' => $semanaAtual,
                'semana_anterior' => $semanaAnt,
                'delta_semana_pct' => $deltaSem,
                'mes_corrente' => $mesAtual,
                'mes_anterior' => $mesAnt,
                // delta_mes_pct mantido por BC (consumers podem ter dependência),
                // mas brief executivo DEVE usar projecao_fechamento + delta_projetado.
                'delta_mes_pct' => $deltaMes,
                'dias_decorridos_mes' => $diasDecorridos,
                'dias_restantes_mes' => $diasRestantes,
                'ritmo_diario' => $ritmoDiario,
                'projecao_fechamento_mes' => $projecaoFechamento,
                'delta_projetado_pct' => $deltaMesProjetadoPct,
            ];
        } catch (Throwable $e) {
            return $this->errorSource($e);
        }
    }

    /**
     * Auxiliar: soma vendas (count + total + ticket_medio) num período.
     * Type='sell', status NOT IN draft.
     */
    private function somaVendas(\Carbon\CarbonInterface $de, \Carbon\CarbonInterface $ate): array
    {
        $row = DB::table('transactions')
            ->where('business_id', $this->businessId)
            ->where('type', 'sell')
            ->whereNotIn('status', ['draft'])
            ->whereBetween('transaction_date', [$de, $ate])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(final_total), 0) as total')
            ->first();

        return [
            'count' => (int) ($row->count ?? 0),
            'total' => (float) ($row->total ?? 0),
            'ticket_medio' => $row->count > 0 ? round($row->total / $row->count, 2) : 0.0,
        ];
    }

    /**
     * Source 2: INADIMPLÊNCIA por buckets (0-30d / 30-60d / 60-90d / >90d).
     *
     * Returns: buckets + total_devido + clientes_inadimplentes_count + top_5
     */
    public function inadimplenciaBuckets(): array
    {
        try {
            if (! Schema::hasTable('transactions')) {
                return $this->emptySource('table_missing');
            }

            $buckets = [
                'em_dia' => ['days_min' => null, 'days_max' => 0, 'count' => 0, 'total' => 0.0],
                '0_30' => ['days_min' => 1, 'days_max' => 30, 'count' => 0, 'total' => 0.0],
                '30_60' => ['days_min' => 31, 'days_max' => 60, 'count' => 0, 'total' => 0.0],
                '60_90' => ['days_min' => 61, 'days_max' => 90, 'count' => 0, 'total' => 0.0],
                'mais_90' => ['days_min' => 91, 'days_max' => null, 'count' => 0, 'total' => 0.0],
            ];

            // Itera transactions devendo (payment_status due/partial) por contact
            $rows = DB::table('transactions')
                ->where('business_id', $this->businessId)
                ->where('type', 'sell')
                ->whereIn('payment_status', ['due', 'partial'])
                ->whereNotNull('due_date')
                ->select('id', 'contact_id', 'final_total', 'due_date')
                ->get();

            $totalDevido = 0.0;
            $clientesIds = [];

            foreach ($rows as $row) {
                $pago = (float) (DB::table('transaction_payments')
                    ->where('transaction_id', $row->id)
                    ->sum('amount') ?? 0);
                $aberto = max(0, ((float) $row->final_total) - $pago);
                if ($aberto <= 0.01) {
                    continue;
                }

                $diasAtraso = (int) max(0, now()->diffInDays(\Carbon\Carbon::parse($row->due_date), false) * -1);

                $key = match (true) {
                    $diasAtraso <= 0 => 'em_dia',
                    $diasAtraso <= 30 => '0_30',
                    $diasAtraso <= 60 => '30_60',
                    $diasAtraso <= 90 => '60_90',
                    default => 'mais_90',
                };
                $buckets[$key]['count']++;
                $buckets[$key]['total'] += $aberto;
                if ($row->contact_id) {
                    $clientesIds[$row->contact_id] = ($clientesIds[$row->contact_id] ?? 0) + $aberto;
                }
                if ($diasAtraso > 0) {
                    $totalDevido += $aberto;
                }
            }

            // Top 5 clientes inadimplentes
            arsort($clientesIds);
            $top5Ids = array_slice(array_keys($clientesIds), 0, 5, true);
            $top5 = $top5Ids
                ? DB::table('contacts')
                    ->whereIn('id', $top5Ids)
                    ->where('business_id', $this->businessId)
                    ->select('id', 'name')
                    ->get()
                    ->map(fn ($c) => [
                        'id' => (int) $c->id,
                        'name' => $c->name,
                        'devido' => round($clientesIds[$c->id], 2),
                    ])
                    ->sortByDesc('devido')
                    ->values()
                    ->all()
                : [];

            return [
                'ok' => true,
                'buckets' => array_map(fn ($b) => [
                    'count' => $b['count'],
                    'total' => round($b['total'], 2),
                ], $buckets),
                'total_devido_atrasado' => round($totalDevido, 2),
                'clientes_inadimplentes_count' => count($clientesIds),
                'top_5_devedores' => $top5,
            ];
        } catch (Throwable $e) {
            return $this->errorSource($e);
        }
    }

    /**
     * Source 3: TICKETS priorizados — top 5 conversations com unread > 0 OU
     * sentimento detectado raivoso/frustrado (heurística simplificada da
     * skill ticket-triage v0.1.0).
     */
    public function ticketsPriorizados(): array
    {
        try {
            if (! Schema::hasTable('conversations')) {
                return $this->emptySource('table_missing');
            }

            $convsPriority = DB::table('conversations')
                ->where('business_id', $this->businessId)
                ->where(function ($q) {
                    $q->where('unread_count', '>', 0)
                        ->orWhere('status', 'awaiting_human');
                })
                ->orderByDesc('unread_count')
                ->orderByDesc('last_message_at')
                ->limit(10)
                ->get();

            $tickets = [];
            $palavrasCriticas = ['cancelar', 'procon', 'advogado', 'socorro', 'urgente', 'parou'];

            foreach ($convsPriority as $c) {
                $ultimaMsg = DB::table('messages')
                    ->where('conversation_id', $c->id)
                    ->whereNotNull('body')
                    ->where('body', '!=', '')
                    ->orderByDesc('created_at')
                    ->select('body', 'direction', 'created_at')
                    ->first();

                $textoLower = mb_strtolower((string) ($ultimaMsg->body ?? ''));
                $temPalavraCritica = false;
                foreach ($palavrasCriticas as $p) {
                    if (mb_stripos($textoLower, $p) !== false) {
                        $temPalavraCritica = true;
                        break;
                    }
                }

                $prio = match (true) {
                    $temPalavraCritica => 'P1',
                    $c->unread_count >= 5 => 'P2',
                    $c->unread_count >= 2 => 'P3',
                    default => 'P4',
                };

                $tickets[] = [
                    'conv_id' => (int) $c->id,
                    'contact_name' => $c->contact_name ?? $c->customer_external_id,
                    'unread' => (int) $c->unread_count,
                    'status' => $c->status,
                    'is_blocked' => (bool) ($c->is_blocked ?? false),
                    'ultima_msg' => $ultimaMsg ? mb_substr((string) $ultimaMsg->body, 0, 100) : null,
                    'prioridade' => $prio,
                    'tem_palavra_critica' => $temPalavraCritica,
                ];
            }

            // Sort P1>P2>P3>P4 e limita 5
            $rank = ['P1' => 1, 'P2' => 2, 'P3' => 3, 'P4' => 4];
            usort($tickets, fn ($a, $b) => $rank[$a['prioridade']] <=> $rank[$b['prioridade']]);
            $tickets = array_slice($tickets, 0, 5);

            return [
                'ok' => true,
                'top_5' => $tickets,
                'total_unread_business' => (int) DB::table('conversations')
                    ->where('business_id', $this->businessId)
                    ->sum('unread_count'),
            ];
        } catch (Throwable $e) {
            return $this->errorSource($e);
        }
    }

    /**
     * Source 4: NFe status — emissões 30d + rejeições por cstat + cert
     * vencimento se Modules/NfeBrasil instalado.
     */
    public function nfeStatus(): array
    {
        try {
            if (! Schema::hasTable('nfe_emissoes')) {
                return $this->emptySource('module_not_installed', ['module' => 'NfeBrasil']);
            }

            $base = DB::table('nfe_emissoes')
                ->where('business_id', $this->businessId)
                ->where('created_at', '>=', now()->subDays(30));

            $totalEmitidas = (clone $base)->where('cstat', 100)->count();
            $totalRejeitadas = (clone $base)->where('cstat', '!=', 100)->whereNotNull('cstat')->count();
            $totalPendentes = (clone $base)->whereNull('cstat')->count();

            $rejeicoesPorCstat = DB::table('nfe_emissoes')
                ->where('business_id', $this->businessId)
                ->where('created_at', '>=', now()->subDays(30))
                ->where('cstat', '!=', 100)
                ->whereNotNull('cstat')
                ->selectRaw('cstat, COUNT(*) as count')
                ->groupBy('cstat')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(fn ($r) => ['cstat' => (int) $r->cstat, 'count' => (int) $r->count])
                ->all();

            return [
                'ok' => true,
                'emitidas_30d' => $totalEmitidas,
                'rejeitadas_30d' => $totalRejeitadas,
                'pendentes_30d' => $totalPendentes,
                'taxa_rejeicao_pct' => ($totalEmitidas + $totalRejeitadas) > 0
                    ? round(100 * $totalRejeitadas / ($totalEmitidas + $totalRejeitadas), 1)
                    : 0.0,
                'top_5_cstats_rejeicao' => $rejeicoesPorCstat,
            ];
        } catch (Throwable $e) {
            return $this->errorSource($e);
        }
    }

    /**
     * Source 5: OPORTUNIDADES de upsell — combo (cliente comprou >3x mesmo
     * produto, sugerir kit) + reativação (inativos >60d com LTV > média).
     */
    public function oportunidadesUpsell(): array
    {
        try {
            if (! Schema::hasTable('transactions') || ! Schema::hasTable('transaction_sell_lines')) {
                return $this->emptySource('table_missing');
            }

            // Combo: top 5 (contact, product) com count >3 em 90d.
            // US-COPI-202c (fix Wagner 2026-05-12): EXCLUI walk-in customers
            // (UltimatePOS marca "Cliente Balcão"/"Cliente Padrão" com
            // contacts.is_default=1). Sem o filtro, agregação de vendas sem
            // cadastro vira falso combo (várias clientes comprando produto X
            // viram "cliente walk-in comprou X 6 vezes"). Anti-pattern: tratar
            // produto best-seller como combo individual.
            $combo = DB::table('transaction_sell_lines as tsl')
                ->join('transactions as t', 't.id', '=', 'tsl.transaction_id')
                ->join('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $this->businessId)
                ->where('t.type', 'sell')
                ->whereNotIn('t.status', ['draft'])
                ->where('t.transaction_date', '>=', now()->subDays(90))
                ->whereNotNull('t.contact_id')
                ->where(function ($q) {
                    // Schema UltimatePOS: contacts.is_default=1 marca walk-in.
                    // Em ambientes de teste a coluna pode não existir — usar
                    // raw IS NULL OR != 1 pra tolerar.
                    $q->whereRaw('(c.is_default IS NULL OR c.is_default <> 1)');
                })
                ->selectRaw('t.contact_id, tsl.product_id, COUNT(*) as repetes')
                ->groupBy('t.contact_id', 'tsl.product_id')
                ->having('repetes', '>=', 3)
                ->orderByDesc('repetes')
                ->limit(5)
                ->get();

            $comboEnriched = $combo->map(function ($r) {
                $contact = DB::table('contacts')
                    ->where('id', $r->contact_id)
                    ->where('business_id', $this->businessId)
                    ->first(['id', 'name']);
                $product = DB::table('products')
                    ->where('id', $r->product_id)
                    ->where('business_id', $this->businessId)
                    ->first(['id', 'name']);
                return [
                    'contact_id' => (int) $r->contact_id,
                    'contact_name' => $contact->name ?? null,
                    'product_id' => (int) $r->product_id,
                    'product_name' => $product->name ?? null,
                    'compras_90d' => (int) $r->repetes,
                ];
            })->filter(fn ($r) => $r['contact_name'] && $r['product_name'])->values()->all();

            // Reativação: contacts com última compra >60d E LTV > 1k.
            // US-COPI-202c (fix Wagner 2026-05-12): EXCLUI walk-in (is_default=1)
            // — Cliente Balcão acumula LTV gigante de várias clientes anônimas
            // e contamina ranking.
            $reativacao = DB::table('transactions as t')
                ->join('contacts as c', 'c.id', '=', 't.contact_id')
                ->where('t.business_id', $this->businessId)
                ->where('t.type', 'sell')
                ->whereNotIn('t.status', ['draft'])
                ->whereNotNull('t.contact_id')
                ->whereRaw('(c.is_default IS NULL OR c.is_default <> 1)')
                ->selectRaw('t.contact_id, SUM(t.final_total) as ltv, MAX(t.transaction_date) as ultima_compra')
                ->groupBy('t.contact_id')
                ->havingRaw('SUM(t.final_total) > ?', [1000])
                ->havingRaw('MAX(t.transaction_date) < ?', [now()->subDays(60)])
                ->orderByDesc('ltv')
                ->limit(5)
                ->get();

            $reativacaoEnriched = $reativacao->map(function ($r) {
                $contact = DB::table('contacts')
                    ->where('id', $r->contact_id)
                    ->where('business_id', $this->businessId)
                    ->first(['id', 'name']);
                return [
                    'contact_id' => (int) $r->contact_id,
                    'contact_name' => $contact->name ?? null,
                    'ltv' => round((float) $r->ltv, 2),
                    'ultima_compra' => $r->ultima_compra,
                    'dias_sem_comprar' => (int) max(0, now()->diffInDays(\Carbon\Carbon::parse($r->ultima_compra), false) * -1),
                ];
            })->filter(fn ($r) => $r['contact_name'])->values()->all();

            return [
                'ok' => true,
                'combo_candidatos' => $comboEnriched,
                'reativacao_candidatos' => $reativacaoEnriched,
            ];
        } catch (Throwable $e) {
            return $this->errorSource($e);
        }
    }

    /**
     * Shape estável quando source está vazia/módulo desinstalado.
     */
    private function emptySource(string $reason, array $extra = []): array
    {
        return array_merge([
            'ok' => false,
            'reason' => $reason,
            'data' => null,
        ], $extra);
    }

    /**
     * Shape estável quando source lança exceção. NÃO vaza PII LGPD do erro.
     */
    private function errorSource(Throwable $e): array
    {
        return [
            'ok' => false,
            'reason' => 'exception',
            'error_class' => get_class($e),
            // Apenas primeiras 200 chars da msg — anti-PII LGPD
            'error_message' => mb_substr($e->getMessage(), 0, 200),
        ];
    }
}
