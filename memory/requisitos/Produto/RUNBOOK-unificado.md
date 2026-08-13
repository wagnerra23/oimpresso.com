---
title: "RUNBOOK — Catálogo Unificado (/products/unificado)"
module: Produto
tela: Produto/Unificado/Index
owner: W
status: ativo
last_validated: "2026-08-13"
preconditions:
  - "Usuário autenticado com product.view OU product.create no business ativo"
  - "Lane de teste: Estoque · MySQL (tests/Feature/Produto/) — nunca local, nunca biz=4"
steps:
  - "Conferir o contrato de visibilidade em Index.casos.md (UC-PUNI-01..06)"
  - "Rodar ProdutoUnificadoContratoTest no CT 100"
  - "Smoke da tela nos 3 perfis de permissão"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0093-multi-tenant-isolation-tier-0
  - 0107-emendation-0104-visual-comparison-gate-f3
---

# RUNBOOK — Catálogo Unificado (`/products/unificado`)

> **Por que este arquivo nasce agora (2026-08-13):** ele é a **F1 PLAN** do MWART
> ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)) que nunca foi feita
> pra esta tela. A tela existe desde 2026-05-09 (charter `draft`) e ganhou contrato em
> `Index.casos.md` (PR #5597), mas sem RUNBOOK o hook `block-mwart-violation.mjs` **bloqueia**
> qualquer Edit no `.tsx` — corretamente, porque F1 vem antes de F3. Este documento paga a F1
> pra que o gate de visibilidade (UC-PUNI-01..06) possa ser fechado no frontend.
>
> ⚠️ Não é RUNBOOK de migração Blade→React: **não existe Blade equivalente** desta tela. O
> `/products` (lista) é que tem par Blade. Aqui a origem é o protótipo Cowork
> `prototipo-ui/cowork/prototipo-ui-patch/prototipos/produto/produto-app.jsx`, declarado no
> `related_prototype` do charter.

## 1. O que a tela é

Cockpit denso com **5 sub-telas numa rota só**, trocadas por partial reload (`setSubTela`):

| Sub-tela | Prop | Origem |
|---|---|---|
| Produtos | `produtos` | `App\Product` + `App\Variation` (variação DUMMY ou a 1ª) |
| Categorias | `categorias` | `App\Category` + `COUNT(products)` por leftJoin |
| Insumos · BOM | `insumos` | `App\Product` com `not_for_selling = 1` |
| Tabelas de preço | `tabelas` | `App\SellingPriceGroup` |
| Histórico de uso | `historico` | `App\TransactionSellLine` (30d, vendas `final`) |

Backend: [`app/Http/Controllers/ProdutoUnificadoController.php`](../../../app/Http/Controllers/ProdutoUnificadoController.php)
(mora em `app/`, **não** em `Modules/Produto` — o domínio produto é UltimatePOS herdado).
Frontend: [`resources/js/Pages/Produto/Unificado/Index.tsx`](../../../resources/js/Pages/Produto/Unificado/Index.tsx).
Rota: `routes/web.php` → `products.unificado.index`.

## 2. Contrato de visibilidade — a razão de existir deste RUNBOOK

A tela reúne, numa rota só, **tudo que as outras telas do Produto gateiam separadamente**: custo,
preço de venda, tabelas de preço e composição. O contrato está em
[`Index.casos.md`](../../../resources/js/Pages/Produto/Unificado/Index.casos.md) (UC-PUNI-01..06);
aqui fica só o mapa operacional de quem gateia o quê:

| Dado | Permissão | Onde o gate mora |
|---|---|---|
| A tela inteira | `product.view` **ou** `product.create` | `index()` — `abort(403)`, mesma semântica de `ProductController@index:66` |
| `cost` (linha) · `cost` (insumo) | `view_purchase_price` | `produtos()` / `insumos()` — a chave **não é emitida** |
| `price` (linha) | `access_default_selling_price` | `produtos()` — a chave **não é emitida** |
| `margin` | **as duas** (é derivada de ambos) | `produtos()` — entregar margem com um dos dois entrega o outro por dedução |
| `value` (Histórico) | `access_default_selling_price` | `historico()` — porta lateral do mesmo dado |
| `tabelas` (sub-tela) | `access_default_selling_price` | `index()` — a prop vira `[]` |
| `insumos` + `bomCount` | módulo Manufacturing no pacote **e** `manufacturing.access_recipe` | `podeVerComposicao()` — camadas 1 e 3 |

**A regra é ausência, não campo vazio** (`AR-PROD-015`: os campos *somem* da tela). Por isso o
backend **omite a chave** e o frontend esconde a **coluna inteira** — nunca renderiza `R$ 0,00`
nem `—` no lugar de um valor que o usuário não pode ver: zero é uma afirmação sobre o custo.

**Camadas 1 e 3 são as canônicas** ([feedback-habilitar-modulo-por-business](../../reference/feedback-habilitar-modulo-por-business.md)) —
nenhuma permissão nova foi criada, e **nunca** `if ($business_id === N)`.

## 3. Rodar o contrato (CT 100 — nunca local)

```bash
tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=ProdutoUnificadoContratoTest"
```

Espera-se **7 passed** (UC-PUNI-01, 02, 02b, 03, 04, 05, 06). `0 failed` **não** prova execução —
leia o número de *assertions*; se vier `skipped`, o schema/seed do container está incompleto e o
veredito é a lane `Estoque · MySQL` do CI, não este comando.

O arquivo **saiu** de `.github/estoque-pest-quarantine.list` quando o gate foi fechado. Se voltar a
falhar, o certo é consertar o controller — **não** reintroduzir a linha na quarentena.

## 4. Smoke manual — 3 perfis, porque 1 não prova nada

O bug que este RUNBOOK acompanha só aparece em quem **não** tem permissão; testar como admin passa
verde por construção.

| Perfil | Como montar | O que tem que acontecer |
|---|---|---|
| Admin (tem tudo) | usuário padrão do business | as 5 sub-telas iguais a antes — **nenhuma diferença visual** |
| Sem custo | revogar `view_purchase_price` no papel (`/roles/{id}/edit`) | coluna "Custo · margem" some da tabela; o switch "Mostrar custo" some do painel Ajustes; coluna Margem some de Tabelas de preço |
| Sem preço | revogar `access_default_selling_price` | coluna "Preço" some; sub-tela "Tabelas de preço" fica vazia com aviso; coluna "Valor" some do Histórico |
| Sem nada de Produto | revogar `product.view` e `product.create` | a rota devolve **403** |

Evidência aceita: `curl -sv` mostrando o status **ou** screenshot da tela nos perfis 2 e 3.
Declarar "funcionando" sem isso viola a R1 do [PROTOCOLO-WAGNER-SEMPRE](../../reference/PROTOCOLO-WAGNER-SEMPRE.md).

## 5. Pegadinhas já pagas (não redescobrir)

1. **`fmtBRL(undefined)` crasha a tela.** `n.toLocaleString` em `undefined` lança `TypeError` e o
   React derruba a página inteira. Gatear só o backend, sem tornar os campos opcionais no `.tsx`,
   troca um vazamento por uma tela branca. Os tipos `price`/`cost`/`margin`/`value`/`bomCount` são
   **opcionais** de propósito.
2. **`categorias` dá 500 se usar `Category::withCount('products')`** — `App\Category` não declara
   `products()`. A contagem vem de `leftJoin` + `COUNT`, com `categories.` qualificado em **toda**
   cláusula (senão: *"Column 'business_id' in where clause is ambiguous"*).
3. **Raiz de categoria é `parent_id = 0`, nunca `NULL`** — `whereNull` devolve lista vazia com o
   banco cheio.
4. **`stockQty` é `null` fixo** (TODO de somar `variation_location_details`) — a coluna mostra `—`,
   nunca `0`.
5. **Partial reload:** `setSubTela` pede `only: ['tela','filters','insumos','tabelas','historico']`.
   Prop nova que a tela precise **em toda sub-tela** (como `permissoes`) tem que ser eager, senão
   some na troca de aba.

## 6. Resíduos declarados (abertos — não são regressão)

- **Contagem de categorias não escopa `products` por `business_id`** no leftJoin
  (`ProdutoUnificadoController` §categorias). Herdado do helper de produção; `category_id` é por
  business na prática, então não há vazamento conhecido — mas não está defendido. `UC-PUNI-05`
  vigia o limite superior (`soma ≤ total do business`). Mudar a semântica da lista viva é decisão [W].
- **`mult` das tabelas de preço é `1.00` hardcoded** — o multiplicador não existe nativamente no
  UltimatePOS ([ADR ARQ-0001](adr/arq/0001-selling-price-multiplier.md), US-PROD-022).
- **`bomCount` é literal `0`** — a composição ainda não é servida; `UC-PUNI-04` é preventivo, pra
  que ela **nasça** gated quando alguém plugar a query.
- **KPIs `populares`, `margem_media`, `sem_giro` são `0`** (TODOs de agregação).
- **Sem `Inertia::defer`** — a tela é a exceção citada no SPEC §Dívidas.
- **Charter segue `status: draft`** — promover pra `live` exige [W] aprovar Non-Goals +
  Anti-hooks (US-PROD-023).
