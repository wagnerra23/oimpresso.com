---
id: modules-superadmin-pages-superadmin-paginas-index-charter
page: /superadmin/frontend-pages
component: Modules/Superadmin/Resources/js/Pages/superadmin/Paginas/Index.tsx
related_prototype: "n/a (herda PT-01 Lista; segue o Padrão de Tela — não há sa-paginas no protótipo)"
owner: wagner
status: draft
last_validated: "2026-10-09"
related_us: [US-SUPER-005]
parent_module: Superadmin
related_adrs: [104, 93]
tier: B
charter_version: 1
runbook: memory/requisitos/Superadmin/RUNBOOK-paginas.md
---

# Page Charter — /superadmin/frontend-pages

> Nasce `draft` na thread Superadmin/08 (decisão [W] D-PAG, 2026-10-07), atrás da chave
> `?tela=nova`. Sem a chave o backend serve a Blade; o cutover é do [W]. Backend:
> `PageController@index/store/update/destroy`. Ver
> [RUNBOOK-paginas](../../../../../../../memory/requisitos/Superadmin/RUNBOOK-paginas.md).

## Mission

Responde *"quais páginas institucionais o site publica, e como mudo uma?"*: lista as páginas com
endereço, ordem no menu e visibilidade, e abre um drawer para criar, editar ou excluir. Persona
única: [W], superadmin. Separado do Cms por decisão [W] (D1).

## Goals — Features (faz)

- Lista todas as páginas em ordem de menu, com resumo do conteúdo **sem marcação**.
- Drawer com título, slug, ordem, visível e conteúdo (paridade com `pages.create`/`pages.edit`).
- Slug repetido aparece como erro no campo, sem perder o que foi digitado.
- Exclusão pede confirmação (a Blade pedia `swal`).

## Non-Goals — Features (NÃO faz)

- **Não renderiza a página pública** `/page/{slug}`: ela segue na Blade do site.
- **Não tem editor rico**: o conteúdo é HTML em texto, como o banco guarda.

## Automation Anti-hooks (o que a próxima sessão NÃO pode "consertar")

- ❌ **Não escopar por `business_id`.** `superadmin_frontend_pages` é conteúdo global do site
  ([ADR 0093](../../../../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md) §exceções Superadmin).
- ❌ **Não imprimir o `conteudo` como HTML na lista** (`dangerouslySetInnerHTML`). A lista mostra
  `resumo` sem tag; a Blade antiga imprimia o HTML cru dentro do painel.
- ❌ **Não tornar `?tela=nova` o padrão** nem apagar as Blades sem o cutover do [W].

## Refs

- Casos: [Index.casos.md](Index.casos.md)
