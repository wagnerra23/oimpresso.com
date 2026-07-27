---
id: resources-js-pages-sells-index-casos
casos: Lista de vendas · /sells
irmaos: Index.charter.md (lei)
tecnica: Caso de uso = narrativa do cliente + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — não muda no refactor; é teste E explicação de uso E material de treino.
owner: wagner
last_run: "2026-07-03"
---

# Casos de Uso & Aceite — Lista de vendas

> Tela P0 (mandato ONDAS-QUALIDADE Q2). `Status: ✅` só com veredito `pass` no manifesto G-7.
>
> **Status:** ✅ passa (com prova no manifesto) · 🧪 em teste/prova parcial · ⬜ não verificado · ❌ quebrou.

---

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

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

> Regra G-2: UC declarado sem teste citando o id = órfão. Itens SEM token de UC até existir teste real.

- **[BACKLOG] Filtrar pela pílula muda as linhas** — clicar "A receber" mostra só vendas `due/partial`; exige venda semeada com status variados no harness.
- **[BACKLOG] Drawer da venda (documento vivo)** — clicar na linha abre o drawer com FSM/pagamentos; espelhar âncoras pós-DS v6.
- **[BACKLOG] Imprimir resumo do caixa de hoje** — botão no header; exige stub de print (mesmo padrão UC-11 Oficina).

## Como rodar a suíte
1. **E2E:** `npm run e2e:check` no harness do CI (e2e-gate) — vereditos viram manifesto via `npm run casos:results` (merge per-UC).
2. **Cadência:** rodar ao fim de toda mexida em Sells/Index. UC ❌ = regressão → lição + conserto.

## Trilha do tempo
- 2026-07-27 · [CC] **UC-SIDX-01 `[T0]` + UC-SIDX-02 `[V0]`** derivados pelo agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)) junto com o [SDD do módulo](../../../../memory/requisitos/Sells/SDD-tela-venda-v1.0.md) (`CU-SELL-32`/`CU-SELL-33`) + o teste `SellsIndexTenantContratoTest.php` + a lane nova `sells-pest.yml`. Achado que motivou o UC-SIDX-01: a derivação de `has_return` **não é escopada por `business_id`** em nenhum dos 2 sites medidos. **Convenção de id:** os novos usam 3 segmentos (`UC-SIDX-*`) porque o extrator de `scripts/governance/requisitos-status.mjs` exige `UC-XXX-NN` — os antigos `UC-S1x` ficam (renomear quebraria a citação dos testes que já os defendem, G-2). Ver SDD §9 D-4.
- 2026-07-03 · [CC] UC-S12 "ver que a venda já teve devolução (setinha de retorno)" + contrato em `SellsIndexCoworkPayloadTest.php` — restaura o indicador `has_return`/badge perdido no rewrite Cowork #1032 (payload React nunca selecionava `return_exists`). Incidente reportado por Guilherme @ biz=4 ROTA LIVRE. Ancorado no critério canônico `return_parent_id + type='sell_return'` (ADR 0264 G-2).
- 2026-07-01 · [CC] UC-S11 "Da lista, iniciar devolução" + spec no `sells-index.spec.ts` — fecha o gap por onde o menu de Ações sumiu no #1032 (adversário ancorado em ADR 0264/0256 + L-24: o que pega a regressão é UC+teste de comportamento, não gate de presença de charter). Refs #3488/#3494/#3499.
- 2026-06-11 · [CL] criado na Onda Q2 (mandato ONDAS-QUALIDADE) com UC-S10 + spec `sells-index.spec.ts`.
- 2026-06-22 · [CL] link "Caixa do dia" (`/vendas/caixa`) adicionado ao dropdown Visões de `Index.tsx` (tela viva órfã de navegação — `SellController@inertiaCaixa` → `Sells/Caixa/Index`, ADR 0192 Onda 6). `last_run` bumpado e UC-S10 rebaixado ✅→🧪 (G-7: prova e2e stale, não re-rodável fora do harness CI). Refs ADR 0264, ADR 0192.
