---
date: "2026-06-20"
topic: "Forense: branch 'órfã' que era artefato de clone shallow — recuperação sem perda + 2 lições git (shallow mente o merge-base; MSYS mutila rev:path)"
type: session
authors: [W, C]
---

# Forense — branch "órfã" `feat/governance-ds-rollout-ledger` era ilusão de clone shallow

## TL;DR

Uma branch aparentou ser **órfã catastrófica** (`merge-base` vazio com `main`, "337 commits", diff de 1735 arquivos / +425k / 304 "deletes"). Forense adversarial (8 agentes) + `git fetch --unshallow` provaram que **não era órfã**: era **artefato de clone shallow**. O `.git/shallow` cortava a aresta de parent do ancestral comum real (`a5b1a0d5` = squash da PR #2224), fazendo o `merge-base` mentir. Pós-unshallow: ramo lateral normal, **26 ahead / 508 behind**, só 19 commits únicos, **98% já em `main`** por outras PRs, **zero perda de dados**. Resíduo genuíno (24 docs) salvo no main via [PR #3107](https://github.com/wagnerra23/oimpresso.com/pull/3107); branch superseded aposentada (preservada em `backup/orphan-ds-rollout-52aa4a0f6` + tag `safety/pre-recovery-2026-06-20-ds-rollout`, local + remoto).

## O incidente (timeline)

1. Pedido inicial: "compare todos os arquivos modificados no git". `git status` só mostrava untracked; `git diff main...HEAD` → **`fatal: no merge base`**.
2. Diagnóstico precipitado (ERRADO): "branch órfã, história disjunta, possível near-miss tipo `--no-checkout` mass-delete".
3. **Rede de segurança PRIMEIRO** (regra #1 de recuperação): `git branch backup/...` + `git tag safety/...` apontando pro tip — antes de tocar em qualquer coisa.
4. Forense (workflow 8 agentes, verificação adversarial) achou a verdade: **shallow clone**, não órfã.
5. `git fetch --unshallow origin` restaurou o `merge-base` real (`b04b1e41`, #2562, 11/jun).
6. Comparação honesta: 19 únicos, 98% superseded. Salvar resíduo + aposentar branch.

## Diagnóstico real (provas)

- `git rev-parse --is-shallow-repository` → **true**; `.git/shallow` existia (3 boundaries: `a5b1a0d5`, `dfec2f46`, `ef2a10c8`).
- `git cat-file -p a5b1a0d5` mostrava header `parent ...` MAS `git log --format=%P a5b1a0d5` retornava **vazio** → aresta cortada pelo shallow boundary.
- `git replace -l` **vazio** → não era graft sintético; falsificação 100% do shallow.
- Pós-unshallow: `merge-base` deixou de ser vazio; "337 órfãos" colapsaram em **26 ahead / 508 behind**; `git cherry` = 19 `+` / 7 `-`.
- Perda de dados: os 305 "D" do diff 2-dot eram arquivos que o **`main` adicionou** pós-divergência (Errors framework, TeamMcp Forja, Jana audit-chain, dark bridge). Teste decisivo: união de TODOS os paths tocados nos 337 commits ∩ os 305 D = **0**.

## Lições reutilizáveis

### L1 — `merge-base` vazio + "N órfãos = total" = assinatura de clone SHALLOW, não de órfã real

Sintomas que parecem catástrofe mas são o shallow mentindo:
- `git merge-base A B` vazio (exit 1) entre branches que deveriam ter ancestral comum.
- `git rev-list --count main..HEAD` == total de commits da branch (todo commit "único").
- `git diff main HEAD` (2-dot) com milhares de arquivos, a maioria como `A`/`D` (parece que tudo foi adicionado/removido).
- `git rev-list --max-parents=0 --all` lista várias "roots" (são fronteiras shallow, não roots verdadeiras).

**Antes de cunhar pânico, rode `git rev-parse --is-shallow-repository`.** Se `true`:
1. `git fetch --unshallow origin` (read-only quanto a refs/working-tree; só adensa o object store + reescreve `.git/shallow`).
2. Recalcule `merge-base` / ahead-behind / `git cherry` — quase sempre colapsa numa relação normal.
3. NÃO faça `rebase --onto --root`, squash-restore, nem `git replace --graft` antes do unshallow — todos reaplicam patches já em `main` (squash-twins) e geram conflito ALTO inútil.

`git replace -l` vazio confirma que a falsificação é o shallow, não um graft sintético. Conecta com [[licao-no-checkout-worktree-mass-delete]] (outro modo de falsa-órfã, esse REAL) — diferenciar pela evidência mecânica.

### L2 — git-bash/MSYS no Windows mutila o argumento `<rev>:<path>` (falso "missing")

`git cat-file -e "origin/main:.claude/workflows/x.js"` → o MSYS converte `:`→`;` e `/`→`\` (vira `origin\main;.claude\workflows\x.js`) → revisão inválida → **falso-negativo** "arquivo não existe". Atinge especialmente paths começando com `.` (ex.: `.claude/`).

**Checagem de existência à prova de mangling** (sem `:path` no argumento):
```bash
git ls-tree -r origin/main --name-only | grep -qx ".claude/workflows/x.js" && echo OK
```
Conecta com [[licao-git-lstree-grep-rev-cwd-scope]]. Regra geral: pra existência/escopo de arquivo num `<rev>`, prefira `ls-tree -r <rev> --name-only | grep` a `cat-file/show "<rev>:<path>"` no git-bash Windows.

## Receita de recuperação (ordem)

1. **Snapshot primeiro** — `git branch backup/...` + `git tag safety/...` no tip (local; push a tag pra preservação remota). Nada mais pode perder o trabalho.
2. `git fetch --unshallow origin` — corrige a causa-raiz.
3. Reanálise honesta (`merge-base`, `cherry`, diff 3-dot `...`).
4. Se branch superseded: salvar só o resíduo genuinamente ausente (`comm -23` entre o que a branch tocou e o que `main` tocou) via PR limpo de uma worktree dedicada (não brigar pelo working-tree compartilhado de sessões paralelas).
5. Aposentar a branch (preservada pelo backup+tag).

## Refs

- PR salvamento: [#3107](https://github.com/wagnerra23/oimpresso.com/pull/3107) (squash `9ac8582`).
- Preservação: branch `backup/orphan-ds-rollout-52aa4a0f6` + tag `safety/pre-recovery-2026-06-20-ds-rollout` (`52aa4a0f6`, local + remoto).
- Lições relacionadas: [[licao-no-checkout-worktree-mass-delete]], [[licao-git-lstree-grep-rev-cwd-scope]], [[licao-crlf-python-writes]].
