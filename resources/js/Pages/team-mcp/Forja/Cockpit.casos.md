---
id: resources-js-pages-team-mcp-forja-cockpit-casos
casos: Forja · cockpit do cowork loop · /forja
irmaos: Cockpit.charter.md (lei) · Cockpit.tsx (tela)
tecnica: Caso de uso = narrativa + critério de aceite verificável
owner: wagner
last_run: "2026-06-17"
---

# Casos de uso — /forja (cockpit Forja · shell)

> **Status:** ✅ passa (provado por teste) · 🧪 em teste (Pest escrito, aguarda run verde) · ⬜ não verificado · ❌ quebrou.

> Onda Forja: cockpit do cowork loop, projetando `mcp_tasks` project=FORJA + git/ADR/sessão + gates (sem dado fantasma; contrato/tokens/auditoria da aba MCP seguem MOCKADOS por design). Persona: Wagner [W] (superadmin). A Triagem só muta sob confirmação [W]. Referência: [forja-cockpit-visual-comparison.md](../../../../memory/requisitos/TeamMcp/forja-cockpit-visual-comparison.md).

> ⚠️ **Errata 2026-07-27 — "as 6 abas" era stale.** A redação anterior afirmava 6 abas próprias incluindo `/forja/saude`. Medido: **5** rotas GET sob `/forja` e **9** itens de topnav (o hub foi fundido com TeamMcp em 2026-06-16, e "Saúde" aponta pro Scorecard). Recibos abaixo em cada UC. A rota `/forja/saude` **nunca existiu** nesta versão — `ForjaRoutesSmokeTest` a listava e por isso falhava; removida em [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887).

## UC-FORJA-01 — As rotas /forja respondem (shell no ar)
Status: 🧪 (o Pest foi consertado e passou a rodar em lane — [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887). Segue 🧪 e não ✅ porque o ✅ vem do manifesto `scripts/casos-test-results.json`, derivado do JUnit do CI pelo `casos-results-publish` — não se escreve à mão.)
`ForjaController` serve **5** rotas GET de aba: `/forja` (Triagem), `/forja/backlog`, `/forja/quadro`, `/forja/changelog`, `/forja/mcp`. Não há `/forja/saude` — Saúde foi fundida no Scorecard real (`/team-mcp/scorecard`), conforme o comentário em `Modules/TeamMcp/Http/routes.php`.
Recibo: `docker exec oimpresso-staging php artisan route:list --path=forja --json` (CT 100, 2026-07-27) devolveu 5 GET de aba + os POST de lever/triagem, **sem** `forja.saude`.
**Pronto quando:** cada uma das 5 rotas renderiza `team-mcp/Forja/Cockpit` com a prop `tab` certa (sem 500 / tela branca).

## UC-FORJA-02 — Topnav do hub aparece e navega
Status: ⬜ (manual/visual)
O topnav vem de `config/core_topnavs.php['Forja']` via `useAutoModuleNav`. São **9** itens, não 6: 5 próprios (`Triagem · Backlog · Quadro · Changelog · MCP`) + 4 do hub TeamMcp absorvido (`Tarefas · Equipe · CC Sessions · Saúde→/team-mcp/scorecard`). Desde a fusão de 2026-06-16 este é o ÚNICO topnav que casa `/team-mcp/*` — a nav é a mesma em todo o hub.
**Pronto quando:** os 9 itens aparecem no header, navegam e destacam o ativo por URL.

## UC-FORJA-03 — Entry "Forja" na sidebar
Status: ⬜ (manual/visual)
`DataController@modifyAdminMenu` injeta o dropdown "Forja" (ícone martelo, atalho `G F`), separado do hub Equipe; os ghosts espelham os itens do topnav acima.
**Pronto quando:** "Forja" aparece na sidebar e leva ao cockpit; os ghosts batem 1:1 com `config/core_topnavs.php['Forja']['items']`.

## UC-FORJA-05 — Read-only (o shell não muta nada)
Status: ⬜ (manual)
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

