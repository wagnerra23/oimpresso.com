# Especificação funcional

## 3. User stories

> Convenção do ID: `US-ESSE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-ESSE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-ESSE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Essentials
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-002 · Autorização Spatie `essentials.crud_leave_type`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_leave_type`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_leave_type')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-003 · Autorização Spatie `essentials.crud_all_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_leave')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-004 · Autorização Spatie `essentials.crud_own_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_own_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_own_leave')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-005 · Autorização Spatie `essentials.approve_leave`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.approve_leave`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.approve_leave')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-006 · Autorização Spatie `essentials.crud_all_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.crud_all_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.crud_all_attendance')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)

### R-ESSE-007 · Autorização Spatie `essentials.view_own_attendance`

```gherkin
Dado que um usuário **não** tem a permissão `essentials.view_own_attendance`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('essentials.view_own_attendance')`  
**Testado em:** `Modules/Essentials/Tests/Feature/PermissionsTest` (stub pendente)
