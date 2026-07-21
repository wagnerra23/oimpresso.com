---
distilled_at: "2026-07-02"
distilled_by: jana:distill-module-truth
module: Repair
status: shared-infra
updated_at: "2026-07-02"
---

# BRIEFING — Repair (verdade destilada)

# Repair — BRIEFING (estado consolidado)

> Última atualização: 2026-06-13

## Estado atual
O módulo "Repair" gerencia ordens de serviço por meio de uma infraestrutura compartilhada entre múltiplas verticais, sendo consumido por módulos como `OficinaAuto`, `ComunicacaoVisual` e `Vestuario`. Atualmente, a integração e testes estão em andamento após a última atualização significativa, focando em auditoria e validação de dados.

## Capacidades
- **JobSheet** como entidade central para gerenciamento de ordens.
- **RepairStatus** personalizável com 5 colunas fixas de progresso: Recepção, Diagnóstico, Aguardando peças, Em execução e Pronto.
- Interface **ProducaoOficina** com funcionalidades drag-and-drop para manipulação visual das ordens.
- Integração com protocolo de automação para faturamento de ordens de serviço ao final do processo.
- Implementação de **FSM Pipeline** que orquestra as transições entre diferentes estágios de uma ordem de serviço.
- Suporte a múltiplas verticais com vocabulário genérico e configurações específicas por negócio.

## Gaps
- Implementação do **Smoke browser MCP** para garantir compatibilidade e performance.
- Extração da camada de serviços em `KanbanProductionService` para maior escalabilidade.
- Desenvolvimento de teste multi-tenant dedicado para o módulo de reparos.
- Migração das vendas legadas para o pipeline FSM para atualização do fluxo de trabalho.

## Última mudança
Na auditoria recente, foram identificados e testados potenciais corrompimentos de dados no SQLite, com foco nas integrações e no mapeamento das telas do projeto, visando garantir a estabilidade e confiabilidade do módulo.

## Proveniência (destilado de)

- audit `requisitos/Repair/CAPTERRA-FICHA.md` — CAPTERRA-FICHA.md
- session `sessions/2026-06-13-audit-sqlite-test-corruptors.md` (2026-06-13) — 2026-06-13-audit-sqlite-test-corruptors.md
- session `sessions/2026-06-13-auditoria-adversarial-sdd-f2b-floor.md` (2026-06-13) — 2026-06-13-auditoria-adversarial-sdd-f2b-floor.md
- session `sessions/2026-06-08-mapa-telas-projeto.md` (2026-06-08) — 2026-06-08-mapa-telas-projeto.md
