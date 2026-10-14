---
id: requisitos-crm-cliente-index-visual-comparison
inertia_target: resources/js/Pages/Cliente/Index.tsx
date: 2026-05-15
---

# Visual Comparison — Cliente/Index (W1-B3)

## Blueprint Cowork
`prototipo-ui/cowork/Wagner/clientes-page.jsx`

## Approval status (ADR 0114)
- ✅ Pattern reuse aprovado via ADR 0149 (Index = canon do blueprint)
- ⏳ Screenshot Wagner pendente (Wave 1 batch sync após canary biz=1)

## Telas derivadas que herdam aprovação
- Cliente/Index (esta — canon)
- Cliente/Create, Cliente/Show, Cliente/Edit (mesma família visual)

## Telas com divergência declarada
- Cliente/Import (wizard upload)
- Cliente/Ledger (tabela financeira)
- Cliente/Map (split-pane)

## 15 dimensões mwart-comparative V4

| Dimensão | Cowork | Inertia | Status |
|---|---|---|---|
| 1. Hierarchy header | h1 22-24px font-semibold | ✅ aplicado | OK |
| 2. KPI cards | 4 cards rounded-xl border | ✅ aplicado | OK |
| 3. Filter pills | rounded-full canon | ✅ aplicado | OK |
| 4. Table density | px-4 py-3 | ✅ aplicado | OK |
| 5. Status badge | rounded-full border 11px | ✅ aplicado | OK |
| 6. Avatar | rounded-md monocromático | ✅ aplicado | OK |
| 7. Drawer 480px right | Sheet UI canon | ✅ aplicado | OK |
| 8. Cores semânticas | rose/sky/stone/emerald | ✅ aplicado | OK |
| 9. Tabular nums | tabular-nums em dinheiro/data | ✅ aplicado | OK |
| 10. Empty state | mensagem PT-BR centrada | ✅ aplicado | OK |
| 11. Loading state | spinner + label | ✅ aplicado | OK |
| 12. Pagination | controls + per_page select | ✅ aplicado | OK |
| 13. Search debounce | 300ms | ✅ aplicado | OK |
| 14. localStorage prefix | oimpresso.cliente.* | ✅ aplicado | OK |
| 15. PII mask | tax_number_masked server | ✅ aplicado | OK |

## Acessibilidade (a11y)
- ARIA labels em filter pills + sort buttons + nav
- aria-sort em SortableTh
- aria-current em pill ativa
- aria-label em search input + actions menu

## Performance targets
- p95 first-paint < 1200ms (com 50 customers)
- KPIs defer < 800ms
- Customers paginate defer < 1500ms

## Gate F1.5 (Wagner aprova screenshot)
- ⏳ Pendente — Wagner valida após canary biz=1 mostrar render correto em prod

## Medição 2026-10-14 (thread cliente/02) — `design-diff` nos dois lados

Mesma sonda (`node scripts/design/design-diff.mjs --probe`, sha256 `a4c1cbf6…8a861c27`) injetada em:
- **prod:** `https://oimpresso.com/cliente` (sessão logada, tema escuro, 1280×900);
- **protótipo:** `prototipo-ui/cowork/Wagner/oimpresso.com.html`, rota `clientes`, tema escuro, 1280×900, servido a partir da raiz do repo (o shell só acha o DS em `../../design-system/` assim — servindo a pasta do dono, 2 folhas voltam vazias e o `--compare` acusa `NÃO MEDI`).

| Item | prod | protótipo | veredito |
|---|---|---|---|
| Título `h1` | 22px · 700 | 22px · 600 | tamanho IGUAL; o "18px" de 2026-09-18 era a página errada (`_saida-02`) |
| Altura da linha | 55,56px | 58,69px (68–71px com 2ª linha de dado) | segue o conteúdo da célula |
| Colunas | foto · Cliente · Tipo · Documento · Cidade/UF · Frescor · Saldo · OS · Tags · ★ · ⋮ | Cliente (com foto) · Tipo · Documento · Cidade/UF · Frescor · Saldo · OS · Tags · **Última OS** · ★ · ⋮ | **DIVERGE** — coluna deslocada em 1 |

Com a coluna deslocada, o `--compare` pareia `col1` da prod (Cliente) com `col1` do protótipo (Tipo): os 12 `DIVERGE (bug)` por célula daquela rodada são, na maioria, efeito do deslocamento, não da célula.

**Decisão [W] 2026-10-14:** seguir o protótipo nas duas — foto dentro da célula Cliente e coluna "Última OS" (UC-CIDX-05). A re-medição depois do deploy fica registrada aqui.
