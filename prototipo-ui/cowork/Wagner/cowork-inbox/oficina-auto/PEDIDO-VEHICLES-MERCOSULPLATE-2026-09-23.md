# PEDIDO — OficinaAuto/Vehicles: placa via `MercosulPlate` nas 4 telas (2026-09-23)

> **Dono:** [CL]. **Lido no `main` @`cd78c7a10f66` neste turno.** Fonte do gap: `memory/requisitos/OficinaAuto/vehicles-index-gap.md` (09/09) + FRESCOR quadro OficinaAuto.
> **Não é EXPORT de layout:** não há protótipo destas telas (exclusão declarada em `oficina-forms.jsx:7` / `ancora.mjs` → `n/a`). A âncora é o **charter** + o componente canon que já roda em produção. Sem ALVO medido ⇒ não mexer em mais nada além da placa.

## Por quê (medido hoje)
- `resources/js/Pages/OficinaAuto/Vehicles/Index.tsx:398` imprime `{v.vehicle_number ?? v.plate}` como **texto puro**.
- `Index.charter.md:34` (Goal) exige *"Coluna placa com componente MercosulPlate"*; `:56` (Anti-pattern) proíbe *"Placa em texto puro"*. A tela viola o próprio charter.
- O componente existe e já é usado fora de Vehicles: `resources/js/Components/shared/MercosulPlate.tsx` (consumidores citados no gap: `Sells/Create.tsx:1206`, `Sells/_components/SellsTabelaUnificada.tsx:364`, `ServiceOrders/Board.tsx`, `ServiceOrderKanbanCard.tsx:250`, `ServiceOrderRichSheet.tsx:366`).
- Cliente: `Index.charter.md:24` — Martinho, 2026-05-26: *"placa Mercosul ficou top"*.

## O que fazer (1 PR, ≤300 linhas, prefixo `OficinaAuto/Vehicles`)
1. **Index** — trocar a célula de `:398-401` por `<MercosulPlate plate={v.plate} size="sm" />`; `vehicle_number` vira rótulo secundário (texto pequeno abaixo/ao lado). Tamanho `sm` = precedente de lista (`SellsTabelaUnificada.tsx:364`). Placa secundária (cavalo + reboque, ADR 0194): seguir o par de `Sells/Create.tsx:1206-1207`.
2. **Show** — cabeçalho/ficha: `MercosulPlate` `size="md"` onde a placa aparece.
3. **Create / Edit** — prévia da placa ao lado do campo (atualiza enquanto digita), `size="sm"`. O campo continua sendo input de texto; o componente é só leitura.
4. `aria-label` na placa com o texto da placa (leitor de tela não lê o desenho).

**Localizar em Show/Create/Edit:** `git grep -n "plate" resources/js/Pages/OficinaAuto/Vehicles/` — **não conferi as linhas dessas 3 telas neste turno**.

## O que NÃO fazer neste PR
- Não mexer em KPIs (`KpiCard` local × shared), filtro `vehicle_type`, `Deferred`, `_form/VehicleForm`, atalhos — todos estão no gap como **decidir** ou onda própria.
- Não tocar no resíduo `CurrentRental` / `current_status.locada` (decisão [W], fora do P0 da ADR 0265).
- Não usar o `PlacaVeiculo` do DS: o gap fixa `MercosulPlate` (vivo no repo); convergir os dois é decisão de DS.
- Nenhum rótulo com `cacamba`/`recapagem` — `dominio:check` estoura.

## Prova
- `git grep -c MercosulPlate resources/js/Pages/OficinaAuto/Vehicles/` ≥ 4 arquivos (hoje: 0 nos `.tsx`).
- `Index.tsx` sem `{v.vehicle_number ?? v.plate}` como conteúdo de célula.
- Charter: `status` e `last_run` do `Index.casos.md` atualizados; UC de forma da placa acrescentado (hoje nenhum UC cobre).
- Recibo: `_saida-01.md` nesta pasta, escrito por quem executar.
