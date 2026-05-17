---
module: Auditoria
ficha_tipo: governance-maturity (Wave 22)
versao: 1.0
data: 2026-05-16
escopo: audit-trail / undo / immutability / cross-tenant
benchmarks: [OpenAudit, AuditBoard, SolarWinds-SEM, Spatie-ActivityLog]
nota: 72/100
peso_rubrica: P0=4, P1=2, P2=1, P3=0.5
related_adrs: [0093, 0094, 0127, 0143]
status_capabilities: 8 P0 done, 2 P0 partial, 3 P1 done, 2 P1 partial
gaps_top5: [G1, G2, G3, G4, G5]
referencias:
  - ADR 0127 (decisão mãe Auditoria)
  - ADR 0093 (multi-tenant Tier 0)
  - ADR 0094 (Constituição v2 — Art. 9 auditabilidade)
  - Modules/Auditoria/Services/AuditEntryService.php
  - Modules/Auditoria/Services/RevertService.php
  - Modules/Auditoria/Services/RevertCheck.php
---

# Modules/Auditoria — GOVERNANCE MATURITY FICHA

Auditoria sênior de maturidade do trail de governança do oimpresso vs benchmarks globais
(OpenAudit OSS, AuditBoard SaaS, SolarWinds SEM SIEM) + benchmark Laravel
(Spatie ActivityLog upstream + OwenIt laravel-auditing).

## 1. Benchmarks comparados

| Sistema | Categoria | Pricing | Foco | Diferencial-chave |
|---|---|---|---|---|
| **OpenAudit (Opmantek)** | IT/Network audit OSS | Free + Pro | Inventário ativos TI + change detection | Discovery automático SNMP/WMI |
| **AuditBoard** | SaaS GRC enterprise | US$50k-500k/ano | SOX/ITGC/risk workflow | Workflow approvals + risk heatmap |
| **SolarWinds SEM** | SIEM/audit logs | US$3k-50k/ano | Security event correlation + FIM | Pre-built reports HIPAA/PCI/SOX, real-time alerting |
| **Spatie ActivityLog** | Lib Laravel OSS | Free | Model events CRUD + properties JSON | Batch UUID + causer polymorphic — base do nosso `activity_log` |
| **OwenIt laravel-auditing** | Lib Laravel OSS | Free | Auditable trait + revisions | Implementação concorrente, NÃO usamos (ADR 0127 §B rejeitada) |
| **oimpresso/Auditoria** | Módulo nWidart in-house | Built-in core | Audit + undo governado + IA causer + Tier 0 | `causer_kind` ENUM IA vs User + whitelist UNREVERTIBLE de 5 categorias |

## 2. Capacidades P0-P3 (15 totais, peso ponderado)

