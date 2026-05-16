---
module: Infra
status: meta-módulo governança (loop META → SINAL → DESVIO → RECÁLCULO — ADR 0105)
piloto: N/A (loop interno plataforma)
last_review: 2026-05-16
owner: wagner
parent_adr: 0105
related_adrs: [0105, 0106, 0153, 0154, 0155, 0156]
nota_atual_v2: "~50/100 (injusto — D5+D4.b+D6.a penalizados)"
nota_esperada_v3: "~80-85/100 pós-PR3 na_justified declarado"
---

# BRIEFING — Infra (loop de governança fechado)

> **1-pager executivo** · Atualizado: 2026-05-16 (pós-PR3 governance-v3-docs `na_justified` declarado)
> Canon: [SPEC.md](SPEC.md) · ADR mãe: [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) · Rubrica v3: [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) + [0156](../../decisions/0156-rubrica-v3-pesos-redistribuidos.md)

## TL;DR

**Meta-módulo** que orquestra o loop de governança da plataforma (META → TRACKING → SINAL → DESVIO → RECÁLCULO → HITL → EXECUÇÃO → MEDIÇÃO). NÃO é módulo de features cliente — concentra runbooks operacionais (deploy Centrifugo, Hostinger, CT 100, GrowthBook) + SPECs de infra (US-INFRA-001+ → GrowthBook self-hosted, Client Signal, APM full-stack). US-INFRA-* tipicamente viram features em outros módulos (Governance/Brief/Admin).

## Capacidade core

- **Runbooks operacionais** — `RUNBOOK-acesso-ct100.md`, `RUNBOOK-criar-modulo.md`, `RUNBOOK-growthbook-deploy.md` etc
- **GrowthBook self-hosted** (US-INFRA-001, p0) — feature flag system scoped por business no CT 100
- **Client Signal canônico** (US-INFRA-002, p1) — entidade `mcp_client_signals` + URL `/feedback?biz=X&token=Y` formaliza ADR 0105
- **APM full-stack** (US-INFRA-003) — captura "lento aqui" automaticamente
- **Separação runtime Tier 0** — Hostinger ≠ CT 100 ([ADR 0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md)) IRREVOGÁVEL

## Cliente piloto

**N/A por design.** Loop de governança serve o projeto inteiro — não há cliente externo consumindo. Consumidores são módulos `Modules/*` que herdam runbooks/flags/signal infrastructure.

## Score module-grade

| Versão | Score | Observação |
|---|---|---|
| v2 (pré-PR3) | ~50/100 | Penalizava D5 (sem cliente externo), D4.b (sem FSM), D6.a (sem Controllers Inertia::render) — injusto pra meta-módulo |
| **v3 (pós-PR3)** | **~80-85/100** (esperado) | `na_justified` D5+D4.b+D6.a declarado no SPEC → rubrica v3 redistribui peso (ADR 0156) |

**`na_justified` declarado no SPEC:**
- **D5 (cliente externo):** loop de governança da plataforma — biz=4 ROTA LIVRE não consome GrowthBook/APM/MCP diretamente, são fundações.
- **D4.b (FSM canônica):** sem Eloquent Models com transições — concentra runbooks + ADRs operacionais.
- **D6.a (Inertia::defer):** sem Controllers `Inertia::render` próprios — US-INFRA-* materializam em outros módulos.

## Gaps remanescentes

- 🔴 US-INFRA-001 GrowthBook self-hosted ainda **todo** (p0, 1.5h IA-pair)
- 🟡 US-INFRA-002 Client Signal canônico **todo** (p1, depende US-INFRA-001)
- 🟡 US-INFRA-003 APM full-stack — escolher Sentry vs GlitchTip self-hosted
- 🟢 Runbooks `RUNBOOK-acesso-ct100.md` + `RUNBOOK-criar-modulo.md` validados em produção (Modules/ADS, Modules/ConsultaOs)

## Próximo passo sugerido

1. Wagner desbloquear deploy GrowthBook CT 100 (Tier 0 — só Wagner faz Docker compose lá)
2. US-INFRA-001 → feature flag pra `useV2SellsCreate` migrar do `pos_settings` JSON
3. Encadeia US-INFRA-002 (Client Signal) → ADS S5 antecipado (~30/maio) consome

## ADRs centrais

- [0105](../../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md) Cliente como sinal qualificado (mãe)
- [0106](../../decisions/0106-recalibracao-velocidade-fator-10x-ia-pair.md) Recalibração estimates IA-pair
- [0062](../../decisions/0062-separacao-runtime-hostinger-ct100.md) Hostinger ≠ CT 100 IRREVOGÁVEL
- [0155](../../decisions/0155-module-grade-v3-anti-injustica-na-justified.md) Rubrica v3 anti-injustiça
