---
sessao: "_saida-01"
thread: "01 · Rede: 2 specs E2E (cockpit + NF-e)"
dono: "[C]"
data: 2026-09-23
tipo: recibo retroativo
entregue_em: "#7257 (2026-09-14)"
base_lida: wagnerra23/oimpresso.com@main 1418ca208
---
# _saida-01

## Entregue
Pelo #7257 (2026-09-14): *test(fiscal): 2 specs E2E do módulo — o eixo que o jsdom declara fora do alcance*.

Recibo escrito depois, na sessão de recibos de 2026-09-23. A thread tinha todas as provas verdes
e nenhum `_saida`, então o placar a mostrava como `próximo` — abrir sessão nela refaria trabalho
já mergeado. Conferido o que a thread pede contra o `main` antes de escrever isto.

## Provas medidas no main (1418ca208)
1. `e2e/fiscal-cockpit.spec.ts` e `e2e/fiscal-nfe.spec.ts` existem
2. guardas intactas: `fiscal-cockpit.contract.json` existe; `Fiscal/Cockpit.tsx` contém `onKeyDown` (UC-FCKP-11)
