---
roadmap_item: P03
slug: us-gov-021-isolamento-era-sqlite
onda: 2
status: executed
executed_at: "2026-07-01"
depende_de: []
destrava: [P04]
related_adrs: [275, 276, 279]
esforco_estimado: "1.5d codavel (IA-pair, margem 2x) + 7-14d relogio (2 nightlies p/ medir queda + janela catraca)"
---
# P03 · US-GOV-021: isolamento era-sqlite (o lever real do floor)

> **✅ EXECUTADO 2026-07-01** (reconciliação de bookkeeping) — sqlite-test-corruptors --strict exit 0 (corruptores REAIS=0).

## Problema (o que esta quebrado, em 2-3 frases)
O floor do nightly full-suite nao cai porque ~18-30 testes "era-sqlite" fazem `Schema::create/drop` de tabela compartilhada numa base MySQL persistente — corrompendo o schema pra todos os testes seguintes na mesma conexao (cascata "Base table not found"). Esse isolamento e o **lever real** do floor (declarado no SPEC `Governance/SPEC.md:408-409`), nao tweak de harness. US-GOV-021 foi nomeada como a US que trata isso, mas nasceu **orfa**: e referenciada 2x e tem **0 secoes definidas** — nao ha DoD, nao ha gate, ninguem pode fechar nem medir.

## Causa-raiz (evidencia VERIFICADA — file:line reais que confirmei)
- **Declaracao do lever:** `memory/requisitos/Governance/SPEC.md:408-409` — "**Nao e harness** — e o isolamento dos ~19-30 testes 'era-sqlite' que dropam tabela CORE numa base MySQL persistente compartilhada. Tratado em **US-GOV-021** (front-2)." Confirmado literalmente.
- **US-GOV-021 orfa:** `git grep "### US-GOV-021"` retorna **ZERO** secoes (rodei; saida "ZERO sections"). As unicas mencoes sao referencias-ponteiro: `Governance/SPEC.md:409,411`, `handoffs/2026-06-13-1730-sdd-floor-frente-c-era-sqlite.md:19,36`, `Infra/RUNBOOK-ct100-fullsuite.md`, `sessions/2026-06-20-sdd-avaliacao-30threads.md`. Nenhuma define DoD ou criterio.
- **Mecanismo de corrupcao provado:** o handoff `2026-06-13-1730-...:25-27,44` documenta a cadeia: A.2 (FK-off) deixou os testes era-sqlite dropar `business` com sucesso -> cascata `business 252x`; foi **revertido** como net-harmful (`Governance/SPEC.md:405-406`). Conclusao registrada: falhar-seguro (Cannot-drop) e melhor que mascarar.
- **Auditor read-only JA EXISTE** (achado nao mencionado na evidencia): `scripts/audit/sqlite-test-corruptors.mjs`. Classifica por COMPORTAMENTO-NO-MYSQL (nao text-match), campo-chave `corruptsOnMysql` (`:292`), tem flag `--strict` que da `process.exit(1)` quando ha corruptor real no tier filtrado (`:46,395`). Rodei `--json`: **256 com DDL manual, 18 corruptores reais (todos tier A, score 60-75), 238 guardados.**
- **Os 18 corruptores reais** (lista exata do auditor, ANTES das @highBlast): `Modules/Jana/Tests/Feature/TaskRegistry/{ClaimlessMutationWarning,FsmTransitionGuard,TaskUpdateAtomic,AcceptanceRef}Test.php`, `Modules/Jana/Tests/Feature/Mcp/WorkLeaseServiceTest.php`, `Modules/TeamMcp/Tests/Feature/{CoworkHandoffCrossTenant,ForjaBacklogService,ForjaChangelogService,ForjaMcpService,ForjaQuadroService,HandoffIngest,HandoffLeverTool,HandoffStaleAlert,HandoffSubmitTool,HandoffTools,IngestHeartbeat,IngestLiveness}Test.php`, `Modules/Brief/Tests/Feature/LeaseBriefSectionServiceTest.php`.
- **Contrato do auditor travado:** `tests/sqliteCorruptors.spec.ts` (sensibilidade + especificidade, 2 lados). Roda no umbrella como `npm run test:sqlite-corruptors` (`package.json:93`).

