---
name: Máximo ~3 deploys daemon Baileys por dia (anti-abuse Multi-Device)
description: Limite empírico ~3 mass-restarts do daemon Baileys por dia. Acima disso, WhatsApp Multi-Device anti-abuse banem TODAS sessions (mesmo as com meta.json). Anti-ban middleware só protege send, não reconnect.
type: feedback
---
# Limite ~3 deploys daemon Baileys por dia (anti-abuse Multi-Device)

**Caí 2026-05-12** — fiz 4 rebuilds + restarts do daemon CT 100 no mesmo dia (PRs #688, #692, #685, #695, #699). Cada rebuild → mass re-handshake das instances Baileys. O WhatsApp Multi-Device tem proteção anti-abuse contra reconnects rápidos do mesmo número:

- **3º restart**: instances pegam `Connection Failure` no noise-handler durante init queries, banned
- **4º restart**: mesmo com `meta.json` populado (PR #685 auto-reconnect funcionou), instances re-handshake → WA marca devices como abuse → todas voltam `banned`

**Mesmo o anti-ban middleware (PR #699) NÃO mitiga isso** — ele só atua em `sendMessage()` (outbound). Reconnect/handshake/init não passam pelo middleware.

**Why:** Wagner perdeu Suorte conectado várias vezes hoje. Cliente Larissa (ROTA LIVRE biz=4, 99% volume) é alvo crítico — qualquer downtime no inbox é grave. Mass re-handshake recorrente vira ban progressivo (o WA "lembra" do padrão).

**How to apply:**
1. **Limite duro: máximo 2 rebuilds daemon por dia.** Acima disso, **adiar próximo PR daemon pra dia seguinte**.
2. **Empilhar PRs daemon antes de deploy**: se tiver 3 PRs daemon ready (ex: #685 + #695 + #699), fazer SE possível um único deploy consolidado. Reduz mass-restarts de 3 pra 1.
3. **Anti-ban middleware NÃO previne ban de reconnect**. Pra reduzir reconnect-induced bans, alternativas:
   - **Não rebuild** quando só código TS mudou — `docker compose restart whatsapp-baileys` preserva conn state em memória
   - **Auto state persistido em DB** (PR #701 quando deployar) — handshake mais rápido, menos detecção
   - **Wait period entre deploys**: depois de 1 deploy, esperar 4-6h antes do próximo (WA destemporaria)
4. **Sessions banidas precisam ~30min-24h cooldown** ou re-pair QR manual. Banir 3× seguidas pode banir o NÚMERO temporariamente (até 7d).
5. **PR estrutural que requer rebuild deve esperar 24h+ entre tentativas** se anterior baniu.
6. **Cron `whatsapp:health-probe-channels` (PR #686) detecta canais banidos**: usar pra decidir SE pode deployar ou se está em cooldown.

## Sinal claro de over-deploy
Pattern de log:
```
bootstrap auto-reconnect completo   reconnected: N
... 3-5 segundos depois ...
ch-XXX  state: banned  ban_reason: logged_out
```
Aconteceu hoje com Suorte no 4º restart. O auto-reconnect funcionou (bootstrap diz `reconnected: 1`), mas o WA derrubou logo em seguida.

## Mitigação após ban
- **NÃO** tentar reconnect imediato (acelera ban definitivo)
- Esperar 30min-2h
- Re-pair QR no canal afetado (purge session no disco + click Conectar UI)
- Se ban persiste pós-cooldown, esperar 24h
