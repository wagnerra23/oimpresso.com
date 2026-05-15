# 2026-05-15 — Agent B (Wave 3) — Cron horário retry mídia inbound recente

> **Worktree:** `flamboyant-chaum-16429f`
> **Owner:** Claude (Agent B paralelo, wave 3 de 3)
> **Trigger origem:** Wagner reportou "mídias não estão mostrando" no Inbox `/atendimento/inbox` prod biz=1 (ROTA LIVRE) após re-pareamento Baileys 7.x CT 100 às 09:25 BRT. 55 msgs history sync ficaram com `media_url=NULL`.

## Decisão — Opção B (comando novo) escolhida

Prompt sugeriu Opção A (registrar `BackfillMediaDownloadCommand` no Kernel.php com `--since=24h`). **Não cabe** — comando existente faz `Carbon::parse($this->option('since'))->startOfDay()` que SÓ aceita `YYYY-MM-DD` rígido. Tentar `--since=24h` → `Carbon::parse('24h')` falha silenciosamente ou retorna data inesperada.

Opção B implementada: novo comando `whatsapp:retry-recent-media-downloads` com 3 flags simples (`--hours=N`, `--limit=N`, `--dry-run`), cross-business sempre, mais permissivo de propósito.

### Diferenças vs Camada 4 (`RetryFailedMediaDownloadsJob` — já hourly)

| Aspecto | Camada 4 | Agent B (este) |
|---|---|---|
| Lookback | 7 dias | 24h |
| `media_download_status` filtro | `IN (pending, downloading)` | qualquer EXCETO `failed_permanent` (inclui NULL!) |
| `media_download_attempts` filtro | `< MAX_ATTEMPTS` | sem filtro (idempotência do Job basta) |
| Idempotente | Sim (Job early-return em success) | Sim (mesma proteção) |
| Cobre status anômalo NULL | ❌ não | ✅ sim |
| Schedule offset | `hourly()` (minuto 00) | `hourlyAt(15)` (não disputa daemon) |

**Por que cobrir status anômalo NULL importa:** `PersistHistorySyncBatchJob` (worker `whatsapp-history`, every minute) pode bulk-insert msgs sem disparar `MessageObserver::created` (observers podem ser pulados em batch insert). Resultado: `media_download_status` fica em valor default da coluna ou `NULL`. Camada 4 com `whereIn(...)` exclui isso; Agent B pega.

## Arquivos modificados / criados

- **NOVO:** `Modules/Whatsapp/Console/Commands/RetryRecentMediaDownloadsCommand.php` — comando (~120 linhas com docblock completo)
- **EDIT:** `app/Console/Kernel.php` — adicionado entry `hourlyAt(15)` logo após Camada 4 (15 linhas)
- **NOVO:** `Modules/Whatsapp/Tests/Feature/RetryRecentMediaDownloadsTest.php` — 7 testes Pest cobrindo happy path, cross-tenant biz=1 vs biz=99 (ADR 0101 — nunca biz=4 ROTA LIVRE em test), lookback, skip failed_permanent, skip media_url preenchido, dry-run, status anômalo NULL

## Schedule cron entry exato (Kernel.php)

```php
// Wave 3 Agent B (2026-05-15) — retry mídia inbound recente (24h) órfã.
// Camada 4 pareada: Camada 4 só pega `status IN (pending, downloading)`.
// Este comando é MAIS PERMISSIVO (qualquer media_url IS NULL com media_mime
// NOT NULL, status irrelevante exceto failed_permanent) e MAIS CONSERVADOR
// (só últimas 24h, limit 200).
$schedule->command('whatsapp:retry-recent-media-downloads --hours=24 --limit=200')
    ->hourlyAt(15)
    ->withoutOverlapping(30)
    ->environments(['live'])
    ->onFailure(function () {
        \Illuminate\Support\Facades\Log::channel('single')->error(
            'Schedule whatsapp:retry-recent-media-downloads FALHOU — mídia recente pode atrasar no Inbox'
        );
    });
```

## SQL probe pra Wagner validar em prod biz=1

```sql
-- Quantas mídias inbound últimas 24h prod biz=1 com URL faltando?
SELECT type,
       COUNT(*) AS total,
       SUM(media_url IS NULL) AS sem_url,
       SUM(media_download_status = 'failed_permanent') AS cap_atingido,
       SUM(media_download_status IS NULL) AS status_anomalo_null
FROM messages
WHERE business_id = 1
  AND type IN ('image','audio','video','document')
  AND created_at > NOW() - INTERVAL 24 HOUR
GROUP BY type;
```

**Expectativa pós-deploy:** após o primeiro tick `hourlyAt(15)` rodar, `sem_url` deve cair drasticamente. `status_anomalo_null` > 0 confirma que Agent B pega o que Camada 4 deixa passar.

## Comando ad-hoc pra rodar manual em prod (Wagner urgente)

