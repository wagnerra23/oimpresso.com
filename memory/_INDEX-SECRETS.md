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
| **Hostinger DNS API token** | Bearer | `/root/.hostinger-api-token` CT 100 (fonte canônica) | `tailscale ssh root@ct100-mcp 'cat /root/.hostinger-api-token'` | ~anual | 🔴 **EXPIRED 2026-05-28** — Wagner regerar |
| **Hostinger SSH key (id_ed25519_oimpresso)** | SSH private key | `~/.ssh/id_ed25519_oimpresso` local (Wagner machine) | já configurado, agente usa `ssh -4 -i ~/.ssh/id_ed25519_oimpresso -p 65002 u906587222@148.135.133.115` | sob demanda (incident only) | ✅ active |
| **Hostinger MySQL credentials** | DB user/pass | `.env` do Hostinger (variáveis `DB_USERNAME` + `DB_PASSWORD`) | `ssh ... 'grep ^DB_ .env'` (já no padrão receita `memory/reference/hostinger.md`) | sob demanda | ✅ active |
| **Tailscale auth (CT 100 ct100-mcp)** | Tailscale ACL key | tailnet UI; `tailscale ssh` SÓ funciona de máquina **no mesmo tailnet** do CT 100 (`tail38e4d9`, dono `wagnerra@`) | `tailscale ssh root@ct100-mcp 'COMANDO'` | sem rotação (key não expira) | 🟡 **bloqueado cross-tailnet 2026-06-05** — máquina WR2 (`tailf7c41b`/`wr2backup@`) vê o CT 100 só via node-share → policy nega SSH root/dev/wagner (`tailnet policy does not permit you to SSH as user`). Fix: Wagner convida user no tailnet `tail38e4d9` OU usar LAN (linha abaixo) |
| **CT 100 root SSH (LAN — fallback canônico p/ testes)** | SSH via chave | host `192.168.0.50:22` (LAN empresa, **NÃO passa pelo Tailscale**) + chave `~/.ssh/id_ed25519_oimpresso` | `ssh -i ~/.ssh/id_ed25519_oimpresso root@192.168.0.50 'docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=X'` · ⚠️ só de dentro da LAN da empresa · ⚠️ fail2ban bane o IP após 3 falhas de auth (usar `IdentitiesOnly=yes` + chave certa; recomendado `ignoreip` da LAN) | sob demanda | ✅ active (verificado 2026-06-05 — conecta + `oimpresso-staging` Laravel 13.6 vivo) |
| **UltimatePOS superadmin (login "WR2")** | senha de login (god-mode cross-tenant) | hash bcrypt na tabela `users`; senha em claro → Vaultwarden item `ultimatepos-superadmin` (criar) | inacessível ao agente (humano-only — conta cross-tenant Tier 0) | sob demanda | 🟡 rotacionando 2026-06-08 (Wagner) — falta cadastrar no Vault |
| **MinIO root (CT 100 langfuse)** | Access key + secret | `/opt/langfuse/code/docker/langfuse/docker-compose.yml` env `MINIO_ROOT_USER` / `MINIO_ROOT_PASSWORD` (referenciado de `/opt/docker-host/.env`) | `tailscale ssh root@ct100-mcp 'grep MINIO_ROOT /opt/docker-host/.env'` | semestral | ✅ active |
| **MinIO user `oimpresso_*`** | Access key + secret | salvo Vaultwarden item `arquivos-minio-app-credentials` (criar item Sprint 0 + 0.4) | `get-secret.sh arquivos-minio-app-credentials` (após setup service account) | anual | 🟡 criado 2026-05-28 (ACCESS_KEY=oimpresso_0019f2a8669f) — **falta cadastrar no Vault** |
| **Vaultwarden ADMIN_TOKEN** | admin token | `/opt/docker-host/.env` env var `VAULTWARDEN_ADMIN_TOKEN` | `tailscale ssh root@ct100-mcp 'grep ^VAULTWARDEN_ADMIN_TOKEN /opt/docker-host/.env'` | sem rotação automática (só se vazar) | 🔴 **ROTACIONAR** — valor apareceu em transcript de sessão Claude 2026-07-12 (`docker inspect` sem redação). Wagner regerar token + `docker compose up -d vaultwarden` |
| **Vaultwarden service account `claude-agent`** | API key (client_id/secret) + master pass | credenciais em `/root/.vaultwarden-agent-creds` CT 100 (chmod 600); user no Vaultwarden `vault.oimpresso.com` | `tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh <slug>'` (mecanismo canônico — Opção B, `scripts/infra/get-secret.sh`) | anual (rotação da API key) | 🟡 **setup 1× pendente Wagner** (criar user + API key + colar 3 credenciais + compartilhar itens). Mecanismo (`bw` CLI + script) já pronto no CT 100 |
| **Vaultwarden user master password (Wagner)** | master password | **MEMÓRIA HUMANA Wagner** — papel físico backup; não tem cache CT 100 | inacessível ao agente (by design) | sem rotação (perdeu = perdeu, recovery via reset email) | 🔒 LOCKED humano-only |
| **Centrifugo HMAC + API key** | HMAC secret + API key | `/opt/centrifugo/config.json` CT 100 (vimos em incident 2026-05-28 fix `omnichannel` namespace) | `tailscale ssh root@ct100-mcp 'cat /opt/centrifugo/config.json'` | semestral | ✅ active |
| **Whatsmeow daemon HMAC** | HMAC pra webhook | Hostinger `.env` `WHATSAPP_WHATSMEOW_HMAC_SECRET` | `ssh ... 'grep WHATSMEOW_HMAC .env'` | semestral | ✅ active |
| **Whatsmeow daemon API key admin** | admin token global | Hostinger `.env` `WHATSAPP_WHATSMEOW_API_KEY` | `ssh ... 'grep WHATSMEOW_API .env'` | semestral | ✅ active |
| **WhatsApp Meta Cloud (Embedded Signup)** | OAuth client_id + client_secret + verify_token | Hostinger `.env` `META_*` (multi-tenant: per-business via `whatsapp_business_configs`) | `ssh ... 'grep ^META_ .env'` | per cliente — bot ADR 0202 | ⏸ Fase 2 implementação pendente |
| **Asaas API token** | Bearer per-business | `business_*_payment_gateway_credentials` table (multi-tenant DB) | SQL via Hostinger SSH | sob demanda cliente | ✅ active |
| **Sicoob API client credentials** | client_id + cert PFX | Hostinger `~/certificates/` + `business_payment_gateway_credentials` table | ADR 0193 + `memory/requisitos/PaymentGateway/RUNBOOK-sicoob-api.md` | per cliente | ✅ active |
| **Mailgun API key** | API key | Hostinger `.env` `MAIL_PASSWORD` | `ssh ... 'grep ^MAIL .env'` | sob demanda | ✅ active |
| **GitHub PAT (CI/Actions)** | PAT | GitHub repo Settings → Secrets (não acessível ao agente diretamente) | via Actions runtime ($GITHUB_TOKEN) ou Wagner gera novo | sob demanda | ✅ active |
| **COWORK_BOT_PAT (PAT conta wagnerra23 pra auto-PR que dispara CI)** | PAT (Actions secret) | GitHub repo Settings → Secrets → Actions, nome `COWORK_BOT_PAT` (valor só Wagner) | via Actions runtime `${{ secrets.COWORK_BOT_PAT }}` — usar quando workflow precisa criar PR/comment que DISPARE CI (anti-recursão: evento do `GITHUB_TOKEN` não dispara workflow `pull_request`). Consumidores: `shipped-log-cron`, `sdd-scorecard-publish`, `mv-metabolismo`, `screen-smoke-after-merge`, `jana-ragas-canary`, `visual-regression` (modo update) | sob demanda (última atualização 2026-06-18) | ✅ active |
| **GitHub `gh` CLI auth (Wagner local)** | OAuth token | `~/.config/gh/hosts.yml` Wagner local (Win) | já configurado; agente usa `gh` sem precisar | sob demanda | ✅ active |
| **Token MCP pessoal (`mcp.oimpresso.com`)** | Bearer `mcp_*` — **per-dev**, um por pessoa | `.claude/settings.local.json` em `mcpServers.oimpresso.headers.Authorization` (gitignored, **sem backup no git**) — é o cofre que os **hooks** leem (`brief-fetch-curl.mjs`, `cc-watcher/index.js`, `fluxo-sistema.mjs`). ⚠️ **Medido 2026-09-15 (cliente 2.1.257): esse bloco NÃO alimenta o cliente MCP** — quem conecta é o `.mcp.json`, que expande `${OIMPRESSO_MCP_TOKEN}` do **ambiente**; sem a variável o cliente cai em OAuth e o servidor responde 419 (PR #7366 · `MEMORY_TEAM_ONBOARDING.md` passo E). Gerado em `/copiloto/admin/team`. Template: [`.claude/settings.local.json.example`](../.claude/settings.local.json.example) · cofre de restauração: `~/.claude/oimpresso-local/mcp-settings.local.json` | **ler o token de OUTRO dev: não é acessível ao agente** (credencial pessoal). Mas **rotacionar o próprio é self-service por CLI** (Wave 22): `php artisan teammcp:token:rotate --token=<id> [--dry-run]` — revoke+issue atômico em transação, raw impresso 1× e nunca logado (ADR 0081). O `mcp:token:gerar` **só emite, não revoga**. Formato aceito pelo `readAuthHeader`: prefixo `Bearer mcp_` **e** sem a marca `COLE_SEU` | por pessoa / ao revogar acesso | ✅ **rotacionado 2026-09-15** — #4 → #30 (Wagner). Na mesma sessão revogados: os 5 órfãos `last_used=NUNCA` desde abril (#6 #7 #8 #9 #18), o #5 (uso único em 30/04), o #1 (WagnerLaptop) e o #24 (drift-sentinel — **já estava soft-deleted desde 17/06**; só o `revoked_at` foi preenchido, pra o audit fechar). **Vivos: 2** — #10 (DXT/Claude Desktop, uso 15/09) e #30. O #1 (WagnerLaptop, uso 12/08) foi revogado no mesmo dia, por decisão [W]. ⚠️ **contar ativos por `revoked_at` no query builder cru IGNORA SoftDeletes e superestima** — o critério é `revoked_at IS NULL AND deleted_at IS NULL`; e `McpTokenIssuer::revoke()` usa `find()`, então num token já soft-deleted ele retorna `false` (use `withTrashed()->revogar()`) · ⚠️ **Medido 2026-09-16 (decisão dos pares, a pedido de [W]):** **#10 vs #30 (Wagner)** e **#11 vs #21 (Maiara)** são **clientes CONCORRENTES, não sucessão — nenhum se revoga.** O discriminador **não é IP nem `user_agent`**: o `last_used_ip` dominante cobre **11 de 16** tokens (rede compartilhada entre pessoas distintas) e `user_agent` é `node` nos quatro. É o **`endpoint` do `mcp_audit_log`**: handshake-only (`initialize`/`tools/list`/`prompts/list`/`resources/list`, **zero `tools/call`**) = Claude Desktop/DXT; quem emite `tools/call` é o cliente que trabalha. **Wagner:** #10 handshake-only (8.992 req, 0 `tools/call`) × #30 trabalha (615 req · `brief-fetch`/`cycles-active`/`my-work`/`whats-active`/`tasks-detail`) — usados com **84s de diferença** em 16/09. **Maiara:** #21, o **MAIS NOVO**, é o handshake-only × #11, o mais velho, é quem trabalha (só `tools/call brief-fetch`, padrão do hook) — logo *"revogar o antigo"* mataria o token **útil** dela. ⚠️ **E o `Vivos: 2` acima é escopo WAGNER** (fato datado de 15/09, preservado): pelo critério canônico (`revoked_at IS NULL AND deleted_at IS NULL`) o total em 16/09 era **9** — #10 #11 #13 #20 #21 #22 #23 #29 #30 — **todos com `expires_at` NUNCA** (o piso de 180d do #7388 é forward-only). Número vivo: re-rode, `DB::table("mcp_tokens")->whereNull("revoked_at")->whereNull("deleted_at")->count()` no Hostinger · 🗑️ **Revogados 2026-09-16** (determinação [W]; executados por `McpTokenIssuer::revoke($id, 1)` — `revoked_at`/`revoked_by=1` + **soft-delete**, linha preservada, logo reversível limpando os dois campos): **#22** (Luiz) e **#23** (user 2 "Wagner 48"). **Evidência medida antes de revogar:** #22 parado há 20 dias — 940 chamadas no `mcp_audit_log`, **todas até 2026-08-27 15:11**; #23 com **2 chamadas na vida**, 5 segundos de intervalo em 17/06 (`tools/list` + `handoff-pending`, `curl/8.6.0`) — sonda única, não conta de serviço. ⚠️ **Consequência:** o MCP do Luiz responde 401 até ele gerar token novo (self-service: `/copiloto/admin/team` ou `teammcp:token:rotate`). ⚠️ **Como LER esses campos sem errar** (quase declarei o #22 ativo por isso): contagem em **JANELA** (`30d=188`) **não é recência** — um pico de 20 dias atrás enche o balde de 30d exatamente como uso diário faria. Pra *"está ocioso?"* o oráculo é **`max(ts)` do `mcp_audit_log` contra o `now()` do servidor** (ou o `last_used_at`); o balde responde **volume**, nunca recência. **Vivos após a revogação: 7** em 16/09 11:02 (hora do servidor) — #10 #11 #13 #20 #21 #29 #30; número vivo, re-rode o comando acima · 🔒 cada dev gera/rotaciona o seu |
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

**Nota 2026-06-07:** o legado `memory/claude/` (que tinha tokens literais commitados, ex `reference_hostinger_hpanel.md`) foi PURGADO na auditoria de conflitos 2026-06-07. Segredos vivem só em CT100/Vault/.env. Os que estavam em claro foram catalogados como comprometidos para rotação (Wagner).

### Como atualizar este índice

Toda vez que:
- Cria nova integração com secret → adiciona linha aqui ANTES de commit
- Rotaciona secret → atualiza coluna Status + data
- Descobre secret órfão sem dono → adiciona com status `🟡 warning`
- Wagner regera token expirado → atualiza ponteiro + status 🔴 → ✅

PR title sugerido: `chore(secrets): rotaciona <secret> 2026-MM-DD`.

## Mecanismo de leitura canônico — `get-secret.sh` (Opção B)

Depois de achar o ponteiro aqui, o agente lê o valor via **`get-secret.sh`** (Vaultwarden service account `claude-agent`) — sem manusear valor no chat, sem escalar pro Wagner:

```bash
tailscale ssh root@ct100-mcp '/root/bin/get-secret.sh <slug>'    # fonte: scripts/infra/get-secret.sh
```

Setup 1× (SÓ Wagner): criar user `claude-agent` + API key + colar credenciais em `/root/.vaultwarden-agent-creds` (chmod 600) + compartilhar itens. Detalhe em [INFRA-ACESSO-CANON.md §Secrets](reference/INFRA-ACESSO-CANON.md) e skill `hostinger-dns-autonomy` (Path 1). Enquanto não configurado, os ponteiros `.env`/CT 100-file da tabela continuam sendo os fallbacks diretos.

## Skill enforcement

Skill Tier A `memory-first-secret-search` (criar pós este PR) força agente a:

1. `grep` ou Read deste arquivo ANTES de qualquer busca por secret
2. Se status `EXPIRED` ou `LOCKED` → registra como rotação/gap + propõe ADR
3. Se ponteiro existe → segue pra fonte (CT 100 ssh, ssh Hostinger, grep .env)
4. NÃO escala Wagner se ponteiro indica caminho automatizável

## Refs

- `memory/proibicoes.md` — regra Tier 0 enforcement
- `.claude/skills/memory-first-secret-search/SKILL.md` (criar) — bloqueador agente
- `.claude/skills/hostinger-dns-autonomy/SKILL.md` — Path 1 = `get-secret.sh` (service account Vaultwarden)
- `scripts/infra/get-secret.sh` — mecanismo canônico de leitura via `bw` CLI (Opção B, Tier 0 gap 2026-05-28)
- ADR 0044 (Vaultwarden self-hosted), ADR 0061 (zero auto-mem privada), ADR 0131 (tiering memória)
- Falha origem: 2026-05-28 18:30 incident agente declarou Tier 0 gap falsamente
