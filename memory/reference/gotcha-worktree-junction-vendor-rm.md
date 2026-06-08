---
name: Gotcha — git worktree remove --force segue mklink junction e deleta vendor real
description: Junção Windows criada pra rodar Pest em worktree filho aponta pro vendor do repo pai. `git worktree remove --force` segue o link e deleta o vendor real, quebrando todo `php artisan` local. Custo recovery ~5min composer install.
type: reference
---

# Gotcha — `worktree remove --force` deleta vendor compartilhado

## Sintoma observado 2026-05-26

Após várias rodadas de `git worktree add` + `mklink /J vendor → D:/oimpresso.com/vendor` (pra rodar `vendor/bin/pest` em worktrees filhos sem reinstalar deps) + `git worktree remove --force` no fim:

```powershell
PS> php artisan route:list --path=comunicacao-visual
PHP Warning:  require(D:\oimpresso.com/vendor/autoload.php): Failed to open stream: No such file or directory
PHP Fatal error: Uncaught Error: Failed opening required 'D:\oimpresso.com/vendor/autoload.php'
```

`D:/oimpresso.com/vendor/` foi inteiramente deletado, não apenas a junção do worktree.

## Causa raiz

`mklink /J <target> <source>` no Windows cria **NTFS Directory Junction** (reparse point tipo `IO_REPARSE_TAG_MOUNT_POINT`). Quando uma ferramenta como `git worktree remove --force` (que internamente faz remoção recursiva da working tree) encontra esse reparse point, **segue o link e deleta o conteúdo apontado** em vez de só remover a junção.

Isso é diferente de:
- **Symlinks simbólicos** (`mklink /D`): mais ferramentas respeitam e removem só o link
- **Hard links** (`mklink /H`): não aplicável a diretórios

`--force` no `git worktree remove` ignora warnings sobre "modified or untracked files" mas não tem heurística pra detectar junções e tratar especialmente.

## Quando o risco aparece

Cenário canônico que provoca:

1. Criar worktree filho a partir de `origin/main`: `git worktree add <path> -b <branch> origin/main`
2. Worktree não tem `vendor/` (composer install não roda automático em worktree)
3. Pra rodar `vendor/bin/pest` ou `php artisan` no worktree, criar junção apontando pro vendor do repo pai: `cmd /c "mklink /J <worktree>/vendor <repo-pai>/vendor"`
4. Trabalho terminado, cleanup: `git worktree remove --force <path>`
5. **Vendor real do repo pai está deletado.**

## Prevenção

### Opção A — Remover junção ANTES do worktree remove

```powershell
# Remove APENAS a junção (não segue o link com este método):
Remove-Item D:\oimpresso.com\.claude\worktrees\<nome>\vendor -Force

# Verifica que sumiu mas vendor pai intacto:
Test-Path D:\oimpresso.com\.claude\worktrees\<nome>\vendor      # False
Test-Path D:\oimpresso.com\vendor\autoload.php                  # True

# Agora seguro:
git worktree remove --force D:\oimpresso.com\.claude\worktrees\<nome>
```

### Opção B — Worktree remove SEM `--force` (preferível)

Se o worktree não tem arquivos modificados/untracked importantes:

```powershell
git worktree remove D:\oimpresso.com\.claude\worktrees\<nome>
```

Sem `--force`, o git aborta se há mudanças não commitadas. Isso dá chance de inspecionar. Mas se há junção, **mesmo sem `--force` o `git worktree remove` pode seguir o link** — sempre testar com a Opção A primeiro.

### Opção C — Não usar junção (rodar composer no worktree)

```powershell
cd <worktree>
composer install --prefer-dist --no-progress
```

Custo: ~3-5min por worktree. Benefício: zero risco de drenar vendor compartilhado. Recomendado se worktree vai durar tempo significativo.

### Opção D — Usar symlink direcional ao invés de junction

```powershell
# Requer privilégio elevado (Administrator) ou Developer Mode ON:
cmd /c "mklink /D <worktree>\vendor D:\oimpresso.com\vendor"
```

Symlinks `/D` são mais "transparentes" pra ferramentas modernas (`git`, `Remove-Item`). Menos testado, mas teoricamente menos perigoso.

## Recovery quando acontece

```powershell
cd D:\oimpresso.com
composer install --prefer-dist --no-progress
# ~3-5min Hostinger SSD; depois `php artisan` volta a funcionar
```

CI no GitHub Actions **não é afetado** (cada job tem composer install próprio).

## Aprendizado meta

Wagner regra implícita: **agentes Claude que criam junções devem documentar e desfazer explicitamente antes de cleanup**. Adicionado ao [feedback-worktree-cleanup-junction-safe.md](feedback-worktree-cleanup-junction-safe.md) (se criar como feedback Tier).

## Refs

- Sessão de origem: [2026-05-26 sidebar canon cleanup](../sessions/2026-05-26-sidebar-canon-cleanup-comvis-fix.md) §Cronologia ~08:30 (vendor sumiu durante PR ComVis)
- Microsoft Docs: [mklink (NTFS Junctions vs Symlinks)](https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/mklink)
- Git Docs: [git-worktree(1)](https://git-scm.com/docs/git-worktree) — `--force` doc não menciona junctions
