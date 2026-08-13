---
page_id: forja-trabalho
page: /forja/trabalho
component: Modules/Forja/Resources/js/Pages/Forja/Trabalho/Index.tsx
related_prototype: prototipo-ui/cowork/forja-page.jsx
owner: wagner
status: draft
last_validated: "2026-08-10"
parent_module: Forja
related_us: [US-FORJA-006]
related_adrs: [70, 93, 253]
tier: B
charter_version: 4
---

# Page Charter — /forja/trabalho

> **Status:** `draft`. Promover pra `live` é ato de [W].
>
> **Origem:** `US-FORJA-006` — *"as abas do cockpit sobrepõem Triage/Backlog/Board/Activity;
> foram movidas, não fundidas, e fundir = deletar uma implementação"*.

---

## Mission

Responder **"o que tem pra fazer?"** em **um** lugar. Hoje a mesma pergunta tem três respostas
diferentes, e nenhuma é errada — são escopos distintos que ninguém declarou:

| tela | escopo | riqueza |
|---|---|---|
| `Pages/Forja/Backlog/Index.tsx` (416 ln) | `project=FORJA` | filtros · KPIs · epics/owners/sprints · **tem `casos.md`** |
| `_components/ForjaBacklog.tsx` (207 ln) | `project=FORJA` | lista chapada, sem filtro |
| `Pages/team-mcp/Tasks/Index.tsx` (647 ln) | **todas** as tasks | KPI-filtros · ActorSeal |

Números via `wc -l` em 2026-08-08 — re-conte em vez de confiar no retrato.

## A escolha, e por que

**As nativas vencem.** São as ricas e as únicas com contrato defendido por gate; o cockpit é a
versão enxuta. Este `TrabalhoService` é a lógica do `BacklogController` generalizada (sai o
`project_id` obrigatório), mais a projeção `forja_*` que só o cockpit tinha, mais o escopo
sem-recorte que só o `team-mcp/Tasks` tinha.

## Contrato de dados

| prop | origem | eager/defer |
|---|---|---|
| `titulo` · `subtitle` · `filtros` · `sorts` · `statuses` | controller (constantes e query string) | eager — a UI monta os controles no 1º paint |
| `tasks` | `TrabalhoService::build()` — teto 500, memoizado por filtros | **defer** |
| `kpis` | mesma query de `tasks` (por isso a memoização) | **defer** |
| `frentes` | `McpProject::pluck('key','id')` — evita N+1 no rótulo do grupo | **defer** |

## Sem chip de frente

Decisão [W] 2026-08-08: a lista abre com **todas** as `mcp_tasks`. O recorte por projeto se faz
**agrupando** (a tela agrupa por Frente) ou **buscando** — não por um chip que esconde o resto.
O filtro `frente` existe no service para quem chegar por URL, mas a UI não o oferece.

## Sub-visões — Lista e Quadro sobre a MESMA lista

`visao=lista|quadro` (e `eixo=execucao|pipeline` quando quadro) viajam nos **filtros**, não em
rotas distintas: é a mesma consulta, mesmo pool, mesmos filtros — só muda como se olha. Por isso
a chave do cache **ignora** os dois; alternar a vista não refaz a query.

**Por que o Quadro tem DOIS eixos.** O hub tinha dois boards que pareciam concorrentes e
respondiam perguntas diferentes:

| eixo | pergunta | quem entra |
|---|---|---|
| **Pipeline** F0→F4 | *"em que ponto do protocolo de tela isto está?"* | só quem **tem** `forja_fase` — o eixo **filtra** |
| **Execução** (status canon) | *"o que está andando?"* | toda task ativa; `done`/`cancelled` ficam fora |

O eixo Pipeline **filtra em vez de inventar uma coluna "sem fase"**: task de infra/gate/ADR não
tem fase, e isso é correto — forçá-la numa coluna mentiria sobre ela. O board mostra quantas
ficaram de fora e por quê, senão quem conta os cards e compara com o KPI conclui que sumiu task.

As fases do front **espelham** o `ForjaQuadroService` (backend). Espelho sem trava vira duas
declarações do pipeline que divergem na 1ª mudança — por isso o `UC-TRAB-07` cruza os dois lados,
como o `UC-FORJA-14` faz com as duas superfícies de navegação.

## A 3ª vista: Gantt — ATALHO, não fusão de payload

O pedido chama o Gantt de "3ª sub-visão", e do ponto de vista de **quem procura trabalho** ele é
exatamente isso. Mas ele **continua morando** em `/forja/roadmap-gantt`, e o botão diz isso (seta de
saída + `title`): a URL vai mudar, e esconder esse fato seria mentir sobre onde a pessoa está.

**Por que não portar as 681 linhas** — quatro colisões medidas em 2026-08-09, não estimadas:

