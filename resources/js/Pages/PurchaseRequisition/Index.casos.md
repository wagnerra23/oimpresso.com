---
id: resources-js-pages-purchaserequisition-index-casos
casos: Requisições de compra · /purchase-requisition
irmaos: Index.charter.md (lei) · Index.tsx (tela)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: requisição de outro negócio ou de local vetado, ou prazo diferente do da Blade, leva o comprador a repor errado.
owner: wagner
last_run: "2026-10-27"
---

# Casos de Uso & Aceite — Requisições de compra

> Thread `compras/playbook/07`. Derivados do `PurchaseRequisitionController` e da Blade `purchase_requisition/index`
> (RUNBOOK-purchase-requisition-index, `purchase-requisition-parity.md`) — não do `Index.tsx`.
> Teste: [`tests/Feature/Purchase/RequisicoesCompraContratoTest.php`](../../../../tests/Feature/Purchase/RequisicoesCompraContratoTest.php),
> lane `purchase-pest.yml` (MySQL). A Blade está travada em `RequisicoesCompraBaselineTest`.
>
> **Status:** ✅ passa (prova no manifesto G-7) · 🧪 teste cita o UC (veredito do CI) · ⬜ não verificado · ❌ quebrou.

---

## UC-REQC-01 · Com a flag ligada, a tela abre em React
- **Persona:** o comprador que acompanha o que os locais pediram para repor.
- **Aceite:** Dado `purchase_requisition.view_all` e a flag `useV2ComprasRequisicoes` ligada · Quando faço
  `GET /purchase-requisition` como o browser faz (`X-Inertia` **e** `X-Requested-With`) · Então renderiza
  **`PurchaseRequisition/Index`**.
- **Status: 🧪**

## UC-REQC-02 · Com a flag desligada, ou com `?legacy=1`, a Blade continua
- **Aceite:** Dado a flag desligada · Quando faço `GET /purchase-requisition` · Então a resposta é a Blade
  `purchase_requisition.index` · E com a flag ligada e `?legacy=1` também.
- **Status: 🧪**

## UC-REQC-03 · Minhas requisições, com o mesmo "necessário até" da Blade · `[T0]`
- **Aceite:** Dado uma requisição minha necessária até 10/03/2031, um pedido de compra do meu negócio e uma requisição de
  outro negócio · Quando a lista carrega (prop deferida `requisicoes`) · Então vejo a minha com `necessario_ate` igual à
  coluna da DataTable da Blade para a mesma requisição · E não vejo o pedido nem a requisição alheia.
- **Status: 🧪**

## UC-REQC-04 · Sem acesso a todos os locais, só as requisições dos meus locais · `[T0]`
- **Aceite:** Dado um usuário sem `access_all_locations`, com permissão só no local L · Quando a lista carrega · Então
  vejo a requisição de L · E não vejo a de outro local do mesmo negócio.
- **Status: 🧪**

## UC-REQC-05 · O filtro de status chega ao servidor e os KPIs contam pelo status
- **Aceite:** Dado uma requisição ordenada, uma parcial e uma concluída · Quando a lista carrega com `status=partial` ·
  Então vejo só a parcial · E sem filtro, `sem_pedido` conta as ordenadas e `parciais` conta as parciais da lista.
- **Status: 🧪**

## UC-REQC-06 · A coluna Fornecedor mostra o fornecedor da requisição · `[T0]`
- **Aceite:** Dado uma requisição com fornecedor (razão social), uma com fornecedor sem razão social, uma sem
  fornecedor e uma apontando para fornecedor de outro negócio · Quando a lista carrega · Então vejo a razão
  social, o nome, vazio e vazio, nessa ordem · E o nome do outro negócio não aparece.
- **Status: 🧪**

---


## UC-REQC-07 · A necessidade de compra (MRP) aparece acima das requisições · `must`

- **Pedido de origem:** P-110 (documento de casos de uso, pedido de 2026-10-27)
- **Persona:** o comprador.
- **Aceite:** Dado insumos cuja reserva das OS e das vendas, somada ao estoque mínimo, passa do saldo
  mais o que está a caminho · Quando a tela abre · Então a seção "Necessidade de compra" lista, por
  insumo e local, quanto comprar, a conta (reservado + mínimo − saldo − a caminho), o fornecedor da
  última compra e a data de pedir (já formatada pelo servidor), com "Atrasado" quando ela passou. O
  filtro de local da tela também filtra a seção; só aparecem os locais que o usuário pode ver.
- **Teste:** [`RequisicoesNecessidadeContratoTest`](../../../../tests/Feature/Purchase/RequisicoesNecessidadeContratoTest.php)
  (lane purchase-pest) e `tests/purchase-necessidade-compra.test.tsx` (vitest).
