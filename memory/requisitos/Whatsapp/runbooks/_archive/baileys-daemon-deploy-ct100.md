---
id: requisitos-whatsapp-runbooks-archive-baileys-daemon-deploy-ct100
---

> ⚠️ **ARQUIVADO 2026-05-27** — BaileysDriver descontinuado por [ADR 0202](../../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).
> Conteúdo preservado como lição histórica. **NÃO aplicar em produção.**

# RUNBOOK · whatsapp-baileys daemon — Deploy CT 100

> **Pré-requisitos:** acesso `tailscale ssh root@ct100-mcp` validado. Skill `proxmox-docker-host`.
> **Decisão mãe:** [ADR 0096 emenda 4](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> **Arquitetura:** [ARCHITECTURE.md §16](../ARCHITECTURE.md)
> **Source:** `Modules/Whatsapp/daemon-node/`

## 0. Pré-checagem (5 min)

```bash
# Conexão Tailscale ok
tailscale ssh root@ct100-mcp 'whoami && uname -a && docker version'

# Networks externas existem
tailscale ssh root@ct100-mcp 'docker network ls | grep -E "traefik|observability"'

# Disco livre (/srv/docker)
tailscale ssh root@ct100-mcp 'df -h /srv/docker'
# >= 2 GB livres (sessões Baileys ~ 5-20 MB cada × 30 instances)

# Loki/OTel collector reachable interno
tailscale ssh root@ct100-mcp 'curl -fsS http://loki-otel:4318/v1/metrics -X POST -d "{}" -m 3 || true'
```

## 1. Provisionar host CT 100 (1× — primeiro deploy)

```bash
tailscale ssh root@ct100-mcp '
  mkdir -p /srv/docker/whatsapp-baileys/sessions \
           /etc/docker-compose/services/whatsapp-baileys
  chmod 700 /srv/docker/whatsapp-baileys/sessions
'
```

## 2. Gerar API key + Docker secret

API key compartilhada entre Hostinger PHP (`whatsapp_business_configs.baileys_api_key`) e o daemon. Gerar 32 bytes hex:

```bash
# Local (Windows PowerShell ou bash)
openssl rand -hex 32 > /tmp/baileys-api-key.txt   # 64 chars hex

# Subir como Docker secret
tailscale ssh root@ct100-mcp '
  cat - | docker secret create whatsapp_baileys_api_key -
' < /tmp/baileys-api-key.txt

# Confirmar
tailscale ssh root@ct100-mcp 'docker secret ls | grep whatsapp_baileys'
```

> Guarde a chave em **Vaultwarden CT 100** com label `whatsapp-baileys-api-key-prod`. Nunca em repo nem `.env` plaintext.
> Para rotacionar (Recovery 4 do troubleshoot): `docker secret rm` + create de novo + `docker compose up -d --force-recreate`.

## 3. Materializar `docker-compose.yml`

```bash
# Copiar do source
scp Modules/Whatsapp/daemon-node/docker-compose.yml \
    root@ct100-mcp:/etc/docker-compose/services/whatsapp-baileys/docker-compose.yml

# Variáveis de ambiente do compose (não-sensíveis — chave fica em secret)
tailscale ssh root@ct100-mcp 'cat > /etc/docker-compose/services/whatsapp-baileys/.env <<EOF
IMAGE_TAG=v0.1.0
LOG_LEVEL=info
WEBHOOK_BASE_URL=https://oimpresso.com/api/whatsapp/webhook/baileys
WEBHOOK_TIMEOUT_MS=10000
WEBHOOK_MAX_RETRIES=5
OTEL_ENABLED=true
OTEL_EXPORTER_OTLP_ENDPOINT=http://loki-otel:4318
MAX_INSTANCES=30
EOF'
```

## 4. Build da imagem (CI ou manual)

### Opção A — CI/CD (preferido)

`.github/workflows/baileys-daemon-build.yml` (a criar) builda no push de tag `baileys-daemon-vX.Y.Z` e publica em `ghcr.io/oimpresso/whatsapp-baileys-daemon:vX.Y.Z`. Não há side-effect na Hostinger.

### Opção B — Build manual no CT 100

```bash
# Sincronizar source
rsync -av --exclude node_modules --exclude dist --exclude var \
      Modules/Whatsapp/daemon-node/ \
      root@ct100-mcp:/srv/build/whatsapp-baileys-daemon/

tailscale ssh root@ct100-mcp '
  cd /srv/build/whatsapp-baileys-daemon
  docker build -t oimpresso/whatsapp-baileys-daemon:v0.1.0 .
'
```

## 5. Subir o serviço

```bash
tailscale ssh root@ct100-mcp '
  cd /etc/docker-compose/services/whatsapp-baileys
  docker compose pull || true
  docker compose up -d
  docker compose ps
'
```

Esperado:

```
NAME                  IMAGE                                       STATUS         PORTS
whatsapp-baileys      oimpresso/whatsapp-baileys-daemon:v0.1.0    Up (healthy)   3000/tcp
```

## 6. Smoke test interno (CT 100)

```bash
tailscale ssh root@ct100-mcp '
  curl -fsS http://127.0.0.1:3000/health | jq .
  curl -fsS http://127.0.0.1:3000/metrics | head -20
'
```

`/health` deve retornar `status: ok` + `instances: []` (zero ainda).

## 7. Smoke test via Traefik (deve passar IP whitelist)

```bash
# Do CT 100 (IP origem do CT, NÃO whitelisted) — deve dar 403
tailscale ssh root@ct100-mcp '
  curl -sS -o /dev/null -w "%{http_code}\n" \
    https://whatsapp-baileys.oimpresso.local/health
'   # esperado: 403

# Da Hostinger (IP 148.135.133.115, whitelisted) — deve dar 200
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  'curl -fsS https://whatsapp-baileys.oimpresso.local/health'
```

> Se o IP whitelist falhar com 403 mesmo da Hostinger, conferir
> `traefik.http.middlewares.baileys-ip.ipwhitelist.sourcerange` no compose
> e que `traefik` está ouvindo a entrypoint `websecure`.

## 8. Configurar 1ª instance (cliente piloto)

No painel Hostinger:

1. Login admin business piloto.
2. Acessar `/whatsapp/settings` → wizard 3ª opção "Baileys custom".
3. Preencher:
   - `baileys_instance_id`: `biz<ID>-main` (ex: `biz4-main` para ROTA LIVRE)
   - `baileys_daemon_url`: `https://whatsapp-baileys.oimpresso.local`
   - `baileys_api_key`: a chave de **/tmp/baileys-api-key.txt** (cifrada ao salvar)
4. Aceitar termo LGPD com adendo Baileys.
5. Cadastrar Meta Cloud como fallback obrigatório (gating duro do FormRequest).
6. Salvar — UI mostra "QR Code aguardando".

Backend dispara `POST /instances/biz4-main/connect` no daemon. Daemon cria pasta `/srv/docker/whatsapp-baileys/sessions/biz4-main/`, gera QR, retorna PNG base64. UI exibe → admin escaneia com WhatsApp do business.

## 9. Validar fim-a-fim

```bash
# No CT 100 — métricas devem refletir 1 instance conectada
tailscale ssh root@ct100-mcp '
  curl -fsS http://127.0.0.1:3000/health | jq ".instances"
  curl -fsS http://127.0.0.1:3000/metrics | grep whatsapp_baileys_session_state
'
```

Esperado: `state: connected`, gauge `whatsapp_baileys_session_state{...}=1`.

Enviar mensagem real (UI `/whatsapp/conversations` ou listener Repair). Conferir:
- Hostinger Loki: log `[whatsapp.send.baileys.ok]` com message_id.
- CT 100 Loki: span `whatsapp-baileys.daemon.send` com latência < 2s.
- Cliente recebe no WhatsApp. ✅

## 10. Habilitar Grafana dashboard

Importar dashboard `grafana/dashboards/whatsapp-baileys-daemon.json` (a criar — **fora desta US-WA-002d**, vira US separada). Painéis:
- Estado de sessão por instance (gauge timeseries)
- P50/P95 message lag
- Send/recv rate
- Bans 24h por business (alarme cross-tenant ≥ 3)
- Container restarts 24h

## 11. Backup / restore das sessões

```bash
# Backup diário (cron CT 100 03:00 BRT — adicionar ao backup geral)
tailscale ssh root@ct100-mcp '
  tar -czf /srv/backup/whatsapp-baileys-sessions-$(date +%F).tgz \
      -C /srv/docker/whatsapp-baileys sessions
'

# Restore (usar SÓ se sessions corrompeu — força re-scan QR de todos)
tailscale ssh root@ct100-mcp '
  cd /etc/docker-compose/services/whatsapp-baileys
  docker compose stop
  rm -rf /srv/docker/whatsapp-baileys/sessions/*
  tar -xzf /srv/backup/whatsapp-baileys-sessions-YYYY-MM-DD.tgz \
      -C /srv/docker/whatsapp-baileys
  docker compose start
'
```

## 12. Logs operacionais

```bash
# Acompanhar
tailscale ssh root@ct100-mcp 'docker logs -f --tail 200 whatsapp-baileys'

# Buscar erro específico (PII redacted nos logs do daemon)
tailscale ssh root@ct100-mcp 'docker logs whatsapp-baileys 2>&1 | grep -i "ban\|forbidden\|loggedOut"'
```

## 13. Rollback rápido

Se nova versão quebra:

```bash
tailscale ssh root@ct100-mcp '
  cd /etc/docker-compose/services/whatsapp-baileys
  sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=v0.0.9/" .env   # tag anterior conhecida boa
  docker compose pull
  docker compose up -d
'
```

Sessões persistidas em volume — não perdem auth state ao trocar tag.

## 14. Checklist DoD

- [ ] CT 100 acessível via Tailscale + redes traefik/observability presentes
- [ ] `/srv/docker/whatsapp-baileys/sessions/` criada com chmod 700
- [ ] Docker secret `whatsapp_baileys_api_key` registrada
- [ ] API key salva em Vaultwarden com label `whatsapp-baileys-api-key-prod`
- [ ] `/etc/docker-compose/services/whatsapp-baileys/.env` materializado
- [ ] `docker compose up -d` retorna container `Up (healthy)`
- [ ] `/health` interno retorna 200 + instances vazio
- [ ] IP whitelist testado: CT 100 = 403, Hostinger = 200
- [ ] 1 instance pareada (QR scaneado) + métrica `session_state=1`
- [ ] Mensagem real enviada/recebida ponta-a-ponta
- [ ] Backup cron diário das sessões configurado

## Apêndices

### A. Performance esperada
- Cada instance Whatsapp Web: ~80 MB RAM em regime
- CT 100 com 4 GB livres → ~30-40 instances (config `MAX_INSTANCES=30` por margem)
- Latência send: P95 < 1.5s (Baileys → Whatsapp Web → ack)
- Webhook outbound CT 100 → Hostinger: P95 < 800ms (mesma região)

### B. Quando escalar horizontal
≥ 25 instances ativas → planejar 2º container ou ir SaaS BSP
([ADR 0096 §16.10](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) trigger).

### C. Referências
- [baileys-troubleshoot-ban.md](baileys-troubleshoot-ban.md) — recuperação ban
- [baileys-upgrade-lib.md](baileys-upgrade-lib.md) — upgrade `@whiskeysockets/baileys`
- [ARCHITECTURE.md §16.6](../ARCHITECTURE.md) — compose canônico
