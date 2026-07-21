---
proposal_id: funcao-scorecard-validacao-por-outcome
status: proposta
created: "2026-07-21"
proposed_by: claude-code
decided_by: wagner
parent_adr: 0345 (topicos-vivos) + 0256 (knowledge survival)
related_adrs: [0345, 0256, 0230, 0264, 0105, 0093]
type: mecanismo-de-processo
---

# funcao-scorecard — validação por OUTCOME (fechar a barra CodeScene, sem copiar a solução)

- **Status:** PROPOSTA. Merge/ratificação = [W]. NÃO é gate (§"O que NÃO é" abaixo).
- **Data:** 2026-07-21 · **Autor:** [CC] · **Origem:** grade de réguas — sub-dimensão *"Code-health ancorado (barra CodeScene, validado por outcome)"* = a nota mais baixa (6,5) da nova estrutura ([session grade-catálogo 2026-07-21](../../sessions/2026-07-21-grade-catalogo-aprendizado-vs-mercado.md), lá registrada como 6,0 antes de `validação-não-circular` subir 4→8,5).
- **Complementa, não duplica:** a proposta [funcao-scorecard-opiniao-ancorada-rubrica](2026-07-21-funcao-scorecard-opiniao-ancorada-rubrica.md) (aceita → [ADR 0345](../0345-topicos-vivos-aprendizado-por-critica-revisada.md)) + a calibração [`funcao-scorecard-calibracao.mjs`](../../../scripts/governance/funcao-scorecard-calibracao.mjs) validam **o INSTRUMENTO** (o juiz discrimina defeito mecânico numa fixture sintética, κ=1,0). Esta proposta valida coisa **ortogonal**: se o veredito numa **função REAL correlaciona com aquela função de fato gerar incidente** — o eixo que a barra CodeScene mede e que hoje está em zero.

## Contexto — o gap exato (não é o mesmo que a calibração já cobre)

O funcao-scorecard hoje tem duas provas, e nenhuma é validação-por-outcome:

| Prova existente | O que garante | O que NÃO garante |
|---|---|---|
| Calibração κ=1,0 (fixture sintética, juiz cego) | O juiz **discrimina** defeito mecânico plantado, não-circular | Que o veredito numa função de produção corresponda a defeito real |
| Braço-incidente (t12–t14: rótulo ancorado em teste de regressão real) | O juiz reconhece o **padrão** de 3 defeitos históricos | Que funções que ele marca `discordo` sejam as que **materializam** incidente |

A barra (CodeScene Code Health) exige o terceiro: **correlação nota↔defeito real ao longo do tempo**. É o que faltava medir — e este documento mede pela primeira vez.

## Como o CodeScene valida por outcome (a barra — fatual, citado)

