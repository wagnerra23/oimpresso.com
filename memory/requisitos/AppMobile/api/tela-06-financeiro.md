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

## Filtro avançado e caixas (chave `FILTRO_FINANCEIRO` do app)

Parâmetros opcionais, os que o app monta em `paramsFiltros` (`src/financeiro-filtros.ts`); a regra é a de
`filtrarLancamentos` do mesmo arquivo. Parâmetro malformado é ignorado. O filtro muda `itens`, `contadores`
e `contadores_situacao`; **`resumo` e `contas` nunca**.

- `situacao=a_receber,a_pagar,recebidas,pagas` — caixas no lugar da aba (vale sobre `aba`). Em aberto primeiro,
  por vencimento; depois os liquidados, do pagamento mais recente. Valor desconhecido é ignorado; nenhuma
  caixa válida = lista vazia. `aba` segue funcionando: receber = `a_receber`, pagar = `a_pagar`, extrato =
  `recebidas,pagas`.
- `data_campo=vencimento|pagamento|emissao|competencia|venda` + `de` / `ate` (AAAA-MM-DD). Competência
  compara pelo mês. Título sem a data escolhida fica fora (em aberto não tem pagamento). Sem período, os
  liquidados são os do mês (o extrato de sempre); com período, o período manda.
- `busca` — número do título, cliente/fornecedor. `documento` — nº do título (sem pontuação).
- `forma_pagamento` — rótulo de `formas_pagamento` ("Pix") ou a chave do ERP ("pix"); desconhecida não casa
  nada. Vale a forma **realizada** (última baixa sem estorno); sem baixa, a prevista no título.
- `conta_id` — conta de `contas`; mesma regra (a da última baixa, senão a prevista).
- `nf` — dígitos contidos no nº da NF-e (`metadata.nfe_numero`/`nfe_chave`). `nosso_numero` — dígitos contidos
  no nosso número de um boleto do título.
- `valor_min` / `valor_max` — sobre o `valor` mostrado (em aberto = o que falta; liquidado = o baixado).
- `plano_conta` — código; um grupo ("2.1") pega as contas de baixo ("2.1.01").
- `registro=ativos|excluidos|todos` — excluído = título apagado no ERP (soft delete), que segue no histórico.
- `contrato` — nº do contrato da Cobrança Recorrente (id da assinatura) que gerou o título.

Na resposta, a mais: `contadores_situacao: {a_receber, a_pagar, recebidas, pagas}` (sempre, já com o
filtro), `planos_conta: [{codigo, nome, tipo?}]` (ativos do business, na ordem natural do código; `tipo` =
`receita` ou `despesa`, custo conta como despesa) e `formas_pagamento: [rótulo]` (as do ERP). Cada item ganha
`emissao`, `competencia` (AAAA-MM), `venda_em`, `forma_pagamento` (rótulo), `conta_id`, `nf_numero`,
`nosso_numero`, `plano_codigo`, `plano_nome`, `documento` (= nº do título), `excluido` e `contrato`.

## Novo lançamento (chave `NOVO_LANCAMENTO` do app) — REGRA MESTRE (valor)

`POST /api/app/financeiro/lancamentos`, header `Idempotency-Key` obrigatório, corpo `NovoLancamento` do app
(`src/lancamento-regras.ts`): `{tipo, descricao, contato_id, valor, vencimento, emissao, competencia,
forma_pagamento, conta_id, plano_codigo, documento, nf_numero, observacao, parcelas, recorrencia}` →
`201 { id, ids, recorrencia_id: null }` (`id` = o primeiro; `ids` = todos, um por parcela).

- Acesso: o do Financeiro + `financeiro.lancamentos.create` ou `financeiro.contas_receber.create` /
  `financeiro.contas_pagar.create` conforme o tipo (ou superadmin). Sem ela, `403 sem_permissao`.
- O ERP revalida tudo e conta em **centavos inteiros**: `valor` com mais de 2 casas é recusado. O título nasce
  `aberto` com `valor_aberto = valor_total`, origem `manual`, número `R-00000`/`P-00000` do business (o mesmo
  sequencial da Visão Unificada web). `descricao`, `documento` e `nf_numero` vão no `metadata`
  (`descricao`/`documento`/`nfe_numero`, as chaves que o cockpit web e a lista do app leem).
- `plano_codigo`: conta ativa do business, analítica (sem filhas) e do tipo (receber = receita; pagar =
  despesa ou custo). `contato_id` e `conta_id`: do business. `forma_pagamento`: rótulo ou chave do ERP.
- `parcelas` (2 a 60): `valor` é o TOTAL, dividido em centavos com a diferença na 1ª (a regra
  `dividirParcelas` do app: 100,00 em 3 = 33,34 + 33,33 + 33,33); uma parcela por mês a partir do
  vencimento, sem estourar o dia (31/01 → 28/02 → 31/03); descrição "X (i/n)", documento "D/i".
- `recorrencia` preenchida → `422 campos.recorrencia`: o Financeiro não tem gerador de título recorrente.
- Erros: `422 {erro:"validacao", mensagem, campos}`. Idempotência na tabela `app_idempotencia` (rota
  `financeiro.lancamentos`), na mesma transação dos títulos: mesma chave e corpo → `200` com a mesma
  resposta; corpo diferente → `422 idempotencia_conflito`; ainda gravando → `409 em_andamento`.
