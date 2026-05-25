# DESIGN.md — Ponto de entrada de design do Oimpresso ERP

> **Para quem é este arquivo:** qualquer pessoa (humana ou agente) que vai trabalhar no visual, na UX, no design system, ou que precisa abrir uma sessão no **Claude Design canvas** (claude.ai/design) pra produzir mockups.
>
> Pra trabalhos de código backend/CRUD não-visuais, comece em [`CLAUDE.md`](CLAUDE.md). Pra acesso/deploy de produção, em [`INFRA.md`](INFRA.md).

---

## 1. O que você quer fazer?

| Cenário | Vá direto pra |
|---|---|
| **Comparar tela alvo com canon visual antes de codar** ⭐ | [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/) — UI Kit canônico (`os-page.jsx` é referência pra list+detail, [_DS UI-0010](memory/requisitos/_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md)) |
| Codar uma tela React nova ou alterar uma existente | Seção "Padrão técnico de implementação React" abaixo nesse arquivo |
| Mockup visual numa nova sessão Claude Design canvas | [`memory/requisitos/_DesignSystem/BRIEFING_CLAUDE_DESIGN.md`](memory/requisitos/_DesignSystem/BRIEFING_CLAUDE_DESIGN.md) — colar como 1ª mensagem + anexar arquivo |
| Decidir se um padrão visual é canônico ou divergência | [_DS UI-0010](memory/requisitos/_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md) (canon visual) + [ADR raiz 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md) (Cockpit layout-mãe) |
| Buscar/usar um componente shared existente | [`memory/requisitos/_DesignSystem/SPEC.md`](memory/requisitos/_DesignSystem/SPEC.md) |
| Gerar/auditar runbook de tela | Skill `cockpit-runbook` (pede `runbook da tela X` ou `audita tela X contra Cockpit`) |
| Auditar uma tela contra o sistema de design | [`memory/requisitos/_DesignSystem/audits/`](memory/requisitos/_DesignSystem/audits/) |
| Catálogo de acabamentos (lâminas, vinis, papéis) pra módulos gráficos | [`memory/requisitos/_DesignSystem/CATALOGO_ACABAMENTOS.md`](memory/requisitos/_DesignSystem/CATALOGO_ACABAMENTOS.md) |
| Visão geral da arquitetura visual | [`memory/requisitos/_DesignSystem/ARCHITECTURE.md`](memory/requisitos/_DesignSystem/ARCHITECTURE.md) |
| Histórico de mudanças no design system | [`memory/requisitos/_DesignSystem/CHANGELOG.md`](memory/requisitos/_DesignSystem/CHANGELOG.md) |

---

## 2. Workflow padrão de design (Wagner usa hoje)

**Loop formalizado** em [`prototipo-ui/PROTOCOL.md`](prototipo-ui/PROTOCOL.md) ([ADR 0114](memory/decisions/0114-prototipo-ui-cowork-loop-formalizado.md)) — 6 papéis + 7 fases (F0 brief → F1 design → F1.5 critique → F2 screenshot Wagner → F3 code → F3.5 a11y → F4 merge) com gates ratchet automatizados.

```
F0 BRIEF       [W]   pedido em COWORK_NOTES.md
F1 DESIGN      [CC]  protótipo visual em prototipos/<tela>/page.tsx
F1.5 CRITIQUE  [CD]  score ≥80 ok / 70-79 1 round refator / <70 discussão
F2 SCREENSHOT  [W]   aprovação visual síncrona (não tabela)
F3 CODE        [CL]  refator Inertia em <Tela>.tsx + .charter.md
F3.5 A11Y      [CA]  accessibility-review WCAG 2.1 AA
F4 MERGE       [W]   PR merge se F3.5 passou
```

Skill orquestradora: [`mwart-comparative V4`](.claude/skills/mwart-comparative/SKILL.md) ([ADR 0109](memory/decisions/0109-claude-design-plugin-integrado-processo-mwart.md)) integra Claude Design plugin Anthropic (design-critique + design-system + design-handoff + ux-copy + accessibility-review + research-synthesis).

