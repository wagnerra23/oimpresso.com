---
name: Hostinger — acesso, API, SSH, DNS e análise DB consolidado
description: Cluster único Hostinger (shared hosting prod oimpresso.com). SSH credenciais + receita warm-up + hPanel humano + API autorizada + DNS endpoint canônico + receita análise DB via SSH+MySQL/heredoc PHP. Tokens reais no Vaultwarden.
type: reference
---
Servidor produção `oimpresso.com` na Hostinger Cloud Startup. Shared hosting — runtime separado de CT 100 Proxmox (ADR 0062). Não roda daemons aqui.

## SSH credenciais e acesso

```
Host: 148.135.133.115
Port: 65002
User: u906587222
Key:  ~/.ssh/id_ed25519_oimpresso
```

Comando padrão:
```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'COMANDO'
```

- `-4` IPv4 obrigatório (IPv6 falha)
- Repo Laravel: `~/domains/oimpresso.com/public_html` (NUNCA `~/domains/oimpresso.com/` direto — só backup)
- Composer: `/usr/local/bin/composer` · PHP: `/usr/bin/php` (8.4.19)
- Banco: user/grupo `u906587222` / `o51617061`
- Branch canônica: `main` (promovido 2026-04-27 ADR 0038, antes era `6.7-bootstrap`)
- Servidor em UTC; BR é UTC-3

### Regra crítica: warm-up + retry (SSH é flaky)

Sem warm-up, primeiro SSH quase sempre dá `Connection timed out`. Sempre fazer 5 hits curl IPv4 antes:
```bash
for i in 1 2 3 4 5; do
  curl -s -o /dev/null -w "$i:%{http_code} " https://oimpresso.com/login --max-time 15
done; echo
```

> ⚠️ **NÃO encurtar o `ConnectTimeout` (lição cara 2026-06-11).** O handshake do Hostinger *leva minutos* — `ConnectTimeout` tem que ser ALTO (manual: 900; no CI bounded a 180 pelo job timeout de 30min). Em 2026-06-11 o CC tentou "consertar" o deploy flaky **encurtando** pra `ConnectTimeout=30 × ConnectionAttempts=2` + warm-up por TCP-probe de 8s + retry-loop externo. Resultado: PIOROU ("sempre quebra") — o SSH desistia ANTES do handshake completar, e o probe de 8s sempre falhava. **Reverter pro canon**: `ConnectTimeout=180+` (minutos), `ConnectionAttempts=3-5`, `ServerAliveInterval=3`, `ServerAliveCountMax=200`, `-4`. Warm-up = **curl 443 ×5** (só acorda a rota) + 1 `ssh true` tolerante (paga o 1º connect lento). **Não** martelar com retry-loop agressivo (risco de ban / "cuidado com hostinger" — Wagner 2026-06-11).
>
> **Quando o deploy falha mesmo com os flags canônicos:** geralmente é **queda real da rota SSH do Hostinger** (a 443 pode continuar 200 enquanto a 65002 está inalcançável). Foi o caso 2026-06-11 ~12:30→13:30 (deploys 11:25/12:06/12:16 passaram; depois a 65002 caiu ~1h). Nenhum timeout sobrevive a outage de 1h dentro de job de 30min — **esperar a rota voltar e re-disparar UMA vez**, não ficar martelando.

### SSH config robusto (não cortar nenhum flag)

```bash
ssh -4 \
  -o ConnectTimeout=900 \
  -o ServerAliveInterval=3 \
  -o ServerAliveCountMax=200 \
  -o ConnectionAttempts=5 \
  -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "comando_aqui"
```

- `ConnectTimeout=900` (Hostinger leva minutos pra handshake)
- `ServerAliveInterval=3` (router intermediário derruba conexão "ociosa" sem keepalive)
- `ServerAliveCountMax=200` · `ConnectionAttempts=5`
- Multiplexing (`ControlMaster=auto`) NÃO funciona — falha "mux_client_request_session". Uma conexão por comando.
- BatchMode=yes pra falhar rápido em prompt inesperado.
- Não fazer sleep entre commands; usar `run_in_background` ou Monitor com until-loop.

### Deploy típico

```bash
# Após warm-up:
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git fetch origin main && \
   git reset --hard origin/main && \
   /usr/bin/php artisan view:clear && \
   /usr/bin/php artisan config:clear && \
   /usr/bin/php artisan cache:clear"
```

Se `composer.json/lock` mudou: adicionar `composer install --optimize-autoloader && \` antes do clear. **NUNCA `--no-dev`** (Faker é usado em prod).

### Rollback rápido

```bash
ssh -4 [...flags...] u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git fetch origin main && git reset --hard origin/main && \
   /usr/bin/php artisan view:clear"
