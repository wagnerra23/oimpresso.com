# Arquitetura

## 1. Objetivo

Useful for all kind of repair shops

## 2. Áreas funcionais

### 2.2. Core

**Controller(s):** `DashboardController`, `RepairController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `editRepairStatus`, `updateRepairStatus`, `deleteMedia`, `printLabel`, `printCustomerCopy`

_Descrição funcional:_ [TODO]

### 2.1. Customer Repair Status

**Controller(s):** `CustomerRepairStatusController`  
**Ações (2):** `index`, `postRepairStatus`

_Descrição funcional:_ [TODO]

### 2.3. Device Model

**Controller(s):** `DeviceModelController`  
**Ações (9):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getDeviceModels`, `getRepairChecklists`

_Descrição funcional:_ [TODO]

### 2.4. Job Sheet

**Controller(s):** `JobSheetController`  
**Ações (17):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `editStatus`, `updateStatus`, `deleteJobSheetImage`, `addParts`, `saveParts` _+ 5_

_Descrição funcional:_ [TODO]

### 2.5. Settings

**Controller(s):** `RepairSettingsController`  
**Ações (3):** `index`, `store`, `updateJobsheetSettings`

_Descrição funcional:_ [TODO]

### 2.6. Status

**Controller(s):** `RepairStatusController`  
**Ações (5):** `index`, `create`, `store`, `edit`, `update`

_Descrição funcional:_ [TODO]

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles
- **`get_pos_screen_view()`** — hook do UltimatePOS
- **`after_sale_saved()`** — hook do UltimatePOS
- **`after_product_saved()`** — hook do UltimatePOS
- **`addTaxonomies()`** — registra categorias/taxonomias customizadas

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `DeviceModel` | `repair_device_models` | [TODO] |
| `JobSheet` | `repair_job_sheets` | [TODO] |
| `RepairStatus` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Repair`_  
_Ver no MemCofre: `/docs/modulos/Repair`_
