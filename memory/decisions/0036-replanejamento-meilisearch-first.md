# ADR 0036 — Replanejamento canônico: Meilisearch primeiro, Mem0 por último

**Status:** ✅ Aceita — **REVISA ADRs 0031, 0033 e 0035** com conhecimento agregado
**Data decisão:** 2026-04-26 (sessão 17 fim de noite)
**Autor:** Wagner — *"replaneje, com os conhecimentos agregados. consolide melhor ROI. deixe o Mem0 por último. já tá instalado outro [Meilisearch]"*
**Registrado por:** Claude (sessão `dazzling-lichterman-e59b61`)
**Revisa parcialmente:**
- [ADR 0031 — `MemoriaContrato` + Mem0RestDriver default](0031-memoriacontrato-mem0-default.md) — driver default trocado
- [ADR 0033 — Vector store backend](0033-vector-store-meilisearch-pgvector-mem0.md) — Meilisearch promovido de fallback condicional pra default
- [ADR 0035 — Stack canônica](0035-stack-ai-canonica-wagner-2026-04-26.md) — ordem dos sprints reordenada

---

## Contexto: o que mudou

ADRs 0031/0033/0035 foram lavrados **antes** de:

1. ✅ **Sprint 1 ter sido executado** ([PR #24](https://github.com/wagnerra23/oimpresso.com/pull/24)) — `laravel/ai` + 3 Agents + `LaravelAiSdkDriver` mergeados em `6.7-bootstrap` (`3d64e5bb`).
2. ✅ **Meilisearch ter sido instalado** — local Windows rodando em `127.0.0.1:7700` (PID 31928); Hostinger v1.10.3 binário pronto em `~/meilisearch/` (GLIBC 2.34 compatível, descoberta: latest exigia 2.35).
3. ✅ **Confirmação Hostinger:** Go 1.22.0 disponível em `/opt/golang/1.22.0/bin/go` (Wagner estava certo). Self-hosted viável.
4. ✅ **Laravel AI SDK** tem `Embeddings.php` builtin — gera vetores nativos sem dep extra.
5. ✅ Comparativo Capterra dos 9 sistemas + 7 frameworks (memory/comparativos/) já feito + 26/27 testes Pest passando.

**Realidade financeira:** Copiloto tem **0 cliente pagante** hoje. Pagar Mem0 mensal ($25-300) **antes** de validar tese de retenção = queimar grana sem retorno mensurável. Meilisearch self-hosted permite **iterar grátis** até validar valor.

---

## Decisão: replanejar com Meilisearch como driver default

**Reordena o roadmap canônico do ADR 0035** mantendo a **stack-alvo** (`laravel/ai` + Vizra ADK + `MemoriaContrato`) intacta — só muda **qual driver da camada C** entra primeiro.

### Roadmap revisado (7 sprints)

| Sprint | Era ADR 0035 | Agora ADR 0036 | ROI delta |
|---|---|---|---|
| **1** | `laravel/ai` swap | ✅ **FEITO** ([PR #24](https://github.com/wagnerra23/oimpresso.com/pull/24)) | — |
| **2** | Vizra ADK | **DEPLOY DO SPRINT 1** primeiro: SSH `composer install` + iniciar Meilisearch daemon Hostinger + setar `OPENAI_API_KEY` + `COPILOTO_AI_DRY_RUN=false` + smoke `/copiloto` | **Tira Copiloto de fixtures EM PRODUÇÃO** — destrava demos pra prospects |
| **3** | Vizra ADK | Vizra ADK install + `CopilotoAgent extends Vizra\Agent` + migrar `copiloto_conversas` → `vizra_sessions` + 5-10 tools | Sem mudança vs ADR 0035 |
| **4** | Mem0RestDriver | **`MeilisearchDriver`** primeiro — implementa `MemoriaContrato` via Scout + Meilisearch + embeddings via `Laravel\Ai\Embeddings` | **R$0/mês recorrente** vs $25-300/mês do Mem0 |
| **5** | Bridge memória↔chat (Mem0) | Bridge memória↔chat com **MeilisearchDriver** (busca top-K antes / extrai fatos via LLM depois em Job assíncrono) | Sem mudança de feature, custo zero |
| **6** | Tela LGPD | Tela `/copiloto/memoria` (US-COPI-MEM-012) + soft delete = "esquecer" + opt-out | Sem mudança |
| **7** | Stress test + Vizra Cloud opt | Eval LLM-as-Judge no CI/CD via Vizra ADK + stress test + decisão Vizra Cloud (managed) | Sem mudança |
| **8 (CONDICIONAL)** | — | **`Mem0RestDriver`** entra como **upgrade**, NÃO baseline — só se Meilisearch falhar em alguma feature crítica (ex.: dedup automático, summary inteligente, conflict resolution sofisticado) | Investimento $$ só após validação de tese |

### Trigger pra ativar Sprint 8 (Mem0 upgrade)

Mem0 vira default só se ≥1 dos critérios bater:

- [ ] Meilisearch dedup por similarity threshold falha em ≥10% dos casos (gera duplicatas que Larissa percebe)
- [ ] Conversas >50 turnos perdem contexto perceptível com summary rolling caseiro
- [ ] Conflict resolution temporal precisa de validity windows native (Mem0/Zep dão; Meilisearch não)
- [ ] Wagner explicitamente pedir
- [ ] Tier 6-7 LongMemEval virar requisito comercial (raro pra MVP)

Sem ≥1 trigger, **fica em MeilisearchDriver indefinidamente**.

---

## Math do ROI revisado (consolidado)

**Pressupostos:**
- 1 sprint Wagner ≈ 80h de código PHP
- Hostinger Cloud Startup já pago (custo marginal de Meilisearch ≈ R$0)
- Mem0 starter: $25/mês = ~R$125/mês = ~R$1.500/ano
- Mem0 escala (50 clientes ativos): $300/mês = ~R$1.500/mês = ~R$18.000/ano
- Cliente Tier 1A do oimpresso (ADR 0026): R$199-599/mês

### Caminho ADR 0035 original (Mem0 default)

| Item | Custo |
|---|---|
| 7 sprints código | mesmo |
| Mem0 recorrente (12 meses) | R$1.500-18.000 |
| Risco | "queimar Mem0 sem cliente pagante" — saída cara cedo |
| ROI | Tier 6-7 LongMemEval mas custo recorrente sem retorno validado |

### Caminho ADR 0036 revisado (Meilisearch default + Mem0 condicional)

| Item | Custo |
|---|---|
| 7 sprints código | **mesmo** |
| Meilisearch recorrente (12 meses) | **R$0** (já no servidor) |
| Risco | Tier 5-6 LongMemEval — pode bater limite em casos extremos. Mitigação: ADR 0033 prevê schema com `valid_from/until`, soft delete LGPD; dedup via Scout similarity |
| ROI | **Igual feature-set MVP, R$0/mês**. Mem0 só entra com tese validada |

### Delta de economia anual

- Cenário conservador: **R$1.500/ano economizados** (Mem0 starter)
- Cenário escala (50 clientes): **R$18.000/ano economizados** (Mem0 mensalidade cresce)

### Quanto isso vale na meta R$5mi/ano (ADR 0022)?

R$18.000/ano ≈ **1 mês de marketing digital** OU **3 meses de Vizra Cloud managed** OU **1 sprint extra do Wagner em features comerciais (PricingFpv)**.

Não é dinheiro grande. Mas é **dinheiro que NÃO sai antes de comprovar valor** — é a diferença entre "validamos antes de pagar" e "pagamos pra validar".

---

## Justificativa (consolidada)

1. **Já investimos em Meilisearch** (sessão 17) — local rodando + Hostinger pronto. Mem0 first desperdiçaria esse investimento.
2. **Custo zero recorrente** mantém runway até primeiro cliente pagante. Mem0 vira despesa antes de receita.
3. **Hybrid search Meilisearch é estado-da-arte** pra MVP — Tier 5-6 LongMemEval é mais que suficiente pra Copiloto não-crítico.
4. **`MemoriaContrato` é interface trocável** (ADR 0031) — `MeilisearchDriver` agora, `Mem0RestDriver` quando trigger ativar. Zero refactor da app.
5. **Laravel AI SDK tem embeddings nativos** — não precisa de Mem0 só pra ter vetores.
6. **Self-hosted = soberania sobre dados** (LGPD friendly, Wagner controla onde fato do cliente mora).
7. **Trade-off explícito:** trocamos Tier 6-7 por Tier 5-6 LongMemEval, ganhamos ~R$1.500-18.000/ano.

---

## Consequências

✅ **Sprint 4-5 ficam mais magros** — Meilisearch + Scout integration é mais simples que Mem0 REST adapter (não precisa wrapping de tier scoping, agent-generated facts são tratados explicitamente).
✅ Custo recorrente **R$0** até comprovar tese.
✅ `MemoriaContrato` blinda contra abandono de upstream Meilisearch (caso aconteça).
✅ Soberania de dados — LGPD compliant by default.
✅ Reaproveita binário Meilisearch já instalado (sessão 17).
⚠️ **Tier LongMemEval estimado em 5-6** vs 6-7 do Mem0 — features avançadas (dedup automático, conflict resolution) precisam ser implementadas no PHP via Job + similarity threshold.
⚠️ **Daemon Meilisearch Hostinger compartilhada pode ser morto** em horários de baixo uso. Sprint 4 inclui cron simples (`* * * * * pgrep meilisearch || ~/meilisearch/start.sh`) pra resiliência.
⚠️ **Embeddings via OpenAI dentro do laravel/ai custam tokens** — orçar antes de virar prod. Alternativa: embedder local (sentence-transformers via Python sub-processo) — adiar por complexidade.
⚠️ **Sprint 8 (Mem0) fica `condicional`** com critérios mensuráveis no ADR — evita decisão de upgrade por hype.

---

## Alternativas consideradas (e rejeitadas)

- **Mem0 default agora (ADR 0035 original):** rejeitado — queima recorrente sem cliente pagante valida.
- **Pular memória especializada e usar só chat history flat (Vizra default):** rejeitado — não atinge tese "ERP com IA" do ADR 0026; Larissa abandona após 1 semana.
- **Híbrido Mem0 + Meilisearch desde sprint 4:** rejeitado — dobra complexidade sem ganho mensurável de feature pra MVP.
- **pgvector + migrar pra PostgreSQL:** rejeitado em ADR 0033, continua válido.
- **Trocar Meilisearch por Typesense:** considerado. Typesense também tem vector search nativo + Scout driver. Mantém Typesense como segunda alternativa documentada se Meilisearch tiver problema específico.
- **Self-host LangMem:** rejeitado em ADR 0034 — exige container Python.

---

## Refs

ADRs revisados: 0031, 0033, 0035
ADRs relacionados: 0027, 0032, 0034
Comparativos: [stack_agente_php_*](../comparativos/stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md), [copiloto_runtime_memory_*](../comparativos/copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md)
Sessions: [2026-04-26-sprint1-stack-canonica.md](../sessions/2026-04-26-sprint1-stack-canonica.md)
PR: [#24](https://github.com/wagnerra23/oimpresso.com/pull/24) (mergeado `3d64e5bb`)

---

## Anexo — Benchmark externo + triggers concretos (Wagner 29-abr-2026)

> Adendo registrado em 2026-04-29 com base em pesquisa exaustiva do Wagner (`files.zip/ADR-003-stack-armazenamento-memoria.md`). **Não revoga nada** — fortalece a justificativa e concretiza os triggers.

### Benchmark externo citável

Benchmark independente (LongMemEval) confirma que **BM25 + busca vetorial em hybrid mode** entrega:

| Stack | LongMemEval score |
|-------|-------------------|
| **MySQL + Meilisearch hybrid (BM25 + vetor)** | **95.2%** |
| Mem0 (camada dedicada paga) | 93.4% |
| Zep | 71.2% |

Implicação: o stack atual oimpresso (MySQL + Meilisearch hybrid + Redis cache) entrega **+1.8 ponto percentual sobre Mem0 paga** e **+24 pontos sobre Zep**. O Tier 5-6 estimado originalmente foi conservador; a régua real é **Tier 7 confirmado externamente** quando o hybrid embedder está corretamente configurado (= MEM-HOT-1, ADR 0047, deployed em 2026-04-29).

### Triggers concretos para reavaliar (substitui os 5 originais)

Migrar para Postgres+pgvector ou camada dedicada (Mem0/Qdrant/Zep) **somente se ≥1** dos seguintes ativar:

1. **Latência p99** do Meilisearch hybrid passar de **200ms sustentado** (medido por `memory_metrics.latencia_p95_ms` — ADR 0050).
2. **Volume total de vetores** passar de **50M** (`copiloto_memoria_facts.count() > 50.000.000`).
3. **Curadoria de prompts de extração** consumir mais de **1 dia/semana por mais de um trimestre** (sinal: `ExtrairFatosDaConversaJob` com retrabalho recorrente).
4. **Raciocínio temporal forte** virar requisito (ex: "qual era a meta da Larissa em fevereiro?") — Graphiti/Zep seriam insubstituíveis para esse caso.
5. **Wagner explicitamente pedir** após cliente pagante #10 (sinal de tração comercial).

Sem ≥1 trigger ativo, **MeilisearchDriver hybrid continua como driver default por tempo indeterminado**. O custo recorrente fica em **R$ 0/mês**; toda a economia anual (R$1.500-18.000/ano) é redirecionada para sprints de produto.

### Referência cruzada

- ADR 0048 — Vizra ADK rejeitada (Wagner ADR-001)
- ADR 0049 — 6 camadas de memória (Wagner ADR-002)
- ADR 0050 — 8 métricas obrigatórias + tabela `memory_metrics` (Wagner ADR-004)

---

## Compromisso e gatilho de revisão

Revisão deste ADR exigida quando ≥1 trigger do Sprint 8 ativar OU quando Copiloto cruzar 10 clientes pagantes (sinal de tração — pode justificar Mem0 pra escalar). Sem trigger, MeilisearchDriver continua como driver default da `MemoriaContrato`.

**Wagner em 2026-04-26 sessão 17 (fim de noite):** *"replaneje, com os conhecimentos agregados. consolide melhor ROI. deixe o Mem0 por último. já tá instalado outro."* — instrução explícita.
