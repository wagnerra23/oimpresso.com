---
id: requisitos-oficina-auto-ordens-servico-board-gap
tela: OficinaAuto/ServiceOrders/Board (/oficina-auto/ordens-servico)
prototipo: prototipo-ui/cowork/oficina-page.jsx
tela_viva: resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx
paridade_atual: PARIDADE — vivo à frente em arquitetura (FSM real, dado real, 4 views in-page)
gerado_em: "2026-09-06"
gerado_por: "[C] — Fase 1 read-only (aplicar-prototipo), por grep real nos dois lados"
---

# Gap — Oficina Auto · workspace de OS (`Board.tsx`) × `oficina-page.jsx` (mockup Cowork)

> **Read-only.** Fase 1 da skill `aplicar-prototipo`. Este é o **par correto** para `oficina-page.jsx`:
> a âncora é computada do charter (`resources/js/Pages/OficinaAuto/ServiceOrders/Board.charter.md:5`
> `visual_source: oficina-page.jsx`; `node prototipo-ui/ancora.mjs OficinaAuto/ServiceOrders/Board --staging prototipo-ui/cowork`
> → `[-page.jsx (bundle · bundle_source)] oficina-page.jsx`). O gap anterior
> ([`kanban-producao-gap.md`](kanban-producao-gap.md), 2026-06-30) comparava o mesmo mockup com
> `Repair/ProducaoOficina` — par revogado como MIS-ANCHOR no charter do Repair em 2026-06-30 (`Index.charter.md:14-17`).
>
> - **Mockup:** `prototipo-ui/cowork/oficina-page.jsx` (1296 ln; cabeçalho `:1-9`: "Oficina Auto (vertical) embedada no shell unificado … Sprint paridade CRUD (2026-05-26 m0193): Nova OS + drawer, itens inline, DVI inline, StageGate").
> - **Vivo:** `resources/js/Pages/OficinaAuto/ServiceOrders/Board.tsx` (1300 ln) — tela ÚNICA em `/oficina-auto/ordens-servico` e `/board` (charter v6, `status: live`, biz=164 Martinho).
> - **Método:** cada parte abaixo tem `arquivo:linha` medido em 2026-09-06 nos DOIS lados (`grep -n`); a coluna Ação deriva do charter (Goals / Non-Goals / Anti-hooks) e do código, nunca da descrição do mockup.

## Restrições Tier 0 que enquadram TODO este gap

- **ADR 0194/0265 — Oficina é REPARO/MECÂNICA** (Martinho: caminhão basculante). Vocabulário automotivo (placa/km/box/mecânico) é legítimo **nesta** tela; no `Repair/ProducaoOficina` não é (guard `repair-shared-vocab.yml`) — foi exatamente isso que fez o par antigo ser MIS-ANCHOR.
- **FSM via `ExecuteStageActionService` (ADR 0143).** Charter Anti-hook: nunca `update(['current_stage_id' => …])`. As duas portas (drag + botão) passam por confirmação + service.
- **Regra mestre VALOR/ESTOQUE.** KPI "Valor em curso", coluna Valor da Lista e peças/MO do drawer exibem/editam valor. O que está no vivo já vive atrás do backend + FSM; **nada do mockup é adotado nesse eixo** — só descrito.
- **`no-mock-in-prod` (Non-Goal do charter):** campo sem coluna real (ETA-diag, "Encomendado: peça chega X", "Pago") e heurística sintoma→serviço da Grade ficam **fora** — reentram só com schema.

## Tabela de partes (medida 2026-09-06)

