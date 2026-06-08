> ⚠️ **ARQUIVADO 2026-05-27** — BaileysDriver descontinuado por [ADR 0202](../../../../decisions/0202-whatsapp-profissionalizacao-baileys-out.md).
> Conteúdo preservado como lição histórica. **NÃO aplicar em produção.**

# RUNBOOK · whatsapp-baileys daemon — Migração Baileys 6.7.18 → 7.0.0-rc11

> **Decisão mãe:** [ADR 0096 emenda 4](../../../decisions/0096-modulo-whatsapp-meta-cloud-api-direto.md)
> **Decisão Wagner:** [`memory/reference/feedback-baileys-7x-decisao-irreversivel.md`](../../../reference/feedback-baileys-7x-decisao-irreversivel.md) — Baileys 7.x IRREVERSÍVEL
> **Versão alvo:** `7.0.0-rc11` (latest 7.x npm em 2026-05-15)
> **Skill canônica:** [`baileys-update-procedure`](../../../../.claude/skills/baileys-update-procedure/SKILL.md) — 5-fase pre-check / migration / build / smoke / rollback
> **Runbook genérico upgrade:** [`baileys-upgrade-lib.md`](baileys-upgrade-lib.md) — usar pra upgrades menores 7.x→7.y
>
> Este RUNBOOK é específico pra migração 6.x → 7.x (mudança major com ESM-only, novos campos key.remoteJidAlt/participantAlt, breaking changes API).

## Por que esta migração

