# Handoff 2026-05-13 23:30 — Whatsapp daemon saga 11 PRs + rebuild CT 100 + safeguards

> Continuação direta dos handoffs anteriores do dia (`17:50 comvis-revert` + `11:30 sessao-recorde`). Sessão noite focada em incident WhatsApp Baileys → 11 PRs cumulativos → rebuild manual daemon CT 100 → safeguards permanentes.

## TL;DR (5 linhas)

- Incident reportado por Wagner: 2 canais Baileys travados, "QR não abre", "mensagens não vêm após pareamento"
- Audit profundo identificou 2 bugs principais: auto-purge ausente antes /connect + `syncFullHistory: false` desabilitando history sync
- 11 PRs abertos cobrindo: 2 fixes + 2 agents canônicos + 2 hardening + 2 sistemas anti-recorrência + 1 history sync + 1 capterra agent + 1 safeguards
- Daemon CT 100 rebuilt manualmente pra `:v823` (4h descobrindo processo na unha — `/srv/build/whatsapp-baileys-daemon/` não é git repo, Dockerfile clash com base image atualizada)
- PR #825 (safeguards) codifica RUNBOOK + CI + drift sentinel pra próximo deploy levar ~10min ao invés de 4h

## Estado MCP no momento do fechamento

⚠️ **NÃO consultei MCP tools (cycles-active, my-work, sessions-recent, decisions-search) durante a saga.** Sessão emergencial focada em resolver problema operacional. Wagner aprovou cada PR antes de mergear. Próxima sessão DEVE invocar `brief-fetch` Tier A no início — repetição da violação catalogada em handoff anterior (17:50).

## PRs desta saga (11 total — 10 merged + 1 aguardando CI)

