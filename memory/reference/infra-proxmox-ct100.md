---
name: Proxmox + CT 100 — acesso, hardening, runtime
description: Servidor Proxmox empresa (192.168.0.2) + CT 100 docker-host (192.168.0.50) — credenciais (no Vaultwarden), SSH key-only + fail2ban systemd backend, Tailscale 100.99.207.66, stack Docker (Traefik/Portainer/Vaultwarden/Reverb/Meilisearch), bootstrap services, Traefik labels pattern, autossh tunnel pra Hostinger MySQL
type: reference
---

## Servidor Proxmox empresa (host)

**Hardware:** Intel Xeon E5-2680v4, 14C, **125.7 GB RAM**, 319.6 GB local-lvm livre, 85 GB local livre — Proxmox VE 9.1.1 hostname `sistema`. Wagner declarou 2026-04-28: máquina física com **128 GB RAM, 2 TB HD, IP fixo**, status ativo e disponível.

**Rede:**
- LAN: `https://192.168.0.2:8006/` (painel) · `192.168.0.2:22` (SSH host) · bridge `vmbr0` em `192.168.0.2/24`
- IP público empresa: `177.74.67.30` (ISP ateky.net.br — confirmado 2026-04-28)
- Painel publicamente exposto via `https://177.74.67.30:8006/` (risco — ver infra-rede-empresa.md)

**Login web/SSH (root@pam):**
- User: `root@pam`
- Senha: **valor no Vaultwarden** (item `proxmox-root`); case-sensitive

**Token API ATIVO** (criado 2026-04-28 via REST, autorizado por Wagner):
- Token ID: `root@pam!mcp2`
- Secret: **valor no Vaultwarden** (item `proxmox-api-token-mcp2`)
- **Privsep: `0`** (full root rights — root@pam permissions)
- Expira: nunca
- Header: `Authorization: PVEAPIToken=root@pam!mcp2=<secret>`

**Token antigo** `root@pam!mcp` (privsep=1) ainda existe mas Wagner perdeu o secret. Pode deletar.

**Limitação token API:** mesmo `privsep=0` não pode setar features de LXC tipo `keyctl=1` — só `root@pam` direto via password ticket. Usar token pra read-only/operações comuns; password ticket pra criar VMs/CTs com features especiais.

**Quando usar Proxmox:** qualquer serviço que precise de daemon persistente, controle de portas, supervisor, ou config nginx custom — onde Hostinger compartilhado bloqueia.

**Casos de uso já mapeados:**
1. **Centrifugo** (WebSocket broadcaster) — ADR 0058 (Reverb foi SUBSTITUÍDO por Centrifugo+FrankenPHP, ADR 0042 superseded)
2. **Meilisearch** (memória semântica Jana) — ADR 0036
3. **Horizon + workers IA** — ADR 0035
4. **VM staging** — réplica pré-produção

**NÃO usar pra:** o app PHP-FPM principal (`oimpresso.com`) — fica no Hostinger Cloud Startup. Proxmox é só pra daemons/serviços auxiliares (ADR 0062 — separação runtime IRREVOGÁVEL).

**Riscos lembrados:** energia/link da empresa (UPS + 4G failover), backup off-site (snapshot Proxmox não sai do HW), DNS via Cloudflare laranja-off pra WS nativo.

---

## CT 100 docker-host (criado 2026-04-28)

- Hostname: `docker-host`
- IP LAN: **`192.168.0.50/24`**
- Tipo: LXC unprivileged Debian 12
- Specs: 4 vCPU / 8 GB RAM / 60 GB disk (local-lvm)
- Features: `nesting=1,keyctl=1` (Docker dentro)
- onboot: 1 (auto-start no reboot Proxmox)
- Senha root: **valor no Vaultwarden** (item `ct100-root`) — usar SSH key sempre que possível
- Docker: 29.4.1, Compose v5.1.3 (validados com hello-world em 2026-04-28)

### Acesso SSH (3 caminhos, hardening 2026-04-30)

| Origem | Endpoint | Auth | Status |
|---|---|---|---|
| LAN empresa | `ssh root@192.168.0.50` | chave SSH | ok |
| Tailscale | `ssh root@100.99.207.66` | Tailscale ACL + chave | ok |
| Internet pública | TP-Link NAT bloqueia | — | bloqueado |

**Por que NÃO expor 22 público:**
- Tailscale resolve 100% (qualquer lugar do mundo, ACL granular)
- LAN empresa cobre Wagner/Felipe in-office
- Bots fazem 1000+ tentativas SSH/dia em portas 22 expostas — fail2ban segura mas é ruído
- Risco de zero-day OpenSSH > benefício
- TP-Link já bloqueia (ver infra-rede-empresa.md)

### Receita SSH hardening (cole-e-roda em outros CTs)

**1. Drop-in config (não modifica `/etc/ssh/sshd_config` original):**

