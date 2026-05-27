# Oimpresso ERP — Design System

> Sistema de design do **Oimpresso ERP**, plataforma vertical para gráficas e comunicação visual (plotters, fachadas, brindes). Baseado em UltimatePOS v6 com módulos próprios.

---

## Contexto do Produto

**Oimpresso ERP** é um sistema de gestão para o setor gráfico brasileiro. Stack: Laravel 13.6 + Inertia v3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui + lucide-react.

**Cliente principal real:** ROTA LIVRE (Larissa, monitor 1280px, gráfica de bairro, opera 8h/dia).

**Fontes deste design system:**
- Repositório: `github.com/wagnerra23/oimpresso.com` (branch `main`)
- Protótipo de referência: projeto "Oimpresso ERP Comunicação Visual" (Claude Design, conta WR2)
- CSS canônico: `resources/css/cockpit.css`
- ADR principal de UI: `memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md`

---

## Layout-mãe: Cockpit (ADR UI-0008)

Toda tela operacional do ERP vive dentro do **Cockpit** — 3 colunas fixas:

```
┌──────────────┬────────────────────────┬─────────────────┐
│ Sidebar 260px│   Main column (1fr)    │ Apps Vinc. 320px│
│   (dark)     │                        │   (opcional)    │
└──────────────┴────────────────────────┴─────────────────┘
```

- **Sidebar (260px, sempre dark):** toggle Chat↔Menu + company picker + user footer
- **Main (1fr):** conteúdo do módulo ativo
- **Apps Vinculados (320px):** blocos colapsáveis por módulo (OS, Cliente, FIN, Ponto, Anexos, Histórico)

---

## CONTENT FUNDAMENTALS

**Idioma:** PT-BR em todo label, copy, comentário, commit. Código (classes, métodos) em inglês.

**Tom:** direto, funcional, sem floreio. Fala com operador de gráfica que usa o sistema 8h/dia.

**Voz:** "Aprovações pendentes" (não "Suas aprovações estão pendentes"). Substantivos, não verbos desnecessários.

**Casing:** Sentence case em títulos e botões. CAPS apenas em labels de seção (ex: `FIXADAS`, `RECENTES`).

**Emoji:** Não usado na UI operacional. Apenas internamente em código de exemplo.

**Números:** sempre `font-variant-numeric: tabular-nums` em tabelas e timestamps.

**Datas/horas:** formato brasileiro — `dd/mm/yyyy`, `HH:MM`.

---

## VISUAL FOUNDATIONS

### Tipografia
- **Sans:** IBM Plex Sans (weights: 400, 500, 600, 700) — UI principal
- **Mono:** IBM Plex Mono (weights: 400, 500, 600) — IDs, timestamps, valores numéricos, código
- **Base size:** 13.5px no body; escala: 10px (label-section) → 11px → 11.5px → 12px → 13px → 13.5px → 14px → 15px → 16px (h2) → 22px (h1)
- **Substituto Google Fonts:** IBM Plex Sans + IBM Plex Mono (disponíveis via CDN)

### Cores
Todas as cores são definidas em `oklch()` para consistência perceptual entre modos.

**Accent:** `oklch(0.58 0.09 220)` — azul acinzentado (hue configurável via Tweaks, padrão 220°)

**Origin badges (5 módulos fixos):**
| Módulo | Cor | oklch bg |
|--------|-----|----------|
| OS | amber | `oklch(0.93 0.07 70)` |
| CRM | blue | `oklch(0.92 0.06 220)` |
| FIN | green | `oklch(0.93 0.07 145)` |
| PNT | violet | `oklch(0.93 0.06 295)` |
| MFG | orange | `oklch(0.93 0.05 30)` |

### Backgrounds e Superfícies
- **Light:** bg quase-branco com toque de warmth (`oklch(0.985 0.003 90)`)
- **Dark:** azul-acinzentado profundo (`oklch(0.16 0.003 240)`)
- **Sidebar:** sempre dark independente do tema principal (`oklch(0.21 0 0)`)
- Sem gradientes de fundo nas telas operacionais — superfícies planas
- Cards: `background: var(--surface)` + `border: 1px solid var(--border)` + `border-radius: 8px`

