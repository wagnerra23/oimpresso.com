# Postmortem — Sprint 2 MWART OS Listagem

> **Status:** 🔴 TEMPLATE — preencher após 48h de soak em prod com `MWART_REPAIR_INDEX=true`.
> **Cliente piloto:** ROTA LIVRE (`business_id=4`, Larissa).

---

## Frontmatter

```yaml
---
sprint_id: S2
title: MWART OS Listagem (Repair) — Blade → React Inertia
status: in-soak
soak_start: 2026-05-XX  # data ativação flag em prod
soak_end: 2026-05-XX  # +48h
flag: MWART_REPAIR_INDEX
piloto: ROTA LIVRE (business_id=4)
authored_by: wagner
co_authored_by: sonnet
verdict: ?  # success | partial | failed
---
```

---

## 1. Métricas medidas

### 1.1. Latência p95 (Telescope)

```sql
-- p95 do endpoint /repair/repair com flag ativo
SELECT
  hour,
  COUNT(*) AS requests,
  AVG(duration_ms) AS media,
  -- p95 aproximado via PERCENT_RANK
  MAX(duration_ms) FILTER (WHERE rank <= 0.95) AS p95
FROM (
  SELECT
    DATE_FORMAT(created_at, '%Y-%m-%d %H:00') AS hour,
    duration AS duration_ms,
    PERCENT_RANK() OVER (PARTITION BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00') ORDER BY duration) AS rank
  FROM telescope_entries
  WHERE type = 'request'
    AND content->'$.uri' LIKE '%/repair/repair%'
    AND created_at > NOW() - INTERVAL 48 HOUR
) sub
GROUP BY hour
ORDER BY hour;
```

**Alvo:** p95 < 400ms em todas as horas (charter invariant).

### 1.2. Erros JS (Sentry)

```bash
# Via CLI Sentry ou dashboard
# Eventos com fingerprint matching /repair/repair nas últimas 48h
sentry events list --query "url:*repair/repair* age:-48h"
```

**Alvo:** zero novos erros JS atribuíveis ao Inertia (versão Blade pode continuar gerando os mesmos).

### 1.3. Paridade funcional Blade vs Inertia

Checklist manual (rodar 2x em sessões separadas):

- [ ] Filtros: status, cliente, período, location funcionando ambos
- [ ] Paginação preserva URL bookmarkável (`?page=3&repair_status_id=2`)
- [ ] Bulk actions: mudar status, change service staff
- [ ] Permissions Spatie: `repair.view` (todas) vs `repair.view_own` (só `created_by = auth()->id()`)
- [ ] Multi-tenant: queries sempre com `transactions.business_id = session('user.business_id')`

### 1.4. Tráfego canary (medir se em CANARY com `canary_pct < 100`)

```sql
-- Atualmente sem canary state (S2 não chegou a usar canary_pct, isso é S6)
-- Apenas confirmar que usuários estão na versão Inertia
SELECT
  COUNT(DISTINCT user_id) AS usuarios_inertia,
  COUNT(*) AS pageviews_inertia
FROM mcp_audit_log
WHERE tool_name = 'page_view'
  AND content->>'$.route' = '/repair/repair'
  AND content->>'$.flag.MWART_REPAIR_INDEX' = 'true'
  AND called_at > NOW() - INTERVAL 48 HOUR;
```

**Alvo:** se Wagner ativou flag global, ≥1 usuário ROTA LIVRE acessou Inertia version.

### 1.5. Multi-tenant isolation (Tier 0 — ⚠️ crítico)

```sql
-- Verificar que NENHUM resultado de listagem tinha business_id != session
-- Se este SELECT retornar > 0, é incidente Tier 0 imediato
SELECT
  COUNT(*) AS vazamentos_potenciais
FROM transactions t
JOIN mcp_audit_log al ON al.content->>'$.transaction_id' = t.id
WHERE al.tool_name = 'page_view'
  AND al.content->>'$.route' = '/repair/repair'
  AND t.business_id != JSON_VALUE(al.content, '$.user_business_id')
  AND al.called_at > NOW() - INTERVAL 48 HOUR;
```

**Alvo:** **0 vazamentos**. Qualquer resultado >0 → rollback imediato + investigação.

---

## 2. Resultados (preencher)

| Métrica | Alvo | Real | Veredicto |
|---|---|---|---|
| p95 < 400ms | sempre | ? | ⏳ |
| Erros JS Sentry | 0 | ? | ⏳ |
| Paridade funcional | 100% | ?% | ⏳ |
| Multi-tenant leak | 0 | ? | ⏳ |
| Larissa/usuários ROTA LIVRE notaram diferença? | não/aceitam | ? | ⏳ |

Veredicto global: ⏳

---

## 3. Bugs encontrados durante soak

| # | Sintoma | Diagnóstico | Fix | Commit |
|---|---|---|---|---|
| 1 | (placeholder) | | | |

---

## 4. Decisão sobre S2.5 (replicar 4 telas Repair)

> Wagner aprovou S2.5 paralelo a S3. Decidir aqui se segue como planejado.

- [ ] S2.5 GO? (paridade S2 ok + métricas no alvo)
- [ ] Quem conduz S2.5? (Felipe ou Wagner)
- [ ] Cronograma: 4 telas × 2 dias = 8 dias úteis
- [ ] Próxima tela: Job Sheet (`/repair/job-sheet`)

---

## 5. Aprendizados pra MWART (template `mwart-migrate`)

(Preencher após análise — vai virar updates da skill `mwart-migrate`)

- (placeholder)

---

## 6. Decisão de fechamento

- [ ] Sprint 2 oficialmente fechado (tag `s2-completed-YYYY-MM-DD`)
- [ ] Atualizar `ROTEIRO-MESTRE.md §2` (S2 vira ✅ DONE)
- [ ] Atualizar `mcp_route_migration_state` (S6 ainda não criou tabela; provisorio em comentário): `repair.index = MIGRATED desde YYYY-MM-DD`
- [ ] Avisar Larissa (cliente piloto) que migração está estável

---

## Notas

- Se vazamento multi-tenant → rollback IMEDIATO, postmortem urgente, ADR HISTORICAL
- Se p95 piorar mas paridade ok → ajustar índices schema (S2 já adicionou alguns; pode precisar mais)
- S2.5 não bloqueia S3, mas se S2 falhar, S2.5 não acontece
