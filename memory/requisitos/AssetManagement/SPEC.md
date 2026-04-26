# Especificação funcional — AssetManagement

> Pattern espelha `memory/requisitos/Project/SPEC.md` e `Accounting/SPEC.md`.
> Documentação viva — consumida pelo MemCofre em `/docs/modulos/AssetManagement`.

## 1. Objetivo

Gerenciamento de ativos da empresa (equipamentos, ferramentas, licenças). Permite cadastrar, alocar a colaboradores, registrar manutenções, garantias e revogar alocações. Integra com `business_locations` e `categories` do core UltimatePOS.

## 2. Áreas funcionais

| Área | Controller | Ações públicas |
|---|---|---|
| Asset | `AssetController` | `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `dashboard` |
| Asset Allocation | `AssetAllocationController` | resource (7 ações) |
| Asset Maintenance | `AssetMaitenanceController` (sic — typo upstream) | resource (7 ações) |
| Asset Settings | `AssetSettingsController` | resource (7 ações) |
| Revoke Allocated Asset | `RevokeAllocatedAssetController` | resource (7 ações) |

## 3. User stories

> Convenção: `US-ASSE-NNN`. Campo `implementado_em` linka com a Page (Blade
> hoje, candidata a migração React — ver `memory/requisitos/AssetManagement.md`).

### US-ASSE-001 · Listar ativos da empresa

**Como** admin/gestor de ativos
**Quero** ver todos os ativos cadastrados (com categoria, localização, qtd alocada)
**Para** ter visão geral do parque

**Implementado em:** `Modules/AssetManagement/Resources/views/asset/index.blade.php`
**Testado em:** `tests/Feature/Modules/AssetManagement/AssetRoutesAuthTest.php::test_admin_acessa_assets_index_sem_500`

**Definition of Done:**
- [ ] Rota `/asset/assets` exige autenticação (`302` para guest)
- [ ] Scope por `business_id` (R-ASSE-001)
- [ ] DataTable com colunas: código, nome, categoria, localização, qtd, qtd alocada, qtd revogada
- [ ] Filtro por purchase_type (owned/rented/leased)
- [ ] Permissão `asset.view` ou `superadmin`

### US-ASSE-002 · Criar novo ativo

**Como** admin
**Quero** cadastrar um ativo novo (com asset_code, modelo, valor, garantia)
**Para** rastrear sua localização e ciclo de vida

**Implementado em:** `Modules/AssetManagement/Resources/views/asset/create.blade.php`
**Testado em:** `tests/Feature/Modules/AssetManagement/AssetCrudTest.php::test_store_sem_dados_retorna_validacao`

**Definition of Done:**
- [ ] FormRequest valida campos obrigatórios (name, category_id, location_id, quantity)
- [ ] `business_id` injetado a partir da session (não vem do form)
- [ ] Permissão `asset.create` ou `superadmin`
- [ ] Upload de mídia opcional (Spatie media library)

### US-ASSE-003 · Alocar ativo a colaborador

**Como** admin
**Quero** alocar uma quantidade do ativo X a um user Y
**Para** rastrear quem está com o quê

**Implementado em:** `Modules/AssetManagement/Resources/views/allocation/*.blade.php`

**Definition of Done:**
- [ ] Quantidade alocada não pode exceder `quantity - allocated + revoked`
- [ ] Cria `AssetTransaction` com `transaction_type = 'allocate'`
- [ ] Notifica colaborador (`AssetAllocated` notification)

### US-ASSE-004 · Revogar alocação

**Como** admin
**Quero** registrar a devolução do ativo
**Para** liberar saldo

**Implementado em:** `Modules/AssetManagement/Resources/views/revocation/*.blade.php`

**Definition of Done:**
- [ ] Cria `AssetTransaction` com `transaction_type = 'revoke'`
- [ ] Atualiza saldo disponível em `Asset::forDropdown()`

### US-ASSE-005 · Manutenção de ativo

**Como** admin
**Quero** registrar manutenções (preventiva/corretiva) com data, valor, fornecedor
**Para** ter histórico e custo total de propriedade

**Implementado em:** `Modules/AssetManagement/Resources/views/asset_maintenance/*.blade.php`
**Testado em:** `tests/Feature/Modules/AssetManagement/AssetMaintenanceTest.php`

## 4. Regras de negócio (Gherkin)

### R-ASSE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo AssetManagement
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))` (ver `AssetController@index:74`).
**Testado em:** `tests/Feature/Modules/AssetManagement/AssetCrudTest.php::test_asset_model_tem_coluna_business_id`

### R-ASSE-002 · Autorização Spatie `asset.view`

```gherkin
Dado que um usuário não tem `asset.view`
Quando ele acessa /asset/assets
Então recebe 403
```

**Implementação:** `AssetController@index` checa `$user->can('superadmin')` ou subscription `assetmanagement_module`.
**Testado em:** `tests/Feature/Modules/AssetManagement/AssetRoutesAuthTest.php`

### R-ASSE-003 · Saldo disponível nunca negativo

```gherkin
Dado um ativo com quantity=10, allocated=8, revoked=0
Quando admin tenta alocar mais 5
Então a alocação é rejeitada (validação)
```

**Implementação:** `Asset::forDropdown()` faz `havingRaw('quantity > 0')` no dropdown.
**Testado em:** _[TODO — caso happy path]_

### R-ASSE-004 · Estado do ativo: in_warranty derivado

```gherkin
Dado um ativo com warranty entre start_date e end_date
Quando agora está dentro do range
Então `is_in_warranty` retorna a warranty ativa
```

**Implementação:** `Asset::getIsInWarrantyAttribute()` (Modules/AssetManagement/Entities/Asset.php:95).

## 5. Integrações

### 5.1. Hooks UltimatePOS registrados

- `modifyAdminMenu()` — sub-menu na sidebar admin
- `superadmin_package()` — pacote `assetmanagement_module` no Superadmin
- `user_permissions()` — permissions Spatie `asset.*`
- `addTaxonomies()` — registra taxonomy "Asset Category"

### 5.2. Tabelas core consumidas

- `business_locations` (FK `assets.location_id`)
- `categories` (FK `assets.category_id`)
- `users` (FK `assets.created_by`, `asset_transactions.allocated_to`)
- `media` (Spatie morphMany)

## 6. Dados e entidades

| Modelo | Tabela | Finalidade |
|---|---|---|
| `Asset` | `assets` | Cadastro do ativo (código, qtd, valor, categoria, localização) |
| `AssetTransaction` | `asset_transactions` | Movimentações allocate/revoke (append-style) |
| `AssetWarranty` | `asset_warranties` | Garantias com start/end date |
| `AssetMaintenance` | `asset_maintenances` | Manutenções com custo e fornecedor |

## 7. Decisões em aberto

- [ ] Migração das telas Blade para React (priorizar Index + Show por valor)
- [ ] Saldo disponível: cachear em coluna `available_qty` ou recalcular a cada query?
- [ ] Política de retenção de `asset_transactions` (append-only ou soft delete?)

## 8. Histórico e notas

- **2026-04-22** — Documento `requisitos/AssetManagement.md` gerado automaticamente.
- **2026-04-26** — SPEC.md criado junto com testes Pest do batch 6 (cobre auth + smoke + tenancy schema).

---
_Tests cobrindo este módulo:_
- `tests/Feature/Modules/AssetManagement/AssetRoutesAuthTest.php`
- `tests/Feature/Modules/AssetManagement/AssetCrudTest.php`
- `tests/Feature/Modules/AssetManagement/AssetMaintenanceTest.php`
