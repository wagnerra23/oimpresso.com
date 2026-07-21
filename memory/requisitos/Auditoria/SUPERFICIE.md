---
name: "SUPERFÍCIE — Auditoria"
description: "Índice GERADO dos artefatos do módulo Auditoria reconhecidos pelo classificador, agrupados por papel. NÃO editar à mão."
type: reference
authority: generated
lifecycle: ativo
module: Auditoria
---

# 🗺️ Superfície de código — Auditoria

> ⚙️ **Gerado por máquina** (`scripts/governance/module-surface.mjs`). NÃO edite à mão — a próxima geração sobrescreve.
> Regenerar: `node scripts/governance/module-surface.mjs Auditoria --write`. Validar frescor: `--check` (exit 1 se a árvore mudou e isto não foi regenerado).
>
> **O que isto é:** os artefatos reconhecidos pelo classificador dentro de `Modules/Auditoria/**` + `resources/js/Pages/Auditoria/**`, separados por papel — inclusive telas e seus componentes sem confundir um com o outro. **O que NÃO é:** manifesto de todo byte da pasta, cobertura/nota/status por tela (donos: `screen-coverage-map.mjs` + `casos-gate`) nem âncoras cross-cutting (bridge em `app/`, FSM) — essas vivem narradas no [BRIEFING](BRIEFING.md), não aqui.

**Total mapeado:** 36 arquivos em 12 papéis.

## Controllers — 3

- [AuditoriaController.php](../../../Modules/Auditoria/Http/Controllers/AuditoriaController.php)
- [DataController.php](../../../Modules/Auditoria/Http/Controllers/DataController.php)
- [InstallController.php](../../../Modules/Auditoria/Http/Controllers/InstallController.php)

## Requests (validação) — 6

- [BulkRevertActivityRequest.php](../../../Modules/Auditoria/Http/Requests/BulkRevertActivityRequest.php)
- [ExportAuditEntriesRequest.php](../../../Modules/Auditoria/Http/Requests/ExportAuditEntriesRequest.php)
- [FilterAuditEntriesRequest.php](../../../Modules/Auditoria/Http/Requests/FilterAuditEntriesRequest.php)
- [RevertActivityRequest.php](../../../Modules/Auditoria/Http/Requests/RevertActivityRequest.php)
- [StoreAuditNoteRequest.php](../../../Modules/Auditoria/Http/Requests/StoreAuditNoteRequest.php)
- [UpdateAuditNoteRequest.php](../../../Modules/Auditoria/Http/Requests/UpdateAuditNoteRequest.php)

## Services — 3

- [AuditEntryService.php](../../../Modules/Auditoria/Services/AuditEntryService.php)
- [RevertCheck.php](../../../Modules/Auditoria/Services/RevertCheck.php)
- [RevertService.php](../../../Modules/Auditoria/Services/RevertService.php)

## Models / Entities — 1

- [AuditNote.php](../../../Modules/Auditoria/Entities/AuditNote.php)

## Console / Commands — 1

- [AuditoriaHealthCommand.php](../../../Modules/Auditoria/Console/Commands/AuditoriaHealthCommand.php)

## Providers — 2

- [AuditoriaServiceProvider.php](../../../Modules/Auditoria/Providers/AuditoriaServiceProvider.php)
- [RouteServiceProvider.php](../../../Modules/Auditoria/Providers/RouteServiceProvider.php)

## Rotas — 2

- [api.php](../../../Modules/Auditoria/Routes/api.php)
- [web.php](../../../Modules/Auditoria/Routes/web.php)

## Migrations (schema) — 1

- [2026_05_16_190000_create_auditoria_audit_notes_table.php](../../../Modules/Auditoria/Database/Migrations/2026_05_16_190000_create_auditoria_audit_notes_table.php)

## Config — 2

- [config.php](../../../Modules/Auditoria/Config/config.php)
- [retention.php](../../../Modules/Auditoria/Config/retention.php)

## Telas (Inertia/React) — 2

- [Detail.tsx](../../../resources/js/Pages/Auditoria/Detail.tsx)
- [Index.tsx](../../../resources/js/Pages/Auditoria/Index.tsx)

## Charters (lei da tela) — 2

- [Detail.charter.md](../../../resources/js/Pages/Auditoria/Detail.charter.md)
- [Index.charter.md](../../../resources/js/Pages/Auditoria/Index.charter.md)

## Testes (Pest) — 11

- 11 arquivos em [Modules/Auditoria/Tests/Feature/](../../../Modules/Auditoria/Tests/Feature) — cobertura é do `casos-gate`/`screen-coverage`, não deste índice.
