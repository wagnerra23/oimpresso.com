---
slug: runbook-bridge-sells-titulos-backfill
title: "RUNBOOK — Backfill bridge Sells/Compras → fin_titulos quando cliente não vê 'quem está devendo'"
type: runbook
status: live
last_validated: 2026-05-20
owner: claude
applies_to: Modules/Financeiro bridge para UltimatePOS core (transactions/transaction_payments)
canonical_session: memory/sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md
agent_pareado: .claude/agents/financeiro-bridge-auditor.md
---

# RUNBOOK — Bridge Sells/Compras → fin_titulos backfill

## Quando usar

Cliente piloto (ex ROTA LIVRE biz=4 Larissa) reporta:
- **"Não consigo ver quem está devendo"** — tela `/financeiro/contas-receber` mostra "—" ou subestima valor real
- **Total a receber muito baixo** vs realidade (ex R$ [redacted Tier 0]k visível mas Sells mostra R$ [redacted Tier 0]k em `payment_status=due`)
- **Lançou pagamento na Sells mas Financeiro não atualizou** — `transactions.payment_status='paid'` mas `fin_titulos.status='aberto'`

## Sintoma técnico

3 gaps canônicos possíveis (pode ter 1, 2 ou 3 ao mesmo tempo):

| Gap | Detector SQL | Causa raiz |
|---|---|---|
| **A** Observer no-op por falta de conta | `SELECT COUNT(*) FROM fin_contas_bancarias WHERE business_id={N}` = 0 | Commit `540a26a41` (2026-05-08) instalou no-op gracioso. Sem conta cadastrada, `TransactionPaymentObserver` NÃO cria `fin_titulo_baixas`. |
| **B** Observer não-retroativo | `transactions` finais > `fin_titulos` count | `TransactionObserver` instalado em 2026-04-25 (commit `990bd8b18`) — vendas/compras anteriores ficaram órfãs. |
| **C** `cliente_descricao` NULL | `SELECT COUNT(*) FROM fin_titulos WHERE business_id={N} AND cliente_descricao IS NULL` > 5% | Onda Edit 2026-05-18 (commit `db85ba441`) adicionou auto-pop do campo — titulos pré-data ficaram NULL. |

## Pré-requisitos

- Acesso prod MySQL Hostinger (`srv1818.hstgr.io:3306`) via canônico `reference/hostinger-remote-mysql.md` (tailscale ssh + docker mysql client)
- Wagner autorizado a executar DML em prod (sempre confirma cada fase via "go fase X")
- Branch limpa pra session log + artefatos

## Workflow 5 fases

### Fase 1 — Audit & diagnóstico

Spawn agent canônico: `Agent(subagent_type: "financeiro-bridge-auditor")` com `biz_id={N}`. Vai retornar:
- Status integridade /100
- 5 métricas chave
- 3 SQLs backfill prontos
- Critérios validação pós

OU rodar manualmente as 5 queries do agent SKILL section "2. Coletar métricas chave".

### Fase 2-pre — Stub fin_contas_bancarias (se Gap A)

**Sintoma**: `SELECT COUNT(*) FROM fin_contas_bancarias WHERE business_id={N}` retorna `0`.

**Pre-discovery**: `accounts` core ID:
```sql
SELECT id FROM accounts WHERE business_id={N} ORDER BY id LIMIT 1;
```

**INSERT**:
```sql
INSERT INTO fin_contas_bancarias (
  business_id, account_id,
  banco_codigo, agencia, carteira,
  beneficiario_documento, beneficiario_razao_social,
  ativo_para_boleto, tipo_conta,
  created_at, updated_at
) VALUES (
  {N}, {accounts.id da Q acima},
  '000', '', '',
  'XX.XXX.XXX/XXXX-XX',  -- placeholder, qualquer string varchar(18) — não emite boleto
  '{business.name pesquisado em SELECT name FROM business WHERE id={N}}',
  0,                                  -- 0 = NÃO emite boleto (stub pra desbloquear Observer)
  'corrente',
  NOW(), NOW()
);
```

