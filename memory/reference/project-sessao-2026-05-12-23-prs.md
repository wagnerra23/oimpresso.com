---
name: Sessão recorde 2026-05-12 — 23 PRs WhatsApp/omnichannel deployed em 1 dia
description: Maratona de PRs (10 estabilidade Baileys + 7 CYCLE-07 P0/P1 + 6 CYCLE-08 + auth state MySQL LIVE em prod). Score CAPTERRA 78% → 91%. Setup técnico pro deal Agrosys.
type: project
---
# Sessão 2026-05-12 — 23 PRs em 1 dia

## Estado consolidado

| Onda | PRs | Foco |
|---|---|---|
| **Manhã — estabilidade Baileys** | 10 | Anti-QR-fest + Multi-Device + Baileys 6.7.18 + LID resolution |
| **Tarde 1 — CYCLE-07 P0/P1** | 5 | Mídia outbound/inbound + SLA + métricas + macros + auto-link CRM |
| **Tarde 2 — CYCLE-07 deep** | 3 | CSAT + HSM interactive + LID backfill |
| **Tarde 3 — CYCLE-08** | 5 | Multi-phone UI + HSM dialog + A/B testing + auth state MySQL deploy |
| **TOTAL** | **23 PRs** | |

## PRs MERGED em código (ordem cronológica)

### Onda 1 — Estabilidade Baileys (10 PRs)
- **#687** webm/quicktime whitelist (UX anexos)
- **#688** daemon emite `messages.upsert fromMe=true` (Multi-Device unified inbox)
- **#692** schema `mime`→`mimetype` Zod + Pest regression test fromMe
- **#685** `InstanceManager.bootstrap()` + `persistMeta()` (anti-QR-fest VALIDADO em prod)
- **#686** `whatsapp:health-probe-channels` daily + `whatsapp:reconnect-and-import` combo
- **#695** Baileys 6.7.9 → 6.7.18 (último CJS antes ESM-only 6.7.19+)
- **#696** LID resolution custom (tabela `whatsapp_lid_pn_map` + UI badge)
- **#698** LID backfill command + cache TTL 24h
- **#699** Anti-ban middleware (jitter Gaussian + typing presence + warmup 7d)
- **#700** Botão "Conectar canal" com label visível (UX)

### Onda 2 — CYCLE-07 P0/P1 (5 PRs)
- **#707** Mídia outbound UI preview-then-send (composer + MediaPreviewCard)
- **#708** Auto-link Contact CRM por phone match (E.164 normalizado)
- **#709** Macros + Quick replies (CRUD + dropdown composer + actions JSON)
- **#710** SLA policies + escalation (3 actions: notify/reassign/set_status)
- **#711** Métricas conversation dashboard (`/atendimento/metricas` + KPIs)

### Onda 3 — CYCLE-07 deep (3 PRs)
- **#714** CSAT pós-resolução (parser 1-5 estrelas + dashboard)
- **#715** HSM botões interativos + List messages (4 drivers + daemon endpoint)
- **#716** Mídia inbound processada (filtro `media_inbound_24h` + lightbox modal)

### Onda 4 — CYCLE-08 + auth state (5 PRs)
- **#718** Multi-phone UI completa (dropdown topbar ChannelSelector)
- **#719** A/B testing variants pra macros HSM (US-WA-049)
- **#720** UI dialog HSM botões interativos (US-WA-045b)
- **#701** auth state MySQL custom (`useMySQLAuthState` substitui `useMultiFileAuthState`)
- **#702** Scripts migrate-fs-to-mysql + rotate-encryption-key

## Marcos do dia

### Anti-QR-fest validado em prod
PR #685 `InstanceManager.bootstrap()` provou que session com `meta.json` auto-reconecta sem QR após rebuild daemon. Suorte reconectou em 7s no primeiro deploy pós-pair. Ver feedback-daemon-qrfest.md.

