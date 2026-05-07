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

**Princípios estruturais:**
- **Hostinger** — UI + webhook receiver + DB primary (HTTP-only, sem daemons — ADR 0062)
- **CT 100** — Horizon worker + Centrifugo (daemon-land, ADR 0058)
- **Driver pattern** — `MetaCloudDriver` default + `NullDriver` dev/CI (ADR 0096)
- **Multi-tenant Tier 0** — `business_id` global scope + webhook URL com slug + access_token cifrado (ADR 0093)

## 2. Schema de banco

### 2.1 `whatsapp_business_configs`

1 row por business com Whatsapp ativo.

```sql
CREATE TABLE whatsapp_business_configs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  business_uuid CHAR(36) NOT NULL UNIQUE COMMENT 'usado no webhook URL',
  phone_number_id VARCHAR(64) NOT NULL COMMENT 'ID Meta do número (≠ telefone)',
  display_phone VARCHAR(20) NOT NULL COMMENT '+5511987654321',
  access_token TEXT NOT NULL COMMENT 'cifrado Laravel encrypted cast',
  app_secret TEXT NOT NULL COMMENT 'cifrado — usado pra HMAC webhook',
  webhook_verify_token VARCHAR(64) NOT NULL COMMENT 'random 32 bytes',
  bot_enabled TINYINT(1) NOT NULL DEFAULT 0,
  template_repair_ready_name VARCHAR(64) NULL,
  template_repair_waiting_parts_name VARCHAR(64) NULL,
  template_billing_due_name VARCHAR(64) NULL,
  template_billing_paid_name VARCHAR(64) NULL,
  driver VARCHAR(20) NOT NULL DEFAULT 'meta_cloud' COMMENT 'meta_cloud|null|twilio|blip',
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  INDEX idx_business_id (business_id),
  CONSTRAINT fk_wbc_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

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
  meta_message_id VARCHAR(128) NULL COMMENT 'wamid.XYZ — UNIQUE quando preenchido',
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
  UNIQUE KEY uniq_meta_message (meta_message_id),
  INDEX idx_biz_conv_created (business_id, conversation_id, created_at DESC),
  INDEX idx_biz_status (business_id, status, created_at),
  CONSTRAINT fk_wm_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_conversation FOREIGN KEY (conversation_id) REFERENCES whatsapp_conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_wm_sender_user FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE SET NULL
);
```

**Append-only enforcement:** trigger MySQL bloqueia UPDATE em colunas-chave (body, direction, meta_message_id, conversation_id). Updates permitidos só em `status`/`failed_reason`/`updated_at` (status delivery flow). Padrão Ponto Marcacoes.

### 2.4 `whatsapp_templates`

Espelho local dos HSM templates aprovados na Meta Business Manager.

```sql
CREATE TABLE whatsapp_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  business_id INT UNSIGNED NOT NULL,
  meta_template_id VARCHAR(64) NULL,
  name VARCHAR(64) NOT NULL,
  language VARCHAR(10) NOT NULL DEFAULT 'pt_BR',
  category ENUM('UTILITY','MARKETING','AUTHENTICATION') NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','PAUSED','DISABLED') NOT NULL,
  components JSON NOT NULL COMMENT 'estrutura header/body/footer/buttons',
  rejection_reason VARCHAR(255) NULL,
  last_synced_at TIMESTAMP NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  UNIQUE KEY uniq_biz_name_lang (business_id, name, language),
  INDEX idx_biz_status (business_id, status),
  CONSTRAINT fk_wt_business FOREIGN KEY (business_id) REFERENCES business(id) ON DELETE CASCADE
);
```

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
| `ProcessIncomingWebhookJob` | Webhook | linear | 3 | 30, 90, 270 |
| `SyncMessageStatusJob` | Webhook (Meta status update) | linear | 3 | 10, 30, 90 |
| `SyncTemplatesJob` | UI button + diário 06:00 | linear | 3 | 60, 180, 600 |
| `AggregateMetricasJob` | Scheduler 04:00 | none | 1 | — |

