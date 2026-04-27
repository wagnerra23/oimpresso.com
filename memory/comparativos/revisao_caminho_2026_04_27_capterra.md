# Revisão de caminho do Copiloto — auditoria Capterra (2026-04-27)

> **Assunto:** o caminho percorrido até sprint 6 está certo? Existe alternativa de melhor custo-benefício pra reescrever decisões antes de avançar?
> **Data:** 2026-04-27
> **Autor:** Claude (sessão `dazzling-lichterman-e59b61`) sob direção do Wagner — *"revisar o projeto para verificar se ainda está no caminho certo. ou se tem coisa melhor custo benefício"*
> **Concorrentes incluídos:** 5 caminhos (atual canônico + 4 alternativas) — *competimos contra nós mesmos do passado e contra o que não fizemos*
> **Decisão que vai sair daqui:** confirmar (ou pivotar) o roadmap do ADR 0037 antes de Sprint 7
> **Companion docs:**
> - [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md)
> - [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md)
> - [memory/decisions/0036-replanejamento-meilisearch-first.md](../decisions/0036-replanejamento-meilisearch-first.md)
> - [memory/decisions/0037-roadmap-evolucao-tier-7-plus.md](../decisions/0037-roadmap-evolucao-tier-7-plus.md)
> **Template usado:** [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0

---

## 1. TL;DR (5 frases)

1. **6 sprints feitos em 2 dias (2026-04-26/27): Copiloto saiu de fixtures pra stack-alvo canônica funcional** — `laravel/ai` (PR #24) + `MemoriaContrato`+`MeilisearchDriver` (PR #25) + bridge Hot/Cold (PR #26) + tela LGPD (PR #27). 48/51 Pest passing.
2. **Custo recorrente atual: R$0/mês** (Meilisearch self-hosted + Boost/Telescope/Pail/Horizon todos gratuitos) — fiel ao ROI prometido pelo ADR 0036.
3. **Já estamos alinhados com padrão estado-da-arte 2026** (dual-layer Hot/Cold) — concorrentes verticais brasileiros (Mubisys/Zênite/Calcgraf) NÃO têm Copiloto IA-first.
4. **Pendência crítica: SEM baseline mensurável** (RAGAS) — sprints 7-10 do ADR 0037 ficam em "fé" sem isso. Sprint 7 é gate.
5. **O dilema agora:** continuar sequência ADR 0037 (sprint 7 RAGAS → 8 caching → 9 RRF → 10 HyDE) OU pivotar pra trabalho comercial (ADR 0026: PricingFpv / CT-e / Copiloto v1 commercial-ready) já que stack técnica está pronta.

---

## 2. Caminhos avaliados

| Caminho | Tese curta | Tier de mercado |
|---|---|---|
| **A — ATUAL: ADR 0037 sequencial (RAGAS→cache→RRF→HyDE→Mem0)** | Maximizar Tier LongMemEval antes de comercial | Engenharia first |
| **B — Pivot pra ADR 0026 commercial-ready** (PricingFpv + CT-e + Copiloto v1 vendável) | Stack técnica suficiente; foco em receita | Comercial first |
| **C — Stack rebuild: trocar Meilisearch por Typesense agora** | Latência <50ms p99 + auto-embedding builtin | Otimização infra |
| **D — Consolidar Mem0 cedo (cancelar ADR 0036, voltar pra ADR 0035 original)** | Tier 6-7 imediato, $25/mês aceitável | "Pague pra acelerar" |
| **E — Pausa estratégica: validar Larissa antes de codar mais** | Único cliente real, perguntar se ela percebe valor | Validação first |

---

## 3. Matriz feature-by-feature por caminho

Legenda: ✅ atende · 🟡 atende parcial · ❌ não atende · ⏳ depende de evolução

| Critério | A (ADR 0037) | B (Comercial) | C (Typesense) | D (Mem0 cedo) | E (Validar Larissa) |
|---|---|---|---|---|---|
| **Atinge Tier 7+ LongMemEval em 8 sem** | ✅ | ❌ | 🟡 | ✅ | ❌ |
| **Gera primeira receita do Copiloto** | ❌ | ✅ | ❌ | ❌ | 🟡 |
| **Custo recorrente até primeiro cliente** | R$0 | R$0 | R$0 | R$1.500/ano | R$0 |
| **Sprints até resultado visível** | 1 (RAGAS) | 4-6 | 1 | 2 | 0.5 |
| **Risco de "engenharia sem demanda"** | 🟡 médio | ✅ baixo | 🟡 médio | 🔴 alto | ✅ baixíssimo |
| **Risco de "feature pronta sem cliente"** | 🟡 | 🟡 | 🟡 | 🟡 | ❌ |
| **Diferencial vs concorrente vertical** | ⏳ | ✅ (PricingFpv é killer) | ⏳ | ⏳ | ⏳ |
| **Reaproveita 6 sprints feitos** | ✅ | ✅ (stack pronta sustenta) | 🟡 (refaz indexer) | ✅ | ✅ |
| **Bloqueia learning de produto** | 🟡 | ❌ | ❌ | ❌ | ❌ |
| **Alinhado com ADR 0026 (posicionamento)** | 🟡 (suporte) | ✅ (execução direta) | ❌ | 🟡 | ✅ |
| **Custo de pivot futuro se errar** | baixo (interface trocável) | médio (Copiloto v1 com bugs) | alto (refaz schema) | alto (Mem0 lock-in) | baixo |
| **Dificuldade técnica** | média | média-alta | baixa | baixa | baixa |
| **Validação científica** (RAGAS) | ✅ sprint 1 | ❌ | ❌ | ❌ | ❌ |
| **Insights direto do mercado** | ❌ | ✅ (cliente novo) | ❌ | ❌ | ✅✅ |
| **Custo de tokens IA por mês** | ↓ -68% (sprint 8) | atual | atual | -91% Mem0 | atual |

---

## 4. Notas estimadas (escala G2/Capterra 1-5)

| Critério | A (atual) | B (comercial) | C (Typesense) | D (Mem0) | E (Larissa) |
|---|---|---|---|---|---|
| **ROI mensurável 90d** | 3 (sem cliente, ROI técnico) | **5** (gera receita) | 2 | 3 (UX melhora) | **5** (aprende sem custo) |
| **Risco** | 4 (baixo, R$0/mês) | 3 (commercial bugs caros) | 4 | 3 (lock-in $) | **5** (zero risco) |
| **Reaproveita stack atual** | **5** | **5** | 3 | **5** | **5** |
| **Diferenciação comercial** | 3 (técnica invisível) | **5** (PricingFpv = killer) | 2 | 3 | 4 |
| **Disciplina** (medir antes de otimizar) | **5** (RAGAS) | 3 | 3 | 2 | **5** |
| **Score total (média)** | **4.0** | **4.2** ⭐ | 2.8 | 3.2 | **4.8** ⭐ |

**Top 2:** **Caminho E (Validar Larissa) 4.8** seguido de **Caminho B (Comercial) 4.2**. **Caminho A (atual ADR 0037) está em 4.0** — não é ruim, mas não é o melhor ROI absoluto.

---

## 5. Top 3 GAPs do caminho atual

### GAP 1 — Zero validação de demanda do Copiloto

**O que falta:** 6 sprints feitos, 0 minutos de Larissa testando o Copiloto. Estamos otimizando UX/recall sem evidência que ela percebe valor. ADR 0036 já definiu métrica de fé 90d (*"se Larissa explicitamente falar 'lembrou da minha meta'"*) mas ninguém pediu pra ela.
**Esforço pra fechar:** Baixíssimo (1-2h) — Wagner manda um WhatsApp pra Larissa, pede 15min de tela compartilhada testando `/copiloto`.
**Impacto se não fechar:** sprints 7-10 entregam Tier 7+ pra um produto que ninguém pediu — engenharia em vácuo.

### GAP 2 — Vizra ADK bloqueado em L13 (camada B canônica)

**O que falta:** Vizra ADK (camada B canônica do ADR 0035) ainda não suporta L13. `LaravelAiSdkDriver` (sprint 1) está sustentando sozinho — funciona, mas perde features prometidas (auto-tracing, eval LLM-as-Judge, multi-agent workflows).
**Esforço pra fechar:** Alto (>3 sprints) — ou contribuir PR pro Vizra (uncertain), ou trocar pra LarAgent/Neuron AI (overlap não vale), ou esperar upstream (semanas-meses).
**Impacto se não fechar:** ficamos sem 20+ assertions builtin do Vizra → sprint 7 (RAGAS eval) tem que reinventar parte. Custo de cobertura adicional.

### GAP 3 — Embedder Meilisearch ainda não configurado em produção

**O que falta:** PR #25 criou schema, PR #26 criou bridge — mas o **embedder do Meilisearch (POST /indexes/.../settings/embedders com OpenAI text-embedding-3-small) ainda NÃO foi configurado** no Hostinger nem local. Sem isso, hybrid search vira full-text only (ratio 0.5 ainda funciona mas perde semântica).
**Esforço pra fechar:** Baixo (1h SSH + curl) — comando documentado em handoff sessão 18.
**Impacto se não fechar:** sprint 5 (recall) entrega ~50% da capacidade prometida.

---

## 6. Top 3 VANTAGENS reais conquistadas

### V1 — Stack canônica documentada com 7 ADRs interlocked

**Por que é vantagem:** ADRs 0027/0031/0032/0033/0034/0035/0036/0037 formam corpo coerente — qualquer agente (Cursor, Claude) que pegar o repo amanhã sabe exatamente: o que usar, por que, alternativas rejeitadas, gatilhos pra revisar. Isso é raro em projetos solo.
**Como capitalizar:** material pode virar **"PHP AI Stack 2026" blog post** — atrai prospects técnicos. Wagner pode publicar em laravel-news.com / DEV.to.
**Risco de erodir:** baixo — ADRs Nygard são estáveis.

### V2 — R$0/mês recorrente sustentado

**Por que é vantagem:** competidores que adotam Mem0/Letta managed pagam $25-300/mês desde dia 1. Meilisearch self-hosted nos dá Tier 5-6 grátis. ADR 0036 protege essa decisão com triggers mensuráveis.
**Como capitalizar:** vira fala de pricing — "Copiloto sem custo de IA escondido pro cliente final" (concorrentes que pagam Mem0 repassam custo).
**Risco de erodir:** médio (em 12-18m Mem0 pode lançar pricing agressivo).

### V3 — Bridge Hot/Cold dual-layer já alinhado com 2026 state-of-the-art

**Por que é vantagem:** padrão dual-layer Hot/Cold (recall síncrono + extração assíncrona) é exatamente o que Mem0/Zep/Letta convergiram em 2026. Implementamos no PR #26 antes de saber o nome do padrão. **Sorte ou bom design.**
**Como capitalizar:** documentado em ADR 0037 + auto-memória — futuros agentes não vão pivotar sem motivo forte.
**Risco de erodir:** baixo — padrão é estável até pelo menos 2027.

---

## 7. Posicionamento sugerido — qual caminho seguir agora

| Caminho | Veredito |
|---|---|
| A — ADR 0037 sequencial (RAGAS first) | 🟡 OK, mas só **DEPOIS** de E |
| **B — Pivot comercial (PricingFpv/CT-e/Copiloto v1 vendável)** | ✅ tem mérito mas só **depois** de E confirmar valor |
| C — Trocar Meilisearch por Typesense | ❌ otimização prematura |
| D — Mem0 cedo | ❌ contradiz ADR 0036 que era explícito |
| **E — Validar com Larissa primeiro** | ✅ **RECOMENDADO** — feedback antes de mais código |

**Recomendado: E primeiro (1 dia), depois A ou B baseado no feedback.**

**Roadmap revisado pós-E:**

```
Sprint 7 = E (Validar Larissa) → 1 dia
  ├─ Wagner abre /copiloto pra ela em call
  ├─ Pergunta direta: "o que você quer que ele lembre?"
  ├─ Mede: ela pediu memória? ela quer recall? ela quer só metas/relatórios?
  └─ Output: depoimento real
       ├─ se "sim, quero memória" → continua ADR 0037 (RAGAS, etc)
       ├─ se "preciso é PricingFpv" → pivota pra ADR 0026 caminho B
       └─ se "nada disso, preciso CT-e/MDF-e" → ADR 0026 outras features
```

**Frase de posicionamento:**
> *"Stack canônica pronta + R$0/mês recorrente. Antes de otimizar, validamos demanda com 1 cliente real (Larissa do ROTA LIVRE). 1 dia de validação > 4 semanas de engenharia em vácuo."*

---

## 8. Math da meta R$5mi/ano (ADR 0022)

Pressupostos:
- Cliente Tier 1A: R$199-599/mês
- Take-rate boletos: 0,5%
- Conversão prospect → assinante: 10% (otimista)

| Caminho | Sprints até "lança feature comercial" | Meses até primeiro cliente | Receita Y1 estimada |
|---|---|---|---|
| **A → atual** | 5 (sprints 7-11) | ~6 | R$0-2k (sem ainda Copiloto comercial) |
| **B → pivot comercial** | 4 (PricingFpv + CT-e/MDF-e) | ~3-4 | R$10-50k |
| **E → validar primeiro** | 0.5 + N | ~2-3 | depende do feedback |

**Sem cliente pagando, sprints técnicos não geram receita por definição.** Caminho E preserva opcionalidade de B sem sacrificar A.

**Assunção crítica não validada:** "Larissa quer memória do Copiloto." Se SIM, A; se NÃO, B. Sem perguntar, é fé.

---

## 9. Recomendação concreta

### 3 ações prioritárias pra próximas 2 semanas (em ordem)

1. **Validação com Larissa (1-2 dias).** Wagner abre `/copiloto` em call, faz ela testar 3 cenários: pergunta sobre meta, conversa longa (>15 turnos), corrige fato. Mede: ela percebe diferença vs ChatGPT comum? Quer + memória ou + features comerciais?
2. **Configurar embedder Meilisearch no Hostinger (1 dia, GAP 3).** SSH + curl. Sem isso, sprint 5 entrega 50% da capacidade. Receita já documentada.
3. **Decisão pós-validação:** Sprint 7 = RAGAS (continua A) **OU** pivota pra ADR 0026 (caminho B) baseado no feedback dela.

### O que NÃO fazer agora

- ❌ NÃO pular validação Larissa pra "ganhar tempo" — é o gate de tudo
- ❌ NÃO trocar Meilisearch por Typesense (otimização prematura — Meilisearch atende)
- ❌ NÃO ativar Mem0 — ADR 0036 já trava (sem trigger ativado)
- ❌ NÃO deletar ADRs antigos — eles documentam o porquê de cada decisão

### Métrica de fé (90 dias)

> *"Se em 90 dias (até 2026-07-25) Larissa do ROTA LIVRE explicitamente disser 'o Copiloto lembrou da minha meta' OU 'queria que ele lembrasse de X' (qualquer feedback de memória), **confirma caminho A (ADR 0037)**. Se ela falar 'preciso é PricingFpv' OU 'preciso CT-e' OU silêncio (não usou), **pivota pra B (ADR 0026)**. Sem feedback dela em 30 dias = WAGNER PERGUNTA."*

Gatilho de pivot mensurável: 1 sessão de uso real de Larissa em 7 dias = sprint 7 inicia sequencial. 0 sessões em 30 dias = caminho B vira default.

---

## 10. Sources

### Externas (ainda válidas, das pesquisas 2026-04-26)
- [Mem0: State of AI Agent Memory 2026](https://mem0.ai/blog/state-of-ai-agent-memory-2026)
- [Hermes OS: dual-layer architecture 2026](https://hermesos.cloud/blog/ai-agent-memory-systems)
- [RAG at Scale (Redis 2026)](https://redis.io/blog/rag-at-scale/)

### Internas
- [_TEMPLATE_capterra_oimpresso.md](_TEMPLATE_capterra_oimpresso.md) v1.0
- [stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md](stack_agente_php_vizra_prism_mem0_capterra_2026_04_26.md)
- [copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md](copiloto_runtime_memory_vs_mem0_langgraph_letta_zep_capterra_2026_04_26.md)
- ADRs 0026 (posicionamento) / 0035 (verdade canônica) / 0036 (Meilisearch first) / 0037 (roadmap Tier 7+)
- 6 PRs mergeados (#24 / #25 / #26 / #27)

---

## Checklist (template)

- [x] TL;DR cabe em 5 frases
- [x] Mín. 4 concorrentes (5 caminhos)
- [x] 30+ features (15 critérios x 5 caminhos = 75 cells)
- [x] Notas escala 1-5 (estimadas — sem reviews G2 pra "caminho de roadmap")
- [x] **Exatamente 3 GAPS e 3 VANTAGENS**
- [x] **Mín. 3 caminhos posicionamento** (5 caminhos com veredito)
- [x] Math da meta R$5mi/ano
- [x] **3 ações prioritárias** em ordem
- [x] **Métrica de fé** com prazo (90d) e gatilho de pivot
- [x] Sources literais com URL
- [x] Companion docs no frontmatter

**Score: 11/11 ✅**
