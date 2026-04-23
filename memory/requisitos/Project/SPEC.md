# Especificação funcional

## 3. User stories

> Convenção do ID: `US-PROJ-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-PROJ-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-PROJ-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Project
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-002 · Autorização Spatie `project.create_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.create_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.create_project')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-003 · Autorização Spatie `project.edit_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.edit_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.edit_project')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-PROJ-004 · Autorização Spatie `project.delete_project`

```gherkin
Dado que um usuário **não** tem a permissão `project.delete_project`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('project.delete_project')`  
**Testado em:** _[TODO — apontar caminho do teste]_
