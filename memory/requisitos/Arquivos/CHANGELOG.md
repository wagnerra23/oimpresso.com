# Changelog — Modules/Arquivos

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [0.4.0] - 2026-05-16 — Wave 18 RETRY SATURATION

### Added (D9 — Service novo com 4 spans OTel)

- `Services/ArquivosRetentionService` — políticas LGPD Art. 16 com 4 spans canônicos:
  - `arquivos.retention.scan` — lista elegíveis (read-only)
  - `arquivos.retention.expire_one` — soft-delete individual idempotente
  - `arquivos.retention.purge_one` — hard-delete + remove storage (irreversível)
  - `arquivos.retention.run` — orquestração batch (default `dry_run=true` defesa em profundidade)

### Added (D8 — FormRequests novas, ratio 2 → 5)

- `Http/Requests/DeleteArquivoRequest` — soft-delete UI, reason LGPD opcional.
- `Http/Requests/RestoreArquivoRequest` — restore com authorize gate `arquivos.restore`/`superadmin`.
- `Http/Requests/ListArquivosRequest` — filtros admin (bucket allow-list, mime regex, per_page cap 100).

### Added (D5 — README "como cliente usa")

- `memory/requisitos/Arquivos/README-COMO-CLIENTE-USA.md` — jornada por persona (operador comum, admin Wagner, auditor LGPD).

### Added (tests Wave 18 RETRY)

- `Tests/Feature/Wave18RetryArquivosSaturationTest` — cobertura D9 (spans declarados + run dry_run default) + D8 (3 FormRequests) + D5 (README existe + conteúdo).

### Changed

- `module.json`: adiciona `fsm_n_a:true` + razão (lifecycle SoftDeletes trivial, não justifica FSM canônica ADR 0143).

## [0.3.0] - 2026-05-16 — Wave 18 base

### Added

- `Services/VaultEncryptionService` gains spans `arquivos.vault.put_encrypted` + `arquivos.vault.get_decrypted`.
- `Services/ArquivosService::attach/classify/signedUrl/softDelete/restore` envelopados em `OtelHelper::spanBiz` (D9.a).
- `Tests/Feature/ArquivosOtelD9Test`.

## [0.2.0] - 2026-05-10 — Sprint 1 dia 4

### Added

- `Services/VaultEncryptionService` — AES-256-CBC envelope encryption (Crypt::encryptString) com cap 50MB.
- `Http/Requests/UploadArquivoRequest` + `DownloadArquivoRequest`.
- `arquivos:reencrypt-vault` command (rotação APP_KEY).

## [0.1.0] - 2026-05-05 — Bootstrap ADR 0123

- Entity `Arquivo` + Polimorfismo morph (`arquivable_type/arquivable_id`).
- Trait `HasArquivos` (opt-in pra módulos consumidores).
- `ArquivosService` API canônica (attach + classify + signedUrl + softDelete + restore).
- `CuradorEngine` pipeline 5-fase (DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY).
- 7 comandos artisan (audit-log, dedupe-stats, export-zip, health-check, recalcular-metadata, retention-cleanup).
