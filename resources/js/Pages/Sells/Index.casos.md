---
id: resources-js-pages-sells-index-casos
casos: Lista de vendas · /sells
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-10-23"
---

> ℹ️ **`last_run` 2026-09-06 → 2026-09-11 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` no [#7224](https://github.com/wagnerra23/oimpresso.com/pull/7224) foi **1 linha(s) de COMENTÁRIO** —
> o path do protótipo (`prototipo-ui/cowork/…` → `prototipo-ui/cowork/Wagner/…`, topologia por dono da ADR 0397).
> Zero JSX estrutural, zero handler, zero prop, zero copy alterada (verificado: `git diff origin/main...HEAD -- resources/js/Pages/Sells/Index.tsx`
> só tem linhas iniciadas por `//`, `*` ou `{/*`). **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run — mesmo tratamento do #6913.

# Casos de Uso & Aceite — Lista de vendas

> Tela P0 (mandato ONDAS-QUALIDADE Q2). `Status: ✅` só com veredito `pass` no manifesto G-7.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.
>
> ℹ️ **`last_run` 2026-07-03 → 2026-08-10 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` foi o **bloco de comentário das linhas 53-60**: ele citava
> `Pages/Jana/components/JanaCockpitV2.tsx` como consumidor, e 7 docs canon leram aquela citação
> como prova de consumo — o arquivo tinha 0 imports e foi removido. Zero JSX, zero handler, zero
> prop alterada; nenhum UC desta tela foi reexecutado nem revalidado. O bump é o que o campo
> significa na prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run.

> ℹ️ **`last_run` 2026-08-10 → 2026-08-25 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx e os dois componentes de impressao` foi **comentário de ponteiro de design**: a linha citava um
> caminho de protótipo que não existe mais no repo, e passou a citar o vivo (ou a declarar a
> ausência com data). Zero JSX, zero handler, zero prop, zero copy alterada — `git diff --numstat`
> do PR não tem uma linha sequer fora de comentário. **Nenhum UC desta tela foi reexecutado nem
> revalidado**; o bump é o que o campo significa na prática (*trio reconciliado com a tela nesta
> data*), não afirmação de re-run.
---

> ℹ️ **`last_run` 2026-08-25 → 2026-09-06 (G-6), e o que mudou na tela NÃO foi comportamento.**
> O único toque em `Index.tsx` (+ `_components/FiscalSection.tsx` e `_components/SaleAiPanel.tsx`) foi o atributo **`data-contract="<id>"`** no elemento raiz de
> região (menu-visoes · foco-comissao · saved-tree, mais drawer-fiscal e painel-ia nos componentes) — a âncora estável que o `<tela>.map.json` declara em `vivo.ancora`
> (`scripts/governance/design-code-map-check.mjs`). Atributo inerte: nenhum CSS o seleciona
> nestas telas (`grep -rn 'data-contract' resources/css/` → só `.arq-page [data-contract=abas|cabecalho]`)
> e nenhum JS de runtime o lê. Zero JSX estrutural, zero handler, zero prop, zero copy alterada.
> **Nenhum UC desta tela foi reexecutado nem revalidado**; o bump é o que o campo significa na
> prática (*trio reconciliado com a tela nesta data*), não afirmação de re-run.
## UC-S10 · Abrir a lista e enxergar a cobrança num relance
- **Persona:** Larissa / Kamila — "quem está devendo?" sem montar relatório.
- **Aceite:** Dado a tela carregada · Então o título **Vendas** renderiza e as pílulas de status por pagamento aparecem (default **Todas**; pagas/a-receber derivadas de `payment_status`).
- **Teste:** `e2e/sells-index.spec.ts` (Playwright, harness G-3 e2e-gate).
- **Status: 🧪** — prova parcial (rebaixado de verde por G-7 até o manifesto regravar).
- **Nota 🧪 (2026-06-22):** a prova no manifesto (`scripts/casos-test-results.json`, `ran_at: 2026-06-11`) ficou stale após a mudança de navegação de hoje (link "Caixa do dia" no dropdown Visões). O comportamento de UC-S10 (título **Vendas** + pílulas de pagamento) **não** foi tocado — o E2E `sells-index.spec.ts` segue passando no CI. Volta a verde quando `npm run e2e:check` + `npm run casos:results` regravarem o manifesto. Sem fingir prova, sem `casos:baseline:write`.

---

