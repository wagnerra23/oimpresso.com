---
slug: 2026-07-23-sentinelas-staleness-prontidao-required
title: "Sentinelas de staleness/drift — prontidão pra required (ranking por evidência; recomendação = promover NENHUMA agora)"
type: proposal
status: proposto
authority: proposal
lifecycle: ativo
kind: analysis
decided_by: [W]
proposed_by: [CC]
proposed_at: "2026-07-23"
module: governance
tags: [governance, gates, ci, required, advisory, staleness, drift, sentinela, promocao, anti-teatro]
related:
  - 0314-poda-gates-onda-2-lei-fusoes
  - 0336-gates-design-promocao-por-mordida-provada-emenda-0314
  - 0327-anchor-content-required-emenda-0314
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
pii: false
---

# Sentinelas de staleness/drift — prontidão pra required (item 3 da grade anti-apodrecimento 2026-07-23)

> **⚡ ADENDO (2026-07-23) — decisão [W] override soberano:** o proposal recomendava *promover
> nenhuma*. [W] leu e decidiu *"faça promover com teste agora"* (ADR 0238 / R10). Foi promovida a
> **única candidata require-safe zero-FP: `briefing-code-staleness --strict-coverage`** (a linha #1 da
> tabela — cobertura, âncora de existência, NÃO frescor). Registrada em
> **[ADR 0348](../0348-briefing-coverage-required-emenda-0314.md)** honestamente como *desvio
> consciente* da DR-2 da 0336 (bite-log 0), apoiada no teste de mordida M/N/O + zero-FP + verde no
> main. **O restante da análise abaixo segue valendo** — frescor continua advisory-por-lei; nada mais
> foi promovido.

> **Recomendação de cabeçalho: promover NENHUMA agora.** Nenhuma sentinela bate, hoje, os
> critérios de promoção (0314 default + 0336 mordida provada). Matar candidatas fracas é o
> resultado — e a análise abaixo mostra *por quê*, com número medido (não afirmado). Este doc
> NÃO implementa nada: flip de required é ato [W] (R10). Só prepara.

## A pergunta (contexto da grade)

O padrão vencedor de "docs que não apodrecem" (Swimm, 81/100) é **doc↔código com CI que MORDE**.
O oimpresso tem os SENSORES (7+ sentinelas de staleness) mas a maioria é **advisory** por lei
([ADR 0314](../0314-poda-gates-onda-2-lei-fusoes.md): required = só Tier-0). Item 3: **quais dessas
sentinelas medem um fato DERIVÁVEL do código/dado — determinístico, sem julgamento, sem
falso-positivo — e portanto poderiam legitimamente virar required?**

## O eixo que decide tudo: o que a sentinela ANCORA

Cada sentinela mede uma "derivada" (porta parada enquanto o alvo anda). O que separa require-safe
de teatro é **em que âncora a derivada se apoia**:

| Classe de âncora | Determinístico? | Gameável? | Atribuível ao PR? | Require-safe? |
|---|---|---|---|---|
| **Data DECLARADA** no doc (`updated_at:`/`last_updated:`; git-date só como *fallback*) | sim (compara datas) | **SIM** — o autor escreve a data | sim | **NÃO** |
| **Existência** (dir de módulo existe, arquivo-porta falta) | sim | não (existência de arquivo) | sim (diff-aware) | **sim (forma)** |
| **Hash de identidade** (`source_blob_sha` vs `git hash-object`) | sim | não (não dá pra falsear hash sem re-fazer) | sim | **sim (forma)** |
| **Asserção binária diff-aware** ("a `.tsx` mudou sem sinal de autorização no PR") | sim | parcial | sim | **sim (forma)** — com ressalvas |
| **Estado do mundo externo** (lista de required checks; canário parado) | **sim (âncora perfeita, não-gameável)** | não | **NÃO** — muda por causa fora do PR | **NÃO** (ver `protection-drift`) |

