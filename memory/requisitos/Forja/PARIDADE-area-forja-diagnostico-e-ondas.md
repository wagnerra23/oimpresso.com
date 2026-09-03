# Área Forja — paridade protótipo × produção: diagnóstico medido e ondas por tela

- **Data da medição:** 2026-08-18 · **base:** `origin/main` (worktree `claude/forja-wave-parity-manual-42f3df`; `git rev-list --left-right --count origin/main...HEAD` = `0 0`; repo **não** raso)
- **Âncora:** `prototipo-ui/cowork/forja-page.jsx` (1.253 ln) + os irmãos que o shell carrega — `forja-data.jsx` · `forja-aprova.jsx` · `forja-mcp.jsx` · `forja-integra.jsx` · `forja-page.css`. Resolvida por `node prototipo-ui/ancora.mjs <Mod/Tela>`, **não** escolhida no olho.
- **Servido em:** `localhost:5577/oimpresso.com.html` (shell do espelho; `HTTP 200`, 10.399 bytes na medição). As 5 tags `<script src="forja-*.jsx?v=…">` estão nas linhas 111-115 do shell.
- **Origem:** [W] pediu aplicar o protótipo "no módulo Forja inteiro" + um manual de paridade de ondas por tela.

> **Limite deste documento — leia antes de usar.** Tudo abaixo é **estrutural**: leitura de código, de rota e do protótipo servido. **Não mede fidelidade visual.** Fidelidade exige `cowork-mirror-freshness --compare` (SYNC do espelho contra o Cowork vivo) + sonda `design-diff` nos DOIS renders, e **nenhum dos dois rodou** — o `--compare` aqui abortou por falta de snapshot do `DesignSync.get_file` (`✗ --compare exige um snapshot.json existente`). Logo: nenhuma linha daqui autoriza dizer "está igual ao design".
>
> **Este documento não é o "como executar".** As fases, comandos e gates vivem no painel executável — `node prototipo-ui/protocolo.config.mjs` é a **fonte única** e se lê no momento da onda. Copiar a tabela de fases pra cá seria restatear o que outro sistema sabe melhor (lápide §5 2026-07-17), e apodreceria no primeiro ajuste do protocolo. O que só este documento sabe é **quais telas existem, quais sobrevivem, em que ordem e por quê**.

---

## 1 · O achado que redesenha o pedido

"Aplicar no módulo Forja inteiro" colide com uma decisão [W] já registrada e datada.

A [**ADR 0367**](../../decisions/0367-cockpit-unico-forja-project-mgmt-morre.md) (`status: proposto`, `decided_by: [W]`, `decided_at: 2026-08-04`) decide em **D1**: *"`/project-mgmt/*` morre — as 8 telas e as 32 rotas do prefixo saem"*. As 8 são exatamente `Activity` · `Backlog` · `Board` (+`DetailSheet`) · `Burndown` · `Inbox` · `MyWork` · `Roadmap/Index` · `Triage`.

**Consequência direta:** aplicar o protótipo nessas telas é trabalho que a própria decisão manda deletar depois. Elas ficam **fora** das ondas — não por julgamento meu, por decisão registrada. Duas ressalvas da própria ADR:

- **D7 — `Roadmap/Index` (quarter view) NÃO morre agora.** Sobrevive como segunda leitura do roadmap e *"só sai quando o Gantt provar que substitui"*.
- **D5 — `MyWork`, `Inbox` e `Burndown` morrem sem receptor.** Perda consciente. ⚠️ Duas US estavam **em review** quando isso foi decidido (`US-TR-305` Inbox marcar lido, `US-TR-306` Inbox deep-link) — e o **Daily Brief de hoje ainda as lista em voo** ("Inbox — marcar lido", "Inbox — deep-link pra task/DetailSheet", 9d). O brief mostra trabalho vivo que a 0367 mata: **é conflito ativo, não esquecimento** (§9).

⚠️ A 0367 está `proposto`. A **decisão** de [W] existe e está datada; falta o **ato formal** de ratificação (PR de flip, receita em `memory/decisions/README.md`). Enquanto não ratificada, tratar as 8 como "a matar" é ler a intenção do dono — e é o que este manual faz, declarado.

## 2 · As telas que a área tem — medido, não inferido

`git grep "Inertia::render(" -- Modules/Forja/Http/Controllers/` devolve **20 componentes** distintos (o `Cockpit` aparece 6×, uma por aba). Somando `Board/DetailSheet`, são **21 arquivos** `.tsx` de tela.

### 2.1 Sobrevivem e recebem onda — prefixos `/forja` e `/team-mcp`

| rota | Page | controller | âncora no protótipo |
|---|---|---|---|
| `/forja/aprovacoes` | `Forja/Aprovacoes/Index` | `AprovacoesController` | view `hoje` → `ForjaAprovacoes` |
| `/forja/trabalho` | `Forja/Trabalho/Index` | `TrabalhoController` | view `trabalho` · `trabVis=lista` |
| — (`_components`) | `Forja/Trabalho/_components/TrabalhoQuadro` | idem | view `trabalho` · `trabVis=quadro` → `KanbanView` |
| `/forja/roadmap-gantt` | `Forja/Roadmap/Gantt` | `RoadmapGanttController` | view `trabalho` · `trabVis=gantt` → `GanttView` |
| `/forja` · `/backlog` · `/quadro` · `/changelog` · `/mcp` · `/handoffs` | `team-mcp/Forja/Cockpit` (**6 rotas, 1 tela**) | `ForjaController` (6 métodos) | views `changelog` · `mcp` → `ForjaMCPView`, `HandoffPanel` |
| `/team-mcp/scorecard` | `team-mcp/Scorecard/Index` | `ScorecardController` | view `saude` → `SaudeView` (ver §4) |
| `/team-mcp/tasks` | `team-mcp/Tasks/Index` (+`TaskDrawer`) | `TasksAdminController` | concorrente de quadro (ver §5) |

