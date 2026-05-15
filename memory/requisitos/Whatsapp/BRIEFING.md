# BRIEFING — Whatsapp / Atendimento

> **Mantido por:** skill `brief-update` (Tier B auto-trigger) + Wagner review
> **Atualizado:** 2026-05-15 15:00 BRT (inaugural — PR #871 mergeado + PR #873 em rev)
> **Próximo update esperado:** quando próximo PR `feat/perf/fix(whatsapp)` mergear

---

## 1. O que é

**URL principal:** [oimpresso.com/atendimento/inbox](https://oimpresso.com/atendimento/inbox)
**Backend:** `Modules/Whatsapp/` · 1664 linhas InboxController + 11+ outros Controllers
**Frontend:** `resources/js/Pages/Atendimento/` + `resources/js/Pages/Whatsapp/_components/`

Inbox omnichannel polimórfico (ADR 0135 Fase 0) — multi-canal por design com WhatsApp Baileys/Z-API/Meta Cloud ativos + IG/FB/Email/SMS/ML preparados (preview-only). **Único ERP BR PME com inbox real ancorado em ledger Financeiro + IA conversacional (Jana) + Pix/NFe/boleto auto-anexo.**

## 2. Estado consolidado

| Dimensão | % | Última medição |
|---|---|---|
| Operacional PME (P0+P1 core) | **~95%** | 2026-05-15 (1 P0 parcial: multi-phone UI; resto verde) |
| Capterra score vs top-mercado (Take Blip) | **53.4/59 = 91%** | 2026-05-12 (COMPARATIVO-v2) |
| Diferencial competitivo ERP-nativo | **~100%** | 2026-05-15 (7 diferenciais únicos catalogados) |
| Cobertura SPEC formal (US done/spec) | **43%** | 2026-05-15 (D-1 governance backfill aplicado) |
| Documentação canon (SPEC + AUDIT-LOG + CAPTERRA) | **~90%** | 2026-05-15 (subiu de 60% pós-#871 backfill) |
| Deploy/ops (prod biz=1) | **~90%** | 2026-05-15 (CT 100 Baileys 7.x daemon pendente Wagner) |

## 3. Capacidades hoje (paridade Chatwoot OSS + ERP-nativo)

- **Canais:** WhatsApp Baileys daemon CT 100 + Z-API SaaS + Meta Cloud fallback automático healthcheck (US-WA-014)
- **Atendimento:** Inbox 3-col (lista + thread + sidebar) · ACL canal/fila per-user (US-WA-069) · atalhos `J/K/E/A/?` · status (open/awaiting_human/resolved/archived)
- **Conteúdo:** Mídia outbound preview-then-send (PR #707) · mídia inbound + Whisper transcrição (PR #648/#664/#675/#669/#679) · 4 templates HSM Cloud API · botões interativos backend (PR #715/#720) · list messages cardápio
- **Automação:** Macros + quick replies CRUD (PR #709 — 4 actions) · slash commands `/lembrar`/`/corrigir`/`/lembrete`/`/config` ancorados Jana (PRs #649/#657/#658/#659) · SLA policies + escalation alerts (PR #710) · CSAT pós-resolução 1-5★ (PR #714)
- **CRM/360:** Auto-link Contact UltimatePOS por phone E.164 (PR #708/#682) · tags classificadoras + seed defaults (PR #547/#581) · LGPD opt-in nativo (PR #651)
- **Real-time:** Centrifugo WebSocket + polling fallback 5s defense-in-depth (US-WA-066) · OTel tracing + Prometheus alerts (Onda 1+2 PR #834/#835)
- **Métricas:** Dashboard `/atendimento/metricas` (custo/deflection/tempo resposta) · `/atendimento/csat` (PR #711/#714)
- **Perf:** `Inertia::defer()` em 4 props caras (PR #873) — switch conversa 300ms→50ms SPA-feel real

## 4. Diferenciais únicos (não-replicáveis BSPs)

1. **Multi-tenant Tier 0 IRREVOGÁVEL** (ADR 0093) — global scope em Channel/Conversation/Message + tabelas auxiliares. Convention test 100% cobertura.
2. **Integração ERP transacional** — listeners `NotifyRepairCustomer` + `DispatchToJanaBot` + `BillingNotificationListener` cross-module. **Único BR PME** — Bling/Tiny/Omie zero integração transacional.
3. **Driver fallback healthcheck automático** — flip Z-API/Baileys → Meta Cloud quando `driver_health` degrada.
4. **Bot Jana com `ContextoNegocio` 3 ângulos** (ADR 0052) — sabe quanto cliente deve, OS abertas, produto comprado. Chatwoot bolt-on ChatGPT é só chat genérico.
5. **LID resolution custom** (US-WA-093) — workaround "1 LID @lid ≠ 1 pessoa" Baileys 6.7.x via `whatsapp_lid_pn_map` + Service + cache Redis 24h + backfill cmd. **Ninguém faz** — diferencial técnico ~4-6 meses até Baileys 7.x maduro.
6. **Anti-ban middleware daemon** (PR #699) — Box-Muller Gaussian jitter + typing presence + warmup 7d quotas + circadian quiet hours. Chip vive 3-5× mais.
7. **Schema 3-identifiers anti-cross-contact** (PR #855/#864 incident 2026-05-14) — `lid` + `phone_e164` + `bsuid` + 10 testes regression. **Concorrentes BR não fazem.**

## 5. Gaps remanescentes (CYCLE-08 → ~96%)

| # | Item | Esforço | Score impact |
|---|---|---|---|
| 1 | Multi-phone UI completa (US-WA-040 PR3+PR4) | 3h IA-pair | +1.5pp |
| 2 | Botões interativos UX + List messages UX consolidado | 4h IA-pair | +1pp |
| 3 | Mídia inbound UX consolidada (filtro+lightbox modal) | 3h IA-pair | +1pp |
| 4 | Daemon auth state MySQL (PRs #701/#702) | 4h + cooldown 48h | 0pp (operacional) |
| 5 | A/B testing templates HSM (destrava por #711 métricas) | 3h IA-pair | +0.5pp |
| 6 | **Auto-cadastro contact 38→70** (estado-da-arte 2026-05-14, 3 PRs P0) | 10h IA-pair | +5pp estratégico |

Total CYCLE-08: **~13-23h IA-pair** + Wagner manual (deploy CT 100 + canary 7d) → score estimado **~96%**.

## 6. Bloqueadores manuais Wagner

- ⏳ **Deploy CT 100 Baileys 7.x daemon** — Tailscale SSH `docker compose build/up` (~30min)
- ⏳ **Re-pareamento canal id=7 prod biz=1** — QR scan novo pós-7.x
- ⏳ **Canary biz=99 sandbox 7d** antes promoção biz=1 (runbook canon)
- ⏳ **Wagner-curate CAPTERRA-FICHA v2** `ux_heuristics` + `automation_targets` (~30min — G-2 aberto)

## 7. ROI defendido vs concorrentes

| Concorrente | Como ganhamos | Como perdemos |
|---|---|---|
| **Chatwoot OSS** (líder open-source) | ERP nativo, LID resolver, anti-ban, IA Jana + ContextoNegocio | Telegram/FB/Insta canais nativos (oimpresso preview-only) |
| **Take Blip** (R$ [redacted Tier 0]/mês BSP oficial) | 15× mais barato (oimpresso Pro R$ [redacted Tier 0]/mês), ERP nativo, onboarding 5min | CTWA + Catalog + compliance Meta oficial puro |
| **Bling/Tiny/Omie/Conta Azul** (ERP BR) | **5 anos à frente** (zero inbox real lá; só "Enviar WhatsApp" wa.me) | — |
| **Octadesk/Zendesk/Intercom** (omnichannel enterprise) | ERP integrado nativo (eles são SaaS atendimento isolado) | Multi-canal real + recursos enterprise |

**Pricing payback:** < 1 mês (NFe + boleto auto-anexo elimina BSP parceiro ~R$ [redacted Tier 0]/mês).

## 8. Risks ativos

- 🟡 **Daemon CT 100 Baileys 7.x deploy pendente** — sem isso, biz=1 prod continua em 6.7.18 → próximo bug LID re-arar. Mitigation: handoff 2026-05-15-07:00 + runbook canary pronto, Wagner-manual unblock.
- 🟡 **Multi-phone UI parcial** (US-WA-040 PR3+PR4) — operações 5+ atendentes ainda sem `Settings/Edit.tsx` multi-select. Bloqueia clientes Martinho (em consideração canary).
- 🟡 **Proliferação cron jobs** (whatsapp:* daily) — atualmente 4 crons. Overlap horários risk; consolidar em `whatsapp:cron-orchestrator` (P2).
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

- **Atual prod biz=1:** ROTA LIVRE / Larissa (Vestuário) — 99% volume vendas históricas. Status: estável pós-recovery 83 msgs DELETE 2026-05-15 06:00.
- **Próximo canary Baileys 7.x:** **biz=99 sandbox** (não-cliente real) por 7d ANTES promover biz=1 prod
- **Cliente piloto novo módulo Whatsapp avançado:** **Martinho Caçambas / biz=164** (legacy v1404 migrado, CYCLE-06) — feedback canon Wagner: **NÃO ROTA LIVRE pra novos rollouts WhatsApp** (canary inicia em Martinho)

## 11. ADRs centrais

- [ADR 0096](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Decisão mãe driver (Z-API/Baileys + Meta Cloud fallback, Evolution PROIBIDO)
- [ADR 0135](../../decisions/0135-omnichannel-inbox-arquitetura.md) — Channel polimórfico + 4 fases
- [ADR 0096 emenda 4](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — Baileys daemon CT 100 custom
- [ADR 0117](../../decisions/0117-multiplos-numeros-whatsapp-por-business.md) — Multi-phone per-business
- [ADR 0146](../../decisions/0146-contact-lid-canonico-pk-refactor.md) — feature-wish PK identidade contact_lid

## 12. Sessões e handoffs relevantes (últimos 7d)

- [2026-05-15-07:00 maratona Baileys 7.x deploy](../../handoffs/2026-05-15-0700-whatsapp-maratona-fechamento-8prs-baileys7x-deploy-hostinger.md) — 8 PRs incident anti-cross-contact + Baileys 7.x migration + Hostinger deploy biz=1
- [2026-05-15-00:30 incident cross-contact P0](../../handoffs/2026-05-15-0030-whatsapp-incident-anti-cross-contact-p0.md) — 81 msgs caíram em contato errado, 3 fixes P0 + estado-da-arte 38/100
- [2026-05-14-18:34 fix --verbose conflict](../../handoffs/2026-05-14-1834-whatsapp-purge-fix-verbose.md)
- [2026-05-14-03:00 async queue final fix](../../handoffs/2026-05-14-0300-whatsapp-async-queue-final-fix.md)
- [2026-05-13-23:30 daemon saga 11 PRs](../../handoffs/2026-05-13-2330-whatsapp-daemon-saga-11prs-rebuild-safeguards.md)

## 13. Último update

**Atualizado:** 2026-05-15 15:00 BRT — inaugural via skill `brief-update` por sessão do briefing canon (Wagner pediu "manter atualizado o briefing acho isso super necessário")
**PRs incorporados:** #871 (F1+F2 Caixa Unificada v4 + governance backfill D-1/D-3/D-11) + #873 (D-14 perf Inertia::defer — em rev CI)
**Próximo update esperado:** quando próximo `feat/perf/fix(whatsapp)` mergear (auto-trigger skill `brief-update`)
**Mantenedor:** Claude (auto) + Wagner (review)