> A última linha é a lição mais fina da análise: **determinismo é necessário mas não suficiente**.
> `protection-drift` tem a âncora *menos* gameável de todas (timestamps literais da API + lista
> congelada em baseline), e ainda assim **não é require-safe** — porque o fato que ela mede não é
> *atribuível ao PR sob revisão* (vai 🔴 se um admin demover um check ou um canário parar >48h). Um
> gate de PR required só pode morder o que o PR causou.

A conclusão-chave: **quase toda a família de staleness ancora na DATA DECLARADA** — que é
auto-escrita. Isso é *exatamente* o `last_validated`/§-não-vazio que a proibicoes.md §5 já
rejeitou 2× (2026-07-01, 2026-07-09): **campo auto-declarado como catraca vira teatro** ("bumpar a
data sem refrescar o conteúdo passa verde"). **Os próprios scripts declaram isso no header** e se
recusam a virar required — não é opinião minha, é a doutrina que eles já carregam:

- `briefing-code-staleness.mjs` (G2): *"data declarada é auto-escrita… por isso é reporter, NUNCA
  catraca (§5 2026-07-09)"*.
- `agents-md-staleness.mjs` (G1): *"mede TEMPO, não VERDADE: fresco e errado sai verde… compra
  prazo, não corretude"*.
- `visual-comparison-staleness.mjs`: *"NÃO é required — ADR 0314… frescor é HIGIENE"*.

## Ranking por prontidão (medido em 2026-07-23 · `origin/main` do worktree)

> Recibo (§5 "fato derivado não se restateia"): rodei os reporters localmente hoje. Números abaixo
> são saída de tool, re-rodável — não afirmação atemporal. Sistema medido: os `.mjs` + git local.

| # | Sentinela | O que mede | Âncora | FP? | Sinal de custo REAL | Prontidão |
|---|---|---|---|---|---|---|
| 1 | **`briefing-code-staleness --strict-coverage`** (COBERTURA) | módulo backend existe sem `BRIEFING.md` | **existência** | **zero** | **0 gaps hoje** (51 aval., coverageGaps=[]) | 🟡 forma-OK, **mordida=0** |
| 2 | **`detect-ui-drift --strict`** | `.tsx` mudou sem sinal de autorização no PR | diff binário | **fricção alta** (bugfix legítimo flaga — TP por design, não FP) | bite-log **vazio (0)** | 🟡 forma-OK, **mordida=0 + paths-filter** |
| 3 | **`briefing-code-staleness --strict-scorecards`** | scorecard de função stale por `source_blob_sha` | **hash** | médio (edit legítimo) | 0 stale hoje | 🟠 nicho, custo baixo |
| 4 | `briefing-code-staleness --strict-legacy-briefings` | BRIEFING legado ≠ lápide válida | regex estrutura | baixo | 0 hoje | 🟠 nicho |
| 5 | **`ds-mirror-drift --enforce`** | token git diverge do snapshot do espelho | baseline auto-escrito | alto (git=SSOT, espelho atrasa por design) | 0336 já julgou **MANTER advisory** | 🔴 |
| 6 | `briefing-code-staleness` (frescor) | BRIEFING atrás do código | **data declarada** | alto | **7 stale hoje** | 🔴 gameável |
| 7 | `visual-comparison-staleness` | comparativo atrás da tela | **data declarada** | alto | **14/28 stale (50%)** | 🔴 gameável |
| 8 | `agents-md-staleness` | AGENTS.md atrás do CLAUDE.md | **data declarada** | alto | fresco hoje (5d) | 🔴 gameável |
| 9 | `doc-freshness-score` | RADAR score 0-100 por doc | agregador (score) | n/a | é *radar*, não *dente* | 🔴 nem é gate binário |
| 10 | `cowork-mirror-freshness` | espelho Cowork atrás do vivo | ledger auto-escrito | — | **não wirado em CI** (auth interativa) | 🔴 nem é gate |

## Por que CADA 🔴 morre (matar candidata fraca é resultado válido)

- **#6, #7, #8 (frescor por data declarada):** promover a required = catraca sobre campo
  auto-escrito = o `last_validated` banido em §5. **Prova numérica hoje:** o frescor tem **7
  BRIEFINGs stale** e o visual-comparison **14 de 28 (50%)**. Se qualquer um virasse required
  AGORA, bloquearia ~21 PRs/telas legítimos por dívida de HIGIENE — a catástrofe "tocar legado
  acorda o gate" (§5 2026-07-12) somada ao teatro do bump-vazio. Frescor é **advisory-por-lei
  permanente**; o valor é `::warning::` + o Daily Brief, nunca o merge.
- **#9 `doc-freshness-score`:** é agregador/radar (score 0-100 pra *ranquear*), o próprio header
  diz *"AGREGADOR ≠ DENTE… a AÇÃO vem do dente específico"*. Um threshold de score seria número
  arbitrário — não há "fato binário" pra morder.
- **#10 `cowork-mirror-freshness`:** o header é explícito: *"NÃO está wirado em CI… não declare
  isto 'gate'"*. Auth do DesignSync é interativa; o `--sla` lê um ledger auto-escrito
  (família `last_validated`). Não é candidata — é rotina de dispatch logado.
- **#5 `ds-mirror-drift`:** a [ADR 0336](../0336-gates-design-promocao-por-mordida-provada-emenda-0314.md)
  **já adjudicou "MANTER advisory"** (0/15 fails, 2d de idade no aceite). git é SSOT e o snapshot
  do espelho atrasa **por design** → um PR que muda token legitimamente ficaria bloqueado até
  alguém refrescar um snapshot que o CI **não consegue** refrescar (sem login claude.ai). Baseline
  via `--update-baseline` é auto-escrito. Required puniria o SSOT pelo atraso do espelho.

## As 2 candidatas de FORMA — e por que ainda NÃO promovem

As únicas com **âncora não-gameável** (existência / diff binário) são #1 e #2. Ambas passam no
teste de *forma* (o fato é derivável, determinístico). Mas **nenhuma bate a régua de promoção**:

### #1 `--strict-coverage` (módulo backend sem BRIEFING)
- ✅ **Determinística, zero-FP, não-gameável** — o próprio script diz *"isto é ENFORÇÁVEL (diferente
  do frescor): o sinal é a EXISTÊNCIA… mesma classe aceita do casos-gate G-1"*. É a forma
  require-safe (existência + diff-aware + grandfather), a MESMA que a [ADR 0341](../0341-memory-schema-charter-spec-required-emenda-0314.md)
  usou pra promover charter/spec (só as famílias em zero-violação).
