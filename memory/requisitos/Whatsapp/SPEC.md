# Especificação funcional — Whatsapp

> Convenção do ID: `US-WA-NNN` para user stories, `R-WA-NNN` para regras Gherkin.
> Decisão arquitetural mãe: [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md).

## 1. Glossário rápido

- **Cloud API** — Meta Cloud API (oficial, hospedada Meta). Endpoint: `graph.facebook.com/v21.0`
- **HSM (Highly Structured Message)** — template aprovado Meta pra mensagens fora da janela 24h
- **Janela 24h** — após cliente enviar mensagem, business pode responder freeform por 24h sem HSM
- **`phone_number_id`** — ID Meta do número Whatsapp Business (não é o telefone, é o ID interno)
- **`access_token`** — token Meta long-lived (60 dias) ou system-user (eterno) — preferir system-user
- **HMAC** — assinatura webhook, header `X-Hub-Signature-256` calculado com `app_secret`
- **HITL** — Human In The Loop (handoff bot→humano quando PolicyEngine retorna `REQUIRE_HUMAN_REVIEW`)
- **Deflection** — % de conversas resolvidas sem intervenção humana
- **Conversation** — janela 24h Meta (cobrada uma vez); várias mensagens dentro = 1 conversa
- **Driver** — abstração `MetaCloudDriver` / `NullDriver` (padrão canon ADR 0050)

## 2. User Stories — Sub-módulo Core (Sprint 1)

### US-WA-001 · Cadastrar configuração Whatsapp do business

> **Área:** Settings
> **Rota:** `GET/PUT /whatsapp/settings`
> **Controller/ação:** `BusinessSettingsController@show` / `update`
> **Permissão Spatie:** `whatsapp.settings.manage`

**Como** Wagner (admin business)
**Quero** cadastrar `phone_number_id`, `access_token`, `app_secret`, `webhook_verify_token` do meu Whatsapp Business
**Para** o módulo conseguir enviar e receber mensagens em nome do meu business

**DoD:**
- [ ] FormRequest valida `phone_number_id` numeric, `access_token` min:50 chars
- [ ] `access_token` cifrado em DB via `encrypted` cast Laravel
- [ ] `app_secret` cifrado em DB
- [ ] Webhook URL exibida na UI: `https://oimpresso.com/api/whatsapp/webhook/{business_uuid}` (clipboard copy)
- [ ] Botão "Testar conexão" — chama `MetaCloudDriver::ping()` → exibe nome do número Meta
- [ ] Onboarding guide (link Meta Business Manager + screenshots passo-a-passo)
- [ ] Pest: `BusinessSettingsTest` validando isolamento multi-tenant + criptografia

### US-WA-002 · Driver Interface + MetaCloudDriver

> **Área:** Core
> **Service:** `Modules\Whatsapp\Services\Drivers\DriverInterface`
> **Implementações:** `MetaCloudDriver` (default), `NullDriver` (dev/CI)

**Como** Sistema
**Quero** abstração trocável de provedor (Meta, Twilio futuro, Blip futuro)
**Para** trocar provedor em 1 PR sem refactor cross-module

**DoD:**
- [ ] Interface `DriverInterface`:
  - `sendTemplate(WhatsappBusinessConfig $config, string $to, string $templateName, array $params, string $locale='pt_BR'): WhatsappSendResult`
  - `sendFreeform(WhatsappBusinessConfig $config, string $to, string $body): WhatsappSendResult` (só dentro janela 24h)
  - `fetchMessageStatus(WhatsappBusinessConfig $config, string $metaMessageId): MessageStatus`
- [ ] `MetaCloudDriver` usa `Http::withToken()` Laravel HTTP client; sem dependência Composer extra
- [ ] `NullDriver` retorna sucesso fake; gera `meta_message_id` UUID; usa `Event::dispatch` pra simular delivery
- [ ] Binding em `WhatsappServiceProvider`: `app()->bind(DriverInterface::class, fn() => config('whatsapp.driver') === 'null' ? new NullDriver : new MetaCloudDriver)`
- [ ] Pest: `MetaCloudDriverTest` com `Http::fake()` cobrindo sucesso/4xx/5xx; `NullDriverTest`

### US-WA-003 · Enviar mensagem template (Job assíncrono)

> **Área:** Core
> **Job:** `SendWhatsappMessageJob`
> **Permissão Spatie:** `whatsapp.send`

**Como** Sistema (listener Repair / RecurringBilling) ou usuário (botão UI)
**Quero** enviar mensagem template HSM com retry exponencial
**Para** garantir entrega mesmo com falha temporária Meta API

