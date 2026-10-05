# Tela 13 · Lucratividade e comissões (P6) ⬜ — só leitura

> Proposta. Acrescenta abas ao `api/tela-13-relatorios.md` do ERP (a rota `GET /api/app/relatorios` já existe ✅).

## Já existe ✅
- Comissão do UltimatePOS:
  - `users.cmmsn_percent` (% por vendedor)
  - `pos_settings.cmmsn_calculation_type` = `invoice_value` (sobre a venda) ou `payment_received` (sobre o recebido)
  - cálculo em `TransactionUtil::getTotalSellCommission` e `getTotalPaymentWithCommission` (usados pelo `ReportController`)
- Permissões: `commission_agent.view` (a lista) e `dashboard.data`.

## Novo ⬜ — `GET /api/app/relatorios?aba=lucratividade|comissoes&periodo=mes|trimestre|ano`

`aba=lucratividade` (exige `dashboard.data`):
```json
{ "lucratividade": { "receita": 148230.00, "custo_variavel": 79400.00, "custo_fixo": 41000.00,
    "margem_contribuicao": 68830.00, "margem_pct": 46.4, "ponto_equilibrio": 88362.07,
    "menores_margens": [ { "pedido_id": 4819, "numero": "4819", "resumo": "Placas PS 30 × 40 cm", "valor": 452.00, "margem_pct": 12.0 } ] } }
```
- Custo variável = custo da mercadoria vendida (`purchase_lines` via `mapPurchaseSell`). Custo fixo = despesas do período no Financeiro. **⬜ confirmar com o dono do DRE (§10.3)**.

`aba=comissoes`:
```json
{ "comissoes": { "base": "payment_received",
    "vendedores": [ { "id": 7, "nome": "Ana Souza", "percentual": 3.0, "vendido": 62100.00, "recebido": 54300.00, "comissao": 1629.00, "a_liberar": 234.00 } ] } }
```
- `base` vem do `pos_settings`. Com `invoice_value`, `a_liberar` sai 0.
- **Visibilidade:** quem tem `commission_agent.view` vê todos. Sem ela, quem é agente de comissão vê **só a própria linha**. Sem nenhum dos dois → `comissoes: null`.

Bloco sem permissão → `null`, e o app esconde a aba (§0).

## Fora
Pagar comissão, mudar % ou base.

## Ajuste da empresa
`app_comissao_vendedor_ve_propria` (padrão `true`). Com `false`, quem não tem `commission_agent.view` recebe `comissoes: null`.
