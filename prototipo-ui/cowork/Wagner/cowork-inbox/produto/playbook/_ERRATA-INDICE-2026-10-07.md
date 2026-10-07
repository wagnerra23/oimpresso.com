---
sessao: "errata-indice"
titulo: "Produto — a thread 09 aparece em curso por erro da prova do índice, não por trabalho pendente"
executor: "[CL]"
data: 2026-10-07
base: wagnerra23/oimpresso.com@main ea4b4f0985
---
# Errata do `00-INDICE.md` do Produto (pedido ao Cowork)

O Code não edita o `00-INDICE.md` no espelho: a correção vale só quando o Cowork a fizer e o
próximo retorno a trouxer. Este arquivo diz o que mudar e por quê. Só a thread 09 é classe (a)
neste playbook; as 08, 10 e 11 ficam listadas no fim, com o motivo de não estarem aqui.

## 09 — entregue; a prova `execucao` é que não decide

A thread foi entregue sem mudar código de produção: as 5 buscas por `sub_sku` já filtravam pelo
negócio, e o [#8644](https://github.com/wagnerra23/oimpresso.com/pull/8644) (mergeado em
2026-10-05) acrescentou `tests/Feature/Produto/SubSkuEscopoNegocioContratoTest.php`, tenant 98 × 99,
com controle positivo em cada caso. O recibo é o `_saida-09.md`.

Reconferido em `ea4b4f0985`, lendo cada chamada:

| chamada | filtro de negócio |
|---|---|
| `ImportOpeningStockController.php:123` | `->where('P.business_id', $business_id)` (join `products AS P`) |
| `ProductController.php:1742` (`checkProductSku`) | join `products` + `->where('business_id', $business_id)` |
| `ProductController.php:1787` (`validateVaritionSkus`) | join `products` + `->where('business_id', $business_id)` |
| `PurchaseController.php:1639` | `->where('products.business_id', $business_id)` |
| `app/Services/Sells/ImportSalesService.php:276` (saiu do `ImportSalesController`) | `->where('imp_produto.business_id', $business_id)` |

A prova do json é do tipo `execucao`, que o placar não decide desde a ADR 0397 (o avaliador de
recibo saiu do repo). Por isso a thread fica `em curso (indecidível)` para sempre, mesmo entregue.

**Pedido:** na thread `09`, trocar `provas` por estas 4, todas decidíveis pelo placar:

```json
[
  {"tipo": "arquivo", "path": "tests/Feature/Produto/SubSkuEscopoNegocioContratoTest.php"},
  {"tipo": "contem", "path": "app/Http/Controllers/ImportOpeningStockController.php", "padrao": "->where('P.business_id', $business_id)"},
  {"tipo": "contem", "path": "app/Http/Controllers/PurchaseController.php", "padrao": "->where('products.business_id', $business_id)"},
  {"tipo": "contem", "path": "app/Services/Sells/ImportSalesService.php", "padrao": "->where('imp_produto.business_id', $business_id)"}
]
```

A prova de comportamento continua sendo o teste na lane `PHP / Pest (Estoque · MySQL)`; os 3
`contem` só travam que o filtro não suma do texto. Ressalva: no `PurchaseController.php` o padrão
aparece **2 vezes**, então apagar só o filtro da linha 1639 não derruba essa prova (derruba o teste).
Os dois `ProductController` ficam sem `contem` porque o padrão deles (`where('business_id'`) é
genérico demais para identificar a chamada.

## Placar medido

`node scripts/qa/placar.mjs --indice` em `ea4b4f0985`, com o json atual e com o json simulado
(as 4 provas acima aplicadas numa cópia de trabalho, depois restaurada; nada foi commitado no índice):

```
ANTES
Produto: entregue 7 de 14 · próximo 1 · em curso 4 · pendente 1 · bloqueada 1
  09 [em curso ] (indecidível) P0 · busca por sub_sku sem escopo de negócio fora do import de preço —  (prova "execucao" precisa do avaliador de recibo (não portado — ver docblock))
DEPOIS
Produto: entregue 8 de 14 · próximo 1 · em curso 3 · pendente 1 · bloqueada 1
  09 [feito    ] P0 · busca por sub_sku sem escopo de negócio fora do import de preço
```

## Fora desta errata

| thread | por que não é classe (a) |
|---|---|
| 08 | Código entregue no [#8812](https://github.com/wagnerra23/oimpresso.com/pull/8812). Faltava teste: o [#8899](https://github.com/wagnerra23/oimpresso.com/pull/8899) acrescenta `tests/Feature/Produto/ProdutoMenuPermissaoContratoTest.php`, que lê o `shell.menu` real. **Depois que ele entrar**, a prova `execucao` pode virar `{"tipo": "arquivo", "path": "tests/Feature/Produto/ProdutoMenuPermissaoContratoTest.php"}`. Antes do merge o placar marcaria "arquivo ausente", por isso não está pedido aqui. Resta o smoke de UI em produção (menu aberto + deep-links). |
| 10 | PR-a entrou no [#8721](https://github.com/wagnerra23/oimpresso.com/pull/8721). A prova `comparacao` (drawer do protótipo × Page) não foi medida do lado da Page: produção exige login e o agente não entra com senha. Precisa de [W], ou de uma sessão já logada, para rodar o `design-diff --probe` em `/units`. |
| 11 | PR-b entrou junto, no squash do #8721 (`_saida-11.md`). Mesmo bloqueio da 10: a comparação das 5 abas precisa da sessão logada. |

Decisões que seguem com o [W] (não executadas):
- **09:** `ProductController::checkProductSku` termina em `echo …; exit;` e não é testável por HTTP. Trocar por `return` deixaria testar, mas a ficha manda não mexer onde já escopa.
- **10/11:** Grupos de preço ainda abre o modal da Blade. Falta dizer se ganha thread própria.
- **10/11:** o protótipo desenha modal central (`FormModal`), e a Page usa o drawer PT-02 decidido pelo [W] em 2026-10-05. O protótipo e a ficha precisam refletir o drawer.