- **Contrato:** SPEC Compras US-COM-030 (decisão coordenação 2026-10-26). A conta é provada com
  números em `NecessidadeDeCompraTest`.
- **Status: 🧪 comportamento**
## Estoque mínimo vira requisição de compra (US-COM-027 · UC-FLX-19)

> **Âncora:** [US-COM-027](../../../../memory/requisitos/Compras/SPEC.md) e o UC-FLX-19 do catálogo de
> fluxos ("Abaixo do mínimo → cria a requisição"). Nesta entrega é o job diário por business
> (`RequisicaoEstoqueMinimoJob`, agendado via `compras:requisicao-estoque-minimo`) que grava a requisição
> do núcleo que esta tela lista; a tela não mudou. Mínimo = `products.alert_quantity`. Regra **desligada
> por padrão** (`business.common_settings['compras_requisicao_estoque_minimo']`), ligada na tela Fluxos
> (aba Fluxos da empresa, card Compra — UC-FLXT-24). A requisição sai com o fornecedor e o preço da
> última compra recebida do item, uma por local e fornecedor (UC-REQMIN-07). Prova:
> `Modules/Compras/Tests/Feature/RequisicaoEstoqueMinimoContratoTest.php`, lane `compras-pest.yml`.
> Nenhum estoque muda; só nasce requisição.

## UC-REQMIN-01 · Abaixo do mínimo nasce uma requisição por local · `[T0]` estoque
- **Aceite:** Dado a regra ligada e, no local L1, A (mínimo 10, saldo 3,5), B (mínimo 20, saldo 0), C (mínimo 5,
  saldo 12) e D (mínimo 8, saldo 8), e no local L2 A com saldo 9,99 · Quando o job do dia roda · Então L1 ganha 1
  requisição `ordered` com A = 7 e B = 20 (sem C nem D) · E L2 ganha 1 requisição com A = 1.
- **Status: 🧪**

## UC-REQMIN-02 · Rodar de novo não duplica; o item novo entra na mesma requisição
- **Aceite:** Dado A abaixo do mínimo em L1 · Quando o job roda duas vezes · Então há 1 requisição com 1 linha de A
  (6 = 10 − 4) · E quando outro item cai abaixo do mínimo e o job roda · Então ele entra nessa mesma requisição aberta.
- **Status: 🧪**

## UC-REQMIN-03 · Item já numa requisição aberta não é pedido de novo
- **Aceite:** Dado A abaixo do mínimo em L1 e uma requisição manual `partial` de L1 com A · Quando o job roda ·
  Então nenhuma requisição nem linha nova é criada.
- **Status: 🧪**

## UC-REQMIN-04 · Regra desligada (padrão) não cria nada
- **Aceite:** Dado um business sem a chave da regra · Quando o job roda com item abaixo do mínimo · Então nenhuma
  requisição é criada.
- **Status: 🧪**

## UC-REQMIN-05 · Nenhum estoque muda e nenhum pedido de compra nasce · `[T0]` estoque
- **Aceite:** Dado A em L1 com saldo 3,5 · Quando o job cria a requisição · Então o saldo de A em L1 segue 3,5 · E a
  contagem de compras/pedidos de compra do business não muda.
- **Status: 🧪**

## UC-REQMIN-06 · Um business não cria requisição no outro · `[T0]`
- **Aceite:** Dado os businesses 98 e 99 com a regra ligada, cada um com item abaixo do mínimo, e um saldo de produto
  do 99 lançado num local do 98 · Quando roda o job do 98 · Então só o local do 98 ganha requisição, sem o produto do
  99 · E o 99 fica sem requisição.
- **Status: 🧪**

## UC-REQMIN-07 · A requisição sai com o fornecedor e o preço da última compra · `[T0]` valor
- **Aceite:** Dado A abaixo do mínimo em L1, comprado do Velho (09,00) e depois do Atual (12,50 · 13,75 com
  imposto), e ainda uma compra pendente e uma devolvida por inteiro, ambas mais novas · E B comprado do
  Fornecedor do B (4,20) · E um item nunca comprado · Quando o job roda · Então A entra numa requisição do
  Atual com 12,50 / 13,75 · E B numa requisição do Fornecedor do B com 4,20 · E o item novo numa requisição
  sem fornecedor, com preço 0 · E um item novo do Atual entra na requisição aberta dele. VALOR: o preço é
  provado à mão e pela linha da compra. O preço é sugestão: a requisição não tem total.
- **Status: 🧪**

