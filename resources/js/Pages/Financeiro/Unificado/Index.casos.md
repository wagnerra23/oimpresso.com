---
id: resources-js-pages-financeiro-unificado-index-casos
casos: Financeiro Unificado · /financeiro/unificado
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-14"
last_run_ci: "0 UC executado — UC-FUNI-01..04 nascem neste PR; veredito pendente da lane PHP / Pest (Financeiro · MySQL)"
sdd: memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md
---

> ℹ️ **`last_run` 2026-09-25 → 2026-10-14 (G-6) — tema escuro, thread 03 do playbook `cowork-inbox/tema-escuro`: só COR.**
> O [#9319](https://github.com/wagnerra23/oimpresso.com/pull/9319) passou as cores da pílula de direção (Receber/Pagar) de `style` inline para classes com par `dark:`, e a borda e a sombra para `currentColor`. No modo claro as cores são as mesmas oklch de antes. Zero handler, prop, rota, cálculo ou copy alterado.
> **Nenhum UC foi reexecutado**: o bump registra *trio reconciliado com a tela nesta data*.

# Casos de Uso & Aceite — Financeiro Unificado

> Tela P0 do fio **venda → faturamento → caixa** (mandato ONDAS-QUALIDADE Q2). Os UCs
> abaixo espelham o ENCADEAMENTO que sustenta esta tela: a venda gera o título a receber,
> o recebimento baixa o título e registra entrada no caixa — provado ponta-a-ponta contra
> DB real (canon: não-mocka-DB) por `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`,
> que roda no CI (`financeiro-pest.yml`, check required) e alimenta o manifesto G-7 via
> JUnit. `Status: ✅` só com veredito `pass` no manifesto.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

> ℹ️ **`last_run` 2026-09-06 → 2026-09-23 (G-6) — FIN-8, só FORMA.** Quatro toques em `Index.tsx`, nenhum de comportamento: `uppercase` no botão do cabeçalho ordenável (o preflight do Tailwind zerava o text-transform herdado do `th`); a busca volta à linha dos filtros (encolhe 240→160px antes de quebrar); placeholder "Buscar lançamento…" → "Filtrar nesta lista…"; rótulo da paleta "Novo lançamento" → "Novo título". Zero handler, zero prop, zero query. **Nenhum UC foi reexecutado**; o bump reconcilia o trio com a tela nesta data.
>
> ℹ️ **`last_run` 2026-08-08 → 2026-09-06 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` (+ 5 `_components/*`) foi o atributo **`data-contract="<id>"`** no elemento raiz de
> região (header-acoes · kpis · filter-pills-toolbar · tabela-linhas · aprovacao-de-pagamento · drawer-detalhe, mais periodbar-personalizado · dialogo-de-baixa · anexos-no-drawer · ocr-boleto · combobox-contraparte-no-novo-titulo nos componentes) — a âncora estável que o `<tela>.map.json` declara em `vivo.ancora`
> (`scripts/governance/design-code-map-check.mjs`). Atributo inerte: nenhum CSS o seleciona
> nestas telas (`grep -rn 'data-contract' resources/css/` → só `.arq-page [data-contract=abas|cabecalho]`)
> e nenhum JS de runtime o lê. Zero JSX estrutural, zero handler, zero prop, zero copy alterada.
> **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo significa na
> prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run.
## UC-F01 · Venda a prazo gera título a receber (CU-3→CU-5)
- **Persona:** Kamila (financeiro) — confiança de que NENHUMA venda a prazo fica sem cobrança.
- **Aceite:** Dado venda final a prazo 30 dias · Então nasce `fin_titulos` tipo `receber`, status `aberto`, valor total = valor da venda, vencimento +30d da data da venda.
- **Teste:** `RetencaoLoopE2ETest` ("UC-F01 · CU-3→CU-5") — Observer da venda, DB MySQL real.
- **Status: 🧪** (o teste existe e é real, mas **pulou** na captura do manifesto — `verdict: skip`. Vira ✅ quando o manifesto trouxer `pass`.)

## UC-F02 · Recebimento baixa o título e entra no caixa (CU-5)
- **Persona:** Kamila — o "recebi" do balcão tem que virar baixa + caixa sem digitação dupla.
- **Aceite:** Dado título aberto · Quando o pagamento total entra (`TransactionPayment`) · Então o título quita (`valor_aberto = 0`), nasce a `fin_titulo_baixas` ligada ao pagamento e o `fin_caixa_movimentos` registra `entrada` ligada à baixa.
- **Teste:** `RetencaoLoopE2ETest` ("UC-F02 · CU-5") — TransactionPaymentObserver no DB real.
- **Status: 🧪** (o teste existe e é real, mas **pulou** na captura do manifesto — `verdict: skip`. Vira ✅ quando o manifesto trouxer `pass`.)

## UC-F03 · Wire fiscal da venda existe (CU-4)
- **Persona:** Larissa — o botão "Emitir NF-e" da venda não pode apontar pro vazio.
- **Aceite:** Dado a rota da tela de venda · Então os endpoints fiscais que ela dispara (NF-e emitir) existem e respondem (a emissão SEFAZ em si é coberta com stub pelas suítes NfeBrasil/NFSe — não reduplicado aqui).
- **Teste:** `RetencaoLoopE2ETest` ("UC-F03 · CU-4").
- **Status: 🧪** (o teste existe e é real, mas **pulou** na captura do manifesto — `verdict: skip`. Vira ✅ quando o manifesto trouxer `pass`.)

## UC-F04 · Ações em lote respeitam o tenant e a contabilidade (US-FIN-031)
- **Persona:** Eliana [E] — fechamento do mês com 200+ títulos sem 200 cliques; e NUNCA um lote pode vazar pra outro negócio nem apagar registro contábil.
- **Aceite:** Dado títulos selecionados na Visão Unificada · Quando uma ação em lote roda (`POST /financeiro/unificado/bulk` — baixar/categoria/plano/cancelar/exportar) · Então (a) 1 id de outro business no lote = 422 e NADA aplica; (b) baixar em lote quita com a soma exata provada por 2 caminhos (baixas criadas × total do audit trail — REGRA MESTRE valor); (c) cancelar é `status='cancelado'` append-only, pula quitado, e o modal apresenta "N títulos totalizando R$ X" ANTES de aplicar; (d) limite 500 por chamada; (e) audit trail grava user+ids+count+total.
- **Teste:** `UnificadoBulkGuardTest` ("UC-F04 GUARD G1..G3" + G4/G5/G6) — DB MySQL real, lane `financeiro-pest.yml`.
- **Status: 🧪** (vira ✅ com veredito `pass` no manifesto G-7)

## UC-F05 · Pagamento sem vinculação bancária sinaliza "Conta indefinida" (US-FIN-038)
- **Persona:** Eliana [E] — precisa VER quais recebimentos/pagamentos foram baixados sem conta bancária (ADR 0175 permite), pra organizar o caixa depois — sem caçar linha a linha.
- **Aceite:** Dado um título com baixa cuja `conta_bancaria_id` é NULL · Quando a linha/drawer renderiza na Visão Unificada · Então a coluna Conta mostra o pill **"Conta indefinida"** (warning leve, CTA pra cadastro de conta) em vez de "—"; título com baixa vinculada ou sem baixa NÃO mostra o pill. Não altera valor/estoque.
- **Teste:** `UnificadoContaIndefinidaGuardTest` ("GUARD G1..G3") — DB MySQL real, lane `financeiro-pest.yml`.
- **Status: 🧪** (vira ✅ com veredito `pass` no manifesto G-7)

---

## Rastreabilidade UC → CU → US

> **Âncora:** `CU-FIN-01` · `CU-FIN-02` · `CU-FIN-03` · `CU-FIN-04` · `CU-FIN-05` · `CU-FIN-06` · `CU-FIN-07` · `CU-FIN-08` ([SDD do Financeiro](../../../../../memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md) §6.1)
>
> **Cobre:** `US-FIN-003` (baixa) · `US-FIN-013` (visão unificada) · `US-FIN-031` (lote, via UC-F04) · `US-FIN-038` (conta indefinida, via UC-F05)

| UC | Comportamento | Força | Âncora de contrato | Teste | Status |
|---|---|---|---|---|---|
| UC-FUNI-01 | Baixa parcial faz SPLIT e conserva o valor | must `[V0]` | `CU-FIN-02` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-02 | Baixa acima do aberto quita exatamente o aberto | must `[V0]` | `CU-FIN-03` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-03 | Quitado/cancelado recusa baixa | must | `CU-FIN-04` + `US-FIN-003` | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-FUNI-04 | Conta de outro business recusada | must `[T0]` | `CU-FIN-05` + ADR 0093 | `BaixaConservacaoValorContratoTest` | 🧪 |
| UC-PAGBAN-01 | Desligado, nada vai ao banco | must | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-02 | Valor confirmado diferente do aberto não envia | must `[V0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-03 | Pedir não baixa; a trilha guarda quem, quanto e para onde | must `[V0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-04 | Um pedido em aberto por título | must `[V0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-05 | Banco confirma → baixa uma vez e quita | must `[V0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-06 | Banco recusa → sem baixa, pode pedir de novo | must | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-07 | Título quitado por outro caminho não recebe 2ª baixa | must `[V0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-08 | Outra empresa não paga o título nem usa a conta de outra | must `[T0]` | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-PAGBAN-09 | Envio ao Inter nunca devolve pago | must `[V0]` | `US-FIN-079` | `InterPagadorBancoTest` | ⬜ |
| UC-PAGBAN-10 | Só status conhecido de débito vira pago | must `[V0]` | `US-FIN-079` | `InterPagadorBancoTest` | ⬜ |
| UC-PAGBAN-11 | Sync só consulta, e só com a flag | must | `US-FIN-079` | `PagamentoBancoContratoTest` | ⬜ |
| UC-F01 (legado) | Venda a prazo gera título a receber | must `[V0]` | `CU-FIN-01` + `US-FIN-013` | `RetencaoLoopE2ETest` | ✅ |
| UC-F02 (legado) | Recebimento baixa o título e entra no caixa | must `[V0]` | `CU-FIN-08` + `US-FIN-003` | `RetencaoLoopE2ETest` | ✅ |
| UC-F04 (legado) | Ações em lote respeitam tenant/limite/contabilidade | must `[V0][T0]` | `CU-FIN-07` + `US-FIN-031` | `UnificadoBulkGuardTest` | 🧪 |
| UC-F05 (legado) | Pill "Conta indefinida" na baixa sem conta | should | `CU-FIN-06` + `US-FIN-038` | `UnificadoContaIndefinidaGuardTest` | 🧪 |
| UC-DDA-01 | Fornecedor casado pelo CNPJ da própria empresa | must `[T0]` | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-02 | Mesmo boleto vira uma sugestão só | must | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-03 | Sugestão não cria conta a pagar | must `[V0]` | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-04 | Aceitar cria o título a pagar com valor e vencimento do boleto | must `[V0]` | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-05 | Outra empresa não decide a sugestão | must `[T0]` | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-06 | Descartar encerra sem título | must | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |
| UC-DDA-07 | Sync falha visível sem flag ou sem fonte | must | `US-FIN-078` | `DdaContasPagarSugeridasTest` | ⬜ |

> ⚠️ Os ids legados `UC-F0N` **não casam** com a régua estrita do `requisitos-status.mjs`
> (`UC-[A-Z0-9]{2,10}-\d{2,3}` exige o 2º hífen). Por isso esta tabela os cita **nominalmente**
> e a linha `> **Âncora:**` acima é o que liga os CU — renomeá-los exigiria editar
> `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`, fora da área deste chip.

---

## Contrato do pagamento pelo banco (US-FIN-079)

> Derivam da **US-FIN-079** (`memory/requisitos/Financeiro/SPEC.md`): saída de dinheiro, então nunca
> automático — cada pedido exige o valor confirmado pelo usuário, e a baixa só vem da confirmação do
> banco. Banco simulado nos testes (o adaptador do Inter usa HTTP simulado). O botão na gaveta do título a pagar entra na próxima onda, só com
> a flag e a permissão `financeiro.contas_pagar.pagar_banco`.
> **Lane:** `PHP / Pest (Financeiro · MySQL)` (roda a árvore do módulo menos a quarentena). **Veredito:** ⬜ até o primeiro run do CI — quem dá é a lane (G-7).

## UC-PAGBAN-01 · Desligado, nada vai ao banco `[must]`
- **Persona:** Wagner [W] — pagamento pelo banco só existe quando ele ligar a flag.
- **Aceite:** Dado FINANCEIRO_PAGAMENTO_BANCO_ENABLED desligado · Quando o usuário pede o pagamento de um título · Então é recusado e o banco não é chamado.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-01 · …").
- **Regressão que defende:** dinheiro saindo da conta antes de a integração ser liberada.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-02 · Valor confirmado diferente do aberto não envia `[V0]` `[must]`
- **Persona:** Eliana [E] — aprova cada pagamento conferindo o valor; um centavo errado não pode passar.
- **Aceite:** Dado título a pagar com aberto de 1234.56 · Quando o valor confirmado é 1234.55, 1234,56, 1234.5 ou 1234.560 · Então nada é enviado ao banco e nenhum pedido é gravado.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-02 · …").
- **Regressão que defende:** pagamento sem aprovação explícita ou com valor diferente do título.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-03 · Pedir não baixa; a trilha guarda quem, quanto e para onde `[V0]` `[must]`
- **Persona:** Eliana [E] — precisa provar depois quem pagou, quanto e para onde.
- **Aceite:** Dado título a pagar aberto · Quando o pedido vai ao banco · Então fin_pagamentos_banco guarda solicitante, valor, destino normalizado e código do banco, e o título segue aberto, sem baixa.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-03 · …").
- **Regressão que defende:** título quitado antes de o banco pagar, e saída de dinheiro sem trilha.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-04 · Um pedido em aberto por título `[V0]` `[must]`
- **Persona:** Eliana [E] — clicou duas vezes ou outra pessoa pediu ao mesmo tempo.
- **Aceite:** Dado um pedido do título aguardando o banco · Quando alguém pede de novo · Então é recusado e o banco recebeu um envio só.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-04 · …").
- **Regressão que defende:** o mesmo boleto pago duas vezes.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-05 · Banco confirma → baixa uma vez e quita `[V0]` `[must]`
- **Persona:** Eliana [E] — quer o título quitado sozinho quando o banco confirma, sem baixa manual.
- **Aceite:** Dado pedido aguardando o banco · Quando a consulta devolve pago, duas vezes · Então existe UMA baixa de 1234.56 com o meio do pedido e a mesma chave de idempotência, e o título fica quitado com aberto 0.00.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-05 · …").
- **Regressão que defende:** baixa em dobro ou com valor diferente do que saiu da conta.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-06 · Banco recusa → sem baixa, pode pedir de novo `[must]`
- **Persona:** Eliana [E] — o banco recusou (saldo, boleto vencido); ela vai tentar de novo.
- **Aceite:** Dado título a pagar aberto · Quando o banco recusa o pedido · Então não há baixa, o título segue aberto e um novo pedido é aceito.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-06 · …").
- **Regressão que defende:** título dado como pago sem débito, ou travado para nova tentativa.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-07 · Título quitado por outro caminho não recebe 2ª baixa `[V0]` `[must]`
- **Persona:** Eliana [E] — alguém deu baixa manual enquanto o banco processava.
- **Aceite:** Dado pedido aguardando e o título quitado à mão no meio · Quando o banco confirma · Então nenhuma baixa nova; o pedido fica pago com o motivo conferir duplicidade.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-07 · …").
- **Regressão que defende:** segunda baixa no mesmo título, inflando o pago do mês.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-08 · Outra empresa não paga o título nem usa a conta de outra `[T0]` `[must]`
- **Persona:** Qualquer usuário — invariante de plataforma (ADR 0093).
- **Aceite:** Dado título e conta da empresa 98 · Quando a empresa 99 tenta pagar o título de 98, ou debitar a conta de 98; e quando alguém tenta pagar um título a receber · Então tudo é recusado e o banco não é chamado.
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-08 · …").
- **Regressão que defende:** uma empresa pagando com a conta de outra, ou dinheiro saindo para título a receber.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-09 · Envio ao Inter nunca devolve pago `[V0]` `[must]`
- **Persona:** Eliana [E] — só quer ver pago o que o banco de fato debitou.
- **Aceite:** Dado o Inter respondendo realizado logo no envio do boleto · Quando o pedido é enviado · Então o status fica aguardando o banco; a chave de idempotência vai no cabeçalho x-id-idempotente e o valor sai em 2 casas (1234.56).
- **Teste:** `Modules/Financeiro/Tests/Feature/InterPagadorBancoTest.php`, it("UC-PAGBAN-09 · …").
- **Regressão que defende:** título quitado na hora do envio, antes da confirmação, e reenvio que o banco não reconhece como o mesmo pedido.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-10 · Só status conhecido de débito vira pago `[V0]` `[must]`
- **Persona:** Eliana [E] — o banco devolve status que o sistema não conhece; ela não pode ver isso como pago.
- **Aceite:** Dado consultas do Pix que devolvem EM_PROCESSAMENTO, PAGO e REJEITADO, e uma conta sem credencial Inter ativa · Quando o sistema consulta ou tenta enviar · Então desconhecido fica aguardando, PAGO vira pago, REJEITADO vira recusado, e a conta sem credencial não chama o banco.
- **Teste:** `Modules/Financeiro/Tests/Feature/InterPagadorBancoTest.php`, it("UC-PAGBAN-10 · …").
- **Regressão que defende:** status novo do banco lido como pago, ou chamada ao banco com conta não habilitada.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-PAGBAN-11 · Sync só consulta, e só com a flag `[must]`
- **Persona:** Wagner [W] — a rotina agendada não pode mover dinheiro, só confirmar.
- **Aceite:** Dado um pedido aguardando o banco · Quando roda financeiro:pagamentos-banco-sync com a flag desligada e depois ligada · Então desligada não toca nada; ligada, baixa o confirmado e o banco recebeu um envio só (o do usuário).
- **Teste:** `Modules/Financeiro/Tests/Feature/PagamentoBancoContratoTest.php`, it("UC-PAGBAN-11 · …").
- **Regressão que defende:** rotina agendada enviando pagamento sozinha, ou deixando título pago sem baixa.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

---

## Contrato da BAIXA (SDD §6.1 · onda `sdd-from-source` 2026-07-27)

> Estes 4 UC derivam do **SDD `§6.1 CU-FIN-02..05`** (`memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md`),
> que por sua vez deriva de `US-FIN-003` + charter v12 + a decisão [W] 2026-06-04 (*"baixa parcial
> vira SPLIT"*) — **não** da leitura do `.tsx` nem do corpo do teste.
>
> ⚠️ **Todos são `[V0]`/`[T0]`** (REGRA MESTRE valor · multi-tenant). O assert é da **invariante de
> conservação**, não do nome do campo: `Σ(filhos.valor_total) + pai.valor_aberto == valor original`.
> Um assert em `status == 'parcial'` — o que o contrato antigo pedia — **já estaria falso hoje**.
>
> **Lane:** `PHP / Pest (Financeiro · MySQL)` — **required** (consta em
> `governance/required-checks-baseline.json`): reprovar aqui **bloqueia merge**.
> **Veredito:** 🧪 em todos — o trio nasce neste PR e quem dá o veredito é a lane, não esta leitura (G-7).

## UC-FUNI-01 · Baixa parcial faz SPLIT e conserva o valor `[V0]` `[must]`
- **Persona:** Eliana [E] — recebeu metade hoje; o que falta tem que continuar cobrável, ao centavo.
- **Aceite:** Dado título aberto de R$ V · Quando entra baixa de R$ B < V · Então nasce um título **FILHO** quitado de B (`titulo_pai_id` = pai), o **pai reduz** para `V − B` e **segue aberto**; e o valor fecha por **DOIS caminhos independentes**: (a) `Σ(filhos.valor_total) + pai.valor_aberto == V` e (b) `Σ(fin_titulo_baixas.valor_baixa) == B`. Não existe mais `status='parcial'`.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-01 · baixa parcial conserva o valor no split") — MySQL real, com **pré-condição anti-vácuo** (`cvExigeQueTenhaBaixado`: sem linha de baixa gravada o caso FALHA em vez de passar no vácuo).
- **Regressão que defende:** baixa parcial que "some" com centavo no arredondamento do split, ou split que duplica o valor (pai não reduz).
- **Status: 🧪**

## UC-FUNI-02 · Baixa acima do aberto quita exatamente o aberto `[V0]` `[must]`
- **Persona:** Larissa — digitou o valor errado no balcão; o sistema não pode inventar crédito.
- **Aceite:** Dado título aberto de R$ V · Quando a baixa pedida é > V · Então o registrado é **exatamente V** (`clamp` superior), `valor_aberto = 0`, **nunca negativo**, e o excesso **não** vira título-filho de crédito.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-02 · baixa acima do aberto quita exatamente o aberto").
- **Regressão que defende:** excesso virando crédito fantasma ou `valor_aberto` negativo contaminando KPI/DRE.
- **Status: 🧪**

## UC-FUNI-03 · Título quitado/cancelado recusa baixa `[must]`
- **Persona:** Eliana [E] — o histórico contábil é append-only; título fechado não se reabre por baixa.
- **Aceite:** Dado título `cancelado` (idem `quitado`) · Quando chega POST de baixa · Então **nenhuma** `fin_titulo_baixas` é criada, o `status` permanece, o `valor_aberto` **não muda**, e o usuário recebe flash `error`.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-03 · título cancelado recusa baixa").
- **Regressão que defende:** dupla-baixa do mesmo título inflando o recebido do mês.
- **Status: 🧪**

## UC-FUNI-04 · Conta bancária de outro business é recusada `[T0]` `[must]`
- **Persona:** qualquer — é invariante de plataforma, não de usuário ([ADR 0093]).
- **Aceite:** Dado `conta_bancaria_id` que pertence a **outro** business · Quando a baixa é submetida · Então a operação é recusada **fail-closed**: zero `fin_titulo_baixas`, `valor_aberto` e `status` intactos.
- **Teste:** `BaixaConservacaoValorContratoTest` ("UC-FUNI-04 · Tier 0: conta bancária de outro business é recusada") — precisa de 2 businesses semeados (a lane semeia biz=1 + biz=2).
- **Regressão que defende:** dinheiro de um tenant caindo na conta de outro.
- **Status: 🧪**

---

## Contrato do DDA (US-FIN-078)

> Derivam da **US-FIN-078** (`memory/requisitos/Financeiro/SPEC.md`): boleto emitido contra o CNPJ
> da empresa vira conta a pagar **sugerida**; título só nasce quando o usuário aceita. Backend nesta
> onda; a lista de sugestões nesta tela entra na próxima, só com `FINANCEIRO_DDA_ENABLED`.
> **Lane:** `PHP / Pest (Financeiro · MySQL)` (roda a árvore do módulo menos a quarentena). **Veredito:** ⬜ até o primeiro run do CI — quem dá é a lane (G-7).

## UC-DDA-01 · Fornecedor casado pelo CNPJ da própria empresa `[T0]` `[must]`
- **Persona:** Eliana [E] — cuida do contas a pagar; quer o boleto já com o fornecedor certo, sem procurar cadastro.
- **Aceite:** Dado um fornecedor da própria empresa com CNPJ formatado e outro de outra empresa com o mesmo CNPJ · Quando chega o boleto DDA desse CNPJ · Então a sugestão aponta o fornecedor da própria empresa; sem cadastro, fica sem fornecedor.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-01 · …").
- **Regressão que defende:** boleto casado com fornecedor de outra empresa (vazamento entre tenants) ou casamento que falha por causa da pontuação do CNPJ.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-02 · Mesmo boleto vira uma sugestão só `[must]`
- **Persona:** Eliana [E] — o banco reenvia o mesmo boleto; ela não pode ver a conta duas vezes.
- **Aceite:** Dado o mesmo código de barras entregue duas vezes, com e sem pontuação · Quando a fonte registra os dois · Então existe uma sugestão só.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-02 · …").
- **Regressão que defende:** conta a pagar duplicada e pagamento em dobro.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-03 · Sugestão não cria conta a pagar `[V0]` `[must]`
- **Persona:** Eliana [E] — decide o que entra no contas a pagar; o banco não decide por ela.
- **Aceite:** Dado um boleto DDA recebido · Quando ele é registrado como sugestão · Então o número de títulos da empresa não muda.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-03 · …").
- **Regressão que defende:** boleto de terceiro (golpe ou engano) virando conta a pagar sem ninguém ver.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-04 · Aceitar cria o título a pagar do boleto `[V0]` `[must]`
- **Persona:** Eliana [E] — aceita o boleto e quer a conta com o valor e o vencimento certos, ao centavo.
- **Aceite:** Dado sugestão de 1234,56 com vencimento em 10/04 · Quando ela aceita · Então nasce título a pagar aberto com valor_total = valor_aberto = 1234.56, o vencimento e o fornecedor do boleto; a sugestão fica aceita e ligada a ele; aceitar de novo é recusado.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-04 · …").
- **Regressão que defende:** valor ou vencimento divergente do boleto, ou dois títulos para a mesma sugestão.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-05 · Outra empresa não decide a sugestão `[T0]` `[must]`
- **Persona:** Qualquer usuário — invariante de plataforma (ADR 0093).
- **Aceite:** Dado uma sugestão da empresa 98 · Quando a empresa 99 tenta aceitar ou descartar · Então é recusado e a sugestão segue sugerida.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-05 · …").
- **Regressão que defende:** uma empresa criando ou apagando conta a pagar de outra.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-06 · Descartar encerra sem título `[must]`
- **Persona:** Eliana [E] — o boleto não é da empresa; ela quer tirá-lo da fila.
- **Aceite:** Dado uma sugestão em aberto · Quando ela descarta · Então a sugestão fica descartada e nenhum título é criado.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-06 · …").
- **Regressão que defende:** descarte que cria título ou que deixa a sugestão voltando.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

## UC-DDA-07 · Sync falha visível sem flag ou sem fonte `[must]`
- **Persona:** Wagner [W] — liga a rotina e precisa saber se ela de fato trouxe boletos.
- **Aceite:** Dado FINANCEIRO_DDA_ENABLED desligado, ou ligado sem fonte registrada · Quando roda financeiro:dda-sync · Então o comando sai com erro; com uma fonte registrada, grava a sugestão e sai com sucesso.
- **Teste:** `Modules/Financeiro/Tests/Feature/DdaContasPagarSugeridasTest.php`, it("UC-DDA-07 · …").
- **Regressão que defende:** rotina verde que não leu nada, parecendo que não havia boletos.
- **Status: ⬜** — não verificado: teste escrito, ainda sem run do CI.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Lentes (caixa/competência/fiscal) refinam os KPIs** — coberto por `UnificadoLentesGuardTest` (Pest GUARD); vira UC com id quando os GUARDs ganharem UC-id no título.
- **[BACKLOG] Baixa manual pela tela (dialog)** — coberto por `UnificadoBaixaDialogGuardTest`; idem.
- **[BACKLOG] Conciliação bancária (extrato ↔ título)** — fluxo Pluggy/Inter; espelhar quando o harness tiver extrato fixture.
- **[BACKLOG]** Total do serviço, retido e recebido em colunas separadas (UC-FLX-30) `[V0]` — Dado o
  título de 1.000,00 com a baixa de retenção de 96,50 e o cliente pagando 903,50, Então o título mostra
  "Total do serviço 1.000,00 · retido 96,50 · recebido 903,50", e o relatório de recebimentos soma o
  recebido e o retido em colunas separadas (o retido não entra no caixa). Fonte: UC-CRISS-02 e 03. Veio da tela de Contas a receber, aposentada (2026-10-23).
- **[BACKLOG]** Título de venda agrupada (US-SELL-024) `[V0]` — Dado três vendas de um cliente (300,00,
  450,00 e 250,00), Quando as agrupo numa cobrança só, pela tela unificada, Então nasce um título de 1.000,00 que lista as
  três vendas; pagar o título paga as três; uma venda não entra em dois grupos; cancelar uma venda do
  grupo antes do pagamento tira o valor dela do título. Mexe em valor: Regra Mestre. Fonte: US-SELL-024
  e o Delphi (`CODFINANCEIRO_GRUPO`). Veio da tela de Contas a receber, aposentada (2026-10-23).
- **[BACKLOG]** Desfazer ou corrigir o agrupamento de OS `[V0]` — pedido de Zoom, Techpress e Mhundo
  (2026-10-28), que desfazem agrupamentos por erro de cadastro ou de valor. Dado três OS de um cliente
  agrupadas numa cobrança só, com uma OS de valor errado ou o grupo feito no cliente errado, Quando desfaço
  o agrupamento, informando o motivo, Então, se o grupo não tem pagamento, as OS voltam a ficar soltas com
  os seus valores, o título do grupo é cancelado (não apagado) e o histórico mostra quem agrupou, quem
  desfez, quando e por quê. Se o erro é numa OS só, tiro só ela do grupo e o título é recalculado com as
  outras. Se o grupo já tem nota emitida, a nota é cancelada antes, dentro do prazo, ou o desagrupamento é
  recusado com o motivo. Se já tem pagamento, o pagamento é estornado antes, com motivo, como no caso de
  desagrupar parcelas já pagas. Hoje o agrupamento não existe nesta tela (bullet acima); o Financeiro já não
  apaga título, só cancela. Falta decisão [W]: quem pode desfazer um grupo, e se um valor errado numa OS
  agrupada pode ser corrigido sem desfazer o grupo, só recalculando o título. Mexe em valor: Regra Mestre.
- **[BACKLOG]** Título de cada parte faturada `[V0]` — Dado uma venda de 1.000,00 faturada em duas
  partes (400,00 e 600,00), Então cada parte tem o seu título; cancelar a nota de uma parte cancela o
  título dela se ainda não estiver pago. Mexe em valor: Regra Mestre. Fonte: Omie (pedido parcial). Veio da tela de Contas a receber, aposentada (2026-10-23).

## Como rodar a suíte
1. **Pest (MySQL real):** lane `financeiro-pest.yml` (check required `PHP / Pest (Financeiro · MySQL)`) — JUnit vira manifesto via `npm run casos:results` (merge per-UC).
2. **Cadência:** rodar ao fim de toda mexida no Financeiro. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-10-28 · [F+C] Backlog: desfazer ou corrigir o agrupamento de OS (pedido de Zoom, Techpress e Mhundo). Sem id.
- 2026-10-23 · [M+C] Backlog vindo da tela de Contas a receber (aposentada): total do serviço, retido e recebido; título de venda agrupada; título de cada parte faturada. Sem id.
- 2026-07-27 · [CC] onda `sdd-from-source` (passo 5 · Onda 2): nasce o **SDD do módulo**
  (`memory/requisitos/Financeiro/SDD-tela-financeiro-v1.0.md` — o Financeiro tinha 0 CU) e os UC de
  contrato da baixa **UC-FUNI-01..04** (`[V0]`/`[T0]`), derivados de `§6.1 CU-FIN-02..05`, com teste novo
  `BaixaConservacaoValorContratoTest` na allowlist da lane. Nenhum status promovido a ✅ — o veredito é
  da lane (G-7). **2 drifts registrados, não escondidos:** (a) os `it()` dos UC legados usam id no formato
  `UC-F0N`, que a régua estrita do `requisitos-status.mjs` (`UC-[A-Z0-9]{2,10}-\d{2,3}`) **não enxerga** —
  por isso a porta imprimia *"casos.md existe mas não declara nenhum UC"* para esta tela e acusava
  US-FIN-031/038 como "entregue sem contrato"; **não renomeei** porque UC-F01..03 são citados por
  `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`, fora da área deste chip. (b) o `it()` do
  `UnificadoBaixaDialogGuardTest` **G3** ainda se chama *"reduz valor_aberto e marca parcial"* embora o
  corpo asserte o SPLIT correto — descrição stale, corpo certo.
- 2026-07-13 · [Codex] revalidado (bump `last_run`): criação por empty state e por Cmd+K deixam de navegar para a rota legada e abrem o mesmo `TituloCreateSheet` do menu “Novo título”. Os UCs F01..F04 continuam sendo provas backend (venda→título→caixa e lote); a nova prova de intenção da tela é o contrato com cinco fluxos, validado pelos auditores estático e adversarial. Nenhum status ✅ foi promovido sem teste verde.
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE): UC-F01..03 espelham o RetencaoLoopE2ETest (CU-3→CU-5) no manifesto G-7; RetencaoLoop entrou na allowlist do financeiro-pest + JUnit artifact.
- 2026-06-16 · [CL] revalidado (bump last_run) na onda "Financeiro adversário Wave 1": mudança é só de UI no hero/audit trail (% pt-BR + cor de saldo negativo); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-06-18 · [CL] revalidado (bump last_run): migração do header pro `<PageHeader>` canon (#2947) — mudança só de chrome do header (Zona R preservada: 3 lentes + divisor + FinanceiroSubNav + dropdown "Novo título"); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): fix de cor do primary "Novo título" (style inline roxo 295 canon, ADR 0190 — corrige botão ghost/magenta pego no diff prod×protótipo). Mudança puramente cosmética (1 `style` no botão do header); UC-F01..03 são do fluxo backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): filtro de campo de data `<select>` → segmentado (fidelidade protótipo [W] — "iguale o filtro de data ao segmented"). Contrato backend `data_campo` INTACTO (mesmo `aplicar({data_campo})`), só troca o controle visual; some 1 `<select>` nativo (ds/no-native-select). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest. A cobertura do `data_campo` é o `UnificadoDataCampoTest` (Pest GUARD), inalterado.
- 2026-07-06 · [CL] revalidado (bump last_run): rótulo da ação de baixa "Receber/Pagar" → "✓ Recebi/Paguei" (1ª pessoa, fidelidade protótipo [W] — "sim eu quero o recebi/paguei"). 2 lugares (tabela por linha + footer do drawer), só o texto do botão; ação `onBaixar`/`openBaixa` inalterada (abre a FinBaixaSheet). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-06 · [CL] revalidado (bump last_run): pacote fidelidade+D-14 ([W] "arrume"): (a) `aplicar()` ganha `only:[kpis,lancamentos,pagination,filters,periodLabel]` + controller lazy-fica contas/categorias/planosConta/agingBreakdown em closures — PARTIAL reload de verdade, mata o "recarrega a página inteira" (anti-padrão do sistema); Pest full-request avalia closures normal (mesma resposta), `UnificadoDataCampoTest`/`UnificadoLentesGuardTest` inalterados. (b) setas do mês viram SVG lucide ChevronLeft/Right (proto). (c) barra de filtro em 2 LINHAS (linha 1 = Filtrar-por+PeriodBar; linha 2 = chips/contas/plano/busca — ordem do proto). Medido: título da linha (12.5px/500) e formato do footer JÁ eram idênticos ao proto (a diferença percebida era dado real em CAPS + valores reais). UC-F01..03 (fluxo backend) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-06 · [CC] US-FIN-031 bulk actions ENTREGUE: novo UC-F04 (ações em lote) com GUARD real `UnificadoBulkGuardTest` (entrou na allowlist do financeiro-pest). Endpoint genérico `POST /unificado/bulk` (baixar/categoria/plano_conta/cancelar/exportar_csv) — ownership Tier 0 de TODOS os ids (422 fail-closed), limite 500, audit trail Activity `bulk_*` com {action,ids,count,total}. Footer bulk ganha Plano lote + Cancelar lote (Sheet destrutivo com "N títulos totalizando R$ X" ANTES de aplicar — REGRA MESTRE valor) + Exportar CSV; "Marcar pago/recebido" migra do loop de N POSTs pro endpoint bulk (1 request). Categorizar lote migrado pro mesmo endpoint (rota antiga preservada back-compat). UC-F01..03 (fluxo backend venda→título→caixa) intocados — seguem ✅ pelo RetencaoLoopE2ETest.
- 2026-07-07 · [CC] revalidado (bump last_run): dark-mode legível — ~99 classes de tema-claro fixas (`text-stone-*`/`bg-stone-*`/`border-stone-*`/`bg-white`) → tokens shadcn dark-aware (`text-foreground`/`text-muted-foreground`/`border-border`/`bg-muted`/`bg-card`). SÓ cor (diff toca apenas strings de `className`; zero lógica/valor — REGRA MESTRE n/a). Prova ao vivo browser MCP: remap nas classes reais de 1312 elementos → tabela inteira legível no dark (antes: thead/colunas invisíveis, escuro-no-escuro). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
- 2026-07-10 · [CC] revalidado (bump last_run): pacote fidelidade proto (mandato [W] "zerar diferenças", charter v19) — (a) rodapé/toggles dark theme-aware (`white`→`var(--surface)` em fin-cowork.css; emoji 🗄→lucide Archive); (b) ícones opt-in nas abas da subnav; (c) segmented 12.5px/500/600 + sombra do ativo; (d) IBM Plex Mono nos números (KPI/Valor/rodapé); (e) doc-chip inline NFe/Doc (dado do payload, zero backend). SÓ apresentação (className/CSS/ícone; zero lógica/valor/rota — REGRA MESTRE n/a). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
- 2026-07-09 · [CC] revalidado (bump last_run): fix "duas cores" no header/footer da lista ([W] screenshot anotado) — remove o tint `bg-muted/30` do `<thead>` e `bg-muted/40` do rodapé de paginação, que no dark liam mais escuros que o card (2ª cor emoldurando topo/fim). Agora header/footer herdam a cor do card (1 cor), separados só pela régua `border-b`/`border-t`. SÓ cor/className (diff = 2 classes removidas; zero lógica/valor — REGRA MESTRE n/a). UC-F01..04 (fluxo backend + bulk) intocados — seguem ✅ por RetencaoLoopE2ETest + UnificadoBulkGuardTest.
- 2026-07-14 · [CC] US-FIN-038 PR1/3 (Visão Unificada): novo **UC-F05** — pill "Conta indefinida" na coluna Conta (linha + drawer) quando a baixa tem `conta_bancaria_id NULL` (ADR 0175), CTA pra cadastro de conta. Backend `shapeTitulo.conta_indefinida` (booleano de exibição; zero valor/estoque — REGRA MESTRE n/a). GUARD real `UnificadoContaIndefinidaGuardTest` (G1 null→pill · G2 vinculada→sem pill · G3 sem baixa→sem pill) na allowlist financeiro-pest. ContasReceber/Cobranca herdam o componente nas PRs 2/3 (US-FIN-038 segue `doing`). UC-F01..04 intocados.
- 2026-08-08 · [CC] revalidado (bump `last_run`): 1 palavra de COMENTÁRIO — a referência ao
  `FinanceiroPrimaryButton` (shim deletado neste PR) passou a apontar `PageHeaderPrimary`. O botão
  "Novo título" desta tela NÃO mudou (segue o fix inline de 2026-07-07). UC-F01..03 são do fluxo
  backend venda→título→caixa, intocados — seguem ✅ pelo mesmo RetencaoLoopE2ETest.
- 2026-09-25 · [CL] revalidado (bump `last_run`): thread 07 do playbook financeiro — só acabamento do drawer.
  Index.tsx: `.fin-ai-panel` ganha a classe `fin-curadoria` (pra fin-ia.css alcançar o portal) e o glifo ✦ da aba IA vira
  o ícone `Sparkles`. Nenhum UC cobre a aba IA nem o ícone; UC-F01..05 e UC-FUNI-01..04 são de fluxo/backend e seguem
  intocados (nenhum handler, prop ou texto de ação mudou).
- 2026-10-13 · [CL] revalidado (bump `last_run`): thread 11 do playbook financeiro — o vínculo `#BL-N` (linkify do
  título e chip "Boleto #N" da lente Vínculos, `Index.tsx:803` + `FinCrossLinkify.tsx:32`) passa a levar a
  `/financeiro/cobranca` em vez de `/financeiro/boletos/N`, que não tem rota. Só o destino do clique muda.
  Varredura dos UCs deste arquivo por `BL-`, vínculo, linkify, xlink, cobranca e boleto: nenhum UC cobre o
  vínculo (o único casamento é o nome de região "ocr-boleto", que é outra coisa). UC-F01..05 e UC-FUNI-01..04
  seguem intocados. O destino fica fixado por `Onda7OutputR3Test.php:47-48`.