## UC-FORJA-13 — Badge `conflito` quando o ack mente sobre o gate (Gap 2 · ADR 0283)
Status: 🧪 (7 testes de `ForjaMcpServiceTest` **citam este UC no título** — conflito em check vermelho/pendente, mantém verde com checks verdes, só cruza ack verde, degrada sem token/API/branch-protection; GitHub API mockada via `Http::fake`, sqlite lane `ci-sqlite-pest.list`. Mesmo run verde de 2026-07-27 do UC-FORJA-12; segue 🧪 pelo mesmo motivo — o ✅ é derivado do manifesto, não declarado.)
O `gate_status` é AUTO-REPORTADO pelo [CC] e pode divergir dos required checks REAIS do PR no GitHub. `ForjaMcpService::deriveGate` cruza o ack VERDE com o estado real do PR (`PrChecksResolver` → GitHub API: PR → branch protection → check-runs). Se a realidade não está verde (vermelho/pendente) → badge `conflito` (dot destructive pulsando, drill pro PR, hint no hover). Best-effort: sem token/rede/branch-protection legível → segue o `gate_status` (comportamento da Fase 1, sem conflito falso por check advisory).
**Pronto quando:** um handoff `applied` com `gate_status` verde + `pr_url` cujo required check está vermelho/pendente mostra `conflito ack×checks`; com checks verdes mostra `gate ok`; e a leitura nunca quebra quando o GitHub está indisponível.

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

## Dívida SALDADA — o Pest das rotas estava quebrado E mudo (resolvido em #4887)

Registro do que era, porque a lição não é o conserto — é **como um teste fica anos parecendo cobertura sem nunca ter executado**.

`Modules/TeamMcp/Tests/Feature/ForjaRoutesSmokeTest.php` cobriria `UC-FORJA-01` e `UC-FORJA-07`, mas **nunca rodou uma vez**:

- **Não rodava em lane nenhuma.** `TeamMcp` não estava no matrix do `modules-pest.yml` (que nem emite JUnit), e da pasta só 7 dos 26 arquivos estavam em `.github/ci-sqlite-pest.list`. Varredura contada em 2026-07-27.
- **Falhava se rodasse.** Executado no CT 100 (`DB_CONNECTION=mysql`): **7 failed, 5 passed**. Duas causas independentes — (a) `RouteNotFoundException: Route [forja.saude] not defined` (a rota não existe; ver errata no topo); (b) `DatasetArgumentsMismatch: Test expects 2 arguments but dataset only provides 1` ×6 — o dataset era array associativo, que em Pest vira **1** argumento (a chave é nome do caso), mas o `it()` declarava dois parâmetros. O happy-path (`assertStatus(200)` + `component(...)`) **jamais executou**.

**Saldado em [#4887](https://github.com/wagnerra23/oimpresso.com/pull/4887)** (2026-07-27), com recibo no mesmo oráculo: `7 failed / 5 passed` → **`0 failed / 5 passed / 10 skipped`**. Rota fantasma removida; dataset virou `nome do caso => [routeName, tab]`; `UC-FORJA-07` ganhou a perna de autenticado-sem-permissão — com o usuário filtrado por **não-admin**, porque o `Gate::before` do `AuthServiceProvider` libera qualquer ability pra quem tem `Admin#{business_id}` e o 403 seria falso-verde. O módulo ganhou lane: +18 alvos na lista sqlite (que emite `pest-ci-junit`, já colhido pelo `casos-results-publish`) + a lane MySQL `teammcp-pest.yml`.

**Limite honesto que permanece:** em sqlite o teste só *pula* (a stack UltimatePOS exige schema MySQL), então quem executa o happy-path é a lane MySQL nova — e ela **não pôde ser validada fora do CI**: o `oimpresso-staging` do CT 100 tem 15 tabelas (`copiloto_*`/`vestuario_*`), sem schema UltimatePOS, e `Business::first()` não existe lá. Enquanto o veredito `pass` de `UC-FORJA-01` não aparecer no manifesto vindo do JUnit, o Status correto destes dois UCs é **🧪**, não ✅.
