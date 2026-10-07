---
sessao: "05"
titulo: Baseline catraca da sonda de produção
dono: "[CL]"
base: f97a0be9fa4d
decisao: D3 (_DECISOES-W-2026-10-07.md)
---
# 05 · Catraca sobre as 19 Pages da `_saida-02`

1. Baseline = as 19 Pages da `_saida-02` §3 (Page + seletor + contagem), ao lado da sonda.
2. Falha: Page fora da baseline com superfície clara, ou contagem que sobe. Contagem que desce: o PR baixa a baseline (nunca sobe).
3. O step roda em todo PR que toca `resources/js/Pages/**` ou `resources/css/**`. Se estourar os 25 min, job próprio — não cortar universo.
4. `swatch` na allowlist (pedido da `_saida-04`).
5. Recibo com PR de controle: 1 superfície clara numa Page limpa → vermelho.
