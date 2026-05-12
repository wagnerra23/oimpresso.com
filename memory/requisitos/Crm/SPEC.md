---
module: Crm
alias: crm
status: draft-proposal
proposal_date: 2026-05-12
proposed_by: opus-4.7 (discovery + research)
needs_wagner_approval: true
parent_adr_proposal: memory/decisions/proposals/drafts/crm-pre-venda-pipeline.md
related_adrs: [0093, 0094, 0096, 0105, 0117, 0121, 0143]
related_modules: [Whatsapp, Sells (FSM canon), Jana, Repair, RecurringBilling]
fsm_handoff: lead_won → transactions.current_stage_id = "quote_draft" (US-SELL-033 processo "Venda Com Produção")
---

# Especificação funcional — Crm/Pipeline Pré-venda

> Convenção do ID: `US-CRM-NNN`. Campo `implementado_em` linka com a Page React que atende a story.

> ⚠️ **Draft de proposta** — todas as US abaixo são **propostas** que dependem de aprovação Wagner via ADR-mãe (`memory/decisions/proposals/drafts/crm-pre-venda-pipeline.md`). Não criar tasks MCP nem PR sem decisão D1-D5 dessa ADR fechada.

> 🔍 **Princípio guia**: este SPEC parte do que JÁ EXISTE no Modules/Crm legacy (herdado UltimatePOS, 21 controllers + 11 entities + 26 migrations) e propõe SOMENTE features incrementais. Cada US cita o gap concreto encontrado no §0.

---

## §0 — O que JÁ EXISTE (discovery 2026-05-12)

### Tabelas, models e controllers JÁ presentes em `Modules/Crm/`

| Conceito CRM | Schema/Código atual no Modules/Crm | Onde fica | Estado |
|---|---|---|---|
| **Lead entity** | `Leaduser` model (`crm_lead_users` pivot) + `CrmContact extends Contact` usando `contacts.type='lead'` + colunas `crm_source`, `crm_life_stage`, `converted_by`, `converted_on` | `Entities/Leaduser.php`, `Entities/CrmContact.php` | ✅ funcional |
| **Lead CRUD** | `LeadController@index/create/store/show/edit/update/destroy/convertToCustomer/postLifeStage` | `Http/Controllers/LeadController.php` | ✅ funcional Blade+DataTables |
| **Kanban view por life stage** | `LeadController@index` modo `lead_view=kanban` → groupBy `crm_life_stage` + drag-to-board | `LeadController.php:215-282` | ✅ funcional Blade |
| **Conversão Lead → Customer** | `LeadController::convertToCustomer($id)` muda `contacts.type='lead' → 'customer'` + activityLog | `LeadController.php:536-570` | ✅ funcional (mas é só flip de coluna, não cria Transaction) |
| **Life stages (funil)** | Tabela `categories` polimórfica com `category_type='life_stage'` — configurável per business | `Category::forDropdown($business_id, 'life_stage')` | ✅ funcional |
| **Sources (origem do lead)** | Tabela `categories` com `category_type='source'` (Google Ads, indicação, etc) | `Category::forDropdown($business_id, 'source')` | ✅ funcional |
| **Atribuição vendedor** | `crm_lead_users` (many-to-many `contact_id` × `user_id`) — vários vendedores podem dividir um lead | Migration `2020_04_09_101052_create_lead_users_table.php` | ✅ funcional |
| **Permissão dono-vs-todos** | `crm.access_all_leads` vs `crm.access_own_leads` + scope `OnlyOwnLeads` | `CrmContact::scopeOnlyOwnLeads`, `LeadController.php:64-66` | ✅ funcional Spatie |
| **Follow-up agendado** | `Schedule` model (`crm_schedules`) com `start_datetime`, `notify_type`, `notify_via` (sms/mail), `followup_category_id`, `is_recursive` | `Entities/Schedule.php`, migration `2020_03_27_133605` + extras 2021 | ✅ funcional |
| **Follow-up log** | `ScheduleLog` model (`crm_schedule_logs`) — registra outcome do follow-up | `Entities/ScheduleLog.php` | ✅ funcional |
| **Follow-up ligado a faturas** | `crm_followup_invoices` (pivot `follow_up_id × transaction_id`) | Migration `2021_02_19_120846` | ✅ funcional |
| **Lembrete automático** | Command `crm:send-follow-up-reminders` via Scheduler 15min (ADR TECH-0001) | `Console/Commands/` + `Kernel.php` | ✅ funcional |
| **Call log** | `CrmCallLog` model (`crm_call_logs`) com `call_type`, `mobile_number`, `start_time`, `end_time`, `duration` | Migration `2021_02_04_120439` + `2021_02_08_172047_add_mobile_name` | ✅ funcional |
| **Campanha** | `Campaign` model (`crm_campaigns`) — broadcast SMS/email a lista contact_ids | `Entities/Campaign.php` + `CampaignController` | ✅ funcional |
| **Proposta comercial** | `Proposal` model (`crm_proposals`) + `ProposalTemplate` (`crm_proposal_templates`) com body, subject, anexos (`App\Media` morphMany) — envio com CC/BCC | Migrations `2021_06_15` + `2021_06_16` + `2022_06_06_073006` | ✅ funcional |
| **Marketplace B2B (importação leads)** | `CrmMarketplace` model + `CrmMarketplaceController::importLeads()` — integração com marketplace externo via `site_key`/`site_id` | Migration `2022_02_09_055012` + `2022_02_17_113045_add_source_id` | ✅ funcional (uso real desconhecido) |
| **Contact login (portal cliente)** | `ContactLoginController` permite contact logar e ver pedidos/extrato | `Routes/web.php:6-19` (prefix `/contact/*`) | ✅ funcional |
| **Comissão por contact-person** | `CrmContactPersonCommission` model | Migration `2022_05_26_061553` | ✅ funcional |
| **Order request (B2B)** | `OrderRequestController` — pedido feito pelo contact via portal, vira venda | `OrderRequestController.php` | ✅ funcional |
| **Dashboard CRM** | `CrmDashboardController@index` — leads por origem, conversão, agenda | `Http/Controllers/CrmDashboardController.php` | ✅ funcional Blade |
| **Relatórios** | `ReportController` — follow-ups por user, por contato, conversão lead→customer com drill-down | `Http/Controllers/ReportController.php` | ✅ funcional Blade |
| **Settings per business** | `business.crm_settings` (column JSON, migration `2021_09_24_065738`) + `CrmSettingsController` | `Entities/Business` + migration | ✅ funcional |

