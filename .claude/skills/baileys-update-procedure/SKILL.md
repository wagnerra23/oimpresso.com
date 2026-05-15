---
name: baileys-update-procedure
description: ATIVAR quando user pedir "atualizar baileys", "nova versão baileys", "bump @whiskeysockets/baileys", OU quando daemon CT 100 apresentar bug "Connection Failure" / "noise-handler" / ban_detected suspeito recorrente. Roda procedimento 5-fase de update Baileys daemon (pre-check / migration / build / smoke / rollback) com gotchas conhecidos. Substitui exploração ad-hoc por checklist canônico — descobertos 5 traps em 2026-05-11 que custaram ~4h da sessão. Tier B (auto-trigger por description).
---

# baileys-update-procedure — atualizar @whiskeysockets/baileys com segurança

## Quando ativar (gatilhos)

1. **User pede:** "atualizar baileys", "nova versão baileys", "bump @whiskeysockets/baileys"
2. **Bug recorrente no daemon CT 100:**
   - `Error: Connection Failure` em `noise-handler.ts` pós-pairing
   - `Stream Errored (restart required)` em loop sem recovery
   - `ban_detected` repetido onde WhatsApp pessoal funciona normal (= falso positivo banDetector)
3. **GitHub release nova** (>1 patch atrás da versão pinned)
4. **Antes de cadastrar canal Baileys produção** se daemon ≥ 30 dias sem update

## Procedimento 5-fase

### Fase 1 — Pre-check (~5min)

```bash
# Versão atual no daemon CT 100
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build && grep baileys package.json'

# Latest release Baileys (precisa internet)
npm view @whiskeysockets/baileys versions --json | tail -10

# Última 3 releases — ler changelog
gh api repos/WhiskeySockets/Baileys/releases --jq '.[0:3] | .[] | {tag_name, name, published_at, body}'
```

**Decisão:** se versão atual está N patches atrás E há fixes "Connection Failure" / "pairing code" no changelog → vale upgrade.

### Fase 1.5 — Audit auth_state pré-bump MAJOR (obrigatório se bump 6.x→7.x ou 7.x→8.x)

> 🚨 **Lição catalogada incident 2026-05-15:** deploy Baileys 6.7.18→7.0.0-rc11 SEM purgar auth_state corrompeu daemon com `failed to find key "AAAAALtG" to decode mutation` em `chat-utils.ts:309`. Estrutura interna `mysqlAuthState` mudou entre majors — chaves Signal Protocol 6.x são opacas pro 7.x. Resultado: 103 rows precisaram ser purgadas manualmente, canal id=7 deletado, canal id=8 banned virou stale, 78 webhook nonces velhos. Custou ~30min troubleshooting. **PURGUE ANTES de fazer o bump, não depois.**

```bash
# 1. Detecta drift atual (testa hipótese ANTES do bump)
php artisan whatsapp:auth-state-drift-check

# 2. Se bump é major (6.x → 7.x): backup + purge proactive
php artisan tinker --execute='
echo "Rows atuais: ".\DB::table("whatsapp_baileys_auth_state")->count().PHP_EOL;
\$backup = \DB::table("whatsapp_baileys_auth_state")->select("id","instance_id","key_id","updated_at")->get();
file_put_contents(storage_path("app/backups/auth-state-PRE-MAJOR-BUMP-".date("Ymd-His").".json"), json_encode(\$backup, JSON_PRETTY_PRINT));
echo "Backup summary salvo (sem value_encrypted — irrecuperável de qualquer jeito).".PHP_EOL;
// AINDA NÃO DELETE — confirme com Wagner antes de purge real
'

# 3. Após Wagner aprovar:
php artisan tinker --execute='
\$d = \DB::table("whatsapp_baileys_auth_state")->delete();
\$n = \DB::table("webhook_nonces")->delete();
echo "auth_state: \$d rows / nonces: \$n rows DELETED".PHP_EOL;
'

# 4. Após purge: clientes ATIVOS vão precisar re-parear via UI (QR scan)
# Avisar Wagner ANTES (regra Tier 0: "cliente como sinal" — não surpresa)
```

