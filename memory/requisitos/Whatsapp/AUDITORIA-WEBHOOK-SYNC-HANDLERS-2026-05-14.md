# Auditoria webhook handlers sync→async — 2026-05-14

## Resumo executivo

Mapeei **10 endpoints externos** que recebem webhooks de provedores no Hostinger
(`QUEUE_CONNECTION=sync` default — `config/queue.php:16`). Encontrei **3 Tier 0**
que vão ou já estouram timeout/Apache 404 quando o provedor enviar lote, **2
Tier 1** com risco moderado em cenários de pico, e **5 Tier 2** que já delegam pra
Job ou são single-record fast. Wave 1 (Tier 0) deve subir antes de ativar
qualquer integração WooCommerce em produção e antes do próximo push grande pro
webhook GitHub sync-memory.

| Tier | Quantidade | Endpoints |
|------|------------|-----------|
| Tier 0 RISCO ALTO | 3 | Woocommerce (4 rotas), SyncMemory (GitHub→DB), ChannelBaileysWebhook (`history.sync` ainda OK + `message` borderline) |
| Tier 1 RISCO MÉDIO | 2 | InterWebhook (loop pix sync inline), AsaasWebhook (idempotência boa, mas usa queue `rb_webhooks` sem worker) |
| Tier 2 OK | 5 | MetaWebhook, ZapiWebhook, BaileysWebhook (legacy), Brief MCP tool, Connector Delphi handlers |

## Metodologia

Grep patterns aplicados em `Modules/`, `app/Http/Controllers/`, `routes/`:

- `Route::(post|any|match|put|patch).*webhook` (case-insensitive)
- `WebhookController|HookController` em filenames + grep
- Glob `**/*Webhook*.php`
- `function (handle|store|process|callback|notify|webhook|inbound|receive)` em Controllers
- `dispatchAfterResponse|->dispatch(` em Controllers

