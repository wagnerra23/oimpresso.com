---
title: "Stock adjustment — fora do escopo contract tests (ADR 0205 § matriz opcional)"
type: session
date: 2026-05-27
author: Claude (Opus 4.7) — sub-agent paralelo wave 2
status: complete
audience: Wagner + Felipe + Maiara (próximas iterações contract tests)
related_adrs:
  - 0205
source_files:
  - "app/Http/Controllers/StockAdjustmentController.php"
  - "resources/js/Pages/StockAdjustment/Create.tsx"
  - "routes/web.php (linhas 525-527)"
---

# Contract test Stock adjustment — decisão: NÃO criar fixture

> Investigação seguindo ADR 0205 matriz "Quando criar fixture (obrigatório vs opcional)".

## TL;DR

Stock adjustment **NÃO atende critério obrigatório** do ADR 0205 — é tela CRUD tradicional full-form Save+Redirect, **sem endpoints PATCH per-field autosave**. Classifica como ⚪ opcional na matriz; bug silencioso de mismatch chave/valor não pode ocorrer porque usuário vê erro de validação direto ao tentar submeter o form inteiro.

**Decisão:** não criar fixture agora. Se Stock adjustment for migrado pra padrão drawer com autosave (similar ao Cliente drawer 760), criar fixture nessa ocasião como parte do PR de migração.

## Investigação

### 1. Controller — `app/Http/Controllers/StockAdjustmentController.php`

| Método | Estado | Pattern |
|---|---|---|
| `index` (linha 47) | Implementado | Datatable AJAX listing + Blade view |
| `create` (linha ~260) | Implementado | Retorna `Inertia::render('StockAdjustment/Create', ...)` |
| `store` (linha 288) | Implementado | **Single POST submit** → `return redirect('stock-adjustments')` (linha 382). Cria `Transaction` + `stock_adjustment_lines` em DB transaction. |
| `edit` (linha 423) | **VAZIO** (stub `//`) | Não implementado |
| `update` (linha 435) | **VAZIO** (stub `//`) | Não implementado |
| `destroy` (linha 446) | Implementado | DELETE AJAX |
| `getProductRow` (linha 507) | Implementado | POST lookup AJAX — retorna Blade partial HTML pra preencher linha do form (lookup de produto), **não persiste nada** |
| `removeExpiredStock` (linha 547) | Implementado | GET — cria um stock_adjustment derivado a partir de purchase_line expirada (action, não autosave de campo) |

### 2. Rotas — `routes/web.php` linhas 525-527

```php
Route::get('/stock-adjustments/remove-expired-stock/{purchase_line_id}', ...)
Route::post('/stock-adjustments/get_product_row', ...)
Route::resource('stock-adjustments', StockAdjustmentController::class)
```

`Route::resource` gera: GET index/create/show, POST store, PUT/PATCH update, DELETE destroy.

**Não há rota PATCH per-field** (`/stock-adjustments/{id}/<aba>` ou similar). PATCH/PUT do `Route::resource` mapeia pra `update()` que **está vazio** — efetivamente inoperante.

### 3. Frontend — `resources/js/Pages/StockAdjustment/Create.tsx`

Tem `Pages/StockAdjustment/Create.tsx` Inertia (charter `Create.charter.md` ao lado). Mas o pattern é:

```tsx
const form = useForm({ location_id, ref_no, transaction_date, adjustment_type,
                       additional_notes, total_amount_recovered, final_total, products });

const enviar = (e) => {
  e.preventDefault();
  form.transform((dados) => ({ ...dados, products: linhas, final_total: totalFinal }));
  form.post('/stock-adjustments', { forceFormData: true, preserveScroll: true });
};
```

**Single `form.post()` no submit do `<form>`** — Save+Redirect tradicional. Nenhum `useEffect` debounced disparando PATCH por campo. Nenhum hook tipo `useAutosave`. O `getProductRow` é AJAX de lookup (carrega dados do produto pra exibir na linha), não persistência.

