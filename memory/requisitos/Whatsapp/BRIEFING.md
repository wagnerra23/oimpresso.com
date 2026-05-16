# BRIEFING — Whatsapp / Atendimento

> **Mantido por:** skill `brief-update` (Tier B auto-trigger) + Wagner review
> **Atualizado:** 2026-05-16 (Wave M agent boost — Service facade + 3 charters + governance D3.c/D4)
> **Próximo update esperado:** quando próximo PR `feat/perf/fix(whatsapp)` mergear

---

## 1. O que é

**URL principal:** [oimpresso.com/atendimento/inbox](https://oimpresso.com/atendimento/inbox)
**Backend:** `Modules/Whatsapp/` · 1700+ linhas InboxController + 11 outros Controllers
**Frontend:** `resources/js/Pages/Atendimento/` + `resources/js/Pages/Whatsapp/_components/`

Inbox omnichannel polimórfico (ADR 0135 Fase 0) — multi-canal por design com WhatsApp Baileys/Z-API/Meta Cloud ativos + IG/FB/Email/SMS/ML preparados (preview-only). **Único ERP BR PME com inbox real ancorado em ledger Financeiro + IA conversacional (Jana) + Pix/NFe/boleto auto-anexo.**

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | **~95%** | 2026-05-15 (1 P0 parcial: multi-phone UI; resto verde) |
| Capterra score vs top-mercado (Take Blip) | **53.4/59 = 91%** | 2026-05-12 (COMPARATIVO-v2) |
| Diferencial competitivo ERP-nativo | **~100%** | 2026-05-15 (7 diferenciais únicos catalogados) |
| Cobertura SPEC formal (US done/spec) | **43%** | 2026-05-15 (D-1 governance backfill aplicado) |
| Documentação canon (SPEC + AUDIT-LOG + CAPTERRA + BRIEFING) | **~95%** | 2026-05-15 (BRIEFING inaugural + skill `brief-update` ativa) |
| Deploy/ops (prod biz=1) | **~100%** | 2026-05-15 (CT 100 Baileys 7.x daemon LIVE 09:00 BRT) |
| **Perf SPA-feel cross-página** | **~85%** | 2026-05-15 (7 páginas com `Inertia::defer`; JanaTemplates eager OK; outras Pages oimpresso ainda eager) |
| **Governance D3.c — charter ratio** | **~62%** | 2026-05-16 (6 charters live: Inbox + CaixaUnificada + JanaTemplates + Channels/Index + Macros/Index + Metricas/Index — Wave M boost) |
| **Governance D4 — Service ratio** | **~75%** | 2026-05-16 (`InboxQueryService` facade adicionado — agrega leituras Inbox/CaixaUnificada/DataController; 22 Services em `Modules/Whatsapp/Services/`) |

## 3. Capacidades hoje (paridade Chatwoot OSS + ERP-nativo)

- **Canais:** WhatsApp Baileys daemon CT 100 (7.0.0-rc11 LIVE) + Z-API SaaS + Meta Cloud fallback automático healthcheck (US-WA-014)
- **Atendimento:** Inbox 3-col (lista + thread + sidebar) · ACL canal/fila per-user (US-WA-069) · atalhos `J/K/E/A/?` · status (open/awaiting_human/resolved/archived)
- **Conteúdo:** Mídia outbound preview-then-send (PR #707) · mídia inbound + Whisper transcrição (PR #648/#664/#675/#669/#679) · 4 templates HSM Cloud API · botões interativos backend (PR #715/#720) · list messages cardápio
- **Automação:** Macros + quick replies CRUD (PR #709 — 4 actions) · slash commands `/lembrar`/`/corrigir`/`/lembrete`/`/config` ancorados Jana (PRs #649/#657/#658/#659) · SLA policies + escalation alerts (PR #710) · CSAT pós-resolução 1-5★ (PR #714)
- **CRM/360:** Auto-link Contact UltimatePOS por phone E.164 (PR #708/#682/#870) · ContactObserver invalidação cache (PR #870) · tags classificadoras + seed defaults (PR #547/#581) · LGPD opt-in nativo (PR #651)
- **Real-time:** Centrifugo WebSocket + polling fallback 5s defense-in-depth (US-WA-066) · OTel tracing + Prometheus alerts (Onda 1+2 PR #834/#835)
- **Métricas:** Dashboard `/atendimento/metricas` (custo/deflection/tempo resposta) · `/atendimento/csat` (PR #711/#714)
- **Anti-drift:** `whatsapp:auth-state-drift-check` cron daily (PR #869) · `whatsapp:channels-reconcile` cron 5min (PR #824)
- **Perf SPA-feel:** `Inertia::defer()` em 7 páginas (Inbox + Channels Index/Show + Macros + MacroVariants + Csat + Metricas + Templates) — switch página 300→50ms validado D-14

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — global scope em Channel/Conversation/Message + tabelas auxiliares. Convention test 100% cobertura.
2. **Integração ERP transacional** — listeners `NotifyRepairCustomer` + `DispatchToJanaBot` + `BillingNotificationListener` cross-module. **Único BR PME** — Bling/Tiny/Omie zero integração transacional.
3. **Driver fallback healthcheck automático** — flip Z-API/Baileys → Meta Cloud quando `driver_health` degrada.
4. **Bot Jana com `ContextoNegocio` 3 ângulos** (ADR 0052) — sabe quanto cliente deve, OS abertas, produto comprado. Chatwoot bolt-on ChatGPT é só chat genérico.
5. **LID resolution custom** (US-WA-093) — workaround "1 LID @lid ≠ 1 pessoa" Baileys 6.7.x via `whatsapp_lid_pn_map` + Service + cache Redis 24h + backfill cmd. **Ninguém faz** — diferencial técnico ~4-6 meses até Baileys 7.x maduro.
6. **Anti-ban middleware daemon** (PR #699) — Box-Muller Gaussian jitter + typing presence + warmup 7d quotas + circadian quiet hours. Chip vive 3-5× mais.
7. **Schema 3-identifiers anti-cross-contact** (PR #855/#864/#870 incident 2026-05-14) — `lid` + `phone_e164` + `bsuid` + ContactObserver cache invalidation + 10 testes regression. **Concorrentes BR não fazem.**

## 5. Gaps remanescentes (CYCLE-08 → ~96%)

| # | Item | Esforço | Score impact |
|---|---|---|---|
| 1 | Multi-phone UI completa (US-WA-040 PR3+PR4) | 3h IA-pair | +1.5pp |
| 2 | Botões interativos UX + List messages UX consolidado | 4h IA-pair | +1pp |
| 3 | Mídia inbound UX consolidada (filtro+lightbox modal) | 3h IA-pair | +1pp |
| 4 | A/B testing templates HSM (destrava por #711 métricas) | 3h IA-pair | +0.5pp |
| 5 | **Auto-cadastro contact 38→70** (estado-da-arte 2026-05-14, 3 PRs P0) | 10h IA-pair | +5pp estratégico |
| 6 | Caixa Unificada v4 frontend redesign (F3 design handoff Claude Design) | 7-9h IA-pair | UX/visual |

Total CYCLE-08: **~30h IA-pair** → score estimado **~96%**.

## 6. Bloqueadores manuais Wagner

- ⏳ **Wagner-curate CAPTERRA-FICHA v2** `ux_heuristics` + `automation_targets` (~30min — G-2 aberto)
- ⏳ **Aplicar defer cross-módulo** (Sells/Repair/Crm/Financeiro/OficinaAuto/Vestuario/ProjectMgmt/Ponto/NfeBrasil) — 9 módulos pendentes spawn de agents paralelos
- ✅ ~~Deploy CT 100 Baileys 7.x~~ (fechado 2026-05-15 09:00)
- ✅ ~~Re-pareamento canal id=8 prod biz=1~~ (fechado 09:25)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| **Chatwoot OSS** (líder open-source) | ERP nativo, LID resolver, anti-ban, IA Jana + ContextoNegocio | Telegram/FB/Insta canais nativos (oimpresso preview-only) |
| **Take Blip** (R$ [redacted Tier 0]/mês BSP oficial) | 15× mais barato (oimpresso Pro R$ [redacted Tier 0]/mês), ERP nativo, onboarding 5min | CTWA + Catalog + compliance Meta oficial puro |
| **Bling/Tiny/Omie/Conta Azul** (ERP BR) | **5 anos à frente** (zero inbox real lá; só "Enviar WhatsApp" wa.me) | — |
| **Octadesk/Zendesk/Intercom** (omnichannel enterprise) | ERP integrado nativo (eles são SaaS atendimento isolado) | Multi-canal real + recursos enterprise |

**Pricing payback:** < 1 mês (NFe + boleto auto-anexo elimina BSP parceiro ~R$ [redacted Tier 0]/mês).

## 8. Risks ativos

- 🟡 **Multi-phone UI parcial** (US-WA-040 PR3+PR4) — operações 5+ atendentes ainda sem `Settings/Edit.tsx` multi-select. Bloqueia clientes Martinho (em consideração canary).
- 🟡 **Proliferação cron jobs** (whatsapp:* daily) — atualmente 5 crons. Overlap horários risk; consolidar em `whatsapp:cron-orchestrator` (P2).
- 🟢 **Anti-ban warmup counter in-memory** — perde em restart daemon. P2 Redis pra persist (PR #5 CYCLE-08).
- 🟢 **CSAT spammar cliente** se conv re-resolve 2× em 24h — `DISPATCH_DEDUP_HOURS=24` mitiga; observar 30d prod (P3).

## 9. Métricas-chave (last 7d — biz=1 prod)

> ⚠️ Stale 2026-05-15 — dashboard `/atendimento/metricas` agrega snapshot daily 02:30 BRT (PR #711). Atualizar via cron próximo run.

- Volume diário: N msgs/dia
- Custo HSM: R$ N/dia
- Deflection bot Jana: N%
- Tempo médio 1ª resposta: N min
- SLA breaches últimas 24h: N

## 10. Cliente piloto / canary

- **Atual prod biz=1:** ROTA LIVRE / Larissa (Vestuário) — 99% volume vendas históricas. Status: estável pós-recovery 83 msgs DELETE + Baileys 7.x re-pareamento 2026-05-15 09:25. 55 msgs sincronizadas via history fetch.
- **Próximo canary Baileys 7.x prod:** já em biz=1 desde 2026-05-15 09:00 (Wagner fez deploy manual). Estável 7d a observar via OTel.
- **Cliente piloto novo módulo Whatsapp avançado:** **Martinho Caçambas / biz=164** (legacy v1404 migrado, CYCLE-06) — feedback canon Wagner: **NÃO ROTA LIVRE pra novos rollouts WhatsApp** (canary inicia em Martinho)
- **Canary Cloud API Meta:** stub PoC PR #858 — aguarda Wagner config Meta Business Manager (biz=99 sandbox)

## 11. ADRs centrais

- [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Decisão mãe driver (Z-API/Baileys + Meta Cloud fallback, Evolution PROIBIDO)
- [ADR 0135](../../decisions/0135-omnichannel-inbox-arquitetura.md) — Channel polimórfico + 4 fases
- [ADR 0096 emenda 4](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Baileys daemon CT 100 custom
- [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md) — Multi-phone per-business
- [ADR 0146](../../decisions/0146-contact-lid-canonico-pk-refactor.md) — feature-wish PK identidade contact_lid

## 12. Sessões e handoffs relevantes (últimos 7d)

- [2026-05-15-10:10 maratona encerrada 12 PRs](../../handoffs/2026-05-15-1010-whatsapp-maratona-encerrada-12prs-ui-brave-validada-regra-primaria.md) — UI validada Brave + regra primária "mexeu registra" formalizada
- [2026-05-15-07:00 maratona Baileys 7.x deploy](../../handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md) — 8 PRs incident anti-cross-contact + Baileys 7.x + Hostinger deploy biz=1
- [2026-05-15-00:30 incident cross-contact P0](../../handoffs/2026-05-15-0030-whatsapp-incident-anti-cross-contact-p0.md) — 81 msgs caíram contato errado, 3 fixes P0 + estado-da-arte 38/100
- [2026-05-14-18:34 fix --verbose conflict](../../handoffs/2026-05-14-1834-whatsapp-purge-fix-verbose.md)
- [2026-05-14-03:00 async queue final fix](../../handoffs/2026-05-14-0300-whatsapp-async-queue-final-fix.md)

## 13. Último update

**Atualizado:** 2026-05-16 — Wave M agent (boost 69→meta 80) — focal gap D4 (Service ratio) + D3.c (charter ratio)
**Mudanças desta wave:**
- `Modules/Whatsapp/Services/InboxQueryService.php` — facade THIN sobre 4 leituras compartilhadas (visibleChannelIds / listConversations / listMessages / unreadBadgeForUser). Multi-tenant Tier 0 explícito ($businessId required, sem session()). Compatível Jobs assíncronos. Delega pra Entities existentes — zero regra nova.
- 3 charters live novos: `resources/js/Pages/Atendimento/{Channels,Macros,Metricas}/Index.charter.md` — Mission/Goals/Non-Goals/UX/Automation/Anti-hooks. Validadas 2026-05-16. Total 6 charters cobertos no Atendimento.
- BRIEFING table refreshed — 2 dimensões governance acrescentadas (D3.c charter ratio + D4 Service ratio).

**PRs anteriores incorporados:** #871 (F1+F2 Caixa Unificada v4 + governance backfill D-1/D-3/D-11), #873 (D-14 perf Inertia::defer Inbox 300ms→50ms), #875 (governance regras Tier 0 + 2 skills `inertia-defer-default` + `brief-update` + BRIEFING-TEMPLATE), defer cross-tela Whatsapp (7 páginas restantes além do Inbox)
**Perf SPA-feel cross-página:** 7 telas do módulo agora com `Inertia::defer` — switch página ~50ms validado. JanaTemplates exceção legítima eager (1 query simples, ADR Tier 0 §10 do RUNBOOK).
**Próximo update esperado:** quando próximo `feat/perf/fix(whatsapp)` mergear (auto-trigger skill `brief-update`)
**Mantenedor:** Claude (auto via skill) + Wagner (review)
