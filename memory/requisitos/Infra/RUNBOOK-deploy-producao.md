---
title: "RUNBOOK — Deploy em produção (Hostinger) + staging (CT 100) + rollback"
module: Infra
owner: W
status: rascunho
last_validated: "2026-11-14"
preconditions:
  - "PR aprovado e com os checks obrigatórios verdes (merge na main = deploy automático, ADR 0269)"
  - "Chave SSH ~/.ssh/id_ed25519_oimpresso (Hostinger, porta 65002, -4 obrigatório)"
  - "Tailscale ativo para o CT 100 (tailscale ssh root@ct100-mcp)"
  - "gh autenticado com permissão de workflow no repo"
steps:
  - "§2 Pré-check local do PR"
  - "§3 Staging CT 100 (opcional hoje — não é gate do pipeline)"
  - "§4 Smoke staging"
  - "§5 Produção: merge → deploy.yml automático"
  - "§6 Smoke produção"
  - "§7 Rollback (A revert · B redeploy de SHA bom · C bundle · D banco)"
  - "§8 DNS/TLS pós-deploy"
related_adrs:
  - 0062-separacao-runtime-hostinger-ct100
  - 0058-reverb-substituido-por-centrifugo-frankenphp
  - 0269-deploy-automatico-build-no-runner
  - 0235-staging-ct100-clone-anonimizado
  - 0045-hostinger-dns-api-endpoint-canonico
---

# RUNBOOK — Deploy em produção

> **Dono executável:** [`.github/workflows/deploy.yml`](../../../.github/workflows/deploy.yml). Ele muda mais rápido que
> este texto. Se divergirem, o workflow vale e este RUNBOOK está errado: corrija aqui.
> **Modelo mental:** [`memory/reference/FLUXO-DEPLOY.md`](../../reference/FLUXO-DEPLOY.md). **Receitas de incidente:**
> [`memory/reference/deploy-recovery-patterns.md`](../../reference/deploy-recovery-patterns.md).
>
> Status `rascunho` até [W] aprovar e o rollback B (§7) ser ensaiado uma vez.

## 0. Como o deploy funciona hoje (medido em 2026-11-14)

| Fato | Evidência |
|---|---|
| **Merge na `main` publica em produção sozinho.** Não existe passo manual nem aprovação depois do merge. | `deploy.yml` `on.push.branches: [main]` (ADR 0269) |
| **O staging NÃO fica entre o merge e a produção.** O pipeline vai direto do runner do GitHub para o Hostinger. O staging do CT 100 é um ambiente separado e manual. | `deploy.yml` não tem job de staging; `docker/oimpresso-staging/deploy.sh` é rodado à mão |
| Push só de docs (`memory/**`, `*.md`, `prototipo-ui/**`) não dispara run. `*.casos.md` dispara. | filtro `paths` do `deploy.yml` |
| Push que não toca runtime vai pelo **sync leve** (só `git reset`, sem maintenance). O resto vai pelo **deploy completo**. Em caso de dúvida, vai pelo completo. | `scripts/deploy/classifica-push.sh` + teste `scripts/governance/deploy-classifica-push.test.mjs` |
| 19 dos últimos 20 deploys verdes. O vermelho (run 43485819612, 2026-11-13) foi a sonda do Passport, não o deploy (ver gap G-03). | `gh run list --workflow deploy.yml -L 20` |
| Produção estava no tip da `main` (`20040dde34`), 0 migrations pendentes, sem maintenance. | SSH só-leitura, 2026-11-14 |

Consequência prática: **"não deployar sem [W]" significa "não mergear sem [W]"** em PR com código de runtime. O
controle fica no merge, não no deploy.

### Mapa de runtimes (ADR 0062)

| Alvo | Host | Como chega código | Smoke canônico |
|---|---|---|---|
| `oimpresso.com` (prod) | Hostinger 148.135.133.115, `~/domains/oimpresso.com/public_html` | `deploy.yml` automático | `GET /login` → 200 |
| `uniafibro.com.br` | o mesmo app do Hostinger (rota por domínio, [RUNBOOK-uniafibro](../Site/RUNBOOK-uniafibro.md)) | junto com prod | `GET /` → 200 |
| `staging.oimpresso.com` | CT 100 177.74.67.30, `/opt/oimpresso-staging/code` (ADR 0235) | `deploy.sh <branch>` manual | `GET /login` → 200 |
| `mcp.oimpresso.com` | CT 100, `/opt/oimpresso-mcp/code` | `oimpresso-git-sync.timer` (6h) + `self-update.sh` | `GET /api/mcp/health` → 200 |

