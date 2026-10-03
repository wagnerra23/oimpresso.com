---
id: resources-js-pages-sells-edit-casos
casos: Editar venda · /sells/{id}/edit
irmaos: Edit.charter.md (lei) · tests/Feature/Sells/SellsEditContratoTest.php (defesa)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-02"
---

<!-- REVALIDAÇÃO 2026-10-02 (G-6: o .tsx mudou — UC-SEDIT-09, conserto da edição React).
     Mudanças no .tsx: pré-fill pelo preço antes do desconto, subtotal na regra do servidor
     (desconto fixo por unidade) e payload pro SellPosController@update. Revalidação por alcance:
     UC-SEDIT-01..07 exercem o GET do SellController@edit (guards, defer, leitura pura, payload do
     form); deles, este PR só muda `urls.submit` (/sells → /pos), que nenhum dos 7 asserta —
     status mantidos. UC-SEDIT-08 (paliativo) segue ativo.
     UC-SEDIT-09 nasce 🧪. -->
<!-- REVALIDAÇÃO 2026-10-02 (alerta "Cliente vencido"): SellController@edit passa a mandar
     `customer.dues_total` a partir do número cru da dívida, não do texto pt-BR re-parseado. Só o
     campo `customer.dues_total` do payload muda; UC-SEDIT-01..09 não o assertam — status mantidos.
     UC-SEDIT-10 nasce 🧪. -->
<!-- REVALIDAÇÃO 2026-10-02 (paliativo removido): SellController@edit deixa de devolver 409 na
     navegação React. Muda só o UC-SEDIT-08 (aceite reescrito, volta a 🧪); UC-SEDIT-05/06/07 e o
     prefill passam a pedir /sells/{id}/edit sem ?react=1 — mesmo payload, status mantidos. -->
<!-- REVALIDAÇÃO 2026-10-02 (G-6: o .tsx mudou — UC-SEDIT-12, seção Reparo). O .tsx ganhou a
     seção Reparo e os campos repair_* no envio, só quando o `form` traz `reparo` (venda de
     reparo). Venda comum: `reparo` = null, nada muda no que a tela mostra nem no que envia —
     UC-SEDIT-01..11 seguem com os status que tinham. UC-SEDIT-12 nasce 🧪. -->

# Casos de Uso & Aceite — Editar venda

