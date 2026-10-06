---
casos: Cotações (orçamentos) · /sells/quotations
irmaos: Quotations.charter.md (lei) · Quotations.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: cotação é proposta ao cliente que divide a tabela `transactions` com o rascunho e se distingue só por `sub_status = quotation` — quem vê qual cotação, e de qual empresa, não pode mudar num refactor visual.
owner: wagner
last_run: "2026-10-06"
last_run_ci: "UC-QUO-01 3/3 verde no CT 100 (19 assertions, 2026-10-06) — veredito da lane PHP / Pest (Sells · MySQL) pendente deste PR"
---

# Casos de Uso & Aceite — Cotações

> **Fonte (ordem fixa, how-trabalhar):** aceite da **US-SELL-061** (`memory/requisitos/Sells/SPEC.md`) +
> **CU-SELL-08** do `SDD-tela-venda-v1.0.md` (status cotação) + ficha `03-orcamentos.md` do playbook
> `venda-menu` (UC-QUO-01..04). Código (`SellController@getQuotations` / `@getDraftDatables`) só
> confirmou o comportamento. Nada derivado do `.tsx`.
>
> **Teste:** `tests/Feature/Sells/SellsQuotationsContratoTest.php` — tenant 98 × adversário 99,
> `DatabaseTransactions`, headers do browser (`X-Inertia` + `X-Requested-With`).
>
> ⚖️ **Lane:** `PHP / Pest (Sells · MySQL)` — `.github/workflows/sells-pest.yml`.
>
> **Status:** ✅ passa (manifesto G-7) · 🧪 teste cita o UC, sem veredito · ⬜ não verificado · ❌ quebrou.

## UC-QUO-01 · Lista só as cotações do business, no escopo da permissão `[T0]` `[must]`
- **Persona:** Larissa (dona, `quotation.view_all`) e o vendedor do balcão (`quotation.view_own`).
- **Aceite:** Dado cotações de dois vendedores, um rascunho comum, uma venda final e uma cotação de outra empresa · Quando abro a lista de cotações · Então com `view_all` vejo as duas cotações do meu business; com só `view_own`, cada vendedor vê apenas a que criou; rascunho comum, venda final e a cotação da outra empresa nunca aparecem. Sem `view_all` nem `view_own`, a tela devolve 403; com `view_own`, abre `Sells/Quotations` dizendo quais permissões o usuário tem.
- **Dados da lista:** `GET /sells/draft-dt?is_quotation=1` — o endpoint que o Blade lê.
- **Teste:** `SellsQuotationsContratoTest` — `UC-QUO-01 [T0] com quotation.view_all a lista traz as cotações do business…`, `UC-QUO-01 com só quotation.view_own o vendedor vê apenas as cotações que ele criou`, `UC-QUO-01 a tela abre com quotation.view_own e é negada (403)…`.
- **Regressão que defende:** cotação de outra empresa na lista (ADR 0093); vendedor vendo cotação do colega; rascunho ou venda final misturados às cotações.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Enviar abre o documento da cotação (título "Orçamento", validade), não o da venda; a venda final segue com o layout de nota (2º caso da ficha `03-orcamentos.md`). **Medido 2026-10-06:** o botão "Enviar" da Page aponta `/sells/{id}/print?quotation=1`, e `SellPosController@printInvoice` só tem ramo `ajax()` — em navegação comum o corpo volta vazio. O Blade usa a impressão por AJAX e `quotation.downloadPdf`. Ganha id junto do conserto do botão.
- **[BACKLOG]** Salvar cotação não baixa estoque nem gera título; finalizar a venda gera os dois (3º caso da ficha). Exige `POST /pos` real com lastro de compra (receita do `SellsRepairSubtipoContratoTest`) — intent próprio.
- **[BACKLOG]** Converter preserva itens, quantidades e preços; 2ª conversão → 409 (4º caso da ficha). É da thread **Q3** (`QuotationConvertTest`), que promove o caso quando o teste existir.
- **[BACKLOG]** O endpoint de dados `/sells/draft-dt?is_quotation=1` não checa permissão de cotação: **medido 2026-10-06 no CT 100**, um usuário do mesmo business **sem** `quotation.view_all` nem `quotation.view_own` recebeu as duas cotações da fixture. Outro business segue fora (o [T0] vale). Ganha id junto do conserto (gate no `getDraftDatables`).

## Trilha do tempo
- 2026-10-06 · [CL] trio completado na thread Q1 do playbook `venda-menu`. Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358 · US-SELL-061.
