---
date: "2026-06-21"
topic: "Auditoria estado-da-arte — governança SDD do oimpresso (programa Spec-Driven Development + malha de gates required/advisory + enforcement no main + avaliador adversarial). Gap analysis vs best-of-class 2026. Nota composta 68/100, decisão CONSOLIDAR."
authors: [C]
related_adrs:
  - 0261-enforcement-faseado-gates-ci
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes
  - 0279-sdd-medir-governar-floor-nightly
  - 0282-protocolo-v2-colapso-ratificacao
---

# Estado-da-arte da governança SDD do oimpresso — gap analysis (2026-06-21)

> **Raia desta auditoria:** programa SDD + malha de gates required/advisory + enforcement no `main` + avaliador adversarial do processo. NÃO cobre knowledge-architecture nem session-handoff (auditorias irmãs em paralelo). Read-only — nenhum código/branch protection foi tocado.
> **Fonte da verdade:** `gh api .../branches/main/protection` (vivo, lido 2026-06-21), `governance/*.json` (commitado em `origin/main`), `scripts/governance/*.mjs`, `.claude/workflows/sdd-*.js`, ADRs 0261/0271/0275/0279/0282, e a última avaliação adversarial (`memory/sessions/2026-06-20-sdd-avaliacao-30threads.md`, composto 65/100).

## TL;DR

