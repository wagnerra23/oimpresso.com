---
roadmap_item: P08
slug: conectar-metricas-gt-e-fixture-anchor
onda: 1
status: proposed
depende_de: []
destrava: [P13]
related_adrs: [275, 273, 279]
esforco_estimado: "0.5d codavel + IA-pair (sem janela de relogio real — fontes ja existem)"
---
# P08 · Conectar metricas GT-proprias (drift_alarms + backfill_error_rate) + fixture anchor-lint no gate-selftest

## Problema (o que esta quebrado, em 2-3 frases)
Duas metricas do stream GT do scorecard SDD declaram `not_yet_measured` mesmo tendo
fonte VIVA e funcionando hoje: `drift_alarms` (apesar de `protection-drift.mjs` rodar
live e emitir veredito) e `backfill_error_rate` (apesar do ledger ja ter 4 entries com
error 0%). Alem disso, a catraca `anchor-lint --check` NAO tem fixture de controle-negativo
no `gate-selftest.mjs` — entao o "morde" do anchor-lint so e provado por teste manual
ad-hoc, fora do sistema que vigia os vigias.

## Causa-raiz (evidencia VERIFICADA — file:line reais que confirmei)
- **drift_alarms hardcoded not_yet_measured** — `scripts/governance/sdd-scorecard.mjs:276-277`:
  `drift_alarms: notYet('down', 'advisory perene', 'protection-drift + watchdog de staleness (GT-G4)')`.
  A fonte EXISTE e roda: rodei `node scripts/governance/protection-drift.mjs --json` → exit 0,
  emite `{verdict, reds:[], warns, drift, watchdog}` (`scripts/governance/protection-drift.mjs:144-146`).
- **backfill_error_rate hardcoded not_yet_measured** — `scripts/governance/sdd-scorecard.mjs:278-279`:
  `backfill_error_rate: notYet('down', '<2%', 'ledger ... so existe apos 1o lote IA refutado')`.
  O ledger JA existe com 4 entries, todas `error_rate_pct: 0` e `veredito: aprovado`
  (`governance/sdd-verification-ledger.json:24-91`; PRs 2750, 2754, 2761, 2970).
- **anchor-lint fora do gate-selftest** — o array `CATRACAS` em
  `scripts/governance/gate-selftest.mjs:82-115` tem EXATAMENTE 5 catracas:
  `knowledge-drift` (L84), `foundation-ratchet` (L88), `ledger-check` (L96),
  `sdd-scorecard` (L106), `memory-health` (L110). NENHUMA delas e anchor-lint. Confirmado
  live: o selftest imprime "5 catracas x 2 fixtures" e passa 10/10. O modo `--check` do
  anchor-lint que deveria morder existe em `scripts/governance/anchor-lint.mjs:192`
  (`if (CHECK && (byState.anchored_dead > 0 || ...v1_violations > 0)) process.exit(1)`),
  mas nada no selftest exercita esse exit 1.

## Estado atual no repo (o que achei ao verificar agora)
- A evidencia do prompt bate 100% com o repo. Sem divergencias materiais. Notas finas:
  - O scorecard live mostra `drift_alarms=not_yet_measured` e `backfill_error_rate=not_yet_measured`
    (rodei `sdd-scorecard.mjs --json`), confirmando o gap.
  - O baseline `governance/sdd-scorecard-baseline.json` ja tem as DUAS chaves
    (`drift_alarms`/`backfill_error_rate`) presentes mas `value=null, armed=false, status=not_yet_measured`
    — entao virar `measured` exige tambem capturar baseline na 1a medicao real (ADR 0275 §3:
    so arma apos 3 medicoes validas; este PR so deixa MEDIDO+desarmado, nao armado).
  - `protection-drift.mjs` depende de `gh api` (rede + GITHUB_TOKEN). No CI do scorecard
    isso e aceitavel, mas `drift_alarms` precisa de fallback `not_yet_measured` honesto se
    `gh` falhar/ausente (espelhar o padrao `measureFullSuiteFloor` L117-139 — nunca "mente 0").
  - O ledger NAO expoe funcao agregadora; `ledger-check.mjs` valida 1 PR por vez. A leitura
    do error_rate agregado tem que ser feita direto do JSON (ler `entries[]`, computar
    max/media de `error_rate_pct`) — analogo a `measureFullSuiteFloor` lendo `nightly-floor.json`.
  - Fixtures vivem em `tests/governance-fixtures/<catraca>/{good,bad}/`; anchor-lint resolve
    paths contra `process.cwd()` (`anchor-lint.mjs:33,72,140`), entao a fixture tem que ser um
    SANDBOX por cwd (igual `knowledge-drift`), nao so um arquivo solto.

