# CAPTERRA-FICHA — Repair

> Ficha canônica de benchmark do módulo shared Repair (Kanban OS — infraestrutura entre verticais).
> **Bucket**: `vertical_client_facing` ([ADR 0160](../../decisions/0160-scoped-scorecard-evaluator-v3.md))
> **Wave 23** — saturação 69 → ≥85 (rubrica scoped vertical_client_facing.yaml)
> ADR governança: [0089](../../decisions/0089-capterra-driven-module-evolution.md) + [0143](../../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)

---

## Identidade do módulo

- **Nome interno**: `Repair`
- **Domínio**: Ordem de Serviço (OS) com Kanban + FSM canon 13 stages
- **Função**: Shared infrastructure entre verticais (Vestuario opcional, ComunicacaoVisual, OficinaAuto)
- **Estado lifecycle** (ADR 0121): **shared infra** (em prod biz=1 desde 2026-05-12 — ADR 0143 marco)
- **Clientes diretos**: Wagner biz=1 (validação prod) + qualquer business UPos que ative repair_module
- **Diferencial-chave**: FSM canonico tabular 13 stages × ~15 actions × 6 roles + portal público sem login

## Concorrentes-alvo

| Concorrente | Pricing/mês | Foco | Lacuna oimpresso preenche |
|---|---|---|---|
| **Cellity OS** | R$ [redacted Tier 0]-400 | celular/assistência | UI legacy, sem FSM auditável |
| **Sistema OS** | R$ [redacted Tier 0]-300 | small assistance | sem multi-tenant Tier 0 |
| **Smart OS** | R$ [redacted Tier 0]-500 | médio porte | sem FSM canon, portal cliente fraco |
| **Mecanizou** | R$ [redacted Tier 0]-450 | oficinas em geral | sem portal público sem login |

## Capacidades em produção (validadas biz=1)

```yaml
capacidades_em_prod:
  - us: US-REP-001
    nome: "CRUD JobSheet com PII redactor (contact_id/device_id/defects)"
    score: P0
    onde: "JobSheetController + JobSheet entity (LogsActivity + HasBusinessScope)"

  - us: US-REP-FSM-003
    nome: "FSM canon 13 stages × actions × roles per-business"
    score: P0
    onde: "app/Domain/Fsm/ (ADR 0143 LIVE prod 2026-05-12)"
    evidencia: "RepairFsmActionControllerTest + GuardsFsmTransitions trait"

  - us: US-REP-002
    nome: "Portal público consulta status sem login (token UUID)"
    score: P0
    onde: "CustomerRepairStatusController"

  - us: US-REP-003
    nome: "WhatsApp aprovação cliente (paridade OficinaAuto/Vestuario)"
    score: P0
    onde: "Wave 17/18 saturação + LogsActivity"

  - us: US-REP-004
    nome: "Audit append-only (sale_stage_history + activity_log)"
    score: P0
    onde: "ADR 0143 sale_stage_history NUNCA purgado + Spatie LogsActivity"
```

## Top 5 gaps P0 (pra subir nota ≥85)

| US | Capacidade | Esforço | ROI estimado | Concorrente que tem |
|----|------------|---------|--------------|---------------------|
| US-REP-005 | Dashboard KPIs OS (lead time, reincidência, MTBF por modelo) | 24h | alto (gestão operacional) | Smart OS, Cellity |
| US-REP-006 | App mobile técnico (foto + checklist + assinatura cliente) | 40h | alto (paridade Mecanizou) | Smart OS |
| US-REP-007 | Comissão por técnico (% material/serviço) | 16h | médio (rotatividade) | Cellity |
| US-REP-008 | Catálogo serviço com peças vinculadas (orçamento 1-click) | 20h | médio (velocidade venda) | Smart OS |
| US-REP-009 | Job artisan `repair:retention-purge` (anonymize após 5y) | 12h | médio (LGPD operacional) | n/a |

## Diferenciais oimpresso vs concorrentes

1. **FSM canon tabular** (ADR 0143) — 13 stages × ~15 actions × 6 roles per-business com audit append-only
2. **GuardsFsmTransitions trait** — bloqueia UPDATE direto em current_stage_id (fail-secure)
3. **Portal público consulta** — cliente vê status sem login (token UUID + signed URL)
4. **Multi-tenant Tier 0** com PII redactor + retention 5y anonymize (preserva métricas SRE)
5. **Activity_log Spatie** complementa sale_stage_history — audit duplo (transição FSM + mudança campo)
6. **Stack moderna** Laravel 13.6 + React 19 + FSM em prod desde 2026-05-12

## Score Capterra W22 → W23

| Dimensão (scoped vertical_client_facing) | W22 | W23 alvo |
|------------------------------------------|-----|----------|
| V1 Pest E2E Customer Journey (13 stages) | 9/15 | **13/15** |
| V2 Code Quality FormRequests (Wave 18) | 10/10 | 10/10 |
| V3 Perf UX (Inertia::defer 5x JobSheetController) | 8/10 | 9/10 |
| V4 LGPD retention canon + LogsActivity | 10/15 | **14/15** |
| V5 Docs canon (7 RUNBOOKs + BRIEFING + CAPTERRA) | 14/20 | **18/20** |
| V6 Capterra ROI Top 5 | 4/10 | 8/10 |
| **Total scoped** | **69/100** (médio-alto) | **≥85/100** |

## Status lifecycle (ADR 0121)

- ✅ `shared infrastructure` — em prod biz=1 desde 2026-05-12 (ADR 0143 marco)
- ✅ `consumido por` — Vestuario opcional, ComunicacaoVisual planejado, OficinaAuto V0
- ⏳ `multi-vertical maturo` — meta Q4/26 (3 verticais ativos + métricas SRE consolidadas)

## Anti-padrões (Tier 0 IRREVOGÁVEIS)

- ⛔ UPDATE direto em `current_stage_id` sem ExecuteStageActionService (GuardsFsmTransitions bloqueia)
- ⛔ Mudar `sale_stage_history.action_id` pra NOT NULL (entrada "Pipeline iniciado" sem action)
- ⛔ Action FSM `is_critical=true` SEM role cadastrada (UnauthorizedActionException fail-secure)
- ⛔ Roles Spatie sem suffix `#{biz}` (FK roles.business_id NOT NULL)
- ⛔ PII contact/device/defects em log sem PiiRedactor
- ⛔ Smoke `business_id=4` (ROTA LIVRE PROD) — usar biz=1 ou biz=99 (ADR 0101)
- ⛔ `static::observe()` dentro de bootXxx() do trait (LogicException recursão)

## Referências

- [BRIEFING.md](BRIEFING.md) — estado consolidado
- [README.md](README.md) — visão geral módulo
- [ARCHITECTURE.md](ARCHITECTURE.md) — arquitetura técnica
- [PII-LGPD.md](PII-LGPD.md) — política PII
- [OBSERVABILITY.md](OBSERVABILITY.md) — observabilidade
- [GLOSSARY.md](GLOSSARY.md) — glossário domínio
- [RUNBOOK-jobsheet-*.md](.) — 5 runbooks MWART do JobSheet
- [RUNBOOK-repair-*.md](.) — 2 runbooks MWART do Repair index/show

---

**Próxima revisão**: 2026-08-16 (trimestre) ou quando 3º vertical ativar.
**Wave**: 23 (saturação bucket vertical_client_facing — ADR 0160).
