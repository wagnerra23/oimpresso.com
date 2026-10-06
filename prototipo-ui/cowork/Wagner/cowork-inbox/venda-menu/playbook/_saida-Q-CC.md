---
sessao: "Q-CC"
titulo: Protótipo — uma rota só para cotações
autor: "[CC]"
data: 2026-10-06
decisao: D-ORC-2 (delegada ao [CC] por [W] no chat, 2026-10-06)
---
# Q-CC · recibo

**Escolha:** fica `venda-cotacoes`. Motivo: é a que espelha a Page viva (`resources/js/Pages/Sells/Quotations.tsx`, menu Vendas, permissão `quotation.*`); `orcamentos` era rota avulsa herdada do mapa antigo do Officeimpresso.

**Mudou no build (este projeto):**
- `app.jsx` — `venda-cotacoes` renderiza `OrcListPage` (a lista mais completa das duas); `ROUTE_301` ganhou `orcamentos → venda-cotacoes` (rota salva antiga não quebra, mesmo padrão do `boletos → cobranca`).
- `data.jsx` — item duplicado "Orçamentos" (`orcamentos`) saiu do menu Vendas; `venda-cotacoes` passou de "Lista de compromissos" (tradução errada de *quotations*) para **"Orçamentos"**; papel Larissa e atalho `O` apontam para `venda-cotacoes`.
- `comunicacao-visual-page.jsx` — botão "Orçamentos" navega para `venda-cotacoes`.

**Não mudou:** `orcamentos` continua na tabela de módulos do `app.jsx` (linha de inventário, não rota). `VENDA_VIEW["venda-cotacoes"]` fica inalcançável (o `if` acima captura antes) — inofensivo.

**Prova:** as duas `nao_contem` da Q-CC no `00-INDICE.md`, medidas no espelho depois do import.
