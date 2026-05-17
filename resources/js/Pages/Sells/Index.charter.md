---
page: /sells
component: resources/js/Pages/Sells/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-17
parent_module: Sells
related_adrs: [0110, 0107, 0109, 0104, 0093, 0114, 0143]
tier: A
charter_version: 2
visual_source: prototipo-ui/prototipos/sells-index/vendas-page.jsx
canon_method: Cowork KB-9.75 (chat10, score 9.75/10)
---

# Page Charter — /sells (v2 · Cowork rewrite)

> **Status:** live · rewrite Cowork em curso 2026-05-17 (sessão `stupefied-noether-89f83d`). Charter v1 (Cockpit V2 puro) preservado historicamente em [`Index.tsx.bak.cowork-rewrite-2026-05-17`](Index.tsx.bak.cowork-rewrite-2026-05-17).
>
> **Origem v2:** prototype Claude Design `Kf6GHQu6fkwlh0vnL30Oog` (handoff 2026-05-16). Visual-comparison: [`memory/requisitos/Sells/index-r1-visual-comparison.md`](../../../memory/requisitos/Sells/index-r1-visual-comparison.md). Decisão de cópia integral: [`memory/reference/feedback-design-literal-copy-quando-aprovado.md`](../../../memory/reference/feedback-design-literal-copy-quando-aprovado.md).

---

## Mission

Listar vendas com filtros por status de pagamento e abrir detalhes em drawer lateral — substitui Blade legacy AdminLTE roxo (`sell.index.blade.php`) preservando DataTables AJAX como fallback condicional.

---

## Goals — Features (faz · v2 Cowork)

- AppShellV2 sidebar dark (260px) + `.sells-cowork` wrapper escopa CSS verbatim do prototype
- Header com h1 "Vendas" + subtitle "Pedidos · faturamento · NF-e/NFS-e" + ⌘K busca + "Nova venda" CTA com kbd N
- Toolbar 2: Foco segmented control (Caixa/Faturamento/Comissão) + Saved views tree dropdown (Pendentes pgto. / Pendentes / Atrasadas / Rejeitadas / Faturadas) + Imprimir caixa + Visões ▾ dropdown
- 4 KPI cards: Faturado hoje dark green hero com sparkline · Ticket médio · A receber + breakdown SLA (estourado/atrasando/fresco) + ageing bar 0-30/31-60/+60d · 4º vista-dependente (Pagos hoje / Notas fiscais / Top vendedor)
- 5 status pills com counts: Todas / Paga / Pendente / Faturada / Cancelada
- Tabela 10 cols: checkbox + Venda (#) + Data + Cliente (nome + items_summary) + Atendido por (avatar + nome) + Pipeline dots (FSM stepper + label curta) + Fiscal badges (NF-e + status SEFAZ) + Pagamento (método + parcelas + SLA pill compacta) + Total + Status
- Pipeline dots derivam de `sale_process_stages.sort_order` + `sale_processes` (FSM ADR 0143 live biz=1)
- SLA pill 4 estados: paga · fresco · atrasando · estourado (computado backend `sla_kind`)
- Linha em foco (J/K) ganha `row-focused` border-left accent + scrollIntoView
- Linha selecionada `.os-row.selected` (CSS scoped)
- Hover-reveal row actions (DANFE PDF / XML / Imprimir recibo) quando fiscal_status=autorizada
- Favoritas ★ via atalho `B` + persiste em `localStorage[oimpresso.sells.favs]`
- Bulk action bar fixed-bottom quando há seleção: Emitir NF-e lote / Marcar pagas / Exportar / Limpar
- Cheat-sheet overlay (?) + ⌘K palette (busca venda/cliente/chave SEFAZ + ações + prefixos #/@/$/`/`)
- Drawer SaleSheet preservado (sem mudança)
- Endpoint REST canon: `GET /sells-list-json` (50 por page, agora retorna +14 campos derivados)
- Multi-tenant Tier 0: `business_id` global scope + permission gate (`direct_sell.view` + variants)
- Paginator compacta no rodapé (preserva contrato existente)

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição inline (vai pra `/sells/{id}/edit` Blade legacy)
- ❌ Print direto (rota Blade `/sells/{id}/print`)
- ❌ Filtros avançados de data range / location / customer / source — Visões dropdown atende parcialmente; refator futuro
- ❌ Date field dropdown (7 opções) e Group-by — features Cockpit V2 não migradas (eram do legacy Index.tsx pré-Cowork; reavaliar valor)
- ❌ ViewMode toggle `lista | grade-avancada` — Cowork tem visual unificado; Grade Avançada legacy permanece no _components/ mas não está montada
- ❌ Real-time updates (WebSocket/Centrifugo) — backlog
- ❌ Migrar `index()` Blade view por completo — fallback `request()->ajax()` mantido
- ❌ R2 IA painel drawer, R3 comentários inline, R4 transcript PDF + apresentação fullscreen — refinos opcionais KB-9.75 (não no escopo desta cópia)

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + 50 linhas)
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC)
- Cabe em monitor 1280px sem scroll horizontal (cliente ROTA LIVRE)
- Drawer abre em < 300ms após click (fetch JSON + render)
- Tipografia canon ADR 0110: h1 22-24px, pill 12px, badge 11px
- Cores semânticas Cockpit V2: rose/emerald/amber/blue (NÃO cor crua)

