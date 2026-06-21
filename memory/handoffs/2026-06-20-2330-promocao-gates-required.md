---
date: "2026-06-20"
time: "23:30 BRT"
slug: promocao-gates-required
tldr: "Proposta dos 8 gates que mordem mas estão fora do required, depois EXECUÇÃO Wave-0 + 1º passo Wave-1. Landado: #3109 (baseline reconciliado +a11y), dSIH zumbi removido do protection vivo (Tier-0 PATCH [W]), #3114 (design-index→always-run+short-circuit, advisory). Pixel-diff NÃO flipado (sem soak). Resto é flip [W] futuro."
decided_by: [W]
cycle: CYCLE-08
prs: [3109, 3114]
related_adrs: ["0261-enforcement-faseado-gates-ci", "0271-revisao-gates-ci-estado-real-required-e-subtracao-segura"]
next_steps:
  - "design-index always-run soakar → flip a required (protection + baseline mesmo PR)"
  - "PRs de UI acumularem 2 verdes do pixel-diff → remover continue-on-error"
  - "Landar ADR 0275 (sai de draft) + decidir demoção module-grades (0271 D-4)"
---

# Promoção de gates a required — proposta + Wave-0/Wave-1 executados

## Estado MCP no momento
MCP `oimpresso` **indisponível** (server unavailable) → git+gh como fonte da verdade (padrão das últimas sessões). Off-cycle CYCLE-08. A branch de trabalho era `fix/tasks-create-id-collision-orphan` (**509 commits atrás do main** — todos os reads de workflow re-verificados contra `origin/main`).

## O que aconteceu
[W] pediu PROPOSTA (não promover sozinho) sobre 8 gates que fazem `exit 1` mas cujo context não está no required → 🔴 e mergeiam mesmo assim. Confirmei o `exit 1` real de cada (script-level: `process.exit(1)`/`exit(1)`, não só ausência de `continue-on-error`), idade (git log), estabilidade (gh run), cruzando com **protection vivo** (gh api), baseline JSON, ADR 0261/0271 e o calendário **ainda-draft** da ADR 0275.

Achados que mudaram a ação: (1) **`a11y-axe` JÁ É required ao vivo** (always-run via ADR 0282/#2885), só faltava no baseline → drift 🟡; (2) **`dSIH ratchet vs baseline` é required-ZUMBI** (sem produtor no repo, não reporta check-run); (3) os outros 6 seguem path-scoped → conversão skip-as-pass é pré-requisito do flip; (4) **`module-grades` não promover** (ADR 0271 D-4 propõe demotir).

[W] aprovou → **Wave-0** (reconciliação) + autorizou a remoção Tier-0 do dSIH + o 1º passo de **Wave-1** (conversão design-index). Depois [W] "merge" → mergeei #3114 (35 checks verdes).

## Artefatos gerados
- **PR #3109** (MERGED) — `governance/required-checks-baseline.json` +`A11y axe` context + `_meta` bump; carrega o session log `memory/sessions/2026-06-20-promocao-gates-required.md` (~210 linhas, scorecard dos 8).
- **dSIH removido do protection vivo** — `gh api PATCH required_status_checks` (18→17 contexts; backup pré-PATCH em `D:/tmp/rsc-backup.json`); `enforce_admins:true` intacto.
- **PR #3114** (MERGED) — `design-index-gate.yml` always-run + short-circuit `git diff` (padrão a11y verbatim); self-test verde (rodou o Pest pois tocava o próprio .yml). **Advisory — NÃO é o flip.**

## Persistência
- Git canon: #3109 + #3114 em `main`; este handoff via PR `docs/handoff-promocao-gates-required`.
- MCP: offline — sem task update (governança infra, não US/cycle).
- BRIEFING: n/a (módulo governance, sem tela tocada).

## Próximos passos pra retomar
Ler `memory/sessions/2026-06-20-promocao-gates-required.md` §4 (plano faseado). **Nada executável com segurança agora** — tudo gated por soak/flip [W]:
1. design-index always-run soakar alguns PRs → flip a required (protection + baseline no mesmo PR).
2. pixel-diff: esperar PRs de UI acumularem 2 verdes → remover `continue-on-error` (o check pai já é required, sem clique de protection).
3. Landar ADR 0275 (calendário sai de draft) + decidir demoção `module-grades` (0271 D-4).

## Lições catalogadas
- **Verificar soak ANTES de flipar:** pixel-diff não rodou em PR de UI nos últimos 60 runs (atividade recente toda governança/docs → skip-as-pass) → "2 verdes" da ADR 0261 não satisfeito → NÃO flipei. `job=success` ≠ step passou (`continue-on-error` mascara o step).
- **Working tree 509 atrás do main** → reads de `.yml`/`git log` estavam stale; re-verifiquei contra `origin/main` (gh api de protection/runs já era live). `git show ref:path` no Windows precisa `MSYS_NO_PATHCONV=1`.
- **`required` ≠ baseline ≠ ADR:** a11y required-vivo-fora-do-baseline; module-grades ADR-diz-required mas-não-no-vivo; dSIH required-sem-produtor. Os 3 mecanismos driftaram entre si — exatamente a doença da auditoria #3098/#3100.
- **Sessão paralela `arm-gates` ativa** (incidente de duplicata #3092) → trabalhei isolado (worktrees off origin/main, removidos) + pinguei nos corpos dos PRs. `node require` em path MSYS `/tmp` quebra → usar `D:/tmp` (path que bash+node+gh native concordam).

## Pointers detalhados
- Proposta/scorecard: `memory/sessions/2026-06-20-promocao-gates-required.md`
- Revert do dSIH: `gh api -X PATCH .../required_status_checks --input D:/tmp/rsc-backup.json` (ajustar p/ formato `{strict,checks}`)
- Padrão short-circuit replicável: `.github/workflows/a11y-axe-gate.yml` (ADR 0282 / #2885)