### 2.2 Fora das ondas — `/project-mgmt/*`, mortas pela 0367 D1

`Forja/Activity/Index` · `Forja/Backlog/Index` · `Forja/Board/Index` · `Forja/Board/DetailSheet` · `Forja/Burndown/Index` · `Forja/Inbox/Index` · `Forja/MyWork/Index` · `Forja/Triage/Index` — e `Forja/Roadmap/Index`, que fica **em suspensão** pela D7.

### 2.3 Fora do escopo desta paridade — sem âncora no protótipo Forja

`team-mcp/Team/Index` · `team-mcp/CcSessions/Index` · `ads/Admin/{Tools,TeamScopes,Projects,ProjectShow}`. Nenhuma resolve pra um `forja-*.jsx`. Incluí-las seria inventar demanda.

## 3 · O que o protótipo desenha — 6 views de topo

`ForjaPage` roteia por `view`, persistido em `localStorage["oimpresso.forja.view"]`:

| view | conteúdo | sub-visões |
|---|---|---|
| `hoje` | mesa de aprovações, com badge de pendências no topnav | — |
| `trabalho` | KPIs + Toolbar + FilterBar + frentes (`trabFrente`: `forja` \| unificado) | `lista` · `quadro` · `gantt` (tecla `v` cicla) |
| `changelog` | `ChangelogFeed` | — |
| `mcp` | `ForjaMCPView` + `HandoffPanel` + `ForjaIAPanel` (grupo "Esteira") | — |
| `saude` | `SaudeView` (grupo "Esteira") | — |
| `integra` | `ForjaIntegrador` — "Forja ↔ TeamMcp" | — |

Mais, transversais: `IssueDrawer` · `TarefaDrawer` · `ForjaNotifs` (painel de notificações) · `ForjaDossie` · `ForjaNewIssue` · `ForjaRunbook` · command palette.

**O protótipo já fez a consolidação que a 0367 decidiu.** A linha de migração do `localStorage` é a prova literal: `if (v === "backlog" || v === "quadro" || v === "tarefas") return "trabalho"`. Três views antigas colapsaram em uma. O protótipo está **à frente** do produto neste eixo, não atrás.

## 4 · Duas views do protótipo NÃO são demanda de tela

Presença no espelho não é pedido (lápide §5 2026-08-17). Para cada uma, a pergunta é *o alvo ainda é desejado?* — e as duas respostas são datadas:

- **`saude` → o receptor É o Scorecard, e a rota própria nunca existiu.** O `Cockpit.casos.md` carrega a errata de 2026-07-27, textual: *"A rota `/forja/saude` **nunca existiu** nesta versão — `ForjaRoutesSmokeTest` a listava e por isso falhava; removida em #4887"*. "Saúde" no topnav aponta pro `/team-mcp/scorecard` real. Logo `SaudeView` não abre tela nova: é âncora **do Scorecard** (Onda 6).
- **`integra` → propósito já cumprido; é fóssil.** `ForjaIntegrador` desenha a ponte "Forja ↔ TeamMcp". Essa fusão **aconteceu** em 2026-07-31 (o `SCOPE.md` registra o TeamMcp deletado e as capacidades movidas pra cá). Uma tela pra integrar dois módulos que hoje são um só não tem alvo. **Nenhuma onda.** Se [W] discordar, é decisão dele — não é gap a fechar.
  - ⚠️ **[W] DISCORDOU — 2026-09-01.** A cláusula de escape acima foi acionada: [W] mandou o render do `ForjaIntegrador` com a legenda textual *"isso que é esperado"*, e o topnav desse render tem `Integrador` como um dos **6** destinos (grupo Histórico). O raciocínio de 08-18 continua registrado e não era errado — a fusão de fato aconteceu; o que ele não podia saber é que o dono queria a tela **como referência viva da absorção**, não como registro de uma migração encerrada. Consequência: `integra` **sai** de "sem onda" e o §9.4 fecha (ver lá). A onda dela ainda não está numerada — depende da Onda 0, como as demais do Cockpit.
- **`ForjaNotifs`** desenha notificações, cujo receptor natural (`Inbox`) a 0367 D5 **mata sem receptor**. Fica em §9 como pergunta, não como onda.

## 5 · O conflito que bloqueia metade das ondas: 3 implementações da mesma pergunta

Recontado hoje com `wc -l` (o `BRIEFING.md` pede recontar em vez de confiar no retrato — os números de 2026-08-08 **se confirmaram** em 2026-08-18):

| pergunta | implementação A | implementação B | implementação C |
|---|---|---|---|
| backlog | `Forja/Backlog/Index` **416 ln** | `_components/ForjaBacklog` 207 ln | `team-mcp/Tasks/Index` **647 ln** |
| quadro | `Forja/Board/Index` **529 ln** | `_components/ForjaQuadro` 130 ln | — |
| triagem | `Forja/Triage/Index` **471 ln** | `_components/ForjaTriage` 210 ln | — |

Duas leituras que **invertem a intuição** e mudam a ordem das ondas:

1. **As nativas são as ricas** — o cockpit é a versão enxuta. Fundir "levando tudo pro cockpit" perderia capacidade. O `BRIEFING.md` afirma isso e a recontagem sustenta.
2. **`Forja/Trabalho` já é a quarta implementação, e nasceu pra ser a vencedora.** O `SCOPE.md` diz, textual, que ela *"funde os TRÊS backlogs"* com base na **nativa** (filtros/KPIs/memoização) — e completa: *"a remoção da implementação perdedora é decisão [W] e NÃO aconteceu nesta onda — as três convivem"*.

