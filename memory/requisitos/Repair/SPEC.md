# Especificação funcional

## 3. User stories

> Convenção do ID: `US-REPA-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-REPA-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-REPA-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Repair
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-002 · Autorização Spatie `repair.create`

```gherkin
Dado que um usuário **não** tem a permissão `repair.create`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.create')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-003 · Autorização Spatie `repair.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.update')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-004 · Autorização Spatie `repair.view`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-005 · Autorização Spatie `repair.view_own`

```gherkin
Dado que um usuário **não** tem a permissão `repair.view_own`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.view_own')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-006 · Autorização Spatie `repair.delete`

```gherkin
Dado que um usuário **não** tem a permissão `repair.delete`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair.delete')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)

### R-REPA-007 · Autorização Spatie `repair_status.update`

```gherkin
Dado que um usuário **não** tem a permissão `repair_status.update`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('repair_status.update')`  
**Testado em:** `Modules/Repair/Tests/Feature/PermissionsTest` (stub pendente)
