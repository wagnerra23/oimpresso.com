# PII Redaction — Modules/Cms

> Documento D7 LGPD — declara onde e como PII é tratada em Cms (site público + admin).

## Implementação canônica

PII de leads/contatos/conteúdo público Cms é redactada via `Modules\Jana\Services\Privacy\PiiRedactor` (serviço compartilhado canon entre módulos).

## Pontos de uso

| Cenário | Tratamento | Arquivo |
|---|---|---|
| Log de captura de lead (`postContactForm`) | `PiiRedactor::redactArray()` antes de `Log::info` | `Modules/Cms/Http/Controllers/CmsController.php` |
| Exception em postContactForm | `PiiRedactor::redact()` em `Log::emergency` | idem |
| Exception em SettingsController (notifiable_email + mail_us) | `PiiRedactor::redact()` em `Log::emergency` | `Modules/Cms/Http/Controllers/SettingsController.php` |
| Exception em CmsPageController (conteúdo texto livre) | `PiiRedactor::redact()` em `Log::emergency` | `Modules/Cms/Http/Controllers/CmsPageController.php` |
| Audit trail (activity_log) | spatie/laravel-activitylog via trait `LogsActivity` em 3 Models | ver tabela abaixo |
| Notificação mail admin (NewLeadGeneratedNotification) | helper `leadForLog()` redacta PII pra log de queue | `Modules/Cms/Notifications/NewLeadGeneratedNotification.php` |

## Padrões redactados (heurística do PiiRedactor)

- CPF (`xxx.xxx.xxx-xx` / `xxxxxxxxxxx`)
- CNPJ (`xx.xxx.xxx/xxxx-xx` / `xxxxxxxxxxxxxx`)
- Telefone BR (`+55xxxxxxxxxxx` / `(xx) xxxxx-xxxx`)
- E-mail (`local@dominio.tld`)
- CEP (`xxxxx-xxx`)

## D7 LGPD compliance

- ✅ **D7.a Pii Redaction** — `PiiRedactor` aplicado em 4 pontos de log Cms (CmsController + SettingsController + CmsPageController + NewLeadGeneratedNotification)
- ✅ **D7.b Audit Trail** — `LogsActivity` ativo em 3 Models críticos (CmsPage + CmsSiteDetail + CmsPageMeta) escrevendo em `activity_log` (spatie append-only)
- ✅ **D7.c Retention** — `Modules/Cms/Config/retention.php` declara janelas (leads 730d, contatos 1095d, blog comments 1825d, activity log 2555d)

## Trait LogsActivity nos Models

| Model | Tabela | Atributos logados |
|---|---|---|
| `Modules\Cms\Entities\CmsPage` | `cms_pages` | fillable (logFillable + logOnlyDirty) |
| `Modules\Cms\Entities\CmsSiteDetail` | `cms_site_details` | fillable (logFillable + logOnlyDirty) |
| `Modules\Cms\Entities\CmsPageMeta` | `cms_page_metas` | fillable (logFillable + logOnlyDirty) |

Config canônica: `LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs()`.

## Retention canônica

Arquivo: `Modules/Cms/Config/retention.php` — chaves:

- `leads_days` = 730 (~24 meses, prospecção B2B típica)
- `contacts_days` = 1095 (~36 meses, futura tabela `cms_contact_messages`)
- `blog_comments_days` = 1825 (~5 anos, Marco Civil)
- `activity_log_days` = 2555 (~7 anos, prescricional civil BR Art. 205)

Override via `.env` (`CMS_RETENTION_LEADS_DAYS` etc).

## Cross-ref

- [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0) — PII NUNCA cruza tenant; purge sempre scopado por `business_id`
- [ADR 0094](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4 — custo IA tracking (purge não toca LLM)
- skill `commit-discipline` (Tier A) — bloqueia PII real em commit
- `Modules/Jana/Services/Privacy/PiiRedactor.php` — implementação canônica
- `memory/requisitos/Crm/PII-REDACTION.md` — pattern espelho (Onda 9)

---
**Última atualização:** 2026-05-16 — Wave 11 D7 push Cms 55→62 (PiiRedactor + LogsActivity 3 Models + retention.php)
