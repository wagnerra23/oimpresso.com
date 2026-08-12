---
id: resources-js-pages-forja-inbox-index-casos
casos: Forja · caixa de entrada por-pessoa · /project-mgmt/inbox
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-04"
---

# Casos de uso — /project-mgmt/inbox

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Trio nascendo sobre tela com charter `status: draft` (Onda 2). Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + [`SPEC-UI-FASE7`](../../../../../memory/requisitos/TaskRegistry/SPEC-UI-FASE7.md) §3 US-TR-304..306 — **nunca** do `.tsx`. Persona: qualquer membro do time vendo **as próprias** notificações.

> ⚠️ **Isolamento aqui é por `user_id`, não por `business_id`.** `mcp_inbox_notifications` é por-pessoa ([ADR 0070](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md) marca `mcp_*` como repo-wide); o vetor de vazamento é **entre usuários do mesmo business**, não entre tenants. Por isso os dois UC de isolamento abaixo (`UC-INBOX-03` leitura e `UC-INBOX-06` escrita) são o núcleo deste arquivo, não acessório.

> ⚠️ **Todos os UC estão 🧪, e o motivo é estrutural.** `InboxControllerTest.php` está registrado em `phpunit.xml` (`./Modules/Forja/Tests/Feature`) mas **não** aparece em `.github/ci-sqlite-pest.list` nem no alvo do `forja-pest.yml` (que roda só `ForjaRoutesSmokeTest.php`) — as duas lanes são **allowlists explícitas**. Verificado 2026-08-04. Enquanto a entrada não for consolidada, estes UC são **"verde impossível"**: o teste existe, cita o UC e **não roda** — inclusive os dois de isolamento. O `✅` só pode vir do manifesto `scripts/casos-test-results.json` (derivado do JUnit do CI) — não se escreve à mão.

## UC-INBOX-01 — Acesso à caixa exige login + permissão
Status: 🧪 (2 testes de `InboxControllerTest` citam este UC — a leitura (`GET /inbox`) e a escrita em lote (`PATCH /inbox/read-all`). Cobrir só a leitura deixaria o botão "marcar todas" fora da trava.)
`InboxController` exige login + `jana.mcp.usage.all`, tanto pra listar quanto pra marcar lido.
**Pronto quando:** usuário autenticado **sem** a permissão recebe 403 nos dois caminhos.

## UC-INBOX-02 — A tela renderiza a caixa com contadores e filtros
Status: 🧪 (1 teste de `InboxControllerTest` cita este UC — o componente Inertia + as 3 chaves de payload.)
A tela é `Forja/Inbox/Index` e recebe: `inbox` (as notificações), `inbox_stats` (os 2 contadores do charter — Não-lidas / Últimos 30 dias) e `filters` (inclui o toggle `show_read`).
**Pronto quando:** `/project-mgmt/inbox` renderiza o componente com `inbox`, `inbox_stats` e `filters` presentes (sem 500 / tela branca).

## UC-INBOX-03 — Só as notificações do próprio usuário aparecem (Tier 0 · leitura)
Status: 🧪 (1 teste de `InboxControllerTest` cita este UC — e é um teste com **controle**: cria uma notificação do usuário autenticado e outra de um `user_id` diferente, e exige que a primeira apareça **e a segunda não**. Só assertar "a minha aparece" deixaria passar um controller que listasse a caixa de todo mundo.)
US-TR-304 + charter §Multi-tenant: a listagem escopa por `auth()->id()`. O anti-pattern está escrito na lei: *"❌ Mostrar notificação de outro usuário"*.
**Pronto quando:** a notificação do usuário autenticado aparece na lista e a de outro `user_id` **não** aparece.

## UC-INBOX-04 — Marcar uma notificação como lida
Status: 🧪 (1 teste de `InboxControllerTest` cita este UC — asserta o `read_at` **nulo antes** e **não-nulo depois**, no mesmo caso. Sem a pré-condição, um teste que só olhasse o "depois" não distinguiria "marcou agora" de "já estava lida".)
US-TR-305: `PATCH /project-mgmt/inbox/{id}/read` seta `read_at` e a notificação sai do conjunto de não-lidas.
**Pronto quando:** o PATCH devolve `{ok:true, id}` e o `read_at` daquela notificação deixa de ser nulo.

## UC-INBOX-05 — Marcar todas as minhas como lidas
Status: 🧪 (1 teste de `InboxControllerTest` cita este UC — cria **duas** não-lidas, checa o contador `marked` devolvido e confirma o `read_at` de **cada uma**. Só o contador não provaria persistência; só uma notificação não provaria "todas".)
US-TR-305: `PATCH /project-mgmt/inbox/read-all` marca de uma vez as não-lidas **do usuário autenticado**.
**Pronto quando:** o endpoint devolve `ok:true` + a contagem de marcadas, e todas as não-lidas daquele usuário passam a ter `read_at`.

## UC-INBOX-06 — Ninguém marca notificação alheia (Tier 0 · escrita)
Status: 🧪 (1 teste de `InboxControllerTest` cita este UC — 404 **e** o `read_at` da notificação alheia continuando nulo. O 404 sozinho não prova nada: um controller poderia marcar e só então devolver 404.)
Charter §Multi-tenant: toda **escrita** escopa por `auth()->id()` (`abort_unless` / `where user_id`). Tentar marcar a notificação de outro usuário devolve 404 — não 403, pra não confirmar a existência do recurso alheio.
**Pronto quando:** `PATCH /inbox/{id}/read` sobre notificação de outro `user_id` devolve 404 e **não** altera o `read_at` dela.

---

## [BACKLOG] — contrato declarado no charter, ainda sem teste que o defenda

Prosa honesta: cada item **está no charter** e **não tem** teste citando um UC. Vira UC quando ganhar teste — não antes ([`how-trabalhar.md`](../../../../../memory/how-trabalhar.md) §Pedido de tela/feature).

- [BACKLOG] Agrupamento por tipo na ordem do charter (mention → assigned → review_requested → status_changed → commented → due_soon → blocked_resolved), com ícone + label PT-BR por tipo.
- [BACKLOG] Deep-link (US-TR-306): clicar o item abre `/project-mgmt/board?task=ID` com o DetailSheet **e marca lido no caminho**.
- [BACKLOG] Toggle mostrar-lidas / só-não-lidas (`?show_read=1`) — a chave `filters` tem teste (UC-INBOX-02), o comportamento do toggle não.
- [BACKLOG] Empty state **"Caixa de entrada vazia"** (e "Nada na caixa." no modo `show_read`).
- [BACKLOG] UI otimista com rollback ao marcar lido + partial reload `only:['inbox','inbox_stats']` (o anti-pattern "recarregar a página inteira" não tem defesa mecânica).
- [BACKLOG] Atalhos J/K (navegar item) · Enter (abrir no Board) · R (marca lida) · Shift+R (marca todas).
- [BACKLOG] Polling 30s + reload on-focus re-sincronizando badge/contador.
- [BACKLOG] UX targets: first-paint p95 < 1500ms · 0 erro de console · marcar-lido reflete < 100ms e reconcilia < 1s · deep-link < 300ms · toque-friendly ≥ 360px.
- [BACKLOG] Badge realtime via Centrifugo (`inbox.{user_id}`) — o charter marca como **fora desta entrega** (ADR 0058); fica aqui só como rastro, não como dívida em aberto.
