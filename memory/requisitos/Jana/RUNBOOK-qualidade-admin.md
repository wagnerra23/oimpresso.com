---
slug: jana-runbook-qualidade-admin
title: "Jana — Runbook da tela Qualidade IA (Admin)"
type: runbook
module: Jana
status: active
date: 2026-05-09
---

# RUNBOOK — Qualidade IA Admin (Jana)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0036](../../decisions/0036-replanejamento-meilisearch-first.md), [ADR 0049](../../decisions/0049-camadas-memoria-agente-fase-por-fase.md), [ADR 0050](../../decisions/0050-metricas-obrigatorias-memoria-table.md), [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
> **Status:** implementada V1 (visualização) — `/copiloto/admin/qualidade`. V2 (HITL + alerts) no Cycle 02.
> **Permissão:** `copiloto.mcp.usage.all` (Wagner/superadmin)
> **Story:** MEM-MET-4 ([ADR 0050](../../decisions/0050-metricas-obrigatorias-memoria-table.md))

Painel de **trend 7-90d das 8 métricas obrigatórias + 3 RAGAS** lido de `copiloto_memoria_metricas` (alimentado pelo cron `copiloto:metrics:apurar` daily 23:55 + `copiloto:eval --persist` contra gabarito `jana_memoria_gabarito`). KPIs por business (última leitura) + gates verde/vermelho do [ADR 0049](../../decisions/0049-camadas-memoria-agente-fase-por-fase.md) + tabela trend com sparklines SVG inline + tabela detalhada de runs recentes. Persona: Wagner avaliando se Recall@3 está acima do gate (≥0.80) pra calibrar HyDE/Reranker/RRF da camada de retrieval.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto/admin/qualidade` | Login com `copiloto.mcp.usage.all` → URL → header "Qualidade IA" + filtros |
| AppShellV2 envolvendo via Persistent Layout | Inspetor: `<div class="cockpit">` ao redor; breadcrumb "Copiloto / Qualidade IA" |
| Filtro Janela (7/30/60/90d) + Business + Aplicar | `<Select>` shadcn no top |
| 1 Card por business com 8 KpiCards de gate | Verde quando `kpi.recall_at_3 >= gates.recall_at_3.alvo` |
| Trend table com sparklines SVG por métrica × business | `<svg width="120" height="28">` 1 polyline/série |
| Runs recentes — top 30 ordenados por data desc | `series.flatMap(...).sort(...).slice(0,30)` |
| Gabarito count visível no filtro | "Gabarito: N perguntas · personal=X · negocio=Y" |
| Empty trend | Linha vazia com dica "rode `php artisan copiloto:metrics:apurar`" |

## 1. Objetivo

Painel admin **observabilidade de retrieval** ([ADR 0050 MEM-MET-4](../../decisions/0050-metricas-obrigatorias-memoria-table.md)) — Wagner abre todos os dias na pré-reunião pra validar que mudanças no MeilisearchDriver/HyDE/Reranker NÃO regrediram qualidade. As 8 métricas obrigatórias + 3 RAGAS são o contrato canônico:

**8 obrigatórias** (gates ADR 0049):
1. `recall_at_3` ≥ 0.80 ★
2. `precision_at_3` ≥ 0.60 ★
3. `mrr` ≥ 0.70 ★
4. `latencia_p95_ms` ≤ 2000ms ★
5. `tokens_medio` ≤ 3000
6. `memory_bloat` ≥ 0.60
7. `taxa_contradicoes_pct` ≤ 2.0%
8. `cross_tenant_violations` == 0 (multi-tenant Tier 0 — qualquer >0 é vazamento)

**3 RAGAS-aligned:** `faithfulness` (≥0.85), `answer_relevancy`, `context_precision`.

★ = bloqueante de **evolução de camada** (fase 1 → fase 2 só com gates verdes 7d consecutivos).

V1 hoje: visualização read-only. V2 (Cycle 02): HITL "essa resposta foi boa?" + alerts de drift.

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules` (ADR 0024)
- [ ] Permissão `copiloto.mcp.usage.all` atribuída ao role do usuário (geralmente Wagner/superadmin)
- [ ] Rota `Route::get('/admin/qualidade', 'Admin\QualidadeController@index')->name('jana.admin.qualidade.index')` em [`Modules/Jana/Http/routes.php:106`](../../../Modules/Jana/Http/routes.php) — dentro do prefix `/copiloto`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Admin/Qualidade/Index.tsx`](../../../resources/js/Pages/Jana/Admin/Qualidade/Index.tsx)
- [ ] Tabela `copiloto_memoria_metricas` (Entity `MemoriaMetrica`) com scope `ultimosDias($dias)` — alimentada pelo cron `copiloto:metrics:apurar` (daily 23:55) E pelo command `copiloto:eval --persist`
- [ ] Tabela `jana_memoria_gabarito` populada com perguntas ativas + `categoria` (personal/negocio/etc) — pra contagem do filtro
- [ ] Tabela `mcp_memory_documents` populada (usada pra listar businesses no filtro — ver pegadinha §10)
- [ ] Skill irmã `jana-arch` (stack ADRs 0035-0053) + `jana-recall-flow` (memória)
- [ ] Skill irmã `multi-tenant-patterns` — Controller filtra opcional por `business_id`

## 3. Passo-a-passo

### 1. Controller agrupa séries por business + monta gates canônicos

```php
// Modules/Jana/Http/Controllers/Admin/QualidadeController.php
class QualidadeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.mcp.usage.all');
    }

    public function index(Request $request): Response
    {
        $dias       = (int) max(7, min(90, $request->get('dias', 30)));   // clamp [7..90]
        $businessId = $request->get('business_id') !== null ? (int) $request->get('business_id') : null;

        // Trend série
        $query = MemoriaMetrica::query()->ultimosDias($dias)->orderBy('apurado_em');
        if ($businessId !== null) $query->where('business_id', $businessId);
        $rows = $query->get();

        // Agrupar por business
        $series = [];
        foreach ($rows as $r) {
            $bizKey = $r->business_id === null ? 'plataforma' : "biz_{$r->business_id}";
            if (!isset($series[$bizKey])) {
                $series[$bizKey] = [
                    'business_id' => $r->business_id,
                    'label'       => $r->business_id === null ? 'Plataforma' : "Business #{$r->business_id}",
                    'pontos'      => [],
                ];
            }
            $series[$bizKey]['pontos'][] = [
                'data'                    => optional($r->apurado_em)->toDateString(),
                'recall_at_3'             => $r->recall_at_3 !== null ? (float) $r->recall_at_3 : null,
                'precision_at_3'          => $r->precision_at_3 !== null ? (float) $r->precision_at_3 : null,
                'mrr'                     => $r->mrr !== null ? (float) $r->mrr : null,
                'latencia_p95_ms'         => $r->latencia_p95_ms !== null ? (int) $r->latencia_p95_ms : null,
                'tokens_medio'            => $r->tokens_medio_interacao !== null ? (int) $r->tokens_medio_interacao : null,
                'memory_bloat'            => $r->memory_bloat_ratio !== null ? (float) $r->memory_bloat_ratio : null,
                'taxa_contradicoes_pct'   => $r->taxa_contradicoes_pct !== null ? (float) $r->taxa_contradicoes_pct : null,
                'cross_tenant_violations' => (int) $r->cross_tenant_violations,
                'faithfulness'            => $r->faithfulness !== null ? (float) $r->faithfulness : null,
                'answer_relevancy'        => $r->answer_relevancy !== null ? (float) $r->answer_relevancy : null,
                'context_precision'       => $r->context_precision !== null ? (float) $r->context_precision : null,
                'total_interacoes_dia'    => (int) $r->total_interacoes_dia,
                'total_memorias_ativas'   => (int) $r->total_memorias_ativas,
            ];
        }

        // Última métrica por business (KPIs)
        $kpis = [];
        foreach ($series as $key => $s) {
            $ultimo = end($s['pontos']);
            if ($ultimo === false) continue;
            $kpis[$key] = ['business_id' => $s['business_id'], /* ... últimos valores */];
        }

        // Gates canônicos ADR 0049/0050
        $gates = [
            'recall_at_3'             => ['op' => '>=', 'alvo' => 0.80, 'unit' => '',   'label' => 'Recall@3'],
            'precision_at_3'          => ['op' => '>=', 'alvo' => 0.60, 'unit' => '',   'label' => 'Precision@3'],
            'mrr'                     => ['op' => '>=', 'alvo' => 0.70, 'unit' => '',   'label' => 'MRR'],
            'faithfulness'            => ['op' => '>=', 'alvo' => 0.85, 'unit' => '',   'label' => 'Faithfulness'],
            'latencia_p95_ms'         => ['op' => '<=', 'alvo' => 2000, 'unit' => 'ms', 'label' => 'Latência p95'],
            'tokens_medio'            => ['op' => '<=', 'alvo' => 3000, 'unit' => 'tk', 'label' => 'Tokens/interação'],
            'memory_bloat'            => ['op' => '>=', 'alvo' => 0.60, 'unit' => '',   'label' => 'Bloat ratio'],
            'taxa_contradicoes_pct'   => ['op' => '<=', 'alvo' => 2.0,  'unit' => '%',  'label' => 'Contradições'],
            'cross_tenant_violations' => ['op' => '==', 'alvo' => 0,    'unit' => '',   'label' => 'Cross-tenant'],
        ];

        return Inertia::render('Jana/Admin/Qualidade/Index', [
            'series'                 => array_values($series),
            'kpis'                   => array_values($kpis),
            'gates'                  => $gates,
            'filtros'                => ['dias' => $dias, 'business_id' => $businessId],
            'gabarito_total'         => DB::table('jana_memoria_gabarito')->where('ativo', true)->count(),
            'gabarito_por_categoria' => DB::table('jana_memoria_gabarito')->where('ativo', true)
                                          ->select('categoria', DB::raw('COUNT(*) as c'))
                                          ->groupBy('categoria')->pluck('c', 'categoria'),
        ]);
    }
}
```

> Clamp `(int) max(7, min(90, ...))` defensivo: usuário com `?dias=999` recebe 90; `?dias=2` recebe 7. Não há erro 400.

### 2. Page Inertia recebe Props tipadas

```tsx
// resources/js/Pages/Jana/Admin/Qualidade/Index.tsx
interface Ponto {
  data: string;
  recall_at_3: number | null;
  precision_at_3: number | null;
  mrr: number | null;
  latencia_p95_ms: number | null;
  tokens_medio: number | null;
  memory_bloat: number | null;
  taxa_contradicoes_pct: number | null;
  cross_tenant_violations: number;
  faithfulness: number | null;
  answer_relevancy: number | null;
  context_precision: number | null;
  total_interacoes_dia: number;
  total_memorias_ativas: number;
}
interface Serie  { business_id: number | null; label: string; pontos: Ponto[] }
interface Kpi    { business_id: number | null; label: string; apurado_em: string; /* ... */ }
interface Gate   { op: '>=' | '<=' | '=='; alvo: number; unit: string; label: string }
interface Props  {
  series: Serie[]; kpis: Kpi[]; gates: Record<string, Gate>;
  filtros: { dias: number; business_id: number | null };
  gabarito_total: number;
  gabarito_por_categoria: Record<string, number>;
}
```

### 3. Função `gateStatus()` — comparação op/alvo + emoji

```tsx
function gateStatus(value: number | null, gate: Gate): { ok: boolean; emoji: string; color: string } | null {
  if (value === null) return null;
  let ok = false;
  if (gate.op === '>=') ok = value >= gate.alvo;
  if (gate.op === '<=') ok = value <= gate.alvo;
  if (gate.op === '==') ok = value === gate.alvo;
  return ok
    ? { ok: true,  emoji: '✅', color: 'text-emerald-600 dark:text-emerald-400' }
    : { ok: false, emoji: '🔴', color: 'text-red-600 dark:text-red-400' };
}
```

### 4. `Sparkline` SVG inline minimalista (120×28, 1 polyline)

```tsx
function Sparkline({ values, color = '#3b82f6' }: { values: (number | null)[]; color?: string }) {
  const w = 120, h = 28;
  const valid = values.filter((v): v is number => v !== null);
  if (valid.length < 2) {
    return <span className="text-[10px] text-muted-foreground">{valid.length} ponto{valid.length === 1 ? '' : 's'}</span>;
  }
  const min = Math.min(...valid);
  const max = Math.max(...valid);
  const range = max - min || 1;
  const points = values.map((v, i) => {
    if (v === null) return null;
    const x = (i / (values.length - 1)) * w;
    const y = h - ((v - min) / range) * h;
    return `${x.toFixed(1)},${y.toFixed(1)}`;
  }).filter(Boolean).join(' ');
  return (
    <svg width={w} height={h} className="inline-block">
      <polyline fill="none" stroke={color} strokeWidth="1.5" points={points} />
    </svg>
  );
}
```

> Cor passada como **HEX hardcoded** (`#3b82f6`, `#10b981`, etc) no array `allMetrics` — ver pegadinha §10 (R-DS-002 violado).

