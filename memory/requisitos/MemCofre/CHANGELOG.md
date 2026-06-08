# Changelog · MemCofre

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/lang/pt-BR/).

## [0.4.1] - 2026-04-24

### Changed

- **Docs do próprio MemCofre sincronizadas com o código atual.** README, SPEC, ARCHITECTURE, RUNBOOK e GLOSSARY estavam congelados em 2026-04-22 (versão 0.1) enquanto o módulo evoluiu pra 0.4.0 — sync manual corrigiu a divergência.
- README: frontmatter atualizado (`version: 0.4`, `last_generated: 2026-04-24`, `areas` expandido com `auditoria` e `memoria`). Tabela de rotas completa (14 endpoints, antes 4). Status atual expandido pras fases 1, 2, 2.5, 2.6, 2.7, 3 (em curso), 4 (futuro).
- SPEC: adicionadas 6 stories (US-006 memória unificada, US-007 migrate plano→pasta, US-008 anotação `@docvault`, US-009 auditoria, US-010 validação global, US-011 gen-test a partir de Gherkin) com DoD marcado conforme o que já está implementado.
- ARCHITECTURE: stack corrigida (Laravel 9.51 → **13.6**). Modelo de dados completo (7 tabelas — antes 4; incluiu `docs_chat_messages`, `docs_pages`, `docs_validation_runs`). Camadas expandidas (8 controllers, 7 commands, 5 services — antes 1). Decisões D5/D6/D7 adicionadas (memória tripartite, auditoria+validação separadas, fallback offline do chat). Fluxos F4-F9 novos (leitura de módulo, sync memories/pages, audit, validate, chat).
- RUNBOOK: branch corrigida (`6.7-react` → `6.7-bootstrap`). Problemas novos de auditoria/validação documentados. Comando de deploy na Hostinger adicionado.
- GLOSSARY: termos novos (`docs_validation_runs`, `docs_chat_messages`, `docs_pages`, `Health score`, `MemoryReader`, `ModuleAuditor`, `DocValidator`, `ChatAssistant`, `RequirementsFileReader`).

### Context

MemCofre era o piloto da estrutura pasta-por-módulo mas ironicamente tinha a própria documentação mais desatualizada que a do PontoWr2 ou Essentials. Sessão de 2026-04-24 foi dedicada a rodar `docvault:audit-module MemCofre` mental/manual e corrigir os findings — agora o módulo pratica o que prega.

## [0.4.0] - 2026-04-24

### Added

- **Seção "Diff de versão" na documentação de módulo** — padrão pra módulos restaurados após upgrade (ex.: Officeimpresso restaurado do 3.7 pro 6.7). Formato: tabela comparativa de controllers + endpoints + infra, com coluna "Mudança contrato" pra sinalizar impacto externo.
- ADR 0008 MemCofre (arq): padrão de documentação pra restaurações entre versões — comparar o que foi preservado, o que foi adaptado e o que é novo.
- Arquivo `reference_diff_3_7_vs_6_7_officeimpresso.md` em memória durável (fora do repo) — consulta rápida durante manutenção.

### Context

A restauração do módulo Officeimpresso da branch `origin/3.7-com-nfe` gerou 5 ADRs (0017–0021) e o `Modules/Officeimpresso/CHANGELOG.md` v1.0.0→v1.3.0. A demanda de navegar entre eles pra entender "o que é igual ao 3.7 e o que é novo" justificou consolidar num único doc de referência — o padrão "Diff de versão" nasceu aí.

## [0.3.0] - 2026-04-22

### Added

