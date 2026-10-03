---
id: resources-js-pages-sells-create-casos
casos: Venda balcão (Sells/Create V2) · /sells/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-02"
---

<!-- REVALIDAÇÃO 2026-10-01 (G-6: o .tsx mudou de novo — UC-S04, preço do grupo ao adicionar).
     Mudanças no .tsx: o autocomplete recebe `priceGroupId`, e o preço da linha nova e o da
     troca de grupo saem da mesma função (`precoDaBusca`). Sem grupo de preço, o envio e o
     preço são os de antes. UC-S01 e UC-S02 usam venda sem grupo, logo não são atingidos;
     UC-S03 também não (o tipo da venda não toca o preço). Status mantidos. UC-S04 nasce 🧪. -->

<!-- REVALIDAÇÃO 2026-10-01 (G-6: o .tsx mudou — UC-S03, reparo como tipo de venda).
     A mudança no .tsx foi o `transform` do envio ganhar `...camposDeSubtipo(props.subType)`,
     que devolve `{}` para venda comum. Revalidação por alcance: UC-S01 (fiado) e UC-S02
     (desconto %) exercem venda comum — o payload deles não muda; status de ambos mantido
     como estava (🧪), sem promoção. UC-S03 nasce 🧪, provado pelo Pest da lane sells-pest. -->

<!-- REVALIDAÇÃO 2026-08-17 (G-6: o .tsx mudou depois do last_run anterior).
     A mudança na tela foi UMA linha — o `placeholder` do campo de valor, que exibia
     ao usuário a string de redação `R$ [redacted Tier 0]` (resíduo do filter-repo),
     voltando a `R$ 0,00`. Como cada UC foi revalidado:

     UC-S02 · tests/Feature/Calculo/CalculoValorSellsTest.php — RODADO no CT 100
              (não local, `proibicoes.md` §Ambiente): 14 passed, 1 skipped,
              20 assertions. O teste do caso aparece por nome no output:
              `calculate invoice total desconto percentual nao infla` ✓.

     UC-S01 · e2e/sells-index.spec.ts + e2e/sells-venda-balcao.spec.ts — MEDIDO que
              não dependem do elemento alterado: `sells-index` tem 0 referências a
              placeholder; as 2 de `sells-venda-balcao` são
              `getByPlaceholder(/Buscar por nome, SKU/i)`, a caixa de BUSCA, não o
              campo de valor. Mudar o placeholder do valor não os alcança.

     O que este bump NÃO afirma: que a suíte inteira de Sells foi re-rodada. Ele
     afirma o que foi medido acima, nos dois UCs deste arquivo. -->

<!-- REVALIDAÇÃO 2026-08-26 (G-6: o .tsx mudou depois do last_run de 17/08).
     A mudança na tela foi UMA linha de comportamento: o `onOpenChange` do
     AlertDialog de recuperação de rascunho deixou de chamar `handleDraftDiscard()`
     (que faz `localStorage.removeItem()`, irreversível) e passou a apenas fechar o
     diálogo. Antes, Esc / clique fora / qualquer fechamento que não fosse o botão
     "Recuperar" apagava a venda montada em silêncio — vetor real relatado pela
     ROTA LIVRE durante as janelas de 503 do deploy.

     Como cada UC foi revalidado — por MEDIÇÃO do alcance do diff, não por leitura:

     UC-S01 · e2e/sells-index.spec.ts + e2e/sells-venda-balcao.spec.ts — grep
              contado por `rascunho|Recuperar|Descartar|draftRecover|AlertDialog`:
              **0 ocorrências em cada um**. Nenhum dos dois specs interage com o
              diálogo de rascunho, logo a mudança não os alcança.

     UC-S02 · tests/Feature/Calculo/CalculoValorSellsTest.php — mesmo padrão:
              **0 ocorrências**. É teste de totalizador (`calculateInvoiceTotal` /
              `Util::num_uf`); o diff não toca nenhuma linha de cálculo.

     Controle positivo do padrão de busca: o mesmo grep em `Sells/Create.tsx`
     devolve **55** ocorrências — ou seja, o padrão acha o alvo quando ele existe,
     e o 0 acima é ausência real, não regex quebrada.

     NÃO rodei Pest nem e2e nesta revalidação: `vendor/` e `node_modules/` não
     existem neste worktree e a suíte só roda no CT 100 (`proibicoes.md` §Ambiente).
     O que está afirmado aqui é o ALCANCE do diff — medido — não um veredito de
     execução. O comportamento novo em si ainda não tem teste: está no Backlog
     abaixo, sem id, conforme G-2. -->