## UC-S11 · Da lista, iniciar a devolução de uma venda
- **Persona:** Larissa / caixa — cliente traz a peça de volta; iniciar o retorno pro estoque a partir da própria linha da venda, sem procurar outra tela.
- **Aceite:** Dado a lista carregada com ≥1 venda · Quando abro o menu **Ações da venda** (⋮) da linha e clico **Devolução** · Então o item aponta pra `/sell-return/add/{id}` (formulário de devolução daquela venda → retorno pro estoque).
- **Teste:** `e2e/sells-venda-balcao.spec.ts` (Playwright, harness G-3 e2e-gate) — roda após UC-S01 (que cria uma venda real, garantindo ≥1 linha na lista) e assere o menu ⋮ + o destino da Devolução por role/nome (L-24, sem classe CSS).
- **Regressão que defende:** o menu de Ações por linha existia (commit `d6f4dddcdc`) e sumiu no rewrite Cowork #1032 — drift que deixou a lista React sem o ponto de entrada da devolução (incidente 2026-07-01). Este UC + teste tornam a remoção uma **falha de CI**, não um sumiço silencioso. Refs: #3488 · #3494 · #3499 · [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md).
- **Status: 🧪** — spec escrito e defendido pelo e2e-gate; volta a ✅ quando `npm run e2e:check` + `npm run casos:results` regravarem o manifesto (G-7, sem fingir prova).

---

## UC-S12 · Da lista, ver que a venda já teve devolução (setinha de retorno)
- **Persona:** Larissa / Guilherme — depois de devolver uma peça, reconhecer num relance, na própria lista, que aquela venda teve retorno — sem abrir a venda pra descobrir.
- **Aceite:** Dado que existe uma transação `sell_return` apontando pra uma venda (`return_parent_id = venda.id AND type = 'sell_return'`) · Quando a lista carrega · Então o payload `/sells-list-json` traz `has_return: true` pra essa venda e o frontend desenha o badge de retorno (`vd-return-flag`, ícone `Undo2`, tooltip "Venda com devolução") ao lado do `#invoice`; vendas sem retorno vêm `has_return: false` e sem badge.
- **Teste:** `tests/Feature/Sells/SellsIndexCoworkPayloadTest.php` (Pest, harness G-2/casos-gate) — assere `has_return` no payload de `inertiaList` ancorado no critério canônico `return_parent_id + type='sell_return'` (o mesmo do JOIN `SR` em `TransactionUtil::getSellsCurrentFy`, L-24 sem classe CSS) + o render do badge em `SellsTabelaUnificada.tsx`.
- **Regressão que defende:** a "setinha de retorno" existia no Blade legado (`SellController@index` → `return_exists`, `fa-undo`) e sumiu no rewrite Cowork #1032 — o payload React nunca selecionava `return_exists`, então a lista mostrava a venda devolvida como normal (incidente 2026-07-03, reportado por Guilherme @ biz=4 ROTA LIVRE). Este UC + teste tornam a remoção da subquery/badge uma **falha de CI**. Ref: [ADR 0264](../../../../memory/decisions/0264-governanca-executavel-trio-dominio-e2e.md).
- **Status: 🧪** — teste de contrato escrito; volta a ✅ quando `npm run casos:results` regravar o manifesto (G-7, sem fingir prova).

---

## UC-SIDX-01 · O indicador de devolução só conta devoluções DO MESMO business `[T0]` `[must]`
- **Persona:** Larissa @ ROTA LIVRE (biz=4) — a lista dela não pode reagir a dado de outro tenant.
- **Como usa:** abre `/sells`. Cada linha mostra (ou não) o indicador de retorno. O que decide isso é o payload `has_return` de `/sells-list-json`.
- **Aceite:** Dado uma venda `final` do business A · E uma transação `type='sell_return'` de **outro** business cujo `return_parent_id` aponta pra essa venda · Quando o payload da lista de A é montado · Então a linha vem com `has_return` **falso** — a derivação considera apenas devoluções do próprio tenant.
- **Âncora de contrato (2 fontes):** `CU-SELL-32` do [SDD §6.3](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md) · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) (multi-tenant Tier 0). O critério canônico de devolução (`return_parent_id` + `type='sell_return'`) é o mesmo de UC-S12 — este UC acrescenta a dimensão de **tenant**, que o UC-S12 não afirma.
- **Teste:** `tests/Feature/Sells/SellsIndexTenantContratoTest.php` (Pest, lane `PHP / Pest (Sells · MySQL)`).
- **Regressão que defende:** a derivação de `has_return` é uma **subquery crua** (`SellController@inertiaList`) e o irmão legado é um `leftjoin('transactions as SR', …)` (`TransactionUtil::getSellsCurrentFy`) — **nenhum dos dois filtra `business_id`**, e `App\Transaction` não tem global scope. Medido 2026-07-27 (`grep -n "return_parent_id" app/Http/Controllers/SellController.php app/Utils/TransactionUtil.php` + `grep -n "addGlobalScope" app/Transaction.php` = 0). Sem este UC, escopar (ou re-desescopar) a subquery é mudança silenciosa.
- **Limite honesto:** `return_parent_id` não é controlado pelo usuário no fluxo normal → **não há vazamento provado**; o que está medido é a **ausência do escopo** (defesa em profundidade), não uma exploração. Ver [SDD §9 D-1](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md).
- **Força do veredito:** lane `PHP / Pest (Sells · MySQL)` — **advisory**: reprova visível, **não bloqueia merge** (fora do `governance/required-checks-baseline.json`).
- **Status: ⬜** — contrato nasce neste PR; **0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)**.

