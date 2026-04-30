---
name: oimpresso-team-onboarding
description: Configura ou valida acesso ao MCP server da empresa oimpresso (Wagner/Felipe/Maíra/Luiz/Eliana). Ativa quando dev novo abre Claude Code pela 1ª vez no projeto, ou quando user pede "setup MCP", "configurar acesso à memória do time", "entrar no MCP server oimpresso", "conectar Claude Code ao oimpresso". Também guia Wagner pra adicionar dev novo ao time. Gera/valida `.claude/settings.local.json` automaticamente.
allowed-tools: Read, Write, Edit, Bash, WebFetch, mcp__oimpresso__*
---

# Skill — Onboarding Claude Code do time oimpresso

> **Quando ativar:** dev abre Claude Code no repo `D:\oimpresso.com` (ou clone) e o MCP server "oimpresso" não está respondendo, ou o user pede explicitamente "configurar MCP/memória" / "setup oimpresso".

## Contexto canônico

- **MCP server URL:** `https://mcp.oimpresso.com/api/mcp`
- **Admin console:** `https://oimpresso.com/copiloto/admin/team` (Wagner gera tokens)
- **ADR:** [memory/decisions/0056-mcp-fonte-unica-memoria-copiloto-claude-code.md](../../../memory/decisions/0056-mcp-fonte-unica-memoria-copiloto-claude-code.md)
- **Doc longo:** [MEMORY_TEAM_ONBOARDING.md](../../../MEMORY_TEAM_ONBOARDING.md)

## 1. Diagnóstico rápido (antes de tudo)

Quando ativar, primeiro **verifique o estado atual**:

```bash
# 1. Está em algum projeto oimpresso?
test -f CLAUDE.md && grep -l "oimpresso ERP" CLAUDE.md
# 2. Tem .mcp.json no root?
test -f .mcp.json && cat .mcp.json
# 3. Tem .claude/settings.local.json com token?
test -f .claude/settings.local.json && grep -c "Bearer mcp_" .claude/settings.local.json
```

Decida o caminho:

| Estado | Caminho |
|---|---|
| Sem CLAUDE.md ou outro projeto | Sair: "esta skill é só pro projeto oimpresso" |
| `.mcp.json` ausente | Provavelmente ainda não fez `git pull` — pedir pra fazer |
| `.claude/settings.local.json` ausente | **Modo A: dev novo** (gera template) |
| `settings.local.json` tem `COLE_SEU_TOKEN` | Dev preencheu errado — **Modo B: pegar token** |
| `settings.local.json` tem token mas MCP não responde | **Modo C: validar/debugar** |

## 2. Modo A — Dev novo (primeira vez)

```
Olá! Você está abrindo o oimpresso pela primeira vez. Vou te conectar ao MCP server
do time em 3 passos rápidos.

PASSO 1 — Pegar seu token MCP pessoal
   Wagner já te enviou um token via Vaultwarden? (algo tipo "mcp_abc123...")
   - Se sim: cola aqui
   - Se não: avisa o Wagner pra gerar pra você em
     https://oimpresso.com/copiloto/admin/team
```

Se o user colar o token (`mcp_<hex>`):

1. **Cria** `.claude/settings.local.json` com Write tool:
   ```json
   {
     "mcpServers": {
       "oimpresso": {
         "url": "https://mcp.oimpresso.com/api/mcp",
         "headers": { "Authorization": "Bearer mcp_<TOKEN_DO_DEV>" }
       }
     }
   }
   ```

2. **Valida** chamando o MCP:
   ```bash
   curl -s -X POST https://mcp.oimpresso.com/api/mcp \
     -H "Content-Type: application/json" \
     -H "Accept: application/json, text/event-stream" \
     -H "Authorization: Bearer <TOKEN>" \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}' | head -c 200
   ```

3. **Se HTTP 200 + tools listadas:**
   ```
   ✅ Conectado! Você tem acesso a:
   - tasks-current        → estado vivo do cycle
   - decisions-search     → 56 ADRs do projeto
   - decisions-fetch      → carregar 1 ADR
   - sessions-recent      → últimos session logs
   - memoria-search       → memória persistente do Copiloto
   - claude-code-usage-self → seu próprio uso/custo

   Pra começar, te recomendo:
   1. Ler CLAUDE.md (orientação geral)
   2. Ler memory/08-handoff.md (estado canônico)
   3. Pedir "tasks-current" pra ver o que o time tá fazendo

   Reinicia Claude Code pra carregar a config (Ctrl+C + abrir de novo).
   ```

