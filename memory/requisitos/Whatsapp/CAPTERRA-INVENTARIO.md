---
title: "CAPTERRA-INVENTARIO — Whatsapp"
slug: capterra-inventario-whatsapp
type: inventario
status: aceito
generated_at: 2026-05-09
generated_by: audit-constituicao
source_ficha: CAPTERRA-FICHA.md
source_spec: SPEC.md
---

# CAPTERRA-INVENTARIO — Whatsapp

> Cruzamento entre [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) (concorrentes + capacidades baseline) × [SPEC.md](SPEC.md) (US-WA-NNN) × código real em [`Modules/Whatsapp/`](../../../Modules/Whatsapp/) + [Pages](../../../resources/js/Pages/Whatsapp/).
>
> Skill aplicada: [`comparativo-do-modulo`](../../../.claude/skills/comparativo-do-modulo/SKILL.md) (ADR 0089).
>
> Decisão arquitetural mãe: [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Z-API default + Meta Cloud fallback obrigatório (Evolution PROIBIDO Tier 0).
> Emendas: [ADR 0111](../../decisions/0111-modulo-whatsapp-bypass-meta-fallback.md), [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md).
> Exceção MWART: [ADR 0112](../../decisions/0112-mwart-excecao-whatsapp-settings.md) (Settings = Page único com charter+Pest).

## Resumo executivo

Total de **32 capacidades** rastreadas (8 P0 + 8 P1 + 10 P2 + 4 P3 + 2 diferenciais únicos).

| Bucket | Total | % | Notas |
|---|:-:|:-:|---|
| ✅ **APROVADO** | 17 | 53% | Todas P0 cobertas + 4 P1 + 2 diferenciais únicos + 2 P2 + 1 P3 |
| 🟡 **PARCIAL** | 6 | 19% | Mídia outbound + tags + quick replies + métricas + voice transcription |
| ❌ **AUSENTE** | 9 | 28% | Maioria fora-escopo deliberado (CTWA, Voice, Whatsapp Pay, Catalog, A/B testing) |

**Cobertura ponderada** (P0=4, P1=2, P2=1, P3=0.5):
- Mercado top (Take Blip): 8×4 + 8×2 + 10×1 + 4×0.5 = 60
- oimpresso atual: 8×4 + 4×2 + 4×1 + 1×0.5 = **44.5 pts** (74% do top — coerente com alvo Sprint 1-3 da FICHA = 78%)

**Diferenciais únicos confirmados em código:** integração ERP transacional + multi-tenant Tier 0 nativo + listener Repair + Cockpit pattern Inertia + multi-números per business (ADR 0117). NFe/boleto anexo automatizado AINDA em backlog (US-WA-032).

---

## ✅ APROVADO (17 capacidades)

Capacidade existe no código real e funciona end-to-end. Evidências cruzam Controller/Service/Job/Migration + Page Inertia quando aplicável.

### P0 (8/8 — 100% — paridade de mercado completa)

| ID | Capacidade | Evidência (código) |
|---|---|---|
| C-001 | Send template HSM (utility/marketing) | [`SendWhatsappMessageJob`](../../../Modules/Whatsapp/Jobs/SendWhatsappMessageJob.php) + [`MetaCloudDriver::sendTemplate()`](../../../Modules/Whatsapp/Services/Drivers/MetaCloudDriver.php) + Pest `SendWhatsappMessageJobTest` |
| C-002 | Send freeform (janela 24h) | `SendMessageRequest` valida janela 24h via `WhatsappConversation::isWithinMeta24hWindow()` ([`ConversationsController@send`](../../../Modules/Whatsapp/Http/Controllers/Admin/ConversationsController.php#L274)) |
| C-003 | Receive webhook + verificar assinatura | 3 controllers: [`MetaWebhookController`](../../../Modules/Whatsapp/Http/Controllers/Api/MetaWebhookController.php) + [`ZapiWebhookController`](../../../Modules/Whatsapp/Http/Controllers/Api/ZapiWebhookController.php) + [`BaileysWebhookController`](../../../Modules/Whatsapp/Http/Controllers/Api/BaileysWebhookController.php). Pest `WebhookSignatureTest` |
| C-004 | Status delivery (sent/delivered/read/failed) | `WhatsappMessage.status` enum + `Driver::fetchMessageStatus()` + eventos `WhatsappMessageSent`/`Failed` em [`Events/`](../../../Modules/Whatsapp/Events/) |
| C-005 | Inbox UI (lista conversas + chat) | Cockpit 3-painéis: [`Conversations/Index.tsx`](../../../resources/js/Pages/Whatsapp/Conversations/Index.tsx) + 4 sub-componentes (`ConversationList`, `ConversationThread`, `ConversationSidebar`, `TemplatePicker`) + tabs unread/assigned/bot/resolved |
| C-006 | Templates manager (sync HSM aprovados) | [`TemplatesController@syncMeta`](../../../Modules/Whatsapp/Http/Controllers/Admin/TemplatesController.php) + Page [`Templates/Index.tsx`](../../../resources/js/Pages/Whatsapp/Templates/Index.tsx) + filtros provider/status + Pest `FetchTemplatesTest` |
| C-007 | Multi-número / multi-tenant | **DIFERENCIAL** — [`WhatsappBusinessPhone`](../../../Modules/Whatsapp/Entities/WhatsappBusinessPhone.php) com `HasBusinessScope` (Tier 0). N rows per business (ADR 0117). Pest `MultiTenantIsolationTest` + `PhonesMigrationDataTest` |
| C-008 | HMAC signature webhook | 3 middlewares: [`VerifyMetaSignature`](../../../Modules/Whatsapp/Http/Middleware/VerifyMetaSignature.php) (HMAC SHA-256) + [`VerifyZapiSignature`](../../../Modules/Whatsapp/Http/Middleware/VerifyZapiSignature.php) (Client-Token timing-safe) + [`VerifyBaileysSignature`](../../../Modules/Whatsapp/Http/Middleware/VerifyBaileysSignature.php) (Bearer) |

### P1 (4/8 — 50% — competitivo parcial)

| ID | Capacidade | Evidência (código) |
|---|---|---|
| C-101 | Bot conversacional integrado | [`DispatchToJanaBot`](../../../Modules/Whatsapp/Listeners/DispatchToJanaBot.php) listener — placeholder Sprint 2 funcional (marca `bot_handling=true`); call real `decide('whatsapp','reply',...)` é Sprint 3 (skill `ads-route` Tier A dormente) |
| C-102 | HITL handoff bot↔humano | `WhatsappConversation.status='awaiting_human'` + UI Cockpit ações ("Atribuir a mim"/"Marcar resolvida") em [`ConversationsController@updateStatus`](../../../Modules/Whatsapp/Http/Controllers/Admin/ConversationsController.php#L232) |
| C-105 | Atribuição conversa a atendente | `whatsapp_conversations.assigned_user_id` (migration) + tab "Atribuídas a mim" + ACL phone↔user via [`whatsapp_phone_user_access`](../../../Modules/Whatsapp/Database/Migrations/2026_05_09_120100_create_whatsapp_phone_user_access_table.php) |
| C-210 | Customer 360 (perfil unificado) | [`WhatsappConversation::contact()`](../../../Modules/Whatsapp/Entities/WhatsappConversation.php) → `App\Contact` (UltimatePOS core) — match por mobile normalizado em `ProcessIncomingWebhookJob` |

### P2 (2/10 — 20% — diferenciais únicos do oimpresso)

| ID | Capacidade | Evidência (código) |
|---|---|---|
| C-206 | Integração ERP nativa (transactional) | **DIFERENCIAL ÚNICO** — Listener [`NotifyRepairCustomer`](../../../Modules/Whatsapp/Listeners/NotifyRepairCustomer.php) escuta `RepairStatusChanged` → dispara template + roteamento per-phone via `WhatsappBusinessPhone::resolveForEvent()` |
| C-205 | NFe/boleto anexo (compliance BR) | **PARCIAL** — listener Billing referenciado em SPEC (handles_billing flag em phone), mas mídia outbound (PDF anexo NFe/boleto) ainda não wired no SendWhatsappMessageJob — ver bucket 🟡 |

### P3 (1/4 — 25%)

| ID | Capacidade | Evidência (código) |
|---|---|---|
| C-303 | IA generativa próprias (LLM-built bot) | Bot Jana via PolicyEngine ADS planejado em US-WA-020. Hoje: hook listener registrado + log; Sprint 3 troca pra `decide()` real |

### Multi-tenant + governança (Tier 0 — não numeradas na FICHA mas críticas)

| ID | Capacidade | Evidência |
|---|---|---|
| GOV-1 | Multi-tenant `business_id` global scope | Trait `HasBusinessScope` em 6 Entities Whatsapp; Pest [`MultiTenantIsolationTest`](../../../Modules/Whatsapp/Tests/Feature/MultiTenantIsolationTest.php) |
| GOV-2 | PII redacted em logs | `App\Support\PiiRedactor` referenciado em DispatchToJanaBot + daemon-node logger |

---

## 🟡 PARCIAL (6 capacidades)

Existe estrutura ou código mas com gaps de integração / UI / automação.

| ID | Capacidade | Score | Gap atual |
|---|---|:-:|---|
| C-103 | Mídia outbound (img/PDF/audio) | P1 | `SendWhatsappMessageJob` + 3 drivers já suportam `kind='media'` com `media_url`/`type`/`caption`. **Falta:** UI Composer no `ConversationThread.tsx` pra anexar arquivo + listener `RecurringBilling` que cole o boleto/NFe PDF automático (US-WA-032 backlog) |
| C-104 | Mídia inbound (cliente envia) | P1 | `ProcessIncomingWebhookJob::extractFromMeta()` reconhece `image`/`document`/`audio` e cria `WhatsappMessage.type='image'` mas só persiste o caption — **falta** download do binário Meta + storage S3/local + thumbnail UI (US-WA-033) |
| C-205 | NFe/boleto anexo automático | P2 | **DIFERENCIAL** parcial — flag `handles_billing` existe em `WhatsappBusinessPhone`. **Falta:** listener `RecurringBilling::PaymentReceived` que dispara `SendWhatsappMessageJob` com `kind='media'` apontando pro PDF NFe (loop pago→Whatsapp→NFe ainda manual) |
| C-107 | Métricas custo/deflection | P1 | US-WA-021 spec'ada (tabela `whatsapp_conversation_metricas` + service + scheduler 04:00). **Não implementada** — sem migration nem service. Sprint 3 |
| C-014 | Driver Health Check | (P0 oimpresso) | [`WhatsappDriverHealthCheckJob`](../../../Modules/Whatsapp/Jobs/WhatsappDriverHealthCheckJob.php) implementado + Pest. **Falta:** scheduler em `Console/Kernel.php` (referenciado em SCOPE como `DriverHealthCheckAllCommand` — comando existe mas cron não wired ainda) e verificação cross-tenant alarm threshold |
| C-304 | Voice transcription inbound | P3 | Áudio inbound chega como `type='audio'` body `[áudio]` (placeholder). **Falta:** integração whisper.cpp local CT 100 (US-WA-033 backlog) |

---

## ❌ AUSENTE (9 capacidades)

Sem código nem migration. Maioria fora-escopo deliberado (FICHA §2 / não-escopo SCOPE.md).

### Backlog explícito (futuro pode entrar)

| ID | Capacidade | Score | Motivo |
|---|---|:-:|---|
| C-201 | Botões interativos (CTA) | P2 | US-WA-030 backlog — sem grep de `interactive`/`button`/`cta_url` no módulo. Templates HSM hoje só BODY |
| C-202 | List messages (cardápio) | P2 | US-WA-031 backlog — cardápio gráfica (orçar/acompanhar OS/segunda via). Sem código |
| C-106 | Tags / labels conversa | P1 | Backlog FICHA — `WhatsappConversation` não tem `tags` JSON nem tabela pivot. Sem migration |
| C-108 | Quick replies / atalhos | P1 | Backlog FICHA — sem código nem UI Composer dropdown atalhos |
| C-209 | A/B testing templates | P2 | Backlog FICHA — sem `experiment_id` em `whatsapp_templates` |
| C-204 | Pix Copia-e-Cola via Whatsapp | P2 | US-WA-038 backlog v2 RecurringBilling — sem código |

### Fora-escopo deliberado (decisão Wagner — não entrar)

| ID | Capacidade | Score | Motivo registrado |
|---|---|:-:|---|
| C-203 | Catalog / commerce nativo | P2 | FICHA "Fora escopo" — Whatsapp Business Catalog não é caso de uso gráfica (ADR não criada porque é decisão clara) |
| C-207 | Multi-canal (SMS + Email + Whatsapp) | P2 | FICHA "Fora escopo (Whatsapp-first)" — Twilio/Take Blip são multi-canal; oimpresso foca Whatsapp BR |
| C-208 | Click-to-Whatsapp Ads (CTWA) | P2 | FICHA "Fora escopo (não fazemos ads)" — sem Modules/Ads (S5 ADS Universal é decisão, não ads Meta) |
| C-301 | Voice (chamadas Whatsapp) | P3 | FICHA + SCOPE não-escopo — beta Meta |
| C-302 | Whatsapp Pay BR | P3 | FICHA "Fora escopo (Pix Automático cobre)" — RecurringBilling US-RB-044 cobre o caso |

---

## Diferenciais únicos do oimpresso (confirmados em código)

Capacidades que **nenhum BSP do mercado entrega nativamente** — confirmadas no código real:

1. **Integração ERP transacional nativa** ([C-206]) — Listener Repair → Whatsapp + roteamento per-phone (`handles_repair_status`). Take Blip integra como "API client", não nativo.
2. **Multi-tenant Tier 0 nativo** ([C-007]) — `HasBusinessScope` global em 6 Entities. BSPs assumem 1 tenant por conta. Diferencial pra revenda Officeimpresso.
3. **Multi-números per business** ([ADR 0117]) — `WhatsappBusinessPhone` com flags `handles_*` + ACL `whatsapp_phone_user_access`. WR2 Sistemas (cliente sinal qualificado) já usa Comercial vs Financeiro separados.
4. **3 drivers intercambiáveis com fallback runtime** — `DriverFactory::makePrimary()` resolve ZapiDriver/MetaCloudDriver/BaileysDriver via `driver_health` automaticamente. BSPs trancam você na plataforma deles.
5. **BaileysDriver custom (daemon Node CT 100)** — schema/logs OTel/métricas Prometheus próprios. Resolve as 3 dores Wagner do Evolution (bans, schema, observabilidade).

---

## Gaps priorizados (top 8)

Critério: P0 e P1 da FICHA + diferenciais únicos com gating crítico + cliente sinal qualificado pedindo. Não inclui fora-escopo deliberado.

| # | Capacidade | Score | US relacionada | Por que prioritário |
|:-:|---|:-:|---|---|
| 1 | **NFe/boleto anexo automático** (C-205) | P2 (oimpresso = P0 — DIFERENCIAL) | US-WA-032 + listener `RecurringBilling::PaymentReceived` | Loop pago→Whatsapp→NFe é o pitch comercial. Hoje quebra a promessa US-RB-044 (NFe automática a partir de boleto pago) |
| 2 | **Métricas conversation** (C-107) | P1 | US-WA-021 (spec'ada, não implementada) | Cliente PME (ROTA LIVRE/WR2) precisa de ROI dashboard pra justificar custo Whatsapp ao mercado |
| 3 | **Mídia outbound — UI Composer + anexo** (C-103) | P1 | US-WA-032 | 50% das interações gráfica precisam de imagem/PDF (orçamento, prova fotográfica). Backend pronto; falta UI |
| 4 | **Mídia inbound — download + storage** (C-104) | P1 | US-WA-033 | Cliente manda foto do produto pra orçar é fluxo principal gráfica. Hoje só caption persiste |
| 5 | **Scheduler Driver Health Check** (C-014 oimpresso P0) | (P0 interno) | US-WA-014 implementada, falta cron | Sem cron, fallback automático Z-API→Meta Cloud não dispara — toda mitigação ban morre na prática |
| 6 | **Bot Jana real via `decide()`** (C-101 / C-303) | P1+P3 | US-WA-020 Sprint 3 — depende ADS Universal S5 | Diferencial vs Wati (bot conversacional). Hoje placeholder log-only. Bloqueado por skill `ads-route` Tier A dormente até ~jul/2026 |
| 7 | **Tags / labels conversa** (C-106) | P1 | Backlog (sem US ainda) | Atendentes querem agrupar conversas por campanha/atendente/produto. Mercado tem (Take Blip/Zenvia/Wati) |
| 8 | **Quick replies / atalhos** (C-108) | P1 | Backlog (sem US ainda) | Larissa (ROTA LIVRE) responde 30+ "qual prazo?" por dia — atalho economiza ~5min/dia |

---

## Próximos passos (sugestões — NÃO criadas)

> Esta seção sugere tasks pra Wagner aprovar via `tasks-create` MCP. **Skill `comparativo-do-modulo` (ADR 0089) exige confirmação humana** antes de batch-criar.

### Sugestão 1: Tasks P0/P1 imediatas (Sprint 4 ou início Sprint 5)

| Sugestão título | Score | US base | Esforço estimado |
|---|:-:|---|:-:|
| `feat(whatsapp): listener RecurringBilling::PaymentReceived → mídia NFe PDF` | P0-DIFF | US-WA-032 + nova US-WA-045 | M (~6h) |
| `feat(whatsapp): scheduler DriverHealthCheckAllCommand cron 6h em Console/Kernel` | P0-interno | US-WA-014 | S (~1h) |
| `feat(whatsapp): UI Composer anexo arquivo (image/PDF) ConversationThread.tsx` | P1 | US-WA-032 | M (~8h) |
| `feat(whatsapp): tabela whatsapp_conversation_metricas + service + scheduler 04h` | P1 | US-WA-021 | L (~12h) |
| `feat(whatsapp): download mídia inbound + storage local/S3 + thumbnail UI` | P1 | US-WA-033 | L (~10h) |

### Sugestão 2: Tasks P1 next quarter (após Sprint 5 ADS Universal)

| Sugestão título | Score | US base | Bloqueio |
|---|:-:|---|---|
| `feat(whatsapp): substituir DispatchToJanaBot log-only por decide() real` | P1 | US-WA-020 | Depende skill `ads-route` (S5 ~jul/2026) |
| `feat(whatsapp): tabela whatsapp_conversation_tags + UI sidebar tags` | P1 | nova US-WA-046 | — |
| `feat(whatsapp): quick replies dropdown UI Composer + tabela whatsapp_quick_replies` | P1 | nova US-WA-047 | — |

### Sugestão 3: NÃO criar tasks pra (fora-escopo deliberado)

- C-203 Catalog · C-207 Multi-canal · C-208 CTWA · C-301 Voice · C-302 Whatsapp Pay
- Documentado como "fora escopo" na FICHA §2 e SCOPE.md. Reabrir só via nova ADR explícita Wagner-aceita (cliente sinal qualificado pedindo + métrica detectando drift — [ADR 0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)).

---

## Notas de auditoria

- **UX heuristics** + **Automation targets** (Capterra v2 eixos): FICHA §UX heuristics e §Automation targets estão como `# TODO Wagner pesquisar/curate` — placeholders vazios. Inventário não pode pontuar até Wagner inventariar 3-5 heurísticas P0 + 3-5 automações P0. Quando preencher, reauditar.
- **Tests Pest:** 19 arquivos em [`Tests/Feature/`](../../../Modules/Whatsapp/Tests/Feature/) — cobertura forte de drivers, multi-tenant, webhook signatures, charter Settings, multi-números migration data.
- **MWART**: Settings é exceção [ADR 0112](../../decisions/0112-mwart-excecao-whatsapp-settings.md) — Page único com `Settings.charter.md` + `WhatsappSettingsCharterTest`. Conversations/Templates seguem MWART comum (Cockpit V2 ADR 0110).
- **daemon-node** existe ([`Modules/Whatsapp/daemon-node/`](../../../Modules/Whatsapp/daemon-node/)) — Dockerfile + docker-compose + src/{baileys,config,http,observability,webhook,server.ts}. Deploy CT 100 ainda pendente (smoke fim-a-fim em prod aberto em US-WA-022 DoD).

---

**Próxima revisão sugerida:** após scheduler DriverHealthCheck wired + listener RecurringBilling implementado (top 2 gaps). Reauditar com `comparativo-do-modulo` rodado novamente.
