<?php

namespace Modules\Manufacturing\Services;

use App\Transaction;
use App\User;
use App\Util\OtelHelper;
use App\Variation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Support\Privacy\PiiRedactor;
use Modules\Manufacturing\Entities\MfgRecipe;

/**
 * ProductionService — orquestração thin de queries de produção (Manufacturing).
 *
 * Centraliza leituras de `transactions` type=production_purchase/production_sell
 * com escopo multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093). Substitui queries inline
 * em Controllers conforme migração MWART (Blade -> Inertia/React) avança.
 *
 * Wave J — Manufacturing boost 59 -> meta 70 (Capterra D4.a 2/6 -> 3/6).
 *
 * Wave 14 D7.a (LGPD) — método `logProductionEvent()` aplica PiiRedactor
 * em strings antes de logar (defesa em profundidade: ref_no/lot_number/notas
 * podem conter PII de cliente em casos extremos).
 */
class ProductionService
{
    public function __construct(
        private ?PiiRedactor $piiRedactor = null,
        private ?RecipeBomService $bomService = null,
    ) {
        // Resolve do container se não injetado (Manufacturing legacy usa instanciação direta)
        $this->piiRedactor = $piiRedactor ?? app(PiiRedactor::class);
        $this->bomService = $bomService ?? app(RecipeBomService::class);
    }

    /**
     * Log estruturado de eventos de produção com PII redactada (D7.a LGPD).
     *
     * Use SEMPRE pra logar contexto operacional de produção em vez de Log::xxx
     * direto. Redaciona CPF/CNPJ/email/telefone/CEP no contexto antes de gravar.
     *
     * @param  string  $level  emergency|error|warning|info
     * @param  string  $message  Mensagem livre (será redactada)
     * @param  array<string,mixed>  $context  Contexto adicional (strings serão redactadas)
     */
    public function logProductionEvent(string $level, string $message, array $context = []): void
    {
        $safeMessage = $this->piiRedactor->redact($message);
        $safeContext = $this->piiRedactor->redactArray($context);

        Log::log($level, '[manufacturing.production] '.$safeMessage, $safeContext);
    }

