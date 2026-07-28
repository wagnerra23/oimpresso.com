---
id: reference-gotcha-worktree-junction-vendor-rm
name: Gotcha — git worktree remove segue junction do Windows e apaga o vendor/node_modules real
description: No Windows, `git worktree remove` (COM OU SEM `--force`) segue a junction NTFS criada no worktree e apaga o conteúdo REAL apontado no repo principal — vendor/ ou node_modules/. Remover a junction antes, com método que o MSYS não mangleia, é a única defesa. Recovery ~3-5min.
type: reference
authority: canonical
lifecycle: ativo
updated_at: '2026-07-28'
---

# Gotcha — `git worktree remove` apaga o alvo real via junction (Windows)

> ⚠️ **Correção de 2026-07-14 — a versão anterior deste doc estava errada.** Ela atribuía a causa ao `--force` e recomendava "remover sem `--force`" como caminho seguro. **Não é seguro:** o `git worktree remove` sem flag também segue a junction. Foi exatamente esse conselho que esvaziou o `node_modules/` real em 2026-07-14.
>
> **Detalhe completo, com recovery passo a passo e as duas ocorrências:** [`memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md`](../requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md). Este doc é o resumo indexado; aquele é o dono do tema.

## O que acontece

`New-Item -ItemType Junction` (ou `mklink /J`) cria uma **NTFS Directory Junction** — reparse point do tipo `IO_REPARSE_TAG_MOUNT_POINT`. Quando o `git worktree remove` faz a remoção recursiva da working tree e encontra esse reparse point, ele **atravessa o link e apaga o conteúdo apontado**, em vez de remover só a junction.

O alvo apontado é o do repositório principal. Resultado observado nas duas ocorrências: `vendor/` (318 MB) e `node_modules/` (~700 pacotes) zerados em segundos, e todo `php artisan` / build de front quebrado.

**O `--force` não é a causa.** Ele só ignora avisos sobre arquivos modificados ou untracked; não tem nada a ver com atravessar reparse points. Remover a flag não protege.

## Quando o risco aparece

O cenário que provoca, e é comum:

1. `git worktree add <path> -b <branch> origin/main` — o worktree nasce sem `vendor/` e sem `node_modules/` (ambos gitignored).
2. Para rodar `vendor/bin/pest` ou `npm run build` sem reinstalar dependências, cria-se uma junction apontando para o repo principal.
3. Trabalho termina, cleanup: `git worktree remove <path>` — com ou sem `--force`.
4. O `vendor/` ou `node_modules/` **do repositório principal** está vazio.

## Sequência segura (a única que funciona)

Remover a junction **primeiro**, confirmar que o alvo sobreviveu, e só então remover o worktree:

```powershell
# 1. Remover APENAS a junction — método robusto
(Get-Item 'D:\oimpresso.com\.claude\worktrees\<nome>\vendor').Delete()
```

```bash
# 1-alt. Pelo Git Bash, desarmando o path mangling do MSYS:
MSYS_NO_PATHCONV=1 cmd //c "rmdir D:\oimpresso.com\.claude\worktrees\<nome>\vendor"
```

```powershell
# 2. Confirmar que o alvo REAL continua intacto (não pule este passo)
Test-Path D:\oimpresso.com\vendor\autoload.php     # precisa ser True
Get-ChildItem D:\oimpresso.com\node_modules | Measure-Object | % Count   # > 0

# 3. Só agora
git worktree remove D:\oimpresso.com\.claude\worktrees\<nome>
```

## Os dois métodos que NÃO funcionam para remover a junction

- `Remove-Item <worktree>\vendor -Force` — falha de forma silenciosa em alguns casos e **deixa a junction no lugar**; o `git worktree remove` seguinte então apaga o alvo real.
- `rmdir` cru pelo Git Bash — o MSYS converte `\` em `/` no caminho, o comando erra sem alarde e a junction permanece.

Em ambos os casos o sintoma é o mesmo: parece que deu certo, e o estrago acontece no passo seguinte. Por isso o passo 2 (confirmar o alvo) é obrigatório, não opcional.

## Alternativa que elimina o risco

Não usar junction: rodar `composer install --prefer-dist --no-progress` (ou `npm ci`) dentro do worktree. Custo de 3-5 minutos por worktree, risco zero de drenar o repositório principal. Recomendado quando o worktree vai durar mais que algumas horas.

## Recovery quando já aconteceu

```bash
cd D:/oimpresso.com
composer install --prefer-dist --no-progress   # se foi o vendor/
npm ci                                          # se foi o node_modules/
```

Leva 3-5 minutos. O CI no GitHub Actions **não é afetado** — cada job faz sua própria instalação.

## Histórico

- **2026-05-11** — `vendor/` (318 MB) zerado durante sessão JANA Pro Sprint A.
- **2026-07-14** — `node_modules/` zerado na sessão wizardly-sammet, com `git worktree remove` **sem** `--force`; foi o que provou que a flag não era a causa e derrubou a redação original deste doc.

## Refs

- Dono do tema (detalhe completo): [`memory/requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md`](../requisitos/Infra/PEGADINHA-junction-vendor-worktree-windows.md)
- Regra canônica em [`memory/proibicoes.md`](../proibicoes.md) §Ambiente
- Microsoft Docs: [mklink — NTFS Junctions vs Symlinks](https://learn.microsoft.com/en-us/windows-server/administration/windows-commands/mklink)
