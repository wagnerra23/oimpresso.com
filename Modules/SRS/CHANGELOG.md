# CHANGELOG — Modules/SRS

Append-only. Cada PR mergeado que toca `Modules/SRS/` deve adicionar 1 linha na entrada do Wave/data.

<<<<<<< HEAD
## Wave 28 — 2026-05-17 (SATURATION FINAL functional → ≥92)

### Added

- `Tests/Feature/Wave28SaturationTest.php` — D2 cross-tenant defesa em camadas (3 casos source-level): 3 Entities tenant-scoped (DocSource/DocRequirement/DocEvidence) confirmam `HasBusinessScope` trait + DocChatMessage business_id no fillable + DocEvidence::shouldBeSearchable() filtra anti-vazamento Meilisearch + DocLink/DocPage justificativa repo-wide source-level.

### Notes

- Sub-dimensoes alvo Wave 28: D2 (+3 = cross-tenant defesa Model-level reforço — 3 entities tenant-scoped trait confirmation + 2 entities repo-wide justificadas source-level).
- Pattern alinhado com Wave 26 (`class_uses_recursive` + reflexão + zero hit MySQL) — Wave 25 já fez DB-level full coverage com 4 tenants quando MySQL disponível.
- Tier 0 ADR 0093 preservado — DocLink/DocPage/DocValidationRun são repo-wide intencional (justificado Wave 26 source-level).
=======
## Wave 27 — 2026-05-17 (POLISH final → ≥88)

### Added

- `Tests/Feature/Wave27PolishTest.php` — 20 cenarios cobrindo:
  - D1.A LogsActivity expand (DocRequirement + DocEvidence — paridade DocSource/DocChatMessage) — 8 cenarios incluindo preserve HasBusinessScope + Searchable + source-doc motivacao LGPD
  - D9.A Spans canon expand (ModuleAuditor.audit `srs.audit.module` + MemoryReader.listRoots `srs.memory.list_roots`) — 4 cenarios
  - D7.A LGPD push final retention.php Wave 27: 8 cenarios cobrindo `base_legal` Art. 7º II+IX + `notice_period_days` configuravel + `hierarquia` ordem crescente + `strategy` hard/soft + `entities` mapping + justificativa CLT Art. 11 + back-compat keys

### Changed

- `Entities/DocRequirement.php` — D1.A adicionado `LogsActivity` trait + `getActivitylogOptions()` (paridade DocSource Wave 12). Audit trail user stories — rastreabilidade governance ADR/CHANGELOG cross-check.
- `Entities/DocEvidence.php` — D1.A adicionado `LogsActivity` trait + `getActivitylogOptions()`. Audit triagem evidencias (ingerida → triaged → linked-to-requirement).
- `Services/ModuleAuditor.php` — D9.A `audit()` envolve `OtelHelper::spanBiz('srs.audit.module')` (latencia tipica 50-200ms). Metodo `auditInterno()` separado preserva contrato source-level.
- `Services/MemoryReader.php` — D9.A `listRoots()` envolve `OtelHelper::spanBiz('srs.memory.list_roots')` (I/O-heavy 100-800ms recursivo). Ajuda Wagner notar regressao quando memory/ cresce.
- `Config/retention.php` — D7.A push final Wave 27: 5 chaves novas (`base_legal` LGPD Art. 7º II+IX + `notice_period_days` env-configuravel + `hierarquia` ordem crescente declarativa + `strategy` hard/soft + `entities` mapping pra `srs:retention-cleanup` futuro). Back-compat keys legadas preservadas.

### Notes

- Sub-dimensoes alvo Wave 27: D1 (+3 = LogsActivity em 2 entities tenant-scoped restantes — DocRequirement/DocEvidence, paridade com DocSource/DocChatMessage Wave 12), D9 (+3 = spans canon em 2 services I/O-heavy adicionais — ModuleAuditor + MemoryReader, paridade ChatAssistant/DocValidator), D7 (+5 = retention.php audit-ready com base_legal + hierarquia + strategy + entities mapping — fiscal-ready Wagner pode demonstrar compliance LGPD per-rotina).
- Multi-tenant Tier 0 PRESERVADO: LogsActivity nao quebra HasBusinessScope (traits independentes); spans canon NAO vazam business_id em rota repo-wide.
- Append-only governance: retention.php `strategy=hard` por design — generated_docs governance preservado em git canonico (memory/requisitos/...) e fonte primaria; tabelas docs_* sao cache operacional.
- DocLink/DocPage/DocValidationRun mantem repo-wide intencional (EXCEÇÃO REPO-WIDE Wave 17 — isolamento transitivo via parents) — NAO ganham LogsActivity Wave 27 (catalogos governance sem mutacao frequente).
- bucket governance v4 mantido `functional_horizontal` em module.json.
>>>>>>> origin/main

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
