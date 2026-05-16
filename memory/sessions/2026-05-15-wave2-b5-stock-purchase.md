---
data: 2026-05-15
agent: W2-D (Wave2 B5 Stock/Purchase MWART massivo)
worktree: .claude/worktrees/happy-golick-daeae6
escopo: 6 telas MWART blade → Inertia/React dual path
status: F3 implementado (aguarda smoke Wagner + Pest run no parent)
adr_refs: [0104 MWART, 0093 Tier 0, 0114 gate visual, 0149 pattern reuse]
---

# Wave2 B5 — Stock/Purchase MWART massivo

## Objetivo

Migrar 6 telas Blade → Inertia/React via dual path opt-in `?v=2`, sem quebrar Blade legacy.
Áreas isoladas em relação aos agents W1-A (Sells), W1-B (Cliente), W2-C (Produto), W3-E (Repair).

## Telas entregues

| # | Tela | Tipo | Arquivo TSX | Controller dual path | Pest baseline + Inertia | RUNBOOK | visual-comparison | Charter |
|---|---|---|---|---|---|---|---|---|
| 1 | Purchase/Create | FORM CREATE | ✅ Create.tsx (608 linhas) | ✅ PurchaseController@createInertia | ✅ Wave2Create*Test | ✅ | ✅ | ✅ Create.charter.md |
| 2 | Purchase/Edit | FORM EDIT | ✅ Edit.tsx (475 linhas) | ✅ PurchaseController@editInertia | ✅ Wave2Edit*Test | ✅ | ✅ | ✅ Edit.charter.md |
| 3 | StockTransfer/Index | LIST | ✅ Index.tsx (264 linhas) | ✅ StockTransferController@indexInertia | ✅ Wave2StockTransferIndex*Test | ✅ | ✅ | ✅ Index.charter.md |
| 4 | StockTransfer/Create | FORM CREATE | ✅ Create.tsx (385 linhas) | ✅ StockTransferController@createInertia | ✅ Wave2StockTransferCreate*Test | ✅ | ✅ | ✅ Create.charter.md |
| 5 | StockAdjustment/Index | LIST | ✅ Index.tsx (235 linhas) | ✅ StockAdjustmentController@indexInertia | ✅ Wave2StockAdjustmentIndex*Test | ✅ | ✅ | ✅ Index.charter.md |
| 6 | StockAdjustment/Create | FORM CREATE | ✅ Create.tsx (370 linhas) | ✅ StockAdjustmentController@createInertia | ✅ Wave2StockAdjustmentCreate*Test | ✅ | ✅ | ✅ Create.charter.md |

## Padrão arquitetural aplicado (consistente nas 6)

### Frontend
- AppShellV2 layout persistent (ADR 0094)
- PageHeader shared (ADR 0110)
- shadcn primitives: Card / CardHeader / CardContent, Button, Input, Label, Textarea
- `useForm()` Inertia para FORMs com `forceFormData: true` (compatibilidade FormData backend legacy)
- `useMemo` para totais reativos
- TypeScript estrito (zero `any`)
- PT-BR em todo UI
- Lucide icons

### Backend dual path
- Branch detection: `request()->header('X-Inertia') || request()->query('v') === '2'`
- Método privado `*Inertia()` recebe `$business_id` como parâmetro (Tier 0 IRREVOGÁVEL)
- Blade legacy preservado intacto
- AJAX DataTables (Yajra) path preservado nos Index controllers

### Multi-tenant Tier 0 IRREVOGÁVEL ([ADR 0093](../decisions/0093-multi-tenant-isolation-tier-0.md))
- 100% das queries scopadas via `where('business_id', $business_id)` ou `where('transactions.business_id', $business_id)`
- `permitted_locations` filter preservado em Index/Create de Stock*
- `view_own_purchase` ownership scope preservado
- Zero `withoutGlobalScopes` introduzido
- Zero `business_id` hardcoded em props/tsx

### Validações UX críticas client-side
- **R-XFER-004** (StockTransfer/Create): origem ≠ destino — button disabled + AlertCircle
- **R-ADJ-003** (StockAdjustment/Create): total_amount_recovered ≤ final_total — button disabled + AlertCircle
- Filtro inteligente: destino dropdown remove option = origem selecionada (StockTransfer)

## Arquivos criados/editados (todos verificados via `ls -la`)

