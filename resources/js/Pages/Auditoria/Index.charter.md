---
charter_id: auditoria-index
status: live
last_review: 2026-05-10
owner: Wagner
---

# Charter — `/auditoria` (Index)

## Mission

Dar visibilidade de TODA alteração em registros de negócio com filtros rápidos. Distinguir IA vs humano em 1 clique.

## Goals

1. **Achar ação suspeita em < 30s** — filtros (data, causer, tipo, evento) suficientes pra triar 50+ entries/dia.
2. **Distinguir IA vs humano em 1 clique** — Badge colorida por `causer_kind` (Usuário azul / IA roxo / Sistema cinza / API âmbar).

## Non-goals

- Análise estatística (BI, gráficos, dashboards) — fora do escopo MVP. Se virar demanda, módulo separado.
- Auditoria de leitura de PII — Pilar 3 LGPD (Eliana ainda estuda), não cabe aqui.
- Edição inline de registros — esta tela é leitura. Reverter é a única ação destrutiva, fica em Detail.

## Anti-hooks (NÃO mexer sem justificativa explícita)

- Coluna `causer_kind` Badge — distinção User vs IA é razão de existir do módulo. Remover quebra ROI.
- Filtro `causer_kind=agent` — métrica `pct_ia_actions_reverted_30d` depende disso ser observável.
- Multi-tenant Tier 0 obrigatório (queries scoped por `business_id`) — proibição duríssima ADR 0093.

## Métricas de saúde

- Tempo médio na página antes de drill-down: < 30s (Goal 1)
- % de uso do filtro causer_kind=agent: > 5% (sinal de uso real do recurso IA)