Resultado esperado: `id` da nova `fin_contas_bancarias` (guarde — usado em Fase 2b).

### Fase 2a — Backfill `fin_titulos` retroativo (se Gap B)

**Sintoma**: query Q3 do agent retorna `sells_orphans > 0`.

**Dry-run primeiro** (CONTAR quantos vão ser criados):
```sql
SELECT
  t.type, COUNT(*) AS n_a_criar, SUM(t.final_total) AS total_R$,
  MIN(t.transaction_date) AS desde
  FROM transactions t
 WHERE t.business_id={N}
   AND t.type IN ('sell', 'purchase')
   AND t.status = 'final'
   AND NOT EXISTS (
     SELECT 1 FROM fin_titulos ft WHERE ft.business_id={N}
       AND ft.origem = (CASE t.type WHEN 'sell' THEN 'venda' WHEN 'purchase' THEN 'compra' END)
       AND ft.origem_id = t.id AND ft.parcela_numero IS NULL
   )
 GROUP BY t.type;
```

**INSERT INTO SELECT** (idempotente — re-execução não duplica):

```sql
INSERT INTO fin_titulos (
  business_id, numero, tipo, status,
  cliente_id, cliente_descricao,
  valor_total, valor_aberto, moeda,
  emissao, vencimento, competencia_mes,
  origem, origem_id, parcela_numero,
  observacoes, created_by, metadata,
  created_at, updated_at
)
SELECT
  {N},
  CONCAT(CASE t.type WHEN 'sell' THEN 'R-' WHEN 'purchase' THEN 'P-' END, LPAD(t.id, 6, '0')),
  CASE t.type WHEN 'sell' THEN 'receber' WHEN 'purchase' THEN 'pagar' END,
  'aberto',
  t.contact_id,
  CONCAT(
    COALESCE(NULLIF(c.name, ''), NULLIF(c.supplier_business_name, ''), CONCAT('Cliente #', c.id)),
    ' · #',
    CASE t.type WHEN 'sell' THEN 'V' WHEN 'purchase' THEN 'PC' END,
    '-', t.id
  ),
  t.final_total, t.final_total, 'BRL',
  DATE(t.transaction_date), DATE(t.transaction_date),
  DATE_FORMAT(t.transaction_date, '%Y-%m'),
  CASE t.type WHEN 'sell' THEN 'venda' WHEN 'purchase' THEN 'compra' END,
  t.id, NULL,
  t.additional_notes,
  COALESCE(t.created_by, 1),
  JSON_OBJECT('backfill_sql', '{YYYY-MM-DD}', 'transaction_invoice_no', t.invoice_no),
  COALESCE(t.created_at, NOW()), NOW()
  FROM transactions t
  LEFT JOIN contacts c ON c.id = t.contact_id
 WHERE t.business_id = {N}
   AND t.type IN ('sell', 'purchase')
   AND t.status = 'final'
   AND NOT EXISTS (
     SELECT 1 FROM fin_titulos ft WHERE ft.business_id={N}
       AND ft.origem = (CASE t.type WHEN 'sell' THEN 'venda' WHEN 'purchase' THEN 'compra' END)
       AND ft.origem_id = t.id AND ft.parcela_numero IS NULL
   );
```

### Fase 2b — Backfill `fin_titulo_baixas` (sempre que Gap A foi resolvido)

Idempotente — `NOT EXISTS` em `transaction_payment_id`.

