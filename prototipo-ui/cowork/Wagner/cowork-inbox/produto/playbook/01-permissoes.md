---
sessao: "01"
titulo: Permissões nos 3 controllers abertos
dono: "[CL]"
base: 89f32db43080
---
# 01 · A-P1 — fechar as 3 rotas sem permissão

**Revisado @89f32db43080 (arquivos lidos inteiros):** `VariationTemplateController` (8 KB) e `WarrantyController` (4,4 KB) não têm nenhum `can(`; `LabelsController` (9,5 KB) também não, e `preview()` faz `Barcode::find($barcode_setting)` sem `business_id`. `SellingPriceGroupController`, `ImportProductsController` e `ImportOpeningStockController` **já checam** `product.*` — ficam fora desta thread.

**Só abre com D1 e D2.**

- Seeder idempotente das permissões novas.
- `if (! auth()->user()->can('…')) abort(403, 'Unauthorized action.');` no formato do `UnitController`.
- `LabelsController::preview`: `Barcode` escopado por `business_id` (ou `whereNull` pro padrão do sistema, como `show()` já faz).
- Teste por controller: sem permissão → 403; com → 200.

## Prova
No JSON do índice. Recibo `_saida-01.md`.
