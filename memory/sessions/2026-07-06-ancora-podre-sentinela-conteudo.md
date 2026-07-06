---
date: "2026-07-06"
topic: "Âncora de design podre (2/9) pega por Wagner no instinto — sentinela de conteúdo fecha o buraco proveniência-vs-correção"
authors: [W, C]
related_adrs:
  - 0264-governanca-executavel-trio-dominio-e2e
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Âncora podre + sentinela de conteúdo (2026-07-06)

> **TL;DR:** trabalhando na tela `Financeiro/Unificado`, Wagner desconfiou da âncora de
> design ("pode estar ligado errado") e mandou conferir. Conferido: **2 de 9 âncoras da
> frota estavam podres e nenhum gate tinha pegado** — Unificado apontava pro *shell* do app
> (`oimpresso.com.html`, título "Chat", 0 conteúdo da tela) e Fluxo pra um arquivo que
> **sumiu** (`Financeiro.html`). A máquina não estava com a lógica errada — estava **cega**:
> o `ancora.mjs` + hook `block-ancora-no-olho` provam PROVENIÊNCIA (âncora declarada no
> charter), nunca CORREÇÃO (o arquivo bate com a tela). Quem pegou foi o instinto humano,
> não a máquina. Fix: 2 âncoras corrigidas + sentinela `anchor-content-check` que abre a
> âncora. "Presença ≠ correção" (L-24) aplicado a design.

## O buraco, com número

| | |
|---|---|
| charters com âncora real | 9 |
| **podres, não detectadas** | **2** (`Unificado`→shell · `Fluxo`→arquivo-fantasma) |
| saudáveis | 4 |
| prosa não-resolvível a arquivo | 3 |

- `Financeiro/Unificado`: `related_prototype: oimpresso.com.html` — 146 linhas, `<title>Oimpresso
  ERP — Chat</title>`, linka 27 CSS de todas as telas. É o SHELL/índice do app, não a tela. O
  design real (`financeiro-page.jsx`, 116KB, 59× "lente", "visão unificada", "saldo previsto") já
  estava nomeado no `bundle_source` do MESMO charter e citado no corpo — a âncora que
  `ancora.mjs` lê (`related_prototype`) discordava dos dois.
