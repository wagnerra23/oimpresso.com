---
page: /recurring-billing
component: resources/js/Pages/RecurringBilling/Index.tsx
owner: wagner
status: live
last_validated: "2026-06-07"
parent_module: RecurringBilling
related_adrs: [110, 107, 109, 104, 93, 114, 101, 94, 143]
tier: A
charter_version: 1
visual_source: prototipo-ui/prototipos/recurring/recurring-page.jsx
canon_method: Cowork KB-9.75
sidebar_group: fin (FINANCEIRO)
---

# Page Charter — /recurring-billing (Cobrança Recorrente · v1 Cowork)

> **Status:** live · primeiro PR de UI Inertia (Ondas 3+4+5 do plano [Index-visual-comparison.md](../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md)).
>
> **Origem visual:** prototipo Cowork canônico `recurring-page.jsx` (1.637 linhas IIFE) + `recurring-data.jsx` (18 subs · 5 planos) — REFINO #1 (3-col base) implementado neste PR. Refinos #2/#3/#4 (Modo apresentação · Tour · Troubleshooters · Print · CmdPalette IA) ficam pra PRs futuros.

---

## Mission

Listar assinaturas recorrentes (plano + cliente + próxima cobrança + status pagamento) com filtros por status/vencimento/plano, drawer lateral mostrando detalhe completo (KV grid + bloco fiscal + ações stub + timeline) — substitui Blade legacy "Hello World" preservando rota legacy `/recurringbilling` redirecionada na Onda 10.

---

## Goals — Features (faz · v1 Cowork)

- AppShellV2 sidebar + `.rec-cowork` wrapper escopa CSS verbatim do prototipo
- Header `Cobrança recorrente` + subtitle mono `N ATIVAS · MRR R$ X · CHURN Y%` + 4 tabs (Assinaturas / Planos / Faturas / Configurações) + CTA `+ Nova assinatura`
- 4 KPI cards: MRR (hero dark + sparkline derivada) · Churn mês · Próxima cobrança · Retentado falhos
- 3-col body sub-tab Assinaturas:
  - Coluna 1 filtros (~220px): toggle favoritos + Próxima cobrança (qualquer/hoje/amanhã/semana/30d) + Status (todas/em_dia/retentando/falhou/pausada/cancelada) + Plano + MRR filtrado
  - Coluna 2 lista (flex 1): search `/` + linha com Avatar hueFor + nome + plano + RecStatusBadge + método ícone + valor
  - Coluna 3 drawer (~340px): header + card próxima cobrança destacado + KV grid (Plano/Ciclo/Desde/Pagas/Falhas/LTV/Contato/OS) + nota pinada + FiscalBlock (NFe/NFS-e + canais envio + Reenviar stub) + ações stub (Editar plano / Pausar) + timeline append-only
- Mapeamento status Cowork = derivado:
  - `em_dia` ← DB `status IN (active, trialing)` + zero overdue invoices
  - `retentando` ← DB `status=past_due` + last_charge_attempts < 3
  - `falhou` ← DB `status=past_due` + last_charge_attempts >= 3
  - `pausada` ← DB `status=paused`
  - `cancelada` ← DB `status=canceled`