- ❌ **Não-Tier-0** (higiene de doc, não dinheiro/PII/tenant/fiscal) → cai sob 0336, que exige
  **mordida provada (≥2)**.
- ❌ **Mordida = 0** (coverageGaps=[] hoje; nunca disparou porque cobertura já é 100%). Promover um
  gate que nunca mordeu, com superfície quase-parada, é o **anti-padrão foundation-ratchet /
  ADR 0346** que a §5 nomeia ("armar gate no zero-day").
- ❌ Nem sequer roda com `--strict-coverage` em nenhum workflow hoje — é flag opt-in local.
- **Veredito:** candidata de forma, **sinal de custo fraco**. Só faria sentido como *exceção
  soberana [W]* estilo memory-schema (custo-zero, não acorda legado, morde só módulo novo torto) —
  e mesmo assim é higiene, não Tier-0. **Não recomendo agora.**

### #2 `detect-ui-drift --strict` (mudança de UI não-declarada)
- ✅ **A asserção é binária e factual** — o script a marca como *"caminho de promoção honesto — a
  asserção 'mudou sem declaração' é verdadeira ou falsa, nunca teatro"*. É a candidata mais próxima
  de um "dente" real de design.
- ❌ **Não-Tier-0** → 0336 → **bite-log VAZIO (0 mordidas)**. Medido hoje:
  `design-gate-bites.mjs --tally` = *"ledger vazio — 0 mordidas"*. DR-2 exige **≥2**. Promover
  agora = flip-cego que a própria 0336 condena.