---

## UC-SIDX-02 · O totalizador de dinheiro do rodapé não soma venda de outro business `[V0]` `[T0]` `[must]`
- **Persona:** Kamila / Larissa — lê o total do rodapé pra saber quanto o filtro corrente representa. É número de dinheiro na tela.
- **Como usa:** abre `/sells` e lê o somatório do rodapé (`sum_final_total` / `sum_total_paid`).
- **Aceite:** Dado uma venda `final` de valor conhecido no business B · Quando o operador do business A carrega `/sells-list-json` · Então o somatório de A **não** varia por causa dela — o agregado herda o mesmo escopo `business_id` da query das linhas.
- **⚠️ O que este UC deliberadamente NÃO afirma:** que o somatório seja igual à soma das linhas **retornadas**. Medido no fonte (`SellController@inertiaList`): o agregado sai de uma `$totalsQuery` **separada**, sem o `limit` da listagem — logo cobre o conjunto filtrado inteiro, não a página. Afirmar "soma == linhas visíveis" produziria **falso-vermelho contra comportamento correto**; o contrato real é o **escopo**, não a paginação.
- **Âncora de contrato (2 fontes):** `CU-SELL-33` do [SDD §6.3](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md) · **REGRA MESTRE valor/estoque** ([proibicoes.md](../../../../memory/proibicoes.md)) — total exibido ao operador é valor · reforçado por [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `tests/Feature/Sells/SellsIndexTenantContratoTest.php` (Pest, mesma lane).
- **O que o assert mede:** o **comportamento** (o agregado não se move quando existe venda alheia), não o nome da chave — o teste localiza o somatório e compara **valor antes → depois**, do jeito que a REGRA MESTRE pede.
- **Força do veredito:** lane `PHP / Pest (Sells · MySQL)` — **advisory**.
- **Status: ⬜** — contrato nasce neste PR; **0 UC executado — trio nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL)**.

---

## UC-SIDX-03 · Filtro de período até um dia inclui as vendas desse dia inteiro `[V0]` `[must]`
- **Persona:** Kamila / Larissa — escolhe "Dia" (ou um período que termina hoje) pra conferir o que vendeu.
- **Como usa:** abre `/sells`, escolhe o preset **Dia** ou um **Personalizado** com data final, e lê a lista e o rodapé.
- **Aceite:** Dado uma venda `final` às 14:30 de um dia D · Quando o filtro vai de D até D · Então a venda aparece na lista e entra no totalizador · E uma venda à 00:00:00 de D+1 continua fora · E um `date_to` que já traz hora é respeitado como veio.
- **Âncora de contrato:** o `SellsDateFilter` manda `date_to` só com a data (`computePresetRange` → `yyyy-MM-dd`, e o `<input type="date">` do Personalizado) — o período escolhido pelo operador é um dia de calendário, não um instante · **REGRA MESTRE valor/estoque** ([proibicoes.md](../../../../memory/proibicoes.md)) — o totalizador da janela é valor na tela.
- **Teste:** `tests/Feature/Sells/SellsIndexFiltroDataFimContratoTest.php` (Pest, lane `PHP / Pest (Sells · MySQL)`).
- **O que o assert mede:** a linha da venda na lista e `totals.count`/`sum_final_total` da janela (antes do conserto: fora · 0 · 0; depois: dentro · 1 · 150), mais a borda superior com controle positivo.
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL).

---

## UC-SIDX-04 · A pílula "Rascunhos" mostra só os rascunhos do business, no recorte da permissão `[T0]` `[must]`
- **Persona:** Larissa (ROTA LIVRE) — deixa a venda pela metade quando o telefone toca e volta pra ela pela consulta de vendas.
- **Como usa:** abre `/sells`, clica a pílula **Rascunhos** (ou chega por `/sells?pill=rascunho`, link do menu Visões), e na linha usa **Continuar venda**, **Imprimir** ou **Excluir rascunho**; clicar a linha abre o drawer com o selo "Rascunho", sem "Receber" e sem "Devolução".
- **Aceite:** Dado rascunhos de dois vendedores, uma cotação, uma venda final e um rascunho de outra empresa · Quando a lista pede `status=draft` · Então volta só rascunho comum (`status=draft`, `sub_status` nulo) do business; cotação, venda final e outra empresa nunca aparecem · E com só `draft.view_own` cada vendedor vê o dele · E sem `draft.view_all` nem `draft.view_own` o pedido é 403 e o contador da pílula vem nulo (a pílula some).
- **Âncora de contrato:** US-SELL-060 aceite 2 (escopo `business_id`) e 3 (só `status = draft`; cotação é tela própria, US-SELL-061) · CU-SELL-08 do [SDD](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md) · o rascunho é o 1º estágio do pipeline da venda (`quote_draft`, `InitialStageResolver`, [ADR 0143](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)) · decisão [W] 2026-10-19 (pedido da ROTA LIVRE: "era para estar junta na consulta de vendas").
- **Teste:** `tests/Feature/Sells/SellsIndexRascunhosContratoTest.php` — `UC-SIDX-04 [T0] a pílula Rascunhos traz só rascunho comum do business…` (lane `PHP / Pest (Sells · MySQL)`).
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL).

