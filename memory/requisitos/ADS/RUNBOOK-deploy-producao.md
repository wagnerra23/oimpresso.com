---
owner: W
last_validated: "2026-06-08"
slug: ads-runbook-deploy-producao
title: "ADS — Runbook de deploy em produção (Hostinger app + CT 100 daemon)"
type: runbook
module: ADS
status: ativo
date: 2026-05-03
---

# RUNBOOK — Deploy do ADS em produção

> ⚠️ Antes de executar este runbook, ler ARQ-0006 (Policy Engine), ARQ-0010 (Governance)
> e **ARQ-0011 (Topologia de deployment)**. Wagner deve aprovar cada fase manualmente.

## Topologia (ARQ-0011)

```
HOSTINGER                              CT 100 PROXMOX
  Laravel ADS code                       Brain A daemon (Node.js)
  MySQL mcp_dual_brain_*                 Ollama qwen2.5-coder:14b
  UI /ads/admin/decisoes                 Watchers HTTP poll Hostinger
  Cron php artisan ads:process-brain-b   systemd ads-brain-a.service
  API endpoints (/route, /recent-*)
        ▲                                        │
        └────────── HTTPS Bearer ────────────────┘
```

## Pré-requisitos

- [ ] PR mergeado em `main` com Modules/ADS/ + scripts/dual-brain/
- [ ] Migrations validadas em local (5/5 ran, smoke e2e passando)
- [ ] 63 testes Pest verdes
- [ ] `ADS_API_KEY` gerada e guardada no Vaultwarden

## Fase 1 — Deploy do app Laravel no Hostinger

**Objetivo:** subir Modules/ADS/ via git pull no Hostinger sem rodar daemons (Hostinger é shared hosting).

```bash
# Warm-up SSH (CLAUDE.md §7)
for i in 1 2 3 4 5; do
  curl -s -o /dev/null --max-time 15 https://oimpresso.com/login
done

# SSH com timeouts altos
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115

# No servidor:
cd ~/domains/oimpresso.com/public_html
git fetch origin main
git status                          # confirmar working tree limpo
git pull origin main                # traz Modules/ADS/ + scripts/dual-brain/

# composer install obrigatório se composer.json mudou (ver auto-mem composer_install_obrigatorio_pos_deploy)
# No nosso caso, ADS não adiciona dependências PHP, apenas estrutura de módulo.
# Mas validar:
git diff HEAD~5..HEAD -- composer.json composer.lock
# Se vazio: pular composer.
# Se mudou: composer install --no-dev --optimize-autoloader --prefer-dist

# Adicionar ADS_API_KEY ao .env do servidor
nano .env
# Adicionar linha:
# ADS_API_KEY=<copiar do Vaultwarden>

# Limpar caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

**Validação:** `curl https://oimpresso.com/api/ads/health` deve retornar 200 com `{"status":"ok",...}`.

## Fase 2 — Migrations no MySQL Hostinger

```bash
# Ainda no SSH Hostinger
php artisan migrate --path=Modules/ADS/Database/Migrations --force --pretend
# Revisar SQL impresso. Se tudo OK:
php artisan migrate --path=Modules/ADS/Database/Migrations --force
```

**Validação:**
```bash
php artisan migrate:status 2>&1 | grep -E "mcp_(file_locks|decision_thresholds|confidence_scores|dual_brain_decisions|decision_patterns)"
# Esperado: 5 linhas com [Ran]
```

## Fase 3 — Habilitar módulo + ativar UI

```bash
# Editar modules_statuses.json — adicionar "ADS": true
nano modules_statuses.json
# Validar JSON:
python3 -m json.tool < modules_statuses.json > /dev/null && echo OK

# Build do bundle Inertia (Pages/ads/Admin/Decisoes.tsx + DecisaoShow.tsx)
npm ci
npm run build

# Limpar OPcache (se houver):
php artisan config:cache
```

**Validação:**
- Abrir `https://oimpresso.com/ads/admin/decisoes` no browser logado como Wagner
- Inbox deve renderizar com KPIs zerados (sem decisions ainda)

## Fase 4 — Brain A daemon no CT 100 Proxmox

**Por que CT 100 e não Hostinger:** daemons longa-duração não rodam em shared hosting (CLAUDE.md §4).

