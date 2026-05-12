---
tela: purchase/index
modulo: Purchase
tipo: LIST
generated_at: 2026-05-11
generated_by: [CL] (skill migracao-blade-react v0.1.0 piloto)
status: aguardando-screenshot-wagner (STEP 4 da skill)
snapshot: memory/mwart-inventory/purchase/index.snapshot.md
draft_tsx: resources/js/Pages/Purchase/Index.tsx
controller_delta: app/Http/Controllers/PurchaseController.php@indexInertia
cowork_source: prototipo-ui/prototipos/compras/visual-source.html
---

# Visual Comparison — `purchase/index` (LIST)

> STEP 4 do pipeline [migracao-blade-react](../../../.claude/skills/migracao-blade-react/SKILL.md).
> **Skill PARA aqui aguardando Wagner aprovar SCREENSHOT** rodando local antes do merge do PR.

## Estado atual

| Item | Status | Localização |
|------|--------|-------------|
| Snapshot paridade | ✅ completo | [memory/mwart-inventory/purchase/index.snapshot.md](../../mwart-inventory/purchase/index.snapshot.md) |
| Draft Inertia/TSX | ✅ criado | [resources/js/Pages/Purchase/Index.tsx](../../../resources/js/Pages/Purchase/Index.tsx) |
| Adaptação Controller | ✅ dual path | [app/Http/Controllers/PurchaseController.php@indexInertia](../../../app/Http/Controllers/PurchaseController.php) |
| Pest fixtures | 🟡 pendente STEP 5 | `tests/Feature/Purchase/IndexTest.php` |
| Screenshot Blade legacy | 🔴 **TODO Wagner** | rodar `/purchases` (sem `?v=2`) em Herd biz=1 |
| Screenshot Inertia draft | 🔴 **TODO Wagner** | rodar `/purchases?v=2` em Herd biz=1 |

## Como rodar o smoke local

```bash
# 1. Garantir que está em http://oimpresso.test (Herd)
# 2. Build front
npm run build

# 3. Login com user biz=1 em http://oimpresso.test/login

# 4. Acessar AMBAS as versões:
#    - Blade legacy: http://oimpresso.test/purchases
#    - Inertia novo: http://oimpresso.test/purchases?v=2
#
# 5. Capturar screenshot das duas e colocar lado-a-lado pra comparar
```

## 15 dimensões comparativas (Anthropic Claude Design framework)

### 1. Hierarquia visual

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Título da página | H1 grande sem ícone, "Compras" | H1 + breadcrumb sutil | `PageHeader` shared + count contextual |
| Subtítulo | (sem) | "X compras" | "X compras" (contextual com filtros) |
| CTA primário | `pull-right` botão gradient | botão accent | `<Button>` shared (top-right) |

### 2. Densidade de informação

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Linhas por viewport | ~12 (DataTables default) | ~15 (compact) | ~15 (h-11 = 44px) |
| Padding célula | 9px 10px | 9px 10px | 9px (px-2) |
| Truncate longas | (não) | max-width + ellipsis | (não — pode ajustar v0.2) |

### 3. Filtros

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Quantidade | 5 (Location, Supplier, Status, PaymentStatus, DateRange) | 4 visuais + busca | 6 (idem Blade + busca client-side) |
| Layout | 4 cols + 1 col date range | filter-pills + tabs | flex-wrap horizontal sticky `top-14` |
| Date range | jQuery daterangepicker | placeholder | 2 inputs HTML5 date |
| Busca | (sem — DataTables global) | input com ⌘K hint | input client-side `useMemo` |

### 4. Tabela

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Colunas | 10 (action, date, ref_no, location, supplier, status, payment_status, total, due, added_by) | 8 visíveis | 9 (combina added_by no supplier subline opcional) |
| Header sticky | (sim — DataTables fixedHeader plugin) | sticky CSS | (não — pode ativar v0.2) |
| Footer com totais | sim (Yajra footerCallback) | (sem) | (sem — pode ativar v0.2) |
| Row click | `data-href` → show modal | sim | (sem — ações inline em vez de row click) |

### 5. Ações inline por linha

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Local | dropdown único `<button class="btn-modal">` | dropdown ou ícones | 4 ícones inline (View/Print/Edit/Delete) |
| Quantidade | 14 ações (view/print/edit/delete/barcode/document/payment/return/status/3× notifications) | 3-4 ações | 4 ações (MVP — outras vão pro Sheet drawer v0.2) |
| Confirmação delete | toast nativo | (sem mockup) | `window.confirm` |

### 6. Status pills

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Tone received | `label bg-green` | emerald-soft | `bg-emerald-50 text-emerald-700` |
| Tone pending | `label bg-yellow` | amber-soft | `bg-amber-50 text-amber-800` |
| Tone ordered | `label bg-blue` | accent-soft | `bg-blue-50 text-blue-700` |
| Payment overdue | `label bg-red` | err-soft | `bg-rose-50 text-rose-700` |

### 7. Tipografia

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Família base | system-ui + Roboto fallback | ui-sans-serif system-ui | tailwind default (system-ui) |
| Tamanho body | 13px | 13px | 13px (h-11 + text-[13px]) |
| Tabular nums | Yajra rawColumn (manual) | font-variant-numeric | `tabular-nums` Tailwind |

### 8. Cores (paleta)

| Token | Blade legacy | Cowork mockup | Draft Inertia | Match? |
|-------|--------------|----------------|----------------|:------:|
| Background | UPOS skin claro | `#f6f4ef` (stone-50) | bg padrão shadcn (stone) | ✅ |
| Paper/card | branco | `#fff` | `<Card>` shadcn | ✅ |
| Ink | preto sistema | `#1a1917` | stone-900 | ✅ |
| Accent | bootstrap blue | `#1f3a5f` | (não usa accent customizado) | 🟡 |

