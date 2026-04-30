# Session log — 2026-04-29 noite — MCP Team self-host + memória cross-source

> **Sessão de contexto:** estourou ~970K tokens (anti-padrão; ver `HOW_TO_ASK_CLAUDE.md`). Consolidação no fim.

---

## Entregas (commits em ordem)

| Commit | O quê | Arquivo-chave |
|--------|-------|---------------|
| `e3ea5b92` | ADR 0054 — pacote enterprise busca memória + 5-fase com gates Recall@3 | `memory/decisions/0054-pacote-enterprise-busca-memoria-evolucao.md` |
| `c4706bef` | MEM-TEAM-1 Self-host equiv Anthropic Team plan + ADR 0055 | `Modules/Copiloto/Http/Controllers/Admin/TeamController.php`, `resources/js/Pages/Copiloto/Admin/Team/Index.tsx`, `Modules/Copiloto/Entities/Mcp/*.php` |
| `c2339ba1` | QuotaEnforcer 3 kinds (brl/calls/tokens) + alertas idempotentes 50/80/100% | `Modules/Copiloto/Services/Mcp/QuotaEnforcer.php`, migration `mcp_alertas_eventos` |
| `8c8b7ccb` | Middleware popular `custo_brl` + tokens automaticamente | `Modules/Copiloto/Http/Middleware/McpAuthMiddleware.php` |
| `a58e7f34` | MEM-MEM-MCP-1 MCP-as-memory-source + ADR 0056 + McpMemoriaDriver com fallback | `Modules/Copiloto/Services/Memoria/McpMemoriaDriver.php`, `Modules/Copiloto/Mcp/Tools/MemoriaSearchTool.php`, `Modules/Copiloto/Console/Commands/McpSystemTokenCommand.php` |
| `4fa97dd8` | Sprint A+B onboarding skills + cc-search tool + 3 migrations mcp_cc_* | `.mcp.json`, `.claude/settings.local.json.example`, `.claude/skills/oimpresso-team-onboarding/SKILL.md`, `.claude/skills/oimpresso-cc-watcher-setup/SKILL.md`, `Modules/Copiloto/Mcp/Tools/CcSearchTool.php`, `MEMORY_TEAM_ONBOARDING.md` |
| `c807d5db` | Fix migration `mcp_cc_blobs` — `binary()` em vez de `mediumBlob()` (não existe L13) | `Modules/Copiloto/Database/Migrations/2026_04_29_300003_create_mcp_cc_blobs_table.php` |

**Validações em produção:**
- Hostinger: 3 tabelas `mcp_cc_*` migradas (`Ran` em `migrate:status`)
- CT 100 MCP server: `tools/list` retorna 7 tools (5 originais + `memoria-search` + `cc-search`)
- TeamController smoke: KPIs renderizam, modais token/quota funcionam
- McpAuthMiddleware: `custo_brl` populado automaticamente (12 in / 529 out / R$ 0.001756)

---

## ADRs canônicos criados

1. **0054 — Pacote enterprise busca memória + estratégia evolução**
   - 10 otimizações em 3 camadas (cache / retrieval / hygiene)
   - 5-fase com gates objetivos Recall@3 (Corpus → Retrieval → Ranking → Economia → Inteligência)

2. **0055 — Self-host equivalente Anthropic Team plan**
   - Decisão "100% build, zero buy" — todas features Team plan ($25/seat/mo) replicadas self-host
   - Comparativo: governança, memória, ingestão de docs, configuração MCP + 11 dimensões adicionais
   - 4 gaps salvos (MEM-GAP-1 a 4) pra evolução futura

3. **0056 — MCP fonte única de memória Copiloto + Claude Code**
   - Servidor MCP é a única camada de memória pra **todos** os clientes IA
   - Migração backward-compat via `COPILOTO_MEMORIA_DRIVER=mcp` env
   - Fluxo: Claude Code/Copiloto chat → MCP server → DB (vez de cada um ter driver próprio)

---

## Skills publicadas

- `.claude/skills/oimpresso-team-onboarding/SKILL.md` — auto-detecta estado e guia dev através do setup MCP em 4 modos
- `.claude/skills/oimpresso-cc-watcher-setup/SKILL.md` — orquestra Claude Code a criar `.cc-watcher/` local, instalar deps, fazer backfill de `~/.claude/projects/*.jsonl`, configurar como serviço (systemd/launchd/Task Scheduler)

---

## Pendente pro próximo passo (NÃO fiz nesta sessão)

| # | Pendência | Bloqueia | Como rodar |
|---|-----------|----------|------------|
| 1 | Gerar system token Copiloto chat | Liga MCP no fluxo chat | `php artisan copiloto:mcp:system-token --user-email=wagner@…` |
| 2 | Add `COPILOTO_MEMORIA_DRIVER=mcp` + `COPILOTO_MCP_SYSTEM_TOKEN=mcp_xxx` em `.env` Hostinger | Mesmo | `ssh hostinger && nano .env` |
| 3 | Smoke chat real → recall via MCP | Validação ponta-a-ponta | Chat real Hostinger; `tail -f storage/logs/copiloto.log` |
| 4 | Wagner roda `oimpresso-cc-watcher-setup` skill 1× local | Sprint B operacional (cc-search retorna hits) | Abrir Claude Code em `D:\oimpresso.com`, pedir "configura watcher" |
| 5 | Backfill facts: `copiloto:backfill-fatos --business=all --sync` | Sobe Recall@3 0.125 → ~0.30 (Phase 2 ADR 0054) | SSH Hostinger |
| 6 | Re-rodar gabarito 50 perguntas pós-backfill | Mede ΔR@3 | `copiloto:eval-gabarito` |

---

## Anti-padrões desta sessão (lições)

1. **Sessão única de 970K tokens.** Deveria ter sido N sessões focadas com `/clear` no fim de cada feature.
2. **Pedidos abertos do tipo "construa o que falta"** disparam exploração ampla. Pedir item específico com arquivo + linha + o que mudar economiza 80% dos tokens.
3. **Múltiplas mudanças simultâneas** (TeamController + QuotaEnforcer + middleware + entidades + migrations) fica difícil reverter parcialmente.

**Mitigação:** criado `HOW_TO_ASK_CLAUDE.md` com 3 regras de ouro + receitas por tipo de pedido + sinais de quando dar `/clear`.

---

## Estado canônico pós-sessão

- ✅ `mcp.oimpresso.com` em prod com 7 tools (5 originais + `memoria-search` + `cc-search`)
- ✅ Hostinger Laravel app: 3 tabelas `mcp_cc_*` + 5 entidades Mcp + TeamController + dashboard `/copiloto/admin/team`
- ✅ Skill onboarding pra dev novo + skill watcher local
- ✅ ADRs 0054/0055/0056 commitados
- ✅ HOW_TO_ASK_CLAUDE.md criado pra próximas sessões
- 🔲 Token system + env Hostinger (manual, 5 min)
- 🔲 Watcher skill rodada por Wagner (manual, 15 min)
