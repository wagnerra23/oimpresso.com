---
slug: 0174-errata-deprecation-plan-accounting-ondas-3-4-skip
number: 174
title: "Errata DEPRECATION-PLAN Accounting — Ondas 3+4 SKIP (audit prod 0 rows)"
type: adr
status: aceito
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: "2026-05-20"
module: financeiro
quarter: 2026-Q2
tags: [deprecation, accounting, financeiro, errata, audit-prod]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - 0172-deprecar-modulo-accounting-fundir-financeiro
  - 0173-errata-arq-0005-tabelas-accounting-sem-prefixo
pii: false
review_triggers:
  - "Canary 30d termina em 2026-06-19 — re-validar achados"
  - "Onda 5 git rm Modules/Accounting/ pós canary"
amends:
  - 0172-deprecar-modulo-accounting-fundir-financeiro
ref_audit: memory/sessions/2026-05-20-audit-accounting-prod-zero-rows.md
ref_plano: memory/requisitos/Accounting/DEPRECATION-PLAN.md
ref_inspecao: memory/requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md
---

# ADR 0174 — Errata DEPRECATION-PLAN Accounting: Ondas 3+4 SKIP

## Status

**accepted** (Wagner 2026-05-20 pós-audit prod — "sim sim" à proposta de pular Ondas 3+4 e encurtar timeline).

Esta ADR **amends** (não supersedes) a ADR 0172. ADR 0172 segue válida — apenas o cronograma das 7 ondas é ajustado pelos achados empíricos da Onda 0 audit.

## Contexto

A [ADR 0172](0172-deprecar-modulo-accounting-fundir-financeiro.md) (accepted 2026-05-20 manhã) chumbou roadmap de 7 ondas (~26 semanas corridas) pra deprecar `Modules/Accounting` em favor de `Modules/Financeiro`. O cronograma assumiu **cenário conservador**: existiriam clientes heavy usando Accounting cujos dados precisariam migrar pra `fin_*`, com view bridge transitional 60d pra zero-downtime.

Na **mesma sessão de aceitação da ADR 0172** (tarde de 2026-05-20), Claude executou o audit `DEPREC-ACC-001` (Onda 0 do plano) contra MySQL Hostinger produção. Os resultados [completos no session log](../sessions/2026-05-20-audit-accounting-prod-zero-rows.md) refutaram a premissa conservadora:

### Achados do audit (5 evidências convergentes)

1. **6 tabelas owned-by-Accounting com ZERO rows em prod:**
   - `chart_of_accounts`: 0
   - `journal_entries`: 0
   - `budgets`: 0
   - `transfers`: 0
   - `payment_details`: 0
   - `branch_capital`: 0

2. **Zero subscriptions ativas com `accounting_module` no `package_details` JSON.** Query `SELECT id, business_id FROM subscriptions WHERE JSON_EXTRACT(package_details, '$.accounting_module') IS NOT NULL AND status='approved' AND deleted_at IS NULL AND (end_date >= CURDATE() OR end_date IS NULL)` retorna vazio.

3. **`accounts_legacy_map` é Financeiro infra, não Accounting:** 19 rows, todas biz=1 Wagner WR2, `legacy_source='wr-comercial-delphi'` (Banking importer Officeimpresso/Delphi, 2026-05-11). DEPRECATION-PLAN.md já marcava PRESERVE in-place. Audit confirma.

4. **`accounts` + `account_transactions` (11.884 tx em biz=4 majoritariamente) são UltimatePOS core, não Accounting.** ADR 0172 §5 já marcava PRESERVE in-place. Audit confirma volume real.

5. **Financeiro operacional cobrindo prod:** biz=1 140 fin_titulos, biz=4 54 fin_titulos, biz=164 83.040 fin_titulos + 71.675 fin_titulo_baixas. Substituto canônico já LIVE.

### Smoke prod pós PR #1246 (UI freeze 410)

- `GET https://oimpresso.com/accounting/dashboard` → **HTTP/1.1 410 Gone** (text/html) ✅
- `GET https://oimpresso.com/accounting/journal_entry` (Accept: application/json) → **HTTP/1.1 410 Gone** + JSON `{"adr":"ADR 0172","substituto":"/financeiro/*"}` ✅
- Deploy auto-pull já consumiu commits `eef793ffe` + `d88bf9e1e`

## Decisão

**Pular Ondas 3 e 4 do DEPRECATION-PLAN.md** (originalmente E4 view bridge no plano, e a Onda 3 migration script que era item separado das tasks DEPREC-ACC-005/006). Manter Ondas 5 e 6 com cronograma de canary encurtado.

### Justificativa por onda

**Onda 3 (DEPREC-ACC-005 — Migration script `accounts_legacy_map` → `fin_*`)** — SKIP porque:
- Origem está vazia (0 rows em `chart_of_accounts`, `journal_entries`, `budgets`, `transfers`)
- `accounts_legacy_map` já É Financeiro infra (DEPRECATION-PLAN.md §5 PRESERVE in-place)
- Não há dados pra migrar