- `Financeiro/Fluxo`: `related_prototype: Financeiro.html` — arquivo **não existe** (sumiu no
  refactor SSOT Cowork #3259/#3528). A máquina "resolvia" âncora pra um fantasma.

## Por que a máquina não pegou (a lição)

O `ancora.mjs` e o hook `block-ancora-no-olho.mjs` (que EXISTE, não foi retirado) resolvem a
questão *"a âncora veio do charter, não de um print no olho?"* — proveniência. NENHUM abre o
arquivo pra perguntar *"esse arquivo é mesmo o design DESTA tela?"* — correção. A própria
proibição do anchor-guard já confessava o limite: *"a confiança termina no charter, sem
oráculo formal acima"*. O charter driftou (refactor) e o oráculo humano (Wagner) foi o único
detector. É o mesmo padrão "presença ≠ correção" que o adversário de arquitetura catalogou
horas antes no mesmo dia.

## Fix

1. **Dados corrigidos** (âncora → fonte real da tela):
   - `Unificado` → `financeiro-page.jsx`
   - `Fluxo` → `financeiro-telas-extras.jsx` (contém `TelaFluxo`)
2. **`scripts/governance/anchor-content-check.mjs`** — sentinela determinístico que abre cada
   âncora e classifica: `MISSING` (sumiu) · `SHELL` (.html com ≥10 stylesheets = índice do app,
   não tela) · `NO-MODULE` (0 menção do módulo) · `OK`. `--check` sai 1 em MISSING/SHELL.
   +17 checks de contrato no selftest (incl. counterfactual "shell com módulo presente AINDA é
   shell"). Ligado no `design-memory-gate.yml` (advisory, lei 0314) ao lado do `ancora selftest`.
   Pós-fix: **0 podre, 6 ok**.

## Limite honesto (o que o sentinela ainda NÃO cobre)

- 3 âncoras são **prosa** sem caminho de arquivo resolvível (ex: "prototipo Cowork
  'payment-gateway-ui'") — o sentinela as pula. Poderiam ser normalizadas pra caminho, mas
  hoje escapam da checagem.
- `NO-MODULE` é warn (não bloqueia) — nome de módulo pode não aparecer no protótipo por
  legítima diferença de nomenclatura; é sinal, não veredito.
- A confiança final continua no charter: se o charter apontar pro arquivo de OUTRA tela do
  mesmo módulo (financeiro→financeiro), o sentinela não distingue. Oráculo perfeito de
  "esse arquivo é o design DESTA tela específica" não existe sem custo — o `bundle_source`
  ajuda mas não é enforçado igual.

## Meta-lição (pro dia inteiro)

Wagner de manhã: "essa é a melhor estrutura?". Resposta do dia: a estrutura não é perfeita, é
a que acha os defeitos rápido. Aqui o detector foi HUMANO — a máquina tinha o buraco. O
sentinela transfere ESTE detector pra máquina (da próxima vez ela grita antes do instinto).
Mas registra o padrão: toda vez que um gate prova *presença/proveniência* e não
*correção/conteúdo*, existe um buraco esperando um refactor pra abrir.

---

## Evolução (mesma sessão, à tarde): sentinela de frescor RASCUNHADA e DEFERIDA após adversário

Rascunhei um `cowork-mirror-freshness.mjs` (compara `md5(repo)` vs `md5(vivo via DesignSync`) +
uma ADR 0324. **Provei o caminho vivo de verdade:** UUID do projeto ComVis achado no repo
(`019dcfd3-6ef2-7ee6-8512-b1b0e5544e58`), `get_project`/`list_files`/`get_file` funcionaram
(sessão logada), e `financeiro-page.jsx` vivo = repo = `ae3a2cfe…` → **o mirror está SYNC hoje**.

**Mas um passe adversarial (3 céticos) matou o mérito de mergear isso agora:**
- **2 bugs ALTA reais:** `buildManifest` colidia por basename (arquivos homônimos em subdirs com
  md5 diferente); e CRLF/EOL dava STALE falso (o próprio Gap 3 da ADR previu e o código não
  mitigou).
- **Teatro estrutural:** a checagem VIVA (`--compare`) **nunca roda em CI headless** (exige
  `/design-login`) — nem bug-fixado ela vira gate; é rotina de dispatch. Chamar de "sentinela de
  frescor" superdimensionava.
- **Quebrava governança:** o script (código que roda) citava ADRs `proposto` (0299/0314/0324) →
  memory-health **Check L** vermelho ("proposto vs realizado"); e a 0324 se declarava "advisory
  de nascença", a frase que a [ADR 0298](../decisions/0298-teto-de-governanca-anti-proliferacao-gates.md) bane.
- **Retrabalho:** a sessão paralela [W] já canonizou o essencial no [INDEX §0.2](../requisitos/_DesignSystem/INDEX-DESIGN-MEMORIAS.md) (2 projetos + integração viva + "diffar antes de concluir" + mirror em sincronia).

**Decisão (Wagner: "correção > aplicar"):** o `cowork-mirror-freshness.mjs`, o `.test.mjs` e a
ADR 0324 foram **RETIRADOS do PR** (ficam no histórico git p/ retomar). O PR ficou só com o que é
comprovadamente correto: **a limpeza dos 42 relatórios meta do espelho** (validada por 3 gates +
adversário: zero âncora quebrada). Se a sentinela voltar, é num PR próprio — com path completo (não
basename), EOL normalizado, e uma decisão explícita sobre a 0298 (dispatch/cron, não advisory-eterno).

**Intenção do Wagner PARKED (não perder):** ele disse verbatim *"vai apagar todas as copias dos
prototipos e deixar apenas a do link da api nova"* — migrar a fonte pro Cowork vivo, git deixa de
carregar cópia. Isso **contradiz o §0.2 vigente** (mirror é a fonte-espelho, em sincronia) e
depende da pendência ABERTA do §0.2 (*"antigo = arquivo defasado [refutado] vs direção de design a
redesenhar [aberto]"*). **Não vira ADR enquanto o Wagner não cravar redesign (1) vs (2).** Fica
registrado aqui pra a próxima sessão saber que a intenção existe e está esperando decisão.

**Lição perene:** provar o mecanismo (get_file → md5 → SYNC) ≠ o mecanismo valer merge. O
adversário separou "a lógica funciona" de "isto deve subir" — e o honesto foi subir só a limpeza.
