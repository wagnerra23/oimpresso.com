---
id: resources-js-pages-produto-edit-casos
casos: Editar produto · /products/{id}/edit
irmaos: Edit.charter.md (lei)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
por_que: comportamento é durável — editar o cadastro não pode perder o que a tela velha (Blade + Delphi) preservava.
owner: wagner
last_run: "2026-07-24"
last_run_ci: "0 UC executado — trio nasce agora (piloto sdd-from-source, ADR 0351); UCs stubbed citando e2e/produto-edit.spec.ts (test.fixme), veredito ⬜/🔶 honesto até rodar no CT100"
---

# Casos de Uso & Aceite — Editar produto

> **Âncora:** `CU-PROD-02` (produto variável), `CU-PROD-10` (multi-tenant) e `CU-PROD-01.4` `[V0]` do
> [SDD §6.1](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md), cruzados com o
> **contrato de paridade Delphi** ([ANTI-REGRESSAO-cadastro-produto-legacy.md](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md), Office Comercial 2026.1.1.38).
> Os UCs derivam do **contrato** (CU-PROD + charter + AR-PROD), **nunca** do `Edit.tsx` — teste derivado do
> código é tautológico e trava o desvio em vez de pegá-lo (`proibicoes.md` §5, 2026-06-05).
>
> **Como este arquivo nasceu:** piloto do agent `sdd-from-source` ([ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md)),
> triangulando as **3 fontes** — React (`Edit.tsx` → `PUT /products/{id}` → `ProductController@update`) +
> Blade (`resources/views/product/edit.blade.php`, o cadastro real em prod) + Delphi (as ~120 âncoras `AR-PROD-*`).
> Fecha a lacuna medida: `Produto/Edit.tsx` era `trio:missing-casos` no baseline do `casos-gate`.
>
> **O que ele NÃO faz:** não descreve o layout. Um caso de uso é contrato de **comportamento** — "editar o
> produto não apaga as variações" vale igual se o form mora no `Edit.tsx`, numa aba nova ou no Blade.
>
> **Status:** ✅ passa · 🧪 teste cita o UC e passa · ⬜ não verificado (stub) · 🔶 backlog (achado a verificar) · ❌ quebrou.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora | Teste | Status |
|----|-------------|------|--------|-------|--------|
| UC-PEDIT-01 | Editar produto variável não apaga as variações existentes | must | `CU-PROD-02` + AR-PROD-021/032 | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |
| UC-PEDIT-02 | Tipo (single/variable/combo) não muda após criação | should | Non-Goal charter + AR-PROD (tipo na criação) | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |
| UC-PEDIT-03 | Editar produto de outro business → 404 (não vaza, não 500) | must `[T0]` | `CU-PROD-10` + ADR 0093 + charter Goal | `e2e/produto-edit.spec.ts` (stub) | 🔶 backlog — achado a verificar no CT100 |
| UC-PEDIT-04 | Campo monetário no update não infla no parser pt-BR | must `[V0]` | `CU-PROD-01.4` + REGRA MESTRE | `e2e/produto-edit.spec.ts` (stub) | ⬜ não verificado |

> ⚠️ **Nenhum UC está afirmado ✅** — o trio nasce agora e os testes são stub `test.fixme` (não rodam, não
> quebram o CI; satisfazem só a rastreabilidade G-2). Afirmar verde sem rodar seria a lápide `proibicoes.md`
> §5 2026-07-15. O veredito real de cada UC vem quando o Pest rodar no CT100 (não local — [ADR 0062]).

---

