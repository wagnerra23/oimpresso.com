---
id: resources-js-pages-forja-board-index-casos
casos: Forja · Kanban board Jira-style · /project-mgmt/board
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-04"
---

# Casos de uso — /project-mgmt/board

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Trio nascendo **forward-only** sobre tela já viva (`status: live`, charter v2). Os UC abaixo derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [`memory/requisitos/Forja/SPEC.md`](../../../../../memory/requisitos/Forja/SPEC.md) §PMG-001..007 e §Regras de negócio R-PMG-001/R-PMG-005 — **nunca** do `.tsx`. Persona: time interno ([W]/[F]/[M]/[L]/[E]) sobre `mcp_tasks` (governança repo-wide, sem `business_id` — [ADR 0070](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) + R-PMG-002).

> ⚠️ **Todos os UC estão 🧪, e o motivo é estrutural — não é modéstia.** `BoardControllerTest.php` está registrado em `phpunit.xml` (`./Modules/Forja/Tests/Feature`) mas **não** aparece em `.github/ci-sqlite-pest.list` nem no alvo do `forja-pest.yml` (que roda só `ForjaRoutesSmokeTest.php`) — as duas lanes são **allowlists explícitas**. Verificado 2026-08-04 por varredura nos três arquivos. Enquanto a entrada não for consolidada, estes UC são **"verde impossível"**: o teste existe, cita o UC e **não roda**. O `✅` só pode vir do manifesto `scripts/casos-test-results.json` (derivado do JUnit do CI pelo `casos-results-publish`) — não se escreve à mão.

## UC-BOARD-01 — Acesso ao board exige login + permissão
Status: 🧪 (7 testes de `BoardControllerTest` citam este UC no título — o 403 cobre as SETE superfícies do controller, não só a tela: `GET /board`, `PATCH /status`, `GET /detail`, `POST /comment`, `GET /users/suggest`, `POST /watch`, `POST /subtask`. Cobrir só a de leitura deixaria as de escrita descobertas, que é o buraco que importa.)
Todo endpoint do `BoardController` exige login + `jana.mcp.usage.all` (R-PMG-001, middleware `can:` no construtor). Cross-business intencional — `mcp_*` é governança, não business ([ADR 0093](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) via R-PMG-002).
**Pronto quando:** usuário autenticado **sem** `jana.mcp.usage.all` recebe 403 em qualquer endpoint do board, e nada é persistido no caminho.

## UC-BOARD-02 — O board renderiza com KPIs, colunas e filtros
Status: 🧪 (1 teste de `BoardControllerTest` cita este UC — o componente Inertia + as 5 chaves de payload que o charter promete.)
A tela é `Forja/Board/Index` e recebe do controller: `kanban` (cards por coluna), `kpis` (Total / Doing / Review / Blocked / P0 — o `<KpiGrid>` de 5 contadores do charter), `columns` e `filters` (cycle · epic · owner · busca).
**Pronto quando:** `/project-mgmt/board` renderiza o componente com `kanban`, `columns`, `filters` e os contadores `kpis.total`/`kpis.doing`/`kpis.blocked`/`kpis.p0_aberto` presentes (sem 500 / tela branca).

## UC-BOARD-03 — Mover a task de status persiste e deixa rastro auditável
Status: 🧪 (1 teste de `BoardControllerTest` cita este UC — persistência **e** o evento. Asserta também o `started_at` populado pelo side-effect do `TaskCrudService`; sem a perna do evento, um endpoint que gravasse status sem auditar passaria.)
O charter fixa **E** = avançar status (todo→doing→review→done) e **A** = voltar; as duas rotas de teclado chamam o mesmo `PATCH /project-mgmt/board/{taskId}/status`. A mudança gera linha em `mcp_task_events` (`event_type=status_changed`) — o board é fonte de governança, então transição sem audit não vale.
**Pronto quando:** o PATCH devolve `{ok:true, task_id, status}`, o status persiste no banco e existe um evento `status_changed` novo pra aquela task.

## UC-BOARD-04 — Transição inválida não muta nada
Status: 🧪 (2 testes de `BoardControllerTest` citam este UC — status fora do enum (422) e task inexistente (404). Os dois **reasseveram o estado depois da resposta**, não só o código: o 422 sem checar o banco não distingue "recusou" de "recusou depois de gravar".)
Status fora do conjunto canônico → 422. `taskId` inexistente → 404. Em nenhum dos dois o estado da task muda.
**Pronto quando:** o código de erro é o certo **e** a task continua exatamente como estava.

## UC-BOARD-05 — Edição concorrente não sobrescreve trabalho alheio (R-PMG-005)
Status: 🧪 (2 testes de `BoardControllerTest` citam este UC — o par completo: `expected_updated_at` obsoleto → 409 com `current` state e status intacto; `expected_updated_at` correto → 200 com `updated_at` novo. Só o 409 seria alarme sem controle: um endpoint que recusasse **sempre** passaria.)
Regra R-PMG-005 do SPEC: dois usuários com o board aberto movem o mesmo card. O segundo PATCH chega com `expected_updated_at` obsoleto e é **rejeitado com 409 Conflict**, devolvendo o `current` real pro cliente reverter o otimismo.
**Pronto quando:** PATCH com `expected_updated_at` velho devolve 409 + `current` (com o status real), a task **não** regride pro valor recusado, e PATCH com o timestamp correto passa e devolve `updated_at` novo.

