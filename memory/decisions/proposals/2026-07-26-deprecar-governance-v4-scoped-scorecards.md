---
slug: 2026-07-26-deprecar-governance-v4-scoped-scorecards
title: "Deprecar o Governance v4 (scoped scorecards) — o score não depende do scorecard; medido com controle negativo"
type: proposal
status: proposto
authority: proposal
lifecycle: ativo
kind: decision
decided_by: [W]
proposed_by: [CC]
proposed_at: "2026-07-26"
module: governance
tags: [governance, scorecard, v4, buckets, deprecacao, cron, drift, anti-teatro, obra-parada]
related:
  - 0160-governance-v4-scoped-scorecards-buckets
  - 0161-governance-v4-aposentar-hacks-0159-redundantes
  - 0163-governance-v4-metas-alcancadas-ondas-19-28
  - 0317-watchdog-crons-governanca
  - 0256-knowledge-survival-meia-vida-catraca-sentinela
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
pii: false
---

# Deprecar o Governance v4 (scoped scorecards)

> **O que dispara isto:** o check advisory `crons de governança vivos? (watchdog G6)` falha em
> **100% dos PRs** desde 2026-07-26 (verificado em #4800, #4805, #4815) acusando 5 artefatos
> parados há 70-71 dias — os 5 scorecards YAML do v4. Alarme que sempre toca é alarme desligado.
>
> Este proposal **não é sobre o alarme**. O alarme está certo; ele achou um mecanismo morto.

## 1. Os fatos (medidos 2026-07-26 — comando + resultado, não leitura)

### 1.1 Nenhuma automação escreve os 5 scorecards

Varredura contada por escritores (`file_put_contents` · `Yaml::dump` · `writeFileSync`) em
`Modules/`, `scripts/`, `app/`, `.claude/`: **zero** escrevem `memory/governance/scorecards/*.yaml`.
Os escritores que existem apontam pra outros paths (`storage/reports/`,
`memory/governance/service-scorecard.json`, `governance/sdd-scorecard.json`).

Oráculo de runtime (`php artisan schedule:list` no CT 100 — não parse do `Kernel.php`, LC-08):

```
6:05  module:grade-snapshot                 → grava mcp_module_grades_history
7:00  governance:scorecard-snapshot --alert → LÊ os YAMLs → grava mcp_scorecard_runs
7:10  governance:sdd-scorecard-snapshot     → grava histórico SDD (mecanismo distinto, vivo)
```

`ScorecardSnapshotCommand::handle()` chama `loadScorecardForModule()`: o YAML é **input**.
`last_grade_at` é carimbo de **curadoria humana**. Isto já está registrado no dono da regra —
o cabeçalho de [`_template.yaml`](../../governance/scorecards/_template.yaml), corrigido em
[#4798](https://github.com/wagnerra23/oimpresso.com/pull/4798).

### 1.2 Eles não pararam em maio — nunca andaram

`git log` (clone desrasado antes de medir):

| arquivo | nasceu | único toque posterior |
|---|---|---|
| `admin` · `auditoria` · `governance` | 2026-05-16 (Wave 23+24, `a353668701`) | 08/06 — deleção pelo squash do #2413 + restauração |
| `vestuario` | 2026-05-16 (Wave 25, `48527edd08`) | idem |
| `comunicacaovisual` | 2026-05-17 (Wave 26, `122b56a220`) | idem |

Conteúdo material intocado desde o nascimento: foram baseline curado das Waves 23-26, e o
programa v4 nunca foi retomado.

### 1.3 O achado que decide: 4 dos 5 scorecards não afetam o próprio score

Rodado no CT 100 com **controle negativo** — avaliar o módulo com o YAML curado × com `[]` —
depois de provar por `md5` que os 5 arquivos no container são idênticos aos do repo:

| módulo | bucket | score COM o YAML | score SEM o YAML |
|---|---|---:|---:|
| Admin | `cross_cutting_infra` | 17 | **17** |
| Auditoria | `cross_cutting_infra` | 17 | **17** |
| Governance | `cross_cutting_infra` | 17 | **17** |
| Vestuario | `vertical_client_facing` | 20 | **20** |
| ComunicacaoVisual | `vertical_client_facing` | 91 | 20 |

**Causa:** vocabulário incompatível. Os 4 declaram as dimensões da rubrica **v3**
(`D1_models`, `D2_pest`, `C1_coerencia`…), e `evaluateScorecard()` itera as chaves do **bucket**
(`multi_tenant`, `pest_coverage`, `C_reflexividade`…). Chave que não casa cai em `?? 0` no core
e `?? $dimCfg['target']` nas `bucket_dimensions` — o score sai dos *defaults do bucket*, não do
scorecard. Só `comunicacaovisual.yaml` (escrito depois, com as chaves do bucket) é lido de fato.

### 1.4 O motor que mediria o código está desligado nas duas pontas

- `detectRule()` — dispatcher de 11 detectores reais (`file_exists`, `grep`, `ratio`, `ast_scan`,
  `ci_health`, `otel_query`…) — tem **zero call sites em produção**: 4 chamadas no repo inteiro,
  **todas em teste**.
- Os 3 buckets (`cross_cutting_infra`, `vertical_client_facing`, `meta_governance`) declaram
  **zero** blocos `detect`. Não há regra pros detectores medirem.

**Consequências, todas verificáveis:**

1. O score é **constante** entre execuções → o `--alert` de drift `>=5pts` **nunca pode disparar**,
   salvo no dia em que um humano editar um YAML. É alarme tautológico — mesma família do
   `jana:drift-sentinel` (§5 2026-07-17), e a régua já escrita lá se aplica: *quando todos os
   pontos são idênticos, o baseline não é o problema — o medidor é.*
2. O cron grava **todo dia** `score=17/100` para o módulo Governance e `20/100` para Vestuario em
   `mcp_scorecard_runs` — enquanto o v3 dá média **80,2**. É número falso, persistido diariamente,
   alimentando `/admin/governance/v4`.
3. `/admin/governance/v4` tem **0 hits** no ledger `governance/route-hits.json` (32 rotas e
   11 pages com hit até 25/07). *Ressalva honesta:* janela móvel + export manual — é sinal de
   rota fria, não prova de "nunca usada".

### 1.5 A premissa original do v4 já tinha sido refutada (medição de #4798, 2026-07-26)

O `_INDEX.md` dos buckets justificava a separação com *"a mesma rubrica castiga injustamente os
meta-módulos"*. Medido: `cross_cutting_infra` tem a **maior** média v3 (82,1 vs 80,2 geral) e
gabarita `client_real` (100%) — justamente a dimensão que a rubrica v4 corta de 15 pra 5 pontos.
Simulando a rubrica v4 nos 3 módulos: **−2, −2, 0**. Todo o aparato move ≤2 pontos, para baixo.

## 2. Recomendação: deprecar o v4, faseado

O v3 (`module:grade-snapshot`, 06:05 BRT → `mcp_module_grades_history`) segue como régua viva, e
já resolve melhor o problema que o v4 dizia resolver: `fsm_n_a` em 16 módulos (13 com razão
escrita) ajusta **por dimensão**, mais fino que por bucket.

| Fase | O que sai | Risco |
|---|---|---|
| **F1** — parar de produzir dado falso | remover o schedule `governance:scorecard-snapshot --alert` do `Kernel.php`; flag `GOVERNANCE_V4_ENABLED=false` em prod | baixo — nada consome `mcp_scorecard_runs` fora da tela v4 |
| **F2** — remover a superfície | rota + `GovernanceV4DashboardController` + `GovernanceV4.tsx`/`GovernanceV4Dashboard.tsx` + `governanceV4Types.ts` + `mockGovernanceV4.ts` + 3 Requests | médio — 13 arquivos de teste citam v4/evaluator; a maioria são testes de saturação de Wave que precisam ser podados junto |
| **F3** — remover o motor | `ScopedScorecardEvaluator` + `ModuleGradeV4Command` + `ModuleGradeService::gradeV4()` + os 5 YAMLs + os 3 buckets | médio |
| **F4** — fechar o registro | ADR de supersede das **0160/0161/0163** (`accepted` → append-only, ADR nova com `supersedes:`) + lápide no §5 | — |

Tabelas `mcp_scorecard_runs` / `mcp_scorecard_ai_suggestions`: **preservar** (dado histórico,
append-only por hábito) — F1 só para de alimentá-las.

O alarme do watchdog apaga **sozinho** na F3: sem os 5 YAMLs, o corpus datado do eixo de entrega
volta a 9 artefatos e nenhum passa de 60d.

## 3. Alternativas consideradas

| Alternativa | Por que cai |
|---|---|
| **Re-curar os 5 YAMLs à mão** | apaga o vermelho por ~60d e ele volta; 4 dos 5 continuam sem efeito nenhum sobre o score; e é polir o baseline de um medidor tautológico — o §5 2026-07-17 já mata essa forma nominalmente |
| **Ligar a máquina de verdade** (alinhar as chaves + declarar `rules[].detect` + invocar `detectRule()`) | é a leitura mais generosa da regra *"ligue a máquina"*, e o motor existe com testes. Mas restaura um aparato cuja premissa foi **refutada por medição** (§1.5) e que move ≤2pp — trabalho grande pra reconstruir um segundo juiz do mesmo tema que o v3 já julga (§5 2026-07-09 *"duplica régua consolidada"*). Se [W] preferir este caminho, ele é tecnicamente viável e o `detectRule` é o ponto de partida |
| **Excluir os 5 do eixo 2 do watchdog** | vira allowlist por nome/pasta — critério sintático que o §5 já matou 4× (allowlist-de-pasta · guard `@scope` · vocabulário 130 FP · `toHaveKey` 100% FP). O alarme está **certo**; silenciá-lo é esconder o achado |
| **Não fazer nada** | o advisory fica vermelho em todo PR e treina o time a ignorar o vermelho — o custo que o próprio [W] nomeou |

## 4. O que exige [W] (soberania — não faço sozinho)

1. **Ratificar a deprecação** — as ADRs 0160/0161/0163 estão `accepted`; mudar exige ADR nova com
   `supersedes:` e o merge é ato de [W].
2. **Escolher entre deprecar (§2) e reconstruir (`detectRule`, §3)** — é decisão de produto sobre
   quanto o projeto quer de rubrica por bucket, não cálculo técnico.
3. Se deprecar: autorizar a poda dos testes de saturação das Waves 23-27 que ancoram no v4.

## 5. Gate de reversão

Se depois da F3 aparecer necessidade real de rubrica por bucket, o caminho de volta é o
`detectRule()` — que fica preservado no histórico git com os 11 detectores e seus testes. A
reversão custa reconstruir os YAMLs de bucket com `rules[].detect`, não reescrever o motor.
