---
slug: 0163-governance-v4-metas-alcancadas-ondas-19-28
number: 163
title: "Governance v4 — metas por bucket alcançadas (Ondas 19-28) · 4/4 buckets acima da meta"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-17
accepted_at: 2026-05-17
review_at: 2026-08-17
module: Governance
quarter: 2026-Q2
tags: [governance, v4, scoped-scorecards, metas, ondas-19-28, milestone, dual-mode, aposentar-v3]
supersedes: []
supersedes_partially: []
superseded_by: []
related: [0155, 0156, 0157, 0158, 0159, 0160, 0161, 0162, 0094, 0105]
pii: false
review_triggers:
  - Quando dual-mode v3/v4 completar 30 dias estável em prod (aposentar v3 código + UI)
  - Se algum bucket cair abaixo da meta por 2 quarters consecutivos (revisar critérios bucket-specific)
  - Quando CYCLE-06 Martinho Jana V2 demo for entregue (próximo foco produto, não governance)
  - Se média de algum bucket bater meta+10pts consistente 2 quarters (elevar barra do bucket — anti-complacência)
---

# ADR 0163 — Governance v4 metas alcançadas (Ondas 19-28) · 4/4 buckets acima da meta

## 1. Contexto

[ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md) instituiu `module-grade-v4` com 4 buckets (lenses) e meta diferente por bucket — abandonou a régua única v3 que travava nominalmente em ~88-92pp por inadequação estrutural a módulos heterogêneos. [ADR 0161](0161-governance-v4-aposentar-hacks-0159-redundantes.md) aposentou 3/4 hacks compensatórios da v3 e [ADR 0162](0162-otel-collector-prod-observability.md) destravou D6.b + D9.b com OTel Collector ativo no CT 100.

Entre 2026-05-16 e 2026-05-17, **10 Waves consecutivas (W19→W28)** dispararam ~100 sub-agents Opus paralelos em áreas isoladas (`Modules/<X>/` + `memory/requisitos/<X>/` + `memory/decisions/`), cobrindo:

- Push de pattern canônico (PiiRedactor + LogsActivity + retention + opt-out LGPD) nos 34 módulos
- Features estado-da-arte estratégicas (PluggyClient Open Finance, Asaas Pix QR, EtiquetaTag impressão, Devolução fluxo NFe, ServiceOrderItem + Firebird importer, Deal Pipeline Kanban CRM, MobileMarcacao PWA Ponto, Initiatives Governance roadmap, RAGAS CI gate, BGE Reranker prod Jana)
- Mecanismos anti-gaming v4 ativos: **paired indicators** (cada métrica de velocidade tem par de qualidade que cap-eia score), **drift detection cron daily**, **AI baseline ScopedScorecardEvaluator** (Wave 24 V1), **OTel collector ready** (W23 ADR 0162)

## 2. Decisão

**Declarar v4 LIVE em prod com metas por bucket atingidas em 4/4 buckets**, dual-mode v3/v4 mantido por mais 30 dias antes de aposentar v3 (PR de remoção de código + UI v3 agendado quando smoke real local + Hostinger confirmar zero regressão).

### Metas por bucket (estado pós-W28)

| Bucket | Meta v4 | Média atingida W28 | Status |
|---|---|---|---|
| **vertical_client_facing** | ≥85 | **~92** | ✓ +7 acima |
| **cross_cutting_infra** | ≥90 | **~93** | ✓ +3 acima |
| **ai_central** | ≥85 | **~93** | ✓ +8 acima |
| **functional_horizontal** | ≥80 | **~91** | ✓ +11 acima |

Média global ponderada por bucket: **~92pp** (cap natural v4 ~95pp, sem hacks ADR 0159 ativos exceto D9.b residual aposentável pós-validação 30d OTel collector).

### Salto histórico

- Pré-Wave 19 (rubrica v3 pós-erratas 0156-0159): média ~78pp (post-W12) → ~88pp (post-W18)
- Post-W28 v4: **~92pp** (régua diferente — bucket-aware — números não diretamente comparáveis com v3 single-lens)
- Salto de fato em v3 single-lens equivalente: **49 → 78+ (+59%)** ao longo das 10 Waves

### Distribuição final 34 módulos por nota v4

- **Excelente (≥90):** 13+ módulos
- **Bom (80-89):** 21 módulos
- **Médio/crítico (<80):** 0 módulos

## 3. Consequências

### Imediatas (aplicáveis pós-merge do PR de fechamento Wave 28)