| Parte | Protótipo (`oficina-page.jsx`) | Estado no vivo (`Board.tsx`) | Ação |
|---|---|---|---|
| Header + título | `:963-972` — `<h1>Oficina Auto</h1>` + subtítulo + botões `Imprimir fila` (`:970`) e `Nova OS` (`:972`). | `:685-700` — `<h1>` "Oficina Auto" (`:687`), subtítulo (`:688`), `Imprimir fila` ghost (`:693-695`, `data-testid="board-print-fila"`), `Nova OS` → `/oficina-auto/ordens-servico/create` (`:696-699`). | Nada — paridade (Onda 1; charter §Goals "header fica só com Imprimir fila + Nova OS", `Board.charter.md:56-59`). |
| KPIs | `:977-1005` — 6 cartões clicáveis (`kpiClick`, `kpiFilter` `:753`, filtro `:822`), incl. Urgentes e "Valor em curso". | `:477-484` `kpiCards` (6, com sublinha; "Valor em curso" = `formatBRL(kpis.valor_em_curso)` do backend, `filterKey: null`) · `:354` `kpiClick` · `:486-492` predicado client-side · `:708` render · chip "limpar filtro" (canon D-05, cabeçalho `:38`). | Nada — paridade (charter §Goals "KPIs com sublinha … 5 clicáveis como filtro"). ⚠️ valor: o KPI só **exibe** o agregado computado no backend (`kpis.valor_em_curso`), não soma no front — é o que o gap antigo (parte 2) exigia. |
| Filtros — boxes/elevadores | `:1010-1025` `.prod-equip-filters` — "Todos os boxes" + 1 botão por recurso com contagem (`RECURSOS` `:41-47`, hardcoded). | `:316-319` estado da aba · `:501-510` contagem por box sobre `columns` · `:720` render das abas (paridade `.prod-equip-filters`); boxes vêm de `filterOptions.boxes` (`:545`), não hardcoded. | Nada — paridade (charter §Goals "Abas de box/elevador (filtro client-side com contador)"). Data-driven onde o mockup era `RECURSOS` fixo. |
| Busca livre | `:1029-1033` input "placa · veículo · cliente · sintoma · #OS" + limpar. | `:769-772` `type="search"` "Buscar OS, placa ou cliente…  ( / )" + botão limpar; atalho `/` (`:600`, canon D-07). | Nada — paridade (cabeçalho `:42` "busca com botão limpar (×) + atalho /"). |
| Toggles de view | `:1036-1048` toggle Kanban/Lista/Grade/Fila · menu "Visão" (`:1051-1070`: Foco + Densidade) · Fila via `window.OficinaFila.FilaView` (`:1094-1095`) · Grade `:1111` (heurística sintoma→serviço) · Lista `:1191`. | `:175-181` `BoardView`/`BOARD_VIEWS` (quadro · lista · grade · fila) · `:759` barra `.ofc-view-toolbar` · `:836-874` menu Visão (Foco `:322-331` + Densidade `:326-336`, persistidos) · `:900-909` render por view · `:905` `<BoardFila>` · Grade "sempre por etapa" (`:880`). | Nada — paridade (tela unificada [W] 2026-06-11; charter §Goals "Toggle de 4 views — TODAS in-page"). A Grade **não** adota a heurística sintoma→serviço — Non-Goal do charter (`no-mock-in-prod`): a marca espelha só a etapa FSM real. |
| Colunas + cards de OS | `:63` `STAGES` (5 etapas fixas) · `:194-340` `CardRecepcao`/`CardDiagnostico`/`CardPecas`/`CardExecucao`/`CardPronto` · `:176` `StageGateMini` (gate por etapa, localStorage) · ETA/Encomendado/Pago nos cards. | Colunas data-driven do payload `columns` (`:133`, `:524`); `:909-931` `KanbanDndProvider` + `:946` `DragConfirmDialog` (drag → confirmação → FSM); card via `ServiceOrderKanbanCard` (`:97`) com `MercosulPlate` (`:89`), contador DVI x/y, barra de progresso, linha "últ." (cabeçalho `:19,56`); botão de ação por etapa (charter §Goals "duas portas"). ETA-diag / "Encomendado" / "Pago": omitidos. | Nada — paridade nas partes com lastro (Onda 1.5) + Non-Goal do charter para as sem coluna real ("NÃO inventa campo sem lastro … reentram quando houver schema"). Avanço nunca por `UPDATE current_stage_id` (Anti-hook). |
| Drawer (detalhe da OS) | `:394-730` `Drawer` — venda vinculada, veículo, DVI editável com semáforo e valor, Fotos & Laudo, Peças & MO editável, StageGate, timeline, "Imprimir OS" (`:705`). | `:938` `<ServiceOrderRichSheet>` (drawer rico) · `:1239` `<ServiceOrderRichBody>` inline na Fila (mesmo corpo, 2 chromes — charter §Goals Onda 2 v5); corpo = DVI semáforo / Fotos & Laudo / Peças & MO / Checklist de etapa / Pipeline FSM / Timeline. | Nada — paridade (Onda 2). ⚠️ valor: edição de peças/MO/DVI toca valor e já vive atrás do FSM + Regra Mestre no vivo; nada a adotar do mock (que edita estado React local). |
| Impressão | `:970` `window.OficinaPrint.printFila` · `:705` `printOS` — helper `oficina-print.js` referenciado e **ausente** no bundle (o `_pendente_` do gap antigo, parte 8). | `:88` `import { printOficinaFila } from '@/Lib/printOficinaFila'` · `:638-665` folha A4 da fila com os filtros aplicados · OS individual: `resources/js/Pages/OficinaAuto/ProducaoOficina/_components/ServiceOrderRichSheet.tsx:589-592` "Imprimir OS · A4". | Nada — fechado no código: o `_pendente_` (fonte do `OficinaPrint`) foi resolvido com helper próprio; fila e OS individual existem. |

## Veredito: **PARIDADE — vivo à frente em arquitetura**

As 8 partes do mockup estão no vivo (Onda 1 · 1.5 · 2, 2026-06-10/11) com dado real e FSM real; onde o mockup inventava (ETA, "Pago", heurística da Grade, gate em localStorage) o charter fecha por Non-Goal. **Nada a adotar** desta rodada. O que este gap entrega é o **anchor-map** (`ordens-servico-board.map.json`, âncora dupla por parte, `prototipo_sha` por conteúdo) — a Fase 4 (`consumir-map.mjs`) aborta se o `oficina-page.jsx` re-exportar.

**Não regredir (vivo à frente):** colunas data-driven do FSM · boxes de `filterOptions` · 4 views sobre 1 payload · `ServiceOrderRichBody` único (1 corpo, 2 chromes) · `printOficinaFila.ts`.

**Fora deste gap (dono próprio):** `Repair/ProducaoOficina` (kanban genérico do Repair — charter Non-Goal "NÃO mexe no board de caçamba"); a tela "Nova OS" (`oficina-os-page.jsx` → [`oficina-os-nova-prototipo-visual-comparison.md`](oficina-os-nova-prototipo-visual-comparison.md)).