## Objetivo / DoD (criterio de pronto OBJETIVO e checavel)
1. `node scripts/governance/sdd-scorecard.mjs --json` mostra `drift_alarms.status == "measured"`
   (valor = nº de `reds` do protection-drift, ou contagem de alarmes) OU `not_yet_measured`
   com `source` explicando que `gh` falhou — NUNCA hardcoded.
2. Mesmo comando mostra `backfill_error_rate.status == "measured"` com `value` = error_rate
   agregado do ledger (hoje `0`), lido de `governance/sdd-verification-ledger.json`.
3. `node scripts/governance/gate-selftest.mjs` imprime "6 catracas x 2 fixtures" e passa 12/12,
   com a linha `anchor-lint  bad  exit 1` provando que o caso ruim MORDE.
4. Existe par de fixtures versionado `tests/governance-fixtures/anchor-lint/{good,bad}/` com
   SPEC sintetico (good = anchor para path existente na fixture; bad = anchor para path
   inexistente → `anchored_dead>0`).
5. README `tests/governance-fixtures/README.md` ganha a linha da catraca anchor-lint.

## Passos (ordenados, concretos)
1. **backfill_error_rate (mais simples, fonte 100% local):** em `sdd-scorecard.mjs`, criar
   `measureBackfillErrorRate(ledgerPath=governance/sdd-verification-ledger.json)` espelhando
   `measureFullSuiteFloor` (L117-139): se ausente/invalido/`entries` vazio → `notYet`; senao
   `measured`, `value` = `max(error_rate_pct)` das entries `aprovado` (ou media — decidir e
   documentar no `source`), `detail` = nº entries + ultimo PR. Trocar L278-279 pela chamada.
2. **drift_alarms:** criar `measureDriftAlarms()` que executa `protection-drift.mjs --json`
   via `execSync` (igual `measureKnowledgeDrift` L40-50). Em try/catch: sucesso → `measured`,
   `value` = `json.reds.length` (alarmes duros), `detail` = `{warns, verdict}`; falha (`gh`
   ausente/sem token) → `notYet(..., 'gh api indisponivel — drift_alarms requer GITHUB_TOKEN')`.
   Trocar L276-277 pela chamada.
3. **Fixture anchor-lint good:** `tests/governance-fixtures/anchor-lint/good/memory/requisitos/DemoAnchor/SPEC.md`
   com 1 heading `## US-DA-001` + linha `**Implementado em:** \`path/que/existe/na/fixture\` · verificado@abc1234 (2026-06-21)`.
   Criar o arquivo-alvo dentro da propria fixture pra `existsSync` passar (anchored_ok).
4. **Fixture anchor-lint bad:** mesma estrutura, mas o segmento-path aponta pra arquivo
   inexistente → classify() devolve `anchored_dead` → `--check` exit 1.
5. **Adicionar catraca ao gate-selftest:** no array `CATRACAS` (L82), inserir entry
   `{ id:'anchor-lint', run:(kind)=>runNode(script('anchor-lint',...), ['--check'], join(FIX,'anchor-lint',kind)), expect:{ good:/<msg ok>/, bad:/💀|anchored_dead|<acusacao>/ } }`.
   ATENCAO: anchor-lint no modo `--check` good imprime a tabela e exit 0; escolher um regex
   de OK robusto (ex.: `/ANCHOR COVERAGE GLOBAL/` que sempre sai) e um regex bad que case a
   linha 💀 de anchored_dead (`anchor-lint.mjs:183`). cwd da fixture = `join(FIX,'anchor-lint',kind)`.
6. **README:** adicionar linha na tabela de `tests/governance-fixtures/README.md` (apos L15).
7. **Selftest do selftest:** rodar `node scripts/governance/gate-selftest.mjs --only anchor-lint`
   e confirmar good=0 / bad=1. Rodar full → 12/12.
8. **NAO armar baseline neste PR** — deixar `drift_alarms`/`backfill_error_rate` como
   `measured, armed:false` (ADR 0275 §3 exige 3 medicoes consecutivas antes de armar; o
   armamento e item separado, P13 destrava disso).

## Arquivos a tocar (lista real)
- `scripts/governance/sdd-scorecard.mjs` (L276-279 + 2 funcoes novas)
- `scripts/governance/gate-selftest.mjs` (array CATRACAS L82-115 + contagem na linha de header L136)
- `tests/governance-fixtures/anchor-lint/good/**` (novo — SPEC + arquivo-alvo)
- `tests/governance-fixtures/anchor-lint/bad/**` (novo — SPEC com path morto)
- `tests/governance-fixtures/README.md` (tabela)
- (opcional, NAO neste PR) `governance/sdd-scorecard-baseline.json` — so quando armar (P13)

