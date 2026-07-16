# PEGADINHA — `git worktree remove` (COM OU SEM `--force`) apaga o alvo real via junction (Windows)

> **Severidade:** alta — esvazia o alvo REAL do repo principal (`vendor/` 318MB, `node_modules/` ~700 pacotes) em segundos.
> **Plataforma:** Windows + git worktree + NTFS junction (`vendor/` **OU** `node_modules/`).
> **Quem caiu:** Claude Code (2026-05-11 `vendor/`, sessão JANA Pro Sprint A · 2026-07-14 `node_modules/`, sessão wizardly-sammet).
> **Status:** documentado, não resolvido em ferramentas — só evitação manual.
>
> ⚠️ **Correção 2026-07-14:** o `--force` **NÃO** é a causa. `git worktree remove` **sem `--force`** também segue a junction e esvazia o alvo real. E o furo vale pra **`node_modules/`** tanto quanto pra `vendor/`. A única defesa é remover a junction (com método robusto — não `Remove-Item -Force`/`rmdir` cru, que quebram por MSYS mangling) **e confirmar o alvo intacto** ANTES de `git worktree remove`.

---

## A armadilha

Quando crias um **worktree git** no Windows e o `vendor/` ainda não existe lá (gitignored), uma tentação comum é:

```powershell
# DENTRO do worktree, criar junction apontando pro vendor do main:
New-Item -ItemType Junction -Path '<worktree>\vendor' -Target 'D:\oimpresso.com\vendor'
```

Funciona perfeitamente pra rodar Pest sem reinstalar 162 packages (idem `node_modules` pro build front). Mas quando termina o trabalho:

```powershell
# Cleanup do worktree — PERIGOSO com junction ainda presente:
git worktree remove .claude/worktrees/<nome>          # SEM --force também esvazia o alvo!
git worktree remove .claude/worktrees/<nome> --force  # idem
```

**💀 BUG NTFS: o delete recursivo do `git worktree remove` interpreta a junction como pasta normal e DELETA O CONTEÚDO DO ALVO — com ou sem `--force`.**

Resultado: `D:\oimpresso.com\vendor\` (ou `D:\oimpresso.com\node_modules\`) fica **VAZIO**. App/build quebra com `class not found` (Laravel, Pest, laravel/ai, ...) ou `Cannot find module` (vite/react/...). Lockfile intacto, mas o alvo é zero bytes.

---

## Sintomas

- `php artisan ...` → `Class 'Illuminate\...' not found` (vendor) · `npm run dev`/`vite` → `Cannot find module` (node_modules)
- `ls vendor/` (ou `ls node_modules/`) → vazio (sem output)
- `du -sh vendor/` / `du -sh node_modules/` → `0B` ou `cannot access`
- Worktree foi removido (COM OU SEM `--force`) há minutos
- Junction (`vendor/` ou `node_modules/`) tinha sido criada no worktree

---

## Como recuperar

```powershell
# Reinstalar deps (no main worktree). vendor/ esvaziado:
Set-Location 'D:\oimpresso.com'
composer install --no-interaction --no-progress `
  --ignore-platform-req=ext-pcntl `
  --ignore-platform-req=ext-posix

# node_modules/ esvaziado (incidente 2026-07-14):
npm ci
```

Tempo: ~3-5min. Sem `composer update`/`npm install` que mexem em versão — usa `composer.lock` / `package-lock.json` intactos.

Verificação:
```bash
du -sh vendor/    # deve voltar ~318MB
ls vendor/laravel/ai/src/Providers/  # deve listar GroqProvider.php etc
# OU, se foi node_modules:
ls node_modules/react node_modules/vite  # deve listar conteúdo
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

Symlinks NTFS têm semântica diferente de junctions — `Remove-Item -Recurse` em symlink **não segue o link**. Mas `git worktree remove` (com ou sem `--force`) ainda pode pegar caminho diferente. **Não validado em produção.**

### Opção 4 — Junction OK + cleanup MANUAL ROBUSTO + verificação antes de remover worktree

