---
name: SSH hardening CT 100 + fail2ban (LXC Debian 12)
description: Receita aplicada 2026-04-30 — SSH key-only + fail2ban journald backend + Tailscale + LAN access; SEM internet pública. Reproduzir em outros CTs.
type: reference
originSessionId: d0c86329-6176-4940-860b-8f1d166e2ad7
---
## Estado final SSH CT 100

| Origem | Endpoint | Auth | Status |
|---|---|---|---|
| LAN empresa | `ssh root@192.168.0.50` | chave SSH (LAN) | ✅ |
| Tailscale | `ssh root@100.99.207.66` | Tailscale ACL + chave | ✅ |
| Internet pública | TP-Link NAT bloqueia | — | 🚫 |

## Receita aplicada (cole-e-roda em outros CTs)

### 1. Drop-in config (não modifica `/etc/ssh/sshd_config` original)

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

### 2. fail2ban com backend systemd (Debian 12 LXC NÃO tem `/var/log/auth.log`)

**Pegadinha:** install padrão do fail2ban tenta ler `/var/log/auth.log` que não existe em LXC moderno (logs vão pro journald). Erro típico: `ERROR Failed during configuration: Have not found any log file for sshd jail`.

Solução: forçar `backend = systemd`:

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

## Adicionar dev novo (Felipe/Maíra/Luiz/Eliana)

### Opção A — chave em root (simples, mas todos viram root)

```bash
ssh root@100.99.207.66 "
cat >> ~/.ssh/authorized_keys <<'EOF'
ssh-ed25519 AAAA... felipe@oimpresso
EOF
chmod 600 ~/.ssh/authorized_keys
"
```

### Opção B — usuário separado (recomendado pra audit per-dev)

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

## Por que NÃO expor 22 público

- Tailscale resolve 100% (qualquer lugar do mundo, ACL granular)
- LAN empresa cobre Wagner/Felipe in-office
- Bots fazem 1000+ tentativas SSH/dia em portas 22 expostas — fail2ban segura mas é ruído
- Risco de zero-day OpenSSH > benefício
- TP-Link já bloqueia (auto-mem `reference_router_empresa_port_forwards.md`)

## Refs cruzadas (MCP)

- ADR 0042 — Infra empresa padrão
- Auto-mem `reference_proxmox_acesso_2026_04_29.md` — caminho original (só Proxmox web console)
- Auto-mem `reference_router_empresa_port_forwards.md` — TP-Link NAT 443+8006 only
- Auto-mem `reference_proxmox_credenciais.md` — root@pam senha
