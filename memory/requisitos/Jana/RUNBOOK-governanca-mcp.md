---
slug: copiloto-runbook-governanca-mcp
title: "Jana — Runbook da tela Governança MCP"
type: runbook
module: Jana
status: active
date: 2026-05-05
---

# RUNBOOK — Governança MCP

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0039](../../decisions/0039-ui-chat-cockpit-padrao.md), [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md), [_DS ADR 0008](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
> **Validado:** portado Cockpit em 2026-05-05

Tela de observabilidade admin do MCP server `mcp.oimpresso.com`. Exibe KPIs de consumo cross-team (calls, latência, custo, taxa de sucesso), gráfico temporal, distribuição por status, denied por error_code e ranking de top tools/users. Acessível apenas para Wagner/superadmin (`copiloto.mcp.usage.all`). Não tem contexto vinculado a entidade específica — coluna direita "Apps Vinculados" omitida (ADR 0039 §3 — sem context vinculado, coluna some).

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| Tela renderiza em `/copiloto/admin/governanca` | Login Wagner → URL → 4 KPI cards + gráfico + tabelas |
| AppShellV2 envolvendo | DevTools: `[data-slot="app-shell-v2"]` ao redor da Page |
| Filtro de período persiste no reload | Selecionar "Últimos 30 dias" → F5 → preset mantido (`localStorage["oimpresso.copiloto.governanca.preset"]`) |
| Atalho `/` foca o Select de período | Foco fora de input → pressionar `/` → SelectTrigger recebe foco |
| `StatusBadge kind="mcp_status"` renderiza | Card "Distribuição por status" → badges emerald/amber/rose |
| `EmptyState` aparece em dados vazios | Selecionar "Hoje" com zero calls → empty states com ícone + texto |
| Dark mode funciona | Toggle dark → gráfico SVG usa `stroke-amber-300` na linha denied |

## 1. Objetivo

Dashboard de governança para Wagner acompanhar consumo do MCP server em tempo real. Mostra 4 KPIs principais, série diária de chamadas (SVG inline), distribuição por status com barra de progresso, denied agrupado por error_code (debug RBAC) e rankings de top tools/resources e top users. Filtro de período: presets fixos (hoje/ontem/7d/30d/mês anterior) + range customizado. Dentro do layout Cockpit 3 colunas via `AppShellV2` (sidebar 260 / main 1fr / sem coluna direita — tela admin sem contexto vinculado).

## 2. Pré-condições

- [ ] Módulo `Jana` instalado em `/manage-modules`
- [ ] Permissão `copiloto.mcp.usage.all` atribuída ao role do usuário (somente Wagner/superadmin)
- [ ] Rota registrada em [`Modules/Jana/Routes/web.php`](../../../Modules/Jana/Routes/web.php) como `GET /copiloto/admin/governanca`
- [ ] Page Inertia em [`resources/js/Pages/Jana/Admin/Governanca/Index.tsx`](../../../resources/js/Pages/Jana/Admin/Governanca/Index.tsx)
- [ ] Tabela `mcp_audit_log` existindo e com dados (ADR 0053)
- [ ] Skill irmã `copiloto-arch` carregada se for mexer na lógica de métricas

## 3. Passo-a-passo

### 1. Confirmar rota e controller

```php
// Modules/Jana/Routes/web.php
Route::prefix('copiloto/admin')
    ->middleware(['web', 'auth', 'copiloto.mcp.usage.all'])
    ->group(function () {
        Route::get('governanca', [GovernancaController::class, 'index'])
             ->name('copiloto.admin.governanca');
    });
```

**Validação:** `php artisan route:list --name=copiloto.admin.governanca` — retorna a rota.

### 2. Controller — query + Inertia render

```php
// Modules/Jana/Http/Controllers/Admin/GovernancaController.php
public function index(Request $request): Response
{
    $preset  = $request->input('preset', '7d');
    $periodo = $this->calcularPeriodo($preset, $request->input('de'), $request->input('ate'));

    return Inertia::render('Jana/Admin/Governanca/Index', [
        'kpis'              => $this->kpis($periodo),
        'por_status'        => $this->porStatus($periodo),
        'latency'           => $this->latency($periodo),
        'top_tools'         => $this->topTools($periodo),
        'top_users'         => $this->topUsers($periodo),
        'denied_por_codigo' => $this->deniedPorCodigo($periodo),
        'serie_diaria'      => $this->serieDiaria($periodo),
        'periodo'           => $periodo,
        'filters'           => ['preset' => $preset, 'de' => $request->input('de'), 'ate' => $request->input('ate')],
    ]);
}
```

**Validação:** `curl -s https://oimpresso.com/copiloto/admin/governanca` com cookie de autenticação → retorna JSON Inertia com `component: "Jana/Admin/Governanca/Index"`.

### 3. Page Inertia — Persistent Layout

```tsx
// resources/js/Pages/Jana/Admin/Governanca/Index.tsx

GovernancaIndex.layout = (page: ReactNode) => (
  <AppShellV2
    title="Jana — Governança MCP"
    breadcrumbItems={[{ label: 'Jana' }, { label: 'Governança MCP' }]}
  >
    {page}
  </AppShellV2>
);

export default GovernancaIndex;
```

**Validação:** navegar entre páginas → breadcrumb troca sem reload total do shell.

### 4. Filtro de período + persistência localStorage

```tsx
const LS_PRESET_KEY = 'oimpresso.copiloto.governanca.preset';

// Persiste preset (ADR 0039 §4)
useEffect(() => {
  if (filters.preset !== 'custom') {
    localStorage.setItem(LS_PRESET_KEY, filters.preset);
  }
}, [filters.preset]);

// Aplicar filtro sem reload total
const aplicar = (patch: Partial<Filters>) => {
  router.get('/copiloto/admin/governanca', { ...filters, ...patch }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};
```

**Validação:** selecionar "Últimos 30 dias" → F5 → `localStorage["oimpresso.copiloto.governanca.preset"]` = `"30d"`.

### 5. Atalho `/` para focar o filtro de período

```tsx
const selectRef = useRef<HTMLButtonElement>(null);

useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
    if (e.key === '/') {
      e.preventDefault();
      selectRef.current?.focus();
    }
  };
  window.addEventListener('keydown', handler);
  return () => window.removeEventListener('keydown', handler);
}, []);

// No JSX:
<SelectTrigger ref={selectRef}><SelectValue /></SelectTrigger>
```

**Validação:** foco fora de inputs → `/` → SelectTrigger fica destacado com ring de foco.

### 6. StatusBadge para status MCP

Adicionar mapping `mcp_status` em `StatusBadge.tsx`:

```tsx
// resources/js/Components/shared/StatusBadge.tsx — dentro de mappings
mcp_status: {
  ok:             { variant: 'default',     label: 'ok',             className: 'bg-emerald-600 hover:bg-emerald-700' },
  denied:         { variant: 'default',     label: 'denied',         className: 'bg-amber-600 hover:bg-amber-700' },
  error:          { variant: 'destructive', label: 'error' },
  quota_exceeded: { variant: 'default',     label: 'quota_exceeded', className: 'bg-orange-600 hover:bg-orange-700' },
},
```

```tsx
// No Card "Distribuição por status"
<StatusBadge kind="mcp_status" value={s.status} className="font-mono text-xs" />
```

**Validação:** Card "Distribuição por status" → badges com cores corretas por tipo.

### 7. EmptyState shared para seções sem dados

```tsx
import EmptyState from '@/Components/shared/EmptyState';

// Exemplo — Card denied
{denied_por_codigo.length === 0 ? (
  <EmptyState
    icon="shield-check"
    title="Nenhum denied no período"
    description="Todas as chamadas passaram nas políticas de acesso."
    variant="success"
    className="py-6"
  />
) : (
  <table>...</table>
)}
```

**Validação:** selecionar "Hoje" com zero denied → EmptyState verde com ícone shield-check.

### 8. Cores no SVG do gráfico

```tsx
// Linha denied — trocar stroke-yellow-500 por token dark-mode-safe
<polyline
  points={deniedPts}
  fill="none"
  className="stroke-amber-400 dark:stroke-amber-300"
  strokeWidth={1.5}
  strokeDasharray="4 2"
/>

// Legenda
<span className="inline-block w-3 h-0.5 bg-amber-400 dark:bg-amber-300" />
```

**Validação:** toggle dark mode → linha denied muda de amber-400 para amber-300.

## 4. Tokens CSS

| Token | Onde aplica | Esta tela usa? |
|---|---|---|
| `--bg`, `--bg-2` | Fundo da viewport | ✅ (via AppShellV2) |
| `--panel`, `--panel-2` | Cards | ✅ |
| `--border`, `--border-2` | Bordas + dividers | ✅ |
| `--text`, `--text-mute` | Texto primário/secundário | ✅ |
| `--accent`, `--accent-2`, `--accent-soft` | Linha principal do gráfico | ✅ (`stroke-primary`) |
| `--origin-OS-{bg,fg}` | Tag OS | ❌ (não aplicável) |
| `--origin-CRM-{bg,fg}` | Tag CRM | ❌ (não aplicável) |
| `--origin-FIN-{bg,fg}` | Tag Financeiro | ❌ (não aplicável) |
| `--origin-PNT-{bg,fg}` | Tag Ponto | ❌ (não aplicável) |
| `--row-h`, `--card-pad`, `--card-gap` | Densidade | ✅ (via KpiGrid) |

**Tokens shadcn semânticos usados:** `bg-muted`, `text-muted-foreground`, `border-border`, `bg-primary`, `fill-primary/15`, `text-foreground`.

**Exceção documentada:** barras de status (`bg-emerald-500`, `bg-amber-500`, `bg-rose-500`, `bg-orange-500`) — cores de status semântico, permitidas por GOTCHAS.md §Tokens.

## 5. Estados visuais

| Estado | Trigger | Tokens / classes | Notas |
|---|---|---|---|
| `default` | — | `bg-panel border-border` | Cards renderizam KPIs e tabelas |
| `hover` | mouse-over nas linhas | `hover:bg-muted/40` | Rows de top_tools e top_users |
| `focus` | Tab / `/` atalho | `focus-visible:ring-2` | SelectTrigger do período |
| `active` | — | — | Nenhum elemento toggleável |
| `disabled` | — | — | Nenhum elemento desabilitável |
| `loading` | — | — | Dados chegam via SSR (sem loading state client-side) |
| `empty` | Sem dados no período | `<EmptyState/>` | 5 seções com EmptyState próprio |
| `error` | Falha no controller | — | Tratado pelo Inertia (500 page) |

## 6. Responsividade

| Breakpoint | Largura | Comportamento |
|---|---|---|
| `default` | <768px | KpiGrid empilha em 1 col; filtro em coluna; cards Linha 2/3 em 1 col |
| `md` | ≥768px | KpiGrid 2 cols; filtro em linha; Cards Linha 2/3 em 2 cols side-by-side |
| `lg` | ≥1024px | Layout estável; sidebar Cockpit visível |
| `xl` | ≥1280px | KpiGrid 4 cols; gráfico SVG full-width com overflow-x-auto |
| `2xl` | ≥1536px | Igual xl — tela não muda comportamento |

**Nota:** coluna direita omitida (sem contexto vinculado) — main ocupa `1fr` inteiro em todos os breakpoints.

## 7. Atalhos

| Tecla | Ação | Escopo | Listener |
|---|---|---|---|
| `⌘K` / `Ctrl+K` | Busca global | Shell (não a tela) | Já no AppShellV2 |
| `J` | — | — | Tela não implementa (sem lista navegável) |
| `K` | — | — | Tela não implementa |
| `E` | — | — | Tela não implementa |
| `A` | — | — | Tela não implementa |
| `N` | — | — | Tela não implementa (dashboard read-only) |
| `/` | Focar Select de período | Tela inteira (fora de inputs) | `useEffect` + `selectRef` |

```tsx
useEffect(() => {
  const handler = (e: KeyboardEvent) => {
    if (e.target instanceof HTMLInputElement || e.target instanceof HTMLTextAreaElement) return;
    if (e.key === '/') {
      e.preventDefault();
      selectRef.current?.focus();
    }
  };
  window.addEventListener('keydown', handler);
  return () => window.removeEventListener('keydown', handler);
}, []);
```

## 8. Component contract

Props que a Page recebe via `Inertia::render('Jana/Admin/Governanca/Index', [...])`:

```tsx
interface Props {
  kpis: {
    total_calls: number;        // total de chamadas MCP no período
    usuarios_ativos: number;    // usuários únicos que chamaram o MCP
    custo_total: number;        // custo em R$ (soma dos tokens × preço)
    tokens_total: number;       // total de tokens consumidos
    latency_avg_ms: number;     // latência média em ms
  };
  por_status: Array<{
    status: string;             // 'ok' | 'denied' | 'error' | 'quota_exceeded'
    calls: number;
    pct: number;                // 0-100 (pré-calculado no controller)
  }>;
  latency: {
    p50: number; p95: number; p99: number; max: number;  // ms
  };
  top_tools: Array<{ tool: string; calls: number; custo_brl: number; }>;
  top_users: Array<{ user_id: number; nome: string; calls: number; custo_brl: number; }>;
  denied_por_codigo: Array<{ error_code: string; calls: number; }>;
  serie_diaria: Array<{ data: string; calls: number; custo_brl: number; denied: number; }>;
  periodo: { inicio: string; fim: string; label: string; };
  filters: { preset: Preset; de: string | null; ate: string | null; };
}
```

**Componentes shared usados:**

- [`@/Components/shared/PageHeader`](../../../resources/js/Components/shared/PageHeader.tsx)
- [`@/Components/shared/KpiGrid`](../../../resources/js/Components/shared/KpiGrid.tsx)
- [`@/Components/shared/KpiCard`](../../../resources/js/Components/shared/KpiCard.tsx)
- [`@/Components/shared/StatusBadge`](../../../resources/js/Components/shared/StatusBadge.tsx) — kind `mcp_status`
- [`@/Components/shared/EmptyState`](../../../resources/js/Components/shared/EmptyState.tsx)

## 9. DoD checklist

- [x] Tela vive dentro de `AppShellV2` (Persistent Layout)
- [x] Tokens CSS do shell + shadcn semânticos (sem cor crua — R-DS-002)
- [x] Coluna direita omitida com justificativa (tela admin sem contexto vinculado — ADR 0039 §3)
- [x] Atalho `/` ativo para focar filtro (com `removeEventListener` no cleanup)
- [x] Preset persistido em `localStorage["oimpresso.copiloto.governanca.preset"]`
- [x] `StatusBadge kind="mcp_status"` adicionado ao shared
- [x] `EmptyState` shared usado em todas as seções sem dados (5 seções)
- [x] PT-BR em todo label/copy/comentário
- [ ] Dark mode validado manualmente (contraste ≥ 4.5:1)
- [ ] Responsividade: 375px, 768px, 1280px conferidos no navegador
- [ ] Bundle Inertia builda: `npm run build:inertia` + manifest confirma `Jana/Admin/Governanca/Index`
- [ ] Teste Pest `GovernancaControllerTest` passando

## 10. Pegadinhas

- ❌ **NÃO usar `route('copiloto.admin.governanca')` em React** — Ziggy não disponível. Sintoma: `route is not defined`. Fix: URL literal `/copiloto/admin/governanca` no `router.get()`.
- ❌ **NÃO usar `sessionStorage`** pra preservar preset — perde na nova aba. Sempre `localStorage` com prefixo `oimpresso.` (ADR 0039 §4).
- ❌ **NÃO usar `stroke-yellow-500` em SVG** — quebra dark mode (yellow-500 some em dark backgrounds). Usar `stroke-amber-400 dark:stroke-amber-300`.
- ❌ **NÃO chamar `statusBadgeClass()`** com strings inline — use `StatusBadge kind="mcp_status"` + adicione novo status ao mapping em `StatusBadge.tsx` se surgir novo valor no `mcp_audit_log`.
- ❌ **NÃO usar `window.location.reload()`** para reaplicar filtros — causa reload total do shell. Usar `router.get(..., { preserveState: true, preserveScroll: true })`.
- ❌ **`mcp_audit_log` é append-only** — nunca DELETE/UPDATE nessa tabela, mesmo pra "limpar dados de teste". Criar registros de teste com `business_id` separado.
- ❌ **Listener de atalho sem `removeEventListener`** — causa disparo em outras telas após navegação. O `useEffect` deve sempre retornar o cleanup.
- ❌ **NÃO tentar montar DataTable paginado** nas tabelas top_tools/top_users — são dados já agregados e pequenos (máx 20 linhas). `<table>` HTML simples com `hover:bg-muted/40` é o padrão correto aqui.

## 11. ADR de origem

- [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe 3 colunas; coluna direita omitida quando sem contexto vinculado
- [ADR 0053 — MCP Server Governança como Produto](../../decisions/0053-mcp-server-governanca-como-produto.md) — `mcp_audit_log` append-only + RBAC `copiloto.mcp.*` + endpoint canônico `mcp.oimpresso.com`
- [ADR 0011 — Padrãa Jana](../../decisions/0011-alinhamento-padrao-jana.md) — base estrutural UltimatePOS-like
- [_DS ADR 0008 — Cockpit layout-mãe](../_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)
- [_DS ADR UI-0023 — Sidebar preta (dark-fixo)](../_DesignSystem/adr/ui/0023-sidebar-dark-fixo-preto-definitivo-supersede-0019.md)

---

**Última atualização:** 2026-05-05
