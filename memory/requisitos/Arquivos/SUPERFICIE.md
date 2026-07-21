---
name: "SUPERFÍCIE — Arquivos"
description: "Índice GERADO dos artefatos do módulo Arquivos reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Arquivos
---

# 🗺️ Superfície de código — Arquivos

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Arquivos --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Arquivos/**` + `resources/js/Pages/Arquivos/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 58 arquivos em 11 papéis.

## Controllers — 3

- [DataController.php](../../../Modules/Arquivos/Http/Controllers/DataController.php)
- [DownloadController.php](../../../Modules/Arquivos/Http/Controllers/DownloadController.php)
- [InstallController.php](../../../Modules/Arquivos/Http/Controllers/InstallController.php)

## Requests (validação) — 7

- [DeleteArquivoRequest.php](../../../Modules/Arquivos/Http/Requests/DeleteArquivoRequest.php)
- [DownloadArquivoRequest.php](../../../Modules/Arquivos/Http/Requests/DownloadArquivoRequest.php)
- [ListArquivosRequest.php](../../../Modules/Arquivos/Http/Requests/ListArquivosRequest.php)
- [ReclassifyArquivoRequest.php](../../../Modules/Arquivos/Http/Requests/ReclassifyArquivoRequest.php)
- [RestoreArquivoRequest.php](../../../Modules/Arquivos/Http/Requests/RestoreArquivoRequest.php)
- [RetentionRunRequest.php](../../../Modules/Arquivos/Http/Requests/RetentionRunRequest.php)
- [UploadArquivoRequest.php](../../../Modules/Arquivos/Http/Requests/UploadArquivoRequest.php)

## Services — 4

- [ArquivosRetentionService.php](../../../Modules/Arquivos/Services/ArquivosRetentionService.php)
- [ArquivosService.php](../../../Modules/Arquivos/Services/ArquivosService.php)
- [CuradorEngine.php](../../../Modules/Arquivos/Services/Curador/CuradorEngine.php)
- [VaultEncryptionService.php](../../../Modules/Arquivos/Services/VaultEncryptionService.php)

## Models / Entities — 1

- [Arquivo.php](../../../Modules/Arquivos/Entities/Arquivo.php)

## Console / Commands — 7

- [AuditLogCommand.php](../../../Modules/Arquivos/Console/Commands/AuditLogCommand.php)
- [DedupeStatsCommand.php](../../../Modules/Arquivos/Console/Commands/DedupeStatsCommand.php)
- [ExportZipCommand.php](../../../Modules/Arquivos/Console/Commands/ExportZipCommand.php)
- [HealthCheckCommand.php](../../../Modules/Arquivos/Console/Commands/HealthCheckCommand.php)
- [RecalcularMetadataCommand.php](../../../Modules/Arquivos/Console/Commands/RecalcularMetadataCommand.php)
- [ReencryptVaultCommand.php](../../../Modules/Arquivos/Console/Commands/ReencryptVaultCommand.php)
- [RetentionCleanupCommand.php](../../../Modules/Arquivos/Console/Commands/RetentionCleanupCommand.php)

## Providers — 1

- [ArquivosServiceProvider.php](../../../Modules/Arquivos/Providers/ArquivosServiceProvider.php)

## Rotas — 1

- [web.php](../../../Modules/Arquivos/Routes/web.php)

## Migrations (schema) — 7

- [2026_05_10_000001_create_arquivos_table.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000001_create_arquivos_table.php)
- [2026_05_10_000002_create_arquivos_audit_log_table.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000002_create_arquivos_audit_log_table.php)
- [2026_05_10_000003_create_arquivos_dedupe_table.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000003_create_arquivos_dedupe_table.php)
- [2026_05_10_000010_backfill_nfe_xml_arquivos.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000010_backfill_nfe_xml_arquivos.php)
- [2026_05_10_000020_backfill_consumers_arquivos.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000020_backfill_consumers_arquivos.php)
- [2026_05_10_000030_add_metadata_recalculated_at_to_arquivos.php](../../../Modules/Arquivos/Database/Migrations/2026_05_10_000030_add_metadata_recalculated_at_to_arquivos.php)
- [2026_07_02_000001_widen_arquivos_audit_log_action_enum.php](../../../Modules/Arquivos/Database/Migrations/2026_07_02_000001_widen_arquivos_audit_log_action_enum.php)

## Config — 2

- [config.php](../../../Modules/Arquivos/Config/config.php)
- [retention.php](../../../Modules/Arquivos/Config/retention.php)

## Testes (Pest) — 24

- 24 arquivos em [Modules/Arquivos/Tests/Feature/](../../../Modules/Arquivos/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.

## Outros (raiz/misc) — 1

- [HasArquivos.php](../../../Modules/Arquivos/Concerns/HasArquivos.php)
