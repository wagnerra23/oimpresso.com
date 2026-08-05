---
slug: governance-runbook-audit
title: "Governance — Runbook da tela Audit Log (/governance/audit)"
type: runbook
module: Governance
tela: governance/Audit
owner: W
status: rascunho
last_validated: "2026-08-05"
related_adrs:
  - 0079-constituicao-oimpresso-7-camadas-governanca
  - 0084-triggers-mysql-imutabilidade-mcp-audit-log
  - 0086-fase-5-mvp-governance-actiongate-warn
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0366-fronteira-jana-forja-governance-kb
---

# RUNBOOK — `/governance/audit` (Audit Log)

> **Origem desta versão (recibo, não afirmação atemporal):** escrito em **2026-08-05** por
> **leitura do código** (`AuditController` · `AuditDrillDownService` · `Http/routes.php` ·
> `Audit.tsx` · `Audit.charter.md`), pra destravar a ligação da strip `GovernancaSubNav`
> nesta tela — o hook `block-mwart-violation.mjs` (ADR 0104 §F1) exige RUNBOOK antes de
> Edit em `Pages/**/<Tela>.tsx`, e a lacuna era **pré-existente**.
>
> `status: rascunho` é literal: **nenhum passo abaixo foi EXECUTADO** nesta sessão
> (Pest só roda no CT 100; smoke prod não foi feito). O que está escrito veio do código;
> o que não foi possível derivar está marcado **⬜ ABERTO**, nunca preenchido por palpite.

---

## 0. Estado final esperado

| Verificação | Como conferir |
|---|---|
| Rota responde 200 pra usuário autenticado | `curl -sv https://oimpresso.com/governance/audit` (hop final `200`, não `302 /login`) |
| Strip de sub-navegação aparece com `Audit log` ativo | Browser MCP · screenshot · ghost `audit` destacado |
| Filtros aplicam sem full-page reload | DevTools → Network → request `X-Inertia: true` com `only=entries,kpis,filters` |
| Tabela respeita o teto de 200 linhas | Rodapé literal "Limit 200 entries por query" + `AuditDrillDownService::getRecentEntries(200, …)` |

---

## 1. Objetivo

Drill-down forense do `mcp_audit_log` — **Constituição Art. 9** ([ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md)).
Responde "quem chamou qual endpoint do MCP, com qual tool/resource, com que status e em
quanto tempo", numa janela de tempo escolhida.

**Read-only por lei:** a tabela é append-only enforçada por **trigger MySQL**
([ADR 0084](../../decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md)) — UPDATE/DELETE
numa entry é incidente P0, não bug de UI.

Layout: `AppShellV2` (`Audit.layout`), header `@/Components/shared/PageHeader` (congelado — ver §10).

---

## 2. Pré-condições

- Módulo `Governance` instalado e `governance_module` ativo no pacote da subscription do business
  (UI `/superadmin/packages/{id}/edit` — **nunca** hardcode `business_id`).
- Permission Spatie `governance.dashboard.view` no usuário — é o gate que faz a **entry de
  sidebar** aparecer (`DataController::modifyAdminMenu`, Gate 2). Sem ela a strip some (o
  `GovernancaSubNav` retorna `null` porque `shell.menu` não traz a entry).
- Tabelas `mcp_audit_log` e `mcp_actors` existentes e populadas (senão a tela renderiza o
  `EmptyState`, o que é comportamento correto, não falha).
- Middlewares da rota (já aplicados no grupo): `web`, `authh`, `auth`, `SetSessionData`,
  `language`, `timezone`, `AdminSidebarMenu`, `CheckUserLogin` + `throttle:30,1`.

---

## 3. Passo-a-passo

1. **Abrir a rota** `/governance/audit` — nomeada `governance.audit.index`
   ([`Modules/Governance/Http/routes.php`](../../../Modules/Governance/Http/routes.php)).
2. **Controller monta 5 props** (`AuditController::index`):
   `entries` e `kpis` **eager**; `available_endpoints` e `available_actors` como **closures**;
   `filters` com o payload cru do request.
3. **Filtros** saem do request com defaults: `period='24h'`, `actor`/`endpoint`/`status` `null`.
4. **Service resolve o período** (`cutoffFor`): `1h|24h|7d|30d`, `default => now()->subDay()`.
5. **Actor é resolvido por slug→user_id** via `mcp_actors` (`whereNull('revoked_at')`).
   Slug inexistente vira `whereRaw('1=0')` — **zero resultados, fail-safe**, nunca "todos".
6. **Query final:** `orderByDesc('ts')->limit(200)` com `ts as created_at`.
7. **Front aplica filtro** com `router.get('/governance/audit', …, { only: ['entries','kpis','filters'] })` —
   as duas closures ficam de fora de propósito (não mudam com filtro).
8. **Conferir o resultado no browser** (obrigatório antes de declarar pronto — R1 do
   PROTOCOLO-WAGNER-SEMPRE): screenshot + console limpo.

---

## 4. Tokens CSS

- Cor **sempre semântica**, nunca crua nova: a tela usa `bg-emerald-100/text-emerald-700`
  (ok) e `bg-red-100/text-red-700` (erro) centralizados no helper `statusColor()` —
  se precisar mudar cor de status, muda **no helper**, não no JSX.
- Superfície/neutros por par claro/escuro: `bg-zinc-50 dark:bg-zinc-900`,
  `border-zinc-200 dark:border-zinc-700`.