**DoD:**
- [ ] Job constructor recebe `(int $businessId, string $to, string $templateName, array $params)` — **NUNCA `session()`** (multi-tenant Tier 0)
- [ ] Resolve `WhatsappBusinessConfig` via `WhatsappBusinessConfig::where('business_id', $this->businessId)->firstOrFail()`
- [ ] Cria `WhatsappMessage` em `status=queued` antes de chamar Driver
- [ ] Chama `Driver::sendTemplate()`; atualiza `status=sent` + `meta_message_id` em sucesso, `status=failed` em hard error (4xx)
- [ ] Retry exponencial: tries=5, backoff `[60, 300, 900, 3600, 86400]` (1m → 1d)
- [ ] Eventos: `WhatsappMessageQueued`, `WhatsappMessageSent`, `WhatsappMessageFailed`
- [ ] Job tag: `"business:{$this->businessId}"` (Horizon tagging pra debug)
- [ ] Pest: `SendWhatsappMessageJobTest` com `Bus::fake()` + multi-tenant isolation

### US-WA-004 · Listener Repair: status `ready` dispara WhatsApp

> **Área:** Core (cross-module)
> **Listener:** `Modules\Whatsapp\Listeners\NotifyRepairCustomer`
> **Event:** `Modules\Repair\Events\RepairStatusChanged`

**Como** Sistema
**Quero** quando OS Repair muda pra `ready` ou `waiting_parts`, disparar template Whatsapp
**Para** cumprir ADR Repair tech/0001 sem custo de SMS

**DoD:**
- [ ] Listener registra em `WhatsappEventServiceProvider`; só dispara se `WhatsappBusinessConfig` existe pra business
- [ ] Template name: `repair_status_ready` ou `repair_status_waiting_parts` (configurável via `whatsapp_business_configs.template_repair_ready_name`)
- [ ] Params: `[customer_name, repair_id, ready_at_formatted]`
- [ ] Falha silenciosa se cliente não tem mobile cadastrado (log info; sem exception)
- [ ] Pest: `NotifyRepairCustomerTest` cobrindo (a) com config + cliente válido = job dispatched, (b) sem config = no-op, (c) sem mobile = log + no-op

## 3. User Stories — Sub-módulo Inbox + Webhook (Sprint 2)

### US-WA-010 · Receber webhook Meta + assinatura HMAC

> **Área:** Webhook
> **Rota:** `POST /api/whatsapp/webhook/{business_uuid}` (público, autenticado por HMAC)
> **Controller/ação:** `WebhookController@handle`

**Como** Meta Cloud API
**Quero** entregar evento `messages` ou `statuses` ao webhook do business
**Para** o oimpresso processar mensagens recebidas e atualizações de status

**DoD:**
- [ ] Middleware `VerifyMetaSignature`: lê header `X-Hub-Signature-256`, calcula HMAC SHA-256 do raw body com `business->whatsapp_business_config->app_secret`, rejeita 401 se mismatch
- [ ] GET handler pra Meta verification challenge: retorna `hub.challenge` se `hub.verify_token` bate com `webhook_verify_token` cadastrado
- [ ] POST handler enfileira `ProcessIncomingWebhookJob` — não processa síncrono (resposta < 200ms pra Meta não retentar)
- [ ] Resposta sempre 200 (Meta retenta agressivo se ≠200) — só rejeita 401 em assinatura inválida
- [ ] Log estruturado (Loki/CT100) com `business_id`, `event_type`, `meta_message_id` — telefone redacted
- [ ] Pest: `WebhookSignatureTest` cobrindo (a) HMAC válido = 200, (b) HMAC inválido = 401, (c) verify challenge = retorna challenge string

### US-WA-011 · Processar mensagem recebida (Job)

> **Área:** Webhook
> **Job:** `ProcessIncomingWebhookJob` (CT 100 Horizon)

**Como** Sistema
**Quero** processar payload Meta `messages` criando `WhatsappMessage` direction=inbound + atualizando/criando `WhatsappConversation`
**Para** UI Inbox renderizar conversa em real-time

**DoD:**
- [ ] Idempotência: `meta_message_id` UNIQUE em `whatsapp_messages`; processar mesma mensagem 2× = no-op
- [ ] Match `Contact` existente por mobile (com/sem DDI normalizado); cria provisional se não achou (`is_provisional=true`)
- [ ] Cria/atualiza `WhatsappConversation` (1 por contact+business; updates `last_message_at`, `unread_count++`)
- [ ] Dispara evento `WhatsappMessageReceived` (listener `DispatchToJanaBot` ouve em sprint 3)
- [ ] Centrifugo publish channel `whatsapp:business:{id}` payload `{conversation_id, message_id, preview}` pra UI atualizar live
- [ ] Pest: `ProcessIncomingWebhookJobTest` cobrindo (a) primeira mensagem cria conversation, (b) segunda atualiza last_message_at, (c) duplicata = no-op