<!-- REVALIDAÇÃO 2026-10-02 (G-6: o .tsx mudou depois do last_run de 01/10).
     Única mudança no .tsx: `osOrigem` entrou nas dependências do useMemo do `draftKey`
     (ESLint exhaustive-deps). osOrigem vem das props e não muda durante a tela, então o
     rascunho segue igual: venda aberta da OS não usa rascunho (UC-S07), venda comum usa.
     Nenhum UC muda de status. -->



# Casos de Uso & Aceite — Venda balcão (Sells/Create)

> Tela P0 do fio **venda → estoque → faturamento → caixa** (mandato ONDAS-QUALIDADE Q2).
> O encadeamento backend (venda gera título a receber +30d, recebimento baixa o título)
> é provado por `tests/Feature/TravaSegunda/RetencaoLoopE2ETest.php`; aqui fica o contrato
> DO LADO DA TELA que a Larissa opera no balcão. `Status: ✅` só com veredito `pass` no
> manifesto G-7 (`scripts/casos-test-results.json`).
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

## UC-S01 · Venda balcão a prazo (fiado)
- **Persona:** Larissa @ ROTA LIVRE (balcão, 1280px) — cliente leva o produto e paga depois.
- **Como usa:** abre a venda, busca o produto (nome/SKU/código de barras), confere a linha no carrinho, NÃO informa pagamento e salva. O sistema acusa o saldo devedor em vez de bloquear (decisão [W] 2026-05-27 — paridade com o POS Blade que sempre permitiu finalizar sem pagamento).
- **Aceite:** Dado cliente default (Walk-In) + location pré-selecionada · Quando adiciona produto e salva sem pagamento · Então o indicador **"Venda a prazo — saldo devedor R$ X"** aparece antes do submit, o POST cria a venda (backend `payment_status=due`) e a tela sai do formulário sem erro.
- **Teste:** `e2e/sells-venda-balcao.spec.ts` (Playwright, harness G-3 e2e-gate).
- **Status: 🧪** _(refactor só-de-layout 2026-06-18 — total de itens no rodapé + ordem desconto→pagamento; o fluxo venda-a-prazo não mudou. A prova de 2026-06-11 ficou anterior ao código; re-rodar o e2e + `npm run casos:results` revalida e restaura o status verde.)_

---

