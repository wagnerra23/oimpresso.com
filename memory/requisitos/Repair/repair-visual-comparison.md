# Visual Comparison — Repair/Show Wave 3 B6 MWART

> **Wave:** W3-B6 Repair · **Tela:** Repair/Show (venda-de-reparo)
> **Refs:** [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md) · [ADR 0149](../../decisions/0149-mwart-screen-pattern-reuse-cowork.md)

## Pattern Reuse

Blueprint canônico: **`prototipo-ui/prototipos/os/cowork-app.jsx`** (OsDetailPanel adaptado).

| Aspecto | Blueprint | Repair/Show |
|---|---|---|
| Header | título + status + ações | título "Venda de Reparo #..." + status badge + ações Edit/Print |
| Layout | 2 cols (detalhe + sidebar) | lg:grid-cols-3 (2/3 detalhe + 1/3 sidebar) |
| Dados principais | timeline + descrição OS | detalhes venda (data, prazo, aparelho, defeitos, total) |
| Sidebar | actions | Pagamentos + (opcional) FSM Sells panel |
| Linhas/itens | — (drawer-only) | Sell lines table (peças/serviços faturados) |
| Timeline | activities | Deferred activities |

## Divergência justificada

Repair/Show é **VENDA-de-reparo** (Transaction sub_type=repair), não OS. Por isso adiciona:
- Sell lines table (peças/serviços faturados)
- Payments list
- Warranty info
- Opt-in FSM Sells panel (ADR 0143 - canônico via flag)

Blueprint cowork-app.jsx mostra OS (job sheet) — semelhante mas semanticamente diferente. UI **distingue claramente** via título "Venda de Reparo #..." vs "OS #...".

## Status visual aprovação

**PENDENTE Wagner sign-off** via screenshot real após `npm run build`.

## Pest

`Wave3B6RepairShowTest.php` — 4 testes (flag OFF Blade · flag ON Inertia · cross-tenant 404 · FSM flag independente).
