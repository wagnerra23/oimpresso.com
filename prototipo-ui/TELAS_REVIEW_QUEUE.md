# TELAS_REVIEW_QUEUE.md — fila de telas pra review

> Lista priorizada de telas pra passar pelo loop Claude Design.
> Wagner pode reordenar. Status atualizado a cada movimento de fase.

**Legenda:**
- `[ ]` pending — não começou
- `[~]` in-flight — em alguma fase F0-F3.5
- `[x]` done — mergeada
- `[!]` blocked — bloqueada (Wagner explica em SYNC_LOG)

---

## P0 — Coração da venda

| Status | Tela | Prioridade visual | Charter |
|---|---|---|---|
| `[ ]` | [`Sells/Create`](../resources/js/Pages/Sells/Create.tsx) | Larissa 1280px — KPIs gigantes, action bar sticky, split pagamento | [existe](../resources/js/Pages/Sells/Create.charter.md) |
| `[ ]` | [`Sells/Index`](../resources/js/Pages/Sells/Index.tsx) | Larissa — densidade tabela, drawer SaleSheet, abas filter sync | [existe](../resources/js/Pages/Sells/Index.charter.md) |

## P1 — Fluxos com charter existente

| Status | Tela | Prioridade visual | Charter |
|---|---|---|---|
| `[x]` | [`Repair/ProducaoOficina`](../resources/js/Pages/Repair/ProducaoOficina/Index.tsx) | Larissa 1280px — kanban 5 colunas, drawer com banner aprovação | [existe](../resources/js/Pages/Repair/ProducaoOficina/Index.charter.md) — F1→F3 em 1 dia (PR #326→#330, 2026-05-09) |
| `[ ]` | [`Repair/Dashboard`](../resources/js/Pages/Repair/Dashboard/Index.tsx) | Técnico mobile-first | [existe](../resources/js/Pages/Repair/Dashboard/Index.charter.md) |
| `[ ]` | [`Repair/JobSheet`](../resources/js/Pages/Repair/JobSheet/Index.tsx) | Técnico mobile, status visíveis a 2m | [existe](../resources/js/Pages/Repair/JobSheet/Index.charter.md) |
| `[ ]` | [`Repair/Status`](../resources/js/Pages/Repair/Status/Index.tsx) | Técnico mobile, transição clara | [existe](../resources/js/Pages/Repair/Status/Index.charter.md) |
| `[ ]` | [`Financeiro/ContasBancarias`](../resources/js/Pages/Financeiro/ContasBancarias/Index.tsx) | Eliana — número grande saldo Mercury-like | [existe](../resources/js/Pages/Financeiro/ContasBancarias/Index.charter.md) |
| `[ ]` | [`Financeiro/Extrato`](../resources/js/Pages/Financeiro/Extrato/Index.tsx) | Eliana — entrada/saída sem depender só de cor | [existe](../resources/js/Pages/Financeiro/Extrato/Index.charter.md) |
| `[ ]` | [`ProjectMgmt/Board`](../resources/js/Pages/ProjectMgmt/Board/Index.tsx) | Wagner+Time — Kanban Linear-like | [existe](../resources/js/Pages/ProjectMgmt/Board/Index.charter.md) |
| `[ ]` | [`governance/Dashboard`](../resources/js/Pages/governance/Dashboard.charter.md) | Wagner — KPIs saúde sem virar Christmas tree | [existe](../resources/js/Pages/governance/Dashboard.charter.md) |

## P2 — Telas quentes sem charter (gerar charter junto)

| Status | Tela | Prioridade visual | Charter |
|---|---|---|---|
| `[ ]` | [`Jana/Chat`](../resources/js/Pages/Jana/Chat.tsx) | Wagner+Larissa+Eliana — IA conversacional | [existe](../resources/js/Pages/Jana/Chat.charter.md) |
| `[ ]` | [`NfeBrasil/Tributacao/Index`](../resources/js/Pages/NfeBrasil/Tributacao/Index.tsx) | Eliana — tela densa CFOP/CSOSN | criar |
| `[ ]` | [`NfeBrasil/Transactions/NfceStatus`](../resources/js/Pages/NfeBrasil/Transactions/NfceStatus.tsx) | Larissa — polling status fiscal real-time | criar |
| `[ ]` | [`Whatsapp/Conversations/Index`](../resources/js/Pages/Whatsapp/Conversations/Index.tsx) | Atendente — inbox 3-col Front/Intercom-like | criar |

## P3 — Site público (vitrine de vendas)

| Status | Tela | Prioridade visual | Charter |
|---|---|---|---|
| `[ ]` | [`Site/Home`](../resources/js/Pages/Site/Home.tsx) | Visitante — copy verbo-de-ação Com. Visual | criar |
| `[ ]` | [`Site/Pricing`](../resources/js/Pages/Site/Pricing.tsx) | Lead — tier visual SaaS | criar |
| `[ ]` | [`Site/Login`](../resources/js/Pages/Site/Login.tsx) | Lead/Cliente — formal + SaaS-friendly | criar |

---

## Critérios pra mover de coluna

- `[ ] → [~]`: Wagner adicionou em [COWORK_NOTES.md](COWORK_NOTES.md) com pedido completo
- `[~] → [x]`: PR de F3 mergeada + a11y-report sem critical
- `[~] → [!]`: bloqueador explicado em [SYNC_LOG.md](SYNC_LOG.md)

## Reordenação

Wagner pode subir P2/P3 pra P0/P1 a qualquer momento — basta editar este arquivo na PR e justificar em [SYNC_LOG.md](SYNC_LOG.md).