## UC-PEDIT-01 · Editar produto variável não apaga as variações existentes · `must`
- **Persona:** Larissa / ROTA LIVRE — abre um produto com grade tam×cor (N variações), corrige o nome e salva. As variações (SKU, preço, estoque por variação) **não podem sumir** só porque ela editou o cabeçalho.
- **Aceite:** Dado um produto variável com **N variações** · Quando envio `PUT /products/{id}` alterando **apenas** o `name` · Então as **N variações persistem** (mesmo `sub_sku`/preço/estoque) — `ProductController@update` **não** recria nem apaga a grade.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-01` (stub `test.fixme`; virar Pest `ProdutoEditContratoTest` no CT100).
- **Contrato:** `CU-PROD-02` (produto variável — grade preservada) + Edit.charter Non-Goal *"❌ Editar variations dinamicamente (Wave 3)"* — editar o cabeçalho **não é** editar a grade.
- **Paridade Delphi:** `AR-PROD-021`/`AR-PROD-032` — no legado, "Alterar" edita o registro **preservando** o que já estava carregado (trocar de aba/salvar não perde o produto).
- **Regressão que defende:** `update()` monta a variação a partir do request; se o payload da Edit React (que **não** manda os campos de variação) fizer o backend zerar a grade, o produto perde SKU/preço/estoque calado.
- **Status: ⬜** — stub; vira 🧪/✅ quando o Pest rodar no CT100.

---

## UC-PEDIT-02 · Tipo (single/variable/combo) não muda após criação · `should`
- **Persona:** qualquer operador — o `type` define a natureza do produto (simples × grade × kit). Mudá-lo depois de criado corromperia variações/BOM já existentes.
- **Aceite:** Dado um produto `type='single'` · Quando um `PUT /products/{id}` forja `type='variable'` · Então o produto **continua** `single` (o backend ignora a troca de tipo).
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-02` (stub).
- **Contrato:** Edit.charter Non-Goal *"❌ Mudar `type` (Single/Variable/Combo) após criar"* + o `<Input>` de tipo é `disabled` na Edit React (defesa de UI, não de contrato — o UC prova o **backend**).
- **Paridade Delphi:** o tipo é definido na criação; a tela de alteração não expõe troca de tipo.
- **Regressão que defende:** o React desabilita o campo, mas UI desabilitada **não** impede o request de mandar `type` — mesma família do furo `UC-PCAD-05`/`UC-PTAB-04` (dropdown escopado ≠ request escopado).
- **Status: ⬜** — stub.

---

