# Sessão 2026-07-09 — Profissionalizar o DS: fechar o loop git↔espelho (P2-P4 + conclusão)

**Worktrees:** `ds-sync-loop` (P2/P3/P4) + `ds-loop-fim` (conclusão). **Base:** `origin/main` fresco (a sessão nasceu −4932 stale — trabalhei sempre a partir de `origin/main`). **Cycle:** off-cycle.

## Contexto

Continuação da profissionalização do DS. A **P1** (reconciliar o canvas dark, sessão paralela `busy-edison-daa2a0`) mergeou primeiro (#3981 FASE1 opção C hue 240 + #3982 FASE2 + #3983). Esta sessão fez **P2-P4** e depois **concluiu o loop** (primeiro push git→espelho).

**Descoberta que reorientou o escopo:** a P1 (#3982) já tinha entregue metade do que o pedido P2/P3 pedia — `design-sync-pull.md` (pull design→git com triagem) + o motor `ds-token-diff.mjs`. Então não dupliquei: construí sobre isso.

## O que foi feito

### P2 — `design-sync-push.md` (PR #3990, MERGED)
Runbook da perna **git→espelho** do loop (o par do pull da P1). Re-espelho incremental via `DesignSync finalize_plan/write_files` (nunca replace atacado), transform determinístico dos `_generated-*.css`, refresh do snapshot do sentinela. + pointer no `design-sync.md` antigo desambiguando os **3 syncs** (design→código × pull espelho→git × push git→espelho).

### P3 — sentinela `ds-mirror-drift` (PR #3991, MERGED)
`scripts/governance/ds-mirror-drift.mjs` — advisory, **reusa o motor `ds-token-diff.mjs`** da P1 via `--json`. **Restrição resolvida:** o CI do GH Actions não tem login claude.ai → não pode chamar `DesignSync`; então compara o git contra um **snapshot commitado** do espelho + baseline (o diff contra o espelho vivo roda local/cron). Advisory primeiro (política ADR 0314 = required só Tier-0). + workflow `ds-mirror-drift.yml` + baseline + `mirror-snapshot/README.md`. **Fix de CI:** workflow novo exige registro no `gates-registry.json` (memory-health Check G) — registrei (classe gate, advisory, promote_by 2026-07-23).

### P4 — proposta de transição (PR #3992, MERGED)
`memory/decisions/proposals/2026-07-09-ds-transicao-congelado-para-vivo-git-ssot.md` — a ADR (a numerar por [W]) da transição **"DS v6 congelado → projeto vivo, git ainda SSOT"**. Consolida a **emenda 0315 (D-1 design→git)** da proposta-irmã 2026-07-08-ds-direcao num ADR único de loop; D-2 (sidebar preto-fixa) e D-3 (valores dark) ficam pra UI-ADR à parte. Ref 0239/0249/0300/0315/0325/0281.

### Conclusão do loop — primeiro push git→espelho (PR #3997)
1. `ds-mirror-build.mjs` (o "future automation" que o runbook previa) — montador determinístico: pega o `colors_and_type.css` do espelho e troca **só o valor** dos tokens compartilhados pelos do git, preservando scaffold.
2. **Reconciliação validada:** `ds-token-diff` **BEFORE 19 divergências (todas dark, batendo com o "28→19" da proposta-direção) → AFTER 0**, scaffold intacto (mesmas 423 linhas).
3. **Push executado** via `DesignSync write_files` (opt-in [W] "pode passar" + `.design-sync-allow`, incremental). **Verificado no espelho vivo:** `.dark` agora = git (`--color-background 0.26/240`, `--color-primary 0.7/295`, `--color-card 0.30/240`…). **As 3 cópias do canvas dark viraram 1.**
4. **Sentinela vivo:** snapshot = espelho reconciliado, baseline = 0 → `drift 0 · ✓`.
5. **Cosmético:** header do `README.md` do espelho atualizado (commit-fonte `5390c5a2cd8f → f197e39abc` + parágrafo "living mirror / loop"). Opt-in re-trancado após uso.

## Lições

- **Sessão paralela viva = coordenar, não assumir.** A P1 rodava na hora; esperei ela mergear (gate explícito) e construí sobre o que ela entregou, em vez de duplicar `ds-token-diff.mjs`/pull runbook.
- **CI não alcança o espelho.** GH Actions sem login claude.ai → sentinela de drift precisa de snapshot commitado (o diff vivo é local/cron). Padrão reusável pra qualquer gate contra recurso externo autenticado.
- **Push git→espelho é o inverso exato do `ds-token-diff`** — deu pra escrever o `ds-mirror-build` determinístico (troca só valores compartilhados) e validar com o próprio diff (BEFORE 19 → AFTER 0). Nunca montar o espelho à mão.
- **Hook de publicação bloqueia por design** (`block-design-sync-without-optin`): checa `.design-sync-allow` no `process.cwd()` da SESSÃO (não em roots arbitrários). Não self-bypass — pedi opt-in explícito ([W] "pode passar"), usei, re-tranquei.
- **Classificador de Bash/Write oscilou muito** nesta sessão (infra) — retry funcionou; rascunhei no scratchpad enquanto caía.

## Pendências
- Mergear **#3997** (conclusão git-side).
- **Numerar/aceitar** a proposta P4 → vira ADR em `memory/decisions/`.
- D-2 (sidebar preto-fixa) + D-3 (valores dark) da proposta-irmã seguem aguardando [W] numerar (UI-ADR à parte — fora do meu escopo).