| # | colisão |
|---|---|
| 1 | **Contratos de payload opostos.** Esta tela é defer-first; o Gantt é **eager consciente por HOTFIX DE PRODUÇÃO** — com `defer` os dropdowns chegavam `undefined` no 1º paint e o `.map()` estourava em prod ([PR #1550/#1552](https://github.com/wagnerra23/oimpresso.com/pull/1552)). Está escrito no controller com `⛔ DESENHO CONSCIENTE`. |
| 2 | **A prop `tasks` colide.** Os dois a serializam com shapes diferentes — o Gantt manda ~20 campos (`estimate_h`, `blocked_by`, `started_at`…) que esta lista não tem. |
| 3 | **O Gantt tem mutação própria** (`PATCH /roadmap-gantt/tasks/{id}/schedule`). Ela ficaria pendurada numa tela cujo controller não a conhece. |
| 4 | **Trio próprio** — `Gantt.charter.md` · `Gantt.casos.md` · `RUNBOOK-gantt.md`. Portar exigiria realocar o contrato inteiro. |

**O que o atalho carrega:** só os filtros que o **destino lê**
(`TrabalhoService::FILTROS_ATALHO_GANTT` = `cycle` · `owner` · `priority` · `module`). A lista vem do
**backend**, não espelhada no front — assim não há 2ª declaração pra divergir, que foi o custo que o
espelho das fases cobrou no `UC-TRAB-07`.

⚠️ **`status` fica de fora de propósito.** O Gantt *serializa* `status` na saída, mas **não** o aceita
como filtro de entrada. Mandá-lo seria parâmetro ignorado em silêncio — a pessoa veria "não filtrou"
sem saber por quê. `UC-TRAB-10` trava os dois lados disso.

---

## O card do Quadro usa os selos CANÔNICOS (não hand-roll)

`<PriorityDot>` e `<ActorSeal>` vêm de `@/Components/shared/TaskBadges` — promovidos de
`Pages/team-mcp/Tasks/_components/` nesta onda, porque a rule `components.md` diz que composto
consumido por **≥2 módulos** mora em `shared/`.

**Por que isso está no charter e não só no código:** a 1ª versão deste quadro mostrava `owner` em
texto cinza e prioridade em badge de texto — **tendo os dois componentes prontos a um import de
distância**, e citando o `ActorSeal` na tabela comparativa acima. Perder a distinção
**agente vs humano** apaga o conceito central da Forja (o subtítulo do hub diz *"atores humano vs
agente"*). Ver proibições §5 2026-08-10 (*"Construir tela derivando do CÓDIGO quando existe FONTE DE DESIGN"*).

A lista de agentes vem do backend (`TrabalhoService::agentes()`) — **allowlist** de `ai_agent` não
revogado, em minúsculas. Nunca heurística de nome.

---

## Non-Goals

> Derivados do pedido do [W] (2026-08-08) e da `US-FORJA-006`. Não inferidos.

- ❌ **Deletar ou redirecionar** as três telas existentes **nesta onda**. Elas convivem; a remoção
  da perdedora é decisão [W], depois do smoke comparativo.
- ❌ Coluna/campo novo em `mcp_tasks` — `tam` P/M/G/GG duplicaria `estimate_unit='tshirt'` +
  `estimate_value`, que já existem.
- ❌ Rank híbrido com **pin persistido**. O pedido o descreve, mas ele depende de user-pref
  gravada; esta onda entrega a ordem que já existia (`rank`) + o eixo de execução.
- ❌ Workflow configurável — F0→F4 é constituição.
- ❌ Permission nova — reusa `jana.mcp.usage.all`, como o resto do hub.
- ❌ **Portar o payload do Gantt** para esta tela. As 4 colisões acima são medidas; a mais dura é o
  hotfix de produção que proíbe `defer` lá. Fundir de verdade é reescrever a tela, não movê-la.
- ❌ **Arrastar card no Quadro** nesta onda. Mover card é **mutação**, e mutação fora do caminho
  governado (`TaskCrudService`, que valida o FSM) seria um segundo caminho de escrita — o erro
  que a Mesa de Aprovações evitou de propósito.

## Automation Anti-hooks

- ❌ **Não** exigir `project_id` para listar. O default é TODAS as tasks; devolver `[]` sem
  projeto é o comportamento do `ForjaBacklogService`, e é o oposto do que [W] pediu.
- ❌ **Não** aceitar `sort` livre da query string. Valor fora da allowlist vira `FIELD(...)` sem
  correspondência e a ordem sai aleatória **sem erro visível** — falha silenciosa.
- ❌ **Não** tratar `forja_fase` ausente como dado faltando. Fase só existe para trabalho do
  pipeline de tela; task de infra/gate/ADR não tem, e isso é correto.
- ❌ **Não** mandar no atalho filtro que o destino não lê. Parâmetro ignorado em silêncio é pior que
  erro — não avisa ninguém. A lista de compatíveis é do backend; se crescer, o destino tem que ler.
- ❌ **Não** dar `aria-pressed` ao botão Gantt. Ele não é estado desta tela, é navegação; marcá-lo
  como toggle diria ao leitor de tela que a pessoa continua onde estava.
- ❌ **Não** hand-rolar selo de prioridade/ator no card. Existe canon em `shared/TaskBadges`; se
  faltar variante, estenda o canon — não faça um ao lado.
- ❌ **Não** decidir "é agente?" por padrão de nome (`claude*`, `*-bot`). A fonte é a tabela
  `mcp_actors` (`type=ai_agent`, não revogado) — heurística de nome erra e o selo mente.
- ❌ **Não** criar coluna "sem fase" no eixo Pipeline. Ausência de fase é informação, não buraco.
- ❌ **Não** pôr `visao`/`eixo` na chave do cache — eles não afetam a consulta; incluí-los faz
  cada toggle refazer a query inteira por nada.
- ❌ **Não** duplicar a query entre `tasks` e `kpis` — eles compartilham resultado por
  memoização; separar dobra a consulta em toda render.

---

## Estado / pendências

- Todos os UC nascem 🧪 — o ✅ vem do manifesto derivado do JUnit, nunca escrito à mão.
- **Não verificado localmente:** `route:list`, `tsc` e Pest (worktree sem `vendor/`/`node_modules`;
  Pest local é proibido — [ADR 0062](../../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)).
- **Sem smoke visual ainda** — obrigatório antes de declarar pronto (R1).
- **A decisão da US-FORJA-006 continua aberta.** Esta tela é a candidata; qual das outras sai é [W].