## UC-S02 · Venda com desconto percentual não infla o total (dente de cálculo)
- **Persona:** Larissa @ ROTA LIVRE (balcão, 1280px) — aplica um desconto em % sobre o total da venda.
- **Como usa:** monta o carrinho, informa desconto percentual (ex 10,05%) e finaliza. O `final_total` gravado tem que ser o total real com desconto — **nunca** um valor inflado ~×100.000 por erro de parsing de separador decimal.
- **Aceite:** Dado uma venda de `227,90` com desconto de `10,05%` · Quando o totalizador `ProductUtil::calculateInvoiceTotal` roda (que passa por `Util::num_uf`) · Então `total_before_tax = 227.90`, `discount = 22.90395` e `final_total = 204.99605` (jamais `~20.499.605`); e o invariante `final_total ≤ total_before_tax` vale sempre. Round-trip `num_uf(num_f(x)) == x` na precisão de moeda.
- **Divergência de pagamento (caracterizada, não unificada):** `getTotalPaid` é **líquido** (`SUM(IF(is_return=0, amount, amount*-1))` — desconta devolução) e é a **fonte de verdade** do `payment_status` (via `calculatePaymentStatus`); `getTotalAmountPaid` é **bruto** (`SUM(amount)` — ignora `is_return`). O teste trava as duas definições ATUAIS. Unificar = mudança de valor em prod → **US separada sob REGRA MESTRE** (dupla confirmação + antes→depois + OK [W]), nunca pega carona neste PR.
- **Teste:** `tests/Feature/Calculo/CalculoValorSellsTest.php` (Pest, property + golden no totalizador real + discriminação RED + caracterização da divergência). Guards de `num_uf` em isolamento: `tests/Unit/Utils/IncidentValorInfladoNumUfTest.php` + `NumUfHeuristicPtBRTest.php`.
- **Status: 🧪** _(Onda 1.4 — teste green no CT100, mas o veredito ainda não entra no manifesto G-7 `scripts/casos-test-results.json` (Pest fora do harness JUnit e2e). Vira ✅ quando o manifesto carregar o veredito `pass` deste UC. Origem do vetor: incidente 2026-06-05, fix #2279.)_

---

## UC-S03 · Venda aberta como reparo é gravada como reparo, sem mudar valor nem estoque
- **Persona:** técnico de assistência que abre "Nova OS" no Repair. A porta que entrega o tipo ao React é `/pos/create?sub_type=repair` (`SellPosController`). O botão da listagem abre `/sells/create?sub_type=repair` (`SellController`, que lê `?sale_type=`) e passou a **redirecionar** para ela; o menu apontava para `/sells/pos/create` (404 em prod) e foi corrigido para `/pos/create`. Medido 2026-10-01.
- **Como usa:** monta a venda do reparo (peças, serviço, desconto, pagamento) e salva. Reparo é um **tipo de venda** (decisão [W] 2026-10-01: *"é uma venda, tipo de venda igual ao OS auto"*): o cálculo e a baixa de estoque são os da venda comum; só muda onde a venda aparece.
- **Aceite:** Dado o PDV aberto com `sub_type=repair` · Quando salva · Então o envio carrega `sub_type=repair` e `print_label=0`, a venda grava `transactions.sub_type='repair'`, os campos de reparo enviados (ex. nº de série) persistem e a tela volta para a listagem do Repair. **E** uma venda comum idêntica tem o mesmo `final_total`, `total_before_tax`, `tax_amount`, `discount_amount`, as mesmas linhas e a mesma baixa de estoque. **E** se o envio de reparo vier sem `print_label`, a venda grava e redireciona sem erro.
- **Teste:** `tests/Feature/Sells/SellsRepairSubtipoContratoTest.php` (POST `/pos` real, tenant 98, lane `sells-pest`) + `tests/js/sells-subtipo-venda.test.ts` (o que o envio carrega, lane `sells-v3-dominio-gate`).
- **Status: 🧪** _(nasce sem run — Pest só no CI/CT 100; vira ✅ com o veredito `pass` no manifesto G-7.)_
- **Fora deste UC (próximas ondas):** os campos de reparo na tela (aparelho, marca, modelo, série, defeitos, status, prazo, garantia, checklist, senha) e a edição da venda de reparo pelo React.

## UC-S04 · Produto adicionado com grupo de preço entra pelo preço do grupo
- **Persona:** operador do PDV num local com grupo de preço padrão, ou que escolhe um grupo (ou um cliente com grupo) antes de lançar os itens.
- **Como usa:** com o grupo já definido na venda, busca o produto (digitando ou pelo leitor de código) e adiciona.
- **Aceite:** Dado uma venda com grupo de preço · Quando adiciona um produto · Então a busca vai a `/products/list` com `price_group` e a linha entra pelo preço do grupo (fixo → o valor; percentual → % do preço da variação), o mesmo que o POS Blade aplica (`getProductRow` → `getVariationGroupPrice`). **E** variação sem preço naquele grupo entra pelo preço base. **E** sem grupo a busca não leva `price_group` e o preço é o base, como antes.
- **Teste:** `tests/js/sells-busca-preco-grupo.test.tsx` (o envio leva o grupo e a linha usa o preço dele; lane `sells-v3-dominio-gate`) + `tests/Feature/Sells/BuscaProdutoPrecoDeGrupoContratoTest.php` (o preço que o React recebe, calculado em SQL, é igual ao do Blade, calculado em PHP, e à conta feita à mão; tenant 98, lane `sells-pest`).
- **Status: 🧪** _(rodado no CT 100 em 2026-10-01: 5 passed, 25 assertions; vira ✅ com o veredito `pass` no manifesto G-7.)_
- **Preço de grupo 0 (2026-10-02):** segue o Blade (`getProductRow`, `!empty`). Fixo 0 vale e a linha entra em 0; percentual 0 não vale e a linha entra pelo preço base. Medido no MySQL; provado pelo mesmo teste Pest.
- **Fora deste UC:** a tela `Sells/Edit` não tem grupo de preço e segue sem ele.

---

## UC-S05 · Venda de reparo registra o aparelho e o atendimento
- **Persona:** técnico de assistência no balcão.
- **Como usa:** na venda aberta como reparo, a seção **Reparo** pede status (obrigatório, já vem com o status padrão do Repair), entrega prevista, concluído em, garantia, marca, aparelho, modelo, nº de série e o problema relatado (vários, com sugestões das configurações do Repair). Na venda comum a seção não existe. Paridade com o POS Blade de reparo (`repair_pos.blade.php`).
- **Aceite:** Dado a venda aberta como reparo · Então a tela recebe as opções de reparo **só do próprio business** (status de outro business nunca aparece) e sem status não dá pra salvar · Quando salva · Então a venda grava `repair_status_id`, `repair_serial_no`, `repair_due_date` (lido no formato da data da venda) e `repair_defects` no JSON do Tagify (`[{"value":"…"}]`, o que a tela Blade e o recibo leem), com o **mesmo** `final_total` e a mesma baixa de estoque da venda comum.
- **Teste:** `tests/Feature/Sells/SellsRepairSubtipoContratoTest.php` (UC-S05: opções por business + gravação, tenant 98, lane `sells-pest`) + `tests/js/sells-reparo-venda.test.ts` (o que o envio produz, lane `sells-v3-dominio-gate`).
- **Status: 🧪** _(nasce sem run — vira ✅ com o veredito `pass` no manifesto G-7.)_
- **Fora deste UC:** checklist pré-reparo e senha/padrão do aparelho (→ UC-S06) · abrir a venda a partir de uma OS (`job_sheet_id`), que **adiciona peças ao carrinho** e por isso é mudança de valor, sob a REGRA MESTRE.

---

## UC-S06 · Venda de reparo registra checklist pré-reparo e senha/padrão do aparelho
- **Persona:** técnico de assistência recebendo o aparelho no balcão.
- **Como usa:** na seção Reparo, ao trocar marca ou aparelho, a lista de **modelos** se restringe aos que cabem (como o Blade, que recarrega via `/repair/get-device-models`). Escolhido o modelo, aparece o **checklist pré-reparo**: os itens padrão das configurações do Repair e depois os do modelo, cada um Sim / Não / N/A (N/A por padrão). Registra também a **senha** do aparelho e o **padrão de desbloqueio** tocando os pontos da grade 3×3 em ordem.
- **Aceite:** Dado a venda aberta como reparo · Então a tela recebe **só os modelos do próprio business** (modelo de outro business nunca aparece), cada um com seu checklist sem itens vazios · Quando salva com modelo, senha, padrão e respostas · Então a venda grava `repair_model_id`, `repair_security_pwd`, `repair_security_pattern` (a sequência 1–9, formato do `patternlock.js` do Blade) e `repair_checklist` com **todos** os itens exibidos (`not_applicable` onde não houve resposta, como o Blade) — com o **mesmo** `final_total` e a mesma baixa de estoque da venda comum.
- **Teste:** `tests/Feature/Sells/SellsRepairSubtipoContratoTest.php` (UC-S06, tenant 98, lane `sells-pest`) + `tests/js/sells-reparo-venda.test.ts` (filtro de modelos, itens, padrão e envio; lane `sells-v3-dominio-gate`).
- **Status: 🧪** _(nasce sem run.)_
- **Diferença consciente do Blade:** sem marca nem aparelho escolhidos, a lista mostra **todos** os modelos (o Blade, depois de trocar o aparelho para vazio, filtra por `device_id IS NULL`). O padrão é tocado ponto a ponto em vez de arrastado — mesmo valor gravado, e funciona com teclado.

---

## UC-S07 · Venda de reparo aberta a partir de uma OS
- **Persona:** técnico que conclui a OS e vai faturar ("Adicionar fatura" na listagem de OS → `/pos/create?sub_type=repair&job_sheet_id=N`).
- **Como usa:** a venda abre com o **cliente**, o **local**, os dados do aparelho (status, marca, aparelho, modelo, série, defeitos, entrega, senha, padrão, checklist) e as **peças usadas** da OS no carrinho, com aviso do que veio da OS e de peça que não pôde entrar.
- **Valor (REGRA MESTRE):** cada peça entra pelo **mesmo preço** que teria se o operador a adicionasse à mão no React: o `/products/list` com o grupo de preço com que a venda abre, e a regra do `precoDaBusca` (#8455) — preço do grupo se houver, senão o base; a quantidade é a da OS; mesma variação repetida vira uma linha só, somada. O cliente da OS entra pelo próprio `handleCustomerSelect` — se ele tiver grupo de preço, o React reprecifica como faria na mão. A venda leva `repair_job_sheet_id`.
- **Aceite:** Dado uma OS do próprio business com 2 unidades de uma peça de preço 37,50 (valor fictício do teste) · Quando a venda da OS abre · Então a peça vem com `unit_price` = 37,50 = o `selling_price` que `/products/list` devolve pra mesma variação (dupla prova) e `quantity` = 2 · Quando salva · Então `final_total` = 75,00, estoque 10 → 8 e `repair_job_sheet_id` = a OS · Quando a OS é concluída depois · Então **não** nasce uma 2ª venda (o `JobSheetObserver` é idempotente pela OS; controle positivo: OS sem fatura, ao concluir, gera a venda) · Dado uma OS de **outro business** · Então a venda abre **sem** origem, com 200 (antes caía em 500). · Dado o local com **grupo de preço padrão** e preço de grupo 30 para a peça (base 37,50) · Então a peça da OS entra a **30** = o `variation_group_price` que `/products/list?price_group=` devolve ao React.
- **Teste:** `tests/Feature/Sells/SellsRepairSubtipoContratoTest.php` (UC-S07, tenant 98, lane `sells-pest`) + `tests/js/sells-reparo-venda.test.ts` (peças → carrinho, vínculo no envio).
- **Status: 🧪** _(nasce sem run.)_
- **Diferenças conscientes do Blade:** o técnico da OS (`service_staff` → `res_waiter_id`) não é trazido — o PDV React não tem esse campo; a venda da OS não usa rascunho no `localStorage` (recuperar outra venda por cima misturaria origens).

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens abaixo SEM token de UC de
> propósito até existir teste real — visíveis, não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] Venda paga no ato (dinheiro/PIX) fecha sem saldo devedor** — caminho feliz com pagamento integral; exige modelar o bloco de pagamentos no harness.
- **[BACKLOG] Bloqueio por limite de crédito** — backend devolve `errors.venda` e o carrinho fica intacto (toast 8s) — exige fixture de limite no seed.
- **[BACKLOG] Emissão NF-e da venda (homolog/stub SEFAZ)** — wire já existe (suítes NfeBrasil); espelhar como UC quando o fluxo de emissão entrar no harness e2e.
- **[BACKLOG] Fechar o diálogo de rascunho preserva a venda montada** — Dado um rascunho salvo · Quando o operador fecha o diálogo de recuperação SEM escolher (Esc, clique fora) · Então o rascunho **continua** no `localStorage` e a pergunta volta na próxima montagem; só o botão "Descartar" apaga. Exige exercitar `AlertDialog` do Radix no harness e2e (incluindo se `Esc` de fato fecha nesta versão, que não foi verificado). Origem: ROTA LIVRE perdeu venda montada durante janela de 503 do deploy, 2026-08-26.

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate, gate de PR desde Onda Q1) — vereditos viram manifesto via `npm run casos:results`.
2. **Cadência:** rodar ao fim de toda mexida em Sells/Create. UC ❌ = regressão → lição + conserto antes de seguir.

