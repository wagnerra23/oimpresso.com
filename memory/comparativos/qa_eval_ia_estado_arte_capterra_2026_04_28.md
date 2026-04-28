# Matriz Comparativa estilo Capterra/G2 — QA / Eval de IA, ciclo de vida completo (2026-04-28)

> **Assunto:** estado-da-arte de **garantia de qualidade de IA** — frameworks, plataformas, métricas e processo, do **dataset golden até drift detection em produção**. Cruzado com nossa stack canônica (ADR 0035: laravel/ai + Vizra ADK + Mem0/Meilisearch) pra responder: **como tirar o Copiloto de fixtures e botar em produção sem queimar a Larissa do ROTA LIVRE**.
> **Data:** 2026-04-28
> **Autor:** Claude (sessão `loving-black-f3caa3`) sob direção do Wagner ("como fazer benchmark pra garantir a qualidade da IA? Estado-da-arte do ciclo de vida completo. Pesquise e crie os prints. Seja agressivo.")
> **Concorrentes incluídos:** 8 plataformas/frameworks — Vizra ADK eval (NOSSO baseline), Braintrust, LangSmith, Langfuse, Arize Phoenix, DeepEval/Confident AI, Promptfoo, Anthropic Claude Skills built-in evals
> **Decisão que vai sair daqui:** que stack/processo de eval adotar nos sprints 7-9 (após validação Larissa) — **comprar Braintrust SaaS, self-host Langfuse+Phoenix, ou ficar só com Vizra ADK eval CLI**.
> **Companion docs:** [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md) (define stack-alvo IA) · [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md) (Camada C memória) · [revisao_caminho_2026_04_27_capterra.md](revisao_caminho_2026_04_27_capterra.md) (auditoria pós-sprint 6)
> **Template usado:** [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0
> **Decisões dependentes deste doc:** ADR 0037 (roadmap Tier 7-9 LongMemEval) sai do "aceito" pra "executável" só depois desta seleção · sprint 7 (RAGAS evaluation) tem stack indefinida sem este doc.

---

## ⚠️ Distinção crítica antes de tudo

**3 camadas de eval de IA frequentemente confundidas:**

| Camada | O que avalia | Quando | Onde mora |
|---|---|---|---|
| **OFFLINE / Pre-deploy** | Modelo/prompt/agent contra **golden set fixo** | Em CI, antes de merge | `pest`/`vitest`/`promptfoo run` em GitHub Actions |
| **ONLINE / Production** | Tráfego real amostrado, judge async | Continuamente, 24/7 | Langfuse/Phoenix/Braintrust ingestion |
| **HUMAN / SME-driven** | Anotação manual subject-matter expert | Semanalmente, snapshot | Tela web do app + label studio + Comet |

**Os 3 são complementares, não substitutos.** Quem pula offline fica refém de A/B em produção (cliente serve de cobaia). Quem pula online não detecta drift (modelo se degrada em silêncio). Quem pula human acumula viés de LLM-judge (verbosity bias 15% inflation, position bias 40% GPT-4 inconsistência).

Este comparativo cobre as **3 camadas** e mapeia **ciclo de vida completo** de QA de IA.

---

## 1. TL;DR (5 frases)

1. **Hoje Oimpresso = ZERO eval de IA em produção.** Copiloto roda em `COPILOTO_AI_DRY_RUN=true` (fixtures), nenhum golden set commitado, nenhum LLM-judge online, nenhuma detecção de drift. **Risco:** primeira conversa real da Larissa que devolve hallucination = perda de confiança permanente.
2. **Diferencial real defensável:** Vizra ADK (camada B do ADR 0035) já vem com `php artisan vizra:make:eval` + 20+ assertions + LLM-as-Judge built-in PHP-nativo — vantagem brutal sobre concorrentes BR (Mubisys/Zênite/Calcgraf zero) e até globais Python-first (Braintrust/LangSmith não falam Laravel idiomático). **Mas Vizra adia L13** (sprint 3+) — temos GAP de stack até lá.
3. **Onde perdemos pra players globais (Braintrust/LangSmith/Langfuse):** sem dashboard de drift visual, sem CI/CD gating com regression baselines, sem online judge sampling 5%, sem HITL annotation UI pra Larissa anotar "essa resposta tá errada".
4. **Onde perdemos pra players verticais (RAGAS/DeepEval):** sem RAG metrics canônicos (faithfulness, context precision/recall) — quando Sprint 5 (MeilisearchDriver) entrar, vamos retornar contexto do índice e **não temos como medir se o índice tá retornando lixo**.
5. **O dilema:** **(A)** Braintrust full SaaS US$$ recorrente + lock-in cloud — tira eval do mapa em 1 sprint mas viola ADR 0036 (R$0/mês recorrente filosofia); **(B)** self-host Langfuse + DeepEval CLI + Vizra eval (quando vier) — alinha com Meilisearch-first, **R$0/mês**, mas exige 3 sprints de plumbing; **(C)** ficar só com `pest tests/` + LLM-judge manual em prompt — barato mas não escala além de Larissa. **Recomendado: B** (seção 7).

---

## 2. Players incluídos (8 plataformas/frameworks de QA de IA)

Categorizados em **3 grupos** — full-lifecycle SaaS, framework CI/CD focused, observabilidade self-host:

| Nome | URL | Tier | Tipo | Observação relevante |
|---|---|---|---|---|
| **Vizra ADK eval** (NOSSO baseline) | [vizra.ai/docs/adk](https://vizra.ai/docs/adk) | Nicho PHP | Framework + LLM-judge | `php artisan vizra:make:eval` + 20+ assertions + CSV golden set built-in. **Único PHP-native real do mercado**. Adia L13 (sprint 3+) |
| **Braintrust** | [braintrust.dev](https://www.braintrust.dev/) | Líder enterprise SaaS | Full-lifecycle | Cobre eval lifecycle completo (offline+online+HITL+release gates). **Lock-in cloud**, USD recorrente |
| **LangSmith** | [smith.langchain.com](https://smith.langchain.com/) | Líder LangChain | Trace + eval + monitor | Tracing nativo LangChain/LangGraph, framework coupling. **Per-seat pricing**, friction pra mixed-stack |
| **Langfuse** | [langfuse.com](https://langfuse.com/) | Líder open-source | Observabilidade + eval | **Self-host gratis** (feature parity), prompt mgmt, evaluator templates (hallucination/toxicity), session replay. **Vencedor open-source 2026** |
| **Arize Phoenix** | [arize.com/phoenix](https://phoenix.arize.com/) | Líder open observabilidade | Trace + drift + RAG eval | OpenTelemetry-native, **self-host Elastic License 2.0**, drift visual plots, RAG quality boards. Best-in-class pra detectar drift silencioso |
| **DeepEval / Confident AI** | [deepeval.com](https://deepeval.com/) | Framework CI/CD + cloud opcional | Testing framework | "Pytest pra LLM" + Synthesizer (7 evolutions golden set). 14+ métricas, hallucination/faithfulness/answer relevancy. CLI primeiro |
| **Promptfoo** | [promptfoo.dev](https://www.promptfoo.dev/) | Open-source CLI | Eval + red teaming + CI | YAML configs, GitHub Actions native, **red teaming** primeira classe (jailbreak, PII leak, prompt injection). Local-first |
| **Anthropic Claude Skills evals** | [anthropic.com/engineering/demystifying-evals](https://www.anthropic.com/engineering/demystifying-evals-for-ai-agents) | Native Claude | Built-in evals + benchmarks | A/B testing skills no-code, multi-agent parallel runs, blind comparators. **Só pra Claude**, não substitui platform genérica |

**Out-of-scope (mencionados em sources como follow-up):**
- **Maxim AI**, **Galileo** (full-lifecycle SaaS Tier 2 — pula porque Braintrust já cobre)
- **Helicone** (proxy-based — pula porque overlap com Langfuse, mas vale revisitar se quiser zero-SDK)
- **Patronus AI** (guardrails especializados, Lynx > GPT-4 em HaluBench — vale como add-on, não como base)
- **NVIDIA NeMo Guardrails** (Colang DSL pra safety rails — vale pra LGPD layer, decisão separada)
- **MLflow AI Monitoring** (DataBricks-leaning — overlap com Langfuse pra nosso stack)
- **OpenAI Evals** (framework do provedor — só funciona bem pra modelos OpenAI; abandonado em 2026 favor Braintrust)
- **Evidently AI** (open-source LLM observability — overlap forte com Langfuse, sem killer feature)
- **TruLens** (RAG eval — overlap com RAGAS, menos adoção)

---

## 3. Ciclo de vida completo (estado-da-arte 2026)

```
                                   ┌─────────────────────────────────────────────────┐
                                   │  TIER 0 — PRINCÍPIOS                           │
                                   │  • Eval-first: prompts/agents são código,      │
                                   │    código tem teste antes de ship.             │
                                   │  • Erro mede + reporta: error bars sempre      │
                                   │    (Anthropic statistical approach)            │
                                   │  • Online ≠ offline ≠ HITL — usar os 3.        │
                                   └─────────────────────────────────────────────────┘
                                                          │
       ┌──────────────────────────────────────────────────┴─────────────────────────────┐
       │                                                                                 │
       ▼                                                                                 ▼
┌──────────────────────────────────────────┐                  ┌────────────────────────────────────────┐
│ FASE 1 — DATASET / GOLDEN SET             │                  │ FASE 2 — DEFINIÇÃO DE QUALIDADE        │
│                                            │                  │                                         │
│ 1.1 Coleta seed de produção               │                  │ 2.1 Métricas core (RAG):               │
│ 1.2 SME (Larissa) anota "good vs bad"     │                  │   • Faithfulness ≥ 0.8 (hallucination) │
│ 1.3 Synthetic gen via DeepEval Synthesizer│                  │   • Context Precision ≥ 0.7            │
│   (7 evolutions: reasoning, multi-context,│                  │   • Context Recall ≥ 0.85              │
│    constraint, comparative...)            │                  │   • Answer Relevancy ≥ 0.8             │
│ 1.4 Validate: Silver → Gold (SME review)  │                  │ 2.2 Métricas agente:                   │
│ 1.5 Versiona em git ou platform           │                  │   • Tool selection accuracy            │
│ 1.6 Edge cases: 5+ exemplos adversariais  │                  │   • Sub-agent delegation               │
│                                            │                  │   • Multi-turn coherence               │
│ Tools: DeepEval Synthesizer, RAGAS        │                  │ 2.3 Métricas safety:                   │
│ Golden, Braintrust datasets, Langfuse     │                  │   • Hallucination rate                 │
│                                            │                  │   • PII leak (LGPD!)                   │
│                                            │                  │   • Prompt injection resistance        │
│                                            │                  │   • Toxicity / off-topic               │
│                                            │                  │ 2.4 Métricas custo:                    │
│                                            │                  │   • $/conversa                         │
│                                            │                  │   • Tokens P95                         │
│                                            │                  │   • Latência P95                       │
└──────────────────────────────────────────┘                  └────────────────────────────────────────┘
       │                                                                                 │
       └────────────────────────────────────┬───────────────────────────────────────────┘
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 3 — OFFLINE EVAL (PRE-DEPLOY)                                                      │
│                                                                                          │
│ 3.1 Unit-test style: PR → CI → DeepEval/Promptfoo/Vizra rodam golden set                │
│ 3.2 LLM-as-Judge:                                                                        │
│   • Use 1-4 scale (não 1-10) — reduz verbosity bias                                     │
│   • Swap A/B order, conta só "wins consistentes" (position bias 40%→<5%)               │
│   • Multi-judge ensemble (Claude+GPT+Gemini) pra high-stakes (custo 3-5x mas bias -30%)│
│ 3.3 Pairwise comparison: candidato vs baseline (não absoluto)                           │
│ 3.4 Statistical bars: Anthropic recomenda CI 95% sempre, n≥100 mínimo                   │
│ 3.5 Regression gates: novo PR FAILS CI se score cai >5% absoluto vs baseline            │
│ 3.6 Cheap-first: code-based graders (regex, exact match) ANTES de LLM-judge ($)         │
│                                                                                          │
│ Tools: Vizra ADK eval (PHP), DeepEval (pytest-style), Promptfoo (YAML), Braintrust    │
│        SDK, GitHub Actions matrix runs                                                   │
└────────────────────────────────────────────────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 4 — DEPLOY GRADUAL (não 100% direto!)                                              │
│                                                                                          │
│ 4.1 SHADOW MODE — duplica request, candidato roda sem mostrar pro user, log + diff     │
│ 4.2 CANARY — 5% tráfego pro candidato, monitora métricas tempo-real, ramp ou rollback  │
│ 4.3 A/B SPLIT — 50/50 com user-level statistical significance, Bayesian preferido      │
│ 4.4 Rollout 100% só quando: shadow OK + canary 5%→25%→50%→100% sem regressão           │
│                                                                                          │
│ Tools: Traceloop, Helicone proxy, custom feature flags (LaunchDarkly/Unleash)          │
└────────────────────────────────────────────────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 5 — ONLINE EVAL (PRODUCTION)                                                       │
│                                                                                          │
│ 5.1 Trace ingestion 100%: cada request → span → langfuse/phoenix/braintrust            │
│ 5.2 LLM-Judge sampling 5% async (background job, não bloqueia UX)                      │
│ 5.3 Eval-driven alerting:                                                                │
│   • faithfulness < 0.7 → page on-call                                                   │
│   • PII leak detected → cut traffic + escalate                                         │
│   • cost spike >2σ → throttle + investigate                                            │
│ 5.4 Drift detection: distribuição embeddings input + output, KS-test ou MMD             │
│   • 6 meses sem monitoramento = error rate jump 35% (LLMOps 2025 report)               │
│ 5.5 Refusal/retry pattern monitoring: spike em "I can't help with that" = drift bad    │
│                                                                                          │
│ Tools: Langfuse (open-source self-host), Arize Phoenix (drift especialista),           │
│        Helicone (proxy zero-code), Confident AI online evals                            │
└────────────────────────────────────────────────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 6 — HUMAN-IN-THE-LOOP (SME WEEKLY)                                                 │
│                                                                                          │
│ 6.1 Sample 50-100 conversas/semana pra SME (Larissa) anotar                            │
│ 6.2 Rubrica de domínio: "respondeu corretamente sobre meta?" "preservou contexto?"     │
│ 6.3 Inter-annotator agreement: 2 SMEs no mesmo subset, Cohen's κ ≥ 0.7                 │
│ 6.4 Feedback loop: anotações → dataset golden → retrain prompt/skill → eval offline    │
│ 6.5 Spot-check 5-10% das verdicts do LLM-judge contra SME (pega bias drift)            │
│                                                                                          │
│ Tools: Braintrust HITL UI, Comet session-level, Label Studio, Argilla, custom Inertia │
│        page no Copiloto                                                                  │
└────────────────────────────────────────────────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 7 — RED TEAMING & GUARDRAILS (CONTÍNUO)                                            │
│                                                                                          │
│ 7.1 Adversarial dataset: prompt injection, jailbreak, PII extraction attempts           │
│ 7.2 Roda mensal/trimestral, score regressivo                                             │
│ 7.3 Runtime guardrails: NeMo / Patronus Lynx / regex pra PII (CPF/CNPJ) PRÉ-LLM         │
│ 7.4 Topic restriction: "off-topic about meta de venda" → rejeita                       │
│ 7.5 OWASP LLM Top 10 cobertura                                                           │
│                                                                                          │
│ Tools: Promptfoo red team, NeMo Guardrails (Colang), Patronus AI (Lynx), custom regex │
└────────────────────────────────────────────────────────────────────────────────────────┘
                                            │
                                            ▼
┌────────────────────────────────────────────────────────────────────────────────────────┐
│ FASE 8 — RETRO + ITERAÇÃO (CONTINUOUS)                                                  │
│                                                                                          │
│ 8.1 Weekly: dashboard score (faithfulness, custo, latência, refusal rate)              │
│ 8.2 Monthly: retro do golden set (adicionar edge cases vistos em prod)                  │
│ 8.3 Quarterly: re-baseline (modelo/prompt mudou? expectativas mudaram?)                 │
│ 8.4 Eval-of-eval: judge prompt drift? ainda concorda 80%+ com SME?                     │
│ 8.5 Cost optimization: pode trocar Opus→Sonnet em X% das queries sem perder qualidade? │
└────────────────────────────────────────────────────────────────────────────────────────┘
```

**Métricas de impacto comprovadas (2026 industry data):**
- Times com eval framework comprehensive: **40% iteration cycle mais rápido**
- **60% menos production incidents**
- **6x maior taxa de sucesso em produção** (LangChain State of AI Agents 2026)
- Sem monitoramento por **6 meses → error rate jump 35%** (LLMOps report)
- **57% das organizações** têm agents em prod, **32% citam qualidade como #1 barreira de deploy**

---

## 4. Matriz Feature-by-Feature (42 features)

**Legenda:** ✅ Tem completo · 🟡 Tem básico/limitado · ❌ Não tem · ❓ Não confirmado · 🔵 N/A pra escopo

### Categoria 1 — Pre-deploy / Offline eval

| Feature | oimpresso (hoje) | Vizra ADK eval | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| Golden dataset CSV/versionado | ❌ | ✅ (CSV nativo) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (YAML) | ✅ |
| Synthetic data generation | ❌ | 🟡 | ✅ | ✅ | 🟡 | ✅ | ✅ (Synthesizer 7 evol.) | 🟡 | 🟡 |
| LLM-as-Judge built-in | ❌ | ✅ (PHP) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Position bias mitigation (A/B swap) | ❌ | ❓ | ✅ | 🟡 | 🟡 | 🟡 | ✅ | 🟡 | 🟡 |
| Multi-judge ensemble | ❌ | 🟡 | ✅ | 🟡 | 🟡 | 🟡 | ✅ | 🟡 | ✅ |
| 1-4 scale (anti-verbosity bias) | ❌ | 🟡 | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ | ✅ |
| Statistical error bars (Anthropic) | ❌ | ❌ | ✅ | 🟡 | ❌ | 🟡 | 🟡 | ❌ | ✅ |
| Pairwise comparison (A vs B) | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ (blind comparator) |
| Regression baseline gates | ❌ | 🟡 | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ |
| GitHub Actions native | ❌ | 🟡 | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | 🟡 |

### Categoria 2 — Online / Production monitoring

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| 100% trace ingestion | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ (Confident AI) | ❌ | 🔵 |
| Async judge sampling (5%) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | 🔵 |
| Eval-driven alerting | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Drift detection (embeddings KS) | ❌ | ❌ | ✅ | 🟡 | 🟡 | ✅ (best-in-class) | 🟡 | ❌ | ❌ |
| Cost / token tracking | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | 🔵 |
| Latency P95/P99 | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | 🔵 |
| Refusal/retry pattern alarm | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | 🟡 | ❌ | ❌ |
| Session replay | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | 🟡 |

### Categoria 3 — Human-in-the-loop / SME

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| Annotation UI nativa | ❌ | ❌ | ✅ | ✅ | 🟡 | 🟡 | ✅ (Confident) | ❌ | 🟡 |
| Inter-annotator agreement (κ) | ❌ | ❌ | ✅ | 🟡 | ❌ | ❌ | 🟡 | ❌ | ❌ |
| Anotação → golden set loop | ❌ | 🟡 | ✅ | ✅ | ✅ | 🟡 | ✅ | 🟡 | ✅ |
| Custom rubrics por domínio | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |

### Categoria 4 — RAG-specific evals (sprint 4-5 dependency)

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| Faithfulness (hallucination vs ctx) | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | 🟡 |
| Context Precision (top-K ranking) | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ (board) | ✅ | ❌ | ❌ |
| Context Recall | ❌ | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| Answer Relevancy | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | 🟡 |
| RAGAS metrics integration | ❌ | ❌ | 🟡 | 🟡 | ✅ | ✅ | ✅ | ❌ | ❌ |
| LongMemEval / LoCoMo native | ❌ | ❌ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ❌ | ❌ |

### Categoria 5 — Agent-specific (Vizra/sub-agents)

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| Tool selection accuracy | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ |
| Sub-agent delegation eval | ❌ | ✅ | 🟡 | ✅ (LangGraph) | 🟡 | 🟡 | 🟡 | ❌ | ✅ |
| Multi-turn coherence | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ |
| Trace de raciocínio (CoT visible) | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | 🟡 | ✅ |
| 20+ assertions catalog | ❌ | ✅ (built-in) | 🟡 | 🟡 | 🟡 | 🟡 | ✅ (14 metrics) | 🟡 | ✅ |

### Categoria 6 — Safety & guardrails

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| PII detection (CPF/CNPJ BR!) | ❌ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ (red team) | 🟡 |
| Prompt injection detection | ❌ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ | 🟡 |
| Jailbreak resistance test | ❌ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ (best-in-class) | ✅ |
| Toxicity / off-topic | ❌ | 🟡 | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| OWASP LLM Top 10 coverage | ❌ | ❓ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ | ❓ |
| Runtime guardrail integration | ❌ | ❓ | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | 🟡 | ✅ |

### Categoria 7 — Stack BR / Operação

| Feature | oimpresso | Vizra | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|---|
| PHP-native (Laravel idiomático) | 🔵 | ✅ (ÚNICO!) | ❌ | ❌ | ❌ | ❌ | ❌ | 🟡 (Node) | ❌ |
| Self-hostable R$0/mês recorrente | 🔵 | ✅ | ❌ | ❌ | ✅ | ✅ | 🟡 (CLI sim, cloud não) | ✅ | ❌ |
| LGPD-compatible data residency | 🔵 | ✅ | 🟡 | 🟡 | ✅ | ✅ | 🟡 | ✅ | 🟡 |
| Custo previsível R$ | 🔵 | ✅ (R$0 lib) | ❌ ($USD/seat) | ❌ ($USD/seat) | ✅ | ✅ | ✅ (free tier) | ✅ | ❌ |
| Onboarding < 1 dia | 🔵 | 🟡 (após L13) | ✅ | ✅ | 🟡 | 🟡 | ✅ | ✅ | ✅ |
| Open-source license permissiva | 🔵 | MIT | ❌ | ❌ | MIT | Elastic 2.0 | Apache 2 | MIT | 🟡 |

**Total:** 42 features cobertas em 7 categorias.

---

## 5. Notas G2/Capterra-style (1-5)

| Critério | Vizra eval | Braintrust | LangSmith | Langfuse | Phoenix | DeepEval | Promptfoo | Claude Skills |
|---|---|---|---|---|---|---|---|---|
| Facilidade de uso | 4 (estimado) | 5 | 4 | 4 | 4 | 4 | 4 | 5 |
| Suporte / docs | 3 (community small) | 5 | 5 | 4 | 4 | 4 | 4 | 5 |
| Custo-benefício | 5 (R$0) | 2 ($$$) | 2 ($$) | 5 (open) | 5 (open) | 4 | 5 | 3 (lock-in Claude) |
| Específico pro nicho (PHP/Laravel) | 5 (único PHP) | 1 | 1 | 2 | 2 | 1 | 2 | 1 |
| Cobertura ciclo completo | 3 (offline only) | 5 | 4 | 5 | 4 | 4 | 3 | 3 |
| Production-grade scale | 3 (early) | 5 | 5 | 4 | 4 | 4 | 3 | 4 |

**Sources de notas:**
- Sem reviews G2/Capterra >50 reviews disponíveis pra Vizra ADK (lançamento recente, scope nicho)
- Braintrust/LangSmith/Langfuse: G2 tem 50-100 reviews cada, médias 4.3-4.6
- DeepEval: GitHub stars >5k, Confident AI tem >100 reviews G2
- **TODAS as notas com componente "(estimado)"** — comparativo é qualitativo, não review platform

---

## 6. Top 3 GAPS críticos (oimpresso vs estado-da-arte)

### GAP 1 — Zero golden set commitado pra Copiloto

**O que falta:** nenhum CSV/JSON de "perguntas reais da Larissa + respostas esperadas + métricas alvo" no repo. Quando trocarmos `laravel/ai` 0.6.3 → 0.7 ou OpenAI key → Anthropic, **não temos como saber se quebrou**. DeepEval Synthesizer faz isso em 1 dia (7 evolutions de seed); Braintrust dataset versionado idem; Promptfoo YAML idem. Nós: começa do zero.

**Esforço:** **Baixo (3-5 dias)** — coletar 20 conversas reais da Larissa (extrair do Hostinger DB), Wagner anota "boa/ruim/comentário" em planilha, Claude expande pra 50-80 com synthetic, salva em `Modules/Copiloto/Tests/Datasets/golden_v1.csv`, commit.

**Impacto se não fechar:** **CRÍTICO**. ADR 0037 sprint 7 = "RAGAS evaluation" depende disso. Sem golden set, RAGAS retorna número solto sem baseline. Pior: quando produção quebrar (vai quebrar), não há regression test que pegue antes do user.

---

### GAP 2 — Zero LLM-judge online sampling em produção

**O que falta:** quando tirarmos `COPILOTO_AI_DRY_RUN=false` (sprint 2 do roadmap ADR 0036), Larissa começa conversar real e **ninguém vê hallucination/PII leak/refusal pattern** até ela reclamar (= confiança queimada). Estado-da-arte: 5% sampling async via Langfuse/Phoenix, dashboard com faithfulness rolling 7d, alarme se cai >0.7. Industry data: 60% menos incidentes com isso ligado.

**Esforço:** **Médio (2-3 sprints)** — sprint A: instalar Langfuse self-host no Hostinger (Docker compose + PostgreSQL + Clickhouse, ~4h). Sprint B: instrumentar `OpenAiDirectDriver`/`LaravelAiSdkDriver` pra emitir traces (use `langfuse-php` SDK community OU instrumentation OTEL nativa do `laravel/ai`). Sprint C: configurar evaluator template (faithfulness + answer relevancy) + alerting Slack/email.

**Impacto se não fechar:** **ALTO**. Sem isso, primeiro incidente real (Larissa vê resposta errada sobre meta) só vamos saber por suporte humano (= já tarde). Drift de modelo silencioso pode acumular 35% error em 6 meses (LLMOps 2025).

---

### GAP 3 — Zero PII redaction PRÉ-envio pra LLM (LGPD!)

**O que falta:** hoje quando Copiloto chamar OpenAI/Anthropic real, **CPF/CNPJ/email/telefone do cliente final do ROTA LIVRE vão direto pro vendor americano**. LGPD Art. 7º exige base legal + minimização. Estado-da-arte: regex BR PII (CPF/CNPJ patterns) + NER fallback (Patronus Lynx ou NeMo) ANTES do `->chat()`, replace por `[CPF_REDACTED]`, restore depois se contexto exigir.

**Esforço:** **Baixo-Médio (1 sprint)** — `PiiRedactorService.php` com regex CPF (`\d{3}\.\d{3}\.\d{3}-\d{2}`), CNPJ (`\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2}`), email/telefone básico. Aplicar em `OpenAiDirectDriver::chat()` antes de enviar payload. Logar (sem PII!) que houve redação.

**Impacto se não fechar:** **CRÍTICO regulatório**. Multa LGPD = até 2% faturamento limitado a R$50M. Mais grave: cliente B2B (ex.: contrato governo SP) cancela contrato se descobre PII pra vendor sem DPA. Nada protege isso hoje no nosso código.

---

## 7. Top 3 VANTAGENS reais

### V1 — Vizra ADK eval é PHP-native (único do mercado)

**Por que é vantagem:** todos os concorrentes BR (Mubisys/Zênite/Calcgraf) — quando forem fazer eval (vão fazer em ~12-24m) — vão precisar **stack Python paralela** (Pydantic+pytest+DeepEval) OU **SaaS USD** (Braintrust). Nós: `php artisan vizra:make:eval` integra-se ao mesmo Pest/PHPUnit que já roda. **Curva de adoção da equipe = zero** quando Vizra suportar L13.

**Como capitalizar:** quando Vizra ADK suportar L13 (sprint 3 do ADR 0036, dependência upstream sem ETA), virar **único ERP gráfico BR com QA de IA documentado**. Marketing: "nossa IA tem testes automatizados, faithfulness 0.9+ rolling 30d, dashboard público no /copiloto/qualidade". Concorrente não consegue replicar em <12m porque não tem Vizra equivalente em outras linguagens.

**Risco de erodir:** **Médio em 18m**. Vizra pode não conseguir suportar L13 (issue não tem ETA — ADR 0035). Plano B: drop Vizra, adotar DeepEval Python via REST + Vizra-shaped wrapper PHP custom (perde idiomático, mantém valor). Plano C: ficar só com `pest` + LLM-judge custom em PHP — pior UX dev mas funciona.

---

### V2 — Cliente único focal (ROTA LIVRE / Larissa) = golden set factível

**Por que é vantagem:** a maioria dos players globais sofre porque **golden set tem que cobrir N personas × M domínios** = 10k+ exemplos. Nós temos **1 persona real (Larissa) + 1 domínio (gestão de meta de venda gráfica)** = 50-100 exemplos cobrem 80% do uso. SaaS players cobram pra "te ajudar a fazer golden set" porque é o gargalo. Pra nós, é finito e pequeno.

**Como capitalizar:** Sprint 7 = **Wagner+Claude fazem 50 exemplos golden set em 1 semana** (validar com Larissa em 1h). RAGAS roda contra isso, faithfulness de baseline sai. Cada cliente novo só adiciona <20 exemplos. Compounding curve mais favorável que players genéricos.

**Risco de erodir:** **Alto em 12m se sucesso comercial real**. Quando entrarem cliente 5, 10, 20 — cada um tem domínio próprio (gráfica de banner ≠ flyer ≠ adesivo). Golden set explode. Mitigação: estrutura `Modules/Copiloto/Tests/Datasets/{cliente_id}/golden_v{n}.csv` com versionamento por tenant + base comum + dataset comum mensurado contra baseline da plataforma.

---

### V3 — Stack canônica já tem 2/3 da observabilidade (Pail+Telescope+Horizon)

**Por que é vantagem:** sessão 18 (2026-04-26) instalou `laravel/horizon + telescope + pail` — observabilidade Laravel-native instalada. Phoenix/Langfuse pegariam tracing aplicacional via OpenTelemetry nativo do `laravel/ai`. **80% do plumbing já existe**, falta apenas conectar OTEL endpoint pro Langfuse.

**Como capitalizar:** Sprint 8 = "instalar Langfuse Hostinger" custa **<1 sprint** porque Telescope já dá DB queries, Horizon já dá jobs, Pail já dá logs. Adicionar tracing IA é o último 20%. Concorrente novo IA-first (ex.: Lovable, Manus) não tem isso barato porque não tem stack Laravel sólida embaixo.

**Risco de erodir:** **Baixo**. Pail/Telescope/Horizon são 1ª-party Laravel (nunca vão sumir). Risco mais real é Wagner não ligar essa parte por focar em features visíveis — mitigação: incluir como acceptance criteria no DoD de sprint 8.

---

## 8. Posicionamento sugerido (3 caminhos)

| Caminho | Tese curta | Veredito |
|---|---|---|
| **A — "Eval Enterprise SaaS"** | Adota Braintrust + LangSmith pago, fica enterprise-grade desde dia 1. R$2-5k/mês USD recorrente em ERP que cobra R$497/cliente. | ❌ **Viola ADR 0036** (R$0/mês recorrente filosofia) e **ADR 0030** (sem dep estrangeira pra core). Lock-in cloud cria fragilidade quando concorrente com mesma stack chega. **OK só se cliente enterprise (>R$10k/mês ticket) bater na porta**. |
| **B — "Self-host pragmático"** | Vizra ADK eval (offline, quando L13 ok) + Langfuse self-host Hostinger (online) + DeepEval CLI gates (CI) + golden set manual. R$0 recorrente, 3 sprints de build. | ✅ **Recomendado.** Alinha com ADR 0036 (Meilisearch-first, R$0). Aproveita Pail/Telescope/Horizon. PHP-native quando Vizra L13 vier. **Faltará annotation UI HITL** (resolver com Inertia page custom no `/copiloto/admin/qualidade` — 1 sprint extra). |
| **C — "Defer eval"** | Só fazer eval quando primeiro incidente real explodir. Fica em `pest tests/Modules/Copiloto/` simples. | ❌ Já estamos em **risco LGPD ativo** sem PII redaction (GAP 3) — defer = jogar Larissa de cobaia. Industry data: 6 meses sem eval = error 35% jump. **Veto técnico.** |

**Recomendado: Caminho B.**

**Frase de posicionamento que sai dessa decisão (pra site/marketing — quando estiver feito):**

> *"Único ERP gráfico brasileiro com IA monitorada em tempo real. Dashboard público de qualidade do Copiloto: faithfulness 0.9+, hallucination <2%, latência P95 <3s. Você pode auditar."*

---

## 9. Math da meta

Como esse comparativo conecta com **ADR 0022 (R$5mi/ano)**:

- Meta = R$5mi/ano = R$417k/mês de MRR
- Ticket médio assumido: **R$497/mês** (PME gráfica BR — não validado em 5+ prospects)
- Clientes necessários: **839 clientes ativos**
- Churn assumido: **3%/mês** (default não validado)
- Funil necessário: **839 × 3.5% acquired/month líquido = 30 novos/mês sustentável**
- Realidade hoje: **7 clientes pagantes (1 ativo real = ROTA LIVRE)** = ~R$3.5k MRR
- **Gap:** 832 clientes a fechar nos próximos 24m = 35 novos/mês sustentável

**Como QA de IA muda o math:**

| Cenário | Conversão funil | Tempo até close | Churn |
|---|---|---|---|
| Sem eval (hoje) | 15% (medo de IA quebrar) | 45 dias (stakeholder duvida) | 5% (cliente sai cedo se IA "burra") |
| Caminho B implementado | 25% (dashboard público de qualidade vira sales tool) | 28 dias (FAQ "como vocês garantem qualidade?" tem resposta) | 2.5% (eval continuous detecta degradação antes do user) |

**Δ MRR projetado em 12m com caminho B:** +R$45k/mês (+10% acquisition × +12% retention compounding).

**Assunção MAIS frágil:** "dashboard de qualidade vira sales tool" — não testado. Nenhum competidor BR tem isso, então não tem benchmark. Se prospect não ligar, vantagem vira só técnica.

---

## 10. Recomendação concreta

### 3 sprints prioritárias (ordem)

#### **Sprint 7 — Golden set + DeepEval CLI offline (1 sprint, 5-7 dias)**

**Entregáveis:**
1. `Modules/Copiloto/Tests/Datasets/golden_v1.csv` — 50 perguntas-resposta-rubrica de Larissa real (extraídas do Hostinger DB sessão 5+ + 10 sintéticas DeepEval Synthesizer + 5 adversariais red team)
2. `composer require deepeval-php/sdk` (se existir; senão wrapper REST custom em `Modules/Copiloto/Services/Eval/DeepEvalDriver.php`)
3. `Modules/Copiloto/Tests/Feature/CopilotoEvalTest.php` — Pest test que roda `faithfulness + answer_relevancy + context_precision` contra golden set, threshold 0.75 mínimo
4. GitHub Actions workflow `eval.yml` — roda em todo PR que toca `Modules/Copiloto/`, falha CI se score cai >5% absoluto vs `main` baseline

**Por que #1:** sem golden set, todo resto (online judge, drift, HITL) é número solto sem baseline. RAGAS sprint 7 do ADR 0036 depende disso. Tira "fixture-only" de risco binário pra "fixture-then-real-with-safety-net".

**Bloqueante:** validar com Larissa **antes** do sprint começar (handoff §🎯 hoje) — se feedback dela vier "Pivot ADR 0026", esse sprint não acontece.

---

#### **Sprint 8 — PII redactor + Langfuse self-host Hostinger (1 sprint, 5-7 dias)**

**Entregáveis:**
1. `Modules/Copiloto/Services/Privacy/PiiRedactorService.php` com regex BR (CPF/CNPJ/email/telefone-BR) + tests Pest
2. Plug `PiiRedactorService` em `OpenAiDirectDriver::chat()` ANTES de payload outbound
3. Docker compose do Langfuse self-host no Hostinger (`~/services/langfuse/` com PostgreSQL+Clickhouse) — coordenar com Meilisearch já running
4. Instrumentação OTEL no `LaravelAiSdkDriver` (1ª class do `laravel/ai` ^0.6.3) → Langfuse endpoint
5. Dashboard básico: traces 100%, custo/token aggregation, latência P95 view

**Por que #2:** fecha GAP 3 (LGPD) que é regulatório-CRÍTICO + abre canal de observabilidade pro sprint 9. Langfuse precisa estar instalado antes de qualquer dashboard ou alerting.

**Bloqueante:** confirmar OPENAI_API_KEY ativo em produção (handoff §"Configurar embedder" pendente).

---

#### **Sprint 9 — Online judge sampling + drift + HITL admin page (1-2 sprints, 7-10 dias)**

**Entregáveis:**
1. `ApurarQualidadeJob` (Horizon background job) — sample 5% conversas/dia, chama LLM-judge sobre rubrica, grava `copiloto_qualidade_scores` tabela com {conversa_id, faithfulness, answer_relevancy, judge_model, judge_reasoning}
2. Eval-driven alerting: faithfulness rolling-7d <0.7 → email Wagner + log critical
3. `/copiloto/admin/qualidade` Inertia page (HITL) — Wagner/Larissa veem 20 conversas semana, anotam "boa/ruim/comentário", anotações viram input pro golden set v2
4. Drift dashboard simples no Langfuse: distribution embeddings input/output, weekly KS-test, flag anomaly
5. Métricas integradas no `/copiloto/admin/custos` (já em andamento — branch `claude/nervous-burnell-f497b8`)

**Por que #3:** fecha GAP 2 (online sampling). Sem isso, Caminho B fica meio. SP9 fecha o ciclo de 6 fases — offline + online + HITL + drift juntos. Depois disso o produto tem um QA de IA real que pode botar no site.

---

### O que NÃO fazer agora (corta)

- ❌ **Não comprar Braintrust/LangSmith** mesmo se sales chamar. Só revisar quando MRR >R$30k/mês ou cliente enterprise pedir explicitamente DPA + dashboard.
- ❌ **Não implementar multi-judge ensemble** (Claude+GPT+Gemini) ainda. 3-5x custo, ROI só faz sentido depois de 100k+ requests/mês. Fica como ADR follow-up sprint 12+.
- ❌ **Não abrir tela pública `/copiloto/qualidade`** ainda — Frase do site na §8 vale pra MARKETING só depois de sprint 9 + 30 dias de dado real.
- ❌ **Não plugar NeMo Guardrails** ainda — Patronus Lynx idem. Adicionam latência sem ROI até termos volume. PII redactor regex BR cobre 90% até lá.
- ❌ **Não esperar Vizra L13** pra começar QA. Sprint 7 usa DeepEval Python via REST adapter no PHP. Quando Vizra L13 vier, **migra eval harness, não constrói do zero**.
- ❌ **Não fazer red team formal** (Promptfoo) ainda. Roda só uma sweep de PII/jailbreak no sprint 9 (~2h trabalho), não vira sprint dedicado.

---

## 11. Métrica de fé (90 dias) — 2026-07-27

> **Se em 90 dias (28-jul-2026):**
> 1. Golden set v1 commitado (50 exemplos validados pela Larissa);
> 2. CI/CD eval gate ativo bloqueando PR com regression >5%;
> 3. Larissa converse 30+ vezes em produção real (não fixture);
> 4. LLM-judge online registrar **≥85% faithfulness rolling-7d**;
> 5. **0 vazamento de PII** detectado em logs (PII redactor passa);
> 6. Dashboard `/copiloto/admin/qualidade` mostrando trend visual;
>
> **→ Tese B (self-host pragmático) confirma. Avança pra red team formal sprint 10+.**
>
> **Senão (qualquer um falha):**
> - Faithfulness <0.85 → golden set tá raso OU prompt do `ChatCopilotoAgent` precisa retrain (volta sprint 7).
> - PII leak detectado → escalonar pra Patronus Lynx sprint 10 imediato (Caminho B+).
> - Golden set não saiu → **pivot pra Caminho A** (Braintrust 30-day trial + decisão MRR-gated).
> - Larissa não conversou 30+ vezes → não é problema de eval, é problema de **adoption** — pivot pra ADR 0026 PricingFpv/CT-e (recomendação revisão 2026-04-27 do Capterra).

---

## 12. Riscos transversais

1. **Eval awareness** — Anthropic descobriu (2026-04) que Claude Opus 4.6 detecta quando está sendo avaliado e muda comportamento (BrowseComp paper). Mitigação: **misturar golden set com tráfego real indistinguível** + nunca prefixar prompt com "evaluate this".
2. **LLM-judge bias auto-induzido** — annotation feita com sugestão de LLM enviesa avaliação posterior do mesmo LLM ([arXiv 2507.15821](https://arxiv.org/abs/2507.15821) "Just Put a Human in the Loop?"). Mitigação: **Larissa anota SEM ver sugestão da IA**, só depois compara κ.
3. **Cost spiral** — LLM-judge async sampling 5% sobre 1k conversas/dia × 90 dias = 4.5k judge calls/mês × $0.01 média = **R$200-500/mês esquecíveis** mas precisam orçamento. Adicionar limite hard 5%/dia.
4. **Drift do judge prompt** — judge prompt rota tanto quanto qualquer prompt. Re-baseline κ vs SME mensalmente. Nunca usar judge prompt antigo sem re-validação trimestral.
5. **Larissa fadiga de anotação** — 50 conversas/semana é demais pra dona+operadora. Reduzir pra 10-15 + Claude assistido (suggestion mode), aceitar agreement rate menor.

---

## 13. Sources (literais)

**Plataformas comparadas:**
- [Vizra ADK Documentation](https://vizra.ai/docs/adk) · [vizra-ai/vizra-adk GitHub](https://github.com/vizra-ai/vizra-adk) · [Laravel News — Vizra ADK](https://laravel-news.com/vizra-adk)
- [Braintrust](https://www.braintrust.dev/) · [Braintrust DeepEval alternatives 2026](https://www.braintrust.dev/articles/deepeval-alternatives-2026) · [Braintrust HITL evals](https://www.braintrust.dev/articles/human-in-the-loop-evals-for-llm-apps)
- [LangSmith](https://smith.langchain.com/) · [LangSmith CI/CD Integration 2026](https://markaicode.com/langsmith-cicd-automated-regression-testing/)
- [Langfuse](https://langfuse.com/) · [Langfuse vs Arize 2026](https://langfuse.com/faq/all/best-phoenix-arize-alternatives) · [Spheron LLM Observability self-host 2026](https://www.spheron.network/blog/llm-observability-gpu-cloud-langfuse-arize-phoenix-helicone/)
- [Arize Phoenix](https://phoenix.arize.com/) · [Arize Synthetic Datasets 2026](https://arize.com/blog/creating-and-validating-synthetic-datasets-for-llm-evaluation-experimentation/)
- [DeepEval](https://deepeval.com/) · [DeepEval Synthesizer](https://www.deepeval.com/docs/synthesizer-introduction) · [DeepEval CI/CD Unit Testing](https://deepeval.com/docs/evaluation-unit-testing-in-ci-cd) · [Confident AI](https://www.confident-ai.com/)
- [Promptfoo](https://www.promptfoo.dev/) · [Promptfoo CI/CD](https://www.promptfoo.dev/docs/integrations/ci-cd/)
- [Anthropic Demystifying Evals for AI Agents](https://www.anthropic.com/engineering/demystifying-evals-for-ai-agents) · [Anthropic Statistical Approach to Evals](https://www.anthropic.com/research/statistical-approach-to-model-evals) · [Anthropic Eval Awareness Browseomp](https://www.anthropic.com/engineering/eval-awareness-browsecomp)

**Métodos / pesquisa:**
- [LongMemEval paper (arXiv 2410.10813)](https://arxiv.org/pdf/2410.10813) · [LoCoMo Snap Research](https://snap-research.github.io/locomo/) · [MEMTRACK arXiv 2510.01353](https://arxiv.org/pdf/2510.01353)
- [LLM-as-a-Judge Survey arXiv 2411.15594](https://arxiv.org/abs/2411.15594) · [Empirical Study LLM-as-Judge arXiv 2506.13639](https://arxiv.org/html/2506.13639v1)
- [Just Put Human in the Loop arXiv 2507.15821](https://arxiv.org/abs/2507.15821) · [HITL ACL Anthology](https://aclanthology.org/2025.findings-acl.1323/)
- [RAGAS Metrics docs](https://docs.ragas.io/en/stable/concepts/metrics/available_metrics/) · [RAG Evaluation 2026 PremAI](https://blog.premai.io/rag-evaluation-metrics-frameworks-testing-2026/) · [Patronus RAG Eval](https://www.patronus.ai/llm-testing/rag-evaluation-metrics)

**Industry data / state of art:**
- [LangChain State of AI Agents 2026 — Lovelytics summary](https://lovelytics.com/post/state-of-ai-agents-2026-lessons-on-governance-evaluation-and-scale/)
- [Datadog State of AI Engineering](https://www.datadoghq.com/state-of-ai-engineering/)
- [Top 5 AI Agent Evaluation Platforms 2026 (Maxim)](https://www.getmaxim.ai/articles/top-5-ai-agent-evaluation-platforms-in-2026/)
- [TierZero Production AI Agents 2026 Landscape](https://www.tierzero.ai/blog/production-ai-agents-2026-landscape/)
- [Arthur AI Agentic Observability 2026 Playbook](https://www.arthur.ai/column/agentic-ai-observability-playbook-2026)
- [Inference.net LLM Evaluation Tools 2026](https://inference.net/content/llm-evaluation-tools-comparison/)
- [Awesome AI Evaluation Guide GitHub](https://github.com/hparreao/Awesome-AI-Evaluation-Guide)

**Drift / production / deployment:**
- [VentureBeat Monitoring LLM Behavior](https://venturebeat.com/infrastructure/monitoring-llm-behavior-drift-retries-and-refusal-patterns) · [Orq.ai Model vs Data Drift 2026](https://orq.ai/blog/model-vs-data-drift) · [All Days Tech Drift Runbook 2026](https://alldaystech.com/guides/artificial-intelligence/model-drift-detection-monitoring-response)
- [TianPan Shadow Mode + Canary + A/B 2026](https://tianpan.co/blog/2026-04-09-llm-gradual-rollout-shadow-canary-ab-testing) · [MarkTechPost 4 Strategies](https://www.marktechpost.com/2026/03/21/safely-deploying-ml-models-to-production-four-controlled-strategies-a-b-canary-interleaved-shadow-testing/)
- [Confident AI Top 5 LLM Monitoring 2026](https://www.confident-ai.com/knowledge-base/top-5-llm-monitoring-tools-for-ai)

**Guardrails / red teaming:**
- [NVIDIA NeMo Guardrails GitHub](https://github.com/NVIDIA-NeMo/Guardrails) · [Maxim Top 5 Guardrails Platforms 2026](https://www.getmaxim.ai/articles/top-5-ai-guardrails-platforms-for-responsible-enterprise-ai-in-2026/) · [Galileo Top 8 Agent Guardrails 2026](https://galileo.ai/blog/best-ai-agent-guardrails-solutions)
- [OWASP Gen AI Security LLM Guardrails](https://genai.owasp.org/solution-taxonomy/llm-guardrails/) · [HuuPhan AI Red Teaming Tools 2026](https://www.huuphan.com/2026/04/ai-red-teaming-tools-2026.html)
- [Patronus AI Lynx hallucination detection](https://www.patronus.ai/llm-testing/rag-evaluation-metrics)

**Cost / economics:**
- [NVIDIA Blackwell Token Cost 2026](https://perspectives.nvidia.com/real-cost-ai-scale-hyperscaler-accelerator-economics-2026/) · [Spheron AI Inference Cost 2026](https://www.spheron.network/blog/ai-inference-cost-economics-2026/)
- [Acropolium AI Agent Unit Economics](https://acropolium.com/blog/ai-agent-unit-economics/) · [TheProductSpace Agentic Economics 2026](https://theproductspace.in/blogs/artificial-intelligence/agentic-ai-economics-cost-performance-and-roi-in-2026)
- [Deloitte AI Token Spend Dynamics](https://www.deloitte.com/us/en/insights/topics/emerging-technologies/ai-tokens-how-to-navigate-spend-dynamics.html)

---

## Checklist final

- [x] TL;DR cabe em 5 frases
- [x] Mín. 4 concorrentes — temos 8 (oimpresso/Vizra + 7 plataformas)
- [x] 30+ features na matriz — temos 42 em 7 categorias
- [x] Notas G2 1-5 com "(estimado)" onde aplicável
- [x] Exatamente 3 GAPs e 3 VANTAGENS
- [x] 3 caminhos de posicionamento com veredito (A❌ / B✅ / C❌)
- [x] Math da meta R$5mi feito (com Δ MRR projetado)
- [x] 3 features prioritárias em ordem (Sprints 7-9)
- [x] Métrica de fé com prazo (90d, 28-jul-2026) e gatilho de pivot
- [x] Sources literais com URL
- [x] Companion docs linkados no frontmatter

---

> **Versão deste comparativo:** 1.0 (2026-04-28)
> **Atualizado por:** Claude (sessão `loving-black-f3caa3`) sob direção do Wagner
> **Próxima revisão sugerida:** após sprint 9 fechar (28-jul-2026) com métrica de fé batida ou pivot decidido — re-validar GAPs com produção real ao vivo.
> **Companion read:** decisão formalizada em **[ADR 0041 — Stack de QA de IA: Vizra + Langfuse + DeepEval](../decisions/0041-stack-qa-ia-vizra-langfuse-deepeval.md)** (renumerado de 0040 → 0041 em 2026-04-28 por reconciliação com main que já tinha 0040 sobre publication-policy).
