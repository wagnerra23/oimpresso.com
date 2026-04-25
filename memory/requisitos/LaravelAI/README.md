---
module: LaravelAI
alias: laravel-ai
status: ativo
migration_target: react
migration_priority: media
risk: medio
problem: "oimpresso tem informação espalhada em ADRs, audit log, permissões Spatie, código, sessions. Wagner+tenants querem agente IA central que consulta as 4 fontes ao mesmo tempo. Hoje só dá pra grep manual."
persona: "Wagner (entender estado do sistema sem grep) + Cliente final (chat natural sobre seus dados) + Auditoria (visualizar permissions)"
positioning: "Pergunte ao seu ERP em português. Quem teve acesso a esse dado ontem? Qual ADR justifica essa permissão? Resposta em 5 segundos, fundamentada nos seus dados — não em alucinação."
estimated_effort: "MVP 2-3 semanas (Eloquent + RAG OpenAI); Produção 6-8 semanas (Neo4j + visualização React + ML)"
revenue_tier: 3
revenue_pricing:
  starter: "Não disponível — diferencial premium"
  pro: "R$ 199/mês (add-on aos planos Pro de outros módulos) — chat contextual + 1.000 queries/mês + visualização básica"
  enterprise: "R$ 599/mês (add-on Enterprise) — multi-modal + visualização React Flow completa + API Graph + 10.000 queries/mês + custom embeddings tenant"
revenue_take_rate: "n/a (subscription puro)"
references:
  - https://claude.ai/chat/78c2af50-ffd7-4fea-97fd-bb92700fcac1
  - ideia_chat_ia_contextual.md (auto-memória)
  - reference_ultimatepos_integracao.md
related_modules:
  - MemCofre (já tem RAG/embeddings parcial)
  - Officeimpresso (visualização do grafo de licenças)
  - Financeiro (consulta natural sobre títulos)
  - NfeBrasil (análise de rejeições com explicação contextual)
last_generated: 2026-04-24
last_updated: 2026-04-24
---

# LaravelAI

> **Pitch para o tenant:** _Pergunte ao seu ERP em português._ "Quem teve acesso a esse dado ontem? Qual ADR justifica essa permissão?" — resposta em 5 segundos, fundamentada nos seus dados. Não é IA que adivinha; é IA que **lê** o seu sistema.

## Propósito

Tornar oimpresso em **plataforma com IA contextual** que entende dados do tenant e responde perguntas em linguagem natural fundamentadas em **3 fontes simultâneas**:

1. **Knowledge Graph** — relacionamentos (user → role → permission → resource) tipados em grafo
2. **RAG (Retrieval Augmented Generation)** — busca semântica em ADRs, políticas, documentação MemCofre
3. **Audit Log** — histórico imutável de toda ação (Spatie activity_log)

Sem alucinação: agente cita a fonte ("Veja ADR 0007", "Audit log 2026-04-22 14:32").

Diferencial vs ChatGPT/Copilot:
- **Multi-tenant nativo** — scope por `business_id` SEMPRE
- **Conhece domínio** — entende regras BR (ICMS, NFC-e, dunning, etc.)
- **Conectado nos dados** — não é "exporta CSV pra outra ferramenta"; consulta direto

## Posicionamento de mercado (revenue thesis)

LaravelAI é **multiplier**, não foundation. Tenant que paga R$ 199-599 pelos módulos foundation (Financeiro, NfeBrasil, RecurringBilling) **paga +R$ 199-599 por LaravelAI** porque transforma a operação:

- Operador: "qual NFC-e foi rejeitada hoje?" → sem treinar SQL
- Auditor: "quem editou esse título nos últimos 7 dias?" → sem entender activity_log
- Gestor: "qual contrato está em risco?" → análise preditiva (futuro ML)

Pricing sugerido **add-on** (não plano standalone):

| Plano base | Add-on LaravelAI | Total mensal | Margem (estimada) |
|---|---|---|---|
| Pro (qualquer módulo R$ 149-449) | R$ 199 | R$ 348-648 | ~70% (custo: tokens OpenAI ~R$ 30/tenant médio) |
| Enterprise (R$ 599-999) | R$ 599 | R$ 1.198-1.598 | ~80% (volume diluí custos) |

**Lock-in moderado** — embeddings são facilmente regeneráveis, mas hábito + treino do agente nos dados específicos do tenant criam fricção pra sair.