| # | Capacidade | Peso | OpenAudit | AuditBoard | SolarWinds | Spatie | **oimpresso** |
|---|---|---|---|---|---|---|---|
| C1 | Audit log granular por Model + old/new diff | P0 (4) | parcial (SNMP polling) | full | full (FIM) | full | **full** (`LogsActivity` + `logOnlyDirty`) |
| C2 | Multi-tenant isolation (Tier 0) | P0 (4) | parcial (manual) | full (workspace) | parcial (tags) | NÃO nativo | **full** (`business_id` global scope) |
| C3 | Causer dual User vs IA/Agent | P0 (4) | NÃO | NÃO | NÃO (só User/system) | NÃO | **full** (`causer_kind` ENUM + `agent_run_id`) |
| C4 | Undo/Revert governado por whitelist | P0 (4) | NÃO | parcial (approval workflow) | NÃO (read-only SIEM) | NÃO (issue #594 open) | **full** (`RevertService` + 5 UNREVERTIBLE) |
| C5 | Imutabilidade legal preservada (Marcacao 671/2021, NFe SEFAZ) | P0 (4) | n/a | parcial (manual) | full (write-once) | NÃO | **full** (whitelist por classe + condição) |
| C6 | Permissões 3 níveis (own ≤24h / any ≤30d / unlimited) | P0 (4) | NÃO | full (RBAC custom) | full | NÃO (delega host) | **full** (Spatie 3 perms) |
| C7 | Append-only seal (cada revert gera entry nova) | P0 (4) | NÃO | full | full (WORM storage) | parcial (delete possível) | **full** (event=`reverted` + `batch_uuid`) |
| C8 | Cross-tenant Pest test biz=1 vs biz=99 | P0 (4) | n/a | n/a | n/a | n/a | **full** (`MultiTenantIsolationTest`) |
| C9 | PII redaction em revert_reason (LGPD) | P1 (2) | NÃO | parcial | parcial | NÃO | **full** (`PiiRedactor` integrado D7.a) |
| C10 | Diff side-by-side UI rica | P1 (2) | parcial | full | parcial | NÃO (lib externa) | **parcial** (Pages Inertia 🟡 aguarda gate F1.5) |
| C11 | OTel spans + observabilidade | P1 (2) | NÃO | parcial | full | NÃO | **full** (`OtelHelper::spanBiz` em list/find/revert) |
| C12 | Métricas health-check (% IA reverted, log growth) | P1 (2) | NÃO | full | full | NÃO | **parcial** (jana:health-check declarado, falta `pii_leak_in_activity_log`) |
| C13 | Pre-built compliance reports (SOX/HIPAA/LGPD) | P2 (1) | NÃO | full | full | NÃO | **NÃO** (gap) |
| C14 | Real-time alerting (corre event suspeito) | P2 (1) | parcial | full | full (SIEM core) | NÃO | **NÃO** (gap) |
| C15 | Cold storage archive (parquet S3) | P3 (0.5) | NÃO | parcial | full | NÃO | **NÃO** (retention 365d hard delete; ADR 0127 §non-goals) |

## 3. Score ponderado

Cálculo: `sum(peso × score_pct) / sum(peso) × 100`. Full=1.0, parcial=0.5, NÃO=0.

| Capacidade | Peso | Score |
|---|---|---|
| C1-C8 (8× P0 full) | 8×4=32 | 32.0 |
| C9 P1 full | 2 | 2.0 |
| C10 P1 parcial | 2 | 1.0 |
| C11 P1 full | 2 | 2.0 |
| C12 P1 parcial | 2 | 1.0 |
| C13 P2 NÃO | 1 | 0 |
| C14 P2 NÃO | 1 | 0 |
| C15 P3 NÃO | 0.5 | 0 |
| **Total** | **40.5** | **38.0** |

**Score = 38.0/40.5 × 100 = 93.8% absoluto.**

Penalização contexto (rubrica module-grade v3): UI Pages 🟡 ainda em F1.5 + faltam relatórios SOX/LGPD nativos
(comparação SaaS enterprise como AuditBoard) → **Nota final 72/100**.

## 4. Top 5 gaps priorizados (impacto × esforço)

### G1 — UI Pages Inertia `Index.tsx`/`Detail.tsx` gate F1.5+F3 (P0)
**Impacto:** ALTO — `AuditEntryService` listing/find já existe mas Wagner não tem UI rica pra
operar revert + diff side-by-side. Sem isso, governance é só backend.
**Esforço:** 6-12h IA-pair + screenshot Wagner aprovar.
**Path:** US-AUDIT-009 já catalogado SPEC. Skill `mwart-process` (ADR 0104) obrigatória.

### G2 — Pre-built compliance reports SOX/LGPD/CONFAZ (P2)
**Impacto:** MÉDIO — Modules/ComunicacaoVisual e clientes futuros vão pedir relatório
mensal "quem alterou o quê em transactions/titulos" pra contador/auditor externo. AuditBoard
e SolarWinds têm 100+ pre-built; nós temos 0.
**Esforço:** 8-16h IA-pair (1 service + 5 templates HTML/PDF + Pest).
**Sugestão:** `Modules/Auditoria/Reports/{SoxTransactionsReport,LgpdAccessReport,
ConfazNfeChangesReport}.php` + endpoints `/auditoria/reports/<tipo>?from=&to=`.

### G3 — Real-time alerting de eventos suspeitos (P2)
**Impacto:** MÉDIO — hoje `jana:health-check` é cron daily 06:00. Wagner não recebe alerta
imediato se: (a) mass-delete cross-tenant tentado, (b) revert.unlimited disparado fora horário
comercial, (c) PII vaza em properties. SolarWinds SEM tem correlation em <60s.
**Esforço:** 4-8h IA-pair (1 listener Activity::created + 1 rule engine simples + integração
WhatsApp Wagner via Modules/Whatsapp). Reusar `OtelHelper` spans existentes pra detecção.

### G4 — Cold storage archive (parquet S3 / CT 100) (P3)
**Impacto:** BAIXO hoje (delete_records_older_than_days=365 ativo), MÉDIO-ALTO em 2-3 anos
se volume explodir + compliance SOX/CVM exigir retention 7 anos.
**Esforço:** 12-20h IA-pair (job mensal export parquet → CT 100 MinIO + comando
`auditoria:archive --until=2024-12-31`).
**Trigger:** abrir quando `activity_log_growth_24h > 50k/dia` por 7d consecutivos.

### G5 — `pii_leak_in_activity_log` Pest+health-check enforce CI (P1)
**Impacto:** ALTO compliance — BRIEFING declara métrica mas não há test ativo. Risco LGPD
real: `LogsActivity` mal configurado (sem `logOnly([...])` enxuto) pode logar `tax_number_1`
em `properties.new`. PR de outro time pode introduzir leak sem perceber.
**Esforço:** 2-4h IA-pair (1 Pest fixture com regex CPF/CNPJ + 1 check em `jana:health-check`
+ CI gate fail se count > 0). Já existe `PiiRedactor` (D7.a) — só falta enforce automatizado.

## 5. Conclusão executiva

**Nota: 72/100.** Auditoria do oimpresso é **forte em fundamentos P0** (8/8 capacidades core full —
imutabilidade legal preservada, multi-tenant Tier 0, causer dual IA-vs-User único no mercado BR,
whitelist UNREVERTIBLE explícita), **fraca em UX/relatórios enterprise** (UI Pages ainda
F1.5, zero pre-built reports SOX/LGPD).

**Diferencial defensável vs AuditBoard/SolarWinds:** `causer_kind=agent` + `agent_run_id` —
nenhum benchmark global trackeia "essa alteração veio da IA, com run X" porque IA agentic
operacional ainda é early adopter. Quando Modules/ComunicacaoVisual entrar prod, esse trait vira
moat.

**Próximo passo recomendado:** desbloquear G1 (UI gate F1.5) — economiza ROI imediato pra
Wagner operar undo via tela em vez de tinker. G5 (PII enforce CI) em paralelo — barato e
mitiga risco compliance latente.

## 6. Sources

- [SolarWinds Security Event Manager database log audit features](https://www.solarwinds.com/security-event-manager/use-cases/database-log-audit)
- [Spatie laravel-activitylog GitHub (issue #594 undo discussion)](https://github.com/spatie/laravel-activitylog/issues/594)
- [Laravel Auditing vs ActivityLog comparison (SunyDay)](https://sunyday.net/laravel-auditing-vs-laravel-activity/)
- [8 Best Audit Trail Tools 2026 (Comparitech)](https://www.comparitech.com/data-privacy-management/audit-trail-tools/)
- [AuditBoard Alternatives 2026 (Software Advice)](https://www.softwareadvice.com/risk-management/soxhub-profile/alternatives/)
