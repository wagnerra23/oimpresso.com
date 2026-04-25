# ADR ARQ-0002 (LaravelAI) · Estende MemCofre, não duplica

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq

## Contexto

MemCofre já tem (em produção 2026-04):
- `docs_evidences` — evidências (chat logs, screenshots)
- `docs_chat_messages` — chat assistente offline (keyword + AI stub)
- `MemoryReader` — lê primer + project + Claude memories
- ADRs estruturados em `memory/requisitos/<modulo>/adr/`
- DocValidator + ModuleAuditor — auditoria do triângulo fluxo/tela/teste

LaravelAI quer:
- Knowledge Graph (`kg_entities`, `kg_relations`)
- RAG sobre ADRs/políticas
- Agente conversacional com 3 fontes (Graph + RAG + Audit)
- Visualização React Flow

Sobreposição grande. Pergunta: **construir LaravelAI separado ou estender MemCofre?**

## Decisão

**LaravelAI estende MemCofre — não duplica funcionalidade existente.**

Estrutura:

```
Modules/MemCofre/                    ← já existe; mantém estrutura
├── (tabelas docs_*)
├── ChatAssistant.php                ← mantém (fallback offline)
├── MemoryReader.php                 ← mantém
└── ...

Modules/LaravelAI/                   ← novo
├── Models/KgEntity.php              ← novo schema kg_*
├── Models/KgRelation.php
├── Services/AgentService.php        ← USA MemCofre::ChatAssistant + adiciona Graph + RAG
├── Services/GraphService.php
├── Services/VectorStoreService.php  ← popula kg_entities.embedding usando ADRs do MemCofre
└── Pages/Chat/Contextual.tsx
```

Reuso concreto:
- **MemCofre::MemoryReader** → fonte de ADRs/primer/project (LaravelAI consome)
- **MemCofre::ChatAssistant** → fallback offline quando OpenAI down (LaravelAI fallback)
- **MemCofre::DocValidator** → auditoria docs (LaravelAI cita ADRs validados)
- **Tabelas docs_evidences / docs_chat_messages** → input pro vetor store
- **Frontend tabs** existentes em `/memcofre/...` ganham aba "IA & Knowledge" linkando pra `/laravel-ai/`

## Consequências

**Positivas:**
- Zero duplicação — embeddings dos ADRs são gerados uma vez (em MemCofre) e usados em ambos
- LaravelAI evolui rápido (não reinventa MemoryReader, ChatAssistant)
- MemCofre mantém escopo focado (documentação viva); LaravelAI adiciona "conversação semântica"
- Tenant que tem MemCofre + LaravelAI vê coerência (mesma fonte de verdade)
- Manutenção: bug em `ADR-X` corrige em 1 lugar (MemCofre); LaravelAI re-embeddings via observer

**Negativas:**
- Acoplamento: LaravelAI depende de MemCofre estar habilitado (não é módulo standalone)
- Cross-module dependency: LaravelAI service consume MemCofre service direto (não só via evento)
- Refactor pra extrair LaravelAI futuramente (se decidirmos) precisa quebrar dependência

## Pattern de extensão

```php
// Em Modules/LaravelAI/Services/AgentService.php
class AgentService {
    public function __construct(
        private \Modules\MemCofre\Services\MemoryReader $memoryReader,
        private \Modules\MemCofre\Services\ChatAssistant $fallbackChat,
        private GraphService $graph,
        private VectorStoreService $vectorStore,
        private LLMProvider $llm,
    ) {}

    public function ask(string $question, int $businessId): AgentResponse {
        // 1. Tenta fontes ricas (Graph + RAG)
        $graphResults = $this->graph->query($question, $businessId);
        $ragResults = $this->vectorStore->search($question, $businessId);

        // 2. Se LLM falha, fallback pro chat keyword-based do MemCofre
        try {
            return $this->llm->ask($question, [$graphResults, $ragResults]);
        } catch (LLMUnavailable $e) {
            return $this->fallbackChat->respondToQuestion($question);  // MemCofre offline
        }
    }
}
```

## Cross-link MemCofre ↔ LaravelAI

- Em `/memcofre/modulos/<X>` adicionar tab "Pergunte à IA" que abre LaravelAI chat preset com contexto desse módulo
- Em `/laravel-ai/chat` resposta com citation `adr:0007` linka pra `/memcofre/modulos/<X>/adr/0007`
- Em `/laravel-ai/graph` node tipo 'adr' linka pra MemCofre

## Quando reavaliar (extrair LaravelAI)

Se virar verdade:
- LaravelAI ganha consumidores fora de oimpresso (vende como SaaS standalone)
- MemCofre vira obsoleto (substituído por LaravelAI completo)
- Equipe ganha capacidade pra manter 2 módulos sem dependência

Hoje: extender é certo.

## Alternativas consideradas

- **LaravelAI 100% separado** — rejeitado: duplica RAG, MemoryReader, ChatAssistant; manutenção dobrada
- **Substituir MemCofre por LaravelAI** — rejeitado: MemCofre tem features (DocValidator, audits) fora do escopo de LaravelAI
- **LaravelAI como sub-módulo de MemCofre** — rejeitado: escopo diferente justifica módulo separado, só compartilha recursos

## Referências

- `MemCofre/SPEC.md`
- `MemCofre/adr/0007-estrutura-expandida-...md`
- ARQ-0001 (storage do grafo)
- `_Ideias/LaravelAI/evidencias/conversa-claude-2026-04-mobile.md`
