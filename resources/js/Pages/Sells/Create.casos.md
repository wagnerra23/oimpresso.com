---
id: resources-js-pages-sells-create-casos
casos: Venda balcão (Sells/Create V2) · /sells/create
irmaos: Create.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-27"
---

<!-- REVALIDAÇÃO 2026-10-27 (G-6: o .tsx mudou — US-SELL-079, resgate de pontos).
     O total passa a subtrair `rp_redeemed_amount`, que nasce "0.00" e só muda pelo bloco de resgate,
     que só aparece com a flag SELLS_RESGATE_PONTOS_ENABLED e a empresa com pontos. Venda sem resgate:
     total e envio idênticos aos de antes, então UC-S01..S07 e UC-VST-* mantêm o status. UC-SRP-* nascem ⬜. -->

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

<!-- REVALIDAÇÃO 2026-10-26 (G-6: o .tsx mudou depois do last_run de 02/10).
     Commits no .tsx depois de 02/10, medidos com `git log --since=2026-10-01 -- Sells/Create.tsx`
     (clone completo): #8439 (03/10, checklist pré-reparo, senha/padrão e modelos filtrados) e
     #8447 (03/10, venda de reparo aberta a partir da OS). Os dois já trazem o próprio caso neste
     arquivo — UC-S06 e UC-S07 — e mantêm o 🧪. A seção nova desta edição (UC-VST-01..05, ICMS-ST,
     US-SELL-078) é do servidor e não toca o .tsx. Nenhum UC muda de status. -->

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

## ICMS-ST no total da venda (US-SELL-078 · [W] 2026-10-26)

> **Âncora:** US-SELL-078 (`memory/requisitos/Sells/SPEC.md`). O ST é calculado pela conta da NF-e (R-NFE-043) e
> entra no fechamento da venda. Números do fixture do Fiscal: item de 1.000,00 de SC para contribuinte no RJ, ICMS
> 12%, MVA 40%, interna do RJ 18% → ST 132,00. Prova: `tests/Feature/Sells/IcmsStNaVendaContratoTest.php`.

