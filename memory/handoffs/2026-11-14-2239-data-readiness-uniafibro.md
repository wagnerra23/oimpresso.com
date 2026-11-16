---
date: "2026-11-14"
time: "22:39"
slug: data-readiness-uniafibro
tldr: "RUNBOOK de entrada da Uniafibro em produção pronto em rascunho + seed das associações-membro empilhado na onda 2. Nada executado: falta [W] responder 7 perguntas, ratificar a ADR 0451 e fazer o backup antes do merge da onda 2."
cycle: null
related_adrs:
  - 0451-associacoes-rede-uniafibro-area-de-membros
  - 0093-multi-tenant-isolation-tier-0
next_steps:
  - "[W] responde RUNBOOK-data-readiness-uniafibro §1"
  - "[W] ratifica ADR 0451 (merge onda 1)"
  - "Agente roda migrate --pretend no CT 100 nas ondas 2 e 6"
  - "[W] backup só-banco pelo Actions antes do merge da onda 2 (o deploy migra sozinho e não faz dump)"
---

# Data readiness da Uniafibro

Mapa completo no [RUNBOOK](../requisitos/Infra/RUNBOOK-data-readiness-uniafibro.md) e na
[sessão](../sessions/2026-11-14-2239-data-readiness-uniafibro.md).

- Migrations: só 2, ambas `CREATE` de tabelas novas — onda 2 (#10878) e onda 6 (#10880). Merge na ordem 2→3→4→5→6.
- Seed: `associacoes:semear-rede --business=<id> [--aplicar]` (AGFESC, AFIBROMAR), branch `claude/associacoes-seed-rede-inicial` sobre a onda 2.
- Risco principal: merge da onda 2 = migration em produção sem dump de banco. Backup manual primeiro (RUNBOOK §3).
- Empresa, pacote, instalação e papéis: [W] pelo superadmin.

## Estado MCP no momento do fechamento

`brief-fetch` (brief #864): sem cycle ativo, 4 HITL pendentes de [W], nenhum incidente.
`decisions-fetch 0451` não encontra a ADR (ainda só na branch da onda 1). Nenhuma task MCP criada.