```bash
# Acesso CT 100 via Tailscale
ssh -i ~/.ssh/id_ed25519_oimpresso wagner@100.99.207.66

# Clone leve só dos scripts (NÃO clonar repo inteiro)
sudo mkdir -p /opt/ads-daemon
sudo chown wagner:wagner /opt/ads-daemon
cd /opt/ads-daemon

# Opção A — clone parcial do repo
git clone --depth 1 --filter=blob:none --sparse https://github.com/wagnerra23/oimpresso.com.git .
git sparse-checkout set scripts/dual-brain

# Opção B — copiar arquivos manualmente via scp (mais simples se preferir)

cd scripts/dual-brain
cp .env.example .env
nano .env
# ADS_API_URL=https://oimpresso.com/api/ads/route
# ADS_HEALTH_URL=https://oimpresso.com/api/ads/health
# ADS_API_KEY=<copiar do Vaultwarden, mesmo do .env Hostinger>
# DEFAULT_BUSINESS_ID=1
# REPO_PATH=/path/onde/git/é/observado   # se daemon não vai observar git, deixar vazio e desabilitar
# LARAVEL_LOG_PATH=                       # idem
# ALLOW_INSECURE_TLS=false                # produção usa cert válido
# OLLAMA_HOST=http://localhost:11434      # se Ollama instalado no CT

npm install --production

# Smoke test ANTES de rodar como serviço
npm run smoke
# Esperado: Health 200 + 3 cenários ✓
```

### Rodar como systemd service

```bash
sudo nano /etc/systemd/system/ads-brain-a.service
```

```ini
[Unit]
Description=ADS Brain A Daemon
After=network.target

[Service]
Type=simple
User=wagner
WorkingDirectory=/opt/ads-daemon/scripts/dual-brain
ExecStart=/usr/bin/node brain-a-daemon.js
Restart=on-failure
RestartSec=10
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable ads-brain-a
sudo systemctl start ads-brain-a
sudo systemctl status ads-brain-a
journalctl -u ads-brain-a -f
```

**Validação:**
- `journalctl -u ads-brain-a -n 20` mostra `[boot] ADS health OK` e `[boot] Brain A operacional`
- Em produção, `mcp_dual_brain_decisions` começa a receber eventos quando algo for commitado/logar erro

## Fase 5 — Cron processador Brain B

**No Hostinger (não no CT 100, pois precisa do Laravel/composer):**

```bash
# Editar crontab
crontab -e

# Adicionar:
*/5 * * * * cd ~/domains/oimpresso.com/public_html && php artisan ads:process-brain-b --limit=5 >> storage/logs/ads-brain-b.log 2>&1
```

A cada 5 minutos processa até 5 decisions com `destination=brain_b`. Custo estimado: ~$0.05/dia em produção real considerando Sonnet com prompt caching.

## Rollback de cada fase

| Fase | Como reverter |
|---|---|
| 1 | `git reset --hard <sha-anterior>` no Hostinger; remover `ADS_API_KEY` do `.env` |
| 2 | Cada migration tem `down()` — `php artisan migrate:rollback --path=Modules/ADS/Database/Migrations --step=5` |
| 3 | `"ADS": false` no `modules_statuses.json` + `php artisan config:clear` |
| 4 | `sudo systemctl stop ads-brain-a && sudo systemctl disable ads-brain-a` |
| 5 | `crontab -e` removendo a linha |

## Riscos conhecidos

- **Custo Brain B fora de controle:** se daemon dispara muitos eventos, o cron processa até 5 a cada 5min → 1440 chamadas/dia max → ~$1.50/dia teto. Acima disso, ajustar `--limit` ou adicionar quota em `mcp_quotas`.
- **Daemon spam por commit_hooks:** se Wagner faz `git commit --amend` repetidamente, daemon detecta cada um. Mitigação: triage rule-based + Ollama filtram triviais.
- **Brain A travado por erro Ollama:** OllamaClient tem `timeoutMs=8000` + fallback rule-based. Se Ollama trava 8s/evento, daemon ainda processa via regex.

## Métricas para acompanhar (primeiros 30 dias)

- `mcp_dual_brain_decisions` total/dia
- Distribuição por `destination`: alvo HiTL-0 ≥ 30% no fim do mês
- Taxa de `outcome=wagner_modified` < 20% (acima = priors mal calibrados)
- Custo total Brain B / mês < $30