### 5. Filtro via `router.get` + state local

```tsx
const [businessFilter, setBusinessFilter] = useState<string>(
  filtros.business_id !== null ? String(filtros.business_id) : '__all__'
);
const [diasFilter, setDiasFilter] = useState<string>(String(filtros.dias));

function applyFilter() {
  const params: Record<string, string | number> = { dias: Number(diasFilter) };
  if (businessFilter !== '__all__') params.business_id = Number(businessFilter);
  router.get('/copiloto/admin/qualidade', params, { preserveScroll: true, preserveState: true });
}
```

### 6. Loop por business renderizando 8 KpiCards de gate

```tsx
{kpis.map((kpi) => {
  const gateRecall  = gateStatus(kpi.recall_at_3, gates.recall_at_3);
  const gatePrec    = gateStatus(kpi.precision_at_3, gates.precision_at_3);
  // ... 6 outros gates

  return (
    <Card key={kpi.business_id ?? 'plataforma'} className="mt-4">
      <CardHeader>
        <CardTitle className="text-sm">
          {kpi.label}
          <span className="ml-2 text-xs text-muted-foreground font-normal">
            última leitura {kpi.apurado_em} · {num(kpi.total_interacoes_dia)} interações no dia
          </span>
        </CardTitle>
      </CardHeader>
      <CardContent>
        <KpiGrid cols={4}>
          <KpiCard icon="target" tone={gateRecall?.ok ? 'success' : 'danger'}
            label="Recall@3" value={fmtPct(kpi.recall_at_3)}
            description={`gate ≥ ${fmtPct(gates.recall_at_3.alvo)} · ${gateRecall?.emoji ?? '—'}`} />
          {/* ... 7 outros KpiCards */}
        </KpiGrid>
      </CardContent>
    </Card>
  );
})}
```

