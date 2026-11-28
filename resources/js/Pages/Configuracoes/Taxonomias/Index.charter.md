---
id: resources-js-pages-configuracoes-taxonomias-index-charter
page: /taxonomies
component: resources/js/Pages/Configuracoes/Taxonomias/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
runbook: memory/requisitos/Configuracoes/RUNBOOK-taxonomias.md
owner: felipe
status: draft
last_validated: "2026-11-27"
parent_module: Configuracoes
related_adrs: [93, 104]
related_us: [US-MWART-010]
tier: B
charter_version: 2
---

# Page Charter — /taxonomies (DRAFT · listas dos módulos)

> **Status:** draft. Onda 1 do [RUNBOOK](../../../../../memory/requisitos/Configuracoes/RUNBOOK-taxonomias.md)
> (`TaxonomyController@index`, Blade → Inertia) atrás da flag `useV2Taxonomias`, ligada por padrão por decisão
> [F] 2026-11-27. Atende Fontes, Estágios de vida e Categorias de acompanhamento (CRM, `crm-blade-telas.jsx` →
> `Taxonomias()`) e Departamentos e Cargos (RH, `hrm-page.jsx` → `Taxonomia()`). Casos: [`Index.casos.md`](Index.casos.md).

## Mission

Cadastrar as listas que os módulos usam (de onde vem o lead, em que fase ele está, como se classifica um
acompanhamento, departamento e cargo do colaborador), vendo quantos registros usam cada item.

## Goals — Features (faz, nesta fase)

- Lista do tipo, só do negócio da sessão, com código (quando o módulo habilita), descrição e "Em uso".
- Abas com as listas irmãs do mesmo módulo (CRM: três; RH: duas).
- Adicionar, renomear e excluir pelos endpoints JSON da Blade.
- Item em uso não se exclui (botão desabilitado e o servidor recusa).
- `?classico=1` abre a Blade; categoria de produto e tipos não listados seguem na Blade.

## Non-Goals (não faz, nesta fase)

- ❌ Subcategoria: os cinco tipos não usam (`enable_sub_taxonomy` falso).
- ❌ Categoria de produto: tem tela própria em `/units`.

## Automation Anti-hooks

- ❌ Abrir a tela para um tipo que o módulo dono não declara para o usuário (aí a rota segue na Blade).
- ❌ Contar uso fora do `business_id` da sessão.