⛔ Nunca: `composer install` com mcp/octane no Hostinger, daemon no Hostinger, editar arquivo direto no servidor.

## 1. Segredos e acessos que o pipeline usa

| Segredo (GitHub Actions) | Uso | Existe? |
|---|---|---|
| `SSH_HOST` `SSH_PORT` `SSH_USER` `SSH_PRIVATE_KEY` | SSH runner → Hostinger | ✅ |
| `OPCACHE_RESET_TOKEN` | semente do token do `_ops_opcache_reset.php` (o passo se auto-cura se faltar) | ✅ |
| `SMOKE_PROD_USER` `SMOKE_PROD_PASS` | smoke autenticado (`screen-smoke-after-merge.yml`) | ✅ |
| Token da API DNS Hostinger | `/root/.hostinger-api-token` no CT 100 (não fica no GitHub) | não conferido (CT 100 inacessível na auditoria) |

## 2. Pré-check (antes do merge)

Rode no worktree do PR. Nada aqui toca servidor.

```bash
git fetch origin main && git log --oneline -1 origin/main
```

```bash
git diff --name-only origin/main...HEAD | grep -E 'Database/Migrations|database/migrations' || echo "sem migration"
```

```bash
git diff --quiet origin/main...HEAD -- composer.json composer.lock || echo "composer mudou: o deploy vai rodar composer install (janela de 503 maior)"
```

```bash
npm ci --no-audit --no-fund && npm run build:inertia && npm run build
```

```bash
node scripts/governance/deploy-classifica-push.test.mjs && node scripts/governance/deploy-sync-leve.test.mjs
```

Checklist:

- [ ] Migration nova tem `down()` e é idempotente (`.claude/rules/migrations.md`). DDL do MySQL/MariaDB **não é transacional**: migration que falha no meio deixa tabela órfã ([recovery §4](../../reference/deploy-recovery-patterns.md)).
- [ ] Migration destrutiva (drop/rename de coluna com dado) → passa pelo staging (§3) **e** confere backup do banco da noite (§7-D) antes do merge.
- [ ] `composer.lock` em sincronia com `composer.json` (o workflow `composer-lock-sync.yml` acusa).
- [ ] Nada de `--no-dev`: o Faker é usado em produção.
- [ ] Merge em horário de baixo uso se tiver migration pesada. A janela de 503 do deploy completo tem mediana de ~78s.

## 3. Deploy no staging (CT 100, FrankenPHP clássico)

**Hoje é opcional e manual.** Use em PR com migration destrutiva, troca de pacote, ou mudança em middleware/boot.
Detalhe completo e pegadinhas: [RUNBOOK-staging-ct100](RUNBOOK-staging-ct100.md).

```bash
tailscale ssh root@ct100-mcp 'git -C /opt/oimpresso-staging/code status --short | head; git -C /opt/oimpresso-staging/code branch --show-current'
```

> ⚠️ O checkout do staging é compartilhado e pode ter teste em andamento de outra pessoa. Se o `status` não estiver
> limpo, **pare e pergunte** antes de trocar de branch: o `deploy.sh` faz `reset --hard`.

```bash
tailscale ssh root@ct100-mcp 'cd /opt/oimpresso-staging/code && sh docker/oimpresso-staging/deploy.sh <branch>'
```

Migration no staging (o `deploy.sh` não roda):

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-staging php artisan migrate:status --pending'
```

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-staging php artisan migrate --force'
```

Banco do staging velho demais → re-seed anonimizado: `bash docker/oimpresso-staging/seed-from-prod.sh` (só com [W]: lê o banco de produção).

## 4. Smoke do staging

```bash
curl -s -o /dev/null -w '%{http_code} ssl:%{ssl_verify_result}\n' https://staging.oimpresso.com/login
```

Esperado `200 ssl:0`. Depois, login com qualquer username e senha `staging2026`, e abrir as telas que o PR tocou.

```bash
tailscale ssh root@ct100-mcp 'docker exec oimpresso-staging php artisan about >/dev/null && echo BOOT_OK; docker logs --tail 50 oimpresso-staging 2>&1 | grep -iE "error|exception" | tail -5'
```

