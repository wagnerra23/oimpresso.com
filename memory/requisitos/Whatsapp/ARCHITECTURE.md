---
id: requisitos-whatsapp-architecture
---

# ARCHITECTURE — Whatsapp

> Decisão mãe: [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> SPEC: [SPEC.md](SPEC.md)
> Capterra: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md)

## 1. Visão alto nível

```
┌──────────────────────────────────────────────────────────────────────┐
│                         CLIENTE FINAL (Whatsapp)                      │
└──────┬─────────────────────────────────────────────────▲─────────────┘
       │ inbound msg                                      │ outbound msg
       ▼                                                  │
┌─────────────────────────────────┐                       │
│       META CLOUD API            │                       │
│  graph.facebook.com/v21.0       │                       │
└──────┬──────────────────────────┘                       │
       │ webhook POST                                     │ HTTP POST
       │ X-Hub-Signature-256                              │ Bearer token
       ▼                                                  │
┌────────────────────────────────────────┐    ┌─────────────────────────┐
│ HOSTINGER (oimpresso.com)              │    │ CT 100 PROXMOX           │
│ ┌────────────────────────────────┐     │    │  ┌───────────────────┐   │
│ │ POST /api/whatsapp/webhook/    │     │    │  │  Horizon Worker   │   │
│ │      {business_uuid}           │     │    │  │  whatsapp queue   │   │
│ │   ↓ middleware HMAC verify     │     │    │  └────────┬──────────┘   │
│ │   ↓ enfileira Job (DB queue)   │     │    │           │              │
│ └────────────────────────────────┘     │    │  ┌────────▼──────────┐   │
│                                         │    │  │ ProcessIncoming   │   │
│ Inertia/React UI                        │    │  │ WebhookJob        │   │
│ /whatsapp/conversations  (Cockpit)      │    │  │                   │   │
│ /whatsapp/templates                     │    │  │ SendWhatsapp-     │   │
│ /whatsapp/settings                      │    │  │ MessageJob        │   │
│                                         │    │  └────────┬──────────┘   │
│ Centrifugo client (real-time inbox)     │◀───┼──────────┘              │
└─────────────────────────────────────────┘    │           │              │
                                                │  ┌────────▼──────────┐  │
                                                │  │  Centrifugo       │  │
                                                │  │  channel          │  │
                                                │  │  whatsapp:        │  │
                                                │  │  business:{id}    │  │
                                                │  └───────────────────┘  │
                                                └─────────────────────────┘
                ┌──────────────────────────────┐
                │  MySQL (Hostinger primary)   │
                │  - whatsapp_business_configs │
                │  - whatsapp_conversations    │
                │  - whatsapp_messages         │
                │  - whatsapp_templates        │
                │  - whatsapp_metricas         │
                └──────────────────────────────┘
```

**Princípios estruturais (pós ADR 0202 2026-05-27):**
- **Hostinger** — UI + webhook receiver + DB primary (HTTP-only, sem daemons — ADR 0062)
- **CT 100** — Horizon worker + Centrifugo (daemon-land, ADR 0058). Container `whatsapp-baileys` REMOVIDO 2026-05-27 (ADR 0202 supersede 0096 emenda 4).
- **NÃO** roda Evolution API (PROIBIDO permanente — emenda 4: bans recorrentes em produção Wagner + schema não atende + falta de observabilidade).
- **NÃO** roda BaileysDriver custom (DESCONTINUADO 2026-05-27 — ADR 0202: instabilidade WhatsApp Web non-official + Wagner reportou "ninguém ativo, pode desconectar todos").
- **Driver pattern (pós ADR 0202): Meta Cloud default universal + Z-API opcional + Null CI** — `MetaCloudDriver` + `ZapiDriver` + `NullDriver`
- **Fallback obrigatório (gating duro)** — `whatsapp_business_configs` exige `meta_*` campos preenchidos quando `driver = zapi` (FormRequest cross-field validation)
- **Fallback automático** — quando `driver_health` ≥ degraded em Z-API, `DriverFactory` resolve `MetaCloudDriver` em runtime, sem intervenção
- **Multi-tenant Tier 0** — `business_id` global scope + webhook URL com slug + tokens cifrados (ADR 0093)

## 2. Schema de banco

### 2.1 `whatsapp_business_configs`

1 row por business com Whatsapp ativo. Campos por driver são nullable — só os do driver escolhido são preenchidos.

