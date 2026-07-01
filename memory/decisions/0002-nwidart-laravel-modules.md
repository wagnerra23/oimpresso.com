---
slug: 0002-nwidart-laravel-modules
number: 2
title: !!binary gJQgVXNhciBuV2lkYXJ0L2xhcmF2ZWwtbW9kdWxlcyBjb21vIHNpc3RlbWEgZGUgbcOzZHVsb3M=
type: adr
status: aceito
authority: canonical
lifecycle: ativo
superseded_by: []
decided_by:
  - W
decided_at: '2026-04-18'
quarter: 2026-Q2
tags: {  }
related:
  - '0001'
pii: false
---
# ADR 0002 — Usar nWidart/laravel-modules como sistema de módulos

**Status:** ✅ Aceita
**Data:** 2026-04-18

## Contexto

Decidida Opção C (ADR 0001), precisamos de um sistema de módulos para isolar o Ponto WR2 do core. Opções:

- **nWidart/laravel-modules**: lib popular, já usada pelo UltimatePOS Essentials
- **Laravel Packages (pacote composer normal)**: mais simples, mas não tem o conceito de "instalável via admin"
- **Custom solution**: reinventar

## Decisão

**Usar nWidart/laravel-modules.**

- É o que o UltimatePOS já usa para Essentials, Connector, Superadmin etc.
- Existe tooling maduro (`php artisan module:make`, autoload, routes, migrations)
- Reduz fricção: desenvolvedores que conhecem Essentials entendem nossa estrutura
- Integra com o cadastro de módulos da UI admin do UltimatePOS

## Consequências

### Positivas

- Padrão conhecido pela comunidade UltimatePOS
- Estrutura de pastas pré-definida (`Config/`, `Database/`, `Entities/`, `Http/`, `Resources/`, `Routes/`, `Services/`)
- Módulo ativável/desativável via `modules_statuses.json`
- Suporte nativo a dependências entre módulos (`"requires": ["Essentials"]`)

### Negativas

- Overhead de entender a lib (namespaces, autoload)
- Algumas convenções do nWidart divergem do Laravel vanilla

### Neutras

- Implica seguir a estrutura esperada pela lib (não inventamos nomes de pastas)
