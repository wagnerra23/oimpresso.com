---
sessao: "Q1"
titulo: Quotations — trio completo (casos.md + teste de contrato)
autor: "[CL]"
data: 2026-10-06
base: wagnerra23/oimpresso.com@main bab78f4764 (lida 2026-10-06)
---
# Q1 · recibo

**Entregue:**
- `resources/js/Pages/Sells/Quotations.casos.md` — **UC-QUO-01** `[T0]` `[must]` (lista só cotações do business, no escopo `view_all` × `view_own`; 403 sem as duas). Fonte: aceite da US-SELL-061 + CU-SELL-08 + ficha `03-orcamentos.md`; o código só confirmou.
- `tests/Feature/Sells/SellsQuotationsContratoTest.php` — 3 casos citando UC-QUO-01, tenant 98 × 99, dados pelo endpoint que o Blade lê (`/sells/draft-dt?is_quotation=1`). **3/3 verde no CT 100, 19 assertions** (2026-10-06), antes do PR.
- `.github/workflows/sells-pest.yml` — o teste entrou na allowlist da lane `PHP / Pest (Sells · MySQL)`. Os gatilhos de path já cobriam `tests/Feature/Sells/**` e o `SellController`.
- `Quotations.charter.md` — só `last_validated`, `status_detail` e links pro casos/teste.

**Não fiz, e por quê:**
- **Charter segue `draft`** (a ficha pedia "sai de draft"). "live" seria falso hoje: o GET comum ainda entrega o Blade (Q2 não ligou) e a lista React está quebrada (achado A abaixo). Sai de draft depois de Q2 + o conserto da Q3.
- **UC-QUO-02, 03 e 04 ficaram `[BACKLOG]` sem id**, cada um com o motivo no casos.md: 02 depende do conserto do botão Enviar (achado B); 03 exige `POST /pos` real com lastro de compra (intent próprio); 04 é da Q3, que promove o caso quando o `QuotationConvertTest` existir (combinado com a sessão Q3).

**Achados (medidos, fora do prefixo da Q1 — não consertei):**
- **A** · `getQuotations` passa à Page `urls.datatable = '/sells/quotations?is_quotation=1'`; essa rota cai no próprio `getQuotations`, que não tem ramo AJAX e devolve HTML. `res.json()` falha e a lista React sai sempre vazia. O Blade lê `/sells/draft-dt?is_quotation=1`. **A Q3 assumiu o conserto** (prefixo dela).
- **B** · "Enviar" aponta `/sells/{id}/print?quotation=1`; `SellPosController@printInvoice` só tem ramo `ajax()`, então em navegação comum o corpo volta vazio.
- **C** · `/sells/draft-dt?is_quotation=1` não checa permissão de cotação. Medido no CT 100: um usuário do mesmo business **sem** `quotation.view_all` nem `quotation.view_own` recebeu as duas cotações da fixture. Outro business seguiu fora.
- **D** (da Q3, registrado aqui porque afeta a 6-ter) · o "Converter em fatura" do Blade está atrás de `config('constants.enable_convert_draft_to_invoice')`, que é `false` (`config/constants.php:85`). Logo o Blade **não** oferece converter hoje; a premissa (2) da 6-ter não se sustenta medida.

**Prova:** `arquivo` `resources/js/Pages/Sells/Quotations.casos.md` (placar). Mordida da defesa: `casos-coverage-guard` (3 modos do `casos-gate.yml`) verde local; veredito da lane `sells-pest` no PR.
