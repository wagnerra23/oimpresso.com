# CAPTERRA-INVENTÁRIO — Whatsapp

> **Última atualização 2026-05-15 14:00 BRT** (refresh via D-11 governance backfill — sincroniza com [COMPARATIVO-MERCADO-2026-05-12-v2.md](COMPARATIVO-MERCADO-2026-05-12-v2.md) pós-16-PRs CYCLE-07).
> Versão anterior: 2026-05-10 16:30 BRT (sobrescrita).
> Fontes: [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) + [SPEC.md](SPEC.md) + `Modules/Whatsapp/` + `resources/js/Pages/Whatsapp/` + `resources/js/Pages/Atendimento/`.
> ADR mãe: [0089](../../decisions/0089-capterra-driven-module-evolution.md) · Estende [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) + [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md) + [ADR 0135](../../decisions/0135-omnichannel-inbox-arquitetura.md).
> Trigger: 16 PRs CYCLE-07 deployed 2026-05-12 + 11 PRs saga daemon 2026-05-13 + 8 PRs maratona 2026-05-14/15 (incident anti-cross-contact + Baileys 7.x).

## Resumo

- ✅ APROVADO: **19** de 24 in-scope (+5 vs snapshot 2026-05-10)
- 🟡 PARCIAL: **3** (C-007 multi-phone UI parcial, C-104 mídia inbound UX consolidada parcial, C-106 tags UI ainda fraca)
- ❌ AUSENTE: **2** (C-201 botões interativos UX, C-202 list messages UX — backend pronto, UI compositor parcial)
- Out of scope deliberado: 5 (C-203 catalog, C-207 multi-canal real, C-208 CTWA, C-301 voice, C-302 WA Pay)
- **Score ponderado oimpresso vs top mercado:** 53.4/59 (**91%**) — vs **78%** snapshot 2026-05-10

### Evolução em 5 dias

