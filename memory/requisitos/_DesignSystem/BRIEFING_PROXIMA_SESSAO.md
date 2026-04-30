# Briefing para retomar o Design System Oimpresso — Claude Design

> Cole este arquivo como **primeira mensagem** numa nova sessão Claude Design.
> Depois anexe também: `README.md`, `SKILL.md` e `colors_and_type.css` deste projeto.

---

## O que já foi feito — NÃO recriar

### Arquivos criados
- `README.md` — contexto completo do produto + visual foundations
- `SKILL.md` — skill para Claude Code
- `colors_and_type.css` — todos os tokens CSS (cores, tipo, radius, sombras)
- `MANUAL_CLAUDE_CODE.md` — manual de uso para o time dev
- `BRIEFING_PROXIMA_SESSAO.md` — este arquivo

### 12 cards de preview (Design System tab)
| Grupo | Card |
|-------|------|
| Colors | Primary & Neutrals, Origin Badges, Semantic/Status |
| Type | IBM Plex Sans, IBM Plex Mono |
| Spacing | Radius, Shadows & Tokens |
| Components | Buttons & Inputs, Status Badges, Cards & Viewer Blocks, Sidebar Cockpit, Chat Bubbles & Composer, KPI Cards |

### Arquivos importados do repo
`github.com/wagnerra23/oimpresso.com` branch `main`:
- `resources/css/cockpit.css` — CSS canônico completo (46KB)
- `resources/js/Components/cockpit/` — Sidebar, LinkedApps, Thread, TweaksPanel, shared.ts
- `resources/js/Components/shared/` — PageHeader, DataTable, KpiCard, KpiGrid, StatusBadge, EmptyState, BulkActionBar, PageFilters, ModuleTopNav
- `resources/js/Layouts/AppShell.tsx` + `AppShellV2.tsx`
- `resources/js/Pages/Copiloto/` — Cockpit.tsx, Chat.tsx, Dashboard.tsx
- `memory/requisitos/_DesignSystem/` — SPEC.md, BRIEFING, ARCHITECTURE, CHANGELOG, ADRs UI
- `Modules/PontoWr2/Http/Controllers/DataController.php` — menu real Ponto
- `Modules/Financeiro/Http/Controllers/DataController.php` — menu real Financeiro
- `Modules/Officeimpresso/Http/Controllers/DataController.php` — menu real Officeimpresso
- `app/Services/LegacyMenuAdapter.php` — como o menu é montado

### UI Kit interativo (`ui_kits/cockpit/index.html`)
✅ Sidebar dark 260px com company picker no topo
✅ Toggle Chat↔Menu
✅ Menu **real** do sistema (ordem dos DataControllers):
- Core UltimatePOS: Home · POS · Vendas · Compras · Produtos · Contatos · Relatórios · Configurações
- order 85: Financeiro (com sub-itens reais: Dashboard, Contas a Receber, Contas a Pagar, Caixa, Contas Bancárias, Categorias, Boletos, Conciliação, Relatórios)
- order 88: Ponto WR2 (Dashboard, Espelho, Aprovações, Intercorrências, Banco de Horas, Escalas, Importações, Relatórios, Colaboradores, Configurações)
- Copiloto IA · MemCofre
✅ Superadmin no **user dropdown do rodapé** (não no menu): Módulos, Officeimpresso, Backup, CMS, Connector
✅ Clicar em item → abre module stub com nome correto
✅ Aba Chat: thread de mensagens + composer + linked apps colapsáveis
✅ Aba Tarefas: master/detail + viewers OS/CRM/FIN/PNT
✅ Tweaks panel: Vibe × Densidade × Accent hue
✅ Persistência localStorage `oimpresso.*`

### Progresso: ~85%

**O que falta para 100%:**
- [ ] Telas completas dos módulos (OS, Financeiro, Ponto) — hoje são module stubs
- [ ] Dark mode toggle visível no UI kit
- [ ] Tela de login/guest
- [ ] Slides de apresentação do produto
- [ ] Preview card do DataTable completo

---

## Produto: Oimpresso ERP

**O que é:** ERP para gráficas rápidas, plotters, fachadas, brindes. Baseado em UltimatePOS v6.

**Stack:** Laravel 13.6 + Inertia v3 + React 19 + TypeScript + Tailwind 4 + shadcn/ui + lucide-react

**Cliente principal:** ROTA LIVRE — Larissa, 1280px, opera 8h/dia

---

## Visual Foundations (resumo)

### Tipografia
- **Sans:** IBM Plex Sans (400/500/600/700) — UI principal
- **Mono:** IBM Plex Mono — IDs, timestamps, valores financeiros
- **Base:** 13.5px body

### Cores (oklch)
```
--accent:       oklch(0.58 0.09 220)   /* azul — configurável via Tweaks */
--bg:           oklch(0.985 0.003 90)  /* quase-branco warm */
--sb-bg:        oklch(0.21 0 0)        /* sidebar sempre dark */
--text:         oklch(0.22 0.01 80)

/* Origin badges (5 fixos — NUNCA inventar novo) */
OS  → amber   oklch(0.93 0.07 70)
CRM → blue    oklch(0.92 0.06 220)
FIN → green   oklch(0.93 0.07 145)
PNT → violet  oklch(0.93 0.06 295)
MFG → orange  oklch(0.93 0.05 30)
```

