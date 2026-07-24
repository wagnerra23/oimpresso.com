---
id: b1-controle-produto-edit-casos-agent
casos: Editar produto · /products/{id}/edit — RASCUNHO DE CONTROLE (agent `sdd-from-source`)
irmaos: resources/js/Pages/Produto/Edit.charter.md (lei) · Edit.casos.md (versão humana, NÃO tocada)
tecnica: Caso de uso = narrativa do operador + critério de aceite verificável (Dado/Quando/Então)
owner: wagner
status: rascunho-de-controle
gerado_por: agent sdd-from-source (B1-CONTROLE, autorizado por [F] 2026-07-24)
nao_e_canon: true
---

# RASCUNHO DE CONTROLE — Casos de Uso & Aceite · Editar produto

> ⚠️ **ESTE ARQUIVO NÃO É CANON.** É a saída bruta do agent `sdd-from-source` rodado como grupo de
> controle contra o `resources/js/Pages/Produto/Edit.casos.md` feito à mão (PR #4767). Não substitui,
> não é lido por gate, não deve ser citado por teste. Existe só para a comparação lado a lado.

## Camada 1 — as 3 fontes resolvidas

| # | Fonte | Resolvida em | Estado |
|---|---|---|---|
| 1 | Doc canon | `SDD-tela-cadastro-produto-v1.0.md` §6.1 (`CU-PROD-01/04/09/10`) + `Edit.charter.md` + `SPEC.md` (US-PROD-020/023) | ✅ — **mas sem CU próprio de "editar"** (ver Lacuna L-1) |
| 2 | React/Laravel vivo | `Pages/Produto/Edit.tsx:122` → `PUT /products/{id}` → `ProductController@update` (`:966-1210`); GET em `@edit` (`:856-957`) | ✅ |
| 3 | Blade AdminLTE legada | `resources/views/product/edit.blade.php` + `partials/edit_single_product_form_part.blade.php` | ✅ |
| 4 | Delphi / Office Comercial | `ANTI-REGRESSAO-cadastro-produto-legacy.md` (`AR-PROD-001..015`, `020..025`, `030..032`, `040..042`) | ✅ |

**Varredura contada (recibos):**

```
git grep -nE "put\(|patch\(" -- 'resources/js/**/*.tsx' | grep -icE "product"   → 1   (1 de 1: Edit.tsx:122)
git grep -n "/edit" -- 'resources/js/Pages/Produto'                             → 1 entrada React (Show.tsx:114)
grep -c "AR-PROD-" ANTI-REGRESSAO-cadastro-produto-legacy.md                    → 159 linhas com âncora
```

## Camada 1.2 — fluxo real (vai pro §5 do SDD)

```
Show.tsx:114 ──<Link>──► GET /products/{id}/edit ──► ProductController@edit (:856)
                                                     ├─ can('product.update') senão 403
                                                     ├─ Product::where(business_id)->firstOrFail()  ← 404 cross-tenant OK
                                                     └─ if (X-Inertia) Inertia::render('Produto/Edit')  (:909)
                                                        else view('product.edit')                        ← branch dual ADR 0104

Edit.tsx:122 ──useForm.put──► PUT /products/{id} ──► ProductController@update (:966)
                                                     ├─ can('product.update') senão 403
                                                     ├─ firstOrFail() FORA do try (:983)  ← 404 cross-tenant OK
                                                     └─ try { $request->only([...36 campos]) (:988)
                                                          ├─ :1011 alert_quantity → num_uf()        [V0]
                                                          ├─ :1037 preparation_time_in_minutes      ← H1
                                                          ├─ :1041 enable_stock → 0 se ausente      [V0] H3
                                                          ├─ :1088 save() + :1089 touch()           (AR-PROD-004)
                                                          ├─ :1111 if type==='single' → Variation::find(single_variation_id)  ← H2
                                                          └─ } catch (\Exception) { rollback; success:0 }  (:1187)
                                                        → redirect('products')  (:1209)
```

**Assimetria confirmada (2 de 2 caminhos lidos):** o `edit()` (GET) e o `update()` (PUT) fazem o guard
multi-tenant **fora** do try; o `saveSellingPrices` (tela irmã) faz **dentro** — buraco já documentado
em `CU-PROD-10.2` e corrigido aqui.

## Rastreabilidade

| UC | Caso de uso | Prio | Âncora de contrato | Teste | Status |
|----|-------------|------|--------------------|-------|--------|
| UC-PEDIT-A1 | Form de edição chega preenchido com o produto atual | must | charter §Goals "Defaults preenchidos" · AR-PROD-021 | `Wave2EditInertiaTest` (existente) | ⬜ não verificado por mim |
| UC-PEDIT-A2 | Tipo não muda depois de criado | must | charter §Non-Goals ❌ "Mudar type" | — | ⬜ sem teste |
| UC-PEDIT-A3 | Produto de outro business → **404** no GET **e** no PUT | must `[T0]` | `CU-PROD-10.2` · ADR 0093 | — | ⬜ sem teste |
| UC-PEDIT-A4 | Dropdowns só trazem opções do business atual | must `[T0]` | `CU-PROD-01.5` | — | ⬜ sem teste |
| UC-PEDIT-A5 | Quantidade de alerta com decimal pt-BR não infla ×100 | must `[V0]` | `CU-PROD-01.4` · `CU-PROD-04.4` | — | ⬜ sem teste |
| UC-PEDIT-A6 | Salvar registra a hora da alteração (auditoria) | should | **AR-PROD-004** | — | ⬜ sem teste |
| UC-PEDIT-A7 | Salvar pela tela nova **não pode** desligar o controle de estoque | must `[V0]` | **AR-PROD-051/056** + REGRA MESTRE | — | 🔴 **hipótese H3** |
| UC-PEDIT-A8 | Salvar pela tela nova **não pode** apagar campo que a tela não mostra | must | AR-PROD-042 · AR-PROD-003 | — | 🔴 **hipótese H4** |
| UC-PEDIT-A9 | "Salvar alterações" realmente persiste (não faz rollback silencioso) | must | charter §Goals "Salvar alterações" · AR-PROD-021 | — | 🔴 **hipótese H1** |
| UC-PEDIT-A10 | Editar Custo/Valor/Margem com binding bidirecional | — | **AR-PROD-006/007/008** `[V0]` | — | 🔶 **backlog / Non-Goal?** decisão [W] |
| UC-PEDIT-A11 | Custo e Margem somem para quem não pode ver custo | — | **AR-PROD-015** `[V0]` | — | 🔶 backlog |
| UC-PEDIT-A12 | Código do produto é read-only na edição | should | **AR-PROD-001** | — | 🔴 **divergência D-1** (React deixa o SKU editável) |

---

## UC-PEDIT-A1 — O form de edição chega preenchido `[must]`

- **Persona:** Larissa (SDD §2) — corrige o nome/categoria de um produto já cadastrado.
- **Âncora:** `Edit.charter.md` §Goals *"Defaults preenchidos com produto atual"* + AR-PROD-021 (*Alterar — habilita edição do registro atual*).
- **Aceite:** *Dado* um produto do meu business · *Quando* faço `GET /products/{id}/edit` com `X-Inertia` · *Então* `props.product` traz `name`, `sku`, `type`, `categoryId`, `unitId`, `tax`, `taxType`, `barcodeType`, `alertQuantity` **não-nulos quando existem no banco**.
- **Teste:** `tests/Feature/Produto/Wave2EditInertiaTest.php` (já existe — **não rodei**, ver §Veredito).
- **Regressão que defende:** tela de edição abrir em branco e o operador re-digitar tudo.

## UC-PEDIT-A2 — Tipo não muda depois de criado `[must]`

- **Âncora:** `Edit.charter.md` §Non-Goals ❌ *"Mudar `type` (Single/Variable/Combo) após criar"*.
- **Aceite:** *Dado* um produto `type='single'` · *Quando* mando `PUT /products/{id}` com `type=variable` no corpo · *Então* o produto continua `single` no banco (o campo é ignorado server-side, não só desabilitado no HTML).
- **Regressão que defende:** confiar no `disabled` do input. `disabled` é UI; quem garante é o servidor **não atribuir** `type`. Hoje isso vale por **omissão** (o `update()` nunca escreve `$product->type`) — omissão não é contrato até ter teste.

## UC-PEDIT-A3 — Cross-tenant → 404 no GET e no PUT `[must][T0]`

- **Âncora:** `CU-PROD-10.2` (*Cross-tenant por ID → 404, não 403*) · ADR 0093 · charter §Goals.
- **Aceite:** *Dado* um produto de OUTRO `business_id` · *Quando* faço `GET /products/{id}/edit` **ou** `PUT /products/{id}` · *Então* **404** — e nenhum campo é gravado.
- **Teste (biz=1 vs biz=2, nunca biz=4 — ADR 0101):** `tests/Feature/Produto/ProdutoTenantGuardTest.php` (`test.fixme` até rodar no CT100).
- **Regressão que defende:** exatamente o buraco do `saveSellingPrices` ([#4300](https://github.com/wagnerra23/oimpresso.com/pull/4300)) — `firstOrFail` dentro do `try` vira 302 `success:0`. Aqui o guard está **fora** do try (`:983`), com comentário explicando. **O comportamento correto já está no código; falta o teste que o trava.**

## UC-PEDIT-A5 — `num_uf` no campo de alerta `[must][V0]`

- **Âncora:** `CU-PROD-01.4` + `CU-PROD-04.4` (REGRA MESTRE valor/estoque).
- **Aceite:** *Dado* `alert_quantity = "1.234,50"` · *Quando* salvo · *Então* o banco grava `1234.50` — **não** `123450`.
- **Dupla-confirmação exigida ([V0]):** (a) asserção no banco pós-`PUT`; (b) recomputo do mesmo valor por `ProductUtil::num_uf()` isolado. Tabela antes→depois obrigatória no PR.
- **Regressão que defende:** o incidente `num_uf` de 2026-06-05 (venda inflada ×100k), agora no campo de estoque.

---

## 🔴 Hipóteses (NÃO são achados — falta o teste vermelho)

> **Disciplina `proibicoes.md` §5 (2026-07-15):** varri os consumidores e contei (1 de 1), e cito a
> âncora de contrato — mas **não rodei o teste** (Pest é CT100, ADR 0062, sem acesso nesta corrida).
> Enquanto o vermelho não existe, o vocabulário é **hipótese**. E, principalmente:
> **NÃO unifico as quatro numa "causa raiz"** — são defeitos independentes cujas correções
> **brigam entre si** (ver §Ordem, abaixo).

O `useForm` do `Edit.tsx` (`:83-100`) manda **17 chaves**. O `update()` lê **36+** via
`$request->only(...)` (`:988`) e ainda `$request->input(...)` avulso. A diferença é a superfície das
hipóteses:

| # | Campo que o React **não** manda | Linha | Efeito lido no código | Severidade |
|---|---|---|---|---|
| **H1** | `preparation_time_in_minutes` | `:1037` | acesso **direto** a chave inexistente → `E_WARNING` → Laravel converte em `ErrorException` → cai no `catch (\Exception)` (`:1187`) → `DB::rollBack()` → `success: 0` → `redirect('products')`. **Nada é salvo, e a tela não mostra erro de campo.** | 🔴 bloqueante |
| **H2** | `single_variation_id`, `single_dpp`, `single_dpp_inc_tax`, `single_dsp`, `single_dsp_inc_tax`, `profit_percent` | `:1112-1121` | `Variation::find(null)` → `null` → `$variation->sub_sku = ...` → **`\Error`**, que **não** é `\Exception` → **não é capturado** pelo catch → 500 | 🔴 bloqueante (se H1 for corrigida) |
| **H3** | `enable_stock` | `:1041-1045` | ausente → `$product->enable_stock = 0`. **Toda edição desligaria o controle de estoque do produto.** | 🔴 `[V0]` REGRA MESTRE |
| **H4** | `not_for_selling`, `enable_sr_no`, `sub_unit_ids`, `secondary_unit_id`, `product_custom_field5..20`, `expiry_period`/`expiry_period_type` | `:1036-1070` | zerados/nulificados por ausência (`?? ''`, `! empty(...) ? ... : null`) | 🔴 perda silenciosa |

### Ordem — por que consertar H1 sozinha PIORA a situação

H1 dispara na linha `1037`, **antes** de tudo. Enquanto ela existir, o `rollback` do `catch`
**protege acidentalmente** o produto de H2/H3/H4: nada é gravado, então nada é apagado.

> **Consequência dura:** um PR que "conserte o warning do `preparation_time_in_minutes"` e nada mais
> **destrava** H3/H4 e passa a **zerar `enable_stock` e apagar campos** em produção. A correção certa
> é o **contrato do payload** (o `update()` só pode escrever o que recebeu — `$request->has()` /
> `filled()` por campo, ou um FormRequest com `sometimes`), nunca o warning isolado.
> Isto é o padrão *"N defeitos independentes cujas correções se anulam"* de `proibicoes.md` §5.

### Como virar achado (teste vermelho a rodar no CT100)

```php
// tests/Feature/Produto/ProdutoEditPayloadContratoTest.php  (failing-first)
it('PUT com o payload EXATO do Edit.tsx persiste o nome', ...);            // H1
it('PUT pelo Edit.tsx não desliga enable_stock', ...);                     // H3  [V0]
it('PUT pelo Edit.tsx não apaga product_custom_field5', ...);              // H4
it('PUT em produto single não estoura 500 por variação ausente', ...);     // H2
```

Comando: `tailscale ssh root@ct100-mcp "docker exec -e DB_CONNECTION=mysql oimpresso-staging php artisan test --filter=ProdutoEditPayloadContrato"`

---

## 🔶 Paridade Delphi → React (gaps, cada um precisa de CU ou Non-Goal explícito)

| Capacidade do legado | Âncora | Onde está no React | Veredito |
|---|---|---|---|
| **Custo · Valor · Margem** editáveis com binding bidirecional pivotando no Custo `[V0]` | AR-PROD-006/007/008 | **ausente** no `/edit` — o card "Preço & Imposto" só tem `tax` e `tax_type`; o Blade tem os 6 campos `required` | 🔴 **maior perda da tela**; decisão [W]: CU novo ou Non-Goal? |
| Custo/Margem **somem** sem permissão | AR-PROD-015 `[V0]` | ausente (não há campo pra esconder) | 🔶 cai junto com o de cima |
| **Código read-only na edição** | AR-PROD-001 | `Edit.tsx:178-184` — SKU **editável**, sem `disabled` | 🔴 **divergência D-1** |
| **Última Alteração** (timestamp de auditoria) | AR-PROD-004 | `touch()` grava (`:1089`) mas a tela **não exibe** | 🟡 dado existe, UI não mostra |
| **Ativo S/N** (inativo some da venda) | AR-PROD-003 | `not_for_selling` só no controller; sem campo na tela | 🟡 + risco H4 |
| Navegação ← → entre registros | AR-PROD-024 | ausente | 🔶 Non-Goal provável |
| Abas (Fiscal · Estoque · Custos · Preço Especial · Anexo · Atividade) | AR-PROD-030 | ausente no `/edit` (existe `SellingPrices.tsx` separado) | 🔶 [F] está reconstruindo em abas (#4403) |
| Excluir = **soft-delete** (some da lista, fica no filtro) | AR-PROD-022 | charter declara Non-Goal ❌ "Deletar produto inline" | ✅ Non-Goal explícito, ok |
| Observações (texto livre) | AR-PROD-042 | `product_description` ✅ presente | ✅ |

---

## Lacunas que precisam do [W] (não inventei nada aqui)

- **L-1 — o SDD §6 não tem CU de "editar produto".** Os 12 `CU-PROD-*` cobrem cadastrar, variável,
  preço por tabela, estoque inicial, combo, importar, duplicar, quick-add, barras, multi-tenant,
  kardex e valor-em-estoque. **Editar não tem CU próprio** — derivei de `CU-PROD-01/04/09/10` por
  analogia + charter + AR-PROD. Isso é interpretação minha: **precisa de ratificação [W]** (ou de um
  `CU-PROD-13 — Editar produto` no SDD, que é onde ele deveria morar).
- **L-2 — Custo/Valor/Margem no `/edit` é CU novo ou Non-Goal?** O legado tem (AR-PROD-006/007/008,
  `[V0]`). O charter atual **não declara** nem uma coisa nem outra — é omissão, não decisão.
- **L-3 — SKU editável na edição contradiz AR-PROD-001.** Foi decisão consciente (o oimpresso permite
  trocar SKU) ou regressão? Não há registro. **Não inventei Non-Goal.**
- **L-4 — não rodei nenhum teste** (Pest = CT100, ADR 0062). Todo status acima é ⬜/🔴-hipótese, nunca ✅.

---

## APÊNDICE (post-hoc, após abrir a versão humana) — refinamento de H1/H2

> Escrito **depois** de travar o rascunho acima e ler `Edit.casos.md` + `ProdutoEditContratoTest.php`.
> Marcado como post-hoc de propósito: não é mérito do run cego.

**Correção de um erro meu:** na tabela de rastreabilidade marquei `UC-PEDIT-A3` como "⬜ sem teste".
**Está errado** — `tests/Feature/Produto/ProdutoEditContratoTest.php` já cobre GET **e** PUT
cross-tenant. Eu tinha listado o arquivo na varredura e mesmo assim escrevi "sem teste": afirmei
status sem cruzar com o inventário que eu próprio tinha em mãos. É LC-08 na minha mão.

**H1/H2 não dependem da conversão `E_WARNING`→`ErrorException`.** O `vendor/` não está instalado
neste worktree (`ls vendor` → não existe), então **não pude verificar** o `HandleExceptions` do
Laravel. Mas o resultado é o mesmo pelos **dois** ramos, e muda **por tipo de produto**:

| `type` | Caminho a partir do payload do `Edit.tsx` | Desfecho lido |
|---|---|---|
| `single` (o caso comum) | `:1112` `$request->only([...])` devolve `[]` → `$single_data['single_variation_id']` ausente → `Variation::find(null)` → `null` → `:1115` `$variation->sub_sku = ...` | **`\Error`** — e `\Error` **não** é `\Exception`, logo o `catch (:1187)` **não pega** → 500. Se o warning virar exceção antes (`:1037`), então rollback + `success:0` silencioso. **Nos dois ramos: não salva.** |
| `variable` | `:1126`/`:1132` são guardados por `! empty(...)` → pulam | **"salva"** — e é o pior caso: grava com `enable_stock=0` (H3), `not_for_selling=0`, `enable_sr_no=0`, `sub_unit_ids=null`, `secondary_unit_id=null`, `product_custom_field5..20=''` (H4) |
| `combo` | `:1154` `Variation::find($request->input('combo_variation_id'))` → `null` → `:1155` | mesmo `\Error` do `single` |

**Consequência pro contraste com a versão humana:** o `UC-PEDIT-01` (variações preservadas) do humano
**passaria verde** no ramo `variable` — as guardas `! empty()` realmente protegem a grade. O dano no
`variable` não é na grade, é no **cabeçalho** (H3/H4). Ou seja: o UC humano defende um contrato real,
mas verde nele **não** implica tela sã.

**Por que ninguém tropeçou nisso ainda (hipótese com recibo):** `Edit.charter.md` está
`status: draft` (`grep -n "^status:" resources/js/Pages/Produto/*.charter.md` → só o `Index` é `live`)
e a `PARIDADE-charter-vs-legado.md:18` registra *"as 8 telas Inertia existem mas nenhuma é `live`"*.
A tela nunca passou por smoke real — e o Blade legado (que manda o payload completo) é o que roda em
produção.

**Colunas confirmadas** (`git grep -n ... -- database/migrations`): `preparation_time_in_minutes`
(`2022_08_25_132707`), `not_for_selling` (`2019_07_22_152649`), `product_custom_field5..20`
(`2023_04_17_155216`). Não são campos fantasma.

---

## ⚠️ Correção de severidade — "alcançável em produção" era FALSO (2026-07-24, mesma sessão)

> **Quem errou:** eu ([CC]), no **commit message** que introduziu este arquivo (`e5ff0f39`) e no
> relato em chat. O corpo deste documento (produzido pelo agent) **não** carrega a afirmação — ele
> já dizia o certo: *"o Blade legado é o que roda em produção"*. A correção mora aqui porque o
> histórico não se reescreve (force-push bloqueado por hook, e com razão).

**O que eu afirmei:** *"alcançável em produção — `edit()` só devolve React com header `X-Inertia`, e
`Produto/Show.tsx:114` tem `<Link>` Inertia pro `/products/{id}/edit`; `<Link>` manda esse header."*

**Por que é falso — varredura parcial apresentada como conclusão.** Verifiquei o salto
`Show → Edit` e **não verifiquei se o `Show` em si é alcançável**. É a classe LC-08 de novo
(§5 2026-07-15: *"apresentar achado sem varrer TODOS os consumidores e dizer o número"*).

**Medido depois, e é o que fecha a conta:**

| Fato | Recibo |
|---|---|
| As telas do Produto são **duais** — `X-Inertia` → React, senão Blade ("coexistência opt-in", [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) | `ProductController.php:342` (`index`) · `:909` (`edit`) |
| A sidebar do cockpit usa **`<a href>` puro**, não `<Link>` do Inertia → reload completo → **não manda `X-Inertia`** | `resources/js/Components/cockpit/Sidebar.tsx:489` |
| Todos os `<Link href="/products">` vivem **dentro das próprias páginas React do Produto** — circular, sem porta de entrada | `grep -rnE 'href=[\"'"'"'`]/products' resources/js/` → 10 hits, **10 em `Pages/Produto/**`** |

**Conclusão correta:** as telas React do Produto são **inalcançáveis hoje**. O que roda em produção
é o Blade — confirmado por [F] em 2026-07-24: *"no produto o que está funcionando hoje é o blade, e
vamos migrar para react"*.

### O achado NÃO morre — muda de natureza

Continua real e medido (`Edit.tsx` manda 18 chaves; `update()` lê 33+ via `$request->only()` mais
`$request->input()`; 4 flags viram zero na ausência — `enable_stock` L76-79, `not_for_selling` L82,
`enable_sr_no` L101-104, `sub_unit_ids` L71). Mas o status correto é:

> **bloqueador de migração**, não incidente de produção.

É a **definição de pronto** da tela React: enquanto o payload não fechar, ligar a tela significa
zerar estoque no primeiro save. Encaixa no MWART ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) —
o cutover F5 só depois da paridade, e este é um item duro dessa paridade.

**Segue valendo a REGRA MESTRE** (`proibicoes.md` §CÁLCULO DE VALOR ou ESTOQUE): leitura de código
não é prova de runtime. O instrumento que converte isto em fato é o
`ProdutoEditPayloadContratoTest` failing-first, na lane `PHP / Pest (Estoque · MySQL)` do CI.
