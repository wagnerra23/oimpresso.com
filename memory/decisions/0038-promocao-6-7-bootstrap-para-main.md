# ADR 0038 — Promoção de `6.7-bootstrap` para `main` como branch principal

**Status:** ✅ Aceita (executada)
**Data decisão:** 2026-04-27
**Autor:** Wagner
**Registrado por:** Claude (worktree `heuristic-hawking-2bb058`)
**Relacionado:** ADR 0010 (sistema de memória), auto-memória `project_current_branch.md`

---

## Contexto

A branch `main` no repositório `wagnerra23/oimpresso.com` carregava 7 commits de uma versão antiga (3.7-com-nfe + migration de city) que perderam relevância após a migração para v6.7. Todo o trabalho ativo desde 2026-04 vive em `6.7-bootstrap`: Laravel 13.6, PHP 8.4, Inertia v3, módulos Copiloto / Financeiro / MemCofre, Form shim, etc. — 326 commits únicos vs `main`.

Manter as duas branches viável trazia atrito constante:

- Auto-memória precisa lembrar que a "principal" não é `main` (`project_current_branch.md`).
- PRs novos têm que mirar `6.7-bootstrap` em vez do default.
- CI/CD (`deploy.yml`, `quick-sync.yml`) hardcoded em `6.7-bootstrap`.
- CLAUDE.md instruindo `git pull origin 6.7-bootstrap` no servidor.
- Toda doc operacional precisava da nota "não main, é 6.7-bootstrap".

Wagner explicitou em 2026-04-27: "no main não tem mais nada que eu queira".

## Decisão

**Promover `6.7-bootstrap` ao papel de branch principal `main`** via force-push, descontinuando `6.7-bootstrap`. O conteúdo histórico do `main` pré-promoção fica preservado em `archive/main-pre-6.7-merge` como salvaguarda.

## Execução (2026-04-27)

| Passo | Comando | SHA antes | SHA depois |
|---|---|---|---|
| Backup do `main` antigo | `git push origin 0c3a8300:refs/heads/archive/main-pre-6.7-merge` | — | `0c3a8300` |
| Force-push `6.7-bootstrap` → `main` | `git push origin --force-with-lease=main:0c3a8300 bd74b80f:refs/heads/main` | `0c3a8300` | `bd74b80f` |
| Mover worktree principal | `git -C D:/oimpresso.com checkout main && git -C D:/oimpresso.com reset --hard origin/main` | `bd74b80f` | `bd74b80f` (em `main`) |
| Deletar branch `6.7-bootstrap` | `git push origin :6.7-bootstrap && git branch -D 6.7-bootstrap` | — | (deletada) |

Verificação final:
- `origin/main` = `bd74b80f` (== ex-`6.7-bootstrap`) ✅
- `origin/archive/main-pre-6.7-merge` = `0c3a8300` ✅
- `origin/6.7-bootstrap` ausente ✅
- Worktree `D:/oimpresso.com` em `main`, alinhado com remoto ✅

## Consequências

### Positivas
- Convenção restaurada: branch default do GitHub e branch de trabalho convergem.
- PRs novos miram `main` automaticamente.
- Auto-memória `project_current_branch.md` simplifica.
- CLAUDE.md seção "deploy manual" passa a usar `git pull origin main` (canônico).

### Negativas / Trabalho residual
1. **Workflows CI quebrados** se não atualizados — ação imediata abaixo:
   - [.github/workflows/deploy.yml:83-89](../../.github/workflows/deploy.yml#L83) — `git checkout 6.7-bootstrap` + `git reset --hard origin/6.7-bootstrap` precisam virar `main`.
   - [.github/workflows/quick-sync.yml:9,54](../../.github/workflows/quick-sync.yml#L9) — trigger `on push` filtrado e `reset --hard` em `6.7-bootstrap` precisam virar `main`. (Workflow já está marcado como instável em `reference_quick_sync_quebrada.md`.)
2. **CLAUDE.md** ainda menciona `6.7-bootstrap` em 3 linhas (193, 194, 201) — instruções operacionais que precisam virar `main`.
3. **PR #18 (DRAFT)** continua mirando `main`, mas após o force-push pode ter divergido. Precisará rebase quando virar não-draft.
4. **Sessions logs antigos e fixtures** (`tests/fixtures/memory-fake/`) mantêm referências históricas a `6.7-bootstrap` — deixados intocados como evidência do passado.

### Reversão (se necessário)
A branch `6.7-bootstrap` pode ser recriada a partir do mesmo SHA:
```bash
git push origin bd74b80f:refs/heads/6.7-bootstrap
```
O `main` antigo pode ser recuperado via:
```bash
git push origin --force-with-lease=main:bd74b80f 0c3a8300:refs/heads/main
```

## Alternativas consideradas

- **Manter ambas as branches sincronizadas via merge contínuo:** rejeitado — Wagner explicitamente quer só uma branch principal e disse que `main` "não tem mais nada que eu queira"; sincronização contínua adiciona overhead sem benefício.
- **Deletar `main` e renomear `6.7-bootstrap` para `main`:** rejeitado — força mais churn no GitHub (default branch precisa ser explicitamente migrado, hooks dependentes podem quebrar). Force-push em `main` é equivalente em resultado e mais simples.
- **Não fazer backup do `main` antigo:** rejeitado — custo zero (uma branch ref) e a salvaguarda já se mostrou útil em situações similares.

## Ações imediatas (pendentes)

1. PR atualizando `.github/workflows/deploy.yml`, `.github/workflows/quick-sync.yml` e `CLAUDE.md` para refletir branch `main`. Wagner deve confirmar antes do merge.
2. Atualizar a nota sobre PR #18 e PRs antigos no [`memory/08-handoff.md`](../08-handoff.md).
3. Decidir destino futuro de `archive/main-pre-6.7-merge` — sugestão: manter por 90 dias e reavaliar.