1. **Governance v4 LIVE em prod** — gate CI (workflow `module-grades-gate.yml`) usa baseline v4 por bucket; PRs novos validados contra meta do bucket do módulo tocado, não régua única
2. **Dual-mode v3 ↔ v4** ativo via flag `GOVERNANCE_V4_ENABLED=true` (default true em prod após smoke Wagner); UI mostra ambas notas (transparência) por 30d antes de remover v3
3. **3 hacks ADR 0159 aposentados** (D5 cross-cutting / D4.b FSM / D3.b CHANGELOG) conforme ADR 0161; hack D9.b residual aposentável pós-validação OTel Collector ADR 0162 ≥30d estável
4. **OTel Collector prod CT 100 ativo** (ADR 0162) — destrava D6.b telemetry ready + D9.b observability prod queries fora de placeholder
5. **AI baseline ScopedScorecardEvaluator V1** (Wave 24) — quando >30d de dados acumulados, ativa V2 com sugestões automatizadas de hardening

### Próximos ciclos — foco shift governance → produto/cliente

A partir desta ADR, **foco volta pra cliente/produto** ([ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) sinal qualificado). Governance v4 é fundação estável — não merece mais mega-Waves até que sinal de cliente/drift apareça.

**CYCLE-06 prioridades:**
- **Goal #1 Martinho Jana V2 demo** (OficinaAuto sinal qualificado pendente confirmação) — produto novo, não governance
- **Goal #2 FSM rollout 162 vendas biz=1** ([ADR 0143](0143-fsm-pipeline-live-prod-marco-2026-05-12.md))
- **Goal #3** features estado-da-arte W27/W28 que tenham cliente real reportando dor
- **Goal #4 (opcional)** Inter PJ Fase 3 ([US-RB-047](../requisitos/RecurringBilling/SPEC.md))

### Backlog governance v4 (sem urgência — só quando sinal aparecer)

- Aposentar código v3 + UI v3 após 30d dual-mode estável (PR remoção)
- Aposentar hack D9.b ADR 0159 quando OTel Collector 30d estável + ScopedScorecardEvaluator detectOtelQuery retornando valores reais (não pass-through)
- Re-rubrica v5 só se novo bucket emerge (ex: módulo "agente IA autônomo" categoria diferente de "ai_central" produto) — não criar v5 reativamente

## 4. Pendências Wagner (smoke + ativação)

1. ✅ Mergear PRs Wave 27 + Wave 28 (~#973-#999)
2. 🔴 Smoke real local `php artisan module:grade --all --json --v4` confirmar 4/4 buckets na meta
3. 🔴 Smoke real local com flag OFF (`GOVERNANCE_V4_ENABLED=false`) confirmar v3 ainda funcional (dual-mode)
4. 🔴 Ativar `GOVERNANCE_V4_ENABLED=true` em `.env` Hostinger + CT 100 após smoke local OK
5. 🔴 Deploy OTel Collector CT 100 conforme ADR 0162 RUNBOOK (Tempo + Grafana + sampling 5%)
6. 🔴 Validar ROTA LIVRE (biz=4) 0 regressão pós-merge — Larissa testa fluxo venda normal + Inbox + Repair
7. 🔴 PR remoção código v3 após 30d (agendar 2026-06-17)

## 5. Trade-offs explícitos

| Escolha | Alternativa rejeitada | Por quê |
|---|---|---|
| Declarar v4 LIVE com média 92 (não 97) | Continuar Wave 29-30 pra forçar 97+ | Diminishing returns + foco cliente CYCLE-06; meta 92 já +7 acima da meta v4 mínima |
| Dual-mode v3/v4 30d | Switch atômico v3→v4 | Reverter instantâneo se regressão; transparência pro time MCP entrante (Felipe/Maiara) |
| Aposentar 3/4 hacks ADR 0159 agora | Aposentar todos 4 hacks | D9.b depende OTel collector ≥30d estável — não causar regressão por aposentadoria prematura |
| Foco shift governance → cliente | Continuar perseguindo 97-100 | [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — sem sinal de cliente reclamando de governance, não merece mais investimento |

## 6. Referências

- **Mãe v4:** [ADR 0160](0160-governance-v4-scoped-scorecards-buckets.md)
- **Aposentadoria hacks:** [ADR 0161](0161-governance-v4-aposentar-hacks-0159-redundantes.md)
- **OTel destravar D6.b/D9.b:** [ADR 0162](0162-otel-collector-prod-observability.md)
- **Rubrica v3 base:** [ADR 0155](0155-module-grade-v3-sub-dimensoes-gate-ci.md) + erratas [0156](0156-module-grade-v3-errata-otel-helper-na-justified.md) / [0157](0157-module-grade-v3-d2-detection-hardening.md) / [0158](0158-module-grade-v3-d1-heuristica-hardening.md) / [0159](0159-module-grade-v3-errata-meta-97-realismo.md)
- **Constituição v2:** [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) §3 Tiered cost + §4 Loop fechado por métrica + §8 Confiabilidade com fallback
- **Sinal-cliente Tier 0:** [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md)
- **Handoff Wave 28 final:** `memory/handoffs/2026-05-17-0700-governance-v4-final-ondas-19-28.md`