```
Validado 2026-04-27: Sprint 2 quebrou /memcofre+/copiloto em 5min, rollback em ~30s.

### Workflows GitHub Actions (atualizado 2026-06-10 — ADR 0269)

`.github/workflows/deploy.yml` **auto-deploya em push pra main** (paths-ignore docs) — build no runner + bundles via tar/ssh + OPcache reset confirmado + smoke de hash. `workflow_dispatch` mantido como fallback. `quick-sync.yml` virou **escape manual** (`workflow_dispatch`-only); `force-clean-rebuild-trigger.yml` segue como nuclear manual.

> **Os helpers SSH desses workflows usam os flags canônicos desta página** (`-4` IPv4, `ConnectTimeout`, `ConnectionAttempts=5`, `ServerAlive`) + warm-up curl 5× antes do 1º SSH. Sem o `-4` o runner tentava IPv6 e dava `Connection timed out` em massa (incidente 2026-06-10). **ControlMaster (multiplexing) continua proibido** — falha `mux_client_request_session`; é 1 conexão por comando mesmo.

## hPanel acesso humano

- **Painel:** https://hpanel.hostinger.com/
- **Login:** "Sign in with Google" — `wagnerra@gmail.com` (com 2FA)
- Claude **NÃO automatiza** (OAuth interativo + 2FA)
- Wagner faz manualmente: criar/editar registros DNS sensíveis (MX), trocar plano, comprar/renovar domínio, configurar SSL custom, adicionar/remover usuários da conta
- Acesso SSH (host/port/user) descobertos via hPanel → Sites → oimpresso.com → Avançado → Acesso SSH (2026-04-26)

## API autorizada (DNS / MySQL / cert / whitelist)

Wagner em 2026-04-30 liberou explícito: **"use o api da hostinger caso necessário"**. Posso chamar `developers.hostinger.com` sem perguntar quando for caminho mais direto.

### Quando USAR (sem perguntar)

- DNS — adicionar A/CNAME/TXT pra novos subdomínios (mcp/realtime/etc)
- Whitelist Remote MySQL pra novo IP (CT 100, dev local)
- Verificar status cert SSL Let's Encrypt
- Qualquer operação que evite Wagner abrir hPanel manualmente

### Quando NÃO usar

- Mudar conta / billing
- Cancelar serviço
- Mexer em e-mail / MX sem Wagner explícito (afetam fluxos críticos)

### Token

Criado 2026-04-28, nomeado "Claude da hostiger". **Valor real no Vaultwarden** (item `hostinger-api-token`). Header:
```
Authorization: Bearer <token>
```
**Não commitar token no git.**

### Fluxo padrão

1. Tentar via API.
2. Se 530/timeout, fallback pra SSH ou hPanel manual (Wagner).
3. Anotar mudança permanente em session log.

## API DNS canônica (PUT zones)

**Endpoint correto:** `https://developers.hostinger.com/api/dns/v1/zones/{domain}` — ADR 0045

- **GET** lista a zona inteira
- **PUT** com `overwrite: false` adiciona records sem destruir existentes

### `api.hostinger.com` está com HTTP 530 crônico

Cloudflare 1016 origin DNS error em 2026-04-28. **Não usar.** Sempre `developers.hostinger.com`.

### Receita pra adicionar A record

```bash
TOKEN="<valor do Vaultwarden item hostinger-api-token>"
DOMAIN="oimpresso.com"
SUB="meu-novo-servico"
TARGET="177.74.67.30"

curl -s -X PUT \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/$DOMAIN" \
  -d "{
    \"overwrite\": false,
    \"zone\": [{
      \"name\": \"$SUB\",
      \"type\": \"A\",
      \"ttl\": 300,
      \"records\": [{\"content\": \"$TARGET\"}]
    }]
  }"
# → {"message":"Request accepted"} HTTP 200
# Propaga ~30s autoritativo, ~60s DNS público
```

### Cuidados críticos

- **`overwrite: false` é OBRIGATÓRIO** — sem isso, PUT zera a zona inteira (apaga todos os outros records)
- Token é secret — nunca commitar

### Estado da zona oimpresso.com (2026-04-28)

- A → `177.74.67.30` (CT 100): vault, portainer, traefik, reverb, meilisearch
- A → Hostinger CDN: app, api, crm, doc, ia
- ALIAS/CNAME: @, www, chat, autoconfig, autodiscover, hostingermail-*
- TXT: SPF, DMARC, ACME challenges

Validado 2026-04-28: criou 4 novos A records (reverb/portainer/traefik/vault) → 177.74.67.30 com 1 chamada, propagação ~30s, todos resolvíveis via 1.1.1.1.

ADR formal: `memory/decisions/0045-hostinger-dns-api-endpoint-canonico.md`.

## Análise DB via SSH+MySQL (warm-up + heredoc PHP)

Hostinger é lento/flaky pra SSH — receita pra economizar tempo.

### 1. Sempre warm-up curl 5x antes (ver seção SSH)

### 2. Query SQL via mysql CLI (mais rápido que tinker)

`.env` da Hostinger tem valores entre aspas — remover com `tr -d '"'`:

```bash
ssh ... "cd domains/oimpresso.com/public_html && \
  U=\$(grep ^DB_USERNAME .env | cut -d= -f2 | tr -d '\"') && \
  P=\$(grep ^DB_PASSWORD .env | cut -d= -f2 | tr -d '\"') && \
  D=\$(grep ^DB_DATABASE .env | cut -d= -f2 | tr -d '\"') && \
  mysql -h localhost -u \"\$U\" -p\"\$P\" \"\$D\" -e \"SELECT * FROM business ORDER BY id LIMIT 5\""
```