---

## UX Anti-patterns

- ❌ Cor crua Tailwind dentro do TSX (canon = classes `.vd-*`/`.os-*` escopadas em `.sells-cowork`)
- ❌ Modal/Dialog pra detalhe de linha (canon = drawer SaleSheet lateral via `<Sheet>` shadcn)
- ❌ Adaptar peça-a-peça desfazendo coesão visual do prototype (ver [`feedback-design-literal-copy-quando-aprovado.md`](../../../memory/reference/feedback-design-literal-copy-quando-aprovado.md))
- ❌ `font-bold` em h1 (canon = `font-semibold`)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.sells.`)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/sells-list-json?payment_status=&per_page=50&page=N&sort=&dir=` | 8 fields legacy + 14 fields Cowork derivados (sla_kind, days_to_due, pipeline_step, pipeline_total, pipeline_label, pipeline_color, seller_id, seller_name, seller_abbr, seller_origin, items_summary, items_count, payment_method_label, installments) |
| GET | `/sells/{id}/sheet-data` | Drawer detail JSON (lines + payments + customer + urls) — sem mudança |
| GET | `/sells` (X-Inertia) | Inertia render Sells/Index |
| GET | `/sells` (X-Requested-With ajax) | DataTables legacy (preservado pra `?ajax=1`) |

---

## Tests anti-regressão

- [tests/Feature/Sells/SellsIndexCoworkPayloadTest.php](../../../tests/Feature/Sells/SellsIndexCoworkPayloadTest.php) — 11 testes estruturais novos (backend payload Cowork + tenancy preservada)
- [tests/Feature/Sells/SellsIndexPageTest.php](../../../tests/Feature/Sells/SellsIndexPageTest.php) — 13 testes legacy ainda passam + 9 marcados `markTestSkipped()` com razão canon (atoms Tailwind shadcn substituídos por classes `.vd-*` / `.os-*`)
- [tests/Feature/Sells/SaleSheetComponentTest.php](../../../tests/Feature/Sells/SaleSheetComponentTest.php) — preservados (drawer não mudou)
- [tests/Feature/Sells/SellControllerEndpointsTest.php](../../../tests/Feature/Sells/SellControllerEndpointsTest.php) — 25 testes passam (multi-tenant + endpoints + total_paid subquery)
- Pest browser keyboard J/K/?/B/Enter — backlog (depende de Pest browser estável)

---

## Refs

- [Design.md §16 Cockpit V2](../../../../Design.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- PR #261 (commit `cfa7930a` + hotfix `0b5a09d5`) — implementação inicial
