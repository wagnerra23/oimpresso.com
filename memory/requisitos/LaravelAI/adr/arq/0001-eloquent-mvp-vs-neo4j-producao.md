# ADR ARQ-0001 (LaravelAI) · Eloquent + pgvector pro MVP, Neo4j em produção (futuro)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

Knowledge Graph precisa de:
- **Armazenar** entidades (user, role, permission, ADR) + relações (HAS_ROLE, CAN_ACCESS)
- **Consultar** com queries multi-hop ("quais users → roles → permission X")
- **Embeddings** vetoriais pra busca semântica (RAG)
- **Multi-tenant** scope robusto

Opções:

| Tech | Vantagens | Desvantagens | Quando usar |
|---|---|---|---|
| **Eloquent + pgvector** | Zero infra extra; já usamos PostgreSQL/MySQL | Queries multi-hop ficam pesadas (>3 hops); pgvector limitado em MySQL | MVP < 50k nós |
| **Neo4j** | Cypher poderoso; visualização nativa; performance excelente em multi-hop | Infra extra; aprender Cypher; license free não cobre tudo | Produção > 50k nós ou queries complexas |
| **FalkorDB** | GraphRAG nativo; container leve | Ecossistema Laravel raso | Avaliar quando ecossistema amadurecer |
| **TigerGraph / Memgraph** | Performance enterprise | Custo + complexidade | Não justifica pra SaaS pequeno |

Stack atual oimpresso: PostgreSQL via Hostinger (com pgvector instalável) ou MySQL 8 (sem pgvector — usar JSON column como fallback).

## Decisão

**MVP: Eloquent (`kg_entities`, `kg_relations`) + pgvector (PostgreSQL) ou JSON+app-side similarity (MySQL).**

**Migrar pra Neo4j quando ≥1 dos critérios bater:**
- ≥50k nós em algum business
- Queries com 3+ hops virarem comuns (>10% do volume)
- Visualização exigir interatividade que React Flow não dá
- Equipe ganhar capacidade de operar Neo4j (hoje só Wagner)

## Consequências

**Positivas:**
- POC Onda 1 entrega valor em 2-3 semanas (sem infra extra)
- Migration plan claro: schema relations bate em ambos (Cypher é mais expressivo, Eloquent suporta basics)
- pgvector oficial PostgreSQL — performance OK até 100k vetores
- Tenant Pro/Enterprise não percebem diferença (UX igual)
- Time aprende grafo gradualmente (Eloquent → Cypher só quando necessário)

**Negativas:**
- Queries multi-hop em Eloquent = JOINs recursivos (PostgreSQL `RECURSIVE WITH`); funciona mas não tão expressivo
- Sem `ARRANGE` Cypher pra path-finding ótimo
- pgvector em MySQL: usar JSON column + busca brute-force OK até ~10k embeddings; depois lento

## Schema portável (funciona Eloquent → Neo4j)

```sql
-- MVP Eloquent
CREATE TABLE kg_entities (
    id BIGINT UNSIGNED PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    label VARCHAR(255) NOT NULL,
    properties JSON,
    embedding VECTOR(1536),  -- pgvector
    -- ...
);

CREATE TABLE kg_relations (
    id BIGINT UNSIGNED PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    from_entity_id BIGINT UNSIGNED NOT NULL,
    to_entity_id BIGINT UNSIGNED NOT NULL,
    relation VARCHAR(50) NOT NULL,
    metadata JSON,
    -- ...
);
```

Migra direto pra Neo4j:

```cypher
LOAD CSV FROM 'kg_entities.csv' AS row
CREATE (n:Entity {id: row.id, business_id: row.business_id, type: row.type, ...});

LOAD CSV FROM 'kg_relations.csv' AS row
MATCH (a:Entity {id: row.from_entity_id}), (b:Entity {id: row.to_entity_id})
CREATE (a)-[r:Relation {type: row.relation, ...}]->(b);
```

## Service abstraction

```php
interface GraphServiceInterface {
    public function findUsersWithPermission(int $businessId, string $permission): Collection;
    public function findShortestPath(int $businessId, int $fromId, int $toId, int $maxHops = 5): array;
    public function findRelated(int $businessId, int $entityId, string $relation, int $depth = 1): Collection;
}

class EloquentGraphService implements GraphServiceInterface { /* MVP */ }
class Neo4jGraphService implements GraphServiceInterface { /* futuro */ }
```

Trocar implementação = mudar binding ServiceProvider. Zero impacto em controllers / agente.

## Decisões em aberto

- [ ] PostgreSQL ou MySQL? (Hostinger oferece ambos; PostgreSQL melhor pra pgvector)
- [ ] Trigger pra migrar pra Neo4j: 50k nós ou tempo de query > 1s p95?
- [ ] Ecossistema FalkorDB vale revisitar 2027?

## Alternativas consideradas

- **Neo4j desde o início** — rejeitado: infra extra, time não pronto, MVP atrasa
- **Sem grafo (só RAG)** — rejeitado: perde multi-hop natural ("quais users têm permissão em todos os recursos do tipo X")
- **Hybrid (Neo4j só pra Enterprise)** — rejeitado pra MVP: complexidade dobrada; revisitar futuro

## Referências

- pgvector docs (https://github.com/pgvector/pgvector)
- Neo4j Cypher cheat sheet
- `_Ideias/LaravelAI/evidencias/conversa-claude-2026-04-mobile.md`
