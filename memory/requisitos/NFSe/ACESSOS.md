# NFSe — Checklist de acessos (Eliana[E])

> ⚠️ **Este arquivo NÃO contém senhas.** Lista o que Eliana precisa pedir ao Wagner pra liberar via [Vaultwarden](https://vault.oimpresso.com).
> Wagner libera item-a-item conforme Eliana avança nas tasks; não precisa abrir tudo de uma vez.

## Como Wagner libera acessos

Tudo passa por **Vaultwarden** (`vault.oimpresso.com`):

1. Wagner cria entry no cofre com nome padrão `<categoria>/<nome>` (ex.: `nfse/focusnfe-sandbox-token`)
2. Compartilha com a coleção `Eliana[E]` (Wagner cria 1×, depois é só add ao item)
3. Eliana entra em `vault.oimpresso.com` com Bitwarden client / extensão browser
4. Marca o acesso como ✅ recebido aqui neste arquivo (commit + push pelo `/sync-mem`)

**Senha master Vaultwarden Eliana** = primeira coisa a configurar (em pessoa com Wagner, não por mensagem).

---

## Sprint A — Pesquisa + setup (US-NFSE-001 a 003)

| # | Acesso | Onde | Quem libera | Status |
|---|---|---|---|---|
| 1 | **Vaultwarden** — conta Eliana[E] + master password | `vault.oimpresso.com` | Wagner (1×, em pessoa) | ⬜ |
| 2 | **GitHub** — collaborator `wagnerra23/oimpresso.com` (já deve ter) | github.com | Wagner | ⬜ |
| 3 | **MCP server token** — `mcp_eli_*` em `.claude/settings.local.json.example` | UI `/copiloto/admin/team` | Wagner gera | ⬜ |
| 4 | **Provider NFSe sandbox** (Focus NFe / NFE.io / SN-NFSe) — token + URL endpoint | Vaultwarden `nfse/<provider>-sandbox-token` | Wagner cria conta após US-NFSE-001 | ⬜ |
| 5 | **Dados fiscais oimpresso** — CNPJ, IE, IM, CNAE, regime tributário, código LC 116 | Vaultwarden `oimpresso/dados-fiscais` ou direto com contador | Wagner + contador | ⬜ |

## Sprint B — Backend (US-NFSE-004 a 007)

| # | Acesso | Onde | Quem libera | Status |
|---|---|---|---|---|
| 6 | **MySQL local Laragon** — root sem senha (default Herd/Laragon dev) | Local | — (já existe) | ⬜ |
| 7 | **MySQL prod (read-only)** — pra debug | Vaultwarden `hostinger/mysql-readonly` | Wagner cria user MySQL | ⬜ |
| 8 | **Anthropic API key** — pra usar Copiloto local | Vaultwarden `secrets/anthropic-api-key` | Wagner | ⬜ |

## Sprint C — UI (US-NFSE-008 a 010)

Sem novos acessos (frontend roda local com `npm run dev`).

## Sprint D — Validação + produção (US-NFSE-011 a 014)

| # | Acesso | Onde | Quem libera | Status |
|---|---|---|---|---|
| 9 | **Hostinger SSH** — chave `id_ed25519_oimpresso_eliana` | Vaultwarden `hostinger/ssh-key-eliana` | Wagner gera key + adiciona em `~/.ssh/authorized_keys` Hostinger | ⬜ |
| 10 | **Hostinger hPanel** — Google OAuth `eliana@oimpresso.com.br` ou guest user | Wagner adiciona como guest no hPanel (não compartilhar root) | Wagner | ⬜ |
| 11 | **Provider NFSe produção** — token + URL produção (DIFERENTE do sandbox) | Vaultwarden `nfse/<provider>-PROD-token` | Wagner gera após validar sandbox | ⬜ |
| 12 | **Certificado A1 oimpresso** — `.pfx` + senha do certificado | Vaultwarden `oimpresso/cert-a1-pfx` + arquivo encriptado | Wagner + contador (compra/renova) | ⬜ |
| 13 | **CT 100 Proxmox SSH** (opcional — pra rodar Pest test contra MCP real) | Tailscale + key `id_ed25519_eliana_ct100` | Wagner adiciona dev na CT 100 | ⬜ |
| 14 | **Vault Vaultwarden ADMIN_TOKEN** (NÃO precisa pra NFSe; só se tomar conta de cofre algum dia) | NÃO compartilhar | Wagner mantém | 🚫 |

---

## Acessos que Eliana NÃO precisa (não pedir)

- ❌ Vaultwarden ADMIN_TOKEN (item 14) — administra o próprio cofre, fora do escopo dela
- ❌ Proxmox `root@pam` — só Wagner; Eliana entra em CT 100 como user `dev` ou similar (item 13)
- ❌ TP-Link router empresa — fora do escopo
- ❌ KingHost (DNS wr2.com.br) — domínios de outro projeto
- ❌ DNS Hostinger (manipulação direta) — Wagner faz via API
- ❌ Anthropic billing — Wagner gerencia
- ❌ Acesso financeiro real do oimpresso (banco, conta) — fora do escopo dev

---

## Receitas rápidas

### Eliana validar que MCP server enxerga as mudanças dela

```bash
# Após push pra main:
curl -s -H "Authorization: Bearer $MCP_TOKEN" \
  https://mcp.oimpresso.com/api/mcp/tools/call \
  -d '{"name":"decisions-search","arguments":{"query":"NFSe"}}'
# Deve retornar SPEC + ADR + RUNBOOK + ACESSOS (este doc) que ela criou/leu
```

### Eliana testar SSH Hostinger pela primeira vez

```bash
# Warm-up obrigatório (Hostinger é flaky)
for i in 1 2 3 4 5; do curl -s -o /dev/null --max-time 15 https://oimpresso.com/login; done

# SSH com timeouts grandes
ssh -4 -o ConnectTimeout=900 -o ServerAliveInterval=3 \
    -i ~/.ssh/id_ed25519_oimpresso_eliana -p 65002 \
    u906587222@148.135.133.115 'whoami && pwd'
# Deve printar "u906587222" + "/home/u906587222"
```

### Eliana acessar Vaultwarden 1ª vez

```
1. Browser → https://vault.oimpresso.com
2. Email: eliana@oimpresso.com.br (ou o que Wagner cadastrar)
3. Master password: (em pessoa com Wagner)
4. Habilitar 2FA com app autenticador (Aegis / Google Auth)
5. Instalar Bitwarden extension no browser pra auto-fill
```

---

## Quando algo dá errado

| Sintoma | Solução |
|---|---|
| SSH Hostinger `Permission denied (publickey)` | Wagner não adicionou a key pública no `authorized_keys`. Pedir reabrir item 9 |
| MCP `Autenticação requerida` | Token `mcp_eli_*` expirado ou nunca foi gerado. Item 3 |
| Provider NFSe `401 Unauthorized` | Token sandbox/prod misturado. Conferir `.env` |
| Vaultwarden `Invalid token` | 2FA dessincronizou. Wagner reseta seed |
| Cert A1 `password incorrect` | Senha do `.pfx` está em campo separado no Vaultwarden item 12 |

---

## Refs

- INFRA.md (mapa de ambientes)
- [reference_vaultwarden_credenciais.md](../../claude/reference_vaultwarden_credenciais.md) (auto-mem)
- [reference_hostinger_ssh_credenciais.md](../../claude/reference_hostinger_ssh_credenciais.md)
- TEAM.md → Eliana[E]
- [SPEC.md](SPEC.md) §"Pré-requisitos fora do código"
