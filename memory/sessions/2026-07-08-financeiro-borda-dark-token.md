# Sessão 2026-07-08 — Financeiro borda dark: era token, não hardcode (ADR UI-0022, PR #3958)

## Objetivo
Fechar o `borderColor 56/57 ⚠ SISTEMÁTICO` que o `style-fingerprint.mjs` acusou no dark do Financeiro/Unificado (próximo alvo do handoff 10:44).

## Método (disciplina do handoff: medir ao vivo antes de deployar)
1. **Base stale guard** — o checkout inicial estava 4908 commits atrás de `origin/main`. Criei worktree fresca de `origin/main` (`git worktree add -b claude/fin-surface-border-sweep ... origin/main`) antes de tocar em qualquer coisa.
2. **Análise estática** — grep dos CSS: as bordas usam `var(--fin-line)` (Financeiro), `var(--border)` (cockpit) e utility `border-border` (`--color-border`). O valor medido `oklch(0.30 0.012 282)` só existe nos tokens (`_generated-*.css`), nunca hardcoded em `resources/css` (grep vazio).
3. **Medição ao vivo (browser MCP em prod dark, logado)** — sonda DOM:
   - Distribuição de `borderTopColor`: `oklch(0.30 0.012 282)` domina com **575 lados**.
   - **Sentinela por-var** (pintar `--color-border`/`--border`/`--fin-line` de cores distintas e recontar): as 575 NÃO moveram quando setei na RAIZ → pareciam hardcoded.
   - **Sentinela de propagação** (setar valor único na raiz e ler a var em cada ancestral): FLIPOU de volta a 0.30 **no `.cockpit`** → `.cockpit` re-define a var.
   - **Causa:** `.cockpit.matches('[data-theme="dark"]') === true` → a regra `.dark,[data-theme=dark]{--color-border}` casa `.cockpit` também e sombreia override só-na-raiz.
   - **Prova do fix:** setar `--color-border`+`--border` a `0.335` nos níveis certos → `575 → 0` no velho, `575` no alvo (+49 do `--fin-line` = 624).

## Resultado
- **Diagnóstico corrigido:** É TOKEN, não hardcode. O handoff 10:44 ("não é fix de token, é sweep") estava invertido — a var só parecia morta pelo shadowing do `.cockpit[data-theme=dark]`.
- **Fix (PR #3958, mergeado):** DTCG `semantic.tokens.json` border/input/cockpit-border dark `0.30→0.335` + `tokens:build`. App-wide/fundação (ADR **UI-0022**, igual UI-0021). Equivalência 296/296. CI 74 pass / 0 fail. Wagner: "merge e salve tudo".
- **Artefatos:** ADR UI-0022 (accepted); round 2026-07-08 apendado no `financeiro-unificado-visual-comparison.md`.

## Follow-ups (fora deste PR, 1 PR = 1 intent)
- **Superfície** (`bgEfetivo 56/57`): topo achata em `0.165`; proto quer painel `~0.238` atrás do filtro (tabela já tem `0.205`). Precisa do proto Cowork rodando pra cravar container+valor → PR próprio.
- **`--fin-line`** (49 lados claros/frios sem override dark, Financeiro-scoped, cascade-sensível).
- **Pós-deploy:** VRT baselines dark modo UPDATE + smoke real (fingerprint `--compare`).

## Meta-nota
Boa disciplina: a medição ao vivo (sentinela por-var + sentinela de propagação) desfez uma misdiagnose herdada em vez de repeti-la. O hook `block-askq-execution-menu` barrou uma pergunta de escopo — segui a recomendação (app-wide) e Wagner validou no merge.
