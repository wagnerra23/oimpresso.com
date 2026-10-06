---
casos: Manufacturing/Index — ordens de produção
irmaos: Index.charter.md (lei) · memory/requisitos/Manufacturing/RUNBOOK-producao.md (F1)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — o contrato de teste nasce junto com a tela, não depois.
fonte: handoff "PROTÓTIPO OFICIAL - FABRICAÇÃO V1" §4.5 + §15.1 — os UC abaixo DERIVAM dele
owner: wagner
last_run: "2026-10-06"
---

> ℹ️ **2026-10-06 — UC-OP-08 novo (regra de exibição):** o "N ingredientes" da lista passa a contar
> as linhas consumidas da ordem (as do painel), não os ingredientes da receita atual. UC-OP-04 reescrito
> para dizer isso; o teste dele não muda.

> ℹ️ **`last_run` 2026-10-05 → 2026-10-06 (G-6): só a data.** O `Index.tsx` ganhou data nova pelo merge
> do `main` no branch do #8743 (conflito de empilhamento sobre o #8742), que trouxe o #8745, já validado
> sob o `last_run` 2026-10-05. Nenhum UC mudou; a lane de Fabricação e o build do Vite passaram no head.

> ℹ️ **`last_run` 2026-09-30 → 2026-10-05 (G-6): forma, não regra de negócio.** Os dados, os filtros e
> os totais são os mesmos; o que muda de visível: a lista virou o `shared/DataTable` na anatomia `grid`
> (o par React do `DataGrid` do DS): `<table>`, cabeçalho 10px caixa-alta, linhas listradas, data,
> referência e números em fonte mono à direita. O selo "Rascunho" passa de âmbar a contorno, como o
> protótipo atual. Num segundo PR do mesmo dia, os filtros saem do cartão e ganham as medidas do
> protótipo (rótulo 10,5px/600, campos de 34–36px com canto 8 e texto 13–13,5px); o que eles filtram
> não muda. Num terceiro PR, os 4 indicadores ganham a linha de apoio e o tamanho padrão (como na
> Receitas); os números são os mesmos. Nenhum UC foi reexecutado nesta data.

# Casos de Uso & Aceite — Manufacturing/Index

> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado · ❌ quebrou.
> Regra G-2: UC declarado sem teste citando o id = órfão.
>
> A tela existe desde a Wave J (2026-05); estes UC nascem com a **US-MANU-004**, que é emenda
> (5 → 8 colunas). Derivados do handoff, **não do `.tsx`** (§5 tautológico das proibições).
>
> **Revalidação 2026-09-08** (`last_run` bumpado): o `.tsx` mudou por um PR de **FORMA**
> (rótulos dos filtros · subtítulo · âmbar do rascunho) — nenhum dos 5 UC abaixo fala de forma;
> os cinco são de Service/backend, que este PR não toca. Revalidados de fato, não por suposição:
> a lane `PHP / Pest (Manufacturing · MySQL)` rodou no
> [run 34236466226](https://github.com/wagnerra23/oimpresso.com/actions/runs/34236466226)
> — **146 passed · 426 assertions**, com `✓` em UC-OP-01/02/03/04/05 (conferido no log, item a
> item; "0 failed" sozinho não prova execução).

---

## UC-OP-01 · O custo unitário nunca vira infinito numa ordem sem quantidade
- **Persona:** Eliana (produção) — ordem legada com `quantity` zerado não pode explodir a tela.
- **Aceite:** Dado uma ordem cuja linha de compra tem `quantity = 0` · Quando o Service monta a
  linha · Então `custo_unitario` é `0.0` — nunca `INF`, nunca `NaN`.
- **Fonte:** §7.3 do handoff (divisão por zero) + `RUNBOOK-producao.md §2`.
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** dividir por `quantity` sem guard — o PHP devolve `INF`, que
  atravessa o JSON como `null`/erro de serialização e quebra a coluna inteira.
- **Status: 🧪**

---

## UC-OP-02 · O custo mostrado é o GRAVADO, não um recalculado novo
- **Persona:** Wagner — o número da lista tem que ser o da ordem, não uma segunda fórmula.
- **Aceite:** Dado o `enrichProductionRows()` · Quando ele monta `final_total` e
  `custo_unitario` · Então os dois derivam de `transactions.final_total` (valor gravado na
  criação) — **sem** chamar `RecipeBomService`, que é a fórmula do Relatório.
- **Fonte:** decisão registrada em `RUNBOOK-producao.md §1` (o `custoSnap` do protótipo não
  existe no banco; US-MANU-007 é quem o introduz).
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** alguém "unificar" o custo desta tela com o do Relatório e criar
  uma segunda fórmula de valor na base — §5 2026-06-05 (derivar do lugar errado) + REGRA
  MESTRE de valor.
- **Status: 🧪**

---

## UC-OP-03 · O enriquecimento é em LOTE — não uma query por ordem
- **Persona:** qualquer business com muitas ordens no período.
- **Aceite:** Dado N ordens listadas · Quando o Service enriquece as linhas · Então o nº de
  queries **não cresce com N** (produtos, receitas e usuários são resolvidos em 3 consultas
  `whereIn`, e as linhas de compra vêm por eager-load).
- **Fonte:** padrão do módulo (`listRecipesWithCost`, `reportByProduct`) + ADR 0093 (a cadeia
  de tenant é JOIN, não loop).
- **Teste:** `Wave32ProducaoColunasTest.php` — conta queries com `DB::listen` sobre um
  conjunto sintético.
- **Regressão que defende:** o N+1 clássico (resolver produto/usuário dentro do `map`).
- **Status: 🧪**

---

## UC-OP-04 · Ordem sem receita não some da lista — mostra 0 ingredientes
- **Persona:** Eliana — ordem antiga cujo produto perdeu a receita continua visível.
- **Aceite:** Dado uma ordem cuja variação produzida não tem `mfg_recipes` (e sem linhas
  consumidas gravadas) · Quando a lista carrega · Então a linha aparece com `n_ingredientes = 0`
  e `produto` resolvido (ou `—`), nunca sumindo do resultado. Desde o UC-OP-08 a contagem vem do
  consumo da ordem, não da receita; o caso segue valendo para a ordem que some do join.
- **Fonte:** §4.5 (a lista é de ORDENS, não de receitas) + sintoma catalogado no
  `RUNBOOK-producao.md §4`.
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Regressão que defende:** trocar o `leftJoin` da contagem de ingredientes por `join`, o que
  faria a ordem sem receita desaparecer silenciosamente da tela.
- **Status: 🧪**

---

## UC-OP-05 · Tier 0 — a lista não vaza ordem de outro business
- **Persona:** qualquer tenant.
- **Aceite:** Dado um tenant fictício sem ordens · Quando `listProductions` roda pra ele ·
  Então devolve vazio; e o enriquecimento revalida a cadeia do produto por
  `products.business_id`.
- **Fonte:** [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `Wave32ProducaoColunasTest.php`
- **Status: 🧪**

---

## UC-OP-06 · O intervalo de datas aplica ao escolher, sem botão
- **Persona:** Eliana (produção) — filtra o mês e quer ver a lista mudar, como já acontece com
  Local e "Só finalizadas".
- **Aceite:** Dado a lista de ordens · Quando escolho De **e** Até (datas completas) · Então a
  lista recarrega sozinha por partial reload (`only: productions, summary, filters`), sem clicar
  em nada. Só uma das duas preenchida **não** aplica; apagar as duas volta a lista inteira;
  digitar o ano à mão (o campo emite `0002-…`, `0020-…` no meio) **não** dispara request.
  O botão "Aplicar intervalo de datas" não existe mais.
- **Fonte:** decisão D-MFG-DATA ([W] 2026-09-25, delegada ao [CC]: "escolha melhor opção e
  iguale os dois") no playbook `cowork-inbox/manufacturing/` — o protótipo já aplica ao escolher.
- **Teste:** `tests/js/manufacturing-index-datas.test.tsx` (vitest/jsdom, lane
  `manufacturing-jsdom-gate`). Mordida provada por mutação: sem o guard de ano → 1 failed;
  sem aplicar no change → 3 failed.
- **Regressão que defende:** voltar ao blur + lupa (2 gestos), ou aplicar a cada tecla do ano.
- **Status: 🧪**

---

## UC-OP-07 · O detalhe da ordem usa as contas da tela antiga, só da própria empresa
- **Persona:** Eliana (produção) — abre uma ordem para ver o que foi consumido e quanto custa hoje.
- **Aceite:** Dado uma ordem com ingredientes gravados · Quando a tela pede o detalhe
  (`?ordem=ID`, partial reload de `ordem_detalhe`) · Então vêm os ingredientes com a quantidade
  GRAVADA na produção e o preço de HOJE; o custo extra pela forma gravada na ordem (percentual
  sobre os ingredientes de hoje · por unidade × produzidas + perdidas · fixo); o total de hoje,
  o custo por unidade e o valor gravado (`final_total`). Os números batem com os do detalhe
  antigo (`ProductionController::show()`). Ordem de outra empresa vem sem detalhe, e a carga
  normal da lista não calcula o detalhe.
- **Fonte:** `ProductionController::show()` (paridade, a tela antiga) + protótipo
  `manufacturing-producao.jsx` (`MfgProducaoDrawer`, "Ingredientes (preço de hoje)") +
  [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `Modules/Manufacturing/Tests/Feature/DetalheOrdemProducaoTest.php` — cada caso
  confere por dois caminhos (a conta à mão e as variáveis do `show()` legado).
- **Regressão que defende:** o painel inventar uma fórmula de custo diferente da tela antiga, ou
  mostrar ordem de outra empresa.
- **Status: 🧪**


## UC-OP-08 · O "N ingredientes" da lista é o número de ingredientes consumidos pela ordem
- **Persona:** Eliana (produção) — lê "2 ingredientes" na lista, abre a ordem e espera ver 2.
- **Aceite:** Dado uma ordem com N linhas gravadas na venda de produção (`production_sell`) ·
  Quando a lista carrega · Então a segunda linha da coluna Produto diz N ingredientes, o mesmo
  número de linhas que o painel lista em "Ingredientes consumidos" — mesmo que a receita do produto
  tenha hoje outro número de ingredientes (ou não exista). Só conta linhas da própria empresa.
- **Fonte:** protótipo `manufacturing-producao.jsx` — a lista mostra `c.linhas.length + " ingredientes"`
  e o drawer itera o mesmo `c.linhas` em "Ingredientes consumidos" + `ProductionController::show()`
  (a tela antiga lista as linhas consumidas). Achado em produção 2026-10-06: ordem 2024/0002 com
  "2 ingredientes" na lista e 1 ingrediente consumido no painel e na tela antiga.
- **Teste:** `Modules/Manufacturing/Tests/Feature/DetalheOrdemProducaoTest.php` — a fixture não tem
  receita (contar pela receita daria 0) e grava 2 linhas; confere à mão e contra o `detalheOrdem`.
- **Regressão que defende:** voltar a contar os ingredientes da receita atual, que mudam depois
  da ordem e desencontram a lista do painel.
- **Status: 🧪**
---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **[BACKLOG]** O sufixo `fix` aparece só em ordem finalizada, com o `title` verbatim (R-21) —
  comportamento de navegador; lugar é spec Playwright.
- **[BACKLOG]** O checkbox "Só finalizadas" e o KPI "Finalizadas" governam o MESMO filtro (um
  reflete o outro).
- **[BACKLOG]** O rodapé soma exatamente as ordens exibidas.

## Trilha do tempo
- 2026-09-04 · [F+C] US-MANU-004 (emenda: 5 → 8 colunas). 5 UC com teste Pest. A tela é da
  Wave J; este é o primeiro `casos.md` dela. Refs: UI-0013 · ADR 0264 G-1/G-2 · ADR 0093.
- 2026-09-04 · [F+C] **CUTOVER**: a tela passou a ser servida no endereço CANÔNICO do módulo
  (nasceu em `/manufacturing/v2/*`, que virou 301). Pedido [F]: *"módulo inteiro em produção,
  com os links e vínculos reais, sem rotas alternativas"*, sobre a aprovação [W] da família.
  Pré-condição medida: a regra F5 (cutover exige aviso a cliente) nomeia a ROTA LIVRE, e [F]
  confirmou que ela não usa Fabricação. **Nenhuma rota removida ou renomeada** — `?legacy=1`
  devolve o Blade no MESMO endereço e o ramo AJAX do DataTables segue intacto. Guarda nova:
  `Modules/Manufacturing/Tests/Feature/CutoverRotasCanonicasTest.php` (9 asserts). Nenhum UC
  acima mudou de comportamento; os asserts de ROTA de Wave30/31/33 foram reapontados pro
  canônico porque o `/v2/` agora responde `RedirectController`, não o controller da tela.
- 2026-09-25 · [C] UC-OP-06 (playbook Manufacturing thread 04, D-MFG-DATA): De/Até aplica ao
  escolher. `last_run` bumpado porque o `.tsx` mudou — os UC-OP-01..05 são de Service e não
  foram tocados; o UC-OP-06 roda em vitest (5 passed local, mordida provada por mutação).