**Onda 4 (DEPREC-ACC-006 = E4 plano — View bridge `accounting_*` → `fin_*`, 60d rollback window)** — SKIP porque:
- Bridge view serviria pra código legacy que ainda consulta tabelas `chart_of_accounts`/`journal_entries` durante transição. Inspeção forense §6 documentou **ZERO cross-imports do namespace `Modules\Accounting\`** fora do próprio módulo (grep validado contra Vestuario/NfeBrasil/RecurringBilling/Financeiro/Sells/Crm)
- Tabelas origem têm 0 rows, então bridge view retornaria 0 rows também (zero usuário pra atender)
- 60d wait deixa de ser justificável

### Novo cronograma (compressed)

| Onda | Tipo PR | Estado | LOC | Pré-req | Gate Wagner | ETA |
|---|---|---|---|---|---|---|
| **Onda 0** | Audit produção | ✅ **DONE** | 0 | — | Aprovação dados | 0d (concluída) |
| **Onda 1 (E1)** | docs (ADR 0172+0173) | ✅ **DONE** | ~250 | Onda 0 | — | 0d (concluída) |
| **Onda 1 (E2)** | feat (errata BRIEFING) — PR #1244 | ✅ **DONE** | ~18 | E1 | — | 0d (concluída) |
| **Onda 2 (E3)** | feat (UI freeze + routes 410 + Pest) — PR #1246 | ✅ **DONE** | ~186 | E2 | smoke 410 LIVE | 0d (concluída) |
| **~~Onda 3~~** | ~~migration script~~ | ❌ **SKIP** (ADR 0174) | — | — | — | 0d |
| **~~Onda 4 (E4)~~** | ~~view bridge 60d~~ | ❌ **SKIP** (ADR 0174) | — | — | — | 0d |
| **Canary** | (humano-limitado wait) | ⏳ pending | 0 | Onda 2 LIVE | Logs `/accounting/*` 30d esperado: zero hits | **30d** |
| **Onda 5 (E5)** | chore (drop código PHP + permissions seeder + provider) | ⏳ pending | ~280 | Canary OK | Zero log error 30d + Pest "permissions removidas" | 2d trabalho |
| **Onda 6 (E6)** | chore (DROP TABLE + ARCHIVE seed) | ⏳ pending | ~150 | Onda 5 estável 90d + Wagner approval final | mysqldump validado + smoke biz=4 | **90d** wait + 2d trabalho |
| **Total** | — | — | **~884** | — | — | **~124d corridos ≈ 17-18 semanas** |

**Reduções vs ADR 0172 original:**
- Trabalho ativo: 18d → **~5d úteis** (-72%) — eliminadas implementações migration + view bridge
- Tempo corrido: 184d → **~124d** (-33%) — eliminado 14d wait E3→E4 + ainda mantém os 30d canary + 90d wait E5→E6 (esses são proteções humano-limitadas, não negociáveis)
- LOC entregue: ~1.260 → **~884** (-30%)

### Critérios pra reverter SKIP

Se durante o canary 30d aparecer:
- ≥1 hit `/accounting/*` em prod logs **com Referer interno** (algum bookmark/link interno ainda referenciando) → analisar caso-a-caso; bridge pode reentrar se for cliente pagante
- Qualquer feature request em SPEC.md/charter pedindo dados de `chart_of_accounts`/`journal_entries` → não fazer revert; criar US-FIN-NNN no Financeiro como replacement (cf ADR 0105 cliente como sinal)
- Sinal de novo cliente prospect que exija ECD/ECF nativo no sistema → Portal Advisor (US-FIN-037 já em prod) cobre via export TXT pra contador. Não justifica reverter SKIP.

Critério único de reverter: descobrir DADOS reais em tabelas `chart_of_accounts` ou `journal_entries` durante o canary (não esperado, mas DBA error humano poderia inserir). Re-rodar audit a cada 7d durante canary pra detectar.

## Consequências

### Positivas

- **Time-to-deprecate** encurtou de 26 semanas → ~17-18 semanas (-33%). Wagner libera bandwidth pra outros módulos prioritários (Financeiro Onda 35+ FSM Vestuario Ponto Mobile Initiatives).
- **Risco reduzido** — não escrever migration ou view bridge significa zero code novo pra revisar, testar, manter. Less is more.
- **Captura empírica** do princípio "audit antes de timeline" — pattern aplicável às próximas deprecações (SRS, Officeimpresso quando vier).

### Negativas

- **Rollback fica mais brutal** se algum cliente tiver Accounting heavy (não esperado, mas teorético): sem bridge, rollback requer ressuscitar Modules/Accounting do git + rebuild routes. Probabilidade quase zero dado o audit.
- **Sinal de dependência oculta** difícil de detectar: se algum job/cron/import futuro tentar criar JournalEntry programaticamente, vai falhar silenciosamente ao invés de funcionar via bridge. Mitigação: Pest test em Onda 5 valida zero referência ao namespace `Modules\Accounting\`.

### Neutras

- Permissions Spatie `accounting.*` (11 perms seedadas) **continuam existindo até Onda 5** (defesa em profundidade). Ter permissão sem rota viva é inerte — não causa erro nem leak.
- `account_subtypes` (15 seed) + `account_detail_types` (139 seed) **ficam pra ARCHIVE em Onda 6** — taxonomia GAAP genérica, sem PII, sem business_id.

## Refs

- [ADR 0172](0172-deprecar-modulo-accounting-fundir-financeiro.md) — Decisão mãe (esta ADR amends, não supersedes)
- [ADR 0173](0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md) — Errata drift nomes tabelas
- [ADR 0094](0094-constituicao-v2-7-camadas-8-principios.md) — Constituição v2 (append-only respeitado: ADR 0172 não é editada, esta apêndice corrige cronograma)
- [ADR 0105](0105-cliente-como-sinal-guiar-sem-mandar.md) — Cliente como sinal qualificado (justifica não fazer migration "preventiva" sem dados)
- [ADR 0106](0106-recalibracao-velocidade-fator-10x-ia-pair.md) — Fator 10x IA-pair (estimates já recalibrados)
- [DEPRECATION-PLAN.md §Errata 2026-05-20](../requisitos/Accounting/DEPRECATION-PLAN.md) — apêndice aplicado nesta sessão
- [Session log audit completo](../sessions/2026-05-20-audit-accounting-prod-zero-rows.md)
- PR #1244 (errata BRIEFING) + PR #1246 (UI freeze 410) — Ondas 1+2 em prod
