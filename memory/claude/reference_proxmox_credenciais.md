---
name: Credenciais Proxmox empresa
description: Login do Proxmox sistema (192.168.0.2) — fora do git. Atualizar quando rotacionar senha ou criar token API definitivo
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Servidor:** Proxmox VE 9.1.1 — `sistema` (Intel Xeon E5-2680v4, 14C, 125.7 GB RAM, 319.6 GB local-lvm livre, 85 GB local livre)

**LAN:** `https://192.168.0.2:8006/` (painel) · `192.168.0.2:22` (SSH host) · bridge `vmbr0` em `192.168.0.2/24`

**Login web/SSH:**
- User: `root@pam`
- Senha: `wscrct.01` (ATENÇÃO: senha case-sensitive, é `w` minúsculo. Wagner reusa "Wscrct*..." em outros lugares mas no Proxmox é minúsculo `w` + `.01`)

**Token API ATIVO** (criado 2026-04-28 via REST, autorizado por Wagner):
- Token ID: `root@pam!mcp2`
- Secret: `e15a341f-cd82-4d99-8fd7-8f3b4d17a09b`
- Privsep: `0` (full root rights — root@pam permissions)
- Expira: nunca
- Header: `Authorization: PVEAPIToken=root@pam!mcp2=e15a341f-cd82-4d99-8fd7-8f3b4d17a09b`

**Token antigo** `root@pam!mcp` (privsep=1) ainda existe mas Wagner perdeu o secret. Pode deletar quando quiser.

⚠️ **Limitação token API:** mesmo `privsep=0` não pode setar features de LXC tipo `keyctl=1` — só `root@pam` direto via password ticket. Usar token pra read-only/operações comuns; password ticket pra criar VMs/CTs com features especiais.

---

## CT 100 docker-host (criado 2026-04-28)

- Hostname: `docker-host`
- IP LAN: `192.168.0.50/24`
- Tipo: LXC unprivileged Debian 12
- Specs: 4 vCPU / 8 GB RAM / 60 GB disk (local-lvm)
- Features: `nesting=1,keyctl=1` (Docker dentro)
- onboot: 1 (auto-start no reboot Proxmox)
- SSH: chave `id_ed25519_oimpresso` injetada → `ssh -i ~/.ssh/id_ed25519_oimpresso root@192.168.0.50`
- Senha root: `4R781JvuwYiWqJgTea8oHw` (gerada Claude — usar SSH key sempre que possível)
- Docker: 29.4.1, Compose v5.1.3 (validados com hello-world em 2026-04-28)

### Stack Docker em /opt/docker-host (subiu 2026-04-28, verificado running 2026-04-28 sessão 3)

- `compose.yml` versionado em [`infra/proxmox/docker-host/compose.yml`](infra/proxmox/docker-host/compose.yml)
- `.env` no CT (gitignored): `TRAEFIK_DASHBOARD_AUTH` + `ACME_EMAIL=wagnerra@gmail.com` + `MEILI_MASTER_KEY=9c08945878571ecb76b70d25deb3852b` + `VAULTWARDEN_SIGNUPS_ALLOWED=false`
- **Traefik 3.6** — Dashboard: `https://traefik.oimpresso.com/` BasicAuth `admin:zrG8nSxI0DIcWEIe`
- **Portainer CE LTS 2.39.1** em `https://portainer.oimpresso.com/`
  - **Admin:** `admin` / `Infra@Docker2026!`
  - **Endpoint Docker local** ID 1, nome `docker-host`, `unix:///var/run/docker.sock`
  - **ATENÇÃO:** se volume `portainer-data` perdido → reboot CT → POST /api/users/admin/init → readicionar endpoint
- **Vaultwarden 1.35.8-alpine** em `https://vault.oimpresso.com/`
  - Wagner criou conta: `wagnerra@gmail.com` / `Wscrct*2312`
  - ADMIN_TOKEN: ver `reference_vaultwarden_credenciais.md`
  - SIGNUPS: **false** (desabilitado após criação da conta)
- **Reverb (Laravel 8.4)** em `https://reverb.oimpresso.com/`
  - `REVERB_APP_KEY=5921152f-5c00-4bb6-92f0-0ed94a75c68d`
  - `REVERB_APP_SECRET=8e101a3e-7d35-4dcb-a27b-6f5fd5474c64`
  - Smoke test ✅: `reverb:ping → HTTP 200 em 13ms`
- **Meilisearch v1.10.3** em `https://meilisearch.oimpresso.com/` (DNS pendente — Hostinger API down)
  - `MEILI_MASTER_KEY=9c08945878571ecb76b70d25deb3852b`
  - Volume: `meilisearch-data` em `/meili_data`
  - Container rodando ✅. Índice `copiloto_memoria_facts` precisa embedder OpenAI configurado.

**IP público da empresa:** `177.74.67.30` (ISP ateky.net.br — confirmado 2026-04-28)

**Não commitar senhas em INFRA.md ou no repo.** INFRA.md tem só o método sem o valor.
