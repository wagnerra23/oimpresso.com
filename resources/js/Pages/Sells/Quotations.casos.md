---
casos: Cotações (orçamentos) · /sells/quotations
irmaos: Quotations.charter.md (lei) · Quotations.tsx
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: cotação é proposta ao cliente que divide a tabela `transactions` com o rascunho e se distingue só por `sub_status = quotation` — quem vê qual cotação, e de qual empresa, não pode mudar num refactor visual.
owner: wagner
last_run: "2026-10-21"
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

---

## Termômetro de acesso da proposta (US-SELL-070 · UC-FLX-04)

> **Âncora:** aceite da **US-SELL-070** (`memory/requisitos/Sells/SPEC.md`) e o **UC-FLX-04** do catálogo
> de fluxos (na etapa Enviado, registrar quando o cliente abre a proposta; sem abertura em 48 h, tarefa de
> retorno ao vendedor). Nesta entrega é o backend: a abertura do link público `/quote/{token}` vai para
> `transaction_quote_accesses` e a rotina diária `sells:quote-follow-up` cria o retorno no agendamento do
> CRM. A tela ainda não mostra "aberto em" — fica no backlog abaixo. Prova:
> `tests/Feature/Sells/QuoteAccessTermometroContratoTest.php`.

## UC-QUO-05 · A abertura do link grava a primeira data e soma as aberturas · `must`

**Dado** um orçamento em rascunho de cotação e o relógio em 20/10/2026 10:00 **Quando** o cliente abre o link às
10:00 e de novo às 11:30 **Então** fica gravado primeira abertura 10:00, última 11:30 e 2 aberturas — a
segunda não troca a primeira.

Status: 🧪

## UC-QUO-06 · HEAD, prefetch e o próprio vendedor não contam como abertura · `must`

**Dado** o mesmo orçamento **Quando** chega um HEAD, um GET com `Sec-Purpose: prefetch`, um GET de usuário
logado da própria empresa, ou o link de uma venda final **Então** nenhuma abertura é gravada (0); um GET
comum sem login, logo depois, grava 1.

Status: 🧪

## UC-QUO-07 · Falha ao gravar a abertura não derruba a página do cliente · `must`

**Dado** o link público de um orçamento que abre com 200 e grava 1 abertura **Quando** a gravação passa a
falhar **Então** o link segue abrindo com o mesmo status e o contador fica em 1; um HEAD também não soma.

Status: 🧪

## UC-QUO-08 · Sem abertura em 48 h, uma tarefa de retorno para o vendedor, só na empresa da rotina · `[T0]` `must`

**Dado** no business 98 o orçamento A enviado há 50 h e nunca aberto, B enviado há 50 h e aberto uma vez, C
enviado há 10 h, e no business 99 o orçamento D enviado há 50 h **Quando** a rotina roda para o 98
**Então** cria 1 tarefa (A), atribuída a quem fez o orçamento e ligada a ele; B, C e D ficam com 0. Rodar de
novo cria 0 e A segue com 1.

Status: 🧪

## Entrega prevista ao converter (US-PCP-028 · UC-FLX-06)

> **Âncora:** UC-FLX-06 do catálogo de fluxos (`app/Domain/Fsm/Modelos/CasosDoPrototipo.php`, *"calcula a
> entrega pela fila dos postos"*). O cálculo é contrato do Produção (UC-PCPRZ-01..04 em
> `Modules/Producao/Resources/js/Pages/Producao/Postos/Index.casos.md`); aqui fica o que a tela de cotações
> recebe e mostra.

## UC-QUO-09 · A confirmação de converter mostra a entrega prevista pela fila · `[T0]` `must`

**Dado** uma cotação do business 98 e o Produção no pacote **Quando** abro "Converter em venda" **Então** a
confirmação pergunta `GET /producao/vendas/{id}/prazo` e mostra "Entrega prevista: dd/mm" antes do
Converter; sem roteiro de produção, diz que não há como prever. A Page recebe a URL em `urls.prazo` (null sem
o Produção no pacote: a confirmação não mostra prazo). A cotação de outro business pela mesma URL dá 404.

**Teste:** `tests/Feature/Sells/SellsPrazoEntregaContratoTest.php` — `UC-QUO-09`.

