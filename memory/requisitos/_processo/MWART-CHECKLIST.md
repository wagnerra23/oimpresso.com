---
id: requisitos-processo-mwart-checklist
---

# MWART — Checklist de Tela Nova (forma válida do processo)

> **Quando usar:** ao criar tela Inertia/React em `resources/js/Pages/<Mod>/<Tela>/Index.tsx` (ou `<Tela>.tsx`) acompanhada de Controller. Vale tanto pra greenfield quanto pra migração Blade→Inertia.
>
> **Por quê este doc existe:** PR #349 ("Visão Unificada Cockpit V2") entregou Page funcional mas sem charter, sem Pest test, sem visual-comparison, com botão dando 404. CI passou em soft mode mas a tela foi pra prod incompleta. Este checklist + o gate `mwart-gate.yml` (que agora checa todos os itens) virou o **enforcement** da forma válida.

---

## Pipeline canônico (ADR 0104) — 5 fases

| Fase | O que entrega | Quem faz |
|---|---|---|
| **F1 PLAN** | Pedido + brief + RUNBOOK + SPEC.md (US declarada) | Wagner ↔ Cowork no `prototipo-ui/` |
| **F1.5 VISUAL APPROVAL** | Protótipo HTML + screenshot aprovado por Wagner + visual-comparison.md (15 dimensões) | Cowork → Wagner aprova screenshot |
| **F2 BACKEND BASELINE** | Controller + Routes + DataController menu + Pest test do `index()` legacy (se migração) | Claude Code |
| **F3 FRONTEND** | Page Inertia + charter `<Tela>.charter.md` + Controller atualizado | Claude Code |
| **F4 QA** | Pest GUARD (≥3 tests: Inertia component + KPIs/shape + Tier 0 isolation ADR 0093) | Claude Code |
| **F5 CUTOVER** | Quick Sync deploy + smoke prod + comm canary 7d (se cliente afetado) | Claude Code + Wagner |

---

## ✅ Artefatos obrigatórios por tela MWART nova

> Cada item é checado pelo CI gate `mwart-gate.yml` ([source](../../../.github/workflows/mwart-gate.yml)). Soft mode hoje (alerta no PR), hard mode quando backfill US-MWART-002 entregar.

### 1. Page Inertia
**Path:** `resources/js/Pages/<Mod>/<Tela>/Index.tsx` (ou `<Tela>.tsx`)
**O que tem:** componente exportado + `XxxPage.layout = (page) => <AppShellV2>{page}</AppShellV2>`
**Padrão visual:** Cockpit V2 (ADR ui/0114) — tokens emerald/rose/amber/stone, AppShellV2 layout, 1280px cabe sem scroll H

### 2. Charter ao lado do .tsx
**Path:** `resources/js/Pages/<Mod>/<Tela>/Index.charter.md` (ou `<Tela>.charter.md`)
**Frontmatter obrigatório:** `page, component, owner, status, last_validated, parent_module, tier, charter_version`
**8 seções obrigatórias:** Mission · Goals · Non-Goals · UX Targets · UX Anti-patterns · Automation Hooks · Automation Anti-hooks · Métricas vivas
**Exemplo template:** [Repair/Dashboard/Index.charter.md](../Repair/Dashboard/Index.charter.md)

### 3. Controller
**Path:** `Modules/<Mod>/Http/Controllers/<Tela>Controller.php`
**Padrão:** `Inertia::render('<Mod>/<Tela>/Index', [...props])` + multi-tenant scope `business_id`

### 4. Routes
**Path:** `Modules/<Mod>/Routes/web.php`
**Padrão:** dentro do `Route::middleware(['web', 'auth', 'language', 'timezone', 'AdminSidebarMenu'])->prefix('<mod>')->name('<mod>.')->group(...)`

### 5. Sidebar entry (DataController)
**Path:** `Modules/<Mod>/Http/Controllers/DataController.php` no método `modifyAdminMenu()`
**Por quê:** sem isso, user precisa digitar URL na barra. Não é descoberta — é hidden feature.

### 6. SCOPE.md
**Path:** `Modules/<Mod>/SCOPE.md` linha `contains:` adicionar `<Tela>Controller`
**Enforcement:** workflow `check-scope` falha em soft mode se Controller não declarado