4. **Se HTTP 401:** token errado — peça pra confirmar com Wagner
5. **Se HTTP 403:** token OK mas sem permissão — Wagner precisa atribuir `copiloto.mcp.use` na role do user
6. **Se timeout/connection refused:** MCP server pode estar com problema — checar status em https://mcp.oimpresso.com/api/mcp/health

## 3. Modo B — Token errado (placeholder não substituído)

User esqueceu de colar token real:

```
Vi que seu .claude/settings.local.json ainda tem o placeholder.
Cola aqui o token que o Wagner te enviou (começa com "mcp_")
```

Edita o arquivo com Edit tool, troca `mcp_COLE_SEU_TOKEN_AQUI` pelo real, valida.

## 4. Modo C — Validar/debugar

MCP não responde. Roda diagnóstico:

```bash
# 1. DNS resolve?
nslookup mcp.oimpresso.com
# 2. HTTPS responde?
curl -fsS https://mcp.oimpresso.com/api/mcp/health
# 3. Token tá correto no settings?
grep "Bearer mcp_" .claude/settings.local.json
# 4. Health autenticado funciona?
curl -fsS https://mcp.oimpresso.com/api/mcp/health/auth \
  -H "Authorization: $(grep -o 'Bearer mcp_[a-z0-9]*' .claude/settings.local.json)"
```

Reporta o que falhou + sugere fix.

## 5. Modo Wagner — adicionar dev novo ao time

Se o user é o Wagner (verificar via git config user.email == "wagnerra@gmail.com"):

```
Vou ajudar você a adicionar [Felipe/Maíra/Luiz/Eliana] ao time.

PASSO 1 — Gerar token MCP novo
  Eu posso gerar via comando? (sim/não)
  - sim: rodo `php artisan copiloto:mcp:system-token --user-email=<email>`
  - não: você abre /copiloto/admin/team manualmente, clica "+ Token" do dev,
         e me cola o token de volta

PASSO 2 — Entregar token via canal seguro
  - Vaultwarden (recomendado)
  - WhatsApp criptografado
  - NÃO: email plain, slack público

PASSO 3 — Comunicar setup pro dev
  Mando o link MEMORY_TEAM_ONBOARDING.md + token via VW. Dev:
    git clone https://github.com/wagnerra23/oimpresso.com
    cd oimpresso.com
    cp .claude/settings.local.json.example .claude/settings.local.json
    # edita o arquivo, cola token
    claude  # abre Claude Code

PASSO 4 — Validar (quando dev confirmar setup)
  Wagner abre /copiloto/admin/team — ver se "último uso MCP" do dev preenche
```

## 6. Quando watcher MEM-CC-1 estiver pronto (Sprint B)

Após Sprint A funcionar, oferece:

```
Quer também sincronizar suas memórias locais (~/.claude/projects/) com o servidor?
Isso permite que o Wagner consulte cross-dev "como Felipe resolveu X".

Pra ativar:
  cd scripts/cc-watcher
  npm install
  npm start &  # roda em background
  # ou instala como serviço (--service install)
```

## 7. Comandos úteis (reference card)

```bash
# Ver tools disponíveis no MCP
mcp tools list

# Pedir ADR específica
mcp call decisions-fetch --slug=0046-chat-agent-gap

# Pedir estado vivo do cycle
mcp call tasks-current

# Buscar memória semântica
mcp call memoria-search --query="meta de faturamento"

# Ver custo seu próprio uso
mcp call claude-code-usage-self
```

## 8. Troubleshooting

| Sintoma | Causa provável | Fix |
|---|---|---|
| `mcp.oimpresso.com` 503 | CT 100 Proxmox down | avisar Wagner |
| HTTP 401 | token expirado/errado | pedir novo no admin |
| HTTP 403 + `no_permission` | sem `copiloto.mcp.use` | Wagner atribui via Spatie |
| HTTP 429 | quota diária excedida | esperar reset 00:00 BRT ou Wagner aumenta |
| HTTP 200 mas tools/list vazio | bug — reportar Wagner | — |

## 9. Confirmação final

Antes de finalizar a skill, verifica que:

- [ ] `.claude/settings.local.json` existe + token real (sem placeholder)
- [ ] `tools/list` retorna ≥6 tools
- [ ] `tools/call tasks-current` funciona (response não-vazio)
- [ ] Skills auto-ativáveis (`multi-tenant-patterns`, `publication-policy`) carregam
- [ ] Dev sabe que precisa reiniciar Claude Code 1× pra config carregar

Reporta:
```
✅ Setup completo. Você tem acesso ao MCP server do time.
Próximo: reinicia Claude Code e pergunta "tasks-current" pra ver o que tá rolando.
```