### `resources/js/Pages/Purchase/` (2 novas TSX + 2 charters; Index/Show NÃO TOCADAS)
- ✅ `Create.tsx` (20379 bytes)
- ✅ `Create.charter.md` (1574 bytes)
- ✅ `Edit.tsx` (19400 bytes)
- ✅ `Edit.charter.md` (906 bytes)

### `resources/js/Pages/StockTransfer/` (pasta criada do zero)
- ✅ `Index.tsx` (10357 bytes)
- ✅ `Index.charter.md` (878 bytes)
- ✅ `Create.tsx` (15455 bytes)
- ✅ `Create.charter.md` (878 bytes)

### `resources/js/Pages/StockAdjustment/` (pasta criada do zero)
- ✅ `Index.tsx` (9218 bytes)
- ✅ `Index.charter.md` (775 bytes)
- ✅ `Create.tsx` (14461 bytes)
- ✅ `Create.charter.md` (803 bytes)

### Controllers (editados, somente métodos create/edit/index/createInertia/editInertia/indexInertia)
- ✅ `app/Http/Controllers/PurchaseController.php` (createInertia + editInertia adicionados)
- ✅ `app/Http/Controllers/StockTransferController.php` (use Inertia; indexInertia + createInertia adicionados)
- ✅ `app/Http/Controllers/StockAdjustmentController.php` (use Inertia; indexInertia + createInertia adicionados)

### Runbooks + visual-comparisons (6 + 6 = 12 docs)
- ✅ `memory/requisitos/Inventory/RUNBOOK-purchase-create.md` (4933 bytes)
- ✅ `memory/requisitos/Inventory/RUNBOOK-purchase-edit.md` (2223 bytes)
- ✅ `memory/requisitos/Inventory/RUNBOOK-stock-transfer-index.md` (1799 bytes)
- ✅ `memory/requisitos/Inventory/RUNBOOK-stock-transfer-create.md` (1926 bytes)
- ✅ `memory/requisitos/Inventory/RUNBOOK-stock-adjustment-index.md` (1418 bytes)
- ✅ `memory/requisitos/Inventory/RUNBOOK-stock-adjustment-create.md` (1448 bytes)
- ✅ `memory/requisitos/Inventory/purchase-create-visual-comparison.md`
- ✅ `memory/requisitos/Inventory/purchase-edit-visual-comparison.md`
- ✅ `memory/requisitos/Inventory/stock-transfer-index-visual-comparison.md`
- ✅ `memory/requisitos/Inventory/stock-transfer-create-visual-comparison.md`
- ✅ `memory/requisitos/Inventory/stock-adjustment-index-visual-comparison.md`
- ✅ `memory/requisitos/Inventory/stock-adjustment-create-visual-comparison.md`

### Pest tests (12 — 6 baseline F2 + 6 Inertia F4)
- ✅ `tests/Feature/Purchase/Wave2CreateBaselineTest.php`
- ✅ `tests/Feature/Purchase/Wave2CreateInertiaTest.php`
- ✅ `tests/Feature/Purchase/Wave2EditBaselineTest.php`
- ✅ `tests/Feature/Purchase/Wave2EditInertiaTest.php`
- ✅ `tests/Feature/Stock/Wave2StockTransferIndexBaselineTest.php`
- ✅ `tests/Feature/Stock/Wave2StockTransferIndexInertiaTest.php`
- ✅ `tests/Feature/Stock/Wave2StockTransferCreateBaselineTest.php`
- ✅ `tests/Feature/Stock/Wave2StockTransferCreateInertiaTest.php`
- ✅ `tests/Feature/Stock/Wave2StockAdjustmentIndexBaselineTest.php`
- ✅ `tests/Feature/Stock/Wave2StockAdjustmentIndexInertiaTest.php`
- ✅ `tests/Feature/Stock/Wave2StockAdjustmentCreateBaselineTest.php`
- ✅ `tests/Feature/Stock/Wave2StockAdjustmentCreateInertiaTest.php`

## Padrão `mwart_pattern_reuse` (ADR 0149) — todos os charters

Todos 6 charters declaram:
```yaml
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/prototipos/compras/" OR "prototipo-ui/prototipos/inventario-migracao/"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [tela]
  divergence_from_blueprint: "<curta justificativa OU 'none'>"
```

