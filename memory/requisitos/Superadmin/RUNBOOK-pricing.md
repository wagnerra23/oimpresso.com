---
id: requisitos-superadmin-runbook-pricing
title: "RUNBOOK — /pricing (vitrine pública de planos)"
module: Superadmin
tela: Site/Pricing
owner: W
status: ativo
last_validated: "2026-11-16"
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
spec_ref: memory/requisitos/Superadmin/SPEC.md
---

# RUNBOOK — `/pricing` (vitrine pública de planos)

Escrito em 2026-11-16 (lote L6 da auditoria) para uma tela que já existia sem RUNBOOK. Descreve o código em `origin/main` 580be57.

- **Page:** `Modules/Superadmin/Resources/js/Pages/Site/Pricing.tsx`, dentro do `SiteLayout` (sem AppShellV2 e sem sidebar).
- **Componentes:** `resources/js/Components/Site/PricingTiers.tsx` e `PricingFaq.tsx`.
- **Charter + casos:** `Pricing.charter.md` (`draft`) e `Pricing.casos.md` (UC-SPRIC-01..02).
- **US:** US-SUPER-009.
- **Rotas** (`Modules/Superadmin/Routes/web.php:7-9`), públicas e sem auth:
  - `GET /pricing` → `PricingController::index` (Inertia)
  - `GET /pricing/old` → `indexLegacy` (Blade `pricing/index.blade.php`, mantida "durante a transição", segundo o docblock)

## 1. O que o backend faz

`index`:
- lê `Package::listPackages(true)`: pacotes ativos, sem os privados, ordenados por `sort_order`;
- lê o catálogo de permissões `superadmin_package` dos módulos;
- devolve `packages` e `permissions`.

`PricingTiers` monta os cards a partir de `packages`.

⚠️ **Com zero pacotes** (lista vazia ou nula), `PricingTiers` renderiza `FALLBACK_TIERS`, que são cards escritos no código (`PricingTiers.tsx:38`, `:174-177`). Isso é número que não vem do banco, mostrado em página pública. Não medido em prod; registrado aqui como risco.

O comentário `// PR2: … os tiers estão hardcoded` no `PricingController::index` é anterior à hidratação por `packages` e não descreve mais o caminho normal.

## 2. Invariantes

- Pacote privado (`is_private`) e pacote desativado não aparecem (UC-SPRIC-02).
- A página abre sem login (UC-SPRIC-01).
- O preço vem do pacote; editar preço é a tela Pacotes (REGRA MESTRE de valor em `memory/proibicoes.md`).

## 3. Testes

`Modules/Superadmin/Tests/Feature/SitePricingContratoTest.php` (UC-SPRIC-01..02, `@covers-us US-SUPER-009`). Rodar no CT 100.

## 4. Smoke prod (R1)

Não registrado nesta data. A medição de tela é o lote L7.

## 5. Pendente

- Remover `/pricing/old` e `pricing/index.blade.php` depois da validação (o docblock pede isso; sem data).
- Decidir se o fallback com zero pacotes deve existir ou virar estado vazio.
