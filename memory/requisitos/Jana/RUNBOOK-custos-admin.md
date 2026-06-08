---
slug: jana-runbook-custos-admin
title: "Jana — Runbook da tela Custos de IA (Admin)"
type: runbook
module: Jana
status: active
date: 2026-05-09
---

# RUNBOOK — Custos de IA Admin (Jana)

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0026](../../decisions/0026-posicionamento-erp-grafico-com-ia.md), [ADR 0035](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md), [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md), Copiloto/adr/arq/0003 (Onda 1 — ROI direto)
> **Status:** implementada — `/copiloto/admin/custos`
> **Permissão:** `copiloto.admin.custos.view` (independente de `copiloto.superadmin`)

Painel admin de consumo de IA no business em foco. Mostra **KPIs do período** (R$, mensagens, tokens, usuários ativos), **gráfico de área** de gasto diário (SVG inline, sem dep externa) e **tabela por usuário** ordenada por tokens consumidos. Cálculo R$ é derivado de `jana_mensagens.tokens_in/tokens_out` × pricing por modelo × câmbio (config). Persona: dono do business (Wagner / Larissa) avaliando ROI da Jana — "tô gastando quanto e quem usa mais?".

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto/admin/custos` | Login com `copiloto.admin.custos.view` → URL → header "Custos de IA" + 4 KPIs |
| AppShellV2 envolvendo via Persistent Layout | Inspetor: `<div class="cockpit">` ao redor; breadcrumb "Copiloto / Custos de IA" |
| 4 KPIs no topo (R$, Mensagens, Tokens, Usuários ativos) | `KpiGrid cols={4}` + `KpiCard` shadcn |
| Filtro de período | Select com `mes_atual / mes_anterior / 90d / custom`; custom abre 2 inputs `date` |
| Gráfico de área SVG inline | `<svg viewBox="0 0 800 220">` com polyline + polygon area |
| Tabela por usuário com tfoot Total | Última linha `bg-muted/30` com soma de tokens/mensagens/R$ |
| Câmbio + modelo no header | `R$ X,XX / US$` + `<span className="font-mono">` com `pricing.modelo_default` |

## 1. Objetivo

Painel admin de **ROI Direto da IA** (Onda 1 — Copiloto/adr/arq/0003): respondem 4 perguntas a cada visita:

1. **Quanto a IA custou no período?** (em R$, com câmbio do config)
2. **Quem usou mais?** (top usuários por tokens — tabela ordenada desc)
3. **Como o gasto distribui no tempo?** (série diária, gráfico de área)
4. **Tô gastando o esperado?** (modelo + câmbio visíveis no header → audit em segundos)

Lê dados de `jana_mensagens` (join com `jana_conversas` pra scope multi-tenant `business_id`). Calcula R$ no PHP via `CustosService::calcularCustoBrl()` usando `config('copiloto.ai.pricing_default_model')` × pricing × `config('copiloto.ai.cambio_brl_usd')`. Filtra período via 4 presets ou range custom. Read-only — superadmin que quiser drill-down chama outra rota.

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules` (ADR 0024)
- [ ] Permissão `copiloto.admin.custos.view` atribuída ao role do usuário (Controller usa `middleware('can:copiloto.admin.custos.view')`)
- [ ] Rota `Route::get('/admin/custos', 'Admin\CustosController@index')->name('jana.admin.custos.index')` em [`Modules/Jana/Http/routes.php:84`](../../../Modules/Jana/Http/routes.php) — dentro do prefix `/copiloto`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Admin/Custos/Index.tsx`](../../../resources/js/Pages/Jana/Admin/Custos/Index.tsx)
- [ ] Service `Modules\Jana\Services\CustosService` resolve `painel($businessId, $inicio, $fim)` e `resolverPeriodo($preset, $de, $ate)`
- [ ] Tabelas `jana_mensagens` e `jana_conversas` com `tokens_in` e `tokens_out` populados (preenchidos pelo `OpenAiDirectDriver` ao final do stream — ver `ChatController::sendStream`)
- [ ] Configs: `config('copiloto.ai.pricing_default_model')` e `config('copiloto.ai.cambio_brl_usd')` definidas em `config/copiloto.php`
- [ ] Skill irmã `multi-tenant-patterns` — Service usa `where('c.business_id', $businessId)` no join

## 3. Passo-a-passo

### 1. Controller resolve período + chama Service + renderiza Inertia

```php
// Modules/Jana/Http/Controllers/Admin/CustosController.php
class CustosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('can:copiloto.admin.custos.view');
    }

    public function index(Request $request, CustosService $service): Response
    {
        $businessId = (int) $request->session()->get('user.business_id');

        $preset = $request->get('preset', 'mes_atual');
        if (! in_array($preset, ['mes_atual', 'mes_anterior', '90d', 'custom'], true)) {
            $preset = 'mes_atual';  // whitelist defensiva contra ?preset=DROP_TABLE
        }

        $range  = $service->resolverPeriodo($preset, $request->get('de'), $request->get('ate'));
        $painel = $service->painel($businessId, $range['inicio'], $range['fim']);

        return Inertia::render('Jana/Admin/Custos/Index', [
            'kpis'         => $painel['kpis'],
            'por_usuario'  => $painel['por_usuario'],
            'serie_diaria' => $painel['serie_diaria'],
            'periodo'      => $painel['periodo'],
            'filters'      => [
                'preset' => $preset,
                'de'     => $request->get('de'),
                'ate'    => $request->get('ate'),
            ],
            'pricing' => [
                'modelo_default' => config('copiloto.ai.pricing_default_model'),
                'cambio_brl_usd' => (float) config('copiloto.ai.cambio_brl_usd'),
            ],
        ]);
    }
}
```

**Validação:** `php artisan route:list --name=jana.admin.custos.index` retorna 1 linha com middleware `can:copiloto.admin.custos.view`.

### 2. Service agrega `jana_mensagens` × `jana_conversas`

```php
// Modules/Jana/Services/CustosService.php (linha 38)
public function painel(int $businessId, CarbonInterface $inicio, CarbonInterface $fim): array
{
    $base = DB::table('jana_mensagens as m')
        ->join('jana_conversas as c', 'c.id', '=', 'm.conversa_id')
        ->where('c.business_id', $businessId)              // multi-tenant scope
        ->whereBetween('m.created_at', [$iniSql, $fimSql]);

    // KPIs totais (1 query)
    $totais = (clone $base)->selectRaw('
        COUNT(*) AS mensagens,
        COALESCE(SUM(m.tokens_in), 0)  AS tokens_in,
        COALESCE(SUM(m.tokens_out), 0) AS tokens_out,
        COUNT(DISTINCT c.user_id)      AS usuarios_ativos
    ')->first();

    $kpis = [
        'custo_brl'       => $this->calcularCustoBrl($tokensIn, $tokensOut),
        'mensagens'       => (int) ($totais->mensagens ?? 0),
        'tokens'          => $tokensIn + $tokensOut,
        'usuarios_ativos' => (int) ($totais->usuarios_ativos ?? 0),
    ];

    // Por usuário ordenado desc por tokens (1 query)
    $porUsuario = (clone $base)->leftJoin('users as u', 'u.id', '=', 'c.user_id')
        ->selectRaw("c.user_id, COALESCE(NULLIF(TRIM(CONCAT_WS(' ', u.first_name, u.last_name)), ''), u.username, CONCAT('#', c.user_id)) AS nome, ...")
        ->groupBy('c.user_id', 'u.first_name', 'u.last_name', 'u.username')
        ->orderByDesc(DB::raw('COALESCE(SUM(m.tokens_in), 0) + COALESCE(SUM(m.tokens_out), 0)'))
        ->get()->map(fn ($r) => [...])->values()->all();

    // Série diária preenchida (zero-fill nos dias sem evento)
    $serieDb     = (clone $base)->selectRaw('DATE(m.created_at) AS data, ...')->groupBy(DB::raw('DATE(m.created_at)'))->get()->keyBy('data');
    $serieDiaria = $this->preencherSerie($inicio, $fim, $serieDb);

    return ['kpis' => $kpis, 'por_usuario' => $porUsuario, 'serie_diaria' => $serieDiaria, 'periodo' => [...]];
}
```

> Cálculo R$: `(tokens_in × pricing.input + tokens_out × pricing.output) × cambio` — feito no PHP, NÃO no SQL (pricing por modelo é dict de config).

### 3. Page Inertia recebe Props tipadas

```tsx
// resources/js/Pages/Jana/Admin/Custos/Index.tsx
type Preset = 'mes_atual' | 'mes_anterior' | '90d' | 'custom';

interface Kpis           { custo_brl: number; mensagens: number; tokens: number; usuarios_ativos: number }
interface UsuarioRow     { user_id: number; nome: string; conversas: number; mensagens: number; tokens: number; custo_brl: number }
interface DiaRow         { data: string; custo_brl: number; tokens: number; mensagens: number }
interface Periodo        { inicio: string; fim: string; label: string }
interface Filters        { preset: Preset; de: string | null; ate: string | null }
interface Pricing        { modelo_default: string; cambio_brl_usd: number }
interface Props          { kpis: Kpis; por_usuario: UsuarioRow[]; serie_diaria: DiaRow[]; periodo: Periodo; filters: Filters; pricing: Pricing }
```

### 4. Filtro de período via `router.get` (preserva state + scroll)

```tsx
const aplicar = (patch: Partial<Filters>) => {
  router.get('/copiloto/admin/custos', { ...filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,    // não polui histórico do browser
  });
};

const aplicarCustom = (e: React.FormEvent) => {
  e.preventDefault();
  aplicar({ preset: 'custom', de, ate });
};
```

### 5. Gráfico de área SVG inline (sem dep externa)

```tsx
function GastoDiarioChart({ dados }: { dados: DiaRow[] }) {
  const w = 800, h = 220;
  const pad = { top: 16, right: 16, bottom: 28, left: 56 };
  const innerW = w - pad.left - pad.right;
  const innerH = h - pad.top - pad.bottom;

  const valores = dados.map((d) => d.custo_brl);
  const max = Math.max(0.01, ...valores);   // evita div/0; piso é R$ 0,01
  const n   = dados.length;

  if (n === 0) return <div className="text-center text-sm text-muted-foreground py-12">Sem dados no período.</div>;

  const xAt = (i: number) => pad.left + (n === 1 ? innerW / 2 : (i / (n - 1)) * innerW);
  const yAt = (v: number) => pad.top + innerH - (v / max) * innerH;

  // polyline + polygon (área sombreada)
  const linePts = dados.map((d, i) => `${xAt(i)},${yAt(d.custo_brl)}`).join(' ');
  const areaPts = [`${xAt(0)},${pad.top + innerH}`, ...dados.map((d, i) => `${xAt(i)},${yAt(d.custo_brl)}`), `${xAt(n - 1)},${pad.top + innerH}`].join(' ');

  // X labels esparsas — máximo ~8 rótulos
  const stepX = Math.max(1, Math.ceil(n / 8));
  const xLabels = dados.map((d, i) => ({ d, i })).filter(({ i }) => i % stepX === 0 || i === n - 1);

  return (
    <div className="w-full overflow-x-auto">
      <svg viewBox={`0 0 ${w} ${h}`} className="w-full h-auto text-primary" role="img" aria-label="Gasto de IA por dia">
        {/* grid Y + área + linha + labels X */}
      </svg>
    </div>
  );
}
```

### 6. Persistent Layout AppShellV2 com title + breadcrumbItems

```tsx
CustosIaIndex.layout = (page: ReactNode) => (
  <AppShellV2 title="Copiloto — Custos de IA" breadcrumbItems={[{ label: 'Copiloto' }, { label: 'Custos de IA' }]}>
    {page}
  </AppShellV2>
);
```

### 7. Build local + smoke

```bash
npm run build:inertia
grep -i "Pages/Jana/Admin/Custos/Index" public/build-inertia/manifest.json
# Esperado: 1 linha com hash do bundle
```

## 4. Tokens CSS

| Token / classe | Onde aplica nesta tela | Origem |
|---|---|---|
| `text-primary` | Botão "Aplicar", linha do gráfico (`stroke-primary`) | shadcn semântico (R-DS-002) |
| `fill-primary/15` | Área sombreada do gráfico | Token derivado |
| `stroke-primary` | Linha do gráfico | shadcn semântico |
| `stroke-border` | Grid horizontal (linhas tracejadas Y) | shadcn semântico |
| `fill-muted-foreground` | Labels Y/X do gráfico (`text-[10px]`) | shadcn semântico |
| `text-muted-foreground` | "Modelo base", "Câmbio", labels filtros, "Sem dados no período" | shadcn semântico |
| `text-foreground` | Total destacado em "total R$ X" | shadcn semântico |
| `font-mono` | Valores numéricos da tabela + `pricing.modelo_default` | Tailwind utility |
| `tabular-nums` | — | **NÃO usado** aqui (usa `font-mono` em vez disso) |
| `bg-muted/40` | Hover linha da tabela | Tailwind utility |
| `bg-muted/30` | Linha "Total" do `<tfoot>` | Tailwind utility |
| `KpiCard tone="success"` | Card "Esse período (R$)" — sempre verde, mesmo se R$ 0 | shadcn KpiCard |

**Coluna direita do Cockpit:** esta tela NÃO usa LinkedApps — Custos não tem entidade-foco vinculada (é cross-business overview).

## 5. Estados visuais

| Estado | Trigger | Implementação atual | Pegadinha |
|---|---|---|---|
| `default` | — | `<Card>` shadcn + `KpiGrid` | OK |
| `hover` | mouse-over linha tabela | `hover:bg-muted/40` no `<tr>` | OK |
| `focus` | tab/click | `<Input>` + `<Button>` shadcn herdam `focus-visible` | OK |
| `loading` | `router.get` com `preserveState` | **❌ NÃO IMPLEMENTADO** — Inertia trocando period reflete sem skeleton | Ver §10 |
| `empty (chart)` | `serie_diaria.length === 0` | "Sem dados no período." muted center | OK — implementado em `GastoDiarioChart` |
| `empty (tabela)` | `por_usuario.length === 0` | "Nenhum consumo de IA no período." + dica em `<span>` | OK |
| `error` | `Inertia.render` falha (500) | **❌ NÃO IMPLEMENTADO** localmente | Trata pelo error boundary global |
| `validation` | custom range `de > ate` | **❌ NÃO IMPLEMENTADO** — backend aceita silenciosamente | Ver §10 |

## 6. Responsividade

Card de filtros declara `flex flex-col md:flex-row`:

| Largura | Filtros | KPIs | Gráfico | Tabela |
|---|---|---|---|---|
| `<768px` (mobile) | Stack vertical | 4 cols (KpiGrid responsivo herdado) | `overflow-x-auto` (scroll horizontal SVG) | `overflow-x-auto` |
| `≥768px` `md:` | Linha horizontal (filtro + custom inline) | 4 cols | `w-full` SVG | `w-full` |
| `≥1024px` `lg:` | mesmo | mesmo | mesmo | mesmo |
| `≥1280px` `xl:` | mesmo | mesmo | mesmo | mesmo |

**Pegadinha mobile:** SVG `viewBox="0 0 800 220"` é fixo — em telefone o gráfico vira `overflow-x-auto` mas labels Y `text-[10px]` viram quase ilegíveis. Wagner em monitor 1280px (Larissa-style) está OK.

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

> **Tela read-only sem master/detail interno** — todos os atalhos `—`. Filtros são via mouse no `<Select>`. Decisão consciente (igual ao Dashboard de Metas).

## 8. Component contract

### Props da Page

```tsx
interface Props {
  kpis: {
    custo_brl: number;            // total R$ no período (já com câmbio)
    mensagens: number;
    tokens: number;
    usuarios_ativos: number;
  };
  por_usuario: Array<{
    user_id: number;
    nome: string;                 // CONCAT_WS first_name+last_name OR username OR '#user_id'
    conversas: number;
    mensagens: number;
    tokens: number;
    custo_brl: number;
  }>;
  serie_diaria: Array<{
    data: string;                 // 'YYYY-MM-DD' (PHP DATE())
    custo_brl: number;
    tokens: number;
    mensagens: number;
  }>;
  periodo: { inicio: string; fim: string; label: string };  // ex: 'Mês atual'
  filters: { preset: 'mes_atual'|'mes_anterior'|'90d'|'custom'; de: string|null; ate: string|null };
  pricing: { modelo_default: string; cambio_brl_usd: number };
}
```

### Componentes locais

- `GastoDiarioChart({ dados })` — SVG 800×220 inline (área + linha + grid Y + labels X esparsos)
- `brl(v)` — `Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' })`
- `num(v)` — `Intl.NumberFormat('pt-BR')`
- `formatDataCurta(iso)` — `dd/MM` curto

### Componentes shared usados

- [`@/Layouts/AppShellV2`](../../../resources/js/Layouts/AppShellV2.tsx) — Persistent Layout
- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx) — header com `icon="coins"` + action no canto
- [`@/Components/shared/KpiGrid`](../../../resources/js/Components/shared/KpiGrid.tsx) — `cols={4}`
- [`@/Components/shared/KpiCard`](../../../resources/js/Components/shared/KpiCard.tsx) — variantes `tone: success|info|default`
- [`@/Components/ui/card`](../../../resources/js/Components/ui/card.tsx) — Card/CardHeader/CardContent/CardTitle/CardDescription
- [`@/Components/ui/button`](../../../resources/js/Components/ui/button.tsx) — shadcn Button
- [`@/Components/ui/input`](../../../resources/js/Components/ui/input.tsx) — `<Input type="date">`
- [`@/Components/ui/select`](../../../resources/js/Components/ui/select.tsx) — preset

