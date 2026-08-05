---
date: "2026-08-05"
topic: "Análise de duplicação antes de portar o Gantt /ia/admin/roadmap para a Forja (ADR 0366 §D-C item 3)"
authors: [C]
related_adrs:
  - 0366-fronteira-jana-forja-governance-kb
  - 0367-cockpit-unico-forja-project-mgmt-morre
  - 0070-jira-style-task-management-current-md-removed
  - 0087-drift-resolution-sem-mover-url
  - 0093-multi-tenant-isolation-tier-0
---

# Duplicação de roadmap antes do porte do Gantt (Wave C)

> A [ADR 0366 §D-C](../decisions/0366-fronteira-jana-forja-governance-kb.md) marca o movimento #3
> (`Admin/Roadmap` → Forja) como **"conferir duplicação antes"**. Este é o recibo dessa conferência.

## O que foi medido (leitura de código, não impressão)

| Eixo | `/project-mgmt/roadmap` (quarter) | `/ia/admin/roadmap` (Gantt) |
|---|---|---|
| Controller | `Modules\Forja\Http\Controllers\RoadmapController` | `Modules\Jana\Http\Controllers\Admin\RoadmapController` |
| Componente Inertia | `Forja/Roadmap/Index` | `Jana/Admin/Roadmap` |
| Entidade primária | `McpEpic` (via `McpProject`) | `mcp_cycles` + `mcp_tasks` (query builder cru) |
| Unidade de leitura | **epic** agrupado por `target_quarter` | **task** agrupada por `module`, em timeline |
| Recorte | projeto configurável (`?project=`, default `projectmgmt.default_project_key`) | cycle (default = cycle ativo) + owner + priority + module |
| Permission | `jana.mcp.usage.all` | `jana.mcp.tasks.read` (index) · `jana.mcp.tasks.write` (reschedule) |
| Mutação | nenhuma (read-only; edita `target_quarter` só via MCP `epics-update`) | `PATCH .../schedule` → `TaskCrudService::update` (só `due_date`) |
| Props | `project`, `quarters`, `kpis` (2 via `Inertia::defer`) | `cycles`, `tasks`, `filters`, `owners`, `modules`, `active_cycle_id`, `can_edit` |
| Volume medido na 0367 | 5 epics vivos, cabe numa tela | `500 tasks no filtro atual` · `Timeline (531 linhas)` |

## Veredito

**Não são duplicatas — são duas leituras incomensuráveis do mesmo backlog, e as duas ficam.**
O quarter view responde *"o que entregamos neste trimestre e quanto andou"* sobre a unidade **epic**;
o Gantt responde *"o que vence essa semana e o que bloqueia o quê"* sobre a unidade **task**. Nenhuma
das duas consegue responder a pergunta da outra sem mudar de unidade de agregação: o quarter view não
tem `due_date` nem `blocked_by` (não sabe ordenar no tempo), e o Gantt não tem `epic_id` no payload
(não sabe agrupar por trimestre). A [ADR 0367 D7](../decisions/0367-cockpit-unico-forja-project-mgmt-morre.md)
já decidiu isso textualmente — o quarter view *"sobrevive como segunda leitura do roadmap e **só sai
quando o Gantt provar que substitui** (filtro por cycle efetivo + volume domado)"* — e classifica o
Gantt de hoje como *"despejo cronológico, não tela de decisão"*. Trocar um pelo outro agora seria
regressão, não consolidação.

**Consequência prática deste porte:** a tela nova nasce como `Forja/Roadmap/Gantt` (arquivo
`Gantt.tsx`), **não** `Index.tsx` — o `Index` já é do quarter view e sobrescrevê-lo seria justamente
a fusão que a 0367 D7 proibiu. O `Modules\Forja\...\RoadmapController` (quarter) fica **intocado**; o
porte cria `RoadmapGanttController` ao lado. Duas classes, dois componentes, zero colisão de nome.

## Como as duas devem aparecer na navegação

Hoje o hub Equipe (`Modules/Forja/Http/Controllers/DataController.php`, entry `order(91)`) já expõe
o ghost `['key' => 'roadmap', 'label' => 'Roadmap', 'href' => '/project-mgmt/roadmap']` — o quarter
view. O Gantt precisa de **entrada própria com key distinta**, senão some da navegação (foi
exatamente o que aconteceu no Jana: o ghost `roadmap` → `/ia/admin/roadmap` nasceu em 2026-05-25
*"após audit Jana (browser MCP smoke detectou 3 Pages órfãs sem link)"*).

