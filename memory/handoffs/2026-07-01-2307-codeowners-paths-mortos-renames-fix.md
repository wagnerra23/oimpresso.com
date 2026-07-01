---
date: "2026-07-01"
slug: codeowners-paths-mortos-renames-fix
time: "23:07 BRT"
tldr: "CODEOWNERS apontava 3 diretorios de codigo MORTOS pos-rename (Copiloto->Jana, PontoWr2->Ponto, MemCofre->SRS), deixando modulos LGPD/dado-pessoal/cofre SEM reviewer obrigatorio na main (require_code_owner_reviews=true). PR #3559 reaponta pros paths vivos + adiciona docs Jana/Ponto sem regredir ghost-dirs. Merged verde. Task paralela migrou os 4 ghost-files Copiloto->Jana e removeu a linha legacy — loop fechado."
prs: [3559]
decided_by: [W]
related_adrs: ["0088-module-rename-php-only"]
next_steps: ["Nada pendente deste escopo — dead paths + migracao ghost-dir ambos em main"]
---

## Estado MCP no momento do fechamento

- **cycles-active (COPI):** nenhum cycle ATIVO.
- **my-work (@wagner):** 30 tasks (8 review, 8 blocked, 14 todo) — nenhuma ligada a este fix. Foi item out-of-band da fila humana P11 KL-E2b.
- **decisions-search:** [ADR 0088](../decisions/0088-module-rename-php-only.md) (module rename PHP-only) confirma o rename Copiloto→Jana; [ADR 0092](../decisions/0092-tabela-rename-copiloto-para-jana.md) o rename de tabelas.
- **Handoffs irmãos hoje:** último era [2110 p10-wave1-anchoring-fable](2026-07-01-2110-p10-wave1-anchoring-fable.md).

## O que aconteceu

`.github/CODEOWNERS` tinha 3 regras casando com pastas de **código inexistentes** desde renames de módulo. Com branch protection `require_code_owner_reviews=true` na main, regra apontando pra path morto = módulo **sem reviewer obrigatório**. Auditei todos os paths (não só o Copiloto do pedido):

| Path morto | Reaponta p/ | Classe |
|---|---|---|
| `Modules/Copiloto/` | `Modules/Jana/` | LGPD-crítico |
| `Modules/PontoWr2/` | `Modules/Ponto/` | dado pessoal (ponto) |
| `Modules/MemCofre/` | `Modules/SRS/` | cofre/segredos (em deprecação) |

Docs: **adicionei** `memory/requisitos/{Jana,Ponto}/` (dirs vivos que perderam cobertura) e **mantive** os ghost-dirs `{Copiloto,PontoWr2}/` enquanto tinham arquivos (não regride proteção). O follow-up paralelo (`task_e961b312`) depois migrou os 4 ghost-files `Copiloto/`→`Jana/` e removeu a linha legacy — ambos já em main.

## Artefatos gerados

- **PR [#3559](https://github.com/wagnerra23/oimpresso.com/pull/3559)** — `fix(governance): CODEOWNERS aponta paths vivos pós-renames` — 1 file, +7/−5. CI 52/52 verde. **MERGED** (squash `ca1118a696`), branch deletada. R10: Wagner aprovou ("merge").
- Este handoff + linha no índice [08-handoff.md](../08-handoff.md).

## Persistência

- **git:** PR #3559 merged na main; handoff neste PR (branch `claude/handoff-codeowners`).
- **MCP:** webhook GitHub→MCP propaga o handoff ~2min pós-push.
- **BRIEFING:** N/A (mudança de governança de repo, não de capacidade de módulo).

## Próximos passos pra retomar

Nada deste escopo — loop fechado. Retomar fila humana P11 KL-E2b via [session 2026-07-01-p11-e2b-reseed-meilisearch-e3-distiller](../sessions/2026-07-01-p11-e2b-reseed-meilisearch-e3-distiller.md).

## Lições catalogadas

- **Worktree trap (near-miss):** editei primeiro o `.github/CODEOWNERS` do **repo principal** (`D:\oimpresso.com\.github\`, na branch `feat/vendas-link-caixa-do-dia` de outra sessão) em vez do arquivo da worktree — porque a Read inicial abriu o path do repo raiz enquanto os comandos git rodam na worktree. Detectei pelo `git diff` vazio, restaurei o principal (`git -C <root> checkout -- .github/CODEOWNERS`) e reapliquei na worktree. **Regra:** ao editar em worktree, sempre Read/Edit o arquivo sob o path da worktree, não o do repo raiz.
- **MSYS colon-mangling** reincidiu no `git show origin/main:<path>` (voltou vazio) — contornado com `MSYS_NO_PATHCONV=1` (já catalogado em auto-mem).

## Pointers detalhados

- CODEOWNERS política "merge verde, críticos não": [feedback-claude-aprova-merge-verde-criticos-nao.md](../reference/feedback-claude-aprova-merge-verde-criticos-nao.md).
- Rename canon: [ADR 0088](../decisions/0088-module-rename-php-only.md) + [ADR 0092](../decisions/0092-tabela-rename-copiloto-para-jana.md).
