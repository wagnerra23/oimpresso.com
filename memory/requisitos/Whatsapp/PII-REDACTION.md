# PII Redaction — Modules/Whatsapp

> Documento D7 LGPD — declara onde e como PII é tratada antes de log/telemetria.

## Implementação canônica

PII em mensagens WhatsApp / payloads webhook é redactada via `Modules\Jana\Services\Privacy\PiiRedactor` (serviço compartilhado entre Jana e Whatsapp — único canon).

## Pontos de uso atuais

| Arquivo | Uso |
|---|---|
| `Modules/Whatsapp/Listeners/DispatchToJanaBot.php` | redact texto da mensagem antes de log estruturado |
| `Modules/Whatsapp/Jobs/PersistHistorySyncBatchJob.php` | redact em batch history sync antes de persistir cache |

## Padrões redactados

Telefone (já é identificador do contact, scopado por `business_id`), CPF/CNPJ, e-mail, cartão de crédito, RG, endereço completo — quando aparecem no corpo da mensagem ou history sync.

## Telefone do contato (E.164)

Telefone normalizado E.164 (ex: `+5548999990000`) NÃO é considerado PII livre porque é a chave do contact dentro do tenant. Mas:

- NUNCA logar telefone em texto livre fora do contexto do contact owner
- Logs cross-tenant (`whatsapp_baileys_*_total` metrics) usam `business_id` apenas, sem telefone
- Audit trail em `whatsapp_message_logs` preserva telefone scoped (LGPD Art. 7º base legal: execução de contrato)

## Cross-ref

- ADR 0093 (multi-tenant Tier 0)
- ADR 0096 (módulo WhatsApp Meta Cloud API direto)
- skill `commit-discipline` (Tier A)
- runbook `memory/requisitos/Whatsapp/runbooks/baileys-troubleshoot-ban.md`

## Compliance LGPD D7

- ✅ D7.a Pii Redaction — `PiiRedactor` ativo nos 2 hot-paths
- 🟡 D7.b Audit Trail — `whatsapp_message_logs` existe; LogsActivity em Models pendente bump 2026-Q3

---
**Última atualização:** 2026-05-16 — Onda 3 mass D7 application