```bash
# SSH Hostinger primeiro (warm-up + retry):
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'cd domains/oimpresso.com/public_html && php artisan whatsapp:retry-recent-media-downloads --hours=48 --limit=500'
```

Output esperado:
```
Retry mídia inbound recente — Wave 3 Agent B
  lookback : 48h (since 2026-05-13 ...)
  limit    : 500
  dry-run  : não

Total candidatas: NN
OK — NN jobs dispatchados (falhou: 0) em XXXms.
```

Se queue=sync (Hostinger), cada job baixa síncrono no mesmo request. Pode demorar (NN × 60s timeout daemon worst case).

Alternativa segura — rodar atrás de queue worker:
```bash
ssh ... 'cd ... && php artisan queue:work database --queue=default --max-time=60 --stop-when-empty'
```

## Pegadinhas

1. **Daemon CT 100 deve estar HEALTHY** — comando dispatcha `DownloadMediaJob` que chama `POST {daemon}/media/decrypt-url`. Daemon offline → `HttpFetchException` retryable → soft fail → próximo tick tenta de novo (até cap MAX_ATTEMPTS=5).
2. **Rate limit anti-ban do daemon NÃO bloqueia `/media/decrypt-url`** — só bloqueia outbound (`/send-message`). Decrypt é seguro.
3. **`hourlyAt(15)` é offset de propósito** — evita disputar daemon com Camada 4 (minuto 00) e com `whatsapp:cleanup-webhook-nonces` (hourly minuto 00).
4. **Limit padrão 200/hora** — chip Baileys normal recebe ~10-50 mídias/dia. 200/h cobre múltiplos canais grandes. Wagner pode bumpar via Kernel.php se prod biz=1 saturar.
5. **`media_download_status` na coluna real é `string(30)` com default 'pending'** (migration), mas test schema permite NULL pra simular cenário batch insert. Em prod NULL é raro — default da coluna preenche.
6. **`environments(['live'])` só** — local/staging não roda. Match com padrão dos outros Whatsapp cron jobs.

## Pré-flight feito

Arquivos lidos antes de Edit/Write:
- ✅ `Modules/Whatsapp/Console/Commands/BackfillMediaDownloadCommand.php` (decisão Opção B)
- ✅ `Modules/Whatsapp/Jobs/DownloadMediaJob.php` (ciclo + idempotência early-return success+url)
- ✅ `Modules/Whatsapp/Jobs/RetryFailedMediaDownloadsJob.php` (diferenciar Camada 4)
- ✅ `Modules/Whatsapp/Console/Commands/ScanMediaDriftCommand.php` (não confundir scan vs retry)
- ✅ `app/Console/Kernel.php` (entender pattern + onde inserir)
- ✅ `Modules/Whatsapp/Entities/Message.php` (constantes `DOWNLOAD_STATUS_*` + fillable + casts)
- ✅ `Modules/Whatsapp/Tests/Feature/ReparseMediaFromPayloadCommandTest.php` (template schema mirror + helpers)
- ✅ `memory/requisitos/Whatsapp/BRIEFING.md` (contexto re-pareamento 09:25 + 55 msgs history sync)
- ✅ `phpunit.xml` (confirmou `Modules/Whatsapp/Tests/Feature` registrado — Tier 0 proibição satisfeita)

## Próximos passos (não-Agent)

1. Parent agent consolida 3 waves em PR cohorte
2. CI roda Pest `Modules/Whatsapp/Tests/Feature/RetryRecentMediaDownloadsTest.php`
3. Wagner aprova + merge
4. Deploy Hostinger (`git pull` + reload cron — Hostinger lê schedule automaticamente do Laravel)
5. Wagner roda comando ad-hoc 1× com `--hours=48` pra recuperar 55 msgs history do morning incident
6. Primeiro tick automatico `hourlyAt(15)` na próxima hora cheia confirma operação
7. SQL probe (acima) confirma drop de `sem_url`

## Restrições Tier 0 — auditoria

- ✅ `business_id` SEMPRE passado em Job constructor (`DownloadMediaJob::dispatch($m->business_id, ...)`) — ADR 0093
- ✅ SUPERADMIN cross-business via `withoutGlobalScopes()` com comentário PT-BR justificando
- ✅ Idempotente (DownloadMediaJob early-return success+url)
- ✅ PT-BR em descriptions/logs/output
- ✅ Pest cobertura: happy-path + cross-tenant biz=1 vs biz=99 (NÃO biz=4 — ADR 0101)
- ✅ NÃO duplicou BackfillMediaDownloadCommand (cobre cenário diferente)
- ✅ NÃO criou migration
- ✅ NÃO mexeu em DownloadMediaJob, RetryFailedMediaDownloadsJob, ScanMediaDriftCommand, Webhook controllers
- ✅ NÃO fez git ops (parent consolida)
- ✅ NÃO mexeu em `Modules/Whatsapp/Http/`, Observers, ou `resources/js/`
- ✅ Test registrado em `phpunit.xml` (verificado linha 26)
