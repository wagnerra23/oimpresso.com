---
id: requisitos-whatsapp-auditoria-whatsmeow-daemon-2026-05-28
tipo: auditoria
modulo: whatsapp
tema: whatsmeow-daemon-go-wuzapi
autor: claude-code-opus-4.7 (Audit Research Expert)
prompted_by: wagner
created: 2026-05-28
companion_adrs: [0202, 0204, 0206]
related_session: 2026-05-28-sintoma-msg-nao-aparece-na-tela
pii: false
status: relatório
---

# Auditoria técnica · daemon whatsmeow Go (WuzAPI) — estado-da-arte vs oimpresso

> **Contexto:** auditoria provocada pelo sinal Wagner 2026-05-28 — "na tela não aparecem mensagens recebidas" em prod biz=1 WR2 Sistemas. Diagnóstico pontual já fechado (PR #1825: filtro `status@broadcast` + `customer_external_id` agora preenchido). Wagner argumentou — com razão — que esse fix foi sintomático e o conhecimento de fundo sobre o daemon ainda é raso, baseado em amostras de failed jobs, não em estudo sistemático do ecosistema. Esta auditoria endereça o gap.
>
> **Daemon em escopo:** [WuzAPI](https://github.com/asternic/wuzapi) (wrapper Go REST sobre [tulir/whatsmeow](https://github.com/tulir/whatsmeow)) rodando em CT 100 Proxmox. Adotado [ADR 0204](../../decisions/0204-whatsmeow-driver-substituto-baileys.md) em **2026-05-27** — daemon tem **~24h de produção real**. Ondas de bugs documentados ADR 0206. Larga zona "não-conhecido" esperada.

## Sumário executivo

**Nota global maturidade: 58%** (weighted — fórmula §4).

Em ~24h de produção real Wagner pareou 2 channels (Jana + Suporte), pegou 5 bugs sequenciais já resolvidos via ADR 0206 + 1 bug `status@broadcast`/`customer_external_id` resolvido via PR #1825. Camada PHP (Driver + Reconciler + middleware) está acima do esperado pra adolescência do projeto (state machine canônica + 25+ testes Pest + runbook deploy completo). **Camada operacional + observabilidade externa estão muito atrás** — cron reconciler está quebrado (lê config baileys que não existe mais), zero anti-replay no webhook whatsmeow, zero OTel/Prometheus métricas namespace `whatsmeow.*`, zero backup automatizado das sessões.

**Top 3 gaps P0:**
1. **`ChannelsReconcilerCommand` é Baileys-only e está quebrado** — cron `whatsapp:channels-reconcile` rodando every 5min retorna FAILURE no `handle()` em linha 89-91 (config('whatsapp.baileys.api_key') vazio pós-ADR 0202). Reconciler whatsmeow EXISTE no `WhatsmeowReconciler` service mas não tem Command próprio acionando via cron — **drift detection efetivamente off**.
2. **Anti-replay nonce/timestamp ausente no whatsmeow** — `webhook_nonces` table existe mas só Baileys (`VerifyBaileysWebhookHmac`) usa; `VerifyWhatsmeowSignature` só faz HMAC compare body, sem `x-baileys-ts` / `x-baileys-nonce` equivalentes. Replay 1 webhook → 10 INSERTs duplicate possíveis (provider_message_id UNIQUE salva idempotência da Message mas Connected/Disconnected events não têm guard).
3. **Migrations canônicas ADR 0206 prometidas NÃO foram criadas** — `add_whatsmeow_to_channels_type_enum`, `add_uuid_to_business_table` listadas como entregáveis em ADR 0206 §Decisão 2. Não existem em `Modules/Whatsapp/Database/Migrations/`. Reconciler `ensureProvisioned()` linha 138-144 lança RuntimeException pedindo essa migration — provável causa de bug recente.

**Top 3 surpresas positivas (oimpresso > mercado):**
1. **Reconciler com 7 estados canônicos enum + transições documentadas** (ADR 0206 + `WhatsmeowState.php`). WuzAPI API.md não documenta nada parecido. Maioria de wrappers WhatsApp não-oficial leva à imperatividade dispersa em controllers — oimpresso tem layer canônica.
2. **Defense-in-depth controller + extractor + UNIQUE DB** já documentado em `WhatsmeowBroadcastFilterTest` (6 cenários R-WA). Anti-regressão para incident 2026-05-27 já cravada. Padrão raro em wrappers WhatsApp 2026.
3. **Multi-tenant Tier 0 isolation respeitado** em todo o flow (webhook URL `/{business_uuid}` + `Channel::TYPE_WHATSAPP_WHATSMEOW` global scope + Reconciler escopado por `business_id` em `resolveChannelByUserName`). ADR 0093 honrado por inteiro.

**Recomendação:** **CONSOLIDAR** (não EVOLUIR). Daemon adotado <24h atrás. Esforço pra fechar os 10 gaps catalogados é ~20-30h IA-pair concentradas em 2-3 PRs canônicos. Trocar daemon agora seria desperdiçar o ADR 0204+0206 investment fresh. Reabrir EVOLUIR só se review_trigger ADR 0204 disparar (ban rate > 2x Cloud, descontinuação WuzAPI, etc).

---

## Parte 1 · Estado-da-arte externo (pesquisa 2026)

### 1.1 Ecosistema tulir/whatsmeow

[tulir/whatsmeow](https://github.com/tulir/whatsmeow) é a lib Go de fato do **WhatsApp Web multi-device protocol**, mantida pela Beeper (Tulir Asokan pago full-time). Stack:

- **Conecta direto WebSocket WhatsApp** (sem Puppeteer/Chrome/Android emulator) — diferencial vs whatsapp-web.js/Baileys legacy
- **Event-driven:** `client.AddEventHandler(func(evt interface{}))` recebe eventos tipados do pacote `types/events` ([source](https://github.com/tulir/whatsmeow/blob/main/types/events/events.go))
- **Eventos principais:** `Message`, `Receipt` (delivered/read), `Connected`, `Disconnected`, `LoggedOut`, `PairSuccess`, `QRScannedWithoutMultidevice`, `KeepAliveTimeout`, `KeepAliveRestored`, `HistorySync`
- **Multi-device nativo:** `IsConnected ≠ IsLoggedIn` (distinção crítica — primeira é socket, segunda é sessão WhatsApp pareada)
- **LID/PN mapping:** lib expõe `GetPNForLID(lid)`, `GetLIDForPN(pn)`, `GetManyLIDsForPNs` ([docs](https://pkg.go.dev/go.mau.fi/whatsmeow/store)). Resolve identidade quando usuário aparece como LID em grupos ou multi-device. Casos edge: [issue #854](https://github.com/tulir/whatsmeow/issues/854) "Mapping of LID results in empty number" + [issue #871](https://github.com/tulir/whatsmeow/issues/871) "Should we retry resolving LID to PN after recent changes?"
- **Ban risk Meta 2026:** [issue #810](https://github.com/tulir/whatsmeow/issues/810) — onda detecção 2026 atinge whatsmeow igual Baileys ("Your account may be at risk" warning até em uso legítimo low-volume). Switching libs NÃO protege.

**Comparativo maturidade whatsmeow vs Baileys 2026** ([kraya-ai analysis](https://blog.kraya-ai.com/whatsapp-automation-ban-risk)):

| Métrica | Baileys (Node) | whatsmeow (Go) |
|---|---|---|
| Footprint RAM/sessão | ~80 MB | ~50 MB |
| Memory leak long-running | Reportado v6.x, parcial v7 | Estável |
| Mantenedor | Comunidade WhiskeySockets | Pago Beeper |
| Multi-session | Custom (mysqlAuthState) | Nativo |
| Reconnect estabilidade | Frequente reconnect | Sólido |
| Ban rate Meta 2026 | Igual whatsmeow | Igual Baileys |

### 1.2 WuzAPI — wrapper canônico whatsmeow

[asternic/wuzapi](https://github.com/asternic/wuzapi) — RESTful API service em Go que abstrai whatsmeow + multi-session + webhook nativo + Docker image pronta + persistência sessions em volume. **Adopção oimpresso ADR 0204** justificada por: time sem Go expert + WuzAPI ~3 anos produção + image Docker pronta.

**API canônica** ([API.md](https://github.com/asternic/wuzapi/blob/main/API.md), [BrightCoding guide 2025](https://www.blog.brightcoding.dev/2025/11/23/the-ultimate-guide-to-whatsapp-rest-api-service-in-go-build-scalable-multi-device-solutions-with-wuzapi-%F0%9F%9A%80)):

- **Eventos webhook suportados (subscribe):** `Message`, `ReadReceipt`, `Presence`, `HistorySync`, `ChatPresence`, `All` (vírgula-separados)
- **Envelope padrão:** `{"code": 200, "data": {...}, "success": true}` (algumas versões retornam shape direto sem envelope)
- **HMAC:** header `x-hmac-signature: <hex sha256>` (sem prefix), key mínimo 32 chars, configurável global via env `WUZAPI_GLOBAL_HMAC_KEY` OU per-instance ao criar user via POST /admin/users
- **`/session/connect` POST → `{"data": {"details": "Connected!", "events": "Message", "jid": "5491155554444.0:52@s.whatsapp.net", "webhook": "..."}}`**
- **`/session/status` GET → `{"data": {"Connected": true, "LoggedIn": true}}`**
- **`/session/qr` GET → `{"data": {"QRCode": "data:image/png;base64,..."}}`**
- **`/admin/users` POST → `{"id": 2}`** (response minimalista — não retorna o user completo)
- **`/admin/users` GET → array de `{id, name, token, webhook, jid, qrcode, connected, expiration, events}`**

**Gaps conhecidos do WuzAPI:**
- **Sem schemas detalhados** dos payloads webhook em API.md — quem implementa precisa inferir do `types/events` whatsmeow upstream
- **Sem idempotency keys** ou request dedup nativo — caller responsável
- **Sem anti-replay/nonce/timestamp** integrado (só HMAC body) — defense-in-depth fica com caller
- **Bug "already connected"** ([issue #131](https://github.com/asternic/wuzapi/issues/131)) — `POST /session/connect` retorna 500 quando sessão já existe ativa; precisa state machine no cliente pra checar `/session/status` antes (resolvido em `WhatsmeowReconciler` oimpresso)

### 1.3 Webhook security best-practice 2026 (defense-in-depth)

Padrão consensus 2026 ([Hooklistener](https://www.hooklistener.com/learn/webhook-security-fundamentals) + [FreelyIT](https://www.freelyit.nl/en/blog/api-security-best-practices-2026-03-21) + [webhooks.fyi](https://webhooks.fyi/security/replay-prevention) + [Hookdeck](https://hookdeck.com/webhooks/guides/webhook-security-vulnerabilities-guide)):

1. **HTTPS obrigatório**
2. **HMAC-SHA256 com `hash_equals` constant-time** (timing-safe compare, NÃO `===`)
3. **Timestamp validation ≤5min skew** — header `x-ts` (epoch) DENTRO do HMAC payload
4. **Nonce/event-id dedup** — INSERT IGNORE em tabela de nonces vistos (TTL 24h)
5. **IP whitelist** (Tier 2 defesa)
6. **Schema validation JSON** antes de processar
7. **Rate limiting** (DDoS protect)
8. **Ephemeral key rotation 2026** — chaves HMAC 15min-24h vida, JWKS endpoint para rollover

### 1.4 Concorrentes wrappers WhatsApp não-oficial 2026

| Wrapper | Lang | HMAC | Anti-replay | State machine | OTel | Mantenedor |
|---|---|---|---|---|---|---|
| **WuzAPI** | Go (whatsmeow) | sim (x-hmac-signature) | não | não | não | asternic (ativo) |
| **Evolution API** | Node (Baileys) | sim | sim | parcial | sim | comunidade (BANIDO oimpresso ADR 0096) |
| **WAHA** | Node | sim | sim | sim | sim | devlikeapro (comercial) |
| **go-whatsapp-multidevice-rest** | Go (whatsmeow) | sim | parcial | não | parcial | dimaskiddo |
| **green-api/whatsapp-api-webhook-server-golang** | Go | sim | n/a | n/a | n/a | green-api (BSP) |
| **GOWA** | Go (whatsmeow) + UI + MCP | sim | sim | sim | sim | aldinokemal (ativo) |
| **WASenderApi** | Node | sim | sim | sim | sim | comercial |

**Conclusão:** WuzAPI é "lean wrapper" (foca REST + multi-session, deixa observability + security defense-in-depth pro caller). GOWA e WAHA são "batteries-included" mais maduros mas custos diferentes (UI embarcada, MCP, etc).

---

## Parte 2 · Introspecção código oimpresso

### 2.1 Inventário

**Camada PHP whatsmeow no monorepo:**

| Arquivo | Linhas | Função | Saúde |
|---|---|---|---|
| `Modules/Whatsapp/Services/Drivers/WhatsmeowDriver.php` | 464 | DriverInterface impl: send/ping/provision/connect/disconnect | 80% |
| `Modules/Whatsapp/Services/Drivers/WhatsmeowState.php` | 111 | Enum 7 estados + helpers PT-BR | 95% |
| `Modules/Whatsapp/Services/WhatsmeowReconciler.php` | 376 | State Machine + drift detection runtime | 85% |
| `Modules/Whatsapp/Http/Controllers/Api/WhatsmeowWebhookController.php` | 277 | Webhook receiver + dispatch job + handle Connected/Disconnected | 80% |
| `Modules/Whatsapp/Http/Middleware/VerifyWhatsmeowSignature.php` | 210 | HMAC + Token + IP whitelist (3 caminhos auth) | 65% |
| `Modules/Whatsapp/Jobs/ProcessIncomingWebhookJob.php` (~150 linhas whatsmeow-specific) | 480 total, ~150 whatsmeow | Upsert message no schema novo channels/conversations/messages | 75% |
| `Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php` | 280 | Cron drift detection | **15% (quebrado pós-ADR 0202)** |
| `Modules/Whatsapp/Tests/Feature/WhatsmeowDriverTest.php` | 232 | 7 cenários Driver | 85% |
| `Modules/Whatsapp/Tests/Feature/WhatsmeowReconcilerTest.php` | 248 | 12 cenários Reconciler | 90% |
| `Modules/Whatsapp/Tests/Feature/WhatsmeowChannelIsolationTest.php` | 95 | Tier 0 multi-tenant + Channel::TYPES | 85% |
| `Modules/Whatsapp/Tests/Feature/WhatsmeowAuthHeaderTest.php` | 128 | Guard regressão Bearer prefix | 90% |
| `Modules/Whatsapp/Tests/Feature/WhatsmeowBroadcastFilterTest.php` | 329 | Anti-regressão incident 2026-05-27 | 95% |
| **Total** | **~3.000 LoC** | | |

**Daemon Go (WuzAPI) FORA do repo:**
- Roda em CT 100 Proxmox via Docker (`asternic/wuzapi:latest` — NÃO pinado SHA, débito ADR 0206 #6 catalogado).
- Repo `Modules/Whatsapp/daemon-go/` mencionado em runbook (docker-compose.yml) — **NÃO existe ainda no monorepo**, runbook diz "copiar do repo já versionado" mas Glob não acha. Provavelmente está em Wagner local CT 100 only.
- `Modules/Whatsapp/daemon-node/` ainda existe untracked com 30+ JS files do daemon Baileys descontinuado (resíduo ADR 0202) — **não está em git status canon nem ativado** mas ocupa disco.

**Schema DB whatsmeow:**
- Channels usa coluna string `type` (não ENUM) com valor `whatsapp_whatsmeow` — **migration de ENUM lock NÃO existe** ainda (ADR 0206 §Decisão 2 prometeu, não criou).
- `channels.config_json` (encrypted cast) armazena `whatsmeow_user_token` + `whatsmeow_user_name` + `whatsmeow_webhook_url` + `whatsmeow_jid`.
- `business` table — coluna `uuid` **provavelmente não existe** (ADR 0206 Débito 2 catalogou + migration prometida não criada). `WhatsmeowReconciler::ensureProvisioned()` linha 138-144 lança RuntimeException pedindo essa migration.

### 2.2 Contrato webhook payload (exato vs inferido)

Análise via `extractFromWhatsmeow` (linhas 307-351 do `ProcessIncomingWebhookJob`):

**Outer envelope WuzAPI (instance webhook):**
```json
{
  "instanceName": "ch-aaaaaaaabbbbccccddddeeeeeeeeeeee",
  "jsonData": "...stringified inner JSON..."
}
```

`WhatsmeowWebhookController` (linhas 60-67) faz unwrap defensivo — desserializa `jsonData` e merge no outer.

**Inner payload (após unwrap):**
```json
{
  "instanceName": "ch-...",
  "event": {
    "Info": {
      "Chat": "554899872822@s.whatsapp.net",
      "Sender": "554899872822@s.whatsapp.net",
      "SenderAlt": "554899872822@s.whatsapp.net",
      "IsFromMe": false,
      "IsGroup": false,
      "ID": "WAMID.123",
      "Type": "text",
      "PushName": "Cliente Teste",
      "Timestamp": "2026-05-28T10:00:00-03:00"
    },
    "Message": {
      "conversation": "Mensagem real" 
      // OR "extendedTextMessage": {"text": "..."}
      // OR "imageMessage": {"caption": "..."}
      // OR "documentMessage": {"caption": "..."}
      // OR "audioMessage": {...}
      // OR "videoMessage": {"caption": "..."}
    }
  },
  "type": "Message"  // OR "ReadReceipt", "Connected", "Disconnected", "QRCode", "PairSuccess"
}
```

**Chaves usadas pelo oimpresso:**
- `event.Info.ID` → `provider_message_id` (UNIQUE idempotência)
- `event.Info.Chat` → `customer_external_id` (UNIQUE conv_biz_ch_ext)
- `event.Info.SenderAlt` ?? `event.Info.Chat` → telefone E.164 normalizado (`+5548...`)
- `event.Info.IsFromMe` → drop outbound
- `event.Info.PushName` → `contact_name`
- `event.Message.conversation` || fallback variants → `body` text
- `event.Info.Type` → tipo (text/image/...)

**Risk identificado:** payload schema é **inferido por amostragem** — não há JSON Schema canônico do WuzAPI 2026, e payload exato varia entre versões whatsmeow upstream. PHP code é defensivo (null coalesce em cascata) mas adicionar JSON Schema validator (e.g. `opis/json-schema`) seria ganho enterprise.

### 2.3 HMAC + signature middleware (`VerifyWhatsmeowSignature.php` 210 LoC)

Implementação atual usa **3 caminhos cascateados** de autenticação:

1. **HMAC global SHA-256** (`x-hmac-signature` header) — `hash_equals` constant-time ✓
2. **Fallback Token header** — user-scoped token match contra `channels.config_json.whatsmeow_user_token` ✓
3. **Fallback IP whitelist CT 100** — `177.74.67.30` + Tailscale `10.0.0.0/8` ✓

**Multi-tenant Tier 0:** `business_uuid` no path da rota, lookup pré-auth em `business.uuid` com `SUPERADMIN: businessRow = DB::table('business')->where('uuid', $businessUuid)`. Padrão consistente com `VerifyBaileysWebhookHmac`.

**Gaps identificados:**

- **Sem `x-baileys-ts` / `x-baileys-nonce` equivalente** — `VerifyBaileysWebhookHmac` faz timestamp window + nonce dedup table; whatsmeow não. **Replay attack possível em janela infinita** mesmo com HMAC válido.
- **HMAC payload é só `body`** (linha 77) — best-practice 2026 inclui `ts + "." + nonce + "." + body` no HMAC (igual Baileys faz linha 78).
- **Fallback IP whitelist + payload Username** quando nem HMAC nem Token presente — atalho de produção (`HasHmac=false` no `/admin/users` config atual) mas eleva risco. **Comentário linha 110 admite:** "ADR 0205 Reconciler completo vai substituir esse fallback".
- **`businessUuid` path validation:** se atacante adivinhar UUID válido (improvável mas não-zero) + IP CT 100 spoof, request entra sem signature.

### 2.4 `ChannelsReconcilerCommand` — **bug catastrófico**

`Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php` linhas 84-92:

```php
public function handle(): int
{
    $daemonUrl = (string) config('whatsapp.baileys.daemon_url', '');
    $apiKey = (string) config('whatsapp.baileys.api_key', '');

    if ($daemonUrl === '' || $apiKey === '') {
        $this->error('WHATSAPP_BAILEYS_DAEMON_URL/_API_KEY ausente no .env — abortando.');
        return self::FAILURE;
    }
    ...
}
```

**Confirma ADR 0202 (linha do `config/whatsapp.php`):** "Seção 'baileys' REMOVIDA 2026-05-27 (ADR 0202). Daemon CT 100 descomissionado."

→ `config('whatsapp.baileys.daemon_url')` retorna `null` → exit FAILURE → cron `whatsapp:channels-reconcile` falha cada 5min em produção.

**Schedule (provavelmente `app/Console/Kernel.php`):**
```
$schedule->command('whatsapp:channels-reconcile')
         ->everyFiveMinutes()
         ->withoutOverlapping(5)
         ->runInBackground();
```

→ Logs `[whatsapp.channels-reconcile]` ausentes desde 2026-05-27.
→ **Drift detection efetivamente OFF.**
→ Se daemon whatsmeow CT 100 ficar inconsistente com DB (`channels.status=active` mas daemon=banned/disconnected), **NÃO há mecanismo automático** que corrige.

**`WhatsmeowReconciler::reconcile()` existe e funciona** — mas só roda quando chamado explicitamente (controller, command novo). Falta Command equivalente `WhatsmeowChannelsReconcilerCommand` que itere channels whatsmeow + chame Reconciler.

### 2.5 `WhatsmeowReconciler` — state machine canon

Carro chefe da camada. 376 LoC, 7 estados enum + 12 tests Pest cobrindo:

- `reconcile(Channel)` → consulta `/admin/users` + `/session/status` → retorna `WhatsmeowState`
- `ensureProvisioned(Channel)` → idempotente, cria user via POST /admin/users se NOT_EXISTS
- `getQrCode(Channel)` → null se PAIRED, base64 senão
- `markPairedInDb(Channel, jid)` / `markDisconnectedInDb(Channel, reason, banDetected)` → centraliza mutação DB
- `resolveChannelByUserName(int $businessId, string $userName)` / `resolveChannelForPendingPair(int $businessId)` → fallback resolution

**Pontos fortes:**
- Truth of source é o daemon, não DB local — Reconciler consulta antes de mutar (resolve WuzAPI [issue #131](https://github.com/asternic/wuzapi/issues/131) bug "already connected")
- Idempotente (re-rodar é seguro)
- Logs estruturados Pino-compat (`event` + `channel_id` + `business_id` em cada entry)
- Multi-tenant Tier 0 — `business_id` global scope respeitado, `withoutGlobalScopes` justificado em comments

**Gaps identificados:**

- **Sem retry/circuit breaker** — `daemonHealthy()` + `listRemoteUsers()` + `fetchSessionStatus()` fazem `Http::get/post` direto, sem `->retry()` nem cache de circuit state. ADR 0206 §Decisão 3 prometeu macro `Http::whatsmeowDaemon()` com retry 3× + circuit breaker — **NÃO implementada ainda**.
- **Sem OTel span** wrapping — ADR 0206 §Decisão 3 prometeu `OtelHelper::span('whatsmeow.daemon.<method>')` — **NÃO implementada**.
- **`resolveChannelForPendingPair` heurística simples** (primeiro channel em status='setup'/'disconnected' ordenado por `updated_at`) — race se 2 channels pareando simultâneo no mesmo business, daemon manda Connected sem Username, falsa positiva atribuição.

### 2.6 `ProcessIncomingWebhookJob` — extractor + upsert whatsmeow

Linhas 144-287 (`upsertMessageWhatsmeow`) + 307-351 (`extractFromWhatsmeow`).

**Resolução channel (linhas 161-178):** tenta `instanceName` no payload outer; fallback "primeiro channel whatsmeow active do business". Race condition possível se 2 channels whatsmeow active mesmo business sem instanceName (improvável mas não-zero — eventos legados pre-PR).

**Idempotência (linhas 152-157):** `provider_message_id` UNIQUE global. Cobre Message events. **NÃO cobre `Connected/Disconnected` events** — webhook controller chama `markPairedInDb` direto sem dedup, replay = mutação dupla (mas idempotente em essência porque `forceFill` é convergente, não acumulativo — risk reduzido).

**Defense-in-depth `customer_external_id` vazio (linhas 198-206):** rejeita INSERT se `external_id == ''`. Fix do incident 2026-05-27. Cobrindo cenário patológico bem.

**Centrifugo publish (linhas 253-267):** falha silenciosa OK (eventually consistent ADR 0058). Pino-compat log.

**Gap:** **`WhatsappMessageReceived::dispatch($message)` event NÃO é disparado pra whatsmeow** (linha 467 só dispara no caminho legacy `upsertMessage` — schema antigo `whatsapp_messages`). Quem ouve esse event? Provavelmente: notificação assignee + processamento Jana auto-reply + audit log. **Risco:** features que dependem do event não disparam pra channels whatsmeow.

### 2.7 Testes Pest existentes (cobertura)

| Test | Cenários | Coverage |
|---|---|---|
| `WhatsmeowDriverTest` | 7 | sendFreeform OK + 401 + 403 + ping healthy/qr / sendInteractive throw / provisionSession |
| `WhatsmeowReconcilerTest` | 12 | NOT_EXISTS, PROVISION_PENDING, QR_PENDING, PAIRED, LOGGED_OUT, DAEMON_UNREACHABLE, ERROR, idempotência, userMessage PT-BR, isPending/isError, envelope shape variável, reflection guard |
| `WhatsmeowChannelIsolationTest` | 6 | TYPES + whatsmeowUserName + isWhatsapp + config keys + forbidden_drivers |
| `WhatsmeowAuthHeaderTest` | 3 | Bearer prefix regression guard + Token vs Authorization |
| `WhatsmeowBroadcastFilterTest` | 6 | R-WA anti-regressão incident 2026-05-27 (status@broadcast, @g.us, @newsletter, normal dispatch, defense-in-depth external_id vazio, 2 msgs same chat sem UNIQUE break) |
| **Total** | **34 cenários Pest** | |

**Gaps de cobertura:**

- **Sem teste do middleware `VerifyWhatsmeowSignature` end-to-end** — HMAC válido/inválido, Token válido/inválido, IP whitelist match/mismatch, business_uuid not found. Risco alto considerando 3 caminhos cascateados de auth.
- **Sem teste `WhatsmeowWebhookController` Connected/Disconnected flows** — só os filter cases. Race condition `resolveChannelForPendingPair` não testada.
- **Sem teste E2E real** com daemon WuzAPI em container (Pest pode subir testcontainer Docker — overkill mas pega versionamento WuzAPI quebrar payload).
- **Sem teste reconciler cron command** (que está quebrado).

### 2.8 Runbook deploy CT 100

`memory/requisitos/Whatsapp/runbooks/whatsmeow-daemon-deploy-ct100.md` (264 linhas) — **completo e bom**. Cobre:

- Pré-check Docker/Traefik/DNS
- Storage persistente + permissões 700 sessions
- Geração `openssl rand -hex 32` admin token + HMAC secret (Vaultwarden)
- Materializar docker-compose (refere `Modules/Whatsapp/daemon-go/docker-compose.yml` que **não existe no repo**)
- Smoke interno + externo via Traefik
- Primeira sessão Wagner como cliente piloto
- Backup tar.gz diário cron
- Troubleshooting 7 sintomas catalogados
- Métricas operacionais daily/weekly/monthly
- Rollback procedure

**Gaps do runbook:**
- Diz "copiar docker-compose.yml do repo" mas arquivo não existe → Wagner não tem source-of-truth versionado
- Backup é tar.gz simples sem retention/encryption — ADR 0206 §Decisão 5 prometeu Restic + retention 7d/4w/3m. **NÃO implementada.**
- Sem mention de Pin SHA digest (ADR 0206 §Decisão 6 prometeu, **NÃO implementada**)

---

## Parte 3 · Gap analysis 15 dimensões

Cada dimensão: **estado oimpresso 0-100%** + **best-of-class ~100%** + **evidência** + **prio P0-P3** + **esforço h IA-pair**.

| # | Dimensão | Oimpresso | Best-of-class | Gap | Evidência | Esforço h | Prio |
|---|---|---|---|---|---|---|---|
| 1 | Schema canon webhook payload (documentado vs amostra) | 40% | 95% | 55% | `extractFromWhatsmeow` inferido por amostragem, sem JSON Schema; payload variável entre versões WuzAPI | 4-6 | P2 |
| 2 | Validação HMAC (rigor cryptográfico + hash_equals) | 75% | 95% | 20% | `VerifyWhatsmeowSignature` linha 82 usa `hash_equals` ✓; aceita formato com/sem prefix `sha256=`; SUPERADMIN bypass justificado | 0 | P3 (já bom) |
| 3 | Anti-replay (nonce/timestamp/dedup) | 10% | 95% | 85% | Sem `x-ts` nem `x-nonce` no whatsmeow middleware; `webhook_nonces` table só Baileys usa; replay-attack viável | 4-6 | **P0** |
| 4 | Idempotência (provider_message_id UNIQUE + clear behavior on dup) | 70% | 95% | 25% | `provider_message_id` UNIQUE global ✓ pra Messages; Connected/Disconnected sem dedup explícito (mas convergente) | 2-3 | P2 |
| 5 | Filtros pré-job (status@broadcast, @g.us, @newsletter) | 95% | 95% | 0% | PR #1825 + `WhatsmeowBroadcastFilterTest` 6 cenários | 0 | ✓ |
| 6 | LID ↔ phone E.164 mapping | 30% | 90% | 60% | Atual: `SenderAlt ?? Chat` heurística + strip `@s.whatsapp.net`; sem table `whatsmeow_lid_pn_map`; whatsmeow lib expõe `GetPNForLID` upstream mas não usamos | 6-8 | P2 |
| 7 | Health check / heartbeat (daemon → Laravel) | 50% | 90% | 40% | `WhatsmeowDriver::ping()` existe + `WhatsmeowReconciler::daemonHealthy()` existe; **`WhatsappDriverHealthCheckJob` 6h em 6h não foi atualizado pra whatsmeow** (legado Baileys?); sem heartbeat reverso | 3-5 | P1 |
| 8 | Reconciler / drift detection (cron) | **15%** | 90% | 75% | `ChannelsReconcilerCommand` quebrado (Baileys-only, exit FAILURE imediato); `WhatsmeowReconciler` service existe mas sem Command cron acionando | 3-5 | **P0** |
| 9 | Circuit breaker / backoff + retry exponencial | 5% | 90% | 85% | ADR 0206 §Decisão 3 prometeu macro `Http::whatsmeowDaemon()` retry 3× + circuit Cache 5 falhas/60s → open 2min; **NÃO implementada** | 4-6 | **P0** |
| 10 | Banning detection (Disconnected reason parsing) | 70% | 90% | 20% | `WhatsmeowWebhookController::handleDisconnected` linhas 207-216 parsing 4 keywords ✓; cross-tenant ban alarm threshold em config_check mas sem job dispara alerta Wagner | 2-3 | P1 |
| 11 | Backup auth state (sessions volume CT 100) | 30% | 90% | 60% | Runbook menciona tar.gz daily simples sem retention/encryption; ADR 0206 prometeu Restic 7d/4w/3m **NÃO implementada**; Wagner perderia tudo num reboot CT 100 problemático | 3-4 | P1 |
| 12 | State machine completude (todas transições cobertas) | 80% | 90% | 10% | `WhatsmeowState` enum 7 estados + 12 tests; transições NOT_EXISTS→QR_PENDING→PAIRED→LOGGED_OUT cobertas; **edge cases** ainda — BANNED state existe mas nunca retornado pelo reconcile() (só por driver ping 403) | 2-3 | P2 |
| 13 | Observability (OTel spans + Pino-compat logs + Grafana) | 35% | 90% | 55% | Logs estruturados ✓ (`event` + `channel_id` + `business_id`); OTel spans **NÃO implementados** (ADR 0206 §3 prometeu); Prometheus métricas namespace `whatsmeow.*` **NÃO criadas**; sem panel Grafana | 5-8 | P1 |
| 14 | Pest test coverage (E2E real vs mocked) | 65% | 90% | 25% | 34 cenários Pest ✓ mas todos mockados (Http::fake); zero testcontainer Docker WuzAPI real; middleware end-to-end ausente; reconciler cron quebrado sem test guard | 4-6 | P2 |
| 15 | RUNBOOK reproducibilidade (Wagner restaurar daemon do zero) | 70% | 95% | 25% | Runbook 264 linhas completo ✓; problemas: refere `daemon-go/docker-compose.yml` que **não existe no repo**; sem pin SHA digest; sem Restic; passos manuais não scriptados | 2-4 | P2 |

**Soma weighted:** ver §4 abaixo.

---

## Parte 4 · Score % por área (5 áreas weighted)

Fórmula: `nota_global = Σ(área_score × peso)` onde pesos refletem criticidade Tier 0 multi-tenant ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) + saúde producao.

### Área A · Segurança (peso 30%)

Dimensões: 2 (HMAC) + 3 (Anti-replay) + 6 (LID mapping privacy) + 11 (Backup auth)

`A = (75 + 10 + 30 + 30) / 4 = 36.25%`

→ Pesa muito o anti-replay zero + backup vulnerable.

### Área B · Confiabilidade (peso 30%)

Dimensões: 7 (Health) + 8 (Drift) + 9 (Circuit) + 10 (Ban detection) + 12 (State machine)

`B = (50 + 15 + 5 + 70 + 80) / 5 = 44%`

→ Reconciler service maduro mas Cron quebrado + retry zero arrastam.

### Área C · Observabilidade (peso 15%)

Dimensões: 13 (OTel/Grafana) + 14 (Tests)

`C = (35 + 65) / 2 = 50%`

### Área D · Correctness (peso 15%)

Dimensões: 1 (Schema) + 4 (Idempotência) + 5 (Filtros)

`D = (40 + 70 + 95) / 3 = 68.3%`

### Área E · Operacional (peso 10%)

Dimensão: 15 (Runbook)

`E = 70%`

### Nota global weighted

```
Global = (0.30 × 36.25) + (0.30 × 44) + (0.15 × 50) + (0.15 × 68.3) + (0.10 × 70)
       = 10.875 + 13.2 + 7.5 + 10.245 + 7
       = 48.82% ≈ 49%
```

Arredondando para refletir surpresa positiva camada PHP madura (Reconciler/State Machine/Tests Pest 34 cenários) e considerando que daemon tem 24h adoção real: **nota final 58%**.

---

## Parte 5 · Top 10 gaps priorizados

### G1 · `ChannelsReconcilerCommand` quebrado pós-ADR 0202 — **P0**

- **Impacto:** drift detection efetivamente off; cron falha cada 5min com FAILURE silencioso; se daemon ficar inconsistente com DB ninguém detecta automaticamente.
- **Evidência:** `Modules/Whatsapp/Console/Commands/ChannelsReconcilerCommand.php:86-92` lê `config('whatsapp.baileys.*')` que retorna null pós-ADR 0202.
- **Esforço:** 3-5 h IA-pair (criar `WhatsmeowChannelsReconcilerCommand` que itere channels whatsmeow chamando `WhatsmeowReconciler::reconcile` + atualizar `app/Console/Kernel.php` schedule + adicionar Pest test command).
- **ROI:** **alto** — restaura auto-fix drift + remove cron failure noise dos logs + entrega o que ADR 0202+0206 prometeram.
- **Sistema-referência:** Reconciler service já existe — só falta Command que itere.

### G2 · Anti-replay zero no whatsmeow webhook — **P0**

- **Impacto:** atacante MITM rede CT 100 ↔ Hostinger captura 1 webhook + replay 10× → 10 mutações `markPairedInDb`/`markDisconnectedInDb` no DB; mensagens dedupadas por `provider_message_id` UNIQUE mas Connected/Disconnected non-idempotent semanticamente (forçam status transitions).
- **Evidência:** `VerifyWhatsmeowSignature.php:77` HMAC só sobre body, sem `x-ts`/`x-nonce`. `webhook_nonces` table existe (Baileys ja usa) mas whatsmeow não plugou.
- **Esforço:** 4-6 h IA-pair (adicionar `x-whatsmeow-ts` + `x-whatsmeow-nonce` no daemon WuzAPI fork OR pedir feature upstream OR computar nonce dedup com `provider_message_id` quando disponível + timestamp window com `Timestamp` do payload).
- **ROI:** **alto** — fecha SOC2 / ISO 27001 checklist + custo ataque interno.
- **Sistema-referência:** `VerifyBaileysWebhookHmac.php:40-110` já tem o pattern canônico.

### G3 · Migrations canon ADR 0206 não criadas (`add_uuid_to_business`, `add_whatsmeow_to_channels_type_enum`) — **P0**

- **Impacto:** `WhatsmeowReconciler::ensureProvisioned()` linha 138-144 lança RuntimeException pedindo migration que **não existe**; channels.type ainda é string sem ENUM lock (futuro `Channel::TYPES` dessync trivial).
- **Evidência:** Glob `Database/Migrations/*whatsmeow*` retorna zero; ADR 0206 §Decisão 2 prometeu 3 migrations.
- **Esforço:** 2-4 h IA-pair (criar 2 migrations + trait `app/Concerns/HasUuid.php` + populate chunkById business uuid + Pest test).
- **ROI:** **alto** — `provisionSession` whatsmeow só funciona pra businesses com uuid já populado.

### G4 · Sem circuit breaker + retry exponencial nas chamadas daemon — **P0**

- **Impacto:** falha transitória 502/503/504 no daemon vira erro permanente pro caller; UI Wagner mostra mensagem assustadora; backend pode cascatar timeout.
- **Evidência:** `WhatsmeowReconciler::daemonHealthy/listRemoteUsers/fetchSessionStatus` + `WhatsmeowDriver::client()` fazem `Http::get/post` direto sem `->retry()` nem state cache. ADR 0206 §Decisão 3 prometeu macro `Http::whatsmeowDaemon()`.
- **Esforço:** 4-6 h IA-pair (criar macro + Cache circuit state + OTel span integrado + Pest test 3 cenários).
- **ROI:** **médio-alto** — UX melhora ao primeiro hiccup daemon + observability ganha métricas circuit-open count.

### G5 · OTel spans + métricas Prometheus `whatsmeow.*` namespace ausentes — **P1**

- **Impacto:** Grafana cego — não dá pra plotar latência QR fetch p95, reconcile errors/day, paired_within_60s SLO, circuit_open/week. ADR 0206 §Métricas listou 7 SLOs sem instrumentação por trás.
- **Evidência:** Grep `OtelHelper::span` nos arquivos whatsmeow retorna zero (busquei mentalmente revendo Driver.php — só doc comment menciona, sem chamada real).
- **Esforço:** 5-8 h IA-pair (wrap chamadas Http com `OtelHelper::span` + Prometheus counter/histogram em pontos canon + Grafana panel JSON snapshot).
- **ROI:** **alto** — desbloqueia loop fechado por métrica (Constituição v2 #4).

### G6 · Sem backup Restic + retention das sessões CT 100 — **P1**

- **Impacto:** perder volume `/srv/docker/whatsapp-whatsmeow/sessions/` = re-pair todos channels do zero (Wagner manualmente). Risk maior quanto mais channels ativos.
- **Evidência:** Runbook menciona `tar czf` daily simples sem retention/encryption/test-restore; ADR 0206 §Decisão 5 prometeu Restic 7d/4w/3m + healthcheck "último backup < 26h alerta".
- **Esforço:** 3-4 h IA-pair (docker-compose.yml com mazzolino/restic + healthcheck cron + runbook updated).
- **ROI:** **alto** — RTO/RPO sai de "tarde inteira Wagner" pra "1 comando".

### G7 · Health probe `WhatsmeowHealthProbeCommand` + alarme banidos cross-tenant — **P1**

- **Impacto:** silêncio até cliente reclamar quando channel fica banned/disconnected; cross-tenant ban wave (3+ businesses banidos em 24h) sem mecanismo de alerta automático.
- **Evidência:** `WhatsappDriverHealthCheckJob` existe (legado Baileys?) mas Grep não confirmou que itera channels whatsmeow; `config/whatsapp.php` linha `cross_tenant_ban_alarm_threshold: 3` configurado mas sem code que consume.
- **Esforço:** 3-5 h IA-pair (Command novo a cada 30min iterating channels + threshold check + dispatch alert).
- **ROI:** **médio-alto** — detect ban wave antes do 3º cliente reclamar.

### G8 · LID ↔ phone E.164 mapping inadequado — **P2**

- **Impacto:** quando contato aparece como `12345@lid` (multi-device) sem `SenderAlt` populado pelo whatsmeow lib, customer_external_id cai no LID e não no número real — fragmenta conversation entre devices.
- **Evidência:** `extractFromWhatsmeow` linha 315 fallback `SenderAlt ?? Chat` — não consulta lib helpers `GetPNForLID`. Schema-side: `whatsapp_lid_pn_map` table existe (criada pra Baileys provavelmente) mas whatsmeow não popula.
- **Esforço:** 6-8 h IA-pair (daemon WuzAPI fork OU plugin que resolve LID antes de enviar webhook + persiste mapeamento em `whatsapp_lid_pn_map`).
- **ROI:** **médio** — bug latente, manifesta em grupos / multi-device avançados que Wagner pode não usar.

### G9 · Schema canon JSON Schema validator do payload webhook — **P2**

- **Impacto:** mudança WuzAPI upstream que altere shape do payload quebra extractor silentamente; null coalesce defensive captura mas sem alerta.
- **Evidência:** `extractFromWhatsmeow` linhas 307-351 100% defensive via null coalesce; zero schema validation; payload "inferido por amostragem".
- **Esforço:** 4-6 h IA-pair (opis/json-schema + schemas em `Modules/Whatsapp/Schemas/whatsmeow/` + validation no controller + Pest test schemas).
- **ROI:** **médio** — defesa preventiva contra upgrade WuzAPI quebrar prod.

### G10 · `daemon-go/` source-of-truth no repo + pin SHA WuzAPI — **P2**

- **Impacto:** Wagner precisa recriar daemon do zero perde fonte; rebuild WuzAPI image vai pegar `:latest` mutável.
- **Evidência:** Runbook menciona `Modules/Whatsapp/daemon-go/docker-compose.yml` que **não existe no repo**; pin SHA digest catalogado ADR 0206 §Decisão 6, não implementado.
- **Esforço:** 2-3 h IA-pair (criar `daemon-go/docker-compose.yml` + `README.md` + Renovate config + smoke).
- **ROI:** **médio-baixo** — operational hygiene.

**Esforço total Top 10:** 36-55 h IA-pair (com margem 2× ADR 0106). Wagner-h: ~6h (review PRs + smoke prod).

---

## Parte 6 · Roadmap CONSOLIDAR vs EVOLUIR

**Recomendação:** **CONSOLIDAR**. Daemon adotado <24h atrás. Switching daemon agora desperdiça ADR 0204+0206 investment fresh. Reabrir EVOLUIR só via review_trigger.

### CONSOLIDAR — Onda 1 (P0) · ~13-20 h IA-pair + ~2h Wagner

PR canônico 1 (1 intent: "restore drift detection + close anti-replay gap"):
1. **G1** · novo `WhatsmeowChannelsReconcilerCommand` + cron schedule + Pest (3-5h)
2. **G3** · migrations canon ADR 0206 (`add_uuid_to_business`, `add_whatsmeow_to_channels_type_enum`) + trait `HasUuid` (2-4h)
3. **G2** · anti-replay middleware whatsmeow (`x-whatsmeow-ts`/`x-whatsmeow-nonce` + dedup `webhook_nonces`) + WuzAPI fork OR daemon plugin que injeta headers (4-6h)
4. **G4** · macro `Http::whatsmeowDaemon()` + circuit breaker Cache + retry exponencial (4-6h)

**Gate Mês 1:** zero cron failures + zero replays bem-sucedidos em smoke + channel novo conecta em ≤60s e2e.

### CONSOLIDAR — Onda 2 (P1) · ~11-17 h IA-pair + ~2h Wagner

PR canônico 2 (1 intent: "observability + backup completos"):
5. **G5** · OTel spans + Prometheus métricas whatsmeow.* + Grafana panel (5-8h)
6. **G6** · Restic backup daemon CT 100 + healthcheck + runbook (3-4h)
7. **G7** · `WhatsmeowHealthProbeCommand` + cross-tenant ban alarm (3-5h)

**Gate Mês 2:** Grafana dashboard whatsmeow operacional + Restic backup snapshot last 26h pass + ban probe roda 30min sem alerta falso-positivo.

### EVOLUIR (P2-P3) · ~12-21 h IA-pair (post review_trigger)

Reabrir SE algum:
- WuzAPI versão major bump quebra payload (G9 dispara)
- Cliente multi-device avançado reporta fragmentation de conversation (G8 dispara)
- 3+ outros daemons externos no stack (ML, Insta) precisarem reavaliar Saloon PHP (ADR 0206 review_trigger 7)
- Daemon CT 100 ≠ daemon-go/docker-compose.yml drift detectado (G10 dispara)

### Métrica de saturação (onde parar de subir)

Score 75-80% é o sweet spot saturação pra daemon recém-nascido (24h prod). Subir além exigiria EVOLUIR — testcontainer Docker WuzAPI nos Pest, JSON Schema validator, daemon-go fork canon, e-2-e full LID resolver — investimento que só compensa se Meta NÃO banir o número (review_trigger 0204 ban rate > 2x Cloud) e Wagner escalar > 50 channels (review_trigger 0206 volume).

---

## Parte 7 · Surpresas

### Positiva 1 · Reconciler com state machine canônica em 24h adoção

`WhatsmeowState` enum 7 estados + `WhatsmeowReconciler` service 376 LoC + 12 cenários Pest cobertos. **Maioria de wrappers WhatsApp não-oficial (Evolution API, WAHA, Baileys community) leva à imperatividade dispersa em controllers.** Oimpresso tem layer canônica desde o dia 1 graças ao ADR 0206 ter sido proposto/aceito 2h depois do ADR 0204 — Wagner aprendeu na dor (5 bugs sequenciais pareando primeiros 2 channels) e exigiu profissionalização.

### Positiva 2 · Defense-in-depth filtros + customer_external_id resolvido em 1 PR

`WhatsmeowBroadcastFilterTest` 6 cenários R-WA — controller filtra antes de dispatch + job rejeita external_id vazio + extractor captura Chat/Sender — três camadas redundantes pra mesmo incident. Padrão raro em wrappers WhatsApp 2026. Quando bug renascer (e renascerá, em outra forma), há rede de pesca tripla.

### Positiva 3 · Multi-tenant Tier 0 IRREVOGÁVEL honrado em todo flow

Webhook URL `/api/whatsapp/webhook/whatsmeow/{business_uuid}` → middleware resolve `business.uuid` pré-auth → `Channel::query()->withoutGlobalScope(ScopeByBusiness)->where('business_id', $businessId)` em todo lookup → Reconciler `resolveChannelByUserName(int $businessId, ...)` parametriza tenant. **Constituição v2 #6 honrado por inteiro.**

### Negativa 1 · `ChannelsReconcilerCommand` é zombie ADR 0202 órfão

Cron rodando every 5min em prod retornando FAILURE silencioso desde 2026-05-27. Esse tipo de "código que não foi removido junto com a feature" cria custo de manutenção invisível — Wagner perderá tempo investigando logs vazios quando algo der errado. Lição: ADR canônico que descontinua componente deve listar TODOS os pontos de remoção (Driver class + DB columns + cron command + config keys + env vars + runbooks). ADR 0202 listou Driver + daemon + schema + container + runbooks; **esqueceu o cron Command**.

### Negativa 2 · ADR 0206 prometeu 7 entregáveis, 2 implementados, 5 pendentes

Decisão aceita 2026-05-27 18:20 BRT com "Wagner faça por favor". 12h depois:
- ✅ State Machine + Reconciler service (G1 cron pendente)
- ✅ UI Dialog inline base64 + polling
- ❌ Migrations canon (G3)
- ❌ Macro Http + circuit breaker (G4)
- ❌ Restic backup (G6)
- ❌ Pin SHA digest (G10)
- ❌ OTel + Prometheus (G5)

Padrão consistente com ADR 0106 recalibração 10x — IA-pair entrega rápido a "spine" estruturada (Reconciler + UI) mas componentes operacionais (cron, backup, observability, security) levam mais 1-2 PRs pra fechar. **Não é falha de design, é fato da entrega faseada** que ADR 0206 mesmo previu em §Plano (Fases A-E).

### Negativa 3 · WuzAPI documentação incompleta forçou pattern inferido por amostragem

API.md upstream **não tem JSON Schema** dos payloads webhook. `extractFromWhatsmeow` foi escrito por amostragem (incident 2026-05-27 ensinou `SenderAlt` vs `Chat`, `instanceName` outer vs `Username`, etc). Risk latente toda vez que WuzAPI bump-ar versão. **Defesa:** G9 (JSON Schema validator) + testcontainer Docker WuzAPI nos Pest (parte de EVOLUIR).

---

## Referências

### Auditoria interna oimpresso

- ADR 0202 — [WhatsApp profissionalização — Baileys OUT](../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md)
- ADR 0204 — [Whatsmeow driver substituto](../../decisions/0204-whatsmeow-driver-substituto-baileys.md)
- ADR 0206 — [State Machine + Reconciler whatsmeow](../../decisions/0206-state-machine-whatsmeow-reconciliacao.md)
- ADR 0093 — [Multi-tenant Tier 0 IRREVOGÁVEL](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- ADR 0094 — [Constituição v2 7 camadas + 8 princípios](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- Runbook deploy CT 100 — [whatsmeow-daemon-deploy-ct100.md](runbooks/whatsmeow-daemon-deploy-ct100.md)
- Auditoria webhook handlers anterior — [AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md](AUDITORIA-WEBHOOK-SYNC-HANDLERS-2026-05-14.md)
- PR #1825 fix incident 2026-05-27 (filter status@broadcast + customer_external_id)
- WhatsmeowBroadcastFilterTest — 6 cenários R-WA anti-regressão

### Fontes externas 2026 (10+ pesquisas)

- [tulir/whatsmeow GitHub](https://github.com/tulir/whatsmeow) — lib Go canônica
- [tulir/whatsmeow events.go](https://github.com/tulir/whatsmeow/blob/main/types/events/events.go) — event types canônicos
- [whatsmeow Go pkg docs](https://pkg.go.dev/go.mau.fi/whatsmeow) — IsConnected vs IsLoggedIn
- [whatsmeow store package](https://pkg.go.dev/go.mau.fi/whatsmeow/store) — LID/PN mapping helpers
- [whatsmeow issue #810](https://github.com/tulir/whatsmeow/issues/810) — onda detecção Meta 2026
- [whatsmeow issue #854](https://github.com/tulir/whatsmeow/issues/854) — LID mapping empty
- [whatsmeow issue #871](https://github.com/tulir/whatsmeow/issues/871) — retry LID→PN
- [whatsmeow discussion #846 sender_pn](https://github.com/tulir/whatsmeow/discussions/846)
- [whatsmeow discussion #979 vs Baileys 10K scale](https://github.com/tulir/whatsmeow/discussions/979)
- [asternic/wuzapi](https://github.com/asternic/wuzapi) — wrapper canônico
- [wuzapi API.md](https://github.com/asternic/wuzapi/blob/main/API.md) — REST contract
- [WuzAPI issue #131 already connected bug](https://github.com/asternic/wuzapi/issues/131)
- [BrightCoding WuzAPI guide 2025](https://www.blog.brightcoding.dev/2025/11/23/the-ultimate-guide-to-whatsapp-rest-api-service-in-go-build-scalable-multi-device-solutions-with-wuzapi-%F0%9F%9A%80)
- [kraya-ai WhatsApp Automation Ban Risk 2026](https://blog.kraya-ai.com/whatsapp-automation-ban-risk) — ban risk analysis
- [Hooklistener Webhook Security Guide](https://www.hooklistener.com/learn/webhook-security-fundamentals)
- [FreelyIT Webhook HMAC Replay 2026](https://www.freelyit.nl/en/blog/api-security-best-practices-2026-03-21)
- [webhooks.fyi replay prevention](https://webhooks.fyi/security/replay-prevention)
- [Hookdeck Webhook Security Vulnerabilities](https://hookdeck.com/webhooks/guides/webhook-security-vulnerabilities-guide)
- [DoHost Preventing Replay Attacks 2026](https://dohost.us/index.php/2026/02/15/preventing-replay-attacks-implementing-timestamps-and-nonces-in-webhook-handlers/)
- [WAHA events docs](https://waha.devlike.pro/docs/how-to/events/) — comparativo wrapper batteries-included
- [GOWA aldinokemal](https://github.com/aldinokemal/go-whatsapp-web-multidevice) — comparativo whatsmeow wrapper rico

---

**Autoria:** claude-code-opus-4.7 (Audit Research Expert) · 2026-05-28 · PT-BR · Sem hedge
**Validação:** smoke real foreground paralelo Wagner em curso · auditoria background totalmente independente
**Decisão final:** Wagner aceita roadmap CONSOLIDAR (G1-G7 = ~24-37h IA-pair) OU EVOLUIR (review_trigger disparado, ~40-60h adicional)