Estudo canônico **"Code Red: The Business Impact of Code Quality"** (Tornhill & Borg, TechDebt 2022, [arXiv 2203.04374](https://arxiv.org/abs/2203.04374)):

- **Desenho:** retrospectivo/observacional sobre **39 codebases de produção · 30.737 arquivos** · 14 linguagens.
- **Ground-truth de defeito:** issues do **Jira tipadas como bug, fechadas**, ligadas a arquivos pelo **ID do issue citado nos commits** (NÃO heurística de "fix" na mensagem).
- **Números:** defeitos/arquivo Healthy 0,25 · Warning 0,94 · Alert 3,70 → **Red ≈ 15×** Green. Resolver issue em código ruim = **+124% de tempo**. Correlação Code-Health↔tempo-de-resolução **Pearson r = −0,58** (vs r=0,13 de "linhas de código" naïve).
- **Confounder que os próprios autores marcam como não-resolvido:** causalidade bidirecional ("código ruim gera defeito, ou a nota é baixa PORQUE teve muito defeito?"), amostra desbalanceada (88,99% Healthy), viés de seleção (só clientes CodeScene). Os "15×" são **associação retrospectiva, não efeito causal provado**.
- **Alerta de método (JIT defect prediction, [CSUR 2022](https://damevski.github.io/files/report_CSUR_2022.pdf)):** validação com respeito à **latência temporal** derruba precisão de 55–72% → 18–60%. K-fold ingênuo/mesmo-dia **infla** a precisão aparente. (E [SonarQube empírico](https://arxiv.org/pdf/1908.11590): as regras que ele rotula "bug" quase nunca viram falha — rótulo automático de qualidade ≠ defeito.)

## Traduzir a PREMISSA, não copiar a solução (lápide §5 2026-07-16)

**A solução do CodeScene não é transplantável** — checando se o problema deles é o nosso:

| Premissa do CodeScene | Vale aqui? | Consequência |
|---|---|---|
| Nota **automática** (Code Health 1–10) sobre TODOS os arquivos | ❌ — o funcao-scorecard é **juiz-LLM caro/manual**, 1 arquivo graduado até hoje (ProductUtil, 37 funções) | Não dá pra "pontuar tudo e correlacionar em massa" |
| **Jira** com issues tipadas defect ligadas por ID | ❌ — não temos Jira de defeito por arquivo | Precisa usar NOSSO ground-truth de defeito |
| **Anos** de história de commit+issue | ❌ — a nota nasceu HOJE; o repo local é **shallow** | Não dá pra minerar história; tem que **acretar a partir de agora** |

**A premissa que COMPARTILHAMOS** (essa sim vale): *um parecer de qualidade só é confiável se correlacionar com defeito real ao longo do tempo.* A tradução para o oimpresso:

1. **Nosso "Jira de defeito" já existe, disperso:** o ledger [proibicoes §5](../../proibicoes.md) + os testes de regressão de incidente (`tests/**` marcados `incidente`/`REGRESSÃO`/`GUARD MECANIZADO`) + os fix-commits `US-*`/`fix(...)`. É ground-truth curado por humano, mais forte que heurística de mensagem — só não está **ligado à nota**.
2. **Como a nota é cara, o outcome-log também aponta ONDE gastar o próximo esforço de graduação** (as funções que materializam defeito primeiro) — a economia que o CodeScene não precisa fazer porque a nota dele é grátis. Alinha com [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md) (só investir onde há sinal).

## A medição (o número REAL — protótipo [`funcao-scorecard-outcome-probe.mjs`](../../../scripts/governance/funcao-scorecard-outcome-probe.mjs))

Cruza a contagem de `discordo` por função (sinal de NOTA, parseado do scorecard) × defeito **materializado** (sinal de OUTCOME, de fontes objetivas reproduzíveis). Resultado sobre as 37 funções de ProductUtil:

```
Sinal de NOTA:      19 funções com ≥1 discordo (32 discordos) · 18 clean (0 discordo)
MATERIALIZADO (defeito REAL aconteceu · fix-commit F ∪ ledger §5 L): 2
  • getVariationGroupPrice   discordo=3  [L §5 2026-07-15 · tabela de preço]
  • fixVariationStockMisMatch discordo=1 [F commit 20d95b2d8 · US-PROD-028 #4636]

── CORRELAÇÃO ──
  point-biserial r (discordo × materialized 0/1) = 0,26   Spearman ρ = 0,254
  N=37 · N_materialized=2  ⇒ SPOT-CHECK, não significância
── 2×2 (flagged = discordo≥1) ──
  recall (materializados flagged) = 2/2 = 100%
  specificity (clean ficou clean) = 18/18 = 100%
  precision (flagged materializou) = 2/19 = 10,5%  ← os 17 restantes são LATENTES, não FP
── CONFOUNDER fan-in ──
  clean com alto fan-in (≥10) e 0 discordo: updateProductQuantity(16), decreaseProductQuantity(22), adjustStockOverSelling(10)
```

**Interpretação honesta (o que o número diz e o que NÃO diz):**

- **r = +0,26 / ρ = +0,254** — direção positiva (função mal-avaliada tende a materializar mais), mas com **N_materialized=2 é spot-check, não significância** — não reproduz nem pretende reproduzir o r=−0,58 do CodeScene (eles: 30.737 arquivos × anos; nós: 37 funções × 1 dia).
- **Recall 2/2 + criterion-match 2/2** é o achado forte: os DOIS defeitos materializados foram flagged **no critério exato** — `getVariationGroupPrice` C1 (cross-tenant de preço) + C3 (0-row string vazia) = as duas classes do incidente §5 2026-07-15; `fixVariationStockMisMatch` C2 (grava saldo cru sem num_uf).
- **O único TP prospectivo e limpo** (não-contaminado): `fixVariationStockMisMatch`. O parecer C2 (#4628) **precedeu** o fix, e o commit US-PROD-028 (#4636, 14:52) **cita o parecer como causa** — "Parecer C2 (funcao-scorecard #4628) → fix aprovado por [W] sob REGRA MESTRE". Cadeia nota→defeito-ratificado→fix+teste, documentada no git.
- **Confounder derrubado (importante):** as 3 funções de MAIOR fan-in (16/22/10 consumidores) estão **clean** com 5 goldens diretos. Se o discordo fosse só "função grande apanha mais" (o risco do Code Red), elas seriam as mais flagged — são o oposto. Aqui o discordo trilha **defeito**, não tamanho.
- **A precisão de 10,5% NÃO é taxa de falso-positivo:** os 17 flagged não-materializados são **latentes** (C5 N+1, C6 SQL-cru-int, C7 docblock-mente). Saber quais materializam exige **TEMPO** — é exatamente o ponto do JIT-SDP (validação temporal), e a razão de ser da parte prospectiva abaixo.

**Limites que invalidam qualquer alegação forte (declarados, não escondidos):**
1. **N=2 materializado** — abaixo de qualquer significância. Um TP prospectivo limpo + um retrospectivo.
2. **Contaminação retrospectiva** em `getVariationGroupPrice`: flagged em 21/07 para incidente de 15/07, com proibicoes §5 no contexto do juiz (o juiz podia "saber"). Só `fixVariationStockMisMatch` é limpo.
3. **git shallow** — o sinal (F) só vê a janela do clone, não a história real de defeito. **Isto é o argumento pró-log persistente**, não um bug a consertar com `--unshallow`.
4. **1 arquivo** — a nota só cobre ProductUtil.

## A proposta — outcome-log que ACRETA (o mecanismo que fecha a barra)

Como não temos anos de história (temos que começar agora), a validação vira **prospectiva e acumulativa**:

1. **Registro append-only `memory/governance/scorecards/outcome-log.jsonl`** — 1 linha por defeito REAL que toca uma função graduada, quando ele acontece: `{ data, funcao, arquivo, criterio_relacionado (C1..C8|nenhum), fonte (fix-commit hash | teste-regressão | §5 | US-PROD), veredito_na_epoca (discordo|concordo|incerto|na|nao-graduada), pre_ou_pos_grade }`. Preenchido no fluxo que **já existe** ("mexeu, registra" + incident-done-checklist), não um processo novo.
2. **O probe re-roda sobre o log** e reporta, a cada N meses, a correlação **out-of-sample** (defeitos que chegaram DEPOIS da nota — validação temporal do JIT-SDP, sem a inflação do mesmo-dia).
3. **Fecha o loop de aprendizado** (princípio duro 4): se a materialização concentra nas funções flagged → o parecer é validado empiricamente (evidência pra [W] considerar promovê-lo); se não concentra → o parecer é refutado e a rubrica é revisada. Os dois desfechos são valor.
4. **Fonte de ground-truth = a curada, não heurística:** fix-commit `US-*`/`fix(...)` localizado + teste `incidente`/`REGRESSÃO` + entrada §5. (Melhor que o "fix na mensagem" que o SonarQube-crítica mostra ser fraco; nosso equivalente do Jira-tipado do CodeScene.)

## O que esta proposta NÃO é (anti-duplicação, anti-gate)

- **NÃO é gate.** O outcome-log é MEDIÇÃO advisory. Um `discordo` continua sem autorizar fix (§0.3 do método); a decisão de virar catraca é [W], e só faz sentido DEPOIS do log mostrar correlação out-of-sample. Somar um gate agora seria teatro (§5 2026-07-09 "presence-gate" / "gate que não morde").
- **NÃO duplica régua consolidada.** Não é um 2º juiz nem um 2º detector de cobertura — é o eixo de outcome que **nenhuma** régua atual mede (a calibração mede o instrumento; o casos-gate mede cobertura de tela; este mede nota-função↔defeito-real).
- **NÃO re-alega superioridade.** A barra CodeScene segue à frente como métrica *validada* (r=−0,58, N=30k). Nós temos um spot-check direcional + o método pra acretar. Sem inflar.

## Consequências

- **Positivo:** o funcao-scorecard ganha o eixo que faltava (outcome), com um número medido + um mecanismo pra crescê-lo; o log também prioriza onde graduar em seguida.
- **Custo:** 1 linha JSONL por incidente (trivial, no fluxo existente); o probe é read-only.
- **Risco:** o log pode nunca acumular N suficiente se ProductUtil for estável — nesse caso a resposta honesta é "sem sinal" (ADR 0105), não forçar conclusão.

## Re-grade proposto (sub-dimensão "Code-health ancorado")

**6,5 → 7,0.** Justificativa (o +0,5, sem inflar): saiu de **zero medição de outcome** para (a) um número real medido (r=0,26 direcional, recall 2/2, criterion-match 2/2), (b) 1 cadeia causal prospectiva documentada (C2→US-PROD-028), (c) confounder de fan-in checado e derrubado, (d) probe reproduzível, (e) mapa de limites honesto + mecanismo prospectivo desenhado. **Não sobe mais que isso** porque: N=2, o log prospectivo está **desenhado, não rodando**, 1 dos 2 é retrospectivo-contaminado, 1 arquivo só. Escada até a barra: **8** = log rodando + N_materialized ≥ ~10 out-of-sample; **9** = correlação temporal holds + κ vs gold HUMANO; **10** = a barra CodeScene (validada em escala, multi-arquivo). Re-grade é proposta — aplica no próximo `reguas-do-sistema` ou por ratificação [W] (não edito o fóssil datado da grade).
