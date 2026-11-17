---
id: requisitos-manufacturing-adr-arq-0003-receita-ordem-e-mrp-como-o-codigo-faz
slug: ARQ-0003-receita-ordem-e-mrp-como-o-codigo-faz
title: "Receita plana, ordem que só barra falta de estoque ao finalizar, MRP em Compras: substitui ARQ-0001, ARQ-0002 e TECH-0001"
status: proposto
lifecycle: ativo
authority: canonical
decided_by: [W]
supersedes:
  - "ARQ-0001-recipes-como-bom-hierarquico"
  - "ARQ-0002-production-order-decrementa-estoque-atomicamente"
  - "TECH-0001-mrp-calcula-demand-backward"
related_adrs:
  - "0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica"
  - "0093-multi-tenant-isolation-tier-0"
pii: false
---

# ADR ARQ-0003 (Manufacturing) · Receita plana, falta de estoque barrada só ao finalizar, MRP em Compras

- **Status**: proposto. O merge pelo [W] ratifica.
- **Data**: 2026-11-17
- **Categoria**: arq
- **Origem**: lote 4.4 da auditoria do módulo (2026-11-09, aprovada pelo [W]).

## Contexto

As três ADRs do módulo, todas `accepted` em 2026-04-22, descrevem um sistema que o código não
tem. Ninguém as seguia: elas nasceram do mesmo batch de documentação gerada que o SCOPE e o
ARCHITECTURE do módulo. Medido no `origin/main` `18fd05d4bf`:

| ADR | Afirma | O código |
|---|---|---|
| ARQ-0001 | Tabela `manufacturing_recipes`; ingrediente pode ser produto com receita; custo calculado **recursivamente**; dependência circular detectada ao salvar | A tabela é `mfg_recipes`. `RecipeBomService::custoDosIngredientes` é um laço plano: Σ quantidade × `variations.dpp_inc_tax` × multiplicador da sub-unidade. Um ingrediente que tem receita própria entra pelo preço de compra da variação, sem descer na receita dele. Detecção de ciclo: 0 ocorrências em `Modules/Manufacturing/Services` |
| ARQ-0002 | Ordem em `DB::transaction`; **checa disponibilidade antes**, com `lockForUpdate`; decrementa insumos e **depois** incrementa o acabado; falta = rollback | A transação existe (`ProductionController` `store` e `update`, `beginTransaction`/`commit`/`rollBack`). `lockForUpdate` no módulo: 0 (`git grep -c lockForUpdate -- Modules/Manufacturing`, rc=1). Não há checagem prévia. A entrada do acabado (`createOrUpdatePurchaseLines`) vem **antes** da baixa dos insumos (`decreaseProductQuantity`), que é um `decrement` de uma linha de `variation_location_details`, atômico na linha e sem trava. A falta de estoque só aparece ao **finalizar**, depois da baixa: o `TransactionUtil::mapPurchaseSell` não acha lote de compra para o consumo e lança, a menos que a empresa tenha `pos_settings.allow_overselling` ligado; a exceção desfaz a transação inteira (UC-OP-15) |
| TECH-0001 | `MrpService::calculate($period)` calcula a demanda de trás para frente, pela receita em árvore | `MrpService` não existe (`git grep` = só a própria ADR). O MRP do sistema é `Modules/Compras/Services/NecessidadeDeCompra` (US-COM-030): soma reservas das OS e das vendas, estoque mínimo, saldo e compras a caminho, sem descer em receita |

O handoff normativo *"PROTÓTIPO OFICIAL - FABRICAÇÃO V1"* §6, regra 2, e o R-18 pedem que
**estoque insuficiente avise e não bloqueie** no formulário novo. Esse formulário era a US-MANU-007,
cancelada (D1); o servidor de hoje faz outra coisa, descrita acima.

## Decisão

Substituir as três por esta, que registra o que vale hoje e quem decide o resto.

1. **Receita.** A receita é plana: uma lista de ingredientes, cada um uma variação com
   quantidade e sub-unidade opcional. O custo é recalculado na leitura pelo preço de compra de
   hoje (US-MANU-001). Submontagem não é resolvida pela receita. Composição em árvore, com
   recursão, é a do `product_bom` + `app/Domain/Inventory/Services/BomResolver`, que a D2 da
   [ADR 0448](../../../../decisions/0448-fabricacao-cadastro-producao-execucao-product-bom-fonte-unica.md)
   torna a fonte única da composição. Detecção de ciclo, se for necessária, é do `BomResolver`.
2. **Ordem.** Enquanto a ordem Blade da Fabricação existir (decisão B1 do [W], 2026-11-09):
   gravar e finalizar acontecem numa transação de banco; falha no meio desfaz tudo. O rascunho
   não mexe em estoque. Finalizar sem saldo de insumo **falha e desfaz** (UC-OP-15,
   `OrdemErroTratadoTest.php`), a menos que a empresa permita vender sem estoque
   (`allow_overselling`), caso em que grava e o saldo pode ficar negativo. Não há checagem prévia
   nem `lockForUpdate`. O total é calculado no servidor (UC-OP-19). A ordem nova, e com ela a regra
   de material da D3 (a ordem existe sem material; iniciar e concluir é que bloqueiam), é da
   Produção (D1, ADR 0448).
3. **MRP.** Fica em Compras (`NecessidadeDeCompra`, US-COM-030). Este módulo não terá
   `MrpService`.

## Consequências

**Positivas:**
- Quem ler as ADRs do módulo encontra o comportamento que o código tem, e o lugar onde o resto
  foi decidido (ADR 0448, US-COM-030).
- O `RUNBOOK.md` (lote 4.3) descreve o mesmo comportamento de estoque, sem contradizer uma ADR
  aceita.

**Negativas:**
- Duas ordens finalizadas ao mesmo tempo sobre o mesmo insumo não se travam. O mapeamento por
  lote reduz o risco de saldo negativo sem `allow_overselling`, mas não foi medido sob concorrência.
  Muda quando a ordem for para a Produção.
- Receita com submontagem continua custando o subproduto pelo último preço de compra dele, não
  pela receita dele, até a migração para o `product_bom` (ADR 0448, etapa 4).

## Alternativas consideradas

- **Consertar o código para seguir a ARQ-0002** (checar e travar antes de gravar): rejeitado.
  A D1 tira a ordem daqui e a D3 define outra regra de material para a ordem da Produção; investir
  na ordem Blade além do conserto da B1 seria trabalho descartado.
- **Editar as três ADRs aceitas**: rejeitado. Append-only; as três ficam como estavam e esta as
  substitui.
- **Implementar recursão na receita**: rejeitado. Duplicaria o `BomResolver` e contraria a D2.

## Não medido

- Quantas receitas em produção têm ingrediente que também tem receita (submontagem real).
- Concorrência real: quantas ordens simultâneas sobre o mesmo insumo já aconteceram.
