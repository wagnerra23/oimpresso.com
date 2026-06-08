# 18 — M4 Goal drift detector (stub)

> **Spec da métrica que detecta sessões IA que extrapolam scope da tela editada.**
> Implementação real fica em S7 — depende de telemetria `mcp_audit_log` que ainda não captura "tools usados vs Goals declarados".

---

## Por que é stub em F4

Goal drift detection requer:
1. **Telemetria fina** — sessão IA emite `tools_used: [Edit, Bash, Read]` + `target_charter: /repair/dashboard`
2. **Heurística de classificação** — comparar `tools_used` vs `Goals` do charter target
3. **Aggregação** — `drift_detected = 1` quando sessão usa ≥2 tools fora do contrato

Hoje `mcp_audit_log` só captura `event_type`, não `tools_used` por sessão de tela. Adicionar requer:
- Hook `PostToolUse` que registra cada Edit + Bash em sessão
- Skill `charter-first` (Tier A ATIVA F2) precisa registrar `target_charter` ao começar sessão

Backlog em S7 — tarefa de telemetria, não de governança.

---

## Heurística proposta

Cada sessão tocando tela com charter recebe score:

```
Score(session) = count(tools_used NOT IN charter.allowed_tools) / total_tools

drift_detected = Score >= 0.4   # >=40% das tools usadas saem do contrato
```

`allowed_tools` deriva dos Goals + Automation Hooks. Por exemplo:
- Charter `/repair/dashboard` Goals = "Listar...", "Mostrar..."
  → allowed_tools = [Read (lê DB), Render (Inertia)]
- Sessão IA usa: [Read, Edit (modifica .tsx), Bash (git push)]
  → fora do contrato: Edit + Bash (2 de 3) → Score = 0.66 → drift_detected ✅

Heurística é simplista mas pega casos óbvios.

---

## Output esperado em F5+

```json
{
  "metric": "M4_goal_drift_rate",
  "window": "7d",
  "total_sessions_with_charter": 312,
  "drift_detected_count": 8,
  "drift_rate_pct": 2.56,
  "alvo_pct": 5.0,
  "status": "green"
}
```

---

## Por que vale stub

- **Estado da arte 2026** (NeurIPS Goal Drift research, arXiv 2505.02709) confirma que goal drift mede algo real
- Charter Non-Goals + Pest GUARD já neutralizam casos extremos (M6)
- M4 é o "termômetro mais finos" pra calibrar **se charter está sendo respeitado pela IA antes de sair pra Pest**
- Sem M4, perde sinal de que charter está "lendo só pra check-the-box" (drift sutil)

---

## Critério de aceite F4 (M4 stub)

- [x] Spec aqui
- [x] Comando `php artisan charter:metrics` retorna `m4_goal_drift_rate: null`
- [ ] Implementação em S7 quando telemetria `mcp_audit_log.tools_used` existir
- [ ] Heurística calibrada com 30d de dados antes de virar alerta