## UC-REQMIN-08 · Compra de outro business não vira fornecedor nem preço · `[T0]`
- **Aceite:** Dado A do 98 abaixo do mínimo e uma compra recebida do 99 com a variação de A · Quando roda o job
  do 98 · Então a requisição de A sai sem fornecedor e com preço 0 · E o 99 fica sem requisição.
- **Status: 🧪**

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)
- [BACKLOG] Ver o detalhe e excluir pela tela nova (PR próprio).
- [BACKLOG] KPI "Itens requisitados" e coluna "Pedido gerado" — decisão [W].
- **[BACKLOG] Solicitação de compra** (pedido de 2026-10-23)
  - Aceite: Produção e comprador. Dado um material que a produção precisa · Quando abro a requisição com os itens e a data em que são necessários · Então o comprador vê as requisições abertas e as transforma em pedido de compra.
  - Pedido: P-075
  - Âncora: `UC-REQC-01` a `06` · `UC-REQMIN`
  - Fazer: Nada a fazer: já existe. Fica aqui para ligar os clientes.
  - Hoje: ok | Em produção | Requisição de compra; a tela nova em React depende de uma flag. Fora do PR #9786
  - Comunicação visual: ok | Sim | material pedido pela produção
  - Clientes: Martinho, Max Comunicação, Jo Painéis
  - Etiquetas: ok, cv
- **[BACKLOG] Gerar a requisição ou a cotação dali mesmo** (pedido de 2026-10-27)
  - Aceite: Comprador. Dado a lona na lista Necessidade de compra · Quando clico em gerar na linha · Então nasce a requisição do item com a quantidade, o local e o fornecedor; quando a cotação existir, posso pedir preço a vários fornecedores a partir da mesma linha.
  - Pedido: P-111
  - Âncora: `UC-REQC-07` · US-COM-031 · comentário do Wagner no PR #9930
  - Fazer: Botão na linha da lista para gerar a requisição; depois, gerar a cotação (US-COM-031).
  - Hoje: miss | Não tem | A lista só mostra. Hoje a requisição sai pela tela de criar, que já lista os itens abaixo do mínimo, ou pela requisição automática (desligada por padrão). A cotação ainda não existe
  - Comunicação visual: ok | Sim | compra de insumo da produção
  - Etiquetas: miss, cv
- **[BACKLOG] Sistema sugere o estoque mínimo pelo consumo e pelo prazo de reposição** `[E0]` (pedido de 2026-10-27)
  - Aceite: Dono e comprador. Dado a Lona 440g, que consome em média 2 m por dia e leva 5 dias para repor (cotação, aprovação, compra e entrega do fornecedor) · Quando abro o estoque mínimo do produto · Então o sistema sugere 10 m e a pessoa confirma. Com margem de segurança de 2 dias, sugere 14 m.
  - Pedido: P-112
  - Âncora: `CU-PROD-04` item 2 (SDD do Produto, PR #9930)
  - Fazer: Calcular o consumo médio por dia contando também a baixa de insumo das OS; guardar o prazo do fornecedor e os prazos internos; sugerir o mínimo para a pessoa confirmar. Ao chegar no mínimo, "Pedir até" deve dizer pedir hoje, não atrasado.
  - Hoje: miss | Não tem | O prazo do fornecedor tem tabela, mas nenhuma tela grava nela; o consumo médio não é calculado; o giro do PR #9983 (rascunho) conta só vendas
  - Comunicação visual: ok | Sim | insumo gasto na produção, não vendido
  - Etiquetas: valor, miss, cv, decisao

## Trilha do tempo
- 2026-10-13 · [CL] criado com a F3 da thread `compras/playbook/07`.
- 2026-10-20 · [CC] UC-REQMIN-01..06 (US-COM-027 · UC-FLX-19): requisição gerada pelo estoque mínimo — job
  diário, sem mudança no `.tsx`; `last_run` intocado. Prova: `RequisicaoEstoqueMinimoContratoTest`.
- 2026-10-21 · [CC] UC-REQMIN-07/08 (fornecedor e preço da última compra) e UC-REQC-06 (coluna Fornecedor).
  A regra liga na tela Fluxos (UC-FLXT-24).
- 2026-10-28 · [CC] `last_run` 2026-10-26 → 2026-10-27, a data do commit que mudou o `.tsx` (db703989b3, MRP). Ele mexeu no `.tsx` e no casos.md no mesmo commit; ao ligar os pedidos do documento a este arquivo, o commit do casos.md deixou de ser o do `.tsx` e o G-6 passou a cobrar. Prova: os 15 UCs desta tela com veredito pass no manifesto do main (`scripts/casos-test-results.json`). Nenhum caso foi reexecutado agora.