> Tela que **move dinheiro e estoque no mesmo request** (a venda editada regrava `final_total`,
> linhas e pagamentos) — logo vive sob a **REGRA MESTRE valor/estoque** de
> [`memory/proibicoes.md`](../../../../memory/proibicoes.md). Está **em uso em produção**
> (`route-hits:14hit@2026-08-22`) e até aqui não tinha `casos.md` nem um único teste de
> **comportamento**: os arquivos que pareciam cobri-la — `Wave1EditBaselineTest`,
> `Wave1EditInertiaTest`, `SellsEditCoworkTest`, `SellsEditParkingLotP1P2P3Test`,
> `CommissionSplitEditorTest` — são **estruturais** (leem o `.tsx`/Controller com
> `file_get_contents` e casam string). Provam que o código está ESCRITO; nenhum prova que a
> resposta HTTP faz o que o charter promete.
>
> Fecha o item **2 do §10 Roadmap** do [`SDD-tela-venda-v1.0.md`](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md)
> ("Contrato das 6 telas sem `casos.md`"), na mesma forma já validada em `Show.casos.md`.
>
> **Status:** ✅ passa (com prova no manifesto G-7) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> **De onde os casos saem (ordem de fonte, [`memory/how-trabalhar.md`](../../../../memory/how-trabalhar.md)):**
> `Edit.charter.md` §Goals/§Non-Goals/§UX Targets · [`RUNBOOK-edit.md`](../../../../memory/requisitos/Sells/RUNBOOK-edit.md)
> §2 pré-condições / §5 estados / §9 DoD / §10 pegadinhas · `SDD-tela-venda-v1.0.md` §3.1 Tier 0
> e §3.2 (o incidente `num_uf`, que é contrato de não-regressão) · ADR 0093 · ADR 0143 · ADR 0358.
> O `SellController@edit` (`:2636`) foi lido só pra **confirmar** — nenhum caso deriva dele
> (teste derivado do código é tautológico, `memory/proibicoes.md` §5 2026-06-05).
>
> **Tenant:** fictício **98** ([ADR 0358](../../../../memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) —
> `biz=4` (ROTA LIVRE) é proibido em teste, fixture ou exemplo.

---

## UC-SEDIT-01 · Editar uma venda que não é da minha empresa não abre nada
- **Persona:** qualquer operador — o isolamento entre empresas não pode depender de ninguém "não digitar o id errado". Aqui o risco é maior que na leitura: quem abre o editor recebe o payload inteiro da venda alheia (linhas, preços, cliente, pagamentos).
- **Aceite:** Dado uma venda de OUTRO business, dentro do prazo de edição · Quando abro `/sells/{id}/edit` · Então recebo 404 e nenhum dado da venda alheia viaja no payload.
- **Âncora:** `Edit.charter.md` §Non-Goals ("❌ Edição de venda doutra biz (Tier 0 firstOrFail → 404)") + `RUNBOOK-edit.md` §10 ("NÃO usar `Transaction::find($id)` sem scope `business_id` — Tier 0 viola") + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` — lane `sells-pest.yml` (MySQL real, tenant 98 vs o 2º business semeado).
- **Status: 🧪** — o arquivo **nunca foi executado** (Pest só roda no CI/CT 100 · ADR 0062). Sobe a ✅ quando o manifesto G-7 for regravado a partir de um run VERDE de `main`. Sem fingir prova.

---

## UC-SEDIT-02 · Quem não tem direito de alterar venda não abre o editor
- **Persona:** Larissa configurando perfis — um usuário de estoque/produção até consulta a venda, mas não pode chegar na tela que reescreve preço e quantidade.
- **Aceite:** Dado um usuário do MESMO business sem `direct_sell.update` e sem `so.update` · Quando abro `/sells/{id}/edit` · Então o editor **não** é entregue (403 do gate, ou 302/401 da camada de auth) — nunca 200.
- **Âncora:** `RUNBOOK-edit.md` §2 pré-condições ("Permissão `direct_sell.update` OU `so.update`") + §9 DoD ("Permission gate `direct_sell.update`/`so.update`").
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` — cria user próprio, **sem** a role `Admin#{biz}` (senão o `Gate::before` do `AuthServiceProvider:41` liberaria tudo e o caso mediria o cenário errado).
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-03 · Venda que já teve devolução não pode mais ser editada
- **Persona:** Wagner/Kamila na retaguarda — com a devolução já registrada, mexer na venda de origem descasa o estoque e o valor devolvidos do documento que os gerou.
- **Aceite:** Dado uma venda que tem uma devolução associada (`return_parent_id`) · Quando abro `/sells/{id}/edit` pelo caminho Inertia · Então recebo **422** com a mensagem de bloqueio, e **não** o formulário.
- **Âncora:** `Edit.charter.md` §Non-Goals ("❌ Edição de venda com return associada → backend 422") + §Endpoints alimentadores (`GET /sells/{id}/edit` se `return_exist` → 422 JSON) + `RUNBOOK-edit.md` §10 e §5 (estado `bloqueado return_exist`).
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` — a devolução é semeada por INSERT (fato independente), não pelo fluxo sob teste.
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-04 · Passado o prazo de edição, a venda trava
- **Persona:** Wagner definindo `transaction_edit_days` — venda antiga é documento fechado; reabrir depois do prazo bagunça fechamento de caixa e apuração.
- **Aceite:** Dado `transaction_edit_days = 30` e uma venda de 90 dias atrás · Quando abro `/sells/{id}/edit` pelo caminho Inertia · Então recebo **422** com a mensagem de prazo — e uma venda equivalente com data de hoje abre normalmente (200).
- **Âncora:** `Edit.charter.md` §Non-Goals ("❌ Edição após `transaction_edit_days` expirar → backend 422") + `RUNBOOK-edit.md` §5 (estado `bloqueado edit_days`) + §9 DoD.
- **Por que as DUAS metades:** só asserir o 422 passaria se alguém travasse a tela para **todo mundo**. A segunda metade prova que o bloqueio é do prazo, não da tela.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php`.
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-05 · O editor abre rápido: o formulário pesado vem depois
- **Persona:** Larissa no monitor de 1280px — clica em editar e o cabeçalho da venda aparece na hora; produtos, pagamentos e dropdowns chegam em seguida.
- **Aceite:** Dado a venda carregando · Quando o primeiro response chega · Então ele traz `headline`, `permissions` e `urls` mas **não** traz `form`; e quando o front pede `form` (o `<Deferred data="form">`), ele chega com as linhas da venda.
- **Âncora:** `Edit.charter.md` §Goals ("Form deferred via `Inertia::defer()`" + o wrap `<Deferred data="form" fallback={FormSkeleton}>`) + §UX Targets (p95 first-paint < 800ms) + `RUNBOOK-edit.md` §9 DoD ("Defer payload + Deferred wrap frontend") + [RUNBOOK-inertia-defer-pattern](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md).
- **Por que as DUAS metades:** só asserir "`form` ausente" passaria se alguém simplesmente **deletasse** a prop. A segunda metade prova que o dado existe e chega quando pedido.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php`.
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-06 · Abrir o editor não altera a venda
- **Persona:** Wagner auditando — abrir a tela de edição e desistir não pode mexer em valor, em pagamento, nem fazer o pipeline andar sozinho.
- **Aceite:** Dado uma venda em qualquer estágio · Quando abro `/sells/{id}/edit` e a tela renderiza (inclusive o payload deferido) · Então `updated_at`, `final_total`, `payment_status` e `current_stage_id` continuam idênticos.
- **Âncora:** `Edit.charter.md` §Goals ("FSM safety: NUNCA setar `current_stage_id` no useForm") + §Non-Goals ("❌ Mudança de status pra cancelled/completed direto — FSM via ActionPanel") + `RUNBOOK-edit.md` §4 e §10 + [ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) (só `ExecuteStageActionService` transiciona) + REGRA MESTRE valor/estoque.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` — pré-condição anti-vácuo confirma que a request percorreu o caminho inteiro (200 + `component` + `headline`) antes de afirmar "nada mudou".
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-07 · Os valores que o editor pré-preenche são os do banco
- **Persona:** Larissa corrigindo uma venda — o que aparece no formulário tem que ser o que está gravado. Se o pré-fill mente, ela "corrige" em cima de número errado e **salva o número errado**.
- **Aceite:** Dado uma venda com quantidade e preço unitário conhecidos (a fixture do teste é a fonte — valor literal não vem pro git, `memory/proibicoes.md` Tier 0) · Quando o payload `form` chega · Então `transaction.final_total`, a quantidade e o preço unitário da linha batem **exatamente** com o que está no banco, sem cair em fallback nem em arredondamento.
- **Âncora:** REGRA MESTRE valor/estoque ([`proibicoes.md`](../../../../memory/proibicoes.md)) + `SDD-tela-venda-v1.0.md` §3.2 (incidente `num_uf` 2026-06-05: `final_total` inflado ~×100.000 em 16 vendas de `biz=4`) + `Edit.charter.md` §Goals ("Form deferred ... pre-fill aguarda payload pesado") + o incidente de pré-fill "venda em branco" reportado em prod (2026-06-10).
- **Divisão de trabalho (para não duplicar régua):** o [`SellsEditPrefillContractTest`](../../../../tests/Feature/Sells/SellsEditPrefillContractTest.php) já congela a **FORMA** do payload (aliases flat, lista sequencial, linha filha excluída). Este caso cobre outra propriedade — o **VALOR** que chega nesses campos. São contratos diferentes sobre o mesmo payload.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` — estado inicial semeado por INSERT (não pelo fluxo sob teste), tolerância de centavo pelo `decimal(22,4)`.
- **Status: 🧪** — mesma condição do UC-SEDIT-01.

---

## UC-SEDIT-08 · Editar venda abre a tela React (paliativo de 2026-10-01 removido em 2026-10-02)
- **Persona:** Larissa corrigindo uma venda pela lista React.
- **Por que existe:** a tela React de edição **não salva** — o "Salvar" envia `PUT /sells/{id}`, e o `SellController` não tem `update` (500; 4 ocorrências no log de prod, todas biz=4: 03/07, 28/07 ×2, 29/08). Além disso a tela lê o preço da linha **já com desconto** e reaplica o desconto: corrigida só a rota, salvar sem mexer mudaria o valor da venda. Decisão [W] 2026-10-01: até o conserto completo, a edição abre o **Blade**, que salva pelo `SellPosController@update`.
- **Aceite (desde 2026-10-02):** Dado uma venda editável · Quando a navegação React pede `/sells/{id}/edit` · Então a resposta é **200 com o componente `Sells/Edit`**, sem `X-Inertia-Location` · E a visita de página inteira (link aberto direto) continua devolvendo a view `sell.edit`. As checagens de permissão, prazo, devolução e business (UC-SEDIT-01..04) seguem valendo nos dois caminhos.
- **Histórico:** de 2026-10-01 a 2026-10-02 a navegação React recebia **409 com `X-Inertia-Location`** (paliativo) e a tela React só abria com `?react=1`. Removido por [W] depois do conserto do UC-SEDIT-09 (a tela salva pelo `SellPosController@update` e salvar sem mexer não muda o valor).
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` (UC-SEDIT-08).
- **Status: 🧪** _(nasce sem run.)_

---

## UC-SEDIT-09 · A edição React salva — e salvar sem mexer não muda nada
- **Persona:** Larissa corrigindo quantidade, preço ou desconto de uma venda.
- **Por que existe:** a tela React nunca salvou em prod (UC-SEDIT-08) e, corrigida só a rota, mudaria o valor ao salvar sem alteração. Decisão [W] 2026-10-02 (opção B): conserto completo, com o paliativo do UC-SEDIT-08 mantido até a validação e removido por [W] em 2026-10-02.
- **Regras (VALOR — REGRA MESTRE):**
  - o "Salvar" envia para `PUT /pos/{id}` = `SellPosController@update`, o caminho do form Blade;
  - o pré-fill usa o preço **antes** do desconto (`unit_price_before_discount`), e o desconto **fixo é por unidade**, como o servidor aplica (rótulo `R$/un`);
  - linha **não mexida** volta com os valores **gravados**, intactos — vendas da tela de criação React gravam `unit_price_inc_tax` sem o desconto da linha, e recalcular "consertaria" a linha em silêncio;
  - o `final_total` é do **servidor**, **por diferença**: resíduo = total gravado − F(linhas e desconto gravados); total novo = F(linhas e desconto enviados) + resíduo. Salvar sem mudar devolve **exatamente** o total gravado; o resíduo carrega o que a tela não edita (embalagem, despesas, arredondamento, pontos);
  - todo número vai como texto pt-BR com 4 casas (`1,1250`): com ponto e 3 casas o `num_uf` leria milhar (incidente 2026-06-05);
  - a data aceita o ISO cru que a tela manda (`uf_datetime_input`).
- **Aceite:** Dado uma venda com linhas a 10% e 5 fixo/un, uma linha inconsistente (inc sem desconto), desconto de 10% no pedido e total gravado **210,1234** · Quando salva pela tela React sem mexer · Então total, linhas e estoque ficam **idênticos** · Quando muda a quantidade de uma linha de 2 para 3 (preço com desconto 90) · Então o total vira **291,1234** (= 210,1234 + 90 × 0,9) e o estoque daquela linha cai 1, os outros intactos · E a página entrega `urls.submit` = `/pos/{id}`.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` (UC-SEDIT-09, lane `sells-pest`) + `tests/js/sells-edicao-venda.test.ts` (regras da tela, lane `sells-v3-dominio-gate`).
- **Status: 🧪** _(nasce sem run.)_

---

## UC-SEDIT-10 · O alerta "Cliente vencido" mostra a dívida no valor certo
- **Persona:** Larissa abrindo uma venda pra corrigir — o aviso de que o cliente deve dinheiro tem que trazer o mesmo valor que o Blade e o extrato mostram. Um aviso 100× maior faz ela cobrar errado ou desconfiar da tela.
- **Por que existe:** smoke em produção 2026-10-02 (biz=1): a tela React mostrava 100× a dívida que o Blade mostrava na mesma venda. O payload re-parseava o texto já formatado em pt-BR, e a vírgula decimal sumia.
- **Aceite:** Dado um cliente cuja única venda está em aberto · Quando o payload `form` chega · Então `customer.dues_total` é igual à dívida calculada por `Util::getContactDue` (a mesma conta do Blade), nos casos **500,00**, **1.234,56** (com separador de milhar) e **0** (venda quitada — sem alerta).
- **Âncora:** REGRA MESTRE valor ([`proibicoes.md`](../../../../memory/proibicoes.md) — parsing de número pt-BR, separador de milhar tem 3 dígitos) + `SDD-tela-venda-v1.0.md` §3.2 (incidente `num_uf`) + paridade com o Blade `sell.edit` (`customerDue`).
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` (UC-SEDIT-10, dataset com os 3 casos) — contato próprio criado no teste, dívida conferida antes pela mesma função do Blade.
- **Status: 🧪** _(nasce sem run.)_

---

## UC-SEDIT-11 · Editar uma venda de reparo pela lista não apaga as datas do reparo
- **Persona:** Larissa corrigindo, pela lista de vendas, uma venda que nasceu como reparo.
- **Por que existe:** o `after_sale_saved` do Repair grava os campos de reparo só quando vêm no request, mas zerava a **entrega** (`repair_due_date`) e a **conclusão** (`repair_completed_on`) quando não vinham. A edição pela lista (esta tela e o `sell.edit` Blade) não manda campo de reparo nenhum — só o PDV de reparo (`/pos/{id}/edit?sub_type=repair`) manda. Achado em 2026-10-02 ao seguir com a venda de reparo na edição.
- **Aceite:** Dado uma venda de reparo com entrega e conclusão gravadas · Quando salva sem enviar esses campos · Então as duas datas ficam **iguais** (e um campo de reparo enviado, como o número de série, é gravado — prova que o hook rodou) · Quando o PDV de reparo envia as datas **vazias** · Então elas são limpas, como antes.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` (UC-SEDIT-11, lane `sells-pest`).
- **Status: 🧪** _(nasce sem run.)_

---

## UC-SEDIT-12 · A edição de uma venda de reparo mostra e salva a seção Reparo
- **Persona:** Larissa corrigindo, pela lista de vendas, o aparelho, o status ou as datas de uma venda de reparo.
- **Por que existe:** reparo é um **tipo de venda** (decisão [W] 2026-10-01). O PDV React já tem a seção Reparo na criação (UC-S05/S06 do Sells/Create); a edição pela lista não tinha, e os campos de reparo só eram editáveis pelo PDV de reparo do módulo.
- **Regras:**
  - a seção aparece **só** na venda de reparo (`sub_type = repair`) com o módulo na assinatura; venda comum não muda;
  - as opções são as mesmas do PDV de reparo (status, marcas, aparelhos, modelos com checklist, garantias), escopadas pelo business;
  - os valores vêm do que está gravado; as datas vão cruas do banco ("AAAA-MM-DDTHH:mm") e voltam em ISO, sem passar pelo formato da empresa;
  - salvar manda os mesmos campos do PDV; data ou defeitos esvaziados vão vazios para limpar. Número de série, senha e padrão não limpam (o `ConvertEmptyStringsToNull` vira "" em null e o hook só grava não-nulos — o PDV Blade também não limpa).
- **Aceite:** Dado uma venda de reparo com status, série, entrega, defeitos e checklist gravados · Quando a tela pede o `form` · Então `reparo.valor` traz esses valores (entrega `2026-10-10T14:00`, defeitos em lista, checklist como objeto) e `reparo.opcoes` traz os status do próprio business · Dado uma venda comum · Então `reparo` é null.
- **Teste:** `tests/Feature/Sells/SellsEditContratoTest.php` (UC-SEDIT-12, lane `sells-pest`) + `tests/js/sells-reparo-venda.test.ts` (envio da edição, lane `sells-v3-dominio-gate`).
- **Status: 🧪** _(nasce sem run.)_

---

## Backlog — achados sem teste ainda (prosa honesta, sem id)

- ~~**[BACKLOG] Desconto de linha aplicado duas vezes ao editar pela tela React**~~ → virou o **UC-SEDIT-09** (2026-10-02), com conserto e teste.

Itens medidos ao derivar os casos acima. **Não são UC** (nenhum teste os cita) e nenhum foi
corrigido — mexer em guard de venda é Tier 0, decisão [W].

- **[BACKLOG]** Os dois guards (`canBeEdited`, `isReturnExist`) rodam **antes** do escopo por
  `business_id` (`SellController@edit:2643-2668`, contra o `findOrFail` escopado em `:2673`).
  Consequência: uma venda de OUTRO business **fora do prazo** devolve **422** ("prazo expirou") em
  vez de 404 — o que confirma ao chamador que aquele id existe e é antigo. O UC-SEDIT-01 fixa de
  propósito o caminho dentro do prazo (404); a variante fora do prazo fica declarada aqui.
- **[BACKLOG]** `TransactionUtil::isReturnExist` (`:4259`) consulta
  `Transaction::where('return_parent_id', $id)` **sem escopo de business** — e `App\Transaction`
  não tem global scope. Mesma família do **D-1** do SDD (`has_return` derivado de subquery não
  escopada): defesa em profundidade, sem vazamento provado, porque `return_parent_id` não é
  controlado pelo usuário no fluxo normal.
- **[BACKLOG]** O charter §Goals promete "Header h1 24px ... + stage FSM", mas o `headline`
  entrega `current_stage_key => null` fixo (`SellController@edit:2955`, comentado como "FSM ADR
  0143 (lazy)"). Contrato do charter à frente do código — pela precedência (`proibicoes.md`),
  corrigir o perdedor é ato de quem fechar esse item, não conserto silencioso aqui.