### 7. Persistent Layout AppShellV2

```tsx
QualidadeIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Qualidade IA — Métricas de memória" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Qualidade IA' }]}>
    {page}
  </AppShellV2>
);
```

### 8. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Admin/Qualidade/Index" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

| Token / classe | Onde aplica nesta tela | Origem |
|---|---|---|
| `text-muted-foreground` | Subtítulos, "última leitura", labels filtros, gabarito count | shadcn semântico (R-DS-002) |
| `text-emerald-600 dark:text-emerald-400` | Gate verde | **Exceção R-DS-002** — status fixos OK |
| `text-red-600 dark:text-red-400` | Gate vermelho | **Exceção R-DS-002** — status fixos OK |
| `text-red-500` | Asterisco `*` "gate crítico ADR 0049" | **Exceção R-DS-002** — status fixos OK |
| `bg-background` | Sticky header da tabela trend | shadcn semântico |
| `bg-muted/40` | Hover linha tabela runs recentes | Tailwind utility |
| `font-mono` | Valores numéricos das tabelas + códigos `php artisan ...` | Tailwind utility |
| `KpiCard tone="success"/"danger"/"warning"` | 8 KpiCards de gate por business | shadcn KpiCard variantes |
| `#3b82f6, #10b981, #8b5cf6, #f59e0b, #ef4444, #06b6d4, #84cc16, #ec4899` | **Cores HEX hardcoded** das sparklines | ❌ Viola R-DS-002 — ver §10 |