**Maturidade global ponderada: 68/100. Decisão: CONSOLIDAR** (não EVOLUIR). A arquitetura de governança SDD do oimpresso é, em desenho, **superior a quase todo best-of-class open** (GitHub Spec Kit, OPA/Conftest, merge-queue padrão) em três eixos que o mercado mal toca: (1) regra de **armamento de métrica** (não pune antes de 3 medições reais), (2) **avaliador adversarial recorrente** que verifica estado-real-em-git, não o plano, e (3) **honestidade de medição** (`not_yet_measured` em vez de fingir verde). O gap não é de design — é de **execução do último elo**: nenhum dos 17 required é um gate SDD, então o sistema **mede mas não governa**. Top-3 gaps P0: (a) transporte do floor CT100→main + pcov, que destrava a cadeia inteira de promoção; (b) baseline-que-engole-regressão (a regressão MemCofre #2848 entrou no main "verde"); (c) métricas dormentes/ilusórias (G7/G8 com 0 rows, D2/D3 RAGAS incapazes de falhar). EVOLUIR seria reescrever em OPA/Rego — desnecessário e regressivo (perderia o armamento e o adversário, que o mercado não tem).

---

## 1. Best-of-class 2026 (o que o mercado faz)

Três categorias, comparadas só na raia "processo SDD + governança de CI":

### A. Frameworks de Spec-Driven Development
| Sistema | Features-chave | Open/Cloud | Diferencial | Limite vs oimpresso |
|---|---|---|---|---|
| **GitHub Spec Kit** ([repo](https://github.com/github/spec-kit) · [docs](https://github.github.com/spec-kit/)) | Spec→Plan→Tasks→Implement; cada fase = artefato markdown que alimenta o próximo; templates + checklists + cross-artifact analysis; bundles por persona | Open-source (toolkit) | Padroniza o "version control do raciocínio"; SSOT de decisões | **Zero enforcement** — não tem catraca de CI nem prova de que a spec bate com o código. É só o lado-autor. |
| **Spec Kit + Copilot/agents** ([MS Learn](https://learn.microsoft.com/en-us/training/modules/spec-driven-development-github-spec-kit-enterprise-developers/) · [fundesk guia 2026](https://www.fundesk.io/spec-driven-development-github-spec-kit-guide)) | "start vibe, finish spec-driven"; presets/extensions; provisão de persona com 1 comando | Open + cloud (Copilot) | Living documentation que evolui com o código | Não fecha o loop por métrica; sem scorecard, sem armamento, sem adversário. |

### B. Policy-as-code / governança de CI
| Sistema | Features-chave | Open/Cloud | Diferencial | Limite vs oimpresso |
|---|---|---|---|---|
| **OPA + Conftest** ([OPA CI/CD](https://www.openpolicyagent.org/docs/cicd) · [secure-pipelines](https://secure-pipelines.com/ci-cd-security/policy-as-code-ci-cd-opa-rego-security-gates/)) | Política como teste (fail=pipeline fail); Rego declarativo; multi-stage (pre-commit/PR/pre-plan/post-plan/runtime audit); audit-mode antes de enforce | Open-source | Política versionada + testada + revisada como código; rollout progressivo audit→enforce | É **genérico** (config/IaC). Não tem o conceito de "spec↔código anchor", nem floor não-determinístico, nem armamento de métrica. O oimpresso já faz o audit→enforce que a [AppScale 2026](https://appscale.blog/en/blog/policy-as-code-architecture-opa-terraform-pattern-library-iac-governance-2026) recomenda. |
| **GitHub Rulesets + Merge Queue** ([branch protection](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches) · [merge queue 2026](https://tenki.cloud/blog/github-merge-queue-setup)) | required status checks; merge_group event; "require all queue entries to pass"; status-check timeout; CODEOWNERS + required-reviewer rule ([changelog 2026-02](https://github.blog/changelog/2026-02-17-required-reviewer-rule-is-now-generally-available/)) | Cloud | Serializa merges; nunca quebra o branch; checks de fila ≠ checks de PR | O oimpresso usa branch protection clássico com `enforce_admins:true` mas **`strict:false` e SEM merge queue** — exatamente o "anti-race de 2 PRs verdes isolados" que a própria ADR 0275 R1 cita como pré-requisito. |

### C. Provar que o gate "morde" (anti-teatro)
| Sistema | Features-chave | Diferencial | Limite vs oimpresso |
|---|---|---|---|
| **Mutation testing** (PIT/Stryker; [ICST Mutation 2026](https://conf.researchr.org/home/icst-2026/mutation-2026)) | Injeta mutantes (> → >=, flip booleano); teste TEM que falhar | Mede o poder-de-detecção real da suíte | O oimpresso **não roda mutation testing** — usa fixtures good/bad (`gate-selftest.mjs`), que é mais barato mas cobre só 5 catracas. |
| **Adversarial LLM test-gen** ([arXiv 2602.08146](https://arxiv.org/abs/2602.08146)) | 2 agentes (gerador de teste × gerador de mutante); +8.56% fault detection | Loop adversarial automatizado | O `sdd-avaliador-processo.js` é a versão **organizacional** disso (7 skeptics × estado-real) — mais raro/avançado que o mercado tem; o que falta é o nível-código (mutation). |

**Achado-mãe da pesquisa:** ninguém no mercado open combina (SDD-autor) + (policy-as-code enforcement) + (armamento de métrica) + (adversário recorrente). O oimpresso já tem os 4 conceitos — falta **ligar o enforcement** no último.

---

## 2. Matriz de capacidades (oimpresso × best-of-class)

Legenda: ✅ feito-verificado · 🟡 parcial/advisory · 🔴 ausente/ilusório · — n/a

| # | Dimensão | oimpresso | Spec Kit | OPA/Conftest | GH Rulesets+MQ | Evidência (oimpresso) |
|---|---|---|---|---|---|---|
| 1 | Spec→Plan→Tasks→Implement | ✅ | ✅ | — | — | skills `sdd-fase-1/2/2a` + workflows |
| 2 | Anchor spec↔código (machine-parseable) | 🟡 | 🔴 | 🔴 | — | ADR 0273; `anchor-lint.mjs`; coverage **7.5%** |
| 3 | Scorecard único versionado | ✅ | 🔴 | 🟡 | — | `governance/sdd-scorecard.json` (12 métricas) |
| 4 | Armamento de métrica (3 medições) | ✅ | 🔴 | 🔴 | — | ADR 0275 §3; **conceito raro no mercado** |
| 5 | Composta v1/v2 regimes não-comparáveis | ✅ | 🔴 | 🔴 | — | ADR 0275 §4 |
| 6 | Política versionada + testada | ✅ | 🔴 | ✅ | — | 80 workflows, `gates-registry.json`, fixtures |
| 7 | Gate selftest (prova que morde) | 🟡 | 🔴 | 🔴 | — | `gate-selftest.mjs` cobre **5/~18** catracas |
| 8 | Mutation testing (nível código) | 🔴 | 🔴 | 🔴 | — | ausente — gap vs ICST 2026 |
| 9 | Avaliador adversarial do processo | ✅ | 🔴 | 🔴 | — | `sdd-avaliador-processo.js` 7 skeptics |
| 10 | Verifica estado-real (não o plano) | ✅ | 🔴 | 🔴 | — | método CONTEXTO do avaliador |
| 11 | Required status checks no main | ✅ | — | 🟡 | ✅ | 17 contexts, `enforce_admins:true` |
| 12 | enforce_admins (sem bypass) | ✅ | — | — | ✅ | live 2026-06-21 |
| 13 | Merge queue (anti-race) | 🔴 | — | — | ✅ | `strict:false`, sem MQ |
| 14 | Required reviews ≥1 | 🔴 | — | — | ✅ | `required_approving_review_count:0` |
| 15 | CODEOWNERS paths sensíveis | ✅ | — | — | ✅ | `.github/CODEOWNERS` (só Wagner = owner válido) |
| 16 | Promoção advisory→required faseada | ✅ | — | 🟡 | 🟡 | ADR 0261/0271/0275 calendário |
| 17 | Calendário c/ critério pré-escrito | ✅ | 🔴 | 🔴 | 🔴 | ADR 0275 §5 — **supera o mercado** |
| 18 | Máx 1 promoção/semana + Wagner flip | ✅ | — | — | — | ADR 0275 §5 (anti promotion-fatigue) |
| 19 | Demoção exige PR+ADR (anti-invisível) | ✅ | — | — | 🔴 | ADR 0275 §5.5 |
| 20 | Watchdog de protection-drift | 🟡 | 🔴 | 🔴 | 🔴 | `protection-drift.mjs` advisory (janela cega ~24h) |
| 21 | Baseline anti-stale (1ª medição real) | ✅ | 🔴 | 🔴 | — | ADR 0275 §baseline |
| 22 | Floor não-determinístico domado | ✅ | — | — | — | `nightly-floor.json` interseção ≥2 runs |
| 23 | **Enforcement REAL de gate SDD** | 🔴 | 🔴 | ✅ | ✅ | **0 dos 17 required são SDD** — o gap central |
| 24 | Gates de teatro removidos | ✅ | — | — | — | ADR 0271 D-3 (jana-ragas desarmado etc) |
| 25 | Hooks bloqueadores locais | ✅ | — | — | — | `block-automem.ps1`, preflight |

---

## 3. Nota % ponderada por sub-área (com pesos e fórmula)

Fórmula: `nota_global = Σ(nota_subárea × peso) ÷ Σ pesos`. Pesos refletem o que destrava o resto (enforcement real e gates-que-mordem pesam mais — alinhado ao peso ×1.8 que o próprio avaliador dá a FV/Fase2b).

| Sub-área | Peso | Nota | Evidência (link + nota curta) |
|---|---|---:|---|
| **A. Cobertura de gates** | 1.0 | **88** | 80 workflows, 62 classe `gate` em `gates-registry.json`; 17 required cobrem Tier-0 (multi-tenant, PII, append-only, fiscal). Largura excelente; o que falta é profundidade SDD. |
| **B. Enforcement real no main** | 1.8 | **74** | `gh api` 2026-06-21: 17 required + `enforce_admins:true`. Forte em catástrofe (Pest/build/PII/business_id). **Penalizado**: `strict:false` (anti-race ausente), reviews=0, **zero required é SDD-específico**. |
| **C. Gates que MORDEM (vs teatro)** | 1.8 | **62** | `gate-selftest.mjs` prova fixture good/bad em **só 5** catracas; ADR 0271 matou 1 teatro (jana-ragas). Mas D2/D3 RAGAS, G7/G8 e Trilha-C são **forma, não correção** (§5). |
| **D. Promoção advisory→required** | 1.3 | **80** | ADR 0275 §5 = calendário com critério objetivo pré-escrito por gate, máx 1/semana, Wagner-flip, demoção-via-ADR. **Supera o mercado.** Penalizado: ainda 0 promoções SDD executadas (cadeia travada na origem). |
| **E. Avaliação adversarial do processo** | 1.3 | **90** | `sdd-avaliador-processo.js` (7 skeptics, estado-real-em-git, "a suite mente"); rodado 2026-06-20 (30 threads). **Raro/superior ao best-of-class.** -10 por não rodar em cadência fixa automatizada. |
| **F. Ergonomia pro dev** | 1.0 | **70** | always-run+short-circuit (ADR 0282) evita "Expected-waiting"; hooks WARN antes de bloquear (red-first advisory). Penalizado: `module-grades-gate` ruidoso (~25 falsos red/mês), backlog scope-guard, sem merge queue → fricção de race. |

**Cálculo:** (88×1.0 + 74×1.8 + 62×1.8 + 80×1.3 + 90×1.3 + 70×1.0) ÷ (1.0+1.8+1.8+1.3+1.3+1.0)
= (88 + 133.2 + 111.6 + 104 + 117 + 70) ÷ 8.2 = **623.8 ÷ 8.2 = 76.1 … ajustado.**

> **Reconciliação com o adversário (65/100):** a nota 76 é da **arquitetura de governança** (desenho + cobertura + processo de promoção + adversário). A nota 65 do avaliador 30-threads é da **execução do programa SDD** (anchors, harness, knowledge). Como o briefing pede "maturidade do processo de governança" e não "% do plano SDD entregue", pondero 60/40 entre as duas (governança/execução): **0.6×76.1 + 0.4×65.2 = 45.7 + 26.1 = 71.8**, arredondado pra baixo por conservadorismo adversarial (o eixo C "morde" ainda é fraco e é o coração da tese) → **nota composta final: 68/100.**

---

## 4. Top 10 gaps priorizados (impacto × esforço)

Esforço em dev-days recalibrados (ADR 0106: fator 10x IA-pair + margem 2x). Matriz: P0 = alto impacto/destrava cadeia; P1 = alto impacto; P2 = médio; P3 = baixo.

| # | Gap | Impacto | Esforço | ROI | Prio | Referência mercado |
|---|---|---|---:|---|---|---|
| 1 | **Transporte floor CT100→main + ler no scorecard** (mata `full_suite_pass_rate=not_yet_measured`; destrava R1→C2→T1→T2→A10→G3, a cadeia inteira) | Altíssimo | 1.5d | ⭐⭐⭐⭐⭐ | **P0** | OPA audit→enforce |
| 2 | **Instrumentar pcov no CI** (`coverage:none`→medido; arma C2) | Alto | 0.5d | ⭐⭐⭐⭐⭐ | **P0** | mutation/coverage gates |
| 3 | **Baseline-que-engole-regressão** (MemCofre #2848 entrou verde; ghost_count 14→16 sem bloqueio) — exigir refutador no PR que MEXE no baseline, não só no lote IA | Altíssimo | 1.5d | ⭐⭐⭐⭐⭐ | **P0** | policy-as-code "review do baseline" |
| 4 | **Promover 1º gate SDD a required** (foundation-ratchet Q1 OU design-index-gate, já always-run #3114) — provar que a tese "catraca que impede regressão" existe na prática | Altíssimo | 1d | ⭐⭐⭐⭐ | **P1** | GH required checks |
| 5 | **Reanimar G7/G8** (migration `mcp_sdd_scorecard_history` nunca aplicada em prod → 0 rows, linha SDD nunca no brief) | Alto | 1d | ⭐⭐⭐⭐ | **P1** | scorecard history |
| 6 | **Matar RAGAS-de-teatro D2/D3** (baseline=0 → gate incapaz de falhar; faithfulness=1.0 tautologia) — desarmar até medição real, como manda ADR 0275 | Alto | 1d | ⭐⭐⭐ | **P1** | adversarial CI |
| 7 | **Ampliar gate-selftest de 5→todas as ~18 catracas required** (o vigia-dos-vigias cobre só 4-5; promover G6 só depois) | Médio-Alto | 1.5d | ⭐⭐⭐ | **P2** | mutation testing |
| 8 | **Merge queue OU `strict:true`** (anti-race de 2 PRs verdes isolados — pré-req do próprio R1 da ADR 0275) | Médio | 1d | ⭐⭐⭐ | **P2** | GH merge queue 2026 |
| 9 | **Fechar janela cega do protection-drift** (~24h: detecta demoção na manhã seguinte, não previne) — webhook on protection-change ou check-run síncrono | Médio | 1d | ⭐⭐ | **P2** | rulesets audit log |
| 10 | **Resolver demoção do module-grades-gate** (ADR 0271 D-4: ~25 red/mês em PRs não-relacionados; métrica composta ruidosa) | Médio | 0.5d | ⭐⭐ | **P3** | sinal vs ruído |

---

## 5. Gates que podem ser TEATRO (não mordem) — investigação

Investiguei a hipótese "a suite mente" contra o código real. Achados:

1. **`full_suite_pass_rate` = mentira benigna mas mentira.** O scorecard commitado em `main` diz `not_yet_measured` (`sdd-scorecard.mjs`), mas o CT100 salvou 15+ runs e a branch órfã `governance/nightly-floor` tem floor=274. **Baseline real existe, scorecard não o lê** (falta transporte — ADR 0279 PR-2 pendente). É a métrica-mãe parada por falta de credencial de push. **Honestidade salva**: reporta `not_yet_measured`, não finge verde.
2. **D2/D3 RAGAS = teatro estrutural.** Baseline=0 → o gate é **matematicamente incapaz de falhar** (D2). D3 com faithfulness=1.0 fixo = tautologia. Forma, não correção. ADR 0275 §3 manda desarmar até medição real — mas o canário ainda existe dando sinal verde falso.
3. **Trilha C `recall_eval_violations` = hardcoded `notYet`.** Golden set nunca criado; flag OFF; `--mode=real` nunca rodou. Métrica que nasce morta.
4. **`anchored_dead` (15 anchors) = anchors que MENTEM** — apontam pra path que não existe mais; o lint é advisory então mergeiam amarelo (`anchor-lint.mjs` não está nos required).
5. **Quarentena cosmética (Frente C, 14/27).** `@group('legacy-quarantine')` sem `--exclude-group` → o teste **ainda roda no floor** (infla skipped +823). Rótulo de quarentena sem efeito de quarentena.
6. **`dSIH ratchet vs baseline` ERA required-zumbi** — context required sem produtor nenhum. **JÁ CORRIGIDO** (#3109 removeu do vivo + reconciliou baseline). Bom precedente de auto-cura.
7. **Baseline-por-módulo grandfather** (o pior): #2848 shipou seu próprio `knowledge-ghosts-baseline/MemCofre.json` → a catraca reportou "0 NOVOS" pra um ghost recém-nascido. O baseline **legitimou a regressão** em vez de pegá-la. É teatro reverso: o gate roda, é verde, e a regressão passou.

**Veredito anti-teatro:** o oimpresso é **honesto sobre seu teatro** (reporta `not_yet_measured`, tem refutador, tem o adversário 30-threads) — o que já o coloca acima de 90% dos sistemas. Mas os itens 2/3/7 são teatro ATIVO (sinal verde que não significa nada) e devem ser desarmados/consertados, não promovidos.

---

## 6. Decisão estratégica: CONSOLIDAR (não EVOLUIR)

**CONSOLIDAR.** O design de governança do oimpresso já é estado-da-arte-ou-superior em 4 conceitos que o mercado open não combina (armamento de métrica, adversário recorrente, calendário com critério pré-escrito, honestidade de medição). EVOLUIR — reescrever em OPA/Rego ou trocar o motor — seria **regressivo**: perderia o armamento, o floor-domado e o avaliador adversarial, que nenhum framework pronto entrega, em troca de um motor genérico de config-policy que não conhece "anchor spec↔código". O trabalho que resta é **execução do último elo** (ligar enforcement de 1 gate SDD, transportar o floor, matar o teatro ativo), não troca de paradigma. A casa foi construída e os alarmes instalados; falta ligar 1 alarme do modo "anota no caderno" pro modo "tranca a porta" — e provar, num counterfactual, que ele de fato tranca.

---

## 7. Roadmap curto (3 ondas, recalibrado)

**Onda 1 — DESTRAVAR (3-4 dev-days) · meta: 1ª métrica SDD `measured` + 1º gate SDD `required`:**
- P0-1 transporte floor CT100→main (`[skip ci]` push, ADR 0279 Opção A) + scorecard lê.
- P0-2 pcov no CI.
- P0-3 refutador no PR que mexe em baseline (mata o grandfather de regressão).
- P1-4 **counterfactual de promoção**: armar foundation-ratchet OU design-index-gate como required em branch de teste, provar que 1 PR-regressão é bloqueado, então flip real (1/semana, Wagner-flip).

**Onda 2 — HONESTAR (2-3 dev-days) · meta: zero teatro ativo:**
- P1-5 reanimar G7/G8 (migrate em prod + ≥1 row + linha SDD no brief).
- P1-6 desarmar D2/D3 RAGAS e Trilha-C até medição real (parar de dar verde falso).
- P2-7 gate-selftest 5→18 catracas (pré-req de promover G6).

**Onda 3 — ENDURECER (2-3 dev-days) · meta: anti-race + anti-ruído:**
- P2-8 merge queue ou `strict:true`.
- P2-9 fechar janela cega do protection-drift.
- P3-10 resolver demoção module-grades-gate (ADR 0271 D-4).

**Métrica de saturação (onde parar de subir):** quando (a) ≥3 gates SDD forem required E mordendo (provado por counterfactual), (b) composta SDD do scorecard atingir v2 (10/10 armadas) estável por 1 quarter, e (c) o avaliador adversarial der ≥85 por 2 rodadas seguidas — parar de promover. Acima disso o custo de manter 10+ fontes vivas supera o ganho marginal (a própria ADR 0275 §reabrir reconhece isso).

---

## 8. Surpresas

**Positiva (oimpresso > mercado):**
1. **Regra de armamento de métrica** (ADR 0275 §3 — 3 medições válidas antes de punir). Nenhum framework SDD/policy-as-code open tem isso; é a cura precisa pro precedente visual-regression (required nascido vermelho mergeando 2× em 24h).
2. **Avaliador adversarial recorrente que verifica estado-real-em-git** (`sdd-avaliador-processo.js`) — o equivalente organizacional do adversarial-LLM-test-gen do [arXiv 2602.08146](https://arxiv.org/abs/2602.08146), mas aplicado ao processo inteiro, não só a unit tests. Raríssimo.
3. **Honestidade institucionalizada**: o sistema prefere `not_yet_measured` a fingir verde, e tem 3 defesas (avaliador + refutador G5 + reprodução) contra "declarar vitória sobre estrutura-pronta". A própria nota 65 do auto-avaliador é prova de que o sistema não mente pra si mesmo.

**Negativa (mercado > oimpresso):**
1. **Enforcement real de SDD: 0 dos 17 required são gates SDD.** OPA/Conftest e GH rulesets já enforçam política de verdade hoje; o oimpresso mede tudo e governa nada na camada SDD — a regressão MemCofre #2848 entrou verde no main. É o gap-mãe.
2. **Sem merge queue / `strict:false`** — o "anti-race de 2 PRs verdes isolados" que a própria ADR 0275 R1 lista como pré-requisito ainda não existe; o mercado (GH merge queue 2026) já resolveu.
3. **`required_approving_review_count:0`** — review humano não é obrigatório (correto enquanto o time não tem write, mas é um buraco até lá; o mercado usa required-reviewer rule + CODEOWNERS com count≥1).

---

### Arquivos-chave de evidência
- `.claude/workflows/sdd-avaliador-processo.js` — 7 skeptics adversariais (linha 21-29 streams; 49-61 prompt anti-teatro)
- `memory/decisions/0275-scorecard-sdd-canonico-10-metricas-calendario-promocoes.md` — armamento §3, composta §4, calendário §5
- `memory/decisions/0271-revisao-gates-ci-estado-real-required-e-subtracao-segura.md` — D-1 required real, D-3 subtração de teatro
- `memory/decisions/0279-sdd-medir-governar-floor-nightly.md` — transporte floor (P0-1)
- `governance/sdd-scorecard.json` — `full_suite_pass_rate`/`coverage_pct` = `not_yet_measured`
- `governance/required-checks-baseline.json` — 17 contexts (reconciliado com vivo #3109, diff=0 em 2026-06-21)
- `scripts/governance/gate-selftest.mjs` — cobre 5 catracas (gap: ~18 required)
- `memory/sessions/2026-06-20-sdd-avaliacao-30threads.md` — auto-avaliação 65/100 (execução)
- live `gh api .../branches/main/protection` 2026-06-21 — `enforce_admins:true`, `strict:false`, reviews count 0
