# RUNBOOK — Rebuild daemon Baileys CT 100

> **Decisão mãe:** [ADR 0096 emenda 4](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> **Auditoria post-incident:** [memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](../../../sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md)
> **Severidade procedimento:** P1 — manipula daemon prod CT 100 que afeta 100% das mensagens WhatsApp

## Quando rodar este runbook

Use quando precisar deployar mudanças do `Modules/Whatsapp/daemon-node/` (TypeScript daemon que vive em CT 100 Proxmox) pra produção.

**Triggers comuns:**

1. Cron `whatsapp:daemon-source-drift-check` alertou drift entre main local e daemon prod
2. PR mergeado tocou `Modules/Whatsapp/daemon-node/**` (CI `daemon-docker-build` passou ok)
3. Update da lib Baileys (usar runbook [baileys-upgrade-lib.md](baileys-upgrade-lib.md) primeiro, este depois)
4. Diagnóstico apontou bug catalogado neste runbook (group clash, source desatualizado, etc)

## Pré-requisitos

- Acesso Tailscale ao CT 100 (`tailscale ssh root@ct100-mcp` funciona)
- Permissão `gh` no repositório (pra `git archive` do main)
- ~10 minutos sem interrupção (re-pareamento dos canais pode estender)
- Wagner em standby pra escanear QR nos canais (se eles ficarem revogados após rebuild)

## Pegadinhas conhecidas (catalogadas 2026-05-13)

### Pegadinha 1 — `/srv/build/whatsapp-baileys-daemon/` NÃO É GIT REPO

Esperado que fosse `git clone`, mas é cópia manual via tar/rsync. Não rode `git pull` lá. Source é populated por este runbook.

### Pegadinha 2 — Dockerfile clash `groupadd: group 'daemon' already exists`

Base image `node:20-bookworm-slim` atualizou e agora tem group `daemon` reservado pelo sistema. Dockerfile que cria user `daemon` falha com exit 9. **Fix permanente em main**: renomeou pra `nodeapp` (commit pós-2026-05-13). Se Dockerfile ainda usar `daemon`, aplicar sed:

```bash
sed -i 's#groupadd --system --gid 1001 daemon#groupadd --system --gid 1001 nodeapp#g; \
        s#useradd --system --uid 1001 --gid daemon --home /app daemon#useradd --system --uid 1001 --gid nodeapp --home /app nodeapp#g; \
        s#chown -R daemon:daemon#chown -R nodeapp:nodeapp#g; \
        s#--chown=daemon:daemon#--chown=nodeapp:nodeapp#g; \
        s#^USER daemon#USER nodeapp#g' Dockerfile
```

### Pegadinha 3 — `npm run build` falha com `Cannot find module './antiBan'`

Significa que o source no CT 100 NÃO foi sincronizado completo — só alguns arquivos foram copiados, e Instance.ts referencia outros que não vieram. **Solução**: sempre sync DIRETÓRIO INTEIRO `src/` via tar, nunca arquivo-por-arquivo.

### Pegadinha 4 — Sessions Baileys ficam revogadas (`logged_out`) após restart

Quando container restart, Baileys reabre conexão WS com WhatsApp. Se o usuário desconectou via celular (Aparelhos Conectados → Sair) ANTES do restart, WhatsApp servidor responde `loggedOut` ao reconnect. **Solução**: após rebuild, sempre purgar instâncias `banned: logged_out` E pedir cliente re-escanear QR via UI.

### Pegadinha 5 — Healthcheck Docker reporta `healthy` mesmo com sockets zumbis

Antes do PR #821 (build :v823+), healthcheck só checava HTTP up. Agora checa zombies (state=connected + last_seen >30min) e retorna 503 → Docker restart policy reage. **Confirmar** que rebuild usa imagem :v823+ pra ter essa proteção.

## Procedimento canônico (5 passos)

### Passo 0 — Pre-flight (1 min)

```bash
# Confirma daemon atual está vivo (rollback será mais fácil se OK)
tailscale ssh root@ct100-mcp 'docker ps --format "{{.Names}}: {{.Status}}" | grep whatsapp-baileys'

# Captura SHA atual pra registro
tailscale ssh root@ct100-mcp 'curl -fsS http://localhost:3000/health 2>/dev/null | grep -o "daemon_source_sha\":\"[^\"]*\""'

# Hash do main local (pra comparar pós-rebuild)
git -C ~/oimpresso.com rev-parse HEAD:Modules/Whatsapp/daemon-node/src
```

### Passo 1 — Backup (1 min)

```bash
tailscale ssh root@ct100-mcp '
  docker tag oimpresso/whatsapp-baileys-daemon:latest \
             oimpresso/whatsapp-baileys-daemon:backup-$(date +%Y%m%d-%H%M)
  cd /srv/build/whatsapp-baileys-daemon
  tar -czf /tmp/daemon-source-backup-$(date +%Y%m%d-%H%M).tar.gz \
      src Dockerfile package.json package-lock.json tsconfig.json
'
```

Rollback rápido se precisar:
```bash
# Image
docker tag oimpresso/whatsapp-baileys-daemon:backup-YYYYMMDD-HHMM oimpresso/whatsapp-baileys-daemon:latest

# Source
cd /srv/build/whatsapp-baileys-daemon && tar -xzf /tmp/daemon-source-backup-YYYYMMDD-HHMM.tar.gz
```

### Passo 2 — Sync source main → CT 100 (2 min)

```bash
# Local: empacota source do main HEAD
git fetch origin main
git archive origin/main --prefix=daemon-node/ \
  Modules/Whatsapp/daemon-node/src \
  Modules/Whatsapp/daemon-node/Dockerfile \
  Modules/Whatsapp/daemon-node/package.json \
  Modules/Whatsapp/daemon-node/package-lock.json \
  Modules/Whatsapp/daemon-node/tsconfig.json \
  --format=tar.gz -o /tmp/daemon-from-main.tar.gz

# Upload + extract no CT 100
cat /tmp/daemon-from-main.tar.gz | tailscale ssh root@ct100-mcp '
  cat > /tmp/daemon-from-main.tar.gz
  mkdir -p /tmp/daemon-extract
  cd /tmp/daemon-extract && tar -xzf /tmp/daemon-from-main.tar.gz

  SRC=/tmp/daemon-extract/daemon-node/Modules/Whatsapp/daemon-node
  cd /srv/build/whatsapp-baileys-daemon

  rm -rf src
  cp -r $SRC/src ./src
  cp $SRC/package.json ./package.json
  cp $SRC/package-lock.json ./package-lock.json
  cp $SRC/tsconfig.json ./tsconfig.json
  cp $SRC/Dockerfile ./Dockerfile

  echo "✅ Source sincronizado"
  ls src/baileys/Instance.ts src/webhook/WebhookDispatcher.ts
'
```

### Passo 3 — Build com SHA build-arg (3 min)

```bash
LOCAL_SHA=$(git rev-parse HEAD:Modules/Whatsapp/daemon-node/src)
echo "Building com SHA: $LOCAL_SHA"

tailscale ssh root@ct100-mcp "
  cd /srv/build/whatsapp-baileys-daemon
  docker build \\
    --build-arg DAEMON_SOURCE_SHA=$LOCAL_SHA \\
    -t oimpresso/whatsapp-baileys-daemon:v\$(date +%Y%m%d) \\
    .
"
```

Se build falhar:
- **`groupadd: group 'daemon' already exists`** → ver Pegadinha 2
- **`Cannot find module './antiBan'`** → ver Pegadinha 3 (sync incompleto)
- **`npm ERR! 404 Not Found`** → package-lock.json desatualizado vs Baileys disponível

### Passo 4 — Restart container preservando config (2 min)

```bash
# Captura comando docker run completo da config atual (preserva env vars + mounts)
tailscale ssh root@ct100-mcp 'docker inspect whatsapp-baileys --format "docker run -d --name whatsapp-baileys --network {{.HostConfig.NetworkMode}} --restart unless-stopped --memory 3g --memory-reservation 512m --cpus 2 --read-only --tmpfs /tmp:size=64m,mode=1777 {{range .Mounts}}-v {{.Source}}:{{.Destination}}{{if not .RW}}:ro{{end}} {{end}}{{range .Config.Env}}{{if and (ne . \"PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin\") (ne . \"NODE_VERSION=20.18.0\") (ne . \"YARN_VERSION=1.22.22\")}}-e {{printf \"%q\" .}} {{end}}{{end}}oimpresso/whatsapp-baileys-daemon:latest" > /tmp/restart-baileys.sh'

# Tag nova versão como latest E reinicia
NEW_TAG="v$(date +%Y%m%d)"
tailscale ssh root@ct100-mcp "
  docker tag oimpresso/whatsapp-baileys-daemon:$NEW_TAG oimpresso/whatsapp-baileys-daemon:latest
  docker stop whatsapp-baileys
  docker rm whatsapp-baileys
  bash /tmp/restart-baileys.sh
  sleep 10
  docker ps --format '{{.Names}}: {{.Status}}' | grep whatsapp-baileys
"
```

### Passo 5 — Validação pós-deploy (1 min)

```bash
tailscale ssh root@ct100-mcp '
  # 1. Container healthy
  docker inspect whatsapp-baileys --format "{{.State.Health.Status}}"

  # 2. SHA novo bate com main
  curl -fsS http://localhost:3000/health | grep daemon_source_sha

  # 3. Logs sem erros fatais
  docker logs whatsapp-baileys --tail 20 2>&1 | grep -E "level\":(50|60)" | head -5
'
```

**Expected:**
- Container `healthy`
- `daemon_source_sha` = hash do main local
- Sem logs level 50/60 nos últimos 20 logs

## Pós-rebuild — gerenciar sessões revogadas (1-10 min, depende cliente)

Após restart, instâncias Baileys reconectam com creds salvas no MySQL auth state. **MAS:** se cliente desconectou via celular antes, sessões viram `banned: logged_out`. Verificar:

```bash
tailscale ssh root@ct100-mcp '
  TOKEN=$(docker exec whatsapp-baileys cat /run/secrets/whatsapp_baileys_api_key)
  # Listar instances banned via DB Hostinger é mais rápido — use:
  #   ssh hostinger "cd ~/domains/oimpresso.com/public_html && \\
  #     php artisan tinker --execute=\"
  #       Modules\\Whatsapp\\Entities\\Channel::withoutGlobalScopes()
  #         ->where(\\\"channel_health\\\", \\\"banned\\\")->get();
  #     \""
'
```

Pra cada instância `banned`:

1. **Purgar daemon-side** (limpa creds revogadas + para loop reconnect):
   ```bash
   php artisan whatsapp:channel-reset {channel_id}
   ```
2. **Cliente re-pareia** via UI `/atendimento/canais` → clica Conectar → scaneia QR no celular
3. **Com daemon :v823+**, syncFullHistory:true → daemon puxa ~90d histórico automaticamente

## Após deploy estável — gerar tasks MCP de follow-up

```bash
# Cron drift check passa a alertar se daemon ficar atrás de novo
ssh hostinger 'cd ~/domains/oimpresso.com/public_html && \
  php artisan whatsapp:daemon-source-drift-check'
```

Output esperado: `✅ Daemon CT 100 está EM SYNC com main local.`

## Rollback emergencial

Se deploy quebra prod:

```bash
tailscale ssh root@ct100-mcp '
  # Restaura imagem backup
  docker tag oimpresso/whatsapp-baileys-daemon:backup-YYYYMMDD-HHMM oimpresso/whatsapp-baileys-daemon:latest

  # Reinicia com mesma config (script salvado no Passo 4)
  docker stop whatsapp-baileys && docker rm whatsapp-baileys
  bash /tmp/restart-baileys.sh

  # Restaura source (pra próximo build não pegar versão quebrada)
  cd /srv/build/whatsapp-baileys-daemon
  tar -xzf /tmp/daemon-source-backup-YYYYMMDD-HHMM.tar.gz
'
```

## Histórico de incidentes mitigados por este runbook

- **2026-05-13** — 4h investigação pra descobrir que `/srv/build/whatsapp-baileys-daemon/` não é git repo + Dockerfile clash + sync incompleto. Este runbook codifica o procedimento descoberto.

## Referências

- [memory/decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) — ADR mãe Baileys driver custom
- [memory/sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md](../../../sessions/2026-05-13-whatsapp-incident-zombie-banned-loop.md) — incident origem deste runbook
- [.github/workflows/daemon-docker-build.yml](../../../../.github/workflows/daemon-docker-build.yml) — CI que bloqueia merge se Dockerfile/build quebra
- [Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php](../../../../Modules/Whatsapp/Console/Commands/DaemonSourceDriftCheckCommand.php) — cron weekly alerta drift
- [baileys-upgrade-lib.md](baileys-upgrade-lib.md) — runbook complementar (update versão Baileys)
- [baileys-troubleshoot-ban.md](baileys-troubleshoot-ban.md) — runbook complementar (cliente banned)
