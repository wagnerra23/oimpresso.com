---
owner: W
last_validated: "2026-06-08"
slug: infra-runbook-deploy-centrifugo
title: "Infra — Runbook deploy Centrifugo público (CT 100) + ativação Whatsapp real-time"
type: runbook
module: Infra
status: ativo
date: 2026-05-08
---

# RUNBOOK — Deploy Centrifugo público no CT 100 + ativação real-time Whatsapp

> **Tipo:** runbook reproduzível
> **Refs:** [ADR 0058 Centrifugo+FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md), [ADR 0042 Infra empresa CT 100](../../decisions/0042-infra-empresa-padrao.md), [ADR 0062 Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md), [ADR 0096 Whatsapp module](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> **Pré-requisito:** acesso SSH ao CT 100 via Tailscale (`tailscale ssh root@ct100-mcp` — ver [RUNBOOK-acesso-ct100.md](RUNBOOK-acesso-ct100.md))

Sintoma alvo deste runbook: Inbox WhatsApp em `oimpresso.com/whatsapp/conversations` mostra `● conectando…` em vez de `● live`, e mensagens recebidas só aparecem após 5s (polling fallback PR #266). Causa: Centrifugo CT 100 não exposto publicamente via Traefik. Este runbook resolve em ~45min.

## Estado final esperado

| Verificação | Como conferir |
|---|---|
| `https://realtime.oimpresso.com/health` retorna 200 OK | `curl -i https://realtime.oimpresso.com/health` |
| `tinker → CentrifugoPublisher::publish('test', ['x' => 1])` retorna `true` | tinker no Hostinger |
| Inbox `/whatsapp/conversations` mostra `● live` (verde) | abrir browser logado; selecionar conversa |
| Mensagem recebida via webhook Z-API aparece em <500ms (sem aguardar polling) | mandar Whatsapp test pra número ROTA LIVRE |
| Polling fallback se desativa quando `liveConnected=true` | DevTools Network → não há reload a cada 5s |

## 1. Pré-condições

- [ ] CT 100 acessível via Tailscale (`tailscale ssh root@ct100-mcp` — IP 100.99.207.66)
- [ ] Traefik rodando no CT 100 com Let's Encrypt (já existe — confere com `docker ps | grep traefik`)
- [ ] DNS A-record `realtime.oimpresso.com` apontado pro IP público 177.74.67.30 (porta 443 já forwardada — pattern de outros subdomínios `mcp.*`, `growthbook.*`)
- [ ] Espaço disco no CT 100: ≥500MB livres em `/var/lib/docker` (Centrifugo binary é ~30MB)
- [ ] Hostinger SSH funcional (`ssh -4 ... u906587222@148.135.133.115`) — ver CLAUDE.md §7 warm-up

⚠️ **Subdomain canônico per ADR 0058:** `realtime.oimpresso.com` (NÃO `centrifugo.oimpresso.com` que é o nome do binary). PR #266 testou nome errado e por isso achou HTTP 000 — o subdomínio nunca tinha sido criado.

## 2. Passo-a-passo

### 1. SSH no CT 100 + verificar estado de Centrifugo

```bash
tailscale ssh root@ct100-mcp 'docker ps -a --format "{{.Names}}: {{.Status}}" | grep -i centrifugo'
```

**Possíveis estados:**

- **Vazio**: Centrifugo nunca foi deployado → segue pro Passo 2.
- **`Up X hours`**: já roda mas só LAN → pula pro Passo 3 (configurar Traefik público).
- **`Exited`**: container existe mas down → reiniciar (`docker start centrifugo`) ou recriar.

### 2. Deploy Centrifugo (caso ainda não esteja rodando)

```bash
tailscale ssh root@ct100-mcp
mkdir -p ~/docker/centrifugo
cd ~/docker/centrifugo
```

Criar `docker-compose.yml`:

```yaml
services:
  centrifugo:
    image: centrifugo/centrifugo:v6
    container_name: centrifugo
    restart: unless-stopped
    command: centrifugo --config=/etc/centrifugo/config.json
    volumes:
      - ./config.json:/etc/centrifugo/config.json:ro
    networks:
      - traefik
    ulimits:
      nofile:
        soft: 65535
        hard: 65535
    labels:
      - "traefik.enable=true"
      - "traefik.http.routers.centrifugo.rule=Host(`realtime.oimpresso.com`)"
      - "traefik.http.routers.centrifugo.entrypoints=websecure"
      - "traefik.http.routers.centrifugo.tls.certresolver=letsencrypt"
      - "traefik.http.services.centrifugo.loadbalancer.server.port=8000"

networks:
  traefik:
    external: true
```

Criar `config.json` (substituir os 2 secrets por valores random — `openssl rand -hex 32`):

```json
{
  "client": {
    "allowed_origins": ["https://oimpresso.com"],
    "token": {
      "hmac_secret_key": "<HMAC_SECRET_GERADO_AQUI>"
    }
  },
  "http_api": {
    "key": "<API_KEY_GERADO_AQUI>"
  },
  "channel": {
    "without_namespace": {
      "presence": false,
      "history_size": 0,
      "history_ttl": "0s"
    }
  },
  "log": {
    "level": "info"
  }
}
```

⚠️ **IMPORTANTE — guardar os 2 secrets**: você vai precisar deles no Passo 4 pra colar no `.env` da Hostinger. Sugestão: cole no `pass`/Vaultwarden agora antes de seguir.

```bash
# Gerar secrets:
openssl rand -hex 32  # cola em hmac_secret_key
openssl rand -hex 32  # cola em http_api.key

# Subir:
docker compose up -d
docker logs centrifugo --tail 20  # esperar "serving websocket on :8000"
```

### 3. Verificar Traefik público + cert Let's Encrypt

```bash
# Aguardar ~30s pra cert gerar:
sleep 30

# Health-check público:
curl -i https://realtime.oimpresso.com/health
# Esperado: HTTP/2 200, body: {}

# Logs Traefik confirmando router subiu:
tailscale ssh root@ct100-mcp 'docker logs traefik 2>&1 | grep -i centrifugo | tail -5'
```

**Se falhar com `404`**: Traefik provavelmente não pegou a label. Verificar se o container está na network `traefik`:

```bash
docker network inspect traefik | grep -A2 centrifugo
```

**Se falhar com `Connection refused`**: DNS A-record não propagou. Aguardar mais 5min ou checar `dig realtime.oimpresso.com`.

### 4. Setar 4 envs no Hostinger

```bash
# Warm-up (CLAUDE.md §7):
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# SSH:
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115
```

Editar `~/domains/oimpresso.com/public_html/.env` (ou onde estiver o `.env` da app — `cd domains/oimpresso.com/public_html && ls .env`):

```bash
# 4 linhas pra adicionar (URLs já têm default canônico no config.php, mas explicitar é melhor pra claridade):
WHATSAPP_CENTRIFUGO_URL=https://realtime.oimpresso.com
WHATSAPP_CENTRIFUGO_WS_URL=wss://realtime.oimpresso.com/connection/websocket
WHATSAPP_CENTRIFUGO_API_KEY=<MESMO_VALOR_HTTP_API_KEY_DO_PASSO_2>
WHATSAPP_CENTRIFUGO_TOKEN_HMAC_SECRET=<MESMO_VALOR_HMAC_SECRET_DO_PASSO_2>
```

⚠️ Os 2 secrets têm que ser **idênticos** ao Centrifugo `config.json` do CT 100, senão JWT é rejeitado silenciosamente e usuário só vê `liveConnected=false` para sempre.

```bash
# Limpar caches Laravel:
php artisan config:clear
php artisan cache:clear
```

### 5. Smoke test backend → frontend

```bash
# 5a. Tinker no Hostinger:
php artisan tinker
>>> app(\Modules\Whatsapp\Services\Centrifugo\CentrifugoPublisher::class)->publish('test', ['x' => 1]);
# Esperado: true (HTTP 200 do Centrifugo API)

# Se retornar false → conferir storage/logs/laravel.log pra warning [whatsapp.centrifugo.publish]
```

```
# 5b. Browser
# 1. Abrir https://oimpresso.com/whatsapp/conversations logado
# 2. Selecionar uma conversa qualquer
# 3. Header da thread deve mostrar "● live" verde
#    (em vez de "● conectando…" cinza)
# 4. DevTools → Console: deve aparecer "centrifuge: connected"
# 5. DevTools → Network: WS aberto pra wss://realtime.oimpresso.com/connection/websocket
```

### 6. Smoke real-time

Mandar mensagem real pelo Whatsapp da Larissa (ROTA LIVRE biz=4) pro número conectado. Esperado: thread atualiza em <500ms (antes do polling 5s ter chance de disparar).

Alternativamente, simular webhook Z-API:

```bash
curl -X POST https://oimpresso.com/api/webhooks/whatsapp/zapi/<biz_uuid> \
  -H "z-api-token: <token_da_business>" \
  -H "Content-Type: application/json" \
  -d '{"phone":"5511987654321","text":{"message":"smoke centrifugo"},"fromMe":false,"messageId":"smoke-'$(date +%s)'"}'
```

Thread aberta vê a mensagem em ~200ms.

## 3. Tokens / endpoints (referência rápida pós-deploy)

| Recurso | URL | Auth |
|---|---|---|
| WSS pro frontend | `wss://realtime.oimpresso.com/connection/websocket` | JWT HS256 emitido por `CentrifugoTokenIssuer` |
| HTTP API pro backend publish | `https://realtime.oimpresso.com/api` | Header `X-API-Key: <api_key>` |
| Health check | `https://realtime.oimpresso.com/health` | público |
| Channel Whatsapp | `whatsapp:business:{N}` | JWT precisa listar este channel em `channels[]` |

## 4. DoD checklist

- [ ] Subdomain `realtime.oimpresso.com` resolve via DNS
- [ ] Centrifugo container `Up` no CT 100
- [ ] `https://realtime.oimpresso.com/health` retorna 200 OK
- [ ] Cert Let's Encrypt válido (não autoassinado)
- [ ] 4 envs setados no `.env` Hostinger
- [ ] `config.json` Centrifugo CT 100 e `.env` Hostinger têm os MESMOS 2 secrets (HMAC + API key)
- [ ] Tinker `CentrifugoPublisher::publish` retorna `true`
- [ ] Browser `/whatsapp/conversations` mostra `● live`
- [ ] Mensagem inbound aparece em <500ms (não 5s)
- [ ] DevTools Network: WS conectado em `wss://realtime.oimpresso.com/...`

## 5. Pegadinhas

- ❌ **`allowed_origins` com barra final** (`https://oimpresso.com/`) — Centrifugo derruba conexão silenciosa. Sem barra: `https://oimpresso.com`.
- ❌ **HMAC secrets diferentes entre CT 100 e Hostinger** — JWT emitido OK no Laravel, mas Centrifugo rejeita. Sintoma: `liveConnected` fica `false` para sempre, sem erro visível pro usuário. Conferir secrets idênticos byte a byte.
- ❌ **Ports 8000 publicado direto** (`-p 8000:8000` no compose) — exporia Centrifugo sem TLS. **NÃO publicar porta**; deixar Traefik fazer o reverse-proxy.
- ❌ **`token` em vez de `client.token` no config.json v6+** — config v5 usava root-level `token`, v6 mudou pra dentro de `client`. Se aparecer "JWT validation failed" mesmo com secret correto, é provável que a versão do Centrifugo seja v6 e o config esteja em formato v5.
- ❌ **DNS A-record só, sem registro AAAA** — Cloudflare/etc requerem AAAA pra IPv6. Verificar com `dig AAAA realtime.oimpresso.com`. Se servidor é IPv4-only, OK ignorar.
- ❌ **Esquecer `php artisan config:clear`** após editar `.env` — Laravel cacheia config e ignora env novo. Sintoma: tinker continua retornando false mesmo com .env correto.
- ❌ **Polling 5s sobreposto após Centrifugo conectar** — Ambos rodam: WS escuta + polling continua. Não duplica mensagens (UI deduplica via `messages.id`), mas dobra reload partial. Comportamento atual é gating por `liveConnected/centrifugoConfig` e desativa polling automaticamente — verificar via DevTools Network pra confirmar que `router.reload` para de aparecer a cada 5s assim que `● live` aparece.

## 6. Rollback

Se a ativação quebrar algo:

```bash
# Hostinger — comentar 4 envs e clear cache:
ssh -4 ... u906587222@148.135.133.115
cd domains/oimpresso.com/public_html
sed -i 's/^WHATSAPP_CENTRIFUGO_/#WHATSAPP_CENTRIFUGO_/' .env
php artisan config:clear

# Frontend automaticamente cai pro polling 5s (PR #266 fallback)
# Status volta pra "● conectando…" mas mensagens chegam em até 5s — funcional.
```

## 7. ADRs de origem

- [ADR 0058 — Reverb substituído por Centrifugo + FrankenPHP](../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) — escolha do Centrifugo
- [ADR 0042 — Infra empresa Proxmox + Docker + Traefik](../../decisions/0042-infra-empresa-padrao.md) — pattern CT 100
- [ADR 0062 — Separação runtime Hostinger ≠ CT 100](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — daemon = CT 100, app = Hostinger
- [ADR 0096 — Módulo Whatsapp Meta Cloud API direto](../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — channel `whatsapp:business:{N}`

---

**Última atualização:** 2026-05-08
