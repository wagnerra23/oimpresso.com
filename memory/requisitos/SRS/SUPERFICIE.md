---
name: "SUPERFÍCIE — SRS"
description: "Índice GERADO dos artefatos do módulo SRS reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: SRS
---

# 🗺️ Superfície de código — SRS

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs SRS --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/SRS/**` + `resources/js/Pages/SRS/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 56 arquivos em 10 papéis.

## Controllers — 8

- [ChatController.php](../../../Modules/SRS/Http/Controllers/ChatController.php)
- [DashboardController.php](../../../Modules/SRS/Http/Controllers/DashboardController.php)
- [DataController.php](../../../Modules/SRS/Http/Controllers/DataController.php)
- [InboxController.php](../../../Modules/SRS/Http/Controllers/InboxController.php)
- [IngestController.php](../../../Modules/SRS/Http/Controllers/IngestController.php)
- [InstallController.php](../../../Modules/SRS/Http/Controllers/InstallController.php)
- [MemoriaController.php](../../../Modules/SRS/Http/Controllers/MemoriaController.php)
- [ModuloController.php](../../../Modules/SRS/Http/Controllers/ModuloController.php)

## Requests (validação) — 4

- [ChatAskRequest.php](../../../Modules/SRS/Http/Requests/ChatAskRequest.php)
- [NewChatSessionRequest.php](../../../Modules/SRS/Http/Requests/NewChatSessionRequest.php)
- [StoreIngestRequest.php](../../../Modules/SRS/Http/Requests/StoreIngestRequest.php)
- [TriageEvidenceRequest.php](../../../Modules/SRS/Http/Requests/TriageEvidenceRequest.php)

## Services — 6

- [ChatAssistant.php](../../../Modules/SRS/Services/ChatAssistant.php)
- [DocRetentionCleaner.php](../../../Modules/SRS/Services/DocRetentionCleaner.php)
- [DocValidator.php](../../../Modules/SRS/Services/DocValidator.php)
- [MemoryReader.php](../../../Modules/SRS/Services/MemoryReader.php)
- [ModuleAuditor.php](../../../Modules/SRS/Services/ModuleAuditor.php)
- [RequirementsFileReader.php](../../../Modules/SRS/Services/RequirementsFileReader.php)

## Models / Entities — 7

- [DocChatMessage.php](../../../Modules/SRS/Entities/DocChatMessage.php)
- [DocEvidence.php](../../../Modules/SRS/Entities/DocEvidence.php)
- [DocLink.php](../../../Modules/SRS/Entities/DocLink.php)
- [DocPage.php](../../../Modules/SRS/Entities/DocPage.php)
- [DocRequirement.php](../../../Modules/SRS/Entities/DocRequirement.php)
- [DocSource.php](../../../Modules/SRS/Entities/DocSource.php)
- [DocValidationRun.php](../../../Modules/SRS/Entities/DocValidationRun.php)

## Console / Commands — 8

- [AuditModuleCommand.php](../../../Modules/SRS/Console/Commands/AuditModuleCommand.php)
- [GenTestCommand.php](../../../Modules/SRS/Console/Commands/GenTestCommand.php)
- [InstallHooksCommand.php](../../../Modules/SRS/Console/Commands/InstallHooksCommand.php)
- [MigrateModuleCommand.php](../../../Modules/SRS/Console/Commands/MigrateModuleCommand.php)
- [SrsHealthCommand.php](../../../Modules/SRS/Console/Commands/SrsHealthCommand.php)
- [SyncMemoriesCommand.php](../../../Modules/SRS/Console/Commands/SyncMemoriesCommand.php)
- [SyncPagesCommand.php](../../../Modules/SRS/Console/Commands/SyncPagesCommand.php)
- [ValidateCommand.php](../../../Modules/SRS/Console/Commands/ValidateCommand.php)

## Providers — 1

- [SrsServiceProvider.php](../../../Modules/SRS/Providers/SrsServiceProvider.php)

## Migrations (schema) — 8

- [2026_04_22_000001_create_docs_sources_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000001_create_docs_sources_table.php)
- [2026_04_22_000002_create_docs_evidences_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000002_create_docs_evidences_table.php)
- [2026_04_22_000003_create_docs_requirements_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000003_create_docs_requirements_table.php)
- [2026_04_22_000004_create_docs_links_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000004_create_docs_links_table.php)
- [2026_04_22_000005_create_docs_chat_messages_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000005_create_docs_chat_messages_table.php)
- [2026_04_22_000006_create_docs_pages_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000006_create_docs_pages_table.php)
- [2026_04_22_000007_create_docs_validation_runs_table.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000007_create_docs_validation_runs_table.php)
- [2026_04_22_000008_add_fulltext_index_to_docs_evidences.php](../../../Modules/SRS/Database/Migrations/2026_04_22_000008_add_fulltext_index_to_docs_evidences.php)

## Config — 2

- [config.php](../../../Modules/SRS/Config/config.php)
- [retention.php](../../../Modules/SRS/Config/retention.php)

## Testes (Pest) — 10

- 10 arquivos em [Modules/SRS/Tests/Feature/](../../../Modules/SRS/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 2

- [routes.php](../../../Modules/SRS/Http/routes.php)
- [start.php](../../../Modules/SRS/start.php)
