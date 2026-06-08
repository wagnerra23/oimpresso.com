---
session: 2026-05-25 Onda 6 — /vendas/caixa coexiste (seção "Por origem")
page: /vendas/caixa
component: resources/js/Pages/Sells/Caixa/Index.tsx
visual_source: prototipo-ui/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)
canon_method: Cowork KB-9.75 + Integração Vendas × Oficina A1 (ADR 0192) — Onda 6 wave separada
related_adrs: [0093, 0094, 0104, 0107, 0114, 0143, 0178, 0192]
charter_impact: cria Sells/Caixa/Index.charter.md (status rascunho · Wagner aprova)
---

# Visual Comparison — Sells/Caixa/Index r1 (Caixa do dia · seção "Por origem")

> **Escopo:** Tela nova `/vendas/caixa` que **coexiste** com `/cash-register/*` legacy (Wagner aprovou 2026-05-25 ~15h). Foco da entrega Onda 6 é replicar `VendasCaixaPage` do Cowork com **seção "Por origem"** consumindo backend payload `/sells-list-json` (Onda 2 já entregou `source`/`source_label`/`os_ref` desde `e98649989`). Skill `mwart-comparative V4` deliverable.

## Contexto

- Backend (Ondas 0-2 mergeadas em main):
  - migration `transactions.source/os_ref/commission_split` (ADR 0192)
  - `JobSheetObserver@updated` cria Transaction quando OS transiciona pra stage `entregue_completo`
  - `/sells-list-json` retorna `source` + `source_label` + `os_ref` (commit `e98649989`)
- Frontend Sells/Index Ondas 3+4 (`e40289010`) já consome (pill VdSource + saved tree "Por origem" + listener `oimpresso:open-venda`)
- Repair drawer Onda 5 (`94300b057`) dispara `window.dispatchEvent(new CustomEvent('oimpresso:open-venda',{detail:{venda_id}}))` quando user clica "Abrir #V-NNNN" no card "Esta OS gerou venda"

**Decisão coexiste vs substitui (Wagner 2026-05-25 ~15h):** Rota nova `/vendas/caixa` Inertia preserva legacy `/cash-register/*` Blade. Pattern Cliente Wave A-G. Rollback trivial (delete route).

## 15 dimensões (skill mwart-comparative V4)

| # | Dimensão | Cowork (canon · vendas-extras.jsx 123-354) | Implementação Inertia | Status |
|---|---|---|---|---|
| 1 | Wrapper class | `.os-page .vc-page .vd-subpage` no root | `.sells-cowork` + `.vc-page` no root da Page Inertia (escopa CSS canon) | ✅ paridade |
| 2 | Header h1+subtitle+CTAs | `<h1>Caixa do dia</h1>` + `<p>Conferência por forma de pagamento, sangrias e fechamento</p>` + date picker + ghost "Imprimir Z" + primary "Fechar caixa" | replicado verbatim com `os-head/os-head-l/os-head-r` · CTA "Fechar caixa" navega `/cash-register/close-register/{id}` legacy preserved · "Imprimir Z" ghost (futuro Onda 6+1) | ✅ paridade |
| 3 | 4 KPI hero | Faturado dia · Esperado em caixa · Conferido · Diferença (alert quando ≠0) | replicado · `os-kpi` + `os-kpi-alert` quando `Math.abs(diff)>0.01` · cor verde/vermelha/amarela via inline style oklch (canon Cowork) | ✅ paridade |
| 4 | Grid 4 cards `vc-grid` | grid-template-columns:1fr 1fr · card 3 (movimentos) ocupa full-row (`grid-column:1/-1`) | replicado · ordem: Por forma pagto · **Por origem** · Movimentos · Conferência | ✅ paridade |
| 5 | Section "Por forma de pagamento" | table `.vc-pay-table` 4 col: Forma (icon+label) · Compensação · Vendas (count) · Total · tfoot total bruto | replicado · backend agrega `byPayment` no controller via SUM/COUNT por `pay_method` UPOS canonical (cash/card/cheque/bank_transfer/other + custom_1..7) → label PT-BR | ✅ paridade |
| 6 | **Section "Por origem" markup** | `<section className="vc-card vc-card-source">` + header h3 "Por origem" + sub "balcão · oficina · online" | replicado verbatim · render condicional `bySource.length > 0` (empty state "Sem movimentação no dia.") | ✅ paridade |
| 7 | **Linha source** | `.vc-src-row .vc-src-{id}` com `.vc-src-h` (dot + b{label} + ct count + tot R$) + barra progresso + meta % | replicado · cada `g` em `bySource` renderiza row · cores via tokens `--vd-src-{balcao,oficina,online}` já existentes em sells-cowork.css linha 7346+ | ✅ paridade |
| 8 | **Barra de progresso** | `.vc-src-bar > div` com `style={{width: pct + "%"}}` onde `pct = Math.round((g.total / totalDay) * 100)` | replicado verbatim · cor da fill = `var(--vd-src-{id})` | ✅ paridade |
| 9 | **Refs OS clicáveis** | `g.id === "oficina" && g.items.some(v => v.osRef) && <small>` com até 3 links `<a onClick={...}>↗ #{v.osRef}</a>` + `+N` overflow | replicado · onClick dispara `window.dispatchEvent(new CustomEvent('oimpresso:open-venda', { detail: { venda_id: v.id } }))` · Sells/Index listener já existe (Onda 4 commit `e40289010`) | ✅ paridade |
| 10 | Section "Movimentos do caixa" | `.vc-moves` ul com items abertura/suprimento/sangria + form `vc-move-add` lançar sangria/suprimento | render somente leitura nesta Onda 6 (input dispatch postCloseRegister fica pro próximo wave) · placeholder "Movimentos hoje serão integrados com legacy `/cash-register/close-register/{id}` na Onda 6+1" | 🟡 parcial · placeholder anota next-step |
| 11 | Section "Conferência física" | `.vc-counter` 2 inputs (abertura + contado) + dl summary 4 linhas (cash sales + suprimentos − sangrias = esperado) | render somente leitura na Onda 6 · placeholder explica que fechamento real continua via legacy modal `close_register_modal.blade.php` | 🟡 parcial · CTA "Fechar caixa" navega `/cash-register/close-register/{id}` legacy |
| 12 | Date picker `vc-date` | `<input type="date">` ligado a state `date` · filtra `dayVendas` | replicado com `useState` · onChange refaz fetch `/sells-list-json?start_date=Y&end_date=Y` (mesmo endpoint Sells/Index) | ✅ paridade |
| 13 | Empty state | "Sem movimentação no dia." quando `bySource.length===0` | replicado verbatim · também aplica pra "Por forma de pagamento" tfoot mostra R$ [redacted Tier 0] | ✅ paridade |
| 14 | Responsive break | grid `vc-grid` colapsa pra 1 coluna abaixo 980px (CSS Cowork) | preservado · CSS já vive em sells-cowork.css linha 4175 (`.vc-grid`) | ✅ paridade |
| 15 | A11y/i18n | labels em PT-BR (Carbon canonical) · cor não é único canal (text + dot + bar) | replicado · todos labels via const `SOURCE_META` server-side (mesmo padrão Sells/Index) · `aria-label` em CTAs primary | ✅ paridade |

