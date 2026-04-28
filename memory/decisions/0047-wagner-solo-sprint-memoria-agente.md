# ADR 0047 — Wagner solo: sprint memória do agente (token economy + assertividade)

**Data:** 2026-04-28
**Status:** Aceito
**Decidido por:** Wagner [W]
**Contexto:** Copiloto IA real ativo em produção (gpt-4o-mini) desde 2026-04-28. Primeiras conversas reais revelaram 2 gaps críticos (ADR 0046). Wagner operando solo — time (Felipe/Maíra/Luiz/Eliana) não disponível neste sprint.

---

## Contexto

Com o Copiloto em produção e os gaps de memória revelados nas primeiras conversas reais, Wagner identificou duas prioridades operacionais:

1. **Economizar tokens** — cada conversa com LLM custa dinheiro; contexto mal gerenciado infla o custo linearmente.
2. **Melhorar assertividade** — o Copiloto não sabe faturamento, clientes, metas. Responde como GPT genérico.

Ao mesmo tempo, o time temporariamente não está disponível para execução. Todo o backlog foi centralizado em Wagner.

Estado atual da stack de memória (2026-04-28):

| Camada | Estado | Problema |
|--------|--------|----------|
| C Hot (SqlDriver) | ✅ ativo | Janela 20 msgs sem compressão → token inflation |
| C Cold (MeilisearchDriver) | ⚠️ ativo mas quebrado | Scout default (full-text) → 0 hits; não passa `hybrid:{embedder}` |
| ChatCopilotoAgent | ⚠️ "burrinho" | `instructions()` vazio de contexto de negócio — ADR 0046 |
| Semantic Caching | ❌ não implementado | ADR 0037 Sprint 8 — reduz 68.8% tokens |
| Conversation Summary | ❌ não implementado | Comprime hot window pra <500 tokens |
| Profile Distillation | ❌ não implementado | Contexto rico de negócio compacto |

---

## Decisão

### D1 — Wagner solo mode

Todos os donos de task são migrados para `[W]`. Sem exceção. Sem co-donos por ora.

Não é redução de capacidade — é clareza de que uma única pessoa está operando o ciclo. Quando o time retornar, tarefas são redistribuídas na abertura do Cycle 02.

### D2 — Sprint memória do agente (prioridade P0/P1 imediata)

Ordenação por **impacto × esforço**:

#### P0 — Hotfixes (esta semana, <2 dias úteis total)

| # | Task | Impacto | Esforço | Arquivo-alvo |
|---|------|---------|---------|--------------|
| MEM-HOT-1 | **Hybrid embedder no MeilisearchDriver** | Recall de 0→50%+ | 2h | `Modules/Copiloto/Services/Memoria/MeilisearchDriver.php` |
| MEM-HOT-2 | **Injetar ContextoNegocio no ChatCopilotoAgent** (ADR 0046 Caminho A) | Assertividade: GPT genérico → sabe negócio | 4h | `Modules/Copiloto/Ai/Agents/ChatCopilotoAgent.php` |

**MEM-HOT-1 (Hybrid fix):** substituir `CopilotoMemoriaFato::search($query)->where(...)->get()` por chamada direta ao Meilisearch com:

```php
$client->index('copiloto_memoria_facts')->search($query, [
    'hybrid' => ['embedder' => 'openai', 'semanticRatio' => 0.7],
    'filter' => "business_id = {$businessId} AND user_id = {$userId}",
    'limit' => $topK,
]);
```

**MEM-HOT-2 (ContextoNegocio):** `ChatCopilotoAgent::instructions()` deve receber instância de `ContextoNegocio` e incluir no system prompt:
- Nome da empresa + setor
- Meta de faturamento atual
- Top 5 produtos vendidos (resumo)
- Período atual (mês/ano)
- Usuário (nome + papel)

#### P1 — Sprint 8 real (semana 2, ~3.5 dias úteis)

