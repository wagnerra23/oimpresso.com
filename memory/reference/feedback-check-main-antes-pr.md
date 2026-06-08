---
name: Sempre fetch + log origin/main..HEAD antes de abrir PR
description: Worktree de Claude pode estar baseada em commit antigo de main; check obrigatório evita duplicar trabalho do time e introduzir conflito
type: feedback
---
Antes de abrir PR (ou já no início da sessão), rodar:

```bash
git fetch origin main
git log --oneline origin/main..HEAD
git log --oneline HEAD..origin/main | head -10
```

**Why:** sessão 2026-05-10 noite (PR #475) — branch `claude/vigorous-meitner-972abb` foi criada em `ae735a32` que era anterior a:
1. [PR #466](https://github.com/wagnerra23/oimpresso.com/pull/466) (workflow YAML fix sem chars não-ASCII + sem migrate)
2. [PR #478](https://github.com/wagnerra23/oimpresso.com/pull/478) (guards canônicos SQLite em 6 arquivos)

Resultado:
- Meu commit `68d2cc67` reverteu o YAML fix de PR #466 (introduzindo bug que GitHub Actions rejeita)
- Meu trabalho em `ProducaoOficinaRefactorTest.php` colidiu com PR #478 já mergeado
- 2 merges adicionais necessários pra resolver

**How to apply:**
- Sempre que worktree não for `main` direto, primeiro check do gap antes de qualquer commit
- Se gap é grande (>5 commits desde divergência), considerar rebase em `origin/main` ou recriar worktree em commit mais novo
- Antes de tocar arquivos compartilhados (workflow, tests guards, scripts) check específico: `git log origin/main -- <arquivo>` pra ver se outro PR já tocou
