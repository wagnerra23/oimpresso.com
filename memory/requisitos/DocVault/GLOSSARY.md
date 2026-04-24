# Glossário · DocVault

## ADR
Architecture Decision Record. Arquivo markdown numerado em `adr/NNNN-slug.md` que documenta uma decisão arquitetural — contexto, opções, escolha, consequências. Formato Michael Nygard estendido.

## Audit score
Pontuação 0-100 que mede a qualidade da documentação de um módulo. Calculado por `docvault:audit-module` com 15 checks (frontmatter, README, SPEC, regras-com-teste, ADRs mínimos, páginas anotadas, etc.). Persistido em `audits/YYYY-MM-DD.md`.

## Bloco @docvault
Comentário no topo de um arquivo `.tsx` declarando stories/rules/adrs/tests associados à tela. Lido por `docvault:sync-pages` e transformado em registro em `docs_pages`.

## Coverage score
Pontuação 0-100 baseada na presença dos 5 arquivos padrão do formato pasta (README, ARCHITECTURE, SPEC, CHANGELOG, adr/). 20 pontos por arquivo presente.

## docs_evidences
Tabela MySQL. Cada registro é um pedaço de informação (trecho, quote, screenshot annotation) aguardando triagem pra virar requisito. Tem `status ∈ {pending, triaged, applied, rejected, duplicate}`.

## docs_links
Tabela MySQL. Grafo de relações entre pares `(source_type, source_id) → (target_type, target_id)` com `relation` (derived_from, affects, duplicate_of) e `weight`.

## docs_pages
Tabela MySQL. Cada registro é uma tela React anotada com `@docvault`, com arrays JSON de stories/rules/adrs/tests associados.

## docs_requirements
Tabela MySQL. Cache de user stories e regras Gherkin extraídas dos `SPEC.md`. Regenerado por `php artisan module:requirements`.

## docs_sources
Tabela MySQL. Fonte bruta da evidência — arquivo uploaded, URL, texto livre, screenshot. Uma fonte gera N evidences.

## docs_validation_runs
Tabela MySQL. Histórico de execuções do `DocValidator`. Cada run guarda `health_score` (0-100), `issues` JSON, `only_module` (se limitado), `ran_at`, `duration_ms`. Base pra gráfico de tendência de saúde da documentação.

## docs_chat_messages
Tabela MySQL. Persiste histórico de chat por `(user_id, business_id, session_id)`. Role ∈ {user, assistant, system}. Suporta múltiplas sessões por usuário no sidebar do `/docs/chat`.

## docs_pages
Tabela MySQL. Cada registro é uma tela React anotada com `@docvault`, com arrays JSON de stories/rules/adrs/tests associados + `last_seen_at` pra detectar páginas removidas.

## Evidence
Pedaço de informação não-estruturada que *pode* virar requisito depois de curadoria. Vive em `docs_evidences`.

## Fase offline (ChatAssistant)
Modo default do chat — busca por keyword + scoring manual, sem API call. Substituído por modo AI quando `DOCVAULT_AI_ENABLED=true`.

## Formato pasta vs plano
- **Pasta**: `memory/requisitos/{Nome}/README.md + SPEC.md + ...` (novo, recomendado).
- **Plano**: `memory/requisitos/{Nome}.md` (legado, retrocompat mantida).

## Frontmatter
Bloco YAML no topo de um .md delimitado por `---`. Contém `status`, `risk`, `migration_priority`, `areas` etc. Parseado pelo `RequirementsFileReader`.

## Módulo virtual
Pasta com prefixo `_` (ex.: `_DesignSystem/`) que carrega documentação cross-cutting (não corresponde a módulo Laravel real). Tratada igual pelos readers, aparece no dashboard.

## Primer
Arquivos raiz do repo (`CLAUDE.md`, `AGENTS.md`) que servem de ponto de entrada pra agentes de IA. Vistos em `/docs/memoria`.

## Rastreabilidade tripla
ADR 0005. Loop Fluxo ↔ Tela ↔ Teste: cada user story tem página associada, cada regra tem teste implementado, cada decisão tem ADR rastreável.

## Trace score
Pontuação 0-100 do dashboard = média de (% stories com página + % regras com teste). Proxy de "docs-reality alignment".

## Health score
Pontuação 0-100 gravada em `docs_validation_runs.health_score`. Calculado pelo `DocValidator` como `100 - penalidades`, onde cada issue tem peso definido no ADR 0005. Diferente de **Audit score** (per-módulo) e **Coverage score** (arquivos presentes).

## MemoryReader
Service `Modules/DocVault/Services/MemoryReader.php`. Lê as 3 fontes de memória do projeto (primer/project/Claude), expõe árvore navegável e preview controlado de arquivo. Whitelist: `md`, `txt`, `json`, `yaml`, `yml`.

## ModuleAuditor
Service `Modules/DocVault/Services/ModuleAuditor.php`. Implementa os 15 checks C01-C15 do ADR 0007. Ponto de entrada: `docvault:audit-module {Nome} [--save]`.

## DocValidator
Service `Modules/DocVault/Services/DocValidator.php`. Implementa os 5 checks de integridade global (STORY_ORPHAN, RULE_NO_TEST, ADR_DANGLING, PAGE_NO_META, PAGE_STALE). Ponto de entrada: `docvault:validate [--module=X]`.

## ChatAssistant
Service `Modules/DocVault/Services/ChatAssistant.php`. Orquestra busca offline (keyword + scoring) e modo AI stub. Retorna `(answer, citations)`. Citações apontam `{módulo, arquivo, linha}` pra cada trecho usado.

## RequirementsFileReader
Service `Modules/DocVault/Services/RequirementsFileReader.php`. Parser central do formato de documentação. Detecta formato pasta vs plano, extrai frontmatter YAML, stories (US-XXX-NNN), regras (R-XXX-NNN), ADRs, auditorias.