## Gate / counterfactual (COMO eu provo que o gate MORDE)
- **Prova 1 (anchor-lint morde):** com a catraca adicionada, neutralizar o exit 1 do
  anchor-lint numa copia temp e apontar via `--script anchor-lint=<copia>` (mecanismo ja
  existe, `gate-selftest.mjs:39-45`). O caso `bad` passa a sair 0 → selftest detecta
  "CATRACA PAROU DE MORDER" e da exit 1. Sem neutralizar, `gate-selftest.mjs --only anchor-lint`
  imprime `anchor-lint bad exit 1` e passa.
- **Prova 2 (drift_alarms conectado):** `git stash` da mudanca e rodar scorecard → campo
  `not_yet_measured`. Aplicar e rodar → `measured` com value numerico. O diff do JSON
  `governance/sdd-scorecard.json` muda de `"status":"not_yet_measured"` para `"status":"measured"`.
- **Prova 3 (backfill conectado):** editar uma entry do ledger pra `error_rate_pct: 5`
  (so localmente, nao commitar) e rodar scorecard → `backfill_error_rate.value` reflete 5
  (prova que LE o ledger, nao um literal).
- NAO e gate required ainda (advisory). O fechamento objetivo e o gate-selftest passar 12/12
  com a 6a catraca + scorecard mostrar 2 metricas a mais `measured`.

## Dependencias (e por que)
- `depende_de: []` — todas as fontes (`protection-drift.mjs`, ledger, `anchor-lint.mjs`,
  estrutura de fixtures) ja existem em main; nada bloqueia comecar hoje.
- `destrava: [P13]` — P13 (presumido: armar as metricas GT a required no calendario ADR 0275)
  so pode acontecer DEPOIS que `drift_alarms`/`backfill_error_rate` estiverem `measured` por
  3 medicoes consecutivas. Sem P08, P13 nao tem o que armar. **Nao localizei o doc de P13 no
  repo** (`memory/requisitos/_Governanca/roadmap/` so tem este arquivo) — a relacao destrava
  vem do roadmap do orquestrador, nao de doc verificavel; registrado como inferencia.

## Esforco (recalibrado ADR 0106)
- **Codavel com IA-pair (10x + margem 2x):** ~0.5 dia. Sao 2 funcoes pequenas espelhando
  `measureFullSuiteFloor` (padrao ja escrito no mesmo arquivo), 1 entry no array de catracas,
  e 2 fixtures de ~5 linhas. Zero design novo — tudo e clone de padrao existente.
- **Humano-limitado / relogio do mundo real:** ZERO. Nao depende de secret do Wagner, nem de
  janela de N dias, nem de canary. Tudo roda offline (anchor-lint/ledger sao local; o unico
  toque de rede e o `gh api` do protection-drift, que tem fallback honesto). NAO arma baseline
  (isso sim teria a janela de 3 medicoes do ADR 0275 §3 — mas e P13, fora de escopo).

## Kill-criteria / risco (quando parar ou reabrir)
- **Risco 1 — drift_alarms flaky por causa do `gh`:** se o `gh api` falhar intermitente no CI,
  a metrica oscila measured↔not_yet_measured e poderia avermelhar um ratchet futuro. Mitigacao:
  manter o fallback `not_yet_measured` (nunca exit 1 por falha de rede) e NAO armar nesta onda.
  Se a oscilacao for frequente, REABRIR pra decidir se drift_alarms le um artefato persistido
  (igual `nightly-floor.json`) em vez de chamar `gh` inline.
- **Risco 2 — fixture anchor-lint poluir contadores:** o foundation-ratchet varre `tests/`
  e o README proibe `.php` em fixtures (`tests/governance-fixtures/README.md:17-19`). Nossa
  fixture e so `.md` + arquivo-alvo neutro → sem risco, mas conferir que o arquivo-alvo NAO
  seja `.php` nem case com nenhum scanner de SPEC global (usar nome ficticio DemoAnchor).
- **Kill:** se ao conectar `backfill_error_rate` descobrir-se que a semantica "max vs media"
  muda o veredito de um gate futuro de forma ambigua, PARAR e levar a decisao (max e mais
  conservador: 1 lote ruim ja estoura o <2%) a um mini-ADR antes de seguir pra P13.