```sql
INSERT INTO fin_titulo_baixas (
  business_id, titulo_id, conta_bancaria_id,
  valor_baixa, juros, multa, desconto,
  data_baixa, meio_pagamento,
  idempotency_key, transaction_payment_id,
  observacoes, created_by, created_at
)
SELECT
  {N}, ft.id, {fin_contas_bancarias.id da Fase 2-pre},
  tp.amount, 0.0000, 0.0000, 0.0000,
  DATE(COALESCE(tp.paid_on, tp.created_at, NOW())),
  CASE tp.method
    WHEN 'cash' THEN 'dinheiro'
    WHEN 'card' THEN 'cartao_credito'
    WHEN 'bank_transfer' THEN 'transferencia'
    WHEN 'cheque' THEN 'cheque'
    WHEN 'pix' THEN 'pix'
    WHEN 'other' THEN 'outro'
    WHEN 'custom_pay_1' THEN 'outro'
    WHEN 'custom_pay_2' THEN 'outro'
    WHEN 'custom_pay_3' THEN 'outro'
    ELSE 'outro'
  END,
  CONCAT('tp_', tp.id),
  tp.id,
  JSON_OBJECT('backfill_sql', '{YYYY-MM-DD}', 'tp_method_original', tp.method),
  COALESCE(tp.created_by, 1),
  COALESCE(tp.created_at, NOW())
  FROM transaction_payments tp
  JOIN transactions t ON t.id = tp.transaction_id
  JOIN fin_titulos ft ON ft.business_id={N}
                      AND ft.origem = (CASE t.type WHEN 'sell' THEN 'venda' WHEN 'purchase' THEN 'compra' END)
                      AND ft.origem_id = t.id
                      AND ft.parcela_numero IS NULL
 WHERE tp.business_id={N}
   AND NOT EXISTS (SELECT 1 FROM fin_titulo_baixas WHERE transaction_payment_id=tp.id AND business_id={N})
   AND t.type IN ('sell', 'purchase')
   AND t.status = 'final';
```

### Fase 2c — Recalc `valor_aberto` + `status`

```sql
-- Step 1: valor_aberto = valor_total - sum(baixas não-estornadas)
UPDATE fin_titulos ft
   SET valor_aberto = GREATEST(
     ft.valor_total - (
       SELECT COALESCE(SUM(valor_baixa), 0)
         FROM fin_titulo_baixas
        WHERE titulo_id = ft.id
          AND business_id = ft.business_id
          AND estorno_de_id IS NULL
     ), 0
   ),
   updated_at = NOW()
 WHERE business_id = {N};

-- Step 2: status baseado em valor_aberto / valor_total
UPDATE fin_titulos
   SET status = CASE
     WHEN valor_aberto <= 0 THEN 'quitado'
     WHEN valor_aberto < valor_total THEN 'parcial'
     ELSE 'aberto'
   END,
   updated_at = NOW()
 WHERE business_id = {N}
   AND status <> 'cancelado';
```

### Fase 2d (opcional) — Backfill `cliente_descricao` antigos (Gap C)

```sql
UPDATE fin_titulos ft
  JOIN contacts c ON c.id = ft.cliente_id
   SET ft.cliente_descricao = CONCAT(
         COALESCE(NULLIF(c.name, ''), NULLIF(c.supplier_business_name, ''), CONCAT('Cliente #', c.id)),
         ' · #',
         CASE ft.origem WHEN 'venda' THEN 'V' WHEN 'compra' THEN 'PC' ELSE '?' END,
         '-', ft.origem_id
       )
 WHERE ft.business_id = {N}
   AND ft.cliente_descricao IS NULL
   AND ft.cliente_id IS NOT NULL
   AND ft.origem IN ('venda', 'compra');
```

## Validação pós

Espelhe as 5 métricas do agent ANTES e DEPOIS, esperado:

| Métrica | Antes | Depois esperado |
|---|---|---|
| `transaction_payments` count | X | igual (não cria nem deleta) |
| `fin_titulo_baixas` count | Y < X | ≈ X (gap ~0) |
| `fin_titulos` count | T | ≈ count `transactions(type IN ('sell','purchase') AND status='final')` |
| `fin_contas_bancarias` count | 0 | 1 stub (ou mais se já tinha) |
| `cliente_descricao` NULL % | >5% | <5% |

## Rollback

Todos os titulos/baixas têm `metadata.backfill_sql='YYYY-MM-DD'` — rollback:

```sql
DELETE FROM fin_titulo_baixas WHERE business_id={N} AND JSON_EXTRACT(observacoes, '$.backfill_sql') = '{YYYY-MM-DD}';
DELETE FROM fin_titulos WHERE business_id={N} AND JSON_EXTRACT(metadata, '$.backfill_sql') = '{YYYY-MM-DD}';
DELETE FROM fin_contas_bancarias WHERE business_id={N} AND id={stub_id_da_fase_2_pre};
```