---

## 3. Princípios não-negociáveis

- **PT-BR em tudo** — copy, label, comentário, commit. Código (classes, métodos) em inglês é OK.
- **Layout-mãe "Chat Cockpit"** (ADR 0039) — toda tela nova nasce dentro do `AppShellV2` 3-colunas (sidebar 260px / coluna principal / Apps Vinculados 320px opcional).
- **Tokens CSS do shell** (definidos em `resources/css/app.css`) — nunca cor hardcoded.
- **Componentes shared antes de criar novo** — `PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `ModuleTopNav`, `StatusBadge`, `EmptyState`, etc.
- **Atalhos canônicos** — ⌘K busca global, J/K navegar lista, E concluir, A adiar, N novo, / focar busca.
- **Cliente não pode reaprender o sistema** — qualquer mudança de menu/labels/ícones precisa de aprovação explícita do Wagner.
- **Dark mode + responsivo mobile** — DoD mínimo de qualquer tela.

Pra divergir de qualquer regra acima: **abrir ADR nova antes de codar**, não decidir em commit solto.

---

## 4. Stack visual

- **Inertia v3** + **React 19** + **TypeScript** + **Tailwind 4** + **shadcn/ui** + **lucide-react**
- **AppShellV2** (em portagem) + componentes em `resources/js/Components/`
- **TaskProvider** pra inboxes de módulo (não cria tela própria — registra provider em `Modules/<Mod>/Tasks/<Slug>Task.php`)
- **localStorage** com prefixo `oimpresso.` pra persistir estado de UI (empresa ativa, aba, filtros, painéis colapsados)

---

## 5. Designer canônico

**Claude (Anthropic)** — em última instância, decide visual sem perguntar; mas registra a decisão no session log de `memory/sessions/YYYY-MM-DD-*.md`.

Wagner é o aprovador final em divergências de padrão. Cliente (WR2 Sistemas / Eliana) é o aprovador final em mudanças de fluxo de trabalho do PontoWr2.

---

# Padrão técnico de implementação React

> **Leia esta seção SEMPRE que for criar nova tela ou alterar tela existente em React (Inertia v3).**
> Padrão formalizado em [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md).

## 6. Antes de codificar qualquer tela

1. **Abra o canon visual** em [`memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/) — UI Kit Cowork. Identifique qual `.jsx` corresponde ao tipo da sua tela:
   - **list+detail (CRUD operacional)** → [`os-page.jsx`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/os-page.jsx) ⭐ padrão canônico
   - **inbox unificada** → [`tasks.jsx`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/tasks.jsx) + [`viewers.jsx`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/viewers.jsx)
   - **conversação/chat** → [`chat.jsx`](memory/requisitos/_DesignSystem/ui_kits/cowork-2026-04-27/chat.jsx)
2. **Leia [_DS UI-0010](memory/requisitos/_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md)** — formaliza zip Cowork como canon e lista conflitos resolvidos (ex.: sidebar light de UI-0009 sobrevive).
3. **Leia [ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md)** — define layout-mãe "Chat Cockpit" 3-colunas, dual-tab Chat/Menu, painel direito de Apps Vinculados, atalhos J/K/E/A, Tweaks (vibe/densidade/accentHue).
4. **Leia o session log mais recente em `memory/sessions/`** — pode ter ajuste de design não refletido no ADR ainda.
5. **Olhe `resources/js/Layouts/AppShellV2.tsx`** — shell único do ERP em React (AppShell legado removido em 2026-05-04). Cliente final NÃO pode reaprender o sistema; mudança de menu/labels/ícones precisa ser aprovada explicitamente.

## 7. Hierarquia de decisões de UI

**Documento mãe canônico:** [**ADR UI-0013 Constituição UI v2**](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md) (accepted 2026-05-24) — define hierarquia de **4 camadas** que **camada superior herda das inferiores e nunca contradiz**:

