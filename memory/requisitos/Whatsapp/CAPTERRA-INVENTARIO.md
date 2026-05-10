# CAPTERRA-INVENTÁRIO — Whatsapp

> Gerado por skill `comparativo-do-modulo` em **2026-05-10 16:30 BRT** (sobrescreveu versão anterior).
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) + [SPEC.md](SPEC.md) + `Modules/Whatsapp/` + `resources/js/Pages/Whatsapp/`.
> ADR mãe: [0089](../../decisions/0089-capterra-driven-module-evolution.md) · Estende [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) + [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md).
> Trigger: Wagner validou em prod 2026-05-10 16:00 que `/whatsapp/conversations` + `/whatsapp/settings` rendam mas detectou 2 gaps (multi-phone UI + permissions UI).

## Resumo

- ✅ APROVADO: **14** de 24 in-scope
- 🟡 PARCIAL: **2** (C-007 multi-número, C-103 mídia outbound)
- ❌ AUSENTE: **8**
- Out of scope deliberado: 5 (C-203 catalog, C-207 multi-canal, C-208 CTWA, C-301 voice, C-302 Whatsapp Pay)
- **Score ponderado oimpresso vs top mercado:** 46.5/59 (**78%**)

## Diferencial competitivo confirmado (não-replicável BSPs)

1. **Multi-tenant Tier 0** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em `WhatsappMessage`/`WhatsappConversation` (`MultiTenantIsolationTest.php`)
2. **Integração ERP nativa** — listener `NotifyRepairCustomer` (US-WA-004) + `DispatchToJanaBot` (US-WA-020) ancorados no Repair/Jana
3. **Driver fallback automático** — `WhatsappDriverHealthCheckJob` + `DriverFactory` resolve por `driver_health` (`WhatsappDriverHealthCheckJobTest.php`)
4. **Bot conversacional ancorado em Jana/Copiloto** com `ContextoNegocio` (3 ângulos faturamento — [ADR 0052](../../decisions/0052-contexto-negocio-3-angulos-faturamento.md))

## Inventário detalhado

### P0 — Obrigatórias (8 capacidades, 7✅ 1🟡)

| ID | Capacidade | Status | Evidência | Próximo passo |
|---|---|---|---|---|
| C-001 | Send template HSM | ✅ APROVADO | `MetaCloudDriver.php` + `SendWhatsappMessageJob.php` + `SendWhatsappMessageJobTest.php` + US-WA-003 [done] | — |
| C-002 | Send freeform (24h) | ✅ APROVADO | `DriverInterface::sendFreeform()` + UI input "Mensagem freeform" prod confirmado | — |
| C-003 | Receive webhook + assinatura | ✅ APROVADO | `MetaWebhookController` + `ZapiWebhookController` + `BaileysWebhookController` + `WebhookSignatureTest.php` + US-WA-010/010b [review/done] | — |
| C-004 | Status delivery | ✅ APROVADO | `MessageStatus.php` + `WhatsappSendResult.php` + `WhatsappMessageObserverTest.php` | — |
| C-005 | Inbox UI | ✅ APROVADO | `Conversations/Index.tsx` + `Show.tsx` + ConversationList/Sidebar/Thread; smoke prod 2026-05-10 confirma render com mensagens reais | — |
| C-006 | Templates manager | ✅ APROVADO | `Templates/Index.tsx` + `TemplatesController` + `FetchTemplatesTest.php` + US-WA-013 [done] | — |
| **C-007** | **Multi-número per-business** | **🟡 PARCIAL** | Multi-tenant ✅ (`MultiTenantIsolationTest`). **Multi-phone:** schema migrated PR1 (`whatsapp_business_phones` + `WhatsappBusinessPhone.php` + `WhatsappPhoneUserAccess.php` + `PhonesMigrationDataTest`) MAS UI ainda single (`Settings.tsx` único, sem `Settings/Index.tsx` + `Settings/Edit.tsx`). DriverFactory recebe `WhatsappBusinessConfig` (legado), não `WhatsappBusinessPhone`. | **US-WA-040 [doing]** PR2-4 ainda pendentes |
| C-008 | HMAC signature webhook | ✅ APROVADO | `WebhookSignatureTest.php` + `VerifyMetaSignature` middleware | — |

### P1 — Competitivas (8 capacidades, 3✅ 1🟡 4❌)