## 5. Deploy em produção

### 5.1 Caminho normal: merge

Mergear o PR na `main`. Acompanhe:

```bash
gh run list --workflow deploy.yml -L 3
```

```bash
gh run watch <run-id> --exit-status
```

Ou `node scripts/deploy/aguardar-deploy.mjs` (espera o run do SHA e devolve o veredito).

O que o job completo faz, em ordem (todos com gate, nenhum com `--force` cego):

1. Build no runner (Node 24, `npm ci`, `build:inertia`, `build`), artefato `vite-build`.
2. SSH com aquecimento, conferência de PHP/disco, hash do `composer.lock` do servidor.
3. Snapshot de arquivos `~/backup-predeploy-<TS>.tar.gz` (guarda 5). **Não inclui banco.**
4. `artisan down --render=errors::503 --secret=<aleatório>`.
5. `git reset --hard <SHA do run>` + prova de que o fonte e o bundle são do mesmo commit.
6. `composer install` só se o lock mudou → `dump-autoload -o` sempre → `verify-classmap.php` → `artisan about`.
7. `artisan migrate --force`.
8. Publica os bundles mesclando (chunks antigos ficam 3 dias; `manifest.anterior.json` guardado).
9. Caches, permissões, chaves do Passport em 660, reset de OPcache antes do gate.
10. **Boot gate WEB**: `/login` atrás do bypass do maintenance. 5xx aqui mantém 503 e falha o deploy.
11. `artisan up` → reset de OPcache obrigatório → smoke `/login` + hash do bundle.
12. Failsafe: só fica no ar se bootar. Senão volta a 503 gracioso (melhor que 500).

### 5.2 Disparo manual (redeploy do tip, ou com `extra_artisan`)

```bash
gh workflow run deploy.yml --ref main -f skip_backup=false -f skip_migrate=false
```

`skip_backup=true` só com [W]. `extra_artisan` roda depois do migrate (ex.: um seeder pontual).

## 6. Smoke de produção

O workflow já confere `/login`, o bundle e as chaves do Passport. Depois de deploy com código de runtime, rode:

```bash
for u in https://oimpresso.com/login https://oimpresso.com/home https://oimpresso.com/sells https://oimpresso.com/financeiro https://www.oimpresso.com/login https://uniafibro.com.br/ https://www.uniafibro.com.br/ https://mcp.oimpresso.com/api/mcp/health; do curl -4 -s -o /dev/null -w "%{http_code} %{redirect_url} $u\n" --max-time 25 "$u"; done
```

| Rota | Esperado |
|---|---|
| `oimpresso.com/login` | 200 |
| `/home`, `/sells`, `/financeiro` (sem cookie) | 302 → `/login` |
| `www.oimpresso.com/login` | 301 → `oimpresso.com/login` |
| `uniafibro.com.br/` | 200 |
| `www.uniafibro.com.br/` | 301 → `uniafibro.com.br/` |
| `mcp.oimpresso.com/api/mcp/health` | 200 |
| `oimpresso.com/api/app/inicio` com Bearer inválido | 401 |

Qualquer 500 → `deploy-recovery-patterns.md §7`. Qualquer 404 numa rota nova → classmap ou cache ([validação pós-deploy](RUNBOOK-validacao-pos-deploy.md), camada 2).

Visual: abrir as telas do PR logado (navegador embutido ou Chrome) e tirar screenshot. Isso é obrigatório para tela
nova ([feedback-deploy-smoke-browser-obrigatorio](../../reference/feedback-deploy-smoke-browser-obrigatorio.md)).
O `screen-smoke-after-merge.yml` faz parte disso sozinho.

## 7. Rollback

**Antes de qualquer rollback, anote o corte:**

```bash
gh run list --workflow deploy.yml -L 10 --json conclusion,headSha,createdAt --jq '.[] | "\(.createdAt) \(.conclusion) \(.headSha)"'
```

O `SHA_BOM` é o `headSha` do último run `success` **antes** do deploy ruim. O horário dele é o corte: dado gravado no
banco depois desse horário é o que um restore do banco perderia.

### A. Revert do PR (preferido, ~10 min, passa por todos os gates)

```bash
gh pr view <PR> --json mergeCommit --jq .mergeCommit.oid
```

Abrir o PR de revert pela interface do GitHub (botão Revert) ou:

```bash
git fetch origin main && git switch -c revert/<PR> origin/main && git revert -m 1 <merge-sha>
```