**Aplicar o protótipo antes dessa decisão significa pagar o mesmo design 2-3× e criar drift entre as cópias.** Por isso a Onda 0 é decisão, não código.

O dono formal disso é **`US-FORJA-006`** (`memory/requisitos/Forja/SPEC.md`, `status: proposto`, `owner: [W]`), cujo DoD já exige: *"[W] decide qual implementação sobrevive"* + *"a perdedora é **removida**"* + `SCOPE.md` §cockpit atualizado + charters/casos reconciliados no mesmo PR.

## 6 · Estado por camada — rode as portas vivas, não confie nesta tabela

Medido em **2026-08-18** com `npm run screen-coverage:report` e `npm run casos:report`:

| grupo | telas | charter | casos.md | E2E | scorecard | VRT |
|---|---|---|---|---|---|---|
| `Forja` | 12 | 12 | 6 | 0 | 9 | 0 |
| `team-mcp` | 5 | 5 | 2 | 0 | 0 | 0 |

Com `casos.md` hoje: `Aprovacoes` · `Board` · `Inbox` · `Roadmap/Gantt` · `Trabalho` · `Triage` · `Cockpit` · `Scorecard`. Sem: `Activity` · `Backlog` · `Burndown` · `MyWork` · `Roadmap/Index` · `Tasks` · `Team` · `CcSessions`.

⛔ **Zerar essa coluna em lote é proibido**, e o dono já escreveu por quê: `US-FORJA-008` — *"Big-bang é proibido… `casos.md` com UC sem teste **quebra o `casos-gate` G-2**, bloqueando o merge de quem for atender a US"*. O caminho é **oportunístico**: o `casos.md` nasce **só na tela que a onda tocar**, no mesmo PR, com ≥1 teste citando cada UC, e o UC **derivado do contrato** (SDD/charter/SPEC) — nunca lido do `.tsx`.

## 6-bis · Primeira medição de fidelidade da área — topnav do Cockpit (2026-09-01)

> O bloco **"Limite deste documento"** no topo dizia que **nenhuma** medição de fidelidade tinha
> rodado nesta área. Rodou hoje, em **um** eixo: o topnav do hub (`ForjaHub.tsx`, compartilhado
> pelas 6 rotas do Cockpit). Isto **não** fecha o §9.5 — o `--compare` do espelho segue órfão; o
> que rodou foi a **sonda nos dois renders**, que é a outra metade do que aquele bloco exigia.

**Como reproduzir.** Protótipo: servir `prototipo-ui/cowork` por HTTP estático (o
`.claude/launch.json` já tem entradas prontas apontando pro espelho — não crie mais uma pro seu
worktree, ela vira caminho morto quando ele sair) → abrir `oimpresso.com.html` →
`localStorage["oimpresso.route"]="teammcp"` (a chave de rota do shell; `"forja"` **não** é
o valor — `app.jsx` casa `projects`/`teammcp`) → esperar
`window.__oiLazyDone` **e** duas leituras iguais de `document.querySelectorAll('*').length`
antes de medir (997/997 — §5 2026-08-24, não medir durante o lazy-load). Produção:
`https://oimpresso.com/forja` autenticado, mesma espera (722/722). Sonda: soma das larguras dos
filhos do nav + `gap × (n−1)`, e `getComputedStyle` no rótulo de grupo — **o que o browser
resolveu**, nunca a classe declarada (§5 2026-07-16).

| campo | protótipo | produção | veredito |
|---|---|---|---|
| destinos no topnav | 6 | 13 | DIVERGE (a classificar) |
| largura do conteúdo do nav | 784,4px | 1447,6px | — |
| cabe em 972px (1280 − sidebar 260 − padding 48)? | sim, sobra 188 | **não, falta 476** | DIVERGE (bug) |
| contêiner do grupo | pílula `.fj-navgroup` — `bg oklch(0.23 0.006 240)` · borda 1px `oklch(0.34 0.008 240)` · `radius 8px` · `padding 2px` · `gap 2px` | sem contêiner; divisor de 1px entre grupos | DIVERGE (a classificar) |
| `letter-spacing` do rótulo de grupo | **+0,665px** (`.07em`) | **−0,2375px** (`tracking-tight`) | DIVERGE (bug) |
| rótulo visível a 1427px | sim (`display:block`) | não (`hidden … 2xl:inline`, só ≥1536) | DIVERGE — consequência da linha acima |

**O que estes números mudam na ordem das ondas.** A pílula e o rótulo sempre-visível do protótipo
só cabem **porque lá são 6 destinos**. Aplicá-los sobre os 13 de hoje agravaria um nav que já
estoura 476px a 1280. No eixo topnav, portanto, **reduzir precede vestir** — e reduzir **é** a
Onda 0 (§5 + §9.2), não um passo de CSS da Onda 5. Quem tentar "só deixar parecido com o
protótipo" antes da Onda 0 vai piorar a barra.

**Onde cada destino removido vai parar** — medido clicando as 6 views do protótipo servido, não
inferido do `.jsx`:

| destino que sai do topo | receptor no protótipo |
|---|---|
| Backlog · Quadro · Tarefas | segmento de **Trabalho** — a linha de migração do `localStorage` já colapsa as três (§3) |
| Triagem | tipo `Proposta` dentro de **Aprovações** |
| Handoffs · Equipe | seções de **MCP**: `HANDOFFS F1→F3 · COWORK · CODE` (chips todas/pendente/aplicado/mergeado/bloqueado/parado) + `CONTRATO DE FERRAMENTAS` + `TOKENS ATIVOS` |
| CC Sessions | segmento `Sessões` de **Changelog** (`Tudo · PRs · ADRs · Sessões · Ondas`) |
| Roadmap (Gantt) | **sem receptor no protótipo** — único dos 8 sem destino declarado; vira pergunta pro §9 |

