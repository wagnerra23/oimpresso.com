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

## Recorrências (chave `RECORRENCIAS` do app)

`GET /api/app/financeiro/recorrencias[?contato_id=N][&vigentes=1]` → `{ itens: Recorrencia[] }` no formato da
`interface Recorrencia` do app (`src/api.ts`): `{id, tipo, descricao, parte, parte_id, plano_nome, valor, frequencia,
dia_geracao, dia_vencimento, inicio, termino, proxima_geracao, proximo_vencimento, gerados, status, interrompida_em,
contrato}`. Ativas primeiro, depois por descrição. Mesmo acesso da lista do Financeiro.

- **Contratos a receber** = assinaturas da Cobrança Recorrente. `valor`/`descricao` do plano; `frequencia` do ciclo
  (custom vai para a mais próxima pelos dias); `proxima_geracao` = `proximo_vencimento` = `next_due_date`, só
  quando a assinatura está `active` (é o que o `rb:generate-invoices` gera, no dia do vencimento); `gerados` =
  faturas criadas; cancelada ou pausada = `interrompida`; `contrato` = id da assinatura (o mesmo nº do filtro
  `contrato` da lista).
- **Contas fixas a pagar** = despesas recorrentes do ERP (`transactions` expense com `is_recurring`). `id` =
  1.000.000.000 + id da despesa (as duas fontes não colidem); `plano_nome` = categoria da despesa; próxima
  geração pela regra do `pos:generateRecurringExpense` (última gerada + intervalo, no "repetir no dia");
  `termino` pelas repetições; `gerados` = a despesa de origem + as geradas; `contrato` = ref. da despesa.
- `vigentes=1`: ativa e sem término vencido. `contato_id`: só as da pessoa.

`POST /api/app/financeiro/recorrencias/{id}/interromper` → a `Recorrencia` atualizada. Para só a geração futura —
nenhum lançamento já criado muda. Contrato: cancela a assinatura (`AssinaturaService::cancelar`, permissão
`cancel` da `SubscriptionPolicy`); conta fixa: marca `recur_stopped_on` (permissão `expense.edit`). Já
interrompida devolve como está (200). Sem permissão `403 sem_permissao`; de outro business ou inexistente
`404 nao_encontrado`.
