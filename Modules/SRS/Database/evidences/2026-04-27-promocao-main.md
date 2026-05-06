# Evidência — Promoção `6.7-bootstrap` → `main`

**Data/hora:** 2026-04-27
**Operador:** Claude (worktree `heuristic-hawking-2bb058`), por solicitação direta do Wagner
**Tipo:** ato administrativo de branch (não tem migration / não toca DB)
**ADR vinculado:** [`memory/decisions/0038-promocao-6-7-bootstrap-para-main.md`](../../../../memory/decisions/0038-promocao-6-7-bootstrap-para-main.md)

> Registrado aqui em arquivo estático porque o módulo MemCofre ainda não tem UI de upload (per CLAUDE.md seção 7: "registrar em arquivo na branch enquanto isso").

---

## Pedido literal do Wagner

> "pode mover o 6.7-bootstrap para main, ele é meu principal. no main não tem mais nada que eu queira"
>
> "b deixe, eu quero usar só a principal. dexa com backup apenas. pode ser?"
>
> "grave na memoria, memoria do cofre as alterações"

## Estado antes (verificado 2026-04-27)

```text
origin/main             = 0c3a8300  (7 commits 3.7-com-nfe + city migration)
origin/6.7-bootstrap    = bd74b80f  (326 commits únicos: L13.6, Inertia v3, Copiloto, Financeiro, MemCofre, etc.)
merge-base              = 2c2cdf41
```

Worktree principal `D:/oimpresso.com` estava checked-out em `[6.7-bootstrap]`. Auto-memória `project_current_branch.md` registrava `6.7-bootstrap` como branch ativa.

## Operações executadas (timeline real)

```bash
# 1. Backup do main antigo (custo zero, salvaguarda)
git push origin 0c3a83006d9e89a24dcbe4104bbd3ef475916292:refs/heads/archive/main-pre-6.7-merge
# resultado: [new branch] archive/main-pre-6.7-merge

# 2. Force-push: 6.7-bootstrap por cima do main, com lease check
git push origin --force-with-lease=main:0c3a83006d9e89a24dcbe4104bbd3ef475916292 \
                 bd74b80f5aa995678eb66c86b7cec8a41cf1f820:refs/heads/main
# resultado: + 0c3a8300...bd74b80f bd74b80f -> main (forced update)

# 3. Mover worktree principal pra main
git fetch --prune origin
git -C D:/oimpresso.com checkout main
git -C D:/oimpresso.com reset --hard origin/main
# resultado: HEAD bd74b80f, working tree atualizado (12980 arquivos)

# 4. Deletar 6.7-bootstrap remoto + local
git push origin :6.7-bootstrap          # [deleted] 6.7-bootstrap
git branch -D 6.7-bootstrap             # Deleted branch 6.7-bootstrap (was bd74b80f)
```

## Estado depois (verificado)

```text
origin/main                          = bd74b80f (== ex-6.7-bootstrap) ✅
origin/archive/main-pre-6.7-merge    = 0c3a8300 (backup do main antigo) ✅
origin/6.7-bootstrap                 = AUSENTE ✅
worktree D:/oimpresso.com            = main, alinhado com origin/main ✅
PRs abertos                          = só PR #18 (DRAFT, vai precisar rebase)
```

## Auto-memórias ajustadas

- `project_current_branch.md` — descrição/conteúdo refeitos: "trabalho em main daqui pra frente, 6.7-bootstrap descontinuada, backup em archive/main-pre-6.7-merge".
- `reference_composer_install_obrigatorio_pos_deploy.md` — title e description trocaram "6.7-bootstrap" por "main" (com nota histórica).
- `MEMORY.md` — entries correspondentes atualizadas.

## Cleanup ADR pendente concluído junto

Aproveitada a sessão pra executar a ação pendente do ADR 0028 (numeração monotônica):

- `memory/decisions/0024-padrao-inertia-react-ultimatepos.md` → `0029-padrao-inertia-react-ultimatepos.md` (via `git mv`, preserva histórico).
- 11 referências cruzadas atualizadas em sessions, requisitos Financeiro e 5 Controllers/FormRequest do `Modules/Financeiro/Http/`.
- ADR 0028 marcado como ✅ executado.

## Trabalho residual identificado (não executado nesta operação)

1. **Workflows CI quebrados em produção:**
   - `.github/workflows/deploy.yml` linhas 83, 88, 89 — `git checkout 6.7-bootstrap` e `git reset --hard origin/6.7-bootstrap` precisam virar `main`.
   - `.github/workflows/quick-sync.yml` linhas 9, 54 — trigger `on push` filtrado em `6.7-bootstrap` e `reset --hard origin/6.7-bootstrap` idem.
2. **CLAUDE.md** linhas 193, 194, 201 — instruções operacionais ainda mandam `git pull origin 6.7-bootstrap`.
3. **PR #18 (DRAFT)** mira `main` mas precisará rebase quando virar não-draft.
4. **Fixtures de teste** (`tests/fixtures/memory-fake/requisitos/Financeiro/SPEC.md`) mencionam `6.7-bootstrap` — deixados como evidência histórica.

Wagner aguardado pra autorizar PR único de cleanup dos itens 1+2.

## Como reverter (se algum dia precisar)

```bash
# Restaurar o main antigo (3.7-com-nfe + city migration):
git push origin --force-with-lease=main:bd74b80f 0c3a8300:refs/heads/main

# Recriar 6.7-bootstrap:
git push origin bd74b80f:refs/heads/6.7-bootstrap
```

A branch `archive/main-pre-6.7-merge` deve ser preservada **por pelo menos 90 dias** (sugestão ADR 0038) antes de qualquer deleção.
