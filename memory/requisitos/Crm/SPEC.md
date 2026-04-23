# Especificação funcional

## 3. User stories

> Convenção do ID: `US-CRM-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-CRM-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-CRM-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Crm
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-002 · Autorização Spatie `crm.access_all_schedule`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_schedule`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_schedule')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-003 · Autorização Spatie `crm.access_own_schedule`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_schedule`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_schedule')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-004 · Autorização Spatie `crm.access_all_leads`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_leads`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_leads')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-005 · Autorização Spatie `crm.access_own_leads`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_leads`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_leads')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-006 · Autorização Spatie `crm.access_all_campaigns`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_all_campaigns`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_all_campaigns')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-CRM-007 · Autorização Spatie `crm.access_own_campaigns`

```gherkin
Dado que um usuário **não** tem a permissão `crm.access_own_campaigns`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('crm.access_own_campaigns')`  
**Testado em:** _[TODO — apontar caminho do teste]_
