# 06 — `charter-fetch` MCP tool: deploy CT 100 pendente

> **Status:** ⏸️ PENDENTE Wagner. Esta é a única peça de F2 que não cabe num PR — exige SSH no CT 100 e deploy real do MCP server canônico (`mcp.oimpresso.com`).

---

## Por que está fora do PR de F2

[ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) (Tier 0): MCP server tools rodam **APENAS no CT 100** (FrankenPHP, `mcp.oimpresso.com`), nunca no Hostinger. Adicionar tool nova exige:

1. SSH no CT 100 via Tailscale (`tailscale ssh root@ct100-mcp`)
2. Pull do branch que adiciona a tool em `Modules/MCP/Tools/CharterFetchTool.php` (ou similar)
3. `composer dump-autoload`
4. Restart do FrankenPHP container
5. Smoke test: `curl mcp.oimpresso.com/api/mcp/tools/charter-fetch?page=/repair/dashboard`

Toda essa sequência exige **um humano com chave SSH ativa** — Claude Code (este agente) não tem credencial. Por **publication-policy**, deploy em prod sem aprovação granular é proibido mesmo que tivesse.

---

## O que está pronto (F2 partial entregue neste PR)

| Peça | Status |
|---|---|
| Skill `charter-first` | ✅ ativada (`enabled: true`) — lê charter via filesystem por enquanto |
| Skill `charter-write` | ✅ criada |
| Artisan `charter:audit` | ✅ criada |
| Artisan `charter:health` | ✅ criada |
| Cron `charter:health` daily 06:30 | ✅ registrado em `app/Console/Kernel.php` |
| `tests/Charter/baseline.json` | ✅ vazio (modo soft mantido) |
| Tool MCP `charter-fetch` | ⏸️ deploy CT 100 pendente |

Skill `charter-first` funciona **sem `charter-fetch`** — ela lê o `.charter.md` direto do filesystem do worktree em sessões locais. A tool MCP é otimização (cache 5min, telemetria, drift signal centralizado) — não é bloqueador.

---

## O que precisa Wagner fazer

Quando quiser ativar `charter-fetch` MCP tool:

```bash
# 1. SSH CT 100
tailscale ssh root@ct100-mcp

# 2. Cd no projeto
cd /var/www/oimpresso

# 3. Pull main (ou branch específica)
git pull origin main

# 4. Implementar tool — TODO em PR separado quando Wagner quiser
# Estrutura sugerida:
# - Modules/MCP/Tools/CharterFetchTool.php (handler)
# - Modules/MCP/Routes/mcp.php (registrar)
# - Tests/Feature/CharterFetchTest.php (smoke)

# 5. Restart
systemctl restart frankenphp

# 6. Smoke
curl https://mcp.oimpresso.com/api/mcp/tools/charter-fetch?page=/repair/dashboard
```

ou: Wagner abre uma sessão separada com `/start-task implementar charter-fetch tool MCP CT 100` e Claude executa via Tailscale SSH com aprovação granular.

---

## Por que isso não bloqueia F2 ser declarada "completa"

Spec da tool ([02-charter-fetch-tool.md](02-charter-fetch-tool.md)) entregue.
Skill que consome ([charter-first](../../../.claude/skills/charter-first/SKILL.md)) ativada com fallback filesystem.
Métricas dependentes (M1 token economy) entram em F4 — quando F4 medir, ela mede o que tiver.

F2 fica **funcional sem CT 100 deploy**. Wagner agenda o deploy separado.

---

## Critério de "F2 fechada de verdade" (quando deploy CT 100 acontecer)

- [ ] Tool `charter-fetch` deployed em `mcp.oimpresso.com`
- [ ] Smoke test passa via `curl`
- [ ] Skill `charter-first` migra de `read filesystem` pra `chama tool MCP`
- [ ] Métrica M1 (token economy) coleta primeiros samples
- [ ] Workflow `charter-gate.yml` pode promover de `soft` → `hard` (precisa baseline aceito também)

Sem o deploy, F3 (Capterra v2) e F4 (Performance Testing) ainda podem rodar — só M1 fica `not measured` até lá.