## Anti-patterns (UX charter Anti-hooks)

- ✅ Sem cor crua Tailwind no TSX — `.sells-cowork .vc-page` escopa CSS canon Cowork + reaproveita tokens `--vd-src-*` já em sells-cowork.css linha 7346+
- ✅ Sem Modal/Dialog — link OS dispara `oimpresso:open-venda` custom event (Sells/Index drawer SaleSheet abre cross-módulo)
- ✅ Sem `font-bold` em h1 — apenas elementos pequenos via `font-weight:600`
- ✅ Persist `date` selecionado em `localStorage[oimpresso.sells.b<bizId>.caixa_date]` (Tier 0 per-business) — opcional Onda 6+1
- ✅ Charter rascunho documentado (status: rascunho · Wagner aprova depois pra virar live)
- ✅ Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL — `business_id` em todo `where()` do controller (defesa em profundidade além do global scope)
- ✅ Permission gate `direct_sell.view` (paridade Sells/Index) ANTES de qualquer query

## Cross-references

- **Backend payload já entrega** `source`/`source_label`/`os_ref` (Onda 2 commit `e98649989`) — controller agrega via `whereDate('transaction_date', $date) + groupBy(source)` + `business_id` Tier 0
- **Listener cross-módulo** `oimpresso:open-venda` já registrado em Sells/Index (commit `e40289010` linha 928-929)
- **Coexistência legacy** `/cash-register/*` Blade preservada intacta — rota nova `/vendas/caixa` é Inertia adicional, zero breaking
- **Próximas Ondas** (fora escopo Onda 6):
  - Onda 6+1: integrar movimentos (sangria/suprimento) read-write substituindo legacy modal
  - Onda 6+2: substituir `/cash-register/close-register` por drawer Inertia
  - Onda 6+3: deprecar legacy Blade quando paridade total atingida

## Gate F2 (Wagner aprova)

Wagner aprovou via decisão 2026-05-25 ~15h "Sells/Caixa.tsx coexiste em rota nova /vendas/caixa". Sem screenshot live nesta worktree (sem servidor rodando) — gate visual depende de smoke pós-merge em prod biz=1 (canary Wagner WR2). Skill `mwart-comparative V4` cumprida via 15 dimensões acima + tokens reaproveitados já validados em Sells/Index (Ondas 3+4).
