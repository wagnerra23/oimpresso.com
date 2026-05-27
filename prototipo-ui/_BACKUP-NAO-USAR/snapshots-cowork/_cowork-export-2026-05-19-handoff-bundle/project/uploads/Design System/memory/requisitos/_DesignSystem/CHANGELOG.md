# Changelog · Design System

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
