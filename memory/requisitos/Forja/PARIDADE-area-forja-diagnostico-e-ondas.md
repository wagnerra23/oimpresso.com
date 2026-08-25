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
4. **`integra` e `ForjaNotifs`** — confirmar que são fósseis (§4) ou declarar receptor.
5. **Teto de fidelidade do `DesignSync.get_file`** — sem ele resolvido, nenhuma onda consegue provar fidelidade visual de arquivo grande; a mesa está aberta em #5757.

## 10 · Como manter este documento

1. Número (contagem, cobertura, nota) só entra **com data + comando reexecutável**. Na dúvida, **re-rode** — não edite o número.
2. Estado de tela vem das portas vivas (`screen-coverage:report`, `casos:report`), nunca de `Glob`/olho.
3. Onda concluída: marque **aqui** com o PR e o recibo do smoke; o contrato da tela mora no `casos.md` dela, não nesta tabela.
4. Mudou a fronteira/proveniência: o dono é o `SCOPE.md`. Mudou requisito: `SPEC.md`/charter/casos. Este arquivo só reconcilia **ordem e veredito**.
5. Fases/comandos do protocolo: **nunca** copiar pra cá — apontar pro painel.