```
Fundações (tokens cor/tipo/espaço · imutável via ADR)
  ↓
Shell (AppShellV2 + PageHeader · 1× pro app)
  ↓
Padrão de Tela (PT-01 Lista + PT-02..N · 5-7 templates)
  ↓
Módulo (varia)
```

Em ordem de precedência (regra mais alta vence em conflito):

1. **Stack-target do projeto** (Inertia v3 + React 19 + TS + Tailwind 4) — não muda sem ADR.
2. **Constituição UI v2** ([UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)) — mãe atual das 4 camadas.
3. **Tokens canon** ([ADR 0190](memory/decisions/0190-primary-button-roxo-medio-canon.md) primary roxo 295) — `oklch(0.55 0.15 295)` bg + `oklch(0.45 0.15 295)` border, universal cross-módulo.
4. **PageHeader canon v3** ([ADR 0180](memory/decisions/0180-pageheader-canon-href-direto-ghost-rapido.md) / [0182](memory/decisions/0182-pageheader-3-zonas-esq-centro-dir.md) / [0189](memory/decisions/0189-pageheader-modo-foco-edit-create.md)) — modo NAV (Index/Show) vs modo FOCO (Edit/Create) + 3 zonas Esq/Centro/Dir.
5. **PT-01 Lista** ([padrão de tela canônico](memory/requisitos/_DesignSystem/padroes-tela/PT-01-Lista.md)) — anatomia + DNA + slots de listagem operacional.
6. **Cockpit Pattern V2** ([ADR 0110](memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md)) — list+detail consolidado (ver §16).
7. **Canon visual Cowork 2026-04-27** ([_DS UI-0010](memory/requisitos/_DesignSystem/adr/ui/0010-zip-cowork-2026-04-27-canon-visual.md)) — UI Kit `os-page.jsx`/`tasks.jsx`/`viewers.jsx`/`chat.jsx`.
8. **Layout-mãe "Chat Cockpit"** ([ADR 0039](memory/decisions/0039-ui-chat-cockpit-padrao.md) + [_DS UI-0008](memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md)).
9. **Sidebar light por padrão** ([_DS UI-0009](memory/requisitos/_DesignSystem/adr/ui/0009-cockpit-sidebar-light-padrao.md) + [UI-0014](memory/requisitos/_DesignSystem/adr/ui/0014-sidebar-light-mantida-v2-parcial.md)) — sobrevive a UI v2 (Wagner explícito).
10. **Padrão Jana** ([ADR 0011](memory/decisions/0011-alinhamento-padrao-jana.md)) — UltimatePOS-like estrutura modular; vale pra DataController/Install (não-visual).
11. **Componentes shared** (`PageHeader`, `DataTable`, `PageFilters`, `KpiCard`, `ModuleTopNav`, `StatusBadge`, `EmptyState`) — usar antes de criar novo.
12. **Convenções 04** (`memory/04-conventions.md`) — naming PHP, rotas, blade.
13. **Bom gosto do designer** — em última instância, Claude decide visual sem perguntar; mas registra no session log + abre ADR se quebrou padrão.

## 8. Layout obrigatório de tela nova

Toda tela React do ERP **nasce dentro do `AppShellV2`** (3 colunas), com:

- **Sidebar (260px)** vinda do shell — você não recria sidebar dentro de página.
- **Topbar com breadcrumb** vinda do shell — você só passa `crumb={[...]}` via Inertia layout.
- **Coluna principal (1fr)** = sua tela.
- **Coluna direita (320px) "Apps Vinculados"** — *opcional*. Se sua tela tem contexto vinculado (uma OS em foco, um cliente, uma marcação), **você é obrigado a entregar o painel direito** com os blocos relevantes. Se não tem, a coluna some.

Para tela em modo **master/detail** (lista + viewer), use o padrão de `Pages/Tarefas/Index.tsx`:
- Lista à esquerda da coluna principal (ex.: 360px), viewer à direita (1fr).
- Atalhos **J/K** (navegar), **E** (concluir/confirmar), **A** (adiar/voltar) ligados via `useEffect` + listener global escopado à página.

