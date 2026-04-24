---
status: idea
priority: média
problem: "oimpresso quer agente IA central que entende dados do sistema (permissões, ADRs, histórico). Falta knowledge graph + RAG + interface de visualização. Hoje não há fonte unificada do que o sistema 'sabe' — informação espalhada em ADRs, código, audit log, sessions."
persona: "Wagner (entender estado do sistema sem grep) + futuro: cliente faz perguntas em linguagem natural sobre seus dados via chat"
estimated_effort: "MVP em 2-3 sem (Eloquent + RAG); produção 6-8 sem (Neo4j + visualização React)"
references:
  - https://claude.ai/chat/78c2af50-ffd7-4fea-97fd-bb92700fcac1
  - ideia_chat_ia_contextual.md (auto-memória — chat IA contextual já planejado)
  - reference_ultimatepos_integracao.md
related_modules:
  - MemCofre (já tem RAG/embeddings via docs_chat_messages — base parcial)
  - Officeimpresso (visualização do grafo de licenças/permissions)
---

# Ideia: LaravelAI — Knowledge Graph + RAG + agente central

## Problema

oimpresso tem informação espalhada em 4 lugares que ninguém liga:
- **ADRs** em `memory/requisitos/{Modulo}/adr/` (estáticas, em markdown)
- **Audit log** (`activity_log` Spatie) — histórico imutável de ações
- **Permissões** (`spatie/permission`) — relacionamentos user→role→resource
- **Código + sessions** — context vivo

Wagner quer agente IA que **consulte as 4 fontes** ao mesmo tempo e responda perguntas tipo "quem pode editar produtos?" ou "qual ADR justifica essa decisão?".

Hoje só dá pra grep manual.

## Persona

| Persona | Job |
|---|---|
| **Wagner** (operador) | "Por que essa permissão foi assim? Qual ADR cobre?" — sem precisar lembrar onde está |
| **Cliente final** (futuro) | "Quem teve acesso a esses dados ontem?" via chat natural |
| **Auditoria** | Visualizar caminhos user → role → permission → resource graficamente |

## Status

`idea` — explorada em conversa Claude mobile (2026-04-20), ainda não tem POC. Conecta com **chat IA contextual** já planejado (`ideia_chat_ia_contextual.md` na auto-memória) — provável que LaravelAI seja **o backend** desse chat.

## Arquitetura proposta

### Knowledge Graph — escolha de banco

| Opção | Quando usar |
|---|---|
| **Eloquent simulado** (`kg_entities`, `kg_relations`) | **MVP**. Zero infra nova. Cobre 80% dos casos com RAG + embeddings. |
| **Neo4j** | Produção/Enterprise. Quando: +50k nós, queries com 3+ hops, visualização obrigatória, time já usa Cypher. |
| **FalkorDB** | GraphRAG nativo + container leve. Mas ecossistema Laravel ainda é raso. |

**Decisão MVP:** Eloquent. Migrar quando os critérios da tabela acima baterem.

### Schema MVP (Eloquent + pgvector)

```sql
CREATE TABLE kg_entities (
    id BIGINT PRIMARY KEY,
    type VARCHAR(50),         -- 'user', 'role', 'policy', 'resource', 'adr'
    label VARCHAR(255),
    properties JSON,
    embedding VECTOR(1536)    -- pgvector pra similarity search
);

CREATE TABLE kg_relations (
    id BIGINT PRIMARY KEY,
    from_entity_id BIGINT REFERENCES kg_entities,
    to_entity_id BIGINT REFERENCES kg_entities,
    relation VARCHAR(50),     -- 'HAS_ROLE', 'CAN_ACCESS', 'GOVERNED_BY'
    metadata JSON
);
```

### Agente central com Laravel AI SDK

```php
class PolicyAgent {
    use RemembersConversations;

    public function resolve(string $question): string {
        return $this->agent()
            ->withTools([
                new GraphService(),       // relacionamentos via kg_relations
                new AuditLogService(),    // histórico imutável
            ])
            ->withVectorStore('adrs')     // RAG sobre ADRs + políticas
            ->ask($question);
    }
}
```

**3 fontes consultadas em paralelo:**
- Vector store (ADRs e políticas estáticas) — embeddings
- Graph (relacionamentos de permissões) — Eloquent ou Cypher
- Audit log (histórico imutável) — `activity_log` query

## Visualização — decisão tomada na conversa: **React + shadcn**

Wagner descartou Filament (mobile) e definiu **React + shadcn/ui + React Flow + Recharts** como stack:

| Lib | Uso |
|---|---|
| **React Flow** | Grafo interativo de permissões |
| **Recharts** | Timeline do audit log |
| **shadcn/ui** | UI base (já é o padrão do oimpresso) |
| **TanStack Query** | Fetch dos endpoints Laravel |

### Endpoints API Laravel

```
GET /api/graph/nodes
GET /api/graph/edges
GET /api/audit-log
GET /api/rag/queries
```

### 3 painéis na interface

1. **Grafo de permissões** — caminhos visuais user → role → resource
2. **Timeline do Audit Log** — histórico imutável filtrável
3. **Painel RAG/ADRs** — quais documentos o agente consultou + score de relevância

## Decisão pendente: SPA separada vs Inertia

Conversa terminou em aberto:

- **SPA separada** — mais liberdade, mas precisa CORS + auth próprio
- **Inertia.js** — auth e rotas compartilhadas (e oimpresso **já é Inertia v2 + React 19**)

**Recomendação implícita:** Inertia (alinhado com stack atual; ver `preference_persistent_layouts.md` na auto-memória). LaravelAI vira página Inertia tipo `/ai/graph`, `/ai/audit`, `/ai/rag`.

## Conexões

- **MemCofre** — já tem `docs_chat_messages` + `docs_evidences` + RAG parcial via embeddings dos docs. **Provável que LaravelAI estenda MemCofre** ao invés de ser módulo separado.
- **ideia_chat_ia_contextual.md** — chat flutuante que sabe a tela atual. LaravelAI é o backend desse chat.
- **Spatie Permission + Activity Log** — já instalados, são as fontes.
- **Officeimpresso** — visualização do grafo de licenças (machine_id → business → módulos liberados).

## Riscos / pontos de atenção

- **Custo de embeddings**: cada ADR/audit entry novo precisa virar embedding. Usar OpenAI `text-embedding-3-small` (barato) ou local com `sentence-transformers`.
- **Sincronização**: como manter `kg_entities.embedding` atualizado quando ADRs mudam? Observer + queue.
- **Privacidade**: audit log tem dados sensíveis. Filtrar antes de mandar pra LLM.
- **Multi-tenant**: scope por `business_id` em TODAS as queries do graph.

## Próximos passos

1. **POC scope mínimo**: 1 painel só (grafo de permissions) com `kg_entities` populada via seeder do `roles`+`permissions` Spatie
2. **Decidir RAG runtime**: OpenAI direct vs Laravel AI SDK package
3. **Decidir merge com MemCofre**: estender MemCofre ou módulo `LaravelAI` separado?
4. **Validar valor**: vale a complexidade pra um SaaS pequeno? Ou começar com chat IA contextual mais simples?
5. **Promover** quando POC mostrar valor: `_Ideias/LaravelAI/` → `requisitos/LaravelAI/` (ou merge com MemCofre)
