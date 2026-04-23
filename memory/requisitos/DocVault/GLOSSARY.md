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