**Coluna direita do Cockpit:** esta tela NÃO usa LinkedApps — observabilidade cross-business sem entidade vinculada.

> ⚠️ **Emojis hardcoded** `✅` `🔴` em `gateStatus()`: se Wagner aprovar, manter; se Eliana[E] preferir ícones lucide consistentes (`CheckCircle2`/`XCircle`), substituir.

## 5. Estados visuais

| Estado | Trigger | Implementação atual | Pegadinha |
|---|---|---|---|
| `default` | — | `<Card>` shadcn + `KpiGrid` | OK |
| `hover` | mouse-over linha tabela runs | `hover:bg-muted/40` no `<tr>` | OK |
| `focus` | tab/click | shadcn primitives herdam `focus-visible` | OK |
| `loading` | `router.get` em filtro | **❌ NÃO IMPLEMENTADO** — sem skeleton | Ver §10 |
| `empty (sparkline)` | `valid.length < 2` | `<span>{valid.length} ponto/pontos</span>` muted | OK — implementado em `Sparkline` |
| `empty (trend table)` | `series.length === 0` | "Sem dados de métricas. Rode `php artisan copiloto:metrics:apurar`..." colspan all | OK |
| `gate ok / gate fail` | `gateStatus()` resolve | `tone={gate?.ok ? 'success' : 'danger'}` no KpiCard | OK |
| `metric null` | métrica `null` (faithfulness opcional) | `'—'` em `fmtPct/fmtNum/fmtMs` | OK |
| `error` | `Inertia.render` falha (500) | **❌ NÃO IMPLEMENTADO** localmente | Trata pelo error boundary global |

