---
page_id: forja-aprovacoes
page: /forja/aprovacoes
component: resources/js/Pages/Forja/Aprovacoes/Index.tsx
related_prototype: prototipo-ui/cowork/forja-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-08"
parent_module: Forja
related_us: [US-FORJA-010]
related_adrs: [70, 93, 368]
tier: B
charter_version: 1
---

# Page Charter — /forja/aprovacoes

> **Status:** `draft`. Promover pra `live` é ato de [W] (merge + gate visual).
>
> **Origem:** [ADR 0368](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
> (aceita 2026-08-04) fechou a POLÍTICA do funil de admissão e escreveu, textual, que
> *"o código vai em PR próprio, com evidência"*. O estado (`pending_approval`), o FSM e a
> trava de recusa-sem-motivo chegaram em [#5283](https://github.com/wagnerra23/oimpresso.com/pull/5283)
> / [#5288](https://github.com/wagnerra23/oimpresso.com/pull/5288). **Esta tela é a peça que
> faltava** — sem ela a fila existe no banco e não existe em lugar nenhum que o [W] consiga olhar.

---

## Mission

Mostrar **o que espera por uma decisão de [W]**, em ordem de espera, com o artefato no centro e a
decisão a um toque. Responde a pergunta *"o que está parado por minha causa, e há quanto tempo"*.

A entidade é a submissão que espera aval — `mcp_tasks` em `pending_approval`
([`McpTask::AWAITING_HUMAN`](../../../../../Modules/Jana/Entities/Mcp/McpTask.php)). Não é lista de
tarefas nem backlog: é **mesa de decisão**, e sai da mesa assim que decidida.

## Contrato de dados

| prop | origem | eager/defer |
|---|---|---|
| `titulo` · `subtitle` | literais do controller | eager |
| `decisoes` | **derivado** de `McpTask::TRANSITIONS['pending_approval']` | eager (a UI desenha os botões no 1º paint) |
| `fila` | `ForjaAprovacoesService::fila()` — `pending_approval`, `created_at` ASC, teto 200 | **defer** |
| `contagem` | `ForjaAprovacoesService::contagem()` | **defer** |

Escrita: **exclusivamente** `POST /forja/aprovacoes/{taskId}/decidir` →
[`TaskCrudService::update`](../../../../../Modules/Jana/Services/TaskRegistry/TaskCrudService.php),
o mesmo chokepoint da tool MCP `tasks-update`.

## Multi-tenant

`mcp_tasks` é **repo-wide** (governança da plataforma, não de tenant) — sem `business_id` por
design, igual `TriageController`/`ForjaMcpService` ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
Permissão `jana.mcp.usage.all`; a [ADR 0368 §4](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
é explícita em **não** criar permission de aprovação enquanto houver um único aprovador.

## Vocabulário (ADR 0368 §6 — eixo "decisão humana")

`admitida` (→ `todo`) · `admitida-parqueada` (→ `backlog`) · `recusada` (→ `cancelled`, **motivo
obrigatório**). **Nunca "aprovado"**: no `CAPTERRA-INVENTARIO.md` essa palavra já significa outra
coisa ("a capacidade existe no sistema"), e dois sentidos lado a lado é ambiguidade garantida.

---

## Non-Goals

> **Cópia literal da §4 "O que NÃO fazer" do pedido do [W] (2026-08-08).** Nenhum item foi inferido,
> adicionado ou reinterpretado — `charter-write` é proibida de inferir; só [W] preenche.

- ❌ Rota nova além de `/forja/handoffs`
- ❌ tabela/campo novo (pin/rank = user-pref, não schema — schema exige ADR mãe)
- ❌ mexer em permissão `jana.mcp.usage.all`
- ❌ fundir Tarefas×Backlog agora
- ❌ workflow configurável (F0→F4 é constituição)

> ⚠️ **Divergência declarada, não silenciosa:** esta tela **cria** a rota `/forja/aprovacoes`, o que
> contraria o 1º item acima. O próprio pedido do [W] a especifica no PR-0 ("Nova rota `/forja/aprovacoes`
> vira a landing"), então trato o PR-0 (específico) como vencendo a §4 (genérica) e registro aqui em vez
> de escolher em silêncio. **Se a leitura certa for a §4, esta tela é que sai** — é decisão de [W].
> O item do campo novo **foi respeitado**: nenhuma coluna nasceu (o `tam` P/M/G/GG do PR-4 seria
> duplicata de `estimate_unit='tshirt'` + `estimate_value`, que já existem em `mcp_tasks`).

## Automation Anti-hooks

> Cada item vira Pest GUARD. Derivados do que já é lei em ADR/§5 — **não** de preferência minha.

- ❌ **Não** listar `blocked` na fila. É trava técnica; misturar reconstrói o proxy que a
  [ADR 0368 §3](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
  aposentou. _(defendido por UC-APROV-02)_
- ❌ **Não** hardcodar a lista de decisões. Ela deriva de `McpTask::TRANSITIONS`; uma cópia aqui vira
  segunda declaração do fluxo e diverge na primeira mudança. _(UC-APROV-05)_
- ❌ **Não** escrever no Eloquent direto. Toda decisão passa por `TaskCrudService`, senão FSM e
  recusa-com-motivo ficam de fora em silêncio. _(UC-APROV-06/07)_
- ❌ **Não** aceitar recusa sem motivo, nem "resolver" isso com motivo default/placeholder.
  _(UC-APROV-07)_
- ❌ **Não** ordenar por prioridade. A ordem é a espera — prioridade esconde o `p3` de três dias.
  _(UC-APROV-03)_
- ❌ **Não** oferecer "Desfazer" que tente reverter decisão já persistida: o FSM não tem volta pra
  `pending_approval`, e um botão desses bateria em 422 sempre — mecanismo anunciando saída que não
  implementa (lápide §5 2026-07-30). A janela de 6s acontece **antes** do POST.

---

## Estado / pendências

- Todos os UC nascem 🧪: `AprovacoesMesaTest.php` entrou na allowlist do `forja-pest.yml`
  **failing-first** (rodar Pest local é proibido — [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
  O ✅ vem do manifesto derivado do JUnit, nunca escrito à mão.
- **Não verificado localmente:** `route:list` e `tsc` — esta worktree não tem `vendor/` nem
  `node_modules/`. Os dois são checados no CI.
- **Sem smoke visual ainda** — screenshot pós-deploy é obrigatório antes de declarar pronto (R1 do
  PROTOCOLO-WAGNER-SEMPRE).
- O `hitl_pending` do Daily Brief **não** é esta fila: a procedure mede `status='blocked' AND
  owner='wagner'` (o proxy velho). Reconciliar é migration de procedure + `ProcedureDriftSnapshotTest`,
  em PR próprio.
