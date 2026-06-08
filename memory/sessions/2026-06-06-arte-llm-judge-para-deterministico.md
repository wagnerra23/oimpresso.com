---
date: "2026-06-06"
topic: "Inventário de tudo que é julgado-por-LLM no oimpresso → o que vira teste determinístico, o que fica LLM-judge"
related_adrs: [0255, 0239, 0236, 0230, 0216, 0114, 0107, 0093, 0106]
---

# Estado-da-arte — LLM-judge → determinístico: o inventário e o framework de decisão

> Pedido Wagner (2026-06-06): "o que mais eu tenho julgamento por IA que posso mudar pra determinístico? o que mais dá pra transformar em teste em vez de julgamento? E se vai ser a melhor escolha?"
> Modelo de referência: **ADR 0255** (charter = intenção LLM-judge + design-spec.json = estrutura teste determinístico). Padrão: separar o núcleo OBJETIVO/derivável (vira teste) do resíduo SUBJETIVO (fica LLM).

## TL;DR

Inventário do que hoje é julgado-por-LLM no oimpresso e o que dá pra virar teste determinístico (mais barato, mais estável, sem drift). Regra: extrair o núcleo objetivo/derivável pra asserção determinística e deixar só o resíduo genuinamente subjetivo pro LLM-judge. Pesquisa (5 fontes 2025-2026) + mapa dos alvos internos + framework de decisão "vira teste vs fica LLM".

---

## 1. PESQUISA — o consenso 2025-2026 (sem contaminar com "como nós fazemos")

5 fontes de referência, mecanismo concreto:

| Player / fonte | Mecanismo concreto | Por que é referência |
|---|---|---|
| **Future AI — "Eval Floor" (det. metrics 2026)** | Camada determinística filtra 30-60% das falhas (JSON quebrado, citação faltando, tool-call malformado, frase banida) a custo ~zero/request; tokens de juiz só vão pros casos que exigem raciocínio. "Quando o modo de falha é estrutural, determinístico vence em custo e estabilidade." | Cunhou o "Hybrid Norm": rubrica LLM **junta-se** ao teste determinístico, não o substitui. É exatamente o split ADR 0255. |
| **Position-bias study (ACL/IJCNLP 2025, arXiv 2406.07791)** | Mediu position bias sistemático em LLM-judge: a escolha do modelo-juiz tem MAIOR impacto no viés que complexidade/tamanho/qualidade. Soma-se self-preference, verbosity, familiar-knowledge bias. | Prova empírica de que LLM-judge é ruidoso onde a resposta é objetiva — usar juiz pra contar/checar presença é desperdício + risco de flaky. |
| **axe-core / Deque** | Regras "highly scoped, explicit, deterministic" — evita de propósito critérios que exigem julgamento (ordem de foco lógica). Pega ~57% dos WCAG automaticamente; marca "incomplete" onde não tem certeza. | A11y é o caso-ouro de "subjetivo virou determinístico parcial": ~57% determinístico + ~43% fica humano/LLM. Modelo do split honesto. |
| **Design-token linting (Atlassian/Stylelint/WordPress Gutenberg)** | ESLint/Stylelint custom rules pegam hex hardcoded, uso errado de token, naming — AST + token-matching é "solved problem". Pre-commit + CI por PR. | "Quando as regras são estáticas e você sabe exatamente o que é 'correto', escreva um linter e rode no CI." Conformidade a registry = determinístico sempre. |
| **Cost-at-scale (arXiv 2512.01232 + AWS caching)** | LLM-judge custa 50-500× um classificador por call + centenas de ms de latência. Caching semântico corta custo mas não remove o viés. | O custo é real: nosso `pr-ui-judge` é ~$0.034/PR (barato) MAS roda julgamento em dims que grep resolve de graça e sem viés. |

**Conclusão da pesquisa (o "Hybrid Norm"):** ninguém sério substitui LLM-judge por determinismo total nem vice-versa. O estado-da-arte é **shift-left do que é estrutural** (vira gate determinístico barato, reproduzível, sem viés) e **reservar tokens de juiz pro que é genuinamente semântico/estético**. Determinizar o subjetivo = falso determinismo (pior: dá falsa confiança).

