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

## Evolução (mesma sessão, à tarde): sentinela de FRESCOR do espelho — [ADR 0324](../decisions/proposals/0324-sentinela-frescor-espelho-cowork-designsync-read.md)

O `anchor-content-check` fecha CORREÇÃO (âncora aponta pro arquivo certo do repo). Fica o
**ponto cego #2** do "Limite honesto" acima: *"protótipo de bubble velha"* — o arquivo existe,
tem conteúdo do módulo, PASSA no anchor-content, mas é **design ANTIGO** (a cópia do repo
driftou do vivo no Cowork). *"Só o olho humano ou um diff pega."* A integração **DesignSync**
(leitura, [ADR 0315](../decisions/0315-design-sync-claude-design-vs-cowork-charter.md)) agora
permite tirar o md5 do vivo — então dá pra fazer ESSE diff por máquina.

**`scripts/governance/cowork-mirror-freshness.mjs`** — compara `md5(repo)` vs `md5(vivo)` por
arquivo-âncora do espelho: `SYNC` / `STALE` (divergiu → re-exportar) / `LIVE-ABSENT` (não achado
no vivo) / `UNCHECKED` (agente não buscou). **Leitura pura, nunca escreve** — só GRITA "o espelho
divergiu", humano decide re-exportar (`nuvem → git` segue proibido, 0299/0315).

Split honesto (o node não fala MCP): a metade LOCAL (`--manifest` + md5 do repo, reusa
`anchorFile`) roda em qualquer lugar; a metade VIVA (`--compare`) é **rotina de dispatch por
agente** (chama `DesignSync.get_file`) — **não gate de PR**, porque `/design-login` não roda em
CI headless (0315 §Furos). SÓ o `--selftest` (26 checks de contrato) foi wirado no
`design-memory-gate.yml` advisory (lei 0314), ao lado do anchor-content.

**Provado nesta sessão:** selftest 26/26 · `--manifest` real = 3 arquivos (`financeiro-page.jsx`
`ae3a2cfe…` bate com a session da manhã) · caminho de LEITURA do DesignSync end-to-end
(`get_file` devolveu `Button.jsx` real) · selftests existentes intactos (anchor-content +
design-memory-gate verdes). **Pendente (Gap 1 da ADR):** o projeto vivo que espelha
`prototipo-ui/cowork/` (`019dcfd3…` "Oimpresso ERP Conunicação Visual") não aparece em
`list_projects` (filtra a graváveis) — a metade viva precisa do UUID pleno em runtime. O
mecanismo está provado; falta o mapa do projeto.

**Lição perene reforçada:** o dia teve TRÊS camadas de sentinela de design agora —
proveniência (`ancora.mjs`) → correção-estática (`anchor-content-check`) → **frescor-vs-vivo**
(`cowork-mirror-freshness`). Todo gate que prova uma camada e não a de cima deixa um buraco;
este fecha a de frescor do espelho.
