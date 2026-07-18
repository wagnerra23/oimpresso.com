---
date: "2026-07-18"
time: "20:51 BRT"
slug: merge-4518-forward-only-lapide-delecao-fantasma
tldr: "Mergeei o #4518 (governança forward-only nos templates/skills de memory) — não era o ITEM 7, era o PR da branch-base desta sessão. Conflito no governance-gate.yml resolvido a favor do main (filtro positivo [0-9]{4}- supersede o negativo do #4518). Lição catalogada: durante merge de branch MUITO atrás de main, git status + diff truncado mostram DELEÇÃO FANTASMA de arquivos que na verdade sobrevivem — verificar com merge-base --is-ancestor + cat-file -e antes de abortar."
prs: [4518]
decided_by: [W]
next_steps:
  - "Nenhum aberto desta sessão. (Dono do KB fecha o baseline module-grades — KB 77→76 é drift de 10 commits de KB, aparece advisory em todo PR cortado de main.)"
---

## Estado MCP no momento

MCP reconectou no fim, mas o snapshot foi por git (fallback). `main` @ `df3aaf8899`. Off-cycle. Sessão longa (ITEM 7 + este merge tangencial).

## O que aconteceu

Depois de fechar o ITEM 7 (handoffs 2146/2222/índice), [W] mandou "merge" e o ci-monitor trouxe o **#4518** — que **não é o ITEM 7**: é o PR da branch-base desta sessão (`claude/schema-forward-only-templates`), `fix(governance): fecha furos forward-only nos templates/skills de memory`. Estava **15+ commits atrás** de um main veloz, com conflito.

**Resolução:** o conflito era no `governance-gate.yml` — **o mesmo fix feito 2 vezes**. O #4518 usava filtro NEGATIVO (`grep -vE 'memory/handoffs/(_[^/]*|README)'`) pra excluir templates da checagem append-only; o main já tinha landado o filtro POSITIVO (`grep -E '[0-9]{4}-.+\.md$'`, casa só handoff real → exclui `_*` automaticamente). Fiquei com a versão do **main** (supersede, mais robusta). A parte de gate do #4518 virou redundante; landou o valor real = **7 arquivos** de template/skill.

Verificações antes de push: 0 marcador de conflito, override `module-grades-allowed-regression` justificado (KB 77→76 = drift pré-existente, 0 KB no diff). Auto-merge landou limpo (`635df6c4d0`).

## Lição catalogada — DELEÇÃO FANTASMA em merge de branch atrasada

**Sintoma:** ao `git merge origin/main` numa branch 15 commits atrás, o `git status` interino mostrou `D scripts/gen-mapa-telas.py` e um `git diff origin/main HEAD --stat` (two-dot, com `tail` truncando o topo) mostrou deleções de arquivos do main (`design-gate-bites.mjs -269` etc.). Parecia que o merge estava **apagando o main** — o gatilho exato da lição "worktree = deleção em massa".

**Realidade:** era FANTASMA. O merge não estava commitado ainda (HEAD = tip antigo da branch), então o two-dot diff comparava o tip velho vs main = mostrava a divergência inteira como "deleção". Depois do commit, o `main...HEAD` (three-dot) deu **7 arquivos, 0 deleção de arquivo do main**.

**Como provei (o padrão a repetir):**
- `git merge-base --is-ancestor origin/main HEAD` → **true** = main inteiro está no merge, nada perdido.
- `git cat-file -e HEAD:scripts/governance/design-gate-bites.mjs` → **existe** = arquivo do main sobreviveu.
- `git diff --name-status origin/main...HEAD | grep '^D'` (com main ATUAL, não stale) → **0**.

**O limite:** não abortar/entrar em pânico por `git status`/two-dot-diff DURANTE um merge não-commitado de branch atrasada — esses comparam contra o tip velho e fabricam deleções. Verificar com `--is-ancestor` + `cat-file -e` + three-dot `main...HEAD`. Família das lições `git ls-tree/grep <rev> escopam por cwd` e `MSYS mangleia revspec :path`. (Bônus reincidente nesta sessão: `git show <ref>:<path>` voltou vazio de novo por MSYS — ler do disco da worktree resolve.)

## Persistência

git (#4518 em main + este handoff) · MCP (webhook).

## Próximos passos pra retomar

Nada aberto. Se a deleção-fantasma reincidir, graduar esta lição pra `memory/reference/` (git-mechanics), como as irmãs cwd-scope/MSYS.

## Pointers

- #4518 squash `635df6c4d0` · `governance-gate.yml` linha ~64 (filtro positivo)
- Handoffs ITEM 7: 2026-07-17-2146 / 2026-07-17-2222
