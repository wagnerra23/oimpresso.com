# Pedido ao Design — playbook 02 de Vendas (troca de Blade por React + o que sobrou do 01)

**Autor:** Code, a pedido do [W] em 2026-10-03 ("use o design para gerar as próximas playbook de PRs").
**Medido no `main` `66dca9007c` (2026-10-03).** O Code não edita o espelho: este pedido é para o Design
gerar o `playbook/02-*.md` e atualizar o `00-INDICE.md` (fonte da máquina, bloco json).

**Absorve, não duplica:** `playbook/00-INDICE.md` e `playbook/01-telas-legadas.md`. O 02 continua
o 01, não o substitui.

## 1 · Onde o playbook 01 parou

| thread | estado | prova |
|---|---|---|
| 01 Lista de POS | entregue | #8488, #8490 · `_saida-01.md` |
| 02 Remessas | backend entregue, **tela aberta** | #8491 entregue · #8493 aberto, espera aprovação do Felipe |
| 03 Devolução | **lista entregue, falta o PR 2** (tela de devolver, `SellReturn/Add`) | #8487 · `_saida-03.md` |
| 04 Descontos | entregue | #8514, #8517 · `_saida-04.md` |
| 05 Importação | entregue (D2 e D3 incluídas) | #8516, #8520, #8542 · `_saida-05.md` |
| 06 Pedido de venda | entregue | #8486 · `_saida-06.md` |
| 07 Caixa (turno) | **não começou** | — |
| 00 Puxar as 9 telas vivas | **não começou** | sem `_saida-00.md` |
| A1 · A2 alvos | entregues | `_saida-A1.md`, `_saida-A2.md` |

## 2 · Achado que o 02 precisa resolver: as telas React novas não chegam a quem usa

Todas as threads entregues seguem o mesmo desenho: o controller responde **React só com o
cabeçalho `X-Inertia`** e mantém a **tela Blade no GET comum**. Todas as `_saida` registram que a
troca final (F5, tirar o Blade) "é humana".

Medido em produção em 2026-10-02, logado no biz=1:

- abrir `/discount`, `/sales-order` e `/sell-return` direto pela URL entrega a **tela Blade**;
- clicar em **Descontos** no menu de Vendas faz carregamento completo da página e também entrega o
  **Blade** (título "Desconto - WR2 Sistemas", DataTables do jQuery carregado).

Ou seja: as Pages `Sells/Pos/Index`, `Discount/Index`, `ImportSales/*`, `SalesOrder/Index` e
`SellReturn/Index` estão em produção, mas quem entra pela URL ou pelo menu recebe o Blade. Pelo
menu só medi Descontos; as outras foram medidas pela URL direta — o playbook deve medir o menu de
cada uma.

## 3 · O que o playbook 02 deve trazer

1. **Fechar o 01:** thread 02 (tela de Remessas), thread 03 PR 2 (`SellReturn/Add`), thread 07
   (Caixa) e thread 00 (puxar as telas vivas para o protótipo).
2. **Cutover F5 por tela**, uma thread por tela, na ordem que o [W] escolher (D4): o menu e o GET
   comum passam a entregar a Page React; smoke em produção da tela trocada; o Blade só sai depois
   do período de observação (D5). Régua do processo MWART (ADR 0104): F5 tem aviso ao cliente e
   canário — Vendas é o módulo de maior uso da ROTA LIVRE (biz=4).
3. **Provas no formato do 01** (bloco json do índice: `prefixo`, `nao_toca`, `provas`), com a prova
   do cutover sendo o GET comum responder a Page React — não só o teste com `X-Inertia`.

## 4 · Decisões para o [W] (o 02 nasce com elas na fila, sem resposta inventada)

- **D4** Ordem do cutover: uma tela por vez ou em lote? Sugestão de partida para o [W] avaliar:
  começar pelas de menor uso (Descontos, Importação, Pedido de venda) e deixar Lista de POS e
  Devolução, as mais usadas no balcão, para o fim.
- **D5** Observação antes de apagar o Blade: os 7 dias de canário do MWART por tela, ou um período
  único para o lote?
- **D6** Aviso à ROTA LIVRE (biz=4) antes da troca: por tela ou uma vez só?
