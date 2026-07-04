---
page: /contacts/import
component: resources/js/Pages/Cliente/Import.tsx
related_us: [US-CRM-082, US-MWART-005]
owner: wagner
status: live
last_validated: "2026-06-24"
parent_module: Cliente
related_adrs: [110, 107, 93, 94, 104, 149]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/clientes-page.jsx"
  blueprint_screenshot_approval: "N/A (divergente)"
  derived_screens: [Import]
  divergence_from_blueprint: "wizard upload XLSX com preview, divergente do Index lista"
---

# Page Charter — /contacts/import (LIVE)

> **Status:** live — reconciliado de draft em 2026-06-24: Wagner confirmou biz=4 (ROTA LIVRE) em React em produção (flag `MWART_CLIENTE_IMPORT` ON; fallback Blade no `ContactController`). Backend canon: `ContactController::getImportContacts()` linha 1057. **Divergência ADR 0149:** Layout wizard 2-step (template + upload) totalmente distinto do Index lista. Não exige novo Cowork F1.5 porque é página utility/admin sem peso visual canônico — Wagner aprovou divergência genérica pra utility pages 2026-05-15.

## Mission

Wizard simples 2-step pra importar clientes em massa via XLSX/CSV. Substitui Blade `contact.import.blade.php` com UX moderno (drag-drop, progress bar, validação prévia).

## Goals

- Step 1: download template XLSX (27 colunas oficiais UPOS)
- Step 2: file upload com dropzone (drag/drop + click)
- Progress bar durante upload
- Notification banner pós-import (success/erro com count)
- Check zip extension server-side (hard error se ausente)
- Submit `multipart/form-data` POST `/contacts/import` (forceFormData useForm)

## Non-Goals

- ❌ Preview do XLSX antes de importar (vai num Step 3 futuro)
- ❌ Validação row-by-row antes do server (vai pro backend retornar count + errors)
- ❌ Importar de Google Sheets URL direto (futuro)
- ❌ Mapeamento custom de colunas (UPOS fixa 27 colunas)

## UX Targets

- File upload < 30s para 1k linhas
- Cabe em 1280px sem scroll horizontal

## Automation Anti-hooks

- ❌ Não envia email "import iniciado/concluído"
- ❌ Não dispara cron de validação CNPJ na Receita Federal automaticamente
- ❌ Não auto-cria customer_group novo (deve existir antes)

## Refs

- Backend: `ContactController::getImportContacts()` + `postImportContacts()`
- Pattern divergência: ADR 0149 §"Casos que NÃO se qualificam"
