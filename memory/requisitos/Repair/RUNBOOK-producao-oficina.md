---
title: "RUNBOOK MWART — Repair/ProducaoOficina/Index"
module: Repair
tela: Repair/ProducaoOficina/Index
owner: W
status: ativo
last_validated: "2026-11-06"
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0180-sidebar-v3-5-grupos-ghosts-header, 0093-multi-tenant-isolation-tier-0, 0143-fsm-pipeline-live-prod-marco-2026-05-12]
---

# RUNBOOK MWART — Repair/ProducaoOficina/Index

> **Tela:** `/repair/producao-oficina` · **Componente:** `resources/js/Pages/Repair/ProducaoOficina/Index.tsx`
> **Controller:** `Modules/Repair/Http/Controllers/ProducaoOficinaController.php` (`index`, `move`)
> **Fonte de design:** `prototipo-ui/cowork/Wagner/repair-page.jsx` — barra `MP.Tabs` "Áreas da assistência técnica" (aba `producao`) e rota `rep-producao`
> **Charter / casos:** `Index.charter.md` (`status: draft`) · `Index.casos.md` (UC-RPOE-01..05)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR 0180](../../decisions/0180-sidebar-v3-5-grupos-ghosts-header.md) · [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | não existe — a tela nasceu Inertia (charter: "Greenfield — sem tela Blade legacy") |
| Rota | `GET /repair/producao-oficina` → `repair.producao-oficina` · `POST …/{id}/move` (drag-and-drop) |
| Layout | `AppShellV2` via `ProducaoOficinaIndex.layout`; kanban de altura total, **sem PageHeader** |
| Dados | `JobSheet` real por `business_id`; cai em mock quando o business não tem `repair_statuses` (selo "mock") |
| RUNBOOK | este arquivo, criado em 2026-11-06. A tela não tinha F1 e o hook MWART barrava qualquer edição |

## Decisões F1 PLAN

1. **A tela já é Inertia.** Nada migra de Blade. Esta onda só acrescenta a barra de abas do módulo.
2. **Barra de abas do módulo no topo, a mesma lista do sidebar.** Pedido [W] 2026-11-06 ("deveria
   ser o sidebar e as abas iguais?"). A lista vem dos `ghosts` do `DataController` do Repair, que
   seguem a barra `MP.Tabs` do protótipo. O componente é o `ModuleTabs`
   (`@/Components/shared/ModuleTabs`), o mesmo das outras telas do módulo.
3. **Sem PageHeader novo.** A tela é um kanban de tela cheia e nunca teve header. A barra entra numa
   faixa própria acima da barra de filtros, sem criar título e sem mexer no resto do layout.
4. **Revisão datada de uma decisão anterior.** O [`RUNBOOK-repair-status.md`](RUNBOOK-repair-status.md),
   decisão F1 nº 2 (2026-09-09), dizia: *"Não portar as abas do protótipo"*, porque o `AppShellV2` já
   roteia. O pedido [W] de 2026-11-06 revisa isso: as abas não substituem o roteamento, elas mostram a
   mesma lista do sidebar dentro da tela. A nota de revisão está naquele RUNBOOK.

## F2 BACKEND

Nada muda. O `ProducaoOficinaController::index()` segue igual. A lista de abas chega pelo
`shell.menu` (shared prop do Inertia), que já vem em toda página.

## F3 FRONTEND — o que muda na forma

| # | De (hoje) | Para |
|---|---|---|
| 1 | barra de filtros colada no topo | faixa com a barra de abas do módulo (`ModuleTabs modulo="Assistência técnica"`) acima dos filtros |
| 2 | sem indicação de onde se está dentro do módulo | aba "Produção" marcada, deduzida pela rota |

Responsivo: a barra rola na horizontal (`scrollable`), então não quebra o UC-RPOE-02 ("cabe em
1280px sem scroll horizontal" da página).

## F4 QA

- Casos que esta onda não pode quebrar: UC-RPOE-01 (5 colunas), UC-RPOE-02 (1280px), UC-RPOE-04
  (zero modal). O teste do componente é `tests/js/module-tabs.test.tsx`; a lista contra o protótipo
  é `tests/js/repair-abas-prototipo.test.tsx`.
- **Testes Pest rodam no CI ou no CT 100**, nunca local. Tenant de teste fictício **98**
  ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)); biz=4 proibido.
- Smoke pós-deploy: abrir `/repair/producao-oficina`, conferir no DOM o `[role=tablist]` com as 7 abas
  e "Produção" com `aria-selected="true"`, e screenshot.

## F5 CUTOVER

Sem cutover: a tela já é a única versão. A barra aparece para quem já vê o item "Assistência técnica"
no menu (mesma regra de pacote e permissão do `DataController`).

## Riscos

| Risco | Mitigação |
|---|---|
| A faixa nova come altura do kanban | uma linha de abas (~40px); o kanban segue `flex-1` |
| Business sem o item no menu (fora do pacote) | `ModuleTabs` não renderiza nada; a tela fica como hoje |
| FSM | a onda não toca `current_stage_id` nem o `move`. [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) intacta |

## Aprovação

Forma segue o protótipo ([ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)); merge segue [W] (R10).

**Última atualização:** 2026-11-06
