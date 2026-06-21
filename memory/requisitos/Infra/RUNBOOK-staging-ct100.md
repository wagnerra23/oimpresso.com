# RUNBOOK — Ambiente de Staging no CT 100 (`staging.oimpresso.com`)

> Como replicar um **clone fiel da produção** (ERP web inteiro) num subdomínio do CT 100,
> com **banco anonimizado** (LGPD-safe) e **integrações neutralizadas**, pra a equipe testar
> sem tocar produção e sem disparar ação no mundo real.
>
> **Construído:** 2026-05-29 (Wagner + Claude). **Artefatos versionados:** [`docker/oimpresso-staging/`](../../../docker/oimpresso-staging/).
> **Decisão:** ADR 0235 (emenda à [0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) — CT 100 passa a servir 1 subdomínio web de staging).

## Arquitetura

```
staging.oimpresso.com
  → DNS A (Hostinger API)  → 177.74.67.30 (IP público CT 100)
    → Traefik (cert Let's Encrypt automático, rede docker-host_default)
      → container oimpresso-staging   (imagem oimpresso/mcp:latest, FrankenPHP CLÁSSICO — sem Octane)
        ├─ código:  /opt/oimpresso-staging/code        (git, branch própria)
        ├─ .env:    derivado do .env de PRODUÇÃO + neutralizado + APP_KEY nova
        └─ banco:   container oimpresso-staging-db (MariaDB 11 dedicado) ← dump anonimizado de prod
```

**Decisões-chave:** (1) FrankenPHP **clássico** (`php-server`, sem workers) porque o UltimatePOS não é
Octane-safe; (2) **MariaDB dedicado** (não o `mysql-workers` que é MySQL 8.0) porque **produção é MariaDB 11.8**
e dump MariaDB→MySQL quebra em collation; (3) `.env` derivado do **real de produção** (traz as flags `MWART_*`
que definem quais telas são Inertia — sem elas o visual diverge de prod).

## Pré-requisitos

| Item | Onde |
|---|---|
| Acesso CT 100 | `tailscale ssh root@ct100-mcp` (sem senha) |
| Token Hostinger DNS API | `/root/.hostinger-api-token` (CT) — receita [ADR 0045](../../decisions/0045-hostinger-dns-api-endpoint-canonico.md) / skill `hostinger-dns-autonomy`. ⚠️ rotaciona ~anual — se 401, gerar novo no hPanel |
| SSH Hostinger (pegar .env prod) | `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` (warm-up curl 5× antes) |
| CT já tem | Docker, Node 20 (`/usr/bin/node`), Traefik, imagem `oimpresso/mcp:latest`, rede `docker-host_default` |

## Passo a passo (do zero)

> A maior parte está automatizada em [`deploy.sh`](../../../docker/oimpresso-staging/deploy.sh) +
> [`seed-from-prod.sh`](../../../docker/oimpresso-staging/seed-from-prod.sh). Abaixo o fluxo manual/explicado.

**1. DNS** — A-record via Hostinger API (`overwrite:false` SEMPRE):
```bash
TOKEN=$(cat /root/.hostinger-api-token)
curl -s -X PUT -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  "https://developers.hostinger.com/api/dns/v1/zones/oimpresso.com" \
  -d '{"overwrite":false,"zone":[{"name":"staging","type":"A","ttl":300,"records":[{"content":"177.74.67.30"}]}]}'
# valida: dig +short staging.oimpresso.com @1.1.1.1  → 177.74.67.30
```

**2. Banco MariaDB dedicado** (container próprio, senhas geradas + guardadas em `/opt/oimpresso-staging/.db-*-pwd`):
```bash
mkdir -p /opt/oimpresso-staging/db
SP=$(openssl rand -hex 20); echo "$SP" > /opt/oimpresso-staging/.db-staging-pwd
RP=$(openssl rand -hex 20); echo "$RP" > /opt/oimpresso-staging/.db-root-pwd
docker run -d --name oimpresso-staging-db --restart unless-stopped --network docker-host_default \
  -e MARIADB_ROOT_PASSWORD="$RP" -e MARIADB_DATABASE=oimpresso_staging \
  -e MARIADB_USER=staging -e MARIADB_PASSWORD="$SP" \
  -v /opt/oimpresso-staging/db:/var/lib/mysql mariadb:11
```