```bash
mkdir -p /etc/ssh/sshd_config.d

cat > /etc/ssh/sshd_config.d/oimpresso-hardening.conf <<'EOF'
PasswordAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no
PermitRootLogin prohibit-password
MaxAuthTries 3
MaxSessions 10
LoginGraceTime 30
AllowTcpForwarding yes
GatewayPorts no
PermitTunnel no
ClientAliveInterval 300
ClientAliveCountMax 6
Banner /etc/issue.net
EOF

cat > /etc/issue.net <<'EOF'
=========================================================
oimpresso CT — acesso AUTORIZADO apenas
Logs em journald + mcp_audit_log
LGPD Art. 18 — tentativas indevidas serão investigadas
=========================================================
EOF

# Validar antes de reload (se sshd -t falhar, sshd_config quebrou)
sshd -t && systemctl reload sshd
```

**2. fail2ban com backend systemd** (Debian 12 LXC NÃO tem `/var/log/auth.log`):

**Pegadinha:** install padrão do fail2ban tenta ler `/var/log/auth.log` que não existe em LXC moderno (logs vão pro journald). Erro típico: `ERROR Failed during configuration: Have not found any log file for sshd jail`.

```bash
apt-get install -y fail2ban

cat > /etc/fail2ban/jail.d/sshd.local <<'EOF'
[DEFAULT]
backend = systemd

[sshd]
enabled = true
filter = sshd
maxretry = 3
bantime = 3600
findtime = 600
EOF

systemctl enable fail2ban
systemctl restart fail2ban
sleep 3
systemctl is-active fail2ban
fail2ban-client status sshd
```

Resultado esperado:
```
Status for the jail: sshd
|- Filter
|  |- Journal matches: _SYSTEMD_UNIT=sshd.service + _COMM=sshd
```

### Adicionar dev novo (Felipe/Maíra/Luiz/Eliana)

**Opção A — chave em root (simples, mas todos viram root):**

```bash
ssh root@100.99.207.66 "
cat >> ~/.ssh/authorized_keys <<'EOF'
ssh-ed25519 AAAA... felipe@oimpresso
EOF
chmod 600 ~/.ssh/authorized_keys
"
```

**Opção B — usuário separado (recomendado pra audit per-dev):**

```bash
ssh root@100.99.207.66 "
useradd -m -s /bin/bash -G docker felipe
mkdir -p /home/felipe/.ssh
cat > /home/felipe/.ssh/authorized_keys <<'EOF'
ssh-ed25519 AAAA... felipe@oimpresso
EOF
chown -R felipe:felipe /home/felipe/.ssh
chmod 700 /home/felipe/.ssh
chmod 600 /home/felipe/.ssh/authorized_keys
"
```

`-G docker` permite Felipe rodar `docker ps` sem sudo. Pra cada login, journald registra `_SYSTEMD_USER=felipe` → audit per-dev.

### Caminho original — recovery via console Proxmox web

Quando todos os caminhos SSH falham (Tailscale down, LAN inacessível):

1. Browser: `https://177.74.67.30:8006`
2. Login Proxmox: `root@pam` (senha no Vaultwarden)
3. Painel esquerdo → `100 (docker-host)` → botão **Console**
4. Login Debian no console: `root` / <senha CT 100 do Vaultwarden>

**Por que precisa fazer manual via console pra bootstrap inicial:**
- Porta SSH 22 NÃO está exposta publicamente (TP-Link NAT só 443+8006)
- Proxmox API REST não tem `pct exec` — só `vncproxy`/`termproxy` (WebSocket interativo)
- Portainer API só roda exec dentro de containers Docker, não no host LXC

---

## Stack Docker em /opt/docker-host (subiu 2026-04-28)

- `compose.yml` versionado em [`infra/proxmox/docker-host/compose.yml`](../../infra/proxmox/docker-host/compose.yml)
- `.env` no CT (gitignored): `TRAEFIK_DASHBOARD_AUTH` + `ACME_EMAIL=wagnerra@gmail.com` + `MEILI_MASTER_KEY=<Vaultwarden>` + `VAULTWARDEN_SIGNUPS_ALLOWED=false`
- **Traefik 3.6** — Dashboard: `https://traefik.oimpresso.com/` BasicAuth (creds no Vaultwarden)
- **Portainer CE LTS 2.39.1** em `https://portainer.oimpresso.com/`
  - **Admin:** `admin` / senha no Vaultwarden (item `portainer-admin`)
  - **Endpoint Docker local** ID 1, nome `docker-host`, `unix:///var/run/docker.sock`
  - Se volume `portainer-data` perdido → reboot CT → POST /api/users/admin/init → readicionar endpoint
- **Vaultwarden 1.35.8-alpine** em `https://vault.oimpresso.com/`
  - Wagner criou conta: `wagnerra@gmail.com` / senha master (não documentar)
  - ADMIN_TOKEN: ver vaultwarden-credenciais.md
  - SIGNUPS: **false** (desabilitado após criação da conta)