## 7 · As ondas

**Invariantes de toda onda** (não repito em cada linha):

1. **1 onda = 1 tela = 1 PR**, ≤300 linhas (`commit-discipline`).
2. A âncora é **computada** (`ancora.mjs`), nunca escolhida no olho.
3. O "como" sai do painel — `node prototipo-ui/protocolo.config.mjs` — lido **no momento** da onda, incluindo o portão fail-closed que proíbe editar produto com grafo/preview incompleto.
4. `casos.md`/UC da tela tocada nascem **no mesmo PR**, com teste que cite o UC (§6).
5. Onda não mergeia sem **CI verde** e, por tocar `.tsx`, sem **smoke real com screenshot** pós-merge (R1 + `post-merge-ui-smoke-required`). Merge de `.tsx` é humano ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)).
6. Precedência quando os artefatos discordarem: **teste verde > casos > charter > SPEC**; corrigir o perdedor no mesmo PR.

| # | onda | tela | âncora | depende de | por que nesta posição |
|---|---|---|---|---|---|
| **0** | **Decidir quem sobrevive** — 0h de código | as 3-4 de §5 | — | — | Sem isso, 1-4 pagam o design 2-3×. É `US-FORJA-006`, decisão [W] |
| **1** | Mesa de Aprovações | `Forja/Aprovacoes/Index` | view `hoje` (`forja-aprova.jsx`) | — | **Única sem concorrente.** Já tem `casos.md`. Superfície da ADR 0368 e de `US-FORJA-010` (**p0**). Começa aqui porque não depende da Onda 0 |
| **2** | Trabalho · lista | `Forja/Trabalho/Index` (356 ln) | `trabalho`/`lista` | Onda 0 | Tem `casos.md` **e** `design-spec.json` — uma onda já passou por aqui; é o candidato declarado a vencedor |
| **3** | Trabalho · quadro | `_components/TrabalhoQuadro` (158 ln) | `trabalho`/`quadro` → `KanbanView` | Onda 0, Onda 2 | Onde mora o gap medido pela 0367: `E`/`A` por teclado, overlay `?`, filtros cycle/epic/owner. O hook `useBoardShortcuts` foi extraído justamente pra ser portado — fecha os 3 num movimento |
| **4** | Trabalho · Gantt | `Forja/Roadmap/Gantt` | `trabalho`/`gantt` → `GanttView` | Onda 0 | 0367 D7: o quarter view só sai **quando o Gantt provar que substitui**. Hoje o Gantt é despejo (531 linhas, barras fora da viewport) — a onda tem que domar volume, não só pintar |
| **5** | Cockpit — shell + changelog + MCP/handoffs | `team-mcp/Forja/Cockpit` (104 ln) + `_components` | views `changelog` · `mcp` | Onda 0 | 6 rotas, 1 tela: o shell é barato e o ganho é largo. Depois da 0 porque o que sobra de aba depende de quem morreu |
| **6** | Saúde → Scorecard | `team-mcp/Scorecard/Index` | view `saude` → `SaudeView` | — | Receptor datado (#4887): "Saúde" **é** o Scorecard. Independente das outras; pode ir em paralelo à 1 |
| — | **sem onda** | `integra` · `ForjaNotifs` | — | — | §4: alvo já cumprido / receptor morto pela 0367 D5 |
| — | **fora** | as 8 de §2.2 + `Roadmap/Index` | — | — | 0367 D1 manda deletar; D7 suspende o quarter |

**Ordem executável:** `0` → (`1` ‖ `6`) → `2` → `3` → `4` → `5`. A Onda 0 é a única que não é código; as duas em paralelo não se tocam.

## 8 · O que este manual NÃO autoriza

- **Não autoriza aplicar nas 8 telas de §2.2.** Nem "de leve", nem "já que estou aqui".
- **Não autoriza big-bang de `casos.md`/SDD** (§6, `US-FORJA-008`).
- **Não autoriza dizer que uma onda ficou "igual ao design".** Isso é medição (`design-diff`/`style-fingerprint` nos dois renders), nunca leitura — a comparação é o mecanismo, não o olho.
- **Não autoriza editar nada sob `prototipo-ui/cowork/`.** É espelho **read-only** ([ADR 0374](../../decisions/0374-emenda-0315-espelho-cowork-e-rota-prevista.md)); o durável nasce no Cowork vivo e desce pela máquina. Remendo à mão aqui some no próximo `--export-from`.
- **Não autoriza criar gate, índice ou máquina nova** pra vigiar estas ondas. Os donos existem: `casos-gate`, `screen-coverage`, `contrato-de-tela`, `design-code-map-check`.
- **Não fixa nota, %, nem prazo.** Onde há número, há data e comando ao lado.

## 9 · Decisões [W] pendentes que este manual não pode tomar

1. **Ratificar (ou não) a ADR 0367.** Ela está `proposto` com decisão datada. Enquanto não ratificada, as 8 telas seguem vivas em produção enquanto o manual as trata como mortas — divergência declarada, não silenciosa.
2. **`US-FORJA-006` — qual implementação sobrevive** por pergunta (backlog / quadro / triagem), e a perdedora **removida**. É a Onda 0; nada de 2-5 deveria começar antes.
3. **`US-TR-305`/`US-TR-306` (Inbox) — matar de fato ou reabrir?** A 0367 D5 as declarou perda consciente; o Daily Brief **ainda as lista em voo**. Enquanto isso não fecha, alguém pode entregar trabalho que a decisão manda deletar.
4. ~~**`integra` e `ForjaNotifs`** — confirmar que são fósseis (§4) ou declarar receptor.~~
   **`integra`: RESPONDIDA por [W] em 2026-09-01** — não é fóssil, é o esperado (§4, errata). Vira
   onda quando a Onda 0 destravar; a tela **não existe** em produção hoje (nenhum `.tsx` a serve),
   então é construção, não re-skin. **`ForjaNotifs` segue em aberto** — o receptor natural (`Inbox`)
   continua morto pela 0367 D5.
5. **Teto de fidelidade do `DesignSync.get_file`** — ⚠️ **CORRIGIDO 2026-08-27: a frase original dizia "arquivo grande" e estava INVERTIDA.** Ela não era refutável quando escrita (este doc é de 08-18; a fronteira foi medida em 08-20) — caducou. O medido (`protocolo.config.mjs:214-217`): conteúdo **acima de ~48 KB volta PERSISTIDO em disco** e é justamente o que desce fiel por `get_file → --export-from`; **abaixo do piso volta INLINE** e é o que não desce. O `#5757` é a mesa do arquivo **pequeno**, a metade oposta da citada. No corpus desta área, **1 de 6** `forja-*.jsx` é grande (`forja-page.jsx` 90.503 B); os outros cinco (7 KB–31 KB) é que ficam de fora da rota avulsa — e a rota que resolve os dois é o bundle v2. Prova por consequência: os 8 arquivos do Ponto desceram por essa rota em 08-20 (`SYNC_LOG.md:255`), incluindo `ponto-telas.jsx` de 66.169 B. **O bloqueio real desta área é outro, e é mais barato:** o `--compare` nunca rodou aqui — aborta com *"exige um snapshot.json existente"* (`:8`). É **medição órfã**, não teto.
6. **`Roadmap (Gantt)` tem receptor no protótipo?** Ele está no topnav de produção (13º destino) e
   **não aparece** nas 6 views do protótipo (§6-bis) — único dos 8 destinos absorvidos sem receptor
   medido. Ou o protótipo está atrás do produto neste eixo, ou o Gantt sai do topo junto com os
   outros 7. A Onda 4 depende dessa resposta.
7. ~~**A redução 13 → 6 do topnav**~~ — **DESTRAVADA por [W] em 2026-09-01**, textual:
   *"remova a proibição, estou mandando"*. A trava era a `US-FORJA-006` ("não remover antes de [W]
   decidir qual sobrevive"); [W] decidiu mandando o render do protótipo. **Executado em parte no
   mesmo dia: 13 → 9.** Saíram os **4 cuja absorção foi medida em produção** — `Backlog`→segmento
   Lista, `Quadro`→segmento Quadro, `Tarefas`→a lista já é o universo (`SEM FRENTE — 375`),
   `Roadmap (Gantt)`→o segmento Gantt **navega** pra `/forja/roadmap-gantt` (clicado e conferido).
   Rotas intactas; saiu o item do topo, não a tela.
   **Os 3 que faltam pra chegar em 6 dependem de construção, não de decisão** — e por isso não
   saíram: `Handoffs` e `Equipe` são seções do MCP no protótipo, mas o `/forja/mcp` de produção
   é **MOCKADO** e não tem nenhuma das duas; `CC Sessions` é o segmento `Sessões` do Changelog, e o
   Changelog de produção projeta só sessão sem título (parede de "Sessão Claude Code" idênticas);
   `Triagem` vira tipo `Proposta` em Aprovações, que abre **vazia** enquanto a Triagem tem 3
   tickets vivos. Removê-los agora encurtaria a barra perdendo produto.

## 10 · Como manter este documento

1. Número (contagem, cobertura, nota) só entra **com data + comando reexecutável**. Na dúvida, **re-rode** — não edite o número.
2. Estado de tela vem das portas vivas (`screen-coverage:report`, `casos:report`), nunca de `Glob`/olho.
3. Onda concluída: marque **aqui** com o PR e o recibo do smoke; o contrato da tela mora no `casos.md` dela, não nesta tabela.
4. Mudou a fronteira/proveniência: o dono é o `SCOPE.md`. Mudou requisito: `SPEC.md`/charter/casos. Este arquivo só reconcilia **ordem e veredito**.
5. Fases/comandos do protocolo: **nunca** copiar pra cá — apontar pro painel.

## 11 · Decisão [W] de 2026-09-02 e a META (o que "igual ao protótipo" significa em número)

**Decisão, textual:** *"pode fazer igual ao protótipo e revogar todo o resto (…) se tiver que apagar para refazer de novo, faça. Eu apenas quero que trace uma meta de conseguir fazer o mesmo layout. O resto não importa. Não uso ainda essa tela."* Isso responde de uma vez os itens 2, 4, 6 e 7 do §9 e a Onda 0 do §7: **o protótipo é a implementação que sobrevive**; as três por pergunta (§5) e as 8 telas de §2.2 são a perdedora e **saem**, não ficam mortas ao lado. O item 1 (ratificar a 0367) já aconteceu — ela está `aceito`.

**A meta é medida, não olhada** (skill `comparar-design-prod`; primeira rodada em [forja-cockpit-visual-comparison.md §2026-09-02](../TeamMcp/forja-cockpit-visual-comparison.md)):

| eixo | alvo | como se prova |
|---|---|---|
| views | as **6** do `forja-page.jsx` servidas pelo `Cockpit` em 6 rotas `/forja/*` (Aprovações é a landing) | `route:list --path=forja` + render de cada uma |
| topnav | **6** destinos em **3** grupos-pílula, **na linha do header**, cabendo a 1280 | sonda do §6-bis |
| fidelidade por view | `design-diff --compare --check` com **0 `DIVERGE(bug)`** em D2/D4/D6/D8, tema dark nos dois lados, roles iguais | 1 par por view, JSON no PR |
| rede | D1 parcial (marcador sobrevive ao clique) em toda ação de filtro/aba | `read_network_requests` |
| revogação | 0 rota `/project-mgmt/*`, 0 `.tsx` das 8 telas de §2.2, 0 componente `_components/Forja{Backlog,Quadro,Triage}` | `git grep` contado + `route:list` |
| vocabulário | as telas usam o bundle `cowork-forja-bundle.css` (classes `fj-`/`ap-`/`tf-`), zero utilitária Tailwind de cor/espaço no que o bundle já cobre | `ds-guard` + `conformance-gate` |

**Ondas (1 PR cada, ≤300 linhas de prosa; CSS/JSX copiado de máquina não conta):**

| # | onda | fecha com |
|---|---|---|
| 0 | esta decisão registrada (SPEC US-FORJA-006 + este §) | merge |
| 1 | `cowork-forja-bundle.css` inteiro no chão + tokens `--dev*` na fundação | gates CSS verdes |
| 2 | shell: header com topnav inline 6/3 grupos, 6 rotas, `Cockpit` roteando por view | sonda do topnav = protótipo — **✅ [#6553](https://github.com/wagnerra23/oimpresso.com/pull/6553)** (merge `e1412acef3`, deploy 2026-09-02 16:00Z): 6/6 · 3/3 · mesma linha · pílula/rótulo idênticos; a sonda pegou **3 DIVERGE** (line-height do preflight, padding copiado do `@media`, `--accent` dark 0,55×0,70) → **Onda 2.1 ✅ [#6563](https://github.com/wagnerra23/oimpresso.com/pull/6563)** (deploy `a91ce0cd5c`): os 3 re-medidos em prod = protótipo (88,4px · 25px · `oklch(0.7 …)`); recibo em [forja-cockpit-visual-comparison.md §2026-09-02 tarde](../TeamMcp/forja-cockpit-visual-comparison.md). Ressalva medida: a 1280 o **shell** do protótipo vira rail 56px e o header quebra em 3 linhas — prod só faz rail por toggle; é fundação, não Forja |
| 3 | Aprovações (view `hoje`) | compare 0 bug — **código aplicado, medição pendente**: a tela virou o markup do [`forja-aprova.jsx`](../../../prototipo-ui/cowork/forja-aprova.jsx) (herói + faixa "Ao vivo no MCP" + mesa + placar), com backend novo pros 2 itens que o [W] pediu em 2026-08-08 e estavam sem fonte. **Achado:** a âncora do charter apontava o `forja-page.jsx`, que só MONTA a view — o markup mora no `forja-aprova.jsx`; corrigido. 3 divergências declaradas (verbos do FSM, dono da caixa de nota, os 4 tipos) e 2 colunas do placar sem fonte, que mostram "—" em vez de número inventado. Gates locais verdes; a11y da fila **melhorou** vs o protótipo. Falta deploy + sonda nos dois lados — recibo em [forja-cockpit-visual-comparison.md §2026-09-02 noite](../TeamMcp/forja-cockpit-visual-comparison.md) |
| 4 | Trabalho · lista | compare 0 bug — **🟡 [#6577](https://github.com/wagnerra23/oimpresso.com/pull/6577) aberto, aguardando merge [W]**: as 3 barras de filtro, a `fj-row` densa, o KPI que FILTRA (`BUTTON`, valor 17px) e o `--accent` dark 0,70. ALVO medido no protótipo antes de codar (dark, `__oiLazyDone` + 2 leituras iguais — recibo em [forja-cockpit-visual-comparison.md §2026-09-02 noite](../TeamMcp/forja-cockpit-visual-comparison.md)): filtro **3** linhas · KPI **4/BUTTON/17px/left** · `.fj-row` **13** filhos. A réplica entrega **11 de 13** — os 2 ausentes são DECLARADOS (`fj-rowcheck`, que exige mutação em massa sem endpoint; `fj-fresco`, campo que `mcp_tasks` não tem). **A sonda pareada ainda NÃO rodou** — ela exige o deploy, e nada foi declarado "0 bug" antes dela |
| 5 | Trabalho · quadro (2 eixos) | compare 0 bug |
| 6 | Trabalho · gantt | **✅ PARCIAL [#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624)** (merge `20875e152`, deploy `cb38ae2af` 13:27Z) — os 2 elementos FORA do motor entregues e medidos em prod autenticado dark: `.fj-quadro-ancora` (1 · 12px · `oklch(0.58 0.005 90)` = `--text-mute`, batendo o bundle) + `.fj-totalbar.fj-g-foot` (1 · `display:flex` · 3 legendas) · recibo em [forja-cockpit-visual-comparison §2026-09-03](../TeamMcp/forja-cockpit-visual-comparison.md). ⚠️ O CORPO do gantt **não** virou réplica, e é decisão [W] em aberto: o protótipo desenha `.fj-g-*` à mão e a tela usa `@svar-ui/react-gantt`; trocar o motor custaria as **163** dependências que hoje viram setas (Goal do charter) pra melhorar uma linha do tempo com **7** prazos reais em 1186 tasks (medido em prod 2026-09-03). O smoke também derrubou uma previsão minha: o contador de vencidas mostra **5**, não 0 |
| 7 | Saúde | compare 0 bug |
| 8 | MCP + Handoffs dentro | compare 0 bug — **🧪 código no ar em PR** (2026-09-02): a view virou réplica (`fj-mcp*`/`fj-perm*`/`fj-token*`/`fj-audit*`/`fj-ho-*`) na ordem do protótipo (intro `mockado` → **Handoffs F1→F3** → grid [contrato \| tokens] → auditoria), e o painel voltou pra dentro — mesmo componente que `/forja/handoffs` (rota viva), mesma projeção `ForjaMcpService`, `Inertia::defer` nos dois. Causa-raiz do D4 medida e corrigida: `.mono` é do **shell** do protótipo (`styles.css:1740`) e **não existe em produção** (0 ocorrências globais) — desceu escopada. Valores-alvo do lado design medidos em [forja-cockpit-visual-comparison.md §Onda 8](../TeamMcp/forja-cockpit-visual-comparison.md). **O `compare 0 bug` NÃO está fechado**: exige prod deployada, e merge de `.tsx` é humano ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)) |
| 9 | Changelog | compare 0 bug — **réplica aplicada** (PR desta onda, 2026-09-02): a linha virou a do `ChangelogFeed` (**2** células, dot + corpo em 3 blocos) contra as **5** colunas achatadas que a medição da manhã pegou, e a parede de `"Sessão Claude Code"` acabou (o título cai em `summary_auto` → 1º prompt de `mcp_cc_messages` → **vazio honesto**). `flags`/`modules` passam a vir de coluna real (`tags` ∩ {`tier-0`,`breaking`} · `module`). Zero CSS novo. **O `compare --check` segue PENDENTE** — precisa do deploy, e o merge de `.tsx` é humano ([ADR 0283](../../decisions/0283-handoff-loop-zero-paste.md)); o alvo do protótipo e a estrutura da réplica já estão medidos em [forja-cockpit-visual-comparison.md §2026-09-02 (Onda 9)](../TeamMcp/forja-cockpit-visual-comparison.md), com o comando do pós-deploy escrito lá |
| 10 | Integrador | compare 0 bug — **réplica aplicada** ([#6620](https://github.com/wagnerra23/oimpresso.com/pull/6620)): as abas deixaram o `.fj-int-tabs` com `<button>` e passaram ao TabBar do DS, portado em `ForjaTabBar.tsx`. **Tipografia/gap medida em 2026-09-03** (o eixo que o §7 do export declarava NÃO medido): **22 de 29** seletores da seção com corpo idêntico ao `forja-page.css`, **6** divergindo só por token inlinado (o MESMO px), **1** reescrita com efeito equivalente (`.fj-int-tab` por `!important`) e **0** ausente. O `className="fj-int-tabs"` que a réplica não escreve está **provado** inerte, não afirmado: o `TabBar` do `_ds_bundle.js` desestrutura só `{tabs, active, onChange}` e descarta `className`/`ariaLabel`/`inset` — o que também torna o `pad={0}` do protótipo equivalente ao `NAV` sem `paddingInline`. **O `compare --check` segue PENDENTE** (prod pede auth: `302 → /login`). Recibo em [§2026-09-03 (Onda 10 · fecho)](../TeamMcp/forja-cockpit-visual-comparison.md) |
| 11 | revogação: `/project-mgmt/*`, duplicatas, rotas, testes, `SCOPE §cockpit` | **✅ PARCIAL — 7 das 8 telas** — [#6617](https://github.com/wagnerra23/oimpresso.com/pull/6617) (merge `e2c8397031`, 2026-09-03 12:38Z). Smoke em prod no §11.2. Ver §11.1 pro que ficou |

**Ressalva que continua valendo:** o segmentado Lista|Quadro|Gantt do protótipo depende do `Segmented` do DS, que o snapshot local (pacote de 24/08) não publica. Em produção ele existe (`Components/ui`), então a onda 4 não fica bloqueada — o que fica cego é a **medição local** dessa peça até o Cowork regerar o pacote.

### 11.1 · Onda 11 executada em PARTE (2026-09-02) — o que saiu, o que ficou e por quê

A onda foi pedida "depois das Ondas 3-10". **Medido em `origin/main`: as Ondas 3-10
não existem** — 0 commit, 0 PR aberto (mergearam 0 · 1 · 2 · 2.1). A revogação
correu só onde o receptor está **no ar e medido**; o resto fica declarado, não
esquecido.

**Saíram (7 telas + controllers + rotas + navegação + testes):**

| tela | receptor | como foi medido |
|---|---|---|
| `Backlog/Index` | `/forja/trabalho?visao=lista` | `SCOPE.md:13` — "funde os TRÊS backlogs" |
| `Board/Index` (+`DetailSheet`) | `/forja/trabalho?visao=quadro` | `TrabalhoController:95` allowlist da `visao` |
| `Triage/Index` | aba `/forja` | ADR 0367 **D6**: "morre a tela, fica a aba" |
| `MyWork` · `Inbox` · `Burndown` | — | ADR 0367 **D5**: perda consciente, custo aceito |
| `Activity` | — | ADR 0367 **D1** |

**FICOU `Roadmap/Index`** (o 8º). A **D7** condiciona a saída a *"o Gantt provar que
substitui (filtro por cycle efetivo + volume domado)"*.

⚠️ **Atualizado 2026-09-03: a Onda 6 RODOU ([#6624](https://github.com/wagnerra23/oimpresso.com/pull/6624)) e a D7 continua NÃO satisfeita.** Medido: o #6624 tocou **só o frontend** (`Gantt.tsx` + charter + casos, 58 linhas) — âncora e barra de totais, fidelidade visual. O `RoadmapGanttController` não mudou, e ele **já tinha** as duas peças que a D7 pede (`MAX_TASKS = 500` e filtro por cycle). Ou seja: a condição da D7 nunca dependeu da Onda 6. O que decide é o que o próprio `SCOPE.md` registra sobre os dois — *"nenhuma responde a pergunta da outra (o quarter não tem due_date/blocked_by, o Gantt não tem epic_id)"*. Enquanto isso valer, o Gantt **não substitui** o quarter view, e a saída dele é decisão [W], não consequência de uma onda.
Revogá-la seria eu sobrepor ADR aceita — decisão [W], não minha.

**FICARAM os 3 `_components/Forja{Backlog,Quadro,Triage}`**, que o §11 também lista.
`Cockpit.tsx:19-21` **importa os três**, e `ForjaTriage` serve `/forja` — a landing
do módulo e o alvo do botão primário "Novo issue" (`ForjaHub.tsx:136`). Apagar hoje
derruba a landing. Destrava na **Onda 3**, que faz Aprovações virar a landing.

**Dois achados que o §11 não previa** — e o primeiro era um buraco real:

1. **A navegação da área tem TRÊS superfícies, não uma.** O §11 listava "rotas,
   testes e SCOPE". Faltava `Modules/Forja/Resources/menus/topnav.php` — a superfície
   **viva** (`LegacyMenuAdapter` → `shell.topnavs.Forja`), que tinha **8 itens, todos
   apontando pras telas revogadas**. Sem tocá-la, as telas morriam e o menu seguia
   oferecendo-as. A 3ª superfície é o `DataController::modifyAdminMenu()`, onde um
   `return` **incondicional** na linha 116 já tornava tudo abaixo código morto.
2. **Um bug vivo, achado pelo caminho.** `SearchController` devolvia `url` de
   resultado do ⌘K apontando pra `/project-mgmt/board?project=X`. O 301 teria
   **descartado o `?project=`**. Reapontado pros filtros que o receptor de fato
   aceita (`q`, `cycle`); `project` não existe lá por decisão [W], então o resultado
   de Projeto vai pra lista inteira — perda declarada.

**Placar honesto contra a meta do §11** (a meta pedia `git grep` = 0):

| alvo | antes | depois | por quê não zerou |
|---|---|---|---|
| rotas nomeadas `project-mgmt.*` | 32 | **5** | 4 são `install.*`, **obrigatórias** pela ADR 0024 (sem elas o botão Install fica sem ação); 1 é o `roadmap` da D7 |
| `.tsx` das 8 telas de §2.2 | 8 | **1** | `Roadmap/Index`, pela D7 |
| `_components/Forja{Backlog,Quadro,Triage}` | 3 | **3** | deps vivas do `Cockpit` — Onda 3 |

Os caminhos revogados viram **301 sem nome de rota**: medi **113 citações** de
`/project-mgmt/*` em `memory/**`, e o time que entra pelo MCP segue esses links.
Rota morta não volta pelo nome; caminho velho continua levando a algum lugar.

### 11.2 · Recibo do smoke em produção (2026-09-03, pós-deploy do `e2c8397031`)

O Infra Contract do PR previu um flip falsificável: **antes**, as 8 rotas afetadas
respondiam `302 → /login` (medido em 2026-09-03 pré-merge); **depois**, só as 7 revogadas
deveriam virar `301` pro receptor, com os controles intactos. Medido em prod:

| rota | antes | depois | veredito |
|---|---|---|---|
| `/project-mgmt/board` | 302 | **301** → `/forja/trabalho?visao=quadro` | ✅ |
| `/project-mgmt/backlog` | 302 | **301** → `/forja/trabalho?visao=lista` | ✅ |
| `/project-mgmt/triage` | 302 | **301** → `/forja` | ✅ |
| `/project-mgmt/my-work` | 302 | **301** → `/forja/trabalho` | ✅ |
| `/project-mgmt/inbox` | 302 | **301** → `/forja` | ✅ |
| `/project-mgmt/activity` | 302 | **301** → `/forja/changelog` | ✅ |
| `/project-mgmt/burndown` | 302 | **301** → `/forja` | ✅ |
| `/project-mgmt` (raiz) | — | **301** → `/forja` | ✅ |
| `/project-mgmt/roadmap` (D7) | 302 | **302** → `/login` | ✅ controle |
| `/forja/trabalho` | 302 | **302** → `/login` | ✅ controle |
| `/forja/aprovacoes` | 302 | **302** → `/login` | ✅ controle |

Duas verificações além da tabela:

- **A busca global mudou de prefixo e sobreviveu.** `/forja/search` responde `302 → /login`
  (existe, atrás do auth) e `/project-mgmt/search` responde **404** — correto: ela nunca foi
  tela, é o endpoint que o `CommandPalette.tsx` consome, e o consumidor foi reapontado no
  MESMO PR. Não deixei 301 nela de propósito (endpoint de API, não caminho que humano digita).
  Resíduo honesto: numa janela curta pós-deploy, um browser com bundle JS em cache ainda
  chama o path velho e recebe 404 — o ⌘K volta ao normal no primeiro reload.
- **A cadeia resolve, não só o primeiro hop.** `curl -L` em `/project-mgmt/board` termina em
  `200` após **2 hops** (`301` → receptor, `302` → `/login`) — o 301 aponta pra rota viva,
  não pra outro caminho morto.

**O que este recibo NÃO prova:** que as telas receptoras *renderizam certo* — todo controle
parou no `/login`, porque o smoke rodou sem sessão. Fidelidade visual das views é das Ondas
3-10 e se mede por `design-diff`, não por status HTTP.

