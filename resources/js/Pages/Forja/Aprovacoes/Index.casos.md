---
id: resources-js-pages-forja-aprovacoes-index-casos
casos: Forja · mesa de aprovações · /forja/aprovacoes
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-08"
---

# Casos de uso — /forja/aprovacoes

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [ADR 0368](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) §3/§4/§5/§6 — **nunca** do `.tsx`. Persona: **[W]**, decidindo o que entra no sistema. Escopo é repo-wide, não por business — `mcp_tasks` é governança da plataforma ([ADR 0070](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) + [ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

> ⚠️ **Todos os UC nascem 🧪 e o motivo é honesto:** `AprovacoesMesaTest.php` entrou na allowlist do [`forja-pest.yml`](../../../../../.github/workflows/forja-pest.yml) **failing-first** — rodar Pest local é proibido (Tier 0, [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md): CT 100/CI só), então o primeiro verde só pode acontecer no CI. O `✅` vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit — **não se escreve à mão**.

## UC-APROV-01 — Acesso à mesa exige login + permissão
Status: 🧪 (1 teste cita este UC — anônimo no POST **e** autenticado-sem-permissão no GET. O usuário do 403 é filtrado pra **não-admin**: `Gate::before` libera qualquer ability pra `Admin#{biz}`, então com admin o caso passaria mesmo se o `can:` sumisse do controller.)
`AprovacoesController` exige login + `jana.mcp.usage.all`. A [ADR 0368 §4](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) é explícita em **não** criar permission de aprovação enquanto houver um único aprovador.
**Pronto quando:** anônimo recebe 302/401/403 no POST e autenticado sem a permissão recebe 403 no GET.

## UC-APROV-02 — A fila mostra só o que espera DECISÃO HUMANA
Status: 🧪 (1 teste cita este UC — e é um teste com **controle**: cria uma `pending_approval` e uma `blocked`, e exige que a primeira apareça **e a segunda não**. Assertar só "a que espera aparece" deixaria passar um service que listasse tudo.)
A fila é `McpTask::AWAITING_HUMAN` (`pending_approval`). `blocked` é trava **técnica** e não entra — a [ADR 0368 §3](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) criou o estado separado justamente porque o proxy antigo (`blocked` + `owner=wagner`) misturava "espera por alguém" com "depende de outra coisa", e nenhum relatório conseguia distinguir.
**Pronto quando:** task em `pending_approval` aparece na fila e task `blocked` **não** aparece.

## UC-APROV-03 — A ordem é por espera crescente
Status: 🧪 (1 teste cita este UC — cria as fixtures em ordem **invertida** à esperada, então um service que não ordenasse (ou ordenasse por inserção) cairia.)
Mais antigo primeiro. Prioridade **não** desempata: deixar um `p3` de três dias atrás de um `p0` de cinco minutos é exatamente como a fila envelhece sem ninguém ver.
**Pronto quando:** o item criado há 3 dias vem antes do criado há 5 minutos.

## UC-APROV-04 — A espera é visível e graduada
Status: 🧪 (1 teste cita este UC — as duas pontas da faixa, `ok` e `urgente`.)
Cada item carrega `espera_min` e uma faixa: `ok` · `atencao` (≥30min) · `urgente` (≥2h). É descritivo — **não bloqueia nada** e não decide nada.
**Pronto quando:** item de 2 minutos vem `ok` e item de 5 horas vem `urgente`.

## UC-APROV-05 — As decisões oferecidas DERIVAM do FSM
Status: 🧪 (2 testes citam este UC — a igualdade com `McpTask::TRANSITIONS` e o caso negativo do destino fora dele, que também confere que o estado **não** mudou.)
As saídas são `McpTask::TRANSITIONS['pending_approval']` = `todo` (admitida) · `backlog` (admitida-parqueada) · `cancelled` (recusada) — vocabulário da [ADR 0368 §6](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md). Hardcodar a lista no service criaria um segundo lugar onde o fluxo é declarado, e os dois divergiriam na primeira mudança.
**Pronto quando:** o conjunto oferecido é igual ao do FSM, e destino fora dele responde 422 sem mutar a task.

## UC-APROV-06 — Admitir move a proposta pro fluxo normal
Status: 🧪 (1 teste cita este UC — a persistência real após o POST.)
`POST /forja/aprovacoes/{taskId}/decidir` com `destino=todo` reusa `TaskCrudService::update` — a mesma via da tool MCP `tasks-update`, então a decisão gera `mcp_task_events`.
**Pronto quando:** o POST devolve `ok:true` e o status persiste como `todo`.

## UC-APROV-07 — Recusar SEM motivo é barrado e nada muda
Status: 🧪 (1 teste cita este UC, com **duas metades**: (a) sem motivo → 422 e estado preservado; (b) com motivo → persiste. A metade (b) é **pré-condição anti-vácuo** — sem ela, (a) passaria igual se o controller fosse inerte, medindo não-execução e chamando de preservação (lápide §5 2026-07-24).)
[ADR 0368 §5](../../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md): recusa exige motivo escrito, senão a mesma capacidade volta em três meses e consome a decisão de novo. Quem enforça é o `TaskCrudService` (throw dentro da transaction), não a UI.
**Pronto quando:** recusa sem motivo dá 422 com o estado intacto, e recusa com motivo persiste `cancelled` + grava `custom_fields['motivo_recusa']`.

## UC-APROV-08 — Item que já saiu da fila não é decidido de novo
Status: 🧪 (1 teste cita este UC.)
Decisão dupla sobre o mesmo item é pior que decisão perdida: outra aba, outra sessão ou a tool MCP podem ter movido o item. O controller responde 409 pedindo recarga em vez de reabrir.
**Pronto quando:** POST sobre task que não está em `pending_approval` responde 409.

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

Prosa honesta: cada item **está no charter** e **não tem** teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] Janela de 6s em que a decisão fica desfazível **antes** do POST (modelo "Undo Send") — hoje é comportamento só de front, sem teste E2E que o exercite.
- [BACKLOG] Atalhos de teclado `a`/`d`/`x` derivados do FSM, inertes enquanto o foco está no campo de motivo.
- [BACKLOG] Placar da equipe de agentes (heartbeat · custo/quota · critique F1.5 · retrabalho) — pedido do [W] em 2026-08-08, ainda sem backend.
- [BACKLOG] Strip "ao vivo" por pessoa (executando / espera-você / offline) a partir de `mcp_cc_sessions` + heartbeat do ingest.
- [BACKLOG] Absorver a Triagem como tipo "Proposta" da mesa + 301 de `/forja` — depende da decisão da `US-FORJA-006` (qual implementação sobrevive).
