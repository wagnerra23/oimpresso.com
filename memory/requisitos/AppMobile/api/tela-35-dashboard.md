# App — Dashboard (tela 35) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/dashboard` →
`{ faturamento_30d:{valor, variacao_pct, serie_semanal:[7]}, kpis:{pedidos_ativos, pedidos_novos,
producao_em_curso, a_receber, vencido}, pedidos_por_dia:[{data, total}]|null, meta_mes:{valor, realizado_pct}|null,
producao_concluida:{concluidas, total}|null }`.

- Acesso: `dashboard.data` (a do faturado do Início). Sem ela → `403 sem_permissao` e a área
  `dashboard` não entra em `areas` (§6).
- `faturamento_30d`: vendas finais dos últimos 30 dias (hoje incluso) nos locais do usuário, pelo
  `getSellTotals` do painel web; `variacao_pct` contra os 30 dias anteriores (`null` sem venda antes);
  `serie_semanal` = os 7 últimos dias, dia a dia, do mais antigo a hoje.
- `meta_mes`: a meta mensal de faturamento da Jana (a mesma que gera a meta do dia, §6) e
  `realizado_pct` = vendido no mês até hoje ÷ meta × 100, inteiro. `null` sem meta vigente.
- Números de pedidos e produção seguem a regra de quem vê vendas (§2/§5) e vêm `null` sem ela —
  assim batem com as abas Pedidos e Produção: `pedidos_ativos` = o do Início; `pedidos_por_dia` = 14
  dias até hoje, vendas finais visíveis por data da venda; `pedidos_novos` = o dia de hoje dessa série;
  `producao_em_curso` = coluna "em produção" de `/producao`; `producao_concluida` = coluna "pronto para
  faturar" sobre o total das 4 colunas.
- `a_receber` / `vencido`: os mesmos da tela 06 ([§10.1](tela-06-financeiro.md)); `null` sem acesso ao Financeiro.
