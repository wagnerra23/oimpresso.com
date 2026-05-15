---
slug: sells-audit-cockpit-runbook-create-2026-05-15
title: "Sells/Create — Audit Cockpit Runbook modo B (pre-canary biz=4)"
type: audit
module: Sells
status: active
date: 2026-05-15
---

# Audit — `Sells/Create.tsx` × Cockpit Pattern (modo B)

> **Auditor:** skill `cockpit-runbook` modo B (regras CHECKLIST.md + Nielsen + ADR 0039 + R-DS-001..012).
> **Alvo:** [`resources/js/Pages/Sells/Create.tsx`](../../../resources/js/Pages/Sells/Create.tsx) — 1391 LOC, MWART live em biz=1 via flag `useV2SellsCreate`, OFF em biz=4 ROTA LIVRE.
> **Charter:** [`Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md) (live, version 1, validado 2026-05-08).
> **Persona:** Larissa @ ROTA LIVRE biz=4 (vestuário Termas do Gravatal/SC, 1280px, ~5 vendas/dia, não-técnica).
> **Contexto:** PR-GATE-2 do plano híbrido sessão 2026-05-15 — pre-canary biz=4. Rodado **paralelo aos 6 agents BG** (Edit + ContactQuickAdd + ProductQuickAdd + Hotkeys + CashDenomination + ContactSummary). Audit **doc-only** — não edita `Create.tsx`. Fixes vão em PR separado pós-merges dos agents.
> **Audit comparado prévio:** [`design-arte 2026-05-13`](../../sessions/2026-05-13-design-arte-sells-create.md) — nota 68/100 (15 dim P0-P3). Esta audit usa 3 categorias (DS40+ADR30+UX30=100) e dá nota **79/100**. Metodologias complementares.

---

## Findings

Ordem: CRITICAL → WARN → INFO. `file:line` clicável.

### CRITICAL (2 — bloqueiam ≥ 70 score; corrigir antes de cutover biz=4)

```
[CRITICAL] resources/js/Pages/Sells/Create.tsx:639-663 — R-DS-001 (filter pills usam <button> HTML cru)
  Fix: trocar por <Button variant={isActive ? "default" : "ghost"} size="sm" className="rounded-full"> de @/Components/ui/button
  Impacto: perde acessibilidade embutida do <Button> shadcn (focus ring, disabled state, aria); 5 pills afetadas

[CRITICAL] resources/js/Pages/Sells/Create.tsx:911-918 — R-DS-001 (botão remover linha produto usa <button> HTML cru)
  Fix: <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-destructive">
       <Trash2 className="h-4 w-4" /></Button>
  Impacto: a11y reduzida em ação destrutiva (remover produto) — pior local pra economizar
```

### WARN (4 — gaps de comportamento; mergeáveis mas geram tasks pós-canary)

```
[UX-WARN]  resources/js/Pages/Sells/Create.tsx:670-734 — H1/Q4 (visibility of system status)
  KPIs (Itens / Total venda / Pago / Status pgto) renderizam zerados durante mount inicial.
  Larissa em conexão SC vê "Itens 0 / R$ [redacted Tier 0]" antes do React hidratar — parece broken.
  Fix: <Skeleton className="h-24"/> nos 4 KPIs durante !data; ou shimmer animado por ~150ms.
  ROI: alto (impressão "lento/quebrado" é mais cara que o esforço).

[UX-WARN]  resources/js/Pages/Sells/Create.tsx:1371-1373 — H3/Q2 (user control and freedom)
  Botão "Cancelar" navega pra /sells imediatamente, sem confirmar se há dados não-salvos.
  Autosave draft cobre F5 mas não cobre "cliquei Cancelar sem querer".
  Fix: handleCancel checa `data.products.length > 0 || data.notes` → confirm("Descartar venda em
       andamento? O rascunho será apagado.") antes de router.visit('/sells').
  ROI: médio (raro mas custo nulo quando acontece).

[UX-WARN]  resources/js/Pages/Sells/Create.tsx:827 — H7/Q5 (flexibility + empty state)
  Empty state diz "aperte / pra focar (em breve)" — promessa quebrada porque atalho `/` nunca foi
  implementado (US-SELL-007 backlog há tempo). Texto "em breve" há meses sinaliza débito.
  Fix opção A: remover "(em breve)" e dizer só "Use a busca acima"
  Fix opção B: implementar atalho `/` (~30min) — focusProductSearch já existe l.345.
  Recomendado: opção B (eliminar débito > esconder).

[UX-WARN]  resources/js/Pages/Sells/Create.tsx:610-621 — H8 (aesthetic and minimalist)
  Subtitle exibe "Local: <name>" mesmo com campo Local visível abaixo no Card "Dados da venda" l.789.
  Ruído duplicado. Larissa lê 2x a mesma info.
  Fix: remover bloco {props.defaultLocation?.name && ...} do subtitle. Campo Local já comunica.
```

### INFO (3 — cosméticos; ficam pra ciclo seguinte ou descartar)

```
[INFO]  resources/js/Pages/Sells/Create.tsx:744 — R-DS-008 (template listagem)
  Grid 4 colunas `grid-cols-1 md:grid-cols-2 lg:grid-cols-4` é pattern correto.
  Mas em 1280px (Larissa) usa apenas md-breakpoint (768px+) = 2 colunas, não 4.
  Considerar `lg:grid-cols-2 xl:grid-cols-4` pra otimizar 1280px (que é breakpoint comum não-stretchado).
  Não-bloqueador — design-arte D-10 deu 8/10 nesse ponto.

[INFO]  resources/js/Pages/Sells/Create.tsx:865, 879, 889, 894 — R-DS-004 (espaçamento)
  Inputs tabela produtos `className="h-8"` (32px) abaixo do touch-target Apple HIG 44px.
  Larissa é desktop-only (mouse) — OK funcional. Mas se algum dia tablet, regressão.
  Fix opcional: `h-9` (36px) é menos denso mas ainda compacto.

[INFO]  resources/js/Pages/Sells/Create.tsx:1367 — H4 (consistency)
  Footer microcopy: "Atalho: Ctrl+Enter pra salvar" mas Mac users veem "⌘+Enter" em outras
  telas. Detectar OS: `navigator.platform.includes('Mac') ? '⌘+Enter' : 'Ctrl+Enter'`.
  ROI baixíssimo — Larissa é Windows.
```

---

## Pontos OK (não mexer — paridade SOTA)

- ✅ **Persistent Layout V2** (l.1391) `SellsCreate.layout = page => <AppShellV2>{page}</AppShellV2>` — ADR 0039 respeitado
- ✅ **localStorage prefixo `oimpresso.`** (l.98, 505) — DESIGN.md §12 + R-DS-012
- ✅ **Multi-tenant Tier 0 em draft key** (l.501-506) `oimpresso.sells.create.draft.{bizId}.{userId}` — ADR 0093, sem isso draft de Wagner biz=1 vazaria pra Larissa biz=4
- ✅ **Iconografia 100% lucide-react** (l.20) — R-DS-003
- ✅ **Tokens shadcn em tudo material** — `bg-background bg-muted/30 bg-card border-border text-foreground text-muted-foreground` (R-DS-002)
- ✅ **Cores semânticas amber/blue/emerald** em status pgto (l.704-722, 978-981) — ADR 0110 permite (rose/emerald/amber/blue=info/warning/success canon)
- ✅ **EmptyState shared com action** (l.823-833) — atende Q5 do CHECKLIST + R-DS-008 + GOTCHAS evita inline emoji
- ✅ **PageHeader shared importado** (l.21) — apesar de a tela renderizar header inline custom (l.605-622) por ter scroll-spy nav, importação está ok pra futuro refactor
- ✅ **Esc + Cmd+Enter listeners com cleanup** (l.452-477) — sem leak, padrão GOTCHAS
- ✅ **Esc bloqueia em input/textarea** via blur — não rouba digitação
- ✅ **`oimpresso.sells.create.advanced.open` persiste `<details>`** (l.98, 209-217)
- ✅ **FieldError + auto-open `<details>` em erro** (l.122-131, 482-494) — US-SELL-010 (PR #789)
- ✅ **dropdownEntries() helper filtra prepend_none vazio** (l.33) — GOTCHAS UltimatePOS forDropdowns
- ✅ **datetime-local nativo + helpers** (l.135-147) — datepicker BR `DD/MM/YYYY HH:mm` ↔ `YYYY-MM-DDTHH:mm`
- ✅ **`is_direct_sale: 1` no transform** (l.373) — pula cashRegister gate no controller (Larissa balcão direto)
- ✅ **Scroll-spy IntersectionObserver** (l.577-598) — pill ativa segue scroll
- ✅ **postMessage contact_created** (l.557-566) — handler escuta cadastro paralelo de cliente em popup

---

## Score

| Categoria | Score  | Detalhe                                                                                            |
|-----------|--------|----------------------------------------------------------------------------------------------------|
| DS (40)   | 30/40  | 2 CRITICAL (R-DS-001 `<button>` cru × 2: pills nav + remover produto); 0 WARN; 0 INFO              |
| ADR (30)  | 30/30  | Zero violations ADR 0039 §1-§5. Coluna direita ausente justificada (form sem entidade em foco — R-DS-010). |
| UX (30)   | 19/30  | 0 CRITICAL; 4 WARN (H1/Q4 skeleton, H3/Q2 cancel sem confirm, H7/Q5 promessa `/` quebrada, H8 subtitle redundante); 3 INFO (grid bp, h-8 input, OS detect) |
| **TOTAL** | **79/100** | 🟡 **Bom, alguns gaps** — mergeável se velocidade > polish; 2 CRITICAL fáceis ROI alto |

**Banda:** 70-89 🟡 Bom (CHECKLIST.md §G.2). Recomendação: corrigir 2 CRITICAL antes de cutover biz=4 (ROI altíssimo — ~30min cada).

---

## Benchmark (categoria: Form / CRUD — criar/editar entidade)

Patterns canônicos da categoria (BENCHMARKS.md §4 — Salesforce / HubSpot / Pipedrive / Notion forms):

- ⚠️ **Auto-save por field** — oimpresso tem `data` debounced 500ms (não per-field). Pattern Notion é per-field com indicador "Salvo" piscando. **Gap aceitável** pra ERP fiscal (commit-all-or-nothing pra transaction integrity).
- ✅ **Side-by-side preview** — N/A (venda não tem preview; recibo é pós-submit)
- ✅ **Validação inline** — FieldError por campo (US-SELL-010 PR #789)
- ✅ **Required marker discreto** — Validação no submit + footer "Adicione pelo menos 1 produto"
- ✅ **Smart defaults baseados em contexto** — `status='final'`, `defaultDatetime=format_now_local`, `walkInCustomer.id` (skill `multi-tenant-patterns` ROTA LIVRE)
- ✅ **Tabs/accordion pra forms longos** — filter pills + `<details>` "Mais opções" (10 campos colapsáveis)
- ❌ **Confirma antes de fechar com unsaved** — UX-WARN catalogado acima (H3/Q2)
- ✅ **Atalho Cmd+S salvar / Cmd+Enter submit** — `⌘+Enter` implementado (l.452-462)

**Gap pra estado da arte categoria:** 1 WARN (confirma unsaved) + 1 pattern-fit aceitável (auto-save per-data vs per-field). Tela está acima da média BR (Bling/Tiny não tem nem inline validation) e abaixo da SOTA SaaS B2B global (Notion/Linear forms) — esperado pra ERP fiscal vertical.

---

## Recomendação executável

**Priorizar pré-canary biz=4** (~1h total):

1. **PR-FIX-1 · 2 CRITICAL R-DS-001** — trocar `<button>` HTML cru por `<Button>` shadcn em pills nav (l.639) + remover produto (l.911). ~30min. ROI altíssimo (a11y + score 79→89).
2. **PR-FIX-2 · 2 WARN ROI alto** — implementar atalho `/` (~30min, `focusProductSearch` já existe) + remover subtitle redundante "Local: X" (l.610-621, 1 linha). Score 79→85 em outra dimensão.

**Pós-canary backlog (não bloqueia ROTA LIVRE):**

3. Skeleton KPIs no mount inicial (H1/Q4) — ~1h
4. Confirma Cancel com unsaved (H3/Q2) — ~30min

**Não fazer (descartar):**

- INFO grid breakpoints (l.744) — 1280px funciona com 2 colunas, é OK
- INFO h-8 inputs — Larissa desktop-only mouse, touch-target irrelevante
- INFO OS detect ⌘ vs Ctrl — Larissa Windows-only

---

## Anti-recomendações (ADR 0105 cliente como sinal)

**Não criar US ativa por:**

- Onboarding tooltips / tour primeira venda (design-arte D-15 nota 3/10) — Larissa usa há 4 anos, **sem sinal**. Vira ADR feature-wish se Martinho Caçambas (OficinaAuto candidato) ativar.
- AI-native form fill conversacional (estado-da-arte Notion AI Q&A) — Charter Non-Goal, fora escopo.

---

## Coexistência com 6 agents BG em curso

Este audit é **doc-only** — NÃO toca `Create.tsx`. Pode merger no main mesmo enquanto agents BG editam a tela. Fixes propostos (PR-FIX-1, PR-FIX-2) **aguardam consolidação dos 6 agents** porque tocam linhas que vão mudar (pills nav, empty state, subtitle).

Ordem recomendada pós-agents:
1. Agents BG fecham → você consolida 6 branches → mergeia
2. Eu (Claude) rodo PR-FIX-1+2 sobre estado pós-consolidação
3. Re-audit (modo B de novo) → confirmar score ≥ 85 → cutover biz=4

---

## Refs

- [resources/js/Pages/Sells/Create.tsx](../../../resources/js/Pages/Sells/Create.tsx) — alvo audit (1391 LOC)
- [`Create.charter.md`](../../../resources/js/Pages/Sells/Create.charter.md) — contrato vivo da tela
- [`RUNBOOK-create.md`](RUNBOOK-create.md) — playbook migração MWART
- [`sells-create-visual-comparison.md`](sells-create-visual-comparison.md) — comparativo visual canon Cockpit (8 dim, 2026-05-08)
- [`CAPTERRA-DESIGN-FICHA.md`](CAPTERRA-DESIGN-FICHA.md) — Capterra UX 15 dim P0-P3 (design-arte 2026-05-13, nota 68/100)
- [Session log design-arte](../../sessions/2026-05-13-design-arte-sells-create.md)
- [ADR 0039 — Chat Cockpit](../../decisions/0039-ui-chat-cockpit-padrao.md) — layout-mãe 3 colunas
- [ADR 0093 — Multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0104 — Processo MWART canônico](../../decisions/0104-processo-mwart-canonico-unico-caminho.md)
- [ADR 0110 — Cockpit Pattern V2 canon list-detail](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md) §Cores semânticas (l.89-92) — rose=danger / emerald=success / amber=warning / blue=info-active permitidas (ADR 0110 sobrescreve R-DS-002 stricto)
- [`_DesignSystem/SPEC.md`](../_DesignSystem/SPEC.md) — R-DS-001..012
- [`.claude/skills/cockpit-runbook/CHECKLIST.md`](../../../.claude/skills/cockpit-runbook/CHECKLIST.md) — regras audit modo B + score 0-100
- [`.claude/skills/cockpit-runbook/BENCHMARKS.md`](../../../.claude/skills/cockpit-runbook/BENCHMARKS.md) — Form/CRUD categoria

**Última atualização:** 2026-05-15
