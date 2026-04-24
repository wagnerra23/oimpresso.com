# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [0.3.0] - 2026-04-24

### Added

- **ADR UI-0002**: Dashboard vivo + roadmap de evolução ao estado da arte. Define 3 personas (Colaborador/Gestor/Auditor), 8 capacidades baseline do mercado e 10 evoluções priorizadas em Tiers A/B/C. Status: `proposed` — implementação depois.

### Changed

- **6 telas refatoradas pro template ADR UI-0006** do Design System (padrão de tela operacional):
  - `Aprovacoes/Index` (480 → 568 linhas; +bulk approve novo)
  - `Intercorrencias/Index` (206 → 242)
  - `BancoHoras/Index` (175 → 169)
  - `Colaboradores/Index` (149 → 192)
  - `Escalas/Index` (129 → 149)
  - `Importacoes/Index` (149 → 168)
- Todas usando `PageHeader`, `KpiGrid` + `KpiCard`, `PageFilters` + `FilterChip`, `StatusBadge` (quando aplicável), `EmptyState` (4 variants), `BulkActionBar` (Aprovacoes).
- Linhas por tela subiram em média por conta de EmptyState dual-path + filter chips removíveis + CTAs explícitos. Trade-off por UX consistente e ganho cumulativo (dev novo faz próxima tela em minutos).

### Notes

- Dashboard/Index, Espelho/Index e Espelho/Show **não foram** refatorados nesta sessão. Espelho requer `MonthPicker` (componente ainda não criado). Dashboard será reescrito na Fase Tier A (ADR UI-0002).
- Ver `_DesignSystem/adr/ui/0005-product-components-shared.md` e `0006-padrao-tela-operacional.md` para os padrões usados.

## [0.2.0] - 2026-04-22

### Added

- Rastreabilidade tripla aplicada (ADR DocVault 0005): 3 telas principais declaram stories/regras/ADRs/tests via bloco @docvault no topo do arquivo.
  - Ponto/Espelho/Show.tsx → US-PONT-007, US-PONT-008, R-PONT-001, R-PONT-002
  - Ponto/Intercorrencias/Index.tsx → US-PONT-001, R-PONT-001, R-PONT-004
  - Ponto/Importacoes/Index.tsx → US-PONT-009/010/011, R-PONT-001
- ADR UI-0001: Espelho Show com totalizadores e gráfico dia-a-dia.
- R-PONT-001 marcada como testada em EspelhoShowTest::test_business_isolation.

### Notes

- Cobertura: 6/12 stories com página (50%), 1/6 regra com teste (17%), trace_score = 34%.
- Próximo: completar @docvault em Aprovacoes, BancoHoras, Configuracoes, Escalas, Relatorios, Colaboradores, Dashboard.

## [0.1.0] - 2026-04-22

### Added

- Documentação inicial consolidada a partir do arquivo plano.
- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).
