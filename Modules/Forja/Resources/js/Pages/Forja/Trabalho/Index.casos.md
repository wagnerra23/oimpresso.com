---
id: resources-js-pages-forja-trabalho-index-casos
casos: Forja · lista única de trabalho · /forja/trabalho
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-02"
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

## UC-TRAB-10 — Os filtros do atalho Gantt são de fato LIDOS pelo destino
Status: 🧪 (1 teste cita este UC — cruza `TrabalhoService::FILTROS_ATALHO_GANTT` × `RoadmapGanttController`, com guarda de lista-vazia **e** controle negativo em `status`.)
O botão "Gantt" leva os filtros pra `/forja/roadmap-gantt`. Se o destino parar de ler um deles, o link **continua funcionando** e o parâmetro é ignorado **em silêncio** — a pessoa vê a lista "não filtrar" e não tem como saber por quê. Não dá erro, não dá 500: só mente.
⚠️ `status` fica fora de propósito — o Gantt o **serializa na saída** mas não o **aceita como filtro**. Confundir os dois é exatamente o que esta trava impede, por isso ela tem assert negativo.
**Pronto quando:** todo filtro da constante aparece como `$request->get('<f>')` no controller do Gantt, e `status` **não** está na constante.

## UC-TRAB-11 — `agentes()` lista SÓ ator `ai_agent` ativo, em lowercase
Status: 🧪 (1 teste cita este UC — cobre os **três** erros possíveis, com fixture pra cada.)
Esta lista alimenta o `<ActorSeal>`, que decide **agente vs humano** no card. É **allowlist, não heurística de nome**: quem não está nela é humano. Daí os três erros que o caso trava — deixar **humano** entrar (o selo chamaria pessoa de robô), deixar **revogado** entrar (ator desligado seguiria carimbando), e errar o **case** (o front compara `agents.includes(owner.toLowerCase())`; sem normalizar, `AgenteFixtura` nunca casa e o selo mente dizendo "humano").
**Pronto quando:** só `ai_agent` não-revogado aparece, e sempre em minúsculas.

## UC-TRAB-12 — O slug `claude` está no Mesh como AGENTE (o selo lê dado, não palpite)
Status: 🧪 (1 teste cita este UC.)
Medido em produção 2026-08-10: `mcp_tasks.owner` usa `claude`, mas o Identity Mesh só tinha `claude-code-wagner-laptop` (o ator-**com-token**). As 8 tasks do claude apareciam como **humano** — em `/forja/trabalho` **e** em `/team-mcp/tasks` (383 selos, **100% human**). O selo existia desde que nasceu e **nunca distinguiu nada**.
A migration `2026_08_10_120000` registra o fato (ator sem token, zero capability, `L4`). Este caso trava a volta: removido o ator, o selo mente **em silêncio** — `ActorSeal` cai em "humano" por default e não dá erro.
**Pronto quando:** `agentes()` contém `claude`. ⚠️ Nunca por `startsWith` — allowlist de dado, não heurística de nome.

---

## [BACKLOG] — declarado no charter, ainda sem teste que o defenda

- [BACKLOG] Agrupamento visual por Frente na tela (o service devolve `frente_id` + o mapa; quem agrupa é o `.tsx`, e isso é comportamento de UI sem E2E ainda).
- [BACKLOG] Eixo de ordenação `execucao` (o que está andando primeiro) — existe no service, sem caso que o exercite.
- [BACKLOG] Arrastar card no Quadro pra mudar status — exige endpoint de mutação pelo `TaskCrudService` (FSM validado); sem ele seria um 2º caminho de escrita.
- [BACKLOG] Rank híbrido com pin persistido — depende de user-pref gravada; fora desta onda por decisão de escopo.

---

## PARIDADE §11 Onda 4 — a lista é a réplica do protótipo

> Decisão [W] de 2026-09-02 (*"pode fazer igual ao protótipo"*), regida pela [ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md): o `forja-page.jsx` é o contrato de **layout**, e a conformidade do DS vira item em [`INCONSISTENCIAS-replica.md`](../../../../memory/requisitos/Forja/INCONSISTENCIAS-replica.md).
>
> Os quatro UC abaixo cobrem o que a réplica **acrescentou de risco**: espelho de vocabulário sem trava (13/14) e KPI que virou filtro (15/16). O que a réplica deixou de fora está no charter §"Diferenças declaradas" — e é ausência **declarada**, não caso pendente.

## UC-TRAB-13 — Os papéis da barra de filtro são os da fonte de design
Status: 🧪 (1 teste cita este UC — extrai `FORJA_ACTORS` de `prototipo-ui/cowork/forja-data.jsx` e compara com `TrabalhoService::PAPEIS`, com guarda anti-falso-verde.)
O papel diz **quem responde** por cada fase, e é o vocabulário do loop Cowork↔Code. Se o backend inventar uma sigla que o Cowork não conhece, a barra desenha um botão que nunca casa nada — filtro que devolve vazio sempre, sem erro. É a mesma doença que o `PipelineParidadeTest` já trava nas fases: espelho contra espelho fica verde enquanto os dois divergem da fonte.
**Pronto quando:** `TrabalhoService::PAPEIS` é exatamente a lista do protótipo, na mesma ordem, e o extrator prova que achou papel (não compara dois vazios).

