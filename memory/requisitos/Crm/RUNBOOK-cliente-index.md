# RUNBOOK — Migração MWART /contacts/customer → Cliente/Index (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.index` (DataTables jQuery)
- **Inertia destino:** `resources/js/Pages/Cliente/Index.tsx`
- **Controller method:** `ContactController::index()` (linha 63)
- **Flag MWART:** `mwart.cliente_index.enabled` + `cliente_index.business_ids`

## 2. Objetivo
Substituir listagem Blade jQuery por React/Inertia preservando endpoint legacy `/contacts/customer`. Foco em KPIs de relacionamento (OS abertas / atrasadas / valor total) + drawer detail.

## 3. Pré-flight checks
- [x] Charter Index.charter.md atualizado (ADR 0149 YAML)
- [x] Blueprint Cowork: `prototipo-ui/prototipos/clientes/cowork-app.jsx`
- [x] ADRs lidas: 0093 (multi-tenant), 0104 (MWART), 0107 (visual gate), 0149 (pattern reuse)

## 4. Risco / Rollback
- **Risco baixo:** Feature flag default OFF; rollback em <30s removendo env var.
- **Rollback:** `MWART_CLIENTE_INDEX=false` no .env

## 5. Multi-tenant
- `Contact::where('business_id', $business_id)` global scope ([ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md))
- Pest cross-tenant biz=1 vs biz=99 obrigatório

## 6. Variáveis de ambiente
```env
MWART_CLIENTE_INDEX=false              # default OFF
MWART_CLIENTE_INDEX_BIZ=1              # canary biz=1 (Wagner WR2)
```

## 7. Pest tests
- `tests/Feature/Cliente/Wave1IndexBaselineTest.php` — F2 controller baseline
- `tests/Feature/Cliente/Wave1IndexInertiaTest.php` — F4 structural .tsx

## 8. Rollout
1. Merge PR com flag OFF (este)
2. Wagner valida code review
3. Wagner liga `MWART_CLIENTE_INDEX=true` + `MWART_CLIENTE_INDEX_BIZ=1` em prod
4. Canary 7 dias biz=1 (WR2 Wagner)
5. Se OK → habilita biz=4 (ROTA LIVRE) após aviso Larissa

## 9. PII / LGPD
- `tax_number` mascarado server-side via `maskTaxNumber()` ANTES de ir pro client
- Nunca enviar plain digits (LGPD Art. 7º)

## 10. Defer obrigatório
- KPIs: `Inertia::defer(fn () => $this->buildClienteIndexKpis(...))`
- Customers paginate: `Inertia::defer(fn () => $this->buildClienteIndexCustomers(...))`

## 11. Sunset legacy
Após canary 30d sem incidente:
- Deletar `resources/views/contact/index.blade.php`
- Remover branch dual no `index()`
- Remover env `MWART_CLIENTE_INDEX*`
