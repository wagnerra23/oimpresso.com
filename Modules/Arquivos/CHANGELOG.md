# CHANGELOG — Modules/Arquivos

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

## [Wave 28 — Polish saturation 74-88 → ≥92 (+4pp)] — 2026-05-17

### Adicionado — D9 +1 span `arquivos.retention.summary` (5º span Retention canon)
- `ArquivosRetentionService::summary(int $businessId, int $retentionDays): array` — novo método público read-only retornando `{total, soft_deleted, expired_eligible, business_id}`. Útil pra HealthCheck dashboard (cron daily) e Wagner conferir saúde por tenant ANTES de aprovar `purge=true`. **Zero mutação** (preserva fail-secure dry_run W18) — apenas count buckets via `Arquivo::query()` respeitando `business_id` scope Tier 0.
- Span attributes sem PII: apenas `business_id` + `retention_days` + `module`.

### Adicionado — D2 +3 Pest Wave 28
- `Tests/Feature/Wave28ArquivosSaturationTest.php` (~7 cenários):
  - D9 W28 método novo + 5º span Retention (cumulativo 4 W18 + 1 W28)
  - D2 W28 businessId+retentionDays Tier 0 obrigatórios + shape canon `{total, soft_deleted, expired_eligible, business_id}` + zero mutação validado via source-grep block (regression guard)
  - OtelHelper fail-loud em spans `arquivos.retention.*` preservado
  - D3 W28 CHANGELOG entry (este)

### D3 W28 doc
- CHANGELOG (este entry).

### Preservado
- D7.c retention.php Wave 25 (8 entities mapeadas LGPD Art. 15-16 + grace_period_days 30)
- D9 ArquivosService spans baseline ≥6 (Wave 18 + 26 dedupe_lookup novo)
- D7.a PiiRedactor em audit log + redact_payload fail-open

### Referências
- ADR 0093 Multi-tenant Tier 0 IRREVOGÁVEL · ADR 0123 Modules/Arquivos backbone · ADR 0155 Module Grade v3 D9 saturated +1 · LGPD Art. 15-16

## [Wave 25 — Polish D7.c retention canônico] — 2026-05-16

### Adicionado — D7.c rubrica governance v3 (+1 arquivo)
- **`Config/retention.php`** novo — shim canônico LGPD que espelha `config.php['retention_days_policy']` (operacional) em formato D7.c-compliant pra auditoria governance v3:
  - 8 entities mapeadas: `nfe-xml` 1825d (Lei 8.846/94), `nfse-xml` 1825d, `documentos-fiscais` 1825d (CTN Art. 173), `contratos` 1825d (CDC Art. 27), `repair-foto` 730d, `os-anexo` 730d, `ticket-anexo` 365d, `default` 90d (LGPD Art. 15-16).
  - `strategy='hard_delete'` (alinha LGPD Art. 18 §VI direito eliminação).
  - `grace_period_days=30` (janela entre retention expirar e hard delete real — HealthCheck #4 alerta).
  - `bucket_override['sensitive']=365d` (mitiga exposição PII em bucket vault).

### Por que ter retention.php se já tem em config.php?
- `config.php` é OPERACIONAL (consumido pelo Service ao fazer upload — preenche `arquivos.retention_days` per-row).
- `retention.php` é AUDITORIAL/DOCUMENTAL (rubrica governance v3 D7.c — fonte da verdade pra compliance LGPD + facilita auditoria estado-arte).
- Mudança real DEVE atualizar ambos (acoplamento explícito documentado nos comments).

### Não alterado (intencional — já saturado)
- D9.a OtelHelper coverage já em 3 Services (ArquivosService + ArquivosRetentionService + VaultEncryptionService) desde W18.
- D9.c HealthCheckCommand já com 5 checks canônicos (orphan_files, dedupe_inconsistent, audit_log_lag, retention_overdue, vault_encryption_ratio) desde Sprint 2.
- D7.a PiiRedactor coverage já aplicada em export ZIP + audit log persist.

### Referências
- ADR 0093 Multi-tenant Tier 0
- ADR 0123 Modules/Arquivos backbone (Sprint 1+2)
- ADR 0155 Module Grade v3 (D7.c saturated 8/8 sub_destinations declaradas)
- ADR 0159 Wave 25 polish (level `biz_1_wagner_active` mantido)
- LGPD Art. 15-16 (eliminação tempestiva) + Art. 18 §VI (direito eliminação)
