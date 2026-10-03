# App — Relatórios (tela 13) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/relatorios?periodo=mes|trimestre|ano&aba=dre|vendas|producao|estoque` →
`{ periodo:{de, ate}, kpis:{receitas, despesas, saldo, margem_pct}|null,
dre:{receitas_por_categoria:[{nome, valor}], despesas_por_categoria:[{nome, valor}]}|null,
vendas:{receita_por_dia:[{data, valor}], top_clientes:[{nome, valor}]}|null,
producao:{por_etapa:[{rotulo, total}]}|null, estoque:{baixo:[{nome, quantidade, minimo, unidade}]}|null }`.

- `periodo` = mês, trimestre ou ano corrente (do primeiro ao último dia). Só o bloco da `aba` vem
  preenchido; os outros vêm `null`.
- Cada bloco segue a permissão da tela web dele e vem `null` sem ela (o app mostra "Sem acesso a este
  relatório"): `kpis` e `dre` = regra do Financeiro ([§10.1](tela-06-financeiro.md) — `kpis` **pode** vir `null`); `vendas` =
  `dashboard.data`; `producao` = quem vê vendas (§5); `estoque` = `stock_report.view`. Sem nenhum →
  `403 sem_permissao`. A área `relatorios` entra em `areas` (§6) quando algum bloco é visível.
- `kpis`: títulos a receber (receitas) e a pagar (despesas) **não cancelados** com competência no
  período — a mesma base do DRE web. `saldo` = receitas − despesas; `margem_pct` = saldo ÷ receitas ×
  100 (1 casa), `null` sem receita. `dre`: os mesmos títulos por categoria (sem categoria → plano de
  contas → "Sem categoria"), maior primeiro.
- `vendas.receita_por_dia`: os 14 dias até hoje (independe do período), venda final nos locais do
  usuário — os filtros do faturado do painel (`getSellTotals`); `top_clientes`: até 5, no período.
- `producao.por_etapa`: os totais das colunas de `GET /api/app/producao`, na mesma ordem.
- `estoque.baixo`: até 20 itens com saldo ≤ mínimo, menor saldo primeiro (`ProductUtil::getProductAlert`,
  o mesmo do Início); produto com variação sai como "Produto — variação".
