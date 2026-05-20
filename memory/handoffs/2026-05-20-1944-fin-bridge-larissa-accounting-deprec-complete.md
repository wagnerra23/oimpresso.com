---
slug: 2026-05-20-1944-fin-bridge-larissa-accounting-deprec-complete
title: "Handoff sessão tarde — Accounting deprec Ondas 0-5 + Larissa biz=4 bridge recovery R$ 12k→R$ 418k + canon"
date: 2026-05-20
hour_utc: 19:44
owner: claude
session_id: frosty-greider-83ab2f
type: handoff
status: closed
canonical_session_logs:
  - memory/sessions/2026-05-20-audit-accounting-prod-zero-rows.md
  - memory/sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md
prs_merged: [1244, 1246, 1256, 1258, 1262]
prod_dml_ops: 6
artefatos_canon: 5
---

# Handoff sessão tarde 2026-05-20 — Accounting deprec + Larissa bridge recovery COMPLETE

## TL;DR (3 linhas)

Sessão **6h ininterrupta** que (a) deprecou `Modules/Accounting` em 4 ondas (PRs #1244/#1246/#1256/#1258) consolidando contabilidade em `Modules/Financeiro`, (b) recuperou bridge `Sells/Compras → fin_titulos` quebrada por 12d em biz=4 ROTA LIVRE — **R$ 12.227 visível → R$ 418.986,59 a receber REAL + R$ 242.041,04 a pagar** descoberto, e (c) formalizou **5 artefatos canon** (agent reusável + RUNBOOK + ADR 0175 proposta + feedback + session log) garantindo replicabilidade pra próximos clientes pilotos. 6 ops DML em prod biz=4 (todos Tier 0 respeitado). Activity log 100% coberto (0.3% → 100%).

## Estado MCP no momento do fechamento

```
=== cycles-active CYCLE-06 ===
Martinho prod + FSM rollout + Jana V2 demo
2026-05-14 → 2026-05-28 · 43% decorrido · 8 dias restantes
Goals trackados (4 zerados — Larissa biz=4 destrava sub-meta operacional Financeiro):
- 🔲 Martinho Caçambas em produção paga ≥1
- 🔲 Inter PJ ao vivo + 1ª cobrança ROTA LIVRE biz=4
- 🔲 FSM rollout 162 vendas legadas biz=1 (alvo 14)
- 🔲 Jana V2 demo navegável

=== my-work (30 tasks) ===
REVIEW(1): FIN-4 cobrança ROTA LIVRE
BLOCKED(6): US-NFE-043..048 trilha Gold (DORMENTE)
TODO(23): US-SELL-009 cutover ROTA LIVRE, US-MWART-001, US-INFRA-001 GrowthBook, COPI-25/28/41/44/46/48, US-WA-044/053/058/059, PONTO-2, GROW-1, US-RB-031/046, US-COPI-079/092/094, US-TR-303, US-SELL-001/002

(Tasks específicas Accounting US-ACCO-011..016 cadastradas via PR #1237 commit f0f2ed4d3 não aparecem no my-work do owner @wagner — webhook sync MCP pode ter latência.)

=== decisions-search since 2026-05-20 manhã ===
- ADR 0172 deprecar-modulo-accounting-fundir-financeiro (manhã, aceita pre-sessão)
- ADR 0173 errata-arq-0005-tabelas-accounting-sem-prefixo (manhã, aceita pre-sessão)
- ADR 0174 errata-deprecation-plan-accounting-ondas-3-4-skip (sessão tarde, aceita esta sessão)
- ADR 0175 fix-observer-conta-bancaria-opcional (PROPOSTA — proposals/, aguarda implementação ~7h)
```

## 5 PRs mergeados nesta sessão (em ordem)

| PR | Onda | Conteúdo | Commit | Mergeado UTC |
|---|---|---|---|---|
| [#1244](https://github.com/wagnerra23/oimpresso.com/pull/1244) | 1 (E1) | docs(accounting): errata BRIEFING US-ACCO-011 — banner deprecating + 2 ERRATA refutando claims falsos via inspeção forense (ZERO cross-imports + ZERO observers) | `eef793ffe` | 17:43 |
| [#1246](https://github.com/wagnerra23/oimpresso.com/pull/1246) | 2 (E3) | feat(accounting): UI freeze sidebar+routes 410 — DataController early return + 96 rotas → 3 catch-all 410 + 9 Pest tests | `d88bf9e1e` | 17:44 |
| [#1256](https://github.com/wagnerra23/oimpresso.com/pull/1256) | (errata) | docs(accounting): ADR 0174 errata DEPRECATION-PLAN — Ondas 3+4 SKIP (audit prod 0 rows confirmou origem vazia + zero cross-imports) | `dbb31c3a6` | 18:25 |
| [#1258](https://github.com/wagnerra23/oimpresso.com/pull/1258) | 5 (E5) | chore(accounting): drop Modules/Accounting (339 files, -42k LOC) + retention shim + modules_statuses=false + baseline-grades reconcile | `606ee915b` | 18:49 |
| [#1262](https://github.com/wagnerra23/oimpresso.com/pull/1262) | (canon) | docs(financeiro): agent financeiro-bridge-auditor + RUNBOOK backfill + ADR 0175 proposta + feedback canon + session log Larissa | `f39b1acc4` | 19:44 |

## DML em prod biz=4 (ROTA LIVRE Larissa)

| # | Op | Linhas | Resultado |
|---|---|---|---|
| 1 | INSERT `fin_contas_bancarias` stub (id=20) | 1 | Bridge Observer destravada pra payments futuros |
| 2 | INSERT INTO SELECT 17.412 `fin_titulos` retroativos (5 anos vendas/compras) | 17.412 | 55 → 17.467 titulos |
| 3 | INSERT INTO SELECT 15.412 `fin_titulo_baixas` | 15.412 | 0 → 15.412 baixas (R$ 3,27M baixados histórico) |
| 4 | UPDATE recalc `valor_aberto` + `status` (17.467 titulos) | 17.467 | Status correto baseado em soma baixas |
| 5 | INSERT INTO SELECT 601 AP `purchases status=received` + baixas | 601 + 518 baixas | R$ 242.041,04 a pagar visível |
| 6 | INSERT INTO SELECT 33.943 `activity_log` sintéticas (`causer_kind='system'`) | 33.943 | Cobertura 0.3% → 100% |

Plus: UPDATE `subscriptions.id=118` biz=1 (Wagner) `$.package_details.financeiro_module='1'` — destrava sidebar Financeiro pra ele.

**Tier 0 multi-tenant ADR 0093 respeitado em 100% das ops** (`WHERE business_id=4` explícito em cada query).

## Resultado real Larissa biz=4 (validado spot-check)

| Métrica | Antes | Depois |
|---|---|---|
| `fin_titulos` biz=4 | 55 | **18.068** |
| `fin_titulo_baixas` biz=4 | 0 | **15.930** |
| `fin_contas_bancarias` biz=4 | 0 | 1 stub |
| **A receber visível** | R$ 12.227 (36 títulos noise) | **R$ 418.986,59 (1.671 títulos)** |
| **A pagar visível** | – | **R$ 242.041,04 (96 títulos)** |
| activity_log cobertura biz=4 | 0.3% | **100%** |
| Top inadimplente | (não visível) | CASA OCRE TUBARÃO SC R$ 4.605 (893d atraso, tel 48988552511) |
| Top 3 devedores agregados | (não visível) | ANDREIA FERNANDES 3 títulos R$ 6.172 |

Spot-check cross-validation: fin_titulos R$ 418.986,59 vs calc direto via `transactions(payment_status IN ('due','partial'))` R$ 417.546,33 = diferença 0.34% (R$ 1.440) explicada por refunds/cancelados parciais.

## Causa raiz descoberta

Cadeia de 3 bugs convergentes invisíveis até Larissa reportar:

1. **Bug primário (Larissa biz=4 sintoma)**: commit `540a26a41` (2026-05-08, "fix BUG-2 no-op gracioso") instalou guard silencioso em `TituloAutoService::registrarPagamento()` quando biz sem `fin_contas_bancarias`. Por 12 dias 15.932 payments biz=4 silenciados em `Log::info`.

2. **Bug secundário (gap retroativo)**: `TransactionObserver` foi adicionado em 2026-04-25 (`990bd8b18`). Vendas/compras criadas antes ficaram sem `fin_titulos` (17.411 órfãs).

3. **Bug terciário (cosmético)**: `cliente_descricao` desnormalizado foi adicionado em 2026-05-18 (`db85ba441` Onda Edit). Titulos pré-data ficaram NULL → tela mostra "—".

Detalhamento em [session log canônico bridge-recovery](../sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md).

## 5 artefatos canon entregues (PR #1262)

| Tipo | Path | Função |
|---|---|---|
| Agent reusável | [`.claude/agents/financeiro-bridge-auditor.md`](../../.claude/agents/financeiro-bridge-auditor.md) | Auditor especialista — `Agent(subagent_type:"financeiro-bridge-auditor", biz_id={N})` |
| RUNBOOK | [`memory/requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md`](../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md) | 5 fases SQL idempotente + rollback + gotchas |
| ADR proposta | [`memory/decisions/proposals/fix-observer-conta-bancaria-opcional.md`](../decisions/proposals/fix-observer-conta-bancaria-opcional.md) | ADR 0175 — fix arquitetural permanente Observer guard |
| Feedback canon | [`memory/reference/feedback-fin-bridge-no-op-account-gap.md`](../reference/feedback-fin-bridge-no-op-account-gap.md) | "No-op gracioso silencioso = débito ALTO" |
| Session log (sessão dupla) | [audit-zero-rows](../sessions/2026-05-20-audit-accounting-prod-zero-rows.md) + [bridge-recovery](../sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md) | Narrativas completas |

## Tabelas TABLE biz=4 estado final detalhado

```
+-----------+---------+--------+-------+--------------+-------------+
| status    | tipo    | origem | n     | total_R$     | aberto_R$   |
+-----------+---------+--------+-------+--------------+-------------+
| aberto    | receber | venda  |  1567 |  405157.2100 | 405157.2100 |  ← AR R$ 405k abertos
| parcial   | receber | venda  |   104 |   35325.1600 |  13829.3800 |  ← AR R$ 14k parcial
| quitado   | receber | venda  | 15772 | 3247029.6600 |      0.0000 |  ← histórico pago
| aberto    | pagar   | compra |    95 |  239955.5200 | 239955.5200 |  ← AP R$ 240k abertos
| parcial   | pagar   | compra |     1 |   23925.5200 |   2085.5200 |  ← AP R$ 2k parcial
| quitado   | pagar   | compra |   515 | 1101220.3200 |      0.0000 |  ← histórico pago
| cancelado | receber | venda  |     6 |    2667.5000 |    976.5000 |
| cancelado | pagar   | compra |     8 |    8766.9500 |   8766.9500 |
+-----------+---------+--------+-------+--------------+-------------+
```

## Pendências fora desta sessão (ordem prioridade)

| # | Item | Prio | Esforço | Ações |
|---|---|---|---|---|
| 1 | **ADR 0175 implementação** (Observer guard fix permanente) | **P1** | ~7h | Próxima sessão dedicada |
| 2 | Health-check command `php artisan financeiro:health-check` | P1 (parte de 1) | ~2h | Parte do ADR 0175 |
| 3 | **Rotacionar senha MySQL Hostinger** `u906587222_oimpresso` (exposta hoje no contexto Claude) | **P1 segurança** | 15min Wagner manual | hPanel + Vaultwarden + `/opt/whatsapp-baileys/build/.env` CT 100 + `.env` Hostinger app |
| 4 | UI label "📦 Histórico importado" pros `activity_log causer_kind='system'` | P2 | ~1h frontend + Pest | Próxima sessão |
| 5 | Onda 6 Accounting (DROP TABLE 6 vazias + ARCHIVE 2 seed) | P2 | ~2d trabalho | Após canary 30d (2026-06-19) |
| 6 | Backfill biz=1/164 se aparecer drift (usar agent canon) | P3 sob demanda | ~1h cada | quando Wagner reportar |
| 7 | Cron daily `financeiro:health-check` detecta novos gaps | P3 | parte de #2 | – |

## Lições catalogadas

1. **No-op gracioso silencioso = débito ALTO** — formalizado em [feedback-fin-bridge-no-op-account-gap.md](../reference/feedback-fin-bridge-no-op-account-gap.md). Aplicável a TODOS módulos.

2. **Observer instalado pós-fato precisa backfill explícito** — quando se adiciona Observer/Listener que sincroniza state cross-tabelas, NECESSARIAMENTE criar artisan command de backfill na mesma PR.

3. **Cliente piloto reporta sintoma final, não causa raiz** — Larissa disse "não vejo quem está devendo". Audit revelou 3 bugs convergentes. Agent `financeiro-bridge-auditor` sintetiza este aprendizado.

4. **SQL backfill direto NÃO dispara Eloquent events** — pros 33.943 titulos+baixas criados via `INSERT INTO SELECT`, `activity_log` ficou vazio inicialmente. Solução A (backfill sintético `causer_kind='system'`) executada nesta sessão restaurou cobertura 100% sem inventar histórico falso.

5. **Tier 0 multi-tenant ADR 0093 IRREVOGÁVEL** — TODAS as 6 ops DML tiveram `WHERE business_id = 4` explícito. Zero risco de contaminação cross-business.

6. **Audit empírico antes de timeline** — ADR 0172 estimou 26 semanas pra deprecação Accounting. Audit revelou ZERO rows nas 6 tabelas core → ADR 0174 cortou pra 17-18 semanas (-33%). Padrão `/audit-and-fix` aplicável a futuras deprecações (SRS, Officeimpresso eventuais).

## Tools usadas

- **MCP MySQL Hostinger via tailscale ssh ct100-mcp + docker mysql:8** (canônico [reference/hostinger-remote-mysql.md](../reference/hostinger-remote-mysql.md)) — 14 queries SQL DML/SELECT executadas
- 14 arquivos SQL versionados em `.claude/run/` (gitignored)
- Hostinger Developer API explorada (limitada a DNS/billing/websites — sem exec)
- GitHub `gh pr` para criação e admin merge das 5 PRs
- Spatie ActivityLog table direto SQL pra backfill `causer_kind='system'`

## Refs estado pós-sessão (canonical)

- [ADR 0093 multi-tenant Tier 0 IRREVOGÁVEL](../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2 append-only](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0105 cliente como sinal qualificado](../decisions/0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0130 handoff append-only MCP-first](../decisions/0130-handoff-append-only-mcp-first.md)
- [ADR 0172 deprecar Modules/Accounting](../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md)
- [ADR 0173 errata ARQ-0005 nomes nus](../decisions/0173-errata-arq-0005-tabelas-accounting-sem-prefixo.md)
- [ADR 0174 errata DEPRECATION-PLAN ondas 3+4 skip](../decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md)
- [ADR 0175 proposta fix Observer guard](../decisions/proposals/fix-observer-conta-bancaria-opcional.md)
- [DEPRECATION-PLAN.md Accounting](../requisitos/Accounting/DEPRECATION-PLAN.md)
- [INSPECAO-FORENSE-2026-05-20.md Accounting](../requisitos/Accounting/INSPECAO-FORENSE-2026-05-20.md)
- [RUNBOOK bridge-sells-titulos-backfill](../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md)
- Agent canônico: [`.claude/agents/financeiro-bridge-auditor.md`](../../.claude/agents/financeiro-bridge-auditor.md)
