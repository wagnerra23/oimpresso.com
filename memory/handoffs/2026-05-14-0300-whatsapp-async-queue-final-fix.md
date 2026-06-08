# Handoff 2026-05-14 03:00 — Whatsapp history sync ASYNC queue final fix + pesquisa especialista

> Madrugada continuação da saga 2026-05-13. Total 14 PRs cumulativos (#813 → #828) cobrindo: incident → fixes → automação → safeguards → root cause webhook 404 → fila async.

## TL;DR (5 linhas)

- Bug residual webhook 404/429 reportado por Wagner: history sync NÃO persistia mesmo após sleep 2s entre chunks
- **Root cause descoberto**: Hostinger `QUEUE_CONNECTION=sync` → handler processava 50 msgs inline → PHP-FPM travava → 404 Apache gateway timeout
- Fix definitivo: `PersistHistorySyncBatchJob` via `dispatchAfterResponse()` Laravel → daemon recebe 202 imediato, msg processa background
- Pesquisa especialista Baileys: **WhatsApp manda full 90d sempre** (não há cursor protocol). Dedup app-side via `provider_message_id` UNIQUE
- PR #828 merged + deployed Hostinger. Suporte canal reset, aguardando Wagner re-parear pra validar

## PRs desta madrugada

| PR | Status | Risco | O que entrega |
|---|---|---|---|
| [#825](https://github.com/wagnerra23/oimpresso.com/pull/825) | ✅ merged | baixo | Safeguards deploy CT 100 (CI build + drift + RUNBOOK) |
| [#827](https://github.com/wagnerra23/oimpresso.com/pull/827) | ✅ merged | baixo | Anti-burst webhook preliminar (sleep + 404 retryable) |
| [#828](https://github.com/wagnerra23/oimpresso.com/pull/828) | ✅ merged | baixo | **Async via dispatchAfterResponse + Job idempotente — fix definitivo** |

## Estado MCP no momento do fechamento

⚠️ Não consultei MCP tools nesta madrugada — sessão emergencial. Próxima sessão precisa de `brief-fetch` Tier A.

## Decisões importantes salvas

### 1. WhatsApp protocol é "retardado" (full 90d sempre)

Pesquisa especialista confirmou: pareamento fresco dispara `messaging-history.set` com FULL ~90d. Não há cursor. App-side dedup obrigatório.

**Workaround possível** (NÃO implementado): filtro server-side `WHERE messageTimestamp > lastKnown(channel_id)` em handleMessage. Decidi não implementar — INSERT IGNORE no UNIQUE já é barato.

### 2. Hostinger `QUEUE_CONNECTION=sync` é root cause comum

Auditar futuros webhook handlers que processam batches dentro do Controller — sempre usar `dispatchAfterResponse()` OU async via Job se houver worker.

### 3. `dispatchAfterResponse()` perfeito pra shared hosting sem queue worker

Não precisa supervisor/cron. Roda Job APÓS HTTP response no mesmo PHP-FPM worker. Trade-off pequeno (FPM termination race).

## Estado prod final

- Daemon CT 100: ainda `:v823+` healthy
- Hostinger: deployed + clear cache feito
- Canal Suporte (id=6): status=setup (purgado pra Wagner re-parear novo teste)
- Canal Jana (id=5): status=active connected

## Pra Wagner

**Re-parear o Suporte (canal id=6)** pra validar o fix:
1. Abrir https://oimpresso.com/atendimento/canais
2. Click Conectar no card "Suporte" → QR aparece
3. Scaneia no celular
4. Aguardar ~5-10min em background — daemon manda 50 msgs/chunk + 2s sleep + Hostinger dispatcha Job + persiste msgs
5. Verificar Inbox `/atendimento/inbox` populado

**Validação técnica** (Wagner ou eu próximo turno):
```bash
# Monitor jobs processados:
ssh hostinger 'tail -f ~/domains/oimpresso.com/public_html/storage/logs/laravel.log | grep history-sync-job'

# Verificar conversations populated:
ssh hostinger 'cd ~/domains/oimpresso.com/public_html && php artisan tinker --execute="
  use Modules\Whatsapp\Entities\Conversation;
  echo Conversation::withoutGlobalScopes()->where(\"channel_id\", 6)->count() . \" conversations\";
"'
```

Se conversations > 0 = fix funcionou.

## Próximos passos pós-demo

1. Setup queue:worker proper (cron `* * * * * php artisan queue:work --once --queue=whatsapp`)
2. Métrica Grafana `whatsapp_history_chunk_queued/processed/failed_3_tries`
3. Auditar TODOS webhook handlers do projeto pra encontrar outros sync handlers que devem virar async
4. Filtro server-side timestamp em handleMessage (opcional — economia bandwidth)

## Referências cruzadas

- [memory/sessions/2026-05-14-whatsapp-history-queue-async-fix.md](../sessions/2026-05-14-whatsapp-history-queue-async-fix.md) — session log madrugada
- [memory/handoffs/2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md](2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md) — handoff noite anterior
- [Issue Baileys #11951](https://github.com/NousResearch/hermes-agent/issues/11951) — syncFullHistory bug
- ADR 0096 emenda 4, ADR 0093, ADR 0130
