---
page: /pricing
component: resources/js/Pages/Site/Pricing.tsx
related_prototype: n/a (landing pública de preços bespoke — não segue um dos 5 Padrões de Tela do ERP)
owner: wagner
status: draft
last_validated: "2026-07-11"
parent_module: Superadmin
related_adrs: [114, 101, 94]
tier: B
charter_version: 1
---

# Page Charter — /pricing (DRAFT)

> **Status:** draft criado em 2026-07-11 no lote de cobertura de charters. Wagner aprova **Non-Goals + Anti-hooks** ANTES de virar `status: live`.
>
> Backend: `Modules/Superadmin/Http/Controllers/PricingController@index` (rota pública `GET /pricing`, sem auth). Página de preços/planos. Renderiza via `SiteLayout` (sem AppShellV2/sidebar).

---

## Mission
Expõe os planos do produto pra prospect decidir. O visitante alterna entre cobrança mensal/anual, compara tiers e é conduzido a `Começar grátis` (`/login`) ou `Falar com o time` (`/c/contact-us`). Backend já entrega `packages` (`Package::listPackages`) + `permissions`, mas os tiers hoje estão majoritariamente hardcoded em `PricingTiers.tsx` pra acelerar a entrega (PR2 planeja hidratar com DB).

---

## Goals — Features (faz)
- Toggle de billing (mensal/anual) com selo `−20%` no anual (estado local `useState`).
- Renderiza `PricingTiers` (recebe `billing` + `packages`) + linha de confiança (suporte humano/LGPD/cancelamento) + `PricingFaq`.
- CTA final: `Começar grátis` → `/login` e `Falar com o time` → `/c/contact-us`.

---

## Non-Goals — Features (NÃO faz)
- ❌ Não executa checkout/compra/troca de plano aqui — só apresenta e encaminha (compra é fluxo separado no superadmin/pacotes).
- ❌ Não exige login nem escopa por `business_id` (página institucional pública).
- ❌ Não usa AppShellV2/sidebar (é `SiteLayout` público).
- ❌ Não persiste a escolha de billing (estado só client-side) — inferência pendente de Wagner.

---

## UX targets
- p95 < 800ms (página pública) ; cabe em 1280px (ROTA LIVRE) ; `SiteLayout` (sem AppShellV2).

---

## Automation hooks (faz)
- Backend entrega `packages` (`Package::listPackages(true)`) + `permissions` (`ModuleUtil::getModuleData('superadmin_package')`) formatadas.

---

## Anti-hooks (NÃO faz automaticamente)
- ❌ Não muta nada em `GET` nem inicia cobrança automaticamente ao selecionar plano.
- ❌ Não faz polling de preço/estoque de plano.
- ❌ Não coleta dado do visitante sem CTA explícito.

---

## Pendências antes de `status: live`
- [ ] Wagner aprova Non-Goals + Anti-hooks
- [ ] Smoke visual 1280/1440 (screenshot)
- [ ] Decidir hidratação real de `packages` (PR2) vs tiers hardcoded em `PricingTiers.tsx`