## UC-PEDIT-03 · Editar produto de outro business → 404 (não vaza, não 500) · `must` `[T0]`
- **Persona:** qualquer tenant. O pior bug do projeto é o catálogo de um business vazar pro outro — aqui pela URL da edição.
- **Aceite:** Dado `GET /products/{id_de_outro_business}/edit` **ou** `PUT /products/{id_de_outro_business}` · Quando acesso · Então **404** — nenhum dado do produto alheio chega ao form nem é gravado.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-03` (stub; Pest cross-tenant biz=1 vs biz=2 no CT100).
- **Contrato:** `CU-PROD-10` + Edit.charter Goal *"Multi-tenant: produto cross-tenant retorna 404"* + [ADR 0093](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md).
- **Regressão que defende:** é **exatamente** a família que já nasceu vermelha 2× no Produto — `UC-PCAD-06` (`create()` `find()`→`findOrFail()`, era 500) e `UC-PTAB-04` (`saveSellingPrices` engolia a `ModelNotFoundException` no `catch` genérico → 302, não 404, [#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)). `edit()` (L856) e `update()` (L966) precisam ser verificados pelo **mesmo** padrão.
- **Status: 🔶 backlog — achado a verificar, NÃO afirmado.** Não li `edit()`/`update()` linha a linha o suficiente pra afirmar se é `find` ou `findOrFail`, nem rodei o teste (LC-08 — não afirmo achado sem varredura + sem teste vermelho). Se nascer vermelho, a correção entra failing-first sob decisão [W]. Verificação = Pest no CT100.

---

## UC-PEDIT-04 · Campo monetário no update não infla no parser pt-BR · `must` `[V0]`
- **Persona:** Larissa — se editar o custo/preço e digitar `1.234,56`, o sistema tem que gravar mil duzentos e trinta e quatro, nunca um milhão.
- **Aceite:** Dado um `PUT /products/{id}` com `single_dpp='1.234,56'` e `single_dsp='2.000,00'` · Quando salvo · Então a variação grava `default_purchase_price ≈ 1234.56` / `default_sell_price ≈ 2000.00` — **nunca** ordem de grandeza maior.
- **Teste:** `e2e/produto-edit.spec.ts` — `UC-PEDIT-04` (stub; Pest com o mesmo par `1.234,56`/`204.99605` do `UC-PCAD-04`).
- **Contrato:** `CU-PROD-01` item 4 `[V0]` + REGRA MESTRE valor/estoque (`proibicoes.md`). `ProductController@update` roda `num_uf` em `single_dpp`/`single_dsp`/`profit_percent` (`ProductController.php:1102-1106`) e `alert_quantity` (`:997`) — **o mesmo parser** que inflou 16 vendas ×100k na ROTA LIVRE (incidente 2026-06-05).
- **⚠️ Achado de paridade (NÃO afirmado como bug — decisão [W]/[F]):** a **Edit React não tem campo de preço** — o `useForm` não manda `single_dpp`/`single_dsp`/`profit_percent`, e o card "Preço & Imposto" só traz `tax`/`tax_type` (mesmo padrão do `Create.tsx`, §Pendência de contrato do `Create.casos.md`). Já o **Delphi edita Custo/Valor/Margem** com binding bidirecional (`AR-PROD-006`/`007`/`008`). Então: (a) o UC defende o **endpoint** (`update()` parseia pt-BR, caminho Blade/legado); (b) a **ausência do preço na Edit React** é gap de paridade Blade/Delphi→React, registrado abaixo — não afirmo se é Non-Goal ou bug (igual à Pendência do Create; segue [F] reconstruindo o cadastro em abas).
- **Status: ⬜** — stub.

---

## Paridade Delphi→React (gaps do cutover — decisão [W]/[F], não bug afirmado)

> O `Edit.tsx` (draft) migrou **8 campos do cabeçalho** (nome/SKU/tipo/unidade/categoria/marca/imposto/alerta/peso/descrição/custom). O Delphi "Alterar produto" faz **muito mais** — cada item abaixo é uma âncora `AR-PROD-*` que **NÃO** deve sumir sem Non-Goal declarado ([ANTI-REGRESSAO §](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)). Vira US/Non-Goal quando [W]/[F] decidir (a Edit segue a US-PROD-023 — promover as 8 telas).

| Gap na Edit React | Âncora Delphi | Onde já existe no oimpresso | Natureza |
|---|---|---|---|
| Editar **Custo · Valor · Margem** (binding bidirecional) `[V0]` | AR-PROD-006/007/008 | `SellingPrices.tsx` (preço por tabela) + `update()` variação | aba Custos ([F], #4403) — provável Non-Goal do `/edit` |
| **Última Alteração** (timestamp read-only de auditoria) | AR-PROD-004 | — | gap |
| **Atividade** (log append-only de modificações do cadastro) | AR-PROD-131..134 | — | gap |
| **Excluir** = soft-delete (inativo + filtro, nunca hard-delete) | AR-PROD-022 | charter Non-Goal *"❌ Deletar produto inline"* | Non-Goal declarado |
| **Estoque** (disponibilidade, kardex, fornecedor, compras) | AR-PROD-050..084 | `StockHistory.tsx` (fachada, G-01) | tela separada |

---

## Âncoras propostas pro SPEC (o agent PROPÕE; [W]/humano aplica)

> **Não aplicadas ao `SPEC.md` neste piloto** — tocar o SPEC legado do Produto acorda o `anchor-lint`
> diff-aware sobre a dívida grandfathered dele (lápide `proibicoes.md` §5 2026-07-12). Ficam como
> proposta pra [W] aplicar quando a US-PROD-023 avançar:

- `US-PROD-023` (promover as 8 telas React) → **Implementado em:** `resources/js/Pages/Produto/Edit.tsx` → `PUT /products/{id}` → [`ProductController@update`](../../../../app/Http/Controllers/ProductController.php) · casos: `Edit.casos.md` · verificado@`<sha-quando-rodar>`

---

## Backlog de casos (sem id — entram quando tiverem teste que os defenda)

- **Editar produto simples grava os campos do cabeçalho** — o caminho feliz óbvio; vira UC quando o Pest `ProdutoEditContratoTest` existir (hoje seria tautológico contra o `.tsx`).
- **Editar Custo/Valor/Margem** (`AR-PROD-006..008`) — só quando a aba Custos existir em git ([F], #4403) e sob a REGRA MESTRE `[V0]`.

---

## Refs

- Charter (lei): [`Edit.charter.md`](Edit.charter.md) — `v1`, `draft`, `last_validated: 2026-05-15`
- SDD (âncora dos CU): [`SDD-tela-cadastro-produto-v1.0.md`](../../../../memory/requisitos/Produto/SDD-tela-cadastro-produto-v1.0.md) §6.1
- Contrato de paridade Delphi: [`ANTI-REGRESSAO-cadastro-produto-legacy.md`](../../../../memory/requisitos/Produto/ANTI-REGRESSAO-cadastro-produto-legacy.md)
- Irmãos que fecharam o trio: [`Create.casos.md`](Create.casos.md) · [`SellingPrices.casos.md`](SellingPrices.casos.md)
- SPEC (a US que promove a tela): [`SPEC.md`](../../../../memory/requisitos/Produto/SPEC.md) — US-PROD-023
- Mecanismo: [ADR 0351](../../../../memory/decisions/0351-sdd-from-source.md) (`sdd-from-source`, piloto)
- Gate: `scripts/casos-coverage-guard.mjs` (G-1/G-2/G-5/G-6/G-7 — ADR 0264)

## Trilha do tempo
- 2026-07-24 · [CC] nascido pelo piloto do agent `sdd-from-source` (ADR 0351) — trio fechado (charter existia + casos novo + stub e2e), triangulando React+Blade+Delphi. Fecha `trio:missing-casos:Produto/Edit.tsx` do baseline. UCs stubbed (⬜/🔶), veredito honesto até rodar no CT100. Refs: ADR 0351 · 0264 G-1/G-2.