### Whatsapp (Modules/Whatsapp) — discovery cruz

| Feature Whatsapp | Status | Liga a CRM? |
|---|---|---|
| Drivers Z-API/Meta Cloud/Baileys (1 número per business, ADR 0117 → multi-número WR2) | Sprint 1 entregue, Sprint 3 Baileys custom planejado | ❌ NÃO integrado com `crm_leads` hoje — `grep -r "Crm\|CrmContact" Modules/Whatsapp/` retorna ZERO matches |
| `whatsapp_conversations` + `whatsapp_messages` (schema ADR 0135) | Sprint 1 entregue | Conversa fica órfã: sem `contact_id`/`lead_id` apontando pra `contacts` |
| Inbox `/atendimento/inbox` (US-WA-067/069) | Sprint 2 em construção | UI mostra mensagem mas não cria/atualiza Lead automaticamente |
| Bot Jana HITL + classificação intenção | Sprint 2/3 planejado | Não classifica `intencao=lead_potencial` ainda |

### FSM Pipeline (Sells) — handoff de Lead → Transaction

ADR 0143 (FSM live em prod 2026-05-12) define o **processo "Venda Com Produção"** que começa em stage `quote_draft`. O **handoff do CRM** seria: quando `crm_life_stage` virar "Won", criar `Transaction` com `current_stage_id = quote_draft`. Hoje `LeadController::convertToCustomer` só muda `contacts.type` — NÃO cria Transaction, NÃO inicia FSM. Esse é o gap principal.

### Comparação features × mercado × o que oimpresso PRECISA construir

