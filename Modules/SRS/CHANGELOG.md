# CHANGELOG — Modules/SRS

Append-only. Cada PR mergeado que toca `Modules/SRS/` deve adicionar 1 linha na entrada do Wave/data.

## Wave 25 — 2026-05-16 (SATURATION functional → ≥85)

### Added

- `Tests/Feature/Wave25CrossTenantSaturationTest.php` — 25+ cenarios cobrindo:
  - D1.A 7 Entities canon declaram business_id no fillable (DocSource/Requirement/Evidence/Page/Link/ChatMessage/ValidationRun)
  - D1.B schema column-level (4 tabelas docs_* têm coluna business_id verificada)
  - D1.C 8 cenarios DB cross-tenant (biz=1/99/100/101 — 4 tenants coexistem isolados; mass-update + delete + count scoped; reverso 99→1)
  - D9 confirmação spans canon (ChatAssistant.ask + DocValidator.validate via OtelHelper::spanBiz)
  - D9 SrsHealthCommand `--detail` (NÃO `--verbose` Symfony reserved)
  - D7 retention.php hierarquia LGPD (drafts<logs<=docs) + base legal cita Art. 16/ADR 0093/ADR 0094

### Changed

- `config/governance/module_clients.yaml` SRS promovido `backlog_hipotese` → `internal_governance_active` (Wagner uso diário /srs Chat + Validator + RetentionCleaner pra SPEC.md/CAPTERRA/BRIEFING — ADR 0159).

### Notes

- Sub-dimensoes alvo Wave 25: D1 (+19 = 25+ cenarios cross-tenant cobrindo 7 entities; legacy MultiTenantIsolationTest cobria só 2 entities × 5 cenarios), D9 (+3 = spans canon Wave 18 confirmados + OtelHelper class exists assert), D7 (+5 = retention.php base legal LGPD/ADRs preservado Wave 17/18 + hierarquia).
- SRS NÃO usa BusinessScope global (verificado MultiTenantIsolationTest legacy) — contrato é column-level: toda query DEVE incluir `where('business_id', ...)` explícito. Test PROTEGE contrato e expande cobertura sobre as 5 entities Wave 16+18 que legacy NÃO cobria (DocEvidence/DocPage/DocLink/DocChatMessage/DocValidationRun).
- bucket governance v4 declarado `functional_horizontal` em module.json.

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
