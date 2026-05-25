---
slug: 0117-multiplos-numeros-whatsapp-por-business
number: 117
title: "Múltiplos números Whatsapp por business — 1 driver + escopo de atendimento por número (WR2 piloto: Comercial + Financeiro)"
type: adr
status: aceito
renumbered_from: 115
renumbered_at: 2026-05-09
renumbered_reason: "Conflito de numeração — PR #308 (ADRs 0115-Gold + 0116-Gold-pivot) mergeou em paralelo a este. Convenção interna exige número único por ADR canon. Esta ADR (Whatsapp) renumerada de 0115 → 0117 por ter cadeia menor de refs externas vs Gold (cadeia 0115 → 0116 emenda)."
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-09"
accepted_at: "2026-05-09"
decided_by: [W]
module: whatsapp
quarter: 2026-Q2
tier: CANON
tags: [whatsapp, multi-numeros, driver-per-phone, multi-tenant, schema-change, sprint-4]
related_adrs: ["0093-multi-tenant-isolation-tier-0", "0094-constituicao-v2-7-camadas-8-principios", "0096-modulo-whatsapp-meta-cloud-api-direto", "0105-cliente-como-sinal-guiar-sem-mandar", "0111-emenda-0096-bypass-meta-fallback-per-business"]
parent_charter: resources/js/Pages/Whatsapp/Settings.charter.md
parent_adr: 0096
supersedes: []
supersedes_partially: ["0096-modulo-whatsapp-meta-cloud-api-direto"]
superseded_by: []
authors: [wagner, opus-4.7]
pii: false
review_triggers:
  - 1 business chegar a 5+ números (avaliar UI lista CRUD vs wizard)
  - Algum cliente pedir compartilhar número entre businesses (NÃO permitir; abrir nova ADR)
  - Conflito de roteamento (2 números marcados pra mesmo evento) virar dor recorrente
  - Spatie permission scope dinâmico ganhar suporte nativo (revisar ACL própria)
---

# ADR 0117 — Múltiplos números Whatsapp por business

## Contexto

[ADR 0096](0096-modulo-whatsapp-meta-cloud-api-direto.md) (módulo Whatsapp mãe) e seu Charter [`Settings.charter.md`](../../resources/js/Pages/Whatsapp/Settings.charter.md) estabeleceram **1 número Whatsapp por business** como Non-Goal explícito:

> ❌ Múltiplas instances Baileys por business — 1 número = 1 sessão.

Schema reforçava: tabela `whatsapp_business_configs` é 1 row por business, com `UNIQUE (business_id, baileys_phone_e164)` ([migration 2026_05_09](../../Modules/Whatsapp/Database/Migrations/2026_05_09_000001_simplify_baileys_columns_in_whatsapp_business_configs.php:40)).

**Sinal qualificado** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — cliente como sinal): WR2 Sistemas (`business_id=1`) precisa operar 2 números:

- **Comercial** — atendimento vendas/leads/dúvidas técnicas (atendentes área comercial)
- **Financeiro** — cobrança, recibo, segunda via boleto (atendentes área financeira)

Cada número tem **escopo de atendimento próprio** — atendente comercial não vê fila financeira e vice-versa. Modelo natural pra qualquer business com >1 área de contato (comum em todos os concorrentes Capterra: Z-API, Wati, Twilio).

Princípio Constituição V2 ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md)) #5 — "SoC brutal": separação por canal de atendimento melhora isolamento de contexto. Cliente que liga pro Financeiro não precisa ver thread de orçamento Comercial.

## Decisão

**Migrar `whatsapp_business_configs` (1:1 business→config) para `whatsapp_business_phones` (1:N business→números)**, com 1 driver + credenciais + LGPD + roteamento de eventos **per-phone**.

### Estrutura nova

```
business
  └── 1:N whatsapp_business_phones (label='Comercial', label='Financeiro', ...)
        ├── driver + credenciais (próprios por número)
        ├── LGPD (aceite por número)
        ├── handles_* flags (quais eventos automáticos atende)
        ├── 1:N whatsapp_phone_user_access (atendentes fixados)
        ├── 1:N whatsapp_conversations (FK adicional)
        └── 1:N whatsapp_messages (FK adicional)
```

### 6 decisões de desenho

