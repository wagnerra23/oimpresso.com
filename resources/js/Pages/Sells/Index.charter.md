---
page: /sells
component: resources/js/Pages/Sells/Index.tsx
owner: wagner
status: live
last_validated: 2026-05-25
parent_module: Sells
related_adrs: [0093, 0094, 0104, 0107, 0109, 0110, 0114, 0136, 0143, 0178, 0192]
tier: A
charter_version: 5
visual_source: prototipo-ui/vendas-page.jsx (Integração Vendas × Oficina · 2026-05-25)
canon_method: Cowork KB-9.75 + Unificação tabs Visão (ADR 0178) + Integração Vendas × Oficina A1 (ADR 0192)
---

# Page Charter — /sells (v5 · Integração Vendas × Oficina)

> **Status:** live · evolução v4→v5 acordada 2026-05-25 — entrega da A1 KB-9.75 (Integração Vendas × Oficina) sobe nota Vendas de 9,0 → 9,3. Backend Ondas 0-2 mergeadas (ADR 0192 + migration `transactions.source/os_ref/commission_split` + `JobSheetObserver@updated` + payload endpoints). Frontend Ondas 3-4 (este charter) adiciona coluna Origem + saved tree "Por origem ▾" + KPI hero breakdown quando Foco=Faturamento + listener cross-módulo `oimpresso:open-venda`.
>
> **Histórico:** v2 (Cowork rewrite 2026-05-17) → v3 (Grade Avançada toggle ADR 0136) → v4 (Unificação tabs Visão ADR 0178 · 2026-05-21) → **v5 (este — Integração Vendas × Oficina ADR 0192 · 2026-05-25)**.
>
> **Origem v2:** prototype Claude Design `Kf6GHQu6fkwlh0vnL30Oog` (handoff 2026-05-16). Visual-comparison v2: [`index-r1-visual-comparison.md`](../../../memory/requisitos/Sells/index-r1-visual-comparison.md). Visual-comparison v4: [`Index-r2-unified-visual-comparison.md`](../../../memory/requisitos/Sells/Index-r2-unified-visual-comparison.md). Visual-comparison v5: [`Index-r3-integracao-vendas-oficina-visual-comparison.md`](../../../memory/requisitos/Sells/Index-r3-integracao-vendas-oficina-visual-comparison.md).

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

### Goals adicionados v5 — Integração Vendas × Oficina (Ondas 3-4 · ADR 0192)

- Coluna **Origem** (entre "Atendido por" e "Pipeline") nos presets `COLUMNS_OPERACIONAL` e `COLUMNS_PRODUCAO` da `SellsTabelaUnificada` — pill colorida `<VdSource>` Balcão (verde 155) / Oficina (azul 230) / Online (âmbar 50) + link `↗ #OS-NNNN` clicável quando `source='oficina'` (navega pra `/repair/producao-oficina?os=OS-NNNN`)
- Stripe sutil border-left azul (`.vd-row-oficina` / `data-source="oficina"`) em linhas vindas da Oficina — assinatura visual cross-módulo
- Saved tree branch **"Por origem ▾"** no dropdown Visões — expansível, filhos Balcão/Oficina/Online com contadores derivados; filtra client-side; persiste seleção em `localStorage[oimpresso.sells.b<bizId>.visao_origem]` (Tier 0 per-business)
- KPI hero **breakdown por source** quando `Foco === 'faturamento'` E há ≥1 venda oficina/online hoje — header ganha tag "· todas origens" + linha `● Balcão R$ X · ● Oficina R$ Y · ● Online R$ Z` (vd-kpi-breakdown grid)
- Listener `window.addEventListener('oimpresso:open-venda', e => setOpenSaleId(e.detail.venda_id))` — Repair drawer card "Esta OS gerou venda #V-NNNN" (Worker B Onda 5) dispara o evento; Sells/Index abre o drawer SaleSheet da venda derivada cross-módulo
- Backend endpoint `/sells-list-json` devolve `source` + `source_label` (PT-BR derivado server-side) + `os_ref` desde Onda 2 (commit `e98649989`)
- Multi-tenant Tier 0 preservado: `source` ENUM scoped por `business_id` (índice composto `idx_transactions_source` na migration Onda 1); `JobSheetObserver` herda `business_id` da OS pra criar Transaction derivada (nunca cross-tenant)

---

## Non-Goals — Features (NÃO faz)

- ❌ Edição inline (vai pra `/sells/{id}/edit` Blade legacy)
- ❌ Print direto (rota Blade `/sells/{id}/print`)
- ❌ Filtros avançados de data range / location / customer / source — Visões dropdown atende parcialmente; refator futuro
- ❌ Date field dropdown (7 opções) e Group-by — features Cockpit V2 não migradas (eram do legacy Index.tsx pré-Cowork; reavaliar valor)
- ❌ Toggle `viewMode = lista | grade-avancada` — **substituído por tabs `visao = operacional | financeira | produção`** ([ADR 0178](../../../../memory/decisions/0178-sells-unified-tabs-visao-supersede-0136.md), supersede ADR 0136). Migração silenciosa localStorage `viewMode` → `visao` no PR4 da Onda Unificação
- ❌ Real-time updates (WebSocket/Centrifugo) — backlog
- ❌ Migrar `index()` Blade view por completo — fallback `request()->ajax()` mantido
- ❌ R3 comentários inline, R4 transcript PDF + apresentação fullscreen — refinos opcionais KB-9.75 (backlog Ondas 3-4)
- ❌ Tabs estruturadas no SaleSheet drawer (Itens/Fiscal/Pagamento/Timeline/✦ IA) — gap catalogado pós-screenshot Wagner; refator futuro (Onda 2.7 candidata)
- ❌ `/sells/create` Cowork (vendas-create-completo.jsx 683 LOC 3 verticais) — Onda 7 candidata

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
- [ADR 0178 — Unificação tabs Visão (supersede 0136)](../../../../memory/decisions/0178-sells-unified-tabs-visao-supersede-0136.md) — **canon ativo da v4**
- [ADR 0136 — Sells split Lista/Grade (superseded)](../../../../memory/decisions/0136-sells-grade-avancada-modo-toggle.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0105 Cliente como sinal qualificado](../../../../memory/decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) — Larissa biz=4 sinal 2026-05-21
- PRs canon da onda de unificação:
  - [#1311 default `'todas'` + Tier 0 per-business localStorage](https://github.com/wagnerra23/oimpresso.com/pull/1311) — merged 2026-05-21
  - [#1314 Pagamento Grade TanStack](https://github.com/wagnerra23/oimpresso.com/pull/1314) — merged 2026-05-21
  - [#1317 Pagamento Grade HTML default](https://github.com/wagnerra23/oimpresso.com/pull/1317) — merged 2026-05-21
  - [#1320 Pagar inline (QuickPaymentDialog)](https://github.com/wagnerra23/oimpresso.com/pull/1320) — merged 2026-05-21
- PR #261 (commit `cfa7930a` + hotfix `0b5a09d5`) — implementação Cowork inicial
- [Dossiê wagner-understand 2026-05-21](../../../../memory/sessions/2026-05-21-understand-sells-unificar-lista-grade.md) — matriz 26 dimensões + 6 PRs atômicos
