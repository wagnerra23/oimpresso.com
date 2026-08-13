---
id: requisitos-forja-runbook-gantt
title: "RUNBOOK — Forja Roadmap Gantt (`/forja/roadmap-gantt`)"
module: Forja
tela: Forja/Roadmap/Gantt
owner: W
status: ativo
last_validated: "2026-08-05"
preconditions:
  - "Usuário autenticado com permission `jana.mcp.tasks.read` (leitura do Gantt)"
  - "Permission `jana.mcp.tasks.write` adicional pra reagendar prazo por drag-drop (US-COPI-111 B2)"
  - "Tabelas `mcp_cycles` + `mcp_tasks` populadas (sync git→MCP via SPEC.md, ADR 0070)"
  - "Pacote `@svar-ui/react-gantt` (MIT) instalado — já está no package.json desde a Onda 5 V1 do Jana"
preconditions_short: jana.mcp.tasks.read (+ .write pra reagendar), mcp_cycles/mcp_tasks populadas, @svar-ui/react-gantt
steps:
  - "GET /forja/roadmap-gantt carrega cycles (20 recentes) + tasks do cycle ativo (máx. 500) + listas distintas de owners/modules"
  - "Filtros cycle/owner/priority/module aplicam via Inertia partial reload (only: tasks/filters), preservando state e scroll"
  - "Click numa barra do Gantt abre Sheet lateral com detalhe da task + snippet `tasks-detail task_id:<ID>`"
  - "Com jana.mcp.tasks.write: arrastar/redimensionar a barra dispara PATCH /forja/roadmap-gantt/tasks/{taskId}/schedule com o novo due_date"
  - "O PATCH persiste via TaskCrudService::update (atômico + audit McpTaskEvent) e volta com back() — o partial reload re-renderiza o Gantt"
  - "Sem tasks no filtro: mensagem 'Sem tasks no filtro atual' (nunca skeleton infinito)"
related_adrs:
  - 0070-jira-style-task-management-current-md-removed
  - 0087-drift-resolution-sem-mover-url
  - 0093-multi-tenant-isolation-tier-0
  - 0104-processo-mwart-canonico-unico-caminho
  - 0110-cockpit-pattern-v2-canon-list-detail
  - 0253-primitivos-layout
  - 0366-fronteira-jana-forja-governance-kb
  - 0367-cockpit-unico-forja-project-mgmt-morre
---

# RUNBOOK — Forja Roadmap Gantt