### Layout Cockpit (obrigatório em toda tela operacional)
```
┌──────────────┬────────────────────────┬─────────────────┐
│ Sidebar 260px│   Main column (1fr)    │ Apps Vinc. 320px│
│   (dark)     │                        │   (opcional)    │
└──────────────┴────────────────────────┴─────────────────┘
```

### Regras críticas
- **Sidebar SEMPRE dark** — independente do tema
- **Ícones:** lucide-react exclusivamente (ADR UI-0003)
- **Cores:** sempre via CSS vars — NUNCA hardcoded (ADR R-DS-002)
- **PT-BR** em todo label/copy/comentário
- **localStorage** com prefixo `oimpresso.*`
- **Density:** `--row-h` 26/30/34px conforme Tweaks panel

---

## O que pode ser feito na próxima sessão

Escolha **uma** opção:

### A. Tela de Ordens de Serviço (P0 — módulo principal)
```
Crie o mockup completo da tela de Ordens de Serviço dentro do Cockpit.
Padrão UI-0006: PageHeader + KpiGrid (OS abertas, em produção, entregues hoje, vencendo)
+ PageFilters (estágio, cliente, período) + DataTable (colunas: nº OS, cliente, produto,
estágio badge amber, prazo, valor) + EmptyState.
Apps Vinculados direita: LinkedOs + LinkedClient + LinkedFin.
Atalhos J/K navegar, E abrir OS, N nova OS.
```

### B. Tela Financeiro Dashboard
```
Crie o mockup do Dashboard Financeiro dentro do Cockpit.
KPIs hero: A receber (green) / A pagar (amber/red se vencido) / Saldo banco (blue).
Lista de títulos com StatusBadge (aberto/parcial/quitado/vencido/cancelado).
Filtros: tipo (receber/pagar), status, período, conta bancária.
```

### C. Tela Ponto WR2 — Aprovações
```
Crie o mockup de Aprovações do Ponto WR2.
Master/detail: lista de intercorrências pendentes (esquerda, 360px)
+ viewer direita com: dados do colaborador, marcações do dia (grade 4 colunas),
motivo da intercorrência, textarea justificativa, botões Aprovar/Rejeitar.
Origin badge PNT violet. Atalhos J/K/E/A.
```

### D. Dark mode no UI kit
```
Adicione toggle de dark mode no UI kit existente (ui_kits/cockpit/index.html).
O botão fica na topbar direita. Alterna data-theme="dark" no root.
Persiste em localStorage oimpresso.theme.
```

### E. Slides de apresentação
```
Crie 6 slides de apresentação do Oimpresso ERP.
Visual: IBM Plex Sans, accent blue oklch(0.58 0.09 220), sidebar dark como elemento.
Slides: 1) Capa, 2) Problema (gráficas sem ERP integrado), 3) Solução (Cockpit),
4) Módulos (grid dos 5 origins), 5) Roadmap, 6) CTA.
Formato 1280×720.
```

---

## Mensagem modelo para abrir sessão

```
Estou continuando o Design System do Oimpresso ERP (progresso ~85%).
Anexei: BRIEFING_PROXIMA_SESSAO.md, README.md, colors_and_type.css.

NÃO recriar do zero — o design system já existe.
Quero agora: [escolha A, B, C, D ou E acima]

Padrão obrigatório:
- Layout Cockpit: sidebar 260px dark + main + apps vinculados
- Tipografia: IBM Plex Sans + IBM Plex Mono
- Cores via CSS vars (--accent, --bg, --text, etc.) — nunca hardcoded
- Ícones: lucide-react exclusivamente
- PT-BR em tudo
- Sidebar: company picker no topo, superadmin no user dropdown do rodapé
- Origin badges: OS amber · CRM blue · FIN green · PNT violet · MFG orange
```

---

## Referências rápidas no repositório

`github.com/wagnerra23/oimpresso.com` branch `main`

| O que precisa | Path |
|---------------|------|
| CSS completo Cockpit | `resources/css/cockpit.css` |
| Layout AppShellV2 | `resources/js/Layouts/AppShellV2.tsx` |
| Sidebar React | `resources/js/Components/cockpit/Sidebar.tsx` |
| Thread / Chat | `resources/js/Components/cockpit/Thread.tsx` |
| Linked Apps | `resources/js/Components/cockpit/LinkedApps.tsx` |
| PageHeader | `resources/js/Components/shared/PageHeader.tsx` |
| StatusBadge | `resources/js/Components/shared/StatusBadge.tsx` |
| DataTable | `resources/js/Components/shared/DataTable.tsx` |
| KpiCard / KpiGrid | `resources/js/Components/shared/KpiCard.tsx` |
| Cockpit page | `resources/js/Pages/Copiloto/Cockpit.tsx` |
| Menu Ponto WR2 | `Modules/PontoWr2/Http/Controllers/DataController.php` |
| Menu Financeiro | `Modules/Financeiro/Http/Controllers/DataController.php` |
| Design System SPEC | `memory/requisitos/_DesignSystem/SPEC.md` |
| ADR Cockpit | `memory/requisitos/_DesignSystem/adr/ui/0008-cockpit-layout-mae-do-erp.md` |

---

> **Sessão anterior:** 2026-04-30 — Design System criado do zero, UI kit completo com menu real, 12 cards de preview, manual Claude Code. Progresso: ~85%.
> **Próxima sessão:** escolher opção A-E acima e continuar.