### Sombras
- **`--shadow-soft`:** `0 1px 2px rgba(0,0,0,.04)` — cards em repouso
- **`--shadow-pop`:** `0 6px 24px -8px rgba(0,0,0,.18), 0 2px 6px -2px rgba(0,0,0,.10)` — dropdowns, modais

### Border Radius
- `--radius-sm: 6px` — botões, inputs, badges pequenos
- `--radius: 8px` — cards, painéis
- `--radius-lg: 12px` — composer, modais

### Animações
- Transições curtas: `150ms ease` (hover), `200ms ease` (collapse/expand)
- Bounce animation: typing indicator (3 pontos, `1.2s infinite ease-in-out`)
- Sem Framer Motion — apenas CSS transitions e `@keyframes`

### Hover/Press States
- Hover: `background: var(--sb-hover)` na sidebar; `background: var(--border-2)` no main
- Active: `background: var(--sb-active)` na sidebar + indicador vertical 2px em `--accent-2`
- Focus: `box-shadow: 0 0 0 3px var(--accent-soft)` + `border-color: var(--accent)`
- Press: sem shrink; apenas opacidade/cor

### Densidade (Tweaks)
- **Compact:** `--row-h: 26px`
- **Default:** `--row-h: 30px`
- **Comfy:** `--row-h: 34px`
- Controlado via `data-density` no root

### Imagery
- Sem imagens decorativas na UI operacional
- Avatares: gradientes lineares em 6 paletas (`oklch`), iniciais em texto branco
- Ícones: **lucide-react exclusivamente** (stroke, não fill)

---

## ICONOGRAFIA

**Sistema único:** `lucide-react` — sem mistura de outras libs (ADR UI-0003).

**Tamanhos canônicos:**
- `size={12}` — ícones em badges/labels de seção
- `size={14}` — ícones em itens de sidebar, ações secundárias
- `size={16}` — ícones em itens de menu principal
- `size={20}` — ícones em PageHeader
- `size={32}` — ícones em module stubs (empty states grandes)

**Regra:** nunca usar emoji, SVG custom ou unicode como ícone.

---

## Arquivos disponíveis

```
/
├── README.md                          ← este arquivo
├── SKILL.md                           ← skill para Claude Code
├── colors_and_type.css                ← tokens CSS completos
│
├── resources/css/
│   ├── cockpit.css                    ← CSS completo do Cockpit (canônico)
│   └── app.css, base.css, ...        ← outros CSS do repo
│
├── resources/js/
│   ├── Components/cockpit/            ← Sidebar, LinkedApps, Thread, TweaksPanel
│   ├── Components/shared/             ← PageHeader, DataTable, KpiCard, StatusBadge...
│   └── Pages/Copiloto/               ← Cockpit.tsx, Chat.tsx, Dashboard.tsx
│
├── memory/requisitos/_DesignSystem/
│   ├── SPEC.md                        ← Regras R-DS-001 a R-DS-012
│   ├── BRIEFING_CLAUDE_DESIGN.md      ← Briefing completo de migração
│   ├── ARCHITECTURE.md                ← Stack e estrutura de arquivos
│   └── adr/ui/                        ← ADRs de decisões de UI
│
├── preview/                           ← Cards do Design System tab
│   ├── colors-primary.html
│   ├── colors-semantic.html
│   ├── colors-origins.html
│   ├── type-sans.html
│   ├── type-mono.html
│   ├── spacing-tokens.html
│   ├── components-buttons.html
│   ├── components-badges.html
│   ├── components-sidebar.html
│   └── components-cards.html
│
└── ui_kits/cockpit/
    └── index.html                     ← Protótipo interativo completo do Cockpit
```

---

## UI Kits

### Cockpit ERP (`ui_kits/cockpit/index.html`)
Protótipo interativo fiel ao `Oimpresso ERP - Chat.html` do projeto Cowork. Inclui:
- Sidebar dual Chat↔Menu com company picker e user dropdown
- Tela de Chat com thread de mensagens e composer
- Tela de Tarefas (master/detail)
- Module stubs para módulos Blade ainda não migrados
- Apps Vinculados (coluna direita colapsável)
- Tweaks panel: Vibe × Densidade × Accent hue
- Persistência em `localStorage` com prefixo `oimpresso.*`

**Design width:** 1280px | **Componentes:** React 18 via CDN + Babel