`mysql` CLI ~10× mais rápido que `artisan tinker` (não boota Laravel).

### 3. Script PHP inline (quando precisa Eloquent/Carbon/Spatie)

Heredoc `<< 'PHPEOF'` (aspas simples = sem interpolação bash, PHP recebe `$` literal):

```bash
ssh ... "cat > domains/oimpresso.com/public_html/temp.php << 'PHPEOF'
<?php
require __DIR__.'/vendor/autoload.php';
\$app = require_once __DIR__.'/bootstrap/app.php';
\$app->make(\\\\Illuminate\\\\Contracts\\\\Console\\\\Kernel::class)->bootstrap();

\$rows = DB::select('SELECT ...');
foreach (\$rows as \$r) { echo \$r->id.PHP_EOL; }
PHPEOF
cd domains/oimpresso.com/public_html && php temp.php && rm temp.php"
```

Crítico:
- `<< 'PHPEOF'` aspas simples = sem expansão bash
- `\\\\Illuminate` (4 níveis: shell→ssh→bash→PHP)
- Sempre `rm` no fim (produção sem PHP órfão)

### 4. tinker --execute falha com namespaces

`php artisan tinker --execute="Carbon\Carbon::now()"` dá **PARSE ERROR**. Alternativas:
- Heredoc PHP com `use Carbon\Carbon;`
- Funcs globais sem namespace (`date()`, `DB::select()`)
- Script PHP inline (receita 3)

### 5. SSH tunnel pra MySQL (quando precisa client local: Python pymysql, DBeaver, etc)

MySQL Hostinger só ouve `localhost:3306` — pra conectar de fora, abrir tunnel SSH:

```bash
ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 \
    -N -L 127.0.0.1:33069:127.0.0.1:3306 \
    -o AddressFamily=inet \
    -o ServerAliveInterval=10 -o ServerAliveCountMax=200 \
    u906587222@148.135.133.115
```

**Gotcha trap descoberta 2026-05-11**: o GRANT do user `u906587222_oimpresso@localhost` **não cobre `::1`** (IPv6 loopback). Sem os flags abaixo, pymysql Windows conecta via `::1` e pega `Access denied for user 'u906587222_oimpresso'@'::1'`:

1. `-L 127.0.0.1:port:127.0.0.1:3306` — bind local IPv4 **explícito** + remote `127.0.0.1` (não `localhost` que pode resolver `::1` no lado server)
2. `-o AddressFamily=inet` — força resolução IPv4 em todas as legs

Cliente local conecta em `127.0.0.1:33069` com user/pass do `.env` Hostinger (que lê via SSH sem ecoar).

Exemplo wrapper Python idempotente: `scripts/legacy-migration/migrar-tudo.py` (ver feedback-legacy-migration-importer.md).

### 6. Queries úteis pra diagnóstico

**Vendas recentes de um business:**
```sql
SELECT id, invoice_no, transaction_date, created_at,
  TIMESTAMPDIFF(MINUTE, created_at, transaction_date) as diff_min
FROM transactions WHERE business_id=4 AND type='sell'
  AND DATE(created_at) BETWEEN '2026-04-21' AND '2026-04-23'
ORDER BY created_at DESC;
```

**Timezone MySQL server:**
```sql
SELECT NOW() as mysql_now, @@session.time_zone, @@global.time_zone;
```
Hostinger: `SYSTEM = UTC` (Linux UTC). PHP em SP via `APP_TIMEZONE` no `.env`. Diferença 3h entre `NOW()` e `Carbon::now()` é esperada.

**Roles sem permissão de location (bug ROTA LIVRE replicável):**
```sql
SELECT r.name as role, r.business_id, (
  SELECT COUNT(*) FROM model_has_permissions mhp
  JOIN permissions p ON p.id = mhp.permission_id
  WHERE mhp.model_type = 'Spatie\\Permission\\Models\\Role'
    AND mhp.model_id = r.id
    AND (p.name LIKE 'location.%' OR p.name = 'access_all_locations')
) as loc_perms
FROM roles r HAVING loc_perms = 0;
```

## Regras de ouro

- Nunca usar `computer-use` pra operar Hostinger — sempre SSH ou API
- Nunca editar arquivo direto via SSH sem commit no git — drift permanente
- Nunca `composer install --no-dev` — Faker em prod
- Nunca `laravel/octane` ou `laravel/mcp` no Hostinger (ADR 0062 — só CT 100)
- Nunca daemons no Hostinger (Reverb/Centrifugo/Horizon/autossh/Meilisearch) — só CT 100
- Boost MCP (`mcp__laravel_boost__*`) NÃO substitui SSH (roda artisan local, não no Hostinger)

## Ver também

- IPv4 obrigatório (regra)
- Action GH automatizada inutilizável (quick-sync quebrada)
- composer install obrigatório (Faker em prod) — deploy-recovery-patterns.md
- ADR 0045 (DNS endpoint) · ADR 0062 (separação runtime Hostinger ≠ CT 100)
