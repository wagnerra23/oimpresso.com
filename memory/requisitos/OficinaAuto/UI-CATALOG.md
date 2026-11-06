# OficinaAuto — UI Catalog

> **Atualizado em 2026-11-05** (auditoria do módulo, lote 5.6), conferido em `origin/main` `7d997591d7`.
> A versão anterior (2026-05-17, W31-10) era "auto-gerada" por `/tmp/generate_catalogs.sh`, que
> não existe no repo, e listava telas que não existem mais (`ServiceOrders/Index`,
> `ProducaoOficina/Index`). Não há gerador: esta é uma lista conferida à mão.
>
> **Cobertura (charter, casos, e2e, scorecard, a11y) não se restateia aqui** — é derivada:
> `npm run screen-coverage:report` e `npm run casos:report`.

Pages dir: `resources/js/Pages/OficinaAuto/`. Zero Blade servido pelo módulo (`SCOPE.md`, `migracao_ui`).

## Telas Inertia

| Tela | Rota | RUNBOOK |
|---|---|---|
| `ServiceOrders/Board.tsx` | `/oficina-auto/ordens-servico` (e `/board`) — Quadro · Lista · Grade · Fila | `RUNBOOK-board.md` |
| `ServiceOrders/Create.tsx` | `/oficina-auto/ordens-servico/create` | `RUNBOOK-create.md` |
| `ServiceOrders/Edit.tsx` | `/oficina-auto/ordens-servico/{order}/edit` | `RUNBOOK-edit.md` |
| `ServiceOrders/Show.tsx` | `/oficina-auto/ordens-servico/{order}` | `RUNBOOK-show.md` (V0); `RUNBOOK-service-orders-show.md` no PR #10465 |
| `Vehicles/Index.tsx` | `/oficina-auto/veiculos` | `RUNBOOK-index.md` |
| `Vehicles/Create.tsx` | `/oficina-auto/veiculos/create` | `RUNBOOK-create.md` |
| `Vehicles/Edit.tsx` | `/oficina-auto/veiculos/{vehicle}/edit` | `RUNBOOK-edit.md` |
| `Vehicles/Show.tsx` | `/oficina-auto/veiculos/{vehicle}` | `RUNBOOK-show.md` |
| `AprovacaoPublica.tsx` | `/aprovar-os/{token}` (pública, sem login) | `RUNBOOK-aprovacao-publica.md` (criado no PR #10465) |

Todas têm `.charter.md` e `.casos.md` ao lado do `.tsx`.

## Notas

- O drawer rico da OS (`ProducaoOficina/_components/ServiceOrderRichSheet.tsx`) vive em
  `Pages/OficinaAuto/ProducaoOficina/_components/` e é consumido pelo Board; a página
  `ProducaoOficina/Index` (kanban genérico) é do módulo Repair (`Pages/Repair/ProducaoOficina/`).
- A lista separada de OS (`ServiceOrders/Index.tsx`) foi aposentada em 2026-06-11: a rota
  `/ordens-servico` renderiza o Board.

## Convenções

- Charter obrigatório antes de `Write` em `.tsx` ([ADR 0104](../../decisions/0104-processo-mwart-canonico-unico-caminho.md));
  o hook `block-mwart-violation` exige o RUNBOOK da tela.
