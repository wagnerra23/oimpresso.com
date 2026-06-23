---
date: "2026-06-23"
topic: "Estado-da-arte — camada Humano + LLM-Judge da validação de tela (L3): tirar o olho do Wagner do gargalo sem o judge alucinar ok"
authors: [C]
type: session
---

# Estado-da-arte — Camada Humano + LLM-Judge da validação de tela (L3)

> Sessão `estado-da-arte` · 2026-06-23 · worktree `detector-wt`
> Missão: desenhar a fundo a camada onde **o olho do Wagner deixa de ser gargalo** e o **LLM-judge para de alucinar "ok"**.
> Pesquisa Fase 1 feita LIMPA (sem ler memory/ antes) — só depois comparei com o oimpresso.

---

## 1. PESQUISA — os melhores (Fase 1, sem contaminar)

Convergência forte entre produto (VRT comercial) e papers 2024-2026. Resumo em 5 linhas:

| Player / fonte | Como resolve (mecanismo concreto) | Por que é referência |
|---|---|---|
| **Applitools Visual AI** | Não compara pixel cru: classifica diff como "provável bug" vs "provável ruído" (ignore regions, match levels). Root-Cause mostra qual nó DOM/CSS mudou. Auto-maintenance replica a aprovação de 1 step pros steps similares. | Líder de mercado em VRT com IA; tira o humano do diff trivial e só leva o suspeito. |
| **Chromatic / Percy** | Baseline Git-aware (compara contra a **aprovada anterior**, não no vácuo). TurboSnap só re-snapshota o que o grafo de deps mudou. Review queue: humano só vê o que diffou; "Accept" promove a nova baseline. | Padrão de fato de VRT por componente; baseline branching + fila de revisão = human-on-uncertainty. |
| **G-Eval (Liu et al., EMNLP 2023)** | LLM decompõe o critério em passos (CoT) e pontua ponderando por log-prob. Rubrica estruturada + descrição só dos extremos (1 e 5) maximiza correlação com humano. | Método mais citado de LLM-as-judge com rubrica; supera BLEU/ROUGE por larga margem. |
| **Selective prediction / ASPEST + active learning** | Modelo abstém quando a confiança `u(x)` cruza um limiar τ → defere ao humano. Uncertainty sampling (margin/least-confidence/entropy) escolhe exatamente os casos da fronteira pro humano rotular. | Base teórica do "humano só na zona cinza"; converte confiança em triagem e em sinal de aprendizado. |
| **VLM-as-judge (surveys 2025-2026 + "VLM Judges Can Rank but Cannot Score")** | Painéis de juízes (PoLL), self-consistency (N amostras temp~0.7, maioria), swap-debias contra position bias. **Achado-chave: VLM rankeia bem mas pontua mal** — pairwise alinha com humano melhor que score absoluto; bias positivo (alucina nota alta) é sistemático. | Estado-da-arte de juiz multimodal robusto; explica POR QUE o single-shot alucina "ok" e o que corrige. |

**Os 3 princípios que saíram limpos da pesquisa:**
1. **Não julgue no vácuo** — golden reference (a aprovada anterior) é o maior ganho de confiabilidade isolado (G-Eval: tirar a referência derruba correlação de 0.666→0.591).
2. **Não confie em 1 amostra** — sampling + média de N (ou maioria de N juízes) ganha 5-8% de alinhamento com humano sobre greedy single-shot.
3. **Confiança é triagem** — o juiz tem que emitir incerteza, não só veredito; o humano entra SÓ na fronteira (selective prediction), e esses casos da fronteira são os mais valiosos pra calibrar.

---

## 2. COMPARA — o que o oimpresso já tem (Fase 2)

Li o código real, não a prosa. O oimpresso já tem **mais infra do que a média do mercado** — falta a composição.

