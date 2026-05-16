# PII Redaction — Modules/Jana

> Documento D7 LGPD — declara onde e como PII é tratada antes de log/telemetria.

## Implementação canônica

PII de prompts/responses/telemetria Jana é redactada via:

- **Serviço:** `Modules\Jana\Services\Privacy\PiiRedactor`
- **Cobertura:** logs estruturados, traces OTel/Langfuse, snapshots de contexto

## Pontos de uso atuais

| Arquivo | Uso |
|---|---|
| `Modules/Jana/Http/Controllers/ChatController.php` | redact em mensagens antes de log |
| `Modules/Jana/Services/Telemetry/LangfuseClient.php` | redact em payloads telemetry |
| `Modules/Jana/Services/Ai/LaravelAiSdkDriver.php` | redact em prompt/response |

## Padrões redactados

CPF/CNPJ, telefone BR (com DDD), e-mail, cartão de crédito, RG (heurística), endereço completo.

## Tests

`Modules/Jana/Tests/Unit/PiiRedactorTest.php` — cobre os 6 padrões + edge cases.

## Cross-ref

- ADR 0093 (multi-tenant Tier 0) — PII NUNCA cruza tenant
- skill `commit-discipline` (Tier A) — bloqueia PII real em commit/PR

## Compliance LGPD D7

- ✅ D7.a Pii Redaction — `PiiRedactor` ativo
- ✅ D7.b Audit Trail — telemetria não persiste PII bruto

---
**Última atualização:** 2026-05-16 — Onda 3 mass D7 application
