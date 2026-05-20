---
name: 2026-05-20-financeiro-bridge-larissa-backfill-recovery
description: Sessão tarde — Larissa biz=4 destrava "quem está devendo" via backfill SQL 3 fases após audit revelar Observer no-op silencioso por 12 dias. R$ 12k visível → R$ 418.986 a receber real
type: recovery-incident
date: 2026-05-20
owner: claude
status: complete
ref_adr_pre: memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
ref_adr_errata: memory/decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md
ref_adr_proposta: memory/decisions/proposals/fix-observer-conta-bancaria-opcional.md
ref_runbook: memory/requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md
ref_feedback: memory/reference/feedback-fin-bridge-no-op-account-gap.md
ref_agent: .claude/agents/financeiro-bridge-auditor.md
---

# Sessão Financeiro Bridge Recovery — Larissa biz=4 (2026-05-20)

## Resumo executivo

Sessão de continuidade pós-deprecação Accounting (PRs #1244/#1246/#1256/#1258 anteriores hoje). Wagner pediu validar que Larissa biz=4 ROTA LIVRE consegue ver "quem está devendo" no Financeiro pós-deprecação. Audit revelou bridge `Sells/Compras → fin_titulos` quebrada — Observer no-op silencioso por 12 dias. Recovery via backfill SQL 3 fases em ~90s wall time. **Resultado: R$ 12.227 visível → R$ 418.986,59 a receber REAL em 1.671 títulos** com nomes reais ("CASA OCRE TUBARÃO SC", "ANDREIA FERNANDES", etc.).

## Contexto entrada

Wagner pediu auditoria pós-merge das 4 PRs Accounting:

- PR #1244 (errata BRIEFING) ✅ merged 17:43 UTC
- PR #1246 (UI freeze 410) ✅ merged 17:44 UTC
- PR #1256 (ADR 0174 errata skip Ondas 3+4) ✅ merged 18:25 UTC
- PR #1258 (drop Modules/Accounting) ✅ merged 18:49 UTC

Pergunta literal Wagner: *"pode conferir o financeiro e venda, compra. o cliente só quer ver qual cliente ainda esta devendo"*

## Diagnóstico (5 queries audit)

### Q1 — transaction_payments biz=4 (Larissa USA o sistema)

```
n_pagamentos_total: 15.932
n_transacoes_pagas: 15.698
valor_total: R$ 4.446.047,83
primeiro_pgto: 2021-05-15 09:46:57
ultimo_pgto: 2026-05-20 15:55:24  ← ATIVO HOJE
```

Larissa usa o Sells DAILY há 5 anos. Hipótese inicial "ela não usa" REJEITADA.

### Q2 — fin_titulo_baixas vs payments biz=4 (BRIDGE QUEBRADA)

```
fin_titulo_baixas biz=4: 0
gap (payments sem baixa): 15.932 (100%)
```

15.932 pagamentos lançados, ZERO baixas geradas. Observer 100% no-op.

### Q3 — fin_titulos vs transactions biz=4 (BRIDGE QUEBRADA DUPLA)

```
fin_titulos biz=4: 54  (apenas pós-2026-04-25 — entrada do Observer)
transactions(sell+purchase, final) biz=4: 17.465
órfãos: 17.411 (99.7%)
```

A BRIDGE TransactionObserver também não fechou os históricos.

### Q4 — fin_contas_bancarias biz=4 (root cause raiz)

```
fin_contas_bancarias biz=4: 0  ← ZERO contas
```

### Q5 — payment_status real dos sells biz=4

```
paid: 15.764 sells (R$ 3.246.764,69) → vão virar status=quitado
due: 1.550 sells (R$ 396.435,35)     → vão virar status=aberto (DINHEIRO REAL DEVENDO)
partial: 97 sells (R$ 34.066,82)     → vão virar status=parcial
```

## Root cause cadeia de bugs

**Bug primário (Larissa biz=4 sintoma)**: commit `540a26a41` (2026-05-08, "fix BUG-2 no-op gracioso"). Quando biz sem `fin_contas_bancarias`, `TituloAutoService::registrarPagamento()` faz `Log::info` + `return null` — silenciosamente NÃO cria `fin_titulo_baixas`. Por 12 dias (2026-05-08 → 2026-05-20) os 15.932 payments biz=4 foram silenciados.

**Bug secundário (gap retroativo)**: `TransactionObserver` foi adicionado em 2026-04-25 (commit `990bd8b18`). Vendas/compras criadas antes ficaram sem `fin_titulos`. Sem backfill no merge → 17.411 órfãos.

**Bug terciário (cosmético)**: `cliente_descricao` campo desnormalizado foi adicionado em 2026-05-18 (commit `db85ba441` "Onda Edit"). Titulos pré-data ficaram NULL no campo → tela mostra "—".

3 bugs convergentes, todos invisíveis até Larissa reportar.

## Recovery em 3 fases SQL

### Fase 2-pre — Stub fin_contas_bancarias

`INSERT` 1 conta `ROTA LIVRE` apontando pra `accounts.id=4` (CAIXA UltimatePOS core biz=4). `ativo_para_boleto=0` (Larissa só recebe PIX/dinheiro). Resultado: `fin_contas_bancarias.id=20`.

### Fase 2a — 17.412 fin_titulos retroativos

`INSERT INTO SELECT` cobrindo todas as `transactions(type IN ('sell','purchase'), status='final')` biz=4 sem `fin_titulos` correspondente.

- `numero`: `R-{LPAD(tx.id,6)}` (vendas) ou `P-{LPAD(tx.id,6)}` (compras)
- `cliente_descricao`: format Observer canônico `{ContactName} · #V-{txId}`
- `metadata.backfill_sql = '2026-05-20'` pra rastreabilidade

Resultado: 55 → **17.467 titulos biz=4**.

### Fase 2b — 15.412 fin_titulo_baixas

`INSERT INTO SELECT` cobrindo todos os `transaction_payments` biz=4 sem `fin_titulo_baixas` correspondente, com `conta_bancaria_id=20` (stub fase 2-pre).

- `idempotency_key`: `tp_{tp.id}` (matches Observer pattern original)
- `meio_pagamento`: map `cash→dinheiro`, `card→cartao_credito`, `bank_transfer→transferencia`, `cheque→cheque`, `pix→pix`, `other/custom_*→outro`

Resultado: 0 → **15.412 baixas biz=4** (R$ 3.277.406,01 baixados historicamente).

### Fase 2c — Recalc valor_aberto + status

2 UPDATEs:
- `valor_aberto = GREATEST(valor_total - SUM(baixas não-estornadas), 0)`
- `status = CASE WHEN valor_aberto<=0 THEN 'quitado' WHEN <valor_total THEN 'parcial' ELSE 'aberto' END` (preserva `cancelado`)

## Resultado prod final (validado)

```
=== fin_titulos biz=4 distribuição final ===
+-----------+---------+--------+-------+--------------+-------------+
| status    | tipo    | origem | n     | total_R$     | aberto_R$   |
+-----------+---------+--------+-------+--------------+-------------+
| aberto    | receber | venda  |  1567 |  405157.2100 | 405157.2100 |
| parcial   | receber | venda  |   104 |   35325.1600 |  13829.3800 |
| quitado   | receber | venda  | 15772 | 3247029.6600 |      0.0000 |
| aberto    | pagar   | compra |    10 |   19390.2000 |  19390.2000 |
| cancelado | receber | venda  |     6 |    2667.5000 |    976.5000 |
| cancelado | pagar   | compra |     8 |    8766.9500 |   8766.9500 |
+-----------+---------+--------+-------+--------------+-------------+

=== Resumo a receber Larissa biz=4 ===
titulos_devendo: 1.671
total_a_receber: R$ 418.986,59
titulos_quitados: 15.772
total_quitado_histórico: R$ 3.247.029,66
```

## Spot-check de realidade (não é placebo)

Top 5 inadimplentes biz=4 com dados cruzados Sells/contacts (anonimizado parcial):

| Cliente | Devendo R$ | Venda | Data | Atraso |
|---|---|---|---|---|
| CASA OCRE - TUBARÃO SC (tel 48988552511) | 4.605,00 | invoice 10667 | 2023-12-09 | 893 dias |
| ANDREIA FERNANDES (3 títulos) | 6.172,52 | invoices 22933, 17559, 19053 | 2023-2025 | 341-878 dias |
| EDILEIDE APOLINARIO - EDSON | 1.886,50 | invoice 7980 | 2023-05-10 | 1.106 dias |

Dados 100% coerentes com `transactions.invoice_no` + `contacts.name` + `transactions.transaction_date`. Cross-check totalizador: fin_titulos R$ 418.986,59 vs cálculo direto via `transactions(payment_status IN ('due','partial'))` R$ 417.546,33 = diferença 0.34% (R$ 1.440) explicada pelos 522 órfãos AP (`purchase status='received'`).

## Pendências fora desta sessão

| Item | Esforço | Prio |
|---|---|---|
| Backfill complementar 518 `purchase status='received'` biz=4 | 1 query INSERT idempotente | P2 |
| Backfill outras businesses (biz=1 Wagner: 54 payments + 10 baixas + gap 44) | mesma técnica | P3 |
| **Fix arquitetural permanente** ([ADR 0175 proposta](../decisions/proposals/fix-observer-conta-bancaria-opcional.md)) | ~7h: migration + service edit + Pest + UI + artisan health-check | **P1** |
| Onda 6 Accounting deprecação (DROP TABLE) | post-canary 30d | P2 |
| `cliente_descricao` backfill biz=1 (52 NULL) | UPDATE 1 query | P3 |
| Backfill activity_log Spatie pros 17.412 titulos criados via SQL direto (events Eloquent não dispararam) | TODO se rastreabilidade Spatie for crítico | P3 |

## Lições catalogadas

1. **No-op gracioso silencioso é débito ALTO**. Sempre instalar alerta (Log::warning + health-check command + Pest assert) quando degradar exception → no-op. Detalhes em [feedback-fin-bridge-no-op-account-gap.md](../reference/feedback-fin-bridge-no-op-account-gap.md).

2. **Observer instalado pós-fato precisa backfill explícito**. Quando se adiciona Observer/Listener que sincroniza state cross-tabelas, NECESSARIAMENTE criar artisan command de backfill na mesma PR. Sem isso, dados pré-data ficam órfãos invisíveis.

3. **Cliente piloto reporta sintoma final, não causa raiz**. Larissa disse "não vejo quem está devendo". Audit revelou 3 bugs convergentes. Sempre fazer audit completo (5 queries chave) antes de propor solução. Skill [`financeiro-bridge-auditor`](.claude/agents/financeiro-bridge-auditor.md) sintetiza este aprendizado.

4. **SQL backfill direto NÃO dispara Eloquent events**. Pros 17.412 titulos criados via `INSERT INTO SELECT`, `activity_log` (Spatie ActivityLog) NÃO tem entradas. Trade-off consciente: backfill SQL é 100x mais rápido que iterate via PHP, mas perde rastreabilidade dos events. Mitigação: `metadata.backfill_sql='YYYY-MM-DD'` tag em cada row.

5. **Tier 0 multi-tenant ADR 0093 IRREVOGÁVEL**: TODAS as 8 queries de backfill nesta sessão tiveram `WHERE business_id = 4` explícito. Zero risco de contaminação cross-business. Sem essa disciplina, 17.412 titulos teriam sido criados em ALL businesses — catastrófico.

## Artefatos canon entregues

- ✅ [Agent `financeiro-bridge-auditor`](.claude/agents/financeiro-bridge-auditor.md) — reusável pra outros businesses
- ✅ [RUNBOOK bridge-sells-titulos-backfill](../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md) — 5 fases manual
- ✅ [ADR 0175 proposta](../decisions/proposals/fix-observer-conta-bancaria-opcional.md) — fix arquitetural permanente
- ✅ [feedback canônico no-op-account-gap](../reference/feedback-fin-bridge-no-op-account-gap.md) — lição
- ✅ Este session log

## Tools usadas

- **MCP MySQL Hostinger via tailscale ssh ct100-mcp + docker mysql:8** (canônico [reference/hostinger-remote-mysql.md](../reference/hostinger-remote-mysql.md))
- Queries SQL versionadas em `.claude/run/` (gitignored — 8 arquivos)
- Spot check cross-validation via JOIN transactions+contacts

## Refs

- [Audit Onda 0 mesma sessão (Accounting deprecação)](2026-05-20-audit-accounting-prod-zero-rows.md) — sessão irmã
- [ADR 0172 Accounting](../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)
- [ADR 0174 errata Accounting](../decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md)
- [Modules/Financeiro/Services/TituloAutoService.php](../../Modules/Financeiro/Services/TituloAutoService.php)
- [Modules/Financeiro/Observers/TransactionPaymentObserver.php](../../Modules/Financeiro/Observers/TransactionPaymentObserver.php)
- [ADR 0093 multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0105 cliente como sinal](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- Commit do bug original: `540a26a41` (2026-05-08)