Baileys 7.x traz:
- `key.remoteJidAlt` / `key.participantAlt` — JID alternativo expõe phone real quando `remoteJid` é `@lid` (workaround LidPhoneResolver custom fica supérfluo na maioria dos casos)
- Fixes para `Connection Failure` em pairing, melhor handling de re-pareamento (mitiga incident cross-contact 2026-05-14)
- ESM-only desde 6.8.0 — daemon precisa migrar `module: NodeNext`
- Compatibilidade com schema 3-identifiers do oimpresso (PR #855 — `conversations.lid` / `phone_e164`)

Decisão é irreversível ([feedback Wagner 2026-05-15](../../../reference/feedback-baileys-7x-decisao-irreversivel.md)). Não citar issues abertas como motivo pra adiar.

## Pré-requisitos antes de deploy CT 100

- [ ] PR de código mergeado em `main` (este PR `claude/wa-baileys-7x-migration`)
- [ ] PR #855 (schema 3-identifiers) mergeado — backend pronto pra ler `lid`/`phone_e164`
- [ ] PR #857 (backup auth_state) mergeado — mitiga perda 90d em re-pareamento
- [ ] Wagner aprovou janela de deploy (recomendado: madrugada BRT, baixo tráfego ROTA LIVRE)
- [ ] Aviso prévio cliente Larissa (ROTA LIVRE biz=4) — 99% do volume, qualquer indisponibilidade é crítica
- [ ] Snapshot pré-deploy: `npm view @whiskeysockets/baileys versions --json | tail -10` confirma 7.0.0-rc11 ainda é latest 7.x

## Fase 1 — Pre-check (5 min)

```bash
# Versão atual no daemon CT 100
tailscale ssh root@ct100-mcp '
  docker exec whatsapp-baileys node -e "console.log(require(\"@whiskeysockets/baileys/package.json\").version)"
'
# Saída esperada: 6.7.18

# Confirma 7.0.0-rc11 disponível
npm view @whiskeysockets/baileys@7.0.0-rc11 version
# Saída esperada: 7.0.0-rc11

# Saúde antes da mudança — linha de base
tailscale ssh root@ct100-mcp '
  curl -fsS http://127.0.0.1:3000/health | jq ".instances | length"
  curl -fsS http://127.0.0.1:3000/metrics | grep -E "session_state|ban_detected|message_lag" | head -10
'
# Anotar: total instances ativas, session_state=1 count, last 24h ban count

# Backup auth_state biz=99 sandbox (single instance teste)
tailscale ssh root@ct100-mcp '
  cp -r /srv/docker/whatsapp-baileys/sessions/99-sandbox /srv/docker/whatsapp-baileys/sessions/99-sandbox.pre-7x.bak
'
```

## Fase 2 — Migration ESM (já feita no código deste PR)

Mudanças no codebase (já commitadas neste PR):
- `Modules/Whatsapp/daemon-node/package.json` — `"type": "module"` + bump baileys → `7.0.0-rc11`
- `Modules/Whatsapp/daemon-node/tsconfig.json` — `module: NodeNext` + `moduleResolution: NodeNext`
- 27 arquivos `.ts` — imports relativos com sufixo `.js` adicionado automaticamente

CT 100 só precisa puxar git + reinstalar deps + rebuild:

```bash
tailscale ssh root@ct100-mcp '
  cd /opt/whatsapp-baileys/build &&
  git fetch origin &&
  git checkout main &&
  git pull origin main &&
  rm -rf node_modules package-lock.json
'
```

## Fase 3 — Build + deploy (10 min)

```bash
tailscale ssh root@ct100-mcp '
  cd /opt/whatsapp-baileys/build &&
  docker compose build --no-cache whatsapp-baileys 2>&1 | tail -20
'

# Sucesso esperado: `npm install` resolve 7.0.0-rc11, `tsc` compila sem erro,
# image final pesa ~similar à anterior (±5%).

tailscale ssh root@ct100-mcp '
  cd /opt/whatsapp-baileys/build &&
  docker compose up -d whatsapp-baileys 2>&1 | tail -5
'

# Smoke check imediato (5s warmup)
sleep 5
tailscale ssh root@ct100-mcp '
  docker logs whatsapp-baileys --tail 20 2>&1 | grep -iE "ready|error|ERR_REQUIRE_ESM|MODULE_NOT_FOUND"
'
```

**Esperado:** linha `whatsapp-baileys-daemon ready` SEM `ERR_REQUIRE_ESM` nem `MODULE_NOT_FOUND`.

**Se aparecer `ERR_REQUIRE_ESM`:** algum import relativo escapou do auto-patch — checar com `grep -rn "from ['\\\"]\\.\\." dist/ | grep -v '.js[\\\"\\']'`.

## Fase 4 — Smoke test pairing biz=99 sandbox (15 min síncrono)

> ⛔ **NUNCA fazer smoke em biz=4 prod ROTA LIVRE (Larissa).** Apenas `business_uuid` de sandbox (`00000000-0000-0000-0000-000000000099`).

```bash
tailscale ssh root@ct100-mcp '
  CID=$(docker ps -q --filter name=whatsapp-baileys);
  CIP=$(docker inspect "$CID" --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}");
  API_KEY=$(cat /srv/secrets/whatsapp_baileys_api_key);

  # 1. Cria instance sandbox biz=99
  curl -s -X POST -H "Authorization: Bearer $API_KEY" -H "Content-Type: application/json" \
    -d "{\"business_uuid\":\"00000000-0000-0000-0000-000000000099\",\"business_id\":99}" \
    "http://$CIP:3000/instances/smoke-7x/connect" >/dev/null

  sleep 8

  # 2. Pega QR
  curl -s -H "Authorization: Bearer $API_KEY" "http://$CIP:3000/instances/smoke-7x/status" | jq .
'
```

**Esperado:**
- `state` = `qr_required`
- `qr` field populado como `data:image/png;base64,...`
- Wagner usa um WhatsApp de TESTE pessoal (não pessoal real diário) pra escanear o QR

**Após pareamento:**
1. Wagner manda 1 mensagem de teste do phone pareado pro número canal
2. Verificar webhook chega no Hostinger: `tail -f storage/logs/laravel.log | grep "channel.baileys.webhook"`
3. Validar `messages` table tem registro com `provider_message_id`
4. **Validação chave Baileys 7.x:** payload contém `key.remoteJidAlt` (não tinha em 6.7.18) — `grep "remoteJidAlt" storage/logs/laravel.log`
5. Validar `conversations.customer_external_id` é E.164 correto (sem LID bruto)

```bash
# 3. Cleanup sandbox depois do smoke
tailscale ssh root@ct100-mcp '
  curl -s -X DELETE -H "Authorization: Bearer $API_KEY" "http://$CIP:3000/instances/smoke-7x"
'
```

## Fase 5 — Canary 7 dias biz=99 sandbox

Antes de promover pra prod biz=1:

- [ ] Smoke pairing biz=99 passou (Fase 4 OK)
- [ ] 7 dias rodando biz=99 sandbox sem `ban_detected` (métrica Prometheus `whatsapp_baileys_ban_detected_total`)
- [ ] Webhook latency p95 < 500ms (sem regressão vs 6.7.18 baseline)
- [ ] Sem `Connection Failure` recorrente em logs CT 100 (`docker logs whatsapp-baileys 2>&1 | grep "Connection Failure" | wc -l` deve ficar < 10/dia)
- [ ] Re-pairing voluntário aos 3-4 dias (testar resiliência): backup auth_state PR #857 restaura sem perda 90d
- [ ] Pest `Baileys7xPayloadShapeTest` passa local + CI (5 it's: remoteJidAlt resolve, back-compat 6.7.x, prioridade senderPn>alt, no_remote_jid guard, only-alt)

**Métricas a observar (Grafana):**
- `whatsapp_baileys_session_state{state="connected"}` — deve ficar estável
- `whatsapp_baileys_message_lag_seconds_p95` — comparar com baseline 6.7.18
- `whatsapp_history_chunk_queued` / `_processed` rate — incremental, não acumular backlog

## Fase 6 — Promoção biz=4 prod ROTA LIVRE

> ⛔ Só executar se canary 7d biz=99 (sandbox) + 7d biz=1 (WR2 dev) passou TODAS as métricas acima.
> ⛔ Janela: madrugada BRT, com aviso prévio Larissa.

```bash
# Em biz=4 prod NÃO é "deploy diferente" — daemon CT 100 já está com Baileys 7.x
# desde Fase 3. Promoção é só LIBERAR pareamento Baileys nos canais biz=4 (ROTA LIVRE)
# existentes (atualmente paused/pinned em 6.7.18-friendly mode se houver flag).

# Wagner valida 1 canal biz=1 (`Suorte` id=2 ou similar) reage normal pós-deploy:
# - mensagens entrando OK
# - re-pairing voluntário não dropa 90d history
# - cross-contact NÃO acontece (incident 2026-05-14 resolvido)
```

## Fase 7 — Rollback se falhar

```bash
tailscale ssh root@ct100-mcp '
  cd /opt/whatsapp-baileys/build &&
  git log --oneline | head -5
  # Identifica SHA pré-merge migração 7.x
  git checkout <SHA-PRE-7X> -- Modules/Whatsapp/daemon-node/
  docker compose build --no-cache whatsapp-baileys &&
  docker compose up -d
'
```

Restaurar auth_state biz=99 backup:
```bash
tailscale ssh root@ct100-mcp '
  rm -rf /srv/docker/whatsapp-baileys/sessions/99-sandbox
  cp -r /srv/docker/whatsapp-baileys/sessions/99-sandbox.pre-7x.bak /srv/docker/whatsapp-baileys/sessions/99-sandbox
'
```

Se rollback exigido, criar US-WA-XXX investigando issue real específica antes de tentar de novo.

## Gotchas observados DURANTE migração

> Esta seção é preenchida APÓS execução real — bugs encontrados pelo Wagner durante deploy CT 100 entram aqui (não preventivamente).

- [ ] _vazio até deploy real ocorrer_

## Anti-padrões

- ❌ Deploy direto biz=4 prod ROTA LIVRE sem canary 7d biz=99 (sandbox) + biz=1 (WR2 dev) — incident 2026-05-14 prova que cross-contact é caro
- ❌ Force re-build sem `rm node_modules package-lock.json` — cache pode mascarar incompatibilidade ESM
- ❌ Smoke em biz=4 cliente real — ADR 0101 IRREVOGÁVEL: `business_id=99` pra todos smokes
- ❌ Citar issues rc.X do Baileys 7.x como motivo pra adiar deploy — decisão Wagner é irreversível ([feedback 2026-05-15](../../../reference/feedback-baileys-7x-decisao-irreversivel.md))
- ❌ Mudar `package.json` direto no CT 100 sem PR no git — drift de governança (ADR 0061)

## Referências cruzadas

- [Feedback Baileys 7.x irreversível](../../../reference/feedback-baileys-7x-decisao-irreversivel.md) — decisão Wagner
- [Skill `baileys-update-procedure`](../../../../.claude/skills/baileys-update-procedure/SKILL.md) — procedimento canônico genérico
- [Runbook `baileys-upgrade-lib.md`](baileys-upgrade-lib.md) — upgrades menores 7.x → 7.y futuros
- [Runbook `daemon-ct100-rebuild.md`](daemon-ct100-rebuild.md) — sync codebase → CT 100
- [Pest `Baileys7xPayloadShapeTest.php`](../../../../Modules/Whatsapp/Tests/Feature/Baileys7xPayloadShapeTest.php) — 5 regression tests payload shape
- [PR #855](https://github.com/wagnerra23/oimpresso.com/pull/855) — schema 3-identifiers (sinergia backend)
- [PR #857](https://github.com/wagnerra23/oimpresso.com/pull/857) — backup auth_state (mitigação perda 90d)
- [ADR 0093](../../../decisions/0093-multi-tenant-isolation-tier-0.md) — multi-tenant Tier 0 irrevogável

## Histórico

- 2026-05-15 — runbook criado. Daemon migrado para Baileys 7.0.0-rc11, ESM-only ativado, 27 arquivos `.ts` patched com `.js` extensions, Pest 5 it's adicionados, MessagePersister + ChannelBaileysWebhookController adaptados pra ler `remoteJidAlt`/`participantAlt`.