| Dimensão (Fase 1) | Estado-da-arte | oimpresso hoje | Distância |
|---|---|---|---|
| **VRT pixel-diff** | pixelmatch + threshold tunável por componente | `PixelBaselineTest.php` (Pest Browser): pixelmatch `threshold 0.3 · maxDiffPixels 300 · maxDiffPixelRatio 1%`, núcleo-6, advisory. Baseline = snapshot **commitado** (gerado no runner CI). | **curta** — já tem pixelmatch + baseline versionada. Falta double-threshold. |
| **Golden reference no judge** | comparar contra aprovada anterior | VRT tem baseline; mas o `PrUiJudgeAgent` (gpt-4o-mini) julga **só o DIFF de código** (.tsx/.css), **sem imagem**, **sem golden**. Existe `GOLDEN-REFERENCE.md` (Sells/Create, 10 regras) mas o juiz LLM não recebe a imagem-ouro. | **longa** — o juiz é text-only e julga no vácuo visual. |
| **Self-consistency / multi-juiz** | N amostras (temp~0.7) + maioria, ou painel | Single-shot, 1 chamada, sem sampling, sem painel. | **longa** — zero. É exatamente o vetor de "alucina ok". |
| **Rubrica estruturada (G-Eval)** | critério decomposto + score-as-code | **JÁ TEM e bem feito**: 9 dims (6 determinísticas via `UiDeterministicScorer` regex + 3 semânticas LLM), score derivado do rationale ("rationale ANTES do score" — G-Eval explícito no prompt). `screen-grade` tem 16 dims niveladas Beginner→Champion. | **curta/zero** — supera o mercado aqui. ADR 0255. |
| **Confiança calibrada / abstenção** | juiz emite incerteza → defere | Não existe. Verdict é `score<60 request_changes / <85 comment / approve`. Sem banda de incerteza, sem "não sei". | **longa** — não há sinal de "isto é zona cinza". |
| **Humano só na fronteira** | review queue / human-on-uncertainty | **Invertido**: Wagner aprova SCREENSHOT 1280/1440 light+dark em **TODA** tela (ADR 0107/0114). Wagner É o detector de regressão (L-38, anti-padrão admitido). | **longa** — é o gargalo central da missão. |
| **Governança do eval** | golden congelado por quem não gera | **JÁ TEM e maduro**: `EVAL_PROTOCOL.md` (golden set congelado por [W], replay cases RC-01..06, delta judge-vs-[W] como KPI, calibração semanal 5 telas). | **zero** — isto é estado-da-arte e o mercado raramente formaliza. |

**Veredito honesto:** o oimpresso tem rubrica e governança de eval **acima** do mercado, e VRT pixel **no nível**. O que falta são as 3 peças de robustez do juiz (golden visual + multi-amostra + confiança) e a inversão do fluxo humano (de "tudo" para "só a zona cinza"). Nada disso é greenfield — tudo compõe sobre o que já existe.

---

## 3. AVALIA — o que falta, rankeado (Fase 3)

### 3a. DESENHO — Double-threshold (humano só na zona cinza)

**Reusa:** `PixelBaselineTest.php` (pixelmatch já roda, já produz `maxDiffPixelRatio`). **Adiciona:** 2 limiares + roteador de fila.

```
ratio = pixels_diferentes / pixels_totais   (já calculado pelo pixelmatch do Pest)

ratio < τ_baixo (0.1%)   → AUTO-APROVA   (ruído subpixel/anti-aliasing — não incomoda o Wagner)
ratio > τ_alto  (2.0%)   → AUTO-FALHA    (regressão óbvia — request_changes sem humano)
τ_baixo ≤ ratio ≤ τ_alto → ZONA CINZA    → fila do Wagner (só estas telas)
```

- **Limiares iniciais** (Wagner ajusta — ele é dono dos números): `τ_baixo = 0.1%` (= o `maxDiffPixelRatio 1%` atual é frouxo demais pra auto-aprovar; aperta pra 0.1%), `τ_alto = 2%`. Calibrar contra 4 semanas de série (EVAL_PROTOCOL Onda 2 já coleta) — meça quantas telas caem em cada banda; mire ≤20% na zona cinza.
- **Como o Wagner vê SÓ a zona cinza:** o job pixel-diff já sobe `pixel-diff-views` (ImageDiffView: expected|actual|diff lado-a-lado) como artifact. Adiciona um **índice de zona cinza** — uma página única (ou comentário de PR) que lista **apenas** as telas na banda, com o trio de imagens e os 2 botões (Aprovar = promove baseline / Rejeitar = request_changes). Wagner não abre 6 telas × 2 viewports × 2 temas = 24 screenshots; abre as 1-2 que diffaram na faixa duvidosa.
- **SOTA aplicado:** human-in-the-loop-on-uncertainty (ASPEST). O "Accept" do Chromatic/Percy é exatamente isto — promover a baseline é a única ação humana, e só na fila.

