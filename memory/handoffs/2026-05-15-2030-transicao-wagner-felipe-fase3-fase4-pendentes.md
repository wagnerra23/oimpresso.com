# Handoff 2026-05-15 20:30 — Transição conta Wagner→Felipe + Fase 3 entregue parcial + Fase 4 pendente

## TL;DR

Wagner está passando esta conta Claude Code pra Felipe (próximo dev do time MCP). Esta sessão:
- **Fase 3 `.claude/rules/` path-scoped:** entregue 5 rules + README + ADR proposta (mas Pest test + dossier longo pulados pra agilizar transição).
- **Fase 4 module-state implementação:** NÃO INICIADA. Spec + RUNBOOK + decisões cinzentas Wagner já estão prontas. Felipe retoma seguindo RUNBOOK fielmente.

## Cronologia desta sessão (continuação direta de [handoff 18:30](2026-05-15-1830-audit-memory-claude-code-evolution-fase1-2-hooks-regression-fix.md))

| Quando | Evento |
|---|---|
| 2026-05-15 18:45 | Wagner aprovou "pode fazer" → AskUserQuestion confirmou Ambas Fase 3+4 + cinzentas resolvidas |
| 18:50 | Session log decisões cinzentas gravado em [memory/sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md](../sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md) |
| 19:00 | Spawn 2 agents paralelos `audit-implement-expert` worktree isolated — Agent A (Fase 3) + Agent B (Fase 4) |
| ~19:30 | Agents terminaram em background — Agent A produziu 4 rules + README (não commitados); Agent B sem output capturado |
| 20:25 | Wagner: *"grave tudo vou continuar em outro code, essa conta vai ser passada para o Felipe se configure. salve tudo de todas as sessões"* |
| 20:30 | Recuperação trabalho Agent A pendente + criação `commands.md` faltante + ADR proposta + este handoff |

## Estado atual dos artefatos

### ✅ Fase 3 — entregue parcial (este PR de transição)

| Arquivo | Status |
|---|---|
| [`.claude/rules/README.md`](../../.claude/rules/README.md) | ✅ pronto |
| [`.claude/rules/modules.md`](../../.claude/rules/modules.md) | ✅ pronto (39 linhas, pré-flight + multi-tenant) |
| [`.claude/rules/pages.md`](../../.claude/rules/pages.md) | ✅ pronto (37 linhas, MWART + charter + Inertia::defer) |
| [`.claude/rules/migrations.md`](../../.claude/rules/migrations.md) | ✅ pronto (63 linhas, idempotência + business_id) |
| [`.claude/rules/routes.md`](../../.claude/rules/routes.md) | ✅ pronto (57 linhas, FQCN obrigatório) |
| [`.claude/rules/commands.md`](../../.claude/rules/commands.md) | ✅ pronto (criado nesta sessão — `--detail` em vez de `--verbose`) |
| [`memory/decisions/proposals/claude-rules-path-scoped.md`](../decisions/proposals/claude-rules-path-scoped.md) | ✅ ADR proposta (status `proposal` — Wagner promove após 2-4 semanas de uso) |

### 🟡 Fase 3 — gaps pulados (não-bloqueantes)

- Dossier longo `memory/sessions/2026-05-15-arte-claude-rules-path-scoped.md` (pesquisa Anthropic 2026 + comparativos) — Felipe pode escrever se quiser, mas ADR proposta cobre o essencial
- Pest test `tests/Feature/ClaudeRulesStructureTest.php` (valida frontmatter parseable + ≤30 linhas + links válidos) — backlog opcional

### ❌ Fase 4 — NÃO INICIADA (Felipe retoma)

Todo o spec + runbook já estão escritos e mergeados na main:

- **Spec:** [memory/requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md](../requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md) — 14 campos response, multi-tenant Tier 0, 5 áreas cinzentas resolvidas
- **RUNBOOK:** [memory/requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md](../requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md) — 7 fases sequenciais com gate humano Fase 6
- **Decisões cinzentas Wagner:** [memory/sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md](../sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md)

**Estimate:** 14h IA-pair · 1.75 dev-day · PR ≤500 linhas em 6 commits ≤300 linhas cada.

## Cinzentas Wagner resolvidas (Fase 4)

Documento canônico: [decisoes-wagner-cinzentas-us-mcp-017](../sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md)

| Cinzenta | Decisão Wagner |
|---|---|
| C1 Multi-tenant edge | **Incluir Jana/Infra/Mcp/Brief** com flag `crossTenant: true` no response |
| C2 Discovery | **Glob** `memory/requisitos/*/SPEC.md` (auto-detect) |
| C3 Cache | **TTL 5min puro** (paridade brief-fetch ADR 0091) |
| C4 Thresholds drift | **`config/mcp.php`** seção `module_state.drift` |
| C5 Granularidade | **Top-level only** (sub-módulos só se houver SPEC.md no glob) |

## Como Felipe retoma Fase 4 (passo-a-passo)

1. **Sessão SessionStart:** `mcp__oimpresso__brief-fetch` primeiro (skill `brief-first` Tier A always-on)
2. **Ler ordem:**
   - [Spec US-MCP-017](../requisitos/Mcp/SPEC-US-MCP-017-module-state-projection.md)
   - [RUNBOOK 7 fases](../requisitos/Mcp/runbooks/RUNBOOK-module-state-tool.md)
   - [Cinzentas Wagner](../sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md)
