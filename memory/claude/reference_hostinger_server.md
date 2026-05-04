---
name: Hostinger server access for oimpresso.com
description: SSH endpoint and deploy path for the oimpresso.com production server on Hostinger. Claude/sandbox CAN SSH directly (key at ~/.ssh/id_ed25519_oimpresso).
type: reference
originSessionId: 0922b4af-6c32-45e6-ae30-5d09580ae4ca
---
Servidor produção oimpresso.com (Hostinger):

- **SSH:** `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` (sempre `-4` pra IPv4)
- **Deploy path:** `/home/u906587222/domains/oimpresso.com/public_html` (ou `domains/oimpresso.com/public_html` relativo ao home)
- **Branch de deploy:** `6.7-bootstrap` (GitHub `wagnerra23/oimpresso.com`)
- **Branch `producao`:** estado real do servidor (90k+ arquivos)
- **Servidor em UTC** — horário do servidor = UTC; BR é UTC-3

**Claude/sandbox SSHa direto** (key instalada em 2026-04-23):
- Conexão é MUITO lenta/flaky — timeouts curtos (60-300s) falham consistente
- **Receita confirmada que funciona** (validada 2026-04-27 após 4 timeouts seguidos com config "padrão"):
  ```bash
  # 1. Warm: 5 curl hits sequenciais antes de qualquer SSH
  for i in 1 2 3 4 5; do
    curl -s -o /dev/null -w "$i:%{http_code} " https://oimpresso.com/login --max-time 15
  done; echo

  # 2. SSH com config robusta (TODOS os flags importam)
  ssh -4 \
    -o ConnectTimeout=900 \
    -o ServerAliveInterval=3 \
    -o ServerAliveCountMax=200 \
    -o ConnectionAttempts=5 \
    -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115 \
    "comando_aqui"
  ```
- Configs CRÍTICAS (não cortar nenhuma):
  - `ConnectTimeout=900` (15min — Hostinger as vezes leva minutos pra responder handshake)
  - `ServerAliveInterval=3` (keepalive a cada 3s — sem isso router intermediário derruba conexão "ociosa")
  - `ServerAliveCountMax=200` (200 keepalives sem resposta antes de desistir)
  - `ConnectionAttempts=5` (retry embutido)
  - `-4` IPv4 obrigatório (ver `feedback_hostinger_ipv4`)
- Não fazer sleep entre commands; usar `run_in_background` ou Monitor com until-loop

**Deploy típico (Claude pode fazer):** branch atual canônica é `main` (não mais `6.7-bootstrap` — promovido em 2026-04-27 ADR 0038).
```bash
# Após warm com 5 curls (ver receita acima):
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

**Se composer.json/lock mudou no PR**, adicionar antes do clear:
```bash
   composer install --no-dev --optimize-autoloader && \
```
(NUNCA cortar `--no-dev` se for produção; mas conferir sempre — alguns pacotes "dev" são usados em prod no oimpresso, vide auto-memória `reference_composer_install_obrigatorio_pos_deploy`)

**Rollback rápido pra estado estável** (quando branch da feature em deploy quebra):
```bash
ssh -4 [...flags...] u906587222@148.135.133.115 \
  "cd domains/oimpresso.com/public_html && \
   git fetch origin main && git reset --hard origin/main && \
   /usr/bin/php artisan view:clear"
```
Validado 2026-04-27 — Sprint 2 quebrou /memcofre+/copiloto em 5min, rollback feito em ~30s, prod estabilizada antes do fix.

**Workflow `.github/workflows/deploy.yml`** existe mas é manual (workflow_dispatch). `quick-sync.yml` está QUEBRADO desde 2026-04-26 (falha em Setup SSH, ver `reference_quick_sync_quebrada`) — Hostinger NÃO recebe deploys automáticos. Sempre fazer pull manual via SSH após merge no main.

**Boost MCP `mcp__laravel_boost__*`** pode estar disponível ou não dependendo da sessão Claude Code (Wagner reinicia + .mcp.json configurado). Verificar com `ToolSearch query="laravel boost"` antes de assumir disponibilidade. **Boost NÃO substitui SSH** — ele roda artisan local, não no Hostinger. Útil pra introspecção do projeto local, não pra deploy.

**Ver também:** `feedback_hostinger_ipv4.md` (IPv4 obrigatório), `reference_hostinger_analise.md` (análise DB + queries SQL com mesma receita SSH), `reference_quick_sync_quebrada.md` (action GH automatizada inutilizável).
