---
title: "RUNBOOK — Funil de vendas em kanban (/crm/funil)"
module: Crm
tela: Crm/Funil/Index
owner: W
status: ativo
last_validated: "2026-10-26"
related_adrs:
  - '0441-funil-de-vendas-sai-da-depreciacao-emenda-0301'
  - '0093-multi-tenant-isolation-tier-0'
  - '0358-doutrina-de-teste-tenant-98-supersede-0101'
---

# RUNBOOK — Funil de vendas (`/crm/funil`)

> US-CRM-092 · [ADR 0441](../../decisions/0441-funil-de-vendas-sai-da-depreciacao-emenda-0301.md) (o funil de negócios sai da depreciação da ADR 0301).
> Tela nova, sem Blade a migrar. Fonte de design: `prototipo-ui/cowork/Felipe/erp-shell-v2/crm-page.jsx` (kanban com arrastar, painel e filtros). Padrão de Tela: PT-05 Kanban.

## 1. Tela
- **Inertia:** `Modules/Crm/Resources/js/Pages/Crm/Funil/Index.tsx` (+ charter, casos e `_components/FunilQuadro.tsx`).
- **Controller:** `Modules\Crm\Http\Controllers\FunilController` — `index`, `store`, `mover`. Motor: `Modules\Crm\Services\DealPipelineService`.
- **Rotas:** `GET /crm/funil` · `POST /crm/funil` · `PATCH /crm/funil/{id}/etapa`.
- **Dados:** `crm_deals` (KEEP desde a ADR 0441). Cliente = `contacts` da mesma empresa.

## 2. Como liberar para uma empresa
1. O módulo CRM precisa estar no pacote da empresa (`crm_module`).
2. Em `/roles/{id}/edit`, marcar **Funil de vendas: ver** (`crm.funil.ver`) e, para quem cria e move, **Funil de vendas: criar e mover negócios** (`crm.funil.editar`). Ninguém tem por padrão.
3. O item **Funil** aparece nas sub-vistas do CRM só para quem tem `crm.funil.ver`.

## 3. Testes
- `Modules/Crm/Tests/Feature/CrmFunilContratoTest.php` (UC-CRMFN-01..05) na lane `verticais-pest` (MySQL, tenant 98 × 99).
- E2E `e2e/crm-funil.spec.ts` é stub (`test.fixme`) até haver sinal de uso.

## 4. Pegadinhas
- Ganho e Perdido são finais no `DealPipelineService`: mover para fora deles devolve erro na etapa (a tela já desabilita os botões).
- O valor vai como número com 2 casas (`NumericInputPtBR`), nunca texto pt-BR.
- O provedor de arrastar é o compartilhado `Components/shared/KanbanDndProvider` (o mesmo do Quadro de OS da Oficina).
