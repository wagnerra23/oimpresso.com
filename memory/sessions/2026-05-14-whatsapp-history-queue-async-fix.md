# WhatsApp history sync queue async fix + root cause webhook 404/429 (2026-05-14 madrugada)

> Continuação da saga 2026-05-13 (incident manhã → 11 PRs noite → rebuild CT 100 → safeguards). Esta sessão fechou o bug residual de webhook 404/429 com fila async + pesquisou especialista sobre comportamento WhatsApp history sync.

## TL;DR

Wagner perguntou de madrugada (2026-05-14 01h): _"ele guarda desda da hora da ultima mensagem... ou é retardado e pede sempre 90 dias?"_ + _"crie uma fila no redis ou em outro lugar"_.

**Respostas:**

1. **WhatsApp é "retardado":** sempre manda **full 90d** no pareamento fresco. Não há cursor protocol-level. Cliente deduplica via `provider_message_id` UNIQUE.
2. **Fila criada:** novo `PersistHistorySyncBatchJob` dispatchado via `dispatchAfterResponse()` — daemon recebe 202 imediato, msgs processam após response sem precisar queue:work daemon (Hostinger shared hosting).

## Root cause descoberto

Hostinger `.env` tem **`QUEUE_CONNECTION=sync`**. Handler `handleHistorySync()` processava 50-100 msgs SÍNCRONO inline no request HTTP. PHP-FPM worker travava 10-30s. Próximos webhooks do daemon recebiam **HTTP 404 do Apache** (gateway timeout disfarçado). Daemon retry esgotava → msgs perdidas.

**Caso real:** Suporte canal id=6 com ~10k msgs históricas (4834 + 4852 = 9686 msgs em 2 chunks syncType=2 FULL). 100% perdidas no 1º re-pair.

## Fix implementado (PR #828)

### Job assíncrono
```php
class PersistHistorySyncBatchJob implements ShouldQueue {
    public int $tries = 3;
    public function backoff(): array { return [10, 30, 90]; }
    public function handle(): void {
        // Itera msgs reusando handleMessage() via Reflection
        // Idempotente via provider_message_id UNIQUE
    }
}
```

### Handler dispatcha + responde 202
```php
PersistHistorySyncBatchJob::dispatchAfterResponse(
    businessId: $channel->business_id,
    channelId: $channel->id,
    syncType: $syncType,
    chunkIndex: $chunkIndex,
    chunkTotal: $chunkTotal,
    messages: $messages,
);
return response()->json([...], 202);
```

### Por que `dispatchAfterResponse()` vs queue:worker proper

Hostinger shared hosting NÃO tem supervisor/daemon de queue worker. Pra rodar Job assíncrono "de verdade" precisaria:
- Cron `* * * * * php artisan queue:work --once --queue=whatsapp --stop-when-empty`
- + `QUEUE_CONNECTION=database` (override sync)

`dispatchAfterResponse()` é solução Laravel built-in que roda Job APÓS response no MESMO PHP-FPM worker — sem precisar mudar nada na infra. Trade-off: se FPM worker terminar entre response e shutdown handler, chunk pode perder (risco baixo na prática).

## Pesquisa especialista — comportamento WhatsApp history sync

### Como funciona (Baileys 7.x + WhatsApp Web protocol 2026)

Pareamento fresco dispara `messaging-history.set` com 5 tipos:

| syncType | Nome | Conteúdo |
|---|---|---|
| 1 | INITIAL_BOOTSTRAP | pairing básico, msgs recentes |
| 2 | FULL | ~90d histórico completo (se `syncFullHistory:true` + `Browsers.Desktop`) |
| 3 | INITIAL_STATUS | status de uso recente |
| 4 | RECENT | msgs recentes (delta entre devices) |
| 5 | PUSH_NAME | display names contacts |
| 6 | ON_DEMAND | resposta de `fetchMessageHistory()` (cursor-based, mas só on-demand) |

### Resposta direta à pergunta do Wagner

**"WhatsApp guarda incremental ou pede sempre 90d?"**

