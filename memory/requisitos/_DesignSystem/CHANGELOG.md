# Changelog · Design System

## [0.5.0] - 2026-05-05 (tarde)

### Added

- **ADR UI-0011**: sidebar single-pane minimalista contextualizada + user menu cascata lateral. Wagner pediu em sessão direta. Documenta toggle Chat/Menu REMOVIDO, items agrupados por scope (OFFICEIMPRESSO/FINANCEIRO/ESTOQUE/RELATÓRIOS/IA/CONFIG), Tarefas+Chat como atalhos primários no topo, user menu cascata estilo Claude Desktop.
- **R-DS-015**: items do shell.menu sempre agrupados por scope visual via `SIDEBAR_GROUPS` lookup table. Items não-mapeados caem em "MAIS" (collapse fechado por default).
- **R-DS-016**: cascade trigger (`▶` no item do user menu) abre subpainel à direita; padrão Claude Desktop / Linear / Notion.
- **`<SidebarShortcuts>`**: Tarefas + Chat como ações primárias no topo da sidebar com badges live (count).
- **`<SidebarGroup>`**: header uppercase mute + chevron + items, colapsável; persistência por `key` em `oimpresso.cockpit.group.<key>.expanded`.
- **Subpainel Aparência funcional**: usa `useTheme()` hook existente; 3 botões (Claro/Escuro/Sistema) com check no ativo, persiste em `users.ui_theme` via POST `/user/preferences/theme`.
- **Rota `/tarefas`**: stub Page Inertia placeholder pra inbox cross-módulo (Fase 4 plano migração ADR 0039).

### Removed

- Componentes `SidebarTabs` e `SidebarChat` deletados (eram parte da v UI-0008 dual-pane).
- Imports lucide unused no Sidebar.tsx limpos: `MessageCircle`, `Hash`, `Bell`, `Cog`, `Inbox`, `Pin`, `Plus` da SidebarChat.

### Changed

- **ADR UI-0008** patched parcialmente: trecho "SidebarTabs (toggle Chat ↔ Menu)" e "SidebarChat" superseded por UI-0011. Estrutura 3-colunas continua válida.
- **AppShellV2** sem state `tab` + sem `<SidebarTabs>`. `LS.TAB` continua existindo no shared.ts mas é ignorado (compat zerado — pode ser removido em ADR futura).

### Débito técnico assumido

- `SIDEBAR_GROUPS` lookup table está hardcoded em `Sidebar.tsx`. Migração planejada pra `LegacyMenuAdapter` (campo `group: string` no `MenuItem`) após validação UX em produção (~2 sprints).
- Subpainel "Disponível" tem 3 placeholders estáticos (Disponível/Ausente/Não perturbe) — backend de status real pendente.

## [0.4.0] - 2026-05-05

### Added

