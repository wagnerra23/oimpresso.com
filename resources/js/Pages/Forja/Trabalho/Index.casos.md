---
id: resources-js-pages-forja-trabalho-index-casos
casos: Forja · lista única de trabalho · /forja/trabalho
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-08-09"
---

# Casos de uso — /forja/trabalho

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Os UC derivam do **contrato** — [`Index.charter.md`](Index.charter.md) (lei) + `US-FORJA-006` no [SPEC](../../../../memory/requisitos/Forja/SPEC.md) — **nunca** do `.tsx`. Persona: o time interno procurando o que fazer. Escopo repo-wide: `mcp_tasks` é governança da plataforma ([ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)).

> ⚠️ **Todos nascem 🧪.** `TrabalhoListaTest.php` entrou na allowlist do [`forja-pest.yml`](../../../../.github/workflows/forja-pest.yml) failing-first — rodar Pest local é proibido ([ADR 0062](../../../../memory/decisions/0062-separacao-runtime-hostinger-ct100.md)), então o primeiro verde só acontece no CI. O `✅` vem do manifesto derivado do JUnit, nunca escrito à mão.

> **Rota/permissão não têm UC próprio aqui, de propósito.** `UC-FORJA-01` e `UC-FORJA-07` (em [`Cockpit.casos.md`](../../team-mcp/Forja/Cockpit.casos.md)) já cobrem o padrão de acesso do hub inteiro; repetir seria régua paralela a régua consolidada.

## UC-TRAB-01 — A lista abre com TODAS as tasks, não só as de um projeto
Status: 🧪 (1 teste cita este UC — cria uma task **sem** `project_id` e outra **com**, e exige que as **duas** apareçam.)
Decisão [W] 2026-08-08: sem chip de frente; o recorte se faz agrupando ou buscando. É o **inverso** do `ForjaBacklogService`, que devolvia `[]` sem `project_id` — se essa regra voltar, a fusão perde o sentido e a tela vira o quarto backlog com o mesmo recorte dos outros dois.
**Pronto quando:** task sem projeto e task com projeto aparecem na mesma lista, sem filtro aplicado.

## UC-TRAB-02 — O filtro por frente existe e restringe (só não é oferecido na UI)
Status: 🧪 (1 teste cita este UC — com **controle**: exige que a de dentro apareça **e** a de fora não.)
O parâmetro `frente` funciona para quem chega por URL; a tela não desenha o chip. Filtrar é possível, esconder por default não é.
**Pronto quando:** `frente=N` devolve só as daquele projeto.

## UC-TRAB-03 — `sort` fora da allowlist não é aceito
Status: 🧪 (1 teste cita este UC — trava o conjunto de válidos e o default.)
`sort` livre viraria um `FIELD(...)` sem correspondência: a ordem sairia **aleatória, sem erro nenhum**. Falha silenciosa é pior que 500, por isso o controller valida contra `TrabalhoService::SORTS` e cai em `rank`.
**Pronto quando:** o conjunto de ordenações é fechado e o default é `rank`.

## UC-TRAB-04 — `tasks` e `kpis` saem da mesma query
Status: 🧪 (1 teste cita este UC — duas chamadas seguidas devolvem a **mesma instância**, provando o cache; e os KPIs conferem contra as fixtures.)
As duas props são deferidas e pedidas na mesma render. Sem memoização, a consulta roda duas vezes — em silêncio, porque o resultado seria idêntico.
**Pronto quando:** `build()` chamado 2× devolve a mesma Collection, e os KPIs contam o que a lista tem.

## UC-TRAB-05 — `cancelled` some por default e volta com `status=all`
Status: 🧪 (1 teste cita este UC, nas duas direções.)
Herdado da nativa: cancelada é ruído na lista de trabalho, mas não pode sumir do sistema — quem procura acha.
**Pronto quando:** sem filtro a cancelada não aparece; com `status=all` aparece.

## UC-TRAB-06 — Cada task carrega os campos das TRÊS origens fundidas
Status: 🧪 (1 teste cita este UC — confere um campo de cada origem no mesmo item.)
A fusão só é fusão se o payload for a união: os campos da nativa (`display_id`/`priority`/`is_overdue`…), a projeção `forja_*` do cockpit, e `frente_id` do team-mcp (que só faz sentido quando a lista mistura projetos).
**Pronto quando:** o mesmo item traz `display_id` (nativa), `forja_fase` (cockpit) e `frente_id` (team-mcp).

## UC-TRAB-07 — As fases do Quadro batem com o dono do pipeline (backend)
Status: 🧪 (1 teste cita este UC — cruza `ForjaQuadroService.php` × `TrabalhoQuadro.tsx`, com guarda anti-falso-verde: dois vazios seriam "iguais".)
O front **espelha** as fases porque desenhar colunas não vale um roundtrip. Espelho sem trava vira duas declarações do pipeline que divergem na 1ª mudança — e o board passa a desenhar coluna que o dado não preenche. Mesma forma do `UC-FORJA-14`, que trava as duas superfícies de navegação.
**Pronto quando:** as chaves de fase dos dois arquivos são idênticas, na mesma ordem.

## UC-TRAB-08 — `visao` e `eixo` têm default e allowlist
Status: 🧪 (1 teste cita este UC — trava os defaults.)
Mesma razão do `sort`: valor livre viraria estado desconhecido no front, que renderiza **vazio sem erro**. A tela abre em Lista/Execução.
**Pronto quando:** `visao=lista` e `eixo=execucao` são os defaults, e valor fora da lista cai neles.

## UC-TRAB-09 — Trocar de visão NÃO refaz a query
Status: 🧪 (1 teste cita este UC — mesma instância de Collection nas duas visões.)
Lista e Quadro são a **mesma** consulta olhada de dois jeitos. Se `visao`/`eixo` entrarem na chave do cache, cada toggle paga uma query inteira — em silêncio, porque o resultado é idêntico.
**Pronto quando:** `build()` com `visao=lista` e com `visao=quadro` devolve a mesma Collection.

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

- [BACKLOG] Agrupamento visual por Frente na tela (o service devolve `frente_id` + o mapa; quem agrupa é o `.tsx`, e isso é comportamento de UI sem E2E ainda).
- [BACKLOG] Eixo de ordenação `execucao` (o que está andando primeiro) — existe no service, sem caso que o exercite.
- [BACKLOG] Gantt como 3ª sub-visão — onda 7.
- [BACKLOG] Arrastar card no Quadro pra mudar status — exige endpoint de mutação pelo `TaskCrudService` (FSM validado); sem ele seria um 2º caminho de escrita.
- [BACKLOG] Rank híbrido com pin persistido — depende de user-pref gravada; fora desta onda por decisão de escopo.
