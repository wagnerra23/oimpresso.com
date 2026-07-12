---
page: /vestuario/etiquetas
component: resources/js/Pages/Vestuario/Etiquetas/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Vestuario
related_us: [US-VEST-020]
related_adrs: [93, 104, 121, 101]
tier: B
charter_version: 1
---

# Page Charter — /vestuario/etiquetas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Vestuario/Http/Controllers/EtiquetaTagController` (perms `vestuario.etiqueta.view` / `vestuario.etiqueta.create`). US-VEST-020 (Etiqueta TAG térmica + EAN-13 + QR Code). ADR 0121 (vertical Vestuario) + 0104 (MWART) + 0093 (multi-tenant).

---

## Mission

Tela de geração de etiquetas TAG térmicas do vertical Vestuário: o operador monta uma lista de itens (produto/variação + quantidade) e imprime etiquetas com EAN-13 + QR Code. É a ferramenta de etiquetagem em lote da loja de vestuário (ROTA LIVRE) — da grade de itens ao envio pra impressão térmica.

---

## Goals — Features (faz)

- Lista editável de itens de etiqueta (produto/variação, quantidade), com edição por linha
- Geração de EAN-13 + QR Code por item
- Montagem do payload de impressão + envio (POST com CSRF) pra a impressora térmica
- AppShellV2 + PageHeader shared, tokens DS

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO cadastra/edita o produto em si (só monta etiqueta a partir dele)
- ❌ NÃO altera preço/estoque do item (etiqueta é saída, não mexe em valor/estoque)
- ❌ NÃO cruza tenants — `business_id` scope (Tier 0)
- ❌ NÃO configura o modelo/driver da impressora aqui (setup é outro fluxo)

---

## UX targets

- p95 < 1500ms (tela admin)
- Cabe em 1280px (ROTA LIVRE — monitor da Larissa)
- Preview/edição de itens antes de imprimir (evita desperdício de etiqueta)

---

## Automation hooks (faz)

- Gera EAN-13/QR por item automaticamente ao montar a lista
- Monta o payload de impressão a partir dos itens editados

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO imprime sem ação explícita do operador
- ❌ NÃO altera estoque/valor do produto ao gerar etiqueta
- ❌ NÃO grava nada em GET

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar contrato do payload de impressão vs RUNBOOK-etiqueta-tag
- [ ] Smoke visual 1280px (screenshot) — lista de itens + geração EAN/QR