---

## 2. COMPARA — inventário do que é LLM-judged no oimpresso hoje

Auditei origin/main fresco. **Descoberta central: o oimpresso já está MUITO à frente do mercado neste split** — não é um repo onde tudo é julgado-por-LLM. Vários mecanismos já são híbridos ou já-determinizados:

| Mecanismo | O que julga | Estado hoje | Já-determinizado? |
|---|---|---|---|
| `score-mechanized.mjs` (GOLDEN-REFERENCE) | 10 regras R1-R10 de tela | **7 de 10 já são regex puro** (R1 hex, R2 native els, R3 localStorage prefix, R4 svg/icon-lib, R6 emoji, R7 status-fill, R9 `<main>`). Só R5 gradient / R8 PT-BR / R10 overflow-chain ficam LLM. | ✅ 70% já feito — modelo a copiar |
| `review-gen.mjs` (`design:review`, 157 reviews) | `<Tela>.review.md` backlog + nota | **Gerador determinístico** ancorado em `measured_against_sha`. A Fase 2 (juiz LLM R5/R8/R10 + nota holística + best-of-class) é refino PAGO, opt-in. | ✅ núcleo determinístico, resíduo LLM opt-in |
| `governance:audit` (ADR 0216, DriftChecker) | 14+ checkers (multi-tenant scope, CVE, ADR link-rot, channels orphan, flag zombie) | **100% SQL/regex/AST — zero LLM.** Framework plugável `DriftCheckerInterface`. | ✅ totalmente determinístico (referência interna) |
| `scorecard.mjs` (ADR 0236, themes/modules/governance) | Nota 0-100 de maturidade | **Híbrido com ancoragem anti-gaming:** nota LLM ANCORADA em evidência objetiva do repo (countPersonas, path-checks) + ratchet (Invariante A) + RTM (Invariante B). | ⚠️ híbrido bom — ancorado mas dims ainda LLM |
| `jana-ragas-runner.py` (RAGAS, Jana) | faithfulness + answer_relevancy de respostas Jana | Métrica numérica com **threshold de regressão vs baseline** (delta_pct). MAS a métrica em si é computada por LLM-judge (RAGAS interno). | ⚠️ gate determinístico sobre número LLM-gerado |
| **screen-grade** (skill, 16 dims, 222 telas) | Maturidade de tela /100 | LLM julga as 16 dims. Dims 16 (Pré-Flight: charter? só `@/Components/ui`? tokens v4? zero anti-padrão) + A11y + i18n + Performance + Internal-consistency são **estruturais**. Aesthetic-usability, Cognitive-load, Brand-confidence, Microcopy = subjetivas. | ❌ ainda LLM-monolítico — **maior alvo** |
| **PR UI Judge** (`pr-ui-judge.yml` + `PrUiJudgeAgent`, 9 dims) | UI de PR vs Constituição v2 | LLM (Sonnet 4.5). **6 das 9 dims são grep-áveis:** tokens_semanticos, componentes_shared, atalhos_jk_cmdk, localStorage_prefix, lucide_only, anti_padroes_ap1_ap8. Só hierarquia_4_camadas, pt_01_slot_adherence, pt_br_voice_tone exigem semântica. | ❌ default-OFF, mas 6/9 redundantes com regex |
| **mwart-comparative** (15 dims, 68 visual-comparison.md) | Tela vs estado-da-arte | Dims 4/6/7/8 (iconografia/atalhos/persistência/shared) = regex puro. Dims 9/10/11/12 (px/gap/cor-warm/microinteração) = **AST-extraível** (valores Tailwind concretos). Dims 2/5/13/14/15 = subjetivo real. | ❌ híbrido latente — 4 det + 4 AST + 5 LLM |

**Distância vs estado-da-arte:** CURTA. O oimpresso já internalizou o "Hybrid Norm" em 4 dos 8 mecanismos (score-mechanized, review-gen, governance:audit, scorecard ancorado). O gap é nos **3 grandes scorecards de tela** (screen-grade, PR-UI-Judge, mwart-comparative) que ainda julgam por LLM dimensões que o próprio `score-mechanized.mjs` já prova serem regex-áveis. **Onde batemos o mercado:** o ratchet+RTM+ancoragem-anti-gaming do ADR 0236 é mais sofisticado que o "deterministic floor" que a Future AI descreve. Onde estamos atrás: não há ainda UM gate determinístico unificado que alimente os scorecards (o ADR 0255 design-spec.json é o primeiro tijolo disso).

