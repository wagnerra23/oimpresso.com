---
id: resources-js-pages-vestuario-etiquetas-index-charter
page: /vestuario/etiquetas
component: resources/js/Pages/Vestuario/Etiquetas/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
related_runbook: memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md
related_casos: resources/js/Pages/Vestuario/Etiquetas/Index.casos.md
related_sdd: memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Vestuario
related_us: [US-VEST-020]
related_adrs: [93, 104, 121, 101]
tier: B
charter_version: 2
---

# Page Charter — /vestuario/etiquetas (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Vestuario/Http/Controllers/EtiquetaTagController`. US-VEST-020 (Etiqueta TAG térmica + EAN-13 + QR Code). ADR 0121 (vertical Vestuario) + 0104 (MWART) + 0093 (multi-tenant).
>
> ⚠️ **Correção factual 2026-07-28 (chip SDD).** A v1 deste charter afirmava *"perms
> `vestuario.etiqueta.view` / `vestuario.etiqueta.create`"* como se fossem **aplicadas**. Elas
> existem e estão registradas em `DataController::user_permissions()`, e gateiam a **entry de
> sidebar** — mas `EtiquetaTagController::authorizeAccess()` **não bloqueia** quem não as tem:
> emite `Log::warning('vestuario.etiqueta.permission_check_missing')` e segue o fluxo (o código
> declara *"Sprint 3 vira hard-block"*). O que hoje protege os endpoints é a **stack de middleware
> autenticada**. Ligar o hard-block é decisão de [W] — registrado no
> [SDD §9 D-1](../../../../../memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md).
>
> **Contrato desta tela:** design e casos de uso em
> [`SDD-tela-etiqueta-tag-v1.0.md`](../../../../../memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md)
> §5.3/§6 · contrato de teste em [`Index.casos.md`](Index.casos.md) (`UC-VET-01`…`UC-VET-09`) ·
> MWART F1 em [`RUNBOOK-etiqueta-tag.md`](../../../../../memory/requisitos/Vestuario/RUNBOOK-etiqueta-tag.md).

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
  > ⚠️ **Divergência ABERTA (2026-07-28, chip SDD) — não resolvida de propósito.** A tela entrega
  > **edição** (lista de itens editável linha a linha), mas **não entrega preview**: os botões
  > baixam `.zpl`/`.pdf` direto. Podar esta promessa **ou** construir o preview é decisão de
  > produto ([W]) — o agente é proibido de escolher o vencedor. Registrada nos dois lados:
  > [SDD §9 D-2](../../../../../memory/requisitos/Vestuario/SDD-tela-etiqueta-tag-v1.0.md) e
  > `[BACKLOG]` do [`Index.casos.md`](Index.casos.md).

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
