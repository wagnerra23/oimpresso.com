---
id: resources-js-pages-forja-roadmap-gantt-casos
casos: Roadmap Gantt do time · /forja/roadmap-gantt
irmaos: Gantt.charter.md (lei) · memory/requisitos/Forja/RUNBOOK-gantt.md (operação)
tecnica: Caso de uso = narrativa de quem usa + critério de aceite verificável (Dado/Quando/Então)
por_que: o comportamento é durável — "quem enxerga o roadmap" e "quem pode mexer no prazo" não mudam no refactor; o Gantt em si pode virar outra lib.
owner: wagner
last_run: "2026-08-12"
---

# Casos de Uso & Aceite — Roadmap Gantt (Forja)

> Trio da tela portada de `Jana/Admin/Roadmap` pela [ADR 0366 §D-B](../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md)
> + [ADR 0367 D4](../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md).
> Os UC derivam do **charter** (`Gantt.charter.md`) e do contrato do endpoint, não do `.tsx` —
> teste derivado da implementação é tautológico (`proibicoes.md` §5 2026-06-05).
>
> ⚠️ **Escopo:** estes UC cobrem SÓ o Gantt por **tarefa**. O quarter view por **epic**
> (`Forja/Roadmap/Index`, `/project-mgmt/roadmap`) é outra tela, segue viva por decisão [W]
> (ADR 0367 D7) e tem contrato próprio. Recibo da não-duplicação:
> [`memory/sessions/2026-08-05-duplicacao-roadmap-forja.md`](../../../../memory/sessions/2026-08-05-duplicacao-roadmap-forja.md).
>
> ⚖️ **Onde estes UC rodam:** `Modules/Forja/Tests/Feature/Roadmap/RoadmapGanttControllerTest.php`,
> dentro de `Modules/Forja/Tests/Feature` (registrado em [`phpunit.xml`](../../../../phpunit.xml) linha 33).
> Pest roda no **CT 100 ou no CI** — nunca local (`proibicoes.md` §Ambiente).
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova (o teste cita o UC mas o
> manifesto não foi regravado) · ⬜ não verificado · ❌ quebrou.
>
> Todos abaixo estão em **🧪**: os testes existem e citam o id no título de `it()`, mas o autor
> deste PR **não rodou a suíte** (ambiente errado — a regra manda CT 100/CI). Declarar ✅ sem o
> manifesto seria `status:unverified` no G-7, e afirmar verde sem rodar é a classe LC-08.

---