> Rota: `GET /forja/roadmap-gantt` · `PATCH /forja/roadmap-gantt/tasks/{taskId}/schedule`
> Componente: `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.tsx`
> Charter: `Modules/Forja/Resources/js/Pages/Forja/Roadmap/Gantt.charter.md`
> Controller: `Modules/Forja/Http/Controllers/RoadmapGanttController.php`
> Testes: `Modules/Forja/Tests/Feature/Roadmap/RoadmapGanttControllerTest.php`
> ⚠️ **Nome do arquivo (`RUNBOOK-gantt.md`, não `RUNBOOK-roadmap-gantt.md`):** o hook
> [`block-mwart-violation.mjs`](../../../.claude/hooks/block-mwart-violation.mjs) só aceita
> `RUNBOOK-<kebab(tela)>.md` ou `RUNBOOK-<kebab(subdir)>.md` — pra `Forja/Roadmap/Gantt.tsx` isso é
> `RUNBOOK-gantt.md` ou `RUNBOOK-roadmap.md`. O resgate por `related_runbook:` do charter **não
> funciona** aqui: o hook faz `join(root, file_path)` e o `file_path` que o Claude Code entrega é
> ABSOLUTO, então no Windows vira `D:\repo\D:\repo\...` e o `existsSync` do charter falha sempre.
> `RUNBOOK-gantt.md` foi escolhido em vez de `RUNBOOK-roadmap.md` porque este último cobriria também
> `Forja/Roadmap/Index.tsx` (o quarter view), que **não** é descrito aqui.
>
> Origem: porte de `Modules/Jana/Http/Controllers/Admin/RoadmapController` + `Pages/Jana/Admin/Roadmap.tsx`
> ([ADR 0366 §D-B](../../decisions/0366-fronteira-jana-forja-governance-kb.md) — *"usa `TaskCrudService`/`McpTask`
> — é tasks, e tasks é Forja"*; [ADR 0367 D4](../../decisions/0367-cockpit-unico-forja-project-mgmt-morre.md)).

---

## 0. Por que esta tela mora na Forja (e não no Jana nem no Governance)

O critério da [ADR 0366 §D-A](../../decisions/0366-fronteira-jana-forja-governance-kb.md) é **a pergunta
que o módulo responde**. Esta tela responde *"o que a gente está fazendo, e o que vence quando"* — a
pergunta da Forja (time interno), não a do Jana (*"como está meu negócio"*, cliente) nem a do Governance
(*"a regra está sendo cumprida"*). A ADR nomeia o destino explicitamente e avisa que mandar pro Governance
*"criaria a 3ª tela de roadmap"*.

⚠️ **Convive com o quarter view, não o substitui.** `/project-mgmt/roadmap` (`Forja/Roadmap/Index`) segue
vivo por decisão explícita da [ADR 0367 D7](../../decisions/0367-cockpit-unico-forja-project-mgmt-morre.md):
ele agrupa **epics por trimestre**, esta agrupa **tasks no tempo**. Análise completa da não-duplicação em
[`memory/sessions/2026-08-05-duplicacao-roadmap-forja.md`](../../sessions/2026-08-05-duplicacao-roadmap-forja.md).

---

## 1. Superfície

| Endpoint | Método | Permission | O que faz |
|---|---|---|---|
| `/forja/roadmap-gantt` | GET | `jana.mcp.tasks.read` | Render do Gantt + filtros |
| `/forja/roadmap-gantt/tasks/{taskId}/schedule` | PATCH | `jana.mcp.tasks.write` | Reagenda **só** o `due_date` |

Nomes de rota: `forja.roadmap-gantt.index` · `forja.roadmap-gantt.schedule`.

Middlewares (idênticos ao resto do grupo `/forja`):
`['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`.

⛔ **As permissions NÃO foram renomeadas no porte.** Continuam `jana.mcp.tasks.read` / `.write`, o mesmo
par que o Jana usava. Permission Spatie vive por **id de linha** — renomear revoga acesso em silêncio, sem
erro e sem log ([ADR 0087](../../decisions/0087-drift-resolution-sem-mover-url.md), reafirmado no §Custo de
execução da 0367). Rename, se um dia acontecer, é ADR + migration própria.

---

## 2. Payload do `index`

```
cycles[]          20 mais recentes (active → planning → closed), pro dropdown
tasks[]           máx. 500, do cycle selecionado, ordenadas por module → priority → due_date
filters{}         cycle | owner | priority | module (espelho dos query params)
owners[]          distinct de mcp_tasks.owner  ← CLOSURE (ver §3)
modules[]         distinct de mcp_tasks.module ← CLOSURE (ver §3)
active_cycle_id   id do cycle status=active (ou null)
can_edit          bool — user tem jana.mcp.tasks.write
```

Filtros são query params (`?cycle=&owner=&priority=&module=`). `cycle=current` ou ausente resolve pro
cycle `status=active`.

---

## 3. ⚠️ Pegadinha preservada do original: `owners`/`modules` são CLOSURE, não `Inertia::defer`

O código traz o comentário abaixo **intacto do Jana** e ele não é cosmético:

```php
// Wagner 2026-05-25 HOTFIX: removido Inertia::defer (owners+modules).
// Roadmap.tsx destruct direto — TypeError `undefined.map` em prod.
```

A regra geral do projeto ([RUNBOOK-inertia-defer-pattern](../_DesignSystem/RUNBOOK-inertia-defer-pattern.md))
manda deferir prop cara. Aqui a exceção é **consciente e testada em produção**: o `.tsx` desestrutura
`owners`/`modules` direto e chama `.map()` no 1º render. Com `defer`, a prop chega `undefined` no primeiro
paint → `TypeError` → tela branca.

A **closure** (`fn () => DB::table(...)`) dá o melhor dos dois: roda no load cheio (1º render nunca vê
`undefined`) e é **pulada no partial reload** (`only: ['tasks','filters']`), que é o caminho quente do
filtro. Este é o padrão D-14 (ref. PR #3889).

⛔ **Não "consertar" isso trocando closure por `Inertia::defer`.** Se alguém quiser deferir, precisa antes
dar default-guard no destructuring do `.tsx` (`owners = []`), como o `Forja/Roadmap/Index.tsx` faz com
`quarters`/`kpis`. As duas mudanças andam juntas ou nenhuma.

---

## 4. Reschedule por drag-drop (US-COPI-111 B2)

- O SVAR Gantt dispara `update-task` no **commit** do drag, com a task já carregando as datas novas.
- Persistimos **apenas `due_date`**. `started_at` é lifecycle-managed (auto-setado quando a task vai pra
  `doing`, ADR 0070) — não é campo editável à mão, então a ponta arrastável é o **prazo**, não o início.
- O `{taskId}` do PATCH é o **`task_id` string** (ex. `US-COPI-110`), não o id numérico que o Gantt usa
  internamente. O frontend mapeia via `$payload.task_id` antes de disparar.
- Summary parents têm id **string** (`g-1`, `g-2`, …) — o handler ignora tudo que não for `number`.
- A escrita passa pelo `Modules\Jana\Services\TaskRegistry\TaskCrudService::update` — a **mesma via** do
  tool MCP `tasks-update`: atômico (lock de linha), allowlist de campos, audit via OTel + `McpTaskEvent`.
  O Service **permanece no Jana**; a Forja só importa (mesmo precedente do `Forja\RoadmapController`, que
  importa `Modules\Jana\Entities\Mcp\McpTask`).
- Quem não tem `jana.mcp.tasks.write` recebe o Gantt em `readonly` — o `api.on('update-task')` nem é
  registrado, então o drag não existe no cliente **e** o endpoint devolve 403 no servidor.

---

## 5. Multi-tenant (Tier 0 — ADR 0093)

`mcp_tasks` e `mcp_cycles` são **cache canon cross-business** — não têm coluna `business_id`, porque a
fonte de verdade é o git (SPEC.md por módulo). Cai na §exceções repo-wide da
[ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md), igual `TriageController`, `ScorecardController`
e `ForjaController`.

O isolamento aqui **é a permission**, não o scope: user de outro business sem `jana.mcp.tasks.read` recebe
403. Há teste cobrindo exatamente isso (`RoadmapGanttControllerTest`, caso cross-tenant).

⛔ Se um dia a tela virar per-business, o filtro entra na query **e** o teste cross-tenant vira asserção de
dado (não de status HTTP).

---

## 6. Frontend — o que é obrigatório e por quê

| Regra | Motivo mecânico |
|---|---|
| Zero `flex`/`grid` cru no `className` | `layout:check` (`scripts/layout-primitives-guard.mjs`) falha **arquivo novo com base 0**. Compor `Stack`/`Inline`/`Grid` de `@/Components/layout` ([ADR 0253](../../decisions/0253-primitivos-layout.md)) |
| Zero cor crua do palette (`bg-rose-500`, `text-emerald-700`…) | `ds/no-raw-palette-color` no `eslint.config.js`. Prioridade usa `Badge variant="danger\|warning\|info\|neutral"` |
| `<label htmlFor>` casando `id` do `SelectTrigger` | `jsx-a11y/label-has-associated-control` — a11y é categoria **protegida** (`a11y:check`, só desce) |
| `SafeSelectItem` pros dropdowns data-driven | `owners`/`modules` vêm de `distinct` do banco e podem ter linha vazia → `<SelectItem value="">` **crasha o render inteiro** do Radix (lápide `proibicoes.md` §5 2026-06-29, tela branca em prod) |
| `PageHeader` de `@/Components/PageHeader` | O `@/Components/shared/PageHeader` está `@deprecated` e o `pageheader-gate` falha se **tela nova** importar |
| `as unknown as {…}` no lugar de `as any` | Regra do projeto pra `.tsx` |

O Gantt em si (`<Gantt>` do `@svar-ui/react-gantt`) recebe `tasks`/`links`/`scales` com cast `as never` —
os tipos da lib são mais estreitos que o shape real que ela aceita. Isso veio do original e é intencional.

---

## 7. Smoke real (R1 — antes de declarar pronto)

```bash
# 1) 302 pro login quando anônimo
curl -sv https://oimpresso.com/forja/roadmap-gantt 2>&1 | grep '^< HTTP'

# 2) logado com jana.mcp.tasks.read → 200 + component correto
#    (via browser MCP: screenshot 1280px + console sem EXCEPTION)

# 3) regression adjacent — as duas rotas vizinhas NÃO podem mudar
curl -sv https://oimpresso.com/project-mgmt/roadmap 2>&1 | grep '^< HTTP'   # quarter view segue vivo
curl -sv https://oimpresso.com/forja/backlog       2>&1 | grep '^< HTTP'   # cockpit intacto
```

⛔ PR que toca `.tsx` exige **screenshot pós-merge** antes de qualquer "pronto/funcionando"
(`proibicoes.md` §Claim sem evidência). CI verde não prova render — o incidente do Radix `value=""`
passou por 20 checks verdes e derrubou a tela.

---

## 8. Testes

`Modules/Forja/Tests/Feature/Roadmap/RoadmapGanttControllerTest.php` — 10 casos, portados 1:1 do
`Modules/Jana/Tests/Feature/Roadmap/RoadmapControllerTest.php` com as URLs novas:

| # | Caso |
|---|---|
| 1 | Redireciona pra login se anônimo |
| 2 | 403 sem `jana.mcp.tasks.read` |
| 3 | 200 + component `Forja/Roadmap/Gantt` + props canônicas |
| 4 | Filtro por `cycle` |
| 5 | Filtro por `module` |
| 6 | Cross-tenant: user de outro business sem permission → 403 |
| 7 | Lista vazia não quebra |
| 8 | 403 no reschedule sem `jana.mcp.tasks.write` |
| 9 | 422/redirect-com-erro sem `due_date` |
| 10 | Reagenda o `due_date` de fato via `TaskCrudService` |

`Modules/Forja/Tests/Feature` já está registrado em `phpunit.xml` (linha 33) — o CI roda.

⛔ **Não rodar Pest local.** Testes rodam no CT 100 (`proibicoes.md` §Ambiente, hook
`block-test-fora-ct100.mjs`) ou no CI. E `0 failed` não prova execução — leia **assertions** (§5 LC-13).

---

## 9. Rollback

O porte é **aditivo**: cria rota/controller/page novos. Reverter = remover as 2 rotas de
`Modules/Forja/Http/routes.php`, o ghost `roadmap-gantt` do `DataController` da Forja, e restaurar o
ghost `roadmap` no `DataController` do Jana. Nada de schema, nada de permission, nada de migration —
por construção não há o que reverter no banco.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-08-05 | [C] | RUNBOOK criado no porte Jana→Forja (Wave C). F1 PLAN da ADR 0104, pré-requisito do `.tsx`. |