## UC-TRAB-14 — Os agrupamentos da lista são os do protótipo, na mesma ordem
Status: 🧪 (1 teste cita este UC — extrai `FJ_GROUPS` de `forja-page.jsx` e compara com `TrabalhoService::GRUPOS` sob a tradução declarada.)
A ordem é a dos botões na tela: trocá-la muda o que a pessoa acha primeiro. A **única** diferença aceita é de vocabulário (`assignee`→`papel`, `prio`→`prioridade`), declarada no próprio teste, porque o resto do módulo já fala PT (`custom_fields.forja_papel`, coluna `priority`).
**Pronto quando:** os seis agrupamentos batem, na ordem, sob a tradução — e o extrator prova que achou agrupamento.

## UC-TRAB-15 — O KPI-filtro recorta a LISTA e não os KPIs
Status: 🧪 (1 teste cita este UC — três fixtures de saúdes diferentes; exige lista recortada **e** KPI intacto **e** pool idêntico com/sem filtro.)
O cartão diz o **tamanho do problema**; o clique mostra **quais são**. Se o número respondesse ao próprio recorte, clicar "P0" zeraria "Fazendo" e "Bloqueadas" — o painel mentiria exatamente quando alguém está investigando. Por isso `build()` devolve o pool e a régua, e `filtrar()` corta depois; `saude` fica fora da query **e** da chave de cache.
**Pronto quando:** sob `saude=p0` a lista tem só a P0 aberta, o KPI `fazendo` segue > 0, e os KPIs com e sem filtro são iguais.

## UC-TRAB-16 — `grupo`, `saude` e `papel` têm default e allowlist
Status: 🧪 (1 teste cita este UC — trava defaults, conjunto de válidos, e prova que valor inválido **não apaga** a lista.)
Mesma razão do `sort` (UC-TRAB-03): valor livre viraria estado desconhecido no front, que renderiza vazio **sem erro**. Em `saude`/`papel` seria pior — recorte silencioso que ninguém pediu e ninguém vê. E o `filtrar()` **ignora** valor fora da allowlist em vez de devolver lista vazia: filtro desconhecido não pode apagar a tela.
**Pronto quando:** o default de `grupo` é `frente`, `saude`/`papel` nascem nulos, e `saude=inventado` / `papel=ZZ` devolvem a lista inteira.

## PARIDADE §11 Onda 5 — o Quadro é a réplica do `KanbanView`

> Mesma lei da Onda 4 ([ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)): o `forja-page.jsx` (`KanbanView`, :467-503) é o contrato de layout do board, e o que a réplica deixou de fora está no charter §"Diferenças declaradas do Quadro".
>
> **Um caso só, e é o que a onda ACRESCENTOU DE RISCO.** O board ganhou o cabeçalho de três linhas do protótipo (`.fj-kcol-top` · `.fj-kcol-quem` · `.fj-kcol-sai`), e as duas últimas exigem `owner`/`faz`/`sai` por fase — campos que o backend **não serve** (`UC-PIPE-04`). O front passou a espelhá-los da fonte de design, como já fazia com o `FASE_HUE`. Espelho novo = trava nova; sem ela, as duas cópias divergem na primeira edição e ninguém percebe.
>
> O resto da onda é **aparência sobre comportamento que já tinha caso**: as colunas, o recorte e o filtro do eixo Pipeline seguem defendidos por `UC-TRAB-07` (fases × backend) e `UC-TRAB-08` (allowlist de `visao`/`eixo`). Repetir aqui seria régua paralela a régua consolidada.

## UC-PIPE-05 — O cabeçalho de fase do Quadro (dono · faz · sai) é o da fonte de design
Status: 🧪 (1 teste cita este UC em [`PipelineParidadeTest.php`](../../../../Tests/Feature/PipelineParidadeTest.php) — extrai os trios dos DOIS lados linha a linha, com guarda anti-falso-verde e mensagem que diz qual fase e qual campo divergiu.)
O `.fj-kcol-quem` diz **quem responde** pela fase e o `.fj-kcol-sai` diz **o que faz o card sair dela** — é o protocolo do loop Cowork↔Code escrito na própria coluna, e foi por isso que o protótipo o pôs ali. Como o payload não carrega esses campos, a tela os espelha da fonte; se o espelho drifar, a coluna passa a afirmar sobre o protocolo uma coisa que o design não diz — e afirma com a autoridade de estar na tela. É a mesma doença que o `PipelineParidadeTest` já trava nas fases, agora no cabeçalho delas.
**Pronto quando:** para cada fase do espelho, o trio `owner`/`faz`/`sai` é idêntico ao de `FORJA_PHASES` no protótipo; o extrator prova que achou papel dos dois lados (não compara dois vazios); e fase que exista só no front reprova com o nome dela na mensagem.

