---
date: "2026-07-21"
hour: "16:20 BRT"
topic: "funcao-scorecard: validação por OUTCOME (fechar a barra CodeScene) — investigação + proposta + protótipo + número real"
authors: [C]
outcomes:
  - "Medido pela 1ª vez a correlação nota↔defeito-real do funcao-scorecard: point-biserial r=0,26 / Spearman ρ=0,254 (N=37 funções, N_materialized=2) — spot-check, não significância."
  - "Recall 2/2 + criterion-match 2/2 nos defeitos materializados; 1 cadeia causal prospectiva limpa (parecer C2 → fix US-PROD-028 #4636, citada no commit)."
  - "Confounder de fan-in derrubado: as 3 funções de maior fan-in (16/22/10) estão clean — discordo trilha defeito, não tamanho."
  - "Achado de método: repo local é shallow → git-churn por função mede a fronteira do clone, não a história de defeito (LC-08 evitado)."
  - "Entregue ADR-proposta (outcome-log prospectivo, NÃO gate) + protótipo reproduzível funcao-scorecard-outcome-probe.mjs. Re-grade proposto 6,5→7,0."
prs: []
us: []
---

# funcao-scorecard — validação por OUTCOME (investigação)

## Pedido

Grade de réguas (nova estrutura): a sub-dimensão **"Code-health ancorado (barra = CodeScene, validado por outcome)"** é a nota mais baixa (6,5). O funcao-scorecard hoje é calibrado contra fixture sintética + braço-incidente, mas **não** validado por outcome contínuo (defeito/churn/incidente ao longo do tempo). Tarefa = investigar + propor (não implementar cego): pesquisar como o CodeScene valida por outcome, propor mecanismo viável **traduzindo a premissa** (não copiando a solução), entregar ADR-proposta + protótipo com **número de correlação real** + re-grade.

## O que já existia (lido, não assumido)

- [`FUNCAO-SCORECARD-METODO.md`](../requisitos/_Governanca/FUNCAO-SCORECARD-METODO.md) — 11 vereditos C1–C8 por função, **sem nota agregada**; calibração κ=1,0 em fixture sintética ([`funcao-scorecard-calibracao.mjs`](../../scripts/governance/funcao-scorecard-calibracao.mjs)).
- Scorecard REAL: [`app-utils-productutil.yaml`](../governance/scorecards/funcoes/app-utils-productutil.yaml) — 37 funções, `totals: discordo 32, incerto 21, concordo 83, na 123`, `validation_status: pareceres-ancorados-nao-validados`.
- Braço-incidente ancora rótulo em teste de regressão real (`IncidentValorInfladoNumUfTest`, `UpdateCrossTenantIdorTest`, `SafeSelectItem`).

**Distinção-chave que orientou tudo:** a calibração prova que **o JUIZ discrimina** defeito mecânico (instrumento). Ela NÃO prova que o **veredito numa função real correlaciona com aquela função gerar incidente** (outcome). É o segundo eixo que a barra CodeScene mede e que estava em zero.

## Pesquisa CodeScene (barra) — resumo

"Code Red" (Tornhill & Borg, 2022, arXiv 2203.04374): retrospectivo, **39 codebases / 30.737 arquivos**, ground-truth = **issues Jira tipadas bug** ligadas por ID no commit (NÃO heurística "fix"), Red≈**15×** defeitos, tempo **+124%**, r=**−0,58**. Confounders que os autores marcam abertos: causalidade bidirecional, amostra desbalanceada, viés de seleção. Alerta JIT-SDP: validação **temporal** derruba precisão 55→18% — mesmo-dia/k-fold ingênuo infla.

## Achado de método (LC-08 evitado)

`git log -L` por função no worktree deu **4 commits** em ProductUtil.php. Verifiquei: `git rev-parse --is-shallow-repository = true`. O repo é **shallow** — o churn por função mede a **fronteira do clone**, não a história real de defeito. Se eu tivesse concluído "churn=4" seria medir a fonte errada (a exata classe do alarme LC-08, 5×). ⇒ redirecionei o sinal de outcome pro corpus que a tarefa nomeia (§5 + testes de regressão + fix-commits), que não depende de história profunda. E isso VIROU argumento da proposta (por que precisa de log persistente, não arqueologia git).

## A medição (protótipo `funcao-scorecard-outcome-probe.mjs`)

Cruza `discordo`-count por função × defeito **materializado** (F fix-commit localizado ∪ L ledger §5; sinais duros). Correção no meio do caminho: a 1ª versão contava função usada como **helper de setup** em teste de incidente como se fosse o sujeito do defeito (helper ≠ sujeito — mesmo vício LC-08); apertei pra `ProductUtil::<fn>` qualificado no docblock (sinal secundário GUARDED, separado do materialized).

**Número real:** point-biserial **r=0,26** · Spearman **ρ=0,254** (N=37, N_materialized=2). 2×2: **recall 2/2**, **specificity 18/18**, precision 2/19 (17 latentes, não FP). Materializados: `getVariationGroupPrice` (§5 15/07, discordo=3, C1+C3 = as classes do incidente) + `fixVariationStockMisMatch` (fix US-PROD-028, discordo=1, C2 citado no commit). Confounder fan-in **derrubado** (maiores fan-in = clean).

**Honestidade (não inflar):** N=2 = spot-check; 1 TP prospectivo limpo (C2→#4636) + 1 retrospectivo contaminado (§5 no contexto do juiz); o valor está no criterion-match + na cadeia causal + no confounder derrubado, **não** na magnitude de r.

## Entregue

1. Protótipo [`scripts/governance/funcao-scorecard-outcome-probe.mjs`](../../scripts/governance/funcao-scorecard-outcome-probe.mjs) — advisory, read-only, reproduzível.
2. ADR-proposta [`2026-07-21-funcao-scorecard-validacao-por-outcome.md`](../decisions/proposals/2026-07-21-funcao-scorecard-validacao-por-outcome.md) — outcome-log prospectivo append-only (`outcome-log.jsonl`) que acreta defeito-real→função-graduada; ground-truth curado (fix-commit + teste-regressão + §5); **NÃO gate**, decisão de gate = [W]; não duplica calibração nem casos-gate.
3. Re-grade proposto **6,5 → 7,0** (+0,5: saiu de zero-outcome para número medido + cadeia causal + confounder checado + probe + mecanismo desenhado; capado porque N=2, log não-rodando, 1 arquivo). Escada: 8 = log rodando N≥10 out-of-sample; 9 = temporal holds + κ vs gold humano; 10 = a barra.

## Próximo passo (decisão [W])

Ratificar a proposta (merge) pra começar o `outcome-log.jsonl` a acumular — ou apontar ajuste. Sem [W], nada vira gate e o log não nasce.