## UC-VST-01 · O caixa recebe o ST do carrinho pela conta da NF-e `[V0]` `[must]`
- **Aceite:** Dado o carrinho com o item de 1.000,00 e cliente contribuinte no RJ · Quando a tela pede o ST (`POST /sells/icms-st`) · Então recebe `132.00`.
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-01`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VST-02 · Ao gravar, total da venda = vNF = título `[V0]` `[must]`
- **Aceite:** Dado a venda gravada com total 1.132,00 e ST enviado 132,00 · Então a venda guarda `icms_st_valor = 132`, a base da nota é 1.000,00, o vNF é 1.132,00 e o título do Financeiro nasce com 1.132,00.
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-02`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VST-03 · Total enviado sem o ST é recusado com o valor certo `[V0]` `[must]`
- **Aceite:** Dado a mesma venda gravada com total 1.000,00 e ST enviado 0 · Então a gravação é recusada com "O ICMS-ST desta venda é R$ 132,00, mas o total enviado considerou R$ 0,00. Atualize a venda e salve de novo."
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-03`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VST-04 · Consumidor final da mesma UF não tem ST `[V0]` `[must]`
- **Aceite:** Dado cliente **sem inscrição estadual** em SC (mesma UF do emitente) · Então o ST é 0 e a venda segue como hoje.
- **Reescrito em 2026-10-29 (P-153, [W] "conserte"):** o aceite de 2026-10-26 dizia *"cliente contribuinte em SC … o ST é 0"*. Era corte de escopo da US-SELL-078, não regra tributária: dentro do estado o contribuinte (quem revende) também paga ST — é o caso do Sabor Brasil, tempero para supermercado em SP com CST 10. O contribuinte da mesma UF passou para o UC-VST-06.
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-04`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VST-06 · Contribuinte da mesma UF paga o ST pela mesma conta da NF-e `[V0]` `[must]`
- **Aceite:** Dado cliente contribuinte em SC (mesma UF do emitente) e a interna da UF cadastrada · Quando a venda grava com ST enviado 132,00 e total 1.132,00 · Então a venda guarda `icms_st_valor = 132`, pela mesma conta da NF-e (a interna da própria UF vai ao motor, `NfeService::internaDoSt`).
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-06`; o lado da nota em `Modules/NfeBrasil/Tests/Feature/NfeStDentroDoEstadoTest.php`.
- **Status: 🧪** — roda na lane `sells-pest`.

## UC-VST-05 · Venda sem cliente identificado não tem ST `[V0]` `[must]`
- **Aceite:** Dado venda sem cliente · Então o ST é 0.
- **Teste:** `tests/Feature/Sells/IcmsStNaVendaContratoTest.php` — `UC-VST-05`.
- **Status: 🧪** — roda na lane `sells-pest`.

---

## Cupom por código no caixa (US-SELL-076 · lacunas [W] 2026-10-27)

> Só o servidor (`POST /sells/cupom`), atrás da flag `SELLS_CUPOM` desligada; não toca o `Create.tsx`. A promoção por
> produto, categoria, marca ou período já existe (`discounts`, por linha). Carrinho do exemplo: subtotal 200,00 sem
> promoção, promoção das linhas 20,00. Prova: `tests/Feature/Sells/CupomDaVendaContratoTest.php`.

## UC-VCUP-01 · Cupom que não acumula e dá mais substitui a promoção `[V0]` `[must]`
- **Persona:** vendedor no caixa, com o cliente pedindo o desconto do cupom.
- **Aceite:** Dado um carrinho de 200,00 com promoção de 20,00 e o cupom de 15% que não acumula · Quando o vendedor aplica o cupom · Então o desconto é 30,00, substitui a promoção e o total é 170,00.
- **Teste:** `tests/Feature/Sells/CupomDaVendaContratoTest.php` — `UC-VCUP-01`.
- **Regressão que defende:** somar cupom e promoção quando o cupom não acumula.
- **Status: ⬜** — não verificado; aguarda o run da lane `sells-pest`.

## UC-VCUP-02 · Cupom que não acumula e dá menos é recusado `[V0]` `[must]`
- **Persona:** vendedor no caixa, com o cliente pedindo o desconto do cupom.
- **Aceite:** Dado o mesmo carrinho e o cupom de 5% que não acumula · Quando o vendedor aplica o cupom · Então ele é recusado ("A promoção do carrinho já dá mais desconto que este cupom, e ele não acumula.") e fica a promoção (total 180,00).
- **Teste:** `tests/Feature/Sells/CupomDaVendaContratoTest.php` — `UC-VCUP-02`.
- **Regressão que defende:** o cupom trocar a promoção por um desconto menor.
- **Status: ⬜** — não verificado; aguarda o run da lane `sells-pest`.

## UC-VCUP-03 · Cupom que acumula incide depois da promoção e não passa da base `[V0]` `[must]`
- **Persona:** vendedor no caixa, com o cliente pedindo o desconto do cupom.
- **Aceite:** Dado o cupom de 10% que acumula · Quando é aplicado · Então o desconto é 18,00 (sobre 180,00) e o total 162,00 · Dado um cupom de valor 250,00 que acumula · Quando é aplicado · Então o desconto para em 180,00.
- **Teste:** `tests/Feature/Sells/CupomDaVendaContratoTest.php` — `UC-VCUP-03`.
- **Regressão que defende:** desconto calculado sobre o subtotal cheio, ou total negativo.
- **Status: ⬜** — não verificado; aguarda o run da lane `sells-pest`.

## UC-VCUP-04 · Validade, limite de uso e valor mínimo `[V0]` `[must]`
- **Persona:** vendedor no caixa, com o cliente pedindo o desconto do cupom.
- **Aceite:** Dado um cupom vencido ou ainda não vigente · Quando é aplicado · Então "fora da validade"; dado compra abaixo do mínimo · Então recusado; dado um cupom de uso único já gasto uma vez · Quando se tenta gastar de novo · Então falha, o contador fica em 1 e o cupom passa a "esgotado".
- **Teste:** `tests/Feature/Sells/CupomDaVendaContratoTest.php` — `UC-VCUP-04`.
- **Regressão que defende:** cupom usado além do limite ou fora do período.
- **Status: ⬜** — não verificado; aguarda o run da lane `sells-pest`.

## UC-VCUP-05 · A rota só existe ligada; cupom de outra empresa é inválido `[T0]` `[must]`
- **Persona:** vendedor de uma empresa (tenant 98), com outra empresa como adversária.
- **Aceite:** Dado a flag ligada · Quando o vendedor digita " quinze " · Então vale como QUINZE e dá 30,00; dado um cupom que só existe em outra empresa · Então responde "Cupom não encontrado."; dado a flag desligada (padrão) · Então a rota responde 404.
- **Teste:** `tests/Feature/Sells/CupomDaVendaContratoTest.php` — `UC-VCUP-05`.
- **Regressão que defende:** usar cupom de outra empresa.
- **Status: ⬜** — não verificado; aguarda o run da lane `sells-pest`.

## Maquininha integrada ao caixa — TEF (US-SELL-075)

> **Âncora:** US-SELL-075 (`memory/requisitos/Sells/SPEC.md`). O caixa manda o valor para a maquininha e a resposta
> (bandeira, NSU, autorização) preenche a linha de pagamento. Camada independente da adquirente; o integrador real é
> decisão [W] (`memory/requisitos/Sells/BRIEFING.md`). Driver `simulador`: valor terminado em ,99 é negado.
> Flag `SELLS_TEF_ENABLED` desligada. Prova: `tests/Feature/Sells/TefCaixaContratoTest.php`.

## UC-TEF-01 · Com a flag desligada a maquininha não existe para a empresa `[V0]` `[must]`
- **Persona:** dono da empresa sem a maquininha contratada.
- **Aceite:** Dado a flag desligada, ou ligada só para outra empresa · Quando o caixa pede uma cobrança · Então recebe 404 e nada é gravado.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-01`.
- **Regressão que defende:** US-SELL-075 (nasce atrás de flag): a cobrança não pode existir para quem não ligou.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-02 · O valor vai para a máquina e a aprovação traz bandeira, NSU e autorização `[V0]` `[must]`
- **Persona:** balconista cobrando no cartão.
- **Aceite:** Dado crédito de 204,90 em 3x · Quando o caixa envia e depois consulta · Então a cobrança nasce `pendente`, vira `aprovada` com bandeira, NSU e autorização, e grava 20.490 centavos.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-02`.
- **Regressão que defende:** US-SELL-075 aceite: a resposta da máquina preenche o pagamento, sem digitação dupla.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-03 · O mesmo clique reenviado não cobra duas vezes `[V0]` `[must]`
- **Persona:** balconista que clicou duas vezes ou perdeu a rede no meio.
- **Aceite:** Dado duas chamadas com a mesma chave de idempotência · Então existe uma só cobrança e as duas respostas têm o mesmo id.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-03`.
- **Regressão que defende:** cobrança em dobro no cartão do cliente.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-04 · Cartão recusado volta como negada `[V0]` `[must]`
- **Persona:** balconista com cartão recusado.
- **Aceite:** Dado débito de 12,99 · Quando a máquina responde · Então o status é `negada` e a autorização fica vazia.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-04`.
- **Regressão que defende:** pagamento marcado como pago sem aprovação do emissor.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-05 · Cobrança de outra empresa não aparece `[V0]` `[must]`
- **Persona:** balconista da empresa 98; adversário é a empresa 99.
- **Aceite:** Dado uma cobrança da empresa 99 · Quando o caixa da 98 consulta pelo id · Então recebe 404.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-05`.
- **Regressão que defende:** vazamento cross-tenant de cobrança (ADR 0093, Tier 0).
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-06 · Valor com mais de 2 casas é recusado; centavos batem por dois caminhos `[V0]` `[must]`
- **Persona:** balconista com desconto percentual no total.
- **Aceite:** Dado `204.99605` · Então a validação recusa. E 204,99 / 12,5 / 0,1 / 1.000 / 1.234,05 viram 20.499 / 1.250 / 10 / 100.000 / 123.405 centavos.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-06`.
- **Regressão que defende:** regra mestre de valor: float ambíguo inflando o valor (incidente 2026-06-05).
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-TEF-07 · Sem resposta da adquirente a cobrança vira erro visível `[V0]` `[must]`
- **Persona:** balconista com a adquirente fora do ar.
- **Aceite:** Dado o driver sem resposta · Quando o caixa envia · Então a cobrança fica `erro`, nunca pendurada.
- **Teste:** `tests/Feature/Sells/TefCaixaContratoTest.php` — `UC-TEF-07`.
- **Regressão que defende:** cobrança presa em "aguardando" sem saída para o caixa.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## Venda sem internet no caixa (US-SELL-054)

> **Âncora:** US-SELL-054 (`memory/requisitos/Sells/SPEC.md`). O caixa guarda a venda feita sem rede e reenvia o
> mesmo payload ao voltar a conexão, com uma chave por venda (`offline_uuid`). O servidor grava uma vez só.
> Flag `SELLS_OFFLINE_ENABLED` desligada. Números à mão: 10 em estoque, venda de 2 × 50,00 = 100,00, saldo 8.
> Prova: `tests/Feature/Sells/VendaOfflineContratoTest.php`.

## UC-VOFF-01 · A venda reenviada duas vezes grava uma venda e baixa o estoque uma vez `[V0]` `[must]`
- **Persona:** balconista que vendeu sem internet e viu a conexão voltar.
- **Aceite:** Dado a mesma venda enviada duas vezes com a mesma chave · Então existe uma venda de 100,00, o saldo vai de 10 para 8 e fica 8, e a segunda resposta diz `duplicada` com o mesmo id.
- **Teste:** `tests/Feature/Sells/VendaOfflineContratoTest.php` — `UC-VOFF-01`.
- **Regressão que defende:** US-SELL-054: venda e baixa de estoque em dobro ao reenviar (regra mestre de estoque).
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-VOFF-02 · A chave de uma empresa não acha venda de outra `[V0]` `[must]`
- **Persona:** balconista da empresa 98; adversário é a empresa 99.
- **Aceite:** Dado a chave já usada pela empresa 99 · Quando a 98 envia a mesma chave · Então a 98 grava a própria venda.
- **Teste:** `tests/Feature/Sells/VendaOfflineContratoTest.php` — `UC-VOFF-02`.
- **Regressão que defende:** chave de venda de outra empresa apagando a venda desta (ADR 0093, Tier 0).
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-VOFF-03 · Com a flag desligada a chave é ignorada `[V0]` `[must]`
- **Persona:** empresa sem a venda offline ligada.
- **Aceite:** Dado a flag desligada · Então a venda segue o fluxo de hoje (redirect) e nenhuma chave é gravada.
- **Teste:** `tests/Feature/Sells/VendaOfflineContratoTest.php` — `UC-VOFF-03`.
- **Regressão que defende:** mudança de comportamento do caixa de hoje sem a flag.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## UC-VOFF-04 · Venda offline recusada volta em JSON e não grava a chave `[V0]` `[must]`
- **Persona:** balconista cuja venda offline o servidor recusou.
- **Aceite:** Dado uma venda que o servidor recusa · Então a resposta é `success: 0` em JSON, nenhuma chave é gravada e o estoque fica 10, para o caixa manter a venda na fila.
- **Teste:** `tests/Feature/Sells/VendaOfflineContratoTest.php` — `UC-VOFF-04`.
- **Regressão que defende:** venda recusada sumindo da fila, ou chave gravada sem venda.
- **Status: ⬜** — aguarda o run da lane `sells-pest` deste PR (CT 100 fora do ar).

## Resgate de pontos de fidelidade (US-SELL-079 · [W] 2026-10-27)

> **Âncora:** US-SELL-079 (`memory/requisitos/Sells/SPEC.md`). Os pontos nativos (`business.enable_rp`) já ganham,
> vencem e aparecem na ficha; o caixa React passa a resgatar. Empresa do teste: ponto vale 0,10, resgate mínimo 10
> pontos, venda mínima 20,00. A tela só mostra o resgate com `SELLS_RESGATE_PONTOS_ENABLED`; a conferência no
> servidor vale sempre.

## UC-SRP-01 · Valor do resgate = pontos × valor do ponto, 2 casas `[V0]` `[must]`
- **Persona:** Operador de caixa, conferindo quanto o cliente economiza.
- **Aceite:** Dado o ponto valendo 0,10, 0,15, 0,3333, 0,005 ou 0,0049 · Quando se resgatam 50, 7, 3, 1 e 1 pontos · Então o resgate vale 5,00, 1,05, 1,00, 0,01 e 0,00, igual na tela e no servidor.
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-01` · `tests/js/resgate-pontos-valor.test.ts` (a mesma tabela, na conta da tela).
- **Regressão que defende:** Tela e servidor calculando o resgate de formas diferentes, com o total gravado divergindo do mostrado.
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest` e no vitest do `ci.yml`.

## UC-SRP-02 · Resgate válido é gravado com pontos e valor conferidos `[V0]` `[must]`
- **Persona:** Operador de caixa, com o cliente pedindo para usar os pontos.
- **Aceite:** Dado cliente com 100 pontos · Quando a venda pede 50 pontos e 5,00 · Então o servidor confirma 50 e 5,00.
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-02`.
- **Regressão que defende:** Resgate legítimo recusado pela conferência nova.
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest`.

## UC-SRP-03 · Mais pontos que o disponível, abaixo do mínimo ou venda pequena são recusados `[V0]` `[must]`
- **Persona:** Operador de caixa diante de um pedido de resgate que não cabe.
- **Aceite:** Dado cliente com 30 pontos, resgate mínimo de 10 e venda mínima de 20,00 · Quando a venda pede 31 pontos, ou 5 pontos, ou 20 pontos numa venda de 19,99 · Então a gravação é recusada com "O cliente pode resgatar até 30 pontos nesta venda.", "O resgate mínimo é de 10 pontos." e "A venda não atinge o valor mínimo para resgatar pontos."
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-03`.
- **Regressão que defende:** Resgatar mais pontos do que o cliente tem (antes só o JavaScript da Blade barrava).
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest`.

## UC-SRP-04 · Valor que não bate com os pontos é recusado `[V0]` `[must]`
- **Persona:** Quem envia a venda direto ao servidor (tela desatualizada ou requisição feita à mão).
- **Aceite:** Dado cliente com 100 pontos e ponto a 0,10 · Quando a venda pede 50 pontos com valor 50,00 · Então é recusada com a mensagem de que o resgate de 50 pontos vale 5,00 (em reais). Com valor 5,01 (1 centavo) passa e grava 5,00.
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-04`.
- **Regressão que defende:** Venda gravada com desconto de pontos maior que o valor dos pontos.
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest`.

## UC-SRP-05 · Cliente padrão, cliente de outra empresa e empresa sem pontos não resgatam `[V0]` `[must]`
- **Persona:** Operador de caixa vendendo para o Cliente padrão ou de outra empresa.
- **Aceite:** Dado o Cliente padrão, ou um cliente da empresa 99, ou a empresa com os pontos desligados · Quando a venda da empresa 98 pede resgate · Então é recusada com "Escolha um cliente cadastrado para resgatar pontos." ou "Os pontos de fidelidade não estão ligados nesta empresa."
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-05`.
- **Regressão que defende:** Pontos de um cliente usados em venda de outro cliente ou de outra empresa (Tier 0).
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest`.

## UC-SRP-06 · A tela só mostra o resgate com a flag ligada e a empresa com pontos `[should]`
- **Persona:** Dono da empresa, que liga ou não o resgate no caixa.
- **Aceite:** Dado a flag `SELLS_RESGATE_PONTOS_ENABLED` desligada, ou a empresa com `enable_rp = 0` · Quando a tela de venda abre · Então não recebe configuração de resgate e o bloco não aparece; com as duas ligadas recebe nome, valor do ponto, venda mínima e resgate mínimo.
- **Teste:** `tests/Feature/Sells/ResgatePontosContratoTest.php` — `UC-SRP-06`.
- **Regressão que defende:** Bloco de resgate aparecendo na ROTA LIVRE sem a flag ligada.
- **Status: ⬜** — não verificado: aguarda o primeiro run na lane `sells-pest`.

## Duplicar a venda guardando a origem (P-035)

## UC-SDUP-01 · Duplicar cria um rascunho novo que guarda a venda de origem `[must]`
- **Persona:** vendedor que repete o pedido de um cliente (recompra).
- **Como usa:** na lista, menu ⋮ → **Duplicar**; abre o rascunho novo para editar.
- **Aceite:** Dado a V-150 · Quando duplico · Então nasce um rascunho da mesma empresa, sem pagamento, com número novo, que guarda a V-150 como origem.
- **Âncora de contrato:** pedido P-035 (backlog abaixo) · `SellController::duplicateSell`.
- **Teste:** `tests/Feature/Sells/SellsDuplicarContratoTest.php` (Pest, lane `PHP / Pest (Sells · MySQL)`) — `UC-SDUP-01 …`.
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane.

---

## UC-SDUP-02 · A cópia leva os mesmos itens e preços e não mexe no estoque `[V0]` `[must]`
- **Persona:** vendedor — o pedido repetido sai como estava.
- **Como usa:** abre o rascunho duplicado e confere os itens antes de finalizar.
- **Aceite:** Dado a origem com 3 un a 20,00 (60,00) e 10 un em estoque · Quando duplico · Então a cópia tem 3 un a 20,00 e total 60,00, linha igual à da origem, e o estoque continua 10.
- **Âncora de contrato:** **REGRA MESTRE valor/estoque** ([proibicoes.md](../../../../memory/proibicoes.md)) — dupla prova no teste (conta à mão + linhas da cópia contra as da origem). O preço vir da origem é o comportamento de hoje; a D-05 (origem ou tabela de hoje) segue em aberto para o [W].
- **Teste:** `tests/Feature/Sells/SellsDuplicarContratoTest.php` (Pest, lane `PHP / Pest (Sells · MySQL)`) — `UC-SDUP-02 …`.
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane.

---

## UC-SDUP-03 · A cópia nasce fora do pipeline FSM `[must]`
- **Persona:** quem acompanha a venda pelo pipeline.
- **Como usa:** não usa: a cópia não aparece numa etapa que não percorreu.
- **Aceite:** Dado a origem numa etapa do pipeline · Quando duplico · Então a cópia não tem processo nem etapa e a origem continua na etapa dela.
- **Âncora de contrato:** [ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) — etapa só muda pelo `ExecuteStageActionService`; antes a cópia herdava a etapa da origem sem histórico.
- **Teste:** `tests/Feature/Sells/SellsDuplicarContratoTest.php` (Pest, lane `PHP / Pest (Sells · MySQL)`) — `UC-SDUP-03 …`.
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens abaixo SEM token de UC de
> propósito até existir teste real — visíveis, não esquecidos, sem virar dívida no baseline.

- **[BACKLOG] Venda paga no ato (dinheiro/PIX) fecha sem saldo devedor** — caminho feliz com pagamento integral; exige modelar o bloco de pagamentos no harness.
- **[BACKLOG] Bloqueio por limite de crédito** — backend devolve `errors.venda` e o carrinho fica intacto (toast 8s) — exige fixture de limite no seed.

**Casos pedidos em 2026-10-23 — limite de crédito, saldo a favor e pedido de compra do cliente**

- **[BACKLOG] Venda acima do limite de crédito avisa ou bloqueia, conforme a empresa (US-CRM-081)**
  `[V0]` — detalha o item acima. Aceite: Dado um cliente com limite de 2.000,00 e 1.500,00 em aberto,
  Quando salvo uma venda a prazo de 800,00, Então com a empresa em "avisar" a venda salva e mostra
  "passa 300,00 do limite"; em "bloquear" a venda não salva, o carrinho fica como estava e a mensagem
  diz quanto passou. O que conta é o valor que fica em aberto: a parte paga no ato não usa limite, e a
  venda com 100% de adiantamento passa mesmo com o limite estourado, como no Omie. Hoje
  o sistema calcula (`TransactionUtil::isCustomerCreditLimitExeeded()`) e só avisa. Mexe em valor:
  Regra Mestre.
  - Pedido: P-010
  - Âncora: US-CRM-081
  - Fazer: Escolha por empresa (avisar ou bloquear); checar ao salvar; mensagem com o valor.
  - Hoje: part | Em parte | O sistema calcula o limite e só avisa
  - Comunicação visual: part | Cliente empresa | venda a prazo
  - Clientes: Madereira Spilere
  - Etiquetas: valor, part, cv
- **[BACKLOG] Usar o saldo a favor do cliente na venda** `[V0]` — Aceite: Dado um cliente com 120,00 a
  favor e uma venda de 500,00, Quando escolho o cliente, Então a venda já abate os 120,00 (entra um
  pagamento "Adiantamento" de 120,00, ficam 380,00 a receber e o saldo do cliente vai a 0,00), e o
  vendedor pode recusar o abatimento, voltando o saldo como estava. Nunca usa mais que o saldo nem mais
  que o total da venda (o Adiantamento já trava no saldo hoje). Mexe em valor: Regra Mestre. Fonte:
  protótipo do [W] `cliente-drawer760.jsx` ("créditos a favor, abatidos na próxima venda; o vendedor
  pode recusar"); US-VEST-021. Verificado contra as regras do [W] em 2026-10-23: o abatimento é automático, e recusar é a exceção.
  - Pedido: P-011
  - Âncora: Protótipo do drawer do cliente · US-VEST-021
  - Fazer: Abater o saldo ao escolher o cliente, com o botão de recusar; nunca passar do saldo nem do total.
  - Hoje: part | Em parte | Pagar com Adiantamento existe; a venda não oferece
  - Comunicação visual: gray | Pouco | veio do vestuário
  - Clientes: NewPrint
  - Etiquetas: valor, part
- **[BACKLOG] Número do pedido de compra do cliente** — o cliente empresa manda o próprio número de
  pedido e quer vê-lo na nota. Aceite: Dado um cliente que enviou o pedido "4500012345", Quando
  preencho "Pedido do cliente" na venda, Então o número fica gravado na venda, sai em cada OS da venda
  e vai para a NF-e e a NFS-e no campo `xPed` (o número do item do pedido em `nItemPed`, quando houver).
  Campo opcional; venda sem ele segue como hoje. Fonte: layout da NF-e (grupo do produto) e o schema
  da NFS-e nacional no módulo NfeBrasil (`xPed`).
  - Pedido: P-012
  - Âncora: NF-e e NFS-e (`xPed`, `nItemPed`)
  - Fazer: Campo novo na venda; copiar para a OS; preencher o campo da nota na emissão.
  - Hoje: miss | Não tem | 
  - Comunicação visual: part | Cliente empresa | redes e construtoras
  - Clientes: Zoom, Techpress
  - Etiquetas: miss, cv
- **[BACKLOG] Venda de serviço para cliente que retém ISS (UC-FLX-30)** `[V0]` — Aceite: Dado um
  cliente marcado no cadastro como tomador que retém ISS e uma venda com instalação de 660,00 (ISS de
  5%), Quando monto a venda, Então o resumo mostra "ISS retido previsto 33,00 · cliente paga" o total
  menos 33,00, avisando que o valor exato sai na NFS-e; o total da venda não muda. Mexe em valor: Regra
  Mestre. Fonte: US-FIN-072 (a retenção vira baixa quando a NFS-e é autorizada).
  - Pedido: P-013
  - Âncora: `UC-FLX-30` · US-FIN-072
  - Fazer: Marca "retém ISS" no cadastro do cliente; prever o retido pela alíquota do serviço; só aviso.
  - Hoje: miss | Não tem | 
  - Comunicação visual: ok | Sim | NFS-e com retenção
  - Clientes: Techpress
  - Etiquetas: valor, miss, cv
- **[BACKLOG] Quem recebe comissão: vendedor, representante, agência ou ninguém** `[V0]` — Aceite:
  Dado a venda, Quando escolho quem recebe comissão (o vendedor, um representante ou uma agência) ou
  deixo "Sem comissionado", Então a venda grava a escolha; sem comissionado, nenhuma comissão nasce no
  faturamento; com comissionado, a comissão nasce pela regra dele (UC-FLX-05). Comissionado de outra
  empresa nunca aparece na lista. Fonte: Comissionados (`sales_commission_agents`, representante
  externo).
  - Pedido: P-014
  - Âncora: `UC-FLX-05` · Comissionados
  - Fazer: Opção "Sem comissionado"; lista só da empresa; comissão pela regra de cada comissionado.
  - Hoje: part | Em parte | O cadastro de comissionados existe
  - Comunicação visual: ok | Sim | agência comum em CV
  - Clientes: Techpress
  - Etiquetas: part, cv, decisao
- **[BACKLOG] Crédito disponível ao escolher o cliente** `[V0]` — Aceite: Dado um cliente com limite
  de 2.000,00 e 1.500,00 em aberto, Quando escolho o cliente na venda, Então o resumo mostra
  "Disponível 500,00" antes de salvar; cliente sem limite não mostra nada. Fonte: DecoNetwork (crédito
  restante no orçamento e no pedido); US-CRM-081.
  - Pedido: P-027
  - Âncora: US-CRM-081 · DecoNetwork
  - Fazer: Mostrar o disponível no resumo da venda, com o mesmo cálculo do caso 16.
  - Hoje: part | Em parte | O cálculo do limite existe e só avisa ao salvar
  - Comunicação visual: part | Cliente empresa | venda a prazo
  - Clientes: Madereira Spilere
  - Etiquetas: valor, part, cv
- **[BACKLOG] A condição do cliente preenche a venda** `[V0]` — Aceite: Dado o cliente com condição
  própria de sinal de 50% e prazo de 28 dias, Quando faço uma venda para ele, Então a venda vem com
  esse sinal e esse prazo, e o vendedor pode trocar; cliente sem condição usa a da empresa. Fonte:
  DecoNetwork (condições por cliente); o caso irmão está em `Cliente/Index.casos.md`.
  - Pedido: P-026
  - Âncora: `UC-FLX-28` · DecoNetwork
  - Fazer: Ler a condição do cliente ao escolhê-lo; permitir trocar na venda.
  - Hoje: part | Em parte | A venda usa a regra da empresa
  - Comunicação visual: ok | Sim | cliente fixo com sinal diferente
  - Clientes: NewPrint, Sabor Brasil, ArtLaser
  - Etiquetas: valor, part, cv
- **[BACKLOG] Cobrar o sinal por link** `[V0]` — Aceite: Dado o sinal mínimo de 30% e uma venda
  aprovada de 1.000,00, Quando gero a cobrança do sinal, Então sai uma cobrança de 300,00 (Pix ou
  boleto) com link para o cliente, e o vendedor pode trocar o valor; paga, o retorno do banco lança o
  pagamento na venda e libera a OS (UC-PCLIB-08). Mexe em valor: Regra Mestre. Fonte: Printavo
  (pedido de pagamento com o valor escolhido); UC-FLX-28.
  - Pedido: P-028
  - Âncora: `UC-FLX-28` · `UC-PCLIB-08` · Printavo
  - Fazer: Gerar a cobrança do sinal pelo valor exigido, com o valor editável pelo vendedor.
  - Hoje: part | Em parte | O retorno do banco já libera a OS
  - Comunicação visual: ok | Sim | cobrar o sinal antes de produzir
  - Clientes: Ecopias, Fixar
  - Etiquetas: valor, part, cv
- **[BACKLOG] Duplicar abre a venda nova com a origem** — Aceite: Quando duplico a V-150, Então abre um
  rascunho com o mesmo cliente e os mesmos itens, sem pagamento, sem nota e com número novo, e o topo
  mostra "Duplicada de V-150"; a origem fica gravada na venda nova. Hoje a cópia não guarda a origem
  (`SellController::duplicateSell`, medido em 23/10/2026). Em aberto para o [W]: a cópia mantém os
  preços da origem ou recalcula pela tabela de hoje.
  - Pedido: P-035
  - Âncora: `SellController::duplicateSell`
  - Fazer: Guardar a origem ao duplicar; decidir se os preços vêm da origem ou da tabela de hoje.
  - Hoje: part | Em parte | A cópia guarda a origem (UC-SDUP-01..03); falta o topo do rascunho mostrar a origem e a D-05
  - Comunicação visual: ok | Sim | recompra do mesmo serviço
  - Clientes: Zoom, Techpress, Sabor Brasil, Display Paraná, Estilo
  - Etiquetas: part, cv, decisao
- **[BACKLOG] Venda para entrega futura** `[V0]` `[E0]` — Aceite: Dado uma venda marcada "entrega
  futura" de 1.000 folhetos, Quando faturo, Então sai a nota de simples faturamento com o valor total
  e o estoque não baixa; a cada entrega sai uma nota de remessa com a quantidade entregue, ligada à de
  faturamento, e só então o estoque baixa; nunca se entrega mais que o faturado. CFOP de exemplo:
  5.922 no faturamento, 5.116 (produção própria) ou 5.117 (revenda) na remessa; o CFOP e o destaque
  do ICMS ficam com o contador. Mexe em valor e estoque: Regra Mestre. Fonte: UC-TRB-24 do protótipo
  fiscal (falta).
  - Pedido: P-036
  - Âncora: `UC-TRB-24` · CFOP de exemplo 5.922 e 5.116/5.117
  - Fazer: Marca "entrega futura" na venda; notas de faturamento e de remessa; estoque só na remessa; o contador decide o CFOP.
  - Hoje: miss | Não tem | 
  - Comunicação visual: ok | Sim | entrega em lotes
  - Clientes: Mil Letras
  - Etiquetas: valor, miss, cv
- **[BACKLOG] Item com imposto faltando avisa ao montar a venda** — Aceite: Dado um produto sem NCM
  ou com a natureza fiscal "a decidir pelo contador", Quando o adiciono à venda, Então a linha mostra o
  que falta para a nota sair, sem impedir salvar a venda. Quem pode editar produto ganha na linha um
  "Informar NCM", que grava no produto; quem não pode vê só o aviso. Fonte: CU-PROD-18 (aviso no
  cadastro do produto) e UC-TRB-16 do protótipo fiscal ("pronto pra emitir"). Pedido da PraMoto
  (2026-10-27): os produtos comprados sem nota entram sem NCM.
  - Pedido: P-047
  - Âncora: `CU-PROD-18` · `UC-TRB-16`
  - Fazer: Levar à venda o aviso que hoje existe só no cadastro do produto, com um "Informar NCM" que grava no produto para quem pode editar produto.
  - Hoje: part | Em parte | O aviso existe só no cadastro do produto
  - Comunicação visual: ok | Sim | mercadoria × serviço em cada item
  - Clientes: PraMoto, Estilo
  - Etiquetas: part, cv
- **[BACKLOG] Cliente sem dados para a nota avisa ao ser escolhido** — Aceite: Dado um cliente sem
  CPF ou CNPJ, sem endereço com município ou sem a indicação de contribuinte do ICMS, Quando o escolho
  numa venda, Então o resumo mostra "Faltam dados para a nota" e quais, com link para completar o
  cadastro, sem impedir salvar a venda. Fonte: grupo do destinatário da NF-e.
  - Pedido: P-048
  - Âncora: Grupo do destinatário da nf-e
  - Fazer: Aviso no resumo da venda ao escolher o cliente.
  - Hoje: miss | Não tem | 
  - Comunicação visual: part | Cliente empresa | nota para empresa
  - Clientes: Techpress, PraMoto
  - Etiquetas: miss, cv
- **[BACKLOG] Instrução de execução no item da venda** (pedido de 2026-10-23) — Aceite: Dado uma
  venda com o item "Fachada em ACM 3 x 1 m", Quando escrevo no item "instalar no 2º andar, cliente
  libera o acesso às 8h", Então a instrução fica gravada no item, sai na ficha da OS e aparece para
  quem executa na produção. Item sem instrução não mostra nada. Hoje o Blade grava a nota do item
  (`sell_line_note`); a venda React não tem o campo. A produção mostra a instrução
  (Producao/Apontamento/Index.casos.md, mesmo pedido).
  - Pedido: P-057
  - Âncora: Nota do item do Blade (`sell_line_note`) · `Sells/Create.casos.md`
  - Fazer: Campo de instrução em cada item da venda, levado à OS e à ficha impressa.
  - Hoje: part | Em parte | O Blade grava a nota do item; a venda nova não tem o campo
  - Comunicação visual: ok | Sim | instalação e acabamento
  - Clientes: Extreme, Zoom, Max Comunicação
  - Etiquetas: part, cv
- **[BACKLOG] Venda condicional** (pedido de 2026-10-23) `[V0]` `[E0]` — Aceite: Dado uma venda
  marcada "condicional" com 5 peças, Quando o cliente leva as peças, Então elas saem do estoque
  disponível, a venda fica "Condicional em aberto" e não gera conta a receber nem nota de venda; Quando
  o cliente devolve 3 peças e fica com 2, Então as 3 voltam ao estoque e a venda vira venda normal das 2
  peças, com valor e conta a receber só delas. Mexe em valor e estoque: Regra Mestre. A nota de
  remessa, se houver, é decisão do contador.
  - Pedido: P-059
  - Âncora: `Sells/Create.casos.md`
  - Fazer: Tipo "Condicional" na venda, com retorno das peças e conversão no que ficou. A nota de remessa, se houver, o contador decide.
  - Hoje: miss | Não tem | Não há venda condicional
  - Comunicação visual: gray | Pouco | é do vestuário
  - Clientes: Rota Livre
  - Etiquetas: valor, miss
- **[BACKLOG] Funcionário que executou o serviço, por item** (pedido de 2026-10-23) — Aceite: Dado uma
  venda com os serviços "troca de óleo" e "alinhamento", Quando indico quem executou cada um, Então o
  nome fica gravado no item e aparece na consulta da venda e na comissão de quem executou. Hoje o
  backend grava `res_service_staff_id` por item (vindo do Blade de restaurante); a venda React não tem
  o campo. A OS tem o caso irmão (Repair/JobSheet/Create.casos.md).
  - Pedido: P-060
  - Âncora: `res_service_staff_id` por item · `Sells/Create.casos.md`
  - Fazer: Campo "Executado por" em cada item de serviço da venda.
  - Hoje: part | Em parte | O backend grava o funcionário por item; a venda nova não tem o campo
  - Comunicação visual: ok | Sim | instalador de cada serviço
  - Clientes: Global Pneus, PraMoto, Mecânica Casa Grande
  - Etiquetas: part, cv, decisao
- **[BACKLOG] Modelo e série do aparelho na venda da pilha** (pedido de 2026-10-23) — Aceite: Dado um
  produto do tipo pilha ou bateria, Quando o adiciono à venda, Então informo o modelo e o número de
  série do aparelho onde ela vai; os dois saem na consulta da venda e na garantia. Produto de outro
  tipo não pede nada. O cadastro do produto diz quais modelos usam a pilha
  (Produto/Index.casos.md, mesmo pedido).
  - Pedido: P-063
  - Âncora: `Sells/Create.casos.md`
  - Fazer: Campos de modelo e série do aparelho no item, só para produto do tipo pilha.
  - Hoje: miss | Não tem | Não há o campo
  - Comunicação visual: gray | Pouco | não é do ramo
  - Etiquetas: miss
- **[BACKLOG] Montar o kit dentro da venda** (pedido de 2026-10-23) `[V0]` `[E0]` — Aceite: Dado o
  kit "Cortina completa", Quando o adiciono à venda e escolho os componentes (tecido 4,2 m, trilho 2 m e
  2 suportes), Então a linha mostra o kit com os componentes e as quantidades, o preço do kit é o da
  tabela ou a soma dos componentes, conforme o cadastro do kit, e a baixa de estoque sai de cada
  componente. Trocar um componente refaz o preço e a baixa. Mexe em valor e estoque: Regra Mestre.
  Hoje o produto do tipo combo (kit) e a composição existem no cadastro
  (`memory/requisitos/Produto/_telas/bom-combo.casos.md`); a busca da venda reconhece o tipo `combo`,
  mas não monta os componentes na venda.
  - Pedido: P-066
  - Âncora: Combo e composição do cadastro (`bom-combo.casos.md`) · `Sells/Create.casos.md`
  - Fazer: Montar os componentes do kit na linha da venda, com preço e baixa por componente.
  - Hoje: part | Em parte | O cadastro tem o kit e a composição; a venda não monta os componentes
  - Comunicação visual: ok | Sim | placa com suporte e fixação
  - Clientes: Lessa Cortinas, Display Paraná, MidiaOff
  - Etiquetas: valor, part, cv
- **[BACKLOG] A tabela de preço do cliente preenche os preços** (pedido de 2026-10-23) `[V0]` — Aceite:
  Dado um cliente ligado à tabela "Atacado", Quando o escolho na venda, Então os itens passam a usar os
  preços dessa tabela, e o vendedor vê qual tabela está valendo; cliente sem tabela usa a padrão. Hoje
  isso já funciona pelo grupo do cliente (o grupo aponta a tabela e a venda reprecifica ao escolher o
  cliente), mas não há tabela no próprio cliente: um cliente com preço só dele precisa de um grupo só
  dele. Sem caso nem teste até agora.
  - Pedido: P-087
  - Âncora: Grupo do cliente (`Cliente/Grupos`) · `Sells/Create.casos.md`
  - Fazer: Permitir a tabela no próprio cliente, além do grupo, e mostrar na venda qual está valendo.
  - Hoje: part | Em parte | Funciona pelo grupo do cliente; não há tabela no próprio cliente
  - Comunicação visual: ok | Sim | preço combinado por cliente
  - Clientes: Sabor Brasil
  - Etiquetas: valor, part, cv
- **[BACKLOG] A tabela de preço segue o canal de venda** (pedido de 2026-10-23) `[V0]` — Aceite: Dado as
  tabelas Varejo e Atacado, Quando escolho o canal "Atacado", Então os itens usam a tabela de
  atacado, e trocar o canal refaz os preços dos itens já lançados. Hoje a venda tem o seletor de tabela
  de preço (`price_group_id`), mas não um canal que escolha a tabela.
  Chamado de canal, e não de tipo de venda, porque o [W] já usa "tipo de venda" para balcão, reparo e
  OS (decisão de 2026-10-01). Verificado contra as regras do [W] em 2026-10-23.
  - Pedido: P-088
  - Âncora: Seletor de tabela da venda (`price_group_id`) · `Sells/Create.casos.md`
  - Fazer: Canal de venda (varejo, atacado) ligado a uma tabela de preço, escolhido na venda. Não chamar de "tipo de venda": o Wagner usa esse nome para balcão, reparo e OS.
  - Hoje: part | Em parte | A venda tem o seletor de tabela, não um canal de venda
  - Comunicação visual: ok | Sim | revenda e consumidor final
  - Clientes: Martinho, ArtLaser, Medeiros Produto de Limpeza
  - Etiquetas: valor, part, cv
- **[BACKLOG] Consignação para revendedor** (comparação com concorrentes, 2026-10-23) `[V0]` `[E0]` —
  Aceite: Dado um revendedor que recebe 20 placas em consignação, Quando faço a remessa, Então as 20
  saem do estoque próprio e ficam como "em poder de terceiros", sem conta a receber; Quando ele presta
  contas de 12 vendidas e devolve 8, Então nasce a venda das 12 com a conta a receber, as 8 voltam ao
  estoque e o saldo em consignação zera. Mexe em valor e estoque: Regra Mestre. Os CFOP da remessa e
  do retorno são decisão do contador. Fonte: Omie (consignação, com estoque de terceiros e devolução
  simbólica). Hoje não existe; a venda condicional (acima) é para o consumidor final.
  - Pedido: P-094
  - Âncora: Omie (consignação com estoque de terceiros) · `Sells/Create.casos.md`
  - Fazer: Remessa em consignação, saldo por revendedor e prestação de contas. O contador decide os CFOP.
  - Hoje: miss | Não tem | Não existe; a venda condicional (caso 59) é para o consumidor
  - Comunicação visual: gray | Pouco | revenda em consignação
  - Etiquetas: valor, miss
- **[BACKLOG] Tabela de preço com validade e por forma de pagamento, vendedor ou região** (comparação
  com concorrentes, 2026-10-23) `[V0]` — Aceite: Dado a tabela "Promoção de outubro" válida de 01/10 a
  31/10 e ligada à forma de pagamento Pix, Quando faço uma venda em 15/10 com Pix, Então os itens usam
  essa tabela; em 01/11, ou com outra forma de pagamento, voltam à tabela que valia antes. O mesmo vale
  para a tabela ligada a um vendedor, a uma região ou a uma loja. Fonte: Bling (lista de preços com
  vigência, ligada a contato, vendedor, forma de pagamento, região ou loja). Hoje a tabela vem do grupo
  do cliente ou do seletor da venda, sem validade nem esses vínculos.
  - Pedido: P-095
  - Âncora: Bling (lista de preços com vigência e vínculos) · `Sells/Create.casos.md`
  - Fazer: Validade na tabela de preço e vínculo com forma de pagamento, vendedor, região e loja.
  - Hoje: part | Em parte | A tabela vem do grupo do cliente ou do seletor da venda, sem validade nem esses vínculos
  - Comunicação visual: ok | Sim | preço à vista e a prazo
  - Etiquetas: valor, part, cv
- **[BACKLOG] Emissão NF-e da venda (homolog/stub SEFAZ)** — wire já existe (suítes NfeBrasil); espelhar como UC quando o fluxo de emissão entrar no harness e2e.
- **[BACKLOG] Fechar o diálogo de rascunho preserva a venda montada** — Dado um rascunho salvo · Quando o operador fecha o diálogo de recuperação SEM escolher (Esc, clique fora) · Então o rascunho **continua** no `localStorage` e a pergunta volta na próxima montagem; só o botão "Descartar" apaga. Exige exercitar `AlertDialog` do Radix no harness e2e (incluindo se `Esc` de fato fecha nesta versão, que não foi verificado). Origem: ROTA LIVRE perdeu venda montada durante janela de 503 do deploy, 2026-08-26.

## Decisões pendentes [W]

Perguntas do documento "Casos de uso por tela" que só o [W] responde. Cada uma diz quais pedidos ela destrava.

- **[DECISÃO] D-05 · Ao duplicar, os preços vêm da venda de origem ou da tabela de hoje?**
  - Estado: Em aberto
  - Opção: Preço da origem — É o que a cópia faz hoje: copia os itens como estavam. Bom para repetir um pedido negociado.
  - Opção: Tabela de hoje — Recalcula pelo preço atual. Evita vender abaixo do custo quando o material subiu.
  - Destrava: P-035, P-037
- **[DECISÃO] D-06 · Quem confirma os CFOP das remessas e da entrega futura?**
  - Estado: Em aberto
  - Texto: Os casos citam CFOP só como exemplo (5.915 e 1.916 no conserto, 5.949 e 1.949 na garantia, 5.922 e 5.116 ou 5.117 na entrega futura). A nota de entrada de bem usado comprado de pessoa física (caso 101) entra na mesma pergunta. Quem decide é o contador, como já acontece na natureza fiscal do produto.
  - Destrava: P-036, P-101
  - Ligação: também destrava P-041, P-042, P-043, que ainda não está no git.

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate, gate de PR desde Onda Q1) — vereditos viram manifesto via `npm run casos:results`.
2. **Cadência:** rodar ao fim de toda mexida em Sells/Create. UC ❌ = regressão → lição + conserto antes de seguir.

## Pedidos de clientes

> Pedidos de clientes registrados com `node scripts/casos-backlog/novo.mjs`. Cada um aparece na
> página "Casos de uso por tela"; quando ganhar teste, vira caso de uso com id e a linha `Pedido de origem`.

- `[BACKLOG]` **O ICMS-ST de cada item sai certo para o supermercado em SP** `[V0]` — Dado um tempero com CST
  10, MVA de 62,3% e alíquota interna de 18%, e o cliente supermercado contribuinte em SP, Quando ele entra na
  venda, Então a venda calcula o ST do item e soma ao total, com o mesmo valor que vai na nota. O tempero com
  CST 20 entra com a base reduzida e sem ST, e a venda pela empresa do Simples (CSOSN 500, ST já retido) não
  calcula ST. Hoje o Sabor Brasil emite assim pelo Delphi: 215 itens com ST desde 2024, todos com o mesmo MVA
  e alíquota. Mexe em valor: Regra Mestre. Pedido de Sabor Brasil. Mexe em valor ou estoque: Regra Mestre.
  - Pedido: P-153
  - Âncora: US-SELL-078 · seção ICMS-ST deste arquivo
  - Fazer: Conferir os três cenários do cliente (CST 10 com MVA, CST 20 com redução, CSOSN 500) contra o cálculo da US-SELL-078 antes da virada.
  - Hoje: part | Em parte | Dentro do estado, para contribuinte, o ST sai na nota e no total da venda (#10162): no `main` de 2026-10-29 o `NfeStDentroDoEstadoTest` passou 5 de 5 (lane NfeBrasil, run 40956167311) e o UC-VST-06 passou (lane Sells, run 40951464966). Falta conferir os outros dois cenários do cliente: CST 20 com base reduzida e CSOSN 500
  - Clientes: Sabor Brasil
  - Etiquetas: valor, part

- `[BACKLOG]` **Bonificação sai como nota de bonificação, sem cobrança** `[V0]` `[E0]` — Dado uma venda
  marcada como bonificação para um supermercado, Quando ela é finalizada, Então a nota sai com CFOP 5910,
  nenhum título nasce no Financeiro e o estoque baixa como numa venda. O Sabor Brasil fez 12 bonificações
  desde 2024 pelo Delphi. Mexe em valor e estoque: Regra Mestre. Pedido de Sabor Brasil. Mexe em valor ou
  estoque: Regra Mestre.
  - Pedido: P-154
  - Âncora: Delphi: VENDA_TIPO BONIFICACAO e BONIFICAÇÃO SEM ST · CFOP 5910
  - Fazer: Tipo de operação bonificação na venda, com CFOP próprio, sem título a receber e com baixa de estoque.
  - Hoje: miss | Não tem | A venda não tem o tipo bonificação; só o módulo de marketplaces conhece o CFOP
  - Clientes: Sabor Brasil
  - Etiquetas: valor, miss

## Trilha do tempo
- 2026-10-29 · [W+C] **UC-SDUP-01..03** (P-035): a cópia guarda a origem, leva itens e preços da origem sem mexer no estoque e nasce fora do pipeline FSM. Falta o topo do rascunho e a D-05. Teste `SellsDuplicarContratoTest.php`.
- 2026-10-28 · [W+C] Decisões pendentes D-05, D-06 do documento "Casos de uso por tela", sem id de caso.
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S01 venda a prazo + spec Playwright `sells-venda-balcao.spec.ts`; produto E2E-0001 entrou no VisregTenantSeeder (enable_stock=0).
- 2026-06-18 · [CC] refactor só-de-layout (Wagner): total de itens no rodapé do card Produtos + card de desconto (Resumo) movido pra antes do Pagamento. Sem mudança de comportamento — UC-S01 baixado pra 🧪 até re-rodar o e2e (G-7 frescor).
- 2026-08-26 · [CC] Fechar o diálogo de recuperação parou de apagar o rascunho (o `onOpenChange` chamava `handleDraftDiscard()` → `localStorage.removeItem()`, irreversível; agora só o botão "Descartar" apaga). Mudança de COMPORTAMENTO, não de layout. UC-S01 e UC-S02 revalidados por medição de alcance (0 ocorrências do diálogo nos testes de ambos; controle positivo 55 no `.tsx`) — nenhum dos dois é atingido, então nenhum foi rebaixado. Comportamento novo entrou no Backlog sem id (G-2) até haver teste que o exercite. Origem: ROTA LIVRE perdendo venda montada nas janelas de 503 do deploy.
- 2026-10-01 · [CL] UC-S03 (reparo como tipo de venda, onda 1): o envio passou a carregar `sub_type` quando o PDV é aberto como reparo; antes a venda de reparo pelo React gravava como venda comum. Medido em prod: 6 vendas de reparo no total, todas do biz=1, a última em 2023-10-11 — defeito latente, sem dado real perdido. UC-S01/S02 não são atingidos (venda comum envia exatamente o mesmo payload de antes).
- 2026-10-02 · [CL] UC-S04, preço de grupo 0 alinhado ao Blade. A anotação de 2026-10-01 ("o Blade trata 0 como sem preço") estava incompleta: medido no MySQL, o Blade usa o fixo 0 e ignora só o percentual 0. O `filterProduct` passou a devolver NULL no percentual que dá 0. Impacto em prod: nenhum (os 3305 preços de grupo são todos fixos).
- 2026-10-01 · [CL] UC-S04: o autocomplete passou a mandar `price_group`, e o produto adicionado com grupo definido entra pelo preço do grupo, como no POS Blade. Antes, só a troca de grupo reprecificava, e só as linhas que já estavam no carrinho. Medido em prod: nenhuma venda com grupo de preço desde 2026-05-27 (a coluna é gravada: 90 vendas com grupo de 2021 a 2026-04-24, um único business) — defeito latente, nenhum valor gravado muda.
- 2026-07-02 · [CC] Onda 1.4 (dente de cálculo): UC-S02 declarado com teste no MESMO PR (coordenação 1.3 ↔ 1.4, regra "declarar UC + teste = 1 PR"). Property `num_uf(num_f(x))==x` + golden no totalizador real `calculateInvoiceTotal` (227,90 − 10,05% = 204.99605, não infla) + discriminação RED vs strip-do-ponto + caracterização da divergência `getTotalPaid`(líquido) ≠ `getTotalAmountPaid`(bruto). TEST-ONLY — nenhum método de cálculo alterado.
- 2026-10-23 · [M+C] Backlog de limite de crédito (avisar ou bloquear), saldo a favor na venda e pedido de compra do cliente, sem id.
- 2026-10-23 · [M+C] Backlog de ISS retido previsto e de quem recebe comissão, sem id.
- 2026-10-23 · [M+C] Backlog da comparação com concorrentes: crédito disponível, condição do cliente e cobrança do sinal por link, sem id; o caso do limite ganhou o aceite do adiantamento (Omie).
- 2026-10-23 · [M+C] Backlog de duplicar com a origem e de venda para entrega futura, sem id.
- 2026-10-23 · [M+C] Backlog: aviso de imposto faltando no item e de dados do cliente faltando para a nota, sem id.
- 2026-10-23 · [M+C] Revalidado (G-6): as mudanças em `Create.tsx` depois do last_run 2026-10-02 são a venda de reparo (#8426, #8439 e #8447), descritas pelos UC-S05, S06 e S07 desta tela. Nenhum UC foi reexecutado; last_run 2026-10-02 → 2026-10-23.
- 2026-10-27 · [W+C] Revalidado (G-6): a mudança em `Create.tsx` depois do last_run 2026-10-26 é o resgate de pontos no caixa (US-SELL-079), descrito pelos UC-SRP desta tela. Nenhum outro UC muda de status; last_run 2026-10-26 → 2026-10-27.
- 2026-10-27 · [F+C] Backlog "Item com imposto faltando": o "Informar NCM" na linha da venda, pedido da PraMoto. Sem id.
- 2026-10-29 · [W+C] Pedido P-153 "O ICMS-ST de cada item sai certo para o supermercado em SP" (Sabor Brasil), registrado com casos-backlog/novo.mjs. Sem id de UC.
- 2026-10-29 · [W+C] Pedido P-154 "Bonificação sai como nota de bonificação, sem cobrança" (Sabor Brasil), registrado com casos-backlog/novo.mjs. Sem id de UC.