| Feature | UPos legacy / Modules/Crm atual | RD Station / Pipedrive / Agendor | oimpresso PRECISA construir? |
|---|---|---|---|
| Lead capture form manual | ✅ `LeadController@create/store` Blade | ✅ todos | ❌ NÃO duplicar — só migrar Blade → Inertia (MWART) |
| Pipeline kanban drag-and-drop | ✅ `LeadController@index lead_view=kanban` (Blade jKanban) | ✅ todos | 🟡 PARCIAL — migrar kanban Blade pra Inertia + drag entre stages com PATCH `crm_life_stage` |
| Atribuição vendedor + round-robin | ✅ `crm_lead_users` pivot manual | ✅ todos (round-robin auto) | 🟢 NOVO — auto round-robin/load-balance não existe |
| **Lead capture via WhatsApp** | ❌ NÃO INTEGRADO (whatsapp_conversations sem lead_id) | ✅ RD Station "conversa vira lead automático" | 🟢 NOVO — listener `WhatsappMessageReceived` → cria/atualiza Lead |
| **Jana IA classifica intenção (spam/cliente/lead/cobrança)** | ❌ NÃO existe | ✅ RD Station "Rê" assistente IA | 🟢 NOVO — Agent classificador (ADR 0035 stack laravel/ai) |
| **Lead scoring (hot/warm/cold automático)** | 🟡 manual via `priority` field (não-automatizado) | ✅ HubSpot, RD Station | 🟢 NOVO — regras simples Sprint 1 (último contato + valor estimado + origem) → ML Sprint N |
| Follow-up agendado + lembrete automático | ✅ `Schedule` + cron 15min `crm:send-follow-up-reminders` | ✅ todos | ❌ NÃO duplicar — só adicionar canal Whatsapp ao `notify_via` (hoje só sms/mail) |
| **SLA de resposta automatizado (lead novo → alerta vendedor em N min)** | ❌ NÃO existe | ✅ todos (RD, Pipedrive workflow) | 🟢 NOVO — coluna `sla_responder_em` + cron alerta superior |
| **Motivo perda rastreado (taxonomia)** | 🟡 PARCIAL — converte pra customer ou destroy() = lead some sem motivo | ✅ Pipedrive "Lost reason" obrigatório | 🟢 NOVO — `crm_motivos_perda` catálogo + obrigar ao mover pra stage "Perdido" |
| Proposta comercial (envio + tracking abertura) | ✅ `Proposal` + `ProposalTemplate` envio email com CC/BCC | ✅ Pipedrive Smart Docs / RD propostas | 🟡 PARCIAL — falta tracking abertura/aceite + assinatura digital |
| Conversão Lead → Customer | ✅ flip `contacts.type` | ✅ todos | 🟢 INSUFICIENTE — não cria Transaction FSM `quote_draft`. Gap crítico ADR 0143. |
| **Reabordagem 90/180d (lead frio reativação)** | ❌ NÃO existe | ✅ RD Station workflow | 🟢 NOVO — cron `lead:reactivate-cold` + tag "reabordar em N dias" |
| **Histórico interações 360º (call+email+whatsapp+follow-up timeline)** | 🟡 fragmentado (call_logs / schedules / proposals separados, sem timeline unificado) | ✅ todos timeline unificado | 🟢 NOVO — view `crm_lead_timeline_unified` ou tabela `crm_lead_interactions` append-only |
| Dashboard pipeline (conversão por stage, ticket médio, ciclo médio) | ✅ `CrmDashboardController` + `ReportController` Blade | ✅ todos | 🟡 PARCIAL — migrar Blade → Inertia/Recharts + KPIs faltantes (ciclo médio, valor pipeline, forecast) |
| **Lead value estimado / forecast pipeline** | ❌ NÃO existe (lead não tem campo `valor_estimado`) | ✅ Pipedrive Deal Value | 🟢 NOVO — coluna `valor_estimado` + agregação total pipeline por stage |
| Integração marketplace externo (importação leads) | ✅ `CrmMarketplaceController::importLeads` | ✅ RD Station landing → CRM | 🟡 PARCIAL — funciona mas integração específica (B2B marketplace, não landing site) |
| Portal cliente (contact login) | ✅ `ContactLoginController` + `OrderRequestController` | 🟡 Bling/Conta Azul têm | ❌ NÃO duplicar — já existe |
| Campanha massa (SMS/Email broadcast) | ✅ `CampaignController@sendNotification` | ✅ todos | ❌ NÃO duplicar — só somar canal Whatsapp |
| **Tags / segmentação livre de leads** | ❌ NÃO existe (só categories rigid) | ✅ todos | 🟢 NOVO — `crm_lead_tags` morphMany flexível |
| **API REST pública pra integrar form externo (landing/site)** | 🟡 PARCIAL via Marketplace endpoint específico | ✅ todos (POST /api/leads documented) | 🟢 NOVO — endpoint público `/api/crm/leads` com `business_uuid` no token |
| Comissão por contact-person | ✅ `CrmContactPersonCommission` | 🟡 Ploomes tem | ❌ NÃO duplicar |
| Conformidade LGPD (opt-in, opt-out, direito esquecimento) | ❌ NÃO formal | ✅ RD Station consentimento explícito | 🟢 NOVO — coluna `lgpd_consent_at` + workflow esquecimento |

### Resumo discovery