⚠️ **Correção 2026-07-14:** a receita antiga (`Remove-Item '<worktree>\vendor' -Force` / `rmdir` cru) **quebra por MSYS path mangling** quando invocada pelo Git Bash — o `\` vira `/`, o comando falha silencioso e a junction **FICA presente**. Aí `git worktree remove` (mesmo sem `--force`) segue a junction e esvazia o alvo. Sequência correta (3 passos, sem pular a verificação):

```bash
# 1) Remover SÓ a junction, método robusto (um dos dois):
#    (a) PowerShell — mais confiável no Windows:
powershell -NoProfile -Command "(Get-Item '<worktree>\node_modules').Delete()"
#    (b) cmd via Git Bash, desligando o path mangling do MSYS:
MSYS_NO_PATHCONV=1 cmd //c "rmdir <worktree>\node_modules"

# 2) CONFIRMAR o alvo REAL intacto ANTES de qualquer worktree remove:
ls node_modules/react node_modules/vite   # (ou: ls vendor/laravel) — DEVE listar conteúdo
#    Se voltar vazio → a junction NÃO foi removida OU já esvaziou. PARE, não rode worktree remove.

# 3) Só ENTÃO remover o worktree:
git worktree remove '<worktree>'
```

✅ Passo (2) é a rede de segurança que faltava — se a remoção da junction falhou (mangling), o alvo vazio delata ANTES do `worktree remove` finalizar o estrago.
⚠️ Frágil — depende lembrar de fazer os 3 passos NA ORDEM. Por isso a **Opção 2** (deps próprias, zero junction) segue a mais segura.

---

## Recomendação canônica

**Pra worktrees curtos (≤1 sessão Claude Code):** use **Opção 2** (composer install próprio). Tempo de instalação se paga em peace-of-mind.

**Pra worktrees longos com muito vendor/node_modules (caso raro):** use **Opção 4** (junction + cleanup manual robusto + verificação ANTES do remove). Anotar no TODO do worktree: "antes de fechar, `(Get-Item link).Delete()` + `ls` do alvo real".

**NUNCA:** `git worktree remove` (COM OU SEM `--force`) com QUALQUER junction (`vendor/` ou `node_modules/`) ainda presente.

---

## Sinais de aviso

Antes de rodar `git worktree remove` (não adianta omitir `--force`), sempre cheque **ambas** as junctions:

```bash
# Tem junction de vendor OU node_modules?
for d in vendor node_modules; do
  test -L "<worktree>/$d" && echo "⚠️ JUNCTION $d DETECTADA — remove manualmente + confirma alvo primeiro"
done

# OU em PowerShell (por link):
(Get-Item '<worktree>\node_modules' -ErrorAction SilentlyContinue).Attributes -band [IO.FileAttributes]::ReparsePoint
```

Se positivo → remove a junction (método robusto da Opção 4), **confirma o alvo real intacto com `ls`**, e só então roda o worktree remove.

---

## Por que isso acontece (técnico)

Junctions NTFS são links no nível do diretório resolvidos pelo driver de filesystem. Diferente de symlinks Unix:

- Tools que respeitam `O_NOFOLLOW` ou `lstat()` veem a junction como link → não seguem
- Tools "ingênuas" (incluindo várias do Windows API antiga) seguem a junction transparentemente → tratam como pasta real

`git worktree remove` usa rotina de delete recursivo que **segue junctions** — o `--force` só pula checagens de "sujo/modificado", NÃO muda esse comportamento (por isso o incidente 2026-07-14 aconteceu SEM `--force`). Quando encontra `<worktree>\node_modules\react\...`, ele deleta arquivos LÁ — mas o caminho real é `D:\oimpresso.com\node_modules\react\...` (via junction). 💥 Idem `<worktree>\vendor\laravel\ai\...`.

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
- Sessão 2026-07-14 (wizardly-sammet) — `git worktree remove` **SEM `--force`** esvaziou o `node_modules` real (~700 pacotes → 0) com junction de node_modules presente; recuperado via `npm ci`. Provou: (1) `--force` não é a causa; (2) vale pra node_modules, não só vendor; (3) `Remove-Item -Force`/`rmdir` cru pelo Git Bash quebram por MSYS path mangling → junction fica → estrago
- [git docs — worktree remove](https://git-scm.com/docs/git-worktree#Documentation/git-worktree.txt-remove)
- [Microsoft Win32 — ReparsePoint](https://learn.microsoft.com/en-us/windows/win32/fileio/reparse-points)
- [memory/proibicoes.md](../proibicoes.md) §Ambiente — entrada relacionada
