# Tela 13 · Lucratividade e comissões (P6) ✅ — só leitura

> ✅ **No ar desde o PR 13 do playbook `propostas-app`** (2026-10-15). Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6) e da rota de [tela-13-relatorios.md](tela-13-relatorios.md). Origem: handoff `propostas-app` do Design, conferido em 2026-10-05. Teste de contrato: `tests/Feature/Sells/AppRelatoriosLucroComissaoContratoTest.php` (UC-APPLC-01..06).

## Base no ERP
- Comissão do UltimatePOS:
  - `users.cmmsn_percent` (% por vendedor)
  - `pos_settings.cmmsn_calculation_type` = `invoice_value` (sobre a venda) ou `payment_received` (sobre o recebido)
  - os filtros de `TransactionUtil::getTotalSellCommission` e `getTotalPaymentWithCommission` (o relatório Comissão por vendedor do web)
- Lucro: `TransactionUtil::getProfitLossDetails` (o relatório Lucro/Prejuízo do web).
- Permissões: `commission_agent.view` / `commission_agent.manage` (a lista) e `dashboard.data`.

## `GET /api/app/relatorios?aba=lucratividade|comissoes&periodo=mes|trimestre|ano`

Período e regra de bloco iguais às outras abas: só o bloco da `aba` pedida vem preenchido; bloco sem permissão → `null` e o app esconde a aba (§0). Sem nenhum bloco visível → `403 sem_permissao`.

`aba=lucratividade` (exige `dashboard.data`):
```json
{ "lucratividade": { "receita": 148230.00, "custo_variavel": 79400.00, "custo_fixo": 41000.00,
    "margem_contribuicao": 68830.00, "margem_pct": 46.4, "ponto_equilibrio": 88362.07,
    "menores_margens": [ { "pedido_id": 4819, "numero": "4819", "resumo": "Placas PS 30 × 40 cm", "valor": 452.00, "margem_pct": 12.0 } ] } }
```
- Os números são os do Lucro/Prejuízo web no mesmo período e nos locais do usuário (`getProfitLossDetails`):
  - `receita` = "Total de vendas (sem imposto e desconto)" (`total_sell`)
  - `margem_contribuicao` = "Lucro bruto" (`gross_profit`: venda menos o custo do lote de compra, `transaction_sell_lines_purchase_lines`)
  - `custo_variavel` = `receita − margem_contribuicao`
  - `custo_fixo` = "Total de despesas" (`total_expense`, despesas menos estornos de despesa)
  - `margem_pct` = margem ÷ receita × 100 (1 casa), `null` sem receita
  - `ponto_equilibrio` = custo_fixo ÷ (margem ÷ receita), `null` sem receita ou com margem ≤ 0
- `menores_margens`: até 5 vendas finais do período com a menor margem (lucro da venda ÷ receita da venda com imposto, a base do lucro bruto). O lucro de cada venda usa a mesma expressão de `getGrossProfit`; `valor` = total da venda; `resumo` = nome do 1º item.
- Decisão: o custo fixo vem das **despesas do UltimatePOS** (a linha do Lucro/Prejuízo web), não dos títulos do Financeiro — assim a aba bate com o relatório web. Os títulos a pagar do Financeiro incluem compras de mercadoria, que já estão no custo variável. Se o dono do DRE (§10.3) preferir outra base, muda aqui e no teste.

`aba=comissoes`:
```json
{ "comissoes": { "base": "payment_received",
    "vendedores": [ { "id": 7, "nome": "Ana Souza", "percentual": 3.0, "vendido": 62100.00, "recebido": 54300.00, "comissao": 1629.00, "a_liberar": 234.00 } ] } }
```
- `base` vem do `pos_settings` (ausente = `invoice_value`, como no web).
- Por vendedor (`transactions.commission_agent`), vendas finais nos locais do usuário:
  - `vendido` = Σ (quantidade − devolvida) × preço sem imposto, pela data da venda
  - `recebido` = Σ pagamentos dessas vendas pela data do pagamento (troco entra negativo)
  - `comissao` = `percentual` × (`recebido` se a base é `payment_received`, senão `vendido`) ÷ 100
  - `a_liberar` = `percentual` × saldo em aberto das vendas do período ÷ 100; com `invoice_value`, 0
- Lista: quem vendeu ou recebeu no período, mais os usuários com percentual > 0 ou marcados como agente de comissão. Maior comissão primeiro.
- **Visibilidade:** quem tem `commission_agent.view` (ou `.manage`) vê todos. Sem ela, o vendedor com percentual > 0 (ou agente de comissão) vê **só a própria linha**. Sem nenhum dos dois → `comissoes: null`.

## Fora
Pagar comissão, mudar % ou base.

## Ajuste da empresa
`app_comissao_vendedor_ve_propria` (padrão `true`), lido em `RelatoriosController::escopoComissoes`. Com `false`, quem não tem `commission_agent.view` recebe `comissoes: null`.
