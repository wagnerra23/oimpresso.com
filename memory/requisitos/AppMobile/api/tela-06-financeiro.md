# App — Financeiro (tela 06) — só leitura

> Parte do contrato da API do app ([API-CONTRATO-v1.md](../API-CONTRATO-v1.md): regras gerais §0, Início §6).

`GET /api/app/financeiro?aba=receber|pagar|extrato&pagina=N` →
`{ resumo:{mes, recebido, pago, saldo, a_receber, vencido, a_pagar}, contas:[{id, nome, detalhe, saldo}],
itens:[{id, tipo, descricao, parte, vencimento, pago_em, valor, status}], contadores:{receber, pagar, extrato},
pagina, tem_mais }`, 20 por página. `resumo` e `contas` não mudam com a aba.

- Acesso: módulo Financeiro no plano + `financeiro.access` (ou superadmin) — a mesma regra do bloco
  `financeiro` do Início. Sem ela, `403 sem_permissao`. A área `financeiro` entra em `areas` (§6) com essa
  regra e conta para o perfil `erp`.
- `resumo.a_receber` / `a_pagar`: `Financeiro\UnificadoService::kpis` — o mesmo número do Início e do
  cockpit web. `vencido` = parte vencida do a receber (já contida nele: aberto/parcial, vencimento < hoje).
- `recebido` / `pago`: baixas do mês corrente (`mes` = `YYYY-MM`) dos títulos a receber / a pagar, sem as
  de estorno (regra do `FluxoRealizadoService`); `saldo` = recebido − pago.
- Abas `receber` e `pagar`: títulos aberto/parcial do tipo, por vencimento (mais antigo primeiro);
  `valor` = o que falta (`valor_aberto`); `status` = `vencido` se o vencimento passou, senão `aberto`.
- Aba `extrato`: títulos quitados cuja última baixa caiu no mês, por `pago_em` (mais recente primeiro);
  `valor` = soma das baixas do título; `status` = `liquidado`. Cancelados não aparecem em aba nenhuma.
- `descricao` = "Título <número>" (+ " · parcela N/M" quando parcelado); `parte` = cliente/fornecedor
  do título (`null` sem). Valor sempre positivo: o sinal vem de `tipo`.
- `contas`: contas bancárias do Financeiro; `detalhe` = banco · agência; `saldo` = saldo em cache, `null`
  quando o ERP não tem.
- Tier 0: todas as tabelas filtradas pelo business do token (os models do Financeiro filtram pela sessão,
  que a API não tem).
