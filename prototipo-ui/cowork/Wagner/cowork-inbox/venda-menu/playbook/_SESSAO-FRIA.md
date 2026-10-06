# Sessão fria — venda-menu

> 1 thread = 1 sessão nova = 1 PR. Gerado pelo [CC] em 2026-09-29 do bloco json do `00-INDICE.md` (sha a71c2f2d052f). Se o índice mudar, **o índice manda**.

## Prompt de abertura — cole no chip novo, trocando só o NN
```
/onda venda-menu --thread NN
```
Leia só isto, nesta ordem: 1) a saída do `placar` (se não for `proximo`, **pare**) · 2) `01-telas-legadas.md` §NN + o charter/casos da tela nesta pasta · 3) o `prefixo`/`nao_toca` da thread no json · 4) o que a ficha lista como âncora, **relido no `main`**.
**Termine:** `_saida-NN.md` nesta pasta e PARE.

## Threads
| # | o que faz | dono | depende de |
|---|---|---|---|
| **00** | PUXAR as 9 telas vivas → protótipo | CC | — |
| **A1** | ALVO lote 1 (pos · remessas · devolução · descontos) | CL | — |
| **A2** | ALVO lote 2 (importação · pedido · caixa) | CL | — |
| **01** | Lista de POS → `Sells/Pos/Index` | CL | A1 |
| **02** | Remessas → `Sells/Shipments/Index` | CL | A1 |
| **03** | Devolução → `SellReturn/{Index,Add}` | CL | A1 |
| **04** | Descontos → `Discount/Index` | CL | A1 · D1 |
| **05** | Importação → `ImportSales/{Index,Preview}` | CL | A2 · D2 · D3 |
| **06** | Pedido de venda → `SalesOrder/Index` | CL | A2 |
| **07** | Caixa: movimentos + conferência | CL | A2 · 00 |
| **C0** | Cutover: chaves `mwart.vendas_*` + helper + teste | CL | — |
| **C1** | Liga biz=1: Descontos, Importação, Pedido | CL | C0 |
| **C2** | Liga biz=1: POS, Remessas, Devoluções | CL | C1 |
| **C3** | Observação 7 dias biz=1 | W | C2 |
| **C4** | Aviso ROTA LIVRE + liga todas | W | C3 |
| **C5** | Observação 7 dias todas | W | C4 |
| **C6** | Apaga Blade + fallback (1 PR/tela) | CL | C5 |
| **Q1** | Quotations: `casos.md` + charter sai de draft | CL | — |
| **Q2** | Quotations no cutover (`vendas_cotacoes`) | CL | C0 · Q3 |
| **Q3** | Converter cotação em venda (reusa `convertToInvoice`) | CL | — |
| **Q-CC** | Protótipo: uma rota só para cotações | CC | — (feito, `_saida-Q-CC.md`) |
