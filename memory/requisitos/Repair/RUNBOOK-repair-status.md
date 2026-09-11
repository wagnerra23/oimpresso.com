---
title: "RUNBOOK MWART — Repair/Status/Index"
module: Repair
tela: Repair/Status/Index
owner: W
status: ativo
last_validated: "2026-09-09"
related_adrs: [0104-processo-mwart-canonico-unico-caminho, 0143-fsm-pipeline-live-prod-marco-2026-05-12, 0093-multi-tenant-isolation-tier-0]
---

# RUNBOOK MWART — Repair/Status/Index

> **Tela:** `/repair/status` · **Componente:** `resources/js/Pages/Repair/Status/Index.tsx`
> **Fonte de design:** `prototipo-ui/cowork/Wagner/repair-page.jsx` região `Status` (L304-337) + `repair-page.css` (`.rep-status-row`, `.rep-st`)
> **Diff medido:** [6telas-index-visual-comparison.md §3.4](6telas-index-visual-comparison.md)
> **Refs:** [ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md) · [ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md) · [ADR 0093](../../decisions/0093-multi-tenant-isolation-tier-0.md)

## Status

| Item | Estado |
|---|---|
| Blade legacy | `Modules/Repair/Resources/views/status/index.blade.php` (preservado, incluído por Settings) |
| Inertia branch | `RepairStatusController::index()` L74-95 — já existia sob flag MWART |
| Flag | `mwart.repair_status_index` (`mwartEnabled`, mesmo padrão do `RepairController`) |
| Charter | `resources/js/Pages/Repair/Status/Index.charter.md` (`status: live`) |
| Casos | `resources/js/Pages/Repair/Status/Index.casos.md` |

## Decisões F1 PLAN

1. **A tela já é Inertia.** Esta onda não migra Blade→React: ela **porta a FORMA** do
   protótipo para a tela React que já existe. F2/F3 abaixo são incrementais.
2. **Não portar as abas** do protótipo. `RepairPage` é uma página com 8 abas porque o shell
   do Cowork é um HTML único sem roteador; aqui o `AppShellV2` já roteia. Importar as abas
   seria trazer solução de um problema que não temos.
3. **A cor do selo vem do dado**, não do DS — `repair_statuses.color` é escolha do usuário
   por business. O protótipo é explícito nisso (comentário em `repair-page.jsx:28`).
4. **"Coluna do kanban" fica FORA.** O protótipo mostra `coluna {s.coluna}`, mas
   `repair_statuses` não tem essa coluna no schema (conferido em `2019_03_07_155813`,
   `2020_07_11_120308`, `2020_08_22_104640`). É escopo novo — não se fabrica número em tela.

## F2 BACKEND — o que o payload passa a mandar

`RepairStatusController::index()`, ramo Inertia:

| Campo | Origem | Por quê |
|---|---|---|
| `sms_template` | coluna existente (`2020_08_22_104640`) | o protótipo mostra `SMS: "…"` na linha |
| `job_sheets_count` | `JobSheet::where('business_id', …)->groupBy('status_id')` | o protótipo mostra "N folha(s)"; é o que diz se dá pra excluir sem deixar folha órfã |

**Multi-tenant Tier 0:** as duas queries filtram `business_id` explicitamente
(`JobSheet` não tem global scope — é o idioma do módulo, ver `DataController:242`,
`DeviceModelController:449`, `JobSheetController:688`). Uma query agregada a mais, sem N+1.

**Não toca:** ramo `ajax()`/DataTables (Blade legacy), `store`, `update`, `destroy`.

## F3 FRONTEND — o que muda na forma

| # | De (hoje) | Para (protótipo) |
|---|---|---|
| 1 | `<table>` de 5 colunas | lista de linhas (`.rep-status-row` → grid de 5 faixas) |
| 2 | bolinha + hex em mono | pill `.rep-st` com a cor do dado (`color-mix` 12% fundo / 30% borda) |
| 3 | — | `ordem N · N folha(s)` em mono |
| 4 | ícone check / travessão | texto "marcado como concluído" / "pendente" |
| 5 | — | `SMS: "…"` truncado (oculto abaixo de `xl`, como o `@media` do protótipo) |
| 6 | — | `Alert` de FK: apagar status usado deixa folha órfã |
| 7 | — | rodapé com a permissão `access_job_sheet_status` |
| 8 | "Status de OS (Repair)" | "Status do reparo" + descrição que diz que a ordem alimenta o kanban |

Responsivo: o protótipo colapsa para 2 colunas abaixo de 1100px e esconde o SMS. A porta usa
`grid-cols-[1fr_auto]` + `xl:grid-cols-[190px_230px_160px_1fr_auto]` — o monitor da Larissa é
1280px, então a faixa `xl` (1280) é a que ela vê.

## F4 QA

- Pest de contrato: `Modules/Repair/Tests/Feature/RepairStatusIndexContratoTest.php` —
  payload traz `sms_template` e `job_sheets_count`; contagem bate; cross-tenant não vaza.
- **Testes rodam no CT 100**, nunca local nem Hostinger:
  `tailscale ssh root@ct100-mcp "docker exec oimpresso-staging php artisan test --filter=RepairStatusIndexContrato"`
- Tenant de teste é o fictício **98** ([ADR 0358](../../decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md)) — biz=4 proibido.

## F5 CUTOVER

Sem cutover: a flag `mwart.repair_status_index` já governa quem vê a tela React. Esta onda
muda a forma de quem já está na tela nova; o Blade segue intacto para quem não está.

## Riscos

| Risco | Mitigação |
|---|---|
| `color` inválido/nulo vindo do legado quebra o `color-mix` | `color` é `nullable` no schema — a porta cai no fallback do CSS quando vem vazio; conferir no smoke |
| Contagem cara em business com muitas folhas | agregada única com `groupBy`, não N+1; `status_id` é indexado por FK |
| FSM | esta tela **não** toca `current_stage_id` nem transição — só configuração de status. [ADR 0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md) intacta |

## Aprovação

Forma é soberania do protótipo ([ADR UI-0029](../_DesignSystem/adr/ui/0029-prototipo-soberano-sobre-adr-ui.md)); merge segue [W] (R10).
