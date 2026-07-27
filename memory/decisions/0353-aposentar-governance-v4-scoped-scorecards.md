---
slug: 0353-aposentar-governance-v4-scoped-scorecards
number: 353
title: "Aposentar o Governance v4 (scoped scorecards por bucket) — o score não dependia do scorecard, medido com controle negativo"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
kind: decision
decided_by: [W]
decided_at: "2026-07-26"
module: governance
tags: [governance, scorecard, v4, buckets, deprecacao, cron, drift, anti-teatro, obra-parada, subtracao]
supersedes:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0161-governance-v4-aposentar-hacks-0159-redundantes
  - 0163-governance-v4-metas-alcancadas-ondas-19-28
superseded_by: []
related:
  - 0155-module-grade-v3-rubrica-gate-ci
  - 0317-watchdog-crons-governanca
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
pii: false
---

# ADR 0353 — Aposentar o Governance v4 (scoped scorecards por bucket)

> **Supersede [ADR 0160], [ADR 0161] e [ADR 0163]** (append-only — [ADR 0257]: o corpo delas não é
> editado; esta ADR nova é que muda o estado). Ratificação = merge por [W], que pediu *"resolva"*
> em 2026-07-26 depois de ver a medição abaixo.

## Contexto

O advisory `crons de governança vivos? (watchdog G6 · [ADR 0317])` passou a falhar em **100% dos
PRs** em 2026-07-26 (#4800, #4805, #4815), acusando 5 artefatos de estado parados há 70-71 dias —
os scorecards YAML do Governance v4. A investigação do alarme achou um mecanismo morto, não um
cron quebrado.

## Decisão

Aposentar o Governance v4 inteiro. A régua viva de módulo passa a ser **só a v3** ([ADR 0155]):
`module:grade-snapshot` (06:05 BRT → `mcp_module_grades_history`) + `module:grade`.

## Por quê — medido, não estimado (2026-07-26)

### 1. Ninguém escrevia os scorecards

Varredura contada de escritores (`file_put_contents` · `Yaml::dump` · `writeFileSync` em
`Modules/`, `scripts/`, `app/`, `.claude/`): **zero** para `memory/governance/scorecards/*.yaml`.
`php artisan schedule:list` no CT 100 (oráculo de runtime, não parse do `Kernel.php`) confirma que
os crons de 06:05 e 07:00 eram **leitores**. Os YAMLs eram input curado à mão, e o campo
`last_grade_at` era carimbo de curadoria humana — não de geração.

### 2. O score não dependia do scorecard (controle negativo)

Avaliando cada módulo com o YAML curado × com `[]`, no CT 100, com os arquivos provados idênticos
aos do repo por `md5`:

| módulo | bucket | COM o YAML | SEM o YAML |
|---|---|---:|---:|
| Admin · Auditoria · Governance | `cross_cutting_infra` | 17 | **17** |
| Vestuario | `vertical_client_facing` | 20 | **20** |
| ComunicacaoVisual | `vertical_client_facing` | 91 | 20 |

**4 dos 5 scorecards eram inertes.** Causa: vocabulário incompatível — declaravam as dimensões da
rubrica **v3** (`D1_models`, `C1_coerencia`…) enquanto `evaluateScorecard()` iterava as chaves do
**bucket** (`multi_tenant`, `pest_coverage`…), caindo em `?? 0` no core e `?? target` nas
`bucket_dimensions`.

### 3. O motor de medição estava desligado nas duas pontas

`ScopedScorecardEvaluator::detectRule()` — dispatcher de 11 detectores reais (`file_exists`,
`grep`, `ratio`, `ast_scan`, `ci_health`, `otel_query`…) — tinha **zero call sites em produção**
(4 chamadas no repo, todas em teste), e os 3 buckets declaravam **zero** blocos `detect`.

Consequências diretas:

- score **constante** ⇒ o `--alert` de drift `>=5pts` **nunca podia disparar**. É a lápide do
  `jana:drift-sentinel` (§5 2026-07-17) noutro mecanismo: *quando todos os pontos são idênticos, o
  problema não é o baseline — é o medidor*;
- o cron persistia **todo dia** `17/100` para o módulo Governance e `20/100` para Vestuario em
  `mcp_scorecard_runs`, enquanto a v3 dá média **80,2** — número falso alimentando
  `/admin/governance/v4`, tela com **0 hits** no ledger `route-hits` (janela até 25/07; export
  manual, então é sinal de rota fria, não prova de desuso).

### 4. A premissa original já tinha sido refutada

O `_INDEX.md` dos buckets justificava a separação com *"a mesma rubrica castiga injustamente os
meta-módulos"*. Medido em #4798: `cross_cutting_infra` tem a **maior** média v3 (82,1 vs 80,2
geral) e gabarita `client_real` (100%) — exatamente a dimensão que a rubrica v4 cortava de 15 pra
5. Simulando a v4 nos 3 módulos: **−2, −2, 0**. Todo o aparato movia ≤2 pontos, para baixo.

