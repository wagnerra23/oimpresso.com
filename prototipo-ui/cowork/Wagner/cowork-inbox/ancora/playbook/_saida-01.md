---
sessao: "_saida-01"
thread: "01 · perna do bundle resolve no LUGAR_FIXO sem --staging"
dono: "[C]"
data: 2026-09-23
tipo: recibo retroativo
entregue_em: "#7120 (2026-09-09)"
base_lida: wagnerra23/oimpresso.com@main 1418ca208
---
# _saida-01

## Entregue
Pelo #7120 (2026-09-09): *fix(ancora): o `-page.jsx` do bundle resolve sem `--staging` — ele está no git*.

Recibo escrito depois, na sessão de recibos de 2026-09-23. A thread tinha todas as provas verdes
e nenhum `_saida`, então o placar a mostrava como `próximo` — abrir sessão nela refaria trabalho
já mergeado. Conferido o que a thread pede contra o `main` antes de escrever isto.

## Provas medidas no main (1418ca208)
1. `scripts/design/ancora.mjs` contém `LUGAR_FIXO` e o bite-test `BITE bundle sem staging`
2. guardas intactas: `.claude/hooks/post-merge-ui-smoke-required.mjs` contém `caminhoDaAncora`; `prototipo-ui/cowork/Wagner/repair-page.jsx` existe

## Nota
Entregue quando o arquivo ainda morava em `prototipo-ui/ancora.mjs`; o #7224 (2026-09-11) o moveu para `scripts/design/`. Por isso `git log -S` no caminho novo aponta o #7224, que é só a mudança de casa.
