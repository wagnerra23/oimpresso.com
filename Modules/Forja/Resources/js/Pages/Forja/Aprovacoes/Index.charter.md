---
page_id: forja-aprovacoes
page: /forja/aprovacoes
component: Modules/Forja/Resources/js/Pages/Forja/Aprovacoes/Index.tsx
related_prototype: prototipo-ui/cowork/forja-aprova.jsx
owner: wagner
status: draft
last_validated: "2026-09-02"
parent_module: Forja
related_us: [US-FORJA-010]
related_adrs: [70, 93, 368, 385, 388]
tier: B
charter_version: 2
---

# Page Charter — /forja/aprovacoes

> **Status:** `draft`. Promover pra `live` é ato de [W] (merge + gate visual).
>
> **Origem:** [ADR 0368](../../../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
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
([`McpTask::AWAITING_HUMAN`](../../../../../../../Modules/Jana/Entities/Mcp/McpTask.php)). Não é lista de
tarefas nem backlog: é **mesa de decisão**, e sai da mesa assim que decidida.

## Contrato de dados

| prop | origem | eager/defer |
|---|---|---|
| `titulo` · `subtitle` | literais do controller | eager |
| `decisoes` | **derivado** de `McpTask::TRANSITIONS['pending_approval']` | eager (a UI desenha os botões no 1º paint) |
| `fila` | `ForjaAprovacoesService::fila()` — `pending_approval`, `created_at` ASC, teto 200 | **defer** |
| `contagem` | `ForjaAprovacoesService::contagem()` | **defer** |
| `aoVivo` | `ForjaAprovacoesService::aoVivo()` — `mcp_actors` × `mcp_cc_sessions` × `mcp_audit_log` | **defer** |
| `placar` | `ForjaAprovacoesService::placar()` — `cowork_handoffs` agrupado por `created_by`, janela 7d | **defer** |
| `handoffsProblema` | `ForjaAprovacoesService::handoffsComProblema()` — delega ao `ForjaMcpService::handoffs()` | **defer** |

Escrita: **exclusivamente** `POST /forja/aprovacoes/{taskId}/decidir` →
[`TaskCrudService::update`](../../../../../../../Modules/Jana/Services/TaskRegistry/TaskCrudService.php),
o mesmo chokepoint da tool MCP `tasks-update`.

## Multi-tenant

`mcp_tasks` é **repo-wide** (governança da plataforma, não de tenant) — sem `business_id` por
design, igual `TriageController`/`ForjaMcpService` ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).
Permissão `jana.mcp.usage.all`; a [ADR 0368 §4](../../../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
é explícita em **não** criar permission de aprovação enquanto houver um único aprovador.

## Vocabulário (ADR 0368 §6 — eixo "decisão humana")

`admitida` (→ `todo`) · `admitida-parqueada` (→ `backlog`) · `recusada` (→ `cancelled`, **motivo
obrigatório**). **Nunca "aprovado"**: no `CAPTERRA-INVENTARIO.md` essa palavra já significa outra
coisa ("a capacidade existe no sistema"), e dois sentidos lado a lado é ambiguidade garantida.

---

## Paridade com o protótipo (Onda 3 · 2026-09-02)

A [ADR 0388](../../../../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)
("réplica primeiro") pôs o protótipo como **contrato de layout**. Esta tela é a view `hoje`
de [`forja-aprova.jsx`](../../../../../../../prototipo-ui/cowork/forja-aprova.jsx) — e **não** do
`forja-page.jsx`, que o charter apontava até aqui: aquele arquivo só **monta** a view (linha 1229),
o markup mora no `forja-aprova`. A âncora foi corrigida no frontmatter; o espelho estava **SYNC**
contra o Cowork vivo em 2026-09-02T11:17Z (sha `cc4cde3692da`, ledger de frescor).

Saíram da tela o `PageHeader` canon e o `KpiGrid`/`KpiCard`: o protótipo põe o número no
**herói** (`.fj-hj-n`) e não tem um segundo cabeçalho. O `ui:lint` R4 (que pede o PageHeader
canon) vira item da lista de inconsistências, não veto — é exatamente o que a 0388 D-2 decide.

### As três divergências DELIBERADAS

São **categoria, não bug de paridade** ([ADR 0385](../../../../../../../memory/decisions/0385-sidebar-alinhado-ao-prototipo-diferenca-em-tres-categorias.md)):
a 0388 é de **aparência** e diz, em D-5, que réplica **não toca comportamento**.

| # | o protótipo | esta tela | por quê |
|---|---|---|---|
| 1 | botões "Aprovar aplicação / Devolver / Rejeitar" | verbos vindos de `decisoes` (**Admitir · Parquear · Recusar**) | ADR 0368 §6 proíbe "aprovado" **e** o anti-hook abaixo proíbe hardcodar a lista |
| 2 | caixa de nota pertence ao "Devolver" | abre na decisão que declara `exige_motivo` | o dono da regra é o FSM (ADR 0368 §5), não o layout |
| 3 | 4 tipos (Plano/Modificação/Design/Proposta) com diff, passos e screenshot | só o artefato que `mcp_tasks` guarda | só `Proposta` tem estado canônico; os outros vivem em `cowork_handoffs` e **fundir as fontes é decisão [W]** |

### O que NÃO tem fonte, e por isso mostra "—"

As colunas **Sessões hoje** e **Custo hoje / quota** do placar são por **usuário**
(`mcp_cc_sessions`, `mcp_audit_log` e `mcp_quotas` são todos `user_id`), e o schema **não tem
vínculo papel→usuário**: os atores semeados são `wagner`/`felipe`/`maira`/`luiz`/`eliana`/
`claude-code-wagner-laptop`, nunca `CC`/`CD`/`CL`. Preenchê-las exigiria inventar o vínculo —
dado fantasma. O backend manda `null` e a célula diz o motivo no `title`. **Criar o vínculo é
decisão [W]** (campo novo = ADR mãe).

Pelo mesmo critério, o eixo `nivel` do protótipo (sênior/júnior/artista/agente) **não existe**:
`mcp_actors` declara `type` (human/ai_agent/service) e `trust_level` (L0..L4), que é outra coisa.
O selo mostra o que É declarado.

### O que a réplica NÃO regrediu

A fila do protótipo é `<li onClick>` cru, que **não abre por teclado**. Aqui a estrutura e a
classe são as dele (`.ap-item` é `display:flex` e o `:last-child` tira a última borda — só
funciona com a classe no próprio `<li>`), com `role=listbox/option` + `tabIndex` + `onKeyDown`
por cima. A 0388 tira o veto da **conformidade do DS**, nunca o da **acessibilidade**: a versão
anterior desta tela já era navegável por teclado, e réplica não regride isso. Medido: as duas
regressões `jsx-a11y` que a 1ª versão introduziu foram a zero.

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
  [ADR 0368 §3](../../../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md)
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
  **failing-first** (rodar Pest local é proibido — [ADR 0062](../../../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
  O ✅ vem do manifesto derivado do JUnit, nunca escrito à mão.
- **Não verificado localmente:** `route:list` e `tsc` — esta worktree não tem `vendor/` nem
  `node_modules/`. Os dois são checados no CI.
- **Fila lateral (`mesa-fila`), 2026-08-18 — paridade com o `ap-fila` do protótipo.** Antes a mesa
  mostrava só o mais antigo e um contador "N na fila": dava o número e não deixava olhar. A fila
  navegável usa o prop `fila` que **já vinha** do service (teto 200); nenhum campo novo, nenhum
  backend. Trocar o foco é estado de front (`selId`) e **não** decide nem reordena — a ordem
  continua sendo a espera, do backend (anti-hook de prioridade preservado). Sem teste que a
  exercite (a tela tem 0 E2E), então está no `[BACKLOG]` do `casos.md`, **não** como UC.
- **Sem smoke visual ainda** — screenshot pós-deploy é obrigatório antes de declarar pronto (R1 do
  PROTOCOLO-WAGNER-SEMPRE).
- O `hitl_pending` do Daily Brief **não** é esta fila: a procedure mede `status='blocked' AND
  owner='wagner'` (o proxy velho). Reconciliar é migration de procedure + `ProcedureDriftSnapshotTest`,
  em PR próprio.
