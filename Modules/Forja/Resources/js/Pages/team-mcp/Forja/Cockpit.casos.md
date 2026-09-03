---
id: resources-js-pages-team-mcp-forja-cockpit-casos
casos: Forja · cockpit do cowork loop · /forja
irmaos: Cockpit.charter.md (lei) · Cockpit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-09-02"
---

# Casos de uso — /forja (cockpit Forja · shell)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Onda Forja: cockpit do cowork loop, projetando `mcp_tasks` project=FORJA + git/ADR/sessão + gates (sem dado fantasma; contrato/tokens/auditoria da aba MCP seguem MOCKADOS por design). Persona: Wagner [W] (superadmin). A Triagem só muta sob confirmação [W]. Referência: [forja-cockpit-visual-comparison.md](../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

> ⚠️ **Errata 2026-07-27 — "as 6 abas" era stale.** A redação anterior afirmava 6 abas próprias incluindo `/forja/saude`. Medido: **5** rotas GET sob `/forja` e **9** itens de topnav (o hub foi fundido com TeamMcp em 2026-06-16, e "Saúde" aponta pro Scorecard). Recibos abaixo em cada UC. A rota `/forja/saude` **nunca existiu** nesta versão — `ForjaRoutesSmokeTest` a listava e por isso falhava; removida em [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887).

## UC-FORJA-01 — As rotas /forja respondem (shell no ar)
Status: 🧪 (o Pest foi consertado e passou a rodar em lane — [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887). Segue 🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI pelo `casos-results-publish` — não se escreve à mão.)
`ForjaController` serve **8** rotas GET de aba: `/forja` (Triagem), `/forja/backlog`, `/forja/quadro`, `/forja/changelog`, `/forja/mcp`, `/forja/handoffs` (desde 2026-08-08), `/forja/integrador` (Onda 2) e `/forja/saude` (Onda 7).
Recibo do número (2026-09-02): o dataset `forjaRotasAbas()` do `ForjaRoutesSmokeTest` — a fonte que o teste de fato percorre — tem 8 entradas; `sed -n '/^function forjaRotasAbas/,/^}/p' … | grep -cE "=> \['forja\."` = **8**. A contagem não se escreve à mão: re-rode o comando.
**Atualização 2026-09-02 (Onda 7):** `/forja/saude` **passou a existir**. A errata de 2026-07-27 acima segue verdadeira **na data dela** — naquele dia a rota era item fantasma do topnav e do teste, e foi removida por isso. O que mudou não é o fato, é o mundo: a PARIDADE §11 mandou construir a view `saude` do protótipo, e o `/team-mcp/scorecard` deixou de ser o destino da pílula pra ser o destino do drill "ver →" dentro dela.
**Pronto quando:** cada uma das 8 rotas renderiza `team-mcp/Forja/Cockpit` com a prop `tab` certa (sem 500 / tela branca).

## UC-FORJA-02 — Topnav do hub aparece e navega
Status: 🧪 (2 testes de `ForjaRoutesSmokeTest` citam este UC — a contagem de **9** itens (era 13 ate 2026-09-01) e, mais importante, que **todo `href` resolve pra rota registrada**. Este segundo cruza DUAS fontes independentes — `config/core_topnavs.php` × registro de rotas do Laravel — e é exatamente a classe do `forja.saude`, item fantasma que sobreviveu meses. Testar o config contra ele mesmo seria tautologia. Rodam em qualquer driver: leem config e router, sem DB. A perna visual — "aparece no header e destaca o ativo" — segue manual.)
O topnav vem de `config/core_topnavs.php['Forja']` via `useAutoModuleNav`. São **6** itens, os do protótipo, em 3 grupos: Trabalho (`Aprovações · Trabalho`) · Esteira (`Saúde→/team-mcp/scorecard · MCP`) · Histórico (`Changelog · Integrador`).

> **9 → 6 em 2026-09-02** (PARIDADE §11 Onda 2, [ADR 0388](../../../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)). O header passou a ser o do protótipo: topnav em pílulas de grupo **na linha do título**, não numa segunda linha. Saíram do topo, com as rotas vivas, `Triagem` (vira tipo Proposta em Aprovações, Onda 3), `Handoffs` e `Equipe` (seções do MCP, Onda 8) e `CC Sessions` (segmento Sessões do Changelog, Onda 9). `Integrador` nasce (`/forja/integrador`, view estática do protótipo). A conta 9 no parágrafo anterior é fato datado de 2026-09-01.

