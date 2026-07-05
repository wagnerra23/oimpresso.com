# Runbook · Deploy daemon Whatsmeow Go (WuzAPI) no CT 100

> **Status:** canon · **Última atualização:** 2026-05-27
> **Decisão arquitetural:** [ADR 0204](../../../decisions/0204-whatsmeow-driver-substituto-baileys.md)
> **Substitui:** daemon Node Baileys (descontinuado [ADR 0202](../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md))
> **Pré-requisitos:** acesso SSH CT 100 (`root@ct100.oimpresso.local`) + Tailscale + Docker + secret manager

## Objetivo

Subir container Docker `whatsapp-whatsmeow` no CT 100, expor via Traefik com IP whitelist Hostinger, configurar webhook bidirecional com Laravel app, validar smoke teste com 1ª sessão WhatsApp.

## Pré-check (5 min)

```bash
# 1. SSH no CT 100
ssh root@ct100.oimpresso.local  # OU via Tailscale: ssh root@ct100

# 2. Confirma Docker + Traefik rodando
docker ps | grep -E "traefik|centrifugo"

# 3. Confirma DNS já apontando
dig whatsapp-whatsmeow.oimpresso.com  # esperado: IP CT 100
# Se não: adicionar A record no Cloudflare ANTES de prosseguir

# 4. Confirma rede Docker compartilhada
docker network ls | grep oimpresso_default
```

## Passo 1 — Provisionar storage persistente (2 min)

```bash
# Volumes pra sessões WhatsApp pareadas + cache de mídia
mkdir -p /srv/docker/whatsapp-whatsmeow/{sessions,files}
chown -R root:root /srv/docker/whatsapp-whatsmeow
chmod -R 700 /srv/docker/whatsapp-whatsmeow  # sessões = credencial sensível

# Backup directory pra cron daily
mkdir -p /srv/backup/whatsmeow
```

> **Warning:** `/srv/docker/whatsapp-whatsmeow/sessions/` armazena credenciais WhatsApp Web (auth state pareada). Perder essas pastas = todos os channels desconectados, Wagner precisa re-scan QR em cada um. **Recomendado:** FS encrypted (LUKS) ou volume snapshot regular.

## Passo 2 — Gerar segredos criptograficamente fortes (1 min)

```bash
# Admin token (Bearer auth pra criar users no daemon)
openssl rand -hex 32 > /run/secrets/whatsmeow_admin_token
chmod 600 /run/secrets/whatsmeow_admin_token

# HMAC secret (assina webhooks daemon → Laravel)
openssl rand -hex 32 > /run/secrets/whatsmeow_hmac_secret
chmod 600 /run/secrets/whatsmeow_hmac_secret

# Anotar valores pra colocar no .env Hostinger DEPOIS (passo 5)
cat /run/secrets/whatsmeow_admin_token
cat /run/secrets/whatsmeow_hmac_secret
# IMPORTANTE: copiar valores AGORA — depois `cat` exige acessar arquivo /run/secrets
```

Salvar no Vaultwarden:
- Entry: **"WhatsApp Whatsmeow Admin Token"** — valor `whatsmeow_admin_token`
- Entry: **"WhatsApp Whatsmeow HMAC Secret"** — valor `whatsmeow_hmac_secret`

## Passo 3 — Materializar docker-compose (3 min)

```bash
mkdir -p /opt/oimpresso/whatsmeow
cd /opt/oimpresso/whatsmeow

# Copiar docker-compose.yml do repo (já versionado)
# Opção A — git clone se não tem ainda:
git clone https://github.com/wagnerra/oimpresso.git /tmp/oimpresso
cp /tmp/oimpresso/Modules/Whatsapp/daemon-go/docker-compose.yml .

# Opção B — copiar via scp do dev local:
# scp Modules/Whatsapp/daemon-go/docker-compose.yml root@ct100:/opt/oimpresso/whatsmeow/

# Verificar service correto
docker compose config --quiet  # silencia se OK
```

## Passo 4 — Subir container + smoke interno (5 min)

```bash
cd /opt/oimpresso/whatsmeow

# Up em background
docker compose up -d

# Aguardar healthy (~30s)
docker compose ps  # esperado: STATUS = "Up (healthy)"

# Logs iniciais — deve mostrar bind 8080 + DB init
docker compose logs --tail=50 whatsapp-whatsmeow

# Smoke interno (rede Docker — não usa Traefik ainda)
docker exec whatsapp-whatsmeow wget -q -O - http://localhost:8080/health
# esperado: {"status":"ok"} OU equivalente WuzAPI
```

