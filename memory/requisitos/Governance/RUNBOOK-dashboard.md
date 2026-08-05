---
title: "Governance — Runbook da tela Painel (/governance/dashboard)"
module: Governance
tela: governance/Dashboard
owner: W
status: ativo
last_validated: "2026-08-05"
preconditions:
  - "Módulo Governance instalado (`governance_module` no pacote da subscription)"
  - "Permissão Spatie `governance.dashboard.view` no role (gate da entry de sidebar)"
  - "Permissão Spatie `jana.mcp.usage.all` no role, para ver a seção Governança MCP"
  - "Tabelas `mcp_memory_documents`, `mcp_audit_log`, `mcp_governance_rules`, `mcp_actors`, `mcp_skill_versions` presentes"
related_adrs:
  - 0366-fronteira-jana-forja-governance-kb
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0053-mcp-server-governanca-como-produto
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0110-cockpit-pattern-v2-canon-list-detail
---

# RUNBOOK — `/governance/dashboard` (Painel consolidado)

> **Tela canônica greenfield** Inertia React (não é migração MWART Blade→Inertia — não existe Blade
> legado desta tela). Override implícito por ser greenfield, igual ao `RUNBOOK-module-grades.md`.
>
> ⚠️ **`last_validated` é a data de REDAÇÃO deste runbook**, não de execução do §5. O smoke real
> (R1 do PROTOCOLO-WAGNER-SEMPRE) da seção MCP **ainda não rodou** — depende do merge + deploy.
> Quem rodar o §5 e bater, atualiza o campo. Ler "validado" aqui antes disso é ler errado.

## 1. Objetivo

Painel único onde [W] opera governança em ~5min/dia. Consolida **cinco** blocos de leitura:

| Bloco | O que responde | Fonte |
|---|---|---|
| Constituição | quantos ADRs/policies/actors/audit-highlights pendem | `mcp_memory_documents`, `mcp_governance_rules`, `mcp_actors`, `mcp_skill_versions`, `mcp_audit_log` |
| SDD (ADR 0275) | a composta do scorecard subiu ou caiu | `mcp_sdd_scorecard_history` |
| Saúde do ecossistema | fila morreu? custo de IA disparou? Brain A narrou o quê? | `failed_jobs`, `jana_mensagens`, `jana_health_narratives` |
| Listas 24h | ADRs aguardando, audit highlights, narrativas | idem acima |
| **Governança MCP** | consumo cross-team do MCP server (calls, latência, custo, RBAC negado, top tools/users) | `mcp_audit_log` via `Modules\Jana\Services\GovernancaService` |

O 5º bloco **chegou em 2026-08-05** pela [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md)
§D-B/§D-C item 1: era a tela `Jana/Admin/Governanca/Index` (`/ia/admin/governanca`) e é a
**sobreposição #4** que a ADR mandou fundir. A pergunta que ela responde — *"a regra está sendo
cumprida?"* — é a do Governance, não a do Jana.

## 2. Pré-condições

- [ ] Módulo `Governance` instalado em `/manage-modules` + `governance_module` marcado no pacote
      (`/superadmin/packages/{id}/edit`, com "Atualizar inscrições existentes")
- [ ] Permissão `governance.dashboard.view` no role — **gate da entry de sidebar**
      (`Modules/Governance/Http/Controllers/DataController::modifyAdminMenu`)
- [ ] Permissão `jana.mcp.usage.all` no role — **gate da seção MCP dentro do painel**
- [ ] Rota `GET /governance/dashboard` → `DashboardController@index`, nome `governance.admin.dashboard.legacy`
      (a raiz `/governance` é `redirect('/ia', 302)` desde 2026-05-22)
- [ ] `Modules/Jana` instalado — o `GovernancaService` continua morando lá ([ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md)
      §D-C: o item 4, mover as `Mcp*` pro Forja, **não** está autorizado; só o consumo mudou de dono)

## 3. Passo-a-passo

### 1. Rota e permissões

```bash
php artisan route:list --path=governance/dashboard
# GET|HEAD  governance/dashboard  governance.admin.dashboard.legacy  DashboardController@index
```

⚠️ **A rota é gateada só por `auth`** (mais `SetSessionData`/`CheckUserLogin`/`throttle:60,1`).
`governance.dashboard.view` **não** é middleware da rota — hoje ele só decide se a entry aparece na
sidebar. Isso é estado de fato, não recomendação: transformar em `can:` na rota é decisão [W] à parte
(mudaria `Modules/Governance/Http/routes.php`, com risco de trancar quem hoje entra por URL).