## 6. Responsividade

`Card` filtros declara `flex flex-wrap items-end gap-3`:

| Largura | Filtros | KPIs (8 cards) | Trend table | Runs table |
|---|---|---|---|---|
| `<768px` (mobile) | Wrap (Janela + Business + Aplicar empilham) | KpiGrid responsivo (2 cols default) | `ScrollArea max-h-[600px]` + scroll-x SVG | `overflow-x-auto` |
| `≥768px` `md:` | Em linha | KpiGrid 4 cols | scroll-x | scroll-x |
| `≥1024px` `lg:` | Em linha | KpiGrid 4 cols | scroll-x | scroll-x |
| `≥1280px` `xl:` | Em linha | KpiGrid 4 cols (8 cards = 2 linhas) | mostrando ~8 colunas inteiras | mostrando 12 colunas inteiras |

**Pegadinha trend table:** `<th style={{ minWidth: 130 }}>` por métrica × 8 métricas = 1040px só nas colunas de métrica + 200px (Business + N pontos) = **1240px mínimo**. Em monitor 1280px (Larissa) cabe; em mobile, scroll horizontal aceitável dentro do `<ScrollArea>`.

**Pegadinha runs table:** 12 colunas, sem `minWidth` declarado — encolhe abaixo do legível em telefone. Não é problema porque persona é Wagner/superadmin em desktop.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell | AppShellV2 |
| `J` | — | — | — |
| `K` | — | — | — |
| `E` | — | — | — |
| `A` | — | — | — |
| `N` | — | — | — |
| `/` | — | — | — |

> **Tela read-only sem master/detail interno** — todos os atalhos `—`. V2 (HITL anotação) pode introduzir `Y/N` pra "essa resposta foi boa?" — quando chegar, revisitar.

## 8. Component contract

### Props da Page (resumo — completo no §3.2)

```tsx
interface Props {
  series: Array<{
    business_id: number | null;            // null = plataforma (cross-business)
    label: string;                         // 'Plataforma' | 'Business #4'
    pontos: Ponto[];                       // 1 por dia em ordem cronológica
  }>;
  kpis: Array<Kpi>;                        // 1 por business — última leitura
  gates: Record<string, Gate>;             // 9 gates canônicos ADR 0049
  filtros: { dias: number; business_id: number | null };
  gabarito_total: number;                  // count(jana_memoria_gabarito where ativo)
  gabarito_por_categoria: Record<string, number>;
}
```

### Componentes locais