## Gotchas conhecidos

1. **Backfill SQL DB direto NÃO gera `activity_log` Spatie** — Eloquent `boot()` events não disparam em raw `INSERT INTO SELECT`. Implicação: histórico do que foi criado pelo backfill fica fora do `activity_log` Spatie (rastreabilidade via `metadata.backfill_sql` tag em vez disso). Pra futuro: rodar via artisan command `php artisan financeiro:bridge-backfill {biz}` dispararia Observer/events corretamente (TODO arquitetural — ver [ADR proposta 0175](../../decisions/proposals/fix-observer-conta-bancaria-opcional.md)).

2. **`payment_status='paid'` no transactions ≠ `status='quitado'` no fin_titulos** automaticamente — depende de baixas registradas. Fase 2c recalcula corretamente.

3. **`status='cancelado'` em titulos pré-existentes é preservado** (UPDATE WHERE status <> 'cancelado'). Não tente "recuperar" cancelados — eles foram cancelados por motivo de negócio.

4. **522 órfãos típicos remanescentes:** `purchase status='received'` (pedido entregue mas não-finalizado), `sell_return`, `expense`. Não cobertos pela bridge canônica (filtra `status='final'` AND `type IN ('sell','purchase')`). Tratamento separado.

5. **Cross-business contamination:** TODA query deve ter `WHERE business_id={N}` EXPLÍCITO (Tier 0 IRREVOGÁVEL ADR 0093). NUNCA omitir.

## Caso histórico calibrador

Larissa biz=4 ROTA LIVRE, 2026-05-20:

- Detectado via [sessão understand-accounting-vs-financeiro](../../sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md) Wagner relatou "cliente quer ver quem está devendo"
- Audit revelou 3 gaps confirmados (A+B+C todos presentes)
- Fase 2-pre: 1 stub fin_contas_bancarias criada (id=20, `ROTA LIVRE`, ativo_para_boleto=0)
- Fase 2a: 17.412 fin_titulos retroativos (R$ [redacted Tier 0]M em 5 anos)
- Fase 2b: 15.412 fin_titulo_baixas (R$ [redacted Tier 0]M baixados histórico)
- Fase 2c: recalc → 1.567 abertos + 104 parciais = **R$ [redacted Tier 0] a receber visível pra Larissa**
- Tier 0 respeitado: 100% queries `WHERE business_id = 4`
- Wall time: ~90s queries DML em prod
- Tela validada: `/financeiro/contas-receber` mostra 1.671 títulos com nomes reais ("CASA OCRE TUBARÃO SC", "ANDREIA FERNANDES" etc.)
- Pendência: 522 payments AP órfãos (purchase received) — backfill separado, baixa prioridade
- Pendência arquitetural: solução B (fix Observer guard) → ADR proposta 0175

## Refs

- [ADR 0093 multi-tenant Tier 0](../../decisions/0093-multi-tenant-isolation-tier-0.md)
- [ADR 0094 Constituição v2 append-only](../../decisions/0094-constituicao-v2-7-camadas-8-principios.md)
- [ADR 0172 deprecar Accounting](../../decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md) (contexto: por que Financeiro é canon)
- [ADR proposta 0175 fix Observer guard](../../decisions/proposals/fix-observer-conta-bancaria-opcional.md) (solução arquitetural permanente)
- [Modules/Financeiro/Observers/](../../../Modules/Financeiro/Observers/)
- [Modules/Financeiro/Services/TituloAutoService.php](../../../Modules/Financeiro/Services/TituloAutoService.php)
- [reference/hostinger-remote-mysql.md](../../reference/hostinger-remote-mysql.md)
- [reference/feedback-fin-bridge-no-op-account-gap.md](../../reference/feedback-fin-bridge-no-op-account-gap.md)
- Agent pareado: [`.claude/agents/financeiro-bridge-auditor.md`](../../../.claude/agents/financeiro-bridge-auditor.md)
