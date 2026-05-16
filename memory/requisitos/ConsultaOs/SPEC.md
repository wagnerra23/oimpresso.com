---
modulo: ConsultaOs
status: mock-only
related_adrs: [0153, 0154]
na_justified:
  D3.b: "ConsultaOs é módulo público de consulta OS (cliente final consulta status via número) — mock-only hoje. Migrar pra real (US-CONSULTA-001) está em backlog. BRIEFING.md prematuro até migração TODO."
---

# SPEC — Modules/ConsultaOs

## Visão

Módulo público (sem auth) para cliente final consultar o status de uma Ordem de Servico (OS) via número de protocolo + telefone/CPF. Hoje opera em **modo mock-only**: 3 Controllers retornam payload fake pra validar layout/UX antes de cabear no Repair real.

## Arquitetura atual (mock-only)

- 3 Controllers (`ConsultaController`, `StatusController`, `LookupController` — mock payload)
- Sem entidades Eloquent próprias (não toca DB ainda)
- 3 tests Pest cobertura básica (Wave B 2026-05-12 — smoke render + 404 + lookup-by-protocol)
- Sem `business_id` scope (consulta pública por protocolo único globally)

## Roadmap (TODO migrar pra real)

- **US-CONSULTA-001** (backlog): substituir mock por query real em `Modules/Repair` via Service read-only, com rate limit por IP + captcha
- **US-CONSULTA-002** (backlog): canary 7d em ROTA LIVRE antes de outros tenants
- **US-CONSULTA-003** (backlog): criar `BRIEFING.md` após migração real (`na_justified D3.b` cai)

## N/A justificado

- **D3.b BRIEFING.md** — prematuro enquanto mock-only. Briefing canônico (1 página executiva) pressupõe capacidade de produto real entregando valor; mock não atende esse critério. Quando US-CONSULTA-001 for done, criar BRIEFING e remover N/A.

## Referências

- ADR 0153 — Module grade rubric v1
- ADR 0154 — Module grade v2 N/A justificado