### Baileys 6.7.18 LIVE
Decisão técnica importante (agent #695): NÃO pular pra 6.7.19+ pq virou ESM-only e tsconfig é CJS. 6.7.18 = último CJS-compatible. Versão v7 ainda RC (rc10).

### Auth state MySQL LIVE em produção
Daemon CT 100 rodando com `AUTH_STATE_BACKEND=mysql` direto pra Hostinger MySQL (`srv1818.hstgr.io:3306`). Sessions WhatsApp persistidas em DB cifrado AES-256-CBC. `useMultiFileAuthState` (upstream Baileys diz "Don't ever use in production") removido.

Solução de tunnel: NÃO autossh (tentaram em 29-abr e rejeitaram). **Remote MySQL hPanel whitelist + DIRETO srv1818.hstgr.io**. Ver hostinger-remote-mysql.md.

### LID resolution custom
Wagner reportou em prod: número aparecendo como customer_phone (LID Multi-Device anti-spam). Solução custom (PR #696 + #698): tabela `whatsapp_lid_pn_map` ponte + cache + UI badge "número oculto". Sem precisar Baileys v7.

### Score CAPTERRA 78% → 91%
Relatório atualizado em `memory/requisitos/Whatsapp/COMPARATIVO-MERCADO-2026-05-12-v2.md` (v2). 8 gaps P0/P1 fechados com PR# evidência. Chassi acima de Octadesk/Tallos.

## Subagent criado

`.claude/agents/whatsapp-baileys-expert.md` — invoke via `Agent({subagent_type:'whatsapp-baileys-expert'})` em sessões futuras pra estabilidade Baileys.

## Lições da sessão (técnicas)

1. **Máximo ~3 deploys daemon/dia** — 4º já bane mesmo com `meta.json` (auth state preservado mas WA anti-abuse fica sensível)
2. **Hostinger Remote MySQL whitelist + direto > autossh** — autossh rejeitado em abr-2026 por Connection timed out
3. **MariaDB 11.8.6 ≠ MySQL 8** — Hostinger virou MariaDB; alguns SQL constructs diferem
4. **Embedded Signup Meta = único caminho viável pra escalar 4000+ clientes** — BSP intermediário quebra margem
5. **Worktree isolation NÃO impede 100% race em arquivos shared** — agents paralelos podem fazer git checkout cruzados; precisa cherry-pick recovery
6. **Conflitos paralelos resolveram-se** com escopo cirúrgico bem definido (PR-1 mídia outbound + PR-4 macros mexeram em `ConversationThread.tsx` em regiões diferentes)

## Lições comerciais

1. **Validação comercial ANTES de codar** — Wagner quase começou a codar widget. Recomendação: 5 perguntas críticas + Eliana revisa contrato + CNPJ verifica
2. **Comissão recurring > 10% pra vendedor = red flag** — Artur 50% perpétuo seria suicida financeiramente
3. **Agrosys = ERP agro (Criciúma/SC, Aliare)**, não imobiliária. Confusão inicial Wagner ↔ Claude

## Pendências pós-sessão

1. Wagner pair Suorte + Suporte com auth state MySQL backend ativo → confirma rows aparecem em `whatsapp_baileys_auth_state`
2. Wagner conversa Artur sobre comissão antes de assinar Agrosys
3. Wagner email Agrosys via Artur pedindo spec XML/PDF + modelo billing + SLA
4. Eliana revisa proposta Agrosys quando chegar
5. Provisionar CT 101 backup no Proxmox empresa (preparar pra escala)

## Estimativa pipeline Wagner pós-sessão

- Deal Agrosys: **R$ [redacted Tier 0]M ano 1** se fechar (carta de intenção esperada)
- Backlog CYCLE-08 restante: ~40h IA-pair (multi-phone done, falta self-service onboarding + dashboard rede + branding white-label)
- Pipeline Modules/ComunicacaoVisual + OficinaAuto + Modules/Agro (novo se Agrosys aceitar) = horizonte 6-12 meses

**Wagner saiu da sessão com**: 23 features deployed + roadmap claro pro deal estratégico + memórias canônicas pra próxima sessão retomar sem perda.
