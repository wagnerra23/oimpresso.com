# Repair — Changelog

## [Wave 28 — 2026-05-17] POLISH ≥95 (80-95 → 96)

### D2 Pest +2 sentry FSM canon ADR 0143
- `Tests/Feature/Wave28PolishTest.php` — +2 testes sentry Wave 28:
  - JobSheet preserva trait `GuardsFsmTransitions` (regression guard pós ADR 0143
    LIVE prod biz=1 — se alguém remover, UPDATE direto em current_stage_id volta
    a passar e quebra audit trail).
  - `CancelJobSheetRequest` (W25 D8) preserva validação multi-tenant (anti-IDOR)
    + motivo obrigatório (LGPD audit trail).
- Tier 0 IRREVOGÁVEL ADR 0143: FSM canon SEMPRE via `ExecuteStageActionService`,
  NUNCA UPDATE direto. Multi-tenant ADR 0093 + biz=4 intocado (ADR 0101).

## [Wave 25 — 2026-05-16] POLISH ≥90 (80 → 90, +10pp)

### D2 Code Quality (13 → 17)
- `Tests/Feature/Wave25RepairFsmCanonExpandedTest.php` — Pest expandido cobrindo
  pipeline canon COMPLETO 13 stages × 12 actions ({@see ADR 0143 §Repair pipeline}):
  recebido_para_diagnostico → em_diagnostico → aguardando_aprovacao → aprovado/recusado
  → aguardando_pecas → em_reparo → em_testes → pronto_para_retirada → entregue_completo
  → garantia_acionada / cancelado / descartado_pelo_cliente.
  - 5 cenários: setup pipeline 13/12, GuardsFsmTransitions bloqueia UPDATE direto,
    cross-tenant biz=99 vs biz=1, action_id nullable preserved (hotfix #643),
    terminal stages sem outgoing actions.
  - Multi-tenant Tier 0 IRREVOGÁVEL ({@see ADR 0093}). Nunca biz=4 cliente ({@see ADR 0101}).
  - Pattern espelha `RepairFsmActionControllerTest` (SQLite in-memory + schema mínimo).

### D8 Security (4 → 8)
- `Http/Requests/CancelJobSheetRequest.php` — FormRequest cancelamento crítico
  com validação multi-tenant (anti-IDOR), motivo obrigatório (LGPD audit trail),
  e bloqueio se já está em stage terminal. Pattern espelha `StartFsmActionRequest`.
  - Wave 17 já entregou `StartFsmActionRequest`. Wave 25 fecha CRUD FSM crítico.
  - Reforça pattern Tier 0 IRREVOGÁVEL ADR 0143 (FSM canon) + ADR 0093 (multi-tenant).

### Notas Tier 0 IRREVOGÁVEIS preservadas
- ⛔ FSM canon (ADR 0143) intacto: 13 stages × 12 actions × `GuardsFsmTransitions`
  bloqueando UPDATE direto preservados nos testes.
- ⛔ Coluna `sale_stage_history.action_id` nullable preservada (hotfix #643 — 2026-05-12).
- ⛔ Multi-tenant Tier 0: tests validam isolation biz=1 vs biz=99 (nunca biz=cliente).
- ⛔ PT-BR em comentários e mensagens. Identificadores PHP em inglês.

## Histórico anterior

Wave 17/18 anteriores cobriram FSM controller specs (5 cenários core), StartFsmActionRequest,
LGPD audit, multi-tenant base, MWART discipline e Inertia::defer pattern.