---

## 3. AVALIA — veredito por mecanismo + o que falta

### Tabela de veredito (o coração do pedido)

| Mecanismo | Núcleo OBJETIVO extraível | Resíduo SUBJETIVO (fica LLM) | Veredito | Impacto × Esforço |
|---|---|---|---|---|
| **screen-grade (16 dim)** | Dim 16 Pré-Flight, A11y (axe!), i18n PT-BR presence, Performance budget, Internal-consistency (token/registry), Mobile-fit breakpoints | Aesthetic-usability, Cognitive-load, Affordance, Brand-confidence, Microcopy, Discoverability | **HÍBRIDO split** (5-6 dims → determinístico via design-spec+axe; 9-10 ficam LLM) | ALTO × MÉDIO |
| **PR UI Judge (9 dim)** | tokens_semanticos, componentes_shared, atalhos_jk_cmdk, localStorage_prefix, lucide_only, anti_padroes_ap1_ap8 (6 dims) | hierarquia_4_camadas, pt_01_slot_adherence, pt_br_voice_tone (3) | **HÍBRIDO split** — mover 6/9 pro `ui-lint.yml`, juiz só nas 3 | ALTO × BAIXO |
| **mwart-comparative (15 dim)** | 4/6/7/8 (regex) + 9/10/11/12 (AST Tailwind px/gap/cor) | 2/5/13/14/15 (hierarquia, estados, ref-aprovada, benchmark, persona) | **HÍBRIDO split** — 8 viram design-spec checks, 5-7 ficam | MÉDIO × MÉDIO |
| **scorecard.mjs (ADR 0236)** | já ancorado em evidência; expandir checks objetivos por scope | nota de maturidade qualitativa | **FICA HÍBRIDO** (já é o estado-da-arte; só ampliar ancoragem) | MÉDIO × BAIXO |
| **RAGAS (Jana)** | threshold de regressão (já determinístico no gate) | faithfulness/relevancy semânticos | **FICA LLM** (a métrica É semântica; gate sobre ela já é determinístico) | — (não mexer) |
| **review-gen / score-mechanized** | já 70% regex | R5/R8/R10 holística | **FICA HÍBRIDO** (modelo a replicar, não a mudar) | — (referência) |
| **governance:audit (ADR 0216)** | 100% já SQL/regex | nenhum | **FICA DETERMINÍSTICO** (já é) | — (referência) |

### Top candidatos a determinizar (rankeados impacto×esforço, priorizando reuso de infra)

1. **PR UI Judge → mover 6/9 dims pro `ui-lint.yml`** — ALTO impacto, BAIXO esforço. As 6 dims (tokens, shared-components, atalhos, localStorage-prefix, lucide-only, anti-padroes AP1-AP8) **já são exatamente os regex de `score-mechanized.mjs`**. Reusa infra 100%. Resultado: juiz LLM roda só 3 dims semânticas → ~⅓ do custo/viés, gate determinístico bloqueia o resto sem flakiness. **Pré-req: nenhum bloqueante** (score-mechanized já existe; é wiring no workflow).
2. **screen-grade → extrair design-spec dims (ADR 0255 estende)** — ALTO impacto, MÉDIO esforço. Dim 16 (Pré-Flight) + A11y já podem virar checks: A11y via **jest-axe/axe-core determinístico** (infra nova mas padrão-mercado), Pré-Flight via `score-mechanized`. O `<Tela>.design-spec.json` do ADR 0255 é o veículo natural — screen-grade lê o spec (objetivo) e só julga o resíduo. **Pré-req: ADR 0255 design-spec-gen landeado** (já aceito, branches `poc/design-spec-gen` + `feat/design-spec-gate` existem).
3. **mwart-comparative → 8 dims viram design-spec checks** — MÉDIO impacto, MÉDIO esforço. Dims 4/6/7/8 (regex) + 9/10/11/12 (AST Tailwind) caem no mesmo `design-spec.json`. Reduz o artefato visual-comparison.md a só as 5-7 dims de julgamento real. **Pré-req: #2 (design-spec maduro).**