| Momento | Score | Δ | O que mudou |
|---|---|---|---|
| 2026-05-10 16:30 | 46.5/59 = **78%** | baseline | snapshot inicial pós-prod validation Wagner |
| 2026-05-12 19:00 | 53.4/59 = **91%** | **+13pp** | 16 PRs CYCLE-07 deployed (SLA, CSAT, macros, métricas, mídia, LID, anti-ban, contact link) |
| 2026-05-13 23:30 | 53.4/59 = **91%** | 0pp | 11 PRs saga daemon (recovery, observability, history-sync) — não somam capability mas estabilizam infra |
| 2026-05-15 07:00 | 53.4/59 = **91%** | 0pp | 8 PRs maratona (anti-cross-contact + Baileys 7.x + deploy Hostinger) — defense-in-depth, não +capability |
| **Próxima onda CYCLE-08** | **~57/59 = ~96%** | **+5pp** (estimado) | 4 PRs CYCLE-08: multi-phone UI completa (#1) + botões interativos UX (#2) + mídia inbound UX (#3) + A/B templates (#5) |

## Diferencial competitivo confirmado (não-replicável BSPs)

> **Atualizado 2026-05-15** — 7 diferenciais únicos catalogados pós-CYCLE-07 + maratona Baileys 7.x.

1. **Multi-tenant Tier 0 IRREVOGÁVEL** ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)) — global scope `business_id` em Channel + Conversation + Message + tabelas auxiliares (Tag, Macro, CsatResponse, SlaPolicy, LidPhoneMap). Convention test cobertura 100%.
2. **Integração ERP nativa transacional** — listeners `NotifyRepairCustomer` (Repair status `ready/waiting_parts`) + `DispatchToJanaBot` (US-WA-020 PolicyEngine ADS 4 outcomes) + `BillingNotificationListener` cross-module RecurringBilling. **Único BR PME que faz** — Bling/Tiny/Omie zero integração transacional.
3. **Driver fallback automático healthcheck** — `WhatsappDriverHealthCheckJob` + `DriverFactory` resolve por `driver_health` (`degraded/disconnected/banned`) → flip Z-API/Baileys → Meta Cloud automaticamente.
4. **Bot conversacional Jana ancorado em `ContextoNegocio`** ([ADR 0052](../../decisions/0052-contexto-negocio-3-angulos-faturamento.md)) — 3 ângulos faturamento (orçamento/produção/faturado) — bot que sabe quanto cliente deve, quais OS abertas, qual produto comprou. Chatwoot bolt-on ChatGPT é só chat genérico.
5. **LID resolution custom** (US-WA-093) — workaround "1 LID @lid ≠ 1 pessoa" no Baileys 6.7.x via tabela `whatsapp_lid_pn_map` + Service `LidPhoneResolver` + cache Redis 24h + backfill cmd. **NINGUÉM faz isso** — diferencial técnico oimpresso ~4-6 meses até Baileys 7.x maduro.
6. **Anti-ban middleware daemon Baileys** (PR #699) — Box-Muller Gaussian jitter 1.5-4s + typing presence + warmup 7d quotas progressive + circadian quiet hours 02-06 BRT. **Z-API/Evolution sem isso** — Take Blip não precisa (oficial) mas custa 15× mais. Chip vive ~3-5× mais.
7. **Schema 3-identifiers anti-cross-contact** (PR #855 + #864 incident 2026-05-14) — `conversations.lid` + `phone_e164` + `bsuid` + 10 testes regression convention/E2E. **Concorrentes BR não fazem** — defense-in-depth Tier 0.

### Diferenciais secundários (LGPD + slash commands)

8. **LGPD opt-in nativo** (`whatsapp_consent` + `email_consent` em contacts, PR #651) — Chatwoot/Take Blip não fazem BR-first.
9. **Slash commands ancorados em Jana** (PRs #649, #657-#659) — `/lembrar`/`/corrigir`/`/lembrete`/`/config` (4 comandos) treinam Jana via sinais reais do atendente. Diferencial único.
10. **Pix Copia-e-Cola + NFe + boleto auto-anexo** (US-RB-044 v1 LIVE) — sai pelo WhatsApp quando cliente paga. Take Blip via parceiros (~R$ 200 extra/mês).
11. **CSAT pós-resolução BR-first** (PR #714) — 1-5 estrelas via emoji `⭐`, parser `CsatResponseParser` regex flexível, dashboard `/atendimento/csat`. Paridade Chatwoot OSS.
12. **SLA policies + escalation alerts** (PR #710) — `sla_policies` table + `SlaEnforcer::scanAndAlert` cron + 3 trigger types + Centrifugo channel + CLI cmd. Paridade Chatwoot OSS.

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

---

## §Refresh 2026-05-15 — gaps remanescentes 78% → 91% → ~96% (CYCLE-08)

> Sincronização desta seção com [COMPARATIVO-MERCADO-2026-05-12-v2.md](COMPARATIVO-MERCADO-2026-05-12-v2.md) §Top 5 PRs CYCLE-08. Não duplica conteúdo — só rastreia status.

### O que mudou entre 78% (10/mai) e 91% (12/mai)

13 das 16 capabilities listadas no INVENTARIO original viraram ✅ ou 🟡 graças aos 16 PRs CYCLE-07:

| Antes (78% snapshot) | Agora (91%) | PR canon |
|---|---|---|
| C-103 Mídia outbound 🟡 PARCIAL | ✅ APROVADO | #707 preview-then-send drag-drop |
| C-104 Mídia inbound ❌ AUSENTE | 🟡 PARCIAL | #648 Whisper + #664 mic + #675 6-layer guard + #669 decrypt-url + #679 reparse-orfas. UX consolidada filtro+lightbox modal pendente (CYCLE-08 #3) |
| C-106 Tags ❌ AUSENTE | 🟡 PARCIAL | #547+#581 Tag CRUD + filtro `?tags=` + seed defaults. UI gestão dedicada `/atendimento/tags` ausente |
| C-107 Métricas ❌ AUSENTE | ✅ APROVADO | #711 `whatsapp_conversation_metricas` + Aggregator + cron 02:30 + `/atendimento/metricas` Cockpit |
| C-108 Quick replies ❌ AUSENTE | ✅ APROVADO | #709 macros + 4 actions + composer dropdown + tela `/atendimento/macros` |
| C-201 Botões interativos ❌ AUSENTE | 🟡 PARCIAL | #715 backend HSM + List msgs OK. #720 dialog UI OK. UX compositor template polish pendente |
| C-202 List messages ❌ AUSENTE | 🟡 PARCIAL | Mesmo PR #715 backend OK. UX cardápio dedicado pendente |
| C-209 A/B templates ❌ AUSENTE | 🔒 DESTRAVADO | #719 A/B variants macros (subset). A/B HSM template completo destravado pelas métricas #711 — CYCLE-08 #5 |
| Permissions UI per-phone (G-1) | 🟡 PARCIAL | #644 ChannelUserAccess UI + #665 register-permissions cmd. Tela dedicada multi-select per-phone pendente — CYCLE-08 #1 |

### Novidades não-listadas na 78% snapshot

5 capabilities NOVAS criadas em CYCLE-07 sem prévio backlog Capterra (gaps reais que mercado tem mas oimpresso não havia detectado):

| Novidade | PR | Status atual | Comparável |
|---|---|---|---|
| **SLA policies + escalation** (gap P0 novo) | #710 | ✅ APROVADO | Chatwoot OSS / Take Blip |
| **CSAT pós-resolução** | #714 | ✅ APROVADO | Chatwoot OSS / Take Blip |
| **Auto-link Contact CRM por phone E.164** (gap P1 novo) | #708+#682 | ✅ APROVADO | Chatwoot ✅, Take Blip ✅ |
| **LID resolution custom** (workaround pré-Baileys 7.x) | #696+#698 | ✅ APROVADO | **NINGUÉM** |
| **Anti-ban middleware** | #699 | ✅ APROVADO | **NINGUÉM** (BSPs oficiais não precisam) |

### Gaps remanescentes (CYCLE-08 alvo ~96%)

5 PRs CYCLE-08 catalogados em [COMPARATIVO §"Top 5 PRs CYCLE-08"](COMPARATIVO-MERCADO-2026-05-12-v2.md) com prioridade IA-pair recalibrada (ADR 0106):

| # | Gap | Estimate IA-pair | Score impact | Status |
|---|---|---|---|---|
| **1** | Multi-phone UI completa (US-WA-040 PR3+PR4) — `Settings/Edit.tsx` + ACL `whatsapp.send.phone.{id}` | ~3h | +1.5pp | em progresso `doing` |
| **2** | Botões interativos UX + List messages UX consolidado | ~4h | +1pp | aguarda decisão Wagner |
| **3** | Mídia inbound UX consolidada (filtro + lightbox) | ~3h | +1pp | aguarda decisão |
| **4** | Daemon auth state MySQL (PRs #701/#702) — eliminar QR-fest | ~4h + 24-48h cooldown | 0pp (operacional) | bloqueado cooldown WA |
| **5** | A/B testing templates HSM (US-WA-049 completa, destrava por #711 métricas) | ~3h | +0.5pp | aguarda decisão |

**Total CYCLE-08:** ~13h IA-pair + Wagner manual (deploy CT 100 Baileys 7.x + canary 7d biz=99). Score estimado: **~57/59 = ~96%**.

### Out-of-scope deliberado (não conta como gap)

- ❌ Catalog/commerce nativo WA (Modules/ComunicacaoVisual cobre quando ativar — ADR 0121 modular especializado)
- ❌ Click-to-WhatsApp Ads (CTWA — não fazemos ads)
- ❌ Whatsapp Pay BR (Pix via Asaas/Inter cobre)
- ❌ Voice chamadas (não viável BR PME)
- ❌ Multi-canal real Telegram/FB Messenger/Email/SMS (Caixa Unificada v4 UI prepara preview-only — drivers reais Sprint futuro condicionado a sinal qualificado ADR 0105)

### Roadmap auto-cadastro contact (D-8 não-spec'ado formalmente)

Estado-da-arte 2026-05-14 ([session](../../sessions/2026-05-14-arte-auto-cadastro-contact-whatsapp.md)) deu nota **38/100** vs 8 concorrentes globais (Intercom, Twilio Conversations, Zendesk SC, etc). Top 3 gaps P0 priorizados por impacto×esforço:

1. **Identity Resolution proativa** (Twilio pattern) — match exact phone E.164 + opt-in merge sem auto-attribution → 2-3 PRs ~6h IA-pair → 38→55
2. **Default cria lead novo sem auto-attribuir** (Intercom pattern) — toda conversa nova de phone desconhecido cria `Contact` shell pendente review (não atribui pra Contact existente sem confirmação) → 1 PR ~3h → 55→65
3. **Política oficial NÃO matchar por phone** (Zendesk pattern pós-incident 2023) — bloquear `LIKE %tail4` permanente, só `=`/`bsuid`/`session_id` → já parcialmente feito (PR #854 suffix-8), formalizar como policy documentada → 1 PR ~1h → 65→70

Roadmap não-spec'ado ainda — Wagner decide quando promover pra US formais.

---

**Última auditoria via skill `module-completeness-audit` Tier B:** 2026-05-15 14:00 BRT (D-11 governance backfill). Próxima auditoria recomendada: pós-CYCLE-08 close (estimativa Q3 2026 quando 4 PRs CYCLE-08 + canary biz=99 maduros).