| ID | Capacidade | Status | Evidência | Próximo passo |
|---|---|---|---|---|
| C-101 | Bot conversacional (Jana) | ✅ APROVADO | `DispatchToJanaBotTest.php` + US-WA-020 [done] (PolicyEngine ADS 4 outcomes) | — |
| C-102 | HITL handoff bot↔humano | ✅ APROVADO | Botão "Atribuir a mim" + `awaiting_human` status (US-WA-020) | — |
| C-103 | Mídia outbound (img/PDF/audio) | 🟡 PARCIAL | `DriverInterface::sendMedia()` existe; UI sem upload; backlog US-WA-032 | **Criar US-WA-NEW-MIDIA-OUT** P1 |
| C-104 | Mídia inbound | ❌ AUSENTE | Sem código + backlog US-WA-033 | **Criar US-WA-NEW-MIDIA-IN** P1 |
| C-105 | Atribuição conversa | ✅ APROVADO | UI "Atribuir a mim" + `assignedTo` em conversation | — |
| C-106 | Tags / labels conversa | ❌ AUSENTE | Sem schema, sem UI | **Criar US-WA-NEW-TAGS** P2 |
| C-107 | Métricas custo/deflection | ❌ AUSENTE | Schema `whatsapp_conversation_metricas` declarado em SPEC mas migration **não criada**; service `WhatsappMetricasService` não existe; **US-WA-021 [todo]** | **Acelerar US-WA-021** P1 — gap importante pra ROI visibility |
| C-108 | Quick replies / atalhos | ❌ AUSENTE | Sem código, backlog | **Criar US-WA-NEW-QUICKREP** P2 |

### P2 — Diferenciais (10 capacidades, 3✅ 5❌ 2 out-of-scope)

| ID | Capacidade | Status | Evidência | Próximo passo |
|---|---|---|---|---|
| C-201 | Botões interativos (CTA) | ❌ AUSENTE | Backlog US-WA-030; HSM com CTAs | **Criar US-WA-030 ativa** P2 |
| C-202 | List messages (cardápio) | ❌ AUSENTE | Backlog US-WA-031 | **Criar US-WA-031 ativa** P2 |
| C-203 | Catalog / commerce | ❌ OUT OF SCOPE | Decisão deliberada SPEC §7 | — |
| C-204 | Pix Copia-e-Cola | ❌ AUSENTE | Backlog US-WA-038 — depende RecurringBilling US-RB-044 v2 | **Aguardar RB v2** |
| C-205 | NFe/boleto anexo | ✅ APROVADO | Cross-module: `Modules/RecurringBilling/Listeners/AnexarBoletoNFe.php` (US-RB-044) + diferencial único | — |
| **C-206** | **Integração ERP nativa transactional** | ✅ APROVADO (DIFERENCIAL ÚNICO) | `NotifyRepairCustomer.php` (Repair status `ready/waiting_parts`) + `DispatchToJanaBot.php` + `BillingNotificationListener` cross-module | — |
| C-207 | Multi-canal SMS+Email+WA | ❌ OUT OF SCOPE | Whatsapp-first deliberado | — |
| C-208 | Click-to-Whatsapp Ads | ❌ OUT OF SCOPE | Não fazemos ads | — |
| C-209 | A/B testing templates | ❌ AUSENTE | Backlog | **Criar US-WA-NEW-AB** P3 |
| C-210 | Customer 360 | ✅ APROVADO | Via `Contact` UltimatePOS Sprint 1 (relacionamento `whatsapp_conversations.contact_id`) | — |

### P3 — Futuro (4 capacidades, 1✅ 1❌ 2 out-of-scope)

| ID | Capacidade | Status | Próximo passo |
|---|---|---|---|
| C-301 | Voice chamadas | ❌ OUT OF SCOPE | — |
| C-302 | Whatsapp Pay BR | ❌ OUT OF SCOPE (Pix cobre) | — |
| C-303 | IA generativa próprias (LLM-built bot) | ✅ APROVADO | Via Jana/Copiloto (S3) — ADRs 0035-0053 |
| C-304 | Voice transcription inbound | ❌ AUSENTE | **Criar US-WA-NEW-WHISPER** P3 (whisper.cpp CT 100) |

## Gaps de governança interna detectados (skill `module-completeness-audit` Tier B)

> Gaps que **não** aparecem na FICHA Capterra (mercado não tem como diferencial) mas violam a Constituição v2 §4 (loop fechado por métrica).

