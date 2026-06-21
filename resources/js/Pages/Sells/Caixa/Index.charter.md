---
page: /vendas/caixa
component: resources/js/Pages/Sells/Caixa/Index.tsx
owner: wagner
status: draft
status_detail: rascunho
last_validated: "2026-05-25"
parent_module: Sells
related_adrs: [93, 94, 104, 107, 114, 143, 178, 192]
tier: B
charter_version: 1
visual_source: prototipo-ui/vendas-extras.jsx · função VendasCaixaPage (linhas 123-354)
canon_method: Cowork KB-9.75 + Integração Vendas × Oficina A1 (ADR 0192) — Onda 6 wave separada
---

# Page Charter — /vendas/caixa (v1 · Caixa do dia · Onda 6 coexiste)

> **Status:** rascunho · Wagner aprova depois pra virar `live`. Tela nova `/vendas/caixa` Inertia **coexiste** com `/cash-register/*` Blade legacy preservado (decisão Wagner 2026-05-25 ~15h — pattern Cliente Wave A-G drawer 760, rollback trivial). Entrega Onda 6 do método KB-9.75 A1 Integração Vendas × Oficina (ADR 0192).
>
> **Histórico:** v1 (este — Onda 6 Cowork canon · 2026-05-25).
>
> **Visual source:** Cowork `prototipo-ui/vendas-extras.jsx` `VendasCaixaPage` (linhas 123-354). Visual comparison: [`Caixa-r1-visual-comparison.md`](../../../../memory/requisitos/Sells/Caixa-r1-visual-comparison.md).

---

## Mission

Caixa do dia — resumo financeiro por forma de pagamento + **por origem** (Balcão / Oficina / Online · A1 KB-9.75) + ações de fechamento. Substitui leitura visual do modal legacy `cash_register.register_details.blade.php` com pattern Cowork canonical, preservando ações de fechamento real via legacy `/cash-register/close-register/{id}` (Onda 6+1 substitui).

---

## Goals — Features (faz · v1 Cowork canon)

- AppShellV2 sidebar dark (260px) + `.sells-cowork` wrapper escopa CSS verbatim do prototype + `.vc-page` Cowork canon (já em sells-cowork.css linha 4171+)
- Header com h1 "Caixa do dia" + subtitle "Conferência por forma de pagamento, sangrias e fechamento" + date picker (`vc-date`) + ghost "Imprimir Z" + primary "Fechar caixa" → navega `/cash-register/close-register/{id}` legacy
- 4 KPI hero cards (`os-kpis` Cowork canon):
  - Faturado no dia (BRL + count vendas)
  - Esperado em caixa (BRL · dinheiro + sangrias)
  - Conferido (BRL · contagem física)
  - Diferença (color verde/vermelha/amarela · `os-kpi-alert` quando |diff| > 0.01 · "ok/falta/sobra")
- Grid 4 cards `vc-grid` (2 colunas · card "Movimentos" full-row):
  1. **Por forma de pagamento** — table 4-col (Forma + icon + label · Compensação · Vendas · Total) + tfoot total bruto
  2. **Por origem** — Section `vc-card vc-card-source` (canon Cowork A1 KB-9.75):
     - Loop em `bySource` (filtrado `count > 0`): cada linha mostra dot color + label PT-BR + count vendas + soma final_total
     - Barra de progresso (`vc-src-bar`) com width = pct do faturamento do dia
     - Meta linha mostra "% do faturamento do dia"
     - Quando `g.id === 'oficina'` E há items com `os_ref`: até 3 links `↗ #OS-NNNN` clicáveis disparam `window.dispatchEvent(new CustomEvent('oimpresso:open-venda', { detail: { venda_id: v.id } }))` → Sells/Index listener abre drawer SaleSheet cross-módulo (Onda 4 commit `e40289010`)
  3. Movimentos do caixa — render somente leitura (placeholder Onda 6+1: integrar sangria/suprimento read-write)
  4. Conferência física — render somente leitura (placeholder Onda 6+1: substituir legacy modal)