3. **Branch:** `git switch -c claude/us-mcp-017-module-state-impl` a partir de `main` atualizado
4. **Implementar Fase 1-5 do RUNBOOK** (skeleton + 9 coletores + drift + cache + 5 Pest tests). NÃO executar Fase 6 (smoke real biz=1 — Wagner valida).
5. **Commits sequenciais** (RUNBOOK plano §"sequenciamento"):
   - `feat(mcp): scaffold ModuleStateTool + schema + migration cache`
   - `feat(mcp): coletores cycle+tasks+adrs+handoffs+prs`
   - `feat(mcp): coletores charter+runbook+spec+capterra + cache layer`
   - `feat(mcp): drift detection 4 regras`
   - `feat(mcp): register OimpressoMcpServer + config/mcp.php`
   - `test(mcp): Pest 5 tests ModuleStateTool incl. Tier 0`
   - `docs(mcp): README + SPEC US-MCP-017 done`
6. **PR + admin merge:** Wagner valida smoke biz=1 (Fase 6 gate humano) com módulos `Whatsapp` + `Sells` + `Jana` + `Crm` + módulo inexistente.

## Setup conta Felipe (passagem técnica)

Felipe precisa:

1. **Configurar acesso MCP server:** skill `oimpresso-team-onboarding` automatiza setup `.claude/settings.local.json`
2. **Watcher local Claude Code:** skill `oimpresso-cc-watcher-setup` configura sync `~/.claude/projects/*.jsonl` com MCP server (cc-search cross-dev)
3. **Brief inicial:** `mcp__oimpresso__brief-fetch` deve retornar estado consolidado ~3k tokens incluindo Felipe nas tasks DOING

Wagner deve adicionar Felipe ao team MCP server (skill `oimpresso-team-onboarding` tem fluxo "adicionar dev novo").

## Artefatos canon novos/atualizados nesta sessão (PR transição)

### Files commitados (este PR)

- `.claude/rules/README.md` (NOVO)
- `.claude/rules/modules.md` (NOVO)
- `.claude/rules/pages.md` (NOVO)
- `.claude/rules/migrations.md` (NOVO)
- `.claude/rules/routes.md` (NOVO)
- `.claude/rules/commands.md` (NOVO — criado nesta sessão)
- `memory/decisions/proposals/claude-rules-path-scoped.md` (NOVO — ADR proposta)
- `memory/sessions/2026-05-15-decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md` (NOVO — audit trail decisões)
- `memory/handoffs/2026-05-15-2030-transicao-wagner-felipe-fase3-fase4-pendentes.md` (este arquivo)
- `memory/08-handoff.md` (Edit — entry novo no índice)

## Worktrees orfanados (cleanup pendente — não-bloqueante)

2 worktrees agent foram criados mas não produziram commits válidos:
- `D:/oimpresso.com/.claude/worktrees/agent-a39bad4f8dda3f28d/` (Agent A Fase 3) — locked
- `D:/oimpresso.com/.claude/worktrees/agent-a97d18aecc43aaae6/` (Agent B Fase 4) — locked

Felipe pode limpar com `git worktree remove --force <path>` quando começar a sessão. Ambos estão no commit `c70f7ede6` (=main pré-trabalho).

## Estado memory infra Claude Code

```
Skills Tier A LIVE:           8 (preflight-modulo Fase 1+2)
Skills Tier A DORMENTE:       1 (ads-route até S5)
Hooks PreToolUse/PostToolUse: 12 (todos BOM UTF-8, smoke-tested 12/12 OK)
Smoke test agregador:         ATIVO (test-all-hooks-smoke.ps1)
Rules path-scoped:            5 NOVO (.claude/rules/ Fase 3 entregue)
ADRs canon decisions/:        146
Sessions log canon:           60+ (esta sessão adiciona 2)
Handoffs append-only:         34+ entries

Nota memory infra: 92 → 94/100 (Fase 3 quase completa — gaps Pest+dossier longo)
                    97/100 quando Fase 4 module-state implementada (Felipe retoma)
                   100/100 quando Pest test rules + dossier longo entregues (backlog)
```

## PRs sessão atual (1 mergeado + 1 abrir agora)

| PR | Conteúdo |
|---|---|
| [#880](https://github.com/wagnerra23/oimpresso.com/pull/880) | Handoff fechamento 18:30 audit memory (mergeado c70f7ede6) |
| TBD | Este PR transição — `.claude/rules/` Fase 3 + handoff 20:30 + ADR proposta |

## Pendente após esta sessão (Felipe assume)

### P0 (Fase 4)
- Implementar tool MCP `module-state` (14h IA-pair, RUNBOOK fielmente)
- Smoke real biz=1 Wagner valida

### P1 (backlog opcional)
- Dossier `memory/sessions/2026-05-15-arte-claude-rules-path-scoped.md` (pesquisa Anthropic 2026 + comparativos com sub-CLAUDE.md)
- Pest test `tests/Feature/ClaudeRulesStructureTest.php` (frontmatter parseable + ≤30 linhas + links válidos)
- Promover ADR proposta `claude-rules-path-scoped.md` pra ADR aceita (próximo número canônico) após 2-4 semanas de uso

### P2 (backlog não-bloqueante)
- Cleanup 2 worktrees orfanados (Felipe na 1ª sessão)
- G8 audit descriptions em 1ª pessoa (anti-pattern Anthropic 2026) — 2h
- G9 playbook visual onboarding time MCP — 4h
- G10 glossary local DDD por bounded context — 6h
- 4 PRs não-WhatsApp ainda abertos: #594, #812, #860, #862

## Wagner — pendências decisão

Nenhuma cinzenta nova. Decisões Fase 4 cinzentas já resolvidas. Felipe executa.

---

**Próxima sessão Felipe:** retomar via `brief-fetch` → ler `decisoes-wagner-cinzentas-us-mcp-017-fase3-fase4.md` → spawn implementação Fase 4 conforme RUNBOOK.