Recomendação (execução é do parent — este agente não edita `DataController.php`):

| Onde | Entrada | Label | href |
|---|---|---|---|
| `Modules/Forja/.../DataController.php` ghosts do hub | `roadmap` (existente) | `Roadmap` | `/project-mgmt/roadmap` |
| idem | `roadmap-gantt` (**novo**) | `Roadmap (Gantt)` | `/forja/roadmap-gantt` |
| `Modules/Jana/.../DataController.php` ghosts | `roadmap` (**remover**) | — | — |

Os dois labels precisam se distinguir no texto porque vão aparecer lado a lado no mesmo dropdown —
"Roadmap" (trimestre) e "Roadmap (Gantt)" (timeline). Quando a 0367 E4/E5 aposentar `/project-mgmt/*`,
o primeiro sai e o segundo pode reassumir o label curto.

## Por que a rota é `/forja/roadmap-gantt` e não `/forja/roadmap`

Dois motivos, ambos verificados no código:

1. **`useAutoModuleNav` casa o topnav pelo 1º segmento da URL** (`config/core_topnavs.php` §Forja,
   comentário literal). `/forja/*` já é o cockpit; a tela nova herda o topnav do hub de graça.
2. O sufixo `-gantt` deixa a coexistência legível na URL enquanto o quarter view viver. `/forja/roadmap`
   ficaria ambíguo com `/project-mgmt/roadmap` para quem lê o log de acesso.

## Nota de execução — nome do RUNBOOK

O RUNBOOK saiu como `memory/requisitos/Forja/RUNBOOK-gantt.md` (e não `RUNBOOK-roadmap-gantt.md`)
porque o hook `block-mwart-violation.mjs` deriva o nome aceito de `kebab(tela)` ou `kebab(subdir)` —
pra `Forja/Roadmap/Gantt.tsx` isso é `RUNBOOK-gantt.md` ou `RUNBOOK-roadmap.md`, e nada mais passa.
O caminho de resgate por `related_runbook:` do charter **não funciona**: o hook faz
`join(root, file_path)` e o `file_path` entregue pelo Claude Code é **absoluto**, então no Windows
o caminho do charter vira `D:\repo\D:\repo\…` e o `existsSync` falha sempre — o resgate por
proveniência está morto pra qualquer tela, não só esta. Vale reportar como bug do hook.

Colisão de rota conferida: o grupo `/forja` tem `Route::get('/{taskId}/dossier')` — **2 segmentos**.
`/forja/roadmap-gantt` tem **1**, e `/forja/roadmap-gantt/tasks/{taskId}/schedule` tem **4**. Nenhum
wildcard de 1 segmento existe no grupo. Sem colisão.

## O que este porte NÃO faz

- ❌ Não deleta, altera nem esconde `/project-mgmt/roadmap` (quarter view) — 0367 D7.
- ❌ Não renomeia as permissions `jana.mcp.tasks.read` / `.write` — rename é ADR + migration própria
  ([ADR 0087](../decisions/0087-drift-resolution-sem-mover-url.md): permission Spatie vive por id de
  linha; mexer revoga acesso em silêncio).
- ❌ Não move `TaskRegistry\TaskCrudService` do Jana — o precedente já está no repo
  (`Modules\Forja\...\RoadmapController` importa `Modules\Jana\Entities\Mcp\McpTask` sem cerimônia;
  o item #4 da 0366 §D-C, que moveria as `Mcp*`, **não está autorizado** por aquela ADR).
- ❌ Não vira aba do `team-mcp/Forja/Cockpit`. A 0367 D4 diz "aba da Forja", mas o Cockpit é um shell
  de 5 abas que compartilham o mesmo componente e trocam só a prop `tab`; o Gantt tem payload,
  filtros e mutação próprios. Nasce como tela própria sob `/forja/*` (mesmo prefixo, mesmo topnav,
  mesma permission-family) — fundir no shell é decisão [W] separada, com o volume já domado (E3).
