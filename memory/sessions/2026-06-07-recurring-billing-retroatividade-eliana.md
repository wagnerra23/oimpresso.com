---
title: RecurringBilling biz=1 ponta-a-ponta — limpa seed Wagner → US-RB-003 implementado → 109 ativos + 52 cancelados + 3389 invoices históricas
date: 2026-06-07
owner: Eliana [E]
prs: [2384]
related_handoff: memory/handoffs/2026-06-07-XXXX-recurring-billing-retroatividade.md
---

# Session log — RecurringBilling retroatividade biz=1 (sessão Eliana)

## Contexto

Continuação direta da sessão de madrugada (`2026-06-07-0220` — migração WR Comercial Delphi → biz=1, PR #2363 fix KPI juros). Eliana queria configurar a **cobrança recorrente** no oimpresso pra ter visibilidade de quem paga em dia, quem deve, faturamento histórico, churn.

## Linha do tempo

### Fase 1 — Limpar seed Wagner (rb_* zerar)
Estado inicial: 41 registros em `rb_*` biz=1 (seed Wagner 18/mai com nomes fake tipo "Padaria Estrela", "Sushi Konichiwa"). Backup defensivo + DELETE em ordem FK em transação. Tier 0 preservado (nenhuma outra biz tinha rb_*).

### Fase 2 — Criar 109 planos pros mensalistas ativos
Investigação `fin_titulos` jun/2026:
- Filtro: plano_conta_id=332 (MENSALIDADE DO SOFTWARE) + observação LIKE 'MENSALIDADE REFERENTE AO MÊS DE %'
- 120 títulos → descartar 11 (renegociação/parcelamento de dívida) → **109 mensalidades puras**
- 108 com cliente_id + 1 sem (CYRINO ENGENHARIA — match por nome contacts id=31257)
- Soma MRR: R$ 37.222,52/mês

Criados 109 `rb_plans` com:
- name = "Mensalidade <RAZÃO SOCIAL>"
- slug = `mensalidade-cliente-{id}`
- ciclo = monthly · fiscal_type = none
- UPDATE CYRINO fin_titulos 165313 SET cliente_id=31257 (reconectar)

### Fase 3 — Construir gerador `rb:generate-invoices` (US-RB-003) — PR #2384
Descobri que `Modules/RecurringBilling/Providers/RecurringBillingServiceProvider::registerCommandSchedules()` estava VAZIO. Sem isso, assinaturas ativas ficariam "paradas" — next_due_date nunca avançaria.

Implementei:
- `Services/InvoiceGeneratorService` — lógica de domínio (idempotência YYYY-MM, avança next_due_date += ciclo via addMonthsNoOverflow, cria SubscriptionEvent kind=event-charge)
- `Console/Commands/GenerateInvoicesCommand` — CLI com `--business --date --lead-days --dry-run --detail`
- `Providers/RecurringBillingServiceProvider` — registra command + schedule daily 03:00 BRT env=live
- `Tests/Feature/InvoiceGeneratorServiceTest` — 8 cenários Pest (SQLite in-memory)

Fix CI: PHPStan reclamou `Plan` model props (`$plan->ciclo` etc) — substituí por `getAttribute()` + PHPDoc `/** @var Plan|null $plan */`. CI 100% verde após. PR #2384 mergeado --admin, commit `46766430a` em prod.

Smoke prod confirmou:
- `rb:generate-invoices` registrado
- hoje (07/jun) → 0 candidatos (sem subs ainda)
- 15/jul → 99 candidatos · 30/jul → 109 (todos)

### Fase 4 — Criar 109 assinaturas (subs ativas)
Base TSV gerada via SSH (JOIN fin_titulos jun/2026 + rb_plans via slug + COALESCE contacts.name).

Python `gerar-sql-subscriptions.py` gerou 109 INSERT com:
- status=active
- start_date/next_due_date/billing_anchor_date = 2026-07-XX (DATE_ADD(venc_jun, INTERVAL 1 MONTH))
- conta_bancaria_id = mesma do título jun/2026 (replicar banco)
- payment_method=boleto · metadata source/cliente/plano

Distribuição banco: 36 C6 + 26+25 Inter + 22 Cora = 109 ✓
Distribuição dia jul: 90 dia 10 · 9 dia 15 · 4 dia 20 · 5 dia 25 · 1 dia 30

### Fase 5 — Retroatividade 2024-2026 (escopo expandido pela Eliana)
Investigação 2024-jun/2026:
- 3.418 mensalidades · 161 clientes únicos · R$ 1.165.583,90
- 109 com sub ativa + **52 cancelados** (sem mensalidade em jun/2026)
- Distribuição cancelamento: 27 em 2024 · 20 em 2025 · 5 em 2026

**Etapa 1** — `etapa1-cancelados-ativos.sql`:
- 52 INSERT rb_plans (ativo=0)
- 52 INSERT rb_subscriptions (status=canceled, next_due_date=ultima_venc, canceled_at=ultima_venc+1mês)
- 109 UPDATE rb_subscriptions ativas (start_date + billing_anchor_date = 1ª mensalidade real)

Pegadinha 1: `next_due_date` NOT NULL → setado pra ultima_venc como sentinela em subs canceladas.

**Etapas 2-3-4** — `gerar-sql-invoices-historicas.py` gerou 3 SQLs (1 por ano):
- 2024: 1.351 invoices · 1297 paid (R$ 444.934,66) · 54 overdue (R$ 14.776,26)
- 2025: 1.375 invoices · 1342 paid (R$ 459.071,23) · 33 overdue (R$ 7.388,22)
- 2026: 663 invoices · 506 paid (R$ 182.831,85) · 50 overdue (R$ 13.124,38) · 107 open (R$ 36.891,11)

**Pegadinha 2:** UNIQUE `(business_id, numero_documento)` — quando cliente tem >1 título no mesmo mês, `RB-{sub_id}-{YYYY-MM}` colide. Fix: sufixo `-L{titulo_id}` (Legacy id).

Status map fin_titulos → rb_invoices:
- `quitado`/`parcial` → `paid` (pago_em = MAX(data_baixa))
- `aberto` + venc<hoje → `overdue`
- `aberto` + venc>=hoje → `open`
- `cancelado` → `canceled`

### Resultado final (smoke ao vivo /recurring-billing/faturas)
- **3389 FATURAS · ATRASADAS 137**
- PAGO ESTE MÊS: R$ 0,00 (jun/2026 ainda começou)
- PENDENTE: R$ 36.891,11 (107 jul/2026 abertas)
- ATRASADO: R$ 35.288,86 (137 vencidas)
- TOTAL HISTÓRICO: R$ 1.086.837,74 recebido
- Sub MHUNDO: "desde há 6a" · EXTREME "desde há 11a" (start_date ajustado) ✓
- CHURN 1.2% / 2 cancelamentos este mês

## Numero documento padrão estabelecido

- **Histórico legacy:** `RB-{sub_id}-{YYYY-MM}-L{titulo_id}` (sufixo `L` = Legacy origem fin_titulos)
- **Futuras (cron):** `RB-{sub_id}-{YYYY-MM}` (US-RB-003)

Histórico jul/2026+ vai ser gerado automaticamente pelo cron — sem conflito de numeração com histórico que vai até jun/2026.

## Backups defensivos (todos em output/ gitignored)

- `backup-rb-pre-limpa/dump-rb-biz1-2026-06-07.sql` (seed Wagner 41 registros)
- `backup-subs-pre-insert-2026-06-07.sql` (subs vazias antes da Fase 4)
- `backup-etapa1-pre-2026-06-07.sql` (109 plans + 109 subs antes de Etapa 1)
- `backup-invoices-pre-2026-06-07.sql` (invoices vazias antes Etapa 2-4)

## Comunicação não-técnica catalogada

Eliana segue padrão da sessão de madrugada — pede clareza, valida números, aprova por etapas. Padrão que funcionou:
- Mostrar tabela antes→depois ANTES de DELETE/UPDATE
- Backup obrigatório mencionado a cada destrutivo
- Etapas separadas pra ela acompanhar (Etapa 1 → Etapa 2 → Etapa 3 → Etapa 4)
- KPIs em PT-BR claro ao final (PAGO ESTE MÊS, PENDENTE, ATRASADO)
- Cada SQL em transação atômica + verificação pós

## Pegadinhas técnicas

1. **UNIQUE `(business_id, numero_documento)` em rb_invoices** — quando cliente tem >1 título no mesmo mês de competência, padrão `RB-{sub_id}-{YYYY-MM}` colide. Solução: sufixo `-L{titulo_id}` pra histórico.
2. **`next_due_date` NOT NULL em rb_subscriptions** — pra subs canceled, usar ultima_venc como sentinela.
3. **PHPStan/Larastan e Eloquent Models** — `$model->propriedade` em relationship retorna `Model` genérico, props quebram análise. Solução: PHPDoc `/** @var Class|null $var */` + `getAttribute('campo')` cirúrgico (não adicionar `@property` que destrava cascata).
4. **Hostinger SSH sem process substitution** — `<(grep ...)` não funciona. Solução: `eval "$(grep -E ... | sed s/^/export /)"`.
5. **Encoding Windows charmap** em Python print — caracteres como `→` (U+2192) quebram. Usar ASCII.
6. **mysql --batch + warnings SSH no stdout** — gera linhas de warning no TSV. Solução: filtrar via `awk -F'\t' 'NF>=N'` exigindo N colunas TAB.

## Artefatos gerados (commitados nesta sessão)

- `scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-planos-mensalidade.py` (Fase 2)
- `scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-subscriptions.py` (Fase 4)
- `scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-etapa1-cancelados-ativos.py` (Etapa 1)
- `scripts/legacy-migration/sql-wr2-pessoas/gerar-sql-invoices-historicas.py` (Etapa 2-3-4)

Outputs SQL/TSV ficam locais (gitignored pra preservar PII — CNPJ/razão social de clientes reais).

## Refs

- PRs desta sessão: [#2384](https://github.com/wagnerra23/oimpresso.com/pull/2384) US-RB-003 gerador
- PR contexto madrugada: [#2363](https://github.com/wagnerra23/oimpresso.com/pull/2363) fix KPI juros
- SPEC: `memory/requisitos/RecurringBilling/SPEC.md` US-RB-003
- ADRs: 0093 multi-tenant Tier 0 · 0101 testes biz=1 · 0094 mexeu-registra
- Sessão anterior: `memory/sessions/2026-06-06-migracao-wr-comercial-financeiro-eliana.md`
