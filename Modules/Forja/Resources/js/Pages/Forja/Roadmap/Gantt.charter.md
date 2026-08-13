---
page_id: forja-roadmap-gantt
page: /forja/roadmap-gantt
component: resources/js/Pages/Forja/Roadmap/Gantt.tsx
owner: wagner
status: draft
last_validated: "2026-08-05"
parent_module: Forja
related_us: [US-COPI-111]
related_runbook: memory/requisitos/Forja/RUNBOOK-gantt.md
related_adrs: [70, 87, 93, 94, 110, 253, 366, 367]
tier: B
charter_version: 1
---

# Page Charter — /forja/roadmap-gantt

> **Status:** `draft`. Este charter é o **porte** do antigo
> `resources/js/Pages/Jana/Admin/Roadmap.charter.md` (também `draft`), que **deixou
> de existir no mesmo PR** — por isso o nome aparece aqui como texto e não como
> link: ref pra arquivo deletado quebra o `charter-refs-gate`, e link que não
> resolve é pior que citação honesta. Quem quiser o conteúdo original acha no
> histórico do git. Movido pela [ADR 0366 §D-B](../../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md)
> e [ADR 0367 D4](../../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md).
> **Os Non-Goals e os Automation Anti-hooks abaixo são cópia literal do charter de origem** — nenhum
> item foi inferido, adicionado ou reinterpretado neste porte (`charter-write` é proibida de inferir;
> só [W] preenche). Promover pra `status: live` segue sendo ato de [W].

---

## Mission

Visualizar cronologicamente os **cycles** + **tasks (`mcp_tasks`)** do time como Gantt interativo,
agrupado por módulo, com filtros por cycle/owner/priority/module e setas de dependência via
`blocked_by[]`. Responde *"o que vence essa semana, o que tá bloqueando o quê"* — a pergunta da
**Forja** (time interno) no critério da ADR 0366 §D-A.

**Não substitui** `/project-mgmt/roadmap` (`Forja/Roadmap/Index`): aquela agrupa **epics por
trimestre**, esta agrupa **tasks no tempo**. Convivência decidida na [ADR 0367 D7](../../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md)
— *"o quarter view sobrevive como segunda leitura do roadmap e só sai quando o Gantt provar que
substitui"*. Recibo da não-duplicação:
[`memory/sessions/2026-08-05-duplicacao-roadmap-forja.md`](../../../../../memory/sessions/2026-08-05-duplicacao-roadmap-forja.md).

---

## Goals — Features (faz)

- AppShellV2 + `PageHeader` canon v3 (ADR 0189/0190); topnav do hub Forja vem do 1º segmento `/forja`
- Gantt SVAR React MIT v2.6.x renderiza tasks como barras + summary tasks por módulo
- Filtros (Select): Cycle ativo (default) / Owner / Priority P0-P3 / Módulo
- Botão "Limpar filtros" aparece só quando algum filtro está aplicado
- Click numa tarefa abre Sheet lateral (`@/Components/ui/sheet`) com:
  - identifier + title + módulo + status + priority badges
  - description completa
  - estimativa (`story_points` OU `estimate_h`)
  - `due_date` / `completed_at`
  - lista de `blocked_by[]`
  - snippet `tasks-detail task_id:US-XXX-NNN` pra abrir no Claude Code/Cursor
- Scales semana + dia (default 1280px friendly)
- Summary task per-module cobrindo mínimo→máximo das tasks do grupo
- Progress visual: `done`=100%, `doing`/`review`=50%, demais=0%
- Setas de dependência `e2s` (end-to-start) a partir de `blocked_by[]`
- **Drag-drop reschedule do PRAZO (`due_date`)** — arrastar/redimensionar a barra reagenda o prazo via
  `PATCH /forja/roadmap-gantt/tasks/{taskId}/schedule` (US-COPI-111 **B2**, Wagner-explícito
  2026-07-12). Só habilita com `jana.mcp.tasks.write`. Persiste pelo `TaskCrudService::update`
  canônico (atômico + audit). **Só o prazo:** `started_at` é lifecycle-managed, NÃO arrastável