| # | Pergunta | Decisão Wagner |
|---|---|---|
| Q1 | Atendente vê quais conversas? | **Fixo num número** — atendente cadastrado em `whatsapp_phone_user_access` só vê conversas daquele `whatsapp_business_phone_id` |
| Q2 | Roteamento de eventos automáticos? | **Cada número escolhe** — colunas `handles_repair_status`, `handles_billing`, `handles_jana_bot`, `handles_outbound_default` (boolean per-phone). Listener resolve via query `where('handles_X', true)` |
| Q3 | Driver por número ou por business? | **Por número** — Comercial pode ser Baileys, Financeiro Meta Cloud (ou qualquer combinação). Toda credencial sobe pra `whatsapp_business_phones` |
| Q4 | Apelido? | **Texto livre** — `label VARCHAR(80) NOT NULL` ("Comercial", "Financeiro", "Larissa pessoal", "Filial Mauá"). Sem enum fechado |
| Q5 | Permissão Spatie granular? | **Permissão base + ACL própria** — `whatsapp.send` Spatie continua existindo (gating nível business), mas filtro per-phone vem de `whatsapp_phone_user_access(phone_id, user_id)`. NÃO criar N permissions Spatie por número (escala mal) |
| Q6 | Conversas antigas em prod? | **Migram pro 1º número cadastrado** — dump dos `whatsapp_business_configs` existentes vira 1 row em `whatsapp_business_phones` com `label='Comercial'` (default), todas conversas/messages apontam pra ele. Admin reclassifica manualmente depois |

### Schema mãe (detalhado em [runbook migração](../requisitos/Whatsapp/runbooks/migrar-1-para-n-numeros.md))

```sql
CREATE TABLE whatsapp_business_phones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    phone_uuid CHAR(36) NOT NULL UNIQUE COMMENT 'usado em webhook URL e Centrifugo channel',
    label VARCHAR(80) NOT NULL COMMENT 'apelido livre Comercial/Financeiro/etc',

    -- Driver per-phone (era per-business em whatsapp_business_configs)
    driver VARCHAR(20) NOT NULL DEFAULT 'zapi',
    fallback_driver VARCHAR(20) NOT NULL DEFAULT 'meta_cloud',
    display_phone VARCHAR(20) NULL,

    -- Credenciais cada driver (idêntico ao schema antigo, só multiplicado)
    meta_phone_number_id VARCHAR(64) NULL,
    meta_access_token TEXT NULL,        -- encrypted cast
    meta_app_secret TEXT NULL,          -- encrypted cast
    meta_webhook_verify_token VARCHAR(64) NULL,
    zapi_instance_id VARCHAR(64) NULL,
    zapi_instance_token TEXT NULL,      -- encrypted
    zapi_client_token TEXT NULL,        -- encrypted
    baileys_instance_id VARCHAR(64) NULL,
    baileys_phone_e164 VARCHAR(20) NULL,
    baileys_verified_name VARCHAR(100) NULL,
    baileys_profile_pic_url VARCHAR(255) NULL,

    -- LGPD per-phone (cada aceite é por número)
    lgpd_acknowledged_at TIMESTAMP NULL,
    lgpd_acknowledged_by_user_id INT UNSIGNED NULL,

    -- Roteamento de eventos (Q2 — decisão B)
    handles_repair_status BOOLEAN NOT NULL DEFAULT FALSE,
    handles_billing BOOLEAN NOT NULL DEFAULT FALSE,
    handles_jana_bot BOOLEAN NOT NULL DEFAULT TRUE,
    handles_outbound_default BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'fallback se nenhum outro flag bate',

    -- Templates per-phone
    template_repair_ready_name VARCHAR(64) NULL,
    template_repair_waiting_parts_name VARCHAR(64) NULL,
    template_billing_due_name VARCHAR(64) NULL,
    template_billing_paid_name VARCHAR(64) NULL,

    -- Health
    driver_health ENUM('healthy','degraded','disconnected','banned','never_checked')
        NOT NULL DEFAULT 'never_checked',
    driver_health_consecutive_failures INT UNSIGNED NOT NULL DEFAULT 0,
    last_health_check_at TIMESTAMP NULL,
    last_health_message TEXT NULL,

    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY wbp_biz_phone_unq (business_id, baileys_phone_e164),
    INDEX wbp_biz_idx (business_id),
    INDEX wbp_drv_health_idx (driver, driver_health)
);

CREATE TABLE whatsapp_phone_user_access (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_id INT UNSIGNED NOT NULL,
    whatsapp_business_phone_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    UNIQUE KEY wpua_phone_user_unq (whatsapp_business_phone_id, user_id),
    INDEX wpua_biz_user_idx (business_id, user_id),
    FOREIGN KEY (whatsapp_business_phone_id)
        REFERENCES whatsapp_business_phones(id) ON DELETE CASCADE
);

ALTER TABLE whatsapp_conversations
    ADD COLUMN whatsapp_business_phone_id BIGINT UNSIGNED NOT NULL AFTER business_id,
    ADD INDEX whatsapp_conversations_phone_idx (whatsapp_business_phone_id);

ALTER TABLE whatsapp_messages
    ADD COLUMN whatsapp_business_phone_id BIGINT UNSIGNED NOT NULL AFTER business_id,
    ADD INDEX whatsapp_messages_phone_idx (whatsapp_business_phone_id);
```

