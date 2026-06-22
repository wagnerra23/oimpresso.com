---
page: /admin/screen-review
component: resources/js/Pages/Admin/ScreenReview.tsx
controller: Modules\Admin\Http\Controllers\ScreenReviewController@index
route: admin.screen-review
status: draft
owner: wagner
persona_principal: Wagner / governance + PDCA loop visual telas (1440px desktop)
persona_secundaria: Claude Code (lê reviews pra próximo round F1.5)
charter_version: 1.0
charter_at: 2026-05-17
related_adrs:
  - 0104-processo-mwart-canonico-unico-caminho
  - 0107-emendation-0104-visual-comparison-gate-f3
  - 0114-prototipo-ui-cowork-loop-formalizado
  - 0122-admin-center-ct100
  - 0160-governance-v4-scoped-scorecards-buckets
related_predecessor_visual: resources/js/Pages/kb/Index.v2.tsx
related_sibling: resources/js/Pages/Admin/GovernanceV4.tsx
mwart_pattern_reuse:
  blueprint_canonical: resources/js/Pages/kb/Index.v2.tsx (tri-pane validado prod)
  blueprint_screenshot_approval: SKIP (kb_v2 já validado Wagner ONDA 2)
  derived_screens: [Admin/ScreenReview]
  divergence_from_blueprint: "Sidebar=Módulos (contagem PDCA) + Lista=Telas filtradas + Reader=ReviewReader (screenshots 1440+1280 + charter excerpt + Wagner actions)"
---

# Charter — `resources/js/Pages/Admin/ScreenReview.tsx` (DRAFT)

> Tela governance PDCA tri-pane mostra TODAS telas `.tsx` do projeto com status review (pending-wagner / approved / rejected / iterate). Copia EXATAMENTE pattern visual de `kb/Index.v2.tsx` (gate F1.5 SKIP). Cada tela com `.review.md` ao lado registra rounds Wagner-Claude append-only.

## Mission

Command center Wagner pra fechar loop PDCA visual: ver 200+ telas Inertia React do projeto, status review consolidado, click pra abrir reader com screenshots 1440+1280 lado-a-lado + charter excerpt + UX targets vs medido + lista desvios. Wagner aprova/rejeita/itera/re-smoke via 4 botões. Status `rejected` opcionalmente abre Initiative governance (Cortex-style 14d deadline) pra Claude iterar. Drift alert topo se >0 pending-wagner há >7d.

## Goals (faz)

1. **ScreenReviewSidebar** — lista módulos top-level (`Admin`, `Vestuario`, `Jana`, etc) com badges contagem status: `2 pending / 5 approved / 1 rejected`. Botão "Todos" agrega.
2. **ScreenList** — telas do módulo selecionado, filter chips pills: status (pending/approved/rejected/iterate) + round range (1-3 / 4+ ) + busca por nome. Click abre Reader.
3. **ReviewReader** — pane direito com:
   - Header: nome tela + módulo + `RoundBadge` (status atual + round N)
   - Screenshots 1440 + 1280 lado-a-lado (se existem em `prototipo-ui/`)
   - Charter excerpt (`UX Targets` bullets) + link "abrir charter"
   - Lista desvios último round
   - 4 botões Wagner: **Aprovar** · **Rejeitar** (+ checkbox "Abrir Initiative") · **Iterar** · **Re-smoke**
   - Histórico rounds anteriores (timeline append-only)
4. **CommandPalette** ⌘K busca tela cross-módulo
5. **DriftAlertBanner topo** vermelho se >0 pending-wagner há >7d (charter Wave 27 pattern)
6. **Integração GovernanceV4**: link header "Screen Review" + accordion "Screen Reviews" pendentes na sidebar GovernanceV4
7. **Persistência localStorage** prefix `oimpresso.screen-review.*` (último módulo, último screen, filter state)

## Non-Goals (NÃO faz)

- Editar charter inline (vai pra editor charter futuro — só leitura aqui)
- Tomar screenshot automático (assume `prototipo-ui/<mod-kebab>/<tela-kebab>/screenshot-1440.png` existe)
- Substituir gate visual F1.5 do MWART (complementa — feedback loop pós-deploy)
- Rodar Pest/build (read-mostly tela; ações via botões disparam endpoints)
- Cross-business (Wagner-only via IsWagner — governance é repo-wide)

## UX Targets