> **13 → 9 em 2026-09-01.** [W] mandou convergir a faixa com o protótipo (`forja-page.jsx`, 6 destinos em 3 pílulas), destravando a `US-FORJA-006` — textual: *"remova a proibição, estou mandando"*. Saíram do TOPO `Backlog`, `Quadro`, `Roadmap (Gantt)` e `Tarefas`; **as rotas seguem vivas** e os quatro continuam a um clique por dentro do `Trabalho` (segmentos `Lista` · `Quadro` · `Gantt`, este último **navegando** pra `/forja/roadmap-gantt`). Só saíram os que tiveram a absorção **medida em produção** — os outros 3 que o protótipo também não mostra (`Triagem`, `Handoffs`, `Equipe`/`CC Sessions`) ficaram, porque o receptor deles aqui ainda não existe: `/forja/mcp` é MOCKADO e não tem seção de handoffs nem tokens de equipe, o Changelog não projeta sessão com título, e `Aprovações` abre vazia enquanto a `Triagem` tem 3 tickets vivos. Medição nos dois renders: [PARIDADE-area-forja §6-bis](../../../../../../memory/requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md). Desde a fusão de 2026-06-16 este é o ÚNICO topnav que casa `/team-mcp/*` — a nav é a mesma em todo o hub.