## Estado atual no repo (o que achei ao verificar agora)
**Divergencias materiais com a evidencia do prompt — REPORTADAS:**

1. **"14/27 so tem @group sem skip" esta errado em DOIS eixos.** O numero real e **22/27** (rodei o loop: 27 arquivos `legacy-quarantine`, 5 com `markTestSkipped`, 22 sem). MAS — mais importante — **`@group legacy-quarantine` e `Schema::drop` de CORE sao conjuntos DISJUNTOS**: dos 27 quarentenados, **ZERO** dropam tabela CORE (rodei o cruzamento: todos deram `-` na coluna DROPS-CORE). Os quarentenados sao testes de UI/design/Sells stale, nao corruptores. Logo "os 27 quarentenados ainda rodam no floor" e verdade, mas **eles NAO sao o lever** — nao corrompem schema.

2. **Os corruptores reais nao sao "os 21 que dropam CORE por literal".** `git grep "Schema::drop...('business'...)"` da **21 arquivos** (nao "~19-30"), mas o auditor por comportamento mostra que a maioria desses 21 e **guardada** (skip no MySQL via `markTestSkipped`+driver, ou drop dentro de `if(sqlite)`) — o auditor conta so **18 corruptores reais**, e o overlap entre "dropa CORE literal" e "corrompe no MySQL" e parcial. **A heuristica de literal-grep do handoff (`:36`) super-conta e mistura guardados com corruptores.** O auditor v2 (`sqlite-test-corruptors.mjs`) e a fonte de verdade — ele ja resolveu o ~48% falso-positivo da v1 (documentado no cabecalho `:13-25`).

3. **`Schema::create/dropIfExists` em ~260 arquivos** — confirmado (rodei: 260 arquivos de teste). Numero da evidencia bate. Mas (1)+(2) acima mostram que o gross-count nao e acionavel; o `corruptsOnMysql=18` e.

4. **Trait `WithSeededTenant` existe** (`tests/Support/WithSeededTenant.php`) — confirmado. **MAS a evidencia esta certa**: ele so RESOLVE o tenant seedado (biz=1) ou faz skip-graceful; **nao impede** nenhum teste de dropar tabela CORE. Nao tem `beforeEach`/teardown-guard, nao tem `dropAllTables` protection. E um resolver de tenant, nao um trait de isolamento.

5. **O gate NAO morde.** O auditor roda apenas como meta-teste advisory no umbrella (`.github/workflows/governance-gate-umbrella.yml:59-61` com `continue-on-error: true`). O auditor **em si** (`--strict`) **nunca e invocado** em nenhum workflow. Confirma o risco-mae global: deteccao L2 (medida), governanca L0 (0 gate SDD required).

6. **Risco-mae do floor confirmado:** `governance/nightly-floor.json` **nao esta no tree do main** (rodei `git ls-files | grep governance/nightly-floor` = vazio). O read-side (`scripts/governance/sdd-floor-read.test.mjs:18-20`) trata ausente como `not_yet_measured` (nao mente 0). Entao o floor publicado pelo write-side (`floor-compute.mjs --out governance/nightly-floor.json`, `:20`) nunca aterrissa onde o read-side le. **Isso e o item P04, mas P03 depende de P04 estar resolvido pra que a queda do floor seja OBSERVAVEL** — sem P04, fixar corruptores nao muda nenhuma metrica visivel.

## Objetivo / DoD (criterio de pronto OBJETIVO e checavel)
US-GOV-021 deixa de ser orfa e ganha DoD com gate que MORDE. Pronto quando TODOS:
1. Secao `### US-GOV-021` escrita em `Governance/SPEC.md` com DoD, owner, anchor `**Implementado em:**`, e a lista canonica dos corruptores (fonte = auditor, nao literal-grep).
2. **Floor cai de VERDADE** medido por 2 nightlies CT100 consecutivos: o `floor_count` do `nightly-floor.json` (ADR 0279) **diminui** vs baseline pre-fix — nao por inflar `skipped`, mas por reduzir `errors` da cascata "Base table not found". Criterio anti-trapaca: a queda de `errors` >= numero de testes downstream que paravam de cascatear; `skipped` NAO pode subir mais que o numero de corruptores legitimamente quarentenados.
3. **Gate liga:** `node scripts/audit/sqlite-test-corruptors.mjs --strict` (sem `--tier`, ou `--tier=A`) **passa a rodar como required** (sem `continue-on-error`) num workflow de PR. Com os 18 corruptores corrigidos/quarentenados, `corruptors=0` -> exit 0. Qualquer PR que reintroduza um corruptor real -> exit 1.
4. Contador do auditor: `corruptors: 18 -> 0` (ou `--tier=A` vazio) verificavel via `node scripts/audit/sqlite-test-corruptors.mjs --json`.

