---
page: /whatsapp/templates
component: resources/js/Pages/Whatsapp/Templates/Index.tsx
related_prototype: n/a (herda PT-01 Lista; segue o Padrão de Tela)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Whatsapp
related_us: [US-WA-013]
related_adrs: [96, 114, 101, 93]
tier: B
charter_version: 1
---

# Page Charter — /whatsapp/templates (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Whatsapp/Http/Controllers/Admin/TemplatesController@index` (`/whatsapp/templates`, perm `whatsapp.templates.manage`). US-WA-013 — templates HSM Meta + locais Z-API/Baileys. ADR 0096. (Lote 2e = lista; sync Meta em Lote 2f.)

---

## Mission

Listagem dos templates de mensagem WhatsApp (HSM Meta + locais Z-API/Baileys) da empresa — nome, categoria, idioma, status de aprovação — pra o time ver quais templates existem e seu estado antes de disparar campanhas/notificações. É a gestão de templates do módulo Whatsapp.

---

## Goals — Features (faz)

- Lista dos templates (cards em grid) com nome, categoria, idioma e status
- Distinção entre templates HSM Meta (aprovados) e locais (Z-API/Baileys)
- AppShellV2 + PageHeader shared, tokens DS
- `EmptyState` quando não há templates

---

## Non-Goals — Features (NÃO faz)

- ❌ NÃO envia mensagem/campanha aqui (é gestão de templates, não disparo)
- ❌ NÃO aprova template na Meta a partir desta tela (aprovação é do lado Meta)
- ❌ NÃO faz sync Meta nesta versão (Lote 2e é só lista; sync vem no Lote 2f)
- ❌ NÃO cruza tenants — `business_id` scope (Tier 0)
- ❌ NÃO gerencia credenciais/canais WhatsApp (isso é a tela de canais)

---

## UX targets

- p95 < 1500ms (tela admin)
- Cabe em 1280px (ROTA LIVRE)
- Status de aprovação legível por badge (tokens)

---

## Automation hooks (faz)

- Reflete o estado dos templates persistidos (Meta HSM + locais)

---

## Anti-hooks (NÃO faz automaticamente)

- ❌ NÃO dispara sync Meta automático ao abrir (Lote 2f, explícito)
- ❌ NÃO envia mensagem de teste automaticamente
- ❌ NÃO grava nada em GET

---

## Pendências antes de `status: live`

- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Confirmar campos exibidos por template (categoria/idioma/status Meta)
- [ ] Smoke visual 1280/1440 (screenshot) — com e sem templates
