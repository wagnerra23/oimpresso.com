# RUNBOOK — SSH hardening em CT Proxmox (Debian 12 LXC)

> **Tipo:** runbook reproduzível
> **Aplicado em:** CT 100 (oimpresso) 2026-04-30
> **Refs:** ADR 0042 infra empresa, ADR 0060 Opção C2, ADR 0061 zero auto-mem

Receita pra hardenizar SSH em qualquer CT LXC Debian 12 do Proxmox empresa. Reproduzível em CT futuros (CT 102 mysql-primary, CT 103 backup, etc).

## Estado final esperado

| Origem | Endpoint | Auth | Status |
|---|---|---|---|
| LAN empresa | `ssh root@192.168.0.X` | chave SSH | ✅ |
| Tailscale | `ssh root@100.X.X.X` | Tailscale ACL + chave | ✅ |
| Internet pública | TP-Link NAT bloqueia | — | 🚫 |

## Pré-requisitos

- CT criado em Proxmox (LXC Debian 12)
- Acesso inicial via console Proxmox web (`https://177.74.67.30:8006`)
- Tailscale instalado no CT (`curl -fsSL https://tailscale.com/install.sh | sh && tailscale up`)
- SSH key Wagner já em `~/.ssh/authorized_keys` no CT

## Passos

### 1. Drop-in config (não modifica `/etc/ssh/sshd_config` original)

```bash
mkdir -p /etc/ssh/sshd_config.d

cat > /etc/ssh/sshd_config.d/oimpresso-hardening.conf <<'EOF'
# oimpresso CT hardening (ADR 0061)
# LAN access only (port 22 não exposto público no TP-Link)

# Auth: só chave (sem senha pra brute-force)
PasswordAuthentication no
PubkeyAuthentication yes
ChallengeResponseAuthentication no

# Root: permite só com chave
PermitRootLogin prohibit-password

# Limites brute-force / scan
MaxAuthTries 3
MaxSessions 10
LoginGraceTime 30

# Forwarding: precisa pra tunnels SSH
AllowTcpForwarding yes
GatewayPorts no
PermitTunnel no

# Idle timeout 30 min
ClientAliveInterval 300
ClientAliveCountMax 6

# Banner aviso
Banner /etc/issue.net
EOF

cat > /etc/issue.net <<'EOF'
=========================================================
oimpresso CT — acesso AUTORIZADO apenas
Logs em journald + mcp_audit_log
LGPD Art. 18 — tentativas indevidas serão investigadas
=========================================================
EOF
```

### 2. Validar antes de reload (CRITICAL)

```bash
# sshd -t testa config sem aplicar — se falhar, NÃO recarrega sshd → não te tranca fora
sshd -t && systemctl reload sshd
```

⚠️ **Se `sshd -t` falhar:** revise o conf antes de continuar. Não reinicie sshd com config inválida.

### 3. fail2ban com backend systemd (pegadinha LXC)

**Por quê backend=systemd:** Debian 12 LXC manda logs SSH pro **journald**, não pra `/var/log/auth.log`. Fail2ban padrão lê arquivo, não journald → falha com `ERROR Failed during configuration: Have not found any log file for sshd jail`.

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
systemctl is-active fail2ban && echo "✅ ativo"
fail2ban-client status sshd
```

Output esperado:
```
Status for the jail: sshd
|- Filter
|  |- Currently failed: 0
|  |- Total failed: 0
|  `- Journal matches: _SYSTEMD_UNIT=sshd.service + _COMM=sshd
`- Actions
   |- Currently banned: 0
```

### 4. Adicionar dev novo (Felipe/Maíra/Luiz/Eliana)

#### Opção A — chave em root (simples, mas todos viram root)

```bash
ssh root@<TAILSCALE_IP> "
cat >> ~/.ssh/authorized_keys <<'EOF'
ssh-ed25519 AAAA... felipe@oimpresso
EOF
chmod 600 ~/.ssh/authorized_keys
"
```

#### Opção B — usuário separado (recomendado pra audit per-dev)

```bash
ssh root@<TAILSCALE_IP> "
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

## Por que NÃO expor 22 público no TP-Link

- ✅ Tailscale resolve 100% (qualquer lugar do mundo, ACL granular per-machine)
- ✅ LAN empresa cobre Wagner/Felipe in-office
- ❌ Bots fazem 1000+ tentativas SSH/dia em portas 22 expostas — fail2ban segura mas é ruído
- ❌ Risco zero-day OpenSSH > benefício
- ✅ TP-Link já bloqueia (auto-mem deprecated `reference_router_empresa_port_forwards`)

## Validação pós-aplicação

```bash
# 1. SSH ainda funciona via Tailscale?
ssh root@<TAILSCALE_IP> "echo OK; hostname"

# 2. Senha está bloqueada (se mesma máquina tem ssh-agent OK)?
ssh -o PreferredAuthentications=password -o PubkeyAuthentication=no root@<TAILSCALE_IP> 2>&1 | grep -q "Permission denied" && echo "✅ senha bloqueada"

# 3. fail2ban está logando?
ssh root@<TAILSCALE_IP> "fail2ban-client status sshd | grep 'Total failed'"

# 4. sshd -t é OK?
ssh root@<TAILSCALE_IP> "sshd -t && echo '✅ config válida'"
```

## Troubleshooting

### `Permission denied (publickey)` após hardening

- Confirma chave Wagner ainda em `~/.ssh/authorized_keys`
- Permissões: `~/.ssh` 700, `~/.ssh/authorized_keys` 600, owner correto
- Acesso emergência: console Proxmox web → CT → Console → login root

### fail2ban service falhando

- Provável `backend = systemd` ausente. Conferir `cat /etc/fail2ban/jail.d/sshd.local`
- LXC capabilities: alguns CTs novos precisam `CAP_NET_ADMIN` pra `fail2ban` manipular nftables. Se persistir: `nesting=1` na config do CT no Proxmox host.

### Quero remover hardening (rollback)

```bash
rm /etc/ssh/sshd_config.d/oimpresso-hardening.conf
systemctl reload sshd
systemctl stop fail2ban && systemctl disable fail2ban
```

`/etc/ssh/sshd_config` original NUNCA foi tocado — drop-in pattern garante rollback limpo.

## Refs

- ADR 0042 — Infra empresa padrão
- ADR 0060 — Opção C2 híbrida
- ADR 0061 — Zero auto-mem privada (esta receita migrada da auto-mem deprecated)
- `INFRA.md` §6.2 — Estado atual CT 100

---

**Última atualização:** 2026-04-30