### Webhook resolution (sem mudar contrato URL)

URLs `/api/whatsapp/webhook/{driver}/{business_uuid}` **continuam iguais**. Cada controller resolve `phone_id` via payload:

- **Meta Cloud** — `entry[].changes[].value.metadata.phone_number_id` → lookup `whatsapp_business_phones.meta_phone_number_id`
- **Z-API** — `instanceId` no body → lookup `whatsapp_business_phones.zapi_instance_id`
- **Baileys** — `instance_id` no payload do daemon CT 100 → lookup `whatsapp_business_phones.baileys_instance_id`

Daemon CT 100 não muda — já manda `instance_id` em todo evento.

### Roteamento de eventos automáticos (Q2)

Listener resolve número correto via flag boolean:

```php
// Modules/Whatsapp/Listeners/NotifyRepairCustomer (US-WA-004)
$phone = WhatsappBusinessPhone::where('business_id', $event->businessId)
    ->where('handles_repair_status', true)
    ->first()
    ?? WhatsappBusinessPhone::where('business_id', $event->businessId)
        ->where('handles_outbound_default', true)
        ->first();

if (!$phone) {
    Log::info('No phone configured for repair_status event', ['business_id' => $event->businessId]);
    return; // falha silenciosa documentada
}

SendWhatsappMessageJob::dispatch(
    $event->businessId,
    $phone->id,           // <-- novo arg obrigatório
    $event->customer->mobile,
    $phone->template_repair_ready_name,
    [...$params]
);
```

**Regra de fallback** — se nenhum número tem flag específica do evento, usa `handles_outbound_default=true`. Se mais de 1 tem o flag, escolhe primeiro (por `id ASC`) e gera warning estruturado pra admin reclassificar.

### Permissão atendente (Q1 + Q5)

Spatie permission `whatsapp.send` continua valendo (gating de quem pode usar Whatsapp do business). Filtro por número vem de ACL:

```php
// resolver: quais conversas Larissa vê?
$phoneIds = WhatsappPhoneUserAccess::where('user_id', auth()->id())
    ->pluck('whatsapp_business_phone_id');

WhatsappConversation::where('business_id', $businessId)
    ->whereIn('whatsapp_business_phone_id', $phoneIds)
    ->paginate(...);
```

Admin/superadmin (`Admin#{biz_id}`) bypassa ACL — vê todos números do business. Definido via Gate dedicada `whatsapp.view-all-phones` (default só admin role).

## Consequências

### Positivas