### US-WA-012 · Inbox UI (Cockpit pattern)

> **Área:** Inbox
> **Rota:** `GET /whatsapp/conversations`
> **Page:** `resources/js/Pages/Whatsapp/Conversations/Index.tsx`

**Como** Larissa (atendente)
**Quero** ver lista de conversas à esquerda + chat painel à direita (Cockpit pattern ADR 0039)
**Para** atender múltiplos clientes sem trocar de tela

**DoD:**
- [ ] AppShellV2 layout (não Blade legacy — ADR 0094 visual canon)
- [ ] Lista esquerda: avatar contact + último preview + timestamp + badge `unread_count`
- [ ] Tabs filtro: "Não lidas" / "Atribuídas a mim" / "Bot" / "Resolvidas" / "Todas"
- [ ] Chat painel direito: mensagens scroll virtualizado (TanStack Virtual), bolha verde direita pra outbound, cinza esquerda pra inbound — padrão MemCofre ADR ui/0001
- [ ] Input bottom: textarea + botão "Enviar" (só ativa dentro janela 24h ou se selecionou template)
- [ ] Real-time via Centrifugo channel `whatsapp:business:{id}` — nova mensagem aparece sem reload (preserva preference cache-estado preservado)
- [ ] Botão "Atribuir a mim" / "Marcar resolvida" / "Encaminhar pra outro atendente"
- [ ] Pest dusk + browser MCP smoke test obrigatório

### US-WA-013 · Templates UI (sync Meta Business Manager)

> **Área:** Templates
> **Rota:** `GET /whatsapp/templates`
> **Page:** `resources/js/Pages/Whatsapp/Templates/Index.tsx`

**Como** Wagner (admin)
**Quero** ver templates HSM cadastrados na Meta Business Manager + status (pending/approved/rejected) + body preview
**Para** saber quais posso usar e quais estão em aprovação

**DoD:**
- [ ] Botão "Sincronizar com Meta" — chama `MetaCloudDriver::fetchTemplates()` → upsert `whatsapp_templates`
- [ ] Lista: nome, language (pt_BR), category (UTILITY/MARKETING/AUTHENTICATION), status com badge colorido, body preview com `{{1}}`/`{{2}}` placeholders
- [ ] Filtro por status + category
- [ ] Disabled state com tooltip "Cadastre template na Meta Business Manager primeiro" se vazio (link MBM)
- [ ] Pest + browser MCP smoke

## 4. User Stories — Sub-módulo Bot Jana + HITL + Métricas (Sprint 3)

### US-WA-020 · Listener DispatchToJanaBot

> **Área:** Bot
> **Listener:** `Modules\Whatsapp\Listeners\DispatchToJanaBot`
> **Event:** `WhatsappMessageReceived`

**Como** Sistema
**Quero** quando mensagem inbound chega + business tem `bot_enabled=true`, encaminhar pro PolicyEngine ADS
**Para** Jana responder automaticamente o que pode + escalar humano onde precisa

**DoD:**
- [ ] Chama `decide('whatsapp', 'reply', {message, conversation, business_id})` (skill `ads-route` Tier A dormente — ativará na S5)
- [ ] PolicyEngine retorna 1 dos 4 outcomes:
  - `ALLOW_BRAIN_A` → Jana responde direto (Brain A gpt-4o-mini); resposta cria `WhatsappMessage` outbound + envia
  - `REQUIRE_BRAIN_B` → escala Brain B (Sonnet); idem
  - `REQUIRE_HUMAN_REVIEW` → marca conversa `status=awaiting_human`; notifica atendentes via Centrifugo
  - `BLOCK_ALWAYS` → log + no-op
- [ ] PII redacted antes de enviar pra Brain (`PiiRedactor`)
- [ ] Pest: `DispatchToJanaBotTest` cobrindo 4 outcomes

### US-WA-021 · Métricas conversation (custo, deflection, tempo resposta)

> **Área:** Métricas
> **Tabela:** `whatsapp_conversation_metricas`
> **Service:** `WhatsappMetricasService::aggregate()`
> **Job:** scheduler diário 04:00

**Como** Wagner / Larissa
**Quero** métricas por business: custo/mês, deflection bot %, tempo médio resposta, NPS pós-conversa
**Para** justificar ROI Whatsapp e otimizar custos Meta

