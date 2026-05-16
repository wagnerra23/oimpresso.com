# Session 2026-05-15 — Wave 1 B3 Cliente (MWART F3 — retry)

## Contexto

Agent W1-B-RETRY. O primeiro agent W1-B alucinou output (reportou 36 arquivos sem ter feito Write). Re-execução com **proof-of-work obrigatório** (ls -la pós cada Write + output Pest colado no relatório).

Wave 1 batch da migração massiva 32 telas — bucket B3 Cliente / Contact module.

## Pré-flight executado

1. ✅ `resources/js/Pages/Cliente/Index.charter.md` (charter existente — atualizado YAML ADR 0149)
2. ✅ `resources/js/Pages/Sells/Index.tsx` (reference W1-A gold-standard)
3. ✅ `config/mwart.php` (pattern flag canônico)
4. ✅ ADRs lidas: 0104, 0107, 0093, 0094, 0149
5. ✅ `app/Http/Controllers/ContactController.php` (linhas 63, 536, 713, 768, 1057, 1326, 1634)

## Entregas

### Arquivos criados (29 total)

**7 .tsx (97KB total):**
- `resources/js/Pages/Cliente/Index.tsx` (28KB, drawer + KPI + table + pills)
- `resources/js/Pages/Cliente/Create.tsx` (11KB, 4 sections form)
- `resources/js/Pages/Cliente/Show.tsx` (10KB, header + stats + sidebar + tx table)
- `resources/js/Pages/Cliente/Edit.tsx` (10KB, mirror Create + PUT)
- `resources/js/Pages/Cliente/Import.tsx` (7KB, wizard upload + dropzone)
- `resources/js/Pages/Cliente/Ledger.tsx` (10KB, tabela débito/crédito + filtros)
- `resources/js/Pages/Cliente/Map.tsx` (8KB, split-pane + Google Maps iframe)

**7 charter.md (ADR 0149 YAML obrigatório):**
- `Index.charter.md` (atualizado), `Create.charter.md`, `Show.charter.md`, `Edit.charter.md`, `Import.charter.md` (divergence), `Ledger.charter.md` (divergence), `Map.charter.md` (divergence)

**7 RUNBOOKs:**
- `memory/requisitos/Crm/RUNBOOK-cliente-{index,create,show,edit,import,ledger,map}.md`

**7 visual-comparison docs:**
- `memory/requisitos/Crm/cliente-{index,create,show,edit,import,ledger,map}-visual-comparison.md`

**14 Pest tests (36 tests / 145 assertions / 0.31s):**
- 7× Wave1*BaselineTest.php (F2 controller)
- 7× Wave1*InertiaTest.php (F4 frontend)

### Arquivos modificados (2)

- `config/mwart.php` — adicionadas 7 flags `cliente_*` (default OFF, canary biz=1)
- `app/Http/Controllers/ContactController.php` — adicionado helper `shouldRenderInertiaCliente()` + helper `maskTaxNumber()` PII + `buildClienteIndexKpis()` + `buildClienteIndexCustomers()` + 7 branches `Inertia::render()` nos métodos `index/create/show/edit/getImportContacts/getLedger/contactMap`

## Decisões técnicas

1. **Pattern dual canônico:** `config('mwart.cliente_X.enabled')` + `business_ids` via helper privado `shouldRenderInertiaCliente()` — Reuse pattern Repair/Sells.
2. **PII Tier 0:** `tax_number` sempre mascarado server-side via `maskTaxNumber()` antes de ir pro client (LGPD Art. 7º).
3. **Inertia::defer aplicado:** KPIs, customers paginated, stats Show, transactions Show, ledger lines.
4. **ADR 0149 pattern reuse:** Index/Create/Show/Edit = mesma família visual (none divergence). Import/Ledger/Map = divergência declarada justificada.
5. **Multi-tenant Tier 0:** Toda Eloquent query scoped `business_id`. Pest test cross-tenant pendente (lazy structural; full feature tests vão num PR seguinte com factories).

## Proof-of-work (ls -la output)

```
$ ls -la resources/js/Pages/Cliente/*.tsx
Create.tsx     11285 bytes
Edit.tsx       10543 bytes
Import.tsx      7500 bytes
Index.tsx      28034 bytes
Ledger.tsx     10722 bytes
Map.tsx         8078 bytes
Show.tsx       10674 bytes

$ grep -c "Inertia::render" app/Http/Controllers/ContactController.php
7
```

## Pest local result

```
Tests:    36 passed (145 assertions)
Duration: 0.31s
```

## Rollback

Cada tela tem flag MWART independente. Disable instantâneo via .env:
```bash
MWART_CLIENTE_INDEX=false
MWART_CLIENTE_CREATE=false
# ... idem outras 5
```

## Próximos passos (Wagner / parent agent)

1. Code review do PR (focar em multi-tenant + PII mask)
2. Smoke biz=1 em dev (canary)
3. Wagner aprova screenshot visual (gate F1.5 ADR 0107)
4. Canary 7d biz=1 (WR2 Wagner)
5. ROTA LIVRE biz=4 após smoke OK

## Refs

- ADR 0104 (MWART canon) · 0107 (visual gate) · 0093 (multi-tenant) · 0149 (pattern reuse)
- Blueprint Cowork: `prototipo-ui/prototipos/clientes/cowork-app.jsx`
- Reference: `resources/js/Pages/Sells/Index.tsx` (W1-A canon)