- **21 controllers existentes** já cobrem CRUD básico + kanban + follow-up + proposta + dashboard
- **11 entities + 26 migrations** já modelam: lead (via contacts), schedule, call_log, proposal, marketplace, campaign
- **Stack atual = Blade + DataTables + jQuery** (NÃO migrado pra Inertia/React ainda — `migration_priority: baixa` no README)
- **Gap principal #1**: Whatsapp ↔ CRM desconectado (zero matches `grep Crm Modules/Whatsapp`)
- **Gap principal #2**: Conversão Lead → Customer não dispara FSM Sells `quote_draft` (ADR 0143 órfão)
- **Gap principal #3**: Jana IA não classifica intenção de mensagem entrante
- **Gap principal #4**: Sem lead scoring, sem forecast, sem SLA automatizado
- **Gap principal #5**: Sem motivo-perda rastreado → não responde "por que perdemos lead?" (post-mortem Gold)

---

## §1 — Visão (proposta)

CRM Pré-venda **integrado** ao oimpresso, capturando lead em **3 canais** (WhatsApp, landing site, manual/cold) → qualificando via **Jana IA + scoring simples** → convertendo em **Transaction Sells stage `quote_draft`** (ADR 0143 handoff).

**Não-duplicação**: aproveita ~80% do que JÁ existe em `Modules/Crm/` legacy (CRUD lead, follow-up, dashboard). Adiciona somente: (a) ponte Whatsapp ↔ CRM, (b) IA classificação, (c) handoff FSM Sells, (d) lead scoring + SLA + motivo-perda + tags + LGPD.

**Não-escopo**:
- ❌ Marketing automation (workflow multi-step trigger-action complexo) — fora de PME early stage; integração RD Station fica como opção D5
- ❌ Conversa Whatsapp em tempo real (inbox UI) — já é entregue por Modules/Whatsapp Inbox (US-WA-067/069)
- ❌ Substituir `Modules/Crm` legacy — estender + migrar incrementalmente MWART

---

## §2 — Cenários (proposta)

### Cenário A — Lead via WhatsApp (cliente final)

```
Cliente Larissa Cliente recebe foto banner via WhatsApp ROTA LIVRE Comercial
→ daemon Baileys CT 100 recebe → grava whatsapp_messages
→ Listener WhatsappMessageReceived dispara
→ JanaIntencaoAgent classifica intencao={lead_potencial|cliente_existente|cobranca|spam}
→ se "lead_potencial" + remetente desconhecido (sem match em contacts.mobile)
    → CrmLeadFromWhatsappService.createOrUpdate()
    → cria contacts (type=lead, crm_source=whatsapp_business_phone_id, supplier_business_name="Lead WhatsApp ${mobile}")
    → atribui via round-robin entre users com permissão crm.access_own_leads + departamento=comercial
    → SLA: cria Schedule scheduled_at=now()+30min com notify_via=['whatsapp','app']
→ se "cliente_existente" → liga conversa ao Lead/Customer existente (crm_lead_interactions)
```

### Cenário B — Lead via landing site público

```
Form embed oimpresso.com/captura-lead → POST /api/v1/crm/leads (token público com business_uuid)
→ rate-limited 60/min por IP
→ valida campos + reCAPTCHA
→ CrmLeadFromApiService.create() — fonte=landing_page, lgpd_consent_at=now()
→ aplica round-robin vendedor
→ envia email/whatsapp auto-resposta template "recebemos seu pedido"
→ Lead aparece kanban stage="Novo lead"
```

### Cenário C — Lead frio (cold outreach manual)

Já funciona via `LeadController@create` — só adicionar: tags livres + valor estimado + próximo follow-up obrigatório.

### Cenário D — Lead perdido com motivo

```
Vendedor arrasta card kanban pra coluna "Perdido"
→ Modal obrigatório motivo: preço | concorrente | timing | sem-resposta | fora-icp | outro
→ Se "concorrente": qual? (lista WR2/Mubisys/Calcgraf/Zenite)
→ Se "timing": data sugestão reabordagem
→ Salva crm_motivos_perda_log (append-only) ligado ao Lead
→ Cron diário lead:reactivate-cold pega leads "Perdido por timing" com data atingida → reabre stage="Reabordar"
```

### Cenário E — Lead virou venda → handoff FSM Sells

