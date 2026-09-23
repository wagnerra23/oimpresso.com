---
sessao: "_saida-01"
thread: "01 · Vehicles: placa via MercosulPlate nas 4 telas"
dono: "[C]"
data: 2026-09-23
tipo: recibo
base_lida: wagnerra23/oimpresso.com@main 1061dbf2e
---
# _saida-01

## Entregue
- **Index** — a célula que imprimia `{v.vehicle_number ?? v.plate}` em texto puro (contra o
  Goal e o anti-padrão do `Index.charter.md`) agora renderiza `<MercosulPlate size="sm">`, com a
  placa secundária ao lado (par de `Sells/Create.tsx`) e `vehicle_number` como rótulo secundário.
- **Show** — `MercosulPlate size="md"` (principal + secundária) no grupo de ações do `PageHeader`.
  O título do `PageHeader` segue sendo a placa em texto (título da página / `<Head>`).
- **Create / Edit** — prévia `size="sm"` abaixo de cada input de placa, só quando há texto,
  atualizando enquanto digita. O input continua sendo o campo; o componente é só leitura.
- **a11y** — nenhuma mudança necessária: o componente já emite `role="img"` +
  `aria-label="Placa <texto>"`.
- `Index.casos.md` — o `[BACKLOG]` da placa foi atualizado com o fato datado; **não** virou UC,
  porque não há teste de render que o cite (G-2).

- **PageHeader (exigência do gate, ADR 0409)** — tocar Show/Create/Edit acordou a dívida do
  header antigo (`pageheader-migration-guard`: "alterar a unidade exige a cura no mesmo PR").
  As 3 telas migraram de `@/Components/shared/PageHeader` para o canon `@/Components/PageHeader`
  (`description`→`subtitle`, `action`→`actions`; o `icon="car"` saiu, pois o canon não tem esse
  slot — igual às migrações do Financeiro). Baseline da dívida 68 → 65.

## Provas
1. `git grep -c MercosulPlate resources/js/Pages/OficinaAuto/Vehicles/*.tsx` → 3 em cada uma das 4 telas.
2. `Index.tsx` sem `{v.vehicle_number ?? v.plate}` como conteúdo de célula (resta só no
   `aria-label` do checkbox, que é texto de leitor de tela).
3. `npx tsc --noEmit -p .` — nenhum erro nas linhas tocadas. O único erro em `Vehicles/` é
   `Index.tsx:181` (`preserveScroll` em `ReloadOptions`), **pré-existente** e fora do diff.
4. `npx eslint` nas 4 telas — 0 erros (4 warnings pré-existentes, fora do diff).
5. `npm run dominio:check` — sem divergências novas.

## Não feito (escopo fechado pelo pedido)
KPIs, filtro `vehicle_type`, `Deferred`, `_form/VehicleForm`, atalhos, `CurrentRental`,
convergência `PlacaVeiculo` × `MercosulPlate`. Smoke de tela em biz=1 fica pós-merge/deploy (R1) —
não rodei preview local (sem PHP local; testes só no CT 100).