**Custo variável crítico**: OpenAI fee. Por isso quota de queries (1k/mês Pro, 10k/mês Enterprise) limita risco. Embeddings caching agressivo reduz custo a longo prazo.

## Índice

- **[SPEC.md](SPEC.md)** — user stories US-AI-NNN + regras Gherkin R-AI-NNN
- **[ARCHITECTURE.md](ARCHITECTURE.md)** — Knowledge Graph + RAG + agente, integração com MemCofre
- **[GLOSSARY.md](GLOSSARY.md)** — vocabulário (embeddings, RAG, Cypher, vector store)
- **[CHANGELOG.md](CHANGELOG.md)** — versão a versão
- **[adr/](adr/)** — decisões numeradas

## Áreas funcionais

| Área | Responsabilidade | Endpoint principal |
|---|---|---|
| **GraphService** | Construir+manter grafo Knowledge | `/api/laravel-ai/graph/...` |
| **VectorStore** | RAG: ADRs/políticas/MemCofre embeddings | `/api/laravel-ai/rag/...` |
| **AgentService** | Orquestra Graph + RAG + Audit pra resposta final | `/api/laravel-ai/chat` |
| **AuditQueryService** | Query Spatie activity_log com semântica | `/api/laravel-ai/audit/...` |
| **VisualizationService** | Endpoints Inertia pro grafo visual | `/laravel-ai/graph` |

## Quem ganha o que

| Persona | Job (concretos) | Tela atende |
|---|---|---|
| **Wagner** (Wagner-tenant) | "Por que essa permissão foi assim? Qual ADR cobre?" | Chat IA contextual (futuro `/laravel-ai/chat`) |
| | "Visualize quem tem acesso a 'sells.create'" | `/laravel-ai/graph?node=sells.create` |
| **Larissa-financeiro** | "Quais clientes vão ficar past_due semana que vem?" | Chat com análise + RAG nos contratos |
| **Auditor** | "Quem teve acesso a transactions/123 e quando?" | `/laravel-ai/audit?subject=transactions/123` |
| **Cliente final** (futuro) | "Qual foi minha última fatura?" via chat natural | Portal B2C (UI-0001 RecurringBilling) com chat |

## Status atual (2026-04-24)

- ✅ **Spec promovida** de `_Ideias/LaravelAI/` para `requisitos/LaravelAI/` (`spec-ready`)
- ⏳ **Onda 1 (POC):** Knowledge Graph básico (kg_entities, kg_relations) populado de Spatie roles+permissions
- ⏳ **Onda 2:** Vector store ADRs + busca semântica (estende MemCofre)
- ⏳ **Onda 3:** AgentService + integração OpenAI / Anthropic
- ⏳ **Onda 4:** Visualização React Flow do grafo
- ⏳ **Onda 5:** Chat IA contextual integrado nas telas
- ⏳ **Onda 6:** Multi-modal (visualizar gráficos) + ML análise preditiva
- ⏳ **Onda 7:** Custom embeddings tenant (Enterprise)

## Onde se conecta

- **MemCofre** — já tem `docs_chat_messages` + `docs_evidences` + RAG parcial via embeddings. **LaravelAI estende MemCofre** ao invés de duplicar (ADR ARQ-0002).
- **Spatie Permission + Activity Log** — fontes principais
- **Financeiro / NfeBrasil / RecurringBilling** — schemas + audit log dos módulos consumidos
- **Officeimpresso** — visualização do grafo de licenças (machine_id → business → módulos)
- **Auto-memória `ideia_chat_ia_contextual.md`** — chat flutuante que sabe a tela atual (LaravelAI é o backend desse chat)

## Próximos passos imediatos

1. **Decidir runtime IA**: OpenAI direto ou Laravel AI SDK (Anthropic + OpenAI agnóstico)?
2. **Decidir merge com MemCofre**: estender (ADR-0002) vs módulo separado?
3. **POC Onda 1**: seedar `kg_entities` de `roles`+`permissions` Spatie + endpoint `/api/laravel-ai/graph/nodes`
4. **Validar valor**: vale a complexidade pra um SaaS pequeno? Ou começar com chat simples primeiro?
5. **Decidir pricing**: add-on (R$ 199-599) faz sentido vs incluir no Enterprise?
