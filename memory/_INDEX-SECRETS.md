---
name: _INDEX-SECRETS
description: Índice canon ÚNICO de TODOS os secrets/credenciais do projeto oimpresso. Agente DEVE consultar PRIMEIRO antes de qualquer busca por token/API key/password/SSH key. Não duplica valores — só ponteiros (path/Vault item/CT 100 file). Atualizado quando rotacionar secret OU adicionar nova integração.
type: index
created: 2026-05-28
owners: [wagner]
lifecycle: active
---

# Índice canon de Secrets & Credenciais — oimpresso

> ⛔ **REGRA Tier 0**: agente DEVE consultar este índice PRIMEIRO antes de qualquer busca por secret. Pular = violação skill `memory-first-secret-search` Tier A.
>
> Origem: falha 2026-05-28 — agente declarou Tier 0 gap "token Hostinger inacessível" sem ter pesquisado memory canon. Token estava em `memory/claude/reference_hostinger_hpanel.md:37` desde 2026-04-28. Wagner cobrou "tem api da hostinger na memoria".
>
> **NÃO duplica valores** — só ponteiros (paths, Vault item slugs, CT 100 files). Quem precisa do valor: lê o ponteiro + acessa fonte.

## Tabela canon (uma linha por secret)

| Nome | Tipo | Onde está (canon) | Como acessar agente | Frequência rotação | Status |
|---|---|---|---|---|---|
| **Hostinger DNS API token** | Bearer | `memory/claude/reference_hostinger_hpanel.md:37` E `/root/.hostinger-api-token` CT 100 (espelho) | `grep "Authorization: Bearer" memory/claude/reference_hostinger_hpanel.md` | ~anual | 🔴 **EXPIRED 2026-05-28** — Wagner regerar |
| **Hostinger SSH key (id_ed25519_oimpresso)** | SSH private key | `~/.ssh/id_ed25519_oimpresso` local (Wagner machine) | já configurado, agente usa `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` | sob demanda (incident only) | ✅ active |
| **Hostinger MySQL credentials** | DB user/pass | `.env` do Hostinger (variáveis `DB_USERNAME` + `DB_PASSWORD`) | `ssh ... 'grep ^DB_ .env'` (já no padrão receita `memory/reference/hostinger.md`) | sob demanda | ✅ active |
| **Tailscale auth (CT 100 ct100-mcp)** | Tailscale ACL key | gerenciado via tailnet UI; agente usa `tailscale ssh` com auth existente | `tailscale ssh root@ct100-mcp 'COMANDO'` | sem rotação (key não expira) | ✅ active |
| **MinIO root (CT 100 langfuse)** | Access key + secret | `/opt/langfuse/code/docker/langfuse/docker-compose.yml` env `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` (referenciado de `/opt/docker-host/.env`) | `tailscale ssh root@ct100-mcp 'grep MINIO_ROOT /opt/docker-host/.env'` | semestral | ✅ active |
| **MinIO user `oimpresso_*`** | Access key + secret | salvo Vaultwarden item `arquivos-minio-app-credentials` (criar item Sprint 0 + 0.4) | Vaultwarden API user-level (futuro skill `secret-vaultwarden`) | anual | 🟡 criado 2026-05-28 (ACCESS_KEY=oimpresso_0019f2a8669f) — **falta cadastrar no Vault** |
| **Vaultwarden ADMIN_TOKEN** | admin token | `/opt/docker-host/.env` env var `VAULTWARDEN_ADMIN_TOKEN` | `tailscale ssh root@ct100-mcp 'grep ^VAULTWARDEN_ADMIN_TOKEN /opt/docker-host/.env'` | sem rotação automática (só se vazar) | ✅ active |
| **Vaultwarden user master password (Wagner)** | master password | **MEMÓRIA HUMANA Wagner** — papel físico backup; não tem cache CT 100 | inacessível ao agente (by design) | sem rotação (perdeu = perdeu, recovery via reset email) | 🔒 LOCKED humano-only |
| **Centrifugo HMAC + API key** | HMAC secret + API key | `/opt/centrifugo/config.json` CT 100 (vimos em incident 2026-05-28 fix `omnichannel` namespace) | `tailscale ssh root@ct100-mcp 'cat /opt/centrifugo/config.json'` | semestral | ✅ active |
| **Whatsmeow daemon HMAC** | HMAC pra webhook | Hostinger `.env` `WHATSAPP_WHATSMEOW_HMAC_SECRET` | `ssh ... 'grep WHATSMEOW_HMAC .env'` | semestral | ✅ active |
| **Whatsmeow daemon API key admin** | admin token global | Hostinger `.env` `WHATSAPP_WHATSMEOW_API_KEY` | `ssh ... 'grep WHATSMEOW_API .env'` | semestral | ✅ active |
| **WhatsApp Meta Cloud (Embedded Signup)** | OAuth client_id + client_secret + verify_token | Hostinger `.env` `META_*` (multi-tenant: per-business via `whatsapp_business_configs`) | `ssh ... 'grep ^META_ .env'` | per cliente — bot ADR 0202 | ⏸ Fase 2 implementação pendente |
| **Asaas API token** | Bearer per-business | `business_*_payment_gateway_credentials` table (multi-tenant DB) | SQL via Hostinger SSH | sob demanda cliente | ✅ active |
| **Sicoob API client credentials** | client_id + cert PFX | Hostinger `~/certificates/` + `business_payment_gateway_credentials` table | ADR 0193 + `memory/requisitos/PaymentGateway/RUNBOOK-sicoob-api.md` | per cliente | ✅ active |
| **Mailgun API key** | API key | Hostinger `.env` `MAIL_PASSWORD` | `ssh ... 'grep ^MAIL .env'` | sob demanda | ✅ active |
| **GitHub PAT (CI/Actions)** | PAT | GitHub repo Settings → Secrets (não acessível ao agente diretamente) | via Actions runtime ($GITHUB_TOKEN) ou Wagner gera novo | sob demanda | ✅ active |
| **GitHub `gh` CLI auth (Wagner local)** | OAuth token | `~/.config/gh/hosts.yml` Wagner local (Win) | já configurado; agente usa `gh` sem precisar | sob demanda | ✅ active |
| **Anthropic API key (Claude API)** | API key | Hostinger `.env` `ANTHROPIC_API_KEY` | `ssh ... 'grep ANTHROPIC .env'` | semestral | ✅ active |
| **OpenAI API key (Jana Brain B fallback)** | API key | Hostinger `.env` `OPENAI_API_KEY` | `ssh ... 'grep OPENAI .env'` | semestral | ✅ active |
| **Langfuse keys (LLM observability)** | public + secret | CT 100 `langfuse.oimpresso.com` user account + Hostinger `.env` `LANGFUSE_*` | dashboard Langfuse + `.env` grep | semestral | ✅ active |
| **Meilisearch master key** | Bearer (master key) | canon: env `MEILI_MASTER_KEY` no host Meilisearch (`meilisearch.oimpresso.com` / `127.0.0.1:7700`). ⚠️ VAZADA em git history (só ponteiro, NÃO copiar valor) — exemplos `curl PATCH .../settings/embedders` em `memory/sessions/2026-04-27-sprints-5-6-mcp-claude-desktop-revisao.md:98`, `memory/handoffs/2026-05-10-2230-cycle-higiene-pivot-fsm.md:1303` e `:1476` (2 chaves distintas) | rotacionar no host + `grep MEILI_MASTER_KEY .env` | sob demanda | 🔴 **COMPROMETIDA 2026-05-28** — em git history (append-only, não removível), tratar como comprometida e **ROTACIONAR** (Wagner). Catalogada aqui pra destravar `secrets:scan` (ADR 0215, drift fonte→índice). |