## Trilha do tempo
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S01 venda a prazo + spec Playwright `sells-venda-balcao.spec.ts`; produto E2E-0001 entrou no VisregTenantSeeder (enable_stock=0).
- 2026-06-18 · [CC] refactor só-de-layout (Wagner): total de itens no rodapé do card Produtos + card de desconto (Resumo) movido pra antes do Pagamento. Sem mudança de comportamento — UC-S01 baixado pra 🧪 até re-rodar o e2e (G-7 frescor).
- 2026-08-26 · [CC] Fechar o diálogo de recuperação parou de apagar o rascunho (o `onOpenChange` chamava `handleDraftDiscard()` → `localStorage.removeItem()`, irreversível; agora só o botão "Descartar" apaga). Mudança de COMPORTAMENTO, não de layout. UC-S01 e UC-S02 revalidados por medição de alcance (0 ocorrências do diálogo nos testes de ambos; controle positivo 55 no `.tsx`) — nenhum dos dois é atingido, então nenhum foi rebaixado. Comportamento novo entrou no Backlog sem id (G-2) até haver teste que o exercite. Origem: ROTA LIVRE perdendo venda montada nas janelas de 503 do deploy.
- 2026-10-01 · [CL] UC-S03 (reparo como tipo de venda, onda 1): o envio passou a carregar `sub_type` quando o PDV é aberto como reparo; antes a venda de reparo pelo React gravava como venda comum. Medido em prod: 6 vendas de reparo no total, todas do biz=1, a última em 2023-10-11 — defeito latente, sem dado real perdido. UC-S01/S02 não são atingidos (venda comum envia exatamente o mesmo payload de antes).
- 2026-10-02 · [CL] UC-S04, preço de grupo 0 alinhado ao Blade. A anotação de 2026-10-01 ("o Blade trata 0 como sem preço") estava incompleta: medido no MySQL, o Blade usa o fixo 0 e ignora só o percentual 0. O `filterProduct` passou a devolver NULL no percentual que dá 0. Impacto em prod: nenhum (os 3305 preços de grupo são todos fixos).
- 2026-10-01 · [CL] UC-S04: o autocomplete passou a mandar `price_group`, e o produto adicionado com grupo definido entra pelo preço do grupo, como no POS Blade. Antes, só a troca de grupo reprecificava, e só as linhas que já estavam no carrinho. Medido em prod: nenhuma venda com grupo de preço desde 2026-05-27 (a coluna é gravada: 90 vendas com grupo de 2021 a 2026-04-24, um único business) — defeito latente, nenhum valor gravado muda.
- 2026-07-02 · [CC] Onda 1.4 (dente de cálculo): UC-S02 declarado com teste no MESMO PR (coordenação 1.3 ↔ 1.4, regra "declarar UC + teste = 1 PR"). Property `num_uf(num_f(x))==x` + golden no totalizador real `calculateInvoiceTotal` (227,90 − 10,05% = 204.99605, não infla) + discriminação RED vs strip-do-ponto + caracterização da divergência `getTotalPaid`(líquido) ≠ `getTotalAmountPaid`(bruto). TEST-ONLY — nenhum método de cálculo alterado.
