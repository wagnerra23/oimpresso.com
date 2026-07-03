---
date: "2026-07-03"
time: "16:03 BRT"
slug: safe-merge-guard-anti-desync
tldr: "Guard de merge anti-desync headRefOid: scripts/gh/safe-merge.sh (sha-pinned + rede pós-merge) + disciplina Claude na skill commit-discipline. Dogfooded 2×; o dogfood pegou e corrigiu um falso-positivo MSYS na própria rede. 2 PRs (#3768 guard + #3769 fix)."
prs: [3769, 3768]
decided_by: [W]
related_adrs: [0271-revisao-gates-ci-estado-real-required-e-subtracao-segura, 0256-knowledge-survival-meia-vida-catraca-sentinela]
next_steps: ["Show/Edit/Create casos_coverage 0%: PR de contrato .casos.md por-tela citando o Pest já existente (G-2: UC+teste no mesmo PR) tira do 0%", "Board UI gaps (no scorecard, não-bloqueio): bg-white→bg-card · densidade 6 KPIs 1280px · confirmar KeyboardSensor dnd-kit", "d1 🔴 final_total=0 OS mecânica (US-OFICINA-027) — é o outro chip (cálculo), segue aberto lá", "opcional: safe-merge.sh deletar a branch pós-merge (hoje depende do auto-delete do repo; gh api merge não deleta)"]
---

# Handoff — Guard de merge anti-desync (safe-merge.sh)

> Fecha a pergunta do [W] "como seria o guard? vai crescer sim" (fim da sessão da régua OficinaAuto).
> Escolha [W]: **Script + disciplina Claude**.

## Estado MCP no momento do fechamento
- **cycles-active:** nenhum cycle ATIVO (trabalho de tooling/governança, off-cycle).
- **my-work (@wagner):** 30 tasks — nenhuma deste trabalho (tooling ad-hoc, sem task MCP).
- **Sessões paralelas ativas:** worktree `fin-regua-cr-cp` (Financeiro régua CR/CP) — **não tocado**.

## O que aconteceu
Origem: nesta mesma sessão, o handoff da régua OficinaAuto **sumiu de main** por desync do GitHub (o squash-merge do #3763 usou `headRefOid` stale → o commit do handoff nunca landou; mesmo padrão do #3732). [W] pediu o guard, ciente de que o time MCP vai crescer.

- **#3768** — `scripts/gh/safe-merge.sh` (2 camadas) + seção **"Merge seguro"** na skill `commit-discipline` (Tier A) + reference `feedback-merge-desync-headrefoid.md`.
- **#3769** — fix do post-check: o **dogfood** (safe-merge mergeando a si mesmo) provou a camada 1 (sha-pin: 3/3 arquivos landaram) mas a camada 2 deu **falso "AUSENTE"** — `git cat-file -e origin/main:$path` sofre **MSYS colon-mangling** no Git-Bash. Trocado por `git ls-tree origin/main -- "$path"`. Re-dogfoodado limpo.

## O guard (em main)
`scripts/gh/safe-merge.sh <PR> [squash|merge|rebase]`:
1. **Prevenção atômica** — pré-check `headRefOid==HEAD` + merge via `gh api PUT .../merge -f sha=$LOCAL` → servidor **409s se o head mexeu** (mata o desync).
2. **Rede pós-merge** — confere via `ls-tree` (MSYS-safe) que os arquivos add/mod do PR estão em `origin/main`.

Disciplina: `commit-discipline §"Merge seguro"` → todo `gh pr merge` vira `safe-merge.sh`. Camada humana pra UI: F5 antes de clicar merge.

## Artefatos gerados
- `scripts/gh/safe-merge.sh` (novo, exec 755) — #3768 + fix #3769
- `.claude/skills/commit-discipline/SKILL.md` — seção "Merge seguro" (#3768)
- `memory/reference/feedback-merge-desync-headrefoid.md` — incidente + guard + dogfood (#3768/#3769)

## Verificação
- Dogfood v1 (#3768): sha-pin OK; rede deu falso-positivo → achou o bug.
- Dogfood v2 (#3769): sha-pin OK + rede limpa (`✓ todos os arquivos presentes`). Confirmado em main com `ls-tree` (MSYS-safe).
- CI verde nos 2 PRs.

## Lições catalogadas
- **Detecção pós-merge é cega pro desync quando a causa é o GitHub com estado velho** (a API de files responde o head velho). A garantia é ANTES: pinar o SHA. Gate de CI pós-merge aqui seria a "suíte que mente" (ADR 0271).
- **A rede tem que ser tão confiável quanto o guard.** O dogfood pegou um falso-positivo MSYS na própria rede — um alarme falso corrói a confiança igual a um falso-negativo. Sempre dogfoodar tooling de guard.
- **MSYS colon-mangling reincide** (auto-mem `licao-msys-revspec-colon-mangling`): NUNCA `git <cmd> <ref>:<path>` em script cross-platform — usar `<cmd> <ref> -- <path>` ou `MSYS_NO_PATHCONV=1`.

## Ficou em aberto (loose ends honestos)
- **Show/Edit/Create casos_coverage 0%** — próximo PR natural: `.casos.md` por-tela citando o Pest que já existe (tira do 0% sem tocar a tela).
- **Board UI gaps** (no scorecard, não-bloqueio): `bg-white`→`bg-card`; densidade 6 KPIs no 1280px; confirmar `KeyboardSensor` do dnd-kit.
- **d1 🔴 final_total=0 OS mecânica** (US-OFICINA-027) — é o **outro chip** (cálculo), segue aberto lá, fora deste escopo.
- **safe-merge.sh não deleta a branch** pós-merge (o repo auto-deleta hoje; num repo sem esse setting, branches acumulariam) — melhoria opcional.
- **Camada humana (UI F5) não é mecanicamente enforçada** — política, não gate. Aceito: ~todo merge aqui é agent-driven (coberto pela camada 1).

## Pointers detalhados (on-demand)
- PRs: https://github.com/wagnerra23/oimpresso.com/pull/3768 · https://github.com/wagnerra23/oimpresso.com/pull/3769
- Guard: `scripts/gh/safe-merge.sh` · reference: `memory/reference/feedback-merge-desync-headrefoid.md`
- Handoffs irmãos desta sessão: régua+Board `2026-07-03-1521-oficinaauto-board-grade-fecha-regua.md`