### 2. Controller — props eager vs deferred

```php
// Modules/Governance/Http/Controllers/DashboardController.php
return Inertia::render('governance/Dashboard', [
    'kpis'             => $this->buildKpisPayload(),      // eager (5 counts)
    'pending_adrs'     => ...,                            // eager
    'audit_highlights' => ...,                            // eager
    'health_kpis'      => $health['kpis'],                // eager
    'narratives'       => $health['narratives'],          // eager
    'sdd'              => Inertia::defer(fn () => ...),   // deferred
    'mcp_enabled'      => $mcpVisivel,                    // eager (bool)
    'mcp_filters'      => $mcpFilters,                    // eager (só request, zero I/O)
    'mcp'              => Inertia::defer(fn () => ...),   // deferred — SÓ quando $mcpVisivel
]);
```

**Validação:** `curl` autenticado em `/governance/dashboard` com `X-Inertia: true` → o JSON
**não** traz `mcp` na primeira resposta (é deferred); traz `mcp_enabled` e `mcp_filters`.

### 3. Seção MCP — filtro de período por query string

Prefixo `mcp_` para não colidir com filtro de outra seção do painel:

```
/governance/dashboard?mcp_preset=7d
/governance/dashboard?mcp_preset=custom&mcp_de=2026-07-01&mcp_ate=2026-07-31
```

Whitelist server-side (`hoje|ontem|7d|30d|mes_anterior|custom`); valor fora da lista, ou `custom`
sem as duas pontas em `YYYY-MM-DD`, degrada pra `30d` — nunca 500.

**Validação:** `?mcp_preset=lixo` → painel abre em "Últimos 30 dias" sem erro.

### 4. Partial reload ao trocar o período

```tsx
router.get('/governance/dashboard',
  { mcp_preset: p, mcp_de: de, mcp_ate: ate },
  { only: ['mcp', 'mcp_filters'], preserveState: true, preserveScroll: true, replace: true });
```

`only:` mantém as outras 8 props intactas — trocar o período **não** re-roda os KPIs da
Constituição nem o scorecard SDD.

**Validação:** DevTools → Network → trocar "Hoje": request com `X-Inertia-Partial-Data: mcp,mcp_filters`.

### 5. `<Deferred>` obrigatório no frontend

```tsx
{mcp_enabled && (
  <Deferred data="mcp" fallback={<p className="text-sm text-muted-foreground">Carregando…</p>}>
    {mcp ? <McpSecao mcp={mcp} … /> : <EmptyState … />}
  </Deferred>
)}
```

**Validação:** desligar `jana.mcp.usage.all` do role → a seção some inteira (título incluso), sem
buraco no layout e sem erro no console.

### 6. Sub-navegação do módulo

`<GovernancaSubNav active="dashboard" />` como **primeiro filho** do container. A lista de ghosts é
lida de `shell.menu` (populado pelo `DataController::modifyAdminMenu`) — **não** duplicar a lista na tela.

**Validação:** a aba "Painel" aparece marcada; as outras (Policies/Audit/Drift/Module Grades/DS Rollout)
navegam.

## 4. Estados visuais

| Estado | Trigger | Como aparece |
|---|---|---|
| `loading` | primeira pintura da seção MCP | fallback do `<Deferred>` (texto curto, sem layout shift) |
| `loading` (refiltro) | trocar o período | dados antigos permanecem + rótulo "atualizando…" (nunca tela em branco) |
| `empty` | período sem chamada MCP | `<EmptyState>` em cada bloco (gráfico, status, denied, tools, users) |
| `hidden` | sem `jana.mcp.usage.all` | seção inteira ausente — nem título, nem prop |
| `degraded` | `mcp_audit_log` ausente (dev/test) | `mcp` volta `null` → `<EmptyState>` explicativo |
| `error` | falha no Service | página 500 do Inertia (não há try/catch por design — falha é sinal) |

## 5. Estado final esperado (o smoke)