## Limitações MVP1 conscientes (todas marcadas em visual-comparisons)

1. **Autocomplete produto** → input texto manual. v0.2: reuso `ProductSearchAutocomplete` do Sells.
2. **Autocomplete fornecedor/cliente** → input ID. v0.2: reuso `CustomerSearchAutocomplete`.
3. **Sem pagamento embutido** em Purchase/Create — V2 (drawer separado).
4. **Sem custom_field_1..4** em Purchase/* — V2 dentro `<details>`.
5. **Sem upload document** em Purchase/* — V2.
6. **Sem batch/lot picker** em Stock*/* — depende Fase 2 ROADMAP Inventory.
7. **Sem update inline status** em StockTransfer/Index (`update_status_modal` legacy não migrado) — V2.
8. **Sem paginação real** nos LISTs — limit 200 hardcoded — V2 server-side.

## Pest local — NÃO rodado

Razão: PHP/Pest não disponíveis no worktree filho (junction Windows sem `vendor/`).
Tests são ESTRUTURAIS (file existence + grep de patterns) — rodam no parent worktree via `vendor/bin/pest`.

Esperativa: 12/12 estruturais devem passar quando parent rodar pois:
- Todos arquivos foram criados e verificados via `ls -la` (output acima provando)
- Patterns nos tests batem com conteúdo escrito (grep self-verificado)

## Áreas tocadas (zero overlap com agents irmãos)

✅ Dentro do escopo permitido:
- `resources/js/Pages/Purchase/{Create,Edit}{.tsx,.charter.md}` (Index/Show preservados)
- `resources/js/Pages/StockTransfer/` (pasta nova)
- `resources/js/Pages/StockAdjustment/` (pasta nova)
- `app/Http/Controllers/{Purchase,StockTransfer,StockAdjustment}Controller.php` (somente métodos create/edit/index/*Inertia)
- `memory/requisitos/Inventory/RUNBOOK-{purchase-*,stock-transfer-*,stock-adjustment-*}.md`
- `memory/requisitos/Inventory/{purchase-*,stock-*}-visual-comparison.md`
- `tests/Feature/Purchase/Wave2*Test.php`
- `tests/Feature/Stock/Wave2*Test.php`

✅ NÃO tocados (agents irmãos):
- Sells/ (W1-A)
- Customer/Cliente/ (W1-B)
- Product/Produto/ (W2-C tocou — vi RUNBOOK-produto-*)
- Repair/ (W3-E)

## Próximos passos (parent consolida)

1. **Pest local no parent** com `vendor/`: `php vendor/bin/pest tests/Feature/Purchase/Wave2* tests/Feature/Stock/Wave2*`
2. **Smoke Wagner biz=1** (cada uma das 6 telas com `?v=2`)
3. **Gate visual ADR 0114** — Wagner aprova screenshot
4. **PR único B5** com label `mwart-wave2` + Refs SPRINT-W2 PASSO B5

## Riscos conhecidos

- **Risco baixo:** dual path opt-in `?v=2` não afeta produção (default Blade).
- **Risco médio:** Purchase/Edit espera `purchase.purchase_lines` populado com `id` real — Controller `editInertia` faz mapeamento explícito.
- **Risco baixo:** FormData submission preserva semântica Blade `Form::open` → `useForm.post` aceita string keys idênticas.
- **Risco zero:** PurchaseController::index NÃO mexido (W1 já mergeou MWART Index pilot).

## ADR refs canônicos

- [0104 — MWART canônico](../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [0114 — Gate visual F3](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [0149 — Pattern reuse mwart_pattern_reuse](../decisions/0149-*.md) (assumido — ler nome exato no parent)
- [0110 — Cockpit V2 PageHeader canon](../decisions/0110-cockpit-v2.md)

## Linha do tempo

- 19:21 pré-flight (RUNBOOKs, Purchase/Index pattern, controllers, Cowork prototipos)
- 19:45 Purchase/Create entregue
- 19:49 Purchase/Edit entregue
- 19:52 StockTransfer/Index entregue
- 19:54 StockTransfer/Create entregue
- 19:56 StockAdjustment/Index entregue
- 19:58 StockAdjustment/Create entregue
- ~20:00 relatório final

**Tempo total**: ~40min, dentro do orçamento 6-8h dado pelo prompt.
