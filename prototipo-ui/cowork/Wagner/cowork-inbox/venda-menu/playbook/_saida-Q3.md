---
sessao: "Q3"
titulo: "Converter cotação em venda — saída da thread"
autor: "[CL]"
criado: 2026-10-06
base: bab78f4764
thread: 03-orcamentos.md §Q3
veredito: "entregue no PR #8843 (aguarda ok [W]: valor/estoque); prova QuotationConvertTest 4/4 na lane sells-pest, run 37523233795; o botão nasce ESCONDIDO em produção porque a flag do Blade é false."
---

# _saída Q3 · Converter cotação em venda

## O que saiu — PR #8843
| arquivo | mudança |
|---|---|
| `SellPosController@convertToInvoice` | reusado tal como está; só ganha a guarda de 409 na 2ª conversão (antes: 404), fora do `try` |
| `SellController@getQuotations` | `permissions.convert` = permissão de venda **e** `constants.enable_convert_draft_to_invoice` (a condição do Blade) · `urls.convert` = a rota do Blade · `urls.datatable` corrigido |
| `SellController@getDraftDatables` | `->setRowId('id')`: devolve o id da linha em `DT_RowId` |
| `Pages/Sells/Quotations.tsx` | botão "Converter em venda" + confirmação + trava de clique duplo; navegação comum para a URL do Blade |
| `QuotationConvertTest` | UC-QUO-04, lane `sells-pest`, tenant 98 × 99 |

## Prova
`tests/Feature/Sells/QuotationConvertTest.php` — **4 passed**, lane `PHP / Pest (Sells · MySQL)`, run **37523233795** (head `2d0a662b8d`; total da lane 333 passed, 2286 assertions). `test-lane-coverage --pr 8843`: 1 de 1 teste executou e passou.

REGRA MESTRE, conta à mão = recalculado das linhas: cotação 3 un × 1.234,50 → venda `final` com total 3.703,50, estoque 10 → 7; 2ª conversão 409 com estoque em 7; cotação de outra empresa: 404, intocada.

## Achados (medidos nesta thread)
1. **A premissa (2) da 6-ter é falsa.** O Blade **não** oferece "Converter em fatura": o item está atrás de `config('constants.enable_convert_draft_to_invoice')` = `false` (`config/constants.php:85`, "Experimental beta feature"). O botão React segue a mesma condição e **em produção nasce escondido**. Ligar é decisão [W]. A dependência Q2 → Q3 ("a Larissa perderia a função") perde o motivo: ela não tem a função hoje.
2. **A lista React de cotações saía sempre vazia** (achado da sessão Q1): `urls.datatable` apontava para `/sells/quotations`, que devolve HTML. Passou para `/sells/draft-dt?is_quotation=1`.
3. **O `draft-dt` não manda o id da linha** (`removeColumn('id')`). Com a lista consertada, Editar/Enviar/Converter iriam para `/sells/0`. Corrigido com `setRowId('id')`. Pego pelo próprio teste (run 37521732482).

## Resíduos — decisão [W] ou outra thread
- **FSM:** a conversão não mexe em `current_stage_id`. Ligar `cliente_aprovou` (quote_sent → quote_approved) reservaria estoque (`ReservarEstoque`) por cima da baixa do `convertToInvoice`. Não liguei.
- `catch (Exception $e)` do `convertToInvoice` não casa (sem `use Exception`): erro sai como 500. Pré-existente.
- A rota de conversão é GET e não lê a flag. Pré-existente.
- O `draft-dt` não checa `quotation.*` (medido pela Q1 no CT 100): usuário do mesmo business sem permissão de cotação recebe as cotações. A Page é gateada no `getQuotations`; o endpoint não. Pré-existente.
- `UC-QUO-04` no `Quotations.casos.md` e o Non-Goal "Converter" do charter ficam para depois dos PRs #8843 e #8844 (combinado com a Q1).