Para tela em modo **CRUD clássico** (cadastro, listagem, edição), siga padrão Jana: `PageHeader` + `PageFilters` + `DataTable` + drawer/modal de edição.

## 9. Tokens visuais

Use **sempre** as variáveis CSS do shell (definidas em [`resources/css/cockpit.css`](resources/css/cockpit.css) + [`resources/css/inertia.css`](resources/css/inertia.css)):

```
--bg, --bg-2, --panel, --panel-2, --border, --border-2
--text, --text-mute, --accent, --accent-2, --accent-soft
--origin-OS-{bg,fg}, --origin-CRM-{bg,fg}, --origin-FIN-{bg,fg}, --origin-PNT-{bg,fg}
--row-h, --card-pad, --card-gap
```

### Primary universal (ADR 0190 — 2026-05-25)

Botões primários cross-módulo: **roxo 295 canônico**.

```css
background: oklch(0.55 0.15 295);  /* fill */
border:     oklch(0.45 0.15 295);  /* border */
```

Aceitos no `bg-primary` shadcn. Vetado: hue-per-grupo (rosa/magenta 330 banido) — universalidade reduz custo cognitivo do usuário trocando entre módulos.

**Não invente cor solta.** Se precisar de uma cor nova, derive via `oklch()` a partir de `--accent` ou da origem do módulo. Lint automático: `php artisan ui:lint` (ratchet mode) + workflow `ui-lint.yml` em CI bloqueia hex/RGB/HSL literal.

## 10. Apps Vinculados (coluna direita)

Cada bloco do painel direito é um componente em `resources/js/Components/LinkedApps/`:

- `LinkedOs.tsx` — número, cliente, prazo, estágio, CTA `[abrir]`
- `LinkedClient.tsx` — nome, telefone, último contato, CTA `[ligar]` `[whatsapp]`
- `LinkedPonto.tsx` — marcações do colaborador no dia, CTA `[justificar]`
- `LinkedFinanceiro.tsx` — saldo cliente + boletos abertos, CTA `[emitir cobrança]`
- `LinkedAttachments.tsx` — anexos da conversa/tarefa
- `LinkedHistory.tsx` — eventos cronológicos

**Regra:** cada bloco é colapsável (estado em `localStorage` por chave `oimpresso.linked.<bloco>.collapsed`), mostra um resumo enxuto e UMA ação primária. Se a tela não tem dado para um bloco, ele simplesmente não renderiza.

## 11. TaskProvider (quando criar tela de inbox)

Se a tela nova é uma inbox de pendências do módulo, **NÃO crie tela própria**. Em vez disso, registre um `TaskProvider`:

```php
// Modules/<Mod>/Tasks/<Slug>Task.php
class <Slug>Task implements TaskProvider {
  public function origin(): string { return 'OS'|'CRM'|'FIN'|'PNT'|'MFG'; }
  public function color(): string  { return 'amber'|'blue'|'emerald'|'violet'|'orange'; }
  public function for(User $u): Collection { /* o que esse usuário precisa fazer */ }
  public function viewerComponent(): string { return '<NomeDoComponenteReact>'; }
}
```

E entregue o componente viewer em `resources/js/Components/Viewers/<NomeDoComponenteReact>.tsx`. A tela `Pages/Tarefas/Index.tsx` agrega via `TaskRegistry` e renderiza o viewer correto.

## 12. Persistência de estado de UI

**Sempre** persistir em `localStorage` com prefixo `oimpresso.`:
- estado de empresa ativa, aba, rota, conversa, tarefa selecionada, filtros
- estado de painéis (colapsado/aberto), accordions do menu
- preferências do Tweaks panel

Nunca persistir em `sessionStorage` para esses casos — perdem na nova aba.

## 13. Atalhos de teclado

