---
slug: jana-chat-header-tabs-visual-comparison
title: "Jana/Chat — header sticky com tabs Dashboard | Chat (gate F1.5 escopo Wagner 2026-05-18)"
type: visual-comparison
module: Jana
status: pending_approval
target_pages:
  - resources/js/Pages/Jana/Chat.tsx
  - resources/js/Pages/Jana/Dashboard.tsx
target_charter: resources/js/Pages/Jana/Chat.charter.md
visual_source: prototipo-ui/_cowork-export-2026-05-15/app.jsx (Header function L247-336)
visual_source_companion: prototipo-ui/_cowork-export-2026-05-15/data.jsx (MENU + GROUP_META)
related_adrs: [0094, 0104, 0107, 0110, 0114]
date: 2026-05-18
escopo: header-only (header sticky com tabs Dashboard | Chat, navegação Inertia entre rotas)
aprovacoes:
  - escopo: pending Wagner — sessão 2026-05-18
---

# Chat.tsx + Dashboard.tsx — Visual Comparison F1.5 (header tabs)

> **Gate canônico** ADR 0107 + ADR 0114 antes de Edit/Write em Page. Wagner pediu sessão 2026-05-18: "padrão deve anexar botões action header" + "dashboard e chat" referenciando `app.jsx` Header function do protótipo Cockpit.

## TL;DR

Aplicar **header sticky** acima do conteúdo da área `/jana` espelhando `app.jsx` Header function (linhas 247-336 do protótipo): dot da área + label "JANA" à esquerda + **tabs `Dashboard | Chat`** (navegação Inertia entre `/jana/dashboard` e `/jana`) + actions (search/bell) à direita.

**Escopo cirúrgico:**
- Novo componente compartilhado `JanaAreaHeader.tsx` em `resources/js/Pages/Jana/_components/`
- Plugado em Chat.tsx (acima de `copiloto-chat-layout`) e Dashboard.tsx (acima do conteúdo principal)
- Tabs usam Inertia `<Link>` (router.get) com `active` baseado em `usePage().url`
- Zero mexida em ConvSidePanel (Fase 2 separada já fechada via PR mergeado)
- Zero CSS global novo (Tailwind utilities + classes `cockpit` existentes)

## Comparáveis canônicos

- **`prototipo-ui/_cowork-export-2026-05-15/app.jsx` Header function L247-336** — fonte canônica (Wagner já validou o protótipo Cockpit)
- **Linear "view header"** — referência pra padrão sticky + tabs + actions
- **Stripe Dashboard "section nav"** — referência pra hierarquia visual (label área + tabs subordinadas)

## 15 dimensões (app.jsx Header ↔ proposta JanaAreaHeader ↔ veredito)

| # | Dimensão | app.jsx Header (protótipo) | Proposta JanaAreaHeader (oimpresso) | Veredito |
|---|---|---|---|---|
| 1 | **Layout container** | `<header className="topbar">` flex 3-cols | `<header className="ja-area-h">` flex 3-cols (left/center/right) | ✅ paridade estrutural |
| 2 | **Left — area dot** | `<span className="topbar-dot" style={{background: oklch(0.62 0.13 ${hue})}}>` hue=220 (IA group) | `<span className="ja-area-dot">` mesma cor (SIDEBAR_GROUP_HUE['ia']=220) | ✅ paridade |
| 3 | **Left — area label** | `<span className="topbar-area-label">JANA</span>` uppercase 13px | `<span className="ja-area-label">JANA</span>` mesma type | ✅ paridade |
| 4 | **Center — tabs container** | `<nav className="topbar-tabs">` | `<nav className="ja-area-tabs">` | ✅ paridade |
| 5 | **Center — tab buttons** | `<button>` com `topbar-tab--chat` + active style inline (borderBottomColor + color) | Substitui `<button>` por **Inertia `<Link href>`** — mudança canônica vs protótipo (router-driven) | 🟡 adapt (HTML link semantic) |
| 6 | **Tab "Dashboard"** | `{key:"dashboard", label:"Dashboard", icon:"📊"}` | `{href:"/jana/dashboard", label:"Dashboard", Icon: LayoutDashboard}` — emoji 📊 → lucide `LayoutDashboard` (charter §UX Anti-patterns: "Avatar circular emoji-style") | 🟡 lucide replace emoji |
| 7 | **Tab "Chat"** | `{key:"ia", label:"Analista IA", icon:"🤖"}` | `{href:"/jana", label:"Chat", Icon: MessageSquare}` — Wagner explicitamente disse "dashboard e chat" (não "Analista IA"). Ícone lucide MessageSquare | 🟡 adapt label + lucide |
| 8 | **Active state** | inline style `{borderBottomColor: t.color, color: t.color}` | `data-active` attribute + Tailwind utilities `border-b-2 border-primary text-primary` | ✅ token-driven |
| 9 | **Right — tenant pill** | `<span className="topbar-tenant">` initials | **Omitir** — CompanyPicker já vive no SidebarTop (não duplicar) | ✅ no-dup |
| 10 | **Right — search button** | `<button className="icon-btn"><I.search/></button>` | Omitir — `/` shortcut do composer já existe (Charter §UX Targets) | ❌ defer (charter) |
| 11 | **Right — bell button** | `<button className="icon-btn"><I.bell/></button>` | Omitir — notificações vivem no shortcut topo "Tarefas" (Sidebar) | ❌ defer (charter) |
| 12 | **Sticky behavior** | sem sticky no protótipo (header rola com page) | `sticky top-0 z-10 backdrop-blur bg-card/95` — Charter §UX Targets pede manter referência visual durante scroll thread | 🟡 enhance vs protótipo |
| 13 | **Bordas** | borderless | `border-b border-border` divisória sutil sob header | 🟡 enhance |
| 14 | **Tipografia** | 13px tab label + 13px area label | mesma type (Cockpit V2 canon ADR 0110) | ✅ paridade |
| 15 | **CSS scope** | global topbar* classes | escopo `.cockpit` + Tailwind utilities + classes `ja-area-*` novas (sem global) — ADR 0110 | ✅ ADR 0110 |

