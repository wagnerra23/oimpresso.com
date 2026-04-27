# Sessão 2026-04-27 (noite) — Promoção `6.7-bootstrap` → `main` + cleanup ADR 0024

**Worktree:** `claude/heuristic-hawking-2bb058`
**Operador:** Claude (Opus 4.7 1M)
**Solicitante:** Wagner

---

## Pedido

Wagner pediu pra promover `6.7-bootstrap` ao papel de branch principal `main`, mantendo backup do `main` antigo e descontinuando `6.7-bootstrap`. Em seguida pediu auditoria de ADRs, gravação de evidência no MemCofre e atualização da memória.

## O que foi feito

### 1. Promoção de branch (operação git)

| Etapa | Comando | Resultado |
|---|---|---|
| Backup | `git push origin 0c3a8300:refs/heads/archive/main-pre-6.7-merge` | ref criada |
| Force-push (com lease) | `git push origin --force-with-lease=main:0c3a8300 bd74b80f:refs/heads/main` | `main` atualizada |
| Worktree principal pra main | `git -C D:/oimpresso.com checkout main && reset --hard origin/main` | em `main`, alinhado |
| Deletar `6.7-bootstrap` | `git push origin :6.7-bootstrap` + `git branch -D` | removida (local + remoto) |

Verificação final: `origin/main = bd74b80f` ✅, `origin/archive/main-pre-6.7-merge = 0c3a8300` ✅, `origin/6.7-bootstrap` ausente ✅.

### 2. Cleanup pendente do ADR 0028 (duplicata 0024)

Aproveitada a sessão pra executar a ação pendente desde 2026-04-26:

- `git mv memory/decisions/0024-padrao-inertia-react-ultimatepos.md memory/decisions/0029-padrao-inertia-react-ultimatepos.md`
- 11 referências cruzadas atualizadas:
  - `memory/decisions/0028-adrs-numeracao-monotonica.md` (marcada como ✅ executada)
  - `memory/sessions/2026-04-25-maratona-financeiro.md` (linhas 23 + 98)
  - `memory/sessions/2026-04-26-deploy-hero-fix-e-conflitos-memoria.md` (linhas 40 + 87)
  - `memory/requisitos/Financeiro/PLANO_DETALHADO.md` (linha 330)
  - `memory/requisitos/Financeiro/DOC_TELAS_E_SCORE.md` (linha 477)
  - `Modules/Financeiro/Http/Requests/UpsertCategoriaRequest.php`
  - `Modules/Financeiro/Http/Controllers/RelatoriosController.php`
  - `Modules/Financeiro/Http/Controllers/ContaReceberController.php`
  - `Modules/Financeiro/Http/Controllers/ContaBancariaController.php`
  - `Modules/Financeiro/Http/Controllers/CategoriaController.php`

### 3. Documentação de memória

- **ADR 0038** — `0038-promocao-6-7-bootstrap-para-main.md` (decisão arquitetural completa, formato Nygard, com seção de reversão).
- **Evidência MemCofre** — `Modules/MemCofre/Database/evidences/2026-04-27-promocao-main.md` (timeline literal de comandos + SHAs + estado antes/depois).
- **Auto-memória** — `project_current_branch.md` reescrita; `reference_composer_install_obrigatorio_pos_deploy.md` ajustada; entries correspondentes em `MEMORY.md`.
- **Handoff** — `memory/08-handoff.md` atualizado: branch ativa, comandos de deploy, nova seção "Sessão 2026-04-27".

## Auditoria de ADRs (estado pós-cleanup)

- **0001 → 0038** (38 arquivos, sem duplicatas).
- Gap em **0012**: histórico, intencional (ADR 0028).
- Gap em **0029** ✅ resolvido — agora ocupado pelo ex-`0024-padrao-inertia-react-ultimatepos.md`.
- Próximo ADR livre: **0039**.

## Trabalho residual (não executado nesta sessão)

1. Workflows CI hardcoded em `6.7-bootstrap` precisam virar `main`:
   - `.github/workflows/deploy.yml:83,88,89`
   - `.github/workflows/quick-sync.yml:9,54`
2. `CLAUDE.md` linhas 193, 194, 201 ainda instruem `git pull origin 6.7-bootstrap`.
3. PR #18 (DRAFT) — vai precisar rebase quando virar não-draft.
4. Fixtures de teste mencionam `6.7-bootstrap` (intencional, evidência histórica).

Wagner aguardado pra autorizar PR único cobrindo itens 1 + 2.

## Refs

- ADR 0038 — [memory/decisions/0038-promocao-6-7-bootstrap-para-main.md](../decisions/0038-promocao-6-7-bootstrap-para-main.md)
- ADR 0028 — [memory/decisions/0028-adrs-numeracao-monotonica.md](../decisions/0028-adrs-numeracao-monotonica.md) (ação concluída)
- Evidência — [Modules/MemCofre/Database/evidences/2026-04-27-promocao-main.md](../../Modules/MemCofre/Database/evidences/2026-04-27-promocao-main.md)

## Reversão de emergência

Se precisar reverter a promoção:

```bash
# Recuperar o main antigo:
git push origin --force-with-lease=main:bd74b80f 0c3a8300:refs/heads/main

# Recriar 6.7-bootstrap:
git push origin bd74b80f:refs/heads/6.7-bootstrap
```

Branch `archive/main-pre-6.7-merge` deve ser preservada por pelo menos 90 dias.