Status: 🧪

## UC-QUO-10 · A lista de cotações mostra se o cliente abriu a proposta · `must`

**Dado** no business 98 o orçamento A aberto pelo cliente duas vezes (primeira 20/10 10:00, última 11:30) e o
orçamento B nunca aberto **Quando** abro a lista de cotações **Então** a coluna "Aberto" de A mostra a primeira
abertura e "2×", e a de B mostra "Não aberto". Âncora: aceite 2 da US-SELL-070 · UC-FLX-04.

- **Dados da lista:** `quote_first_opened_at`, `quote_last_opened_at` e `quote_open_count` no mesmo `GET /sells/draft-dt?is_quotation=1`, lidos de `transaction_quote_accesses` pelo business da cotação.
- **Teste:** `tests/Feature/Sells/QuoteAccessTelaContratoTest.php` — `UC-QUO-10`. O [T0] (linha de acesso de outro business não vira abertura, também na lista) está no `UC-VSHOW-21` do `Show.casos.md`.

Status: 🧪

## Aprovação do orçamento pelo link (US-SELL-074)

Fatia 1: o vendedor gera o link e o serviço monta o que o cliente vê. A página pública, a decisão
do cliente e o movimento da FSM entram nas próximas fatias. Atrás de `SELLS_ORCAMENTO_ACEITE_LINK`
(default desligada). Não muda valor nem estoque.

## UC-OAC-01 · Gerar o link guarda só o sha256 do token, com validade · `must`

**Dado** no business 98 um orçamento aberto e o vendedor com `quotation.update` **Quando** gera o link
**Então** recebe `/orcamento/{token}` com token de 48 caracteres, e o banco guarda só o sha256, válido por 7 dias.

- **Persona:** Vendedor (`quotation.update`).
- **Teste:** `tests/Feature/Sells/OrcamentoAceiteLinkContratoTest.php` — `UC-OAC-01`.
- **Regressão que defende:** token em claro no banco, ou link que nunca vence (o `invoice_token` legado não vence).

Status: ⬜ (sem verde ainda — o 1º run da lane Sells caiu por fixture do teste, corrigida em 3f0641244e)

## UC-OAC-02 · Gerar de novo fecha o link anterior · `must`

**Dado** um link aberto **Quando** o vendedor gera outro **Então** o anterior fica vencido e só o novo abre.

- **Persona:** Vendedor que reenviou o orçamento.
- **Teste:** `tests/Feature/Sells/OrcamentoAceiteLinkContratoTest.php` — `UC-OAC-02`.
- **Regressão que defende:** dois links abertos para o mesmo orçamento, e o cliente aprovando pelo link velho.

Status: ⬜ (sem verde ainda — o 1º run da lane Sells caiu por fixture do teste, corrigida em 3f0641244e)

## UC-OAC-03 · O cliente vê itens, frete e o total gravado, e nada mais · `must`

**Dado** orçamento com 2 × 150,00 + 1 × 49,90 e frete 10,00 **Quando** o link é lido **Então** aparecem os
subtotais 300,00 e 49,90, frete 10,00 e total 359,90 (o total gravado, sem recálculo), sem nome do cliente
nem custo. Token desconhecido não devolve nada.

- **Persona:** Cliente que abre o link (sem login).
- **Teste:** `tests/Feature/Sells/OrcamentoAceiteLinkContratoTest.php` — `UC-OAC-03`.
- **Regressão que defende:** página pública mostrando custo, margem ou dado do cliente, ou um total recalculado diferente do gravado.

Status: ⬜ (sem verde ainda — o 1º run da lane Sells caiu por fixture do teste, corrigida em 3f0641244e)

## UC-OAC-04 · Não gera link de outra empresa, de venda final, nem com a flag desligada · `[T0]` `must`

**Dado** o vendedor do 98 **Quando** pede link de orçamento do 99 **Então** 404; de venda final, 422; com a
flag desligada, 404. Nenhum link é gravado.

- **Persona:** Vendedor de outra empresa.
- **Teste:** `tests/Feature/Sells/OrcamentoAceiteLinkContratoTest.php` — `UC-OAC-04`.
- **Regressão que defende:** link gerado para orçamento de outra empresa (Tier 0), para venda já final, ou com a feature desligada.