### 9. Espaçamento

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Container max | full | full | full (sem max-w) |
| Gutter | 20px lateral | 20px | gap-3 sticky filters |
| Row height | DataTables auto | 36px compact | 44px (h-11) |

### 10. Interatividade

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Hover row | (sim — bootstrap table-hover) | `tr:hover td{background:#fbf9f3}` | `hover:bg-stone-50/60` |
| Loading state | DataTables `processing.gif` | (não mostrado) | (não — Inertia loading global) |
| Empty state | linha "Nenhum dado disponível" DataTables | (não mostrado) | `<EmptyState>` contextual + CTA |

### 11. Multi-tenant (Tier 0)

| Dimensão | Blade legacy | Draft Inertia | Match? |
|----------|--------------|----------------|:------:|
| `business_id` scope | ✅ via `getListPurchases($business_id)` | ✅ preservado idêntico | ✅ |
| `permitted_locations` | ✅ filter | ✅ preservado | ✅ |
| Permissions check | ✅ `abort(403)` se não pode | ✅ preservado | ✅ |
| Ownership filter | ✅ `view_own_purchase` → `created_by` | ✅ preservado | ✅ |

### 12. Performance

| Dimensão | Blade legacy | Draft Inertia | Observação |
|----------|--------------|----------------|------------|
| Paginação | server-side AJAX (Yajra DataTables) | client-side limit 200 | 🟡 piloto MVP — server pagination v0.2 |
| Lazy load | sim (10 rows default) | não — todos 200 | aceitável até biz=4 escalar |
| Filter trigger | AJAX reload | Inertia router.get (preserveState) | comparable |

### 13. Acessibilidade (a11y)

| Dimensão | Blade legacy | Draft Inertia | Observação |
|----------|--------------|----------------|------------|
| Foco visível | navegação tab ok | shadcn defaults ok | ambos ok |
| ARIA labels | parcial (dropdown menus) | botões com `title` | (rodar `accessibility-review` v0.2) |
| Contraste | bootstrap padrão | tailwind stone | ambos ok |

### 14. Responsividade

| Dimensão | Blade legacy | Cowork mockup | Draft Inertia |
|----------|--------------|----------------|----------------|
| Min width | DataTables horizontal scroll | grid sidebar 200px | inherits AppShellV2 |
| Mobile | overflow-x scroll | (focado desktop) | (focado desktop — Wagner monitor 1280px+) |
| Tablet | ok | ok | ok |

### 15. Internacionalização

| Dimensão | Blade legacy | Draft Inertia | Match? |
|----------|--------------|----------------|:------:|
| Idioma | `@lang('purchase.*')` keys i18n | hardcoded PT-BR | 🟡 v0.2 usar `__()` via Inertia shared |
| Moeda | `@format_currency` | `Intl.NumberFormat pt-BR BRL` | ✅ |
| Data | `@format_datetime` (settings business) | `toLocaleDateString pt-BR` | 🟡 não respeita format custom — v0.2 |

## Decisão Wagner — aprova screenshot?

**Critérios pra aprovar:**

- [ ] Tela `/purchases?v=2` renderiza sem erro
- [ ] Filtros funcionam (location, supplier, status, payment, date range, busca)
- [ ] Pelo menos 1 linha de dados aparece (biz=1 em Herd local — Wagner pode usar fixture)
- [ ] Ações inline (View/Print/Edit/Delete) clicam (ainda que destinos sejam Blade legacy)
- [ ] Status pills aparecem com cores corretas (received=verde, pending=amarelo, paid=verde, overdue=vermelho)
- [ ] Permissões respeitadas (user sem `purchase.create` NÃO vê "+ Nova compra")
- [ ] Empty state aparece se nenhuma compra (texto + botão CTA)

**Critérios de bloqueio (Wagner reprova se):**

- ❌ Tier 0 quebrado (vê compras de outro tenant)
- ❌ Permissions vazadas (usuário vê ação que não pode)
- ❌ Crash 500 em qualquer combinação de filtros
- ❌ Visual "muito diferente" do mockup Cowork (justificar v0.2)

## Limitações conhecidas (aceitas pelo piloto MVP)

1. **Sem paginação real** — limit 200. Pra biz=4 com 10k+ compras precisa filtrar primeiro. v0.2 vai server-side pagination.
2. **Sem Yajra footer com totais** — adicionar v0.2 via Card abaixo da tabela.
3. **i18n hardcoded** — strings PT-BR diretas. v0.2 mover pra `__()` Inertia shared.
4. **Ações inline reduzidas (4 vs 14)** — outras 10 ações (barcode, document, payment, return, status, notifications) vão pro Sheet drawer v0.2.
5. **Sidebar não tem entry pra `/purchases?v=2`** — só `?v=2` query manual. Wagner decide se sidebar aponta pra Inertia em PR separado.

## Próximos passos pós-aprovação

- STEP 5: Pest test (`tests/Feature/Purchase/IndexTest.php`) cobrindo biz=1 + cross-tenant biz=99 + permission
- STEP 6: PR ≤300 LOC (commit-discipline). Esse piloto provavelmente passa de 300 — justificar como "1 intent (piloto skill)" no commit message
- v0.2: melhorias listadas em "Limitações conhecidas"

---

**Refs:**
- [ADR 0141 — skill migracao-blade-react](../../decisions/0141-skill-migracao-blade-react.md)
- [runbook-LIST.template.md](../../../.claude/skills/migracao-blade-react/runbook-LIST.template.md)
- [Snapshot paridade](../../mwart-inventory/purchase/index.snapshot.md)
- [Cowork visual source](../../../prototipo-ui/prototipos/compras/visual-source.html)