## Convenções

### Status canônicos

- ✅ **active** — funciona, em uso
- 🟡 **warning** — funciona mas falta documentação/setup secundário (ex: criado mas falta cadastrar no Vault)
- 🔴 **EXPIRED** — secret rotacionado/revogado, precisa renovar — Wagner action required
- 🔒 **LOCKED humano-only** — by design não acessível ao agente (ex: master password Vault)
- ⏸ **pending** — secret previsto mas integração ainda não implementada

### Onde NÃO documentar secrets

❌ NUNCA commitar valores reais de secret em git (LGPD + security).

✅ Sempre documentar **ponteiro** pra fonte:
- Path do arquivo `.env`
- Item Vaultwarden slug
- Path CT 100 chmod 600

**Exceção histórica:** `memory/claude/reference_hostinger_hpanel.md` tem token literal (commitado em 2026-04-28). Foi erro mas o token expirou. Próxima rotação Wagner deve usar padrão correto (só ponteiro).

### Como atualizar este índice

Toda vez que:
- Cria nova integração com secret → adiciona linha aqui ANTES de commit
- Rotaciona secret → atualiza coluna Status + data
- Descobre secret órfão sem dono → adiciona com status `🟡 warning`
- Wagner regera token expirado → atualiza ponteiro + status 🔴 → ✅

PR title sugerido: `chore(secrets): rotaciona <secret> 2026-MM-DD`.

## Skill enforcement

Skill Tier A `memory-first-secret-search` (criar pós este PR) força agente a:

1. `grep` ou Read deste arquivo ANTES de qualquer busca por secret
2. Se status `EXPIRED` ou `LOCKED` → registra como rotação/gap + propõe ADR
3. Se ponteiro existe → segue pra fonte (CT 100 ssh, ssh Hostinger, grep .env)
4. NÃO escala Wagner se ponteiro indica caminho automatizável

## Refs

- `memory/proibicoes.md` — regra Tier 0 enforcement
- `.claude/skills/memory-first-secret-search/SKILL.md` (criar) — bloqueador agente
- `.claude/skills/hostinger-dns-autonomy/SKILL.md` — Path 0 atualizado pra consultar este índice
- ADR 0044 (Vaultwarden self-hosted), ADR 0061 (zero auto-mem privada), ADR 0131 (tiering memória)
- Falha origem: 2026-05-28 18:30 incident agente declarou Tier 0 gap falsamente