- First-paint < 800ms (props `modules` e `screens` em `Inertia::defer`)
- 1440px desktop sem scroll horizontal (Wagner monitor primário)
- Screenshots lazy-load (placeholder enquanto carrega)
- Botões Wagner action com confirm modal apenas quando `rejected` (toast direto pra approved/iterate)
- Cores semânticas Cockpit V2 (`text-destructive` pra rejected, `text-emerald-N00` pra approved)
- Tipografia canon ADR 0110

## UX Anti-patterns

- Modal pra detalhe tela (canon = pane direito tri-pane)
- Tabs `border-b-2` em filter chips (canon = pills `rounded-full`)
- `sessionStorage` (canon = `localStorage` prefix `oimpresso.screen-review.*`)
- Edição inline screenshot (read-only — gera via comando externo)
- Cor crua hardcoded (`bg-red-500` proibido — use tokens)

## Automation Hooks

- `GET /admin/screen-review` — `ScreenReviewController@index` (Inertia::defer `modules` + `screens`)
- `POST /admin/screen-review/{screenPath}/status` — append round em `<Tela>.review.md` (NUNCA edita anterior); status `rejected` + `create_initiative=true` abre Initiative governance via InitiativeService::createFromScorecardBreach (idempotent)
- IsWagner middleware (governance Wagner-only)
- AdminAuditLogger.log('screen_review.update_status') — PiiRedactor aplicado

## Automation Anti-hooks

- NÃO envia notificação Slack/email ao aprovar/rejeitar (Wagner é único usuário — ruído)
- NÃO escreve no DB no render (read-only — updates só via POST explícito)
- NÃO permite edição rounds anteriores (append-only Tier 0)
- NÃO permite acesso fora Wagner (IsWagner middleware fail-secure)
- NÃO acessa screenshots cross-business (governance repo-wide)
- NÃO publica review automático (Wagner é autoridade)

## Sub-components em `_components/` (W30-B scope)

4 sub-components novos:

- `ScreenReviewSidebar.tsx` — lista módulos top-level com badges contagem status
- `ScreenList.tsx` — lista telas filtrada do módulo selecionado + chips
- `ReviewReader.tsx` — pane direito com screenshots + charter + Wagner actions + histórico rounds
- `RoundBadge.tsx` — badge "Round N" com cor por status (verde/vermelho/amarelo/cinza)

Reaproveita componente já existente:

- `DriftAlertBanner` (W29) — variant `pending-over-7d` (mesma cor amarelo Wave 27)

## Métricas vivas (Pest GUARD pendente W30-D)

```php
it('renders /admin/screen-review in <800ms p95 with 200+ telas + defer')
it('does not mutate state on GET')
it('blocks non-Wagner users via IsWagner middleware (403)')
it('updateStatus appends round to <Tela>.review.md (NEVER edits prior rounds)')
it('round number increments monotonically per screen')
it('updateStatus rejected + create_initiative=true creates Initiative idempotently')
it('updateStatus 404 quando screenPath inexistente')
it('uses localStorage prefix oimpresso.screen-review.* (never sessionStorage)')
it('respects IsWagner for screen-review repo-wide')
```

## Comparáveis canônicos

- **Linear** (tri-pane issue navigator + ⌘K densidade) — layout principal
- **Vercel previews** (screenshots 1440+1280 visual diff) — referência screenshots side-by-side
- **Figma comments review** (rounds append-only + Wagner actions) — referência PDCA loop
- **Excluir:** Jira, Asana (overhead enterprise + sem screenshot pattern)

## Refs

- Blueprint canônico tri-pane: [`resources/js/Pages/kb/Index.v2.tsx`](../kb/Index.v2.tsx) (validado Wagner ONDA 2)
- Sibling: [`Admin/GovernanceV4.tsx`](./GovernanceV4.tsx)
- [ADR 0104 — Processo MWART canônico](../../../../memory/decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0107 — Visual comparison gate F3](../../../../memory/decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — Protótipo UI Cowork loop formalizado](../../../../memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0122 — Admin Center @ CT 100](../../../../memory/decisions/0122-admin-center-ct100.md)
- RUNBOOK Inertia::defer: [`memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md`](../../../../memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md)

## Histórico

| Data | Autor | Mudança |
|---|---|---|
| 2026-05-17 | W30 Agent B | Charter draft criado pra `ScreenReview.tsx` (tri-pane copy kb_v2). Gate F1.5 SKIP. Pendente aprovação Wagner em Non-Goals + Anti-hooks pra `status: live`. |