### 3b. DESENHO — LLM-judge robusto (para de alucinar "ok")

**Reusa:** `PrUiJudgeAgent` (rubrica 3 dims semânticas + G-Eval), `UiDeterministicScorer` (6 dims regex), `GOLDEN-REFERENCE.md` (imagem-ouro por arquétipo). **Adiciona:** 4 peças.

1. **Golden visual no prompt** — o juiz hoje é text-only. Passa de "julga diff de código no vácuo" para **VLM comparando o screenshot novo CONTRA o screenshot-ouro do arquétipo** (Sells/Create p/ form, etc.). Pesquisa: golden reference é o maior ganho isolado de confiabilidade. Requer migrar o agent de `gpt-4o-mini` text → modelo com visão (gpt-4o / Claude com imagem).
2. **Pairwise, não score absoluto** — achado VLM 2026: *"judges can rank but cannot score"*. Em vez de "dê nota 0-10 de hierarquia", pergunte **"a tela NOVA está melhor, igual ou pior que a OURO nesta dimensão?"**. Alinha muito melhor com humano e mata o bias positivo (alucinar 8/10).
3. **Self-consistency / multi-juiz** — chama o juiz **N=3-5 vezes** (temp~0.7) OU um painel pequeno de modelos disjuntos (PoLL); pega a **maioria** no verdict por dimensão. Sampling+média ganha 5-8% sobre single-shot. Variância entre as N amostras VIRA o sinal de confiança (próximo item).
4. **Saída calibrada (confiança) + swap-debias** — o veredito carrega `confianca` derivada da concordância entre as N amostras (5/5 concordam = alta; 3/5 = baixa). Em pairwise, randomiza qual imagem é "A" e qual é "B" (swap-debias) pra matar position bias (~5% documentado). Confiança baixa = **abstém → zona cinza** (compõe com 3c).

### 3c. COMPOSIÇÃO — como as duas se encaixam (o cano completo)

```
PR toca tela
   │
   ├─[L1 determinístico] UiDeterministicScorer (6 dims regex) + ui:lint  → falha dura = barra já
   │
   ├─[L2 VRT pixel] PixelBaselineTest + double-threshold
   │     ratio<τ_baixo → AUTO-APROVA ─────────────────────────────────┐
   │     ratio>τ_alto  → AUTO-FALHA  ──→ request_changes              │
   │     zona cinza ───────────────────────────────┐                 │
   │                                                ↓                 ↓
   ├─[L3 LLM-judge robusto] juiz VLM (golden visual + pairwise + N-juiz + confiança)
   │     alta confiança + "igual/melhor" → AUTO-APROVA ───────────────┤
   │     alta confiança + "pior"         → request_changes            │
   │     BAIXA confiança / incerto ──────────────┐                    │
   │                                              ↓                    ↓
   └─[HUMANO] Wagner vê SÓ a zona cinza ──────────┴──→ veredito final NO SCREENSHOT é dele
         (índice de zona cinza: trio de imagens + Aprovar/Rejeitar; promove baseline)
```

**O juiz robusto PRÉ-FILTRA o VRT:** uma tela que diffou na zona cinza de pixel mas o juiz VLM diz "igual à ouro, alta confiança" sai da fila do Wagner (era ruído de layout que o pixel pegou mas semanticamente é equivalente — o caso Applitools "ignore region"). Só o que **ambos** marcam duvidoso (ou o juiz marca baixa-confiança) sobe pro olho do Wagner.

### 3d. Ranking dos gaps

