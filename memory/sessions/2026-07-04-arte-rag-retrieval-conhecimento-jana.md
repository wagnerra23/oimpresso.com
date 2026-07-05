---
date: "2026-07-04"
topic: "Estado-da-arte RAG/retrieval do conhecimento da Jana — grade dimensional + veredito + teste empírico"
authors: [C]
---

# Estado-da-arte RAG/retrieval do conhecimento da Jana — grade dimensional + veredito

**Data:** 2026-07-04 · **Tema:** busca de conhecimento canônico (docs_pipeline `mcp_memory_documents` + memoria_pipeline `jana_memoria_facts`) consumida por `kb-answer`, `decisions-search`, chat Jana
**Problema do dono (Wagner):** Jana não recupera bem o conhecimento — **context_recall 0.42** (ragas real, N=51, ADR 0318). Pergunta literal: *"as 3 camadas (hybrid + contextual + reranker) resolvem meu problema, ou falta mais?"*
**Método:** 10 WebSearch + 1 WebFetch (paper direto) sobre estado-da-arte 2025-2026 + evidência de 1ª mão no código (`BgeReranker.php`, `ContextualizerService.php`) e no estado levantado hoje no CT 100.

---

## TL;DR (6 linhas)

- **Maturidade global do retrieval de conhecimento da Jana: ~46%** (weighted por impacto no recall). Recomendação: **EVOLUIR** — o gargalo não é "faltam features", é que **as peças certas estão construídas e DESLIGADAS/malconfiguradas**, e falta a alavanca de maior ROI que Wagner nem listou.
- **Resposta direta:** as 3 camadas **ajudam, mas sozinhas NÃO resolvem** — e a ordem de prioridade delas está errada. A alavanca #1 é o **reranker** (a literatura 2026 mostra que ele domina tudo); a #2 é **query understanding + instruction-prefix** (a raiz da ADR 0312); a #3 é **curadoria/template rico** (o `content_excerpt` ≈ só título é um gap silencioso maior que o hybrid).
- **Top 3 gaps:** (P0) reranker BGE em rede docker isolada + driver em `rrf` → religar; (P0) **instruction-prefix no embedder** (raiz medida da 0312 — semântico invertido, não "embedder ruim"); (P0) **template do embedder pobre** (400 bytes ≈ título) + `content_excerpt` vazio.
- **Trade-off honesto:** neste corpus (docs curtos, PT/EN, jargão/slug), o **lexical (FULLTEXT/BM25) É o baseline certo** — a literatura confirma. As 3 camadas valem, mas o dinheiro está em **reranker + query-understanding + curadoria**, não em ficar caçando o embedder perfeito num CPU-only.

---

## 1. O pipeline completo (não só as 3 camadas) — onde a Jana está

