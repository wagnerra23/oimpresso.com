---
sessao: "01"
titulo: "Permissões nos 3 controllers abertos (A-P1) — saída da thread"
autor: "[CL]"
criado: 2026-10-01
base: c70451a8c
thread: 03-permissoes.md
veredito: "entregue — os 3 controllers exigem permissão própria; backfill garante zero perda de acesso no deploy; 2 furos cross-tenant fechados no caminho."
---

# _saída 01 · A-P1 — Variações, Garantias e Etiquetas com permissão

## Decisões usadas
D1 e D2 respondidas por [W] em 2026-10-01 (`_DECISOES-W-2026-10-01.md`). O `00-INDICE.md` ainda marca `respondida: false` — a edição do json é do Cowork, não foi feita aqui.

## O que entrou

| arquivo | o quê |
|---|---|
| `app/Http/Controllers/VariationTemplateController.php` | `variation.view` (ou `.create`) no index · `.create` em create/store · `.update` em edit/update · `.delete` em destroy. **E** `update()` passou a achar o valor por `$variation->values()->find($key)`: o `VariationValueTemplate::find($key)` cru renomeava valor de variação de outro negócio pelo id. |
| `app/Http/Controllers/WarrantyController.php` | `warranty.view` (ou `.create`) no index · `.create` · `.update` · `.delete` (o `destroy` segue vazio, só ganhou o gate). |
| `app/Http/Controllers/LabelsController.php` | `print_labels.access` em `show`, `addProductRow`, `preview`. `preview()` resolve o `Barcode` por `business_id` **ou** `NULL` (o mesmo recorte do select de `show()`); fora do recorte → 404, antes do `try` que engoliria o erro. |
| `database/migrations/2026_10_01_120000_add_variation_warranty_print_labels_permissions.php` | cria as 9 permissões e faz o backfill (tabela abaixo). Idempotente. |
| `tests/Feature/Produto/PermissoesCadastrosEtiquetasTest.php` | 403 sem / 200 com nas 3 telas · `delete` exige `variation.delete` · cross-tenant 98×99 em variação, valor de variação, garantia e configuração de etiqueta · backfill (2 execuções, idempotência). |

## Backfill — quem ganha o quê no deploy

Medido nas portas de entrada da UI em `c70451a8c` (não é a D2: é o status quo, pra ninguém perder acesso).

| permissão nova | papéis que recebem | por quê |
|---|---|---|
| `variation.view/create/update/delete` | quem tem `product.create` | "Variações" no `AdminSidebarMenu` só aparece pra `product.create`; quem chegava tinha o CRUD inteiro, com excluir |
| `warranty.view/create/update/delete` | quem tem qualquer uma das 8 que abrem o menu Produtos (`product.view/create`, `brand.view/create`, `unit.view/create`, `category.view/create`) | o item "Garantias" não tem condição própria |
| `print_labels.access` | **todo papel existente** | a ação "Etiquetas/Rótulos" aparece sem permissão nenhuma em toda linha de compra (`PurchaseController:141`, `Purchase/Index.tsx`, `Compras/AcoesDropdown.tsx`) — hoje qualquer usuário imprime etiqueta |

`Admin#{biz}` não depende disso (`Gate::before`). NÃO MEDI em prod quantos papéis há em cada linha — a query de leitura pura está no docblock da migration.

## Provas do json
- `VariationTemplateController.php` contém `->can('` ✔
- `WarrantyController.php` contém `->can('` ✔
- `LabelsController.php` contém `->can('` ✔

## Pendente (não feito, com o porquê)
1. **Editor de papéis não oferece as 9 permissões novas.** `resources/views/role/{create,edit}.blade.php` + `app/Utils/PermissionCatalog::CORE` estão fora do prefixo. Efeito: papéis existentes ficam com o backfill (o save preserva permissão não-ofertada — `RoleController::__preservaNaoOfertadas`), mas **papel criado depois do deploy nasce sem** elas, e o admin não consegue aplicar a D2 pela tela. Precisa de PR próprio (catálogo + as duas views juntos, senão o `PermissionCatalogSyncTest` quebra).
2. **D2 não foi aplicada por papel** — aplicá-la tiraria `*.delete` de quem tem hoje. Fica pro admin de cada negócio depois do item 1.
3. **Errata do prefixo:** a ficha pede "seeder idempotente" e o prefixo lista `database/seeders/`; seeder não roda no deploy. Entrou como **migration** (instrução [W] desta thread: "mudança de DB em prod via migration idempotente").
4. O trigger da lane `estoque-pest.yml` não inclui os 3 controllers (só `tests/Feature/Produto/**`): mexer só no controller não dispara este teste no `main`. Workflow fora do prefixo.

## PR
(preenchido no corpo do PR)
