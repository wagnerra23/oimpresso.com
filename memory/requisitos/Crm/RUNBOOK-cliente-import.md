# RUNBOOK — Migração MWART /contacts/import → Cliente/Import (W1-B3)

## 1. Tela
- **Legacy:** Blade `contact.import` (form upload simples)
- **Inertia:** `resources/js/Pages/Cliente/Import.tsx`
- **Controller:** `ContactController::getImportContacts()` (linha 1057)
- **Flag:** `mwart.cliente_import.enabled`

## 2. Objetivo
Wizard 2-step (download template + upload com dropzone). UX moderno.

## 3. Divergência ADR 0149
Layout wizard divergente do Index list. Aprovado utility page.

## 4. Submit
- `forceFormData: true` em useForm
- POST `/contacts/import` (endpoint legacy preservado)
- Progress bar via Inertia useForm.progress

## 5. Multi-tenant
- Backend `postImportContacts()` força `business_id` da sessão em cada row
- Validação 27 colunas obrigatória

## 6. Pré-req
- PHP extension `zip` disponível (check server-side)
- Se ausente, exibe error banner Inertia

## 7. Variáveis env
```env
MWART_CLIENTE_IMPORT=false
MWART_CLIENTE_IMPORT_BIZ=1
```

## 8. Pest tests
- `Wave1ImportBaselineTest.php`
- `Wave1ImportInertiaTest.php`

## 9. PII
Não logar conteúdo do XLSX após processamento (LGPD).
