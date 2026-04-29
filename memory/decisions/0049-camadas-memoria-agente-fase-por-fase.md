# ADR 0049 — Camadas de memória do agente: ligar fase por fase, medir antes de evoluir

**Status:** Aceito · Estende [ADR 0036](0036-replanejamento-meilisearch-first.md) · Estende [ADR 0037](0037-roadmap-evolucao-tier-7-plus.md)
**Data:** 2026-04-29
**Origem:** Pesquisa exaustiva Wagner (29-abr-2026) — anexo `files.zip/ADR-002-camadas-memoria.md`

---

## Contexto

"Memória de agente" não é uma coisa só. São **6 camadas** com funções diferentes que coexistem. A escolha não é "qual usar" — é em que ordem ligar cada uma e como **medir cada uma isoladamente**.

Falhas históricas em projetos de IA com memória:
- Construir RAG sofisticado antes de ter dados → medindo nada, otimizando intuição.
- Ligar todas as camadas juntas → impossível debugar qual quebrou.
- Pular avaliação → quando o usuário reclama, não há baseline pra comparar.

---

## Decisão

Ligar memória **em fases**, começando pelas mais baratas, e **medir antes de evoluir**. Critério para passar de fase: **Recall@3 > 0.80** com gabarito real (ver ADR 0051).

### Mapa das 6 camadas

| Camada | O que faz | Quando ligar | Estado oimpresso (29-abr-2026) |
|--------|-----------|--------------|---------------------------------|
| **Working** | Contexto da chamada atual (params, prompt) | Já está ligado por design | ✅ ContextoNegocio injetado (MEM-HOT-2 / ADR 0047) |
| **Conversation History** | Últimas N mensagens da sessão | Fase 1 — dia 1 | ✅ últimos 20 msgs em `ChatCopilotoAgent::messages()` |
| **Episodic** | Eventos com timestamp por business_id | Fase 2 — semana 2 | ⚠️ parcial — `copiloto_memoria_facts` tem `valid_from/valid_until` mas sem episódios formais |
| **Semantic / RAG** | Fatos sem amarração temporal, busca por similaridade | Fase 3 — semana 3 | ✅ MeilisearchDriver hybrid (MEM-HOT-1 / ADR 0047) |
| **Procedural** | "Como fazer" — fluxos, prompts, few-shot | Já está nos prompts | ✅ system prompt + agents especializados |
| **Reflective** | O agente aprendendo com seus próprios erros | Só após 100+ interações reais | ❌ não-iniciado — gate em ADR 0051 |

---

## Justificativa

- **Camadas independentes**: cada uma pode estar funcionando ou quebrada, e isso só se descobre **testando isolado**.
- **Conversation History + Episodic** dão 80% do valor percebido com 20% do esforço.
- **Semantic** só faz diferença depois de ter dados reais acumulados.
- **Reflective sem dados** é over-engineering.

---

## Consequências

**Positivas:**
- Cada fase entrega valor mensurável antes da próxima.
- Reduz risco de gastar 2 meses em RAG sofisticado que não move a métrica.
- Permite diagnóstico cirúrgico ("layer X quebrou", não "memória quebrou").

**Negativas / Trade-offs:**
- Mais retrabalho potencial conforme camadas se sobrepõem (ex: episodic + semantic competem por mesmo dado).
- Exige disciplina de medir antes de evoluir.

---

## Critério para evoluir de fase (gate obrigatório)

> **Só passar para a próxima camada quando a anterior atingir Recall@3 > 0.80 com gabarito real.**

Métricas formais e gabarito → ADR 0051.

---

## Aplicação imediata no oimpresso

| Próximo passo | Camada | Bloqueio |
|---------------|--------|----------|
| Construir gabarito 50 perguntas Larissa-style (= COP-002 / MEM-P2-1) | Semantic + Episodic | A4 validação Larissa |
| Implementar `copiloto_memoria_episodios` table com timestamp + tipo | Episodic | Gabarito pronto + Recall@3 medido em Semantic |
| ConversationSummarizer (>15 turnos) — MEM-S8-2 | Conversation History | A3 (SemanticCacheMiddleware) feito |

---

## Referências

- Pesquisa exaustiva Wagner: `files.zip/ADR-002-camadas-memoria.md` (29-abr-2026)
- ADR 0036 — Meilisearch first (este ADR estende com formalização das camadas)
- ADR 0037 — Roadmap Tier 7+ (este ADR substitui o tier model por camadas funcionais)
- ADR 0051 — Estratégia de teste e avaliação de memória (gates desta ADR vivem lá)
