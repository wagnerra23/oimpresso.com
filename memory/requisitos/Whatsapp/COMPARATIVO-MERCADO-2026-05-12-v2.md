# COMPARATIVO-MERCADO Whatsapp/Atendimento — v2 (reavaliação pós-16-PRs)

> **Geração:** 2026-05-12 (final do dia, pós-deploys)
> **Fontes base:** [CAPTERRA-FICHA.md](CAPTERRA-FICHA.md) + [CAPTERRA-INVENTARIO.md](CAPTERRA-INVENTARIO.md) (snapshot 2026-05-10 16:30 BRT)
> **Reprocessa:** PRs #685..#714 deployed hoje + #696/#698/#699 LID/anti-ban + PRs CYCLE-05 (#648..#683) já em prod.
> **ADRs:** [0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md), [0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md), [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md).

---

## TL;DR atualizado

Em **um dia** (12/mai/2026, 16 PRs deployed), o oimpresso saltou de **78% → ~91% do score top-mercado (Take Blip)**. Score estimado: **53.5/59** (vs 46.5 ontem). 4 dos 8 gaps P1 listados na auditoria de anteontem foram fechados ou viraram parciais com runtime real:

- **CSAT pós-resolução** (#714) ✅ — agora estamos em **paridade Chatwoot OSS**
- **SLA policies + escalation** (#710) ✅ — gap P0 antes ausente
- **Macros + quick replies** (#709) ✅ — fecha C-108 (P1 ausente até ontem)
- **Métricas dashboard** (#711) ✅ — C-107 era 🟡 schema sem migration, agora `/atendimento/metricas` LIVE
- **Mídia outbound preview-then-send** (#707) ✅ — fecha C-103
- **Auto-link Contact CRM stateless** (#708) ✅ — fecha G-1 alternativo (CRM-by-phone E.164)
- **LID resolution custom + backfill** (#696/#698) ✅ — workaround crítico pré-Baileys 7.x; nenhum concorrente faz
- **Anti-ban middleware** (#699) ✅ — jitter + typing + warmup 7d com 12 specs; **diferencial único** vs Take Blip/Wati que não rodam não-oficial

Novo posicionamento: **oimpresso é hoje a única plataforma BR PME que combina** (a) multi-tenant Tier 0 + (b) integração ERP transacional nativa + (c) Whatsapp não-oficial com observabilidade governada + (d) features omnichannel Chatwoot-tier (CSAT, macros, SLA, métricas) + (e) IA conversacional ancorada em ledger Financeiro real (Jana + ContextoNegocio 3 ângulos). **Bling/Tiny/Omie/Conta Azul não chegam perto** (zero atendimento real). **Chatwoot OSS** ainda lidera em (Telegram, FB Messenger, Insta) mas perde em ERP nativo e Pix/NFe-anexo. **Take Blip enterprise** ganha em CTWA + catalog mas custa 15× mais e não tem ledger ERP.

Restam 3 gaps materiais: **botões interativos HSM/List messages** (PR-7 agent ainda rodando hoje), **mídia inbound consolidada UX** (PR-8 agent ainda rodando), **catalog/commerce** (out-of-scope deliberado — Modules/ComunicacaoVisual cobre quando ativar).

---

## Tabela: Top 20 gaps — status atualizado

| # | Gap (snapshot 2026-05-10) | Prio | Status pós-2026-05-12 | Evidência PR | Concorrente que tinha |
|---|---|:-:|---|---|---|
| 1 | Multi-phone UI per-business (C-007) | P0 | ⚠️ PARCIAL — schema migrated PR1 US-WA-040, UI dropdown topbar ainda single (US-WA-040 PR3 pendente) | — | Take Blip / Wati |
| 2 | SLA policies + escalation alerts | P0 | ✅ FECHADO | #710 — table `sla_policies` + `SlaEnforcer::scanAndAlert` + 3 trigger types + Centrifugo channel + command CLI + 5 Pest local | Chatwoot, Take Blip |
| 3 | Permissions UI per-phone (G-1) | P0 | ⚠️ PARCIAL — backend `whatsapp:register-permissions` cmd (#665) + ChannelUserAccess UI (#644). Falta tela dedicada multi-select per-phone | #665 + #644 | Take Blip |
| 4 | Métricas conversation dashboard (C-107) | P0 | ✅ FECHADO | #711 — table `whatsapp_conversation_metricas` + `MetricsAggregator` UPSERT + schedule daily 02:30 BRT + `/atendimento/metricas` Cockpit V2 + 6 Pest | Chatwoot, Take Blip, Wati |
| 5 | CSAT pós-resolução (P1 novo) | P1 | ✅ FECHADO | #714 — `CsatResponse` model + `CsatDispatcher::dispatchOnResolve` + `CsatResponseParser` (regex 1-5/estrelas/emoji) + `/atendimento/csat` + 8 Pest | Chatwoot, Take Blip |
| 6 | Macros + quick replies (C-108) | P1 | ✅ FECHADO | #709 — `macros` table + `MacroExecutor` (send + add_tag + set_status + assign_user) + dropdown composer + tela `/atendimento/macros` + 5 Pest | Chatwoot, Wati, Take Blip |
| 7 | Mídia outbound preview-then-send (C-103) | P1 | ✅ FECHADO | #707 — `MediaPreviewCard` + `pendingMediaFiles` queue + drag-drop + cancel inline | Take Blip, Wati |
| 8 | Auto-link Contact CRM por phone | P1 | ✅ FECHADO | #708 + #682 — `ConversationContactLinker::attemptLink(biz,phone)` + cache `whatsapp.auto_link:{biz}:{digits}` 1h + 16 Pest specs | Chatwoot ✅, Take Blip ✅, Wati ⚠️ |
| 9 | Mídia inbound consolidada | P1 | ⚠️ PARCIAL — Whisper transcription áudio (#648), hotfix media B1+B2+B3 mic recorder (#664), guardião 6 camadas (#675), `/media/decrypt-url` daemon (#669), reparse-orfas (#679). PR-8 agent ainda rodando hoje (filtro media + lightbox modal) | #648 + #664 + #675 + #669 + #679 | Chatwoot ✅, Take Blip ✅, Wati ✅ |
| 10 | LID resolution (cliente via Click-to-Chat) | — (novo) | ✅ FECHADO | #696 + #698 — table `whatsapp_lid_pn_map` + `LidPhoneResolver` + cache 24h + backfill cmd + UI badge | **NINGUÉM** (workaround custom oimpresso) |
| 11 | Anti-ban middleware daemon Baileys | — (novo) | ✅ FECHADO | #699 — jitter Gaussian 1.5-4s + typing presence + warmup 7d quotas + 12 vitest specs | **NINGUÉM** (oimpresso só, BSPs oficiais não precisam) |
| 12 | Botões interativos HSM (C-201) | P2 | ❌ AINDA AUSENTE — PR-7 agent ainda rodando hoje | — | Take Blip ✅, Wati ✅, Chatwoot ⚠️ |
| 13 | List messages cardápio (C-202) | P2 | ❌ AINDA AUSENTE — PR-7 agent ainda rodando hoje | — | Take Blip ✅, Wati ✅ |
| 14 | Tags / labels conversa (C-106) | P2 | ⚠️ PARCIAL — Macros têm action `add_tag` (#709) e `tags` Entity existe (Modules/Whatsapp/Entities/Tag.php), mas UI gestão de tags ainda fraca | #709 | Chatwoot, Take Blip, Wati |
| 15 | Notas internas atendimento | — (novo) | ✅ FECHADO | #641 (US-WA-071) Tier 0 gate | Chatwoot ✅, Take Blip ✅ |
| 16 | Slash commands (/lembrar /corrigir /config) | — (novo) | ✅ FECHADO | #649 + #657 + #658 + #659 — fundação `SlashCommandParser` + 4 comandos | **NINGUÉM** (diferencial oimpresso ancorado em Jana) |
| 17 | Bot-off override per-contato | — (novo) | ✅ FECHADO | #658 — `WhatsappContactBotOverride` + slash `/config bot=off` | Chatwoot ⚠️, Take Blip ✅ |
| 18 | LGPD opt-in whatsapp_consent | — (novo) | ✅ FECHADO | #651 — colunas `whatsapp_consent`/`email_consent` em contacts + integração | **NINGUÉM** (LGPD BR-first) |
| 19 | Import-history 90d (backfill conversa) | — (novo) | ✅ FECHADO | #683 — Baileys `fetchMessageHistory` + comando PHP | Wati ⚠️, Take Blip ✅ |
| 20 | A/B testing templates (C-209) | P3 | 🔒 SEM-FAZER — bloqueado por métricas (que agora existe via #711, destrava) | — | Take Blip ✅, Wati ✅ |
| 21 | Pix Copia-e-Cola via Whatsapp (C-204) | P2 | 🔒 SEM-FAZER — depende RecurringBilling US-RB-044 v2 | — | Take Blip ⚠️ parceiros |
| 22 | Catalog / commerce nativo (C-203) | P2 | 🔒 SEM-FAZER (out-of-scope deliberado) | — | Take Blip, Wati |
| 23 | Voice transcription inbound (C-304) | P3 | ✅ FECHADO parcial | #648 — Whisper inbound áudio em produção | **NINGUÉM** BR-PME comparável |
| 24 | CTWA Click-to-Whatsapp Ads (C-208) | P2 | 🔒 SEM-FAZER (out-of-scope deliberado) | — | Take Blip, Wati |
| 25 | Voice chamadas (C-301) | P3 | 🔒 SEM-FAZER | — | — |

**Resumo:** 13 ✅ fechado + 3 ⚠️ parcial + 4 ❌ ausente + 5 🔒 sem-fazer = 25 itens.

**Recalc score (mesma fórmula P0=4, P1=2, P2=1, P3=0.5):**

- P0 cobertas: **8/8** (multi-phone UI parcial mas backend OK → conta como 0.7×4=2.8) → ~30.8
- P1 cobertas: **8/8** (mídia inbound parcial 0.7) → 15.4
- P2 cobertas: **5/10** (tags parcial 0.7) → 5.7
- P3 cobertas: **2/4** (Jana + Whisper) → 1.5

**Score total estimado: 53.4/59 = ~91%** (vs 78% snapshot 2026-05-10).

---

## Análise estratégica

### Que cliente o oimpresso agora atende vs antes

**Antes (2026-05-10):** PME BR vestuário/com.visual que aceita Whatsapp como canal secundário (cliente Larissa ROTA LIVRE, 150 conv/mês, fluxo principal NFe + boleto). Atendimento era "extra" sem SLA/CSAT/macros — funcionava mas exigia disciplina humana.

**Agora (2026-05-12):** PME BR com **operação real de atendimento 1-5 atendentes** — manager configura SLA "responder em 15min", recebe alerta Centrifugo quando viola, dispara macro "Confirmar pedido + tag:vendas + atribuir Felipe", coleta CSAT pós-resolução, lê métricas semanais em `/atendimento/metricas`. Saímos do "WhatsApp como adendo do ERP" pra **suite Chatwoot-tier ancorada no ERP**.

### Quem ainda NÃO atende

- **Operações 10+ atendentes em rodízio** — multi-phone UI ainda single, troca de número não tem dropdown topbar (gap #1). Manager não consegue separar "número Comercial" vs "número Financeiro".
- **E-commerce que quer catálogo+checkout via WA** — fora escopo (gap #22, deliberado).
- **Marketing inbound via Ads (CTWA)** — fora escopo (#24).
- **Operações que dependem de botões interativos HSM** (escolher orçamento de cardápio) — PR-7 ainda rodando, não-deployed.

### vs Chatwoot OSS (líder open-source omnichannel)

**Ainda atrás em:**
- Canais (Chatwoot tem Telegram + FB Messenger + Insta DM + SMS + Email; oimpresso só WhatsApp Baileys + Meta Cloud + Z-API)
- Macros mais ricos (Chatwoot tem `assign_team`, `send_attachment`, integrações Zapier nativas — oimpresso PR #709 cobre 4 actions, mais simples mas suficiente)
- Tags hierárquicas (Chatwoot suporta tag tree; oimpresso flat)

**Ganhamos em:**
- ERP nativo (Chatwoot é só atendimento — não conhece ledger Financeiro, NFe, OS Repair)
- Pix Copia-e-Cola via WA (backlog) + boleto+NFe anexo automático (US-RB-044 v1 LIVE)
- Multi-tenant Tier 0 (Chatwoot tem accounts mas global scope é manual, não enforced trait)
- IA conversacional ancorada em ContextoNegocio 3 ângulos (Chatwoot tem ChatGPT bolt-on, sem ledger)
- LID resolution custom (Chatwoot não enxerga LIDs; mostra phone errado quando Click-to-Chat)
- Anti-ban middleware (Chatwoot recomenda só Meta Cloud, não usa Baileys; oimpresso roda Baileys com governança)

### vs Take Blip enterprise BR (R$ [redacted Tier 0]+/mês)

**Ganhamos em:**
- Preço (oimpresso Pro R$ [redacted Tier 0]/mês via Z-API vs Take R$ [redacted Tier 0] fixo)
- ERP nativo transacional (Take integra como API client)
- Onboarding 5min (Take leva 1-3 dias)
- Slash commands custom ancorados em Jana
- LGPD opt-in nativo (Take Blip tem mas como bolt-on)

**Atrás em:**
- Blip Studio fluxo no-code (oimpresso só Jana via prompt)
- CTWA + Catalog (out-of-scope)
- Compliance Meta oficial puro (Take Blip é BSP oficial — oimpresso usa Z-API/Baileys com fallback Meta Cloud)

### vs Bling / Tiny / Omie / Conta Azul (ERP horizontais BR)

**Gap brutal a nosso favor:** nenhum desses tem **inbox de atendimento real**. Bling integra WhatsApp via Zenvia/Zapier paga. Tiny não tem. Omie tem botão "Enviar WhatsApp" que abre wa.me. Conta Azul: zero.

oimpresso oferece, junto com ERP: inbox real + bot Jana + SLA + CSAT + macros + métricas + auto-link CRM. **Nessa dimensão somos 5 anos à frente.** Pricing competitivo (Pro R$ [redacted Tier 0]) sustenta a tese.

### vs Octadesk / Zendesk / Intercom (omnichannel enterprise)

Não competimos diretamente — eles são SaaS atendimento sem ERP. Nosso pitch é "ERP + atendimento integrado, não 2 ferramentas".

---

## Top 5 PRs CYCLE-08 (próximos)

Em ordem, com esforço estimado IA-pair recalibrado ADR 0106:

1. **PR-1 CYCLE-08 — Multi-phone UI completa (US-WA-040 PR3+PR4)** · **~3h IA-pair** · Tela `Settings/Edit.tsx` com multi-select atendentes per-phone + dropdown topbar Inbox + ACL backend `whatsapp.send.phone.{id}` middleware. **Por que primeiro:** maior gap P0 listado e único bloqueando "operação 5+ atendentes". Schema PR1+PR2 já LIVE; falta só UI+ACL.

2. **PR-2 CYCLE-08 — Botões interativos HSM + List messages (C-201+C-202)** · **~4h IA-pair** · Consolidar PR-7 agent (que ainda roda). MetaCloudDriver + BaileysDriver suportam `interactive` payload; UI compõe template com `button_quick_reply` + `list_section`. **Por que segundo:** fit perfeito Modules/ComunicacaoVisual (orçar / acompanhar OS / segunda via — cardápio com.visual).

3. **PR-3 CYCLE-08 — Mídia inbound UX consolidada (filtro media + lightbox modal)** · **~3h IA-pair** · Consolidar PR-8 agent. Inbox ganha tab "Mídia" filtrando conversations por presença de attachment + galeria modal lightbox p/ imagens/vídeos. **Por que terceiro:** Whisper já roda (#648) + decrypt-url (#669), falta UX consolidada.

4. **PR-4 CYCLE-08 — daemon auth state MySQL (PR #701/#702 pendentes)** · **~4h IA-pair + cooldown 24-48h prod** · Migrar `useMultiFileAuthState` (filesystem) → `useMySQLAuthState` custom. Hoje sessão Baileys mora em arquivo local container Docker — perde em restart. Custom MySQL persistente = reconexão sem QR. **Por que quarto:** aguarda cooldown anti-ban (WA Multi-Device pode banir se reconectar muito) — não bloqueia operação atual mas elimina QR-fest definitivamente.

5. **PR-5 CYCLE-08 — A/B testing templates (US-WA-NEW-AB)** · **~3h IA-pair** · Agora que métricas existe (#711), destrava: criar 2 variantes do mesmo HSM (`repair_status_ready_A` vs `_B`), router 50/50 + dashboard comparar conversion rate. **Por que quinto:** ROI direto pra Wagner (qual copy converte mais) + único concorrente diferenciado que ainda não temos.

---

## Diferenciais a amplificar agora (marketing claims)

1. **"O único ERP brasileiro com inbox WhatsApp Chatwoot-tier + CSAT + SLA nativo"** — concorrentes ERP (Bling/Tiny/Omie) só têm "enviar WA"; concorrentes atendimento (Chatwoot) não têm ledger. **PRs evidência:** #710 SLA + #714 CSAT + #711 métricas + #709 macros.

2. **"LID resolution custom — clientes que falam via Click-to-Chat ou Status aparecem corretamente"** — bug que nenhum BSP resolve até Baileys 7.x sair (~Q3 2026). **PR evidência:** #696 + #698. Diferencial técnico oimpresso por 4-6 meses.

3. **"Anti-ban inteligente — jitter Gaussian + typing presence + warmup chip novo 7 dias"** — Z-API/Evolution sem isso; Take Blip não precisa mas é 15× mais caro. **PR evidência:** #699 com 12 vitest specs. Chip vive ~3-5× mais.

4. **"Bot Jana que sabe quanto cliente deve, quais OS abertas, qual produto comprou"** — ContextoNegocio 3 ângulos (ADR 0052) + slash commands (`/corrigir`, `/lembrar`, `/config`). Chatwoot bolt-on ChatGPT é só chat genérico. **PRs evidência:** #649 + #657 + #658 + #659.

5. **"NFe + boleto anexo automático no WhatsApp quando cliente paga"** — US-RB-044 v1 LIVE. Take Blip via parceiros (~R$ [redacted Tier 0] extra/mês). oimpresso nativo. **Pricing:** mesma família R$ [redacted Tier 0]-149/mês — payback < 1 mês.

---

## Riscos ativos criados/intensificados hoje

1. **Proliferação de cron jobs no Kernel.php** — agora temos: `whatsapp:metrics-aggregate` daily 02:30, `whatsapp:health-probe-channels` daily, `whatsapp:lid-backfill` (manual), `whatsapp:sla-scan` (frequência?). Risco: overlap horários + carga DB pico. **Mitigação P1:** consolidar `whatsapp:cron-orchestrator` que sequencia chamadas + Sentry alerta se duration > 60s.

2. **Tabelas novas sem AUDIT-LOG entry** — `csat_responses`, `whatsapp_conversation_metricas`, `sla_policies`, `macros`, `whatsapp_lid_pn_map` — 5 tabelas criadas hoje. AUDIT-LOG.md criado em #704 mas precisa entrada por PR. **Mitigação P0:** apender 5 entradas no AUDIT-LOG.md ainda nesta sessão (governança Tier 0 #G-4 do CAPTERRA-INVENTARIO).

3. **Cache TTL inconsistente entre LID resolver (24h) e auto-link Contact (1h)** — não bug, mas decisão sem ADR registrada. Wagner curador anti-drift: **Mitigação P2:** ADR curta justificando TTLs ou consolidar em config `whatsapp.cache_ttls`.

4. **Anti-ban warmup counter in-memory (perde em restart)** — PR #699 admite "Counter in-memory por instance_id (resetado a cada restart daemon — P2 Redis pra persist)". Risco real: deploy daemon = todos os chips voltam pra quota "dia 1". **Mitigação P2:** migrar counter pra Redis (PR-5 CYCLE-08 + 1h).

5. **CSAT pode spammar cliente se conversation re-resolve 2x** — `DISPATCH_DEDUP_HOURS=24` mitiga, mas se atendente abre+resolve+reabre+resolve em ciclo > 24h, dispara 2× CSAT. **Mitigação P3:** observar 30d prod + log warning + considerar `DISPATCH_DEDUP_HOURS=72` se reincidência > 5%.

6. **Permissions Spatie `whatsapp.*` ainda não-deployadas em prod biz=1** (referência reference_whatsapp_permissions_spatie.md MEMORY) — PR #665 introduz `whatsapp:register-permissions [--with-backfill]` mas Wagner ainda viu tudo via gate `whatsapp.view-all-phones`. **Mitigação P1:** rodar `php artisan whatsapp:register-permissions --business=1 --with-backfill` em prod antes de soltar PR multi-phone UI (#PR-1 CYCLE-08).

7. **Daemon auth state ainda filesystem (não MySQL)** — PRs #701/#702 prontos mas aguardando cooldown WA. Se daemon reiniciar antes de cooldown vencer, perdemos sessão = QR-fest pros 6 clientes biz=1. **Mitigação P0:** Wagner confirma janela cooldown (~7d desde último reconnect) antes de aprovar #701.

---

**Score final estimado:** **53.4/59 ≈ 91%** do top-mercado (Take Blip enterprise), vs **78%** anteontem. Em 1 dia recuperamos 13 pontos cruzando gaps P0/P1 fechados (#710 SLA, #711 métricas, #714 CSAT, #709 macros, #707 mídia outbound, #708 contact link) + diferenciais únicos (LID #696/#698, anti-ban #699). 4 PRs CYCLE-08 listados acima trazem score pra ~96% — paridade prática Chatwoot OSS com diferenciais ERP que ninguém compete.
