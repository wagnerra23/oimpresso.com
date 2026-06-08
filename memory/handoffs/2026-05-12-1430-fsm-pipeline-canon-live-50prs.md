# 2026-05-12 14:30 BRT — MARCO FSM Pipeline Canônico LIVE prod biz=1 (50 PRs ~10h)

> **Tipo:** handoff (estado pro próximo)
> **Status:** sessão fechada com sucesso (Wagner aprovou UI ao vivo + smoke 8/8 OK)
> **ADR canônica:** [0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)
> **Session log:** [2026-05-12-fsm-pipeline-canon-live-prod-50prs.md](../sessions/2026-05-12-fsm-pipeline-canon-live-prod-50prs.md)

## TL;DR pro próximo agente

Wagner reportou 3 pain points fiscais ROTA LIVRE em 2026-05-12. Resposta: pipeline FSM canônico COMPLETO Orçamento → Produção → Venda → Faturamento em prod biz=1, cobrindo:

- **Sells** (vendas): 11 stages × 21 actions × 10 roles per-business
- **Repair** (OS): 13 stages × ~15 actions × 6 roles per-business
- **Cascade**: NFe SEFAZ + Asaas/Inter (refund pagos + cancel pending) + reserva estoque + WhatsApp/email
- **LGPD**: consent columns
- **UI**: botões dinâmicos drawer + timeline auditável + toast sonner
- **Audit**: `fsm:scan-drift` daily 03:00 BRT
- **Bulk**: comando artisan migrar vendas legadas em lote

50 PRs mergeados ~10h. 4 hotfixes prod detectados em <30min. Wagner aprovou via screenshot UI funcionando.

## Estado em produção (smoke 8/8 OK 2026-05-12 14:13Z)

```
✅ contacts.whatsapp_consent
✅ contacts.email_consent
✅ repair_job_sheets.current_stage_id
✅ nfse_eventos_cancelamento table
✅ App\Domain\Fsm\Services\InitialStageResolver class
✅ Modules\RecurringBilling\Jobs\RefundCobrancaAsaasJob class
✅ Modules\Repair\Http\Controllers\RepairFsmActionController class
✅ Modules\NfeBrasil\Services\NfseCancelService class
```

## Estado MCP no momento do fechamento

```
cycles-active: CYCLE-05 (Inter PJ prod + WhatsApp governança) · 11d restantes · 8% decorrido
  goal 1: Inter PJ Banking em prod (US-RB-048/046/047) — NÃO tocado nesta sessão
  goal 2: WhatsApp FICHA v2 + AUDIT-LOG (US-WA-051/052) — NÃO tocado nesta sessão

decisions-search since 2026-05-12: 0143 (FSM Pipeline LIVE prod marco)

my-work: trabalho FORA do cycle ativo (priorização Wagner — pain points reais)

sessions-recent: 2026-05-12-fsm-pipeline-canon-live-prod-50prs.md (massiva, 50 PRs)
```

## Próximos passos sugeridos (escolher 1)

### Opção A — Continuar pipeline FSM
- Migrar 162 vendas biz=1 via `fsm:bulk-start-pipeline 1 --dry-run` → real
- Wagner valida UI clicando botões em vendas reais

### Opção B — Retornar foco CYCLE-05
- US-RB-048: Inter PJ Banking RUNBOOK pré-prod
- US-WA-051/052: WhatsApp FICHA v2 + AUDIT-LOG.md shell

### Opção C — US backlog FSM expansão
- ADR rename `Sale*` → `Fsm*` tabelas (R5 bloqueador SPEC Repair)
- US-REP-FSM-005: Frontend Repair drawer
- US-NFSE-CANCEL-002+: drivers per-município

### Opção D — Pausa documentação
- Esta handoff + ADR 0143 + session log = marco documentado
- Próximo Claude/dev tem contexto completo via `brief-fetch` + `decisions-fetch slug=0143`

## Regras novas (proibicoes.md atualizado)

8 proibições novas adicionadas em `memory/proibicoes.md` §FSM Pipeline Canônico:
- ⛔ UPDATE direto em `current_stage_id` (trait bloqueia)
- ⛔ Property dinâmica Eloquent com nome ≠ coluna (vira SQL column)
- ⛔ `static::observe()` em bootXxx trait (boot recursion)
- ⛔ `sale_stage_history.action_id` NOT NULL (startPipeline precisa null)
- ⛔ Roles Spatie sem suffix `#{biz}` no UltimatePOS
- ⛔ Action FSM `is_critical=true` sem role cadastrada (fail-secure)
- ⛔ NFe cancelada via SEFAZ NÃO sofre forceDelete (preserva sequencial)
- ⛔ Refund Asaas POST sem flag `ASAAS_REFUND_ENABLED=true`
- ⛔ Mail/Whatsapp sem checar `Contact::canReceive*Notification()` LGPD

## Hotfixes prod descobertos nesta sessão (4)

| # | PR hotfix | Causa | Tempo |
|---|---|---|---|
| 1 | #624 | Spatie roles.business_id NOT NULL (UltimatePOS extends) | ~5min |
| 2 | #639 | `static::observe()` boot recursion | ~7min |
| 3 | #640 | Eloquent property dinâmica vira coluna SQL | ~10min |
| 4 | #643 | `action_id` NOT NULL bloqueia startPipeline | ~5min |

## Anti-patterns formalizados (auto-mem)

- Paralelização agents: stash + branch + add seletivo + stash --keep-index FUNCIONA mas frágil. Anti-pattern: stash pop pode trazer mudanças de outras sessões → reimplementação manual via reporte do agent (workaround validado nesta sessão)
- Sumário agent ≤300 palavras (paths + decisões + TODOs) é critical pro parent consolidar sem ler código completo

## Refs essenciais

- **[ADR 0143](../decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md)** — marco LIVE prod biz=1
- [ADR 0129](../decisions/0129-state-machine-canonica-fsm-rbac.md) — FSM tabular custom (fundação)
- [memory/sessions/2026-05-12-fsm-pipeline-canon-live-prod-50prs.md](../sessions/2026-05-12-fsm-pipeline-canon-live-prod-50prs.md) — narrativa completa
- [CASOS-USO-PIPELINE-VENDAS.md](../requisitos/Sells/CASOS-USO-PIPELINE-VENDAS.md) — 7 casos Given/When/Then
- [SPEC-FSM-WIREUP.md](../requisitos/Repair/SPEC-FSM-WIREUP.md) — Repair 7 fases A-G
- [SPEC-NFSE-CANCEL.md](../requisitos/NfeBrasil/SPEC-NFSE-CANCEL.md) — NFSe 10 US per-padrão

## Mensagem final pro próximo Claude/dev

Esta sessão entregou um **marco arquitetural** — FSM canônico passou de fundação (ADR 0129) pra **plataforma operacional em prod**. Coexistência com legacy preservada → rollout gradual sem big-bang.

Wagner declarou *"ficou ótimo pode continuar"* + smoke prod verde após cada wave. Se precisar continuar pipeline FSM em outros módulos (ProjectMgmt, OficinaAuto, ComunicacaoVisual), o pattern está documentado e reusável.

**NUNCA edite este handoff** — append-only. Próximo handoff vira arquivo novo em `memory/handoffs/YYYY-MM-DD-HHMM-<slug>.md`.