- Backend endpoint REST canon: `GET /vendas/caixa` (Inertia render) + props pré-agregadas pelo controller (`SellController@inertiaCaixa` ou novo método)
- Multi-tenant Tier 0 ADR 0093 IRREVOGÁVEL: `business_id` em todo `where()` (global scope + explícito defesa em profundidade)
- Permission gate: `direct_sell.view` (paridade Sells/Index · UltimatePOS canonical)
- Date picker default = hoje (timezone biz session) · onChange refaz fetch

---

## Non-Goals — não faz nesta v1 (Onda 6)

- ❌ Edição inline de movimentos (sangria/suprimento) — preserva legacy modal `/cash-register/close-register/{id}` (Onda 6+1 substitui)
- ❌ Histórico de caixas fechados — vai pra `/cash-register/close-register/{id}` legacy ou `/financeiro/caixa` (ADR 0183 PR D já entrega)
- ❌ Fechamento de caixa propriamente dito — CTA "Fechar caixa" navega legacy
- ❌ Impressão Z — placeholder ghost (Onda 6+2)
- ❌ Substituir `/cash-register/*` Blade legacy — coexiste durante esta wave (rollback trivial)

---

## UX targets

- p95 < 1500ms primeiro render (TTI Inertia · backend pre-aggregated props · sem fetch extra no mount)
- Monitor 1280px sem scroll horizontal (`.vc-grid` 2-col + KPIs 4-col)
- Responsive 980px (CSS Cowork colapsa grid pra 1 coluna · já existe sells-cowork.css linha 4175)
- A11y: cor não é único canal (text + dot + bar) · `aria-label` em CTAs primary · keyboard nav default
- i18n: labels PT-BR via `SOURCE_META` server-side (mesmo padrão Sells/Index Ondas 3+4)

---

## Anti-hooks (proibido)

- ❌ Cor crua Tailwind dentro do TSX — usar `.sells-cowork .vc-page` + tokens `--vd-src-*` já escopados em sells-cowork.css (linha 7346+)
- ❌ Modal/Dialog dentro da Page — link OS dispara CustomEvent (loose coupling cross-módulo)
- ❌ Query SQL no frontend — `bySource`/`byPayment` vem agregado do controller (defesa Tier 0)
- ❌ Bypass global scope `business_id` — todo `where()` repete explicitamente (defesa em profundidade Tier 0)
- ❌ `direct_sell.create` necessário — só `direct_sell.view` (Caixa é leitura · fechamento real vai pro legacy gate `close_cash_register`)
- ❌ Mudar `/cash-register/*` legacy — coexistência preservada (rollback)

---

## Cross-references

- **ADR 0192** — Integração Vendas × Oficina A1 KB-9.75 (auto-faturar OS → Venda via JobSheetObserver · payload `source`/`source_label`/`os_ref` na linha)
- **ADR 0093** — Multi-tenant Tier 0 IRREVOGÁVEL (`business_id` global scope)
- **ADR 0094** — Constituição v2 (Charter > Spec · este charter v1 atende princípio 3)
- **ADR 0104** — MWART processo único (5 fases · esta tela passa F0-F4)
- **ADR 0107** — Visual comparison gate F3 (cumprido via `Caixa-r1-visual-comparison.md` 15 dimensões)
- **ADR 0114** — Cowork loop formalizado (visual_source canonical)
- **ADR 0178** — Sells unified tabs (paridade arquitetura · Sells/Caixa irmã de Sells/Index)
- **Backend payload** `/sells-list-json` (commit `e98649989`) já retorna `source`/`source_label`/`os_ref` desde Onda 2
- **Listener** `oimpresso:open-venda` já registrado em Sells/Index Onda 4 (`e40289010` linha 928)
- **Decisão coexistência** Wagner 2026-05-25 ~15h: Sells/Caixa coexiste em `/vendas/caixa` · legacy `/cash-register/*` preservado pra rollback