```
Vendedor arrasta card pra coluna "Ganho" (ou clica "Converter")
→ ConvertLeadToSaleService:
   (1) flip contacts.type='lead'→'customer' (preserva contacts.id — referencia legacy)
   (2) cria Transaction (type='sell', status='draft', contact_id=$lead->id, value=$lead->valor_estimado)
   (3) StartFsmPipelineService::start($transaction, processo='venda_com_producao')
       → seta transactions.current_stage_id = stage("quote_draft")
       → loga sale_stage_history com from_stage=NULL, action='converted_from_crm_lead'
   (4) preserva crm_lead_won_log com link bidirecional (lead_id ↔ transaction_id)
→ vendedor é redirecionado pra SaleSheet drawer com pipeline FSM já ativo
```

### Cenário F — Lead scoring automático

```
Cron diário lead:rescore (03:00 BRT):
para cada lead em (stages != Ganho/Perdido):
    score = +30 se origem=whatsapp (alta conversão histórica BR)
          + 20 se origem=indicacao
          + 15 se valor_estimado > p75 do business
          - 10 se ultimo_contato_em > 7 dias
          - 20 se ultimo_contato_em > 30 dias
          + 10 se 3+ interações em <30d
    → atualiza crm_lead_scoring (score + tier)
    → tier hot (>=70) / warm (40-69) / cold (<40)
    → SE tier mudou pra cold E vendedor tem >5 hot → realocar pra vendedor com menos hot
```

---

## §3 — Schema proposto (SOMENTE o que NÃO existe)

### 3.1 Estender `contacts` (legacy UltimatePOS)

Migration `add_crm_pipeline_columns_to_contacts.php`:

```
contacts:
+ valor_estimado            DECIMAL(15,4) NULL
+ ultimo_contato_em         DATETIME NULL  -- denormalizado pra scoring rápido
+ proximo_follow_em         DATETIME NULL  -- idem
+ sla_responder_em          DATETIME NULL  -- 30min após criar lead
+ lgpd_consent_at           DATETIME NULL
+ lgpd_opt_out_at           DATETIME NULL
+ score                     SMALLINT NULL  -- 0-100
+ score_tier                ENUM('hot','warm','cold') NULL
+ score_updated_at          DATETIME NULL
+ won_transaction_id        BIGINT NULL FK transactions.id  -- vínculo bidirecional ao ganhar
+ lost_motivo_id            BIGINT NULL FK crm_motivos_perda.id
+ lost_reabordar_em         DATETIME NULL  -- timing
```

Manter `business_id` global scope ADR 0093 — `contacts` já tem.

### 3.2 `crm_motivos_perda` (catálogo per business)

```
id BIGINT PK
business_id INT UNSIGNED INDEX FK
codigo VARCHAR(40) -- preco|concorrente|timing|sem_resposta|fora_icp|outro
label VARCHAR(120)
ativo TINYINT(1) DEFAULT 1
ordem SMALLINT
created_at, updated_at
UNIQUE(business_id, codigo)
```

Seeder padrão: 6 motivos comuns + Wagner customiza per business.

### 3.3 `crm_lead_interactions` (timeline append-only 360º)

```
id BIGINT PK
business_id INT UNSIGNED INDEX
contact_id INT UNSIGNED FK contacts.id ON DELETE CASCADE
tipo ENUM('whatsapp','email','call','meeting','note','form_submission','status_change','proposal_sent','proposal_opened')
canal_origem_id BIGINT NULL -- ref polimórfica: whatsapp_message_id, schedule_id, call_log_id, proposal_id
payload_snapshot JSON  -- snapshot leitura-fácil (preview, autor, timestamp)
user_id INT NULL -- quem realizou (NULL = sistema/cliente)
occurred_at DATETIME INDEX
created_at
-- append-only (sem updated_at, sem destroy)
INDEX(business_id, contact_id, occurred_at)
```

Hook em `whatsapp_messages.created`, `crm_call_logs.created`, `crm_schedules.completed`, `crm_proposals.sent` → grava interação.

### 3.4 `crm_lead_tags` + pivot `crm_lead_tag_lead`

```
crm_lead_tags:
  id, business_id (FK), label, color (hex), created_by

crm_lead_tag_lead (pivot):
  tag_id, contact_id
  PRIMARY (tag_id, contact_id)
```

### 3.5 `crm_lead_scoring_log` (audit history scoring)

```
id, business_id, contact_id, score_anterior, score_novo, tier_anterior, tier_novo, regras_aplicadas JSON, scored_at
```

### 3.6 `crm_lead_round_robin_state` (fila atribuição)

```
id, business_id, user_id, leads_atribuidos_count, ultima_atribuicao_at
UNIQUE(business_id, user_id)
```

---

## §4 — Pipeline FSM CRM proposto

Stages canônicas pré-Sells (que culminam em Sells FSM `quote_draft` via Cenário E):