## Por que NÃO atende ADR 0205 § obrigatório

Tabela do ADR 0205 (literal):

| Cenário | Obrigatório? |
|---|---|
| Tela nova com 2+ endpoints PATCH autosave | ✅ obrigatório |
| Tela existente sem fixture mas modificada (Controller PATCH novo) | ✅ obrigatório |
| Modificação em validator existente (campo novo, alias novo) | ✅ atualizar fixture |
| **Tela CRUD tradicional (form full-page Save+Redirect, não autosave)** | ⚪ **opcional** |
| Tela read-only | ❌ não aplicável |

Stock adjustment cai na 4ª linha. ADR 0205 motiva explicitamente: **"não causa bug silencioso pq usuário vê erro de validação"** — ao submeter `form.post()`, validator falha = mensagem visível ao usuário = bug aparente (não silencioso tipo "badge Salvo verde mas dado some").

Os 5 bugs originais do drawer Cliente (sessão 2026-05-27) **só foram possíveis** porque o pattern PATCH per-field + badge "Salvo" mascarava o no-op `Eloquent::update([])`. Em Save+Redirect, esse mascaramento não existe.

### Endpoints AJAX restantes — por que não contam

- `getProductRow` (POST) — **lookup**, retorna HTML partial pra renderizar linha. Não grava em DB. Equivalente a um endpoint `/api/products/{id}` GET disfarçado de POST.
- `removeExpiredStock` (GET) — **action derivada**, cria stock_adjustment a partir de purchase_line expirada com payload server-side fixo. Não há "campos do usuário" pra mapear send→recv; o input é apenas o ID na URL.

Nenhum dos dois é "autosave de campos persistidos" no sentido do ADR 0205.

## Recomendação

1. **Não criar fixture agora** — não passaria pelo critério P1 do ADR (fixture = fonte da verdade do contrato autosave; aqui não há autosave).
2. **Gatilho futuro:** se Stock adjustment for migrado pra padrão drawer/autosave (similar Cliente drawer 760 — ADR 0179), criar fixture COMO PARTE do PR de migração. Adicionar trigger explícito no ADR 0205 review_triggers se Wagner achar útil.
3. **Cobertura alternativa** (já existe): testes Feature/Unit tradicionais cobrem `store` (validação payload, criação Transaction, decreaseProductQuantity, mapPurchaseSell) — patterns clássicos de CRUD test, não-contract.

## Telas adjacentes pra contraste

| Tela | Pattern controller | Autosave? | Fixture canon? |
|---|---|---|---|
| Cliente drawer (Wave A) | PATCH per-field (`/cliente/{id}/<aba>`) | ✅ sim — badge "Salvo" | ✅ #1791 |
| ServiceOrder/Edit | PUT form-submit + roundtrip | parcial (PUT único) | ✅ #1795 |
| Sells/Create quick-add | POST `/contacts` 201 | parcial (quick-add inline) | ✅ #1797 |
| **Stock adjustment** | **POST store → redirect** | **❌ não** | **❌ não aplica** |
| Compras/Create | POST `/purchases` redirect | parcial (apenas quick-add Fornecedor inline) | ✅ #1802 (parcial) |

Stock adjustment está na mesma classe de Compras/Create **menos** o quick-add Fornecedor inline. Compras só tem fixture pra cobrir o sub-endpoint quick-add (que É autosave-like inline). Stock adjustment não tem nenhum sub-endpoint análogo.

## Refs

- [ADR 0205](../decisions/0205-contract-tests-autosave-padrao-canonico.md) § Quando criar fixture (matriz)
- [Session 2026-05-27 framework rollout](2026-05-27-contract-tests-framework-rollout.md) — 8 telas cobertas
- `tests/Contract/README.md` — receita prática
- `app/Http/Controllers/StockAdjustmentController.php` linhas 288/423/435/507/547 (investigação)
- `resources/js/Pages/StockAdjustment/Create.tsx` linhas 68/121 (form.post single submit)