    /**
     * Lista produções (production_purchase) do business com filtros opcionais.
     *
     * US-MANU-004 — o eager-load de `purchase_lines` alimenta as colunas Produto/Qtd/Custo
     * unit. do §4.5. `created_by` entra no select porque a coluna Produto mostra "quem
     * lançou" na segunda linha. O enriquecimento (nome do produto, nº de ingredientes, nome
     * do usuário) é feito em LOTE por {@see enrichProductionRows()} — nunca por linha.
     *
     * @param  int  $businessId  Tier 0 — NUNCA omitir, NUNCA usar session() em Job.
     * @param  array{location_id?: int|null, start_date?: string|null, end_date?: string|null, is_final?: bool} $filters
     */
    public function listProductions(int $businessId, array $filters = [], int $perPage = 25): Collection
    {
        return OtelHelper::spanBiz('manufacturing.production.list', function () use ($businessId, $filters, $perPage) {
            $query = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->with([
                    'purchase_lines' => function ($q) {
                        $q->select('id', 'transaction_id', 'variation_id', 'quantity');
                    },
                    // Eager-load do local: sem isto, `optional($ordem->location)->name` no
                    // enriquecimento dispara UMA query POR LINHA (N+1 que existia desde a
                    // Wave J, quando o map vivia no Controller). UC-OP-03 trava isso.
                    'location:id,name',
                ])
                ->select([
                    'id',
                    'ref_no',
                    'transaction_date',
                    'location_id',
                    'final_total',
                    'mfg_is_final',
                    'mfg_wasted_units',
                    'mfg_production_cost',
                    'created_by',
                    'status',
                ]);

            if (! empty($filters['location_id'])) {
                $query->where('location_id', $filters['location_id']);
            }

            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $query->whereDate('transaction_date', '>=', $filters['start_date'])
                    ->whereDate('transaction_date', '<=', $filters['end_date']);
            }

            if (! empty($filters['is_final'])) {
                $query->where('mfg_is_final', 1);
            }

            return $query->orderByDesc('transaction_date')
                ->limit($perPage)
                ->get();
        }, [
            'per_page' => $perPage,
            'has_location_filter' => ! empty($filters['location_id']),
            'is_final' => ! empty($filters['is_final']),
        ]);
    }

    /**
     * US-MANU-004 — enriquece as linhas de `listProductions()` com o que as 8 colunas do
     * §4.5 pedem: nome do produto, nº de ingredientes da receita, quem lançou, quantidade
     * produzida e custo unitário.
     *
     * **Em LOTE, nunca por linha**: 3 queries no total (produtos, receitas, usuários),
     * independentemente de quantas ordens vierem.
     *
     * **O custo NÃO é recalculado aqui** (diferente do Relatório, US-MANU-002): a coluna
     * mostra `transactions.final_total`, o valor GRAVADO na criação da ordem. Para ordem
     * finalizada, ele é o custo daquela data — é o que o sufixo `fix` marca. Recalcular
     * aqui seria uma SEGUNDA fórmula de custo na base ({@see RUNBOOK-producao.md §1}).
     *
     * Tier 0 ({@see ADR 0093}): as ordens já vêm scoped por `business_id`; a cadeia até o
     * produto revalida com `products.business_id` (defesa em profundidade).
     *
     * @param  Collection<int, Transaction>  $ordens  saída de listProductions()
     * @return array<int, array<string, mixed>>
     */
    public function enrichProductionRows(Collection $ordens, int $businessId): array
    {
        return OtelHelper::spanBiz('manufacturing.production.enrich_rows', function () use ($ordens, $businessId) {
            $variationIds = $ordens->pluck('purchase_lines')->flatten()->pluck('variation_id')->filter()->unique()->values();
            $userIds = $ordens->pluck('created_by')->filter()->unique()->values();

            // (1) nome do produto + unidade, pela cadeia de tenant.
            $produtos = $variationIds->isEmpty() ? collect() : Variation::query()
                ->join('products as p', 'variations.product_id', '=', 'p.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->whereIn('variations.id', $variationIds)
                ->where('p.business_id', $businessId) // Tier 0 — defesa em profundidade
                ->select('variations.id', 'p.name as product_name', 'u.short_name as unit_name')
                ->get()
                ->keyBy('id');

            // (2) nº de ingredientes por variação produzida (0 quando não há receita).
            $ingredientes = $variationIds->isEmpty() ? collect() : MfgRecipe::query()
                ->leftJoin('mfg_recipe_ingredients as i', 'i.mfg_recipe_id', '=', 'mfg_recipes.id')
                ->whereIn('mfg_recipes.variation_id', $variationIds)
                ->groupBy('mfg_recipes.variation_id')
                ->select('mfg_recipes.variation_id', DB::raw('COUNT(i.id) as total'))
                ->pluck('total', 'variation_id');

            // (3) quem lançou.
            $usuarios = $userIds->isEmpty() ? collect() : User::query()
                ->whereIn('id', $userIds)
                ->select('id', 'surname', 'first_name', 'last_name')
                ->get()
                ->keyBy('id');

            return $ordens->map(function (Transaction $ordem) use ($produtos, $ingredientes, $usuarios) {
                // getRelation() em vez da propriedade mágica: a relação vem eager-loaded do
                // listProductions(), e o acesso explícito não depende de análise de magic
                // property — que o Larastan não resolve nesta Model (mesmo motivo, e mesmo
                // padrão, do RecipeBomService::presentRecipe).
                $linha = $ordem->getRelation('purchase_lines')->first();
                $variationId = $linha?->variation_id;
                $produto = $variationId ? $produtos->get($variationId) : null;
                $usuario = $ordem->created_by ? $usuarios->get($ordem->created_by) : null;

                $quantidade = (float) ($linha?->quantity ?? 0);
                $total = (float) $ordem->final_total;

                return [
                    'id' => (int) $ordem->id,
                    'ref_no' => $ordem->ref_no,
                    // Carbon::parse, NÃO `?->format()`: `transaction_date` não está em
                    // `$casts` nem em `$dates` no App\Transaction — Eloquent devolve STRING,
                    // e `?->` só guarda null, não tipo. O próprio model usa Carbon::parse
                    // nele (Transaction.php:333,349). PHPStan flagrou: 'Cannot call method
                    // format() on string' — era quebra de runtime esperando página com linha.
                    'transaction_date' => $ordem->transaction_date
                        ? Carbon::parse($ordem->transaction_date)->format('d/m/Y')
                        : null,
                    // `relationLoaded` + `getRelation`, não a propriedade mágica: o Larastan
                    // não resolve `$ordem->location` (acusou property.notFound). Mesmo padrão
                    // do `RecipeBomService::presentRecipe` com `sub_unit`. O guard também
                    // protege quem chamar este método sem o eager-load do `listProductions`.
                    'location_name' => $ordem->relationLoaded('location')
                        ? optional($ordem->getRelation('location'))->name
                        : null,
                    'final_total' => $total,
                    // getAttribute: coluna real da tabela, mas o Larastan não a conhece no
                    // model (property.notFound). Acesso explícito, mesma linha das outras.
                    'mfg_is_final' => (int) $ordem->getAttribute('mfg_is_final'),
                    'produto' => $produto?->getAttribute('product_name') ?: '—',
                    'unidade' => $produto?->getAttribute('unit_name') ?: '',
                    'n_ingredientes' => (int) ($variationId ? ($ingredientes[$variationId] ?? 0) : 0),
                    'criado_por' => $usuario ? trim("{$usuario->surname} {$usuario->first_name} {$usuario->last_name}") : '',
                    'quantidade' => $quantidade,
                    // Guard de divisão por zero — quantidade 0 devolve 0.0, nunca INF/NaN.
                    'custo_unitario' => $quantidade > 0 ? $total / $quantidade : 0.0,
                ];
            })->values()->all();
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Totais agregados (contagem + valor + finalizadas) — usado no header da Index.
     */
    public function summary(int $businessId): array
    {
        return OtelHelper::spanBiz('manufacturing.production.summary', function () use ($businessId) {
            $base = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase');

            return [
                'total_count' => (clone $base)->count(),
                'final_count' => (clone $base)->where('mfg_is_final', 1)->count(),
                'pending_count' => (clone $base)->where('mfg_is_final', 0)->count(),
                'total_value' => (clone $base)->sum('final_total'),
            ];
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Contagem do MÊS CORRENTE — alimenta o KPI "Produção do mês" da tela de Fabricação
     * (§4.2 do handoff: "Conta ordens finalizadas; sublinha nº de rascunhos").
     *
     * Existe separado de `summary()` de propósito: aquele é all-time, e servir all-time sob
     * um rótulo que diz "do mês" seria número certo na etiqueta errada.
     *
     * Multi-tenant Tier 0 ({@see ADR 0093}) — caller injeta business_id.
     *
     * @return array{final_count:int, draft_count:int}
     */
    public function monthSummary(int $businessId): array
    {
        return OtelHelper::spanBiz('manufacturing.production.month_summary', function () use ($businessId) {
            $base = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->whereBetween('transaction_date', [
                    now()->startOfMonth()->startOfDay(),
                    now()->endOfMonth()->endOfDay(),
                ]);

            return [
                'final_count' => (clone $base)->where('mfg_is_final', 1)->count(),
                'draft_count' => (clone $base)->where('mfg_is_final', 0)->count(),
            ];
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Custo médio de produção — usado em widgets dashboard Manufacturing.
     *
     * Wave 27 D9.a — span observa agregada cara (média ponderada sobre todas as
     * production_purchase do business). Zero-cost OTel quando otel.enabled=false.
     *
     * Multi-tenant Tier 0 IRREVOGÁVEIS (ADR 0093) — caller injeta business_id.
     *
     * @param  int  $businessId  Tenant — NUNCA omitir, NUNCA usar session() em Job.
     * @return float Custo médio (zero se não houver produções).
     */
    public function averageProductionCost(int $businessId): float
    {
        return OtelHelper::spanBiz('manufacturing.production.average_cost', function () use ($businessId) {
            $value = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->where('mfg_is_final', 1)
                ->avg('mfg_production_cost');

            return (float) ($value ?? 0.0);
        }, [
            'module' => 'Manufacturing',
        ]);
    }

    /**
     * Wave 26 D9 — KPIs por janela temporal (últimos N dias) pra dashboard.
     *
     * Spans observáveis pra hot-path de dashboards reagentes (Producao card).
     * Multi-tenant Tier 0: caller injeta biz_id, NUNCA session() em Job.
     *
     * @param  int  $businessId  Tenant — explícito sempre
     * @param  int  $windowDays  Janela em dias (default 30)
     * @return array{count:int, value:float, avg_value:float}
     */
    public function windowKpis(int $businessId, int $windowDays = 30): array
    {
        return OtelHelper::spanBiz('manufacturing.production.window_kpis', function () use ($businessId, $windowDays) {
            $since = now()->subDays($windowDays)->toDateTimeString();

            $base = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->where('transaction_date', '>=', $since);

            $count = (clone $base)->count();
            $value = (float) (clone $base)->sum('final_total');

            return [
                'count'     => $count,
                'value'     => $value,
                'avg_value' => $count > 0 ? round($value / $count, 2) : 0.0,
            ];
        }, [
            'module'      => 'Manufacturing',
            'window_days' => $windowDays,
        ]);
    }

    /**
     * Relatório de produção do período, agrupado por produto — US-MANU-002 (SPEC.md).
     *
     * Custo de cada ordem = `RecipeBomService::calculateUnitCost($recipe) × quantidade
     * produzida NA ORDEM` (não a `total_quantity` canônica da receita). REUSA o método já
     * testado (UC-RECIPE-03/04) em vez de reimplementar a fórmula — prova algébrica de que
     * isso reproduz `consumoOP()` do protótipo pras 3 fórmulas de `production_cost_type` em
     * `RUNBOOK-report.md §1` (proibicoes.md §5 2026-06-05 "não deriva do código", aqui deriva
     * de um método já provado, não de leitura nova de código).
     *
     * Não há custo CONGELADO ainda — `transactions` não tem `custoSnap` (US-MANU-007 §9 é
     * quem o introduz). Todo custo aqui é live, igual à tela de Receitas.
     *
     * Tier 0 ({@see ADR 0093}): `transactions.business_id` já é direto (core scoped); a
     * cadeia até a recipe some defesa em profundidade via `products.business_id` no JOIN.
     *
     * @param  int  $businessId  Tier 0 — nunca session() em Job.
     * @param  array{start_date?:string|null,end_date?:string|null,is_final?:bool}  $filters
     * @return array{linhas: array<int, array{recipe_id:int,nome:string,unidade:string,ordens:int,quantidade:float,custo_total:float,custo_medio:float,percentual:float}>, total: float}
     */
    public function reportByProduct(int $businessId, array $filters = []): array
    {
        return OtelHelper::spanBiz('manufacturing.production.report_by_product', function () use ($businessId, $filters) {
            $query = Transaction::query()
                ->where('business_id', $businessId)
                ->where('type', 'production_purchase')
                ->with(['purchase_lines' => function ($q) {
                    $q->select('id', 'transaction_id', 'variation_id', 'quantity');
                }]);

            if (! empty($filters['is_final'])) {
                $query->where('mfg_is_final', 1);
            }

            if (! empty($filters['start_date']) && ! empty($filters['end_date'])) {
                $query->whereDate('transaction_date', '>=', $filters['start_date'])
                    ->whereDate('transaction_date', '<=', $filters['end_date']);
            }

            $orders = $query->get();

            $variationIds = $orders->pluck('purchase_lines')
                ->flatten()
                ->pluck('variation_id')
                ->filter()
                ->unique()
                ->values();

            $recipes = MfgRecipe::query()
                ->join('variations as v', 'mfg_recipes.variation_id', '=', 'v.id')
                ->join('products as p', 'v.product_id', '=', 'p.id')
                ->leftJoin('units as u', 'p.unit_id', '=', 'u.id')
                ->whereIn('mfg_recipes.variation_id', $variationIds)
                ->where('p.business_id', $businessId) // Tier 0 — defesa em profundidade
                ->with(['ingredients', 'ingredients.variation', 'ingredients.sub_unit'])
                ->select('mfg_recipes.*', 'p.name as product_name', 'u.short_name as unit_name')
                ->get()
                ->keyBy('variation_id');

            $acc = [];

            foreach ($orders as $order) {
                foreach ($order->purchase_lines as $line) {
                    $recipe = $recipes->get($line->variation_id);
                    if (! $recipe) {
                        continue; // Ordem cujo item produzido não tem receita (dado legado) — não entra no relatório.
                    }

                    $qty = (float) $line->quantity;
                    $custo = $this->bomService->calculateUnitCost($recipe) * $qty;

                    // `??=`, não `if (!isset)` — este é acumulador (1ª ocorrência da chave
                    // inicia o balde), não fallback silencioso de input ausente. PHPStan
                    // custom rule NoSilentFallbackRule (ADR 0212) não detecta `??=` de
                    // propósito (ver docblock da rule); mesmo idioma de
                    // RecipeBomService::presentRecipe() (`$grupos[$nomeGrupo] ??= [...]`).
                    $key = (string) $recipe->variation_id;
                    $acc[$key] ??= [
                        'recipe_id'   => (int) $recipe->id,
                        'nome'        => $recipe->getAttribute('product_name') ?: '—',
                        'unidade'     => $recipe->getAttribute('unit_name') ?: '',
                        'ordens'      => 0,
                        'quantidade'  => 0.0,
                        'custo_total' => 0.0,
                    ];

                    $acc[$key]['ordens']++;
                    $acc[$key]['quantidade'] += $qty;
                    $acc[$key]['custo_total'] += $custo;
                }
            }

            // (float) explícito: `array_sum([])` devolve int(0), não float — e o contrato
            // declarado no @return desta função é `total: float`. Sem o cast, um período SEM
            // produção devolvia int e o tipo mentia (pego pela lane de CI em 2026-09-03,
            // UC-REPORT-02: "Failed asserting that 0 is identical to 0.0").
            $total = (float) array_sum(array_column($acc, 'custo_total'));

            // §7.3 — divisão por zero devolve 0, nunca NaN/Infinity (mesma defesa da tela de Receitas).
            $linhas = array_map(function ($l) use ($total) {
                $l['custo_medio'] = $l['quantidade'] > 0 ? $l['custo_total'] / $l['quantidade'] : 0.0;
                $l['percentual']  = $total > 0 ? $l['custo_total'] / $total * 100 : 0.0;

                return $l;
            }, array_values($acc));

            usort($linhas, fn ($a, $b) => $b['custo_total'] <=> $a['custo_total']);

            return ['linhas' => $linhas, 'total' => $total];
        }, [
            'module' => 'Manufacturing',
        ]);
    }
}
