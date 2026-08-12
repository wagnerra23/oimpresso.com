---
id: resources-js-pages-forja-triage-index-casos
casos: Forja · triagem de tasks órfãs · /project-mgmt/triage
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-12"
---

# Casos de uso — /project-mgmt/triage

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Trio nascendo sobre tela com charter `status: draft` (Onda 2). Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [`SPEC-UI-FASE7`](../../../../../memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md) §3 US-TR-301..303 — **nunca** do `.tsx`. Persona: time não-técnico ([F]/[M]/[L]/[E]) triando `mcp_tasks` órfãs. Escopo é **por projeto**, não por business — `mcp_*` é governança repo-wide ([ADR 0070](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) + charter §Multi-tenant).

> ⚠️ **Todos os UC estão 🧪, e o motivo é estrutural.** `TriageControllerTest.php` está registrado em `phpunit.xml` (`./Modules/Forja/Tests/Feature`) mas **não** aparece em `.github/ci-sqlite-pest.list` nem no alvo do `forja-pest.yml` (que roda só `ForjaRoutesSmokeTest.php`) — as duas lanes são **allowlists explícitas**. Verificado 2026-08-04. Enquanto a entrada não for consolidada, estes UC são **"verde impossível"**: o teste existe, cita o UC e **não roda**. O `✅` só pode vir do manifesto `scripts/casos-test-results.json` (derivado do JUnit do CI) — não se escreve à mão.

## UC-TRIAGE-01 — Acesso à triagem exige login + permissão
Status: 🧪 (2 testes de `TriageControllerTest` citam este UC — a leitura (`GET /triage`) e a escrita (`PATCH /triage/{id}/assign`). O da escrita **reasseta o banco** depois do 403 (`owner` continua `null`): sem isso, um endpoint que gravasse e só então recusasse passaria.)
`TriageController` exige login + `jana.mcp.usage.all`, tanto pra listar quanto pra atribuir.
**Pronto quando:** usuário autenticado **sem** a permissão recebe 403 nos dois caminhos, e o PATCH recusado não persiste nada.

## UC-TRIAGE-02 — A tela renderiza a fila com contadores e as listas de apoio
Status: 🧪 (1 teste de `TriageControllerTest` cita este UC — o componente Inertia + as 6 chaves de payload. `cycles`/`epics`/`owners` são o que popula os selects: sem elas a atribuição inline do charter não tem o que oferecer.)
A tela é `Forja/Triage/Index` e recebe: `tasks` (fila órfã), `kpis` (Pra triar / Sem dono / Sem prioridade / Em backlog), `cycles`, `epics`, `owners` e `filters`.
**Pronto quando:** `/project-mgmt/triage` renderiza o componente com as 6 chaves presentes (sem 500 / tela branca).

## UC-TRIAGE-03 — A fila é a MESMA da tool MCP `triage` (a UI não inventa query)
Status: 🧪 (1 teste de `TriageControllerTest` cita este UC — e é um teste com **controle**: cria uma task órfã e uma já triada, e exige que a primeira apareça **e a segunda não**. Só assertar "a órfã aparece" deixaria passar um controller que listasse tudo.)
US-TR-301 + charter: a fila consome o scope `McpTask::triage()` — sem owner **OU** sem prioridade **OU** `status=backlog`, excluindo done/cancelled. É a mesma resposta da tool MCP `triage`; divergir é anti-pattern declarado no charter.
**Pronto quando:** task órfã aparece na lista e task com dono + prioridade **não** aparece.

## UC-TRIAGE-04 — Atribuir dono + prioridade inline persiste e deixa rastro
Status: 🧪 (1 teste de `TriageControllerTest` cita este UC — persistência dos dois campos **e** o evento de audit (`assigned`/`field_updated`). Sem a perna do evento, um endpoint que gravasse sem auditar passaria.)
US-TR-302: `PATCH /project-mgmt/triage/{id}/assign` reusa `TaskCrudService::update` (a mesma via da tool `tasks-update`), então a atribuição gera `mcp_task_events` e notifica o dono via `mcp_inbox_notifications`.
**Pronto quando:** o PATCH devolve `ok:true`, `owner` e `priority` persistem, e existe evento novo de `assigned`/`field_updated` pra aquela task.

## UC-TRIAGE-05 — `still_triage` diz se a task continua na fila
Status: 🧪 (1 teste de `TriageControllerTest` cita este UC — o caso **negativo**, que é o difícil: atribuir só prioridade, sem dono, tem que devolver `still_triage=true` e a task fica. A metade positiva (`still_triage=false` quando ganha dono+prio) é assertada no teste do `UC-TRIAGE-04`.)
Charter: a task **some da lista** quando deixa de ser órfã, e isso é dito pelo backend via `still_triage` — o frontend não recalcula a regra por conta própria.
**Pronto quando:** atribuição parcial (só prioridade) devolve `still_triage=true` com `owner` ainda nulo; atribuição completa devolve `still_triage=false`.

## UC-TRIAGE-06 — Atribuição inválida não muta nada
Status: 🧪 (3 testes de `TriageControllerTest` citam este UC — prioridade fora do enum (422), payload sem nenhum campo (422) e task inexistente (404). Os dois primeiros **reasseveram o banco** depois da resposta.)
Prioridade fora do conjunto canônico → 422. PATCH sem campo nenhum → 422 (não é no-op silencioso). `taskId` inexistente → 404.
**Pronto quando:** o código de erro é o certo **e** a task continua exatamente como estava.

---

## [BACKLOG] — contrato declarado no charter, ainda sem teste que o defenda

Prosa honesta: cada item **está no charter** e **não tem** teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] Mover a task pra cycle/epic pelos dropdowns opcionais da linha (US-TR-303) — o charter promete, `TriageControllerTest` só exercita `owner`/`priority`.
- [BACKLOG] O drawer **Analisar** / dossiê do Analista (`GET /triage/{id}/dossier`, read-only) — Forja PR-5a no charter.
- [BACKLOG] As 3 ações "agente propõe, [W] aprova" sob `AlertDialog`: **Aprovar** (`POST /aprovar` → `todo`, exige dono+prio) · **Rejeitar** (`POST /rejeitar` → `cancelled`) · **Fundir** (`POST /fundir` → cancela + evento de duplicata).
- [BACKLOG] UI otimista + **rollback** em erro (banner âmbar inline auto-dismiss 5s) e reconciliação por partial reload `only:['tasks','kpis']`.
- [BACKLOG] Chips de motivo por linha (sem dono / sem prioridade / backlog).
- [BACKLOG] Empty state **"Nada pra triar"** quando a fila zera (sem emoji, PT-BR limpo).
- [BACKLOG] Deep-link `display_id` → `/project-mgmt/board?task=ID` abrindo o DetailSheet.
- [BACKLOG] Atalhos J/K (navegar linha) e Enter (abrir no Board); ⌘K é do AppShellV2, não desta tela.
- [BACKLOG] Polling 30s + reload on-focus re-sincronizando a fila.
- [BACKLOG] UX targets: first-paint p95 < 1500ms · 0 erro de console · atribuição reflete < 100ms e reconcilia < 1s · toque-friendly ≥ 360px.
