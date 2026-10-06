---
sessao: "03"
titulo: Orçamentos (cotações) — trio, cutover e conversão em venda
dono: "[CL]"
base: wagnerra23/oimpresso.com@main 23c3f080aa94 (2026-10-06)
absorve: pedido [W] 2026-10-06 "playbook de Orçamentos" — **não ganha playbook próprio**: cotação é `Sells/Quotations` (venda com status draft + sub_status quotation), que já é deste playbook (linha `venda-cotacoes` do índice).
---
# 03 · Orçamentos (cotações)

## Lido no turno
`resources/js/Pages/Sells/Quotations.charter.md` (status **draft**, `last_validated` 2026-05-15): lista em React viva só com `X-Inertia`; GET comum ainda entrega o Blade `sale_pos.quotations`; permissão `quotation.view_all` **ou** `quotation.view_own`; testes `Wave1QuotationsBaselineTest` + `Wave1QuotationsInertiaTest`. **Não há `Quotations.casos.md`** (a árvore de `Pages/Sells/` lista 21 arquivos e nenhum é esse). Converter em venda está nos Non-Goals ("futuro: FSM `quote_accepted`").

## Threads
| # | o que faz | depende | prova |
|---|---|---|---|
| **Q1** | Completa o trio: `Quotations.casos.md` com UC-QUO-01..04 abaixo; charter sai de `draft` | — | `prototipo-readiness` marca Quotations ✅ |
| **Q2** | Entra no cutover: chave `mwart.vendas_cotacoes` no helper do **C0**; liga junto do **C1** (baixo uso) | C0 | GET comum em produção (biz=1) devolve `Sells/Quotations` |
| **Q3** | Converter cotação em venda (1 clique) | **D-ORC-1** · FSM | `QuotationConvertTest` |
| **Q-CC** | [CC] no protótipo: hoje há **duas rotas** para a mesma lista (`orcamentos` → `OrcListPage` e `venda-cotacoes`). Fica uma | **D-ORC-2** | `app.jsx` com uma rota só |

## Casos de uso (Q1; Q3 só depois de D-ORC-1)
### UC-QUO-01 · Lista só cotações do business, com o escopo da permissão · `must` `[T0]`
- **Aceite:** com `view_own` vejo só as minhas; com `view_all`, todas do business; nada de outro business. Controle positivo: venda final não aparece na lista.
- **Teste:** `Wave1QuotationsInertiaTest` (já existe — citar, não duplicar) + caso `view_own` se faltar.

### UC-QUO-02 · Enviar gera o PDF da cotação, não o da venda · `must`
- **Aceite:** "Enviar" abre o layout de cotação (título "Orçamento", validade). Controle positivo: a venda final segue com o layout de nota.
- **Teste:** `QuotationPrintTest` — `UC-QUO-02`.

### UC-QUO-03 · Editar cotação não baixa estoque nem gera financeiro · `must` `[T0]`
- **Aceite:** salvar cotação (status draft/quotation) não cria movimento de estoque nem título. Controle positivo: finalizar a venda cria os dois.
- **Teste:** `QuotationSideEffectsTest` — `UC-QUO-03`.

### UC-QUO-04 · Converter preserva itens e preço da cotação (Q3) · `must` `[T0]`
- **Aceite:** converter cria a venda com os mesmos itens, quantidades e preços, ligada à cotação; a cotação sai da lista como "convertida". Converter duas vezes → 409. Controle positivo: cotação não convertida segue editável.
- **Teste:** `QuotationConvertTest` — `UC-QUO-04`.

## Decisões [W]
- **D-ORC-1** — Converter em venda entra agora ou espera o FSM `quote_accepted`? **Proposta [CC]:** espera o FSM (o charter já diz isso); Q1 e Q2 seguem sem ela.
- **D-ORC-2** — No protótipo, qual rota fica: `orcamentos` ou `venda-cotacoes`? **Proposta [CC]:** `venda-cotacoes`, que é a que espelha a Page viva.
