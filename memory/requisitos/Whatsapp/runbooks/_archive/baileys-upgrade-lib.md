---
id: requisitos-whatsapp-runbooks-archive-baileys-upgrade-lib
---

> ⚠️ **ARQUIVADO 2026-05-27** — BaileysDriver descontinuado por [ADR 0202](../../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).
> Conteúdo preservado como lição histórica. **NÃO aplicar em produção.**

# RUNBOOK · whatsapp-baileys daemon — Upgrade `@whiskeysockets/baileys`

> **Decisão mãe:** [ADR 0096 emenda 4](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md) (`review_trigger`: "Mudança Meta TOS quebra biblioteca")
> **Versão pinned atual:** ver `Modules/Whatsapp/daemon-node/package.json` campo `dependencies."@whiskeysockets/baileys"`
> **Quando rodar:**
>  - Bug crítico fixado em release nova (segurança / estabilidade)
>  - Meta TOS mudou e versão atual está quebrando ([§7](#7-quando-meta-tos-quebra-tudo))
>  - Sessões caindo > P95 5min sustained sem motivo aparente
>  - Mensalmente, em janela controlada (preventivo)

## 0. Princípio: nunca `latest`, sempre pinned

`@whiskeysockets/baileys` é **lib comunidade** que persegue a Meta. Patch comunidade demora dias-semanas após Meta mudar; durante esse gap, `latest` pode estar quebrado pra todo mundo. Pinning protege do efeito "atualizou e morreu".

Toda atualização passa por: ler changelog → bump pinned → CI build → canary 1 instance → 24h watch → rollout.

## 1. Pré-checagem (10 min)

```bash
# 1.1 — Estado atual da prod
tailscale ssh root@ct100-mcp '
  docker exec whatsapp-baileys node -e "console.log(require(\"@whiskeysockets/baileys/package.json\").version)"
'
# Saída esperada: ex 6.7.9

# 1.2 — Saúde antes da mudança (linha de base)
tailscale ssh root@ct100-mcp '
  curl -fsS http://127.0.0.1:3000/health | jq ".instances | length"
  curl -fsS http://127.0.0.1:3000/metrics | grep -E "session_state|ban_detected|message_lag"
'
# Anotar: total instances ativas, session_state=1 count, last 24h ban count

# 1.3 — Changelog & breaking changes
# Abrir https://github.com/WhiskeySockets/Baileys/releases entre versão atual e alvo
# Procurar por:
#   - Breaking changes na API makeWASocket / events
#   - Mudanças de schema do auth state (pode invalidar sessões!)
#   - Alteração de flags de browser fingerprint
```

> ⚠️ Se changelog menciona "auth state breaking" ou "credentials format change", **não dá pra hot-swap** — TODAS as instances precisam re-scan QR. Pular pra §6.

## 2. Bump em branch isolada (15 min)

```bash
# Local
git checkout -b feat/baileys-daemon-upgrade-vX.Y.Z
cd Modules/Whatsapp/daemon-node

# Editar package.json — UMA linha só
# "@whiskeysockets/baileys": "OLD_VERSION"  →  "NEW_VERSION"

npm install
npm run typecheck   # rebuild types — se quebrar aqui, lib mudou API
npm run lint
npm run test
npm run build
```

Se `typecheck` quebra:
- Verificar mudanças em `src/baileys/Instance.ts` (consumidor principal da lib)
- Provavelmente `WASocket`, `proto.IWebMessageInfo`, ou `DisconnectReason` mudaram
- Se mudança trivial: ajustar wrapper. Se mudança fundamental: ADR explicando mitigação.

## 3. Build da imagem candidata

### 3.1 — CI/CD

```bash
git add Modules/Whatsapp/daemon-node/package.json Modules/Whatsapp/daemon-node/package-lock.json
git commit -m "chore(baileys): upgrade @whiskeysockets/baileys to vX.Y.Z [W]

Refs: SPRINT-3 PASSO upgrade-lib"
git push -u origin feat/baileys-daemon-upgrade-vX.Y.Z
gh pr create --title "chore(baileys): upgrade @whiskeysockets/baileys to vX.Y.Z" \
  --body "$(cat <<'EOF'
## Resumo
- Bump `@whiskeysockets/baileys` da versão pinned anterior para vX.Y.Z
- Changelog crítico: <link releases>
- typecheck/lint/test/build passaram localmente

## Test plan
- [ ] CI build verde
- [ ] Smoke canary 1 instance CT 100 (24h watch)
- [ ] Rollout total (todas instances)
- [ ] Tag imagem final `vX.Y.Z` em ghcr.io

EOF
)"
```

CI builda imagem com tag `:vX.Y.Z-rc1` (release candidate). **Não substitui** `:latest` ainda.

### 3.2 — Manual (se CI não pronto)

```bash
rsync -av --exclude node_modules --exclude dist --exclude var \
      Modules/Whatsapp/daemon-node/ \
      root@ct100-mcp:/srv/build/whatsapp-baileys-daemon/

tailscale ssh root@ct100-mcp '
  cd /srv/build/whatsapp-baileys-daemon
  docker build -t oimpresso/whatsapp-baileys-daemon:vX.Y.Z-rc1 .
'
```

## 4. Canary 1 instance (24h watch)

A imagem RC roda em paralelo, recebendo apenas 1 business piloto. Estratégia: **container separado** apontando pra mesma volume, mas em outro `instance_id` dummy.

### 4.1 — Sobe canary side-by-side

```bash
tailscale ssh root@ct100-mcp '
  docker run -d \
    --name whatsapp-baileys-canary \
    --network traefik \
    --restart unless-stopped \
    -v /srv/docker/whatsapp-baileys/sessions-canary:/app/sessions \
    -e NODE_ENV=production \
    -e LOG_LEVEL=debug \
    -e WEBHOOK_BASE_URL=https://oimpresso.com/api/whatsapp/webhook/baileys \
    -e API_KEY_FILE=/run/secrets/whatsapp_baileys_api_key \
    -e MAX_INSTANCES=2 \
    --secret whatsapp_baileys_api_key \
    oimpresso/whatsapp-baileys-daemon:vX.Y.Z-rc1
'
```

### 4.2 — Aponta 1 business piloto pra canary

Não há cliente real piloto — usar **Wagner pessoal** ou **Eliana[E]**.
No painel Hostinger:
- Criar config Baileys de teste para `business_id` interno (Wagner test biz)
- Apontar `baileys_daemon_url` para `http://whatsapp-baileys-canary:3000` (rede interna CT 100)
- Parear QR
- Enviar 50 mensagens ao longo de 24h (manual + listener Repair se aplicável)

### 4.3 — Watch 24h

Verificar a cada 6h:

```bash
tailscale ssh root@ct100-mcp '
  docker logs whatsapp-baileys-canary --since 6h 2>&1 | grep -iE "error|fatal|ban|loggedOut" | tail -20
  curl -fsS http://127.0.0.1:3000/metrics | grep canary || true
'
```

Critérios pass:
- 0 unhandled errors / restarts
- `session_state=1` sustained
- 0 `ban_detected`
- Lag P95 igual ou melhor que prod atual
- Webhook delivery = 100%

Se algum critério falha → §5 rollback canary, investigar, ajustar.

## 5. Rollback canary (se §4.3 falhar)

```bash
tailscale ssh root@ct100-mcp '
  docker stop whatsapp-baileys-canary && docker rm whatsapp-baileys-canary
  rm -rf /srv/docker/whatsapp-baileys/sessions-canary/*
'

# Hostinger: remover config de teste
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 '
  cd ~/oimpresso.com
  php artisan tinker --execute="
    Modules\Whatsapp\Entities\WhatsappBusinessConfig::withoutGlobalScopes()
      ->where(\"business_id\", WAGNER_TEST_BIZ_ID)->delete();
  "'
```

PR fica aberto, marcar como `do not merge` até identificar causa.

## 6. Rollout total (após canary 24h ✅)

### 6.1 — Tagear como release

```bash
# CI: re-tag imagem rc1 → vX.Y.Z (sem rebuild)
docker pull oimpresso/whatsapp-baileys-daemon:vX.Y.Z-rc1
docker tag oimpresso/whatsapp-baileys-daemon:vX.Y.Z-rc1 \
           oimpresso/whatsapp-baileys-daemon:vX.Y.Z
docker push oimpresso/whatsapp-baileys-daemon:vX.Y.Z
```

### 6.2 — Atualizar prod compose

```bash
tailscale ssh root@ct100-mcp '
  cd /etc/docker-compose/services/whatsapp-baileys
  sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=vX.Y.Z/" .env
  docker compose pull
  docker compose up -d   # zero-downtime se healthcheck OK
'
```

> Compose com `restart: unless-stopped` + healthcheck → up -d substitui container, mas auth state é volume persistente (`/srv/docker/whatsapp-baileys/sessions/`), sessões **não precisam** re-scan QR.

### 6.3 — Verificar rollout

```bash
tailscale ssh root@ct100-mcp '
  docker exec whatsapp-baileys node -e "console.log(require(\"@whiskeysockets/baileys/package.json\").version)"
  curl -fsS http://127.0.0.1:3000/health | jq ".instances | map(.state) | group_by(.) | map({state: .[0], count: length})"
'
```

Esperado: versão = `vX.Y.Z`, todas instances `state=connected`.

### 6.4 — Watch 1h pós-rollout

Acompanhar Grafana dashboard `whatsapp-baileys-daemon` — alarme se `session_state` cair em qualquer instance, ou `ban_detected_total` incrementar.

### 6.5 — Limpar canary

```bash
tailscale ssh root@ct100-mcp '
  docker stop whatsapp-baileys-canary 2>/dev/null && docker rm whatsapp-baileys-canary 2>/dev/null
  rm -rf /srv/docker/whatsapp-baileys/sessions-canary
'
```

### 6.6 — Mergear PR

```bash
gh pr review --approve <PR_NUMBER>
gh pr merge --squash --delete-branch <PR_NUMBER>
```

## 7. Quando Meta TOS quebra tudo

Cenário emergência: Meta mudou TOS / device-link, Baileys vY.Y.Y atual quebrou em todas instances simultaneamente.

### 7.1 — Detectar
- Métrica `session_state` cai pra 0 em massa cross-tenant
- `whatsapp_baileys_send_total{status="failed"}` spike sincronizado
- Logs daemon spam `connection.update` com erros novos

### 7.2 — Mitigação imediata (todos businesses)
**Fallback Meta Cloud já assume automaticamente** — clientes não perdem WhatsApp se Meta Cloud está cadastrado (gating Tier 0 garante).

### 7.3 — Aguardar patch comunidade
- Watching `https://github.com/WhiskeySockets/Baileys/issues` (filtrar `connection`)
- Watching `https://github.com/WhiskeySockets/Baileys/releases`
- Histórico: patch comunitário sai em 24h-7d após mudança Meta

### 7.4 — Quando patch sair
Pular **diretamente para §3** (skip canary 24h se a alternativa é continuar quebrado). Reduzir watch para 2h pós-rollout.

### 7.5 — Documentar
Criar `memory/sessions/YYYY-MM-DD-baileys-meta-tos-incident.md`:
- Quando Meta mudou (timestamp do primeiro fail)
- Quanto tempo até patch comunidade (medir gap)
- Quantos businesses afetados, quantas mensagens em buffer
- Se ≥ 2 incidentes desse tipo em 6 meses → trigger ADR avaliar SaaS BSP ([§16.11 ADR 0096](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md))

## 8. Rollback emergencial pós-rollout

Se algo quebra após `up -d`:

```bash
tailscale ssh root@ct100-mcp '
  cd /etc/docker-compose/services/whatsapp-baileys
  # tag anterior conhecida boa (anota antes de cada upgrade!)
  sed -i "s/^IMAGE_TAG=.*/IMAGE_TAG=vX.Y.Z-PREVIOUS/" .env
  docker compose pull && docker compose up -d
'
```

Tag anterior **deve estar disponível** em registry (não fazer `docker image prune` agressivo entre upgrades).

## 9. Checklist DoD

- [ ] §1 Linha de base anotada (versão atual, instances ativas, métricas)
- [ ] §1.3 Changelog lido, breaking changes mapeadas
- [ ] §2 Bump em branch + typecheck/lint/test/build OK local
- [ ] §3 Imagem RC publicada no registry
- [ ] §4 Canary 1 instance 24h passou todos critérios
- [ ] §6 Rollout sem incidentes; versão prod = nova
- [ ] §6.6 PR mergeado
- [ ] Tag imagem anterior preservada no registry (rollback safety)
- [ ] Documentar versão atual no `memory/requisitos/Whatsapp/runbooks/baileys-upgrade-lib.md` ("versão pinned atual" no topo)

## 10. Cadência sugerida

| Tipo | Frequência | Motivo |
|---|---|---|
| Patch (z em x.y.z) | Mensal preventivo | Bugs, segurança, melhorias minor |
| Minor (y em x.y.z) | Após 30d na release | Pode ter API changes; aguardar comunidade testar |
| Major (x em x.y.z) | Apenas com ADR | Sempre breaking; merece ADR explicando |
| Hotfix Meta TOS | Imediato (§7) | Emergência |

## Apêndices

### A. Onde guardar versão pinned
- **Source de verdade:** `Modules/Whatsapp/daemon-node/package.json` + `package-lock.json` (Git)
- **Documentação humana:** topo deste runbook + `Modules/Whatsapp/daemon-node/README.md` tabela "Stack"
- **Image tag em prod:** `/etc/docker-compose/services/whatsapp-baileys/.env` linha `IMAGE_TAG=`

### B. Referências
- [baileys-daemon-deploy-ct100.md](baileys-daemon-deploy-ct100.md)
- [baileys-troubleshoot-ban.md](baileys-troubleshoot-ban.md)
- [ARCHITECTURE.md §16.10 risco 1](../ARCHITECTURE.md) — "Mudança Meta TOS quebra Baileys"
- [github.com/WhiskeySockets/Baileys/releases](https://github.com/WhiskeySockets/Baileys/releases)