- **UI Kit canônico Cowork 2026-04-27** importado em [`ui_kits/cowork-2026-04-27/`](ui_kits/cowork-2026-04-27/) (14 arquivos: 12 `.jsx` + `styles.css` 90 KB + HTML entry + README). Snapshot do projeto Anthropic Cowork "Oimpresso ERP Comunicação Visual" exportado por Wagner em 2026-04-27. Ratificado como **fonte da verdade visual** em 2026-05-05.
- **ADR UI-0010**: zip Cowork 2026-04-27 é canon visual; **`os-page.jsx` é padrão canônico de tela list+detail**, substituindo parcialmente UI-0006 (template tela operacional) e Pattern Jana (ADR raiz 0011) onde houver conflito visual. ADR documenta tabela de **conflitos resolvidos** (ex.: UI-0009 sidebar light SOBREVIVE — Wagner explícito 2026-05-05 "manter sidebar").
- **R-DS-013**: telas list+detail (Officeimpresso/OS, Repair, Project, Financeiro, Copiloto/Admin/*) seguem `os-page.jsx` como referência visual canônica.
- **R-DS-014**: telas inbox unificada (Pages/Tarefas/Index.tsx, futuras) seguem `tasks.jsx` + `viewers.jsx`.
- **Session 2026-04-28-design-prototype-chat-erp.md** apendida em `memory/sessions/` (estava em `memory-para-github/sessions/` do zip — sinal que era pra entrar no repo e nunca entrou).

### Changed

- **ADR UI-0006** (padrão tela operacional) — agora **substituído parcialmente por UI-0010** quando o conflito for visual. Continua válido pra estrutura de módulo (DataController hooks, modules_statuses.json) que UI-0010 não toca.
- **DESIGN.md §1** apontando explicitamente pro UI Kit + ADR UI-0010 como referência visual antes de qualquer portagem.

## [0.3.1] - 2026-05-04

### Changed

- **Sidebar do Cockpit** segue agora `data-theme` do usuário (light por padrão, dark elegante azul-cinza profundo) — antes era dark fixo. Formalizado em [ADR UI-0009](adr/ui/0009-cockpit-sidebar-light-padrao.md). Tokens `--sb-*` em `resources/css/cockpit.css` agora têm variante em ambos temas; hardcodes pretos substituídos por tokens auxiliares (`--sb-bg-2`, `--sb-scroll`, `--sb-bullet-out`).
- **ADR UI-0008** patchado: trecho "Sidebar 260px, dark fixo na vibe workspace" agora aponta pra UI-0009. Substituição parcial (estrutura do Cockpit segue válida).
- **BRIEFING_CLAUDE_DESIGN.md §2 §6** atualizados pra refletir sidebar segue tema.

### Removed

- **`resources/js/Layouts/AppShell.tsx`** (legado AdminLTE-like) — removido. Já estava órfão (zero imports). Todas as 78 páginas Inertia agora usam `AppShellV2` (Cockpit) — shell único do ERP. Refs JSDoc em `Types/index.ts`, `Hooks/usePageProps.ts`, `Components/shared/ModuleTopNav.tsx`, `Pages/ConsultaOs/Index.tsx` atualizadas pra mencionar AppShellV2.

## [0.3.0] - 2026-04-27

### Added

- **Cockpit é o layout-mãe canônico do ERP** (ADR UI-0008): sidebar dual Chat↔Menu (260px) + main contextual (1fr) + Apps Vinculados (320px). Implementado em `Pages/Copiloto/Cockpit.tsx`, CSS escopado em `resources/css/cockpit.css`. Em produção `https://oimpresso.com/copiloto/cockpit`.
- **R-DS-009**: telas core do ERP nascem dentro do Cockpit (AppShellV2).
- **R-DS-010**: Apps Vinculados renderizam blocos por módulo na coluna direita quando há entidade em foco.
- **R-DS-011**: origin badges com 5 cores semânticas (OS amber, CRM blue, FIN green, PNT violet, MFG orange).
- **R-DS-012**: persistência de UI em `localStorage` com namespace `oimpresso.cockpit.*`.
- **CompanyPicker** funcional no topo da sidebar — lista businesses do user (todas se superadmin, current senão), avatar com gradiente determinístico, "+ Adicionar empresa" no footer.
- **Aba Menu real** carregando `shell.menu` do `LegacyMenuAdapter` (mesma fonte do AppShell legado). 33 itens espelhados.
- **Rodapé com superadmin items separados** (Backup, Módulos, CMS, Office Impresso, Superadmin) acima do user dropdown rico (perfil/disponível/aparência/atalhos/ajuda/sair).
- **Tweaks panel** flutuante (FAB bottom-right): Vibe (workspace/daylight/focus) · Densidade (Skim↔Briefing 0-100%) · Accent hue (0-360°). Repintura em runtime via CSS vars `oklch()`.
- **LinkedApps** completos: 5 cards colapsáveis com origin badge — OS, Cliente (CRM), Financeiro, Anexos, Histórico (timeline).
- **Thread polish**: header com avatar+dot online+actions, context bar (OS pill + cliente + estágio + prazo), bolhas com author label + grouping continued + ✓✓ vs ✓, typing indicator (3 dots animados), composer auto-grow.

### Deprecated

- **ADR raiz 0008** (sidebar 1-item + tabs horizontais) — `superseded by ADR raiz 0039 + UI-0008`. Era pro Ponto isolado dentro do AppShell legado; agora todo o ERP vive dentro do Cockpit.
- **ADR UI-0007** (topbar desktop removida) — parcialmente deprecada. Continua válida pro AppShell legado (telas standalone). No Cockpit, topbar volta com função real (breadcrumb dinâmico + ações contextuais).
- **Auto-memória `project_sidebar_groups_2026_04_27`** — superseded pela posição superadmin no rodapé do Cockpit. Permissões Spatie permanecem, mas localização visual mudou.

### Changed

- **ADR UI-0006** (padrão tela operacional) — escopo redefinido: continua canônico pro **conteúdo** da main column (`PageHeader+KpiGrid+PageFilters+Card(Table)`), mas o **envelope** migra de `<AppShell>` para `<AppShellV2>` (Cockpit).
- **AppShell legado** rebaixado a "shell secundário" — mantido só pra telas administrativas isoladas (Showcase, Modulos manage). Cockpit é o default pra qualquer tela operacional.

### Notes

- Branch `feat/copiloto-cockpit-piloto` em produção como teste do padrão. PR pendente pra mergear no `main` quando Wagner aprovar.
- Backend ainda mock pra `conversas`/`mensagens` no Cockpit. Plug do chat real do Copiloto = Fase 3 do plano de migração (ver ADR UI-0008).
- Heurística de "superadmin label" hardcoded por enquanto (set + regex). TODO Fase 5: virar flag `is_superadmin` no `MenuItem` do `LegacyMenuAdapter`.

## [0.2.0] - 2026-04-24

### Added

- **Camada de componentes de produto em `Components/shared/`** (ADR UI-0005):
  - `PageHeader`, `KpiCard` (+ onClick/selected), `KpiGrid`, `StatusBadge` (6 domínios), `PageFilters` + `FilterChip`, `EmptyState` (4 variants), `BulkActionBar`.
  - Showcase em `/showcase/components` (superadmin) com todos os componentes em estados típicos.
  - ~48 kB gzipped de código reutilizável cobrindo ~80% do padrão visual das telas operacionais.
- **Padrão de tela operacional formalizado** (ADR UI-0006): esqueleto `PageHeader → KpiGrid → PageFilters → Card(Table/EmptyState) → BulkActionBar → Dialogs` pra todas as listagens filtradas. Exceções documentadas (Espelho/Show canvas, Chat, Memoria, formulários).
- **Regra R-DS-008** (SPEC): toda tela de listagem operacional nova deve usar o template da ADR 0006.

### Changed

- **Topbar desktop removida** (ADR UI-0007). `<header>` do AppShell passou a ser `md:hidden` — só mobile tem topbar (precisa do hamburger). Desktop economiza 48px de altura; breadcrumb vira primeira linha após ModuleTopNav.
- **Prova de conceito**: `Ponto/Aprovacoes/Index` refatorada usando 6 dos 7 componentes shared + adicionada nova feature (bulk approve) que o backend já suportava mas a UI não expunha. Commit `22d0fdc5`.

### Notes

- O ganho em linhas de código é cumulativo — primeira tela refatorada quase empata (480 → 568 com bulk approve novo), mas a próxima (`Intercorrencias/Index` de 206 linhas) deve cair pra ~120 sem perder nada, porque não precisa redefinir `estadoConfig`/`prioridadeConfig`/empty state/filter chips.

## [0.1.0] - 2026-04-22

### Added

- Módulo virtual `_DesignSystem/` criado como piloto de pasta cross-cutting (ADR 0007 do MemCofre).
- README + ARCHITECTURE + SPEC + CHANGELOG + GLOSSARY + adr/ui/ com 4 ADRs iniciais.
- 7 regras globais (R-DS-001 a 007): primitivas shadcn, tokens semânticos, lucide, espaçamento 4px, dark mode, focus visível, sem CSS custom sem ADR.