Confirmação por leitura direta dos handlers candidatos (10 arquivos lidos).
Confirmação config: `config/queue.php:16` → `env('QUEUE_CONNECTION', 'sync')`.
Confirmação queue workers existentes: `app/Console/Kernel.php` — só
`whatsapp-history` (cron everyMinute desde fix madrugada PR #828) e
`mcp:sync-memory` (cron 5min, mas é o COMMAND fallback, NÃO worker de queue
`rb_webhooks`).

**Não pesquisei** runtime CT 100 daemon Node (Baileys) — escopo é Hostinger
PHP-FPM. Cobertura focada em Controllers que respondem HTTP no shared hosting.

## Tier 0 — RISCO ALTO (migrar URGENTE)

### T0-1 · Modules\Woocommerce\Http\Controllers\WoocommerceWebhookController — TODOS os 4 métodos

**Arquivos:**
- `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php:48` (`orderCreated`)
- `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php:93` (`orderUpdated`)
- `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php:145` (`orderDeleted`)
- `Modules/Woocommerce/Http/Controllers/WoocommerceWebhookController.php:211` (`orderRestored`)
- `Modules/Woocommerce/Routes/web.php:3-18` (4 rotas POST `/webhook/order-*`, sem prefixo `api/`, sem middleware além do default web)

**Padrão problemático:**
- Sem `dispatchAfterResponse`, sem Job, sem `ShouldQueue`.
- Cada chamada abre `DB::beginTransaction()` e invoca pipeline pesada:
  - `WoocommerceUtil::createNewSaleFromOrder()` (`Modules/Woocommerce/Utils/WoocommerceUtil.php:965`) chama em sequência: `formatOrderToSale`, `createSellTransaction`, `save`, `createOrUpdateSellLines`, `createOrUpdatePaymentLines`, e `foreach ($input['products'])` com `decreaseProductQuantity` por linha, depois `mapPurchaseSell`. Cada `mapPurchaseSell` faz JOINs em `purchase_lines` e UPDATEs.
- Replay/retry do WooCommerce não passa por idempotency table — repete tudo.
- Multi-tenant Tier 0: rota recebe `{business_id}` como path param sem checagem cruzada (signature valida só do business). É válido pq cliente WooCommerce já é do business dono, mas confio cego do path. Não cataloguei como bug separado pq Wagner já valida via secret + isolamento por business.

**Proposta migração:**
- Job novo: `Modules/Woocommerce/Jobs/ProcessWoocommerceOrderJob.php` (action ∈ `created|updated|deleted|restored`, payload bruto + business_id).
- Queue: `database/woocommerce-orders`.
- Cron: `app/Console/Kernel.php` adiciona `queue:work database --queue=woocommerce-orders --max-time=55 --stop-when-empty --tries=3` everyMinute (mesmo padrão do `whatsapp-history`).
- Controller fica só: validar HMAC, registrar em `pg_webhook_events` pra idempotência, `dispatchAfterResponse` e devolver 200. Ver `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php:151-206` (`handleHistorySync`) como template.
- Estimativa: **M (4-6h)** — 4 handlers + util pesado + Pest cobrindo cada caminho.

### T0-2 · Modules\TeamMcp\Http\Controllers\Mcp\SyncMemoryWebhookController

**Arquivo:** `Modules/TeamMcp/Http/Controllers/Mcp/SyncMemoryWebhookController.php`

**Padrão problemático:**
- Rota `POST /api/mcp/sync-memory` (rotas registradas via `Modules/Jana/Http/routes.php` — handler está em `Modules/TeamMcp`, herança histórica).
- O próprio código tem comentário em `:86-87` confessando: *"Roda em foreground (job não-async pra retornar 200 rápido com stats) — Se ficar lento, pode virar dispatch em queue"*.
- Sequência inline:
  1. `Process::path()->run('git fetch origin main')` + `git reset --hard` (até 45s total timeout — `:190` e `:202`).
  2. `IndexarMemoryGitParaDb::run()` — `Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php:49` itera **13× `foreach glob(...)`** percorrendo `memory/decisions/*`, `memory/sessions/*`, `memory/requisitos/*/SPEC.md`, `memory/requisitos/*/adr/*/*.md`, etc. Hoje memory/ tem 352+ docs (confirmado em CLAUDE.md). Cada arquivo: `file_get_contents` + parse front-matter + UPSERT em `mcp_memory_documents` + redação PII.
  3. Se push tocou SPEC.md → `TaskParserService::syncAll()` (outro full-scan).
  4. `GitTaskLinkerService::handlePushEvent` (parsea commits, cria `mcp_git_links`).
- GitHub webhook timeout é 10s antes de marcar entrega como `failed`. Em push pequeno hoje passa; em push grande (sessão maratona, 20+ ADRs no mesmo commit, batch session-logs) o timeout estoura. GitHub re-tenta 3x → 3x o trabalho inline → PHP-FPM saturado → impacto colateral pra usuários do Hostinger.
- Sem idempotência — re-tentativa GitHub roda tudo de novo.

**Proposta migração:**
- Job novo: `Modules/TeamMcp/Jobs/SyncMemoryFromGithubJob.php` (recebe push payload bruto + event name).
- Queue: `database/mcp-sync`.
- Cron: `queue:work database --queue=mcp-sync --max-time=55 --stop-when-empty --tries=3` everyMinute.
- Controller fica: validar HMAC GitHub (mantém `hash_hmac` + `hash_equals`), opcionalmente filtrar `ref !== refs/heads/main` (sem rodar nada async), `dispatchAfterResponse`, responder `202 Accepted` com `{ ok: true, queued: true }`.
- Manter cron fallback `mcp:sync-memory --reason=cron` everyFiveMinutes que já existe (`app/Console/Kernel.php:275`) como rede de proteção — não muda.
- Estimativa: **M (3-5h)** — Job + Pest. Cuidado com `Process::path` que precisa estar disponível no worker (idem agora).

### T0-3 · Modules\Whatsapp\Http\Controllers\Api\ChannelBaileysWebhookController::handleMessage

**Arquivo:** `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php:214-613`

**Status atual:** Parcialmente mitigado — `handleHistorySync` JÁ está async (fix PR #828
documentado no header do método em `:131-149`). Mas `handleMessage` (inbound msg
individual real-time) processa TUDO inline:

- `Conversation::firstOrCreate` + `Message::firstOrCreate` (2 queries com índice
  composto, OK).
- `LidPhoneResolver::record/resolve` (US-WA-093 — cache + DB lookup).
- `ConversationContactLinker::tryLink` — LIKE phone fuzzy match em tabela `contacts`
  do business (`:451`). LIKE sem suffix em coluna varchar grande = full scan
  business_id-scoped. Em business com muitos contatos vira lentíssimo.
- `DownloadMediaJob::dispatch` — JÁ async (`:525`).
- `CsatResponseParser::tryParse + recordResponse` — 2-3 queries inline (`:568-578`).
- `MacroVariantResponseTracker::trackResponseFromInbound` — DB lookup +
  UPDATE inline (`:595`). Tem try/catch best-effort, OK.

**Padrão problemático real-time:**
- Daemon Baileys CT 100 envia 1 webhook por `messages.upsert` event (1 msg).
  Caso esperado: ≤1 msg por chamada. Mas em pareamento novo OU reentrega após
  daemon crash, o daemon pode pingar dezenas de eventos `message` em sequência
  apertada (não usa `history.sync`, usa `message` individual).
- Cada webhook chega no PHP-FPM Hostinger e ocupa 1 worker pool slot por
  ~200-800ms. Pool é finito (default Hostinger ~20 workers). Burst de 100
  msgs/min → starvation → Apache 404 timeout volta (regressão do fix PR #828
  pra `history.sync` mas em outro caminho).
- ROTA LIVRE biz=4 e biz=1 hoje confirmados com volume real (32 conversations
  em biz=1 — comentário `:436`).

**Proposta migração:**
- Job novo: `Modules/Whatsapp/Jobs/PersistInboundMessageJob.php` (channel_id, business_id, payload bruto).
- Queue: `database/whatsapp-inbound` (separar de `whatsapp-history` pra não competir).
- Cron: `queue:work database --queue=whatsapp-inbound --max-time=55 --stop-when-empty --tries=3` everyMinute. **Atenção**: `every minute` adiciona até 1min de latência inbound. Pra reduzir, manter ConversationContactLinker + CsatResponseParser inline (são fast) e mover só o LID resolution + MacroVariantResponseTracker pro Job. Decisão melhor é dividir o método em "fast path" inline + "slow path" Job.
- Alternativa híbrida (preferida): `dispatchAfterResponse(MacroVariantTrackJob)` e
  `dispatchAfterResponse(LidRecordJob)` mantendo o resto inline. Latência percebida
  fica zero (resposta 200 sai antes), pool libera mais rápido.
- Estimativa: **L (6-8h)** — handler grande (~400 linhas), 4 partes a triar
  (fast vs slow), Pest precisa cobrir cada caminho idempotente.

## Tier 1 — RISCO MÉDIO (monitorar, migrar oportunista)

### T1-1 · Modules\RecurringBilling\Http\Controllers\InterWebhookController

**Arquivo:** `Modules/RecurringBilling/Http/Controllers/InterWebhookController.php:34`

**Padrão:**
- Já dispatcha `ProcessInterWebhookJob` (`:88`) **mas** com `->onQueue('rb_webhooks')`.
- Cron `queue:work --queue=rb_webhooks` **NÃO EXISTE** em `app/Console/Kernel.php`
  (grep confirmou — só `whatsapp-history` e `mcp:sync-memory` command). Com
  `QUEUE_CONNECTION=sync` default no Hostinger, `->onQueue('rb_webhooks')`
  é IGNORADO e o `handle()` do Job roda inline na mesma request.
- Loop `foreach ($pixArray as $pix)` (`:60`): cada item faz 2 queries (`exists` +
  `insert`) + dispatch (que em sync = inline 2 queries + UPDATE + `InvoicePaid`
  event listener). Inter API documentação cita batches de até 100 pix por POST
  webhook (raríssimo, mas possível em fim de mês).
- Realidade hoje: clientes RB ativos baixos, batches típicos 1-3 pix. Por
  isso classifiquei Tier 1 não Tier 0 — risco condicional.

**Proposta:**
1. Adicionar cron em `Kernel.php`: `queue:work database --queue=rb_webhooks --max-time=55 --stop-when-empty --tries=3` everyMinute.
2. Acrescentar `dispatchAfterResponse` no controller para que insert em `pg_webhook_events` seja síncrono (idempotência forte) mas dispatch do Job seja diferido.
3. Estimativa: **S (1-2h)** — só Kernel + ajuste dispatch + 1 teste regressivo.

### T1-2 · Modules\RecurringBilling\Http\Controllers\AsaasWebhookController

**Arquivo:** `Modules/RecurringBilling/Http/Controllers/AsaasWebhookController.php:22`

**Padrão:**
- Idempotência implementada (`pg_webhook_events.event_id`).
- Dispatcha `ProcessAsaasWebhookJob->onQueue('rb_webhooks')` (`:52`). Mesmo
  problema do T1-1: queue sem worker → roda inline.
- Asaas envia 1 evento por POST, payload pequeno (~2KB). `ProcessAsaasWebhookJob`
  faz: update `rb_invoices`, criar `account_transactions`, disparar
  `InvoicePaid` event. ~3 queries + 1 evento. Em sync isso é ~150ms — não estoura.
- Por isso Tier 1, não Tier 0. Mas a queue config é uma mentira hoje.

**Proposta:** Mesma do T1-1 — adicionar cron `rb_webhooks` cobre os dois de uma vez.

## Tier 2 — OK (já async ou trivial)

| Endpoint | Arquivo | Por que OK |
|---|---|---|
| MetaWebhookController::handle | `Modules/Whatsapp/Http/Controllers/Api/MetaWebhookController.php:45` | Só `ProcessIncomingWebhookJob::dispatch` (queue default — funciona porque é sync mas job é pequeno) + return 200. ~5ms. |
| ZapiWebhookController::handle | `Modules/Whatsapp/Http/Controllers/Api/ZapiWebhookController.php:34` | Idem Meta — dispatch único + 200. |
| BaileysWebhookController::handle | `Modules/Whatsapp/Http/Controllers/Api/BaileysWebhookController.php:53` | `message` event vai pro Job; state updates (connected/qr/ban/session_lost) são UPDATE single-row + Centrifugo publish (~10ms). |
| BriefFetchController | `Modules/Brief/Http/Controllers/BriefFetchController.php:30` | Tool MCP autenticado (`mcp.auth` + `throttle:60,1`). `force_refresh` síncrono mas cap 8/dia + apenas Wagner. Cache 5min cobre 99% das chamadas. |
| Connector Delphi handlers (`processa-dados-cliente`, `salvar-cliente`, `salvar-equipamento`, `oimpresso/registrar`, `check-update`, etc — 20+ endpoints em `Modules/Connector/Routes/api.php`) | vários `Modules/Connector/Http/Controllers/Api/*Controller.php` | Single-record por chamada (1 INSERT/UPDATE de `licenca_computador` ou `business`). Cada instalação Delphi pinga 1× por start do executável. Sem loops grandes. Auth via Passport. |
| ChannelBaileysWebhookController::handleHistorySync | `Modules/Whatsapp/Http/Controllers/Api/ChannelBaileysWebhookController.php:151` | Já migrado pelo PR #828 — dispatch `PersistHistorySyncBatchJob` + 202. |
| SuperadminSubscriptionsController callbacks (Stripe/RazorPay/etc) | `Modules/Superadmin/Http/Controllers/SuperadminSubscriptionsController.php` | Não-utilizado em prod biz BR (Asaas/Inter cobrem). Single-record. Grep não achou rotas webhook ativas, só callbacks GET de redirect pós-pagamento. |

## Plano sugerido (waves prioridade)

### Wave 1 — antes de ativar Woocommerce + próximo push grande (P0)
1. **T0-1 Woocommerce** — Job + queue + cron + Pest. Pré-req antes de Wagner ligar primeira loja em prod.
2. **T0-2 SyncMemoryWebhook** — risco crescente conforme memory/ cresce (já em 352+). Catalogar.
3. **T1-1 + T1-2 RecurringBilling** — adicionar cron `rb_webhooks` no Kernel.php. ~1h para os dois. Hoje funciona por acidente (sync inline + jobs pequenos), mas a config mente.

### Wave 2 — antes do próximo pico volume WhatsApp (P1)
4. **T0-3 ChannelBaileysWebhook::handleMessage** — split fast/slow path com `dispatchAfterResponse`. Monitorar pool PHP-FPM Hostinger via `php-fpm status` ou Datadog antes pra confirmar threshold.

### Backlog (P2)
- Auditar middleware `EnforceWebhookBackpressure` (`Modules/Whatsapp/Http/Middleware/EnforceWebhookBackpressure.php`) — já existe no caminho ChannelBaileys novo, pode ser generalizado pra Woocommerce/Inter quando ficarem async.
- Centralizar `pg_webhook_events` em service `WebhookEventRecorder` (hoje 2 controllers replicam o INSERT).

## Anti-padrões encontrados

1. **Queue declarada sem worker** — `->onQueue('rb_webhooks')` aparece em 2 controllers RB, mas Kernel.php não tem `queue:work --queue=rb_webhooks`. Com `QUEUE_CONNECTION=sync` é gambiarra que esconde execução inline. **Adicionar ao CLAUDE.md `memory/proibicoes.md`**: "Toda fila nomeada `->onQueue('xyz')` precisa ter cron `queue:work --queue=xyz` correspondente em `app/Console/Kernel.php` no mesmo PR."

2. **Comentário "vira queue se ficar lento"** — `SyncMemoryWebhookController.php:86-87` literalmente confessa débito técnico que virou risco. **Regra:** se o comentário diz "vira queue se", JÁ É HORA. Esses ficam mais lentos com o tempo, nunca mais rápidos.

3. **`DB::beginTransaction` direto no Controller webhook sem dispatch** — `WoocommerceWebhookController` é o exemplo canônico. Webhook handler deve ser dumb pipe: validar HMAC, idempotency check, dispatch, 200. **Adicionar ao CLAUDE.md**: "Webhook receiver pode tocar DB só pra: (a) validar HMAC, (b) idempotency table, (c) enqueue job. Qualquer outra coisa é Tier 0 antipattern."

4. **Hostinger PHP-FPM pool finito (~20 workers) + `QUEUE_CONNECTION=sync`** — combinação root cause do incident messaging-history (PR #828) e dos riscos catalogados aqui. Documentação já existe em handoff 2026-05-14-0300, mas adicionar à `memory/reference/_INDEX.md` linha explícita "Hostinger só roda Job inline (sync). Worker = cron `queue:work --max-time=55 --stop-when-empty` everyMinute. Padrão canônico desde 2026-05-14."

5. **Idempotency inconsistente** — Whatsapp Channel novo usa `firstOrCreate` em `(business_id, provider_message_id)`. RB usa tabela ponte `pg_webhook_events`. Woocommerce: **nenhuma idempotência**. Padronizar: tabela `pg_webhook_events` é o padrão multi-provider quando o provedor tem `event_id`.

6. **Webhook recebendo `business_id` por path param sem cross-validation** — Woocommerce (`/webhook/order-created/{business_id}`) e RB (`/webhooks/asaas/{businessId}`, `/webhooks/inter/pix/{businessId}`) recebem business_id na URL. Catalogar como pegadinha: o secret HMAC valida que é o provedor real, mas se um business descobre o secret de outro (improvável mas possível em onboarding ruim) o path param permite cross-tenant write. Cross-check: o `credential` é buscado por business_id+secret em InterWebhook (`:36-39`) — OK isolado. Woocommerce idem (`:54` carrega secret do business da URL — também isolado). RB-Asaas idem. **Não é bug**, mas vale comentário no novo CLAUDE.md regra: "Webhook com path business_id precisa carregar credential WHERE business_id+secret AND validar HMAC. Nunca buscar credential global e confiar no path."

## Próximos passos pra Wagner

1. **Aprovar Wave 1** ou pedir refinamento — T0-1 (Woocommerce) é claramente bloqueador antes de ativar loja em prod; T0-2 (SyncMemory) é tempo emprestado.
2. **Decidir cron config Hostinger** — `whatsapp-history` everyMinute já roda. Adicionar mais 3-4 crons `queue:work` no mesmo `app/Console/Kernel.php` é trivial mas vale validar com Hostinger se pool de cron tem capacidade (cada `queue:work` consome 1 PHP-CLI process por minuto durante até 55s).
3. **Adicionar regra ao CLAUDE.md** — antipadrões 1-3 da seção anterior viram proibição Tier 1 (não Tier 0, pq nem todo dev sabe da config sync).
4. **Criar tasks via MCP** — não fiz aqui (restrição da auditoria). Sugestão de IDs: T0-1 = `US-WC-WEBHOOK-ASYNC`, T0-2 = `US-MCP-SYNC-ASYNC`, T0-3 = `US-WA-INBOUND-SPLIT`, T1-* = `US-RB-QUEUE-WORKER-001`.
5. **Não tem ADR sobre isso** — `decisions-search query:"webhook async"` provavelmente vazio. Pode virar ADR `NNNN-webhook-receiver-async-pattern.md` quando Wave 1 mergear, codificando o pattern PR #828 + esta auditoria como canônico.

---

**Autor:** Claude (sessão isolada worktree dazzling-shannon-dbea17 · 2026-05-14)
**Escopo:** Apenas handlers HTTP no Hostinger PHP-FPM. Daemon Baileys CT 100 (runtime Node separado) fora.
**Status:** Pesquisa concluída. Zero código produção alterado. Zero tasks criadas. Aguardando aprovação Wagner pra spawnar Wave 1.