### Ícones (lucide-react via `KpiCard icon="..."` — R-DS-003)

`coins` (header), `dollar-sign`, `message-square`, `cpu`, `users`.

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (Persistent Layout via `CustosIaIndex.layout = ...`)
- [x] Tokens shadcn semânticos (`text-primary`, `text-muted-foreground`, `stroke-border`)
- [n/a] Coluna direita "Apps Vinculados" — overview cross-business sem entidade-foco
- [n/a] Atalhos J/K/E/A — tela read-only com filtros via mouse
- [n/a] Estado `localStorage oimpresso.*` — filtros vivem na URL via `router.get` com `replace: true`
- [x] Componentes shared reusados (PageHeader, KpiGrid, KpiCard, Card, Button, Input, Select)
- [x] PT-BR em todos os labels ("Custos de IA", "Esse período (R$)", "Por usuário", "Aplicar", "Mês atual")
- [x] Dark mode validado — usa só tokens shadcn semânticos (`fill-primary/15` funciona em ambos)
- [x] Responsividade `flex flex-col md:flex-row`
- [x] Estados cobertos: default + empty (chart) + empty (tabela) — loading/error/validation pendentes (§10)
- [x] Bundle Inertia: `npm run build:inertia` + `Pages/Jana/Admin/Custos/Index` no manifest
- [x] Multi-tenant: Service filtra por `c.business_id` no join (Service linha 45)
- [x] Permissão: middleware `can:copiloto.admin.custos.view`
- [x] Whitelist defensiva no preset (Controller linha 36)

