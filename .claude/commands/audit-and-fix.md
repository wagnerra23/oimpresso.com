---
description: Aplica o ciclo Pesquisar → Comparar → Avaliar % → Implementar → Consolidar PR num tema/área. Pattern validado 19 PRs em 6h na sessão 2026-05-13 (70% → 91% maturidade). Spawna agent research + N agents implement em paralelo. Uso `/audit-and-fix <tema> [escopo]`.
---

# /audit-and-fix — ciclo canônico Pesquisar→Comparar→%→Fazer

**Pattern validado** na sessão 2026-05-13 (`nervous-mayer-3ff0da`) — 19 PRs mergeados em 6h, 70% → 91% maturidade global. Reuso permanente.

## Argumentos

- `$1` (obrigatório) — **tema** da auditoria. Exemplos:
  - `mcp-sync` (task management, Linear/Jira/Plane)
  - `knowledge-architecture` (PKM/RAG, Notion/Obsidian/Mem0)
  - `session-handoff` (LangGraph/PagerDuty)
  - `reranker` (BGE/Cohere/RRF)
  - `observability` (Langfuse/Phoenix)
  - `charters-s4`
  - qualquer área específica: `sells-grade`, `whatsapp-inbox`, `ads-brain-b`, etc

- `$2` (opcional) — **modo**:
  - `--research-only` — só fase 1 (research + comparativo + nota %); NÃO implementa
  - `--top3` (default) — research + spawn 3 agents implement nos top 3 gaps
  - `--top<N>` — spawn N agents implement (max 6)
  - `--gap-only:<G>` — implementa apenas gap específico G (precisa research feito antes)

## Pré-requisitos

- (opcional mas recomendado) `memory/requisitos/Jana/AUDITORIA-<tema>-*.md` já existir — agent research pula re-pesquisa
- Worktree limpa (sem git status pendente) — evita conflitos com PRs criados pelo ciclo
- Tools MCP conectadas

## Ciclo executado (4 fases)

### Fase 1 — Research (1 agent)

Invoca `Agent(subagent_type: "audit-research-expert")` em background com:

- **Tema** = `$1`
- **Pesquisa web fresh** (5-7 WebSearch) sobre best-of-class do tema 2025-2026
- **Inventário código oimpresso** (Glob/Grep áreas relevantes)
- **Mini-comparativo** (8-12 sistemas × 15-20 dimensões)
- **Score % por área** weighted vs best-of-class
- **Top 10 gaps priorizados** (esforço dev-days + ROI + prioridade)
- **Decisão CONSOLIDAR vs EVOLUIR** fundamentada
- **Roadmap 3 ondas** se EVOLUIR

**Saída obrigatória:** `memory/requisitos/Jana/AUDITORIA-<tema>-YYYY-MM-DD.md` (>5KB, >100 linhas) + verificação `ls -la` no reporte (anti-alucinação).

### Fase 2 — Aprovação Wagner (HITL)

Skill apresenta:
- TL;DR (% atual / % target após onda)
- Top 3 gaps P0/P1
- Decisão recomendada (CONSOLIDAR vs EVOLUIR)
- Surpresa positiva e negativa

Wagner aprova passagem pra Fase 3 (ou aborta com `--research-only`).

### Fase 3 — Implement (N agents paralelos)

Spawna **N agents `audit-implement-expert`** (N = 3 default ou via `--top<N>`):

- Cada agent recebe **1 gap específico** com áreas isoladas explícitas
- **Pré-requisitos validados:** zero overlap entre N agents (vide [aprendizados-onda1-2-3](../../memory/reference/aprendizados-onda1-2-3-2026-05-13.md) §2)
- Cada agent:
  - Pesquisa 2-3 WebSearch específica do gap
  - Mini-comparativo % atual → target
  - Implementação focada (código + tests Pest + RUNBOOK)
  - **Verificação `ls -la` obrigatória no reporte** (anti-alucinação)
  - ZERO git ops — parent consolida

### Fase 4 — Consolidate (parent — sem agents)

Parent (sessão Claude principal) faz:

1. **Por gap implementado**, cria branch `claude/<tema>-<gap-slug>` from `origin/main`
2. Add arquivos isolados do agent + splittar shared files (Provider/Server/Kernel) **manualmente** via Edit (lição: `git add -p` não funciona em script)
3. `git commit -m` conventional + Refs auditoria
4. `git push -u origin <branch>`
5. `gh pr create --title ... --body ...`
6. `gh pr merge --admin --squash --delete-branch` (se Wagner aprovou auto-merge)
7. **Rebase pattern** se 2º PR conflitar com 1º já mergeado (vide aprendizados §4)

## Restrições TIER 0 (CADA agent spawnado herda)

- **`ls -la` no reporte é IRREVOGÁVEL** (lição alucinação Write 2026-05-13)
- **Áreas isoladas obrigatórias** entre N agents paralelos
- **Multi-tenant Tier 0** ([ADR 0093](../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)) IRREVOGÁVEL
- **PT-BR** em prompts/comentários
- **ZERO git ops** nos agents — parent consolida
- **Tools MCP** com mock mode pra Pest sem custo
- **Custo IA tracking** por feature LLM ([ADR 0094](../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md) §4)

## Saída esperada

```
Fase 1 — Research:
  ✅ memory/requisitos/Jana/AUDITORIA-<tema>-YYYY-MM-DD.md (>5KB, NN linhas)
  ✅ Score atual: NN%
  ✅ Top 3 gaps P0/P1: <lista>

Fase 2 — Wagner aprovou? Y/N

Fase 3 — Implement (N agents paralelos):
  ✅ Agent #1 <gap-A>: NN/NN Pest passed
  ✅ Agent #2 <gap-B>: NN/NN
  ✅ Agent #3 <gap-C>: NN/NN

Fase 4 — Consolidate:
  ✅ PR #NNN <gap-A> merged
  ✅ PR #NNN <gap-B> merged
  ✅ PR #NNN <gap-C> merged

Score final pós-ciclo: NN% (era NN%, +NNpp)
```

## Exemplos

```bash
/audit-and-fix mcp-sync                    # Onda 1 da sessão 2026-05-13
/audit-and-fix knowledge-architecture      # Onda 2
/audit-and-fix session-handoff             # Onda 2 (3º artefato)
/audit-and-fix reranker --top3             # R1 hoje
/audit-and-fix observability --research-only  # só audit, sem implement
/audit-and-fix ads-brain-b --top6          # auditar ADS com 6 implementadores
```

## Pattern reusável (não-oimpresso-específico)

Funciona pra qualquer **tema** onde:
1. Existe **estado-da-arte** público pesquisável
2. Existe **código atual** auditável (Glob/Grep)
3. É possível **medir maturidade** em dimensões
4. **Áreas de implementação** podem ser isoladas pra N agents

Anti-pattern: temas vagos ("melhorar sistema"), sem benchmark externo claro, ou onde N agents conflitam inevitavelmente (área única monolítica).

## Refs

- [Aprendizados Onda 1+2+3](../../memory/reference/aprendizados-onda1-2-3-2026-05-13.md) — 8 lições críticas
- [GAP-ANALYSIS-91-100](../../memory/requisitos/Jana/GAP-ANALYSIS-91-100-2026-05-13.md) — métrica saturação 97-98%
- [Pattern documentado](../../memory/reference/pattern-audit-and-fix-cycle.md) — workflow completo
- Agents: `.claude/agents/audit-research-expert.md` + `.claude/agents/audit-implement-expert.md`