```
[INITIAL] novo_lead
   ↓
qualificando             (vendedor inicia contato)
   ↓
qualificado              (passou critérios ICP)
   ↓
proposta_enviada         (envia Proposal — grava interação)
   ↓
negociacao               (cliente respondeu, está discutindo termos)
   ↓
[TERMINAL] ganho         → handoff ConvertLeadToSaleService → Transaction FSM "quote_draft"
[TERMINAL] perdido       → exige motivo + opcional data_reabordagem
[TERMINAL] desqualificado → fora ICP (LGPD opt-out automático opcional)
```

**Decisão de implementação**: usar **`categories.category_type='life_stage'` existente** (Modules/Crm já modela isso) + adicionar coluna `is_terminal` + `terminal_kind` (won/lost/disqualified) na tabela `categories`. NÃO criar FSM nova — economiza complexidade vs ADR 0129/0143. Justificativa: leads movem entre stages como kanban, não tem actions complexas RBAC como Sells.

Side-effect ao chegar em stage terminal:
- `ganho` → dispara `ConvertLeadToSaleService` (cria Transaction + inicia FSM Sells)
- `perdido` → modal obrigatório motivo + log
- `desqualificado` → opcional LGPD opt-out auto

---

## §5 — Integração Whatsapp + Jana

### 5.1 Listener `WhatsappMessageReceivedToCrm`

Em `Modules/Whatsapp/Listeners/`, escutar `WhatsappMessageReceived`:

```
if ($message->direction === 'inbound'):
    $lead = CrmLeadFromWhatsappService::resolve($message->business_id, $message->from_phone)
    if ($lead === null):
        $intencao = JanaIntencaoAgent::classify($message->body)  // laravel/ai
        if ($intencao === 'lead_potencial'):
            $lead = CrmLeadFromWhatsappService::create(
                business_id: $message->business_id,
                mobile: $message->from_phone,
                source: 'whatsapp_phone_id:' . $message->whatsapp_business_phone_id,
                assignee: RoundRobinService::next($message->business_id, departamento: 'comercial')
            )
            $lead->sla_responder_em = now()->addMinutes(30)
    else:
        // Lead/Cliente existente — só grava interação
        CrmLeadInteraction::create(tipo: 'whatsapp', contact_id: $lead->id, ...)
```

### 5.2 JanaIntencaoAgent (laravel/ai ADR 0035)

`Modules/Jana/Ai/Agents/IntencaoAgent.php` — input: texto da mensagem; output: classificação 1-de-N (`lead_potencial`, `cliente_existente`, `cobranca`, `spam`, `nao_classificado`). Provider default Groq Llama 3 (custo baixo) com fallback Sonnet pro HITL quando confidence <0.6.

### 5.3 Auto-resposta template

`whatsapp_templates.context='crm_first_response'` — Wagner aprova template per business. Enviado automaticamente ao criar lead via WhatsApp se `crm_settings.whatsapp_auto_reply_enabled=true`.

### 5.4 Jana lembra vendedor

Cron diário 08:00 BRT — pra cada user com perm `crm.access_own_leads`:
- Lista leads atribuídos sem contato >3d → manda DM Whatsapp (se user tem) "olá Maiara, você tem 3 leads sem resposta: João, Maria, Pedro. Quer agendar follow-up?"

---

## §6 — User Stories (US-CRM-NNN propostas)

> Estimates seguem ADR 0106 (fator 10x IA-pair + margem 2x). H = horas IA-pair Wagner+Claude.

### Bloco A — Foundation (sem features novas, só estender legacy)

#### US-CRM-001 · Migration estender `contacts` com colunas pipeline · **P0** · 2H

**Como** dev oimpresso
**Quero** colunas pipeline em `contacts` (valor_estimado, sla_responder_em, lgpd_consent_at, score, tier, won_transaction_id, lost_motivo_id)
**Para** habilitar todas US seguintes sem duplicar tabela paralela

**Implementado em:** _Modules/Crm/Database/Migrations/2026_xx_add_pipeline_columns_to_contacts.php_

**DoD:**
- [ ] Migration up/down idempotente
- [ ] Pest fixture `contacts` com biz=1 carrega novas colunas
- [ ] Skill `multi-tenant-patterns` validada (sem leak business_id)

#### US-CRM-002 · `crm_motivos_perda` catálogo + seeder padrão · **P0** · 2H

#### US-CRM-003 · `crm_lead_interactions` timeline append-only + observers · **P0** · 4H

