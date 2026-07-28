---
title: "RUNBOOK — sessões Claude Code paralelas via git worktree"
owner: W
status: ativo
last_validated: "2026-06-08"
---

# RUNBOOK — sessões Claude Code paralelas via git worktree

> **Problema:** 2-3 sessões Claude Code abertas simultâneas em `D:\oimpresso.com\` se atrapalham — commits caem na branch errada, `git add` captura arquivos de sessão vizinha, branch muda sem aviso.
>
> **Solução:** cada sessão paralela vive em seu próprio diretório (worktree git) com branch isolada. Convenção `.claude/worktrees/<nome>` (já gitignored — CLAUDE.md).

## Quando usar

- Vai abrir mais de 1 sessão Claude Code ao mesmo tempo
- Cada sessão vai mexer em escopos diferentes (autopecas, arquivos, vestuario, etc.)

Sessão única ou trabalho serial: **não precisa**, abre direto em `D:\oimpresso.com`.

## Fluxo (3 passos)

### 1. Criar worktree antes de abrir a sessão

PowerShell na raiz do repo:

```powershell
.\tools\new-claude-session.ps1 -Name autopecas
```

Saída mostra o `cd` exato pra colar.

### 2. Abrir Claude Code naquele path

```powershell
cd 'D:\oimpresso.com\.claude\worktrees\autopecas'
claude
```

A partir daí, essa janela do Claude Code está **isolada** — branch `claude/autopecas`, working tree próprio, não afeta as outras sessões.

### 3. Limpar quando terminar

Após PR mergeado:

```powershell
cd D:\oimpresso.com
git worktree remove .claude\worktrees\autopecas
```

Branch já foi removida pelo merge (squash), não precisa apagar manual.

## Listar tudo que está rodando

```powershell
.\tools\list-claude-sessions.ps1
```

Mostra cada worktree, branch, último commit, e quantos arquivos têm mudança não-commitada. Útil pra:
- Confirmar que as 3 sessões estão em branches diferentes
- Identificar worktrees órfãos pra limpar

## Convenções

- **Nome do worktree** = nome da branch sem o prefixo `claude/`. Exemplo: branch `claude/autopecas-charter` ⇒ worktree `.claude/worktrees/autopecas-charter`.
- **Base default** = `origin/main`. Pra basear em outra branch: `-Base origin/release-2026-q2`.
- **Não usar** mais que 5 worktrees simultâneos (limite prático — cada um copia node_modules/vendor se rodar build).

## Pegadinhas

- ⚠️ **Composer/npm install** roda em CADA worktree (são working trees separados). Pra evitar duplicação de `vendor/`, use o mesmo `composer install` cache do main.
- ⚠️ **Migrations** — não rode `php artisan migrate` em 2 worktrees ao mesmo tempo (banco MySQL é compartilhado).
- ⚠️ **`.env`** não é compartilhado entre worktrees por padrão — copie do principal se a sessão precisar.

## Ver também

- [git worktree docs](https://git-scm.com/docs/git-worktree)
- CLAUDE.md §"Local repos" — convenção `.claude/worktrees/<nome>`
- `tools/new-claude-session.ps1` + `tools/list-claude-sessions.ps1`
