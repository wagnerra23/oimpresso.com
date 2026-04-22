---
module: Spreadsheet
alias: spreadsheet
status: ativo
migration_target: react
migration_priority: média
risk: médio
areas: [Core]
last_generated: 2026-04-22
scale:
  routes: 9
  controllers: 3
  views: 7
  entities: 2
  permissions: 2
---

# Requisitos funcionais — Spreadsheet

> **Documentação viva.** Foca em _o que o módulo faz de valor pro negócio_,
> separada da spec técnica em `memory/modulos/Spreadsheet.md`.
>
> Arquivos deste formato são consumidos pelo módulo **DocVault**
> (`/docs/modulos/Spreadsheet`) que linka user stories com telas React,
> regras Gherkin com testes, e mantém rastreabilidade evidência → requisito.

## Sumário

1. [Objetivo](#1-objetivo)
2. [Áreas funcionais](#2-áreas-funcionais)
3. [User stories](#3-user-stories)
4. [Regras de negócio (Gherkin)](#4-regras-de-negócio-gherkin)
5. [Integrações](#5-integrações)
6. [Dados e entidades](#6-dados-e-entidades)
7. [Decisões em aberto](#7-decisões-em-aberto)
8. [Histórico e notas](#8-histórico-e-notas)

---

## 1. Objetivo

Allows you to create spreadsheet and share with employees, roles & todos

## 2. Áreas funcionais

### 2.1. Core

**Controller(s):** `SpreadsheetController`  
**Ações (12):** `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`, `getShareSpreadsheet`, `postShareSpreadsheet`, `notifyUsersOfSharedSheets`, `addFolder`, `moveToFolder`

_Descrição funcional:_ [TODO]

## 3. User stories

> Convenção do ID: `US-SPRE-NNN`
> Campo `implementado_em` linka com a Page React que atende a story.

_[TODO — escrever user stories no formato abaixo.]_

### US-SPRE-001 · [TODO — título]

**Como** [papel]  
**Quero** [ação]  
**Para** [objetivo de negócio]

**Implementado em:** _[path]_

**Definition of Done:**
- [ ] [critério]

## 4. Regras de negócio (Gherkin)

> Formato: `Dado ... Quando ... Então ...`. Cada regra deve ser
> **testável** — idealmente tem 1 teste Feature que a valida.

### R-SPRE-001 · Isolamento multi-tenant por business_id

```gherkin
Dado que um usuário pertence ao business A
Quando ele acessa qualquer recurso do módulo Spreadsheet
Então só vê registros com `business_id = A`
```

**Implementação:** Controllers fazem `where('business_id', session('business.id'))`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-SPRE-002 · Autorização Spatie `access.spreadsheet`

```gherkin
Dado que um usuário **não** tem a permissão `access.spreadsheet`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('access.spreadsheet')`  
**Testado em:** _[TODO — apontar caminho do teste]_

### R-SPRE-003 · Autorização Spatie `create.spreadsheet`

```gherkin
Dado que um usuário **não** tem a permissão `create.spreadsheet`
Quando ele tenta acessar a funcionalidade correspondente
Então recebe `403 Unauthorized`
```

**Implementação:** Controllers checam `$user->can('create.spreadsheet')`  
**Testado em:** _[TODO — apontar caminho do teste]_

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
| `Spreadsheet` | `sheet_spreadsheets` | [TODO] |
| `SpreadsheetShare` | `sheet_spreadsheet_shares` | [TODO] |

## 7. Decisões em aberto

> Questões que exigem decisão de produto/negócio antes de avançar.

- [ ] [TODO]
- [ ] [TODO]

## 8. Histórico e notas

> Decisões tomadas, incidentes relevantes, contexto.

- **2026-04-22** — arquivo gerado automaticamente por `module:requirements`

---
_Última regeneração: 2026-04-22 16:35_  
_Regerar: `php artisan module:requirements Spreadsheet`_  
_Ver no DocVault: `/docs/modulos/Spreadsheet`_
