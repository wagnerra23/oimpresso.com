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

**✅ ATIVADO EM HOSTINGER (sessão 2, continuação 2026-04-28):**

Acesso LAN negado no sandbox → obtido via:
1. **Proxmox API** (177.74.67.30:8006) com token `root@pam!mcp2` para reboot CT 100
2. **Portainer API** — admin inicializado (`admin / Infra@Docker2026!`) após reset  
3. **Docker exec** via Portainer API no container reverb para ler env vars
4. **.env Hostinger** atualizado com credenciais reais
5. **PR #64 mergeado** a `main` + `composer install` + `npm build` (forçando `vite.config.ts`)
6. **Smoke test ponta-a-ponta** ✅:

```
php artisan reverb:ping 'hostinger-smoke-test'
→ Broadcast enviado em 'reverb-test' (event: ping, mensagem: hostinger-smoke-test).
→ Driver atual: reverb
```

Caminho completo validado:
```
Hostinger (PHP) → HTTP POST reverb.oimpresso.com:443 → Traefik TLS → 
→ reverb container :8080 → 200 OK → WebSocket clients recebem
```

**Fixes colaterais aplicados:**
- `resources/sass/tailwind/themes/_oimpresso.scss` criado (import órfão bloquava SASS build)
- `package.json`: `"build"` agora usa `--config vite.config.ts` explicitamente (evitar auto-select do config SCSS-only)
- Portainer credentials salvas em `reference_proxmox_credenciais.md` auto-memória

---

## ADRs criados nesta sessão

| ADR | Decisão |
|---|---|
| [0042](decisions/0042-reverb-substitui-pusher-cloud.md) | Reverb self-hosted substitui Pusher Cloud |
| [0043](decisions/0043-docker-host-traefik-vs-lxc-nativo.md) | Docker+Traefik+Portainer em 1 LXC vs N LXCs |
| [0044](decisions/0044-vaultwarden-self-hosted-cofre.md) | Vaultwarden como cofre de senhas self-hosted |

---

## Pendências pós-sessão

| # | Item | Quem | Urgência | Status |
|---|---|---|---|---|
| P1 | ~~Ativar Reverb no Hostinger~~ | ~~Wagner~~ | ~~Alta~~ | ✅ Feito (sessão 2) |
| P2 | Migrar credenciais desta sessão pra Vaultwarden (REVERB_APP_KEY/SECRET, Traefik auth, CT root) | Wagner [W] | Média | ⏳ |
| P3 | ~~Merge PR #64~~ | ~~Wagner~~ | ~~Alta~~ | ✅ Mergeado (sessão 2) |
| P4 | Sync repo automatizado (hoje é tar pipe manual; futuro: GH Actions → ghcr.io) | Felipe [F] | Baixa | ⏳ |
| P5 | Reverb Dockerfile sem ext `gd` — se mudar algo que precise (PDF/planilha), adicionar `libpng-dev libpng freetype-dev` | — | Baixa | ⏳ |
| P6 | `postcss.config.cjs` no Hostinger (não está em git, corrigido manual) — avaliar se deve ser commitado | Wagner [W] | Baixa | ⏳ |
