# Postmortem — Sprint 1 Daily Brief

> **Status:** 🔴 TEMPLATE — preencher após 7 dias de soak em prod.
> **Quando preencher:** quando `mcp_briefs.generated_at` tem ≥48 entries (8 dias × 6/dia = 48 mínimo).

---

## Frontmatter

```yaml
---
sprint_id: S1
title: Daily Brief (camada L7)
status: in-soak  # → completed após 7 dias OK
soak_start: 2026-05-XX  # data do PR #109 merge
soak_end: 2026-05-XX  # +7 dias
authored_by: wagner
co_authored_by: sonnet
verdict: ?  # success | partial | failed
---
```

---

## 1. Métricas medidas (queries SQL prontas)

> Rodar todas no MySQL Hostinger via SSH tunnel ou Telescope.

### 1.1. Brief uptime

```sql
-- Quantos briefs gerados nas últimas 168h (7 dias × 24h)?
-- Esperado: ≥168 × 6/24 = 42 briefs/dia × 7 = 294 briefs (ideal)
SELECT
  COUNT(*) AS briefs_gerados,
  MIN(generated_at) AS primeiro,
  MAX(generated_at) AS ultimo,
  AVG(tokens_used) AS tokens_medio,
  SUM(tokens_used) AS tokens_total
FROM mcp_briefs
WHERE generated_at > NOW() - INTERVAL 7 DAY;
```

**Alvo:** ≥210 briefs (5/dia mínimo, considerando alguma margem); tokens_medio ~3000.

### 1.2. Adoção `brief-first` skill

```sql
-- Quantas sessões começaram com chamada brief-fetch?
SELECT
  COUNT(DISTINCT session_id) AS sessoes_total,
  SUM(CASE WHEN skill_name = 'brief-first' AND fired_first = 1 THEN 1 ELSE 0 END) AS sessoes_com_brief_first,
  SUM(CASE WHEN skill_name = 'brief-first' AND fired_first = 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT session_id) * 100 AS pct_adocao
FROM mcp_skill_telemetry
WHERE fired_at > NOW() - INTERVAL 7 DAY;
```

**Alvo:** ≥90% das sessões.

### 1.3. Custo Brain B (gpt-4o-mini)

```sql
-- Custo total semana
SELECT
  SUM(tokens_used * 0.15 / 1e6) AS custo_input_usd,
  -- gpt-4o-mini: $0.15/1M input
  -- Output não medido separado; assumir ~50% do total
  SUM(tokens_used) AS tokens_total,
  COUNT(*) AS chamadas
FROM mcp_briefs
WHERE generated_at > NOW() - INTERVAL 7 DAY;
```

**Alvo:** ≤$3.50/semana ($0.50/dia).

### 1.4. Diversidade de agents consumindo

```sql
-- Quantos agents distintos chamaram brief-fetch?
SELECT
  COUNT(DISTINCT agent_id) AS agents_distintos,
  COUNT(DISTINCT user_id) AS usuarios_distintos
FROM mcp_audit_log
WHERE tool_name = 'brief-fetch'
  AND called_at > NOW() - INTERVAL 7 DAY;
```

**Alvo:** ≥6 agents distintos (Wagner + Felipe + Maiara + Luiz + Eliana + Opus pelo menos).

### 1.5. Token médio onboarding (delta vs baseline pré-S1)

```sql
-- Tokens consumidos antes da primeira ferramenta produtiva (não-orientação)
-- Comparar com baseline registrado em commit pré-S1 (b850e532)

SELECT
  DATE(called_at) AS dia,
  AVG(tokens_used_in_session_before_productive_tool) AS media,
  STDDEV(tokens_used_in_session_before_productive_tool) AS desvio
FROM mcp_session_metrics
WHERE called_at > NOW() - INTERVAL 7 DAY
GROUP BY DATE(called_at)
ORDER BY dia;
```

**Alvo:** -40% vs baseline pré-S1 (esperado ~25k tokens em vez de ~50k).

---

## 2. Resultados (preencher)

| Métrica | Alvo | Real | Veredicto |
|---|---|---|---|
| Brief uptime | ≥99% | ?% | ⏳ |
| Adoção brief-first | ≥90% | ?% | ⏳ |
| Custo Brain B/semana | ≤$3.50 | $? | ⏳ |
| Agents distintos | ≥6 | ? | ⏳ |
| Token onboarding | -40% | -?% | ⏳ |
| Drift do brief | <2 inconsistências/brief | ? | ⏳ |

Veredicto global: ⏳ pendente

---

## 3. Bugs encontrados durante soak

(Preencher conforme aparece)

| # | Sintoma | Diagnóstico | Fix | Commit |
|---|---|---|---|---|
| 1 | (placeholder) | | | |

---

## 4. Surpresas (positivas + negativas)

### Positivas

- (preencher)

### Negativas

- (preencher)

---

## 5. Aprendizados pra próximos sprints

- (preencher após análise)

---

## 6. Decisão de fechamento

- [ ] Sprint 1 oficialmente fechado (tag `s1-completed-YYYY-MM-DD`)
- [ ] Métricas pós-S1 viram baseline pra S3 medir delta
- [ ] Atualizar `ROTEIRO-MESTRE.md §2` (S1 vira ✅ DONE)
- [ ] Avisar Wagner/Felipe/Maiara/Luiz/Eliana de fechamento

---

## Notas

- Se 2+ critérios falharem: NÃO seguir pra S3 antes de investigar
- Se 1 critério falhar mas <20% do alvo: ajustar configuração e re-soak 3 dias
- Custo do postmortem em si: ~$0.05 (Sonnet escreve análise)
