---
id: requisitos-project-mgmt-spec
module: Forja
owners: [W]
status: ativo
version: "2.1.0"
last_updated: "2026-08-04"
project: PMG
default_component: UI
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0089-capterra-driven-module-evolution
  - 0093-multi-tenant-isolation-tier-0
  - 0100-projectmgmt-ui-redesign
us_list:
  - US-TR-304
  - US-TR-305
  - US-TR-306
  - US-TR-307
  - US-TR-308
  - US-TR-309
  - US-TR-310
  - US-TR-311
na_justified:
  D9.b: "Forja sem jobs assíncronos por design (operações síncronas Kanban) — failed_jobs N/A."
na_justified_v3:
  D6.a: "RoadmapGanttController NÃO usa Inertia::defer POR DECISÃO DE INCIDENTE, não por descuido. O HOTFIX de 2026-05-25 REMOVEU o defer da tela de origem porque o .tsx desestruturava direto e dava TypeError undefined.map — tela branca em prod. Os dropdowns usam closure, que roda no load cheio e pula no partial reload, entregando o mesmo ganho sem o risco. Reintroduzir defer só com <Deferred fallback> no .tsx: é mudança visual e exige aval [W]. Travado por UC-RGT-03 (Gantt.casos.md), pelo charter e pelo RUNBOOK-gantt §3. Decisão [W] 2026-08-05 ao ver o diagnóstico: a métrica pedia o que a produção proíbe."
---

# Forja — SPEC