Todos com `$businessId` no constructor (regra Tier 0 multi-tenant).

## 5. Eventos

| Evento | Disparado por | Listeners |
|---|---|---|
| `WhatsappMessageQueued` | `SendWhatsappMessageJob::handle()` start | `LogConversation`, OTel `whatsapp.message.queued` |
| `WhatsappMessageSent` | Driver retornou sucesso | `LogConversation`, OTel `whatsapp.message.sent` |
| `WhatsappMessageFailed` | Driver hard error 4xx | `LogConversation`, `AlertAdmin` (Sentry-like) |
| `WhatsappMessageReceived` | `ProcessIncomingWebhookJob` | `DispatchToJanaBot`, `LogConversation`, OTel |
| `WhatsappStatusUpdated` | Webhook status (delivered/read) | `LogConversation` |
| `WhatsappConversationAssigned` | UI atribuição manual | OTel + Centrifugo |

## 6. Middlewares

- **`VerifyMetaSignature`** — HMAC SHA-256 + `app_secret` business; 401 se falha
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

```php
return [
    'driver' => env('WHATSAPP_DRIVER', 'meta_cloud'), // meta_cloud|null|twilio|blip
    'meta' => [
        'api_version' => env('WHATSAPP_META_API_VERSION', 'v21.0'),
        'base_url' => env('WHATSAPP_META_BASE_URL', 'https://graph.facebook.com'),
        'request_timeout' => env('WHATSAPP_META_TIMEOUT', 10),
    ],
    'queue' => env('WHATSAPP_QUEUE', 'whatsapp'),
    'webhook' => [
        'rate_limit_per_minute' => 600,
    ],
];
```

## 11. Segurança / LGPD

- `access_token` cifrado em DB (Laravel `encrypted` cast — `APP_KEY`)
- `app_secret` cifrado em DB
- Telefone cliente em logs: redacted via `App\Support\PiiRedactor` (skill `commit-discipline`)
- Webhook URL com `business_uuid` (não business_id sequencial — evita enumeração)
- HMAC SHA-256 obrigatório (rejeita 401 se falha)
- Mensagens (`payload` JSON) podem conter PII: retenção 90 dias; após, anonimização (`body=null, payload=null` mas mantém `id, business_id, direction, status, created_at` pra compliance fiscal)
- LGPD direito ao esquecimento: cliente pede → script `php artisan whatsapp:forget-contact {phone}` anonimiza histórico

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

## 14. Onboarding business novo (manual)

Operação humana (não-automatizada — Meta Business Manager exige):

1. Wagner (admin business) entra em `business.facebook.com`
2. Cria/conecta Whatsapp Business Account
3. Verifica número (recebe SMS Meta)
4. Cria System User → gera access_token eternal (recomendado vs token 60d)
5. Pega `phone_number_id`, `access_token`, `app_secret` (em Meta Apps)
6. Cola na UI `/whatsapp/settings` do oimpresso
7. UI mostra webhook URL `https://oimpresso.com/api/whatsapp/webhook/{biz_uuid}`
8. Cola na Meta App → Webhooks → Whatsapp → URL + verify_token
9. Meta faz challenge GET → oimpresso retorna `hub.challenge` → ✅
10. Subscribe field `messages` + `message_template_status_update` na Meta App
11. UI "Testar conexão" → MetaCloudDriver::ping → ✅

## 15. Referências externas

- Meta Cloud API: `developers.facebook.com/docs/whatsapp/cloud-api`
- Pricing: `developers.facebook.com/docs/whatsapp/pricing`
- HMAC verify: `developers.facebook.com/docs/messenger-platform/webhooks#validate-payloads`
- HSM templates: `developers.facebook.com/docs/whatsapp/business-management-api/message-templates`
- Centrifugo channels: `centrifugal.dev/docs/server/channels`