- Multi-tenant: `mcp_tasks` é cache canon cross-business (ADR 0093 §exceções repo-wide). Permissions
  `jana.mcp.tasks.read` (leitura) / `jana.mcp.tasks.write` (reschedule) controlam acesso
- Limite 500 tasks por render — se exceder, filtros refinam

---

## Non-Goals — Features (NÃO faz)

> Cópia literal do charter de origem (Jana/Admin/Roadmap). Cada item vira Pest GUARD.

- ⚠️ ~~**Editar task no Gantt** (drag-drop reschedule) — leitura pura V1~~ **SUPERSEDED por Wagner
  2026-07-12 (US-COPI-111 B2):** o reschedule do **prazo** (`due_date`) via drag agora É Goal (ver
  acima). Continua Non-Goal: **rename inline** de task no Gantt, e edição de qualquer campo que não
  seja `due_date` (fica em `tasks-update` MCP).
- ❌ **Criar task** no Gantt — usar `tasks-create` MCP
- ❌ **Hierarchy nested >1 nível** (sub-issues 8 níveis estilo GitHub Projects) — só summary-by-module;
  tree view espera `mcp_tasks.parent_task_id` populado
- ❌ **Cycle múltiplo simultâneo** (overlay de 2 cycles lado-a-lado) — 1 cycle por vez
- ❌ **Export PDF/PNG do Gantt** — backlog Wagner
- ❌ **Compartilhar timeline via link público** — risco governança, sem caso de uso interno
- ❌ **Editar dependency arrow** (drag pra criar/remover `blocked_by`) — mutação fica em tools MCP
- ❌ **Notificação real-time** via Centrifugo de tasks updated — snapshot no render; usuário recarrega
- ❌ **Mostrar custo $ por task** — vai pra tela de custos
- ❌ **Time tracking ao vivo** (cronômetro Pomodoro) — fora do escopo MCP-task
- ❌ **Auto-suggestion de reordenação** baseada em prazos — IA roadmap re-plan é US separada

**Non-Goal adicional deste porte** (decorre da ADR 0367 D7, não é inferência):

- ❌ **Substituir ou esconder `/project-mgmt/roadmap`** (quarter view por epic). As duas telas convivem
  até o Gantt provar que substitui — decisão [W] registrada na 0367 D7.

---

## UX Targets

- p95 first-paint < 1500ms com 200 tasks
- Filtro aplicado: Inertia partial reload < 600ms (preserve-state)
- Click numa task → drawer < 100ms (o cliente já tem o objeto)
- Cabe em monitor 1280px sem scroll horizontal no chrome (scroll interno do Gantt é aceito)
- 0 erros JS no console
- Tipografia canon ADR 0110 · cores **só por token semântico** (nunca palette cru)
- Dark mode respeita tokens canônicos

---

## UX Anti-patterns

- ❌ Modal full-screen pra detalhe de task (canon = Sheet lateral)
- ❌ Cor crua `bg-(red|green|blue)-500` em badges (canon = `Badge variant="danger|warning|info|neutral"`)
- ❌ Loading skeleton infinito (canon = "Sem tasks no filtro atual" se vazio)
- ❌ Gantt com cells de 1h (canon = day step mínimo)
- ❌ Animação de barra durante render inicial (canon = render estático)
- ❌ Drawer com mais de 2 tabs internas (canon = info linear + snippet MCP)
- ❌ Auto-scroll horizontal ao abrir (canon = aterrissa na semana atual)

---

## Automation Hooks

- `RoadmapGanttController::index()` carrega cycles (20 recentes) + tasks filtradas + distinct de
  owners/modules pros dropdowns
- Inertia partial reload preserva state ao trocar filtro (URL com query params canônicos)
- `select-task` da API do SVAR Gantt → atualiza state local, abre Sheet
- `update-task` da API do SVAR Gantt (só com `can_edit`) → `PATCH .../schedule` → `TaskCrudService`
- `useMemo` em `toGanttTasks()` + `toGanttLinks()` evita re-cálculo
- Permission gate `can:jana.mcp.tasks.read` (index) / `can:jana.mcp.tasks.write` (schedule) no construtor
- Audit: o reschedule audita via `TaskCrudService` (OTel + `McpTaskEvent`) — o render não audita

