# Onboarding Claude — Time oimpresso

> **Pra quem:** dev novo (Felipe / Maíra / Luiz / Eliana) ou Wagner adicionando alguém.
>
> **Pra quê:** conectar Claude ao MCP server do time — acesso a ADRs, session logs, memória semântica, estado do cycle, custo per-user.
>
> **Time usa Claude Desktop (app).** Claude Code CLI é secundário.

---

## TL;DR — 2 passos (Claude Desktop)

| # | Quem faz | O que |
|---|---|---|
| 1 | **Wagner** | Gera DXT em `/copiloto/admin/team` → entrega `.dxt` via Vaultwarden |
| 2 | **Dev novo** | Abre Claude Desktop → arrasta o `.dxt` na janela → reinicia |

Tempo total: **2 minutos por dev**.

---

## Como Wagner gera o DXT

### Via artisan (recomendado — gera token + DXT num comando)
```bash
# SSH no servidor
ssh hostinger
cd ~/domains/oimpresso.com/public_html
php artisan copiloto:mcp:generate-dxt --user-email=eliana@oimpresso.com.br
# → storage/app/dxt/oimpresso-mcp-eliana.dxt
# → Token novo gerado e gravado no DB
```

### Via script local (quando repo está em D:\oimpresso.com)
```bash
# 1. Pegue o token em oimpresso.com/copiloto/admin/team
# 2. Rode:
node scripts/generate-dxt.js --name="Eliana" --token="mcp_xxx..."
# → ./dxt/oimpresso-mcp-eliana.dxt

# Para gerar todos de uma vez:
cp scripts/tokens.json.example scripts/tokens.json
# edite tokens.json com os tokens reais (NÃO commitar!)
node scripts/generate-dxt.js --all
# → ./dxt/oimpresso-mcp-*.dxt  (1 por membro)
```

### Entregar o arquivo
- ✅ **Vaultwarden** (`vault.oimpresso.com`) — recomendado
- ✅ WhatsApp criptografado
- ❌ Email plain, Slack público, SMS

---

## Como o membro do time instala (Claude Desktop)

1. Baixar o `.dxt` do Vaultwarden
2. Abrir **Claude Desktop**
3. Ir em **Settings → Extensions** → clicar **Install from file** → selecionar o `.dxt`
   — OU simplesmente **arrastar o `.dxt` para a janela do Claude Desktop**
4. Reiniciar o Claude Desktop (1×)
5. Testar: perguntar `qual o estado do cycle?` — deve retornar tabela com tasks

> ⚠️ **Sempre abrir o projeto em `D:\oimpresso.com`** (não em worktrees).
> Se Claude Desktop abrir num worktree, feche e abra na pasta principal.

---

---

## Passo 1 — Wagner gera token MCP pro dev

### Via UI (recomendado)
1. Acessa `https://oimpresso.com/copiloto/admin/team`
2. Encontra o nome do dev na tabela (Felipe/Maíra/Luiz/Eliana)
3. Clica `+ Token`
4. Modal mostra `mcp_xxxx...` (apenas 1 vez!)
5. Copia
6. (Opcional) Define quota daily/monthly em R$ no botão ⚙️

### Via cmd (alternativa)
```bash
ssh hostinger
cd ~/domains/oimpresso.com/public_html
php artisan copiloto:mcp:gerar-token --user-email=felipe@oimpresso.com.br
```

Ambos modos:
- Token raw mostrado **1 vez só** (depois apenas hash fica no DB)
- Token vive até revogar manualmente
- Sem TTL automático

### Entregar token via canal seguro

✅ **Vaultwarden** (recomendado — `vault.oimpresso.com`)
✅ Sinal/WhatsApp criptografado
❌ Email plain
❌ Slack público
❌ SMS

---

## Passo 2 — Dev configura `.claude/settings.local.json`

### A. Clona o repo
```bash
git clone https://github.com/wagnerra23/oimpresso.com
cd oimpresso.com
```

### B. Copia o template
```bash
cp .claude/settings.local.json.example .claude/settings.local.json
```

### C. Edita o arquivo
Abre `.claude/settings.local.json` e troca `mcp_COLE_SEU_TOKEN_AQUI` pelo token real do Vaultwarden.

```json
{
  "mcpServers": {
    "oimpresso": {
      "url": "https://mcp.oimpresso.com/api/mcp",
      "headers": {
        "Authorization": "Bearer mcp_xxxxx_seu_token_xxxxx"
      }
    }
  }
}
```

### D. Verificar gitignore (segurança)
```bash
git status .claude/settings.local.json
# Deve aparecer "ignored" ou não listar — gitignore já cobre
```

⚠️ **NUNCA commit esse arquivo.** Tem token de acesso pessoal.

---

## Passo 3 — Abre Claude Code

```bash
claude
# ou: claude code
```

Na 1ª abertura, Claude Code:
1. Lê `.mcp.json` do repo (config oficial dos servidores)
2. Lê `.claude/settings.local.json` (seu token pessoal)
3. Aprova o servidor "oimpresso" (1×)
4. Carrega skills auto-ativáveis: `multi-tenant-patterns`, `publication-policy`, `oimpresso-team-onboarding`

Se a skill `oimpresso-team-onboarding` detectar problema (token errado, MCP não respondendo, etc), ela **te guia automaticamente** sem você precisar saber comandos.