- KPI **não** carrega cor à mão: `<KpiCard tone="info|warning|success">` decide.

---

## 5. Estados visuais

| Estado | Onde | Comportamento real no código |
|---|---|---|
| Vazio | `entries.length === 0` | `<EmptyState icon="info" title="Sem entries" …>` |
| Erro (linha) | `status !== 'ok'` | `<Badge variant="outline">` vermelho via `statusColor()` |
| Hover linha | `<tr>` | `hover:bg-zinc-50 dark:hover:bg-zinc-900` |
| Sem valor | `user_id`/`tool_or_resource`/`duration_ms` nulos | render literal `—` |
| Loading | — | ⬜ **ABERTO**: não há skeleton; o partial reload troca o conteúdo sem placeholder |

---

## 6. Responsividade

- Container `mx-auto max-w-7xl p-6`.
- Filtros: `grid-cols-1 md:grid-cols-4` (empilham abaixo de `md`).
- Tabela em `overflow-x-auto` — rola dentro do card, o body nunca rola na horizontal.
- ⬜ **ABERTO**: sem teste em 1280px (monitor da Larissa) registrado pra esta tela.

---

## 7. Atalhos

- Herdados do shell: `G G` abre Governança (declarado em `DataController::modifyAdminMenu`),
  `P` é o `shortcut` do primary "Gerenciar policies".
- ⬜ **ABERTO**: a tela **não** registra atalho próprio (nenhum `useEffect` de `keydown` em `Audit.tsx`).

---

## 8. Component contract

```ts
interface Entry { id: number; user_id: number | null; business_id: number | null;
  endpoint: string; tool_or_resource: string | null; status: string;
  duration_ms: number | null; created_at: string }
interface Actor { slug: string; display_name: string }

interface Props {
  entries: Entry[]
  kpis: { total: number; errors: number; unique_users: number }
  filters: { period: string; actor: string | null; endpoint: string | null; status: string | null }
  available_endpoints: string[]
  available_actors: Actor[]
}
```

Sub-navegação: `<GovernancaSubNav active="audit" />` — a key `audit` é a declarada nos
`ghosts` do `DataController`. **A lista de ghosts é a fonte única**; não duplicar aqui.

---

## 9. DoD checklist

- [ ] `npx tsc --noEmit` não aumenta os erros pré-existentes de `Pages/governance`
- [ ] `npm run lint:baseline:check` verde
- [ ] `npm run a11y:check` verde
- [ ] `npm run layout:check` verde
- [ ] Strip renderiza com `audit` ativo (screenshot)
- [ ] Filtros continuam em partial reload (`only` intacto)
- [ ] Nenhum `UPDATE`/`DELETE` introduzido contra `mcp_audit_log`
- [ ] Pest de Governance verde **no CT 100** (nunca local)

---

## 10. Pegadinhas

1. **Header congelado.** Esta tela usa `@/Components/shared/PageHeader`, não o canon
   `@/Components/PageHeader`. Trocar é migração de header — exige aprovação visual por tela,
   PR próprio. Não fazer "de passagem".
2. **`SelectItem` com `value=""` quebra a tela inteira** (Radix lança e o render morre —
   lápide §5 2026-06-29, tela branca em `/governance/audit`). Os `.filter(Boolean)` /
   `.filter((a) => a.slug)` antes dos `.map` **são a defesa** — não remover. Sentinela `ALL='__all__'`
   cobre só a opção fixa "Todos", não os vazios do dado.
3. **`entries` é buscado 2×** por render: `buildEntriesPayload` e `buildKpisPayload` chamam
   `getRecentEntries` cada um. Custo conhecido, não é bug novo — mas quem mexer em KPI
   deve saber que não há memoização entre os dois.
4. **`Inertia::defer` foi revertido de propósito** aqui (comentário `ROLLBACK Wave W7 #953`
   no controller): defer quebrava Pages que esperam props eager. Não "re-otimizar" sem ler o #953.
5. **Filtro de actor falha fechado.** Slug inválido → `1=0`. Se alguém "consertar" isso pra
   ignorar o filtro, a tela passa a mostrar tudo quando o operador pediu um recorte — pior
   que vazio.
6. ⬜ **ABERTO — multi-tenant.** O charter §Goals afirma *"query scopada pelo `business_id`
   do usuário autenticado"*. **Medido no código hoje**: `getRecentEntries` seleciona a coluna
   `business_id` mas **não filtra por ela**; a rota também não aplica `can:governance.audit.view`
   (só `auth`). Não estou chamando isso de bug — `mcp_audit_log` é tabela de governança do
   MCP e o recorte pode ser intencional. **É decisão [W]**: ou o charter descreve a intenção
   e o código precisa do scope, ou o charter é que precisa ser corrigido. Registrar antes de
   qualquer mudança de comportamento (Tier 0 — ADR 0093).

---

## 11. ADR de origem

- [ADR 0079](../../decisions/0079-constituicao-oimpresso-7-camadas-governanca.md) — Constituição, Art. 9 (Auditoria).
- [ADR 0084](../../decisions/0084-triggers-mysql-imutabilidade-mcp-audit-log.md) — trigger MySQL torna `mcp_audit_log` append-only.
- [ADR 0086](../../decisions/0086-fase-5-mvp-governance-actiongate-warn.md) — Governance Fase 5 MVP (origem da tela).
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2.
- [ADR 0366](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — fronteira Jana/Governança: por que a Governança voltou a ter sidebar + strip próprias.
- [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) — o processo que exige este arquivo existir.