## 10. Pegadinhas

- ❌ **Sem skeleton durante `router.get`** — quando o usuário troca o preset, `preserveState: true` faz tela ficar "congelada" até o backend responder. Em range custom + business com muitas mensagens, pode levar 2-3s sem feedback. Adicionar `<Skeleton>` shadcn nos 4 KPIs + chart durante `useRoute().processing`.
- ❌ **Validação de range custom ausente** — front aceita `de > ate` (HTML não impede); backend `CustosService::resolverPeriodo()` aceita silenciosamente, retorna 0 dias. Adicionar `<form>` com `min={de}` no `Input` "Até" + check no submit.
- ❌ **`Math.max(0.01, ...valores)` no chart** — piso de R$ 0,01 evita div/0 mas distorce visualmente quando o gasto real é R$ 0 (cria área falsa de 1 cent). Pegadinha original: dia sem nenhum uso de IA mostra um "barzinho" enganoso. Fix: se `max === 0.01 && valores.every(v => v === 0)`, render empty state em vez de chart.
- ❌ **Cálculo R$ no PHP, não cacheado** — `CustosService::painel()` recalcula a cada request. Para business com 10k+ mensagens/mês + range 90d, é varredura grande. Considerar cache `Cache::remember(key($businessId, $inicio, $fim), 300, fn() => ...)` ou tabela materializada `mcp_usage_diaria` (já existe migration).
- ❌ **`pricing.modelo_default` é label estática, não o modelo REAL usado** — header diz "Modelo base: gpt-4o-mini" mas `jana_mensagens` não armazena `modelo` por linha hoje (CustosService linha 23 confirma: "enquanto não persistirmos `modelo` em jana_mensagens"). Se Wagner mudar o modelo no `.env`, custos passados ficam recalculados pelo modelo NOVO (errado historicamente). Migration pendente: `ALTER TABLE jana_mensagens ADD COLUMN modelo VARCHAR(64) NULL`.
- ❌ **Soma do `<tfoot>` mistura `por_usuario` (já filtrado por scope) com `kpis` direto** — linha 367-373: `conversas` soma `por_usuario`, mas `mensagens`/`tokens`/`custo_brl` lê `kpis.*`. Se `por_usuario.length === 0` mas `kpis.mensagens > 0` (caso patológico em que LEFT JOIN com `users` retornar 0 — usuário deletado), os totais inferiores divergem do superior. Unificar fonte.
- ❌ **`href` direto `/copiloto/admin/custos` no `router.get`** (linha 197) — hardcode da URL. Se ADR de naming reescrever a rota (cycle 2 considera `/jana/admin/custos`), quebra silenciosamente. Usar `route('jana.admin.custos.index')` via Ziggy.
- ❌ **`<Input type="date" required>` sem `lang="pt-BR"`** — em alguns navegadores (Firefox antigo), o picker abre em formato `YYYY-MM-DD` mas o usuário lê `dd/mm/yyyy`. Validar com Larissa em ROTA LIVRE.
- ❌ **`KpiCard tone="success"` SEMPRE verde** mesmo quando `kpis.custo_brl = 0` — visualmente sugere que zero gasto é "bom". Tecnicamente é, mas o card está fora do contexto de "alvo atingido". Considerar `tone="default"` quando `custo_brl === 0`.
- ⚠️ **`router.get` com `replace: true`** — não polui histórico, ótimo. Mas usuário NÃO consegue voltar pro filtro anterior com browser back. Decisão consciente — documentar pra suporte.
- ⚠️ **`new Date(iso + 'T00:00:00')` em `formatDataCurta`** — força meia-noite local, evita o bug clássico de timezone (data ISO `2026-05-09` virar `2026-05-08` em UTC-3). OK, mas confiar nisso depende de o backend sempre devolver `YYYY-MM-DD` puro (não datetime).

Pegadinhas genéricas em [`.claude/skills/cockpit-runbook/GOTCHAS.md`](../../../.claude/skills/cockpit-runbook/GOTCHAS.md).

## 11. ADR de origem

- [ADR 0026 — Posicionamento ERP Gráfico com IA](../../decisions/0026-posicionamento-erp-grafico-com-ia.md) — motivação (controlar CAC da Jana)
- [ADR 0035 — Stack AI canônica](../../decisions/0035-stack-ai-canonica-wagner-2026-04-26.md) — fonte do `pricing_default_model`
- [ADR 0094 — Constituição v2](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — §2 Tiered cost (esta tela é o painel de "tiered cost")
- Copiloto/adr/arq/0003 — Onda 1 (ROI direto): "primeira tela admin do Jana é custo, não feature"

**Stories cobertas:** US-COPI-070 ([SPEC.md](SPEC.md))
**Tests:** [Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php](../../../Modules/Jana/Tests/Feature/Admin/CustosControllerTest.php)
**Service:** [Modules/Jana/Services/CustosService.php](../../../Modules/Jana/Services/CustosService.php)

---

**Última atualização:** 2026-05-09