- Mapeamento ciclo `monthly/quarterly/semiannual/yearly` → label PT `mensal/trimestral/semestral/anual`
- Search server-side: nome cliente, CNPJ, OS (cruzando Subscription.contact_phone_cached + Contact.tax_number + jobsheet legacy soft link)
- `Inertia::defer()` em props caras (subscriptions paginadas + plans + cached KPIs aggregate) — partial reload `only:[subscriptions]` ao mudar filtro
- Multi-tenant Tier 0: `HasBusinessScope` automático em Subscription/Plan/Invoice + Repository scopa explícito + Pest cross-tenant biz=1 vs biz=99
- Permission gate Spatie: `recurringbilling.access` OR `superadmin`
- Sidebar AppShellV2 entry: `Modules/RecurringBilling/Http/Controllers/DataController@modifyAdminMenu` injeta item label `Cobrança Recorrente` order=86 (entre Financeiro=85 e PontoWr2=88), agrupado visualmente em SIDEBAR_GROUPS['fin'] no frontend
- Routes nomeadas: `recurring-billing.index` (GET `/recurring-billing`), legacy `/recurringbilling` intacta (Onda 10 cuta)
- **Onda 21 v9,75 — Nova assinatura ativa:** CTA header + primary sidebar (`?new=1`) + atalho `N` abrem drawer lateral 760px de criação (Sheet DS — busca de cliente debounced via `recurring-billing.contacts.search` Tier 0 + seletor de plano que pré-preenche valor/ciclo + valor/ciclo/data/gateway/forma/descrição) → POST `recurring-billing.store`. Substitui o stub "(em breve)" e conserta o primary do sidebar (que apontava pra `/recurring-billing/create` 404). Form usa componentes DS (Sheet/Input/Select/Textarea/Label/FieldError) — ui:lint R1 + eslint ds/* limpos.

---

## Non-Goals — Features (NÃO faz neste PR)

- ❌ Tabs Planos / Faturas / Configurações funcionais (placeholder "em construção" — Ondas 6/7/8)
- ❌ CmdPalette ⌘K com Jana IA (Onda 9)
- ❌ Modo apresentação fullscreen (REFINO #4)
- ❌ Tour onboarding 4 passos (REFINO #4)
- ❌ Troubleshooters árvore de decisão (REFINO #3)
- ❌ Print extrato `window.print()` styled (REFINO #4)
- ❌ Notes CRUD persistente + Favorites toggle persistente (Onda 9)
- ❌ Reenviar NFe wire real (endpoint existe `POST /nfe/emissoes/{id}/reenviar-email` — Onda 9 conecta)
- ❌ Ações executáveis (Retentar / Pausar / Editar plano / Suspender / Enviar dunning) — botões renderizam, click é no-op com toast "em breve"
- ❌ Atalhos J/K/B/⌘K/P/R/E (esqueleto KeyHandler instalado mas no-op no v1)
- ❌ Cancelar contrato (US-RB-005 — separado em PR próprio)
- ❌ Real-time updates Centrifugo
- ❌ Migrar Blade `view('recurringbilling::index')` — fallback intacto (cutover Onda 10)
- ❌ Pix Automático JRC (US-RB-006 — gap remanescente catalogado)
- ❌ Dunning automatizado (régua 3/7/15d — gap remanescente)

---

## UX Targets

- p95 first-paint < 1500ms (KPIs + 50 linhas paginadas)
- 0 erros JS console em smoke biz=1 (Wagner WR2 SC, monitor 1280px)
- Cabe em monitor 1280px sem scroll horizontal (canon Larissa ROTA LIVRE)
- Drawer abre em < 100ms (já carregado client-side junto com lista)
- Tipografia canon: title 28px/700/-0.02em, subtitle mono 11px uppercase letter-spacing 0.05em, KPI value 22px bold, badge 11px
- Cores semânticas: `oklch(0.75 0.13 145)` (ok verde) · `oklch(0.70 0.13 60)` (warn amarelo) · `oklch(0.55 0.18 25)` (bad vermelho)

---

## UX Anti-patterns

- ❌ Cor crua Tailwind dentro do TSX (canon = classes `.rec-*` escopadas em `.rec-cowork`)
- ❌ Modal/Dialog pra detalhe de linha (canon = drawer 3ª coluna sempre visível desktop ≥1100px, tab mobile <1100px)
- ❌ Adaptar peça-a-peça desfazendo coesão visual do prototipo (ver [feedback-design-literal-copy-quando-aprovado.md](../../../../memory/reference/feedback-design-literal-copy-quando-aprovado.md))
- ❌ `font-bold` em h1 (canon Cowork = `font-weight: 700` mas tipografia já é display)
- ❌ `sessionStorage` (canon = `localStorage` com prefix `oimpresso.rec.`)
- ❌ Inflar Page Inertia com lógica de cobrança/dunning (canon = chama Repository/Service)

---

## Endpoints alimentadores

| Método | Rota | Retorna |
|---|---|---|
| GET | `/recurring-billing` (X-Inertia) | Inertia render `RecurringBilling/Index` props `{kpis, subscriptions (defer), plans, filters, openCreate}` |
| GET | `/recurring-billing` (browser sem header X-Inertia) | mesmo (Inertia gerencia fallback) |
| GET | `/recurring-billing?new=1` (Onda 21) | mesmo render do index + prop `openCreate=true` → auto-abre drawer Nova assinatura (primary do sidebar aponta pra cá) |
| GET | `/recurring-billing/contacts/search?q=` (Onda 21) | JSON `{contacts:[{id,name,mobile,email,tax_number}]}` scoped business_id (Tier 0), type customer/both, min 2 chars |
| POST | `/recurring-billing` (Onda 21 — drawer submit) | cria Subscription (StoreAssinaturaRequest: contact_id, plan_id?, valor, ciclo, data_proxima_cobranca, gateway, forma_pagamento, descricao?) + redirect flash |
| GET | `/recurringbilling` (legacy Blade `view('recurringbilling::index')` "Hello World") | preservado intacto até Onda 10 cutover 301 |

---

## Tests anti-regressão

- [Modules/RecurringBilling/Tests/Feature/Wave4PagesIndexTest.php](../../../Modules/RecurringBilling/Tests/Feature/Wave4PagesIndexTest.php) — 5 cenários:
  1. `/recurring-billing` retorna 200 + Inertia render correto biz=1 autenticado com permission `recurringbilling.access`
  2. Cross-tenant isolation: subscription biz=1 NÃO aparece quando user biz=99
  3. `Inertia::defer` partial reload `only:[subscriptions]` returna só esse prop
  4. Filtro `status=past_due` aplica corretamente Repository scopado
  5. SubscriptionRepository::paginatedWithKpis batendo KPI agregado
- Existing `Wave18Test` + `RecurringV975SchemaTest` preservados sem mudança

---

## Refs

- [Index-visual-comparison.md](../../../../memory/requisitos/RecurringBilling/Index-visual-comparison.md) — visual canon mapeado pixel-perfect Onda 0
- [BRIEFING.md](../../../../memory/requisitos/RecurringBilling/BRIEFING.md) — estado consolidado RecurringBilling (atualizar pós-merge via skill brief-update)
- [ADR 0093 Multi-tenant Tier 0](../../../../memory/decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0101 Tests biz=1](../../../../memory/decisions/0101-tests-business-id-1-nunca-cliente.md)
- [ADR 0104 MWART](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 Visual comparison gate](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110 Cockpit Pattern V2](../../../../memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0114 Prototipo-ui Cowork loop](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- Skill [sidebar-menu-arch](../../../../.claude/skills/sidebar-menu-arch/SKILL.md) — pattern DataController + SIDEBAR_GROUPS
- Skill [inertia-defer-default](../../../../.claude/skills/inertia-defer-default/SKILL.md) — props caras com defer
