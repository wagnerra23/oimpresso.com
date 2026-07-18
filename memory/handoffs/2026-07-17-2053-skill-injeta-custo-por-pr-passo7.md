---
date: "2026-07-17"
time: "2053 BRT"
slug: skill-injeta-custo-por-pr-passo7
tldr: "Passo 7 da skill governance-pr-summary (PR #4491 MERGED) invoca agent-cost-per-pr --pr <N> pós-merge e injeta o bloco de custo no corpo do PR — fecha o item 2 do mandato custo-por-PR (\"fazer o número chegar no PR\"). Item 1 (a ferramenta --pr) já landou via #4488; mecanismo integrado provado ao vivo (--pr 4491 → bloco com número real)."
decided_by: [W]
prs: [4491]
next_steps:
  - "Opcional: dogfoodar o passo 7 no corpo do próprio #4491 (edita PR mergeado via gh pr edit) — é edição de conteúdo público, precisa OK explícito do [W]"
  - "Chip C3 (arco irmão, não deste PR): drift-sentinel da Jana roda mas é cego (baseline mock) — ver handoffs 13:30 e 17:49"
related_adrs:
  - 0271-revisao-gates-ci-estado-real-required-e-subtracao-segura
  - 0314-poda-gates-onda-2-lei-fusoes
---

# Passo 7: fazer o número do custo chegar no corpo do PR

## O que foi feito

Fechado o **item 2** do mandato custo-por-PR. A ferramenta `agent-cost-per-pr.mjs --pr <N>` já imprimia o bloco pronto (`renderPrBlockMd`, marcador `<!-- agent-cost-per-pr -->`), mas **nada a invocava**. **[PR #4491](https://github.com/wagnerra23/oimpresso.com/pull/4491) (MERGED)** adicionou o **passo 7** à skill `governance-pr-summary`: após o merge, roda `--pr <N>` **local** e injeta o bloco no corpo do PR via `gh pr edit --body-file`. Só a skill mudou (+79/−2, 1 arquivo).

Detalhes de desenho (todos verificados, não assumidos) no session log irmão `2026-07-17-skill-injeta-custo-por-pr-passo7.md`. Resumo:
- **LOCAL** (CI não vê o JSONL, G5) · **PÓS-merge** (tool lê `--state merged`; PR aberto nunca casa — correção da redação "ao abrir" do mandato).
- **Guard do marcador**: só injeta se a 1ª linha for `<!-- agent-cost-per-pr -->`; versão sem `--pr` cospe o relatório humano (`═══`) → `*)` no-op.
- **Idempotente**: `awk` ancorado, prefixo byte-estável em 3 passadas, 1 bloco; menção inline sobrevive.
- **Degrada honesto** (PR aberto/sem sessão → `não medido G1/G3`) · **advisory, USD-only, zero R$** (Tier 0).

## Estado / integração

- **Item 1 já landou**: **[PR #4488](https://github.com/wagnerra23/oimpresso.com/pull/4488) (MERGED)** por sessão irmã (`silly-varahamihira`) — a ferramenta `--pr`/`renderPrBlockMd` está em `origin/main`. **NÃO criei PR duplicado** da branch `competent-bassi` (seria re-landar o mesmo código; append-only).
- **Mecanismo integrado provado ao vivo**: ferramenta real do main, `--pr 4491` → bloco com número real (matched por `citacao`). Tool #4488 + skill #4491 = ambos vivos em main.
- **CI do #4491**: todos os required verdes (76 pass, 2 skip). Único vermelho = `module-grades-gate` (**advisory**, ADR 0314 D-1) reportando `KB 77→76 (−1)` — **alheio ao diff** (skill-only, não toca KB); drift live-vs-baseline pré-existente. **Não mexi no baseline** pra silenciar.

## Estado MCP no momento do fechamento

⚠️ **MCP oimpresso indisponível nesta sessão** (`cycles-active` timeout · `my-work`/`decisions-search` "Server unavailable" — mesmo cenário do handoff 17:49). Checklist por **fallback filesystem** (how-trabalhar §Fallback):

- **sessions-recent** (`ls -t memory/sessions`): `2026-07-17-reguas-grade-truncagem-silenciosa` · `-arte-artefatos-por-tela` · `-piso-context-recall-e-schedule-fantasma`.
- **handoffs** (append-only, topo do índice): `2026-07-17-2024-close-design-to-code-session` · `-1900-corpus-c1-f2-gastar` · `-1749-land-custo-por-pr-advisory`.
- **decisions** (recentes, `ls -t memory/decisions`): 0343 (promove ADR-gate required, emenda 0341) · 0340 (tema colapso auto-blade/react) · 0339 (promoção soberana 3 gates).
- **Brief SessionStart** (#372): sem cycle ativo · HITL pending [W]: 2 · Brain B 0% · 0 incidentes 24h.

Off-cycle. Worktree `suspicious-clarke-967365` @ `origin/main`.