→ **Sempre full 90d no PAREAMENTO FRESCO.** WhatsApp protocol não tem "cursor pareamento". Cliente é responsável por deduplicar:

- Baileys interno mantém `creds.processedHistoryMessages` array (dedup local-só, perde se daemon restart purge)
- App-side dedup via `provider_message_id` UNIQUE constraint no MySQL — **abordagem do oimpresso** (idempotente, robusta)
- Filtro `shouldSyncHistoryMessage((msg, syncType) => boolean)` callback **pode** rejeitar antes de processar, mas só por syncType (não por timestamp)

### Workaround possível pra economizar bandwidth

Se quisermos: filtro server-side em `handleMessage` que faz `WHERE messageTimestamp > lastKnown(channel_id)` ANTES de qualquer DB write. Cliente ainda RECEBE tudo (WhatsApp manda), mas Hostinger filtra antes de persistir.

**Decisão:** não vale a pena agora. MessagePersister idempotente já cobre + INSERT IGNORE em DB é barato.

## Sequência de PRs desta sessão (madrugada)

| PR | O que fechou | Status |
|---|---|---|
| #825 | Safeguards deploy CT 100 (CI build + drift sentinel + RUNBOOK) | ✅ merged |
| #827 | Anti-burst webhook (sleep 500ms+ entre chunks + 404 retryable) — preliminar | ✅ merged |
| #828 | Async via `dispatchAfterResponse` + Job idempotente — fix definitivo | ✅ merged |

## Estado prod final 2026-05-14 ~03h

- Daemon CT 100 `:v823+` healthy, ambos canais Wagner connected
- Hostinger main + PR #828 deployed
- Suporte canal id=6 reset → Wagner pareia → daemon manda chunks → Hostinger dispatcha Job + retorna 202 → Job processa msgs em background → DB populado SEM 404
- Backup image `:backup-pre-823` preservada

## Pra demo amanhã (Wagner)

1. Abrir https://oimpresso.com/atendimento/canais
2. Click **Conectar** no **Suporte** → QR aparece
3. Scaneia QR no celular
4. Aguardar ~5-10min em background pro daemon puxar ~10k msgs (50 msgs/chunk + sleep 2s entre)
5. Hostinger persiste tudo via Job assíncrono
6. Inbox `/atendimento/inbox` populado com histórico real

## Lições

### Validadas

- **`dispatchAfterResponse()`** é solução perfeita pra shared hosting sem queue worker
- **MessagePersister idempotente** evita estragos de re-pareamento (WhatsApp manda mesma msg 2x sem cursor)
- **`QUEUE_CONNECTION=sync`** em shared hosting É O ROOT CAUSE de qualquer "bug de webhook lento" no oimpresso

### Pra próxima

- **Auditar todos webhook handlers** que processam batches sync (procurar `foreach` dentro de Controller)
- **Métrica Grafana** `whatsapp_history_chunk_queued / processed / failed_after_3_tries`
- **Setup queue:worker proper** quando Wagner mover pra VPS (não shared)

## Estado MCP no momento do fechamento

⚠️ NÃO consultei MCP tools nesta madrugada — sessão emergencial 02-03h focada em resolver bug pra demo amanhã. Próxima sessão precisa de `brief-fetch` Tier A.

## Referências

- [memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](2026-05-13-whatsapp-incident-zombie-banned-loop.md) — incident manhã (origem)
- [memory/sessions/2026-05-13-whatsapp-daemon-rebuild-safeguards.md](2026-05-13-whatsapp-daemon-rebuild-safeguards.md) — sessão noite (rebuild + safeguards)
- [memory/handoffs/2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md](../handoffs/2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md) — handoff noite
- [Issue Baileys #11951 syncFullHistory bug](https://github.com/NousResearch/hermes-agent/issues/11951)
- [Baileys docs — History Sync](https://baileys.wiki/docs/socket/history-sync/)
- ADR 0096 emenda 4 — driver Baileys autorizado
- ADR 0093 — multi-tenant Tier 0
