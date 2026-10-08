---
date: "2026-10-08"
time: "03:40 BRT"
slug: gestao-fila-merges-noite-07-out
tldr: "Gestão da fila de merges na noite de 07/10 para 08/10. 41 PRs mergeados (#9002–#9042), com deploy confirmado em cada um. Fiscal 15 completa, Sistema 05 completa e 17 relatórios da thread Sistema 07. Fechadas 2 SQL injections e 5 endpoints sem permissão ou sem local permitido. Parada a pedido do [W] (cota semanal em 97%). Ficam #9043 e #9044 na fila e 5 pendências do [W]."
prs: [9002, 9003, 9004, 8999, 9005, 9006, 9007, 9008, 9009, 9010, 9011, 9012, 9013, 9014, 9015, 9016, 9017, 9018, 9019, 9020, 9021, 9022, 9023, 9024, 9025, 9026, 9027, 9028, 9029, 9030, 9031, 9032, 9033, 9034, 9035, 9036, 9037, 9038, 9039, 9040, 9041, 9042]
---

# Handoff — Gestão da fila de merges (noite 07/10 · 2)

Esta sessão é a "Gerente da fila de merges (noite 07/10 · 2)" e sucede o handoff `2026-10-07-1855`. O mandato do [W] continua o mesmo: "gerencie o merge de todos, decida". As exceções também: valor fora do molde, migration destrutiva, cutover e baseline/foto (ADR 0409). O [W] parou a sessão às ~06:30Z porque a cota semanal chegou a 97%.

## Estado ao parar

- **Na fila:** #9043 (o resumo do estoque `get-stock-value` passa a exigir `stock_report.view` e `view_product_stock_value`) e #9044 (o JSON do estoque sai sem as colunas de valor para quem não pode vê-las). Os dois passaram na lane pelo nome do teste. O `fila-perm` mergeia sozinho, se ainda estiver rodando. Se não estiver, mergeie manualmente quando os 48 required estiverem verdes. O smoke dos dois em biz=1 fica com a sessão Sistema 07.
- **Sessões paradas até amanhã:** Sistema 07 (relatórios), Sistema 04 (espera o ok do [W] para a thread 06) e App das lojas.

## O que entrou

- **Fiscal 15 completa:** 15a #9003, 15b #9015 (link assinado do contador, teste Tier 0) e 15c #9018.
- **Sistema 05 completa:** Impostos, Tipos de serviço e Esquemas de fatura. A F3-1 entrou em #9006/#9007/#9009, a F3-2 em #9011/#9012/#9013 e o recibo em #9014.
- **Sistema 07, 17 relatórios:** #9016, #9020, #9022–#9027, #9029–#9031, #9034, #9036–#9038, #9041 e #9042. O smoke foi feito contra o JSON da Blade em todos. Lotes, Mesas e Itens por atendente bateram só no vazio, porque biz=1 não tem esses dados; o valor está provado pelos testes.
- **Segurança:**
  - SQL injection: #9008 (6 relatórios) e #9040 (getProfit).
  - Permissão: #9010 (stock-details), #9035 (service-staff-line-orders) e #9039 (get-stock-by-sell-price).
  - Locais permitidos: #9032 (Compra e venda) e #9033 (Mesas).
- **Flaky:** #9019 (helper do AppEquipe) e #9028 (sufixo no username do UserFactory).
- **Cowork:** #8999 e #9017 (import dos handoffs 53 e 54), e #9021 (recibos `_saida-05` e `_saida-15` enviados e conferidos byte a byte).

## Pendências do [W]

1. **Valor:** o "Total devido" do relatório Clientes e fornecedores ignora o desconto de razão de venda (`devidoDoContato`, #9023). Medido em produção em 08/10 ~00:20Z: zero transações `ledger_discount` em todos os negócios. Corrigir não muda número de ninguém hoje. Com o "pode" do [W], a Sistema 07 abre o PR de uma linha (o teste vai de 300 para 280).
2. **#9000:** o [W] escreve `/design-sync` no chat para subir `venda-v3.jsx`, e então o PR é mergeado.
3. **Sistema 04:** ok para a thread 06. A thread 01 espera a decisão da tela, da chave `MWART_SISTEMA_USUARIOS_INDEX` e da regra D5.
4. **GroupTaxController** (achado do `_saida-05`): aceita alíquotas de outro negócio como sub-imposto, e o `store()` não confere permissão. É Tier 0 e mexe em valor; precisa de thread própria com prova por dois caminhos.
5. **Do handoff anterior:** abrir o ticket no suporte do GitHub (~20 jobs simultâneos) e rodar o seeder `NfeIcmsUfSeeder`.

## Lições

- **Fixture com data acima de 2038 em coluna TIMESTAMP:** o MySQL 8 grava 0000-00-00. O CI quebrou e o CT 100 (MariaDB) passou (#9022). Datas além de 2038 só em DATETIME.
- **Consumidor de endpoint se mede pela chamada, não pelo id do elemento.** O `#closing_stock_by_sp` da página de estoque vem de outro endpoint. Eu tinha deduzido errado, e a Sistema 07 corrigiu medindo.
- **O deploy das 03:49 falhou por timeout de SSH ao Hostinger** e o `gh run rerun --failed` resolveu.

## Ferramentas

As ferramentas estão em `D:/oimpresso.com/.claude/gestor-fila-scripts/noite-0710/`, iguais às do handoff anterior. O `fila-perm` e o `pausa-deploy` expiram em 2h e precisam ser religados. Antes de mexer, confira se ficou um arquivo `PAUSA` órfão.

## Estado MCP no momento do fechamento

Não consultado nesta sessão, para economizar cota. Estado conhecido: brief de 07/10 sem cycle ativo e com 3 HITL do [W].
