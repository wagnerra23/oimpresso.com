---
page: /sells
page_id: sells-index
component: resources/js/Pages/Sells/Index.tsx
bundle_source: vendas-page.jsx
owner: wagner
status: live
last_validated: "2026-07-01"
charter_version: 7
parent_module: Sells
related_us: [US-SELL-COWORK-R5-POLISH]
states: [default, empty, loading, dark]  # gate L2 — error removido: toast sonner não dá estado visível determinístico no VRT (md5 #3290) · sync com tests/Browser/visreg-states.json
tier: A
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0094-constituicao-v2-7-camadas-8-principios
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0143-fsm-pipeline-live-prod-marco-2026-05-12
  - 0180-sidebar-v3-5-grupos-ghosts-header
  - 0182-pageheadertabs-canon-pattern-telas
  - 0189-pageheader-canon-v3-1-cadastro-roxo
  - 0190-primary-button-roxo-universal-295
  - 0192-auto-faturar-os-venda-jobsheet-observer
visual_source: prototipo-ui/vendas-page.jsx + KB-9.75 batch Cowork (2026-05-25 → 2026-05-26)
canon_method: Cowork KB-9.75 + Unificação tabs Visão (ADR 0178) + Integração Vendas × Oficina (ADR 0192) + Emit modais + BulkActionBar + saved view Aguardando faturamento (PRs #1641 / #1644 / #1648 / #1649)
---

# Page Charter — /sells (v6 · Tier A · MWART canon completo)

> **Status:** `live` · v5 → v6 backfill 2026-05-26 acompanha PR #1649 (RUNBOOK-index.md) — consolida toda a onda KB-9.75 P0/P1: VdNextActionPanel emoji canon Cowork (PR #1641) · validações fiscais BR (PR #1641) · Emit modais NFe/NFCe/NFSe single + bulk (PR #1644) · BulkActionBar wire-up + z-index fix (PR #1648) · saved view "Aguardando faturamento" + tree "Por origem ▾" (Onda 4 ADR 0192) · WhatsApp 3-tab message preview (PR #1638).
>
> **Histórico:** v2 (Cowork rewrite 2026-05-17) → v3 (Grade Avançada toggle ADR 0136) → v4 (Unificação tabs Visão ADR 0178) → v5 (Integração Vendas × Oficina ADR 0192) → **v6 (este — backfill MWART Tier A completo · review_trigger 2026-06-15)**.
>
> **RUNBOOK pareado:** [`memory/requisitos/Sells/RUNBOOK-index.md`](../../../../memory/requisitos/Sells/RUNBOOK-index.md) — 15 seções operacionais (golden path, smoke prod, troubleshooting, receitas alternativas). Charter aqui é resumo executivo + contrato MWART; detalhe operacional vive no RUNBOOK.

---

## Mission

Tela cockpit central de operação comercial — lista vendas (pedidos · faturamento · NF-e/NFS-e) do business com 4 KPIs operacionais (Faturado hoje / Ticket médio / A receber / 4º vista-dependente) + pipeline FSM dots por linha + saved views + bulk emit fiscal. Substitui Blade legacy `sell.index.blade.php` preservando Cockpit Pattern V2 + PageHeader v3 (ADR 0180/0182/0189), Primary roxo 295 universal (ADR 0190) e Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL). Tela mais complexa do projeto (1698 LOC) — porta de entrada diária pra Wagner/Larissa/balconista, integra Modules/NfeBrasil, Modules/Whatsapp, Modules/Repair, Modules/PaymentGateway, Modules/Copiloto.

---

## Goals

