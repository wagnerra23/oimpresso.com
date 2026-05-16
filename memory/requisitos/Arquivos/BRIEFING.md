# BRIEFING — Modules/Arquivos

> **Estado consolidado** — 1 página executiva. Atualizado por PR mergeado (skill `brief-update` Tier B). Última revisão: 2026-05-16 (Wave M Capterra boost 71→80).

## Missão

Sistema canônico de arquivos do oimpresso — **vault encryption multi-tenant** + **pipeline curador 5-fase** (DISCOVER → CLASSIFY → REPORT → REVIEW → APPLY) + **deduplicação SHA-256** + **audit log append-only** + **retention LGPD**. Substitui storage ad-hoc espalhado em outros módulos (NfeBrasil XML, Consumidores docs, Repair anexos) por entidade única `Arquivo` referenciada via trait `HasArquivos`.

## Cliente piloto

**biz=1 Wagner** (uso interno) — vault encryption pra curadoria pessoal D:\Conhecimento (manuais Delphi WR Comercial, ADRs históricas, transcripts atendimento). Pré-piloto antes de expor pra ROTA LIVRE biz=4.

## Diferenciais vs mercado

| Capacidade | oimpresso | Bling/Tiny/Omie | Dropbox/GDrive |
|---|---|---|---|
| Vault encryption per-tenant (AES-256 + key rotation) | ✅ `VaultEncryptionService` + `arquivos:reencrypt` | ❌ | 🟡 (encryption-at-rest, sem rotation per-tenant) |
| Pipeline curador heurística-first | ✅ `CuradorEngine` 5-fase | ❌ | ❌ |
| Dedupe SHA-256 cross-módulo | ✅ tabela `arquivos_dedupe` + `arquivos:dedupe-stats` | ❌ | 🟡 (block-level, opaco) |
| Audit log append-only LGPD | ✅ `arquivos_audit_log` + `arquivos:audit-log` | ❌ | 🟡 (event log enterprise only) |
| Multi-tenant Tier 0 (`business_id` global scope) | ✅ Pest `MultiTenantTest.php` | n/a | ❌ |
| Retention LGPD configurável | ✅ `arquivos:retention-cleanup` | ❌ | 🟡 (manual) |
| Backfill legado idempotente | ✅ migrations `backfill_nfe_xml`, `backfill_consumers` | n/a | n/a |

## Arquitetura

- **Entity:** `Modules/Arquivos/Entities/Arquivo.php` — Eloquent com `business_id` global scope + soft delete + hash SHA-256 + metadata JSON
- **Services (3/3 ratio 1.0):**
  - `ArquivosService` — CRUD + upload + lifecycle
  - `VaultEncryptionService` — AES-256 envelope encryption + key rotation
  - `Curador/CuradorEngine` — pipeline 5-fase heurística-first + Claude-second pra ambíguos
- **Controllers:** `DataController` (sidebar JSON), `DownloadController` (stream decrypted), `InstallController` (3 rotas Install conforme RUNBOOK-criar-modulo.md)
- **Trait reuse:** `Concerns/HasArquivos` — outros módulos (NfeBrasil, Consumidores, Repair) anexam via `$model->arquivos()`
- **Migrations:** 6 migrations idempotentes (3 estruturais + 2 backfill legado + 1 evolutiva)
- **Comandos artisan:** 7 comandos (audit-log, dedupe-stats, export-zip, health-check, recalcular-metadata, reencrypt-vault, retention-cleanup)
- **Tests (16 Pest Wave A+B):** scaffold, multi-tenant, vault encryption, curador engine, curador parity, dedupe, backfill nfe-xml, backfill consumers, consumers trait, retention, reencrypt, export-zip, health-check, audit-log, recalcular-metadata (v1+v2)

## Frontend

**Backend-only no momento.** Zero `resources/js/Pages/Arquivos/*.tsx` — exposição via trait `HasArquivos` em UIs dos módulos consumidores (NfeBrasil/Consumidores/Repair). Charters consolidados em `CHARTERS-vault-curador.md` documentam contratos de uso. Quando Wagner decidir expor UI dedicada (admin vault browser), criar Pages Inertia seguindo skill `mwart-process` (ADR 0104).

## Métricas de saúde

- `php artisan arquivos:health-check` — vault key presente, audit log writable, dedupe table OK, retention policy configurada
- `php artisan arquivos:dedupe-stats` — % dedupe ratio (esperado >30% após backfill NfeBrasil XMLs)
- Pest `MultiTenantTest.php` — isolation biz=1 vs biz=99 (ADR 0093 Tier 0)

## Gaps conhecidos (backlog ADR feature-wish — ADR 0105)

- UI admin vault browser (depende sinal qualificado — ROTA LIVRE não pediu)
- Integração Curador com Jana IA (HyDE embeddings pra classificação semântica)
- OCR PDF scanned (Tesseract sidecar no CT 100)
- Versioning de arquivos (snapshot pre-overwrite)

## ADRs relacionadas

- [0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) Multi-tenant Tier 0
- [0124](../../decisions/0124-curador-pipeline-5-fase.md) Curador pipeline 5-fase (se existir; senão skill `curador`)
- [0131](../../decisions/0131-tiering-memoria-canonico-local-segredo.md) Tiering memória (LOCAL vs canon vs segredo)

## Próximos passos sinal-qualificado

1. Wagner usar curador 30d em D:\Conhecimento → métricas reais
2. Se Felipe/Maiara reportarem necessidade de UI vault → criar SPEC Pages Inertia
3. Se ROTA LIVRE precisar anexar docs em vendas → expor via trait em Sells

---

**Quem manteve:** Wave M agent 2026-05-16 (Capterra boost 71→meta 80). Próxima revisão: após próximo PR mergeado que toque `Modules/Arquivos/` ou `resources/js/Pages/Arquivos/`.