Status: ⬜ (sem verde ainda — o 1º run da lane Sells caiu por fixture do teste, corrigida em 3f0641244e)

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** Enviar abre o documento da cotação (título "Orçamento", validade), não o da venda; a venda final segue com o layout de nota (2º caso da ficha `03-orcamentos.md`). **Medido 2026-10-06:** o botão "Enviar" da Page aponta `/sells/{id}/print?quotation=1`, e `SellPosController@printInvoice` só tem ramo `ajax()` — em navegação comum o corpo volta vazio. O Blade usa a impressão por AJAX e `quotation.downloadPdf`. Ganha id junto do conserto do botão.
- **[BACKLOG]** Salvar cotação não baixa estoque nem gera título; finalizar a venda gera os dois (3º caso da ficha). Exige `POST /pos` real com lastro de compra (receita do `SellsRepairSubtipoContratoTest`) — intent próprio.
- **[BACKLOG]** Converter preserva itens, quantidades e preços; 2ª conversão → 409 (4º caso da ficha). É da thread **Q3** (`QuotationConvertTest`), que promove o caso quando o teste existir.
- **[BACKLOG]** O endpoint de dados `/sells/draft-dt?is_quotation=1` não checa permissão de cotação: **medido 2026-10-06 no CT 100**, um usuário do mesmo business **sem** `quotation.view_all` nem `quotation.view_own` recebeu as duas cotações da fixture. Outro business segue fora (o [T0] vale). Ganha id junto do conserto (gate no `getDraftDatables`).
- **[BACKLOG]** Orçamento duplicado mostra de onde veio (pedido de 2026-10-23) — Dado o orçamento
  O-80 duplicado do O-75, Então o O-80 mostra "Duplicado de O-75" com link e o O-75 mostra "Duplicado
  em O-80"; converter o O-80 em venda leva a origem junto. Hoje a duplicação não guarda a origem
  (`SellController::duplicateSell`, medido em 23/10/2026).
  - Pedido: P-037
  - Âncora: `SellController::duplicateSell`
  - Fazer: Mesma origem gravada no orçamento, levada à venda na conversão.
  - Hoje: part | Em parte | Duplicar existe; hoje a cópia não guarda a origem
  - Comunicação visual: ok | Sim | orçamento parecido para outro cliente
  - Clientes: Zoom, Techpress, Sabor Brasil, Display Paraná, Estilo
  - Etiquetas: part, cv

## Trilha do tempo
- 2026-10-27 · [CL] UC-OAC-01..04 (US-SELL-074, fatia 1): gerar o link de aprovação e o que o cliente vê. Teste `OrcamentoAceiteLinkContratoTest`, nasce sem run.
- 2026-10-23 · [M+C] Backlog: orçamento duplicado mostra de onde veio, sem id.
- 2026-10-21 · [CL] UC-QUO-09 (US-PCP-028 · UC-FLX-06): entrega prevista na confirmação de converter. Teste `SellsPrazoEntregaContratoTest`, nasce sem run.
- 2026-10-21 · [CL] UC-QUO-10 (US-SELL-070 · UC-FLX-04): coluna "Aberto" na lista. O item de backlog do detalhe do orçamento saiu daqui e virou UC-VSHOW-20/21 no `Show.casos.md`, que é a tela onde ele mora. Teste `QuoteAccessTelaContratoTest`, nasce sem run.
- 2026-10-21 · [CL] `last_run` 2026-10-06 → 2026-10-21 por conferência, não por run novo: o `Quotations.tsx` deste PR só acrescenta a coluna "Aberto" (2 campos lidos do mesmo JSON); filtro de lista, permissões e conversão de UC-QUO-01..04 não mudam. Nenhum teste re-rodado aqui; o `last_run_ci` segue sendo o de 2026-10-06.
- 2026-10-20 · [CL] UC-QUO-05..08 (US-SELL-070 · UC-FLX-04): termômetro de acesso da proposta e retorno de 48 h. Teste `QuoteAccessTermometroContratoTest`, nasce sem run.
- 2026-10-06 · [CL] trio completado na thread Q1 do playbook `venda-menu`. Refs: ADR 0104 · ADR 0264 G-1/G-2 · ADR 0358 · US-SELL-061.
