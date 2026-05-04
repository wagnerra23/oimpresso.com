---
name: Cockpit é o layout-mãe canônico do ERP (2026-04-27)
description: AppShellV2 com sidebar dual Chat/Menu + main contextual + Apps Vinculados é o NOVO padrão pra core ERP. AppShell legado fica pra telas administrativas isoladas. ADR UI-0008 + raiz 0039.
type: project
priority: high
originSessionId: ec442035-2633-415e-acb3-07670d407de2
---
**Padrão canônico de layout do ERP em React, vigente desde 2026-04-27.**

## Quando usar Cockpit (`AppShellV2` em `Pages/Copiloto/Cockpit.tsx`)
- Toda tela de chat / conversação
- Inbox de tarefas (cross-módulo)
- Dashboard de módulo (Copiloto, Financeiro, Ponto, OS — re-fazer)
- Listagem operacional CRUD (envelope é Cockpit, **conteúdo** segue UI-0006: PageHeader+KpiGrid+PageFilters+Table)

## Quando usar AppShell legado (mantido só pra)
- `/showcase/components` (design system showcase)
- `/modulos` (gerenciador de módulos)
- Settings standalone do superadmin
- Qualquer tela que NÃO seja fluxo operacional dia-a-dia

## Estrutura do Cockpit
- **Sidebar 260px (dark)**: CompanyPicker + Tabs Chat↔Menu + body + footer (superadmin items + user dropdown rico)
- **Main 1fr**: topbar (breadcrumb + actions) + conteúdo contextual (chat/CRUD/dashboard)
- **Apps Vinculados 320px (opcional)**: cards LBlock colapsáveis por entidade em foco — Os/Cliente/Financeiro/Anexos/Historico
- **Tweaks panel** (FAB bottom-right, modo dev): Vibe (3 modos) / Densidade / Accent hue

## Persistência
Todo estado UI em `localStorage` com prefixo `oimpresso.cockpit.*` (sidebar.tab, chat.tab, linked.collapsed, conv, tweaks.{vibe,density,accentHue,open}). Blocos vinculados em `oimpresso.linked.{os,client,fin,att,hist}.collapsed`.

## Origin badges (5 cores semânticas, NÃO inventar nova)
OS amber · CRM blue · FIN green · PNT violet · MFG orange. Tokens em `--origin-{TIPO}-{bg|fg}`.

## Conhecimento depreciado (não seguir mais)
- ADR raiz 0008 (sidebar 1-item + tabs horizontais) — superseded
- ADR UI-0007 (topbar removida) — só vale pro AppShell legado, no Cockpit topbar volta com função
- Auto-memória `project_sidebar_groups_2026_04_27` — superadmin agora no rodapé, não no topo

## Em produção
`https://oimpresso.com/copiloto/cockpit` (branch `feat/copiloto-cockpit-piloto`). Quando PR mergear no main, vira default.

## Antes de criar/alterar qualquer tela React
1. Ler ADR UI-0008 (canônico) + ADR raiz 0039 (decisão original)
2. Ler CLAUDE.md §10 (instruções operacionais pro agente)
3. Olhar `Pages/Copiloto/Cockpit.tsx` como referência viva
4. Persistir em localStorage com prefixo correto
5. Se for listagem CRUD: conteúdo segue UI-0006 dentro do envelope Cockpit
