---
type: evidencia
origin_url: https://claude.ai/chat/78c2af50-ffd7-4fea-97fd-bb92700fcac1
origin_title: "Laravel AI SDK com knowledge graph e RAG"
extracted_at: 2026-04-24
extraction_method: claude.ai data export (conversations.json)
created_at: 2026-04-20T10:03:05
updated_at: 2026-04-20T10:16:44
message_count: 6
---

# Laravel AI SDK com knowledge graph e RAG

**URL:** https://claude.ai/chat/78c2af50-ffd7-4fea-97fd-bb92700fcac1


## 1. Wagner — 2026-04-20 10:03

3. Integração com Laravel AI SDK (agente central)
Use o trait RemembersConversations + RAG para documentos + chame o GraphService como tool.
O agente consulta:
•  Vector store (ADRs e políticas estáticas)
•  Graph (relacionamentos de permissões)
•  Audit log (histórico imutável)
Comparação rápida de opções para Knowledge Graph
•  Neo4j → Melhor maturidade, ecossistema, visualização. Ideal para enterprise e queries complexas.
•  FalkorDB → Mais rápido e AI-friendly (GraphRAG). Bom se você quer leveza.
•  Relational simulado (Eloquent) → Mais simples, sem novo banco. Use para MVP (tabelas entities e relations).
•  Evite pacotes pesados se não precisar de graph real — o Laravel AI SDK já cobre boa parte com RAG + embeddings.
Próximos passos para você.  Melhor para Laravel ?


## 2. Claude — 2026-04-20 10:03

 ## Laravel AI SDK + Knowledge Graph: Recomendação

### TL;DR para Laravel

**MVP agora → Relational simulado (Eloquent)**
**Produção/Enterprise → Neo4j**

---

### Por que Eloquent para MVP?

Você já tem o stack. Zero novo banco, zero nova infra. O Laravel AI SDK com RAG + embeddings já resolve 80% dos casos de um knowledge graph real para permissões e ADRs.

```php
// Estrutura mínima funcional
Schema::create('kg_entities', function (Blueprint $t) {
    $t->id();
    $t->string('type');        // 'user', 'role', 'policy', 'resource'
    $t->string('label');
    $t->json('properties')->nullable();
    $t->vector('embedding', 1536)->nullable(); // pgvector
});

Schema::create('kg_relations', function (Blueprint $t) {
    $t->id();
    $t->foreignId('from_entity_id');
    $t->foreignId('to_entity_id');
    $t->string('relation');    // 'HAS_ROLE', 'CAN_ACCESS', 'GOVERNED_BY'
    $t->json('metadata')->nullable();
});
```

---

### Integração com o Agente Central

```php
// GraphService como Tool do AI SDK
class GraphService implements Tool
{
    public function name(): string { return 'query_knowledge_graph'; }

    public function handle(array $input): string
    {
        $path = KgEntity::query()
            ->whereHas('relationsFrom', fn($q) =>
                $q->where('relation', $input['relation'])
                  ->where('to_entity_id', $input['target_id'])
            )
            ->get();

        return $path->toJson();
    }
}

// Agente com as 3 fontes
class PolicyAgent
{
    use RemembersConversations;

    public function resolve(string $question): string
    {
        return $this->agent()
            ->withTools([
                new GraphService(),        // relacionamentos
                new AuditLogService(),     // histórico imutável
            ])
            ->withVectorStore('adrs')      // RAG → ADRs + políticas
            ->ask($question);
    }
}
```

---

### Quando migrar para Neo4j?

| Sinal | Ação |
|---|---|
| +50k nós no grafo | Migrar |
| Queries com 3+ hops | Migrar |
| Visualização obrigatória | Migrar |
| Time já usa Cypher | Migrar agora |

**FalkorDB** só vale se você precisar de GraphRAG nativo e quiser container leve — mas o ecossistema Laravel ainda é raso para ele.

---

### Próximo passo concreto

Quer que eu monte o **scaffold completo**: migrations + GraphService + PolicyAgent + RAG pipeline integrado, pronto para colar no seu projeto Laravel?


## 3. Wagner — 2026-04-20 10:05

Eu quero é uma interface para entender e ver meus dados. O que me recomenda ?


## 4. Claude — 2026-04-20 10:05

 ## Visualização do Knowledge Graph em Laravel

### Melhor opção: **Filament + Apache ECharts**

Zero novo backend, integra direto no seu stack Laravel.

---

### Por quê?

| Opção | Pro | Contra |
|---|---|---|
| **Filament + ECharts** | Já está no ecosistema Laravel, grafo interativo, rápido | Customização avançada exige JS |
| **Neo4j Bloom** | Visualização nativa incrível | Precisa do Neo4j obrigatoriamente |
| **Grafana** | Ótimo para audit logs/métricas | Não é bom para grafos relacionais |
| **Observable/D3.js** | Máxima flexibilidade | Muito trabalho manual |
| **Retool** | Rápido de montar | Pago, fora do seu stack |

---

### O que você consegue ver

Com Filament + ECharts você teria **3 painéis práticos**:

**1. Grafo de permissões** — quem acessa o quê, caminhos visuais entre usuário → role → resource

**2. Timeline do Audit Log** — histórico imutável em linha do tempo filtrável

**3. Painel RAG/ADRs** — quais documentos o agente consultou, com score de relevância

---

### Quer que eu monte?

Posso gerar o **Filament Resource + widget ECharts** com os dados do seu GraphService já mapeados — nós, arestas e filtros por tipo de entidade. É só confirmar.


## 5. Wagner — 2026-04-20 10:16

Acho que vou vazar a interface com reac shadcn


## 6. Claude — 2026-04-20 10:16

 Boa escolha! React + shadcn é muito mais flexível para isso.

### Stack recomendado

**React + shadcn/ui + React Flow + Recharts**

| Lib | Uso |
|---|---|
| **React Flow** | Grafo interativo de permissões |
| **Recharts** | Timeline do audit log |
| **shadcn/ui** | UI base (tabelas, filtros, cards) |
| **TanStack Query** | Fetch dos endpoints Laravel |

---

### Arquitetura

```
Laravel API (JSON)
    ├── GET /api/graph/nodes
    ├── GET /api/graph/edges
    ├── GET /api/audit-log
    └── GET /api/rag/queries

React App
    ├── /graph      → React Flow
    ├── /audit      → Timeline Recharts
    └── /rag        → Tabela de consultas do agente
```

---

### Dúvida rápida

Isso vai ser uma **SPA separada** consumindo a API Laravel, ou você vai embutir dentro do próprio projeto Laravel com **Inertia.js**?

Isso muda bastante o setup inicial — com Inertia você já tem auth e rotas compartilhadas, SPA separada dá mais liberdade mas precisa cuidar de CORS e autenticação.

