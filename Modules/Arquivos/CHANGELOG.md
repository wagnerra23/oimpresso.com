# CHANGELOG — Modules/Arquivos

> Append-only. Mais novo no topo. Datas YYYY-MM-DD.

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