- `gateStatus(value, gate) → { ok, emoji, color } | null` — resolve verde/vermelho
- `Sparkline({ values, color })` — SVG inline 120×28, 1 polyline, normaliza min/max
- `fmtPct(v, digits=1)` — `v * 100 + '%'` ou `'—'`
- `fmtNum(v, digits=3)` — `v.toFixed(digits)` ou `'—'`
- `fmtMs(v)` — `1234ms` ou `1.23s` ou `'—'`
- `num(v)` — `Intl.NumberFormat('pt-BR')`

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Persistent Layout
- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx) — `icon="trending-up"`
- [`@/Components/shared/KpiGrid`](../../../resources/js/Components/shared/KpiGrid.tsx) — `cols={4}`
- [`@/Components/shared/KpiCard`](../../../resources/js/Components/shared/KpiCard.tsx) — `tone: success|danger|warning|default`
- [`@/Components/ui/card`](../../../resources/js/Components/ui/card.tsx)
- [`@/Components/ui/button`](../../../resources/js/Components/ui/button.tsx)
- [`@/Components/ui/badge`](../../../resources/js/Components/ui/badge.tsx) — importado **mas não usado** no JSX (dead import — ver §10)
- [`@/Components/ui/select`](../../../resources/js/Components/ui/select.tsx) — Janela + Business
- [`@/Components/ui/label`](../../../resources/js/Components/ui/label.tsx) — labels dos selects
- [`@/Components/ui/scroll-area`](../../../resources/js/Components/ui/scroll-area.tsx) — `ScrollArea max-h-[600px]` na trend table

### Ícones (lucide-react via `KpiCard icon="..."` — R-DS-003)

`trending-up` (header), `target`, `shield-check`, `clock`, `zap`, `alert-triangle`, `lock`.

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (Persistent Layout via `QualidadeIndex.layout = ...`)
- [x] Tokens shadcn semânticos majoritários — exceções R-DS-002 em status fixos (emerald/red 600/400)
- [n/a] Coluna direita "Apps Vinculados" — observabilidade cross-business sem entidade-foco
- [n/a] Atalhos J/K/E/A — tela read-only V1 sem master/detail
- [n/a] Estado `localStorage oimpresso.*` — filtros vivem na URL via `router.get`
- [x] Componentes shared reusados (PageHeader, KpiGrid, KpiCard, Card, Button, Select, Label, ScrollArea)
- [x] PT-BR em labels ("Qualidade IA", "Janela", "Business", "última leitura", "Sem dados de métricas")
- [x] Dark mode validado parcialmente — `text-emerald-600 dark:text-emerald-400` declarado; cores HEX das sparklines NÃO têm dark variant (§10)
- [x] Responsividade `flex flex-wrap` filtros + `ScrollArea` trend
- [x] Estados cobertos: default + empty (sparkline) + empty (trend) + null (metric) — loading/error pendentes
- [x] Bundle Inertia: `npm run build:inertia` + `Pages/Jana/Admin/Qualidade/Index` no manifest
- [x] Multi-tenant: Controller filtra opcional por `business_id`; séries agrupadas por `bizKey` (plataforma quando NULL)
- [x] Permissão: middleware `can:copiloto.mcp.usage.all`
- [x] Clamp defensivo `dias` ∈ [7, 90]

## 10. Pegadinhas

