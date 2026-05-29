---
page: /project-mgmt/triage
component: resources/js/Pages/ProjectMgmt/Triage/Index.tsx
owner: wagner
status: draft
last_validated: null
parent_module: ProjectMgmt
related_adrs: [0070, 0093, "UI-0013", 0039]
related_spec: memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md
stories: [US-TR-301, US-TR-302, US-TR-303]
tier: A
charter_version: 1
---

# Page Charter — /project-mgmt/triage

> **Status:** DRAFT (Onda 2, SPEC-UI-FASE7). Superfície humana da tool MCP `triage`.
> **PENDENTE:** smoke visual + aprovação SCREENSHOT do Wagner (ADR 0107/0114) antes de `status: live`.

---

## Mission

Dar ao time não-técnico (e futuros clientes B2B) uma tela dedicada pra **triar** tasks órfãs — atribuir dono + prioridade (+ cycle/epic opcional) inline, sem abrir cada task. A fila é a **mesma** da tool MCP `triage`: a UI não inventa query, consome o scope `McpTask::triage()` (owner NULL OU priority NULL OU status=backlog), excluindo done/cancelled.

---

## Goals — Features (faz)

- AppShellV2 + `<PageHeader>` canon (h1 + subtitle com contadores)
- `<KpiGrid>` + `<KpiCard>` 4 contadores: Pra triar / Sem dono / Sem prioridade / Em backlog
- Lista de tasks órfãs (paridade 1:1 com tool `triage`) ordenada por `created_at desc`
- **Atribuição inline** (US-TR-302): select de owner + select de prioridade direto na linha
- **Mover cycle/epic** (US-TR-303): dropdowns opcionais de cycle + epic na mesma linha
- UI **otimista** chamando `PATCH /triage/{id}/assign` → reusa `TaskCrudService::update` (mesma via que `tasks-update` MCP): gera `mcp_task_events` (assigned/field_updated) + notifica owner via `mcp_inbox_notifications`
- **Rollback** em erro (banner âmbar inline auto-dismiss 5s) + reconciliação via partial reload
- Task **some da lista** quando deixa de ser órfã (`still_triage=false` do backend)
- Chips de motivo por linha (sem dono / sem prioridade / backlog)
- Empty state: **"Nada pra triar 🎉"**
- Deep-link `display_id` → `/project-mgmt/board?task=ID` (abre DetailSheet)
- Polling 30s + on-focus reload (re-sincroniza fila)

---

## Non-Goals — Features (NÃO faz)

- ❌ Editar título/descrição/estimativa da task (isso é DetailSheet no Board)
- ❌ Mudar status arbitrário (Triage só atribui; status muda no Board/Backlog)
- ❌ Bulk-ops multi-seleção (defer ADR 0070 Tier 3 — fica no Backlog)
- ❌ Criar task nova (tasks-create via MCP / botão futuro)
- ❌ Escopo por `business_id` (mcp_tasks é governança GLOBAL repo-wide — ADR 0070/0093)
- ❌ Brain B / autonomia ADS

---

## UX Targets

- p95 first-paint < 1500ms (lista deferida via `Inertia::defer`)
- 0 erros JS console
- Atribuição reflete na UI < 100ms (otimista) e reconcilia < 1s
- Empty state aparece quando a fila zera (não tela em branco)
- Toque-friendly ≥ 360px (selects empilham em mobile com label visível)

---

## UX Anti-patterns

- ❌ Hex cru em badges priority/status (canon = tokens `PRIORITY_BADGE`)
- ❌ Modal pra atribuir (canon = select inline na linha)
- ❌ Recarregar a página inteira a cada atribuição (canon = partial reload `only:['tasks','kpis']`)
- ❌ `sessionStorage`
- ❌ Divergir da fila da tool `triage` (UI e tool devem dar a MESMA resposta)

---

## Multi-tenant (Tier 0 — ADR 0093)

`mcp_tasks` é **governança global** (sem `business_id` efetivo — ADR 0070). O escopo da Triage é **por-projeto** (`resolveProject`, default `COPI`), idêntico a Board/Backlog/MyWork — **não** por business. Board B2B multi-tenant entra só com 1º cliente (sinal qualificado ADR 0105).

---

## Refs

- [SPEC-UI-FASE7](../../../../../memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md) — US-TR-301..303
- [TriageTool MCP](../../../../../Modules/Jana/Mcp/Tools/TriageTool.php) — fila canônica espelhada
- [ADR 0070 Jira-style PM](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR UI-0013 Constituição UI v2](../../../../../memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)
- Pattern fonte: `MyWork/Index.tsx` + `Backlog/Index.tsx`