---

## UC-SIDX-05 · Rascunho não entra nas vendas finais nem nos totais de faturado `[V0]` `[T0]` `[must]`
- **Persona:** Larissa / Kamila — leem "Faturado hoje", "A receber" e o rodapé; rascunho não é dinheiro vendido.
- **Aceite:** Dado um rascunho de valor conhecido criado no business · Quando a consulta carrega sem a pílula Rascunhos · Então a lista e o totalizador (`totals`) continuam só com venda final (o valor não se move) · E o contador da pílula sobe exatamente 1 com um rascunho comum do business, e não se move com cotação nem com rascunho de outra empresa. No front, os KPIs derivam só das vendas finais (lista à parte para rascunhos — inclusive quando a tela abre direto em `?pill=rascunho`).
- **Âncora de contrato:** decisão [W] 2026-10-19 ("os totais de faturado continuam só com venda final") · **REGRA MESTRE valor/estoque** ([proibicoes.md](../../../../memory/proibicoes.md)) — total exibido é valor · [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Teste:** `tests/Feature/Sells/SellsIndexRascunhosContratoTest.php` — `UC-SIDX-05 [V0] [T0] rascunho não entra na lista nem nos totais…` (mede `sum_final_total` antes→depois e o contador por delta).
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane PHP / Pest (Sells · MySQL).

---

## UC-SIDX-06 · Totais do filtro abaixo da lista, com os números do servidor `[V0]` `[must]`
- **Persona:** Larissa / Wagner — "quanto vendi neste período e quanto falta receber?" sem somar linha por linha nem montar relatório; no Delphi o total ficava ao pé do grid.
- **Como usa:** filtra por período, busca ou pílula Paga/Pendente e lê, abaixo da lista, a quantidade de vendas, o **Pago**, o **A receber** e o **Total do filtro** — de todas as páginas, não só da que está aberta.
- **Aceite:** Dado `/sells-list-json` devolvendo `totals` para o filtro · Quando a tela termina de carregar sem filtro que rode só no navegador · Então a barra mostra a quantidade e os três valores exatamente como o servidor mandou, em reais com centavos · E com visão salva diferente de "Todas", pílula Faturada/Cancelada ou filtro de origem (que filtram só a página carregada) a barra não mostra número e diz por quê · E em Rascunhos, durante a busca ou sem vendas, a barra não aparece.
- **Âncora de contrato:** US-SELL-017 (totalizador do filtro inteiro, não da página) · **REGRA MESTRE valor/estoque** ([proibicoes.md](../../../../memory/proibicoes.md)) — a tela só exibe o número calculado no servidor, não soma nada · UC-SIDX-02 (o total do servidor não soma outra empresa) e UC-SIDX-05 (rascunho fora dos totais de faturado) · forma do protótipo `prototipo-ui/cowork/Wagner/vendas-page.jsx` (`vd-totalbar`).
- **Teste:** `tests/js/sells-index-totais.test.tsx` — `UC-SIDX-06 …` (lane Vitest do `Frontend / Vite build`).
- **Status: ⬜** — contrato nasce neste PR; veredito pendente da lane Vitest.

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Filtrar pela pílula muda as linhas** — clicar "A receber" mostra só vendas `due/partial`; exige venda semeada com status variados no harness.
- **[BACKLOG] Drawer da venda (documento vivo)** — clicar na linha abre o drawer com FSM/pagamentos; espelhar âncoras pós-DS v6.
- **[BACKLOG] Imprimir resumo do caixa de hoje** — botão no header; exige stub de print (mesmo padrão UC-11 Oficina).

### Pesquisa de concorrentes 2026-10-20 — núcleo e comunicação visual

> Origem: pesquisa em 5 ERPs brasileiros e 7 sistemas de gráfica/comunicação visual, com fontes, em
> [`memory/sessions/2026-10-20-pesquisa-consulta-venda-concorrentes-cv.md`](../../../../memory/sessions/2026-10-20-pesquisa-consulta-venda-concorrentes-cv.md).
> São **propostas**, não contrato: cada item só ganha id quando tiver teste que o defenda (G-2), e os de
> comunicação visual dependem do fluxo da venda registrar a informação. Premissa: a mesma tela atende o
> vestuário; o que é de comunicação visual entra na visão **Produção** e só aparece para empresa com o
> módulo Comunicação Visual.

**Núcleo (3 ou mais ERPs brasileiros têm):**
- **[BACKLOG] Filtrar por vendedor** — Tiny, Omie, Conta Azul. Não há esse filtro na tela hoje.
- **[BACKLOG] Duplicar a venda (recompra)** — Bling, Tiny, Omie, Conta Azul.
- **[BACKLOG] Enviar a venda ao cliente por e-mail ou WhatsApp pela lista** — Bling, Tiny, Omie, Conta Azul.
- **[BACKLOG] Mudar situação ou etapa de várias vendas de uma vez** — Bling, Tiny, Omie. Passa pelo FSM, nunca por update direto.
- **[BACKLOG] Exportar a lista para planilha** — Bling, Tiny, Omie. Hoje o botão em lote diz "Em breve".
- **[BACKLOG] Imprimir várias vendas ou etiquetas de uma vez** — Bling, Tiny, Omie.
- **[BACKLOG] Gerar ordem de produção a partir de vendas** — Bling, Tiny, Nomus.
- **[BACKLOG] Faturar só parte da venda** — Tiny, Omie, Nomus.

**Comunicação visual:**
- **[BACKLOG] Ver na linha a etapa da produção** (arte, impressão, acabamento, instalação, entrega) — shopVOX, CoreBridge, Printavo, DecoNetwork, Ordant. A coluna Pipeline hoje mostra a etapa do fluxo da venda.
- **[BACKLOG] Atrasadas pela data de entrega, com dias de atraso** — shopVOX, CoreBridge, DecoNetwork, Calcgraf. A visão "Atrasadas" de hoje olha o pagamento (`sla_kind`).
- **[BACKLOG] Situação da arte na linha** (aguardando cliente, ajuste pedido, aprovada com data e quem aprovou) — 6 dos 9 sistemas de gráfica.
- **[BACKLOG] Sinal pago e saldo a receber** — Printavo; colunas Pago e A receber já existem na visão Financeira, falta o sinal.
- **[BACKLOG] Instalação: data, se está confirmada e equipe** — shopVOX, CoreBridge.
- **[BACKLOG] Segunda data: fim da produção, além da data prometida** — shopVOX. Decisão de produto: campo novo ou derivado da etapa de instalação.
- **[BACKLOG] Urgência e retrabalho/cortesia como marcadores** — Printavo e Calcgraf (urgência); retrabalho não confirmado em nenhum. Etiqueta genérica (como no Tiny) cobre os dois.
- **[BACKLOG] Venda parada com motivo** (sem material, esperando cliente) — shopVOX. Diferente de cancelada e de atrasada.
- **[BACKLOG] Status de cada nota da venda** (NF-e do material e NFS-e da instalação) — Calcgraf. Conferir se a coluna Fiscal já mostra as duas.
- **[BACKLOG] Recompra levando a arte já aprovada** — shopVOX.
- **[BACKLOG] Totais da operação** (em produção, atrasadas, aguardando arte, a receber) — Calcgraf, Printavo.

**Casos pedidos em 2026-10-23 — como a consulta mostra cada regra**

> Sem id: entram quando tiverem teste. As regras em si serão revistas na revisão dos fluxos (modelos,
> configurações e estados da FSM); estes casos dizem só o que a consulta mostra delas. O protótipo da
> consulta (PR #9771) já desenha o sinal e a arte.

- **[BACKLOG] Sinal de entrada na linha (UC-FLX-28)** `[V0]` — detalha o "Sinal pago e saldo a
  receber" acima. Aceite: Dado a empresa com sinal mínimo de 30% e uma venda aprovada de 1.000,00 sem
  nada pago, Então a coluna Sinal mostra "Abaixo do mínimo · 0,00 de 300,00" e a coluna Produção
  "Aguardando liberação"; pago o sinal, a coluna Sinal mostra "Pago · falta o saldo" e a OS sai da
  espera. Empresa sem sinal mínimo não mostra a coluna por padrão. Fonte: US-PCP-025 (a OS espera o
  sinal; UC-PCLIB-04 a 08 no Apontamento).
- **[BACKLOG] Situação da arte na linha (UC-FLX-02)** — detalha o item "Situação da arte na linha"
  acima. Aceite: Dado uma venda com OS em Arte, Então a coluna Arte mostra "Aguardando cliente · link
  enviado", "Ajuste pedido" com o comentário do cliente ou "Aprovada em <data> por <nome>", lidos da
  aprovação por link da OS. Venda sem arte não mostra nada. Fonte: US-COMVIS-NEW-004 (UC-PCART no
  Apontamento).
- **[BACKLOG] Item que deixou o estoque abaixo do mínimo (UC-FLX-19)** — Aceite: Dado a regra
  "Requisição pelo estoque mínimo" ligada e uma venda cujo item deixou o produto abaixo do mínimo,
  Quando abro a venda no drawer, Então o item mostra "abaixo do mínimo · requisição <número> aberta".
  Regra desligada (padrão), nada aparece. Fonte: US-COM-027 (a requisição nasce sozinha; UC-REQMIN-01 a
  08 em Requisição de compra).
- **[BACKLOG] Saldo a favor usado na venda** `[V0]` — Aceite: Dado uma venda de 500,00 que usou 120,00
  do saldo a favor do cliente, Então a coluna Pagamento mostra "120,00 do saldo a favor", a aba
  Pagamento do drawer mostra de onde veio o saldo (devolução, troca ou pagamento a mais, com a data) e o
  A receber dos totais conta 380,00. Fonte: o adiantamento do cliente já paga a venda pela forma
  "Adiantamento"; US-VEST-021.
- **[BACKLOG] Venda que passou do limite de crédito** `[V0]` — Aceite: Dado um cliente com limite de
  2.000,00 e uma venda a prazo salva com o aviso de limite, Então o Status mostra "Acima do limite ·
  300,00", e o filtro "Acima do limite" lista só essas vendas. Fonte: US-CRM-081.
- **[BACKLOG] Número do pedido de compra do cliente** — Aceite: Dado uma venda com o pedido do
  cliente "4500012345", Quando busco "4500012345", Então a venda aparece, e a linha mostra "Ped.
  cliente 4500012345" abaixo do nome do cliente. A coluna "Pedido do cliente" fica disponível no botão
  Colunas. Fonte: campo `xPed` da NF-e e da NFS-e (o mesmo número vai na nota).
- **[BACKLOG] Venda com ISS retido: total do serviço × o que o cliente paga (UC-FLX-30)** `[V0]` —
  Aceite: Dado uma venda de 1.000,00 com a NFS-e autorizada e 96,50 retidos (ISS + federais), Então o
  drawer mostra "Total do serviço 1.000,00 · retido 96,50 · cliente paga 903,50", o Total da linha
  segue 1.000,00, a coluna Pagamento diz "retido 96,50" e o A receber dos totais conta só o que falta
  do líquido. Fonte: US-FIN-072 (a retenção é baixa sem caixa; UC-CRISS-01 a 03 em Contas a receber).
- **[BACKLOG] Venda sem comissionado** — Aceite: Dado vendas com vendedor, com representante ou
  agência, e vendas sem ninguém vinculado, Quando filtro "Comissionado: nenhum", Então só aparecem as
  vendas sem ninguém vinculado, e no Foco Comissão elas mostram "sem comissionado" em vez de 0,00.
  Fonte: Comissionados (`sales_commission_agents`); UC-FLX-05.
- **[BACKLOG] Venda agrupada (US-SELL-024)** — várias vendas do mesmo cliente juntadas numa cobrança
  só. Aceite: Dado três vendas de um cliente agrupadas, Então cada uma mostra o selo "Agrupada" com o
  número da cobrança, o filtro "Agrupadas" lista só elas e o selo leva às outras vendas do grupo. Venda
  fora de grupo não mostra selo. Fonte: US-SELL-024 (coluna `is_grouped_invoice`, já no banco) e o
  Delphi (`CODFINANCEIRO_GRUPO`, em 34% a 65% das vendas dos clientes medidos).
- **[BACKLOG] Faturar só parte da venda** `[V0]` — detalha o "Faturar só parte da venda" acima.
  Aceite: Dado uma venda de 10 placas, Quando faturo 4 · Então nasce a parte "<venda>/1" com as 4
  placas, a nota e o título dela, e a venda mostra "4 de 10 faturadas · faltam 6"; cancelar a nota da
  parte devolve as 4 à venda. Mexe em valor e estoque: Regra Mestre. Fonte: Omie (pedido parcial).
- **[BACKLOG] Venda duplicada mostra de onde veio** — Aceite: Dado a V-200 duplicada da V-150, Então
  a linha e o drawer da V-200 mostram "Duplicada de V-150" com link, e o drawer da V-150 mostra
  "Duplicada em V-200"; vale também para orçamento duplicado. Hoje `SellController::duplicateSell`
  copia a venda para um rascunho e não guarda a origem (medido em 23/10/2026). Fonte: o backlog
  "Duplicar a venda (recompra)" acima.
- **[BACKLOG] Venda devolvida no todo ou em parte** — detalha o UC-S12. Aceite: Dado uma venda de 3
  itens com 2 devolvidos, Então o Status mostra "Devolução parcial · 2 de 3"; com os 3 devolvidos,
  "Devolvida"; o ícone de devolução do UC-S12 continua. Fonte: processo "Devolução, troca e garantia"
  de Fluxos (devolução total ou parcial).
- **[BACKLOG] Garantia aberta pela venda** `[E0]` — Aceite: Dado uma venda entregue com um item na
  garantia, Quando abro a garantia pelo menu da venda, Então a troca sai sem cobrança, com a nota de
  remessa em garantia ligada à venda, e a venda mostra "Garantia aberta"; fora do prazo, a troca pede
  aprovação do gerente. Fonte: SPEC de Garantia (planejado, cenários A a E) e o processo "Devolução,
  troca e garantia" de Fluxos.
- **[BACKLOG] Venda para entrega futura: o que falta entregar** — Aceite: Dado uma venda para entrega
  futura de 1.000 folhetos com 400 entregues, Então a linha mostra "Entrega futura · faltam 600" e o
  drawer lista as notas de remessa já emitidas. Fonte: UC-TRB-24 do protótipo fiscal (falta).
- **[BACKLOG] Emitir a nota com imposto faltando** — Aceite: Dado uma venda com um item sem NCM e
  outro com a natureza fiscal "a decidir pelo contador", Quando clico em emitir a nota no drawer,
  Então a tela confere a nota antes de enviar: o item sem NCM aparece "vai sair com o NCM padrão da
  empresa", com "Informar NCM agora" ou "Emitir assim"; o item com natureza a decidir não sai, com o
  motivo e o link para a Tributação; a venda continua salva. A conferência usa as mesmas regras do
  card de Saúde fiscal e do simulador da Tributação. Fonte: UC-TRB-33 do protótipo fiscal (decidido:
  o bloqueio nunca é beco sem saída; sem NCM, a saída é o NCM padrão da empresa, marcado para
  revisão), UC-FNFSE-12 (natureza pendente bloqueia as duas notas) e CU-PROD-18 (aviso no cadastro).
  Pedido da PraMoto (2026-10-27): os produtos comprados sem nota entram sem NCM, e o problema só
  aparece na emissão.
- **[BACKLOG] Emitir a nota com dados do cliente faltando** — Aceite: Dado um cliente empresa sem
  endereço completo (CEP e município) ou sem a indicação de contribuinte do ICMS, Quando clico em
  emitir a nota, Então a nota não sai e a tela lista o que falta, com link para o cadastro do cliente;
  a venda a consumidor final sem identificação segue como hoje. Fonte: grupo do destinatário da NF-e
  (documento, nome, endereço com município e indicador de IE).
- **[BACKLOG] Em que etapa da produção está cada venda** — detalha o "Ver na linha a etapa da
  produção" acima. Aceite: Dado uma venda com 2 OS, uma em Impressão e outra em Acabamento, Então a
  linha mostra "Produção: 0 de 2 prontas · mais atrasada: Impressão", e o filtro "Etapa da produção"
  lista as vendas com alguma OS na etapa escolhida; venda sem OS não mostra nada. Hoje a coluna
  Pipeline mostra a etapa da venda (`pipeline_label`), não a da OS (medido em `Index.tsx`,
  23/10/2026). Fonte: processo "OS e produção por item" de Fluxos; o protótipo da consulta (PR #9771)
  desenha a coluna Produção (OS).
- **[BACKLOG] Pedidos em aberto** — Aceite: Dado vendas aprovadas ainda não entregues e outras já
  entregues ou canceladas, Quando escolho a visão "Em aberto", Então aparecem só as não entregues e
  não canceladas, pagas ou não, com a etapa de cada uma, e o total da visão soma só elas. Hoje as
  visões "Pendentes" e "Pendentes pgto." olham o pagamento, não a entrega (medido em `Index.tsx`,
  23/10/2026).
- **[BACKLOG] Vendas que falta emitir nota** — Aceite: Dado uma venda paga sem nota e outra a receber
  sem nota, Quando escolho "Falta emitir nota", Então as duas aparecem; a venda com nota autorizada e
  a venda marcada para não emitir nota ficam de fora, e a nota rejeitada aparece com o motivo. Hoje a
  visão "Aguardando faturamento" exige `payment_status !== 'paid'`, então a venda paga sem nota não
  aparece em nenhuma visão de nota (medido em `Index.tsx`, 23/10/2026).
- **[BACKLOG] Tipos de venda lançadas** — Aceite: Dado o período escolhido, Então a consulta mostra
  quantas vendas e quanto de cada tipo (balcão, oficina, online, reparo, entrega futura, venda
  agrupada), e clicar num tipo filtra a lista. Hoje a visão Origem conta e soma só balcão, oficina e
  online (medido em `Index.tsx`, 23/10/2026); reparo não entra, e assinatura fica numa tela à parte.

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate) — vereditos viram manifesto via `npm run casos:results` (merge per-UC).
2. **Cadência:** rodar ao fim de toda mexida em Sells/Index. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-10-27 · [F+C] Backlog "Emitir a nota com imposto faltando": a conferência antes de emitir segue o UC-TRB-33 (sem NCM sai com o NCM padrão, marcado para revisão), pedido da PraMoto. Sem id.
- 2026-10-23 · [M+C] Backlog "Casos pedidos em 2026-10-23": sinal, arte, estoque mínimo, saldo a favor, limite de crédito, pedido de compra do cliente, ISS retido, venda sem comissionado e venda agrupada, sem id e sem mudança de comportamento da tela.
- 2026-10-23 · [M+C] Backlog: faturar só parte da venda, da comparação com concorrentes (Omie), sem id.
- 2026-10-23 · [M+C] Backlog de venda duplicada com a origem, devolução total ou parcial, garantia e entrega futura, sem id.
- 2026-10-23 · [M+C] Backlog: emitir a nota com imposto ou dados do cliente faltando, sem id.
- 2026-10-23 · [M+C] Backlog: etapa da produção, pedidos em aberto, vendas que falta emitir nota e tipos de venda lançadas, sem id.
- 2026-10-23 · [M+C] Revalidado (G-6): a única mudança em `Index.tsx` depois do last_run 2026-10-20 é a barra de totais do #9672, que já é o UC-SIDX-06 desta tela. Nenhum UC foi reexecutado; last_run 2026-10-20 → 2026-10-23.
- 2026-10-20 · [M+C] **UC-SIDX-06 `[V0]`** — barra de totais do filtro abaixo da lista (US-SELL-017): os `totals` que o servidor já calculava passam a aparecer. A barra não mostra número quando a visão salva, a pílula Faturada/Cancelada ou a origem filtram só a página carregada. Teste `tests/js/sells-index-totais.test.tsx` na lane Vitest do `Frontend / Vite build`.
- 2026-10-20 · [M+C] Backlog da pesquisa de concorrentes (núcleo + comunicação visual), sem id e sem mudança de comportamento da tela; fontes no [registro da sessão](../../../../memory/sessions/2026-10-20-pesquisa-consulta-venda-concorrentes-cv.md).
- 2026-10-19 · [CC] **UC-SIDX-04 `[T0]` + UC-SIDX-05 `[V0]`** — pílula "Rascunhos" na consulta de vendas (pedido da ROTA LIVRE: a tela `/sells/drafts` não tinha ações e "era para estar junta na consulta de vendas"; decisão [W]: filtro dentro da consulta, mesmas ações da venda, totais de faturado só com venda final). Teste `SellsIndexRascunhosContratoTest.php` na lane Sells. A tela `/sells/drafts` segue no ar até ser aposentada em PR próprio (só ganhou o conserto do "Itens NaN").
- 2026-07-27 · [CC] **UC-SIDX-01 `[T0]` + UC-SIDX-02 `[V0]`** derivados pelo agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)) junto com o [SDD do módulo](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md) (`CU-SELL-32`/`CU-SELL-33`) + o teste `SellsIndexTenantContratoTest.php` + a lane nova `sells-pest.yml`. Achado que motivou o UC-SIDX-01: a derivação de `has_return` **não é escopada por `business_id`** em nenhum dos 2 sites medidos. **Convenção de id:** os novos usam 3 segmentos (`UC-SIDX-*`) porque o extrator de `scripts/governance/requisitos-status.mjs` exige `UC-XXX-NN` — os antigos `UC-S1x` ficam (renomear quebraria a citação dos testes que já os defendem, G-2). Ver SDD §9 D-4.
- 2026-07-03 · [CC] UC-S12 "ver que a venda já teve devolução (setinha de retorno)" + contrato em `SellsIndexCoworkPayloadTest.php` — restaura o indicador `has_return`/badge perdido no rewrite Cowork #1032 (payload React nunca selecionava `return_exists`). Incidente reportado por Guilherme @ biz=4 ROTA LIVRE. Ancorado no critério canônico `return_parent_id + type='sell_return'` (ADR 0264 G-2).
- 2026-07-01 · [CC] UC-S11 "Da lista, iniciar devolução" + spec no `sells-index.spec.ts` — fecha o gap por onde o menu de Ações sumiu no #1032 (adversário ancorado em ADR 0264/0256 + L-24: o que pega a regressão é UC+teste de comportamento, não gate de presença de charter). Refs #3488/#3494/#3499.
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S10 + spec `sells-index.spec.ts`.
- 2026-06-22 · [CL] link "Caixa do dia" (`/vendas/caixa`) adicionado ao dropdown Visões de `Index.tsx` (tela viva órfã de navegação — `SellController@inertiaCaixa` → `Sells/Caixa/Index`, ADR 0192 Onda 6). `last_run` bumpado e UC-S10 rebaixado ✅→🧪 (G-7: prova e2e stale, não re-rodável fora do harness CI). Refs ADR 0264, ADR 0192.