## Passo 5 — Configurar Laravel `.env` Hostinger (2 min)

No painel Hostinger ou SSH:

```bash
# Editar .env Laravel produção
$EDITOR /home/u606057284/domains/oimpresso.com/public_html/.env

# Adicionar (substituir valores pelos gerados no Passo 2):
WHATSMEOW_DAEMON_URL=https://whatsapp-whatsmeow.oimpresso.com
WHATSMEOW_API_KEY=<conteúdo de /run/secrets/whatsmeow_admin_token>
WHATSMEOW_HMAC_SECRET=<conteúdo de /run/secrets/whatsmeow_hmac_secret>
WHATSMEOW_TIMEOUT=15

# Limpar cache config Laravel
php artisan config:clear
```

## Passo 6 — Smoke público via Traefik (5 min)

Do Hostinger app (IP whitelist 148.135.133.115/32 permitido):

```bash
# Conferir Traefik route ativa
curl -sI https://whatsapp-whatsmeow.oimpresso.com
# esperado: HTTP/2 200 (OU 401 sem auth — Tier 0 esperado)

# Listar users (deve estar vazio inicial)
curl -H "Authorization: Bearer $WHATSMEOW_API_KEY" \
  https://whatsapp-whatsmeow.oimpresso.com/admin/users
# esperado: {"data":[]} ou similar
```

De fora do whitelist (qualquer outro IP): deve dar **403 Forbidden** pelo Traefik middleware.

## Passo 7 — Primeira sessão (Wagner como cliente piloto, 5 min)

1. Wagner abre `https://oimpresso.com/atendimento/canais`
2. Clica **"Adicionar canal"**
3. Seleciona tipo **"WhatsApp Whatsmeow (Go)"**
4. Preenche label (ex: "Jana Comercial") + telefone E.164 (ex: `+5548999000000`) + aceita LGPD
5. **"Salvar canal"** → channel criado com `status=setup`
6. Clica **"Conectar"** no card do channel novo
7. Backend chama `POST /admin/users` no daemon → daemon cria sessão `ch-{uuid}` + retorna QR base64
8. Modal mostra QR PNG
9. Wagner abre celular → WhatsApp → Configurações → Dispositivos vinculados → escaneia QR
10. Daemon dispara webhook `Connected` → Laravel atualiza `channel.status=active` + UI Centrifugo realtime detecta

## Passo 8 — Backup diário

> ⚠️ **CORREÇÃO 2026-07-05:** o `tar` inline abaixo copia `main.db`/`users.db` **abertos em WAL** →
> pode gravar um backup inconsistente/corrompido (`.db` sem os frames do `-wal`). Use o script
> versionado **[`infra/scripts/backup-whatsmeow-auth.sh`](../../../../infra/scripts/backup-whatsmeow-auth.sh)**
> (online backup API do SQLite + `integrity_check` + sanity `whatsmeow_device>0`) via cron
> **[`infra/cron/baileys-backup`](../../../../infra/cron/baileys-backup)**. Restore:
> [`restore-auth-state.md`](restore-auth-state.md). O bloco `tar` abaixo fica só como referência
> histórica — **não instalar**.

```bash
# (HISTÓRICO — NÃO USAR. Ver correção acima: tar de SQLite-WAL aberto não é consistente.)
# /etc/cron.daily/backup-whatsmeow
cat > /etc/cron.daily/backup-whatsmeow << 'EOF'
#!/bin/bash
DATE=$(date +%F)
tar czf /srv/backup/whatsmeow/whatsmeow-$DATE.tar.gz \
  /srv/docker/whatsapp-whatsmeow/sessions \
  2>&1

# Retenção: mantém últimos 14 dias
find /srv/backup/whatsmeow/ -name "whatsmeow-*.tar.gz" -mtime +14 -delete
EOF
chmod +x /etc/cron.daily/backup-whatsmeow

# Smoke imediato
/etc/cron.daily/backup-whatsmeow
ls -la /srv/backup/whatsmeow/
```

## Operação contínua

### Health check periódico

```bash
docker compose ps whatsapp-whatsmeow
# espera: STATUS "Up (healthy)"

# Se unhealthy persistente:
docker compose logs --tail=200 whatsapp-whatsmeow
```

### Upgrade WuzAPI

```bash
cd /opt/oimpresso/whatsmeow
docker compose pull
docker compose up -d  # zero downtime se compose recria com mesma volume
docker image prune -f  # limpa images velhas
```

### Restart sem perder sessões

```bash
docker compose restart whatsapp-whatsmeow
```

