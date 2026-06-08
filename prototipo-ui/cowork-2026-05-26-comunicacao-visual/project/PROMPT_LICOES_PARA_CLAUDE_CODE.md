# PROMPT_LICOES_PARA_CLAUDE_CODE — gravar lições F3 no repo

> **Wagner:** cole no Claude Code, repo `wagnerra23/oimpresso.com@main`. Sobe o doc de lições pro `prototipo-ui/` e atualiza CLAUDE.md raiz pra apontar pra ele. Claude Design (eu) leio no início de TODO chat futuro.

---

## Tarefa

Subir documento de anti-padrões F3 (lições da rejeição de 2026-05-09 do Financeiro) pro repo, em local que [CC] lê no setup de cada chat novo.

```bash
git fetch origin
git checkout -b chore/cowork-licoes-f3-financeiro origin/main

curl -fsSL -o prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md \
  "https://019dcfd3-6ef2-7ee6-8512-b1b0e5544e58.claudeusercontent.com/v1/design/projects/019dcfd3-6ef2-7ee6-8512-b1b0e5544e58/serve/prototipo-ui-patch/LICOES_F3_FINANCEIRO_REJEITADO.md?t=46f8f52a9de545659a88ffae842d91a64176a573e093221385a8b10af899299c.c48a1d9b-d2b7-4412-9556-fa885bbd8712.a1e2ad01-7316-4a16-91e6-9ccdbcad8987.1778365593&direct=1"
```

## Editar `CLAUDE.md` (raiz do repo) — adicionar à tabela canônica

Na seção "🟢 v1.0 do protocolo", adicionar uma 6ª linha à tabela:

```markdown
| [`prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md`](https://github.com/wagnerra23/oimpresso.com/blob/main/prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md) | **LEITURA OBRIGATÓRIA antes de qualquer F3.** 6 anti-padrões + pré-flight checklist (Models reais, tenant scope, middleware stack, shape mapping). Documenta rejeição F3 Financeiro 2026-05-09. |
```

## Editar `prototipo-ui/COWORK_NOTES.md` — banner no topo

Adicionar logo abaixo do header existente:

```markdown
> ⚠️ **LEITURA OBRIGATÓRIA antes de qualquer F3:** [`LICOES_F3_FINANCEIRO_REJEITADO.md`](LICOES_F3_FINANCEIRO_REJEITADO.md) — 6 anti-padrões + pré-flight checklist. Aplicar em Estoque/Vendas/RH/Suprimentos/Crédito.
```

## Commit + PR

```bash
git add prototipo-ui/LICOES_F3_FINANCEIRO_REJEITADO.md CLAUDE.md prototipo-ui/COWORK_NOTES.md
git commit -m "chore(cowork): grava lições F3 Financeiro rejeitado em 2026-05-09

[CC] entregou F3 Financeiro com 4 violações Tier 0 (sem tenant scope,
middleware fantasma 'tenant', Models inventados, Unificado regredido).
[CL] rejeitou. PR não foi aberto.

Doc compila 6 anti-padrões + pré-flight checklist obrigatório pra
[CC] não repetir em Estoque/Vendas/RH/Suprimentos/Crédito.

CLAUDE.md raiz aponta pro doc na tabela canônica — leitura no setup
de cada chat futuro de F3 fica garantida.

Refs: PR #295 (protocolo v1.0)"
git push origin chore/cowork-licoes-f3-financeiro
gh pr create --base main \
  --title "chore(cowork): grava lições F3 Financeiro rejeitado" \
  --body "Memória institucional do erro mais grave do loop Cowork até hoje. Sem código de app — só doc de protocolo. Merge direto ok."
```