- ❌ **Cores HEX hardcoded violam R-DS-002** — `allMetrics` array (linha 143-152) define `color: '#3b82f6'`, `'#10b981'`, etc, passados pra `<Sparkline color={...}>`. Em dark mode, alguns ficam mal contrastados (`#84cc16` lime fica fluorescente em dark). Fix: usar tokens semânticos `--chart-1`, `--chart-2`, etc do shadcn (já definidos em `tailwind.config.ts`).
- ❌ **`Badge` importado mas NÃO usado** (linha 16) — `import { Badge } from '@/Components/ui/badge'` sem `<Badge>` no JSX. Dead import; bundle pega tree-shake mas TypeScript não pega como warning. Remover.
- ❌ **`businesses` no filtro NÃO vem de `\App\Business`** — Controller linha 117-121 lê `mcp_memory_documents.module distinct` pra preencher dropdown. Isso retorna **nomes de módulos** (Jana/Repair/PontoWr2), NÃO IDs de business. **A prop `businesses` é coletada mas a Page nunca a recebe** (linha 123-134 não inclui no array de retorno). O `<Select>` de business no front é populado por `series.filter(s => s.business_id !== null)` — ou seja, só businesses que JÁ TÊM métricas aparecem. Business novo sem cron rodado fica invisível. Bug funcional.
- ❌ **`apurado_em` formatado como `toDateString()` PHP** (Controller linha 65) — vira `'2026-05-09'`. Page renderiza direto sem reformatar (`{kpi.apurado_em}`). Em locale BR esperaria `09/05/2026`. Adicionar `formatDataCurta()` igual ao Custos.
- ❌ **Sparkline normaliza por min/max LOCAL da série** — `min = Math.min(...valid); max = Math.max(...valid)` significa que cada sparkline tem escala própria. **Visual engana**: linha do `recall_at_3` indo de 0.78→0.79 (queda quase nula) parece o mesmo "movimento" de 0.20→0.85 (queda enorme). Pra trend ADR 0049 isso é grave — Wagner pode ler "estável" quando o valor real está flutuando próximo do gate. Fix: passar `gates[m.key].alvo` como referência fixa OU usar escala absoluta 0..1 nas métricas %.
- ❌ **Sem `useMemo` em `series.flatMap(...).sort(...).slice(0,30)`** — recompute a cada render do componente (linha 335). Em 30 dias × 50 businesses = 1500 pontos sortados a cada keypress no filtro. Memoizar com `useMemo([series], ...)`.
- ❌ **`emoji` `✅`/`🔴` literal** — pode ser bloqueado por filtros corporativos OU renderizar inconsistente em fonts diferentes (Wagner em Windows, Larissa pode estar em mobile sem emoji-fallback). Substituir por `<CheckCircle2 className="text-emerald-600">` / `<XCircle>`.
- ❌ **`hardcoded URL '/copiloto/admin/qualidade'`** (linha 140) no `router.get` — igual a Custos, deveria usar `route('jana.admin.qualidade.index')` Ziggy. Quebra silencioso se renomear rota.
- ❌ **Comparação float exata em `gate.op === '=='`** (linha 99) — usado pra `cross_tenant_violations == 0`. Valor é `(int)` no Controller, então OK; mas se algum dia `cross_tenant_violations` virar fração ou `null`, o `==` JS vira `0 == 0` = true mesmo quando deveria ser inválido. Defensivo: tratar `value === null` ANTES (já existe linha 95) e usar `===` no resto.
- ❌ **`code` markdown-like inline em `<code className="font-mono">php artisan ...</code>`** (linhas 297-298, 357-358) — não é Markdown renderizado; é só estilização. Ok visual, mas se Wagner colar texto pro Slack sem formatar perde a intenção.
- ❌ **V1 NÃO tem alerta de drift** — se `recall_at_3` cair de 0.85 → 0.78 entre dias, gate fecha, mas Wagner só vê abrindo a tela. Cycle 02 promete; até lá, Wagner precisa abrir manualmente.
- ⚠️ **`max(7, min(90, ...))` clamp silencioso** — `?dias=999` retorna 90 sem aviso. Pra debugging, considerar log warn quando out-of-range.
- ⚠️ **`gabarito_total === 0`** — se ninguém populou `jana_memoria_gabarito`, a métrica `recall_at_3` etc é sempre `null` (eval não tem o que avaliar). Tela mostra `gabarito: 0 perguntas` discreto no canto direito; idealmente teria empty state explícito "Popule o gabarito primeiro".

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — origem do retrieval medido aqui (laravel/ai SDK + MeilisearchDriver)
- [ADR 0036 — Replanejamento Meilisearch-first](../../decisions/0036-replanejamento-meilisearch-first.md) — driver atual (afeta `latencia_p95_ms` direto)
- [ADR 0049 — Camadas de memória do agente fase-por-fase](../../decisions/0049-camadas-memoria-agente-fase-por-fase.md) — **define os gates** (Recall@3≥0.80 = bloqueante de evolução de fase)
- [ADR 0050 — Métricas obrigatórias da tabela `copiloto_memoria_metricas`](../../decisions/0050-metricas-obrigatorias-memoria-table.md) — **define as 8+3 métricas** (esquema da tabela, MEM-MET-4 é esta tela)
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — §4 Loop fechado por métrica (esta tela é o instrumento canônico do princípio §4)

**Stories cobertas:** MEM-MET-4 (ADR 0050)
**Tests:** sem suite Pest dedicada hoje — quando V2 (HITL) chegar, criar `tests/Feature/Modules/Jana/Admin/QualidadeControllerTest.php` validando shape de `series`/`kpis`/`gates`
**Comandos relacionados:** `php artisan copiloto:metrics:apurar` (cron daily 23:55), `php artisan copiloto:eval --persist [--business=N]` (popula recall/precision/MRR/faithfulness contra gabarito)

---

**Última atualização:** 2026-05-09
