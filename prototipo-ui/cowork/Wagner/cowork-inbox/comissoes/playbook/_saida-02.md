---
sessao: "02"
titulo: Relatório de comissão por vendedor → Inertia — saída
playbook: comissoes
thread: "02"
dono: "[CL]"
data: "2026-10-07"
base: wagnerra23/oimpresso.com@main 7d62bc65c6 (merge do #8877)
---

# _saida-02 · Relatório de comissão por vendedor

**Entregue no [#8877](https://github.com/wagnerra23/oimpresso.com/pull/8877)**, mergeado em 2026-10-07 02:44Z (`7d62bc65c6`) com o ok do [W] (a tela exibe valor). Este recibo foi escrito pelo gerente da fila a partir do corpo do PR.

**Resposta curta:** o resumo do relatório está em React atrás de `?tela=nova`. Sem o parâmetro, a Blade segue como antes. A tela não calcula: chama os mesmos três endpoints da Blade e só formata.

## 1 · Feito
- `GET /reports/sales-representative-report?tela=nova` → `Report/SalesRepresentative/Index` (trio `.tsx` + charter + casos).
- `ReportController`: só o ramo novo de render. As funções `getSalesRepresentativeTotal{Sell,Expense,Commission}` não mudaram.
- `tests/Feature/Users/SalesRepresentativeReportPageTest.php` na lane `acessos-pest` (o `ReportController` entrou no gatilho dela).
- `memory/requisitos/Comissao/RUNBOOK-relatorio-comissao.md` (F1 MWART).

### UC-COM-03 · prova por dois caminhos
| caso | endpoint | conta refeita no teste |
|---|---|---|
| vendedor 5%, venda 2 × 150 | base 300 · comissão 15 | 5 × (2 × 150) / 100 = 15 |
| outro vendedor, mesmo filtro (controle) | comissão 0 | sem venda → 0 |

## 2 · Não feito e por quê
- **As 4 abas de listagem da Blade** (vendas, vendas com comissão, despesas, pagamentos com comissão): não cabem num PR. Trocar a rota agora faria sumir as listas. O cutover fica para depois delas + screenshot aprovado pelo [W] (RUNBOOK e backlog do casos).
- **UC-COM-04 "vendedor só vê a própria":** medido, a Blade e os 3 endpoints exigem só `sales_representative.view` e listam todos os usuários do negócio. A ficha mandava medir e manter, e foi mantido. Travar no usuário logado é decisão [W] (no backlog do casos).

## 3 · Pedido literal pro [W]
> Decidir se o relatório deve travar o vendedor no usuário logado (UC-COM-04). Hoje qualquer um com `sales_representative.view` vê todos.

## 4 · Descobertas que mudam outra sessão
- **Achado Tier 0 do PR:** `getSalesRepresentativeTotalCommission` lia `cmmsn_percent` com `User::find` sem `business_id`. Os totais eram filtrados (outro negócio dava comissão 0), mas o percentual alheio vazava. Consertado no [#8884](https://github.com/wagnerra23/oimpresso.com/pull/8884): `User::where('business_id', …)->findOrFail(…)`. Mesmo tenant dá valor idêntico; id alheio dá 404.