1. **Atende WR2 piloto** + qualquer business com >1 área de contato (gráficas com loja física + e-commerce, oficinas com balcão + delivery, etc) — caso de uso comum em CAPTERRA-FICHA
2. **Driver mix por número** — Comercial Baileys (barato) + Financeiro Meta Cloud (zero ban risk) protege fluxo crítico de cobrança
3. **Isolamento de contexto** — atendente Comercial não polui Inbox com cobrança, princípio SoC brutal ([ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) #5)
4. **Webhook URL contract estável** — daemon CT 100 não muda, payload já carrega `instance_id`

### Negativas

1. **Migration mexe em prod** — WR2 e qualquer outro business em prod com `whatsapp_business_configs` precisa de migration de dados (DOC: runbook explica passo-a-passo + rollback)
2. **Charter `Settings.charter.md` muda Non-Goal** — vai virar `status: live, charter_version: 2`, requer aprovação Wagner (parte mais sensível, anti-alucinação skill `charter-write`)
3. **Listeners cross-module mudam assinatura** — `SendWhatsappMessageJob::dispatch` ganha `phone_id` obrigatório. Toca [`NotifyRepairCustomer`](../../Modules/Whatsapp/Listeners/NotifyRepairCustomer.php), Listener Billing (RecurringBilling), `DispatchToJanaBot`. Todos precisam test update
4. **UI Settings.tsx vira lista CRUD** — wizard 1-input atual ([Settings.tsx](../../resources/js/Pages/Whatsapp/Settings.tsx)) vira `Index.tsx` (lista) + `Edit.tsx` (form per-phone). Carga MWART média (≤300 LOC)
5. **Spatie ACL própria adiciona 1 tabela** — manutenção mínima (CRUD admin + listagem); aceite por simplicidade vs Spatie permissions parametrizadas

### Riscos

| Risco | Mitigação |
|---|---|
| Listener dispara em 0 phones (event sem `handles_*`) | Falha silenciosa + log estruturado + alarme cross-tenant se >5%/dia em algum business |
| Listener dispara em 2 phones (config inconsistente) | Gera warning visível na UI Settings ("Comercial e Financeiro estão marcados pra Repair — só o primeiro vai disparar") + regra UNIQUE por evento ainda em estudo |
| Atendente sem `whatsapp_phone_user_access` cadastrado fica sem ver nada | Empty state UI Inbox: "Você não tem acesso a nenhum número Whatsapp deste business — peça pro admin" + link pra config |
| Conversas antigas vinculam num número errado pós-migração | Q6 já endereça — migration pro 1º número (Comercial) é default seguro; admin reclassifica via ação "Mover conversa pra outro número" (US-WA-041 backlog) |

## Alternativas consideradas

### Alternativa A — Hard-coded por evento

Listener `NotifyRepairCustomer` sempre usa primeiro número Comercial; Billing sempre primeiro Financeiro. Sem flags configuráveis.

**Rejeitada**: business diferentes têm escopos diferentes (gráfica pode querer Repair no Financeiro porque cobrança já existe lá; oficina pode separar). Engessa demais.

### Alternativa C — Tabela mapping separada `event_routing`

```sql
CREATE TABLE whatsapp_event_routing (
    business_id, event_type, whatsapp_business_phone_id, ...
);
```

**Rejeitada**: overkill pra 2-3 eventos. Mais 1 tabela pra manter, UI mais complexa, sem ROI claro vs flags boolean. Reabrir se >5 tipos de evento ou se algum business pedir mapping muitos-pra-muitos.

### Alternativa Q5-i — Spatie permissions parametrizadas

Criar permissions `whatsapp.send.commercial`, `whatsapp.send.finance` (1 por número). Hook quando admin cadastra número novo cria permission Spatie correspondente.

**Rejeitada**: polui Spatie permissions table com N permissions/número/business — escala mal (50 businesses × 2 números = 100 permissions só pra esse modulo). ACL própria fica isolada e cleaner.

## Plano de implementação

Quebrado em 4 PRs sequenciais (cada ≤300 LOC, skill `commit-discipline`):

1. **PR 1** — schema + migration de dados (1→N) + `WhatsappBusinessPhone` model + `WhatsappPhoneUserAccess` model + Pest `MultiTenantIsolationTest` adaptado
2. **PR 2** — refactor `DriverFactory::make($phone)` + `SendWhatsappMessageJob($businessId, $phoneId, ...)` + listeners (`NotifyRepairCustomer`, billing, `DispatchToJanaBot`) com `handles_*` resolution + Pest cobrindo 4 flags
3. **PR 3** — Settings UI (Index lista + Edit form per-phone + ACL CRUD page) + Charter v2 + `WhatsappSettingsCharterTest` invariantes novas
4. **PR 4** — Inbox UI filtro por número + ACL filtro automático em `ConversationsController` + dusk smoke test

Detalhes em [`memory/requisitos/Whatsapp/runbooks/migrar-1-para-n-numeros.md`](../requisitos/Whatsapp/runbooks/migrar-1-para-n-numeros.md).

## Cronograma realista (recalibração [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md))

- **Codáveis IA-pair** (PRs 1-4 sem smoke real): ~3-4 dias úteis
- **Humano-limitado** (canary 7d em WR2 biz=1, monitor 30d, smoke fim-a-fim com Wagner cadastrando 2 números reais): ~30-37 dias relógio do mundo real
- **Total ciclo até "estável"**: ~45 dias

## Métrica de validação

- **Ano 0**: WR2 biz=1 opera 2 números 30d sem incidente cross-tenant (`MultiTenantIsolationTest` + manual review log)
- **Ano 0+30d**: 0 conversas vazadas entre números (query: `SELECT COUNT(*) FROM whatsapp_conversations WHERE whatsapp_business_phone_id NOT IN (SELECT id FROM whatsapp_business_phones WHERE business_id = whatsapp_conversations.business_id)` = 0)
- **Ano 0+90d**: Pelo menos 1 outro business adota 2+ números (sinal de que feature atende caso geral, não só WR2)

## Status

Aceito 2026-05-09 — Wagner aprovou Q1-Q6 + escopo dos 4 PRs sequenciais. Implementação fica pra próxima sessão (PR 1: schema + migration de dados).
