---
id: requisitos-task-registry-spec-ui-fase7
module: TaskRegistry
title: "SPEC — Completar UI do Team OS (Triage + Inbox + polish) sobre Modules/ProjectMgmt"
type: spec
status: draft
lifecycle: ativo
quarter: Q2-2026
related_adrs: [0070, 0064, "UI-0013"]
depende_de: AUDIT-TEAM-OS-2026-05-29 (Onda 2)
project_key: TR
owner_module: Modules/ProjectMgmt
decided_at: 2026-05-29
---

# SPEC — Completar UI do Team OS (Onda 2)

> **Correção v2 importante (2026-05-29):** este SPEC **NÃO é "construir a Fase 7 do zero"**. A verificação encontrou que `Modules/ProjectMgmt` **já tem** Board (Kanban), Backlog, MyWork, Roadmap, Burndown e Activity — **2.822 linhas de Inertia/React com `.charter.md` + `.review.md`** (passaram pelo MWART). O backend ([ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) Fases 1-6) está 100% pronto. **Falta apenas: Triage dedicado + Inbox dedicado + um polish de usabilidade não-técnica.** Escopo real ≈ **5-7h IA-pair**, não 12-16h.

## §1. Objetivo & escopo

Fechar a função de menor folga do Team OS pro time **não-técnico** (Felipe/Maiara/Eliana/Luiz) e futuros **clientes B2B**: dar telas dedicadas pra **triar** (atribuir dono/prioridade a tasks órfãs) e pra **caixa de entrada** (notificações de menção/atribuição/review). Hoje essas duas funções só existem via tools MCP (`triage`, `my-inbox`) — sem superfície humana.

**Entra:**
1. **Triage** — tela dedicada listando tasks sem owner OU sem prioridade OU `status=backlog`, com atribuição inline de owner + prioridade (+ cycle/epic opcional).
2. **Inbox** — tela dedicada lendo `mcp_inbox_notifications` (mention / assigned / review_requested / status_changed / commented), com marcar-lido e deep-link pra task.
3. **Polish não-técnico** — passada de acessibilidade/clareza no Board + Backlog existentes (labels PT-BR, empty states, atalho de teclado, mobile-friendly) pra um operador não-técnico conseguir usar sem treino.

