---
sessao: "08"
titulo: StatusBadge: tirar o fill sólido (AP7)
dono: "[CL]"
base: 68a938c5ec5f
---
# 08 · StatusBadge: tirar o fill sólido (AP7)

**Já decidido por [W] em 2026-09-01: aplicar.** Pedido original: `../PEDIDO-CL-AP7-statusbadge-e-col2-mono-2026-09-01.md` (movido para esta pasta).

Hoje @68a938c5ec5f: `StatusBadge.tsx` ainda tem `className: 'bg-success text-success-foreground …'` por cima de `variant:'default'` em `:48`, `:55`, `:57`, `:85`, `:86` (e `variant:'destructive'` onde o soft seria `danger`, `:49`, `:56`, `:62`, `:63`). O `ui/badge.tsx` já é AP7-correto — **1 arquivo**, não 66 telas. A 2ª decisão do pedido (coluna 2 mono no Arquivos) já está no `main` (`Arquivos/Index.tsx:684`).

## Prova
No JSON do `00-INDICE.md` — o placar confere. Recibo: `_saida-08.md`, de quem executar.