## Passos (ordenados, concretos)
1. **Escrever a US.** Criar secao `### US-GOV-021` em `Governance/SPEC.md` (logo apos a `### US-GOV-020`, antes da `### US-GOV-028`), com: owner, priority p0, status, DoD (os 4 itens acima), e a lista dos 18 corruptores como tabela [arquivo | tier | acao: CONVERTER/GUARDAR-TEARDOWN/QUARENTENAR]. Fonte da lista = `sqlite-test-corruptors.mjs --json`, NUNCA literal-grep (anti-padrao documentado).
2. **Classificar os 18 por acao.** Para cada: (a) `CONVERTER` pra `RefreshDatabase`/`DatabaseTransactions` (preferido — vira COBERTURA real); (b) `GUARDAR-TEARDOWN` (early-return `if(driver !== sqlite) return` no afterEach/tearDown — caso PHPUnit 12 roda teardown em teste pulado, ver `:20-23` do auditor); (c) `QUARENTENAR` com `markTestSkipped` non-sqlite (ultimo recurso — vira skip, nao cobertura). Maioria dos 18 (Jana/TeamMcp/Brief) sao Services de TaskRegistry/Handoff — provavel CONVERTER.
3. **PILOTO primeiro (regra do handoff `:35`):** fixar 5-6 corruptores de baixo risco (TeamMcp/Handoff* tier A score 75 sem highBlast = sem drop de tabela mega-compartilhada) + **1 nightly** + MEDIR a queda do `errors` no `nightly-floor.json`. Se cair proporcional -> escalar o resto. Se NAO cair -> reabrir hipotese (o lever pode ser outro; ver kill-criteria).
4. **Escalar** o resto dos 18 apos piloto validado. 1 PR por lote pequeno (`commit-discipline`, <=300 linhas).
5. **Ligar o gate** (apos `corruptors=0`): adicionar step required `node scripts/audit/sqlite-test-corruptors.mjs --strict --tier=A` em workflow de PR (candidato: `governance-gate-umbrella.yml`, novo step SEM `continue-on-error`). Promocao segue calendario ADR 0275 (2 verdes antes de required).
6. **Counterfactual test** (prova que morde): rodar contra um diff que reintroduz um drop nao-guardado -> confirmar exit 1.

## Arquivos a tocar (lista real)
- `memory/requisitos/Governance/SPEC.md` — escrever secao US-GOV-021 (passo 1).
- Os 18 corruptores listados em "Causa-raiz" (passos 2-4) — em `Modules/{Jana,TeamMcp,Brief}/Tests/Feature/**`.
- `.github/workflows/governance-gate-umbrella.yml` — novo step required do auditor `--strict` (passo 5), removendo/sem `continue-on-error`.
- `tests/sqliteCorruptors.spec.ts` — NAO precisa mudar (contrato ja existe); so re-verde apos fixes.
- (NAO criar) auditor `sqlite-test-corruptors.mjs` — JA existe e morde com `--strict`. Reuso, nao reescrita.

