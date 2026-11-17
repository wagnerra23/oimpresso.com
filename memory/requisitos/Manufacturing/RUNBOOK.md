---
title: "Runbook · Manufacturing"
owner: W
status: ativo
last_validated: "2026-11-17"
---

# Runbook · Manufacturing

> Reescrito em 2026-11-17 (lote 4.3 da auditoria do módulo). A versão anterior, validada em
> 2026-06-08, citava 4 comandos (`stock:recalculate`, `manufacturing:mrp-calculate`,
> `manufacturing:recalculate-costs`, `docvault:audit-module`), uma Entity (`SalesForecast`), uma
> tabela (`manufacturing_recipes`), uma coluna (`overhead_percent`) e uma mensagem de erro
> ("Insufficient stock") que não existem no código. Cada sintoma abaixo foi conferido no
> `origin/main` `18fd05d4bf`.
>
> O módulo **não tem comando artisan**: `Modules/Manufacturing/Console` está vazio desde o lote 2.8
> (que removeu o `manufacturing:health`, sem chamador). Por isso não há seção de comandos úteis.
> Os RUNBOOKs de cada tela ficam ao lado: `RUNBOOK-recipes`, `-ingredientes-editor`, `-insumos`,
> `-producao`, `-report`, `-settings`.

## Problema: finalizar a ordem falha por falta de insumo

**Sintoma:** ao finalizar (ou gravar já finalizada), a ordem volta para a lista com mensagem de
erro, sem ordem nova e sem baixa de estoque.

**Causa:** o servidor não confere o saldo antes de gravar, mas ao finalizar o núcleo
(`TransactionUtil::mapPurchaseSell`) liga o consumo aos lotes de compra; sem lote suficiente ele
lança, e a transação inteira é desfeita (UC-OP-15). Se a empresa tem `allow_overselling` ligado
nas configurações de venda, a ordem grava e o saldo do insumo fica negativo. O rascunho não mexe em
estoque e grava sempre.

**Correção:** dar entrada no insumo (compra ou transferência) no local da ordem e finalizar de novo;
ou gravar como rascunho e finalizar depois. A ADR ARQ-0002 do módulo prometia checagem prévia com
trava; não é o que o código faz (lote 4.4). Pela D3 da ADR 0448, a regra de material da ordem nova
é da Produção.

## Problema: custo da ordem diferente do custo da receita

**Sintoma:** a lista de Ordens mostra um valor; a lista de Receitas ou o Relatório mostram outro
para o mesmo produto.

**Causa:** as telas respondem perguntas diferentes. A lista de Ordens mostra o total **gravado** na
ordem (`transactions.final_total`); Receitas e Relatório **recalculam** pelo preço de compra de
hoje (`variations.dpp_inc_tax`). Insumo que subiu depois da ordem explica a diferença.

**Correção:** nenhuma, se o insumo mudou de preço. Desde o lote 1.5 da auditoria o total da ordem
é calculado no servidor no `store` e no `update` (UC-OP-19); um total gravado antes disso vinha do
navegador.

## Problema: a tela não abre (403)

**Sintoma:** `403 Unauthorized action.` ao abrir uma tela do módulo.

**Causa, por tela:**
- Receitas, editor e Insumos: falta `manufacturing.access_recipe`.
- Ordens, Relatório e Configurações: falta `manufacturing.access_production` (decisão B3 do [W],
  2026-11-09).
- Todas: a empresa não tem `manufacturing_module` no pacote, ou o login é de contato do portal
  (`CheckUserLogin`, UC-MCFG-06).
- Ordem ou painel da ordem: o local da ordem não está entre os locais permitidos ao usuário
  (UC-OP-22).

**Correção:** papel em `/roles/{id}/edit`, ou pacote em `/superadmin/packages/{id}/edit` com
"Atualizar inscrições existentes" marcado. Nunca hardcode de `business_id`.

## Problema: colunas de custo vazias

**Sintoma:** custo, total e margem aparecem como `—` nas telas.

**Causa:** o usuário não tem `view_purchase_price`. Desde o lote 2.6 o servidor tira o custo das
respostas (`Support/OcultaCusto`).

**Correção:** dar a permissão no papel, se a pessoa deve ver custo.

## Problema: receita, ordem ou detalhe dá 404

**Sintoma:** 404 ao abrir uma receita, o detalhe dela ou o painel de uma ordem.

**Causa:** o registro é de outra empresa ou não existe. `mfg_recipes` não tem `business_id`; o
servidor confere a cadeia `variation_id → products.business_id` e devolve 404 para o que não é da
empresa da sessão (UC-OP-12, UC-OP-13, UC-OP-14).

**Correção:** conferir a empresa da sessão. Não é defeito.

## Problema: quantidade da ordem gravada ×1000

**Sintoma:** insumo em sub-unidade (por exemplo `0,125`) gravado como 125.

**Causa:** conversão de número feita duas vezes (`num_uf`). Consertado no lote 1.2 (UC-OP-20).
Ordem gravada antes do conserto continua com o número errado.

**Correção:** para ordem antiga, conferir a linha e corrigir pela edição do rascunho. Ordem
finalizada mexe em estoque e valor: REGRA MESTRE ([proibicoes.md](../../proibicoes.md)), com dupla
prova e tabela antes→depois aprovada pelo [W].

## Onde olhar

- Testes do módulo: lane `PHP / Pest (Manufacturing · MySQL)` (`.github/workflows/manufacturing-pest.yml`).
  Não roda local; para rodar avulso, CT 100.
- Trilha de receita e ordem: `/auditoria`, com a empresa preenchida desde o lote 2.7 (UC-RECIPE-25).
- Logs: `storage/logs/laravel.log`, prefixo `manufacturing.` (`logSafeEmergency` nos controllers).
