---
sessao: "03"
titulo: "Recibo — repair.create / repair.edit saem do Blade (eram métodos sem rota)"
autor: "[CL]"
data: 2026-10-05
base: origin/main cb1fe1d6f4
thread: 03-repair-form.md
veredito: "entregue — RepairController@create e @edit removidos; o cadastro de reparo é a venda do PDV com sub_type=repair (D1); as 2 views Blade órfãs saem num PR seguinte"
---

# _saída 03 · Cadastro de reparo fora do Blade

## Decisão que guiou
[W] 2026-10-01, D1 em `_DECISOES-W-2026-10-01b.md`: *"é uma venda, tipo de venda igual ao OS auto"*.
O cadastro de reparo (produtos, imposto, pagamento) é **venda**. Não redireciona pro `JobSheet`.

## O que a medição mostrou antes de editar
Os dois métodos que a ficha manda tirar do Blade **nunca tiveram rota**:

- `Modules/Repair/Routes/web.php` registra `Route::resource('/repair', …)->except(['create', 'edit'])`,
  e o `except` está lá desde o import do repositório (`e44a381361`, 2025-05-16).
- Nenhum `action([RepairController::class, 'create'|'edit'])` no repo, nenhum `@include` das views
  `repair::repair.create` / `repair::repair.edit`.

O caminho de venda que a D1 pede **já existe e já é o usado**:

| ação | destino vivo | quem prova |
|---|---|---|
| nova venda de reparo | `/pos/create?sub_type=repair` (menu do Repair, `SellPosController@create`) | `tests/Feature/Sells/SellsRepairSubtipoContratoTest.php` |
| editar venda de reparo | `/repair/repair/{id}/edit` → `editarVenda` → `/pos/{id}/edit?sub_type=repair` (#8456, #8482) | UC-RSHW-05/06 em `RepairShowContratoTest.php` |

Então "sair do Blade" aqui é **remover código morto**, não construir Page. Uma `Pages/Repair/Create`
seria o segundo formulário de venda, ao lado do PDV que a D1 manda usar.

## O que mudou

| arquivo | mudança |
|---|---|
| `Modules/Repair/Http/Controllers/RepairController.php` | `create()` e `edit()` removidos (−296 linhas) e 6 `use` que só eles usavam (`Brands`, `Business`, `CustomerGroup`, `SellingPriceGroup`, `TransactionSellLine`, `Warranty`). Nenhuma linha adicionada. |
| `Modules/Repair/Tests/Feature/RepairFormVendaContratoTest.php` | novo: lê o **registry de rotas** e exige que nenhuma aponte pra `RepairController@create`/`@edit`, com controle positivo (`GET repair/repair/{id}/edit` → `editarVenda`) pra o assert de ausência não passar num roteador vazio. Sem banco. |
| `.github/workflows/verticais-pest.yml` | o teste novo entra na lista explícita da lane `PHP / Pest (Verticais · MySQL)`. |

Nada mudou em comportamento: os métodos não eram alcançáveis. Não toca valor nem estoque.

## Prova do json
`{"tipo": "nao_contem", "path": "${MOD}/Http/Controllers/RepairController.php", "padrao": "view('repair::repair.create')"}`
— o padrão não ocorre mais no arquivo. `view('repair::repair.edit')` também saiu.

## Placar
**entregue 1 de 1** (a prova do json) · ausentes: as views `Modules/Repair/Resources/views/repair/create.blade.php`
(401 linhas) e `edit.blade.php` (375 linhas), órfãs depois deste PR — saem num PR seguinte para este
ficar dentro de 300 linhas.

## Fora do escopo (não feito)
- As parciais Blade da seção 4 do índice (`edit_repair_status_modal`, `preview_label`, receipts).
- O fallback `view('repair::repair.index'|'show')` atrás do `render` (dual-render).
- Contrato visual: não há tela nova, então nada a aprovar no F1.5.