> **`last_run` 08-05 → 08-06.** A tela ganhou a faixa do hub (`<ForjaHub active="roadmap-gantt" />`,
> [#5346](https://github.com/wagnerra23/oimpresso.com/pull/5346)). Reli os 10 UCs abaixo antes de carimbar: todos tratam de auth,
> permissão, filtros, cross-tenant e reagendamento — **nenhum muda com a navegação**. O carimbo é
> "trio reconciliado com a tela nesta data", não "rodei a suíte".
>
> **[BACKLOG]** Nenhum UC cobre *"a tela abre dentro do hub, com a aba certa ativa"* — e foi exatamente
> esse o defeito que [W] achou em produção (tela abrindo solta, sem faixa). Fica em prosa, sem id, até
> ganhar teste que o cite: a lição do dia é que navegação tem **cinco** superfícies na Forja
> (`FORJA_TABS` · `core_topnavs` · ghosts de dois hubs · `Resources/menus/topnav.php`) e só uma
> renderiza aqui. Um UC que afirme sem provar seria pior que a ausência.

## UC-RGT-01 · Quem não está logado nunca vê o roadmap do time
- **Persona:** visitante anônimo — o roadmap expõe o que o time está fazendo; não é público.
- **Aceite:** Dado nenhum usuário autenticado · Quando `GET /forja/roadmap-gantt` · Então a resposta é redirect pro login (302) ou 401 — nunca 200 com payload.
- **Teste:** `Modules/Forja/Tests/Feature/Roadmap/RoadmapGanttControllerTest.php` — `UC-RGT-01 · redireciona pra login se usuário não estiver autenticado`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-02 · Sem a permissão de leitura de tasks, a tela é 403 (não é tela vazia)
- **Persona:** usuário logado do ERP que não faz parte do time interno.
- **Aceite:** Dado um usuário autenticado **sem** `jana.mcp.tasks.read` · Quando abre a tela · Então recebe **403**. Não pode degradar pra "200 com lista vazia": `mcp_tasks` não tem `business_id`, então a permission **é** o isolamento (ADR 0093 §exceções).
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-02 · responde 403 pra usuário sem permission jana.mcp.tasks.read`.
- **Regressão que defende:** o porte Jana→Forja poderia ter renomeado a permission "pra ficar coerente com o módulo" — permission Spatie vive por id de linha e o rename revogaria acesso em silêncio (ADR 0087). Este UC trava o par de permissions ANTIGO.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-03 · Com a permissão, a tela abre com os dropdowns já preenchidos
- **Persona:** [W] / time interno abrindo o roadmap pela primeira vez no dia.
- **Aceite:** Dado um usuário com `jana.mcp.tasks.read` · Quando abre a tela · Então o Inertia responde 200 com o componente **`Forja/Roadmap/Gantt`** e as props `cycles`, `tasks`, `filters`, `owners`, `modules`, `active_cycle_id` — e **`owners`/`modules` chegam RESOLVIDOS como array já no load cheio**, não como promessa deferida.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-03 · responde 200 e renderiza Inertia component Forja/Roadmap/Gantt com permission`.
- **Regressão que defende:** o HOTFIX de 2026-05-25 (Wagner). Com `Inertia::defer` nos dropdowns, o `.tsx` — que desestrutura direto e chama `.map()` — estourava `TypeError: undefined.map` em **produção**. A closure é o desenho consciente; este UC impede alguém "otimizar" de volta pro defer sem antes dar default-guard no `.tsx`. Ver RUNBOOK §3.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-04 · Filtrar por cycle mostra só o que pertence àquele cycle
- **Persona:** [W] — "o que está no cycle atual" é a leitura default; trocar de cycle é a leitura de retrospectiva.
- **Aceite:** Dado um cycle com tarefas · Quando `?cycle=<id>` · Então as tarefas retornadas são as daquele `cycle_id` e `filters.cycle` ecoa o id escolhido (o filtro fica na URL, nunca no backend).
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-04 · aceita filtro por cycle_id via query param`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-05 · Filtrar por módulo tira os outros módulos da timeline
- **Persona:** [F]/[M] olhando só o que é do módulo em que estão mexendo.
- **Aceite:** Dadas tarefas de dois módulos distintos · Quando `?module=X` · Então a do módulo X aparece e a do outro **não**, e `filters.module` ecoa X.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-05 · filtra tasks por module via query param`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-06 · Usuário de outro negócio não alcança o roadmap do time
- **Persona:** operador de qualquer outro `business_id` da plataforma.
- **Aceite:** Dado um usuário de OUTRO business sem `jana.mcp.tasks.read` · Quando abre a tela · Então **403**. `mcp_tasks` é cache canon cross-business (a fonte é o git, via SPEC.md) e não tem coluna `business_id` — o isolamento aqui é a permission, e é isso que o caso trava.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-06 · respeita global scope multi-tenant (...)`.
- **Regressão que defende:** vazamento cross-tenant (ADR 0093, Tier 0 IRREVOGÁVEL). ⚠️ Se um dia a tela virar per-business, este UC muda de natureza: passa a assertar **dado**, não status HTTP.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-07 · Filtro sem resultado mostra "sem tarefas", não uma tela quebrada
- **Persona:** qualquer um que combine filtros que não casam com nada.
- **Aceite:** Dado um filtro impossível (ex. `?owner=__inexistente__`) · Quando abre a tela · Então responde 200 com `tasks: []` e a UI mostra a mensagem de vazio — nunca skeleton infinito nem erro de render.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-07 · renderiza com lista de tasks vazia sem quebrar (...)`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-08 · Quem só lê não consegue reagendar, nem pela URL
- **Persona:** membro do time com leitura, sem escrita.
- **Aceite:** Dado um usuário **sem** `jana.mcp.tasks.write` · Quando dispara `PATCH /forja/roadmap-gantt/tasks/{taskId}/schedule` direto (sem passar pela UI) · Então **403**. Esconder o drag no cliente (`readonly`) não é a defesa — é conveniência; a defesa é o gate no servidor.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-08 · responde 403 no reschedule sem permission jana.mcp.tasks.write`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-09 · Reagendar sem informar data não grava nada
- **Persona:** cliente HTTP malformado (ou bug de front que perde o payload).
- **Aceite:** Dado um usuário com escrita · Quando o PATCH chega **sem** `due_date` · Então volta com erro de validação em `due_date` e **nenhuma** escrita acontece.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-09 · valida due_date obrigatório no reschedule (422 sem data)`.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## UC-RGT-10 · Arrastar a barra reagenda o PRAZO, e só o prazo
- **Persona:** [W] replanejando a semana direto na timeline (US-COPI-111 B2, Wagner-explícito 2026-07-12).
- **Aceite:** Dada uma tarefa com prazo hoje e um usuário com `jana.mcp.tasks.write` · Quando o PATCH manda um novo `due_date` · Então o `due_date` persistido é exatamente o novo prazo, a escrita passa pelo `TaskCrudService::update` (mesma via do tool MCP `tasks-update`: atômico + audit) e **`started_at` não é tocado** — ele é lifecycle-managed pelo status da task (ADR 0070), não arrastável.
- **Teste:** `RoadmapGanttControllerTest.php` — `UC-RGT-10 · reagenda o due_date da task via TaskCrudService (biz=1)`.
- **Regressão que defende:** um "reschedule" que escrevesse `started_at` (ou qualquer campo fora da allowlist) reescreveria o histórico de execução do time. A allowlist do Service é a trava; o UC é a prova de que o caminho usado é o dele.
- **Status: 🧪** — teste escrito e cita o UC; manifesto pendente.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Click numa barra abre o drawer com o detalhe da tarefa** — exige spec Playwright (harness e2e): o `select-task` do SVAR e o drawer só existem no browser, um teste de controller não alcança. ⚠️ Vale prioridade: os tipos da lib declaram o payload do evento como `{ id }`, e o handler herdado do Jana lia `{ action, data }` — o porte passou a aceitar as **duas** formas, mas só o e2e prova qual delas o SVAR realmente emite.
- **[BACKLOG] O teto de 500 tarefas por render é respeitado** — precisa de fixture com >500 linhas; caro no DB real, e o valor de negócio é o de UX (legibilidade em 1280px), não o do número.
- **[BACKLOG] As setas de dependência refletem `blocked_by[]`** — o mapeamento acontece no cliente (`toGanttLinks`); um render test do `.tsx` cobriria melhor que o controller.
- **[BACKLOG] O drag NÃO aparece pra quem só tem leitura** — `can_edit=false` já é payload testável, mas a asserção que importa (o `api.on('update-task')` nem ser registrado) é de cliente.
