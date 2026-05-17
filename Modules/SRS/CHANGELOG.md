# CHANGELOG — Modules/SRS

Append-only. Cada PR mergeado que toca `Modules/SRS/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 18 RETRY — 2026-05-16 (governança meta-97)

- `module.json`: declarado `fsm_n_a: true` + razão — SRS é doc-ingest + chat assistido, sem ciclo transacional cross-stage.
- `CHANGELOG.md` (este arquivo) + `README.md` criados — preencher D3 docs internas.
- `Tests/Feature/RetentionPolicyTest.php` criado — valida `Config/retention.php` (D7 LGPD asserts declarativos).
- `Services/DocRetentionCleaner.php` extraído — service stub canônico pra futuro comando `srs:retention-cleanup` (Wave 18 RETRY: D4 boost + D7 LGPD).

## Wave 12 — 2026-05-15 (D1 + D7 saturation)

- `Entities/DocSource.php`, `DocPage.php`, `DocRequirement.php`, `DocEvidence.php`, `DocChatMessage.php`, `DocLink.php`, `DocValidationRun.php`: `HasBusinessScope` global scope + `LogsActivity` audit trail Spatie.
- `Config/retention.php`: janelas LGPD declarativas (generated_docs 1825d, drafts 90d, logs 365d, chat 365d).
- `Tests/Feature/MultiTenantIsolationTest.php`: column-level + Model-level cross-tenant biz=1/99.

## Wave 11 — 2026-05-15 (rename MemCofre → SRS)

- Rename `Modules/MemCofre/` → `Modules/SRS/` (Fase 3.7 PR-2). URL/permissions/config keys preservaram prefixo legacy `memcofre.*` por compat.

---

**Como atualizar este CHANGELOG:** entrada nova no TOPO da seção Wave ativa, NUNCA editar antigas. Skill `brief-update` Tier B lembra.