| Verificação | Como conferir |
|---|---|
| Painel abre | login [W] → `https://oimpresso.com/governance/dashboard` → 200 |
| Strip de sub-nav | aba "Painel" ativa; 5 irmãs navegam |
| Blocos herdados intactos | Constituição (6 KPIs), SDD, Saúde (3 KPIs), 3 cards, atalhos, docs |
| Seção MCP presente | h2 "Governança MCP" + 4 KPIs + abas Consumo/Acesso/Usuários |
| Período aplica | escolher "Hoje" → só `mcp` recarrega (Network) |
| Range custom | `custom` + duas datas → label do período muda |
| Sem permissão | role sem `jana.mcp.usage.all` → seção some, resto igual |
| 0 erro no console | DevTools → Console limpo (o incidente 2026-05-25 era `TypeError undefined.find`) |
| Dark mode | toggle → gráfico e barras seguem legíveis (tokens, não cor crua) |

## 6. Component contract

```ts
interface Props {
  kpis: { pending_adrs; active_policies; skill_approvals; actors_registered; audit_highlights; compliance_pct }
  pending_adrs: Adr[]
  audit_highlights: AuditEntry[]
  actiongate_mode: 'off' | 'warn' | 'strict'
  next_review_at: string
  health_kpis: HealthKpis
  narratives: Narrative[]
  sdd?: SddPayload | null              // deferred
  mcp_enabled: boolean                 // eager
  mcp_filters: { preset: McpPreset; de: string | null; ate: string | null }   // eager
  mcp?: McpPayload | null              // deferred — ausente quando !mcp_enabled
}
```

`McpPayload` = o retorno de `GovernancaService::painel()`: `kpis`, `por_status`, `latency`,
`top_tools`, `top_users`, `denied_por_codigo`, `serie_diaria`, `periodo`.

**Componentes shared usados:** `PageHeader`, `KpiGrid`, `KpiCard`, `EmptyState`, `SubNav`
(`variant="segmented"`), `GovernancaSubNav` (`Pages/governance/_shared`).

## 7. Pegadinhas

- ❌ **NÃO desestruturar prop deferred direto** — foi exatamente o incidente de 2026-05-25 na tela
  original (`Governanca/Index.tsx` fazia `por_status.find(...)` sem `<Deferred>` → `TypeError
  undefined.find` em **prod**, e o fix foi *remover* o defer). Aqui o defer volta **com** o wrapper:
  quem consumir `mcp` fora do `<Deferred>` reabre o mesmo bug.
- ❌ **NÃO usar cor crua do Tailwind** (`bg-emerald-500`, `stroke-amber-400`) — a tela original usava,
  mas em `Pages/**` o `ds/no-raw-palette-color` conta como regressão no `lint:baseline:check`. Tokens:
  `bg-success` / `bg-warning` / `bg-destructive` / `stroke-primary`.
- ❌ **NÃO passar `href` pro `<KpiCard>`** — a prop não existe no componente; os 2 usos atuais no
  Dashboard estão no baseline do tsc e não podem ganhar irmãos.
- ❌ **NÃO usar `route()` no React** — Ziggy não está disponível; URL literal `/governance/dashboard`.
- ❌ **NÃO hand-rolar `role="tablist"`** — `ds/no-inline-tablist`. Switch in-page controlado = `<SubNav>`.
- ❌ **`mcp_audit_log` é append-only** ([ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md)) —
  nunca DELETE/UPDATE, nem "pra limpar teste".
- ❌ **NÃO mover o `GovernancaService` pro Governance** — a [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md)
  §D-C item 4 (mover as `Mcp*`) **não está autorizada**; o destino declarado é o **Forja**, não este
  módulo. Consumir cross-módulo tem precedente (`Modules/Forja/.../RoadmapController` usa `McpTask`).
- ❌ **NÃO cachear KPI agregando businesses** — as `mcp_*` são cross-tenant **por design** (exceção
  formal ao Tier 0, Constituição Art. 6+8, coberta por `CrossTenantPolicyTest`). Não inventar
  `business_id` onde a tabela não tem.

## 8. ADR de origem

- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira dos 4 módulos; §D-B manda esta fusão
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Fase 5 MVP do módulo
- [ADR 0053](../../decisions/0053-mcp-server-governanca-como-produto.md) — `mcp_audit_log` + RBAC do MCP
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (os checks)
- [ADR 0275](../../decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md) — card SDD
- [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) — Cockpit Pattern V2 (KpiCard/KpiGrid)

---

**Última atualização:** 2026-08-05 — nascimento do runbook + fusão da Governança MCP (ADR 0366 §D-C item 1).