- **Reverb (Laravel 8.4)** em `https://reverb.oimpresso.com/` (creds Vaultwarden)
- **Meilisearch v1.10.3** em `https://meilisearch.oimpresso.com/` (master key Vaultwarden)

---

## Padrão "compose-managed, Portainer-observed" (ADR 0053)

- Source-of-truth: `docker-compose.yml` versionado em git
- Deploy: `docker compose up -d` SSH'ado no CT 100 (**NÃO** Portainer Stacks)
- Portainer fica só pra UI logs/exec/restart

Razão: Portainer Stacks tem limitações conhecidas com compose-spec recente (`profiles:`, `extends:`, `develop:` etc).

---

## Receita Traefik labels (subdomínio + cert TLS automático)

```yaml
labels:
  - "traefik.enable=true"
  - "traefik.docker.network=docker-host_default"
  - "traefik.http.routers.X.rule=Host(`X.oimpresso.com`)"
  - "traefik.http.routers.X.entrypoints=websecure"
  - "traefik.http.routers.X.tls=true"
  - "traefik.http.routers.X.tls.certresolver=letsencrypt"
  - "traefik.http.services.X.loadbalancer.server.port=PORTA"
  # HTTP→HTTPS redirect
  - "traefik.http.routers.X-http.rule=Host(`X.oimpresso.com`)"
  - "traefik.http.routers.X-http.entrypoints=web"
  - "traefik.http.routers.X-http.middlewares=X-redirect"
  - "traefik.http.middlewares.X-redirect.redirectscheme.scheme=https"

networks:
  docker-host_default:
    external: true
```

---

## SSH tunnel ao Hostinger MySQL via autossh sidecar

Pattern em `docker/oimpresso-mcp/docker-compose.yml`:

```yaml
tunnel:
  image: kroniak/ssh-client:3.20
  command: >
    sh -c "apk add --no-cache autossh netcat-openbsd &&
      autossh -M 0
        -o ServerAliveInterval=30
        -o ServerAliveCountMax=3
        -o ExitOnForwardFailure=yes
        -N -L 0.0.0.0:3306:127.0.0.1:3306
        -p 65002 -i /root/.ssh/id_ed25519_oimpresso
        u906587222@148.135.133.115"
  volumes:
    - /opt/<servico>/ssh:/root/.ssh:ro
  healthcheck:
    test: ["CMD-SHELL", "nc -z localhost 3306"]
```

App principal usa `DB_HOST=tunnel`.

NOTA: pra daemon whatsapp-baileys, autossh foi rejeitado em prol de Remote MySQL direto — ver hostinger-remote-mysql.md.

---

## Troubleshooting comum

### Cert TRAEFIK DEFAULT (em vez de Let's Encrypt)
- Container não está com label `traefik.enable=true`
- Container não está em `docker-host_default` network
- DNS não propagou (verificar `nslookup mcp.oimpresso.com`)

### 504 Gateway Timeout do Traefik
- Container está em network errada — confirma com `docker inspect`
- Healthcheck do Traefik não passa — checa porta interna

### Tunnel SSH não conecta
- Pubkey não adicionada em `~/.ssh/authorized_keys` no Hostinger
- Permissão errada na chave (`chmod 600 ssh/id_ed25519_oimpresso`)
- Hostinger não aceita ed25519 (raro; testar com `-o PubkeyAcceptedKeyTypes=+ssh-rsa`)

---

## Credenciais críticas

**TODAS as senhas/tokens citados aqui estão no Vaultwarden (`vault.oimpresso.com`) como fonte canônica.** Esta auto-mem é cache de referência — valores reais NÃO ficam em git.

| Sistema | User | Vaultwarden item |
|---|---|---|
| Proxmox web | `root@pam` | `proxmox-root` |
| Proxmox API | `root@pam!mcp2` | `proxmox-api-token-mcp2` |
| CT 100 root | `root` | `ct100-root` |
| Portainer | `admin` | `portainer-admin` |
| Vaultwarden web | `wagnerra@gmail.com` | (master Wagner — não documentar) |
| Hostinger SSH | `u906587222` | (chave id_ed25519_oimpresso local) |
| Hostinger MySQL | `u906587222_oimpresso` | `hostinger-mysql-oimpresso` |

**Não commitar senhas em INFRA.md ou no repo.** INFRA.md tem só o método sem o valor.

---

## Refs cruzadas

- **ADR 0042** Infra empresa padrão · **ADR 0053** MCP server governança · **ADR 0058** Centrifugo > Reverb · **ADR 0062** Hostinger ≠ CT 100 (IRREVOGÁVEL)
- infra-rede-empresa.md — TP-Link NAT/DHCP/VoIP
- vaultwarden-credenciais.md — ADMIN_TOKEN
- hostinger.md — IP/porta/key
