---
name: Hostinger hPanel — login via Google OAuth
description: Como Wagner acessa o painel Hostinger (hPanel) — Sign-in with Google. Claude NÃO pode automatizar (OAuth interativo). Tarefas no painel são manuais por Wagner ou via API key
type: reference
originSessionId: 32066199-13c2-4cc8-922b-d65034040e23
---
**Painel:** https://hpanel.hostinger.com/
**Método de login:** **"Sign in with Google"** — Wagner usa a conta `wagnerra@gmail.com`

**Implicação operacional:**

- ❌ Claude **NÃO consegue logar via script/API** — OAuth Google requer browser + 2FA + intervenção manual
- ✅ Claude **PODE usar API key da Hostinger** se Wagner criar uma:
  - Hostinger → hPanel → conta → API Tokens (recente, pode não estar disponível em todos os planos)
  - Endpoint: `https://api.hostinger.com/`
  - Pode fazer DNS, Domains, Cloud Servers via REST autenticada por bearer token
- ✅ Wagner **PODE fazer mudanças manualmente** — Claude orienta passo a passo

**Tarefas que precisam Wagner manualmente:**

- Criar / editar / remover registros DNS (zonas A, CNAME, TXT, MX, etc.)
- Adicionar / remover usuários da conta
- Trocar plano de hospedagem
- Comprar / renovar domínio
- Configurar SSL diferente do Let's Encrypt automático

**Tarefas que Claude já automatiza:**

- SSH no servidor de produção (chave `id_ed25519_oimpresso` em `reference_hostinger_ssh_credenciais.md`)
- Deploy via SSH (`git pull`, `composer install`, `php artisan optimize:clear`)
- Patch de produção (ex.: WP /ajuda/ create_function fix)
- Backup mysqldump

## API token Hostinger ATIVO (criado 2026-04-28)

```
Authorization: Bearer g8JeEn9GsgBlVhsk9uSyxNBwaZpYRFk9zNdQj0Gm7ca72750
```

Wagner gerou e nomeou "Claude da hostiger". Permite:

- Listar zonas DNS: `GET https://developers.hostinger.com/api/dns/v1/zones/{domain}`
- Criar/atualizar registros: `PUT https://developers.hostinger.com/api/dns/v1/zones/{domain}` body com `overwrite=false` + array `zone`
- (provavelmente) outros endpoints: domínios, hospedagem, VPS — testar quando precisar

Validado em 2026-04-28: Claude listou zona oimpresso.com (vê A records `app/api`/`crm/doc/ia` etc.) e criou 4 novos A records (`reverb/portainer/traefik/vault`) → 177.74.67.30 com 1 chamada (response `Request accepted`). Propagação ~30s, todos resolvíveis via 1.1.1.1.

**Não commitar token no git.** Salvo só em auto-memória local.

**MFA:** o Google account do Wagner provavelmente tem 2FA — sem isso, qualquer um com a senha do Google entra. Manter MFA forte.
