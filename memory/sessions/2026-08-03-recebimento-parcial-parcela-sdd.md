---
date: "2026-08-03"
hour: "17:08 BRT"
duration: "0.5h"
topic: "Arquitetura SDD do recebimento parcial de parcela"
authors: [C]
prs: []
us: ["US-FIN-003"]
outcomes:
  - "Recebimento parcial ganhou trio controlado por feature:init/feature-lint."
  - "Arquitetura separa documentos gerados do runtime ainda proposto."
  - "Gap da baixa manual sem caixa foi planejado via BaixaService."
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0093-multi-tenant-isolation-tier-0", "0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Sessão — recebimento parcial de parcela

Gerado por máquina o trio da US-FIN-003: exemplo dourado, arquitetura `BaixaService` e seis tasks.
`feature-lint`: 7 AC/6 tasks/0 erros/avisos; deadlink e diff-check passaram. Sem runtime ou Pest local.