### 7. Pest GUARD
**Path:** `Modules/<Mod>/Tests/Feature/<Tela>ControllerTest.php`
**3+ tests obrigatórios:**
- Renderiza Inertia component path correto
- Expõe shape de props esperado (KPIs, dados)
- **Tier 0 IRREVOGÁVEL** ADR 0093 — multi-tenant isolation: biz B nunca vê dados de biz A
**Padrão:** [`Modules/Repair/Tests/Feature/RepairIndexMwartTest.php`](../../../Modules/Repair/Tests/Feature/RepairIndexMwartTest.php) — skip gracioso quando DB greenfield

### 8. Visual-comparison (ADR 0107)
**Path:** `memory/requisitos/<Mod>/<tela-kebab>-visual-comparison.md` (ou `<mod-lower>-<tela>-visual-comparison.md`)
**Frontmatter:** `status: approved` (não `draft`)
**6+ de 8 dimensões preenchidas** — Layout · Hierarquia · Densidade · Iconografia · Estados · Atalhos · Persistência · Componentes
**Sem TODO/??? na coluna "Decisão MWART"**

### 9. (Opcional) RUNBOOK + SPEC
**Path:** `memory/requisitos/<Mod>/RUNBOOK-<tela-kebab>.md` + `memory/requisitos/<Mod>/SPEC.md` com US-* declarada
**Quando exigido:** sempre via `mwart-gate.yml` (ADR 0104 §F1 PLAN)

---

## 🚨 Anti-padrões (errado em PR #349)

| Erro | Sintoma | Como evitar |
|---|---|---|
| Hardcode de business name no .tsx (`"ROTA LIVRE"`) | Outro biz logado vê nome errado | Controller passa `businessName` via prop; .tsx usa `{businessName}` |
| Hardcode de período (`"Maio 2026"`) | Sempre mostra Maio mesmo em junho | Controller calcula `Carbon::isoFormat('MMMM YYYY')` |
| Botão CTA leva pra rota inexistente | Click → 404 | Criar rota stub ou remover botão até feature pronta |
| KPI card com dado mas sem onClick (gap vs ADR ui/0002) | "Drill-down por click" prometido, mas card não responde | KpiCard component já aceita `onClick`; só passar |
| Sidebar entry esquecida | Tela invisível na navegação; user só acha digitando URL | DataController::modifyAdminMenu() inclui novo `$sub->url(...)` |
| Charter ausente | Sem fonte da verdade pra próximos devs entenderem decisões | Criar `<Tela>.charter.md` ao lado do .tsx **junto com** o Page |
| Pest test ausente | Multi-tenant isolation (Tier 0) sem cobertura → risco vazar dado entre tenants | Criar `<Tela>ControllerTest.php` com test de isolamento |
| Divergir de ADR sem append | ADR canon dorme com decisão antiga; código diverge silenciosamente | Apend ao ADR ou criar nova ADR `supersedes: [N]` |

---

## 📋 Pra rodar o checklist em uma PR

1. CI workflow `mwart-gate.yml` roda automaticamente em PR que toca `resources/js/Pages/**/*.tsx`
2. Comenta no PR com lista de violações (RUNBOOK ausente / charter ausente / Pest test ausente / etc)
3. Soft mode — não bloqueia merge, mas você vê o débito
4. Pra forçar bypass: comentar `/mwart-override <razão>` no PR (vira ADR per-tela `lifecycle: historical`)

---

## 🔗 Referências

- [ADR 0104 — Processo MWART canônico](../decisions/0104-processo-mwart-canonico-unico-caminho.md) (5 fases obrigatórias)
- [ADR 0107 — Emendation 0104 visual-comparison gate F3](../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0114 — prototipo-ui Cowork loop formalizado](../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- [ADR 0093 — Multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 — Constituição V2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [Skill mwart-process](../../../.claude/skills/mwart-process/SKILL.md)
- [Skill mwart-comparative V4](../../../.claude/skills/mwart-comparative/SKILL.md)
- [Workflow mwart-gate.yml](../../../.github/workflows/mwart-gate.yml)
- [Workflow charter-gate.yml](../../../.github/workflows/charter-gate.yml)