- **PageHeader v3** (ADR 0180/0182/0189) com h1 "Vendas" + sub "Pedidos · faturamento · NF-e/NFS-e" + SubNav 4 abas FOCO / Caixa / Faturamento / Comissão + botão primary "Nova venda" (roxo 295 ADR 0190 universal)
- **4 KPI cards via `Inertia::defer`** (skill `inertia-defer-default`): Faturado hoje (mini-spark) · Ticket médio (delta semana) · A receber (faixas 0-30d/31-60d/+60d + ageing bar) · 4º slot vista-dependente (Pagos hoje / Notas fiscais / Top vendedor)
- **Toolbar linha 2:** tabs Visão (Todas/Paga/Pendente/Faturada/Cancelada) + segmented control Operacional/Financeira/Produção + busca + Filtros avançados + Imprimir caixa + Visões ▾ dropdown
- **Tabela 10 colunas exibe 50 vendas:** checkbox + Venda(#) + Data + Cliente + Atendido por + Origem (Balcão/Oficina/Online — ADR 0192) + Pipeline dots (FSM ADR 0143) + Fiscal badges (NF-e SEFAZ) + Pagamento (método + parcelas + SLA pill) + Total + Status
- **Menu "Ações" por linha (kebab ⋮ · `ActionsMenu` em `SellsTabelaUnificada`):** Ver detalhes (abre drawer) · Editar · Adicionar pagamento (só se `payment_status !== 'paid'`) · Imprimir nota · **Devolução** (`/sell-return/add/{id}` — venda retorno → retorno da peça pro estoque, rota Blade) · Excluir (`variant=destructive`, DELETE `/sells/{id}` com CSRF + confirm). Paridade com o menu Blade legado; ações enforçadas no backend (403/permissão). **Restaurado do commit `d6f4dddcdc`, perdido no rewrite Cowork #1032** (PR #3494)
- **Drawer SaleSheet 480px lateral** ao clicar linha — KV grid + Cliente + Produtos(N) + Pagamentos(M) + MENSAGEM WHATSAPP 3-tab (Confirmação/Retirada/Cobrança PR #1638) + FISCAL section + PIPELINE FSM (VdNextActionPanel emojis canon Cowork PR #1641) + ORDEM DE SERVIÇO cross-module + HISTÓRICO append-only + Footer ações + botão "+IA" Copiloto
- **Emit modais single (PR #1644):** VdNfeEmitModal + VdNfseEmitModal abrem do drawer FISCAL section
- **VdBulkEmitModal fullscreen (PR #1644 + #1648 z-index 100):** BulkActionBar floating ao selecionar múltiplas linhas → "Emitir NF-e em lote" abre modal com progress tricolor pending → running → ok|bad
- **Saved view "Aguardando faturamento" (PR #1644):** filtra cliente-side `payment_status !== 'paid' && fiscal_status === null` — receita batch noturno faturamento
- **Saved tree "Por origem ▾" (Onda 4 ADR 0192):** expansível com filhos Balcão/Oficina/Online + contadores derivados + persiste em `localStorage[oimpresso.sells.b<bizId>.visao_origem]` (Tier 0 per-business)
- **Cross-module "Criar OS"** idempotente do drawer (1 venda → N OS · Modules/Repair · ADR 0192 supersede)
- **WhatsApp preview ao vivo** com variáveis dinâmicas (cliente · id · total · forma · saller · prazo · vencimento · status · data)
- **KB-9.75 Slice A atalhos (preservar):** `⌘K` palette · `?` cheat-sheet · `J/K` nav linha · Enter abre drawer · `/` foco busca · `B` favoritar (preserva PR #1309)
- **Listener cross-módulo** `window.addEventListener('oimpresso:open-venda', e => setOpenSaleId(e.detail.venda_id))` — Repair drawer dispara, Sells/Index abre drawer da venda derivada
- **Validações fiscais BR (PR #1641):** `validacoesFiscaisBr.ts` bloqueia emit se cliente sem CNPJ/CPF/idEstrangeiro — erro inline em FISCAL section

---

## Non-Goals

- ❌ **NÃO criar OS sem cliente cadastrado** — Contact.id obrigatório (Tier 0 + LGPD)
- ❌ **NÃO bypass validações fiscais BR** — cliente sem CNPJ/CPF/idEstrangeiro não emite (PR #1641 `validacoesFiscaisBr.ts`)
- ❌ **NÃO duplicar emissão SEFAZ** pra mesma venda — constraint `nfe_emissoes_biz_fx_unique`
- ❌ **NÃO marcar "paga" sem pagamento real** registrado (audit-log enforce + FSM lock ADR 0143)
- ❌ **NÃO editar venda finalizada** (status=paid OU faturado) — usar estorno/cancelamento Blade legacy
- ❌ **NÃO commit de cores hardcoded** nos `_components/Vd*.tsx` — override aprovado APENAS pra emojis canon Cowork via PR #1641 comment 4545772140 (`/mwart-override` Wagner)
- ❌ NÃO força-refresh durante bulk emit em execução (cancela operação)
- ❌ NÃO modal sobre modal — "Falar com Copiloto" abre nova rota `/jana/chat?context=sale:{id}`
- ❌ NÃO real-time updates (WebSocket/Centrifugo) — backlog
- ❌ NÃO migrar `index()` Blade view por completo — fallback `request()->ajax()` mantido pra DataTables legacy
- ❌ NÃO `/sells/create` Cowork — `vendas-create-completo.jsx` 683 LOC 3 verticais é Onda 7 candidata
- ❌ NÃO tabs estruturadas no SaleSheet drawer (Itens/Fiscal/Pagamento/Timeline/✦ IA) — gap catalogado pós-screenshot Wagner; refator futuro (Onda 2.7 candidata)

---

## UX Targets

- **p95 first-paint < 800ms** (KPIs Inertia::defer + 50 linhas tabela)
- **p95 KPIs Inertia::defer < 600ms** (4 cards skeleton → render)
- **p95 drawer abre < 400ms** (fetch `/sells/{id}/sheet-data` + render SaleSheet)
- **p95 bulk emit modal abre < 200ms** (já preload no select-all)
- **Mobile 360px** usável (linhas tabela colapsam essenciais — Larissa biz=4 Android low-end)
- **Viewport 1280×1024** sem scroll horizontal (cliente ROTA LIVRE biz=4 + monitor padrão WR2)
- **Tipografia canon ADR 0110:** h1 22-24px font-semibold, pill 12px, badge 11px
- **Cores semânticas Cockpit V2:** rose/emerald/amber/blue via classes `.vd-*`/`.os-*` escopadas em `.sells-cowork` (NÃO cor crua Tailwind)
- **0 erros JS console** em smoke biz=1 (Wagner WR2 SC) e biz=4 (Larissa ROTA LIVRE)

---

## Automation Anti-hooks

- ⛔ **Tier 0 IRREVOGÁVEL (ADR 0093):** `App\Transaction::where('business_id', $business_id)` em TODA query — global scope automático. Cross-tenant retorna 404 (não 403, evita enumeração).
- ⛔ **NÃO auto-emit SEFAZ on-mount** — emissão SEMPRE requer click humano explícito (drawer FISCAL OU BulkActionBar)
- ⛔ **NÃO duplicar emissão** — constraint banco `nfe_emissoes_biz_fx_unique` + idempotência VdBulkEmitModal por `sale_id`
- ⛔ **NÃO log PII completo** — CPF/CNPJ exibido com máscara via `maskTaxNumber($value)` backend; plain text NUNCA chega ao frontend (drawer fiscal renderiza completo, papel cliente protegido fisicamente)
- ⛔ **NÃO dispara emails ao abrir drawer** — Spatie ActivityLog SÓ em mutate (LGPD)
- ⛔ **NÃO chama LLM em filtros/listagem** — Copiloto +IA dispara APENAS via botão explícito drawer
- ⛔ **NÃO enviar "Falar com Copiloto"** sem confirmação humana — abre rota Jana, não dispara mensagem
- ⛔ **NÃO acessar Sale de outro business_id** (ADR 0093 Tier 0 IRREVOGÁVEL); `BulkEmitItem.id` validado por business_id antes de enqueue Job
- ⛔ **NÃO força-refresh durante bulk emit em execução** (cancela operação)
- ⛔ **NÃO `sessionStorage`** — canon = `localStorage` com prefix `oimpresso.sells.b<bizId>.` (Tier 0 per-business)

---

## Sub-components

- `resources/js/Pages/Sells/Index.tsx` — page raiz (1698 LOC)
- `resources/js/Pages/Sells/_components/SaleSheet.tsx` — drawer 480px
- `resources/js/Pages/Sells/_components/SaleMessagePreview.tsx` — preview WhatsApp 3-tab (PR #1638)
- `resources/js/Pages/Sells/_components/SaleTimeline.tsx` — histórico append-only
- `resources/js/Pages/Sells/_components/VdNextActionPanel.tsx` — FSM cockpit emojis canon Cowork (PR #1641, override aprovado #1641-4545772140)
- `resources/js/Pages/Sells/_components/VdBulkEmitModal.tsx` — bulk emit fullscreen (PR #1644 + z-index 100 PR #1648)
- `resources/js/Pages/Sells/_components/VdNfeEmitModal.tsx` — emit NF-e single (PR #1644)
- `resources/js/Pages/Sells/_components/VdNfseEmitModal.tsx` — emit NFS-e single (PR #1644)
- `resources/js/Pages/Sells/_components/FiscalSection.tsx` — FISCAL drawer section
- `resources/js/Pages/Sells/_components/CobrancaDrawer.tsx` — emitir cobrança (Onda 4f.0 PR #1587)
- `resources/js/Pages/Sells/_components/CriarOsButton.tsx` — cross-module OS (Modules/Repair)
- `resources/js/Pages/Sells/_components/SaleAiPanel.tsx` — botão +IA Copiloto contextual
- `resources/js/Pages/Sells/_components/CommissionSplitEditor.tsx` — splitter comissão
- `resources/js/Pages/Sells/_components/QuickPaymentDialog.tsx` + `QuickPaymentPopover.tsx`
- `resources/js/Pages/Sells/_components/SaleItemComments.tsx`
- `resources/js/Pages/Sells/_components/SellsCheatSheet.tsx` — atalhos `?`
- `resources/js/Pages/Sells/_components/SellsTabelaUnificada.tsx` + `SellsTabsVisao.tsx` + `SellsDateFilter.tsx`
- Shared: `PageHeader` (v3 ADR 0180/0182/0189), `Sheet` (shadcn), `KpiCard`

---

## Refs

- **RUNBOOK pareado:** [`memory/requisitos/Sells/RUNBOOK-index.md`](../../../../memory/requisitos/Sells/RUNBOOK-index.md) (PR #1649 · 15 seções operacionais)
- **ADRs canon:**
  - [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
  - [ADR 0094 — Constituição v2 (7 camadas + 8 princípios)](../../../../memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md)
  - [ADR 0104 — Processo MWART canônico (5 fases)](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
  - [ADR 0107 — Visual gate F1.5/F3](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
  - [ADR 0143 — FSM pipeline live prod](../../../../memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
  - [ADR 0180 — Sidebar v3 5 grupos ghosts](../../../../memory/decisions/0180-sidebar-v3-5-grupos-ghosts-header.md)
  - [ADR 0182 — PageHeaderTabs canon pattern](../../../../memory/decisions/0182-pageheadertabs-canon-pattern-telas.md)
  - [ADR 0189 — PageHeader v3.1 cadastro roxo](../../../../memory/decisions/0189-pageheader-canon-v3-1-cadastro-roxo.md)
  - [ADR 0190 — Primary roxo 295 universal](../../../../memory/decisions/0190-primary-button-roxo-universal-295.md)
  - [ADR 0192 — Auto-faturar OS→Venda JobSheetObserver](../../../../memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md)
- **PRs canon da onda KB-9.75 (maio 2026):**
  - #1638 — bundle KB-9.75 WhatsApp 3-tab + saved views base
  - #1641 — VdNextActionPanel + validações fiscais BR + glossário corrigido (override emoji canon Cowork comment 4545772140)
  - #1644 — Emit modais NFe/NFCe/NFSe + Bulk emit + saved view "Aguardando faturamento"
  - #1647 — charters Edit (sister telas Sells)
  - #1648 — fix BulkActionBar wire-up + z-index 100 (modal sobre drawer)
  - #1649 — RUNBOOK-index.md backfill (este charter v6 acompanha)
  - #1650 / #1655 / #1657 / #1659 — refinos pós-merge
- **Visual comparison:** [`Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md`](../../../../memory/requisitos/Sells/Sells-r4-cowork-kb975-2026-05-26-visual-comparison.md) (r4 delta vs main)
- **Sister charter:** [`Pages/Sells/Edit.charter.md`](Edit.charter.md) (PR #1647)
- **Sister RUNBOOKs:** [`RUNBOOK-create.md`](../../../../memory/requisitos/Sells/RUNBOOK-create.md)
- **Dossiê wagner-understand:** `memory/sessions/2026-05-21-understand-sells-unificar-lista-grade.md` (matriz 26 dimensões + 6 PRs atômicos)

---

## Notas

- **Override emoji canon Cowork** (PR #1641 comment `4545772140` `/mwart-override` Wagner) — VdNextActionPanel usa emojis ✓/📄/📦/💰/⊘ verbatim do prototype Cowork. Permitido APENAS neste componente. Demais Vd*.tsx seguem PRE-MERGE-UI (sem cor crua Tailwind).
- **Review trigger 2026-06-15** — re-validação obrigatória pós canary 7d biz=1 + biz=4. Esperado: zero incidentes Tier 0, zero PII leak, 0 erros JS console.
- **Charter Tier A** = MWART Cowork + Cockpit V2 + Multi-tenant Tier 0 + Anti-hooks LGPD. Mudança requer PR + aprovação Wagner (ADR 0094 §Tier-A).

---

## v6 → v7 changelog (append-only)

- v7 (2026-07-01 · este) — restaura o **menu "Ações" por linha** (kebab ⋮ `ActionsMenu` em `SellsTabelaUnificada`): Ver detalhes · Editar · Adicionar pagamento · Imprimir nota · **Devolução** (`/sell-return/add/{id}`) · Excluir. O dropdown existia (commit `d6f4dddcdc`) e foi **removido silenciosamente no rewrite Cowork KB-9.75 #1032** — drift código↔charter que deixou a lista React sem o ponto de entrada da devolução. Companion do **#3488** (fecha 500 da lista `/sell-return` — RouteServiceProvider de módulo poluía o root controller namespace global). PRs: #3488 + #3494. Smoke prod biz=1 e biz=4 OK (menu renderiza · Devolução → 200)
- v6 (2026-05-26) — backfill MWART Tier A completo · pareado com RUNBOOK-index.md (PR #1649) · consolida onda KB-9.75 P0/P1: Emit modais (#1644) + BulkActionBar fix (#1648) + VdNextActionPanel emoji override (#1641) + validações fiscais BR (#1641) + saved view "Aguardando faturamento" (#1644) · frontmatter canon strict (datas aspas, slugs literais, tier letras) · review_trigger 2026-06-15
- v5 (2026-05-25) — Integração Vendas × Oficina (ADR 0192 Ondas 3-4) · coluna Origem + saved tree "Por origem ▾" + KPI hero breakdown + listener cross-módulo (PR #1506)
- v4 (2026-05-21) — Unificação tabs Visão (ADR 0178 supersede ADR 0136) · `viewMode` → `visao` operacional/financeira/produção
- v3 (~2026-05-18) — Grade Avançada toggle (ADR 0136 superseded)
- v2 (2026-05-17) — Cowork rewrite inicial (PR #261 commit cfa7930a + hotfix 0b5a09d5)