Observers em: `WhatsappMessage`, `CrmCallLog`, `Schedule`, `Proposal` → cria interaction row. Trigger MySQL imutabilidade (ADR 0093 Tier 0).

#### US-CRM-004 · `crm_lead_tags` + pivot · **P1** · 2H

### Bloco B — Whatsapp ↔ CRM (gap principal)

#### US-CRM-010 · Listener `WhatsappMessageReceived` cria/resolve lead · **P0** · 4H

#### US-CRM-011 · `JanaIntencaoAgent` classifica intenção mensagem entrante · **P0** · 6H

Provider Groq Llama 3 default (custo <$0.001 per call) + fallback Sonnet quando confidence <0.6 — gating ADS-route (skill `ads-decision-flow`).

#### US-CRM-012 · `CrmLeadFromWhatsappService.createOrUpdate` + round-robin · **P0** · 4H

#### US-CRM-013 · Auto-resposta template WhatsApp ao criar lead · **P1** · 2H

#### US-CRM-014 · Vincular `whatsapp_conversations.contact_id` ao Lead criado · **P0** · 2H

### Bloco C — Handoff Lead → Sells FSM (gap crítico ADR 0143)

#### US-CRM-020 · `ConvertLeadToSaleService` cria Transaction + inicia FSM `quote_draft` · **P0** · 6H

Substitui `LeadController::convertToCustomer` que só flipa coluna. DoD: cria Transaction, dispara `StartFsmPipelineService` (ADR 0143), grava `won_transaction_id` em contacts, log bidirecional, Pest cobre cross-tenant.

#### US-CRM-021 · UI kanban "Ganho" dispara ConvertLeadToSale + redireciona SaleSheet · **P0** · 4H

#### US-CRM-022 · UI kanban "Perdido" modal obrigatório motivo + data reabordagem · **P0** · 4H

#### US-CRM-023 · Cron `lead:reactivate-cold` reabre leads "perdido_timing" com data atingida · **P1** · 2H

### Bloco D — Inteligência (scoring + SLA)

#### US-CRM-030 · `crm_lead_scoring_log` + cron `lead:rescore` regras simples · **P1** · 6H

#### US-CRM-031 · SLA novo lead → alerta vendedor superior se sla_responder_em vencido · **P1** · 4H

#### US-CRM-032 · Dashboard pipeline com forecast (sum valor_estimado per stage) · **P2** · 6H

### Bloco E — Captura externa

#### US-CRM-040 · API REST pública `/api/v1/crm/leads` (token `business_uuid` + reCAPTCHA + rate-limit) · **P1** · 6H

#### US-CRM-041 · Form embed JS oimpresso.com captura → POST API · **P2** · 4H

### Bloco F — LGPD + compliance

#### US-CRM-050 · `lgpd_consent_at` + opt-in/opt-out + endpoint direito esquecimento · **P0** · 6H

Bloqueador pra escala fora de WR2/ROTA LIVRE — sem isso, lead capturado sem consent vira passivo LGPD.

### Bloco G — MWART (migração Blade → Inertia/React)

#### US-CRM-060 · MWART tela Lead Kanban (atual `crm::lead.index lead_view=kanban`) · **P1** · 12H

5 fases ADR 0104 — esta tela é alta-fricção (drag-drop + ajax patch life_stage). Charter + visual comparison obrigatórios.

#### US-CRM-061 · MWART tela Lead Show (drawer cockpit pattern V2 ADR 0110) · **P2** · 12H

#### US-CRM-062 · MWART CrmDashboard com Recharts · **P2** · 10H

---

## §7 — Regras de negócio (Gherkin)

### R-CRM-001 · Multi-tenant isolation (ADR 0093 Tier 0)

```gherkin
Dado que vendedor X pertence ao business 1
Quando ele lista leads via /crm/leads OU /api/v1/crm/leads
Então só vê contacts.type='lead' WHERE business_id=1
E nunca vê contacts de business 4 (ROTA LIVRE)
```

**Testado em:** `Modules/Crm/Tests/Feature/CrmLeadCrossTenantTest` (Pest, biz=1 + biz=99 — convenção ADR refinada).

### R-CRM-002 · Lead via WhatsApp exige LGPD opt-in registrado

```gherkin
Dado mensagem WhatsApp entrante de mobile desconhecido
Quando JanaIntencaoAgent classifica "lead_potencial" E cria contacts.type='lead'
Então contacts.lgpd_consent_at = NULL (estado inicial)
E auto-resposta template inclui pergunta "topa receber atualizações? sim/não"
E somente após cliente responder "sim" → lgpd_consent_at = now()
```

