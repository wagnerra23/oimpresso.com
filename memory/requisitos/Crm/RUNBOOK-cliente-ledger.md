# RUNBOOK — Migração MWART /contacts/ledger → Cliente/Ledger (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.ledger` (3 formatos: padrão / format_2 / format_3)
- **Inertia:** `resources/js/Pages/Cliente/Ledger.tsx`
- **Controller:** `ContactController::getLedger()` (linha 1326)
- **Flag:** `mwart.cliente_ledger.enabled`

## 2. Objetivo
Extrato financeiro denso (tabela débito/crédito/saldo) + filtros + export PDF/Excel.

## 3. Divergência ADR 0149
Tabela financeira densa — divergente do Index card layout. Aprovado utility report.

## 4. Action PDF
- Action `?action=pdf` mantém fluxo legacy (gera PDF via mPDF)
- Branch Inertia só atua se `action !== 'pdf'` — preserve PDF flow

## 5. Multi-tenant
- `getLedgerDetails($contact_id, ...)` no TransactionUtil já scope por contact (que tem business_id)
- Cross-tenant test obrigatório

## 6. Variáveis env
```env
MWART_CLIENTE_LEDGER=false
MWART_CLIENTE_LEDGER_BIZ=1
```

## 7. Pest tests
- `Wave1LedgerBaselineTest.php`
- `Wave1LedgerInertiaTest.php`

## 8. Filtros canon
- `start_date` / `end_date` (YYYY-MM-DD)
- `format` (`format_1` | `format_2` | `format_3`)
- `location_id` (multi-location UPOS canon)
