---
title: RecurringBilling biz=1 retroatividade completa — US-RB-003 + 161 subs + 3389 invoices históricas
date: 2026-06-07
time: 18:55 BRT
owner: Eliana [E]
slug: recurring-billing-retroatividade-completa
prs: [2384]
related_session: memory/sessions/2026-06-07-recurring-billing-retroatividade-eliana.md
---

# Handoff — RecurringBilling biz=1 retroatividade ponta-a-ponta (sessão Eliana)

## TL;DR

Sessão de continuidade direta da migração WR Comercial (madrugada). Eliana queria configurar a cobrança recorrente do oimpresso pra ter visibilidade total do faturamento WR2 — quem paga, quem deve, churn, histórico.

Feito ponta-a-ponta:
- ✅ Limpou seed Wagner em rb_* biz=1 (41 registros fake)
- ✅ Criou **109 planos** + **109 assinaturas ativas** (MRR R$ [redacted Tier 0]/mês)
- ✅ Construiu **US-RB-003** (PR #2384) — gerador automático `rb:generate-invoices` + scheduler daily 03:00 BRT
- ✅ Retroatividade 2024-2026: **52 subs canceladas** + **3389 invoices históricas** (R$ [redacted Tier 0] recebido + R$ [redacted Tier 0] atrasado)
- ✅ Tier 0 IRREVOGÁVEL preservado em todas operações (só biz=1, nenhuma outra biz tocada)

## Estado MCP no momento do fechamento

MCP `mcp.oimpresso.com` retornou error (`brief-fetch` fallback ativado — mesmo cenário da madrugada). Snapshot via filesystem + smoke prod:

- `git log` Hostinger prod: commit `46766430a` (US-RB-003) em produção desde 21:21 BRT
- `gh pr view 2384`: state=MERGED, mergedAt=2026-06-07T21:21:39Z
- Smoke `rb:generate-invoices --dry-run --business=1` em prod: command registrado, retorna 109 candidatos quando simulado 30/jul/2026
- Tela `https://oimpresso.com/recurring-billing/faturas` ao vivo: **3389 faturas · 137 atrasadas · MRR R$ [redacted Tier 0] · CHURN 1.2%**
- Tasks MCP locais: #1-32 completed (3 deleted — tasks 25/26/27 superadas pelo escopo expandido)

## O que foi feito (5 fases)

### Fase 1 — Limpa rb_* biz=1 (41 registros seed Wagner)
Backup defensivo `dump-rb-biz1-2026-06-07.sql` (22 KB · 41 INSERTs) + DELETE em ordem FK em transação:
- 9 rb_charge_attempts · 2 rb_subscription_favorites · 3 rb_subscription_notes
- 4 rb_invoices · 18 rb_subscriptions · 5 rb_plans
- Preservado: rb_boleto_credentials + pg_webhook_events (configuração)

### Fase 2 — 109 planos mensalidade ativos
Investigação `fin_titulos` jun/2026 (plano_conta_id=332 MENSALIDADE DO SOFTWARE + observação `LIKE 'MENSALIDADE REFERENTE AO MÊS DE %'`): 120 títulos → 11 descartados (RENEGOCIAÇÃO/PARCELAMENTO) → 109 mensalidades puras + 1 fix CYRINO ENGENHARIA (cliente_id=NULL → 31257 por match nome).

109 INSERT `rb_plans` com nome="Mensalidade <RAZÃO>", slug=`mensalidade-cliente-{id}`, ciclo=monthly, ativo=1, fiscal_type=none, metadata com origem.

MRR estimado biz=1: **R$ [redacted Tier 0]/mês**.

### Fase 3 — Construir US-RB-003 (PR #2384)
`Modules/RecurringBilling/Providers/RecurringBillingServiceProvider::registerCommandSchedules()` estava VAZIO. Sem isso, as 109 subs ficariam dormentes.

Arquivos novos:
- `Modules/RecurringBilling/Services/InvoiceGeneratorService.php` (~200 linhas) — lógica de domínio idempotente (YYYY-MM), avança next_due_date += ciclo via `addMonthsNoOverflow` (preserva anchor dia 31 → fev), cria SubscriptionEvent kind=event-charge na timeline
- `Modules/RecurringBilling/Console/Commands/GenerateInvoicesCommand.php` (~100 linhas) — CLI `rb:generate-invoices --business=N --date=YYYY-MM-DD --lead-days=N --dry-run --detail`
- `Modules/RecurringBilling/Providers/RecurringBillingServiceProvider.php` (+20 linhas) — registro command + schedule daily 03:00 BRT env=live withoutOverlapping + onFailure log
- `Modules/RecurringBilling/Tests/Feature/InvoiceGeneratorServiceTest.php` (~280 linhas) — Pest 8 cenários SQLite in-memory (cria/idempotência/avança/skip paused/dry-run/lead-days/cross-tenant Tier 0/SubscriptionEvent)

CI: PHPStan/Larastan reclamou `Plan` props (`$plan->ciclo`/`$plan->valor`) — fix cirúrgico com `getAttribute()` + `/** @var Plan|null $plan */` PHPDoc (sem adicionar `@property` global pra não destravar análise em cascata).

Merge --admin commit `46766430a`. Smoke prod confirmou command registrado + funcional.

### Fase 4 — 109 assinaturas ativas
Base TSV via JOIN fin_titulos jun/2026 × rb_plans (slug) + COALESCE contacts.name. 110 linhas (header + 109).

109 INSERT `rb_subscriptions`:
- status=active
- start_date/next_due_date/billing_anchor_date = 2026-07-XX (DATE_ADD(venc_jun, INTERVAL 1 MONTH))
- conta_bancaria_id = mesma do título (replicar banco que cliente já paga)
- payment_method=boleto · metadata com cliente/plano/source

Distribuição: 36 C6 · 26+25 Inter · 22 Cora = 109. Distribuição dia jul/2026: 90 dia 10 · 9 dia 15 · 4 dia 20 · 5 dia 25 · 1 dia 30.

### Fase 5 — Retroatividade 2024-2026 (escopo expandido)
Investigação ampliada (jan/2024-jun/2026):
- 3.418 mensalidades · 161 clientes únicos · R$ [redacted Tier 0]
- 109 ativos + **52 cancelados** (sem mensalidade jun/2026)
- Distribuição cancelamento: 27 em 2024 · 20 em 2025 · 5 em 2026

**Etapa 1** — 52 plans + 52 subs canceladas + UPDATE 109 ativas (start_date real 2010-2026)
**Etapa 2** — 1351 invoices 2024 (1297 paid R$ [redacted Tier 0] · 54 overdue R$ [redacted Tier 0])
**Etapa 3** — 1375 invoices 2025 (1342 paid R$ [redacted Tier 0] · 33 overdue R$ [redacted Tier 0])
**Etapa 4** — 663 invoices 2026 (506 paid R$ [redacted Tier 0] · 50 overdue R$ [redacted Tier 0] · 107 open R$ [redacted Tier 0])

Status map fin_titulos → rb_invoices:
- `quitado`/`parcial` → `paid` (pago_em = MAX(data_baixa))
- `aberto` + venc<hoje → `overdue`
- `aberto` + venc>=hoje → `open`
- `cancelado` → `canceled`

Numero documento padrão estabelecido:
- **Histórico legacy:** `RB-{sub_id}-{YYYY-MM}-L{titulo_id}` (sufixo L = Legacy)
- **Futuras (cron):** `RB-{sub_id}-{YYYY-MM}` (US-RB-003)

## Resultado final visível na tela /recurring-billing/faturas

| KPI | Valor |
|---|---|
| Faturas total | **3.389** |
| Atrasadas | 137 |
| Pago este mês | R$ [redacted Tier 0] (jun começou) |
| Pendente | R$ [redacted Tier 0] (107 jul/2026 abertas) |
| Atrasado | R$ [redacted Tier 0] |
| Histórico recebido 2024-2026 | **R$ [redacted Tier 0]** |
| Subs ativas | 109 (MRR R$ [redacted Tier 0]) |
| Subs canceladas | 52 |
| CHURN este mês | 1.2% / 2 cancelamentos |

## Pegadinhas catalogadas

1. **UNIQUE `(business_id, numero_documento)` em rb_invoices** — cliente com >1 título mesmo mês colide. Solução: sufixo `-L{titulo_id}` no histórico.
2. **`next_due_date` NOT NULL em rb_subscriptions** — pra subs canceled, usar ultima_venc como sentinela.
3. **PHPStan + Eloquent Models** — relationship retorna Model genérico. Solução: `/** @var Class|null $var */` + `getAttribute('campo')` cirúrgico (nunca adicionar `@property` global).
4. **Hostinger SSH sem process substitution** — `eval "$(grep ... | sed s/^/export /)"` em vez de `<(...)`.
5. **mysql --batch + SSH warnings no stdout** — filtrar TSV via `awk -F'\t' 'NF>=N'`.
6. **Encoding Windows Python print** — caracteres `→` (U+2192) quebram, usar ASCII.
7. **Worktree em sessão paralela** (`feat/inter-register-webhook-e` no master) — usar worktree separada pra docs sem misturar mudanças não relacionadas.

## O que ficou pendente / próximos passos

### Imediato (decisão Eliana ou Wagner)
- [ ] **Ativar gateway real** — hoje invoices geradas têm `gateway=NULL`. Integrar com Inter (077) / C6 (336) / Cora (403) para emitir boleto/PIX automático. Hoje funciona como "registro contábil" só.
- [ ] **Validar visualmente** os 52 cancelados na tab Assinaturas → filtro Canceladas
- [ ] Conferir se KPI "PAGO ESTE MÊS R$ [redacted Tier 0]" está coerente (jun começou, ninguém pagou ainda nos primeiros 7 dias — mas alguns títulos da migração têm baixa < jun pra mensalidade jun)

### Automação ativa (sem ação necessária)
- Cron `rb:generate-invoices` daily 03:00 BRT em prod
- 10 de jul/2026 — primeiros 90 boletos jul vão ser gerados automaticamente
- 15 de jul → +9 (dia 15) · 20 → +4 · 25 → +5 · 30 → +1
- Total esperado fim jul: 109 invoices jul/2026 geradas

### Backlog desta linha
- [ ] Migração boletos vivos 30.071 BOLETOS Firebird (continuação handoff madrugada)
- [ ] Contratos ATIVO 313 → subscription_contracts
- [ ] Re-vincular 19 órfãos plano de contas
- [ ] Limpar `cliente_descricao` órfãos (1 caso CYRINO já resolvido)

## Backups defensivos (gitignored em output/)

- `output/planos-mensalidade/backup-rb-pre-limpa/dump-rb-biz1-2026-06-07.sql` (41 INSERTs seed Wagner)
- `output/planos-mensalidade/backup-subs-pre-insert-2026-06-07.sql` (subs antes Fase 4)
- `output/planos-mensalidade/backup-etapa1-pre-2026-06-07.sql` (218 INSERTs 109 plans+109 subs antes Etapa 1)
- `output/planos-mensalidade/backup-invoices-pre-2026-06-07.sql` (invoices vazias antes Etapa 2-4)

Rollback total (apaga tudo desta sessão):
```sql
DELETE FROM rb_invoices       WHERE business_id=1;
DELETE FROM rb_subscriptions  WHERE business_id=1;
DELETE FROM rb_plans          WHERE business_id=1;
-- restore from backups acima na ordem inversa
```

## Refs

- PR desta sessão: [#2384](https://github.com/wagnerra23/oimpresso.com/pull/2384) US-RB-003 gerador rb:generate-invoices
- PR contexto madrugada: [#2363](https://github.com/wagnerra23/oimpresso.com/pull/2363) fix KPI juros
- ADRs: [0093 multi-tenant Tier 0](../decisions/0093-multi-tenant-isolation-tier-0.md) · [0101 testes biz=1](../decisions/0101-tests-business-id-1-nunca-cliente.md) · [0094 §"Mexeu, REGISTRA"](../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- SPEC: `memory/requisitos/RecurringBilling/SPEC.md` US-RB-003
- Session log: [2026-06-07-recurring-billing-retroatividade-eliana](../sessions/2026-06-07-recurring-billing-retroatividade-eliana.md)
- Sessão anterior (continuidade): [2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros](2026-06-07-0220-migracao-financeira-wr2-completa-fix-kpi-juros.md)
- Scripts: `scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-{planos-mensalidade,subscriptions,etapa1-cancelados-ativos,invoices-historicas}.py`