### R-CRM-003 · Conversão Lead → Customer cria Transaction FSM

```gherkin
Dado lead com crm_life_stage="qualificado" e valor_estimado=R$ 5.000
Quando vendedor move card kanban pra stage="ganho"
Então ConvertLeadToSaleService cria transactions (type='sell', status='draft', contact_id=$lead->id, value=5000)
E transactions.current_stage_id = stage("quote_draft") do processo "Venda Com Produção" (ADR 0143)
E sale_stage_history registra (from_stage=NULL, to_stage="quote_draft", action='converted_from_crm_lead')
E contacts.won_transaction_id = $transaction->id
```

### R-CRM-004 · Stage "Perdido" exige motivo

```gherkin
Dado lead em qualquer stage
Quando vendedor tenta mover pra stage "perdido"
Então sistema bloqueia PATCH crm_life_stage sem payload motivo_id
E retorna 422 "motivo de perda obrigatório"
E somente com motivo_id válido salva + grava lost_motivo_id + opcional lost_reabordar_em
```

### R-CRM-005 · Round-robin atribui ao vendedor com menos leads quentes

```gherkin
Dado business 1 com 3 vendedores (Maiara, Felipe, Luiz) com perm crm.access_own_leads
E Maiara tem 5 leads hot, Felipe 2 hot, Luiz 8 hot
Quando novo lead WhatsApp chega
Então RoundRobinService::next() retorna Felipe (menor count)
E crm_lead_round_robin_state.ultima_atribuicao_at atualizado
```

### R-CRM-006 · Scoring recalcula diário

```gherkin
Dado cron lead:rescore agendado 03:00 BRT
Quando roda
Então pra cada lead em stages não-terminais, calcula score conforme regras §2 cenário F
E atualiza contacts.score, score_tier, score_updated_at
E grava crm_lead_scoring_log (append-only) com snapshot regras_aplicadas
```

### R-CRM-007 · API pública `/api/v1/crm/leads` exige token + reCAPTCHA + rate-limit

```gherkin
Dado endpoint POST /api/v1/crm/leads
Quando recebe sem header Authorization válido OU reCAPTCHA inválido OU IP >60 reqs/min
Então retorna 401/422/429 sem persistir
E somente com (token business_uuid válido + reCAPTCHA score >0.5 + dentro rate-limit) cria lead
```

### R-CRM-008 · LGPD direito esquecimento

```gherkin
Dado cliente envia request "esquecer meus dados" via WhatsApp ou form
Quando admin aprova
Então contacts.name='[REDACTED]', mobile=NULL, email=NULL
E crm_lead_interactions preserva ESTRUTURA mas payload_snapshot redact
E sale_stage_history mantém audit (LGPD Art. 7º permite preservar dados legais/fiscais)
```

---

## §8 — Decisões pendentes Wagner (mapeadas pra ADR proposta D1-D5)

1. **D1** Estender Modules/Crm legacy OU criar Modules/CrmPipeline novo? — recomendação: **estender** (preserva 21 controllers, segue ADR 0011 imitação)
2. **D2** Conversão Ganho → Transaction automática ou manual? — recomendação: **automática** (ConvertLeadToSaleService dispara FSM)
3. **D3** Lead scoring: regras simples (Sprint 1) OU treinar ML (Sprint N)? — recomendação: **regras simples primeiro**, ML após 6 meses dados
4. **D4** SLA: hard-block (não move stage até atender) OU soft-alert (avisa superior)? — recomendação: **soft-alert** (não bloquear operação)
5. **D5** Integração externa RD Station: nativa OU webhook genérico? — recomendação: **webhook genérico** (ADR 0105 cliente como sinal — só construir se cliente paga)

---

## §9 — Histórico

- **2026-05-12** — Draft inicial proposta (opus-4.7 discovery + research). Discovery encontrou 21 controllers + 11 entities + 26 migrations já existentes no Modules/Crm legacy UltimatePOS. 5 gaps principais mapeados: Whatsapp↔CRM desconectado, conversão FSM órfã, sem Jana intencao classifier, sem scoring/SLA/motivo-perda, sem LGPD compliance formal. 21 US propostas em 7 blocos. Aguarda Wagner aprovar ADR-mãe D1-D5 antes de criar tasks MCP.

---

_Última geração: 2026-05-12 — opus-4.7_
_Aprovação pendente: Wagner via ADR `memory/decisions/proposals/drafts/crm-pre-venda-pipeline.md`_