## UC-BOARD-06 — Clicar o card abre o dossiê da task (DetailSheet)
Status: 🧪 (2 testes de `BoardControllerTest` citam este UC — o shape canônico do payload e o 404 de task inexistente. Escopo honesto: prova o **contrato do endpoint** que alimenta o drawer; que o `<Sheet>` abra em <300ms é UX target do charter, medido no smoke visual, não aqui.)
`GET /project-mgmt/board/{taskId}/detail` devolve o material das abas do `<DetailSheet>` (PMG-004): `task` (com `task_id`, `display_id`, `title`, `status`, `priority`, `description`, `project_key`), `comments`, `events`, `subtasks` e `dependencies`.
**Pronto quando:** o endpoint devolve as 5 seções com o shape acima pra uma task real, e 404 pra `taskId` inexistente.

## UC-BOARD-07 — Comentar na task, com @menção de gente
Status: 🧪 (3 testes de `BoardControllerTest` citam este UC — o comentário persistido em `mcp_task_comments` (201), o corpo vazio recusado (422) e o autocomplete sem query devolvendo lista vazia. O caso "sem query" é o que impede o `suggest` de virar dump de usuários.)
PMG-005: `POST /board/{taskId}/comment` cria o comentário (o `TaskCrudService::comment()` extrai `@usuario` e notifica via `mcp_inbox_notifications`); `GET /board/users/suggest?q=` alimenta o autocomplete do `<MentionInput>`.
**Pronto quando:** comentário válido devolve 201 + a linha persiste; corpo vazio devolve 422; `suggest` sem `q` devolve `{users: []}` em vez de listar todo mundo.

## UC-BOARD-08 — Seguir / parar de seguir a task (idempotente)
Status: 🧪 (4 testes de `BoardControllerTest` citam este UC — POST idempotente (2× não duplica), DELETE idempotente (2× não erra), 404 de task inexistente, e o `detail` expondo `watchers` + `is_watching`. A idempotência é o ponto: sem ela, clique duplo cria watcher fantasma e o contador mente.)
PMG-006: `POST /board/{taskId}/watch` e `DELETE .../watch` são idempotentes; `GET .../detail` passa a carregar `watchers[]` + `is_watching` pra aba Watchers do drawer.
**Pronto quando:** POST 2× deixa **um** watcher e `watchers_count=1`; DELETE 2× devolve 200 as duas vezes e zera o contador; `detail` reflete `is_watching` e a lista.

## UC-BOARD-09 — Criar subtask de 1 nível
Status: 🧪 (3 testes de `BoardControllerTest` citam este UC — criação com `parent_task_id` apontando pro pai + status inicial `todo`, título vazio (422) e pai inexistente (404). O assert do `parent_task_id` é o que amarra o Non-Goal do charter: a filha nasce **presa ao pai**, não solta.)
PMG-007: `POST /board/{taskId}/subtask` cria a filha via `TaskCrudService::create()` herdando projeto/cycle/epic, com `parent_task_id` do pai. O charter fixa o limite: **1 nível** (Non-Goal "sub-issue trees infinitos").
**Pronto quando:** a subtask nasce com `parent_task_id` = id do pai e status `todo`; título vazio devolve 422; pai inexistente devolve 404.

---

## Divergência charter × SPEC registrada (sem veredito — decisão [W])

O charter (lei) lista em **Non-Goals**: *"❌ Drag-and-drop entre colunas (atalhos E/A canon, drag está no backlog PMG-008)"*. O SPEC ([`Forja/SPEC.md`](../../../../../memory/requisitos/Forja/SPEC.md) §Fase 1) traz **PMG-001 · Drag-drop completo (optimistic-lock 409)** como `status: done` desde 2026-05-07 (PR #211), e a regra R-PMG-005 descreve literalmente *"dois usuários … ambos **arrastam** o mesmo card"*.

Os dois não podem estar certos ao mesmo tempo. Fica **registrado, não resolvido** — pela regra de precedência ([`memory/proibicoes.md`](../../../../../memory/proibicoes.md) §Regra de precedência) o SPEC é o elo mais fraco, mas corrigir o perdedor é ato de quem tem o veredito, não deste arquivo. Nenhum UC acima depende da resposta: o `UC-BOARD-05` está ancorado no **contrato do 409** (que vale por drag OU por atalho), não no gesto de arrastar.

## [BACKLOG] — contrato declarado no charter, ainda sem teste que o defenda

Prosa honesta: cada item abaixo **está no charter** e **não tem** teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] Atalhos de teclado J/K/E/A/`/`/Enter/?/Esc do board — o charter aponta a defesa pra [`tests/forjaBoardShortcuts.spec.tsx`](../../../../../tests/forjaBoardShortcuts.spec.tsx) na lane `forja-shortcuts-gate`, arquivo **fora** da área deste trio; virar UC exige citar o UC no título daqueles testes.
- [BACKLOG] Nenhum atalho de letra dispara com campo de texto em foco (guarda `isTypingTarget` — PMG-008).
- [BACKLOG] Filtros (cycle · epic · owner · busca) persistidos em `localStorage` sob o prefixo `oimpresso.board.*`.
- [BACKLOG] As 5 abas state-driven do `<DetailSheet>` (Description / Comments / Activity / Subtasks / Watchers) — hoje só o payload que as alimenta tem teste (UC-BOARD-06).
- [BACKLOG] Toggle de conclusão da subtask (`todo`↔`done`) pelo checkbox do drawer — a criação tem teste (UC-BOARD-09), o toggle não.
- [BACKLOG] `<DetailSheet>` como `<Sheet>` lateral (nunca modal) e tabs state-driven — UX anti-pattern do charter, sem defesa mecânica aqui.
- [BACKLOG] UX targets: first-paint p95 < 1500ms · 0 erro de console · atalho < 100ms · drawer < 300ms.
