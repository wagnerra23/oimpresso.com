---
id: requisitos-whatsapp-auditoria-realtime-webhook-ui-2026-05-28
slug: whatsapp-auditoria-realtime-webhook-ui
title: "Whatsapp — Auditoria realtime webhook → UI (Centrifugo, queue, Inertia defer)"
type: auditoria
module: Whatsapp
status: active
date: 2026-05-28
author: claude-code
related:
  - memory/decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md
  - memory/decisions/0135-omnichannel-inbox-arquitetura.md
  - memory/requisitos/Infra/RUNBOOK-deploy-centrifugo.md
  - memory/requisitos/Whatsapp/AUDITORIA-WHATSMEOW-DAEMON-2026-05-28.md
---

# Auditoria — Realtime Webhook → UI Inbox WhatsApp (2026-05-28)

> Disparo: Wagner reportou *"demora absurda pra notificar na tela, Centrifugo funcionando?"*. Sintoma: msg WhatsApp chega no daemon whatsmeow → webhook → DB OK em ~1s; UI só atualiza após 5s (polling) ou 60s (cron). Esperado: <1s via WebSocket. Centrifugo configurado mas latência percebida é a do polling — algo no caminho WS está quebrado silenciosamente.

## 1. Sumário executivo (TL;DR)

**Diagnóstico:** com 99% de certeza, o WebSocket Centrifugo **NUNCA entrega o evento** ao browser. Polling 5s é o canal real, e mascara o problema. Quatro bugs Tier 0 simultâneos causam isso:

1. **CRÍTICO** — `CentrifugoPublisher::doPublish()` checa só `$response->successful()` (HTTP 2xx). Centrifugo retorna **HTTP 200 com `error: {code:102,message:"namespace not found"}`** no body quando publica em `omnichannel:business:*` — namespace `omnichannel` **NÃO está declarado** no `config.json` do CT 100 (só `without_namespace` existe). Publish vira no-op silencioso, log mostra "publish.success" (falso positivo). [`CentrifugoPublisher.php:68-76`](../../../Modules/Whatsapp/Services/Centrifugo/CentrifugoPublisher.php#L68-L76)
2. **CRÍTICO** — `CentrifugoTokenIssuer` emite JWT com claim `channels: ["omnichannel:business:{id}"]` (legado v3 SDK). **Centrifugo v6 espera `b1` (boundary check) + claim `sub` apenas**; canal subscribe precisa de `subscription token` separado OU `allow_subscribe_for_anonymous` no namespace. Sem namespace `omnichannel` declarada, default é `permission denied` (code 103). Token válido mas subscribe falha — front nunca chama `on('publication')`. [`CentrifugoTokenIssuer.php:44-49`](../../../Modules/Whatsapp/Services/Centrifugo/CentrifugoTokenIssuer.php#L44-L49)
3. **ALTO** — Frontend Centrifuge client em [`Index.tsx:106-130`](../../../resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx#L106-L130) NÃO tem handlers `c.on('error')`, `c.on('connected')`, `c.on('disconnected')`, `sub.on('error')`, `sub.on('subscribed')`. Falhas WS são INVISÍVEIS — Wagner vê só "polling funcionando".
4. **MÉDIO** — Queue worker `whatsapp` em cron `everyMinute --stop-when-empty` ([`Kernel.php:628`](../../../app/Console/Kernel.php#L628)) introduz **até 60s** de latência DB-side se WS quebrado E browser inativo (polling pausa em `visibilityState !== 'visible'`).

**Gaps Top-3 priorizados:**
- **P0 — Detectar publish silenciosamente falhado** (parse body Centrifugo `error` field). Esforço: 30min. Impacto: alarme dispara em 1min.
- **P0 — Declarar namespace `omnichannel` no Centrifugo config.json CT 100** + handlers de erro WS no front. Esforço: 1h. Impacto: WS volta a funcionar (latência <500ms).
- **P1 — Pest E2E browser real** (Pest v4 + Playwright `routeWebSocket`) que envia evento sintético → mede latência fim-a-fim. Esforço: 4h. Impacto: regressão fica visível pra sempre.

**Recomendação:** **EVOLUIR** com 3 ondas (W1 = bugfixes hoje, W2 = observability + E2E em 7d, W3 = supervisor + Horizon em 14d). Detalhes Parte 4.

---

## 2. Estado-da-arte 2026 (Parte 1)

### 2.1 Centrifugo v6 — token, namespace, latência

- **Token TTL & refresh**: per oficial spec ([centrifugal.dev/docs/server/authentication](https://centrifugal.dev/docs/server/authentication)), tokens com `exp` claim são VÁLIDOS até o segundo de expiração, mas client SDK precisa registrar **`getToken` callback async** pra renovar antes — *"if the token sets connection expiration then the client SDK will keep the token refreshed by calling a special callback function"* ([centrifuge-js README](https://github.com/centrifugal/centrifuge-js)). Sem `getToken`, conexão CAI silenciosamente após `exp`. Oimpresso usa TTL 3600s e **NÃO registra `getToken`** — sessões longas (atendente 8h no inbox) quebram após 1h.
- **Channel namespace**: canais com prefixo `<namespace>:<rest>` exigem `channel.namespaces[].name = "<namespace>"` no `config.json`. Publish em namespace inexistente retorna **HTTP 200 com `error.code: 102, message: "namespace not found"`** no body — `successful()` não detecta ([centrifugal.dev/docs/server/server_api](https://centrifugal.dev/docs/server/server_api)).
- **Latência p99**: Centrifugo entrega *"up to 500k messages per second with sub-200ms latency in the 99th percentile under heavy load"* ([centrifugal.dev — Emelin](https://medium.com/@fzambia/how-centrifugo-solves-real-problems-of-real-time-messaging-applications-a15d6b8fc8ac)). Real-world WhatsApp inbox: 50-150ms entre publish e DOM update é esperado.
- **`allowed_origins`** já documentado no [RUNBOOK-deploy-centrifugo.md:238](../../../memory/requisitos/Infra/RUNBOOK-deploy-centrifugo.md) — barra final quebra silenciosamente.

### 2.2 Webhook → UI realtime — referências

- **Chatwoot (Rails + ActionCable + Sidekiq)**: bug pattern conhecido — *"Real-time ActionCable logs show job broadcasting, but frontend shows no update; Sidekiq jobs run fully, broadcast events logged, yet messages NOT shown in UI"* ([Issue chatwoot/chatwoot#10557](https://github.com/chatwoot/chatwoot/issues/10557)). Mesmo padrão: backend grita "publish.success", front fica mudo. Mitigação: usar logs do client SDK + handler `error` explícito.
- **Twilio Conversations (SIGNAL 2026 announce)**: arquitetura unificada WS + STT/TTS realtime ([twilio.com/en-us/blog/products/signal-2026](https://www.twilio.com/en-us/blog/products/signal-2026-product-announcements)). Best-practice: webhook returns 200 imediatamente, downstream WS via stream separado, defesa em camadas (polling fallback obrigatório).
- **Front/Intercom**: Pusher Channels com `unsubscribed` event + auto-reconnect 5s backoff. Loga TODOS os state changes do client (connecting/connected/disconnected/error).

### 2.3 Queue worker vs realtime no Hostinger

- **Cron `everyMinute --stop-when-empty`**: latência média 0-60s, p50 ~30s ([sitehost.nz blog](https://sitehost.nz/blog/laravel-queue-performance)). Hostinger shared **NÃO roda supervisor** — sem alternativa nativa.
- **Mitigação canônica**: webhook controller dispatch *queueable async* (current) mas pra realtime crítico, **executar publish síncrono dentro do controller** OU usar Centrifugo Proxy (controller → Centrifugo direto, sem queue). Trade-off: webhook pode demorar +50ms mas UI atualiza em 200ms.
- **Horizon impossível**: requer Redis + supervisor; Hostinger só tem `database` driver ([talltips.novate.co.uk](https://talltips.novate.co.uk/laravel/using-queues-on-shared-hosting)).

### 2.4 Inertia.js — defer props + WebSocket update

- **`router.reload({ only: [...] })` é o padrão correto** com WS ([inertiajs.com/partial-reloads](https://inertiajs.com/partial-reloads)). Mas **`Inertia::defer(fn)`** REQUER que o partial reload mande `only:` incluindo a prop deferred OU explicitamente `reset:[...]` — senão valor permanece `undefined` para sempre.
- **Inertia 2.x novo**: `router.prependToProp()` / `router.replaceProp()` permite update IN-PLACE sem round-trip server ([codecourse.com/articles/deferred-props-in-inertia](https://codecourse.com/articles/deferred-props-in-inertia)). Oimpresso ainda usa `router.reload` (1 round-trip por evento) — funciona, mas burst 10msgs/s gera 10 requisições.
- **Pegadinha conhecida**: `Inertia::always()` força inclusão. Não está sendo usado para `centrifugoConfig` — se token expirar e front fizer `router.reload`, prop chega `null` e WS some.

### 2.5 Pest v4 + Playwright E2E

- Pest 4 integra Playwright nativamente ([pest.dev — Allur blog](https://allur.co/en/blog/pest-4s-playwright-integration-unified-browser-and-visual-testing)) — `it('atualiza inbox via WS')` roda browser real.
- `page.routeWebSocket()` permite interceptar, mockar OU passthrough WS frames ([playwright.dev/docs/network](https://playwright.dev/docs/network)) — measure latência publish→DOM update.

---

## 3. Introspecção código oimpresso (Parte 2)

### 3.1 Fluxo atual realtime (verificado linha-a-linha)

```
[whatsmeow daemon CT 100]
      ↓ POST /api/webhook/whatsmeow + HMAC
[WhatsmeowWebhookController.php:127] dispatch ProcessIncomingWebhookJob
      ↓ queue 'whatsapp' (database driver) — cron everyMinute
[ProcessIncomingWebhookJob.php:237-249] INSERT message
      ↓
[ProcessIncomingWebhookJob.php:261-272] CentrifugoPublisher::publish(
        "omnichannel:business:{$bizId}",
        ['type' => 'message.received', ...]
      )
      ↓
[CentrifugoPublisher.php:56-66] Http::post("{url}/api", X-API-Key, JSON body)
      ↓ ← AQUI: 200 OK com body {"error":{"code":102,"message":"namespace not found"}}
      ↓     successful()===true → return true (FALSO POSITIVO)
[Log::info('whatsapp.centrifugo.publish.success')]    ← mente

[Centrifugo CT 100] NÃO entrega (publication rejeitada server-side)
      ↓
[Index.tsx:109 sub.on('publication')] NUNCA dispara
      ↓
[Index.tsx:141 setInterval 5s polling] FALLBACK que faz tudo funcionar
```

### 3.2 Pontos de código auditados

| Arquivo | Linhas | Achado |
|---|---|---|
| `Modules/Whatsapp/Services/Centrifugo/CentrifugoPublisher.php` | 32-90 | **BUG #1** — checa só HTTP 2xx, ignora `error` field no body. **BUG #2** — `log warning` em exception mas **não** em error-body. |
| `Modules/Whatsapp/Services/Centrifugo/CentrifugoTokenIssuer.php` | 32-59 | JWT v3 syntax: `channels:[]` claim ainda existe mas Centrifugo v6 mudou pra `b1` (broadcast) + subscription tokens separados. Funciona em legacy mode mas exige namespace declarada. |
| `Modules/Whatsapp/Jobs/ProcessIncomingWebhookJob.php` | 261-285 | Publish OK mas try/catch swallow Throwable + log warning **só em exception**. Se `publish()` retornar `false` (HTTP error), comentário linha 254-260 diz "publish.success" sempre. |
| `Modules/Whatsapp/Http/Controllers/Admin/CaixaUnificadaController.php` | 121-132 | Token issued com TTL config 3600s; `centrifugoConfig` é prop **eager** mas após `router.reload({only:['messages']})` ela some (não está em `Inertia::always`) — não causa bug porque useEffect dep array já tem `centrifugoConfig?.token` mas se renderizar `null` o cleanup roda e WS DESCONECTA até token novo. |
| `resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` | 103-136 | **BUG #3** — ZERO error handlers no Centrifuge client. Sem `c.on('error')`, `c.on('connected')`, `sub.on('error')`, `sub.on('subscribed')`. Falha silenciosa garantida. |
| `resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx` | 140-149 | Polling 5s **SEMPRE roda**, paralelo ao WS. Pausa em `document.visibilityState !== 'visible'` — tab inativa + WS quebrado = atendente NÃO vê mensagem até voltar. |
| `app/Console/Kernel.php` | 617-637 | Worker `whatsapp` everyMinute. **Já houve incident 2026-05-28** (comentário linhas 619-622): worker estava órfão, 54 jobs presos 2s. Mostra que isso falha em produção. |
| `memory/requisitos/Infra/RUNBOOK-deploy-centrifugo.md` | 92-112 | `config.json` declara só `client.allowed_origins`, `http_api.key`, `channel.without_namespace`. **NÃO declara namespace `omnichannel`** nem `whatsapp` — qualquer publish nesses prefixos retorna error 102. |
| `Modules/Whatsapp/Tests/Feature/CentrifugoPublishTest.php` | 46-56 | Teste "retorna false em HTTP 5xx" — mas **NÃO testa cenário "200 OK com error body"**. Cobertura incompleta cobre o sintoma exato em produção. |

### 3.3 Logs Hostinger confirmação

Wagner reportou logs com `whatsapp.webhook.whatsmeow.message_persisted` (linha 299 do Job) mas **sem** `centrifugo.publish_failed`. Isso é consistente: o `publish()` retorna `true` (HTTP 200), exception nunca dispara, log warning nunca aparece. **Logs Hostinger silenciosamente normais ENQUANTO o sistema realtime está quebrado.**

---

## 4. TOP 20 erros catalogados (Parte 3)

| # | Nome | Sintoma (Wagner vê) | Detecção | Fix esperado | Pest E2E proposto |
|---|---|---|---|---|---|
| 1 | **Namespace `omnichannel` não declarada Centrifugo** | UI só atualiza via polling 5s; logs mostram publish.success | `curl -XPOST realtime.oimpresso.com/api -H "X-API-Key:$K" -d '{"method":"publish","params":{"channel":"omnichannel:business:1","data":{}}}'` → body tem `error.code:102` | Adicionar `channel.namespaces:[{name:"omnichannel",presence:false,history_size:0}]` em `config.json` CT 100 + restart | `it('publica e detecta error code 102')` mockando Http::fake 200+error body, assert `publish() === false` |
| 2 | **`CentrifugoPublisher` ignora `error` field no body** | Logs mostram "publish.success" mas WS não entrega | grep `centrifugo.publish.success` + grep `centrifugo.publish.body_error` (não existe — adicionar) | Parsear `$response->json('error')`; se `!== null` → log warning + return false | `it('retorna false quando body tem error field e status 200')` |
| 3 | **JWT TTL 1h sem `getToken` refresh** | Atendentes 8h no inbox: depois de 1h o WS some, polling assume; ninguém percebe | DevTools → WS connection state `closed (1006)` após exp; `centrifuge.on('disconnected')` jamais loga | Implementar `getToken` callback no client (chama endpoint `/api/centrifugo/refresh`) | `it('renova token automaticamente antes expirar')` Playwright with timer.advance |
| 4 | **Frontend sem `on('error')` handler** | Falhas WS são invisíveis; Wagner só vê "tela demorada" | DevTools Console → zero log de Centrifuge errors mesmo quando deveria | Adicionar `c.on('error', e=>console.error)`, `c.on('connected')`, `sub.on('subscribed')`, `sub.on('error')` em `Index.tsx:106` | `it('exibe estado conexão centrifuge no DOM data-attr')` |
| 5 | **Token JWT `channels` claim mismatch namespace** | Subscribe falha com `103: permission denied` | Centrifugo log `subscribe failed: not allowed to subscribe`; client `sub.on('error')` | Garantir namespace declarada + token tem `channels:["omnichannel:business:N"]` exato | `it('subscribe channel omnichannel:business:N funciona')` |
| 6 | **Daemon Centrifugo CT 100 caído** | Polling 5s assume tudo; UI lenta mas funciona | `curl -i https://realtime.oimpresso.com/health` → 502/timeout | `docker ps \| grep centrifugo` no CT 100; `docker start centrifugo` | `it('whatsapp:health-check-realtime detecta centrifugo down')` artisan command |
| 7 | **Queue worker `whatsapp` órfão (cron falhou)** | Mensagem chega mas NUNCA persiste — UI nem polling resolve | `SELECT count(*) FROM jobs WHERE queue='whatsapp' AND reserved_at IS NULL` cresce | Restart cron; investigar `runInBackground` PHP-FPM contention | `it('schedule queue:work whatsapp executa')` artisan schedule:test |
| 8 | **`allowed_origins` com barra final** | WS conecta + desconecta imediatamente | DevTools → handshake completa mas `close 1008` | `config.json:"allowed_origins":["https://oimpresso.com"]` sem `/` | Pre-deploy: `jq '.client.allowed_origins[]' config.json \| grep -v '/$'` |
| 9 | **HMAC secret diff Laravel vs Centrifugo** | Token rejeitado, sub falha | Centrifugo log `invalid token signature`; `sub.on('error')` code 109 | Confirmar `WHATSAPP_CENTRIFUGO_TOKEN_HMAC_SECRET` == `config.json.client.token.hmac_secret_key` | `it('CentrifugoTokenIssuer secret == Centrifugo daemon secret')` integration test |
| 10 | **API-Key diff** | publish 401 unauthorized | `Http::response(['error'=>'unauthorized'], 401)`; log warning aparece | Confirmar `WHATSAPP_CENTRIFUGO_API_KEY` == `config.json.http_api.key` | `it('publisher 401 quando api_key errada')` |
| 11 | **Network CT100 ↔ Hostinger firewall** | Publish timeout 5s; Wagner vê polling | `Http::timeout(5)` → `ConnectionException`; log warning OK | Whitelist IP Hostinger (148.135.133.115) no Traefik CT 100 | `it('publisher trata timeout sem derrubar request HTTP')` |
| 12 | **`Inertia::defer` + `router.reload({only:[]})` perde props** | UI atualiza só thread, lista some | DevTools Network → response `props:{thread:{},messages:[...]}` sem `conversations` | Garantir `only:['messages','thread','conversations','stats']` cobre tudo necessário (já está) | `it('partial reload preserva centrifugoConfig')` Pest browser |
| 13 | **`document.visibilityState !== 'visible'` pausa polling** | Tab background: msg nova esperando 30min até voltar | Manual: ativar tab depois + ver chunk de msgs aparecer junto | Adicionar `if(WS_DOWN && !visible) navigator.serviceWorker.register(push)` OU page title flicker | `it('atualiza title \"(3) Caixa\" em background')` |
| 14 | **`router.reload` em burst 10msg/s** | 10 round-trips concorrentes; UI flicker | DevTools Network → 10 GETs paralelos `/atendimento/caixa-unificada` | Debounce 200ms no handler `sub.on('publication')` OU usar `router.replaceProp` | `it('debounce 200ms quando 10 publications em 1s')` |
| 15 | **WS reconnect sem refetch state perdido** | Após reconnect, UI mantém estado antigo (msg recebida durante offline) | `c.on('connecting')` sem trigger de `router.reload` | Em `c.on('connected')`, dispatcha 1 reload imediato pra sincronizar | `it('reload na reconexão WS recupera msgs perdidas')` |
| 16 | **PHP-FPM pool saturado bloqueia dispatch** | Webhook 503/504; mensagem REJEITADA pelo whatsmeow daemon (retry chega depois) | `tail -f /var/log/php-fpm/error.log \| grep "pool seems busy"` | Aumentar pm.max_children ou usar `dispatchAfterResponse` | `it('webhook 200 em <300ms p99')` Pest with stopwatch |
| 17 | **Job `ProcessIncomingWebhookJob` falha mid-flight** | Msg persistida MAS centrifugo.publish nunca executa (linha 261 depois do INSERT 237) | `SELECT count(*) FROM failed_jobs WHERE queue='whatsapp'` > 0 | Garantir try/catch no Job.handle escope só Centrifugo (já está em 261-285) | `it('publish failure não causa job retry/duplicate INSERT')` |
| 18 | **Channel mismatch publish vs subscribe (HISTÓRICO — já corrigido)** | Logs mostram publish em `whatsapp:business:1`, front subscribe `omnichannel:business:1` | grep código `publish.*business` vs frontend `centrifugoConfig.channel` | Já fixed 2026-05-28 (comentário linhas 254-260 do Job). Pest regression test agora. | `it('publish channel canônico === subscribe channel canônico')` snapshot |
| 19 | **ConversationThreadV4 deps useEffect erradas** | Thread aberta mas msg nova só aparece após reabrir | `messages.length` em deps de auto-scroll mas se array referência muda sem length, no-op | Linha [`ConversationThreadV4.tsx:68`](../../../resources/js/Pages/Atendimento/CaixaUnificada/_components/ConversationThreadV4.tsx#L68) `[thread.id, messages.length]` OK — não é bug agora mas frágil | `it('thread atualiza dom quando messages[] novo item')` |
| 20 | **Sentry/OTel não captura `centrifugo.publish_failed`** | Sem alerta proativo; descoberta só por Wagner | OtelHelper::span captura mas se return `false` silencioso, span vai com `status=ok` | Em `doPublish` retorno false, marcar `span->setAttribute('error',true)` + Sentry `captureMessage` | `it('OtelHelper recebe attr error=true quando publish falha')` |

---

## 5. Roadmap automatizações (Parte 4)

Vincula às 3 aprovadas + propõe D. Esforço em horas IA-pair (fator 10x já aplicado).

### A — `whatsapp:health-check-flow` cron 30min (P0, 2h)
**Aprovado.** Adicionar checks:
- `curl https://realtime.oimpresso.com/health` retorna 200
- `publish('healthcheck:business:0', {ping:now()})` retorna `true` E body sem `error` field
- `SELECT count(*) FROM jobs WHERE queue='whatsapp' AND created_at < NOW() - 3min` < 50
- `SELECT count(*) FROM messages WHERE created_at > NOW() - 5min AND business_id IN (SELECT id FROM businesses WHERE has_active_channel=1)` > 0 (se cliente real ativo)

Sentry alert `whatsapp.realtime.degraded` quando falhar.

### B — Pest E2E real do webhook whatsmeow (P0, 3h)
**Aprovado.** Cobertura:
- POST `/api/webhook/whatsmeow` com payload sintético
- Assert: `messages` row criada em <500ms
- Assert: `CentrifugoPublisher` chamado com canal `omnichannel:business:N` E body sem `error`
- Http::fake intercepta + valida payload JSON
- **CRÍTICO:** adicionar cenário "200 OK com error field" → assert publish returns false (gap teste atual)

### C — Skill `wa-validate-prod` bloqueante (P1, 2h)
**Aprovado.** Pré-merge gate que roda:
- `tinker → CentrifugoPublisher::publish('omnichannel:business:0',['ping'=>true])` assert true E sem error body
- `curl wss://realtime.oimpresso.com/connection/websocket` valida handshake 101
- `tail -100 storage/logs/laravel.log | grep centrifugo.publish_failed` → 0 ocorrências últimas 100 entries

### D — **PROPOSTA NOVA** — Pest v4 + Playwright E2E browser real (P1, 4h)
**Justificativa:** A/B/C testam server-side. NENHUM mede tempo real do usuário (publish → DOM update).

Implementação:
```php
// Modules/Whatsapp/Tests/Browser/RealtimeWebhookToUiTest.php
it('msg WhatsApp aparece no DOM em <1s após webhook', function() {
    $page = visit('/atendimento/caixa-unificada')->loginAs($atendente);
    $page->assertSee('Caixa unificada');

    $t0 = microtime(true);
    // Trigger webhook real (não mock)
    $this->postJson('/api/webhook/whatsmeow', $whatsmeowPayload);

    $page->waitFor('[data-conversation-id="'.$convId.'"]', timeout: 2);
    $latency = microtime(true) - $t0;

    expect($latency)->toBeLessThan(1.0, 'realtime SLA <1s violado');
});
```

Bônus: `page.routeWebSocket()` intercepta frames Centrifuge, assert que `publication` event chegou ANTES do polling 5s timer.

---

## 6. Surpresa positiva (oimpresso > mercado)

- **Defense-in-depth polling 5s** já implementado ([`Index.tsx:140-149`](../../../resources/js/Pages/Atendimento/CaixaUnificada/Index.tsx#L140-L149)) — mesmo com WS completamente quebrado, sistema funciona. Chatwoot OSS NÃO tem isso ([issue #10557](https://github.com/chatwoot/chatwoot/issues/10557)). Trade-off: latência 5s vs 200ms, mas zero msg perdida.
- **`visibilityState` check** evita gastar quota Hostinger com tab inativa.

## 7. Surpresa negativa (mercado > oimpresso)

- **Twilio Conversations** publica direto via SDK no controller (sem queue intermediate). Oimpresso depende de cron + queue + WS, 3 pontos de falha em série.
- **Chatwoot ActionCable** loga TODOS state changes do client (connected/disconnected/subscribed/error) — Wagner verá no DevTools imediatamente. Oimpresso: zero logs.

## 8. Métrica de saturação (onde parar)

- p99 latência publish → DOM update < 500ms (≥95% bom; 99% irrelevante diminuir <100ms — eye perception).
- Polling 5s permanente como fallback. Não tentar substituir por push-only.
- Não migrar pra Pusher pago — Centrifugo CT 100 self-host já entrega <200ms. Custo zero recorrente.

---

## 9. Próximos passos imediatos (sequência canônica)

1. **HOJE** (1h) — Wagner ou Felipe SSH CT 100, edita `config.json` adicionando `channel.namespaces:[{name:"omnichannel",presence:false,history_size:0,history_ttl:"0s"}]`, restart container. Validar com `curl` publish manual.
2. **HOJE** (30min) — PR fixando `CentrifugoPublisher::doPublish` pra parsear `error` field do body. Teste Pest cobrindo cenário 200+error.
3. **HOJE** (30min) — PR adicionando handlers `on('error')` + `on('connected')` + `on('disconnected')` + `sub.on('subscribed')` + `sub.on('error')` em `Index.tsx` com `console.error` + Sentry breadcrumb.
4. **AMANHÃ** (2h) — Implementar `getToken` callback no client (endpoint `/api/centrifugo/refresh`) — issue 1h TTL desconexão silenciosa.
5. **7d** — A, B, C, D entregues com PRs separados.

---

**Wagner — leitura mínima:** §1 TL;DR (3min) + §4 tabela top 20 (5min). Total 8min pra ter contexto completo.

**Trust L1:** Itens 12, 17, 19 são candidatos a falso positivo (lidos no código, mas observable evidence só em produção). Itens 1, 2, 3, 4 são bugs **confirmados no código com linhas exatas**.