**Quando pular Fase 1.5:** bump patch (`6.7.18 → 6.7.19`) ou minor (`6.7 → 6.8`). MAJOR sempre obriga.

### Fase 2 — Migration ESM (se necessária, ~30min-2h)

Baileys 6.8.0+ é **ESM-only** ([migration guide oficial](https://baileys.wiki/docs/migration/to-v7.0.0/)). Se daemon atual é CommonJS (`"module": "CommonJS"` no tsconfig), precisa migrar:

```bash
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build && python3 -c "
import json
with open(\"package.json\") as f: pkg = json.load(f)
pkg[\"type\"] = \"module\"
pkg[\"dependencies\"][\"@whiskeysockets/baileys\"] = \"^X.Y.Z\"  # ← bump
with open(\"package.json\",\"w\") as f: json.dump(pkg, f, indent=2)
print(\"package.json patched\")
"'

# tsconfig.json: module=NodeNext + moduleResolution=NodeNext
# Atualiza via sed ou edit direto

# Adicionar .js em todos imports relativos
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build && python3 << "PYEOF"
import re, glob
patched = 0
for ts in glob.glob("src/**/*.ts", recursive=True):
    with open(ts) as f: s = f.read()
    orig = s
    def fix(m):
        full, path = m.group(0), m.group(1)
        if path.endswith(".js") or path.endswith(".json"): return full
        return full.replace(path, path + ".js")
    s = re.sub(r"from\\s+[\"\\x27](\\.\\.?/[^\"\\x27]+)[\"\\x27]", fix, s)
    if s != orig:
        with open(ts,"w") as f: f.write(s)
        patched += 1
print(f"{patched} files patched com .js extensions")
PYEOF'
```

Validado 2026-05-11 — Baileys 6.7.9 → 6.7.18 com ESM migration. 10 arquivos auto-patched.

### Fase 3 — Build + deploy (~5min)

```bash
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build &&
  docker compose build --no-cache whatsapp-baileys 2>&1 | tail -5 &&
  docker compose up -d 2>&1 | tail -3'

# Smoke check
tailscale ssh root@ct100-mcp 'sleep 5; docker logs whatsapp-baileys --tail 5 2>&1 | grep -i "ready\|error"'
```

Esperado: `whatsapp-baileys-daemon ready` sem ERR_REQUIRE_ESM.

### Fase 4 — Smoke test pairing (~5min)

**Sempre num channel_uuid de TESTE (não produção):**

```bash
tailscale ssh root@ct100-mcp 'CID=$(docker ps -q --filter name=whatsapp-baileys);
  CIP=$(docker inspect "$CID" --format "{{range .NetworkSettings.Networks}}{{.IPAddress}}{{end}}");
  API_KEY=$(cat /srv/secrets/whatsapp_baileys_api_key);
  curl -s -X POST -H "Authorization: Bearer $API_KEY" -H "Content-Type: application/json" \
    -d "{\"business_uuid\":\"00000000-0000-0000-0000-000000000099\",\"business_id\":99}" \
    "http://$CIP:3000/instances/smoke-test/connect" >/dev/null
  sleep 6
  curl -s -H "Authorization: Bearer $API_KEY" "http://$CIP:3000/instances/smoke-test/status" | python3 -m json.tool'
```

**Esperado:** state=`qr_required` com `qr` field populado como `data:image/png;base64,...` (Baileys 6.7.18+ rasteriza automaticamente via QRCode.toDataURL no Instance.ts).

```bash
# Purga teste depois
tailscale ssh root@ct100-mcp 'curl -s -X DELETE -H "Authorization: Bearer $API_KEY" "http://$CIP:3000/instances/smoke-test"'
```

### Fase 5 — Rollback (se falhar)

```bash
tailscale ssh root@ct100-mcp 'cd /opt/whatsapp-baileys/build &&
  sed -i "s/\"@whiskeysockets\\/baileys\": \"\\^X.Y.Z\"/\"@whiskeysockets\\/baileys\": \"<versão-anterior>\"/" package.json &&
  docker compose build --no-cache whatsapp-baileys && docker compose up -d'
```

Se rollback exigido, **sempre criar US-WA-***  pra investigar issue específico antes de tentar de novo (não force update).

## Os 5 gotchas conhecidos (2026-05-11)

### 1. **ESM-only desde 6.8.0** (e na verdade 6.7.18 já dá `ERR_REQUIRE_ESM` em alguns setups)
   - Sintoma: `Error [ERR_REQUIRE_ESM]: require() of ES Module ... not supported`
   - Fix: migration ESM completa (Fase 2)

### 2. **`banDetector.ts` heurística agressiva**
   - Sintoma: `state=banned` recorrente mas WhatsApp pessoal funciona normal
   - Fix: em `src/baileys/banDetector.ts`, mudar `DisconnectReason.loggedOut` de `banned: true` pra `banned: false, reason: 'session_expired', shouldReconnect: false`. 401 pós-pairing é session expired, não ban permanente.

### 3. **Stream:error code 515 pós-pairing é NORMAL**
   - Sintoma: log mostra `Connection Failure` 1-2s após `logging in...`, mas reconnect automático em ~7s
   - **Não é bug** — protocolo Baileys exige restart pós-pairing pra ativar sessão real
   - Não tratar como ban / disconnect permanente

### 4. **Permissão sessions/** owner deve ser uid 1001
   - Sintoma: `EACCES: permission denied, mkdir '/app/sessions/...'`
   - Fix: `chown -R 1001:1001 /srv/docker/whatsapp-baileys/sessions`
   - Container roda como user `nodeapp` (uid 1001) — host dir precisa permissão correspondente

### 5. **Let's Encrypt rate-limit se DNS NXDOMAIN na 1ª tentativa**
   - Sintoma: Traefik logs `Unable to obtain ACME certificate ... NXDOMAIN`, fica self-signed
   - Fix imediato: `Http::withoutVerifying()` no backend Hostinger (cert self-signed aceito)
   - Fix real: garantir DNS A propagou ANTES de docker compose up (>30s após PUT zones API Hostinger)

## Pre-flight validations (rodar antes de cada update)

- [ ] Backup `/srv/docker/whatsapp-baileys/sessions/` (sessões ativas — perda = re-pairing)
- [ ] Anotar versão atual em comentário do `package.json`
- [ ] Validar daemon ATUAL responde `/health` 200 (baseline OK)
- [ ] Pre-check Fase 1: ler changelog dos últimos 3 releases — não pular bugs conhecidos

## Pos-deploy validations

- [ ] `docker logs whatsapp-baileys --tail 20` mostra "ready" sem errors
- [ ] Smoke test pairing (Fase 4) retorna QR ou pairing-code com sucesso
- [ ] Channel produção `Suorte` (id=2) continua `connected` em `/instances/.../status`
- [ ] Webhook ainda chega — manda msg test → ver `[channel.baileys.webhook]` em Hostinger logs

## Anti-padrões

- ❌ Update Baileys **direto na produção sem smoke test** em instance separada
- ❌ **Reverter sem pesquisar** quando upgrade falha — ver `feedback_pesquisar_versao_mais_nova_em_erro_lib.md` ([ADR follow-up])
- ❌ Force re-build sem **rm node_modules** (cache pode mascarar incompatibilidade)
- ❌ Ignorar gotcha #3 (stream:error 515) — vai te fazer pensar que tá quebrado

## ROI

Sem skill: ~4h por update (descobrir gotchas do zero).
Com skill: ~30min update + smoke + validate.
Economia: ~3.5h × frequência updates (~1×/3 meses).

## Refs

- [ADR 0135](memory/decisions/0135-omnichannel-inbox-arquitetura.md) — omnichannel arquitetura
- [Baileys v7 Migration Guide](https://baileys.wiki/docs/migration/to-v7.0.0/) — fonte oficial
- [feedback_pesquisar_versao_mais_nova_em_erro_lib.md](memory/feedback_pesquisar_versao_mais_nova_em_erro_lib.md) — não reverter sem pesquisar
- Session 2026-05-11 — primeiro update Baileys 6.7.9 → 6.7.18 + ESM migration (10 files patched)
