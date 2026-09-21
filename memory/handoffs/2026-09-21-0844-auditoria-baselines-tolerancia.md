---
date: "2026-09-21"
time: "08:44 BRT"
slug: auditoria-baselines-tolerancia
tldr: "Baselines de tolerância deixam requireds verdes com dívida conhecida; a auditoria mediu o problema e recomendou migrar cada gate para critério absoluto antes de remover o arquivo de tolerância."
decided_by: [W]
prs: []
next_steps:
  - "Aplicar a primeira catraca Tier 0 e registrar a decisão arquitetural"
related_adrs:
  - 0093-multi-tenant-isolation-tier-0
  - 0208-larastan-baseline-ratchet
  - 0409-zero-baseline-de-tolerancia-conformidade-absoluta
---

# Baselines de tolerância auditados

## TL;DR

A opinião [W] foi confirmada para baselines que grandfatheram dívida: os requireds ficam verdes com milhares de violações conhecidas. O anti-tamper cobre parte, tem escapes e não estava required. Artefatos chamados baseline incluem também snapshots, contratos e fixtures; a migração correta substitui tolerância por regra absoluta e renomeia/preserva provas úteis. Zero baseline operacional alterado. Evidência: [relatório](../audits/2026-09-21-baselines-de-tolerancia.md).

## Estado MCP no momento do fechamento

Tools MCP do projeto indisponíveis nesta sessão; cycle/tasks/sessões/decisões vivas não consultadas nem inferidas. `origin/main` auditado por archive isolado; proteção viva consultada por API GitHub em leitura. Sem commit/push/merge.