### Listar sessões ativas

```bash
curl -H "Authorization: Bearer $(cat /run/secrets/whatsmeow_admin_token)" \
  https://whatsapp-whatsmeow.oimpresso.com/admin/users \
  | jq '.data[] | {name, connected, jid}'
```

### Deletar sessão (rollback / cliente cancelou)

```bash
# Substituir ch-uuid pelo nome real (vem de channel.config_json.whatsmeow_user_name)
curl -X DELETE \
  -H "Authorization: Bearer $(cat /run/secrets/whatsmeow_admin_token)" \
  https://whatsapp-whatsmeow.oimpresso.com/admin/users/ch-aaaaa
```

## Troubleshooting

| Sintoma | Diagnóstico | Mitigação |
|---|---|---|
| `docker compose up` falha em "network not found" | Rede `oimpresso_default` não existe | `docker network create oimpresso_default` ou ajustar `external: false` |
| QR não aparece no UI Laravel | Daemon retorna 401 ou 503 | Confere `WHATSMEOW_API_KEY` Laravel = `/run/secrets/whatsmeow_admin_token` CT 100 |
| Webhook não chega no Laravel | IP outbound CT → Hostinger não passa | Confere DNS público + Traefik permite saída + Hostinger aceita IP CT 100 inbound |
| Channel marcado `banned` após scan | Onda detecção Meta 2026 (whatsmeow issue #810) | Esperado — risco aceito ADR 0204. Fallback Meta Cloud ativa |
| `429` no webhook receiver | Backpressure queue depth > max | Reduzir taxa ou aumentar `WHATSAPP_QUEUE_MAX_DEPTH` (US-WA-084) |
| Volume sessões some após reboot | Volume bind path errado | Confere `docker compose config` tem mount `/srv/docker/whatsapp-whatsmeow/sessions:/app/dbdata` |
| `Token` header rejeitado (401) | user_token Laravel desincronizado | Re-provisiona channel: `DELETE` no daemon + recria via UI Conectar |

## Métricas operacionais (Wagner monitorar)

Daily (`jana:health-check` cron 06:00 BRT):

- Sessões whatsmeow conectadas: `channels.channel_health` em (healthy, never_checked) WHERE type=whatsapp_whatsmeow
- Sessões banidas: `channels.channel_health=banned` WHERE type=whatsapp_whatsmeow
- Cross-tenant ban threshold: ≥3 channels banidos em 24h → ALERT (`whatsapp.cross_tenant_ban_alarm_threshold`)

Weekly:
- RAM uso daemon: `docker stats whatsapp-whatsmeow --no-stream`
- Backup size growth: `du -sh /srv/backup/whatsmeow/`

Monthly:
- Comparar custo CT 100 + RAM daemon vs Meta Cloud msg cost por business
- Avaliar mover channels antigos pra Meta Cloud (cliente maduro)

## Rollback

Se daemon whatsmeow se mostrar problemático (instabilidade, custo, abuse):

1. Marcar canais `whatsapp_whatsmeow` como `enabled=false` no `availableTypesForUi()` (commit + deploy)
2. Migrar channels existentes pra Meta Cloud (cliente final re-scan via Embedded Signup v4)
3. Stop container: `docker compose down` no CT 100
4. Backup final volume: `tar czf /srv/backup/whatsmeow/final-pre-decom.tar.gz /srv/docker/whatsapp-whatsmeow/`
5. Remover volume: `rm -rf /srv/docker/whatsapp-whatsmeow/` (após confirmar backup)
6. Marcar `whatsmeow` em `forbidden_drivers` no `Modules/Whatsapp/Config/config.php`
7. ADR amend formal explicando o que aconteceu

## Referências

- [ADR 0204](../../../decisions/0204-whatsmeow-driver-substituto-baileys.md) — esta decisão
- [ADR 0202](../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md) — Baileys descontinuado integral
- [ADR 0058](../../../decisions/0058-reverb-substituido-por-centrifugo-frankenphp.md) — runtime CT 100 Traefik
- [ADR 0062](../../../decisions/0062-separacao-runtime-hostinger-ct100.md) — Hostinger ≠ CT 100
- [WuzAPI GitHub](https://github.com/asternic/wuzapi)
- [WuzAPI API.md](https://github.com/asternic/wuzapi/blob/main/API.md)
- [whatsmeow GitHub](https://github.com/tulir/whatsmeow)
- [Modules/Whatsapp/daemon-go/](../../../../Modules/Whatsapp/daemon-go/) — docker-compose + README