Lista canônica do ERP (toda tela nova herda):
- **⌘K / Ctrl+K** — busca global (já no shell)
- **J / K** — navegar lista (em master/detail)
- **E** — concluir/confirmar item em foco
- **A** — adiar/postergar item em foco
- **N** — nova entidade (em listagem CRUD; verbo do módulo)
- **/** — focar busca da lista atual

Toda tela com lista deve registrar listener via `useEffect` e `removeEventListener` no cleanup.

## 14. Quando divergir do padrão

Se você (humano ou agente) achar que precisa quebrar o padrão de UI:

1. **Pare antes de codificar.**
2. **Abra ADR nova** (próximo número sequencial após o último em `memory/decisions/`) explicando contexto/decisão/alternativas/consequências.
3. **Peça aprovação do Wagner** antes de mergear.
4. **Atualize esta seção** com a nova regra.

Padrão muda por ADR, nunca por commit solto.

## 15. Checklist mínimo antes de PR

**Checklist canônico completo:** [`memory/requisitos/_DesignSystem/PRE-MERGE-UI.md`](memory/requisitos/_DesignSystem/PRE-MERGE-UI.md) (6 camadas + anti-padrões AP1-AP8). Roda mental ~3min antes de PR.

Quick-check resumido:

- [ ] Tela vive dentro de `AppShellV2`
- [ ] Tokens CSS do shell (sem cor hardcoded) — AP1
- [ ] Coluna direita "Apps Vinculados" entregue se houver contexto vinculado
- [ ] Atalhos J/K/E/A ativos se for master/detail
- [ ] Estado persistido em `localStorage` com prefixo `oimpresso.`
- [ ] Componentes shared reusados antes de criar novo — AP2
- [ ] PT-BR em todo label/copy/comentário — AP8
- [ ] Se inbox de módulo → `TaskProvider` em vez de tela nova
- [ ] Session log atualizado em `memory/sessions/`
- [ ] ADR nova se quebrou padrão
- [ ] Charter `.charter.md` ao lado do `.tsx` se MWART migração (skill `charter-first`)

### Gates automáticos em CI

| Workflow | O que bloqueia |
|---|---|
| `ui-lint.yml` (Onda 2.1) | Hex/RGB/HSL crua, FontAwesome, emoji em UI (ratchet vs baseline) |
| `visual-regression.yml` ([ADR 0108](memory/decisions/0108-regressao-visual-pest-browser-tier-2.md)) | Diff de screenshots Pest 4 Browser > threshold (atualmente INFRA-ONLY, ver [US-INFRA-012](memory/requisitos/Infra/SPEC.md)) |
| `pr-ui-judge.yml` (Onda 4.1) | Claude Sonnet 4.5 avalia PR contra Constituição UI v2 — score 0-100 + 9 dimensões + sugestões cirúrgicas (~$3/mês a 100 PRs) |
| `mwart-gate.yml` | `<tela>-visual-comparison.md` obrigatório se Page Inertia nova (ADR 0107 §F1.5) |
| `charter-gate.yml` | `.charter.md` obrigatório ao lado de `.tsx` em paths MWART |
| `module-grades-gate.yml` ([ADR 0155](memory/decisions/0155-rubrica-module-grade-v3.md)) | Regressão de nota módulo vs baseline |

---

## 16. Cockpit Pattern V2 — list+detail consolidado (ADR 0110)

> **Pra páginas tipo "lista de entidades transacionais"** (vendas, compras, OSes, despesas, contas, clientes, produtos). Espelha `os-page.jsx` da §6 + adiciona spec concreto de tipografia, cores semânticas e endpoints REST.
> Fonte da verdade: [ADR 0110](memory/decisions/0110-cockpit-pattern-v2-canon-list-detail.md). Pages canon vivos: [Sells/Index.tsx](resources/js/Pages/Sells/Index.tsx), [Sells/Create.tsx](resources/js/Pages/Sells/Create.tsx), [SaleSheet.tsx](resources/js/Pages/Sells/_components/SaleSheet.tsx), [governance/Dashboard.tsx](resources/js/Pages/governance/Dashboard.tsx), [ProjectMgmt/Board/Index.tsx](resources/js/Pages/ProjectMgmt/Board/Index.tsx).

### 16.1. Anatomia (5 partes obrigatórias)

```
┌─ AppShellV2 ────────────────────────────────────┐
│ Header                                          │
│   PageHeader shared (h1 + subtitle + 1 botão)   │
│   3 KPIs cards (Abertas / Atrasadas / Total)    │
│   5 filter pills rounded-full + counter         │
├─ Tabela limpa ───────────────────┬─ Drawer Sheet │
│  data│nº│cliente 2L│$│$│badge   │ side="right"  │
│  [select row → bg-blue-50]      │ w-xl          │
├─────────────────────────────────────────────────┤
│ Footer sticky (form pages — opcional)           │
└─────────────────────────────────────────────────┘
```

### 16.2. Cores semânticas Cockpit V2 (proibido cor crua)

| Tom | Light | Dark | Uso |
|---|---|---|---|
| **Rose** (danger) | `bg-rose-50 text-rose-700 border-rose-200` | `bg-rose-950/40 text-rose-300` | Atrasos, urgentes, KPI Atrasadas |
| **Emerald** (success) | `bg-emerald-50 text-emerald-700 border-emerald-200` | `bg-emerald-950/40 text-emerald-300` | Pago, ✓ pagamentos, OK |
| **Amber** (warning) | `bg-amber-50 text-amber-700 border-amber-200` | `bg-amber-950/40 text-amber-300` | Saldo devedor, frete pendente |
| **Blue** (info/active) | `bg-blue-50 text-blue-700 border-blue-200` | `bg-blue-950/50 text-blue-300` | Pill ativo, parcial, linha selecionada |

**Proibido:** `bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+` (cores cruas sem semântica). Test [`CockpitPatternConformanceTest`](tests/Feature/Design/CockpitPatternConformanceTest.php) varre 117 Pages.

Red dot bullet pra urgência: `<span className="h-2 w-2 rounded-full bg-rose-500" />`.

### 16.3. Tipografia Cockpit V2 (specs concretos)

| Elemento | Classe | Px |
|---|---|---|
| h1 página | `text-2xl font-semibold tracking-tight` | 24 |
| Subtitle | `text-sm text-muted-foreground leading-relaxed` | 14 |
| KPI label | `text-[11px] font-semibold uppercase tracking-widest text-muted-foreground` | 11 |
| KPI value | `text-4xl font-semibold tabular-nums` | 36 |
| Filter pill | `text-xs font-medium` | 12 |
| Pill counter | `text-[10px] tabular-nums` | 10 |
| Tabela th | `text-[11px] font-semibold uppercase tracking-wider` | 11 |
| Tabela td | `text-sm` | 14 |
| Badge | `text-[11px] font-medium` | 11 |
| Drawer mini-KPI label | `text-[10px] font-semibold uppercase tracking-wider` | 10 |
| Drawer section title | `text-[10px] font-semibold uppercase tracking-widest` | 10 |

**Proibido:** `font-bold/extrabold/black` em h1-h3 (canon = `font-semibold`).

### 16.4. Filter pills canônicas (NÃO tabs border-bottom)

```tsx
<button className={
  'inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition-colors ' +
  (isActive
    ? danger ? 'bg-rose-100 text-rose-800' : 'bg-blue-50 text-blue-700'
    : danger ? 'bg-rose-50/60 text-rose-700 hover:bg-rose-100'
            : 'bg-muted/40 text-muted-foreground hover:bg-muted hover:text-foreground')
}>
  <Icon size={13} />
  {label}
  {count > 0 && (
    <span className="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[10px] tabular-nums bg-background">
      {count}
    </span>
  )}
</button>
```

### 16.5. Drawer Sheet pattern (lista+detail)

Lista clicável → drawer lateral direito com SheetContent:

```tsx
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/Components/ui/sheet';

<Sheet open={open} onOpenChange={onOpenChange}>
  <SheetContent side="right" className="w-full sm:max-w-xl flex flex-col p-0 overflow-hidden">
    <SheetHeader className="px-6 pt-6 pb-4 border-b border-border">
      <SheetTitle>{title}</SheetTitle>
    </SheetHeader>
    {/* 4 mini-KPIs grid grid-cols-4 + sections + footer ações sticky */}
  </SheetContent>