| # | Task | Impacto | Esforço |
|---|------|---------|---------|
| MEM-S8-1 | **SemanticCacheMiddleware** (ADR 0037 Sprint 8) | -68.8% tokens LLM | 1.5d |
| MEM-S8-2 | **ConversationSummarizer** — comprime hot window | -40% tokens em conv longas | 1.5d |
| MEM-S8-3 | **ProfileDistiller** — extrai perfil de negócio compacto pro system prompt | +assertividade permanente | 1d |

**MEM-S8-1:** middleware antes de `recallMemoria()`:
1. Gerar embedding da query atual (text-embedding-3-small)
2. Buscar no Redis: existe resposta semântica similar com score >0.9?
3. Se sim → retorna cache. Se não → chama LLM, salva no Redis com TTL 6h.

**MEM-S8-2:** quando conversa supera 15 turnos:
1. Pegar turnos 1..N-10 (os mais antigos)
2. Chamar gpt-4o-mini com prompt "Resuma em ≤200 tokens os fatos principais desta conversa"
3. Substituir os N-10 turnos por 1 mensagem `role=system` com o resumo
4. Janela efetiva: ≤10 msgs reais + 1 resumo (~200 tokens)

**MEM-S8-3:** job diário por `business_id`:
1. Agregar metas ativas, top produtos, DRE resumido, histórico Copiloto
2. Serializar em JSON compacto <300 tokens
3. Cachear no Redis com key `copiloto:profile:{business_id}`
4. `ChatCopilotoAgent::instructions()` lê o profile + injeta

#### P2 — Sprint 7 RAGAS baseline (semana 3)

| # | Task | Impacto | Esforço |
|---|------|---------|---------|
| MEM-P2-1 | **Golden set v1 — 50 perguntas Larissa-style** | Baseline mensurável | 1.5d |
| MEM-P2-2 | **RRF tuning** — A/B `semantic_ratio` 0.3 vs 0.7 | +10-15% recall | 0.5d |

### D3 — Sequência de validação (gate obrigatório)

```
[MEM-HOT-1 + MEM-HOT-2]  ← esta semana
        ↓ teste: Larissa pergunta faturamento → responde certo?
[MEM-S8-1 + MEM-S8-2]    ← semana 2
        ↓ teste: conv 20 turnos → tokens usados < 1.500?
[MEM-S8-3]                ← semana 2 fim
        ↓ teste: profile compacto aparece no system prompt?
[MEM-P2-1]                ← semana 3
        ↓ golden set respondido → gravar baseline RAGAS
[MEM-P2-2]                ← semana 3 fim
        ↓ A/B test 2 configs → escolher ratio vencedor
```

---

## Consequências

**Positivas:**
- Assertividade: Copiloto sabe faturamento, clientes, metas da empresa
- Token economy: -68.8% com semantic cache + -40% com conversation summary
- Baseline mensurável: RAGAS golden set vai mostrar onde estamos de verdade

**Negativas / riscos:**
- MEM-HOT-1 requer acesso ao Meilisearch client direto (bypass Scout) — testar que `business_id` scope não vaza entre tenants
- MEM-S8-1 (semantic cache) pode retornar resposta stale se perfil do negócio mudou — TTL curto (6h) mitiga
- MEM-S8-2 (summarizer) pode perder nuances de conversas antigas — aceitável dado custo

**Métricas de sucesso deste sprint:**
1. `memoria_recall_chars > 0` nos logs após conversa com fato indexado (MEM-HOT-1)
2. Larissa pergunta "qual meu faturamento deste mês" → Copiloto responde com número correto (MEM-HOT-2)
3. Conv de 20 turnos usa <2.000 tokens de contexto (MEM-S8-2)
4. Cache hit rate >30% após 10 conversas similares (MEM-S8-1)
5. 50 perguntas golden set com faithfulness >0.70 RAGAS (MEM-P2-1)

---

## ADRs relacionados

- [0035](0035-stack-ai-canonica-wagner-2026-04-26.md) — Stack canônica IA
- [0036](0036-replanejamento-meilisearch-first.md) — Meilisearch first
- [0037](0037-roadmap-evolucao-tier-7-plus.md) — Roadmap Sprint 7-11
- [0046](0046-chat-agent-gap-contexto-rico.md) — Gap ChatCopilotoAgent (origem MEM-HOT-2)
