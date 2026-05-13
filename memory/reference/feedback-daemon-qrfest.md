---
name: Daemon rebuild = QR-fest (mass re-handshake banido pelo WA)
description: Cada rebuild daemon Baileys = mass re-handshake = WA remove devices = QR-fest. PR #685 (bootstrap+meta.json) resolve a partir do primeiro pair.
type: feedback
---
# Daemon Baileys: rebuild = QR-fest até PR #685 estar com meta.json populado

Toda vez que rodar `docker compose build --no-cache && up -d` no daemon CT 100, **todas as instances Baileys re-fazem handshake em paralelo**. O WhatsApp Multi-Device tem proteção anti-abuse que detecta reconnects rápidos do mesmo número e **remove o device server-side** (envia `stream:error code=401 + conflict device_removed`).

Padrão observado 2026-05-12 — caí 3 vezes seguidas pareando + restartando + repareando.

**Why:** Wagner perdeu produtividade reconectando chip Suporte (#3, 554896486699) repetidamente. Cliente Larissa (ROTA LIVRE biz=4) tem 99% volume — qualquer downtime no inbox dói. Aprendi também que sessions corrompidas (`MessageCounterError`) e session legacy sem `meta.json` (pré-PR #685) precisam de QR manual pra serem repopuladas.

**How to apply:**
1. **NÃO rebuild daemon CT 100 desnecessariamente.** Quando código TS não mudou: `docker compose restart whatsapp-baileys` (preserva auth state em memória).
2. **Quando precisar rebuild**: faça em **horário calmo** (madrugada Wagner, ou janela combinada com Larissa).
3. **Após rebuild, espere bootstrap log** (`bootstrap auto-reconnect completo`). Se `skipped > 0`, Wagner precisa clicar "Conectar" 1× em cada canal listado pra popular `meta.json`. **Depois disso**, próximos restarts são invisíveis.
4. **Empilhar PRs antes de deploy**: mergear vários PRs daemon na mesma janela → 1 rebuild só, não N. Padrão eficiente: queue mental `PRs daemon ready` → quando tiver 2-3, deploy junto.
5. **Anti-ban defesa antes de escalar**: PR futuro de jitter Gaussian + typing presence + warmup 7d em chip novo. ROTA LIVRE não pode ser primeiro chip a testar — usar chip secundário.
6. **Validar bootstrap funcionou**: `docker logs whatsapp-baileys 2>&1 | grep bootstrap` deve mostrar `scanned: N reconnected: N` (sem `skipped`) após primeiro pair manual pós-PR #685.

## Comando rápido pra purgar session sem mass-restart

Em vez de rebuild quando só uma session corrompeu, use:
```bash
KEY=<...>
IP=$(docker inspect whatsapp-baileys --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}")
curl -X DELETE -H "Authorization: Bearer $KEY" "http://$IP:3000/instances/ch-<uuid_sem_hifens>"
# Wagner clica Conectar no canal → QR fresh, outras instances PRESERVADAS
```