> **9 → 10 em 2026-08-06** ([W]: *"quero que registre"*). O `Roadmap (Gantt)` chegou da Jana no [#5310](https://github.com/wagnerra23/oimpresso.com/pull/5310) (ADR 0366 §D-C item 3) e abria **sem faixa nenhuma** em produção: o ghost estava no `DataController`, mas a faixa de `/forja/*` sai deste config — duas superfícies distintas, e só uma tinha sido preenchida. O `can` da aba é `jana.mcp.tasks.read` (gate real do `RoadmapGanttController@index`), não o `jana.mcp.usage.all` das vizinhas — aba que aparece e dá 403 é pior que aba ausente. Convive com o quarter view `/project-mgmt/roadmap` por decisão da [ADR 0367 D7](../../../../memory/decisions/0367-cockpit-unico-forja-project-mgmt-morre.md): são leituras incomensuráveis (epic × task), e o quarter só sai "quando o Gantt provar que substitui".

> **10 → 11 em 2026-08-08.** Entrou `Aprovações` (`/forja/aprovacoes`), a superfície do funil de admissão da [ADR 0368](../../../../memory/decisions/0368-funil-admissao-feature-pesquisa-propoe-w-admite.md) — a fila de `mcp_tasks` em `pending_approval`. Vem **primeiro** na faixa de propósito: é o que está parado esperando decisão. Diferente do caso do Gantt acima, as **duas** superfícies foram preenchidas no mesmo PR (`core_topnavs.php` alimenta o shell; `FORJA_TABS` alimenta a faixa do hub, que é a que esta tela renderiza) e a Page monta `<ForjaHub>` — então não repetiu o "abriu sem faixa". `can` = `jana.mcp.usage.all`, igual às vizinhas, porque a ADR 0368 §4 proíbe permission de aprovação enquanto houver um único aprovador. Sem `badge` estático: a contagem é prop deferida viva.

> **11 chapados → 3 grupos em 2026-08-08.** A faixa passou a agrupar em **Trabalho** (Aprovações · Triagem · Backlog · Quadro · Roadmap · Tarefas) · **Esteira** (MCP · Equipe · Saúde) · **Histórico** (Changelog · CC Sessions). É **só apresentação**: nenhuma rota mudou, nenhum item saiu, e o `core_topnavs.php` segue chapado (o shell não desenha grupo) — só reordenado pra bater com a faixa. Cada item ganhou `hint` (tooltip); no par **Backlog × Tarefas** ele faz o mínimo enquanto a `US-FORJA-006` não decide: diz que um é *"issues do projeto FORJA"* e o outro *"todas as tasks do time"* — dois nomes parecidos com escopos diferentes, sem rótulo, é o que confunde.

> **11 → 12 em 2026-08-08.** Entrou `Handoffs` (`/forja/handoffs`) no grupo **Esteira**, antes do MCP. Não é tela nova: é a seção de handoffs que vivia DENTRO da aba MCP e virou tela própria. O motivo é de natureza, não de tamanho — handoff é dado **VIVO** (o loop de design rodando agora, com `stale` e conflito de gate) e a aba MCP é vitrine **MOCKADA** do contrato; operação diária enterrada numa vitrine é operação que ninguém olha. O `ForjaMcp.tsx` saiu de 694 → 263 linhas. Nenhuma regra mudou: as levers seguem POSTando em `/forja/handoff/{slug}/lever` → `HandoffLeverService`, sem auto-merge (ADR 0283).

> **12 → 13 em 2026-08-09.** Entrou `Trabalho` (`/forja/trabalho`), a lista única da `US-FORJA-006` que funde os três backlogs. **Convive** com Backlog e Tarefas de propósito nesta onda: deletar implementação em uso é irreversível, e a US exige que [W] veja qual sobrevive — a comparação é olhando as duas no ar. Quando a perdedora sair, a faixa volta a encolher.

**Pronto quando:** os 9 itens aparecem no header sob os 3 rótulos, navegam e destacam o ativo por URL.

## UC-FORJA-14 — As duas superfícies de navegação servem os mesmos destinos
Status: 🧪 (1 teste de `ForjaRoutesSmokeTest` cita este UC. **Não é tautológico**: cruza `config/core_topnavs.php` — que alimenta o SHELL (`AppShellV2`) — contra `FORJA_TABS` do `ForjaHub.tsx` — que alimenta a FAIXA do hub, a que as telas sob `/forja/*` de fato renderizam. Arquivos distintos, linguagens distintas, mantidos à mão. Tem **guarda anti-falso-verde**: se o parse do `.tsx` não extrair href nenhum, o teste falha em vez de comparar duas listas vazias e passar.)

Existe por um defeito **real**: em 2026-08-06 o Roadmap (Gantt) foi registrado só no config e abriu **sem faixa nenhuma** em produção — 10 itens em `shell.topnavs.Forja__core` e **zero** `.topnav-chip` no DOM. Foram 8 diagnósticos errados antes de alguém perguntar ao runtime (lápide §5 *"Navegação tem CINCO superfícies na Forja"*). Nada impedia que voltasse a divergir; agora impede.

**Pronto quando:** as duas listas de `href` são iguais **e na mesma ordem**; item registrado em um só dos lados reprova, com a mensagem dizendo qual lado está faltando.

## UC-FORJA-15 — Saúde projeta o loop com dado real, e o sparkline só existe onde há série
Status: 🧪 (a rota entra no dataset de `UC-FORJA-01`/`UC-FORJA-05`, que provam render + GET-only. A perna própria deste UC — "cada número vem de uma fonte real e o card sem histórico não desenha linha" — é **prosa verificável por leitura do serviço**, ainda sem Pest dedicado; por isso 🧪 e não ✅. O ✅ vem do manifesto derivado do JUnit, nunca escrito à mão.)

`/forja/saude` renderiza a view `saude` do protótipo (`prototipo-ui/cowork/forja-page.jsx`, `SaudeView`) com o markup e as classes do protótipo (`fj-saude`, `fj-metric`, `fj-spark`, `fj-wip`, `fj-flux-*`, `fj-age`, `fj-gate-health`), conforme [ADR 0388](../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md) — réplica primeiro. O dado é REAL, via `ForjaSaudeService`, que **reusa** `ScorecardBuilderService` (o mesmo do `/team-mcp/scorecard`), `ForjaQuadroService` e `ForjaChangelogService` em vez de refazer as consultas.

A regra dura, e é o que separa este UC de "tem 4 cards bonitos": **o sparkline só é desenhado onde a série É a história da própria métrica.** Chamadas MCP, Movimentações e Devs ativos têm série diária real (`mcp_audit_log.ts`, `mcp_task_events.occurred_at`); "Checks verdes" **não tem histórico persistido em tabela nenhuma**, então o serviço manda `serie: null` e o componente **não renderiza o `<svg>`**. Desenhar ali uma linha derivada de outra grandeza seria rotular como histórico uma coisa que não é — a classe de erro do §5 2026-07-16.

**Diferenças declaradas vs o protótipo** (nenhuma é layout; as três estão na lista de inconsistências):
- a seção **"Automação"** (3 toggles de regra) **não é replicada** — produção não tem motor de regras, e toggle que não liga nada é controle falso;
- **"Gates de CI por fase"** vira **"Checks do MCP"**: mesmo markup (`fj-gate-health`), dado real (`buildChecks()`), porque não há fonte de runtime pro estado verde/âmbar/vermelho dos gates de CI;
- o **custo em BRL** que `buildFacts()` traz **não entra nesta tela** — o cockpit é a superfície que mais recebe screenshot e smoke, e valor monetário nesses artefatos é proibição Tier 0.

**Pronto quando:** a rota responde 200 com `tab=saude`; os 4 cards mostram número vindo de query real; o card sem série não tem `<svg class="fj-spark">`; e a comparação medida com o protótipo (`design-diff --compare --check`, tema dark nos dois lados) dá **0 `DIVERGE(bug)`** em D2/D4/D6/D8 — o critério de fechamento da Onda 7 no [PARIDADE §11](../../../../memory/requisitos/Forja/PARIDADE-area-forja-diagnostico-e-ondas.md).

## UC-FORJA-03 — Entry "Forja" na sidebar
Status: ⬜ (manual/visual)
`DataController@modifyAdminMenu` injeta o dropdown "Forja" (ícone martelo, atalho `G F`), separado do hub Equipe; os ghosts espelham os itens do topnav acima.
**Pronto quando:** "Forja" aparece na sidebar e leva ao cockpit; os ghosts batem 1:1 com `config/core_topnavs.php['Forja']['items']`.

## UC-FORJA-05 — Read-only (o shell não muta nada)
Status: 🧪 (1 teste de `ForjaRoutesSmokeTest` cita este UC — cada uma das 8 rotas de aba é GET-only, lido do registro de rotas. Roda em qualquer driver, inclusive sqlite, ao contrário dos casos de request que só pulam. Escopo honesto: prova que **a aba** não escreve; as rotas POST dedicadas — lever/aprovar/rejeitar/fundir — existem por design e são cobertas pelo `UC-FORJA-09`/`UC-FORJA-10`.)
Nenhuma rota desta onda escreve estado; todas são GET de render.
**Pronto quando:** não há ação na tela que escreva no banco.

## UC-FORJA-07 — Acesso (auth + permissão)
Status: 🧪 (as DUAS pernas cobertas desde [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887) — anônimo e autenticado-sem-permissão — e o Pest passou a rodar em lane. Segue 🧪 pelo mesmo motivo do UC-FORJA-01: o ✅ é derivado do manifesto, não declarado.)
`ForjaController` exige login + `jana.mcp.usage.all` (construtor; mesma do Scorecard/Team). Repo-wide cross-business intencional (ADR 0093) pro superadmin.
**Pronto quando:** usuário autenticado **sem** `jana.mcp.usage.all` recebe 403, e anônimo é barrado pelo `auth`.

> **Errata 2026-07-27:** este bloco dizia `copiloto.mcp.usage.all`. O nome mudou pra `jana.mcp.usage.all` no [#4853](https://github.com/wagnerra23/oimpresso.com/pull/4853) (`632c5182e2`, "alinha o código ao `jana.*` que o banco já usa desde maio`") e a documentação ficou pra trás. Medido em 2026-07-27: `git grep -l "copiloto\.mcp\.usage\.all" -- '*.php'` = **0** (zero PHP vivo); o nome antigo sobrevive em 42 arquivos `.md`/`.tsx`, tratados fora deste PR.

## UC-FORJA-08 — Triagem lista as propostas FORJA fiéis ao protótipo
Status: ⬜ (smoke pós-merge — depende do seeder rodar; sem DB no worktree)
`/forja` projeta `mcp_tasks` project=FORJA em estado de triagem (`McpTask::triage()`) via `Inertia::defer` (`tickets`). Após `db:seed --class=…ForjaDemoTicketsSeeder`: FORJA-152 (Tela·KB·[CC]), FORJA-151 (Bug·Financeiro·[CC]), FORJA-150 (Refino·Atendimento·[CC]).
**Pronto quando:** as 3 linhas aparecem com ID mono · badge de tipo colorido (Tela=roxo·Bug=âmbar·Refino=azul) · título · tag de módulo · selo `[CC]` · botão roxo Analisar; aba mostra badge 3.

## UC-FORJA-09 — Analisar abre o dossiê lateral (Aprovar/Rejeitar/Fundir)
Status: 🧪 (cobertura: endpoints `/forja/{id}/{dossier,aprovar,rejeitar,fundir}` espelham TriageController PR-5a; aguarda Pest verde)
Clicar **Analisar** (ou `Enter` na linha em foco) abre `ForjaDossier` → `GET /forja/{id}/dossier` (valor×esforço sugerido, risco Tier-0 heurístico, duplicatas, docs/sessões). Aprovar→backlog (status→todo, exige dono+prio), Rejeitar (→cancelled), Fundir (duplicata + evento) — cada um sob dialog de confirmação [W].
**Pronto quando:** dossiê carrega dados reais e as 3 ações respondem (Pest cobrindo `aprovar`/`rejeitar`/`fundir` como no TriageController).

## UC-FORJA-10 — Triagem só muta sob confirmação [W]
Status: ⬜ (manual)
Listar e abrir o dossiê é read-only. Nenhuma escrita acontece sem o `AlertDialog` de confirmação (Aprovar/Rejeitar/Fundir). valor×esforço e risco Tier-0 são **sugestão derivada rotulada**, não dado medido.
**Pronto quando:** não há mutação sem confirmação humana; nada inventado é apresentado como medido.

## UC-FORJA-12 — Aba MCP lista os handoffs reais de `cowork_handoffs` (Fase 1 · ADR 0283)
Status: 🧪 (12 testes de `ForjaMcpServiceTest` **citam este UC no título** — exclui superseded, maior-version-por-slug, stale derivado, gate verde/vermelho/rodando/na, serialização, heartbeat. Verde real: 19 passed / 28 assertions no CT 100 em 2026-07-27, `DB_CONNECTION=sqlite` como o `ci.yml` força. Segue 🧪 e não ✅ porque o veredito ✅ vem do manifesto `scripts/casos-test-results.json`, que é **derivado do JUnit do CI** pelo `casos-results-publish` — não se escreve à mão.)
A aba MCP deixou de ser 100% mock: `ForjaController@mcp` projeta `cowork_handoffs` (+ heartbeat do ingest) via `Inertia::defer` (`handoffs`/`heartbeat`) — `ForjaMcpService`. Status REAIS `pending/applied/rejected/stale/superseded`; `stale` derivado na leitura (>3d); gate derivado do `gate_status` com a MESMA regra verde do `handoff-ack` (`conformance && critique_score>=80 && a11y`). A seção fica no topo (`data-testid="forja-mcp-handoffs"`); contrato/tokens/auditoria seguem MOCKADO embaixo (sem regressão de 1º paint — `Deferred` só na seção nova).
**Pronto quando:** `/forja/mcp` lista os handoffs reais (status correto + gate do `gate_status` + ⚿ sig + `N arq` + PR drill), filtros por status com contagem funcionam, empty-state mostra o heartbeat ("transporte sem sinal" vira alerta), e o contrato lista `handoff-pending`/`handoff-ack`. Levers (re-disparar/devolver/supersede) ficam `disabled`+TODO (Fase 2); **SEM merge** (1-clique do [W]).

> **Reconciliação 2026-09-02 (duas frases deste UC ficaram stale, corrigidas aqui e não no histórico).**
> (1) *"Levers ficam `disabled`+TODO"* — caducou na **Fase 2** ([PR-7b](https://github.com/wagnerra23/oimpresso.com/pull/2924), rota `POST /forja/handoff/{slug}/lever` → `HandoffLeverService`): elas operam, sob confirmação. O "SEM merge" segue valendo, e é Tier 0.
> (2) *"A seção fica no topo"* — a seção **saiu** da aba em 2026-08-08 (virou `/forja/handoffs`) e **voltou** na Onda 8 (2026-09-02), agora **abaixo da intro `mockado`**, que é onde o protótipo a desenha (`forja-mcp.jsx::ForjaMCPView`). Medido no protótipo servido: `.fj-mcp` contém `.fj-ho`. O `data-testid="forja-mcp-handoffs"` foi **preservado** nas três mudanças — por isso nenhum teste deste UC quebrou. Detalhe no `UC-FORJA-15`.

## UC-FORJA-13 — Badge `conflito` quando o ack mente sobre o gate (Gap 2 · ADR 0283)
Status: 🧪 (7 testes de `ForjaMcpServiceTest` **citam este UC no título** — conflito em check vermelho/pendente, mantém verde com checks verdes, só cruza ack verde, degrada sem token/API/branch-protection; GitHub API mockada via `Http::fake`, sqlite lane `ci-sqlite-pest.list`. Mesmo run verde de 2026-07-27 do UC-FORJA-12; segue 🧪 pelo mesmo motivo — o ✅ é derivado do manifesto, não declarado.)
O `gate_status` é AUTO-REPORTADO pelo [CC] e pode divergir dos required checks REAIS do PR no GitHub. `ForjaMcpService::deriveGate` cruza o ack VERDE com o estado real do PR (`PrChecksResolver` → GitHub API: PR → branch protection → check-runs). Se a realidade não está verde (vermelho/pendente) → badge `conflito` (dot destructive pulsando, drill pro PR, hint no hover). Best-effort: sem token/rede/branch-protection legível → segue o `gate_status` (comportamento da Fase 1, sem conflito falso por check advisory).
**Pronto quando:** um handoff `applied` com `gate_status` verde + `pr_url` cujo required check está vermelho/pendente mostra `conflito ack×checks`; com checks verdes mostra `gate ok`; e a leitura nunca quebra quando o GitHub está indisponível.

## UC-FORJA-15 — A view MCP é a réplica do protótipo, com o painel Handoffs dentro (PARIDADE §11 Onda 8)
Status: 🧪 (3 testes de `ForjaMcpHandoffsInlineTest` **citam este UC no título** — `/forja/mcp` entrega `handoffs`+`heartbeat`, `/forja/handoffs` segue entregando, e as duas rotas servem a MESMA lista. Registrado nas DUAS lanes: `ci-sqlite-pest.list` (pega erro de binding; o happy-path *pula* sem schema MySQL) e a lane MySQL `forja-pest.yml`, que é quem executa. ⬜→🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI — não se escreve à mão.
**O que este Pest NÃO cobre, de propósito:** a perna **visual** (classe, mono, cor, alinhamento). Ela tem dono — `design-diff` medindo os dois renders — e um Pest que assertasse className seria régua paralela a régua consolidada ([proibicoes.md §5](../../../../memory/proibicoes.md) 2026-07-09). O Pest defende o que some em SILÊNCIO: sem as props deferidas, o `<Deferred>` fica em fallback eterno, sem erro no console.)

`/forja/mcp` renderiza no vocabulário do bundle (`fj-mcp*`, `fj-perm*`, `fj-token*`, `fj-audit*`, `fj-ho-*`), na ordem do protótipo: **intro `mockado` → Handoffs F1→F3 → grid [contrato | tokens] → auditoria**. O painel de handoffs é o MESMO componente que `/forja/handoffs` renderiza (`ForjaHandoffs.tsx`), com a MESMA projeção (`ForjaMcpService`) — uma consulta, dois pontos de render; `Inertia::defer` só executa a closure quando a prop é pedida.

Valores-alvo **medidos** no protótipo servido (dark · 1440 · espelho provado SYNC · portão `--preview-ds` verde), 2026-09-02:
`.fj-mcp-tbl` = 9 linhas · col0 mono `oklch(0.94 0.005 90)` · col1/col2 não-mono `oklch(0.72 0.005 90)` · **os 3 `th` `left`** (produção tinha `text-right` na col2) · `.fj-perm-ok` `oklch(0.84 0.13 150)` sobre `oklch(0.275 0.06 150)`, mono · `.fj-perm-deny` `oklch(0.84 0.18 25)` · e os 6 pontos `.mono` (`fj-token-id`, `fj-audit-ts|tool|args`, `fj-ho-slug`, `fj-ho-pr`) todos monoespaçados.

**Pronto quando:** `design-diff --compare prod.json design.json --check` fecha **0 `DIVERGE(bug)`** em D2/D4/D6/D8 pro par `/forja/mcp` × view `mcp`, tema dark e mesma viewport nos dois lados; e `/forja/handoffs` segue servindo o mesmo painel (a rota não morreu).

**Desvios declarados** (por DADO, não por estilo — o mock tem campo que a tabela real não tem): sem `~onda` (não existe coluna em `cowork_handoffs`); 5 abas de filtro em vez de 6 (o mock tem `merged`, que o dado real não produz; o real tem `superseded`, que o mock não previu e que ganhou pílula neutra); selo de gate omitido quando `gate = 'na'`, como no protótipo.

## UC-FORJA-16 — Changelog desenha o feed do protótipo (dot + corpo), com selo e módulo de coluna REAL
Status: 🧪 (4 testes de `ForjaChangelogServiceTest` **citam este UC no título** — shape completo, `flags` filtrado, `modules` da coluna, `date_label` dd/mm. Segue 🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI pelo `casos-results-publish` — não se escreve à mão.)
PARIDADE §11 Onda 9 ([ADR 0388](../../../../../../memory/decisions/0388-replica-primeiro-conformidade-vira-lista-de-inconsistencias.md)): a linha do changelog deixa de ser uma tabela achatada de **5 colunas** (dot · id · título · ator · data) e passa a ser a do `ChangelogFeed` de `prototipo-ui/cowork/forja-page.jsx` — **2** colunas (`.fj-feed-dot` + `.fj-feed-body`), com o corpo em topo (`.fj-feed-ref` · `.fj-flag-*` · `.fj-feed-when`), resumo (`.fj-feed-resumo`) e meta (`.fj-role` + `.fj-mod sm`). Zero CSS novo — as classes vieram no bundle da Onda 1. `ForjaChangelogService` passa a servir `flags`, `modules` e `date_label`: `flags` é a interseção das `tags` reais do doc com as **duas** que o protótipo estiliza (`tier-0`, `breaking`); `modules` sai da coluna `module` (a string literal `'null'` do frontmatter legado conta como ausência); `date_label` é a MESMA data de `date`, em `dd/mm`.
**Pronto quando:** `/forja/changelog` renderiza `.fj-feed-item` com dot + corpo; tag real fora do par renderizável **não** vira selo sem cor; doc sem `module` não desenha chip; e `date` continua ISO (é ele que ordena e vai pro `title` do `.fj-feed-when`).

## UC-FORJA-17 — Sessão sem resumo herda o 1º prompt — acaba a parede "Sessão Claude Code"
Status: 🧪 (3 testes de `ForjaChangelogServiceTest` **citam este UC no título** — deriva do 1º prompt por `ts`, vazio honesto sem prompt, corte em 160 + PII redigida. Mesmo motivo do UC-FORJA-16 pra seguir 🧪.)
Medido em 2026-09-02 ([forja-cockpit-visual-comparison.md](../../../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md)): produção projetava a string fixa `"Sessão Claude Code"` toda vez que `mcp_cc_sessions.summary_auto` era vazio — uma parede de linhas idênticas. O título agora cai, nesta ordem: (1) `summary_auto`, (2) o **primeiro** prompt do usuário da sessão (`mcp_cc_messages` com `msg_type='user'`, ordenado por `ts` asc) e (3) **string vazia** — e aí o componente omite o parágrafo em vez de inventar rótulo. A derivação roda DEPOIS do corte da lista (≤30 linhas), então são no máximo 30 leituras no índice `cc_msg_sess_ts_idx`. O trecho passa por `PiiRedactor` e é cortado em 160 chars. Sem alargamento de exposição: `/forja/changelog` é `can:jana.mcp.usage.all` (superadmin), mais estreita que a `/team-mcp/cc-sessions` (`jana.cc.read.team`), que já serve `content_text` inteiro.
**Pronto quando:** sessão sem `summary_auto` mostra o 1º prompt (o mais antigo por `ts`, não o de menor `id`); sessão sem prompt algum fica com título vazio; e e-mail no prompt sai como `[REDACTED:EMAIL]`.

---

## Conformance — dono é outro mecanismo (sem UC por design)

**DS v6 (sem cor crua · PageHeader canon)** — `PageHeader` canon (`@/Components/PageHeader`), layout via `inline-flex`, tokens semânticos, zero paleta crua / `rounded-xl+`. O juiz é `conformance-gate` + `layout-primitives` + `pageheader-gate` + `eslint ds/*`.

> **Rebaixado de `UC-FORJA-06` em 2026-07-27 (justificativa).** Não é caso de **uso** — ninguém "usa" conformidade de token; é restrição de conformance cujo critério de aceite é literalmente "gate X verde". Escrever um Pest que reafirme isso seria **régua paralela a régua consolidada**, proibido por [proibicoes.md §5](../../../../memory/proibicoes.md) (entrada 2026-07-09). Mantê-lo como UC só produzia órfão permanente: nenhum teste jamais o citaria, porque o dono não é teste.
>
> Distinção deliberada vs. `UC-KBV2-09` (`kb/Index.v2.casos.md`), que **permanece** UC apesar de também apontar pra um gate: aquele registra uma **violação medida** (68 ocorrências absorvidas no baseline do `ui:lint`) com decisão pendente do [W] — rastrear um débito aberto é conteúdo de contrato. Este aqui afirmava uma restrição **já satisfeita**, que o gate defende sozinho.

## Comportamento que deixou de existir (UC removido)

**"Sem colisão de topnav com /team-mcp"** — o antigo `UC-FORJA-04` exigia que, em `/forja/*`, o topnav exibido fosse "o da Forja, **não o da Equipe**".

> **Removido em 2026-07-27 (justificativa).** O critério ficou **infalsificável**: não existe mais "o da Equipe" pra diferir. `Modules/TeamMcp/Resources/menus/` **não existe** (verificado 2026-07-27) — na fusão de 2026-06-16 o topnav próprio do TeamMcp foi deletado e `config/core_topnavs.php['Forja']` virou o **único** grupo que casa `/team-mcp/*` no `useAutoModuleNav`. O próprio config registra isso: *"este é o ÚNICO que casa `/team-mcp/*` … então a nav é a mesma em todo o hub"*. A colisão foi resolvida por **deleção**, não pelo guard — um UC que só pode passar não defende nada (mesma doutrina do §5 de [proibicoes.md](../../../../memory/proibicoes.md) sobre verde que não pode ficar vermelho).
>
> O arquivo ainda carrega o comentário externo da PR-A dizendo que a raiz separada evita sobreposição; ele é anterior à fusão e descreve a intenção original. Se algum dia o contrato desejado for "a nav do hub casa tanto `/forja/*` quanto `/team-mcp/*`", isso é um UC **novo**, derivado do charter/SDD — não deste bloco, e nunca derivado do config (seria tautológico, §5 2026-06-05).

## Limitações conhecidas (sem UC — não são critério de aceite)

**Badge "3" da aba Triagem é estático.** Vem de `config/core_topnavs.php` (nº de propostas-semente). O contador vivo da fila chega pela prop deferida `triagemCount`, usada no badge do sino. O topnav não suporta badge por-request hoje (`LegacyMenuAdapter::buildTopNavs` lê config estática). Quando o shell ganhar badge dinâmico, migrar o da aba.

> **Rebaixado de `UC-FORJA-11` em 2026-07-27 (justificativa).** O próprio bloco se declarava "nota de fidelidade", não caso de uso: descreve uma **limitação da plataforma**, não um comportamento que o produto promete e que um teste possa defender. Vira prosa honesta — o padrão canônico pra o que não tem teste (ver [how-trabalhar.md §Pedido de tela](../../../../memory/how-trabalhar.md)).

## UC-FORJA-18 — O badge de pendências vive em TODA tela do hub, não só na mesa (Onda 1 do export · §3.1)
Status: 🧪 (7 casos de `ForjaBadgePendenciasTest` **citam este UC no título** — um por controller do hub, na lane MySQL `forja-pest.yml`. Segue 🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI — não se escreve à mão.)

O alvo §3.1 do export pede *"badge de pendências no destino Aprovações"*. No `forja-page.jsx:1123` o badge é renderizado no destino `hoje` em **qualquer** view: `pendencias` é estado da página inteira, não da aba aberta. É o que avisa que há algo esperando decisão enquanto você está em OUTRA tela.

Até aqui só `Forja/Aprovacoes/Index` passava a prop — o badge aparecia justamente na única tela onde é redundante (a fila já está na frente) e sumia nas outras oito. O `forja-cockpit-visual-comparison.md` já tinha **medido** o efeito colateral sem nomear a causa: os **−35px** de largura do nav entre protótipo e produção, que ele classificou como *"dado (badge de pendências), não CSS"*.

Os 7 controllers do hub passam a servir `pendencias` (`Inertia::defer` sobre `ForjaAprovacoesService::contagem()`, um COUNT indexado), e o `ForjaHub` lê a prop **da página** via `usePage()` — assim a próxima Page do hub nasce com o badge, em vez de a prop ser repetida nas nove. A explícita vence: `Aprovacoes/Index` já tem a fila em mãos e passa o número que ela mesma mostra, sem esperar o 2º round-trip do defer.

**O que este Pest NÃO cobre, de propósito:** a perna **visual** (`fj-tab-badge`: cor, raio, posição). Ela tem dono — `design-diff` medindo os dois renders — e um Pest que assertasse className seria régua paralela a régua consolidada ([proibicoes.md §5](../../../../../../../memory/proibicoes.md) 2026-07-09). O Pest defende o que some em SILÊNCIO: apagar a linha de um controller tira o badge daquela tela sem erro, sem console, sem vermelho.

**Pronto quando:** as 7 rotas do hub entregam `pendencias` no partial reload, como inteiro, com o **mesmo** valor de `ForjaAprovacoesService::contagem()`; e o badge aparece no destino Aprovações estando você em qualquer view.

## Dívida SALDADA — o Pest das rotas estava quebrado E mudo (resolvido em #4887)

Registro do que era, porque a lição não é o conserto — é **como um teste fica anos parecendo cobertura sem nunca ter executado**.

`Modules/Forja/Tests/Feature/ForjaRoutesSmokeTest.php` cobriria `UC-FORJA-01` e `UC-FORJA-07`, mas **nunca rodou uma vez**:

- **Não rodava em lane nenhuma.** `TeamMcp` não estava no matrix do `modules-pest.yml` (que nem emite JUnit), e da pasta só 7 dos 26 arquivos estavam em `.github/ci-sqlite-pest.list`. Varredura contada em 2026-07-27.
- **Falhava se rodasse.** Executado no CT 100 (`DB_CONNECTION=mysql`): **7 failed, 5 passed**. Duas causas independentes — (a) `RouteNotFoundException: Route [forja.saude] not defined` (a rota não existe; ver errata no topo); (b) `DatasetArgumentsMismatch: Test expects 2 arguments but dataset only provides 1` ×6 — o dataset era array associativo, que em Pest vira **1** argumento (a chave é nome do caso), mas o `it()` declarava dois parâmetros. O happy-path (`assertStatus(200)` + `component(...)`) **jamais executou**.

**Saldado em [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887)** (2026-07-27), com recibo no mesmo oráculo: `7 failed / 5 passed` → **`0 failed / 5 passed / 10 skipped`**. Rota fantasma removida; dataset virou `nome do caso => [routeName, tab]`; `UC-FORJA-07` ganhou a perna de autenticado-sem-permissão — com o usuário filtrado por **não-admin**, porque o `Gate::before` do `AuthServiceProvider` libera qualquer ability pra quem tem `Admin#{business_id}` e o 403 seria falso-verde. O módulo ganhou lane: +18 alvos na lista sqlite (que emite `pest-ci-junit`, já colhido pelo `casos-results-publish`) + a lane MySQL `forja-pest.yml`.

**Limite honesto que permanece:** em sqlite o teste só *pula* (a stack UltimatePOS exige schema MySQL), então quem executa o happy-path é a lane MySQL nova — e ela **não pôde ser validada fora do CI**: o `oimpresso-staging` do CT 100 tem 15 tabelas (`copiloto_*`/`vestuario_*`), sem schema UltimatePOS, e `Business::first()` não existe lá. Enquanto o veredito `pass` de `UC-FORJA-01` não aparecer no manifesto vindo do JUnit, o Status correto destes dois UCs é **🧪**, não ✅.