- `/docs/chat` — assistente conversacional que busca no conhecimento do MemCofre (README + ARCHITECTURE + SPEC + CHANGELOG + ADRs de todos os módulos).
- Modo offline: keyword-based com ranking por score, cita fonte (módulo + arquivo/ADR) em cada trecho.
- Modo AI (stub): desligado por padrão; ativado com `DOCVAULT_AI_ENABLED=true` e `OPENAI_API_KEY`.
- Tabela `docs_chat_messages` persistindo histórico por usuário + business + session_id.
- Sidebar com conversas recentes + seletor de escopo por módulo.
- Dashboard ganha coluna "Formato" (pasta/plano), coluna "Doc" com 5 dots de cobertura, coluna "ADRs".
- Card "Maturidade da documentação" com score médio global + contagem por formato.
- Comando artisan `docvault:migrate-module {Nome}` que divide automaticamente o .md plano em README/ARCHITECTURE/SPEC/CHANGELOG/adr + backup .bak.
- 2 módulos migrados como piloto: PontoWr2 (12 stories + 6 regras) e Essentials (1 story + 7 regras).

### Fixed

- Regex do parser de stories/regras agora captura todas as stories em sequência sob um único heading `##` (antes `[UR]-` quebrava com `US-`).

## [0.2.0] - 2026-04-22

### Added

- Estrutura de documentação por módulo: `memory/requisitos/{Modulo}/` com 4 arquivos + pasta `adr/` (ADRs numerados). MemCofre é o piloto.
- 4 ADRs iniciais documentando decisões do MemCofre: MySQL sobre Postgres, file-based specs, IA opt-in, estrutura pasta-por-módulo.
- Tab "Decisões" no viewer do módulo: lista ADRs com status colorido (accepted/proposed/deprecated) + preview.
- Bugfix no regex do parser: stories `US-XXX-NNN` em sequência agora são todas detectadas (antes caía no segundo match porque `[UR]-` não matchava `US-`).
- `RequirementsFileReader` agora tenta ler a pasta nova primeiro e faz fallback pro `.md` plano — migração gradual dos 29 módulos.
- Tabs separadas em `/docs/modulos/{Nome}`: Overview / Arquitetura / Spec / Changelog / Markdown.
- Campos `relation` (varchar 32) e `weight` (int) em `docs_links` preparados pra virar grafo de conhecimento real.
- Scout driver `database` configurado nos 3 modelos (`DocSource`, `DocEvidence`, `DocRequirement`) com fulltext MySQL — base pra busca semântica sem +1 serviço.
- `ClassifierAgent` stub (desativado via `DOCVAULT_AI_ENABLED=false`) — preenche `kind` e `module_target` automaticamente quando IA for ligada.

### Changed

- `ModuloController::show` agora passa 5 props ao invés de 3 (raw + arquivos de arquitetura e changelog).
- Viewer React renderiza markdown dos 3 arquivos extras quando presentes.

## [0.1.0] - 2026-04-22

### Added

- Scaffold inicial do módulo MemCofre (padrão UltimatePOS/nwidart).
- 4 migrations criando `docs_sources`, `docs_evidences`, `docs_requirements`, `docs_links`.
- 4 entidades Eloquent correspondentes (`DocSource`, `DocEvidence`, `DocRequirement`, `DocLink`).
- 6 controllers (`DashboardController`, `IngestController`, `InboxController`, `ModuloController`, `DataController`, `InstallController`).
- `RequirementsFileReader` parseia frontmatter YAML + extrai user stories + regras Gherkin dos .md de `memory/requisitos/`.
- 4 telas Inertia+React (`Dashboard`, `Ingest`, `Inbox`, `Modulo`) com shadcn/ui.
- Rotas sob `/docs` com middleware admin (web, auth, SetSessionData, AdminSidebarMenu).
- `/docs` adicionado em `LegacyMenuAdapter::isInertiaRoute`.
- `MemCofre: true` em `modules_statuses.json`.

### Notes

- 29 módulos do sistema documentados em `memory/requisitos/*.md` (formato plano) antes da estrutura de pastas.
- 19 user stories e 71 regras Gherkin consolidadas no total.
- Fase 1 validada em ambiente local (Herd + Laragon MySQL) via `oimpresso.test`.