## Acessibilidade — o buraco que a comparação com o protótipo NÃO acha

> Este caso não nasceu de uma onda de réplica. Nasceu de uma **medição nos dois lados** (2026-09-03): `aria-live` = **0** na produção **e** `0` no protótipo; `.fj-row` é `<div>` nos **dois**. Não era dívida de réplica — era buraco comum, e por isso comparar uma cópia com a outra jamais o encontraria. A ADR 0388 rege **aparência**; papel ARIA é invisível, e a produção já estava à frente do protótipo aqui (o `aria-expanded` do `fj-group-toggle` e os `data-testid` não existem no `forja-page.jsx`). A divergência está declarada no charter §"Reconciliações".

## UC-TRAB-17 — A lista tem papéis ARIA, e a linha NÃO é interativa (a premissa do papel)
Status: 🧪 (1 teste cita este UC — casa a linha de cada nó pelo `className`, com guarda anti-falso-verde que estoura dizendo qual nó sumiu.)
São treze filhos inline por linha. Sem papel, o leitor de tela os lê em sequência e não há fronteira entre uma issue e a próxima — nem posição (*"3 de 17"*). E filtrar (KPI, papel, busca, ★) troca a lista inteira **sem mover o foco**: sem região viva, o clique é mudo pra quem não enxerga, e a pessoa não sabe se fez efeito.

A metade que **não** é presença é a premissa: `listitem` só está correto enquanto a linha for um item que **não navega**. No protótipo ela tem `onClick` (abre o issue-drawer, que nesta tela não existe); aqui não tem. Se ganhar, `listitem` passa a mentir e o papel tem que virar `row`/`button` **junto com o teclado que ele promete** — prometer navegação 2D que a tela não implementa é a mesma afordância falsa do checkbox de seleção em massa (LC-15), só que invisível pra quem enxerga. Sem essa perna o caso seria presence-gate puro (LC-11).

**Pronto quando:** `.fj-list` tem `role="list"`, `.fj-group` tem `role="group"` + `aria-label`, `.fj-row` tem `role="listitem"`, a contagem de issues da barra de totais tem `role="status"` — **e** a `.fj-row` continua sem `onClick`, reprovando com a instrução de rever o papel (não de apagar o caso) no dia em que ganhar.
## PARIDADE §11 — o painel "Papéis" (`forja-runbook`)

## UC-TRAB-18 — O painel de papéis DERIVA da fonte viva, e cala o que a fonte marcou superado
Status: 🧪 (1 teste cita este UC — `tests/js/forja-runbook.test.tsx`, 5 casos; dois deles MUTAM `PAPEIS` em tempo de teste e exigem que a tela acompanhe, com guarda anti-falso-verde se a fonte vier vazia.)
O botão "Papéis" estava na lista de ausências declaradas do `Index.tsx` — *"abre painéis (runbook e IA) que não existem"*. O painel é **onboarding**: quem chega novo lê ali quem faz o quê. Isso muda o risco de lugar — o perigo não é desenhar torto, é **ensinar errado**, e ensinar errado com cara de canon é o modo mais caro de errar (a próxima sessão obedece).

Daí as duas metades deste caso. **Derivar:** a lista de papéis, a contagem do título e o dono de cada fase saem de `trabalhoTokens.ts` — o dono por **inversão** do `desc` (`'F1 — protótipo visual'` ⇒ F1 é do `[CC]`), nunca de um mapa escrito aqui. Papel novo aparece sozinho; papel que sai leva o badge junto. O protótipo escreve `6 papéis` literal e o main tem 7 — número à mão apodrece no primeiro papel novo.

**Calar:** o `forja-runbook.jsx` declara que seu texto vem do `PROTOCOL.md §1–§3`, e o próprio PROTOCOL marca §1 e §3 como 🪦 **superado** (v2 tem 2 papéis, ADR 0282; os gates humanos viraram checks de CI). Copiar aquele texto entregaria uma tela que ensina o loop v1. Então a FORMA é do protótipo (UI-0029, classes `fj-rb-*` e estrutura de drawer) e o CONTEÚDO é do main — conteúdo normativo não é eixo do protótipo. O que não tem fonte válida fica **declarado ausente na própria tela**, não em branco: sem isso a próxima sessão lê o painel curto como bug e "completa" com o texto que este caso barra.

Terceira perna, a que impede affordância falsa (LC-15): o painel é **leitura pura** — zero query, zero escrita. Se um dia ganhar ação, ela precisa de rota antes do botão.

**Pronto quando:** a contagem de `<li>` de papéis bate com `Object.keys(PAPEIS).length` (e falha se a fonte esvaziar); acrescentar um papel à fonte faz surgir a linha **sem editar o componente**; realocar o `desc` do `[CC]` migra o badge de fase junto; o texto renderizado **não** contém `/design-override`, `/screenshot-override`, `/a11y-override` nem "Aprovação visual síncrona", **e** contém a declaração da ausência; o drawer tem `role="dialog"` + `aria-modal` + `aria-label` e fecha no `Esc`.