## Gate / counterfactual (COMO eu provo que o gate MORDE — qual diff da exit 1)
- **Gate:** `node scripts/audit/sqlite-test-corruptors.mjs --strict --tier=A` (ou sem `--tier` apos zerar todos os buckets). Hoje da **exit 1** (18 corruptores tier A). Apos os fixes -> exit 0.
- **Counterfactual (prova de mordida):** em qualquer arquivo de teste, adicionar `Schema::drop('business');` solto num corpo de teste sem guarda sqlite. Rodar o auditor `--strict` -> **DEVE dar exit 1** (o classifier marca `corruptsOnMysql=true`, `highBlast=['business']`, score >=80 tier S). Reverter -> exit 0. Isso ja e testado em espirito por `tests/sqliteCorruptors.spec.ts:30-34` (SENSIBILIDADE: pega corruptor real, highBlast contem 'business'). A novidade de P03 e **promover o auditor de meta-test-advisory pra gate-required que roda o auditor em si**, nao so o meta-teste do classifier.
- **Anti-trapaca do floor (DoD item 2):** se o PR fizer `corruptors` cair so porque adicionou `markTestSkipped` em massa, o `skipped` no `nightly-floor.json` sobe e o `errors` NAO cai proporcionalmente -> a queda e cosmetica. Criterio: `delta(errors) ~ testes downstream que paravam de cascatear`, nao `delta(skipped)`.

## Dependencias (e por que)
- **Depende de P04 (read-side/write-side do floor) — DE FATO, mesmo que o prompt diga `depende_de: []`.** REPORTANDO: sem P04, `governance/nightly-floor.json` nao esta no tree do main (verificado), o read-side retorna `not_yet_measured`, e a queda do floor (DoD item 2) **nao e observavel por nenhuma metrica**. Posso fazer os fixes e ligar o gate do auditor (DoD 1,3,4) sem P04, mas o criterio central "floor cai de VERDADE" (DoD 2) fica cego. Mantive `depende_de: []` no frontmatter conforme instruido, mas a verdade tecnica e: P03 fecha PARCIAL sem P04 (gate liga) e fecha TOTAL so com P04 (queda medida). P03 **destrava P04** no sentido de "da o que medir", mas P04 **viabiliza a medicao** de P03 — sao co-dependentes.
- **Relacao com ADR 0276** (par adversarial): a lista de corruptores ja foi refutada (~48% FP eliminado). Usar o auditor v2, nao re-triar a mao.

## Esforco (recalibrado ADR 0106)
- **Codavel (IA-pair, 10x + margem 2x):** escrever a US (~0.2d), classificar+fixar 18 corruptores (a maioria CONVERTER pra RefreshDatabase, mecanico mas precisa rodar cada teste local) (~0.8d), ligar o gate (~0.1d). Total **~1.5d codavel**.
- **Humano-limitado / relogio do mundo real (NAO comprime):**
  - **2 nightlies CT100** pra medir a queda do floor (piloto + escala) = **minimo 2 noites**, realista **7d** com folga pra re-run se um nightly falhar por infra.
  - **Janela de promocao de gate** (ADR 0275: 2 verdes antes de required) = mais alguns dias de relogio.
  - **Decisao Wagner** no piloto (escalar vs parar) = sincrono, depende de disponibilidade.
  - Total relogio: **7-14d** dominado pelos nightlies, nao pelo codigo.

## Kill-criteria / risco (quando parar ou reabrir)
- **KILL/REABRIR:** se o piloto (5-6 corruptores fixados) NAO derrubar `errors` no `nightly-floor.json` proporcionalmente -> o lever NAO e (so) era-sqlite. Parar a escala, reabrir root-cause. Precedente: o handoff errou previsao 2x ("Frente C -> ~970", "A.2 conserta 508") — **regra dura: MEDIR cada passo, nunca previsao-como-fato** (`handoff:43`).
- **RISCO net-harmful (precedente A.2):** NAO ligar FK-off nem mascarar "Cannot drop". Falhar-seguro e melhor (`SPEC.md:406`). Se um fix de CONVERTER quebrar um teste que dependia do schema manual (ex: `NfeInutilizacao` precisa de state/tax_number especifico), preferir CONVERTER cuidadoso a delete ingenuo.
- **RISCO de medicao cega:** se P04 nao landar, o DoD item 2 fica nao-verificavel — nesse caso fechar P03 SO ate o gate (DoD 1,3,4) e marcar DoD 2 como `blocked_by: P04` explicito, nao fingir que mediu.
- **RISCO de cosmetica:** vigiar o `delta(skipped)` — se subir mais que os corruptores legitimamente quarentenaveis, e trapaca (inflar skip pra baixar floor), exatamente o anti-padrao que o DoD proibe.