## Delta concreto (diff plan)

**Arquivos novos (1):**
- `resources/js/Pages/Jana/_components/JanaAreaHeader.tsx` (~70 linhas — componente compartilhado)

**Arquivos tocados (2):**
- `resources/js/Pages/Jana/Chat.tsx` — adiciona `<JanaAreaHeader active="chat" />` acima de `copiloto-chat-layout` (~3 linhas)
- `resources/js/Pages/Jana/Dashboard.tsx` — adiciona `<JanaAreaHeader active="dashboard" />` acima do conteúdo (~3 linhas)

**Arquivos charter (1):**
- `resources/js/Pages/Jana/Chat.charter.md` — adicionar Goal "Header sticky com tabs Dashboard | Chat (navegação Inertia)" + atualizar `last_validated`
- `resources/js/Pages/Jana/Dashboard.charter.md` — mesmo Goal (charter compartilhado)

**Pseudo-código JanaAreaHeader:**

```tsx
// JanaAreaHeader — header sticky compartilhado Chat + Dashboard
// Espelha app.jsx Header function (linhas 247-336 do protótipo Cockpit)
import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, MessageSquare } from 'lucide-react';

const TABS = [
  { key: 'dashboard', href: '/jana/dashboard', label: 'Dashboard', Icon: LayoutDashboard },
  { key: 'chat',      href: '/jana',           label: 'Chat',      Icon: MessageSquare },
] as const;

export type JanaAreaTab = typeof TABS[number]['key'];

export function JanaAreaHeader({ active }: { active: JanaAreaTab }) {
  return (
    <header className="ja-area-h sticky top-0 z-10 backdrop-blur bg-card/95 border-b border-border">
      <div className="ja-area-l">
        <span className="ja-area-dot" />  {/* oklch(0.62 0.13 220) — IA hue */}
        <span className="ja-area-label">JANA</span>
      </div>
      <nav className="ja-area-tabs" aria-label="Modo do Jana">
        {TABS.map((t) => (
          <Link
            key={t.key}
            href={t.href}
            className="ja-area-tab"
            data-active={active === t.key}
          >
            <t.Icon size={13} />
            <span>{t.label}</span>
          </Link>
        ))}
      </nav>
      <div className="ja-area-r" />  {/* placeholder direita — sem actions hoje */}
    </header>
  );
}
```

## Multi-tenant / charter compliance

- ✅ **Charter Goal compatível**: "header sticky com tabs Dashboard | Chat" não conflita com nenhum Non-Goal existente (não é modal, não é avatar circular, não é cor crua, não é rounded-2xl)
- ✅ **Multi-tenant Tier 0**: navegação Inertia herda `business_id` global scope dos Controllers (`ChatController@index`, `DashboardController@index`)
- ✅ **ADR 0110 Cockpit V2**: header sticky + actions à direita é canon do pattern (Wagner já usa em outros módulos)
- ✅ **ADR 0107 visual gate F3**: esse próprio arquivo cumpre o gate
- ✅ **PT-BR**: "Dashboard" e "Chat" — labels do próprio Wagner

## O que NÃO entra (Non-Goals desta fase)

- ❌ Search button à direita (Charter §UX Targets já cobre via `/` no composer)
- ❌ Bell button à direita (cobre via Tarefas shortcut Sidebar)
- ❌ Mudança em ConvSidePanel (Fase 2 já mergeada PR #1053)
- ❌ Mudança em JanaAssistantUiChat / Brain B / thread render (Charter Tier A)
- ❌ Novo CSS global (só Tailwind utilities + classes `ja-area-*` escopadas)
- ❌ Tab "Analista IA" (Wagner explicitamente disse "Chat" — substitui label do protótipo)
- ❌ Active state via inline style (canon = Tailwind utility + data-active attribute)

## Pest GUARDS sugeridos (escrever após Edit)

```php
// Modules/Jana/Tests/Charters/JanaAreaHeaderCharterTest.php
it('renders header on /jana with active="chat"')
it('renders header on /jana/dashboard with active="dashboard"')
it('uses Inertia Link (not raw <a>) for navigation between tabs')
it('preserves AppShellV2 sidebar/topnav (no layout regression)')
it('does not show search/bell buttons (charter Non-Goal)')
```

## Aprovação

- [ ] **Wagner aprova screenshot** OU aprovação implícita via referência canônica (`app.jsx` Header function — protótipo Cockpit Wagner já validou em PR #295)
- [ ] Approver: Wagner
- [ ] Data:
- [ ] Sessão: 2026-05-18 (`thirsty-robinson-aa9337`)

## Refs

- [Chat.charter.md](../../../resources/js/Pages/Jana/Chat.charter.md) — Goals + Non-Goals
- [Dashboard.charter.md](../../../resources/js/Pages/Jana/Dashboard.charter.md)
- [prototipo-ui/_cowork-export-2026-05-15/app.jsx#L247-336](../../../prototipo-ui/_cowork-export-2026-05-15/app.jsx) — Header function canon
- [ADR 0107](../../decisions/0107-emendation-0104-visual-comparison-gate-f3.md)
- [ADR 0110](../../decisions/0110-cockpit-pattern-v2-canon-list-detail.md)
- [ADR 0114](../../decisions/0114-prototipo-ui-cowork-loop-formalizado.md)
- PR #1053 — Fase 1+2 sidebar reordenada (mergeado)
