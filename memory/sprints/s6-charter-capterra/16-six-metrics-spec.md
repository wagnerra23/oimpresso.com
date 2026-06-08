# 16 — As 6 métricas (M1–M6)

> **Spec formal das 6 métricas de qualidade do Sistema Charter-Capterra.**
> Cada uma tem: definição, query/cálculo, alvo, fonte de dados, status na entrega F4.

---

## M1 — Token /sessão (charter vs sem charter)

**Pergunta:** sessão de IA que toca tela COM charter consome menos tokens que sessão equivalente SEM?

**Cálculo:**
```sql
SELECT
  CASE WHEN charter_present THEN 'with_charter' ELSE 'without_charter' END AS bucket,
  AVG(tokens_input + tokens_output) AS avg_tokens,
  COUNT(*) AS sample_size
FROM mcp_audit_log
WHERE event = 'session.completed'
  AND created_at >= NOW() - INTERVAL 30 DAY
GROUP BY bucket;
```

**Alvo:** `with_charter` ≤ 50% de `without_charter` (-50%)

**Status F4:** ⏸️ requer telemetria histórica `mcp_audit_log.charter_present` — adicionar campo + popular em sessão. Spec aqui, implementação fica em S7.

---

## M2 — Charter Pest GUARD pass rate

**Pergunta:** dos charters Tier A em prod, quantos % passam todos os Pest GUARD tests no CI?

**Cálculo:**
```
charter_count = total *.charter.md em resources/js/Pages/
guard_pass = soma de tests Pest passando referenciados em "Métricas vivas" de cada charter
guard_total = soma de tests Pest referenciados (incluindo skip e fail)

pass_rate = guard_pass / guard_total
```

**Alvo:** ≥ 95% pass rate

**Status F4:** ✅ implementado em `tests/Charter/CharterMetricsTest.php`. Lê output do CI workflow `charter-gate.yml`.

---

## M3 — Charter coverage Tier A

**Pergunta:** dos paths candidatos a Tier A em prod, quantos têm `*.charter.md` ao lado?

**Cálculo:**
```
tier_a_candidates = telas em prod com KPI claro + telemetria + dono identificado
                    (lista canônica em 04-five-charters-prod.md)
charters_present = count(*.charter.md ao lado de Index.tsx Tier A)

coverage = charters_present / tier_a_candidates
```

**Alvo:** ≥ 80% (8/10 telas Tier A)

**Status F4:** ✅ implementado em `tests/Charter/CharterMetricsTest.php` + `php artisan charter:metrics`. Reusa lógica do `charter:audit`.

---

## M4 — Goal drift rate (sessões fora do scope)

**Pergunta:** quantos % das sessões IA tocando uma tela Tier A excedem os Non-Goals declarados no charter?

**Cálculo (heurístico):**
```sql
-- proxy: sessão usa tools/comandos NÃO declarados em "Goals" do charter da tela
SELECT
  COUNT(DISTINCT session_id) FILTER (WHERE drift_detected) /
    NULLIF(COUNT(DISTINCT session_id), 0) AS drift_rate
FROM mcp_audit_log
WHERE event = 'session.completed'
  AND charter_present = TRUE
  AND created_at >= NOW() - INTERVAL 7 DAY;
```

**Alvo:** < 5%

**Status F4:** ⏸️ stub spec aqui. Implementação real depende de:
- Telemetria `drift_detected` em `mcp_audit_log` (heurística que compara tools usados vs Goals declarados)
- Skill `charter-first` com hook que analisa diff vs Non-Goals

Adicionado como TODO em [18-goal-drift-detector.md](18-goal-drift-detector.md). M4 mede 0 enquanto não tiver telemetria.

---

## M5 — Drift detector latency (PR opened → workflow alerta)

**Pergunta:** quanto tempo entre abrir PR que viola charter e GitHub Action `charter-gate.yml` comentar?

**Cálculo:**
```
latency_seconds = workflow_run.completed_at - pull_request.opened_at
```

**Alvo:** p95 < 120s (2 min)

**Fonte:** GitHub Actions API + agregação em `mcp_audit_log` ou local SQLite.

**Status F4:** ✅ workflow já mede via timestamp implícito (PR comment timestamp vs PR open). Métrica derivável diretamente do GH API. Spec aqui; agregador real fica em S7 (precisa cron que pega GH API + grava local).

---

## M6 — Anti-hallucination ratchet (Non-Goals violados em prod)

**Pergunta:** quantas vezes uma tela em prod fez algo declarado como Non-Goal (= alucinação ou drift de implementação)?

**Cálculo (proxy via Pest GUARD):**
```
violations = sum de Pest GUARD tests "it_does_not_X" que falham em prod canary
ratchet_baseline = snapshot inicial em tests/Charter/baseline.json

ratio = current_violations / ratchet_baseline
```

**Alvo:** sempre ≤ baseline (não pode piorar)

**Status F4:** ⏸️ stub. Depende de:
- baseline.json populado (hoje vazio = ainda soft mode)
- Pest GUARD tests escritos pra cada charter Tier A (parcialmente em F1 — JobSheet/Extrato têm 7-8 targets cada)
- Cron `charter:health` rodando 7 dias coletando dados

Quando ramp-up termina, M6 entra automaticamente no dashboard.

---

## Tabela resumo

| # | Métrica | Alvo | Status F4 | Bloqueador |
|---|---|---|---|---|
| M1 | Token /sessão | -50% | ⏸️ stub | Telemetria mcp_audit_log |
| M2 | GUARD pass rate | ≥95% | ✅ implementado | — |
| M3 | Charter coverage Tier A | ≥80% | ✅ implementado | — |
| M4 | Goal drift rate | <5% | ⏸️ stub | Telemetria + heurística |
| M5 | Detector latency | p95 <120s | 🟡 parcial (deriva GH API) | Agregador cron S7 |
| M6 | Anti-hallucination ratchet | ≤baseline | ⏸️ stub | baseline populado + ramp-up 7d |

F4 entrega 2 métricas reais (M2+M3) + spec das outras 4 + comando `charter:metrics` que retorna `null` pra não-implementadas.

---

## Critério de aceite F4

- [x] 6 métricas com spec formal (este doc)
- [x] M2 + M3 implementadas em Pest agregador
- [x] `php artisan charter:metrics --json` retorna struct com 6 chaves (M1=null, M2=value, M3=value, M4=null, M5=null, M6=null)
- [ ] Dashboard `/copiloto/admin/qualidade` ganha 6 colunas charter (S7 — fora F4)
- [ ] Cron `charter:health` exposto pro dashboard (já roda; precisa endpoint API S7)