| Gap | Impacto | Esforço (IA-pair · ADR 0106) | Pré-req? |
|---|---|---|---|
| **Double-threshold no pixel-diff** (3a) | **alto** — tira o Wagner do gargalo já, sem mexer no LLM | ~2-3h IA-pair (já tem ratio + ImageDiffView; falta roteador + índice de zona cinza) | nenhum bloqueante. Pixel-diff já roda advisory. |
| **Índice de zona cinza** (página/comentário com trio + Aprovar/Rejeitar) | **alto** — é o "review queue" que o Wagner usa | ~2h IA-pair | depende do double-threshold (3a) |
| **Self-consistency N-juiz + confiança** (3b.3+3b.4) | médio-alto — mata o "alucina ok" sem trocar de modelo | ~3-4h IA-pair (loop de N chamadas + agregação de maioria + variância→confiança) | nenhum. Funciona já no gpt-4o-mini text. |
| **Pairwise contra golden** (3b.2) | médio — melhora alinhamento, exige reescrever rubrica p/ comparação | ~3h IA-pair | depende de golden visual (3b.1) se for visual; pairwise textual é independente |
| **Golden VISUAL (VLM)** (3b.1) | médio | ~4-6h IA-pair + custo (gpt-4o vs mini ~25x; ver Δcusto no UiJudgePrCommand já instrumentado) | **PRÉ-REQ: acesso a modelo com visão** — o histórico do agent mostra que o projeto OpenAI não tinha gpt-4o; resolver acesso/crédito ANTES. |

---

## RECOMENDAÇÃO FINAL

**Comece pelo double-threshold no pixel-diff (3a).** Alto-impacto-baixo-esforço, sem pré-req bloqueante: o `PixelBaselineTest` já calcula o ratio e já sobe o `ImageDiffView` como artifact — falta só rotear por 2 limiares e montar o índice de zona cinza. Isso tira o Wagner do gargalo HOJE (de "aprovar 24 screenshots por tela" para "aprovar as 1-2 que caíram na faixa duvidosa") **sem tocar no LLM** e **sem resolver acesso a modelo com visão**. O juiz robusto (self-consistency + confiança, 3b.3-4) entra em seguida como pré-filtro da fila, também sem trocar de modelo. O golden visual (3b.1) fica por último porque tem pré-req de acesso a modelo de visão.

**Próxima ação hoje:** abrir o `PixelBaselineTest.php` + o step `pixel-diff` do `visual-regression.yml` e desenhar o roteador de 3 bandas (`< τ_baixo` aprova / `> τ_alto` falha / meio = artifact "zona-cinza" separado), com `τ_baixo=0.1%` e `τ_alto=2%` como chute inicial pro Wagner calibrar contra a série de 4 semanas que o EVAL_PROTOCOL Onda 2 já coleta. Reaproveita 100% do pixelmatch existente — é roteamento, não motor novo.

> **Tier 0 lembrete:** qualquer passo aqui que toque `localStorage`/baseline por tenant tem que respeitar `business_id` (ADR 0093). O seed do VRT é `biz=1` (ADR 0101) — manter. Nenhum desenho acima vaza tenant: pixel-diff é por tela, não por dado de cliente.

---

## Fontes (Fase 1)

- [An Empirical Study of LLM-as-a-Judge: How Design Choices Impact Evaluation Reliability (arXiv 2506.13639)](https://arxiv.org/html/2506.13639v1)
- [VLM Judges Can Rank but Cannot Score: Task-Dependent Uncertainty in Multimodal Evaluation (arXiv 2604.25235)](https://arxiv.org/html/2604.25235v1)
- [G-Eval — Confident AI guide](https://www.confident-ai.com/blog/g-eval-the-definitive-guide) · [DeepEval G-Eval docs](https://deepeval.com/docs/metrics-llm-evals)
- [ASPEST: Bridging the Gap Between Active Learning and Selective Prediction (arXiv 2304.03870)](https://arxiv.org/html/2304.03870v3)
- [Uncertainty-Driven Reliability: Selective Prediction (arXiv 2508.07556)](https://arxiv.org/pdf/2508.07556)
- [Leveraging LLMs as Meta-Judges: A Multi-Agent Framework (arXiv 2504.17087)](https://arxiv.org/pdf/2504.17087)
- [Applitools Visual AI / Root Cause Analysis](https://applitools.com/docs/autonomous/visual-ai)
- [Chromatic TurboSnap + baseline review](https://www.chromatic.com/docs/turbosnap/)
- [Calibrating Scores of LLM-as-a-Judge (GoDaddy, 2025-11)](https://www.godaddy.com/resources/news/calibrating-scores-of-llm-as-a-judge)