- ❌ **`paths:` filter no workflow** → **NÃO é require-safe como está**. Required com paths-filter
  fica "expected/pending" pra sempre nos PRs que não tocam o path (trava merge). Precisaria da
  conversão always-run + `dorny/paths-filter` skip-as-pass ANTES de virar required (padrão dos PRs
  #3444/#4301 no baseline).
- ⚠️ **Fricção alta (TP por design, não FP):** um bugfix de layout / refactor legítimo de `.tsx`
  **flaga** (o script lista "bugfix de layout, refactor" → FLAG). Não é *falso*-positivo — o gate
  QUER toda mudança declarada, então o flag é verdadeiro. Mas required forçaria SYNC_LOG/divergence
  a **cada** bugfix — fricção operacional de custo real que o time sentiria em todo PR de tela.
- ⚠️ **Cobertura incompleta v1:** `.tsx` sem charter irmão (ex.: `_components/`) vira nota advisory,
  não flag — vetor de drift que escapa (incompleto por construção).
- **Veredito:** é a **melhor candidata de longo prazo**, mas hoje falha em 3 dos itens da DR-3.
  O caminho honesto é **deixar o bite-log (já existe, DR-2a) acumular ≥2 mordidas reais**, converter
  o workflow pra always-run, e SÓ ENTÃO promover via 0336. **Não promover agora.**

## Sentinelas fora do recorte das 10 (inventário honesto — furo fechado pós-adversário)

`grep -l "staleness|drift|freshness|mirror" scripts/governance/*.mjs` retorna **13** fontes; a
tabela acima cobre 10. As 3 omitidas + por que ficam advisory:

- **`spec-lib-staleness.mjs`** (6º eixo de frescor — DOC de lib × versão no `composer.lock`):
  **mesma família gameável** do #6/#8 — reusa `declaredDoorDate`. Reforça a tese, não a quebra.
  Advisory-por-lei.
- **`knowledge-drift.mjs`** (staleness doc-vs-doc, git-date puro): é git puro (não gameável!), mas é
  **radar advisory por natureza** — *"toda nota aponta pra DESTILAR/FUNDIR/APAGAR"*, não é dente
  binário. Mesma classe do #9.
- **`mcp-drift-sentinel.mjs`** (SHA servido vs HEAD): sentinela **runtime agendada** (cron */30), não
  gate de PR. Fora de escopo.

E a mais importante — **`protection-drift.mjs`**, que eu tinha omitido e é a exceção mais
instrutiva:

- **Âncora perfeita, não-gameável:** o header crava *"DETERMINÍSTICO: timestamps são literais da
  API; 2 runs sem mudança no mundo = diff vazio"*. Congela a lista real em
  `required-checks-baseline.json`; pega demoção invisível de um required em 1 clique. **Sujeito
  Tier-0-adjacente** (integridade da própria lista de required + watchdog de canário >48h).
- **E MESMO ASSIM não é require-safe** — e o próprio workflow já sabe: `protection-drift.yml:16-19`
  = *"ADVISORY DE NASCENÇA… este workflow NUNCA entra no calendário de promoções a required"*.
  **Por quê:** vai 🔴 por causa **fora do PR** (admin demoveu um check; canário parou). Um gate de PR
  required não pode ser vermelho pelo que o autor do PR não causou. É **sensor-DE-Tier-0 agendado**,
  não gate-Tier-0. → confirma a 5ª linha da tabela de âncoras: **determinismo ≠ require-safe; falta
  a atribuibilidade ao PR.**

## Recomendação

1. **Promover NENHUMA sentinela a required nesta rodada.** Nenhuma bate 0314 (Tier-0) nem 0336
   (mordida ≥2 — bite-log medido vazio). Toda a família de frescor é **advisory-por-lei permanente**
   (âncora auto-declarada = teatro). Isto é o resultado esperado do item 3, não uma falha.
2. **Manter o eixo de frescor advisory + visível** — o valor real dele é `::warning::` no PR + Daily
   Brief ("charters apodrecendo"). Ele *compra prazo*, não corretude — e isso já é útil sem morder.
3. **Ação de maior ROI (sem promoção):** deixar o **bite-log DR-2a** (`design-gate-bites.jsonl`, já
   criado 2026-07-22) rodar — o ZELADOR coleta via `--scan`. `detect-ui-drift` é a única com âncora
   binária + custo plausível; quando o bite-log dela chegar a **≥2 PRs reais**, aí sim reabrir a
   promoção via 0336 DR-3 (desembrulhar exit code + converter paths→always-run + janela ≥14d + [W]).
4. **Se [W] quiser um gate require-safe de custo-zero JÁ** (estilo memory-schema 0341): a única
   forma-limpa é `--strict-coverage`. Mas é higiene não-Tier-0 com mordida 0 — seria **exceção
   soberana** registrada como desvio consciente (padrão 0346), não cumprimento de critério. **Não
   recomendo**; o valor marginal sobre o `::warning::` atual é ~nulo (cobertura já 100%).

## O que NÃO fazer (pra a próxima sessão não regredir)

- ❌ Não promover frescor (data declarada) a required — é o `last_validated` da §5. Os 7+14 stale
  de hoje são a prova de que required aí = bloqueio em massa de higiene.
- ❌ Não construir um "gate de score" sobre `doc-freshness-score` — threshold arbitrário, não fato
  binário.
- ❌ Não re-propor `ds-mirror-drift --enforce` a required — 0336 já disse MANTER advisory; git=SSOT
  vs espelho-que-atrasa = FP por design.
- ❌ Não promover `--strict-coverage`/`detect-ui-drift` "porque a forma é limpa" sem mordida ≥2 —
  é o foundation-ratchet/0346 zero-day que a §5 nomeia.

## Estado da revisão adversarial (2026-07-23)

Rodei um adversário cético de contexto-zero (read-only, re-mediu os reporters + git/gh) com o
mandato de **refutar** cada veredito — inclusive o "promover nenhuma". Resultado:

- **Tese central SOBREVIVE.** O adversário re-mediu e confirmou: `coverageGaps=[]`, bite-log
  *"ledger vazio — 0 mordidas"*, frescor ancora em data declarada preferida
  (`briefing-code-staleness.mjs:211-213`), `ds-mirror-drift` já adjudicado MANTER advisory (0336).
  Nenhuma sentinela de doc-staleness é Tier-0; nenhuma tem mordida ≥2. **Promover nenhuma resiste.**
- **REFUTA PARCIAL (furo de rigor, não de conclusão):** o inventário estava incompleto — faltavam
  `protection-drift`, `spec-lib-staleness`, `knowledge-drift`, `mcp-drift-sentinel` (13 fontes, não
  10). O furo real era `protection-drift` (âncora não-gameável + Tier-0-adjacente). **Corrigido**
  na seção "Sentinelas fora do recorte" + na 5ª linha da tabela de âncoras — e o caso na verdade
  *fortalece* a tese (determinismo ≠ require-safe).
- **2 imprecisões corrigidas:** (a) "FP real" de `detect-ui-drift` → "fricção alta / TP por design"
  (o gate quer o flag; não é falso); (b) a âncora de frescor é *híbrida* (declarada-com-fallback-
  git), não pura — nuançado na tabela.
- **Não-contradições confirmadas:** rec #4 (`--strict-coverage` como escape soberano) é o padrão de
  override [W]/ADR 0238 já usado na 0346, não auto-contradição.

Veredito global do adversário, textual: *"a recomendação 'promover nenhuma' SOBREVIVE… o doc chega
no lugar certo; só precisa mostrar o trabalho sobre as 3-4 sentinelas que omitiu"* — feito nesta
revisão.