O consenso 2025-2026 é que RAG é um **pipeline de ~10 estágios**, e *"80% das falhas de RAG vêm da camada de ingestão/chunking, não do LLM"* ([premai 2026](https://blog.premai.io/building-production-rag-architecture-chunking-evaluation-monitoring-2026-guide/), [callmissed 2026](https://www.callmissed.com/en/blog/rag-best-practices-2026)). Wagner enquadrou o problema como "3 camadas" (hybrid+contextual+reranker) — mas essas são só 3 dos 10 estágios, e **não são os de maior ROI para este corpus**.

Pipeline canônico 2026: `query → query rewrite/expand → hybrid retrieve (dense top-50 + BM25 top-50, RRF) → rerank (cross-encoder) → top 5-8 → context build (metadata+citations) → LLM grounded → eval (recall@k + faithfulness)` ([dev.to 2026](https://dev.to/young_gao/rag-is-not-dead-advanced-retrieval-patterns-that-actually-work-in-2026-2gbo)).

---

## 2. GRADE DIMENSIONAL (o que Wagner pediu)

Nota 0-100 do oimpresso vs melhor 2026, **ponderada por impacto no problema** (a Jana achar o conhecimento certo). Peso: P0=impacto direto no recall, P1=alto, P2=médio.

| # | Dimensão | Peso | oimpresso hoje | Melhor 2026 | Nota | Evidência |
|---|---|---|---|---|---|---|
| D1 | **Reranking (cross-encoder)** | P0 | BGE-v2-m3 construído, container `healthy`, MAS em rede docker isolada (unreachable) + driver em `rrf` (não `bge`) → **não roda** | Cohere Rerank 3.5 / BGE-v2-m3 self-host bem plugado | **20** | `BgeReranker.php` existe e tem fallback graceful; estado CT 100: unreachable + driver errado. Paper: reranker é o fator dominante ([arxiv 2606.28367](https://arxiv.org/html/2606.28367v1)) |
| D2 | **Query understanding** (rewrite, HyDE, multi-query, decomposition, **instruction-prefix**) | P0 | Query vai **RAW** ao embedder. Sem rewrite, sem multi-query, sem prefix `Instruct:…\nQuery:…` | Instruction-prefix obrigatório em Qwen3/jina-v5; query-expansion o único enhancement que sobreviveu ao teste | **10** | ADR 0312 mediu a inversão de similaridade por falta de prefix; query-expansion = +6.7 F1 no paper, HyDE +20% ([geocommunity](https://thegeocommunity.com/blogs/generative-engine-optimization/query-rewriting-multiquery-rag/)) |
| D3 | **Embedding template / doc enrichment** | P0 | `documentTemplate = "{{title}}. {{content_excerpt}}"` truncado a **400 bytes**; `content_excerpt` ≈ só o título na prática | Template rico (title+headings+summary+body), campo `summary` gerado | **15** | 1ª mão: template no estado levantado; embeddar ~só o título = jogar fora o corpo do doc |
| D4 | **Hybrid / fusion (BM25+dense+RRF, semanticRatio)** | P0 | Hybrid DESLIGADO por decisão (ADR 0312) porque o dense estava quebrado. Hoje = **FULLTEXT MySQL puro** | Hybrid dense+BM25 RRF sempre bate ambos isolados | **40** | ADR 0312: FULLTEXT mede melhor (6/7 vs 4/7 no top-3). RRF hybrid > ambos ([arxiv 2604.01733](https://arxiv.org/html/2604.01733v1)). Nota alta porque o lexical certo já entrega |
| D5 | **Escolha de embedding** (tamanho/qualidade/PT-BR/instruction-tuned/CPU) | P1 | qwen3-embedding **0.6b**, CPU-only, 1024d. 4b descartado (CPU proibitivo) | Voyage-3-large / BGE-M3 (100+ langs) / EmbeddingGemma-300M on-device | **45** | 0.6b é instruction-aware e MTEB-forte PARA o tamanho ([Qwen3](https://qwenlm.github.io/blog/qwen3-embedding/)); mas CPU-only limita. EmbeddingGemma-300M rivaliza maiores em CPU ([bentoml](https://www.bentoml.com/blog/a-guide-to-open-source-embedding-models)) |
| D6 | **Contextual retrieval (Anthropic)** | P1 | Service + command construídos, **backfill NUNCA rodou (0/1459)**, template não usa contexto, `ANTHROPIC_API_KEY` ausente no CT 100 | Contextual embeddings + BM25: -49% a -67% failed retrievals | **15** | `ContextualizerService.php` completo, mas 0 docs processados = feature morta. -67% é COM reranker ([Anthropic 2024](https://www.anthropic.com/news/contextual-retrieval)) |
| D7 | **Chunking / ingestion** | P2 | Docs curtos (ADR/session/handoff) — chunking pouco relevante aqui; doc ≈ 1 unidade | Structure-aware + semantic quando prosa longa | **60** | Corpus é doc-curto: chunking não é o gargalo (ao contrário do caso geral). Sentence-based iguala semantic até ~5k tokens ([digitalapplied](https://www.digitalapplied.com/blog/rag-chunking-strategies-2026-retrieval-quality-playbook)) |
| D8 | **Evaluation (offline + online + golden set)** | P1 | ragas real N=51 gold-set (ADR 0318) — **honesto, isto é raro e bom**. Falta canary/online + tamanho do gold-set | Gold-set 50-100q curado + synthetic + leakage-free + online | **55** | ADR 0318 é surpresa positiva. Best practice: 50-100q curado ([anyscale](https://docs.anyscale.com/rag/evaluation)); N=51 está no piso |
| D9 | **Data quality / curadoria / summary / freshness** | P0 | Sem campo `summary` por doc; `content_excerpt` vazio; sem dedup/metadata rica; jargão/slug não normalizado | Campo summary + metadata + dedup + freshness boost | **25** | Curadoria é a alavanca subestimada: *"RAG é tão bom quanto o contexto que consegue ver"* ([dev.to 2026](https://dev.to/young_gao/rag-is-not-dead-advanced-retrieval-patterns-that-actually-work-in-2026-2gbo)) |
| D10 | **Agentic retrieval (self-RAG, corrective-RAG, multi-hop)** | P2 | Nenhum. Retrieval one-shot | CRAG + self-RAG + router (easy→fast, hard→loop) | **10** | Self-RAG: menor taxa de alucinação ([survey 2501.09136](https://arxiv.org/html/2501.09136v4)). Custo 3-10× tokens — só vale no fim |
| D11 | **Generation grounding / citation** | P2 | `kb-answer` cita, mas sem penalidade por citação alucinada | Structured citation + penalty p/ citação fora do retrieved | **50** | Best practice: citation faithfulness com penalidade ([premai](https://blog.premai.io/building-production-rag-architecture-chunking-evaluation-monitoring-2026-guide/)) |

**Score weighted (P0=3, P1=2, P2=1):**
`(20·3 + 10·3 + 15·3 + 40·3 + 45·2 + 15·2 + 60·1 + 55·2 + 25·3 + 10·1 + 50·1) / (Σpesos·100)`
= `(60+30+45+120+90+30+60+110+75+10+50) / (23·100)` = **680/2300 ≈ 46%**.

---

## 3. RESPOSTA DIRETA: as 3 camadas resolvem?

**Não sozinhas, e a prioridade delas está trocada.** Três verdades da literatura 2026:

### (a) O RERANKER é a alavanca dominante — mas está desligado
O paper mais direto ao ponto do Wagner ([*Beyond the Reranker*, arxiv 2606.28367](https://arxiv.org/html/2606.28367v1)) mediu: **remover o reranker cross-encoder derruba nDCG@10 de 0.644 → 0.034 e Success@10 de 0.87 → 0.08** num corpus heterogêneo (código+markdown+tabela+prosa — exatamente o perfil da Jana). É a maior queda de qualquer método testado. **Corolário:** com um reranker forte ligado, quase todos os outros "enhancements" (graph, RAPTOR, fusão, re-retrieval) dão ganho negligível. **Logo:** ligar o BGE (já construído, só está em rede isolada + driver `rrf`) é o **maior ROI single-move do projeto** — e barato (integração, não construção).

### (b) A raiz da 0312 NÃO é "embedder ruim" — é FALTA DE INSTRUCTION-PREFIX
Wagner enquadrou como "semântico perde do lexical neste corpus". Verdade parcial. Mas a ADR 0312 já mediu o mecanismo: qwen3-embedding **exige** query no formato `Instruct: <task>\nQuery: <q>`; o Meilisearch manda RAW → o vetor cai no espaço errado e a similaridade **inverte**. Isso é **bug de configuração, não limite do modelo**. A literatura confirma: Qwen3 e jina-v5 são *instruction-aware / asymmetric* — encoda query com prompt, documento sem, e o prefix vale +1% a +5% ([Qwen3](https://qwenlm.github.io/blog/qwen3-embedding/), [jina-v5](https://arxiv.org/html/2602.15547)). **Sem consertar isso, qualquer conclusão "semântico é ruim neste corpus" é sobre um semântico quebrado.** Esta é a dimensão que Wagner tratou como "descoberta que justifica desligar", quando na verdade é "conserta e reavalia".

### (c) A alavanca que Wagner NÃO listou: CURADORIA / TEMPLATE / SUMMARY
O `documentTemplate` embedda **~só o título** (400 bytes, `content_excerpt` vazio). Isso significa que **o dense nunca teve chance** — não por causa do modelo, mas porque o corpo do doc nunca entrou no vetor. Idem para o `summary` ausente. Curadoria é o multiplicador silencioso: *"o jeito mais rápido de melhorar RAG em 2026 não é modelo melhor, é chunk melhor, retrieve híbrido, rerank agressivo e MEDIR"* ([callmissed](https://www.callmissed.com/en/blog/rag-best-practices-2026)). **Isto tem ROI maior que trocar o embedder.**

### Veredito honesto do trade-off
Neste corpus específico (docs técnicos curtos, PT/EN, jargão de nicho, slugs/nomes), **o lexical/BM25 É o baseline correto e a literatura confirma** — BM25 bate até `text-embedding-3-large` em domínio com jargão/named-entities ([arxiv 2604.01733](https://arxiv.org/html/2604.01733v1), [emergentmind BM25](https://www.emergentmind.com/topics/bm25-retrieval)). **Portanto:**
- **NÃO** vale caçar o "embedder perfeito num CPU-only" agora — ROI baixo, custo alto.
- **VALE** (nesta ordem de ROI): **(1) ligar o reranker BGE** sobre os candidatos do FULLTEXT (rerank melhora precisão 10-30% ([callmissed](https://www.callmissed.com/en/blog/rag-best-practices-2026)) e domina tudo), **(2) query-understanding barato** (instruction-prefix + query-expansion — os 2 enhancements que sobreviveram ao teste), **(3) curadoria** (template rico + campo summary).
- Contextual retrieval e embedder maior (GPU) são **Onda 3** — só depois de medir se (1)+(2)+(3) já fecham a meta. O paper sugere que, **com reranker forte, contextual dá pouco ganho marginal** — então talvez nem precise.

**Complexidade das 3 camadas juntas?** Vale a do reranker (ROI dominante). A do contextual retrieval é **questionável neste corpus** — 120× mais lento na indexação ([kx/medium](https://medium.com/kx-systems/late-chunking-vs-contextual-retrieval-the-math-behind-rags-context-problem-d5a26b9bbd38)) e ganho marginal quando já tem reranker. Sugiro **provar o valor com reranker+prefix+curadoria ANTES** de rodar o backfill contextual de 1459 docs.

---

## 4. TOP 10 GAPS priorizados (impacto × esforço)

| # | Gap | Prio | Esforço | ROI | Tipo | Ref |
|---|---|---|---|---|---|---|
| G1 | **Reranker BGE em rede isolada + driver `rrf`** → religar rede docker + driver `bge` sobre candidatos FULLTEXT | P0 | 1-2 dev-days | ⭐⭐⭐⭐⭐ | CONSOLIDAR (já construído) | arxiv 2606.28367 |
| G2 | **Instruction-prefix ausente** → injetar `Instruct:…\nQuery:…` na query antes do embedder (conserta 0312) | P0 | 1 dev-day | ⭐⭐⭐⭐⭐ | CONSOLIDAR | Qwen3 blog |
| G3 | **Template do embedder pobre / `content_excerpt` vazio** → template rico + popular excerpt com corpo | P0 | 2-3 dev-days | ⭐⭐⭐⭐ | EVOLUIR | premai 2026 |
| G4 | **Campo `summary` por doc ausente** → gerar summary curto (Haiku) e embeddar/BM25 sobre ele | P0 | 3-4 dev-days | ⭐⭐⭐⭐ | EVOLUIR | dev.to 2026 |
| G5 | **Query-expansion / multi-query** (o único enhancement que sobreviveu ao teste, +6.7 F1) | P1 | 2-3 dev-days | ⭐⭐⭐⭐ | EVOLUIR | arxiv 2606.28367 |
| G6 | **Re-ligar hybrid DEPOIS de G2+G3** e re-medir semanticRatio (não antes — dense estava quebrado) | P1 | 2 dev-days | ⭐⭐⭐ | CONSOLIDAR | arxiv 2604.01733 |
| G7 | **Golden set N=51 → 100+ curado** + adicionar canary online | P1 | 3-4 dev-days | ⭐⭐⭐ | EVOLUIR | anyscale |
| G8 | **Contextual retrieval backfill** (0/1459) — SÓ se G1-G6 não fecharem a meta | P2 | 4-5 dev-days | ⭐⭐ | CONSOLIDAR | Anthropic 2024 |
| G9 | **Embedder maior via GPU** (BGE-M3 / EmbeddingGemma-300M CPU) — só se semântico continuar fraco pós-G2/G3 | P2 | 5+ dev-days | ⭐⭐ | EVOLUIR | bentoml |
| G10 | **Citation faithfulness com penalidade** no `kb-answer` | P2 | 2 dev-days | ⭐⭐ | EVOLUIR | premai |

---

## 5. ROADMAP 3 ondas (EVOLUIR)

- **Onda 1 — "religar o que existe" (ROI máximo, ~4-5 dev-days):** G1 (reranker) + G2 (prefix) + G3 (template). Re-rodar ragas. **Hipótese:** context_recall 0.42 → ~0.65+ só com isto. Se fechar, PARA.
- **Onda 2 — "entender a query + curar" (~8 dev-days):** G4 (summary) + G5 (query-expansion) + G6 (re-ligar hybrid e re-tunar semanticRatio) + G7 (gold-set 100+). Re-medir.
- **Onda 3 — "só se precisar" (~10+ dev-days):** G8 (contextual backfill) + G9 (embedder GPU) + G10 (citation penalty) + agentic/CRAG. **Provavelmente desnecessário** se Ondas 1-2 fecharem — o paper diz que reranker forte torna estes marginais.

**Métrica de saturação (onde parar de subir):** context_recall **~0.80-0.85** com faithfulness ≥0.80. Acima disso, ganho marginal não paga complexidade neste corpus doc-curto. Não perseguir 0.95 — é teatro para gold-set N pequeno.

---

## 6. Surpresas

**Positiva (oimpresso > mercado):**
1. **ADR 0318 — ragas REAL com gold-set curado** (não mock). A maioria dos times roda RAGAS synthetic e se ilude; a Jana tem N=51 honesto medindo context_recall 0.42. Isso é maturidade de eval acima da média.
2. **ADR 0312 — desligou o hybrid COM MEDIÇÃO** (FULLTEXT 6/7 vs dense 4/7), não por palpite. Diagnosticou até o mecanismo (inversão de similaridade). Raro.
3. **Reranker + contextual + graceful degradation JÁ construídos** — o trabalho de engenharia pesado está feito; falta plugar. Isto muda o projeto de "EVOLUIR caro" para "CONSOLIDAR barato" na Onda 1.

**Negativa (mercado > oimpresso):**
1. **Peças certas construídas e DESLIGADAS** — reranker em rede isolada, contextual 0/1459, prefix ausente. É o pior dos mundos: pagou o custo de construir e não colhe o benefício.
2. **Embeddar ~só o título** (400 bytes) — o dense nunca teve chance; qualquer conclusão sobre "semântico ruim" está contaminada por este bug de template.

---

## Fontes

- [Beyond the Reranker (arxiv 2606.28367)](https://arxiv.org/html/2606.28367v1) — reranker domina; enhancements marginais com ele presente
- [Qwen3 Embedding blog](https://qwenlm.github.io/blog/qwen3-embedding/) — instruction-aware, asymmetric, prefix +1-5%
- [jina-embeddings-v5 (arxiv 2602.15547)](https://arxiv.org/html/2602.15547) — Query:/Document: prefix asymmetric
- [BM25 to Corrective RAG (arxiv 2604.01733)](https://arxiv.org/html/2604.01733v1) — BM25 bate dense em jargão; RRF hybrid > ambos
- [Anthropic Contextual Retrieval](https://www.anthropic.com/news/contextual-retrieval) — -49% a -67% failed retrievals
- [Late Chunking vs Contextual (kx/medium)](https://medium.com/kx-systems/late-chunking-vs-contextual-retrieval-the-math-behind-rags-context-problem-d5a26b9bbd38) — contextual 120× mais lento na indexação
- [premai Production RAG 2026](https://blog.premai.io/building-production-rag-architecture-chunking-evaluation-monitoring-2026-guide/) — 80% falhas = ingestão/chunking; citation faithfulness
- [callmissed RAG best practices 2026](https://www.callmissed.com/en/blog/rag-best-practices-2026) — chunk+hybrid+rerank+measure
- [dev.to RAG not dead 2026](https://dev.to/young_gao/rag-is-not-dead-advanced-retrieval-patterns-that-actually-work-in-2026-2gbo) — pipeline canônico
- [Query Rewriting & Multi-Query (geocommunity)](https://thegeocommunity.com/blogs/generative-engine-optimization/query-rewriting-multiquery-rag/) — HyDE +20%, multi-query recall
- [Agentic RAG survey (arxiv 2501.09136)](https://arxiv.org/html/2501.09136v4) — self-RAG/CRAG, custo 3-10×
- [anyscale RAG eval](https://docs.anyscale.com/rag/evaluation) — gold-set 50-100q
- [bentoml open-source embeddings 2026](https://www.bentoml.com/blog/a-guide-to-open-source-embedding-models) — EmbeddingGemma-300M CPU, BGE-M3

**Evidência 1ª mão:** `Modules/Jana/Services/Retrieval/BgeReranker.php`, `Modules/Jana/Services/Memoria/Contextual/ContextualizerService.php` + estado CT 100 levantado 2026-07-04 (ADRs 0312/0318).

---

## Adendo (Claude, pós-auditoria) — teste empírico + "vira máquina?"

### G2 (instruction-prefix) REFUTADO empiricamente como P0
A grade pôs o prefix como P0 (nota 10, "raiz da 0312") a partir de papers + leitura do ADR. **Medido no CT 100 (recall@5, golden set N=27):**

| Caminho | recall@5 |
|---|---|
| semantic raw (ratio 1.0) | 0.704 (19/27) |
| semantic **+ prefix** | 0.741 (20/27) |
| hybrid 0.6 raw | 0.704 (19/27) |

O prefix move **+1 doc de 27 (+3.7pp)** — MARGINAL, não a alavanca dominante. A auditoria (web+ADR) superestimou G2; a medição corrige. **Regra reforçada:** cada gap desta grade é HIPÓTESE até A/B empírico próprio — a nota não é fato. O reranker (G1) permanece a aposta forte mas **não-testada** (rede BGE bloqueada).

### "Vira máquina? conserto manual apodrece" (Wagner) — critério de aceite de CADA gap
Um gap só é "feito" quando vira máquina, não ajuste manual:
1. **Config-as-code** (não `.env` manual): prefix/template/driver/ratio no `config.php` + `SettingsReconciler` (que já reconcilia o embedder — perdido 2× por drift). Ajuste manual no `.env` = proibido.
2. **Canary semanal que mede** (espinha da máquina): `jana:ragas-real-eval` (ADR 0318, dom 07:00 CT 100) já roda — adicionar `recall@k` do golden set como métrica trackada.
3. **Catraca de não-regressão**: baseline honesto (0.42→alvo ~0.65) vira floor; se cair, o canary alerta (padrão ADR 0256/0275).
4. **Gold-set como contrato vivo** (G7): `recall-golden.yaml` é a régua; expandir+manter = não apodrecer.
5. **Decisão em ADR** (reversível/auditável), não ajuste solto.

**Corolário:** a Onda 1 NÃO começa religando reranker/hybrid — começa montando o item 2 (canary medindo `recall@k`). Sem a máquina de medição, "melhorou" é palpite e a regressão passa silenciosa no próximo upgrade de lib/embedder. **A máquina vem antes do conserto.**
