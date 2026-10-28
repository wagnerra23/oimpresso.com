---
id: resources-js-pages-vestuario-remarcacao-index-charter
page: /vestuario/remarcacao
component: resources/js/Pages/Vestuario/Remarcacao/Index.tsx
related_prototype: prototipo-ui/cowork/Wagner/vestuario-remarcacao-page.jsx
related_runbook: memory/requisitos/Vestuario/RUNBOOK-remarcacao.md
related_casos: resources/js/Pages/Vestuario/Remarcacao/Index.casos.md
owner: wagner
status: draft
last_validated: "2026-10-27"
parent_module: Vestuario
related_us: [US-VEST-032]
related_adrs: [93, 104, 121, 438]
tier: B
charter_version: 2
---

# Page Charter — /vestuario/remarcacao (DRAFT)

> **Status:** draft. Wagner aprova **Non-Goals + Anti-hooks** antes de virar `status: live`.
> Backend: `Modules/Vestuario/Http/Controllers/RemarcacaoController` + `Services/Remarcacao`.
> Decisão [W] 2026-10-27 (US-VEST-032). Desligada por padrão: setting `remarcacao.enabled` por empresa.

---

## Mission

Remarcar o preço de etiqueta de uma coleção (ou de um filtro de produtos) de uma vez: escolher as
peças, aplicar percentual ou valor, ver a prévia antes → depois, gravar com confirmação, reverter
depois e imprimir as etiquetas novas.

---

## Goals — Features (faz)

- Filtro por categoria, subcategoria, marca e trecho do nome (coleção)
- Novo preço por percentual ou valor fixo, com prévia por variação
- Gravação com confirmação e nome do lote; lista de remarcações anteriores
- Reverter um lote; imprimir as etiquetas do lote (PDF da tela de etiquetas)

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO mexe em estoque nem em custo de compra
- ❌ NÃO mexe nas tabelas de preço por grupo (`variation_group_prices`)
- ❌ NÃO aplica desconto em runtime no carrinho (isso é a US-VEST-023, liquidação)
- ❌ NÃO cruza tenants — `business_id` scope (Tier 0)

---

## UX targets

- Cabe em 1280px (ROTA LIVRE)
- A prévia sempre aparece antes do botão de gravar; o histórico é carregado depois (`Inertia::defer`)

---

## Automation hooks (faz)

- Recalcula preço sem imposto e margem pela taxa do produto ao gravar

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO grava sem prévia em dia e confirmação explícita
- ❌ NÃO remarca a loja inteira sem filtro
- ❌ NÃO reverte preço que foi mexido depois da remarcação