</Sheet>
```

Ref vivo: [SaleSheet.tsx](resources/js/Pages/Sells/_components/SaleSheet.tsx).

### 16.6. Endpoints REST canônicos (2 por entidade)

```php
// Lista JSON minimalista — alimenta tabela Inertia
Route::get('/{entity}-list-json', [EntityController::class, 'inertiaList']);
// Returns 8 fields/linha + booleanos derivados (is_overdue)

// Detalhe JSON — alimenta drawer
Route::get('/{entity}/{id}/sheet-data', [EntityController::class, 'sheetData']);
```

**Permission scope explícito** + `business_id` where (defesa em profundidade ADR 0093). **UltimatePOS gotcha:** `total_paid` não é coluna em `transactions` — sempre subquery `transaction_payments` com `is_return`. Ref [TransactionUtil:2400](app/Utils/TransactionUtil.php).

### 16.7. Componentes shared canônicos (REUSE primeiro)

- `@/Components/shared/PageHeader` — h1+subtitle+action canônicos
- `@/Components/shared/KpiCard` — KPI com tone (default/success/warning/danger/info) + size (compact/default/large)
- `@/Components/shared/EmptyState` — placeholder lista vazia
- `@/Components/ui/sheet` — drawer shadcn

Antes de criar componente custom, **buscar** em `resources/js/Components/`. Pattern: KpiCard custom inline foi banido em 2026-05-08 (33 Pages migradas).

### 16.8. Anti-padrões V2 (testes Pest CI varre)

- ❌ `<AppShell>` sem V2 ([auto-mem](memory/feedback_persistent_layouts.md))
- ❌ `sessionStorage` (use `localStorage` com prefixo `oimpresso.`)
- ❌ `bg-(gray|indigo|purple|pink|yellow|red|green)-[0-9]+`
- ❌ `font-bold/extrabold/black` em h1-h3
- ❌ Tabs `border-b-2 border-primary` em filter (use pills)
- ❌ Modal/Dialog pra detalhe de lista (use Sheet lateral)
- ❌ `Object.entries(record)` direto em props UltimatePOS forDropdowns (use helper `dropdownEntries()`)
- ❌ Patch em Blade legacy tentando paridade visual com Inertia (sempre dissona — migrar página inteira)

Tests automatizados:
- [CockpitPatternConformanceTest](tests/Feature/Design/CockpitPatternConformanceTest.php) — 16 testes / 117 Pages
- [CockpitTypographyConformanceTest](tests/Feature/Design/CockpitTypographyConformanceTest.php) — 7 testes tipografia
- [SellsIndexPageTest](tests/Feature/Sells/SellsIndexPageTest.php) — pattern Sells canon vivo

---

> **Última atualização:** 2026-05-25 — patch §2/§7/§9/§15 refletindo evolução pós-2026-05-08: ADR UI-0013 Constituição UI v2 (4 camadas — mãe atual em §7) · ADR 0190 primary roxo universal 295 (§9) · ADR 0114 prototipo-ui/PROTOCOL.md loop formalizado (§2) · ADR 0180/0182/0189 PageHeader canon v3 (§7) · pr-ui-judge.yml ligado (§15 gates CI) · PRE-MERGE-UI.md link canônico (§15). Fecha US-_DESIGNSYSTEM-001 (era redundante — este arquivo já existia).
> **2026-05-08:** §16 adicionada (Cockpit Pattern V2 ADR 0110 consolidado consultivo). Pages canon vivas: Sells/Index, Sells/Create, SaleSheet, governance/Dashboard, ProjectMgmt/Board/Index.
> **Próxima revisão sugerida:** quando US-_DESIGNSYSTEM-002 (`/dev/components` Inertia) e US-INFRA-012 (visual-regression strict mode) fecharem.