---

## Automation Anti-hooks

> Cópia literal do charter de origem. O que essa tela NUNCA dispara. Vira Pest GUARD.

- ❌ Não dispara emails ao abrir
- ❌ Não escreve no banco no render (`SELECT`-only — a única escrita é o PATCH explícito de reschedule)
- ❌ Não modifica campo de `mcp_tasks` que não seja `due_date` (canon = git via SPEC.md; UI só read + prazo)
- ❌ Não dispara jobs ao filtrar (Inertia partial, sem fila)
- ❌ Não acessa data de outro `business_id` (cache canon cross-business; isolamento é por permission)
- ❌ Não persiste filtro em backend (state vive na URL via query params)
- ❌ Não consulta MCP server externo no render (toda data vem do banco local)
- ❌ Não loga `task_id` em audit_log no click (drawer é cliente puro)

---

## Decisões técnicas-chave

- **Lib:** `@svar-ui/react-gantt` v2.6.x MIT + `@svar-ui/react-gantt/style.css`
- **Rota:** `/forja/roadmap-gantt` — 1 segmento próprio. `useAutoModuleNav` casa o topnav pelo **1º
  segmento**, então herda o topnav do hub Forja de graça. Sufixo `-gantt` desambigua de
  `/project-mgmt/roadmap` enquanto o quarter view viver. Sem colisão: o grupo `/forja` só tem wildcard
  de **2 segmentos** (`/{taskId}/dossier`)
- **Arquivo `Gantt.tsx`, não `Index.tsx`:** `Forja/Roadmap/Index` já é o quarter view — sobrescrever
  seria a fusão que a 0367 D7 proibiu
- **Permissions:** `jana.mcp.tasks.read` / `.write` **inalteradas**. Permission Spatie vive por id de
  linha; renomear revoga acesso em silêncio ([ADR 0087](../../../../../memory/decisions/0087-drift-resolution-sem-mover-url.md))
- **`TaskCrudService` fica no Jana** — a Forja só importa. O item #4 da ADR 0366 §D-C (mover as 30
  `Mcp*`) **não está autorizado** por aquela ADR
- **`owners`/`modules` por CLOSURE, não `Inertia::defer`** — desenho consciente (HOTFIX Wagner
  2026-05-25: o `.tsx` desestrutura direto → `TypeError undefined.map` em prod com defer). Ver
  RUNBOOK §3
- **Layout por primitivos** (`Stack`/`Inline`/`Grid` de `@/Components/layout`, ADR 0253) — arquivo novo
  nasce com base 0 no `layout:check`
- **`SafeSelectItem`** nos dropdowns data-driven — `distinct` do banco pode devolver vazio, e
  `<SelectItem value="">` derruba o render inteiro do Radix (`proibicoes.md` §5 2026-06-29)

---

## Refs

- [ADR 0366 — fronteira Jana/Forja/Governance/KB](../../../../../memory/decisions/0366-fronteira-jana-forja-governance-kb.md) §D-B
- [ADR 0367 — cockpit único da Forja](../../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md) D4/D7
- [ADR 0070 — Jira-style tasks](../../../../../memory/decisions/0070-jira-style-task-management-current-md-removed.md)
- [ADR 0087 — drift sem mover URL / permissions por id](../../../../../memory/decisions/0087-drift-resolution-sem-mover-url.md)
- [ADR 0093 — multi-tenant Tier 0](../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0253 — primitivos de layout](../../../../../memory/decisions/0253-primitivos-layout.md)
- [RUNBOOK-gantt](../../../../../memory/requisitos/Forja/RUNBOOK-gantt.md)
- Charter de origem: `resources/js/Pages/Jana/Admin/Roadmap.charter.md` — **deletado neste PR** (a tela mudou de dono). Sem link de propósito: o alvo não existe mais; o conteúdo vive no histórico do git.

---

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-08-05 | [C] | Charter criado no porte Jana→Forja (Wave C). Non-Goals + Anti-hooks copiados **literalmente** do charter de origem. Segue `draft` — promover é ato de [W]. |