**DoD:**
- [ ] Schema `whatsapp_conversation_metricas`: `business_id, dia, total_conversas, conversas_iniciadas_negocio, conversas_iniciadas_cliente, custo_centavos, tempo_resposta_p50_segundos, tempo_resposta_p95_segundos, deflection_pct`
- [ ] Job agrega dia D-1 às 04:00 BRT
- [ ] Dashboard tab em UI Inbox ou módulo próprio (definir Sprint 3 detalhamento)
- [ ] OpenTelemetry `whatsapp.*` metrics paralelo (padrão ADR 0051)
- [ ] Pest: `MetricasAggregationTest` cobrindo (a) 0 conversas dia = row 0, (b) 10 conversas com 4 deflected = 40% deflection

## 5. Regras Gherkin (DoD detalhado)

### R-WA-001 · Mensagem outbound nunca cross-tenant

```gherkin
Dado business=4 (ROTA LIVRE) com WhatsappBusinessConfig {phone_number_id=A}
E   business=7 com WhatsappBusinessConfig {phone_number_id=B}
Quando SendWhatsappMessageJob(businessId=4, to="+55119...", template, params) executa
Então usa phone_number_id=A
E   chama Meta API com access_token de business=4
E   NUNCA business=7 vê essa mensagem em sua Inbox
```

### R-WA-002 · Webhook rejeita HMAC inválido

```gherkin
Dado business=4 com WhatsappBusinessConfig.app_secret="abc123"
Quando POST /api/whatsapp/webhook/{business_uuid_4} com header "X-Hub-Signature-256: sha256=WRONG"
Então resposta é 401 Unauthorized
E   nenhum WhatsappMessage é criado
E   log estruturado registra "webhook_signature_invalid" com business_id=4
```

### R-WA-003 · Idempotência incoming webhook

```gherkin
Dado WhatsappMessage com meta_message_id="wamid.XYZ" já existe (status=received)
Quando POST /api/whatsapp/webhook/{business_uuid} com mesmo wamid.XYZ
Então ProcessIncomingWebhookJob é enfileirado
E   ao executar detecta UNIQUE meta_message_id e faz no-op
E   nenhum evento WhatsappMessageReceived é re-disparado
```

### R-WA-004 · Janela 24h e fallback HSM

```gherkin
Dado conversa com last_inbound_at = agora - 25h
Quando atendente tenta enviar freeform "obrigado pelo retorno"
Então UI bloqueia botão "Enviar" com tooltip "Janela 24h expirada — selecione um template"
E   UI mostra dropdown templates HSM aprovados pra escolher
```

### R-WA-005 · Multi-tenant Tier 0 — global scope

```gherkin
Dado WhatsappMessage e WhatsappConversation usam BusinessIdScope global
Quando Larissa (business=4) faz GET /whatsapp/conversations
Então query gera "SELECT ... WHERE business_id = 4"
E   conversas de outros businesses NUNCA aparecem
E   tentar GET /whatsapp/conversations/{id_de_outro_business} retorna 404
```

### R-WA-006 · PII redacted em logs

```gherkin
Dado log estruturado de qualquer evento Whatsapp
Quando registra payload contendo telefone "+5511987654321"
Então log persiste como "+551198765[REDACTED]" via PiiRedactor
E   commit/PR review nunca mostra telefones reais (skill commit-discipline Tier A)
```

## 6. Métricas de sucesso

- **Custo Whatsapp/business**: < R$ 50/mês pra businesses < 200 conversas/mês (ROTA LIVRE alvo)
- **Tempo entrega**: p95 < 3s do `SendWhatsappMessageJob` queued até `WhatsappMessageSent` event
- **Deflection bot**: ≥ 40% das conversas inbound resolvidas sem humano (Sprint 3 baseline)
- **Tempo resposta humano**: p50 < 5min em horário comercial
- **Webhook uptime**: ≥ 99.9% (alarme se zero eventos em 24h pra business ativo)
- **Multi-tenant violations**: 0 (teste `MultiTenantIsolationTest` no CI bloqueia merge)

## 7. Backlog futuro (não-Sprint 1-3)

- US-WA-030 — Botões interativos (`button` template) — HSM com CTAs
- US-WA-031 — List messages (cardápio gráfica: orçar, acompanhar OS, segunda via)
- US-WA-032 — Mídia outbound (imagem, PDF de boleto/NFe anexado)
- US-WA-033 — Mídia inbound (cliente manda foto do produto pra orçar)
- US-WA-034 — Suporte multi-driver (Twilio, Take Blip) se enterprise pedir
- US-WA-035 — White-label templates (módulo Officeimpresso revenue)
- US-WA-036 — Portal cliente self-service "minhas conversas Whatsapp"
- US-WA-037 — Integração Crm (lead vindo de Whatsapp vira lead no Crm)
- US-WA-038 — Pix Copia-e-Cola via Whatsapp (RecurringBilling US-RB-044 v2)
