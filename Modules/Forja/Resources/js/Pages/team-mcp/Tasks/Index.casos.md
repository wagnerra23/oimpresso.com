---
id: resources-js-pages-team-mcp-tasks-index-casos
casos: Tarefas MCP · Backlog + Quadro + drawer · /team-mcp/tasks
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-10-07"
last_run_ci: "0 UC executado — trio nasce neste PR; veredito pendente das lanes PHP / Pest (Unit) e PHP / Pest (Forja · MySQL)"
---

# Casos de uso — /team-mcp/tasks

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [`tasks-visual-comparison.md`](../../../../../../../memory/requisitos/TeamMcp/tasks-visual-comparison.md) (decisões [W] de 2026-06-16) + [ADR 0070](../../../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) + [SDD do hub](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md) (CU-TEAM-09) — **nunca** do `.tsx`. O `TasksAdminController` só confirma o comportamento. A tela nasceu Inertia: não há Blade nem Delphi para comparar ([SDD §0.2](../../../../../../../memory/requisitos/TeamMcp/SDD-tela-hub-team-mcp-v1.0.md)).
>
> Persona: **[W]** + time MCP, desktop, `jana.mcp.usage.all`. `mcp_tasks` é **repo-wide por design** (ADR 0070, governança da plataforma), então esta tela **não tem** caso de isolamento por business. Isso é declarado, não esquecido.

> ⚠️ **Todos os UC nascem 🧪, e há duas forças de prova** no [`TasksAdminContratoTest.php`](../../../../../Tests/Feature/TasksAdminContratoTest.php):
> - **registro** (rota, middleware, verbo) — roda na lane sqlite `PHP / Pest (Unit)`, que **bloqueia merge** (está em `governance/required-checks-baseline.json`);
> - **request** (403, PATCH de status, evento, quadro) — só dá veredito na lane MySQL `PHP / Pest (Forja · MySQL)`, que é **advisory**. Em sqlite essas pernas **pulam**, e skip não é cobertura.
>
> O arquivo entrou no `forja-pest.yml` **failing-first** (Pest local é proibido; o checkout do CT 100 está atrás do `main`). O `✅` vem do manifesto `scripts/casos-test-results.json` — **não se escreve à mão**.

## UC-TSK-01 — A rota abre a tela (a Page existe)
Status: 🧪 (2 testes citam este UC — rota `team-mcp.tasks.index` → `TasksAdminController@index`, e a string do `Inertia::render` cruzada com o `.tsx` em disco.)
O charter declara `/team-mcp/tasks` → `team-mcp/Tasks/Index`; o SDD §1 lista a mesma rota e controller.
**Pronto quando:** a rota está registrada no controller certo e o componente que ele renderiza existe (sem Inertia 500).

## UC-TSK-02 — Acesso exige login + `jana.mcp.usage.all`, inclusive no drawer `[T0]`
Status: 🧪 (2 testes citam este UC — **(a)** toda rota `team-mcp.tasks.*` (index, update-status, detail) carrega `auth` + o `can:` no registro, qualquer driver; **(b)** usuário novo, sem `Admin#` e sem a permissão, leva 403 na tela **e** no endpoint do drawer — só na lane MySQL.)
Charter §Restrições Tier 0: *"Permissão `jana.mcp.usage.all` no construtor (todas as ações, incl. `show`)"*; SDD CU-TEAM-09: o hub é repo-wide, então vazar a tela expõe governança de todos os businesses.
**Pronto quando:** as três rotas exigem login + a permissão, e quem não a tem recebe 403 na tela e no drawer.

## UC-TSK-03 — Só os 6 status canônicos; fora disso, 422 e nada muda
Status: 🧪 (1 teste cita este UC — PATCH com `F2` responde 422 e a task segue `todo`. Só na lane MySQL.)
Charter §Non-Goals: *"Inventar fases F0..F4 ❌ — usa os 6 status canônicos (ADR 0070)"*; visual-comparison §Matriz: *"status válidos todo/doing/review/done/blocked/cancelled"*.
**Pronto quando:** status fora dos 6 é recusado com 422 e o status gravado não muda.

## UC-TSK-04 — Mover status persiste e vira atividade real no drawer
Status: 🧪 (1 teste cita este UC — PATCH `todo → doing` responde 200, grava `doing`, acrescenta evento em `mcp_task_events`, e o endpoint do drawer devolve esse evento. Cruza duas fontes: o que o PATCH escreveu e o que o drawer lê. Só na lane MySQL.)
Charter §Métricas: *"drag move status (otimista) e reconcilia no reload; registra `mcp_task_events`"*; charter §Goals: drawer com *"Atividade (`mcp_task_events` real)"*; visual-comparison §Decisões [W] item 2 (endpoint read-only lendo `mcp_task_events`).
**Pronto quando:** a mudança de status persiste, gera evento auditável e o drawer mostra esse evento.

## UC-TSK-05 — A única escrita da tela é o PATCH de status
Status: 🧪 (1 teste cita este UC — entre as rotas `team-mcp.tasks.*`, a única com verbo de escrita é `update-status` PATCH; o resto é GET. Qualquer driver.)
Charter §Anti-hooks: *"NÃO escreve nada além de `PATCH` status (bulk = N× PATCH). `show()` é read-only"*; Non-Goal: *"Criar/editar task na tela ❌"*.
**Pronto quando:** a tela não tem rota de criação, edição ou exclusão.

## UC-TSK-06 — O Quadro tem as colunas todo / doing / review / done
Status: 🧪 (1 teste cita este UC — partial reload de `kanban` (é `Inertia::defer`) filtrado pelo módulo das fixtures: só aparecem essas 4 chaves, `todo` e `review` trazem as fixtures certas (controle contra quadro vazio), e a task `blocked` não aparece. Só na lane MySQL.)
Charter §Goals: *"Aba Quadro: kanban todo/doing/review/done"*; visual-comparison §Matriz: *"Kanban todo/doing/review/done"*.
**Pronto quando:** o Quadro agrupa só nessas 4 colunas e cada task cai na coluna do seu status.

## Backlog (sem id — vira UC quando ganhar teste que o cite)

- [BACKLOG] **Backlog agrupável por 5 dimensões, com grupos que persistem** — charter §Goals + §Métricas. É comportamento de front (`localStorage`); a prova natural é E2E, que esta tela não tem.
- [BACKLOG] **Selo `Bot`/`User` distingue agente de humano** — charter §Goals + visual-comparison §Decisões item 4. O backend entrega `agents`; a decisão de pintar é do front.
- [BACKLOG] **Filtros server-side module/owner/sprint e KPIs** — charter §Goals + visual-comparison §Matriz. Contrato em duas fontes, sem teste neste PR.
- Atalhos (J/K/Enter/X), drawer 560px e DS v6 **não são UC**: o juiz é gate e a ratificação visual é [W] (mesma decisão do SDD §6.5).

## Achado registrado (decisão [W], não consertado aqui)

- **Transição proibida pelo FSM responde 404, não 422.** `updateStatus` só valida se o status é um dos 6. Uma transição que o FSM de `mcp_tasks` proíbe (ex.: `todo → done`) estoura `RuntimeException` no `TaskCrudService`, e o controller devolve **404**, com a mensagem de transição ilegal. Para quem arrasta no Quadro, isso aparece como "task não encontrada". Nenhuma fonte canônica diz qual código esta tela deve devolver nesse caso, então isto não virou UC. Se for 422, é uma mudança de uma linha no controller, e este casos ganha o UC no mesmo PR.