Push, PR, merge com [W]. O merge dispara o deploy completo normal. **Migration não volta sozinha**: ver D.

### B. Redeploy de um SHA bom (quando o revert demora ou há vários PRs ruins)

O job de deploy faz `git reset --hard ${{ github.sha }}`. Disparando o workflow numa branch apontada para o `SHA_BOM`,
o pipeline inteiro (bundle do mesmo commit + gates) publica aquele commit:

```bash
git push origin <SHA_BOM>:refs/heads/rollback/<SHA_BOM_curto>
```

```bash
gh workflow run deploy.yml --ref rollback/<SHA_BOM_curto> -f skip_migrate=true
```

> ⚠️ **Ainda não ensaiado (gap G-05).** Próximo merge na `main` publica o tip de novo: o código ruim precisa ser
> revertido na `main` (A) antes de liberar merges. `skip_migrate=true` porque migration nova da versão ruim continua
> aplicada. Ver D.

### C. Só o bundle JS quebrou (PHP bom)

Os chunks antigos ficam 3 dias no servidor e o manifest anterior fica salvo:

```bash
ssh -4 -o ConnectTimeout=180 -o ServerAliveInterval=3 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd ~/domains/oimpresso.com/public_html/public/build-inertia && ls -l manifest*.json'
```

```bash
ssh -4 -o ConnectTimeout=180 -o ServerAliveInterval=3 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 'cd ~/domains/oimpresso.com/public_html/public/build-inertia && cp manifest.json manifest.ruim.json && cp manifest.anterior.json manifest.json'
```

Dura até o próximo deploy. É edição no servidor: só com [W], e seguida de A ou B.

### D. Banco

1. **Migration com `down()` segura** (só cria tabela ou coluna nova):

   ```bash
   ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan migrate:status | tail -15'
   ```

   ```bash
   ssh ... 'cd ~/domains/oimpresso.com/public_html && php artisan migrate:rollback --step=1 --pretend'
   ```

   Só depois do `--pretend` conferido e com [W]: o mesmo comando sem `--pretend`.

2. **Restore do banco** (último recurso, perde tudo depois do corte, **só [W] executa ou autoriza**). O backup é o
   zip noturno do `spatie/laravel-backup` (01:30) em `public/uploads/UltimatePOS/<data>.zip`, com o dump em
   `db-dumps/`.

   ```bash
   ssh ... 'ls -lt ~/domains/oimpresso.com/public_html/public/uploads/UltimatePOS/*.zip | head -5'
   ```

   ```bash
   ssh ... 'cd ~/tmp-restore && unzip -l ~/domains/oimpresso.com/public_html/public/uploads/UltimatePOS/<data>.zip | grep db-dumps'
   ```

   Antes de importar: `artisan down`, dump do estado atual (`mariadb-dump`) como ponto de volta, e só então importar.
   O passo a passo do import **não está escrito nem ensaiado** (gap G-01). Não improvisar.

> ⚠️ Em 2026-11-14 faltavam os zips de 7, 8, 11, 12 e 13/nov, e todos ficam no mesmo disco do servidor (gap G-01).

### E. Snapshot de arquivos

`~/backup-predeploy-<TS>.tar.gz` (5 últimos, ~800 MB cada) traz o que **não está no git**: `.env`, `storage/app`,
arquivos de usuário fora de `public/uploads/UltimatePOS`. Não traz `vendor`, `.git` nem banco. Serve para recuperar
`.env` ou `storage` corrompidos, não para voltar código (código volta por A ou B).

> ❌ A receita "Rollback rápido" em `memory/reference/hostinger.md` faz `git reset --hard origin/main`. Isso vai
> **para frente**, não para trás, e pula dump-autoload e OPcache. Não use (gap G-05).

## 8. DNS e TLS pós-deploy

Só leitura. Rode depois de mudança de domínio, cert ou `.htaccess`, e uma vez por mês.

```powershell
foreach ($d in 'oimpresso.com','www.oimpresso.com','mcp.oimpresso.com','staging.oimpresso.com','uniafibro.com.br','www.uniafibro.com.br') { Resolve-DnsName $d -Server 1.1.1.1 -DnsOnly | Where-Object { $_.Type -in 'A','CNAME' } | ForEach-Object { "$d $($_.Type) $($_.IPAddress)$($_.NameHost)" } }
```

