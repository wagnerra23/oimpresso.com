# PII Redaction — Modules/Crm

> Documento D7 LGPD — declara onde e como PII é tratada antes de log/telemetria.

## Implementação canônica

PII de leads/contatos/agendamentos CRM é redactada via `Modules\Jana\Services\Privacy\PiiRedactor` (serviço compartilhado canon entre módulos do oimpresso).

## Pontos de uso

| Cenário | Tratamento |
|---|---|
| Log de criação/edição de lead | `PiiRedactor::redact()` antes de log estruturado |
| Telemetria de funil de vendas | `PiiRedactor` antes de Langfuse/OTel |
| Export CSV/PDF de relatórios | RESPEITA permissão `crm.view_pii` (não-redact só pra usuários autorizados) |
| Audit trail (activity_log) | spatie/laravel-activitylog via trait `LogsActivity` em CrmContact + Schedule + Leaduser |

## Padrões redactados (heurística do PiiRedactor)

- CPF (`xxx.xxx.xxx-xx` / `xxxxxxxxxxx`)
- CNPJ (`xx.xxx.xxx/xxxx-xx` / `xxxxxxxxxxxxxx`)
- Telefone BR (`+55xxxxxxxxxxx` / `(xx) xxxxx-xxxx`)
- E-mail (`local@dominio.tld`)
- Cartão de crédito (luhn-checked)
- RG (heurística estado)
- Endereço completo (CEP + rua + número)

## D7 LGPD compliance

- ✅ **D7.a Pii Redaction** — `PiiRedactor` referenciado/aplicável em logs Crm
- ✅ **D7.b Audit Trail** — `LogsActivity` ativo em Models críticos (CrmContact, Schedule, Leaduser) escrevendo em `activity_log` tabela append-only (spatie)

## Trait LogsActivity nos Models

| Model | Tabela | Atributos logados |
|---|---|---|
| `Modules\Crm\Entities\CrmContact` | `contacts` (compartilhada) | fillable (logFillable + logOnlyDirty) |
| `Modules\Crm\Entities\Schedule` | `crm_schedules` | fillable (logFillable + logOnlyDirty) |
| `Modules\Crm\Entities\Leaduser` | `crm_lead_users` | fillable (logFillable + logOnlyDirty) |

Config: `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()`.

## Cross-ref

- ADR 0093 (multi-tenant Tier 0) — PII NUNCA cruza tenant
- skill `commit-discipline` (Tier A) — bloqueia PII real em commit
- `Modules/Jana/Services/Privacy/PiiRedactor.php` — implementação canônica
- `memory/requisitos/Jana/PII-REDACTION.md` — doc fonte

---
**Última atualização:** 2026-05-16 — Onda 3 mass D7 application (LogsActivity bumped em 3 Models)