> **Status**: Fases 1 e 2 entregues 2026-05-08 (7 PMG-*) · PMG-008 entregue 2026-08-04 ([PR #5261](https://github.com/wagnerra23/oimpresso.com/pull/5261)) · Fase 3 restante (PMG-009/010) e Fase 4 (PMG-011/012) seguem `todo` · Fase 5 **proposta**, aguardando [W].
> **Owner**: Wagner [W]
> **ADR mãe redesign**: [0100](../../decisions/0100-projectmgmt-ui-redesign.md)
> **Charter da Board**: [CHARTER-board.md](CHARTER-board.md)
> **CAPTERRA-FICHA**: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (24 capacidades)
> **INVENTARIO**: [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (✅🟡❌ por capacidade)
> **Goal**: Linear-tier UX (≥70% fluidez Linear) em redesign incremental do `Modules/Forja`.

## Nota de formato (2026-08-04)

Este SPEC carrega **duas gerações de identificador** e a diferença é mecânica, não estética:

- **`PMG-NNN`** (Fases 1-5 originais, 25 ids) **não casa** o padrão `us_list` do schema canônico
  (`scripts/memory-schemas/spec.schema.json` → `"pattern": "^US-[A-Z]{2,8}-[0-9]{3,4}$"`, lido 2026-08-04).
  Consequência concreta: essas US ficam **fora do `us_list`** e, portanto, fora da maquinaria de âncora
  (`anchor-lint`, cobertura US↔código). Elas seguem legíveis por humano — só não são medidas por máquina.
- **`US-TR-NNN`** (Onda 2 — Triage/Inbox) já casa e por isso **está** no `us_list` do frontmatter.
- **`US-FORJA-NNN`** (Fase 5 abaixo, proposta) **nasce no padrão atual** — nenhum id novo em `PMG-*`.

**A renomeação `PMG-* → US-FORJA-*` dos 25 legados é decisão [W] e, se aprovada, é forward-only /
oportunística — nunca big-bang.** Motivo medido e já catalogado: tocar arquivo legado **acorda os gates
diff-aware** que o protegiam por grandfather (`anchor-lint --check` + `SDD scorecard ratchet`), e a
reprovação vem da **dívida pré-existente**, não da mudança
(lápide §5 2026-07-12 em [`memory/proibicoes.md`](../../proibicoes.md) — o codemod dos 52 SPECs,
[PR #4156](https://github.com/wagnerra23/oimpresso.com/pull/4156), morreu exatamente assim).
O caminho barato: renomear um `PMG-*` **só quando trabalho real já for tocar aquela US** e pagar a
dívida de âncora dela no mesmo PR.

> As US da Fase 5 estão `status: proposto` e por isso **ainda não entram no `us_list`** — anunciar como
> escopo o que [W] não aprovou seria o SPEC afirmar o que ninguém decidiu. Entram no frontmatter no
> mesmo PR que aprovar cada uma.

**Dúvida deixada explícita (não inventei resposta):** a key opcional `anchor_format: v1` **não** foi
adicionada — ela declara que **todo** campo `**Implementado em:**` do corpo segue a gramática canônica
(path + `verificado@sha7`), e aqui só parte das linhas carrega o `verificado@` (as da Onda 2 têm;
as da Fase 1-2 não). Declarar seria afirmar conformidade não verificada. `_pendente_` até um passe
que normalize as âncoras do corpo.

## Visão

Module Jira-style já em prod desde 2026-05-04 (PRs #91/#92). Redesign UI em **4 fases capterra-driven** com gate humano entre cada uma:

- **Fase 1** — Fundamentos UX moderna (drag-drop completo + Cmd+K + tests Pest base)
- **Fase 2** — Detail Sheet + interações (sheet + @mentions + watchers + subtasks)
- **Fase 3** — Workflow + atalhos (atalhos avançados + cycle close UI + sprint planning)
- **Fase 4** — Real-time + persistence (Centrifugo presence + saved views backend)

## User stories

### Fase 1 — Fundamentos UX (P0) ✅ DONE

#### PMG-001 · Drag-drop completo (optimistic-lock 409)

> owner: wagner · priority: p0 · estimate: 6-8h · status: done · type: feature
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #211](https://github.com/wagnerra23/oimpresso.com/pull/211))

- [x] BoardColumn droppable + TaskCard draggable funcionais (já existiam)
- [x] BoardController::updateStatus aceita `expected_updated_at` opcional → 409 Conflict com `current` state
- [x] serializeTask inclui `updated_at` no payload
- [x] Frontend trata 409: revert otimismo + banner amarelo "Atualizado por outro usuário" + refetch silencioso
- [x] Tests Pest: R-PMG-005 conflict + happy path com expected_updated_at correto

**Implementado em:** [`Modules/Forja/Http/Controllers/BoardController.php`](../../../Modules/Forja/Http/Controllers/BoardController.php) + [`Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx`](../../../Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx)

#### PMG-002 · Cmd+K Search Global

> owner: wagner · priority: p0 · estimate: 3h · status: done · type: feature
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #209](https://github.com/wagnerra23/oimpresso.com/pull/209))

- [x] `Modules/Forja/Http/Controllers/SearchController.php` — `GET /project-mgmt/search?q=` busca cross-resource (mcp_tasks/epics/cycles/projects), permission `jana.mcp.usage.all`, LIKE simples, agrupa por tipo, limita 10/5/5/5
- [x] `resources/js/Components/CommandPalette.tsx` — `CommandDialog` shadcn (cmdk lib) + fetch debounced 220ms + grupos com prioridade dot + status badges
- [x] Atalho global Cmd/Ctrl+K em `AppShellV2` toggle palette (listener com cleanup)
- [x] 4 cenários Pest cobrindo permission/empty/shape/match

**Implementado em:** [`Modules/Forja/Http/Controllers/SearchController.php`](../../../Modules/Forja/Http/Controllers/SearchController.php) + [`resources/js/Components/CommandPalette.tsx`](../../../resources/js/Components/CommandPalette.tsx)

#### PMG-003 · Tests Pest base

> owner: wagner · priority: p0 · estimate: 2h · status: done · type: chore
> blocked_by: —

✅ **concluída 2026-05-07** ([PR #207](https://github.com/wagnerra23/oimpresso.com/pull/207))

- [x] `Modules/Forja/Tests/Feature/BoardControllerTest.php` — 6 cenários iniciais (403 GET/PATCH, 200 happy, 422, 404, audit log)
- [x] Diretório `Modules/Forja/Tests/Feature` registrado em `phpunit.xml`
- [x] Padrão Repair/Whatsapp + helpers (`pmgBootstrapUser`, `pmgGivePerm`, `pmgEnsureProject`, `pmgCreateTask`)

**Implementado em:** [`Modules/Forja/Tests/Feature/BoardControllerTest.php`](../../../Modules/Forja/Tests/Feature/BoardControllerTest.php)

### Fase 2 — Detail Sheet + interações (P1) ✅ DONE

#### PMG-004 · Detail Sheet completo (foundation Fase 2)

> owner: wagner · priority: p1 · estimate: 4-6h · status: done · type: feature
> blocked_by: PMG-003

✅ **concluída 2026-05-08** ([PR #220](https://github.com/wagnerra23/oimpresso.com/pull/220))

- [x] `BoardController::show($taskId)` — `GET /project-mgmt/board/{taskId}/detail` retorna `{task, comments, events, subtasks, dependencies}`. Eager load comments (≤100), events (≤50), subtasks (parent_task_id), dependencies + target map
- [x] `Modules/Forja/Resources/js/Pages/Forja/Board/DetailSheet.tsx` — Sheet shadcn slide-in à direita, w-2xl, overflow-y-auto. Header com display_id + priority dot + status badge + title + meta. 4 tabs state-driven (sem Tabs primitive nova): Description / Comments / Activity / Subtasks. Counts inline.
- [x] Click card → URL `?task=ID` via `window.history.replaceState` (preserveState/Scroll implícito)
- [x] 3 cenários Pest (403, 404, happy)

#### PMG-005 · @mentions em comments (form add inline)

> owner: wagner · priority: p1 · estimate: 3h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #222](https://github.com/wagnerra23/oimpresso.com/pull/222))

- [x] **Backend foundation já existia** em `TaskCrudService::comment()` (regex `/@([a-z][a-z0-9_-]+)/i` + `McpInboxNotification::notify()` dispatch)
- [x] `BoardController::addComment` — `POST /project-mgmt/board/{taskId}/comment`, validação body required/min:1/max:5000
- [x] `BoardController::suggestUsers` — `GET /project-mgmt/board/users/suggest?q=`, autocomplete users com permission `jana.mcp.usage.all`, LIKE em username/first_name/last_name, limit 10
- [x] `resources/js/Components/MentionInput.tsx` — textarea com trigger '@' + autocomplete debounced 180ms + ↑↓ navegar + Enter/Tab completar + Esc fechar + Cmd+Enter enviar
- [x] DetailSheet tab Comments com form inline + Button Send + handlePostComment otimista
- [x] 5 cenários Pest

#### PMG-006 · Watchers UI (Follow/Unfollow)

> owner: wagner · priority: p1 · estimate: 2h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #224](https://github.com/wagnerra23/oimpresso.com/pull/224))

- [x] `Modules/Jana/Entities/Mcp/McpTaskWatcher.php` — Model novo (id, task_id, user_id, timestamps + relation user belongsTo). Tabela `mcp_task_watchers` já existia desde Migration 2026_05_04_180011
- [x] `BoardController::watch` (POST) + `BoardController::unwatch` (DELETE) — idempotentes (firstOrCreate / delete)
- [x] `BoardController::show` extended — payload agora inclui `watchers[]` + `is_watching:bool`
- [x] DetailSheet tab Watchers com card Seguir/Parar de seguir + lista de followers
- [x] 5 cenários Pest

#### PMG-007 · Subtasks UI (create + toggle status)

> owner: wagner · priority: p1 · estimate: 3h · status: done · type: feature
> blocked_by: PMG-004

✅ **concluída 2026-05-08** ([PR #226](https://github.com/wagnerra23/oimpresso.com/pull/226))

- [x] `BoardController::addSubtask` — `POST /project-mgmt/board/{taskId}/subtask`, valida title required min:1 max:255, chama `TaskCrudService::create()` com `parent_task_id` + project key + cycle/epic herdados
- [x] DetailSheet tab Subtasks com checkboxes clicáveis (toggla status todo↔done via PATCH otimista) + form add inline (Enter envia, Plus button)
- [x] Done = riscado + opacity-60. Loading spinner durante toggle
- [x] 4 cenários Pest

### Fase 3 — Workflow + atalhos (P1) 🔲 PARCIAL (PMG-008 done · 009/010 todo)

#### PMG-008 · Atalhos keyboard avançados (overlay help + chord)

> owner: wagner · priority: p1 · estimate: 3h · status: done · type: feature
> blocked_by: —

✅ **concluída 2026-08-04** ([PR #5261](https://github.com/wagnerra23/oimpresso.com/pull/5261) — `ec2d7f852ba`)

- [x] Atalho `?` abre/fecha overlay com a lista de atalhos (J/K/`/`/Enter/E/A/?/Esc) — `Esc` fecha, e com o overlay aberto **só** o Esc responde (navegar cards por trás de modal é o anti-padrão que o overlay existe pra evitar)
- [x] `Enter` abre o DetailSheet da task selecionada
- [x] `preventDefault` em todas as teclas tratadas + guarda `isTypingTarget` (INPUT/TEXTAREA/contentEditable) — digitar "java" na busca não move card nem avança status
- [x] Hook extraído e **testável**: `useBoardShortcuts` roda em jsdom sem a Page inteira (sem AppShellV2/Inertia/defer), com lane de CI própria
- [ ] ⚠️ **Resíduo não entregue:** atalho `c` cria task na coluna ativa (foco no input). Varredura contada 2026-08-04 (`grep "'c'" Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` → 0 ocorrências; a tecla não aparece no `useBoardShortcuts` nem no `ShortcutsOverlay`). **Vira US nova ou é descartado = decisão [W]** — não foi inventada US pra ele aqui.

**Correção do texto do SPEC (precedência charter > SPEC, [`memory/proibicoes.md`](../../proibicoes.md) §Regra de precedência):**
o item original pedia `e` = *"editar task selecionada"*. **Não foi feito, e por decisão certa** —
`Board/Index.charter.md` (lei) fixa **`E` = avançar status** e **`A` = voltar**, e roubar o `E` quebraria
a memória muscular de quem usa o board todo dia. O "abrir pra editar" ficou no **`Enter`**, que estava
livre. A justificativa está escrita no cabeçalho do próprio hook
([`useBoardShortcuts.ts`](../../../Modules/Forja/Resources/js/Pages/Forja/Board/_components/useBoardShortcuts.ts) L9-13).
O SPEC era o perdedor da precedência e está corrigido aqui, no lugar de o código ser dobrado à linha velha.

**Correção do item de teste:** o SPEC dizia *"Tests Pest (~não aplicável; teste manual)"*. Falso hoje —
a cobertura existe e é automatizada em jsdom, não em Pest:
[`tests/forjaBoardShortcuts.spec.tsx`](../../../tests/forjaBoardShortcuts.spec.tsx) rodado pela lane
[`.github/workflows/forja-shortcuts-gate.yml`](../../../.github/workflows/forja-shortcuts-gate.yml).

**Implementado em:** [`Modules/Forja/Resources/js/Pages/Forja/Board/_components/useBoardShortcuts.ts`](../../../Modules/Forja/Resources/js/Pages/Forja/Board/_components/useBoardShortcuts.ts) + [`Modules/Forja/Resources/js/Pages/Forja/Board/_components/ShortcutsOverlay.tsx`](../../../Modules/Forja/Resources/js/Pages/Forja/Board/_components/ShortcutsOverlay.tsx)

#### PMG-009 · Cycle close UI (retro markdown + rollover)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Sheet/Page com tabs Incompletas / Retro / Confirm
- [ ] Lista incompletas + checkbox rollover individual
- [ ] Textarea retro markdown (salva em `mcp_cycles.retro` JSON)
- [ ] Botão Confirm fecha cycle + move incompletas marcadas pro próximo
- [ ] Reusa tool MCP `cycles-close --rollover` existente
- [ ] Tests Pest +3 cenários

#### PMG-010 · Sprint planning Modal ("Add to cycle" do Backlog)

> owner: wagner · priority: p1 · estimate: 2h · status: todo · type: feature
> blocked_by: —

- [ ] Modal abre do Backlog com multi-select tasks
- [ ] Botão "Add to cycle" + dropdown cycle ativo/planning
- [ ] Endpoint `POST /project-mgmt/cycle/{id}/add-tasks` body `{task_ids: []}`
- [ ] Tests Pest +3 cenários

### Fase 4 — Real-time + persistence (P1) 🔲 TODO

#### PMG-011 · Centrifugo presence (avatar stack TopBar)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Hook `usePresence(canal)` em `resources/js/Hooks/`
- [ ] Canal `project-mgmt:board:{cycle_id}`
- [ ] Avatar stack no TopBar do Board mostra outros users conectados
- [ ] Teardown em unmount (leave channel)
- [ ] Tests E2E Pest 1 cenário (mock Centrifugo connect)

#### PMG-012 · Saved views backend (mover localStorage → mcp_views)

> owner: wagner · priority: p1 · estimate: 3h · status: todo · type: feature
> blocked_by: —

- [ ] Endpoints CRUD `/project-mgmt/views` (POST create, GET list, PATCH update, DELETE remove)
- [ ] UI 'Save view' + 'My views' + 'Shared' no FilterBar do Board
- [ ] Migration: nada (tabela `mcp_views` já existe)
- [ ] Tests Pest +4 cenários

### Fase 5 — Diferenciação (P2/P3) — backlog não-comprometido

> Detalhes em [CAPTERRA-INVENTARIO.md § Fase 5](CAPTERRA-INVENTARIO.md). Só entram se Fase 1-4 mostrarem ROI.

- PMG-013 Triage view dedicada (P2)
- PMG-014 Activity feed filtros + permalinks (P2)
- PMG-015 Burndown multi-cycle + scope_creep (P2)
- PMG-016 Dependencies graph (P2)
- PMG-017 Time tracking interno (P2)
- PMG-018 Workload view (P2)
- PMG-019 Custom fields per project (P2)
- PMG-020 Templates de epic/cycle (P2)
- PMG-021 Automation rules (P2)
- PMG-022 Mobile responsive (P3)
- PMG-023 Dark mode toggle (P3)
- PMG-024 Roadmap timeline drag (P3)
- PMG-025 Public share link (P3)

### Fase 6 — Vazão do backlog (proposta · aguardando aprovação [W])

> **Por que Fase 6 e não 5:** a `Fase 5 — Diferenciação (P2/P3)` acima (PMG-013..025) já ocupava o rótulo.
> Renumerar a seção **nova** é grátis (nenhum id `PMG-*` legado muda); renumerar a **antiga** é que seria o
> big-bang vetado pela Nota de formato. Corrigido em 2026-08-04 — o rótulo duplicado durou uma sessão.
>
> Foco desta fase: as fases 1-4 trataram de **entrar** trabalho no sistema (board, sheet, atalhos).
> Esta trata de **sair** — triagem em lote, expurgo, saída do `review`, e as duas decisões [W] que hoje
> deixam telas inteiras sem dado. Nada aqui está aprovado: todas nascem `status: proposto`.

#### US-FORJA-001 · Triagem em lote na tela Triage

> owner: [W] · priority: p0 · estimate: _pendente_ · status: proposto · type: feature
> blocked_by: —

Hoje a Triage é **1-a-1**: todas as rotas são por task individual
([`Http/routes.php`](../../../Modules/Forja/Http/routes.php) L97-118 →
`triage/{taskId}/{assign,dossier,aprovar,rejeitar,fundir}`). O Backlog **já tem** o caminho em lote —
`POST /project-mgmt/backlog/bulk` (L136) → `BacklogController@bulk` → `TaskCrudService::bulkUpdate`
com `bulk_op_id`. A premissa desta US é **reusar esse caminho**, não abrir um segundo.

Recibo do volume: **519 US sem dono** (Daily Brief #461, 2026-08-04). O dono vivo desse número é o
comando `mcp:tasks:unassigned` — re-rode em vez de reescrever o número aqui.

- [ ] Multi-select na lista da Triage (checkbox por linha + "selecionar todas as visíveis")
- [ ] Ação em lote: atribuir **owner** e/ou **prioridade** ao conjunto
- [ ] Ação em lote: **cancelar** o conjunto (pareia com US-FORJA-002 — motivo obrigatório)
- [ ] Reusa `POST /project-mgmt/backlog/bulk` + `TaskCrudService::bulkUpdate` (**não** criar endpoint bulk novo na Triage)
- [ ] Cada item do lote gera `mcp_task_events` e notifica o novo owner — paridade com o caminho 1-a-1 de `TriageController@assign`
- [ ] Pest: lote parcial (uma task inválida no meio não derruba as outras) + `bulk_op_id` presente + evento por item

**Implementado em:** _pendente_

#### US-FORJA-002 · Política de expurgo (usar o `cancelled` que já existe)

> owner: [W] · priority: p0 · estimate: _pendente_ · status: proposto · type: feature
> blocked_by: —

**Sem migration, sem status novo.** O `cancelled` já é canônico
([`McpTask::STATUSES`](../../../Modules/Jana/Entities/Mcp/McpTask.php) L88 = `backlog/todo/doing/review/done/blocked/cancelled`,
e `CLOSED_STATUSES` L91 = `done/cancelled`), e a UI do Backlog já o oferece — em filtro e em edição em lote
([`Backlog/Index.tsx`](../../../Modules/Forja/Resources/js/Pages/Forja/Backlog/Index.tsx) L198/206/293).

O que falta **não é o status — é a política**: hoje cancelar é um select como outro qualquer, sem motivo
registrado e sem critério de quando fazer. Backlog que só cresce vira ruído; expurgo sem rastro vira
"quem matou minha task?".

- [ ] **Motivo obrigatório** ao mudar status para `cancelled` (individual e em lote) — persistido em `mcp_task_events`
- [ ] Critério escrito de quando expurgar (ex.: `backlog` + sem dono + sem movimento há N dias) — **o N é decisão [W]**, não inventado aqui
- [ ] Ação em lote de cancelamento com motivo único aplicado ao conjunto
- [ ] Task cancelada continua **legível** (não some do banco) — `cancelled` já é `CLOSED_STATUSES`, some das listas ativas por scope, não por delete
- [ ] Pest: cancelamento sem motivo é rejeitado (422) + evento gravado com o motivo + task cancelada some de `McpTask::triage()`

**Implementado em:** _pendente_

#### US-FORJA-003 · Estender `mcp:tasks:unassigned` (idade + delta semanal)

> owner: [W] · priority: p1 · estimate: _pendente_ · status: proposto · type: chore
> blocked_by: —

⛔ **É EXTENSÃO DO DONO DO TEMA — proibido criar um 2º medidor de US órfã.**
Régua consolidada já existe e já roda:
[`McpTasksUnassignedCommand`](../../../Modules/Jana/Console/Commands/McpTasksUnassignedCommand.php)
(flags `--days/--module/--strict/--json`), agendado **daily 06:45 BRT** em
[`app/Console/Kernel.php`](../../../app/Console/Kernel.php), no bloco `command('mcp:tasks:unassigned')`
(`environments(['live'])`, US-INFRA-043). Ref de linha removida em 2026-08-04: apodrece a cada edição
do Kernel — a âncora durável é o símbolo + `grep`.
Somar um segundo contador cria dois juízes pro mesmo tema — lápide §5 2026-07-09
*"duplica régua consolidada"* em [`memory/proibicoes.md`](../../proibicoes.md).

Estado verificado 2026-08-04 (leitura do comando + do Kernel): o comando já reporta `total`, `sem_cycle`,
`sem_owner` e a lista de `task_ids`, e a **linha do brief já existe** via `TasksSemDonoBriefLineService`
(comentário do próprio schedule). Logo, "sair no brief" **não** é o gap.

- [ ] Campo `idade_dias` por item + **idade da órfã mais velha** no resumo (hoje o comando traz `created_at` mas não deriva idade)
- [ ] **Delta semanal** (entraram × saíram desde a última corrida) — a série já está no log `single`; falta consolidá-la
- [ ] Os dois campos novos no `--json` (o `--json` é o contrato de quem consome)
- [ ] Enriquecer a linha existente do brief com idade-da-mais-velha + delta — **sem** criar linha nova
- [ ] Segue **advisory** (sem `--strict`) — promover a ratchet é decisão [W] com mordida provada ([ADR 0336](../../decisions/0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)); ligar agora, com o volume atual, seria o anti-padrão `foundation-ratchet`
- [ ] Pest no método público `detectarNaoAtribuidas` (já é público pra ser testável sem console parsing)

**Implementado em:** _pendente_

#### US-FORJA-004 · Ligar a superfície do alarme de `review` parado (o detector já existe)

> owner: [W] · priority: p1 · estimate: _pendente_ · status: proposto · type: chore
> blocked_by: —

⛔ **NÃO criar detector de `review` parado — ele já existe e já roda.**
[`McpTasksHealthCheckCommand`](../../../Modules/Jana/Console/Commands/McpTasksHealthCheckCommand.php)
já flagga `review` sem update há **>5d** → `stale_review` (além de `stale_doing` >7d, `stale_todo`,
`stale_blocked`), agendado **daily 06:20 BRT** em [`app/Console/Kernel.php`](../../../app/Console/Kernel.php) L592.

**A redação anterior desta US pedia um alarme de `review >7d` — era régua duplicada** (teria criado um
3º número pro mesmo fato, lápide §5 2026-07-09). Corrigida em 2026-08-04 após medir o comando.
Fica registrada aqui a versão morta, em vez de apagada.

O gap medido **não é detecção, é superfície**: o docblock do próprio comando (L38) diz que o schedule roda
*"sem `--auto-comment` (só relatório)"* — a flag existe (L46) e está desligada. O sinal morre no log.

- [ ] Ligar `--auto-comment` no schedule (o comentário na task usa `TaskCrudService::comment()`, caminho já existente)
- [ ] Verificar se o comentário automático é idempotente (não comentar a mesma task todo dia) — **se não for, isso é pré-requisito**
- [ ] Levar a contagem de `stale_review` pra linha do brief que já existe — **sem** criar linha nova
- [ ] Pest cobrindo: task flagada recebe 1 comentário · segunda corrida no mesmo estado não duplica
- [ ] ⛔ Volta automática `review → doing` **fica fora** — mudar status de task por política é decisão [W], não efeito colateral de alarme

Recibo do volume: **6 das 7 tasks ativas da Forja estão em `review`** (`tasks-list module:Forja`, 2026-08-04).

**Implementado em:** _pendente_

#### US-FORJA-005 · Rito de cycle — reativar ou aposentar (decisão [W], 0h de código)

> owner: [W] · priority: p0 · estimate: 0h (decisão) · status: proposto · type: chore
> blocked_by: —

**Não há cycle ativo** (`cycles-active` → *"Nenhum cycle ATIVO em COPI"*, medido 2026-08-04). Enquanto
isso for verdade, **Burndown, Roadmap e `cycle-goals-track` são telas sem dado** — e um pedaço do backlog
da Forja existe só pra servir esse rito.

As duas saídas, sem terceira via:

| Saída | O que implica |
|---|---|
| **Reativar** | [W] abre cycle e o time atualiza status no daily async ([`memory/regras-time.md`](../../regras-time.md) §Ciclo de trabalho). Burndown/Roadmap ganham dado. **PMG-009 e PMG-010 seguem vivas.** |
| **Aposentar** | O rito de cycle sai do produto. **PMG-009 (cycle close UI) e PMG-010 (add-to-cycle) morrem junto** — viram lápide, não backlog. Burndown/Roadmap precisam de outro eixo ou saem do menu. |

- [ ] [W] escolhe **reativar** ou **aposentar** — **esta US não decide**, só registra o fork
- [ ] A escolha é registrada aqui (e em ADR, se for aposentar — remover rito é mudança de escopo do módulo)
- [ ] PMG-009 e PMG-010 são atualizadas no **mesmo PR** da decisão (não ficam órfãs apontando pra um rito morto)

**Implementado em:** _pendente_ (decisão, não código)

#### US-FORJA-006 · Sobreposição cockpit `/forja` × telas nativas (decisão [W] + código)

> owner: [W] · priority: p1 · estimate: _pendente_ · status: proposto · type: chore
> blocked_by: US-FORJA-005 (parcial — o que fizer com cycle muda o que sobra pra fundir)

**A decisão já está registrada e tem dono — não recopio aqui.** Ver
[`BRIEFING.md`](BRIEFING.md) §*Decisões e riscos* + §*Próxima ação verificável*, e
[`memory/requisitos/Forja/SCOPE.md`](SCOPE.md) §cockpit (dono da proveniência).
Resumo de uma linha: as abas do cockpit `/forja` sobrepõem Triage/Backlog/Board/Activity nativas —
foram **movidas, não fundidas**, e fundir = deletar uma implementação.

Esta US existe só pra dar **prazo e evidência de conclusão** a algo que hoje é um parágrafo de risco:

- [ ] [W] decide qual implementação sobrevive
- [ ] A perdedora é **removida** (não deixada morta ao lado — duas implementações da mesma tela é o vetor)
- [ ] `SCOPE.md` §cockpit atualizado (é a evidência de conclusão que o próprio BRIEFING declara)
- [ ] Charters/casos da tela sobrevivente reconciliados no mesmo PR

**Implementado em:** _pendente_

#### US-FORJA-007 · WIP por pessoa vs máximo declarado

> owner: [W] · priority: p2 · estimate: _pendente_ · status: proposto · type: feature
> blocked_by: —

**Premissa traduzida, não copiada.** O "Workload" da Jira/Asana estima carga em **hora** — e por isso
precisa de campo de estimativa que quase ninguém preenche. Aqui o dado **já é lei e já está escrito**:
[`memory/regras-time.md`](../../regras-time.md) fixa o WIP máximo por pessoa
(**W=2 · M=2 · F=2 · L=1 · E=1**). Logo o indicador não é "quantas horas o Felipe tem" — é
**"o Felipe está acima do teto que o próprio time declarou?"**, que é binário e não depende de ninguém estimar.

- [ ] Contagem de tasks ativas (`doing`/`review`/`blocked`) por owner
- [ ] Comparação com o teto declarado; sinal visual só quando **acima** (abaixo não é notícia)
- [ ] O teto vem de fonte única (`regras-time.md` / `TEAM.md`) — **proibido** hardcodar `W=2` no `.tsx`
- [ ] Onde mora a visualização é decisão [W]: MyWork, Board ou tela nova — **não escolhi**
- [ ] Pest: pessoa no teto não sinaliza · pessoa 1 acima sinaliza · owner ausente do arquivo de time não quebra a tela

**Implementado em:** _pendente_

#### US-FORJA-008 · Requisitos da Forja no formato atual — **oportunístico**

> owner: [W] · priority: p1 · estimate: _pendente_ · status: proposto · type: chore
> blocked_by: —

Estado medido pelas portas vivas em **2026-08-04** (`npm run screen-coverage:report` + `npm run casos:report`;
re-rode em vez de confiar neste retrato): a Forja tem **9 telas · 9 charter · 0 `casos.md` · 0 E2E · 8 scorecard**.
Zero `casos.md` significa que **nenhuma tela da Forja tem contrato defendido por gate** (`casos-gate` G-2).

⛔ **Big-bang é proibido.** Criar os 9 `casos.md` + SDD de uma vez é exatamente o backfill de legado que
morre no CI (lápide §5 2026-07-12) — e pior, `casos.md` com UC sem teste **quebra o `casos-gate` G-2**,
bloqueando o merge de quem for atender a US. O caminho é **oportunístico**:

- [ ] `casos.md` nasce **só na tela que uma US da Fase 5 for tocar**, no mesmo PR do trabalho real
- [ ] Cada UC nasce com **≥1 teste que o cite** (senão o G-2 pune a US que criou o caso órfão)
- [ ] UC derivado do contrato (SDD/charter/SPEC), **nunca lido do `.tsx`** — teste derivado do código é tautológico (lápide §5 2026-06-05)
- [ ] Tela que **nenhuma** US tocar fica sem `casos.md` — e isso é resíduo honesto, não pendência a zerar
- [ ] O placar volta a ser lido pelas portas vivas, não por um número escrito aqui

**Implementado em:** _pendente_

#### US-FORJA-010 · Mesa de Aprovações — a superfície do funil de admissão

> owner: [W] · priority: p0 · estimate: _pendente_ · status: proposto · type: feature
> blocked_by: —

⛔ **É SUPERFÍCIE DE MECANISMO EXISTENTE — proibido criar um 2º funil.**
A [ADR 0368](../../decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) (aceita
2026-08-04) fechou a política e escreveu, textual, que *"o código vai em PR próprio, com evidência"*.
O que já existe e **não se reimplementa**: o estado
([`McpTask::AWAITING_HUMAN`](../../../Modules/Jana/Entities/Mcp/McpTask.php) = `pending_approval`),
o FSM (`TRANSITIONS['pending_approval']` → `todo`/`backlog`/`cancelled`) e a trava de
recusa-sem-motivo ([`TaskCrudService`](../../../Modules/Jana/Services/TaskRegistry/TaskCrudService.php),
ADR 0368 §5) — tudo entregue em [#5283](https://github.com/wagnerra23/oimpresso.com/pull/5283) /
[#5288](https://github.com/wagnerra23/oimpresso.com/pull/5288).

**O gap é só a TELA.** Verificado 2026-08-08: a fila existe no banco e não tem superfície nenhuma —
o Daily Brief chega a anunciar a contagem sem que haja onde clicar.

- [ ] Tela `/forja/aprovacoes` listando `pending_approval` em ordem de espera (mais antigo primeiro)
- [ ] Decisão pela rota, delegando 100% ao `TaskCrudService` — **sem** segundo caminho de escrita
- [ ] Botões derivados de `McpTask::TRANSITIONS` — **proibido** hardcodar a lista de saídas
- [ ] Vocabulário da ADR 0368 §6 (`admitida`/`recusada`), **nunca** "aprovado" (colide com o `✅ APROVADO` do INVENTARIO)
- [ ] Sem permission nova (ADR 0368 §4 — um só aprovador não justifica cerimônia)
- [ ] Pest citando cada UC do `Aprovacoes/Index.casos.md`, na allowlist do `forja-pest.yml`

⚠️ **Fica FORA desta US** (dívida vizinha, medida no mesmo dia, PR próprio): a procedure do Daily
Brief calcula `hitl_pending` como `status='blocked' AND owner='wagner'` — exatamente o proxy que a
ADR 0368 §3 aposentou. Reconciliar exige migration de procedure + `ProcedureDriftSnapshotTest`.

**Implementado em:** _pendente_

---

## Onda 2 — Triage + Inbox (US-TR-301..308 · SPEC-UI-FASE7)

**Implementado em:** `Modules/Forja/Http/Controllers/TriageController.php` · `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> Superfícies humanas das tools MCP `triage` e `my-inbox`. Telas: `Modules/Forja/Resources/js/Pages/Forja/{Triage,Inbox}/Index.tsx`.
> **PR #1940 — code-complete, segue DRAFT** aguardando gate visual do Wagner (ADR 0107/0114; Chrome MCP off).
> Fonte funcional: [`TaskRegistry/SPEC-UI-FASE7.md`](../TaskRegistry/SPEC-UI-FASE7.md) (pasta `TaskRegistry/` é HISTORICAL→TeamMcp, mas **este arquivo segue a fonte viva** destas telas). RUNBOOK: [`RUNBOOK-index.md`](RUNBOOK-index.md). Visual: [`projectmgmt-index-visual-comparison.md`](projectmgmt-index-visual-comparison.md) (status draft).

### US-TR-309 · Triage — lista de tasks órfãs

> owner: wagner · priority: p1 · estimate: codável (fator 10x) · status: review · type: feature

Como membro do time, vejo uma tela **Triage** (`/project-mgmt/triage`) com todas as tasks órfãs (sem owner OU sem prioridade OU em backlog). A lista = MESMO conjunto que a tool MCP `triage` (scope `McpTask::triage()`, exclui done/cancelled). Vazio → empty state **"Nada pra triar"** (sem emoji — AP). Implementado em [`Triage/Index.tsx`](../../../Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx) + [`TriageController`](../../../Modules/Forja/Http/Controllers/TriageController.php).

### US-TR-310 · Triage — atribuir owner + prioridade inline

**Implementado em:** `Modules/Forja/Http/Controllers/TriageController.php` · `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

Na Triage, atribuo **owner + prioridade inline** sem abrir a task: select inline → `PATCH /triage/{taskId}/assign` (reusa `TaskCrudService::update`, mesma via da tool `tasks-update`) → UI otimista + rollback em erro; gera `mcp_task_events` + notifica o novo owner.

### US-TR-311 · Triage — mover cycle/epic

**Implementado em:** `Modules/Forja/Http/Controllers/TriageController.php` · `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p2 · estimate: codável · status: review · type: feature

Na Triage, movo a task pra um **cycle/epic** opcionalmente (dropdowns na mesma linha); persiste; a task **some da lista** quando deixa de ser órfã (`still_triage=false`).

### US-TR-304 · Inbox — lista de não-lidas

**Implementado em:** `Modules/Forja/Http/Controllers/InboxController.php` · `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

Como membro, vejo uma tela **Inbox** (`/project-mgmt/inbox`) com minhas notificações: lê `mcp_inbox_notifications WHERE user_id=me` (não-lidas por default), **agrupado por tipo**. Paridade com a tool MCP `my-inbox`. Implementado em [`Inbox/Index.tsx`](../../../Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx) + [`InboxController`](../../../Modules/Forja/Http/Controllers/InboxController.php).

### US-TR-305 · Inbox — marcar lido (individual + todas)

**Implementado em:** `Modules/Forja/Http/Controllers/InboxController.php` · `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

No Inbox, **marco como lido** individual (`PATCH /inbox/{id}/read`) e "marcar todas" (`PATCH /inbox/read-all`), otimista com rollback. Escopo `user_id` (Tier 0). Badge realtime via Centrifugo ([ADR 0058](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md)) fica pra fase seguinte (polling 30s cobre agora).

### US-TR-306 · Inbox — deep-link pra task/DetailSheet

**Implementado em:** `Modules/Forja/Http/Controllers/InboxController.php` · `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p1 · estimate: codável · status: review · type: feature

No Inbox, clico (ou Enter) numa notificação e vou direto pra **task** no Board com o `DetailSheet` aberto (`/project-mgmt/board?task=ID`), marcando lido no caminho.

### US-TR-307 · Operador não-técnico usa sem treino

**Implementado em:** `Modules/Forja/Http/Controllers/BoardController.php` · `Modules/Forja/Http/Controllers/TriageController.php` · `Modules/Forja/Http/Controllers/InboxController.php` · `Modules/Forja/Resources/js/Pages/Forja/Board/Index.tsx` · `Modules/Forja/Resources/js/Pages/Forja/Triage/Index.tsx` · `Modules/Forja/Resources/js/Pages/Forja/Inbox/Index.tsx` · verificado@98cae0a (2026-06-18)

> owner: wagner · priority: p2 · estimate: codável · status: review · type: feature

Como operador **não-técnico**, uso Board/Backlog/Triage/Inbox sem treino: labels PT-BR claros, empty states **sem emoji**, foco-teclado (J/K + Enter + ⌘K palette global), toque-friendly ≥360px. A revisar por `design:accessibility-review` no gate visual.

### US-TR-308 · Chips de ADRs/SPECs relacionados (memory-linked)

> owner: wagner · priority: p2 · estimate: codável · status: todo · type: feature

Vejo no card os **ADRs/SPECs relacionados** (diferencial memory-linked): `mcp_task_memory_links` como chips no card/DetailSheet. Reusa o que o DetailSheet do Board já faz. **Não** entregue nesta Onda 2 (vive no Board) — registrado como gap.

---

## Regras de negócio (Gherkin)

### R-PMG-001 · Permission gate `jana.mcp.usage.all`

```gherkin
Dado que um usuário NÃO tem permission `jana.mcp.usage.all`
Quando ele acessa qualquer endpoint do BoardController/SearchController
Então recebe 403 Unauthorized
```

**Implementado em:** Middleware `can:jana.mcp.usage.all` no constructor dos Controllers.
**Testado em:** 6+ cenários `BoardControllerTest::*sem permission*`.

### R-PMG-002 · Multi-tenant não aplicável (governance)

```gherkin
Dado que mcp_* são tabelas de governance (não business)
Quando endpoints retornam dados de mcp_tasks/comments/etc
Então não aplica filter business_id (mcp_* não tem essa coluna)
```

**Justificativa:** ADR 0070 § "tasks são governance, não scoped por business_id".

### R-PMG-005 · Drag-drop concorrente preserva integridade (PMG-001)

```gherkin
Dado que dois usuários têm `/project-mgmt/board` aberto
Quando ambos arrastam o mesmo card simultaneamente para colunas diferentes
Então o segundo PATCH (com expected_updated_at obsoleto) retorna 409 Conflict com `current` state
E o frontend do segundo usuário reverte otimismo + mostra banner + refeta silencioso
```

**Implementado em:** [`BoardController::updateStatus`](../../../Modules/Forja/Http/Controllers/BoardController.php) + `Board/Index.tsx`.
**Testado em:** `BoardControllerTest::R-PMG-005: PATCH com expected_updated_at obsoleto retorna 409`.

---

## Status

- **Última atualização**: 2026-08-04 — frontmatter migrado pro formato atual do schema, drift do PMG-008 corrigido (tinha shipado com `status: todo`), Fase 5 proposta.
  - _2026-05-08_ — Fase 2 completa (PRs #220 #222 #224 #226 mergeadas). Mantido como marco histórico.
- **Owner produto**: [W]
- **Cobertura de teste**: não fixar número aqui (o "27 testes" da redação de 2026-05-08 apodreceu). Portas vivas: `node scripts/governance/module-surface.mjs Forja --check` (superfície, incl. arquivos Pest) e `npm run casos:report` (contrato por tela). Re-rode em vez de editar um número.
- **Próximo passo**: as **duas decisões [W]** da Fase 5 destravam o resto — **US-FORJA-005** (rito de cycle: reativar ou aposentar; PMG-009/010 dependem dela) e **US-FORJA-006** (sobreposição cockpit × telas nativas). Codar PMG-009/010 antes da 005 é arriscar construir pra um rito que vai ser aposentado.

## Métricas (a coletar pós-Fase 2)

- Wagner usa Cmd+K ≥5×/dia per user ativo (telemetria `palette.opened`)
- Wagner usa drag-drop ≥10×/dia (telemetria `board.task.moved`)
- ≥80% das sessões de dev usam atalho J/K (telemetria `hotkey.fired`)
- Notification dispatch via @mention dispara ≥3 vezes em prod (validação backend)
- ≥3 watchers reais cadastrados em tasks ativas
- ≥5 subtasks criadas em tasks reais

#### US-FORJA-009 · Pôr os testes da Forja numa lane de CI (41+ nunca executam)

> owner: [W] · priority: p0 · estimate: _pendente_ · status: proposto · type: chore
> blocked_by: —

**Medido em 2026-08-04 contra `origin/main`** (grep nas duas allowlists, contagem de `it(`/`test(`):

| Arquivo | testes | `.github/ci-sqlite-pest.list` | allowlist MySQL do `forja-pest.yml` |
|---|---|---|---|
| `BoardControllerTest.php` | 25 | **0 ocorrências** | **0 ocorrências** |
| `TriageControllerTest.php` | 9 | **0** | **0** |
| `InboxControllerTest.php` | 7 | **0** | **0** |
| `SearchControllerTest.php` | — | **0** | **0** |

As duas lanes são **allowlists explícitas**; a lane MySQL roda literalmente um arquivo
(`Modules/Forja/Tests/Feature/ForjaRoutesSmokeTest.php`, L139 do workflow). Os arquivos estão em
`phpunit.xml`, o que dá a **aparência** de cobertura — mas ninguém os executa. É a classe **LC-13**
(*verde por não-execução*) em escala de módulo: `"0 failed"` de teste que não rodou não prova nada.

**Acoplamento que torna isto P0:** os `casos.md` de Board/Triage/Inbox declaram 21 UCs com status `🧪`.
O `✅` do G-7 é derivado do manifesto `scripts/casos-test-results.json`, alimentado pelo JUnit **do CI**.
Enquanto estes arquivos não estiverem numa lane, **nenhum desses 21 UCs pode virar `✅` por construção** —
o trio existe e não pode ser provado.

- [ ] Entrar os 4 arquivos numa lane (sqlite ou MySQL — a escolha sai de rodar e ver qual passa, não de opinião)
- [ ] ⚠️ **Esperar vermelho de dívida acumulada é o normal, não regressão de quem ligou** — precedente registrado no próprio `forja-pest.yml` (*"entra na allowlist SEM ser verde — é FAILING-FIRST por desenho"*)
- [ ] Antes de ligar, tratar os 2 falso-verdes que o trio catalogou: `markTestSkipped` no 403 (~14 testes — skip sai exit 0) e bootstrap com `User::first()` + `is_admin`, que faz o `Gate::before` liberar tudo e o teste de 403 nunca provar 403
- [ ] Rodar no CT 100 (`oimpresso-staging`), **nunca local** ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md))
- [ ] DoD: `junit-summary.mjs` mostra **assertions > 0** por arquivo — não `0 failed` (§5 2026-07-24 · LC-13)

**Implementado em:** _pendente_

## Referências

- [ADR 0070](../../decisions/0070-jira-style-task-management-current-md-removed.md) — Jira-style task management (escopo do módulo)
- [ADR 0099](../../decisions/0099-project-legacy-discovery-pre-deletion.md) — Discovery legacy `Modules/Project` queue-for-delete
- [ADR 0100](../../decisions/0100-projectmgmt-ui-redesign.md) — Forja UI Redesign 4 fases
- [CHARTER-board.md](CHARTER-board.md) — anatomia + personas + fluxos + anti-padrões
- [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) — gap analysis ✅🟡❌
- [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) — 24 capacidades P0-P3
- [`memory/requisitos/TaskRegistry/SPEC.md`](../TaskRegistry/SPEC.md) — SPEC funcional histórico (US-TR-NNN; renaming pendente Fase 3.9)
- PRs cadeia: [#197](https://github.com/wagnerra23/oimpresso.com/pull/197) [#202](https://github.com/wagnerra23/oimpresso.com/pull/202) [#205](https://github.com/wagnerra23/oimpresso.com/pull/205) [#207](https://github.com/wagnerra23/oimpresso.com/pull/207) [#209](https://github.com/wagnerra23/oimpresso.com/pull/209) [#211](https://github.com/wagnerra23/oimpresso.com/pull/211) [#220](https://github.com/wagnerra23/oimpresso.com/pull/220) [#222](https://github.com/wagnerra23/oimpresso.com/pull/222) [#224](https://github.com/wagnerra23/oimpresso.com/pull/224) [#226](https://github.com/wagnerra23/oimpresso.com/pull/226)
