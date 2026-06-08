---
name: Vaultwarden — cofre self-hosted no docker-host
description: ADMIN_TOKEN e instruções de 1ª subida do Vaultwarden no CT 100. Vault em https://vault.oimpresso.com. Token real no próprio Vaultwarden (auto-referência) + cache local oimpresso-local
type: reference
---
**Container:** `vaultwarden` em CT 100 docker-host (192.168.0.50)
**Image:** `vaultwarden/server:1.35.8-alpine`
**Painel admin:** `https://vault.oimpresso.com/admin` ou `http://192.168.0.50:8200/admin` (LAN)
**ADR:** [0044](../decisions/0044-vaultwarden-self-hosted-cofre.md)

## ADMIN_TOKEN (acesso ao painel /admin)

**Valor não documentado em git canon** — vive em:
- Vaultwarden item self-referenciado (`vaultwarden-admin-token`) — Wagner logado pode ver
- `~/.claude/oimpresso-local/vault-refs.md` (cache local pessoal, gitignored per ADR 0131)

Plain text — Vaultwarden 1.35 ainda aceita mas avisa. Migrar pra Argon2 PHC numa segunda iteração:

```bash
ssh root@192.168.0.50 'docker exec vaultwarden vaultwarden hash --preset owasp5'
# Cola a string $argon2id$... no .env como VAULTWARDEN_ADMIN_TOKEN
docker compose up -d vaultwarden
```

## 1ª Subida (Wagner faz uma vez)

1. Acessar `http://192.168.0.50:8200/` (LAN, sem TLS válido — porta exposta temporariamente)
2. Clicar **"Create Account"** (SIGNUPS_ALLOWED=true ainda ativo)
3. **Master password forte** (16+ chars, anotar em papel — perdeu = perdeu)
4. Criar account (Wagner), confirmar email se SMTP estiver configurado
5. Após confirmar:
   - SSH no CT: `ssh root@192.168.0.50`
   - `cd /opt/docker-host && sed -i 's/VAULTWARDEN_SIGNUPS_ALLOWED=true/VAULTWARDEN_SIGNUPS_ALLOWED=false/' .env`
   - `docker compose up -d vaultwarden` — recria com cadastro fechado
6. **Remover porta 8200 temp do compose** quando DNS funcionar

## Pra equipe (Felipe, Maíra, Luiz, Eliana)

Após Wagner ter conta:
- Wagner acessa painel `/admin` com ADMIN_TOKEN
- Aba "Invitations" → manda invite pelo e-mail (precisa SMTP configurado — pendência)
- OU envia `https://vault.oimpresso.com/#/register?invitation=...` link manual

## Backup

Volume Docker `vaultwarden-data` persiste em LVM do Proxmox. Snapshot Proxmox cobre.

Backup adicional manual:
```bash
ssh root@192.168.0.50 'docker run --rm -v vaultwarden-data:/data -v /tmp:/backup alpine \
  tar czf /backup/vault-$(date +%Y%m%d).tar.gz -C /data .' && \
scp root@192.168.0.50:/tmp/vault-*.tar.gz ~/backups/
```

## Migrar credenciais existentes pra cá

Senhas hoje em reference em git canon (Proxmox, Traefik dashboard, Hostinger MySQL, etc.) devem ser **migradas pro Vaultwarden** com organizations:

- **Infra (org)** — Proxmox, CT 100 root, Traefik, Hostinger SSH+hPanel, KingHost web
- **App credentials (org)** — OPENAI_API_KEY, REVERB_APP_SECRET, MEILI_MASTER_KEY
- **Pessoal Wagner** — fora-do-projeto

Após migração, **rotacionar** as senhas que apareceram em chat anteriormente e atualizar Vaultwarden.
