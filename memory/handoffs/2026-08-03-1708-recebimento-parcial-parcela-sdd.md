---
date: "2026-08-03"
time: "17:08 BRT"
slug: "recebimento-parcial-parcela-sdd"
tldr: "US-FIN-003 ganhou trio SDD gerado por máquina: R$ 300→R$ 120, BaixaService, 7 AC e 6 tasks; sem runtime."
decided_by: [W]
cycle: null
us: ["US-FIN-003"]
next_steps: ["Implementar T-01..T-06 após aprovação da REGRA MESTRE de valor."]
hour: "17:08 BRT"
topic: "Arquitetura SDD do recebimento parcial de parcela"
authors: [C]
prs: []
related_adrs: ["0062-separacao-runtime-hostinger-ct100", "0093-multi-tenant-isolation-tier-0", "0264-governanca-executavel-trio-dominio-e2e", "0306-strangler-spec-anchored-reconstrucao-sdd"]
---

# Handoff — recebimento parcial de parcela de cliente

`feature:init` gerou `requirements.md`, `plan.md` e `tasks.md` para a US-FIN-003. O contrato fixa o SPLIT,
o antes→depois de R$ 300 com recebimento de R$ 120 e separa os documentos criados do runtime proposto.

A arquitetura materializa o `BaixaService` já citado pelo Financeiro, reutiliza request/repository e prevê
título+baixa+caixa na mesma transação, com idempotência e evento after-commit.

`feature-lint --check`: 7 AC, 6 tasks, 0 erros/avisos. Deadlink e diff-check passaram. Nenhum PHP, TS,
banco ou valor em produção mudou. Implementação bloqueada pela aprovação [W] da REGRA MESTRE. Sem commit/PR.
