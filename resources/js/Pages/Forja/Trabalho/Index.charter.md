---
page_id: forja-trabalho
page: /forja/trabalho
component: resources/js/Pages/Forja/Trabalho/Index.tsx
owner: wagner
status: draft
last_validated: "2026-08-09"
parent_module: Forja
related_us: [US-FORJA-006]
related_adrs: [70, 93, 253]
tier: B
charter_version: 1
---

# Page Charter — /forja/trabalho

> **Status:** `draft`. Promover pra `live` é ato de [W].
>
> **Origem:** `US-FORJA-006` — *"as abas do cockpit sobrepõem Triage/Backlog/Board/Activity;
> foram movidas, não fundidas, e fundir = deletar uma implementação"*.

---

## Mission

Responder **"o que tem pra fazer?"** em **um** lugar. Hoje a mesma pergunta tem três respostas
diferentes, e nenhuma é errada — são escopos distintos que ninguém declarou:

| tela | escopo | riqueza |
|---|---|---|
| `Pages/Forja/Backlog/Index.tsx` (416 ln) | `project=FORJA` | filtros · KPIs · epics/owners/sprints · **tem `casos.md`** |
| `_components/ForjaBacklog.tsx` (207 ln) | `project=FORJA` | lista chapada, sem filtro |
| `Pages/team-mcp/Tasks/Index.tsx` (647 ln) | **todas** as tasks | KPI-filtros · ActorSeal |

Números via `wc -l` em 2026-08-08 — re-conte em vez de confiar no retrato.

## A escolha, e por que

**As nativas vencem.** São as ricas e as únicas com contrato defendido por gate; o cockpit é a
versão enxuta. Este `TrabalhoService` é a lógica do `BacklogController` generalizada (sai o
`project_id` obrigatório), mais a projeção `forja_*` que só o cockpit tinha, mais o escopo
sem-recorte que só o `team-mcp/Tasks` tinha.

## Contrato de dados

| prop | origem | eager/defer |
|---|---|---|
| `titulo` · `subtitle` · `filtros` · `sorts` · `statuses` | controller (constantes e query string) | eager — a UI monta os controles no 1º paint |
| `tasks` | `TrabalhoService::build()` — teto 500, memoizado por filtros | **defer** |
| `kpis` | mesma query de `tasks` (por isso a memoização) | **defer** |
| `frentes` | `McpProject::pluck('key','id')` — evita N+1 no rótulo do grupo | **defer** |

## Sem chip de frente

Decisão [W] 2026-08-08: a lista abre com **todas** as `mcp_tasks`. O recorte por projeto se faz
**agrupando** (a tela agrupa por Frente) ou **buscando** — não por um chip que esconde o resto.
O filtro `frente` existe no service para quem chegar por URL, mas a UI não o oferece.

---

## Non-Goals

> Derivados do pedido do [W] (2026-08-08) e da `US-FORJA-006`. Não inferidos.

- ❌ **Deletar ou redirecionar** as três telas existentes **nesta onda**. Elas convivem; a remoção
  da perdedora é decisão [W], depois do smoke comparativo.
- ❌ Coluna/campo novo em `mcp_tasks` — `tam` P/M/G/GG duplicaria `estimate_unit='tshirt'` +
  `estimate_value`, que já existem.
- ❌ Rank híbrido com **pin persistido**. O pedido o descreve, mas ele depende de user-pref
  gravada; esta onda entrega a ordem que já existia (`rank`) + o eixo de execução.
- ❌ Workflow configurável — F0→F4 é constituição.
- ❌ Permission nova — reusa `jana.mcp.usage.all`, como o resto do hub.

## Automation Anti-hooks

- ❌ **Não** exigir `project_id` para listar. O default é TODAS as tasks; devolver `[]` sem
  projeto é o comportamento do `ForjaBacklogService`, e é o oposto do que [W] pediu.
- ❌ **Não** aceitar `sort` livre da query string. Valor fora da allowlist vira `FIELD(...)` sem
  correspondência e a ordem sai aleatória **sem erro visível** — falha silenciosa.
- ❌ **Não** tratar `forja_fase` ausente como dado faltando. Fase só existe para trabalho do
  pipeline de tela; task de infra/gate/ADR não tem, e isso é correto.
- ❌ **Não** duplicar a query entre `tasks` e `kpis` — eles compartilham resultado por
  memoização; separar dobra a consulta em toda render.

---

## Estado / pendências

- Todos os UC nascem 🧪 — o ✅ vem do manifesto derivado do JUnit, nunca escrito à mão.
- **Não verificado localmente:** `route:list`, `tsc` e Pest (worktree sem `vendor/`/`node_modules`;
  Pest local é proibido — [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
- **Sem smoke visual ainda** — obrigatório antes de declarar pronto (R1).
- **A decisão da US-FORJA-006 continua aberta.** Esta tela é a candidata; qual das outras sai é [W].
