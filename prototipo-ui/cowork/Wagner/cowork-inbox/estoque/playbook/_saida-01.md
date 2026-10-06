---
sessao: "_saida-01"
thread: "01 · Charters completos + casos das 4 Pages"
dono: "[CL]"
data: 2026-10-06
tipo: recibo
entregue_em: "#8763"
base_lida: wagnerra23/oimpresso.com@main 73ba361e01
---
# _saida-01

## Entregue
- 4 `.casos.md` ao lado das Pages: `StockAdjustment/{Index,Create}`, `StockTransfer/{Index,Create}`. 8 UCs
  derivados do `DOC-RAIZ-ESTOQUE` (INV-6) e dos RUNBOOKs: isolamento 98 × 99 na lista e nas filiais do
  formulário, e 403 sem permissão de compra.
- `tests/Feature/Stock/MovimentacoesTelasContratoTest.php` na lane `PHP / Pest (Estoque · MySQL)`. Só leitura,
  nenhum caso grava quantidade.
- Os 4 charters ganharam permissões, estados e link aos casos. R-ADJ-003 e R-XFER-004 diziam "validado no
  servidor"; o código só confere no cliente, e o charter foi corrigido.
- `.tsx` não foram tocados. Baseline do casos-gate: −4.

## Achados (por leitura, no §Backlog dos casos, não consertados)
O caminho grava quantidade, então o conserto é REGRA MESTRE e decisão [W]:
1. `[T0]` `destroy()` de ajuste e de transferência buscam por `id` + `type`, sem `business_id`.
2. `store()` não confere se a filial é do business nem compara origem com destino.
3. O filtro das duas listas React cai no ramo `ajax()` do `index()` (o Inertia manda `X-Requested-With`).

## Nota
O 1º run da lane deu 4 vermelhos por defeito do próprio teste: o usuário da factory saía sem `user_type` e
`allow_login` no modelo em memória, e o `CheckUserLogin` abortava 403 antes do controller. Os dois casos de
403 tinham passado por isso, sem exercer o gate. Corrigido no 2º push do mesmo PR. O veredito é o da lane.
