# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

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