**3. Código** — clone local de `/opt/oimpresso-mcp/code` (rápido, sem auth) + checkout da branch:
```bash
git clone /opt/oimpresso-mcp/code /opt/oimpresso-staging/code
cd /opt/oimpresso-staging/code
git remote set-url origin https://github.com/wagnerra23/oimpresso.com.git
git fetch origin <branch> && git checkout -B <branch> origin/<branch>
```

**4. Composer + assets** (ver `deploy.sh`):
```bash
# composer: imagem mcp NÃO tem composer → usar composer:2 (ver Pegadinha #1)
docker run --rm -v /opt/oimpresso-staging/code:/app -w /app composer:2 \
  install --no-interaction --optimize-autoloader --ignore-platform-reqs --no-scripts
# assets: Node no HOST do CT
cd /opt/oimpresso-staging/code && npm ci --no-audit --no-fund && npm run build:inertia && npm run build
```

**5. `.env`** — derivar do **.env de PRODUÇÃO** (Hostinger) + sobrescrever staging/neutralizações + APP_KEY nova.
Ver função `setenv()` que usei (substitui ou adiciona chave). Trocar: `APP_ENV=staging`, `APP_URL`, `DB_*` (→ staging-db),
`MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`, `WHATSMEOW/BAILEYS/CENTRIFUGO_URL=` (vazio), `NFEBRASIL_AUTO_EMISSION=false`,
gateways `*_SECRET=` (vazio), `APP_KEY=` (gerar nova — Pegadinha #2). **Manter** as flags `MWART_*` de prod.

**6. Container** — `docker compose -f docker/oimpresso-staging/docker-compose.yml up -d` (FrankenPHP clássico via `entrypoint-staging.sh`).

**7. Seed anonimizado** — `bash docker/oimpresso-staging/seed-from-prod.sh`:
dump prod (mariadb-dump) → import → `anonymize.sql` → reset senha `staging2026` → **valida 0-PII** (aborta se sobrar).

**8. Cert** — se o Traefik não emitir (backoff pós-NXDOMAIN), `docker restart traefik` DEPOIS do DNS propagar (Pegadinha #7).

**9. Smoke** — login fim-a-fim:
```bash
curl -s -o /dev/null -w '%{http_code} ssl:%{ssl_verify_result}' https://staging.oimpresso.com/login   # 200 ssl:0
```

**Acesso final:** `https://staging.oimpresso.com` · qualquer username (ex `administrador`, `larissa-04`) · senha `staging2026`.

## ⚠️ Pegadinhas (leia ANTES — cada uma me custou tempo)

1. **Imagem FrankenPHP não tem `composer`** → `sh: composer: not found`. Usar imagem **`composer:2`** com `--ignore-platform-reqs --no-scripts` (as extensões existem na imagem mcp em runtime).
2. **`key:generate` falha com APP_KEY vazia** (galinha-ovo: o boot usa o encrypter antes da key existir). Gerar direto: `docker run --rm --entrypoint php oimpresso/mcp:latest -r "echo 'base64:'.base64_encode(random_bytes(32));"`.
3. **`docker restart` NÃO relê o `env_file`** — o container fica com o `.env` antigo em memória. Após mudar `.env`: `docker compose up -d --force-recreate`.
4. **Produção é MariaDB 11.8, não MySQL.** Staging-db tem que ser **MariaDB** (collation `uca1400` não existe no MySQL 8.0) e o dump tem que ser **`mariadb-dump`** (o `mysqldump` 8.0 quebra com `--set-gtid-purged` contra MariaDB).
5. **Dump completo (607 MB) num pipe único TRAVA** — o Hostinger (shared hosting) dropa a conexão longa. Solução: dump do grosso + dump das tabelas finais (alfabeticamente `u…z`) **separado** (conexão curta não trava). TODO: refazer `seed-from-prod.sh` com dump **em blocos**.
6. **Excluir `activity_log` quebra o LOGIN** — o UltimatePOS faz `INSERT` nela ao logar; se a tabela não existe → 500. Traga ao menos a **estrutura** (`mariadb-dump --no-data ... activity_log`).
7. **Cert Let's Encrypt + DNS:** se o A-record for criado DEPOIS do Traefik tentar o ACME, ele falha (`NXDOMAIN`) e entra em **backoff** — não re-tenta sozinho nem recriando o container. Após o DNS propagar (checar `@1.1.1.1` E `@8.8.8.8`), **`docker restart traefik`** força a re-emissão. Confirmar: `openssl s_client ... | openssl x509 -issuer` deve mostrar `Let's Encrypt`.
8. **Aviso de cert no navegador** mesmo com cert válido = **cache do navegador** (acessou antes do cert emitir). Aba anônima / hard reload resolve.
9. **Healthcheck que depende de DB impede o Traefik rotear** — `/login` dá 500 com banco vazio → container nunca fica `healthy` → 404 no Traefik. Usar healthcheck que aceita 2xx-4xx (`curl ... / | grep -qE '^[234]'`).
10. **Credenciais (`rb_boleto_credentials`, `nfe_certificados`, tokens) são criptografadas com a APP_KEY de PROD** → inúteis no staging (APP_KEY nova) e perigosas → o `anonymize.sql` **TRUNCA** todas (trava de segurança: staging não cobra/emite/conecta de verdade).

## Rodar Pest/PHPStan de um branch (sem mexer no staging vivo)

O staging vivo (`/opt/oimpresso-staging/code`) fica num branch só (ex `feat/staging-ct100`) e pode ter WIP. Pra testar OUTRO branch (ex um PR) **sem trocar o branch do staging nem rebuildar assets**, use um **git worktree descartável** + container one-off reaproveitando `vendor`/`storage`/DB do staging (validado 2026-06-01, re-impl #2045):

```bash
CODE=/opt/oimpresso-staging/code
git -C $CODE fetch origin <branch>
git -C $CODE worktree add --detach /tmp/wt origin/<branch>
cp $CODE/.env /tmp/wt/.env
mkdir -p /tmp/wt-cache && chmod 777 /tmp/wt-cache          # Gotcha C
cp -r $CODE/public/build-inertia /tmp/wt/public/build-inertia   # Gotcha B (só pra testes Inertia GET)
docker run --rm --network docker-host_default \
  -v /tmp/wt:/var/www/html \
  -v $CODE/vendor:/var/www/html/vendor \
  -v /opt/oimpresso-staging/storage:/var/www/html/storage \
  -v /tmp/wt-cache:/var/www/html/bootstrap/cache \
  -e DB_CONNECTION=mysql -e DB_HOST=oimpresso-staging-db -e DB_PORT=3306 -e DB_DATABASE=oimpresso_staging \
  -w /var/www/html --entrypoint php oimpresso/mcp:latest \
  artisan test <arquivo>      # ou: vendor/bin/phpstan analyse -c phpstan.neon.dist --no-progress
# limpar: git -C $CODE worktree remove --force /tmp/wt && rm -rf /tmp/wt-cache
```

Alternativa leve (rodar 1 arquivo de teste novo sem worktree): `git -C $CODE show origin/<branch>:<path> > $CODE/<path>` (fica untracked, **não** muda o branch do staging), rode no container **vivo** (`docker exec -e DB_DATABASE=oimpresso_staging oimpresso-staging php artisan test <path>`) e `rm` depois.

**Gotchas (cada um custou tempo):**
- **A. NÃO passe `-e APP_ENV=staging`.** O `phpunit.xml` só força `APP_ENV=testing` se a env não vier setada; com `staging` os middlewares ligam → **CSRF 419** nos POST e **409** nos GET. Passe só as vars de DB.
- **B. Copie `public/build-inertia/manifest.json`** pro worktree. Sem ele, `HandleInertiaRequests::version()` cai no mix-manifest legado enquanto o helper `inertiaGet` manda `'1'` → **version mismatch 409** em todo teste Inertia GET (`assertOk` falha antes da asserção real).
- **C. Monte `bootstrap/cache` gravável** (dir vazio `chmod 777`). Sem isso, o boot do larastan/artisan estoura `Error: Please provide a valid cache path`.
- **D. Adversário cross-tenant é `business_id=99`** (ADR 0101). Mas tabelas com **FK `business_id → business`** **não** aceitam INSERT em biz=99 (não existe no clone). Pra provar isolamento: insira no **biz=1** e **flipe a sessão** (`session(['user.business_id' => 99])`) — valor de sessão não precisa de row.
- **E. ⛔ NUNCA rode testes SELF-SCHEMA contra o MySQL do staging.** ~30 testes (lista: `grep -rln "Schema::create('users'" tests/ Modules/*/Tests`) criam o próprio schema no `beforeEach` e **DROPAM** `users`/`roles`/`permissions`/`sale_*` no `afterEach` — desenhados pro sqlite `:memory:` do CI. **Incidente 2026-06-10:** `ExecuteStageActionServiceTest` rodado via worktree descartável dropou `sale_stage_actions`+`sale_stage_action_roles`+`sale_stage_history` do `oimpresso_staging` (restauração: `up()` das migrations `2026_05_11_12000{3,4,5}` + `2026_05_12_010001` via tinker + re-seed `FsmProcessoVendaComProducaoSeeder`/`FsmProcessoOsReparoPadraoSeeder`/`FsmProcessoComunicacaoVisualSeeder`/`OficinaAutoFsmSeeder`; o histórico `sale_stage_history` do staging se perdeu). Guard de driver (`markTestSkipped` se != sqlite) está sendo adicionado arquivo a arquivo — até cobrir todos, rode no CT 100 **só por caminho de arquivo explícito** de testes que você LEU antes (sem `--filter` amplo, que carrega/executa vizinhos self-schema).

## Repetir / re-seedar

```bash
# atualizar código + rebuild:
tailscale ssh root@ct100-mcp 'cd /opt/oimpresso-staging/code && bash docker/oimpresso-staging/deploy.sh <branch>'
# re-seedar o banco do zero (dump anonimizado fresco de prod):
tailscale ssh root@ct100-mcp 'bash /opt/oimpresso-staging/code/docker/oimpresso-staging/seed-from-prod.sh'
```

Tudo idempotente. Re-seed dropa+recria as tabelas e re-anonimiza. Senha sempre volta pra `staging2026`.

## Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `/login` HTTP 500 `MissingAppKey` | `.env` recarregado por `restart` (não relê env_file) | `docker compose up -d --force-recreate` |
| `/login` HTTP 500 `activity_log doesn't exist` | tabela excluída do dump | importar estrutura (Pegadinha #6) |
| Traefik 404 no subdomínio | container `unhealthy` (healthcheck DB-dependente) | healthcheck 2xx-4xx (Pegadinha #9) |
| `NET::ERR_CERT_*` no navegador | cache OU ACME em backoff | aba anônima; senão `docker restart traefik` (DNS propagado) |
| Dump para em ~605 MB, 0% CPU | Hostinger dropou a conexão longa | dump em blocos / tabelas finais à parte (Pegadinha #5) |
| Login "credenciais inválidas" | username errado (é por **username**, não e-mail) ou senha ≠ `staging2026` | usar username real + `staging2026` |

## Limpeza / custo

- Disco CT: staging ≈ 2-3 GB (código + vendor + node_modules + banco ~1 GB). Monitorar `df -h /` (CT estava 77% cheio).
- Pra destruir: `docker rm -f oimpresso-staging oimpresso-staging-db && rm -rf /opt/oimpresso-staging` + remover A-record DNS.
