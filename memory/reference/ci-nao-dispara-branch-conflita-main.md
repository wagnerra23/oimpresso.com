---
name: CI não dispara + mergeStateStatus UNKNOWN = branch conflita com main
description: PR com 0 check-runs e mergeStateStatus UNKNOWN significa que a branch conflita com main; GitHub não cria refs/pull/N/merge e nenhum workflow pull_request dispara. Fix = git merge origin/main
type: reference
---

# CI não dispara (0 check-runs) + `mergeStateStatus: UNKNOWN`

> Catalogado 2026-07-17 (sessão erp-ia-produto / US-COPI-141). Custou ~40min de diagnóstico errado (achei que era hiccup de evento do GitHub) antes de achar a causa real.

## Sintoma

Abri PR, mas:
- `gh pr checks <N>` → "no checks reported on the '<branch>' branch"
- `gh run list --branch <branch>` → **0 runs** pro sha novo (só o sha antigo tem runs)
- `gh api repos/OWNER/REPO/commits/<sha>/check-runs --jq '.total_count'` → **0**
- `gh pr view <N> --json mergeStateStatus` → travado em **`UNKNOWN`**

Empty commit, `gh pr close`/`reopen`, e até abrir **PR novo** (evento `opened`) — **nada** dispara o CI.

## Causa-raiz

A branch **conflita com `main`**. Os workflows do projeto disparam por `pull_request` (o `push:` cobre só `main`/`6.7-react`, ver ex. `phpstan-gate.yml`). O GitHub só roda `pull_request` contra o **merge commit** virtual (`refs/pull/N/merge`). Se há conflito de conteúdo, o GitHub **não consegue criar esse ref** → nenhum workflow nasce.

`mergeStateStatus: UNKNOWN` é o tell — não é `BLOCKED` (checks/review faltando) nem `DIRTY`; é o GitHub ainda tentando (e falhando) computar o merge.

## Como confirmar em 10 segundos

```bash
git fetch origin main
# arquivos que main mudou E eu também (desde o merge-base):
comm -12 \
  <(git diff --name-only $(git merge-base HEAD origin/main) origin/main | sort) \
  <(git diff --name-only $(git merge-base HEAD origin/main) HEAD | sort)
```

Se lista qualquer arquivo, é conflito. (`git merge-tree` também serve.)

## Fix

```bash
git merge origin/main          # NÃO force-push
# resolver preservando o trabalho do outro lado (append-only —
# lápide "reescritas-sem-lápide"; se colidiu número de US, renumere a SUA)
git add <todos os arquivos tocados>
git commit --no-edit
git push
```

O CI dispara na hora (o merge commit vira computável).

## Duas pegadinhas que me pegaram na mesma sessão

1. **Não confundir com saturação de fila do Actions.** Se fosse fila, os runs existiriam como `queued`, não ausentes. Eu vi 80 runs `queued` de uma sessão paralela no mesmo minuto e isso me despistou. Fila drena sozinha; conflito não.

2. **O merge commit pode congelar versão velha de arquivos que você editou DEPOIS de resolver o conflito.** Durante um merge conflict, arquivos não-conflitantes já estão staged. Se você editar MAIS arquivos depois (ex: `sed` renumerando US após colisão de número de US) e fizer `git commit --no-edit`, ele commita só o **index** → a edição nova fica unstaged e some. Sempre `git add` **todos** os arquivos tocados antes de fechar o merge, e valide `git show HEAD:<arquivo>` vs working tree. Peguei porque a suite rodou com o número velho.

## Relaciona

- Sessões paralelas na mesma branch (Wagner replica prompt em 2-3 sessões): a que **mergeia primeiro** é a que cria o conflito pra você. Checar `git log origin/main -3` + colisão de número de US antes de renumerar.
- `memory/how-trabalhar.md` §"Paralelização N agents" + §"whats-active".
