# Entity-Relationship · MemCofre

Diagrama ER das tabelas criadas pelo módulo MemCofre. Sintaxe Mermaid — renderizável no GitHub, VS Code e no viewer do MemCofre.

```mermaid
erDiagram
    DOCS_SOURCES ||--o{ DOCS_EVIDENCES : "origem de"
    DOCS_EVIDENCES ||--o{ DOCS_LINKS : "participa em (source)"
    DOCS_REQUIREMENTS ||--o{ DOCS_LINKS : "participa em (target)"
    DOCS_PAGES ||--o{ DOCS_LINKS : "participa em"
    DOCS_VALIDATION_RUNS }o--|| DOCS_REQUIREMENTS : "valida"
    DOCS_CHAT_MESSAGES }o--|| DOCS_SOURCES : "pode referenciar"

    DOCS_SOURCES {
        bigint id PK
        bigint business_id FK
        enum type "screenshot|chat|error|file|text|url"
        string title
        string storage_path
        string source_url
        bigint uploaded_by FK
        timestamps
    }

    DOCS_EVIDENCES {
        bigint id PK
        bigint source_id FK
        bigint business_id FK
        enum kind "bug|rule|flow|quote|screenshot|decision"
        enum status "pending|triaged|applied|rejected|duplicate"
        string module_target "64 chars"
        text content
        string suggested_story_id
        string suggested_rule_id
        boolean extracted_by_ai
        float ai_confidence "0-1"
        text notes
        bigint triaged_by FK
        timestamp triaged_at
        timestamps
    }

    DOCS_REQUIREMENTS {
        bigint id PK
        bigint business_id FK
        string module "64 chars"
        enum kind "story|rule"
        string story_id "US-XXX-NNN"
        string rule_id "R-XXX-NNN"
        string title
        text body
        json dod "checklist items"
        text gherkin
        boolean implemented
        string implementado_em
        boolean tested
        string testado_em
        timestamps
    }

    DOCS_LINKS {
        bigint id PK
        string source_type "evidence|requirement|page|adr"
        bigint source_id
        string target_type
        bigint target_id
        enum relation "derived_from|affects|leads_to|related_to|duplicate_of"
        int weight "default 1"
        timestamps
    }

    DOCS_PAGES {
        bigint id PK
        string path UK "/ponto/espelho"
        string component "Ponto/Espelho/Show.tsx"
        string module "64 chars"
        enum status "planejada|em-dev|implementada|deprecated"
        json stories "[US-PONT-007, US-PONT-008]"
        json rules "[R-PONT-001, R-PONT-002]"
        json adrs "[0001, 0003]"
        json tests "[PontoEspelhoTest]"
        string file_path "resources/js/Pages/..."
        timestamp last_synced_at
        timestamps
    }

    DOCS_VALIDATION_RUNS {
        bigint id PK
        timestamp run_at
        string module "null=global"
        int issues_total
        int issues_critical
        json issues "[{type,level,ref,message}]"
        tinyint health_score "0-100"
        timestamps
    }

    DOCS_CHAT_MESSAGES {
        bigint id PK
        bigint business_id FK
        bigint user_id FK
        string session_id "64 chars"
        enum role "user|assistant|system"
        text content
        string module_context "64 chars"
        json sources "refs a fontes/adrs citados"
        enum mode "offline|ai"
        int tokens_used
        timestamps
    }
```

## Notas

- Todas tabelas prefixadas `docs_` seguindo convenção UltimatePOS.
- `docs_links` é **polimórfico** (source_type/target_type + source_id/target_id), permitindo qualquer par de entidades do sistema — não só as tabelas `docs_*`.
- `docs_pages` é o ponto de ancoragem da **rastreabilidade tripla** (ADR 0005): amarra `.tsx` ↔ stories ↔ regras ↔ ADRs ↔ tests.
- `docs_requirements` serve como **cache** dos arquivos `.md` em `memory/requisitos/`. Fonte da verdade é o arquivo (ADR 0002).
- `docs_chat_messages` não tem FK forte pra sources porque o chat pode citar memória Claude também (fora do DB).

## Atualização desse diagrama

Toda vez que uma migration adicionar/remover coluna significativa, atualize este arquivo. `docvault:audit-module` em versão futura pode comparar DDL vs diagrama.