| PR | Status | Risco | O que entrega |
|---|---|---|---|
| [#813](https://github.com/wagnerra23/oimpresso.com/pull/813) | ✅ merged | baixo | Agent `whatsapp-doctor` + post-mortem incident manhã |
| [#814](https://github.com/wagnerra23/oimpresso.com/pull/814) | ✅ merged | baixo | `display_identifier` unique cross-business (FormRequest validation) |
| [#815](https://github.com/wagnerra23/oimpresso.com/pull/815) | ✅ merged | médio | Sync Laravel→daemon ao deactivate channel (DeleteBaileysInstanceJob) |
| [#816](https://github.com/wagnerra23/oimpresso.com/pull/816) | ✅ merged | baixo | Circadian rhythm anti-ban (jitter ×4 em 02-06 BRT) |
| [#817](https://github.com/wagnerra23/oimpresso.com/pull/817) | ✅ merged | médio | Healthcheck zombie 503 (Docker auto-restart) |
| [#819](https://github.com/wagnerra23/oimpresso.com/pull/819) | ✅ merged | baixo | Agent `capterra-senior` + dogfood WhatsApp FICHA v3 (92% score) |
| [#821](https://github.com/wagnerra23/oimpresso.com/pull/821) | ✅ merged | baixo | Hardening (índice display_identifier + threshold 60min + métrica OTel + helper + agent calibrado) |
| [#822](https://github.com/wagnerra23/oimpresso.com/pull/822) | ✅ merged | baixo | Auto-purge banned/disconnected ANTES de POST /connect (**fix do "QR não abre"**) |
| [#823](https://github.com/wagnerra23/oimpresso.com/pull/823) | ✅ merged | médio | History sync canônico ~90d msgs (`syncFullHistory: true` + Desktop browser + handler `messaging-history.set`) |
| [#824](https://github.com/wagnerra23/oimpresso.com/pull/824) | ✅ merged | baixo | Reconciler cron 5min + reset 1-comando (automação Wagner pediu) |
| [#825](https://github.com/wagnerra23/oimpresso.com/pull/825) | ⏳ CI rodando | baixo | CI Docker build workflow + drift sentinel + RUNBOOK rebuild |

## Decisões importantes salvas

1. **Daemon CT 100 NÃO É git repo** — `/srv/build/whatsapp-baileys-daemon/` é cópia manual via tar/rsync. Documentado em RUNBOOK
2. **Dockerfile clash daemon→nodeapp** — base image `node:20-bookworm-slim` agora tem group `daemon` reservado. Renomeado pra `nodeapp` no PR #825
3. **`syncFullHistory: false` é bug-by-default em Baileys 7.x** — Issue #11951 confirma desabilita TODO history sync sem callback
4. **Mismatch DB cliente**: channel id=4 (biz=164 MARTINHO) tinha display_identifier=`554888782087` (número do Wagner). Wagner pareou por engano antes do PR #814 existir. Channel id=4 purgado + DB reset pra setup
5. **Auto-purge banned antes /connect** (PR #822) — fix raiz do "QR não abre". Detecta state=banned|disconnected|error → DELETE auto → POST connect emite QR limpo
6. **Reconciler cron 5min** (PR #824) — Wagner: "automatize". Auto-fix drift channels↔daemon sem intervenção
7. **CI Docker build em PRs** (PR #825) — pega `groupadd` clash + TS errors ANTES do prod
8. **Drift sentinel cron weekly** (PR #825) — compara SHA local vs `daemon_source_sha` no /health
9. **RUNBOOK daemon-ct100-rebuild.md** — 5 passos + 5 pegadinhas + rollback

## Estado prod pós-saga

- **Daemon CT 100**: `oimpresso/whatsapp-baileys-daemon:v823` healthy, rebuilt manual com source main HEAD
- **Backup**: image `:backup-pre-823` preservada (rollback rápido)
- **Hostinger**: em sync com main (HEAD = `5f4aae2bf` PR #823)
- **Channels biz=1**:
  - id=5 "Jana" (554888782087): status=setup, daemon sem instance → **Wagner deve clicar Conectar na UI**
  - id=6 "Suporte" (554896486699): status=setup, daemon sem instance → **idem**
- **Channel biz=164 MARTINHO**: id=4 status=setup (sem número físico ainda)

## Pra Wagner amanhã (apresentação cliente)

1. Acessa https://oimpresso.com/atendimento/canais
2. Click **Conectar** no card "Jana" → QR aparece em ≤12s (PR #822 auto-purge garante)
3. Scaneia no celular (WhatsApp → Aparelhos vinculados → Vincular dispositivo)
4. Repete pro card "Suporte"
5. Histórico ~90d vem automaticamente após scan QR (daemon `:v823` `syncFullHistory: true`)

## Lições + meta-aprendizado

### O que funcionou

- Pattern Audit → Diagnóstico → PR → Test → Repeat entregou 11 PRs em ~10h
- Agent `whatsapp-doctor` codificou runbook (PR #813) — vai usar em incidents futuros
- Honestidade sobre falsos positivos do agent `capterra-senior` → calibrei description (PR #819 + #821)
- Multi-tenant Tier 0 preservado em TODOS os PRs com Pest cross-biz isolation

### O que falhou

- **Sessão começou SEM `brief-fetch`** — repetição da degradação clássica catalogada hoje cedo (`2026-05-13-agents-canonicos-meta-degradacao.md`). Wagner já criou hook brief-fetch-curl.ps1 no SessionStart pra worktrees mas falhou rodar
- Rebuild manual quebrou na primeira tentativa porque source CT 100 ~15 commits atrás do main
- Mismatch display_identifier biz=164 vs biz=1 existia desde antes do PR #814

### Próximos passos sugeridos pós-demo

1. **Wagner re-pareia os 2 canais** via UI quando puder
2. **Validar drift sentinel rodando** após próximo deploy Hostinger (cron weekly)
3. **CI workflow daemon-docker-build** validado em algum PR futuro que mexa em `daemon-node/`
4. **Dashboard Grafana** pra `whatsapp_baileys_zombies_detected_total` (counter existe mas dashboard ainda não)
5. **Eliminar mismatch DB cross-business** — varrer todos channels e marcar duplicates pre-PR #814 (1-shot command)

## Referências cruzadas

- [memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](../sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md) — incident manhã
- [memory/sessions/2026-05-13-whatsapp-daemon-rebuild-safeguards.md](../sessions/2026-05-13-whatsapp-daemon-rebuild-safeguards.md) — session log expandido (este handoff)
- [memory/sessions/2026-05-13-capterra-whatsapp.md](../sessions/2026-05-13-capterra-whatsapp.md) — Capterra FICHA v3 pesquisa expandida
- [memory/requisitos/Whatsapp/CAPTERRA-FICHA.md](../requisitos/Whatsapp/CAPTERRA-FICHA.md) — FICHA v3 regenerada (score 92%)
- [memory/requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md](../requisitos/Whatsapp/runbooks/daemon-ct100-rebuild.md) — RUNBOOK canônico
- [.claude/agents/whatsapp-doctor.md](../../.claude/agents/whatsapp-doctor.md) — agent SRE canônico
- [.claude/agents/capterra-senior.md](../../.claude/agents/capterra-senior.md) — agent Capterra canônico
- ADR 0096 emenda 4 — driver Baileys autorizado
- ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0130 — handoff append-only convention