E a v3 resolve melhor o problema que o v4 dizia resolver: `fsm_n_a` em 16 módulos (13 com razão
escrita) ajusta **por dimensão** — mais fino que por bucket.

## O que sai

| Camada | Removido |
|---|---|
| Cron | `governance:scorecard-snapshot --alert` (07:00) · `governance:initiative-sync` (08:00, fonte era o snapshot) |
| Motor | `ScopedScorecardEvaluator` · `ModuleGradeV4Command` · `ModuleGradeService::gradeV4()` · `ScorecardSnapshotCommand` · flag `governance.v4_enabled` |
| Superfície | rota `admin.governance.v4` (+3 endpoints) · `GovernanceV4DashboardController` · `GovernanceV4.tsx` · `GovernanceV4Dashboard.tsx` (+ charters, types, mock) · 9 componentes do cluster exclusivo da tela |
| Dados curados | 5 scorecards YAML + `_template.yaml` + 3 buckets + `buckets/_INDEX.md` |
| Testes | 8 arquivos 100% v4 removidos; 7 podados cirurgicamente (casos que assertavam sobre os arquivos deletados) |

## O que FICA — e por quê

- **`mcp_scorecard_runs` / `mcp_scorecard_ai_suggestions`** — dado histórico preservado; ninguém
  mais as alimenta. Deletar tabela é decisão separada.
- **`InitiativeService`** — não é exclusivo do v4: a tela viva `/admin/screen-review` o chama
  direto (`createFromScorecardBreach`). Só o cron que o alimentava por scorecard saiu.
- **`AiScorecardJudge`** (Modules/Jana) — resíduo honesto declarado: já era órfão **antes** desta
  ADR (nenhum código de produção o instanciava; o único `@see` estava no controller v4). Removê-lo
  mexe no teste de call sites de LLM (`LlmHttpCallSitesLangfuseTest`) e é outra decisão.
- **Os 3 FormRequests** (`RemediationRequest`, `CreateInitiativeRequest`, `OverrideBucketRequest`) —
  ficam com nota de aposentadoria no docblock. Eu os havia deletado e o Pest no CT 100 provou que
  era escopo alheio: **20 testes** os cobrem (incl. `Wave25CrossTenantIsolationTest` do Superadmin)
  e `RemediationRequest` é referenciado por `AlertAcknowledgeRequest`, fora do v4. Ficaram sem
  endpoint — resíduo declarado, não force (§5 2026-07-15 "buraco heterogêneo fica resíduo honesto").
- **A v3 inteira** — `ModuleGradeCommand`, `ModuleGradeSnapshotCommand`, `module-grades-gate`.

## Consequências

1. O advisory do watchdog apaga **por consequência**, não por silenciamento: sem os 5 YAMLs, o
   corpus datado do eixo de entrega volta a 9 artefatos e nenhum passa de 60d. Não foi criada
   exceção/allowlist — que seria o critério sintático já morto 4× no §5.
2. Um juiz a menos sobre o mesmo tema (a v3 já julgava módulo) — fase de **subtração** das
   [ADR 0271]/[ADR 0314].
3. Perde-se a possibilidade de rubrica por bucket. Aceito: ela nunca funcionou (§2 e §3).

## Alternativa considerada e recusada

**Ligar a máquina de verdade** — alinhar as chaves dos 4 YAMLs ao vocabulário do bucket, declarar
`rules[].detect` nos buckets e invocar `detectRule()` no `evaluateScorecard()`. É a leitura mais
generosa da regra *"ligue a máquina"* do CLAUDE.md, e o motor tinha testes. Recusada porque
reconstruiria um aparato cuja **premissa foi refutada por medição** (§4) e que move ≤2pp — um
segundo juiz para o tema que a v3 já julga (§5 2026-07-09 *"duplica régua consolidada"*).

Também recusadas: **re-curar os 5 YAMLs** (apagaria o vermelho por ~60d e voltaria; 4 continuariam
inertes — polir baseline de medidor tautológico) e **excluir os 5 do eixo 2 do watchdog** (o
alarme estava certo; silenciá-lo esconderia o achado).

## Gate de reversão

Se voltar a existir necessidade real de rubrica por bucket, o caminho de volta é o `detectRule()`,
preservado no histórico git com os 11 detectores e seus testes (`ScopedScorecardEvaluatorTest`,
`DetectOtelQueryTest`, `ResolveModulePathTest`). A reversão custa reconstruir os YAMLs de bucket
com `rules[].detect` — não reescrever o motor. Sinal que justificaria reabrir: um módulo ser
sistematicamente mal avaliado pela v3 **por causa da sua categoria**, com o número medido (foi
exatamente essa premissa que a medição de §4 refutou).

## Proveniência

Investigação em 2026-07-26 disparada pelo advisory vermelho; proposta em
[`proposals/2026-07-26-deprecar-governance-v4-scoped-scorecards.md`](proposals/2026-07-26-deprecar-governance-v4-scoped-scorecards.md);
[W] decidiu *"resolva"*. Medições no CT 100 (container `oimpresso-staging`), nunca local.