### Validação rápida
Pergunte ao Claude Code: *"qual o estado do cycle atual?"*

Se retornar uma tabela com tasks Active/On-deck → **conectado**.
Se disser "não tenho acesso" → algo errado, ver Troubleshooting.

---

## O que o time inteiro tem disponível

### Tools MCP (compartilhadas)

| Tool | Pra quê |
|---|---|
| `tasks-current` | Estado vivo do cycle (CURRENT.md) |
| `decisions-search` | Buscar nas 56 ADRs do projeto |
| `decisions-fetch` | Ler 1 ADR completa |
| `sessions-recent` | Últimos session logs (cronológico) |
| `memoria-search` | Memória persistente do Copiloto chat (fatos do business) |
| `claude-code-usage-self` | Quanto eu consumi de tokens/R$ esse período |

### Resources

| URI | Conteúdo |
|---|---|
| `oimpresso://memory/handoff` | Estado canônico mais recente |
| `oimpresso://memory/current` | Cycle/sprint ativo |

### Prompts

| Prompt | Pra quê |
|---|---|
| `briefing-oimpresso` | Primer compacto do projeto (~300 tokens) — usar 1× ao começar sessão nova |

### Skills auto-ativáveis (do repo)

| Skill | Quando ativa |
|---|---|
| `multi-tenant-patterns` | Mexendo em código que toca `business_id` |
| `publication-policy` | Antes de git push, abertura de PR, deploy prod |
| `oimpresso-team-onboarding` | MCP não responde ou setup novo |

---

## Quotas e governança

Wagner pode definir spend caps **per-dev**:
- **Daily:** R$ X/dia em consumo MCP (default sem cap)
- **Monthly:** R$ Y/mês

Quando atinge:
- 50%: alerta low (logged)
- 80%: alerta medium (notificação dashboard)
- 100%: HTTP 429 do MCP server (calls bloqueadas até reset)

Vê seu próprio uso: `claude-code-usage-self` MCP tool.

---

## Memórias locais → Servidor (Sprint B em desenvolvimento)

Suas sessões locais Claude Code (`~/.claude/projects/*.jsonl`) **ainda não vão automático** pro servidor. Pra isso, em breve teremos o **watcher Node** (sprint B):

```bash
cd scripts/cc-watcher
npm install
npm start  # daemon em background
# ou: serviço Windows/Linux/macOS
```

Resultado: o que você fizer no Claude Code (todas suas sessões — pesquisa, edição, debug) fica buscável pelo time via tool `cc-search`. Quando Felipe precisar resolver algo que Wagner já resolveu antes, ele pergunta `cc-search "telescope crash 504"` e acha a sessão original.

**Status hoje:** schema pronto, watcher pendente. Acompanhe TASKS.md.

---

## Troubleshooting

### "MCP server não responde"
```bash
# Verifica DNS
nslookup mcp.oimpresso.com

# Verifica HTTPS
curl -fsS https://mcp.oimpresso.com/api/mcp/health

# Esperado: {"status":"ok","service":"oimpresso-mcp",...}
```

Se falhar: avisar Wagner — pode ser CT 100 Proxmox com problema.

### "HTTP 401 Unauthorized"
Token errado ou expirado. Pede novo no `/copiloto/admin/team`.

### "HTTP 403 Forbidden — no_permission"
Token OK mas user não tem `copiloto.mcp.use`. Wagner precisa atribuir:
```bash
ssh hostinger
cd ~/domains/oimpresso.com/public_html
php artisan tinker
> $user = App\User::where('email', 'felipe@...')->first();
> $user->givePermissionTo('copiloto.mcp.use');
```

### "HTTP 429 Quota Exceeded"
Você atingiu a quota diária/mensal. Espera o reset (00:00 BRT diário, dia 1 mensal) ou pede pro Wagner aumentar em `/copiloto/admin/team`.

### "Tool 'memoria-search' não aparece"
Reinicia o Claude Code (Ctrl+C + abre de novo). Pode ser cache do client.

### "Não vejo session logs do Wagner"
Ainda não temos watcher (Sprint B). Por enquanto, sessions logs canônicas estão em `memory/sessions/*.md` (commitados). Use `sessions-recent` MCP.

---

## Privacidade e segurança

- **Token é PESSOAL.** Nunca compartilhe. Se vazar, Wagner revoga em `/copiloto/admin/team`.
- **Toda call MCP é auditada** em `mcp_audit_log` (Wagner pode ver quem fez o quê).
- **LGPD:** dado fica no Brasil (Hostinger). Pra "esquecer-me", Wagner roda hard-delete (cycle 02).
- **Cross-tenant safety:** seu token só vê dados do seu business_id (a menos que Wagner te dê role superadmin).

---

## Referências

- [ADR 0053 — MCP server governança como produto](memory/decisions/0053-mcp-server-governanca-como-produto.md)
- [ADR 0055 — Self-host equivalente Anthropic Team plan](memory/decisions/0055-self-host-team-plan-equivalente-anthropic.md)
- [ADR 0056 — MCP fonte única de memória](memory/decisions/0056-mcp-fonte-unica-memoria-copiloto-claude-code.md)
- [CLAUDE.md](CLAUDE.md) — primer geral
- [TEAM.md](TEAM.md) — perfis e WIP

---

**Última atualização:** 2026-04-29
