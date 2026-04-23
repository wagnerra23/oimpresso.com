# Arquitetura

## 1. Objetivo

Used for businesses where products needs to be manufactured

## 2. Áreas funcionais

### 2.1. Core

**Controller(s):** `ManufacturingController`  
**Ações (7):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`

_Descrição funcional:_ [TODO]

### 2.2. Production

**Controller(s):** `ProductionController`  
**Ações (8):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getManufacturingReport`

_Descrição funcional:_ [TODO]

### 2.3. Recipe

**Controller(s):** `RecipeController`  
**Ações (11):** `index`, `create`, `store`, `show`, `getIngredientRow`, `addIngredients`, `getRecipeDetails`, `getIngredientGroupForm`, `updateRecipeProductPrices`, `destroy`, `isRecipeExist`

_Descrição funcional:_ [TODO]

### 2.4. Settings

**Controller(s):** `SettingsController`  
**Ações (2):** `index`, `store`

_Descrição funcional:_ [TODO]

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- **`modifyAdminMenu()`** — injeta itens na sidebar admin do UltimatePOS
- **`superadmin_package()`** — registra pacote de licenciamento no Superadmin
- **`user_permissions()`** — registra permissões Spatie no cadastro de Roles

### 5.3. Integrações externas

_[TODO — APIs, webhooks, serviços de terceiros, SSO, etc.]_

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `MfgIngredientGroup` | `—` | [TODO] |
| `MfgRecipe` | `—` | [TODO] |
| `MfgRecipeIngredient` | `—` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Manufacturing`_  
_Ver no DocVault: `/docs/modulos/Manufacturing`_
