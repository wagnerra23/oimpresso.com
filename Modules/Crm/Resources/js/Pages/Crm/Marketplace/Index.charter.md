---
id: modules-crm-pages-crm-marketplace-index-charter
page: /crm/b2b-marketplace
component: Modules/Crm/Resources/js/Pages/Crm/Marketplace/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/crm-blade-telas.jsx
runbook: memory/requisitos/Crm/RUNBOOK-marketplace.md
owner: felipe
status: draft
last_validated: "2026-11-27"
parent_module: Crm
related_adrs: [93, 104]
tier: B
charter_version: 2
---

# Page Charter — /crm/b2b-marketplace (DRAFT · Marketplace B2B)

> **Status:** draft. Onda 1 do [RUNBOOK](../../../../../../../memory/requisitos/Crm/RUNBOOK-marketplace.md): a
> integração ligada (`constants.enable_b2b_marketplace`) e a tela React atrás da flag `useV2CrmMarketplace`, ligada
> por padrão por decisão [F] 2026-11-27. Casos: [`Index.casos.md`](Index.casos.md).

## Mission

Deixar o Admin configurar a conta do marketplace Exporters India (chave, e-mail, a quem atribuir e a fonte) e
importar os pedidos de lá como leads com acompanhamento.

## Goals — Features (faz, nesta fase)

- Formulário com chave, e-mail, usuários atribuídos (vários) e fonte, preenchido com o que está salvo.
- Salvar pelo `POST /crm/save-marketplace`; importar pelo `GET /crm/import-leads` (só depois de salvar).
- `?classico=1` abre a Blade.

## Non-Goals (não faz, nesta fase)

- ❌ Outros marketplaces: o controller só conhece Exporters India.
- ❌ Mostrar os leads importados aqui: eles entram na lista de Leads e nos Acompanhamentos.

## Automation Anti-hooks

- ❌ Atribuir lead a usuário de outra empresa ou gravar fonte de outra empresa (o `save()` valida os dois).
- ❌ Mostrar ao usuário o detalhe técnico de erro da importação (vai só para o log, redigido).
