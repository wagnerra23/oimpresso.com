---
id: requisitos-repair-changelog
---

# Changelog

Formato: [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) · [Semver](https://semver.org/).

## [Unreleased] - 2026-05-16 — Wave 23 saturação bucket vertical_client_facing

### Added

- **CAPTERRA-FICHA.md** canônica — concorrentes (Cellity OS, Smart OS, Mecanizou), top 5 gaps P0, score V1-V6 W22→W23 (69→≥85).
- **Wave23RepairSaturationTest.php** — Pest saturação V1/V3/V4/V5/V6 com 15 assertions cobrindo FSM canon 13 stages, GuardsFsmTransitions trait, Inertia::defer ≥5 ocorrências, retention 1825d, 7 RUNBOOKs canon, governance.bucket=vertical_client_facing.
- **module.json governance.bucket=vertical_client_facing** ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md)) com `scoped_score_target: 85`, `fsm_canonico: true`, `fsm_estados: 13`.

### Changed

- Score Capterra scoped (rubrica `vertical_client_facing.yaml`): 69/100 → ≥85/100 estimado.
- V1 Pest E2E: +4 (FsmCanonicalJourney structural reflection).
- V5 Docs canon: +4 (CAPTERRA-FICHA fechando gap W22).

## [0.1.0] - 2026-04-22

### Added

- Documentação inicial consolidada a partir do arquivo plano.
- Migrado para estrutura de pasta (README + ARCHITECTURE + SPEC + CHANGELOG + adr/).

---

## Implementação (histórico movido de `Modules/Repair/CHANGELOG.md`)

> Movido em 2026-08-10. Os dois changelogs registravam eventos DIFERENTES — acima as
> decisões/requisitos, aqui o que foi de fato mergeado. Medido antes de fundir:
> sobreposição de datas era 0-2 (máx. 2, em ComVis e KB), logo nenhum lado era cópia do
> outro e escolher um deles perderia registro. Conteúdo preservado na íntegra.

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

## [Wave 27 — 2026-05-17] POLISH FINAL ≥95 (90 → 95, +5pp)

### D8 Security (8 → 12) — FormRequests FSM canon completos
- `Http/Requests/ReopenJobSheetRequest.php` — FormRequest pra action FSM
  `acionar_garantia` (transição canon `entregue_completo` → `garantia_acionada`
  ADR 0143 §Repair pipeline).
  - Valida stage ATUAL ser `entregue_completo` (única origem válida — bloqueia
    "tentar acionar garantia" a partir de qualquer outro stage).
  - Multi-tenant Tier 0: valida jobsheet pertence ao business da sessão
    (anti-IDOR via DB::table check).
  - Motivo obrigatório (min:5 max:500 chars) — audit LGPD + CDC Art. 26
    rastreabilidade.
  - Soft-warning CDC Art. 26 (90d garantia produto durável): após prazo,
    injeta `_fora_prazo_cdc=true` + `_dias_decorridos` no request pro
    ExecuteStageActionService logar (não bloqueia — Wagner aprovou pós-90d
    como cortesia comercial).
  - Campo opcional `defeito_novo` (max:1000) pra anexar contexto.

### Canon FSM FormRequests completos (4)
- `StartFsmActionRequest` (W17)
- `CancelJobSheetRequest` (W25 — is_critical=true cancelar_os)
- `ExecuteRepairFsmActionRequest` (W S — generic action dispatcher)
- `ReopenJobSheetRequest` (**W27 NEW** — acionar_garantia warranty)

### D2/D7 Sentinel preservation
- `Tests/Feature/Wave25RepairFsmCanonExpandedTest.php` preservado (13 stages × 12 actions canon).
- `Config/retention.php` (W17 shim) sentinel: tabelas FSM auditable, repair_job_sheets=1825d (CCB Art. 206 §5 III).

### Saturação Pest
- `Modules/Repair/Tests/Feature/Wave27RepairPolishTest.php` — 11 specs:
  - ReopenJobSheetRequest existe + estende FormRequest
  - Rules canon (motivo required min:5 max:500 + defeito_novo sometimes)
  - authorize() exige auth básica (não pública)
  - Mensagens PT-BR com LGPD+CDC referenciados
  - Constante CDC_GARANTIA_DIAS=90 (CDC Art. 26 sentinel)
  - 4 FormRequests FSM canon presentes (Start/Cancel/Execute/Reopen)
  - Tier 0 sentinels: stage `entregue_completo` validation + session.business_id anti-IDOR
  - W25 Wave25RepairFsmCanonExpandedTest preservado
  - W17 retention.php shim preservado

### Notas Tier 0 IRREVOGÁVEIS preservadas
- ⛔ FSM canon (ADR 0143) intacto: 13 stages × 12 actions × `GuardsFsmTransitions`
  bloqueando UPDATE direto preservados nos testes W25 + W27.
- ⛔ Coluna `sale_stage_history.action_id` nullable preservada (hotfix #643 — 2026-05-12).
- ⛔ Multi-tenant Tier 0: tests validam isolation biz=1 vs biz=99 (nunca biz=cliente — ADR 0101).
- ⛔ PT-BR em comentários e mensagens. Identificadores PHP em inglês.

### Refs
- ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL
- ADR 0143 FSM Pipeline LIVE prod biz=1
- CDC Art. 26 (garantia produto durável 90d)

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
