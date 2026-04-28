# Sessão 2026-04-28 — Reverb + Docker-host CT 100 (Proxmox) ao vivo

**Branch:** `claude/reverb-install`
**PR:** [#64 feat(broadcast): Reverb (self-hosted) substitui Pusher Cloud](https://github.com/wagnerra23/oimpresso.com/pull/64)
**Duração total da sessão:** ~4–5 h (duas janelas de contexto, continuação)
**Implementador:** Claude + Wagner [W]

---

## O que foi feito

### 1. Substituição Pusher Cloud → Reverb (PR #64, commit `0d15a772`)

- Removido `pusher/pusher-php-server ^5.0` do `composer.json` (cloud pago)
- Adicionado `laravel/reverb ^1.10` — puxa `pusher-php-server@7.2.7` como dep transitiva só pra publicação interna, sem comunicar com Pusher.com
- npm: `+laravel-echo`, `+pusher-js`, `+@laravel/echo-react`
- `config/broadcasting.php`: bloco `reverb` substituindo `pusher` (cloud)
- `resources/js/app.tsx`: `configureEcho({ broadcaster: 'reverb' })`
- `app/Events/ReverbPing.php` + `app/Console/Commands/ReverbPingCommand.php` (smoke test pra `reverb:ping`)
- ADR 0042 documentando a decisão

### 2. CT 100 provisionado no Proxmox (docker-host)

- LXC Debian 12 criado em CT 100, IP `192.168.0.50`
- Docker CE + Docker Compose instalados
- Stack `infra/proxmox/docker-host/compose.yml` com:
  - **Traefik v3.6** — reverse-proxy + Let's Encrypt automático (HTTP-01)
  - **Portainer CE LTS** — UI Docker em `portainer.oimpresso.com`
  - **Vaultwarden 1.35.8-alpine** — cofre de senhas self-hosted em `vault.oimpresso.com`
  - **Reverb** — daemon WS em `reverb.oimpresso.com`
- DNS Cloudflare: 4 A records apontando pra `177.74.67.30` (IP fixo empresa), proxy OFF
- TP-Link port forward: 443 → 192.168.0.50:443 + 80 → 192.168.0.50:80 (ACME)
- ADR 0043 documentando a escolha Docker+Traefik vs N LXCs
- ADR 0044 documentando Vaultwarden self-hosted

### 3. Dockerfile Reverb + .dockerignore (commit `a57f1dc2`)

```
infra/proxmox/docker-host/reverb/Dockerfile
infra/proxmox/docker-host/reverb/.dockerignore
infra/proxmox/docker-host/compose.yml  (bloco reverb adicionado)
infra/proxmox/docker-host/.env.example (REVERB_APP_LARAVEL_KEY + ID/KEY/SECRET)
```

- Base: `php:8.4-cli-alpine`
- Exts instaladas: `pcntl sockets bcmath intl opcache pdo_mysql zip`
- Repo sincronizado pra `/opt/oimpresso-app` via `tar pipe SSH` (manual; futuro: ghcr.io)
- Build context: `/opt/oimpresso-app` (raiz do repo no CT)
- Pivots técnicos durante build:
  - `traefik:v3.5 → v3.6` (incompat com Docker Engine 29 API client 1.24)
  - `linux-headers` obrigatório pra compilar ext `sockets` no Alpine
  - `BROADCAST_DRIVER=reverb` (não `BROADCAST_CONNECTION`)
  - `TELESCOPE_ENABLED=false` (evitar poluição de log por SQLite inexistente)
  - `--ignore-platform-reqs` no `composer install` (contornar `gd` de mpdf/barcode/phpspreadsheet)

### 4. Smoke test ponta-a-ponta ✅ (2026-04-28)

```
docker exec reverb php artisan reverb:ping "smoke-FIX"
→ "Driver atual: reverb"
→ "Broadcast enviado em 'reverb-test' (event: ping)"

Traefik access log:
POST /apps/oimpresso/events auth_key=... → HTTP 200 em 13ms

Caminho: container → DNS público → IP empresa →
         TP-Link 443 forward → Traefik (cert LE válido) → reverb :8080 → 200 OK
```

**4/4 certs Let's Encrypt válidos:**
- `portainer.oimpresso.com` (R13, expira 2026-07-27)
- `traefik.oimpresso.com` (R13)
- `vault.oimpresso.com` (R12, expira 2026-07-27)
- `reverb.oimpresso.com` (R12, expira 2026-07-27)

---

## Estado final da branch

Branch `claude/reverb-install` empurrada. PR #64 aberto (10 commits, +2210/-36 linhas).

**Hostinger (prod) ainda com `BROADCAST_DRIVER="pusher"` e chaves Pusher vazias** — comportamento atual: broadcast silencioso (no-op). A ativação do Reverb em Hostinger foi deferida:

```bash
# Para ativar Reverb em Hostinger, Wagner precisa:
# 1. Pegar REVERB_APP_KEY e REVERB_APP_SECRET do /opt/docker-host/.env no CT 100
#    (ssh root@192.168.0.50 — ou via Portainer → Containers → reverb → Inspect)
# 2. Editar .env no Hostinger:
BROADCAST_DRIVER=reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=oimpresso
REVERB_APP_KEY=<valor do CT .env>
REVERB_APP_SECRET=<valor do CT .env>
REVERB_HOST=reverb.oimpresso.com
REVERB_PORT=443
REVERB_SCHEME=https
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST=reverb.oimpresso.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
# 3. Remover PUSHER_APP_* legados
# 4. php artisan optimize:clear && npm run build (rebuild VITE_REVERB_* no bundle)
```

**Bloqueio técnico desta sessão:** sandbox Claude sem acesso LAN a `192.168.0.50`, impossível ler o `.env` do CT para copiar KEY/SECRET pro Hostinger.

---

## ADRs criados nesta sessão

| ADR | Decisão |
|---|---|
| [0042](decisions/0042-reverb-substitui-pusher-cloud.md) | Reverb self-hosted substitui Pusher Cloud |
| [0043](decisions/0043-docker-host-traefik-vs-lxc-nativo.md) | Docker+Traefik+Portainer em 1 LXC vs N LXCs |
| [0044](decisions/0044-vaultwarden-self-hosted-cofre.md) | Vaultwarden como cofre de senhas self-hosted |

---

## Pendências pós-sessão

| # | Item | Quem | Urgência |
|---|---|---|---|
| P1 | Ativar Reverb no Hostinger — Wagner pega KEY/SECRET do CT e atualiza `.env` + rebuild | Wagner [W] | Alta — Cycle 01 streaming Copiloto |
| P2 | Migrar credenciais geradas nesta sessão pra Vaultwarden (app Reverb, Traefik auth, Vaultwarden admin) | Wagner [W] | Média |
| P3 | Merge PR #64 após validação visual | Wagner [W] | Alta |
| P4 | Sync repo automatizado (hoje é tar pipe manual; futuro: GH Actions → ghcr.io) | Felipe [F] | Baixa |
| P5 | Reverb Dockerfile sem ext `gd` — se mudar algo que precise (PDF/planilha), adicionar `libpng-dev libpng freetype-dev` | — | Baixa |
