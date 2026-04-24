# Changelog · Design System

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

- Módulo virtual `_DesignSystem/` criado como piloto de pasta cross-cutting (ADR 0007 do DocVault).
- README + ARCHITECTURE + SPEC + CHANGELOG + GLOSSARY + adr/ui/ com 4 ADRs iniciais.
- 7 regras globais (R-DS-001 a 007): primitivas shadcn, tokens semânticos, lucide, espaçamento 4px, dark mode, focus visível, sem CSS custom sem ADR.