```sql
CREATE TABLE whatsapp_business_configs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  business_uuid CHAR(36) NOT NULL UNIQUE COMMENT 'usado no webhook URL',
  driver VARCHAR(20) NOT NULL DEFAULT 'meta_cloud' COMMENT 'zapi|meta_cloud|null — baileys descontinuado ADR 0202, evolution PROIBIDO permanente',
  fallback_driver VARCHAR(20) NOT NULL DEFAULT 'meta_cloud' COMMENT 'OBRIGATÓRIO quando driver=zapi (gating FormRequest). Baileys removido ADR 0202.',
  display_phone VARCHAR(20) NULL COMMENT '+5511987654321 (preenchido após primeiro ping bem-sucedido)',

  -- Meta Cloud API (default universal ADR 0202)
  meta_phone_number_id VARCHAR(64) NULL COMMENT 'ID Meta do número (≠ telefone)',
  meta_access_token TEXT NULL COMMENT 'cifrado Laravel encrypted cast (Bearer Meta)',
  meta_app_secret TEXT NULL COMMENT 'cifrado — usado pra HMAC webhook',
  meta_webhook_verify_token VARCHAR(64) NULL COMMENT 'random 32 bytes',

  -- Z-API (opcional fallback ADR 0202)
  zapi_instance_id VARCHAR(64) NULL,
  zapi_instance_token TEXT NULL COMMENT 'cifrado',
  zapi_client_token TEXT NULL COMMENT 'cifrado — header Client-Token + valida webhook',

  -- BaileysDriver columns REMOVIDAS 2026-05-27 (ADR 0202) — migration
  -- 2026_05_28_000001_drop_baileys_columns_from_whatsapp_business_configs.php.
  -- Histórico pré-removal: baileys_instance_id VARCHAR(64), baileys_phone_e164
  -- VARCHAR(20), baileys_verified_name VARCHAR(100), baileys_profile_pic_url
  -- VARCHAR(255), UNIQUE wbc_biz_phone_unq.

  -- LGPD acknowledgment (obrigatório quando driver=zapi pós ADR 0202)
  lgpd_acknowledged_at TIMESTAMP NULL,
  lgpd_acknowledged_by_user_id INT UNSIGNED NULL,

  -- Bot e templates (cross-driver)
  bot_enabled TINYINT(1) NOT NULL DEFAULT 0,
  template_repair_ready_name VARCHAR(64) NULL,
  template_repair_waiting_parts_name VARCHAR(64) NULL,
  template_billing_due_name VARCHAR(64) NULL,
  template_billing_paid_name VARCHAR(64) NULL,

  -- Health
  driver_health ENUM('healthy','degraded','disconnected','banned','never_checked') NOT NULL DEFAULT 'never_checked',
  driver_health_consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
  last_health_check_at TIMESTAMP NULL,
  last_health_message TEXT NULL COMMENT 'última mensagem de erro do ping',

  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_business_id (business_id),
  INDEX idx_driver_health (driver, driver_health),
  CONSTRAINT fk_wbc_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

**Validações de FormRequest (cross-field, gating duro):**
- `driver=zapi` (default) → exige `zapi_*` preenchidos **E** `meta_*` preenchidos como fallback (ban Z-API joga pra Meta) **E** `lgpd_acknowledged_at` not null
- `driver=meta_cloud` → exige `meta_*` preenchidos. Z-API opcional (pode ficar dormente)
- `driver=baileys` → DESCONTINUADO 2026-05-27 (ADR 0202). FormRequest rejeita 422 (`forbidden_drivers` config). Tenants legacy migram pra Meta Cloud em Fase 2/3.
- `driver=evolution` → **422 ValidationException** ("Driver Evolution proibido por ADR 0096 emenda 4 — bans em produção, schema não atende, falta observabilidade")
- `fallback_driver=evolution` → **422 ValidationException** (mesmo motivo)

### 2.2 `whatsapp_conversations`

1 conversa = 1 par (business + contact). Janela 24h Meta tracked aqui.

```sql
CREATE TABLE whatsapp_conversations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  contact_id INT UNSIGNED NULL COMMENT 'contacts.id, NULL se provisional',
  customer_phone VARCHAR(20) NOT NULL COMMENT 'normalizado +5511987654321',
  status ENUM('open','awaiting_human','resolved','archived') NOT NULL DEFAULT 'open',
  assigned_user_id INT UNSIGNED NULL COMMENT 'users.id atendente',
  bot_handling TINYINT(1) NOT NULL DEFAULT 0,
  last_inbound_at TIMESTAMP NULL COMMENT 'última msg cliente — usado pra janela 24h',
  last_outbound_at TIMESTAMP NULL,
  last_message_at TIMESTAMP NULL COMMENT 'maior(in,out) — sort lista',
  unread_count INT UNSIGNED NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uniq_biz_phone (business_id, customer_phone),
  INDEX idx_biz_last_msg (business_id, last_message_at DESC),
  INDEX idx_biz_status (business_id, status),
  CONSTRAINT fk_wc_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
  CONSTRAINT fk_wc_contact FOREIGN KEY (contact_id) REFERENCES contacts(id) ON DELETE SET NULL,
  CONSTRAINT fk_wc_user FOREIGN KEY (assigned_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

### 2.3 `whatsapp_messages`

**Append-only.** Cada mensagem (in/out) é 1 row imutável.

```sql
CREATE TABLE whatsapp_messages (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  conversation_id BIGINT UNSIGNED NOT NULL,
  direction ENUM('inbound','outbound') NOT NULL,
  provider_message_id VARCHAR(128) NULL COMMENT 'wamid.XYZ (Meta) ou messageId (Z-API/Evolution) — UNIQUE quando preenchido',
  provider VARCHAR(20) NOT NULL COMMENT 'zapi|meta_cloud|null — provider=baileys preservado como histórico imutável (ADR 0202, evolution PROIBIDO permanente)',
  type ENUM('text','template','image','document','audio','interactive','location','contacts') NOT NULL DEFAULT 'text',
  template_name VARCHAR(64) NULL,
  body TEXT NULL,
  payload JSON NULL COMMENT 'raw Meta payload (auditoria)',
  status ENUM('queued','sent','delivered','read','failed','received') NOT NULL,
  failed_reason VARCHAR(255) NULL,
  sender_user_id INT UNSIGNED NULL COMMENT 'só outbound humano',
  sender_kind ENUM('human','bot','system') NULL COMMENT 'só outbound',
  cost_centavos INT UNSIGNED NULL COMMENT 'custo Meta da conversa (1ª msg da janela)',
  created_at TIMESTAMP NOT NULL,
  updated_at TIMESTAMP NULL COMMENT 'só pra status updates do mesmo wamid',
  UNIQUE KEY uniq_provider_message (provider_message_id),
  INDEX idx_biz_conv_created (business_id, conversation_id, created_at DESC),
  INDEX idx_biz_status (business_id, status, created_at),
  CONSTRAINT fk_wm_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_conversation FOREIGN KEY (conversation_id) REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_sender_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Append-only enforcement:** trigger MySQL bloqueia UPDATE em colunas-chave (body, direction, provider, provider_message_id, conversation_id). Updates permitidos só em `status`/`failed_reason`/`updated_at` (status delivery flow). Padrão Ponto Marcacoes.

### 2.4 `whatsapp_templates`

Espelho local dos templates. Para Meta Cloud, são HSM aprovados na Meta Business Manager. Para Z-API/Evolution, são templates locais (texto + placeholders) usados como freeform mensagens.

```sql
CREATE TABLE whatsapp_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  provider VARCHAR(20) NOT NULL DEFAULT 'zapi' COMMENT 'zapi (local templates) | meta_cloud (HSM)',
  meta_template_id VARCHAR(64) NULL COMMENT 'só pra provider=meta_cloud',
  name VARCHAR(64) NOT NULL,
  language VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
  category ENUM('UTILITY','MARKETING','AUTHENTICATION') NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','PAUSED','DISABLED','LOCAL') NOT NULL COMMENT 'LOCAL = template Z-API/Evolution sempre disponível',
  components JSON NOT NULL COMMENT 'estrutura header/body/footer/buttons',
  rejection_reason VARCHAR(255) NULL,
  last_synced_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uniq_biz_provider_name_lang (business_id, provider, name, language),
  INDEX idx_biz_status (business_id, status),
  CONSTRAINT fk_wt_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

**Comportamento por driver:**
- **Z-API (default):** templates são `LOCAL` (sempre `status=LOCAL`); driver expande placeholders e manda como freeform. Sem janela 24h restritiva.
- **Meta Cloud (fallback):** templates sincronizados via `MetaCloudDriver::fetchTemplates()`; status reflete aprovação Meta. Outbound fora janela 24h **exige** template aprovado.
- **Cross-driver:** template criado como `LOCAL` (Z-API) deve ter contraparte HSM aprovada na Meta (mesmo nome, mesmas variáveis) pra fallback funcionar — UI alerta se houver template Z-API sem contraparte Meta.

### 2.5 `whatsapp_conversation_metricas`

Agregação diária (job 04:00 BRT). Padrão `copiloto_memoria_metricas` (ADR 0049).

```sql
CREATE TABLE whatsapp_conversation_metricas (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  dia DATE NOT NULL,
  total_conversas INT UNSIGNED NOT NULL DEFAULT 0,
  conversas_iniciadas_negocio INT UNSIGNED NOT NULL DEFAULT 0,
  conversas_iniciadas_cliente INT UNSIGNED NOT NULL DEFAULT 0,
  total_mensagens_inbound INT UNSIGNED NOT NULL DEFAULT 0,
  total_mensagens_outbound INT UNSIGNED NOT NULL DEFAULT 0,
  custo_centavos_utility INT UNSIGNED NOT NULL DEFAULT 0,
  custo_centavos_marketing INT UNSIGNED NOT NULL DEFAULT 0,
  custo_centavos_authentication INT UNSIGNED NOT NULL DEFAULT 0,
  tempo_resposta_p50_segundos INT UNSIGNED NULL,
  tempo_resposta_p95_segundos INT UNSIGNED NULL,
  deflection_pct DECIMAL(5,2) NULL COMMENT '% conversas resolvidas só por bot',
  created_at TIMESTAMP NULL,
  UNIQUE KEY uniq_biz_dia (business_id, dia),
  CONSTRAINT fk_wcm_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

## 3. Fluxos críticos

### 3.1 Outbound — Repair status `ready` → cliente recebe Whatsapp

```
1. Operador clica "Marcar pronto" em /repair/{id}/status
2. Modules\Repair\Events\RepairStatusChanged disparado (já existe)
3. Modules\Whatsapp\Listeners\NotifyRepairCustomer escuta
   ↓ guard: WhatsappBusinessConfig existe? cliente tem mobile?
4. SendWhatsappMessageJob::dispatch($businessId, $contact->mobile,
                                     'repair_status_ready', ['name' => ..., 'os_id' => ...])
5. Job na queue 'whatsapp' (Horizon CT 100 worker)
6. Worker resolve WhatsappBusinessConfig::where('business_id', $businessId)
7. Cria WhatsappMessage status=queued
8. MetaCloudDriver::sendTemplate() → POST graph.facebook.com
9. Atualiza status=sent + meta_message_id
10. Evento WhatsappMessageSent disparado
11. Centrifugo publish whatsapp:business:{id} pra UI atualizar (se aberta)
```

### 3.2 Inbound — cliente responde, Jana atende, escala humano

```
1. Cliente envia "obrigado, mas o orçamento daquele banner?"
2. Meta POST https://oimpresso.com/api/whatsapp/webhook/{biz_uuid}
3. VerifyMetaSignature middleware: HMAC OK
4. WebhookController@handle:
   - retorna 200 imediato
   - dispatch ProcessIncomingWebhookJob (DB queue → CT 100 picks up)
5. Job:
   - resolve business pelo {biz_uuid}
   - upsert whatsapp_messages direction=inbound (UNIQUE meta_message_id)
   - upsert whatsapp_conversations (last_inbound_at = now, unread_count++)
   - dispatch evento WhatsappMessageReceived
   - Centrifugo publish (UI inbox abre badge)
6. Listener DispatchToJanaBot (se bot_enabled):
   - decide('whatsapp', 'reply', {message, conversation, business_id})
   - PolicyEngine retorna ALLOW_BRAIN_A
   - Brain A (gpt-4o-mini) com ContextoNegocio (tem orçamento Repair pendente?)
   - resposta: "Boa! O orçamento do banner é R$ [redacted Tier 0] te mando o boleto?"
   - SendWhatsappMessageJob (sender_kind=bot)
7. Cliente responde "manda"
8. PolicyEngine REQUIRE_HUMAN_REVIEW (intent=closing_deal sensitivity)
   - whatsapp_conversations.status = awaiting_human
   - Centrifugo notifica atendentes online
9. Larissa pega conversa via UI, atribui a si (assigned_user_id)
10. Larissa envia boleto+NFe (mídia outbound) — Sprint 2.5
```

## 4. Jobs (queue `whatsapp` — Horizon tag `whatsapp`)

| Job | Trigger | Retry | Tries | Backoff (s) |
|---|---|---|---|---|
| `SendWhatsappMessageJob` | Listener / UI | exponencial | 5 | 60, 300, 900, 3600, 86400 |
| `ProcessIncomingWebhookJob` | Webhook (qualquer driver) | linear | 3 | 30, 90, 270 |
| `SyncMessageStatusJob` | Webhook status update | linear | 3 | 10, 30, 90 |
| `SyncTemplatesJob` | UI button + diário 06:00 (só driver=meta_cloud) | linear | 3 | 60, 180, 600 |
| `AggregateMetricasJob` | Scheduler 04:00 | none | 1 | — |
| `WhatsappDriverHealthCheckJob` | Scheduler a cada 6h (só `driver=zapi`; meta_cloud é oficial e null não precisa) | linear | 1 | — |

Todos com `$businessId` no constructor (regra Tier 0 multi-tenant). `SendWhatsappMessageJob` resolve driver via `DriverFactory::make($business)` em runtime — se Z-API ficou degraded entre dispatch e handle, usa Meta Cloud automaticamente.

## 5. Eventos

| Evento | Disparado por | Listeners |
|---|---|---|
| `WhatsappMessageQueued` | `SendWhatsappMessageJob::handle()` start | `LogConversation`, OTel `whatsapp.message.queued` |
| `WhatsappMessageSent` | Driver retornou sucesso | `LogConversation`, OTel `whatsapp.message.sent` |
| `WhatsappMessageFailed` | Driver hard error 4xx | `LogConversation`, `AlertAdmin` (Sentry-like) |
| `WhatsappMessageReceived` | `ProcessIncomingWebhookJob` | `DispatchToJanaBot`, `LogConversation`, OTel |
| `WhatsappStatusUpdated` | Webhook status (delivered/read) | `LogConversation` |
| `WhatsappConversationAssigned` | UI atribuição manual | OTel + Centrifugo |
| `WhatsappDriverSessionLost` | `ZapiDriver` auth fail (401 sessão Whatsapp Web caiu) | `WhatsappDriverHealthCheck` (forçar check imediato) |
| `WhatsappDriverFallbackActivated` | `WhatsappDriverHealthCheckJob` troca Z-API → Meta Cloud | `NotifyAdminBusiness`, `LogConversation`, OTel `whatsapp.driver.fallback` |
| `WhatsappDriverBanDetected` | Health check detecta ban Z-API permanente | `NotifyAdminBusiness`, `NotifyOimpressoOps` (Wagner), OTel |

## 6. Middlewares

- **`VerifyZapiSignature`** — header `Client-Token` timing-safe compare (rota `/api/whatsapp/webhook/zapi/{uuid}`); 401 se falha
- **`VerifyMetaSignature`** — HMAC SHA-256 + `app_secret` business (rota `/api/whatsapp/webhook/meta/{uuid}`); 401 se falha
- **`SetWhatsappBusinessConfig`** (UI rotas) — resolve config do `auth()->user()->business_id`; 404 se não existe
- Stack admin padrão UltimatePOS: `['web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin']`

## 7. Pages Inertia/React (AppShellV2)

| Rota | Page | Layout |
|---|---|---|
| `/whatsapp/conversations` | `Pages/Whatsapp/Conversations/Index.tsx` | AppShellV2 + Cockpit (lista esquerda + chat painel direita) |
| `/whatsapp/conversations/{id}` | `Pages/Whatsapp/Conversations/Show.tsx` | idem |
| `/whatsapp/templates` | `Pages/Whatsapp/Templates/Index.tsx` | AppShellV2 + DataTable |
| `/whatsapp/settings` | `Pages/Whatsapp/Settings.tsx` | AppShellV2 + Form |

Real-time: hook `useCentrifugoChannel(`whatsapp:business:${businessId}`)` em `Conversations/Index` e `Show`.

## 8. Topnav (`Modules/Whatsapp/Resources/menus/topnav.php`)

```php
return [
  ['label' => 'Conversas', 'route' => 'whatsapp.conversations.index', 'permission' => 'whatsapp.access'],
  ['label' => 'Templates', 'route' => 'whatsapp.templates.index', 'permission' => 'whatsapp.templates.manage'],
  ['label' => 'Configurações', 'route' => 'whatsapp.settings', 'permission' => 'whatsapp.settings.manage'],
];
```

## 9. Permissões Spatie

```
whatsapp.access                → Conversas inbox
whatsapp.send                  → Enviar manual UI
whatsapp.templates.manage      → CRUD templates
whatsapp.settings.manage       → Config número/token
whatsapp.assign                → Atribuir conversa atendente
whatsapp.metricas.view         → Dashboard métricas
```

## 10. Configuração `config/whatsapp.php`

> Espelha `Modules/Whatsapp/Config/config.php` pós ADR 0202 (2026-05-27).

```php
return [
    'default_driver' => env('WHATSAPP_DEFAULT_DRIVER', 'meta_cloud'),
    // valores válidos pós ADR 0202: meta_cloud (default universal) | zapi (opcional) | null (CI)
    // 'baileys' DESCONTINUADO 2026-05-27 (ADR 0202 supersede 0096 emenda 4) — entrou em forbidden_drivers
    // 'evolution' PROIBIDO permanente (ADR 0096 emenda 4)

    'zapi' => [
        'base_url' => env('WHATSAPP_ZAPI_BASE_URL', 'https://api.z-api.io'),
        'request_timeout' => env('WHATSAPP_ZAPI_TIMEOUT', 15),
    ],

    'meta' => [
        'api_version' => env('WHATSAPP_META_API_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        'request_timeout' => env('WHATSAPP_META_TIMEOUT', 10),
    ],

    // Seção 'baileys' REMOVIDA 2026-05-27 (ADR 0202)

    'health_check' => [
        'interval_seconds' => env('WHATSAPP_HEALTH_INTERVAL', 21600), // 6h
        'consecutive_failures_to_degrade' => 5,
        'consecutive_failures_to_disconnect' => 10,
        'cross_tenant_ban_alarm_threshold' => 3, // 3 businesses banidos em 24h = alarme Wagner
    ],

    'fallback' => [
        'enabled' => env('WHATSAPP_FALLBACK_ENABLED', true),
        'auto_switch_after_status' => 'degraded', // healthy|degraded|disconnected|banned
        'mandatory_for_drivers' => ['zapi'], // ADR 0202: baileys removido — só zapi exige fallback Meta
    ],

    'forbidden_drivers' => ['baileys', 'evolution', 'whatsapp_web_js'],
    // FormRequest rejeita 422 se tentar salvar driver dessa lista
    // 'baileys' ENTROU nessa lista em 2026-05-27 (ADR 0202)

    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),

    'webhook' => [
        'rate_limit_per_minute' => 600,
    ],
];
```

## 11. Segurança / LGPD

- **Tokens cifrados em DB** (Laravel `encrypted` cast — `APP_KEY`):
  - `zapi_instance_token`, `zapi_client_token`
  - `meta_access_token`, `meta_app_secret`
- Telefone cliente em logs: redacted via `App\Support\PiiRedactor` (skill `commit-discipline`)
- Webhook URL com `business_uuid` (não business_id sequencial — evita enumeração)
- Validação webhook por driver (rejeita 401 se falha):
  - Z-API: header `Client-Token` timing-safe compare com `zapi_client_token`
  - Meta: HMAC SHA-256 com `meta_app_secret`
- Mensagens (`payload` JSON) podem conter PII: retenção 90 dias; após, anonimização (`body=null, payload=null` mas mantém `id, business_id, direction, status, created_at` pra compliance fiscal)
- LGPD direito ao esquecimento: cliente pede → script `php artisan whatsapp:forget-contact {phone}` anonimiza histórico
- **LGPD com Z-API (default)**: business assina termo "ciente que Z-API é provedor não-oficial baseado em Whatsapp Web e que existe risco de bloqueio Meta. Configurei Meta Cloud como fallback pra mitigar interrupção." → registrado em `whatsapp_business_configs.lgpd_acknowledged_at` + `lgpd_acknowledged_by_user_id`. Sem termo aceito = não pode salvar `driver=zapi`.

## 12. Observabilidade

- **OTel metrics** namespace `whatsapp.*`:
  - `whatsapp.messages.sent` (counter, tags: business, template_name)
  - `whatsapp.messages.received` (counter, tags: business)
  - `whatsapp.messages.failed` (counter, tags: business, reason)
  - `whatsapp.conversations.deflection_rate` (gauge, tag: business)
  - `whatsapp.cost.centavos` (counter, tags: business, category)
- **Logs estruturados** Loki (CT 100):
  - context: business_id, conversation_id, message_id, meta_message_id
  - phone: redacted
- **Dashboard Grafana** (futuro): custo/dia × business, volume × business, deflection bot × dia

## 13. Test plan (Pest)

| Test | Tipo | Cobre |
|---|---|---|
| `MultiTenantIsolationTest` | Feature | R-WA-001, R-WA-005 |
| `WebhookSignatureTest` | Feature | R-WA-002 |
| `WebhookIdempotencyTest` | Feature | R-WA-003 |
| `WindowExpiryTest` | Feature | R-WA-004 |
| `MetaCloudDriverTest` | Unit (Http::fake) | API calls Meta |
| `NullDriverTest` | Unit | dev/CI sem rede |
| `SendWhatsappMessageJobTest` | Feature (Bus::fake) | retry + status flow |
| `ProcessIncomingWebhookJobTest` | Feature | inbound flow + idempotency |
| `NotifyRepairCustomerTest` | Feature | listener Repair |
| `DispatchToJanaBotTest` | Feature | 4 outcomes PolicyEngine |
| `MetricasAggregationTest` | Feature | rollup diário |
| `PiiRedactionTest` | Unit | PiiRedactor com telefones |

## 14. Onboarding business novo (wizard 2 passos obrigatórios)

UI Settings apresenta wizard sequencial: Z-API hoje + Meta Cloud em paralelo. Não dá pra salvar Z-API ativo sem Meta cadastrado (gating duro FormRequest).

### 14.1 Passo 1 — Z-API (5 minutos)

1. Admin business cria conta em `app.z-api.io` (cartão de crédito; R$ [redacted Tier 0]-299/mês)
2. Cria Instance → Z-API gera `instance_id`, `instance_token`, `client_token`
3. Cola na UI `/whatsapp/settings` Passo 1
4. UI mostra webhook URL `https://oimpresso.com/api/whatsapp/webhook/zapi/{biz_uuid}`
5. Cola no painel Z-API → Webhooks → URL único + Client-Token = mesmo `client_token` cadastrado
6. UI "Testar conexão" → ZapiDriver::ping retorna "Aguardando QR Code"
7. UI mostra QR Code (vem do `GET /instances/{id}/token/{token}/qr-code/image`)
8. Admin escaneia com Whatsapp do business
9. UI atualiza "Conectado, número +5511..." → ✅
10. **Wizard NÃO conclui ainda** — exige Passo 2 Meta Cloud cadastrado

### 14.2 Passo 2 — Meta Cloud (1-3 dias paralelo)

Pode rodar em paralelo ao Passo 1. Bloqueia salvar config completa.

1. Admin business entra em `business.facebook.com`
2. Cria/conecta Whatsapp Business Account
3. Verifica número (recebe SMS Meta)
4. Cria System User → gera access_token eternal (recomendado vs token 60d)
5. Pega `phone_number_id`, `access_token`, `app_secret` (em Meta Apps)
6. Cola na UI `/whatsapp/settings` Passo 2
7. UI mostra webhook URL `https://oimpresso.com/api/whatsapp/webhook/meta/{biz_uuid}`
8. Cola na Meta App → Webhooks → Whatsapp → URL + verify_token
9. Meta faz challenge GET → oimpresso retorna `hub.challenge` → ✅
10. Subscribe field `messages` + `message_template_status_update` na Meta App
11. UI "Testar conexão" → MetaCloudDriver::ping → ✅
12. Cadastra HSM templates na Meta Business Manager (1-3 dias aprovação cada)

### 14.3 Passo final — Aceite LGPD

Modal aparece ao salvar config completa com Z-API ativo:

> "Estou ciente que **Z-API é provedor não-oficial** baseado em Whatsapp Web
> e que **existe risco de bloqueio Meta**. Configurei Meta Cloud como fallback
> pra mitigar interrupção do meu serviço."

Aceite registra `lgpd_acknowledged_at` + `lgpd_acknowledged_by_user_id`. Sem aceite = não salva.

### 14.4 Driver Evolution — REMOVIDO (PROIBIDO Tier 0)

Anteriormente proposto como Sprint 2 self-host CT 100. **Removido em 2026-05-07 (emenda 3 ADR 0096).** Reabrir só via nova ADR explícita.

## 15. Referências externas

### Meta Cloud API
- Cloud API docs: `developers.facebook.com/docs/whatsapp/cloud-api`
- Pricing: `developers.facebook.com/docs/whatsapp/pricing`
- HMAC verify: `developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads`
- HSM templates: `developers.facebook.com/docs/whatsapp/business-management-api/message-templates`

### Z-API
- Docs API REST: `developer.z-api.io`
- Painel: `app.z-api.io`
- Endpoints chave:
  - `POST /instances/{id}/token/{token}/send-text`
  - `POST /instances/{id}/token/{token}/send-image`
  - `GET /instances/{id}/token/{token}/status`
  - `GET /instances/{id}/token/{token}/qr-code/image`
  - Webhooks: `on-message`, `on-message-status`, `on-presence-status`, `on-disconnected`

### Evolution API — PROIBIDO permanente (não usar)

Driver removido por emendas 3 e 4 ADR 0096 (2026-05-07). Não há referência operacional.

### Baileys (lib raiz) — Sprint 3

- GitHub: `github.com/WhiskeySockets/Baileys`
- Pacote NPM: `@whiskeysockets/baileys`
- Endpoints conceituais (wrapper HTTP REST nosso, não Baileys nativo):
  - `POST /instances/{id}/text`
  - `POST /instances/{id}/media`
  - `GET /instances/{id}/status`
  - `GET /instances/{id}/qr`
- Plano detalhado em §16 abaixo.

### Infraestrutura
- Centrifugo channels: `centrifugal.dev/docs/server/channels`
- Traefik labels: `doc.traefik.io/traefik/routing/providers/docker/`
- Padrão CT 100 Docker compose-managed: `memory/requisitos/Infra/RUNBOOK-criar-modulo.md` + skill `proxmox-docker-host`

## 16. ~~Sprint 3 — BaileysDriver custom~~ [DEPRECATED 2026-05-27]

> **DEPRECATED por [ADR 0202](../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).**
> Supersede ADR 0096 emenda 4. Conteúdo abaixo preservado como lição histórica.
> **NÃO implementar.** Direção pós 2026-05-27: Meta Cloud API default universal +
> Z-API opcional fallback per-tenant. Daemon CT 100 já descomissionado.

> Autorizado originalmente em 2026-05-07 por Wagner ([ADR 0096 emenda 4](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)).
> Esta seção foi o **plano de referência** pré-2026-05-27 — agora **arquivado**.

### 16.1 Por que existir (recap razões Wagner)

1. **Evolution está banindo números reais** em produção do Wagner — experiência, não especulação
2. **Schema de banco do Evolution não atende** a estrutura customizada de atendimento que Wagner quer construir
3. **Falta de observabilidade** — Wagner sentiu na pele a opacidade quando bans aconteceram no Evolution
4. **Wagner ciente do custo** — "vai ter código extra por essa decisão"

### 16.2 Topologia

```
┌──────────────────────────────────────────────────────────┐
│ HOSTINGER (oimpresso.com)                                 │
│  PHP BaileysDriver                                        │
│   ↓ Http::baseUrl + Bearer token + IP whitelist           │
└──────────────────┬───────────────────────────────────────┘
                   │ HTTPS (Traefik internal)
                   ▼
┌──────────────────────────────────────────────────────────┐
│ CT 100 PROXMOX                                            │
│  Container Docker `whatsapp-baileys` (compose-managed)    │
│  ┌────────────────────────────────────────────────────┐   │
│  │  Node.js daemon (Fastify ou Hono)                  │   │
│  │  - lib @whiskeysockets/baileys (versão pinned)     │   │
│  │  - 1 socket Whatsapp Web por instance              │   │
│  │  - persistência auth state em volume               │   │
│  │  - OTel SDK Node → Loki/Grafana CT 100             │   │
│  │  - webhook outbound pro Hostinger                  │   │
│  └────────────────────────────────────────────────────┘   │
│  Volume: /srv/docker/whatsapp-baileys/sessions/{instance} │
│  Traefik: whatsapp-baileys.oimpresso.local                │
└──────────────────────────────────────────────────────────┘
```

### 16.3 Componente Node (daemon)

**Repositório:** `Modules/Whatsapp/daemon-node/` (mesmo repo, separação por dir) OU
repo separado `oimpresso/whatsapp-baileys-daemon` (decidir Sprint 3).

**Stack:**
- Node 20 LTS
- `@whiskeysockets/baileys` versão pinned (não `latest` — Meta TOS muda, lib quebra)
- Fastify ou Hono (HTTP framework — leve)
- `@opentelemetry/sdk-node` pra traces + métricas
- TypeScript estrito

**Endpoints REST** (consumidos só pelo PHP BaileysDriver, IP whitelisted):

```
GET  /health
  → { instances: [{ id, state, last_seen, lag_ms }] }

POST /instances/{instance_id}/connect
  → { qr: "data:image/png;base64,..." } se ainda não pareado
  → { connected: true, display_phone: "+55..." } se pareado

POST /instances/{instance_id}/text
  body: { to, text }
  → { message_id, status: "sent" | "queued" | "failed" }

POST /instances/{instance_id}/media
  body: { to, media_url, type, caption? }
  → { message_id, status }

GET  /instances/{instance_id}/status
  → { state: "connected"|"qr_required"|"disconnected"|"banned",
      display_phone, last_seen, session_age_seconds }

POST /instances/{instance_id}/disconnect
  → { ok: true }
```

**Webhook outbound (daemon → oimpresso PHP):**

```
POST https://oimpresso.com/api/whatsapp/webhook/baileys/{business_uuid}
  Header: Authorization: Bearer {api_key}
  Body: {
    instance_id,
    event: "message" | "message_status" | "session_lost" | "ban_detected",
    data: { ... payload Baileys ... }
  }
```

### 16.4 Componente PHP (`BaileysDriver`)

```php
// Modules/Whatsapp/Services/Drivers/BaileysDriver.php
class BaileysDriver implements DriverInterface {
    public function __construct(
        private readonly HttpClient $http // pré-configurado com baseUrl + Bearer
    ) {}

    public function sendFreeform(array $config, string $to, string $body): WhatsappSendResult {
        $response = $this->http
            ->withToken($config['baileys_api_key']) // decifrado pelo cast
            ->post("/instances/{$config['baileys_instance_id']}/text", [
                'to' => $to,
                'text' => $body,
            ]);

        return match (true) {
            $response->successful() => WhatsappSendResult::ok($response->json('message_id')),
            $response->status() === 401 => WhatsappSendResult::failed(
                'baileys_unauthorized',
                $response->body(),
                sessionLost: true
            ),
            $response->json('reason') === 'ban_detected' => WhatsappSendResult::failed(
                'baileys_banned',
                'Baileys detectou ban Meta',
                banDetected: true,
            ),
            default => WhatsappSendResult::failed("baileys_{$response->status()}", $response->body()),
        };
    }
    // ... outros métodos
}
```

### 16.5 Webhook handler PHP

`POST /api/whatsapp/webhook/baileys/{business_uuid}`:

- Middleware `VerifyBaileysSignature` valida Bearer token vindo do daemon Node
  contra `whatsapp_business_configs.baileys_api_key` (decifrado pelo cast)
- Despacha `ProcessIncomingWebhookJob` com `provider='baileys'`
- Mesmo flow que webhook Z-API/Meta — PII redacted, idempotência via
  `provider_message_id`, evento `WhatsappMessageReceived`

### 16.6 Container Docker (CT 100)

```yaml
# /etc/docker-compose/services/whatsapp-baileys/docker-compose.yml
services:
  whatsapp-baileys:
    build: ./daemon-node
    container_name: whatsapp-baileys
    restart: unless-stopped
    volumes:
      - /srv/docker/whatsapp-baileys/sessions:/app/sessions
    environment:
      - NODE_ENV=production
      - LOG_LEVEL=info
      - OTEL_EXPORTER_OTLP_ENDPOINT=http://loki-otel:4318
      - WEBHOOK_BASE_URL=https://oimpresso.com/api/whatsapp/webhook/baileys
      - API_KEY_FILE=/run/secrets/whatsapp_baileys_api_key
    secrets:
      - whatsapp_baileys_api_key
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.baileys.rule=Host(`whatsapp-baileys.oimpresso.local`)"
      - "traefik.http.routers.baileys.tls=true"
      - "traefik.http.middlewares.baileys-ip.ipwhitelist.sourcerange=148.135.133.115"
      - "traefik.http.routers.baileys.middlewares=baileys-ip"

secrets:
  whatsapp_baileys_api_key:
    external: true
```

### 16.7 Observabilidade (a "dor" que justifica o código extra)

**OTel traces ponta-a-ponta:**
- Span 1: `oimpresso.php.SendWhatsappMessageJob.handle` (Hostinger)
- Span 2: `oimpresso.php.BaileysDriver.sendFreeform` (Hostinger)
- Span 3: `whatsapp-baileys.daemon.send` (CT 100 Node)
- Span 4: `whatsapp-baileys.baileys.sendMessage` (CT 100 Node, dentro Baileys)

**Métricas Prometheus exportadas pelo daemon:**

| Métrica | Tipo | Tags | Significado |
|---|---|---|---|
| `whatsapp_baileys_session_state` | gauge | `business_id`, `state` | 1 = conectado, 0 = caído |
| `whatsapp_baileys_message_lag_ms` | histogram | `business_id` | tempo daemon → Whatsapp Web → ack |
| `whatsapp_baileys_send_total` | counter | `business_id`, `status` | sent / failed / banned |
| `whatsapp_baileys_recv_total` | counter | `business_id` | mensagens inbound recebidas |
| `whatsapp_baileys_ban_detected_total` | counter | `business_id` | acumulado bans (alarme cross-tenant) |
| `whatsapp_baileys_session_age_seconds` | gauge | `business_id` | idade da sessão Whatsapp Web atual |

**Dashboard Grafana dedicado** `whatsapp-baileys-daemon`:
- Estado de sessão de todas instances
- P50/P95 message lag
- Taxa de envio/recebimento
- Bans nas últimas 24h por business
- Restarts container nas últimas 24h
- Idade média da sessão

**Alarmes:**
- Sessão caída > 5 min em business ativo → email + Centrifugo UI
- Lag p95 > 2s sustained 5min → alerta perf
- Container restart > 1×/h → alerta infra
- Ban detectado em 3+ businesses em 24h → alarme cross-tenant Wagner

### 16.8 Fallback automático para Meta Cloud

Mesmo flow do Z-API:
- `WhatsappDriverHealthCheckJob` chama `BaileysDriver::ping()` (que chama `GET /instances/{id}/status` no daemon)
- 5 falhas consecutivas → `driver_health = degraded` → `DriverFactory` resolve `MetaCloudDriver` em runtime
- Histórico mensagens preservado (DB Hostinger é independente)

### 16.9 Runbooks Sprint 3

- `runbooks/baileys-daemon-deploy-ct100.md` — deploy inicial do container
- `runbooks/baileys-troubleshoot-ban.md` — passo-a-passo recuperação
- `runbooks/baileys-upgrade-lib.md` — atualizar versão Baileys com cuidado
- `runbooks/baileys-add-instance.md` — onboarding novo business no daemon

### 16.10 Riscos específicos do BaileysDriver custom

1. **Mudança Meta TOS quebra Baileys** — patch comunidade demora dias-semanas. Mitigação: versão pinned + dashboard alerta + fallback Meta Cloud automático.
2. **Wagner se torna mantenedor de daemon Node** — bug crítico = Wagner em 02h da manhã debugando lib JS. Mitigação: testes integração CI + canary deploy + rollback rápido via tag Docker.
3. **Memória cresce com muitas instances** — cada instance Whatsapp Web = ~80MB RAM. CT 100 com 4 GB suporta ~30-40 instances. Acima disso: scale horizontal (mais containers).
4. **Ban detectado em 1 business pode propagar pra outros** — se o IP do CT 100 ficar marcado pela Meta, todas instances novas falham. Mitigação: alarme cross-tenant + plano B "rotacionar IP CT 100".

### 16.11 Decisão futura — quando reconsiderar

- Se `whatsapp_baileys_ban_detected_total` cross-tenant ≥ 5/mês sustained → reabrir ADR pra avaliar SaaS BSP enterprise (Take Blip / Twilio).
- Se manutenção do daemon Node consumir > 4h/mês de Wagner → reabrir ADR pra avaliar deprecar BaileysDriver e voltar pra só Z-API + Meta Cloud.
