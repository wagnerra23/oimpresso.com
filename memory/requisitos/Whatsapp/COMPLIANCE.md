# COMPLIANCE — Modules/Whatsapp

> D7 LGPD chat — retention + audit trail + opt-in/opt-out + PII redaction.
> **Status:** PROPOSTA Onda 3 — aguarda Wagner aprovar política retention.
> Complementa [PII-REDACTION.md](PII-REDACTION.md) (já canônico).

## 1. Base legal (LGPD Art. 7º)

Tratamento de mensagens WhatsApp/Instagram/Messenger pelo oimpresso usa 3 bases:

| Operação | Base legal | Artigo |
|---|---|---|
| Receber mensagem cliente (inbound) | **Execução de contrato** | Art. 7º V |
| Enviar mensagem operacional (NFe, boleto, OS) | **Execução de contrato** | Art. 7º V |
| Enviar marketing/broadcast | **Consentimento** opt-in | Art. 7º I + Art. 8º |
| Análise/treino Jana IA (`payload`) | **Legítimo interesse** com balancing test | Art. 7º IX |
| Retenção pós-resolução | **Cumprimento obrigação legal** (NFe + suporte) | Art. 7º II |

## 2. Retention policy (PROPOSTA — Wagner aprova)

**Mensagens (`messages.body`, `messages.payload`, `messages.media_url`):**

| Estado conversa | Retenção integral | Após período | Ação |
|---|---|---|---|
| `open` / `awaiting_human` | indeterminado | — | mantém vivo |
| `resolved` / `archived` | **180 dias** | T+180d | PII-REDACT body + payload (preserva metadata: timestamp/direction/status/cost — auditoria) |
| `resolved` + cliente solicitou exclusão LGPD Art. 18º VI | imediato | T+0 | hard-redact body+payload+media_url; row preservada (chave estrangeira NFe/OS) |
| Mídia (`media_url`) | **365 dias** | T+365d | delete arquivo storage; mantém `media_filename`+`media_mime` (auditoria) |

**Conversations:**
- nunca delete row (FK histórica NFe/OS/Sells)
- `contact_name`, `customer_external_id`, `phone_e164`, `lid`, `bsuid` permanecem **enquanto contato ativo**; após 24 meses sem interação E sem vínculo Transaction/JobSheet → anonimizar (`phone_e164 = null`, `contact_name = 'Cliente anônimo #' + id`)

**Activity log (`activity_log` Spatie):**
- mudanças de Channel + Conversation registradas via `LogsActivity` (Onda 3)
- **NÃO** loga `body`, `payload`, `contact_name`, `customer_external_id` (PII)
- retention activity_log = **5 anos** (alinha com NFe SEFAZ Art. 23 Lei 8.846/94)

## 3. Cron jobs (planejado — pré-req Wagner aprovar §2)

```php
// app/Console/Kernel.php
$schedule->command('whatsapp:redact-old-messages --days=180')
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();

$schedule->command('whatsapp:purge-old-media --days=365')
    ->dailyAt('03:30')
    ->onOneServer();

$schedule->command('whatsapp:anonymize-inactive-contacts --months=24')
    ->monthlyOn(1, '04:00');
```

Comandos NÃO existem ainda — gerar em Sprint pós-aprovação Wagner.

## 4. Opt-in/opt-out (já implementado parcial)

Wagner ADR + skill `commit-discipline` exigem:

- ✅ `Contact::canReceiveWhatsappNotification()` — NULL=permite (back-compat), FALSE=bloqueia + log [proibicoes.md §FSM]
- ✅ `Contact::canReceiveEmailNotification()` — idem
- 🟡 UI cliente self-service revogação consentimento — **não existe** (gap)
- 🟡 Export dados pessoais Art. 18º II/V — **não existe** (gap)

## 5. Audit trail (Onda 3 — implementado)

`Channel` + `Conversation` agora têm `LogsActivity` trait:

- **Channel:** loga label, status, handles_*, bot_enabled, channel_health, lgpd_acknowledged_at. **NÃO** loga `config_json` (encrypted, tokens).
- **Conversation:** loga status, assigned_user_id, bot_handling, is_blocked, unread_count. **NÃO** loga `contact_name`, `last_message_preview`, `customer_external_id`.
- **Message:** **NÃO** tem LogsActivity (append-only por contrato ADR 0135 §"Message imutável"; `status` flow já gera 1 row por mensagem).

## 6. PII redaction em logs

Ver [PII-REDACTION.md](PII-REDACTION.md). Resumo:

- `Modules\Jana\Services\Privacy\PiiRedactor` aplicado em logs estruturados Loki
- E.164 phone, CPF/CNPJ, email, CEP reduzidos a placeholder `[REDACTED:TIPO]` em `storage/logs/*.log`
- **Wired-up (Wave 9 — 2026-05-16):** `DispatchToJanaBot.php:131` — `inbound_preview` redacted antes de `Log::info`. Único log-call do módulo que copia body raw do cliente; demais Centrifugo previews (`PublishMessageReceivedToCentrifugo`, `PublishOmnichannelToCentrifugo`) são tenant-scoped real-time channels (não vão pra `storage/logs/*.log`)
- Pest: [DispatchToJanaBotPiiRedactionTest.php](../../../Modules/Whatsapp/Tests/Feature/DispatchToJanaBotPiiRedactionTest.php) — 7 it cobrindo CPF/CNPJ/email/CEP + non-PII preservado + multi-tenant biz=1 vs biz=99 isolation + config retention exposed
- Mídia URL truncada a `https://.../[REDACTED]`

## 7. Roadmap LGPD residual (post-Onda 3)

| Gap | Prioridade | Owner |
|---|---|---|
| UI self-service revogação consentimento (Contact) | P1 | Wagner |
| Export dados pessoais Art. 18º (download JSON) | P1 | Wagner |
| 3 artisan commands retention (§3) | P2 | post-aprovação |
| DPIA (Avaliação Impacto) formal | P3 | Eliana[E] (quando decidir DPO) |

---

**Última atualização:** 2026-05-16 (Onda 3 refinement) · **Status:** PROPOSTA — Wagner aprova §2 + §3 antes de implementar cron jobs.