### O que NÃO determinizar (o "melhor escolha" honesto)

- **RAGAS faithfulness/answer_relevancy** — a pergunta "essa resposta da Jana é fiel/relevante?" É genuinamente semântica. O gate sobre o número (delta vs baseline) já é determinístico; determinizar a métrica em si seria reinventar RAGAS pior. **Fica LLM.**
- **Dims estéticas/UX** em todos os scorecards: Aesthetic-usability, Cognitive-load, Brand-confidence, Microcopy tone, "é bonito?", "ajuda o usuário?", fit-de-persona, benchmark externo. Determinizar isso = falso determinismo. axe-core ensina: ~43% dos WCAG e 100% da estética ficam fora do alcance de regra. **Ficam LLM.**
- **ADR 0239 "regressão julgada por IA"** — a regressão *visual/semântica* (drawer sobre modal, layout que viola PT-01 com componentes corretos) é exatamente o que grep não vê. **Fica LLM** — é o caso de uso legítimo do juiz.
- **scorecard.mjs nota de maturidade** — já está no ponto certo (ancorada + ratchet). Mexer = regressão.

### Roadmap

- **Onda 1 (agora, baixo esforço):** PR UI Judge — mover 6/9 dims pro `ui-lint.yml`/`score-mechanized`. Reusa 100% infra. Gate determinístico + juiz só nas 3 semânticas.
- **Onda 2 (pós design-spec maduro):** screen-grade lê `design-spec.json` (ADR 0255) pras dims estruturais + adicionar **jest-axe** pro A11y determinístico. Juiz LLM só nas 9-10 dims subjetivas.
- **Onda 3:** mwart-comparative consome o mesmo design-spec → 8 dims saem do artefato visual; sobra só o julgamento real.
- **Invariante (ADR 0230):** cada dim determinizada nasce com teste anti-regressão (ratchet) + cita memória de origem (RTM).

### Referências
- [Future AI — Deterministic Eval Metrics 2026](https://futureagi.com/blog/deterministic-llm-evaluation-metrics-2026/) (Hybrid Norm, det. floor)
- [Judging the Judges: Position Bias in LLM-as-a-Judge (arXiv 2406.07791)](https://arxiv.org/abs/2406.07791)
- [axe-core (Deque) — deterministic a11y engine](https://github.com/dequelabs/axe-core) · [jest-axe](https://github.com/NickColley/jest-axe)
- [Atlassian — ESLint plugin ensure design token usage](https://atlassian.design/components/eslint-plugin-design-system/ensure-design-token-usage/)
- [LLM-as-Judge cost at scale (arXiv 2512.01232)](https://arxiv.org/html/2512.01232v1)
- [Rubric-based evals & LLM-as-a-Judge — biases (Masood, 2026)](https://medium.com/@adnanmasood/rubric-based-evals-llm-as-a-judge-methodologies-and-empirical-validation-in-domain-context-71936b989e80)

---

## Recomendação final

**Comece pelo PR UI Judge (Onda 1) — alto-impacto, baixo-esforço, sem pré-req bloqueante.** É o único candidato onde a infra determinística (`score-mechanized.mjs`, 6 das 9 dims já são regex idênticos) já existe pronta: é wiring, não construção. Determiniza o estrutural (sem viés, sem custo, reproduzível no PR) e libera o juiz LLM pra só 3 dims genuinamente semânticas — reduzindo custo/viés a ~⅓ e eliminando flakiness do gate. Os scorecards de tela (screen-grade, mwart) vêm depois, ancorados no `design-spec.json` do ADR 0255 + jest-axe.

**Próxima ação hoje:** abrir comparação dim-a-dim entre as 9 dims do `PrUiJudgeAgent.php` e os regex de `score-mechanized.mjs` (R1-R10) + `ui-lint.yml`, confirmar quais 6 são redundantes, e rascunhar a ADR que move-as pro gate determinístico (juiz LLM reduzido a hierarquia_4_camadas + pt_01_slot + pt_br_voice).