| # | Gap | Evidência | Próximo passo |
|---|---|---|---|
| G-1 | **Permissions UI per-phone-number** — Wagner não consegue dar acesso só ao "número Comercial" pra Felipe e só "número Financeiro" pra Eliana | Schema `whatsapp_phone_user_access` migrated PR1 (US-WA-040), mas **sem UI**. Tela `Settings/Edit.tsx` (PR3 US-WA-040) deveria expor multi-select. ACL backend em PR4 ainda pendente | **Acelerar US-WA-040 PR3+PR4** ou criar **US-WA-041 ACL UI separada** P1 |
| G-2 | **CAPTERRA-FICHA seções v2 vazias** — `ux_heuristics: []` + `automation_targets: []` (TODO Wagner curate desde 2026-05-07) | FICHA L196-221: ambos eixos comentados como TODO | **Criar TASK Wagner-curate** — 30min: pesquisar 3-5 heurísticas P0 UX (cliques, tempo) + 3-5 automações P0 (listener Repair, Job RB, etc) |
| G-3 | **Charter Settings v2 não migrated pra multi-phone** — `Settings.charter.md` v1 ainda tem Non-Goal "1 número/business" (US-WA-040 PR3 vai remover) | `resources/js/Pages/Whatsapp/Settings.charter.md` ainda v1 | Parte de US-WA-040 PR3 |
| G-4 | **AUDIT-LOG.md do módulo não existe** — skill `module-completeness-audit` Tier B (criada 2026-05-10) requere | `memory/requisitos/Whatsapp/AUDIT-LOG.md` ausente | Criar shell vazio + apender 1ª entrada nesta auditoria |

## Tasks propostas (aguardando aprovação Wagner)

> **NÃO criar tasks sem confirmação humana** ([publication-policy](../../../.claude/skills/publication-policy/SKILL.md)).

### P1 — Competitivas (gap mercado real, ROI direto)

1. **[P1] US-WA-NEW-METRICAS** — Acelerar `US-WA-021` (do `[todo]` pra `[doing]`) — métricas custo/deflection/tempo resposta. _Evidência: SPEC §6 declara, schema declarado mas sem migration; ROI visibility pra Wagner justificar Whatsapp/business._
2. **[P1] US-WA-NEW-MIDIA-OUT** — Mídia outbound (anexar imagem/PDF nas conversas). _Evidência: `DriverInterface::sendMedia()` existe, falta UI upload em `Conversations/Show.tsx`; bloqueador pra US-RB-044 v2 (boleto auto-anexo)._
3. **[P1] US-WA-NEW-MIDIA-IN** — Mídia inbound (cliente envia foto pra orçar). _Evidência: webhook recebe payload `image/document/audio` mas job descarta hoje; gap em `ProcessIncomingWebhookJob`._
4. **[P1] US-WA-041 PERMS-UI** — UI dedicada de gestão de acesso per-phone (multi-select atendentes em `Settings/Edit.tsx`). _Evidência: schema `whatsapp_phone_user_access` existe, sem UI; gap detectado por Wagner em prod 2026-05-10._

### P2 — Diferenciais

5. **[P2] US-WA-030** — Botões interativos (HSM com CTAs). _Backlog SPEC §8._
6. **[P2] US-WA-031** — List messages (cardápio gráfica: orçar / acompanhar OS / segunda via). _Backlog SPEC §8 — fit perfeito Modules/ComunicacaoVisual._
7. **[P2] US-WA-NEW-TAGS** — Tags/labels conversa (P2). _Mercado pattern: classificar conversa por dept/etapa._
8. **[P2] US-WA-NEW-QUICKREP** — Quick replies/atalhos (P2). _Mercado pattern: respostas pré-definidas atendente._

### P3 — Futuro

9. **[P3] US-WA-NEW-AB-TEMPLATE** — A/B testing templates (P3). _Diferencial enterprise._
10. **[P3] US-WA-NEW-WHISPER** — Voice transcription inbound (whisper.cpp local CT 100). _Backlog SPEC §8._

### Governança interna (skill `module-completeness-audit`)

11. **[P0] TASK-FICHA-V2** — Wagner-curate `ux_heuristics:` + `automation_targets:` na CAPTERRA-FICHA.md. _Evidência: L196-221 ambos `[]` desde 2026-05-07._
12. **[P0] TASK-AUDIT-LOG-INIT** — Criar `memory/requisitos/Whatsapp/AUDIT-LOG.md` shell + apender entrada desta auditoria + entrada Wagner em prod 2026-05-10. _Pré-req da skill `module-completeness-audit`._

---

> **Como aprovar:** responder com `aprovo 1,2,4,11` ou `aprovo P0+P1` ou `aprovo todos` ou `nenhum`.
>
> Após aprovação, eu (Claude):
> - Crio tasks via tool MCP `tasks-create` (uma por aprovada)
> - Apendo blocos `### US-WA-NEW-*` ao SPEC.md (seção "Backlog vindo do Capterra-Inventário")
> - Commit + push pro `webhook GitHub→MCP` propagar