**Fica fora (defer explícito, alinhado ADR 0070 Tier 3):** Roadmap Gantt avançado, Initiatives, bulk-ops na UI, editor visual de saved views, board B2B multi-tenant (entra com 1º cliente — sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

## §2. Backend reusado (NÃO recriar)

A UI é casca Inertia sobre o que já existe (ADR 0070 Fases 1-6):

| Recurso | Tipo | Uso na UI |
|---|---|---|
| `mcp_tasks` (+ `parent_task_id`, `owner`, `priority`, `status`, `custom_fields`) | tabela | cards de Triage |
| `mcp_inbox_notifications` (type, task_id, actor_id, body, read_at) | tabela | itens do Inbox |
| `mcp_views` (filtro `owner IS NULL OR priority IS NULL OR status='backlog'`) | view sistema | fonte do Triage |
| tool `triage` | MCP | já retorna as órfãs (paridade UI ↔ tool) |
| tool `my-inbox` | MCP | já retorna unread |
| tool `tasks-update` | MCP | persistir owner/prio/status no Triage |
| `MyWorkController` / `BoardController` (existentes) | controller | padrão Inertia a espelhar |

**Princípio:** a tela e a tool devem dar a **mesma** resposta (a UI não inventa query nova; consome o mesmo filtro do `triage`/`my-inbox`).

## §3. User Stories (project key `TR` — TaskRegistry)

| ID | História | Critério de aceite |
|---|---|---|
| **US-TR-301** | Como membro do time, vejo uma tela **Triage** com todas as tasks órfãs (sem owner OU sem prio OU backlog) | lista = mesmo conjunto que a tool `triage`; vazio → empty state "Nada pra triar 🎉" |
| **US-TR-302** | Na Triage, atribuo **owner + prioridade inline** sem abrir a task | select inline → `tasks-update` → UI otimista + rollback em erro; gera `mcp_task_events` |
| **US-TR-303** | Na Triage, movo a task pra um **cycle/epic** opcionalmente | dropdown cycle/epic; persiste; some da lista se deixar de ser órfã |
| **US-TR-304** | Como membro, vejo uma tela **Inbox** com minhas notificações não-lidas | lê `mcp_inbox_notifications WHERE user_id=me AND read_at IS NULL`; agrupado por tipo |
| **US-TR-305** | No Inbox, **marco como lido** (individual e "marcar todas") | `read_at` setado; contador do badge no topnav decrementa em tempo real (Centrifugo, [ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) |
| **US-TR-306** | No Inbox, clico numa notificação e vou direto pra **task/DetailSheet** | deep-link abre `Board` com `DetailSheet` da task aberto |
| **US-TR-307** | Como operador **não-técnico**, uso Board/Backlog/Triage sem treino | labels PT-BR claros, empty states, foco-teclado, toque-friendly ≥360px; revisado por `design:accessibility-review` |
| **US-TR-308** | Vejo no card os **ADRs/SPECs relacionados** (diferencial memory-linked) | `mcp_task_memory_links` renderizado como chips no card/DetailSheet (reusa o que o DetailSheet já faz, se já fizer) |

## §4. Arquitetura Inertia

Tudo dentro de **`Modules/ProjectMgmt`** (dono canônico do board — [ADR 0064](../../decisions/0064-modularizacao-split-teammcp-kb-superadmin.md)), espelhando o padrão dos controllers/pages existentes (`BoardController`, `MyWorkController`).

| Camada | Novo | Padrão a seguir |
|---|---|---|
| Controller | `TriageController` + `InboxController` | igual `MyWorkController` (Inertia::render + `Inertia::defer` na lista) |
| Pages | `resources/js/Pages/ProjectMgmt/Triage/Index.tsx` + `Inbox/Index.tsx` (+ `.charter.md` + `.review.md`) | igual `Board/Index.tsx`, `MyWork/Index.tsx` |
| Shell | `AppShellV2` + `PageHeader` canon (modo NAV com SubNav) | [ADR UI-0013](../_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) + [PT-01](../_DesignSystem/padroes-tela/PT-01-Lista.md) |
| Rotas | `/projects/triage`, `/projects/inbox` (ou prefixo já usado pelo módulo) | `Modules/ProjectMgmt/Routes/web.php`, FQCN ([.claude/rules/routes.md](../../../.claude/rules/routes.md)) |
| Realtime | canal Centrifugo `inbox.{user_id}` pro badge | [ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) |

**Tokens/CSS:** somente tokens canônicos da Constituição UI v2 (cor/tipo/espaço); zero hex hardcoded. Rodar [PRE-MERGE-UI](../_DesignSystem/PRE-MERGE-UI.md) antes do PR.

## §5. Multi-tenant

- **Hoje:** `mcp_tasks` / `mcp_inbox_notifications` são governança **global** (a maioria das `mcp_*` não tem `business_id` efetivo — convenção ADR 0070 + [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)). Triage/Inbox escopam por `user_id` (a notificação/owner é por pessoa), não por business.
- **Futuro board B2B:** quando 1º cliente assinar, o board do cliente escopa por `business_id` (global scope). Documentar então; **não** antecipar agora (sinal qualificado [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

## §6. QA / Pest

- Smoke render Triage (200 + lista = tool `triage`) e Inbox (200 + unread = tool `my-inbox`).
- `tasks-update` via Triage persiste owner/prio + gera `mcp_task_events` (audit).
- "Marcar lido" seta `read_at` e some do unread.
- Deep-link Inbox→task abre DetailSheet correto.
- Acessibilidade: `design:accessibility-review` no Triage/Inbox.
- Cross-tenant: deixar teste preparado (skip) pra quando board B2B existir (biz=1 vs biz=99, [ADR 0101](../../decisions/0101-tests-biz-1-nunca-cliente.md)).

## §7. Estimativa

[ADR 0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) (fator 10× IA-pair): **~5-7h IA-pair** (2 telas + polish), vs ~12-16h se fosse do zero. O grosso da Fase 7 já está entregue.

## §8. Cross-links

- Auditoria origem: [`AUDIT-TEAM-OS-2026-05-29.md`](AUDIT-TEAM-OS-2026-05-29.md) — Onda 2 (função 9: 7→9).
- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Fase 7 (UI) + hierarquia + tools.
- UI existente a reusar: `resources/js/Pages/ProjectMgmt/{Board,Backlog,MyWork,Roadmap,Burndown,Activity}/Index.tsx`.

---

**Última atualização:** 2026-05-29 — SPEC Onda 2 (completar UI). Escopo enxuto pós-descoberta de que ProjectMgmt já tem Board/Backlog/Roadmap/MyWork/Burndown/Activity. Não depende de Brain B.
