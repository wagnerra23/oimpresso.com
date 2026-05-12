# PEGADINHA — `git worktree remove --force` apaga vendor original via junction (Windows)

> **Severidade:** alta — esvazia 318MB do `vendor/` do repo principal em segundos.
> **Plataforma:** Windows + git worktree + NTFS junction.
> **Quem caiu:** Claude Code (2026-05-11, sessão JANA Pro Sprint A).
> **Status:** documentado, não resolvido em ferramentas — só evitação manual.

---

## A armadilha

Quando crias um **worktree git** no Windows e o `vendor/` ainda não existe lá (gitignored), uma tentação comum é:

```powershell
# DENTRO do worktree, criar junction apontando pro vendor do main:
New-Item -ItemType Junction -Path '<worktree>\vendor' -Target 'D:\oimpresso.com\vendor'
```

Funciona perfeitamente pra rodar Pest sem reinstalar 162 packages. Mas quando termina o trabalho:

```powershell
# Cleanup do worktree:
git worktree remove .claude/worktrees/<nome> --force
```

**💀 BUG NTFS: o `--force` interpreta a junction como pasta normal e DELETA O CONTEÚDO DO ALVO.**

Resultado: `D:\oimpresso.com\vendor\` fica **VAZIO**. App em produção local quebra com `class not found` em quase tudo (Laravel, Pest, laravel/ai, ...). Composer.lock intacto, mas vendor é zero bytes.

---

## Sintomas

- `php artisan ...` → `Class 'Illuminate\...' not found`
- `ls vendor/` → vazio (sem output)
- `du -sh vendor/` → `0B` ou `cannot access`
- Worktree foi removido com `--force` há minutos
- Junction tinha sido criada no worktree

---

## Como recuperar

```powershell
# Reinstalar deps (no main worktree):
Set-Location 'D:\oimpresso.com'
composer install --no-interaction --no-progress `
  --ignore-platform-req=ext-pcntl `
  --ignore-platform-req=ext-posix
```

Tempo: ~3-5min (depende cache global composer). Sem `composer update` — usa `composer.lock` intacto.

Verificação:
```bash
du -sh vendor/    # deve voltar ~318MB
ls vendor/laravel/ai/src/Providers/  # deve listar GroqProvider.php etc
```

---

## Como evitar — 4 opções em ordem de segurança

### Opção 1 — NÃO usar junction. Rodar Pest do main worktree

Se o worktree é só pra editar arquivos e validar via Pest, **edita no worktree mas roda Pest no main worktree**:

```bash
# Stash worktree files into main, run Pest, revert (NÃO RECOMENDADO — drift)
```

❌ Risco de drift entre worktree e main. Não recomendo.

### Opção 2 — Composer install próprio no worktree (mais seguro)

```powershell
Set-Location '<worktree>'
composer install --no-interaction --no-progress `
  --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix
```

✅ Vendor próprio. Cleanup `git worktree remove` é seguro (deleta só o do worktree).
❌ Custo: ~3-5min por worktree. Espaço: +318MB cada.

### Opção 3 — Symlink **explícito** (não junction)

```powershell
# PowerShell elevado:
New-Item -ItemType SymbolicLink -Path '<worktree>\vendor' -Target 'D:\oimpresso.com\vendor'
```

Symlinks NTFS têm semântica diferente de junctions — `Remove-Item -Recurse` em symlink **não segue o link**. Mas `git worktree remove --force` ainda pode pegar caminho diferente. **Não validado em produção.**

### Opção 4 — Junction OK + cleanup MANUAL antes de remover worktree

```powershell
# ANTES de git worktree remove:
Remove-Item '<worktree>\vendor' -Force    # remove só a junction, não o alvo
# AGORA sim:
git worktree remove '<worktree>'   # sem --force
```

✅ Comprovado seguro nesta sessão (testei em outro worktree depois).
⚠️ Frágil — depende lembrar de fazer ANTES. Esquecer = bug.

---

## Recomendação canônica

**Pra worktrees curtos (≤1 sessão Claude Code):** use **Opção 2** (composer install próprio). Tempo de instalação se paga em peace-of-mind.

**Pra worktrees longos com muito vendor (caso raro):** use **Opção 4** (junction + cleanup manual ANTES do remove). Anotar no TODO do worktree: "antes de fechar, `Remove-Item vendor`".

**NUNCA:** `git worktree remove --force` com junction ainda presente.

---

## Sinais de aviso

Antes de rodar `git worktree remove --force`, sempre cheque:

```bash
# Tem junction de vendor?
test -L <worktree>/vendor && echo "⚠️ JUNCTION DETECTADA — remove manualmente primeiro"

# OU em PowerShell:
(Get-Item '<worktree>\vendor' -ErrorAction SilentlyContinue).Attributes -band [IO.FileAttributes]::ReparsePoint
```

Se positivo → **NÃO use `--force`**. Remove junction primeiro, depois worktree.

---

## Por que isso acontece (técnico)

Junctions NTFS são links no nível do diretório resolvidos pelo driver de filesystem. Diferente de symlinks Unix:

- Tools que respeitam `O_NOFOLLOW` ou `lstat()` veem a junction como link → não seguem
- Tools "ingênuas" (incluindo várias do Windows API antiga) seguem a junction transparentemente → tratam como pasta real

`git worktree remove --force` usa rotina de delete recursivo que **segue junctions**. Quando encontra `<worktree>\vendor\laravel\ai\...`, ele deleta arquivos LÁ — mas o caminho real é `D:\oimpresso.com\vendor\laravel\ai\...` (via junction). 💥

[Stack Overflow tem ~50 threads do mesmo bug.](https://stackoverflow.com/search?q=git+worktree+windows+junction+deleted) Solução upstream do git: nenhuma — pull requests pendem há anos.

---

## Quem mais pode cair

Qualquer dev do time no Windows usando worktrees:
- Wagner [W]
- Maiara [M]
- Felipe [F]
- Luiz [L+C] — risco maior (iniciante)
- Eliana [E] — risco médio (advogada + dev IA)

Linux/macOS **não tem esse bug** (filesystem diferente, sem junctions NTFS).

---

## Referências

- Sessão 2026-05-11 — JANA Pro Sprint A US-COPI-202 — `worktree remove --force` esvaziou vendor após criar junction pra rodar Pest
- [git docs — worktree remove](https://git-scm.com/docs/git-worktree#Documentation/git-worktree.txt-remove)
- [Microsoft Win32 — ReparsePoint](https://learn.microsoft.com/en-us/windows/win32/fileio/reparse-points)
- [memory/proibicoes.md](../proibicoes.md) §Ambiente — entrada relacionada
