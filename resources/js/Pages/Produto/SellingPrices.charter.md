---
page: /products/add-selling-prices/{id}
component: resources/js/Pages/Produto/SellingPrices.tsx
related_prototype: n/a (herda PT-02 Form-Drawer; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-05-15"
parent_module: Produto
related_adrs: [104, 149, 93, 107]
tier: A
charter_version: 1
mwart_pattern_reuse:
  blueprint_cowork: "prototipo-ui/cowork/produtos-page.jsx"
  blueprint_screenshot_approval: "SYNC_LOG (pendente)"
  derived_screens: [SellingPrices]
  divergence_from_blueprint: "matriz variation × price_group é tabela densa específica — não é list cockpit padrão; mantém AppShellV2 + tokens + header pattern; diverge no conteúdo central. ADR 0149 §'Casos que NÃO se qualificam — bulk-edit datatable'"
---

# Page Charter — /products/add-selling-prices/{id} (DRAFT)

## Mission

Configurar preços de variations por price_group (matriz N×M). Cada célula = price + price_type (fixed/percentage). Tela densa pra usuário definir tabelas balcão/atacado/varejo por variação.

## Goals

- AppShellV2 + PageHeader "Tabelas de preço · {nome produto}" + SKU mono
- Tabela densa: linhas = variations ativas, colunas = price_groups ativos
- Por célula: input numérico + Select (fixed/percentage)
- Botão "Salvar tabelas" sticky topo
- Multi-tenant scopado business_id
- Submit POST `/products/save-selling-prices`

## Non-Goals

- ❌ Editar nome variation/price_group inline
- ❌ Criar price_group novo inline (rota separada)
- ❌ Bulk apply (mesma price em N variations) — Wave 3

## UX Targets

- p95 < 800ms
- 1280px responsivo (matriz pode ter scroll horizontal se >5 price_groups)
- Tabular-nums em valores
- Dirty-state visível (badge "Não salvo" + botão salvar desabilitado sem mudança)
- Atalho Cmd/Ctrl+S salva sem sair da tela (preserveScroll) + toast de confirmação
- Navegação por teclado entre células (setas + Enter)
- Erros de validação do servidor por célula (useForm errors, chave group_prices.{pg}.{v}.price)

## Anti-patterns

- ❌ `auth()->user()->business_id` (canon UPOS session)
- ❌ Cor crua (migrado pra tokens v4 — bg-background/bg-card/text-foreground/border-border/destructive — 2026-05-31)

## Pest GUARD

```php
it('Page Inertia existe em Pages/Produto/SellingPrices.tsx')
it('Page declara matriz variations × priceGroups')
it('Controller cross-tenant retorna 404')
```

## Refs

- RUNBOOK: `memory/requisitos/Inventory/RUNBOOK-produto-selling-prices.md`
- Visual comparison: `memory/requisitos/Inventory/produto-selling-prices-visual-comparison.md`
- ADR 0149

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-15 | [W2-C] | Charter criado em Wave 2 B4 Produto. |
| 2026-05-31 | [DS-upgrade] | Paleta stone→tokens v4; header hand-rolled→tokens (breadcrumb/título/SKU); + dirty-state, Cmd+S, navegação teclado, erros por célula, toast. Contrato backend (group_prices, POST save-selling-prices, price_type) intacto. |