| Domínio | Esperado (2026-11-14) |
|---|---|
| `oimpresso.com` | A 148.135.133.115 |
| `www.oimpresso.com` | CNAME `oimpresso.com` |
| `mcp.oimpresso.com`, `staging.oimpresso.com` | A 177.74.67.30 (CT 100) |
| `uniafibro.com.br` | A 84.32.84.240 e 2.57.91.71 (borda Hostinger) |
| `www.uniafibro.com.br` | CNAME `www.uniafibro.com.br.cdn.hstgr.net` (CDN Hostinger) |

```bash
for h in oimpresso.com uniafibro.com.br mcp.oimpresso.com staging.oimpresso.com; do echo -n "$h "; echo | openssl s_client -connect $h:443 -servername $h 2>/dev/null | openssl x509 -noout -enddate; done
```

Certificados vencem em fev/2027 (todos Let's Encrypt, renovação automática). Se algum mostrar menos de 20 dias:
Hostinger → hPanel SSL; CT 100 → `docker restart traefik` depois de conferir o DNS (staging, pegadinha 7).

O `www.uniafibro.com.br` passa pela CDN da Hostinger. Página da Uniafibro mudou e não aparece → limpar o cache da CDN
no hPanel antes de suspeitar do deploy.

## 9. MCP no CT 100 (não usa o `deploy.yml`)

O checkout `/opt/oimpresso-mcp/code` acompanha a `main` pelo `oimpresso-git-sync.timer` (a cada 6h) e pelo
`docker/oimpresso-mcp/scripts/self-update.sh`. Units versionadas em
[`docker/oimpresso-mcp/systemd/`](../../../docker/oimpresso-mcp/systemd/README.md).

```bash
tailscale ssh root@ct100-mcp 'git -C /opt/oimpresso-mcp/code log --oneline -1; systemctl list-timers --no-pager | grep oimpresso; docker ps --format "{{.Names}} {{.Status}}" | grep -E "mcp|traefik"'
```

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://mcp.oimpresso.com/api/mcp/health
```

## 10. Dry-run deste RUNBOOK (2026-11-14, nenhum deploy feito)

| Passo | Comando | Resultado |
|---|---|---|
| §0 histórico | `gh run list --workflow deploy.yml -L 20` | ✅ 19/20 verdes |
| §1 segredos | `gh secret list` | ✅ os 7 do pipeline existem |
| §2 testes do classificador | `node scripts/governance/deploy-*.test.mjs` | ✅ os dois passam, as mutações mordem |
| §2 sintaxe dos scripts | `bash -n scripts/deploy/*.sh docker/oimpresso-staging/*.sh` | ✅ |
| §2 `php -l verify-classmap.php` | — | ⚪ não rodou: sem `php` no PATH do Git Bash |
| §2 `actionlint` | — | ⚪ não instalado na máquina |
| Estado de prod (SSH só-leitura) | `git rev-parse`, `migrate:status --pending`, `df`, `ls` | ✅ HEAD = tip da main, 0 pendentes, disco 60% |
| §3/§4 staging via SSH | `tailscale ssh root@ct100-mcp` | ❌ timeout na porta 22 do CT 100 a partir desta máquina |
| §4 smoke staging (HTTP) | `curl staging.oimpresso.com/login` | ✅ 200 |
| §6 smoke prod | loop de curl | ✅ todas as rotas no esperado |
| §7-A/B rollback | — | ⚪ não executado (exige [W]); B nunca ensaiado |
| §7-C manifest anterior | `ls manifest*.json` | ✅ `manifest.anterior.json` presente |
| §7-D backups do banco | `ls public/uploads/UltimatePOS/*.zip` | ❌ faltam 5 dos últimos 10 dias |
| §8 DNS/TLS | `Resolve-DnsName`, `openssl` | ✅ |
| §9 MCP | `curl /api/mcp/health` | ✅ 200 (a raiz `/` dá 500 em ~15s, sem rota) |

## 11. Gaps

Lista viva no handoff da sessão
[2026-11-14-1900-runbook-deploy-producao](../../handoffs/2026-11-14-1900-runbook-deploy-producao.md).
Resumo: G-01 backups do banco · G-02 staging não é gate · G-03 sonda do Passport derruba deploy verde ·
G-04 arquivos soltos no servidor · G-05 rollback nunca ensaiado e receita errada no `hostinger.md`.
