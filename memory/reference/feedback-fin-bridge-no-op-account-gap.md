---
name: feedback-fin-bridge-no-op-account-gap
description: Feedback canônico — Observer no-op silencioso quando biz sem fin_contas_bancarias. Bug invisível em prod por 12d antes da Larissa reportar.
type: feedback
date_captured: 2026-05-20
captured_in_session: memory/sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md
applies_to: TODOS módulos que dependem de Observer com guard de pré-requisito
severity: alta
---

# Feedback canônico — Observer no-op silencioso é débito técnico ALTO

## A regra

Quando um Observer/Listener faz **no-op gracioso** (return null + Log::info) ao encontrar pré-requisito ausente, instale **alerta de proatividade** que detecte o gap antes do cliente reportar.

## Por que (caso real 2026-05-20)

**Commit `540a26a41` (2026-05-08)** instalou no-op em `TituloAutoService::registrarPagamento()`:

```php
if (! $contaBancaria) {
    \Log::info('TituloAutoService.registrarPagamento: skip — biz sem fin_contas_bancarias', [
        'business_id' => $tx->business_id,
        'tp_id' => $tp->id,
        'tx_id' => $tx->id,
    ]);
    return null;
}
```

**Razão correta na época**: BUG-2 — exception bloqueava `TransactionPayment::create()` no UltimatePOS core de Larissa biz=4 (ela não tinha cadastrado conta bancária e isso quebrava o save da venda). Decisão: degradar exception → no-op pra não quebrar fluxo core.

**Consequência não-prevista**: por **12 dias** (2026-05-08 → 2026-05-20) Larissa lançou **15.932 pagamentos via Sells**, todos `Log::info` silenciados, **ZERO** fin_titulo_baixas geradas. Quando ela perguntou "cadê quem está devendo no Financeiro?", apareceu R$ [redacted Tier 0]k em vez de R$ [redacted Tier 0]k real. Drift de 35x.

## How to apply

Quando você escrever no-op gracioso em Observer/Listener:

### 1. Log em nível mais alto que `info`

`info` é silencioso em prod (nível mínimo geralmente `warning`). Use:

```php
\Log::warning('Component.method: no-op por pré-req X ausente', [...]);
// OU pior:
\Log::error(...);  // se a degradação é violação semântica do contrato
```

### 2. Criar health-check command que detecta drift

```bash
php artisan financeiro:health-check
# Output esperado: lista businesses com fin_contas_bancarias=0 + transaction_payments>0 (gap detectado)
```

E rodar em cron daily / schedule. Quando count > 0, dispara alerta Slack/email.

### 3. Documentar o gap como **débito técnico** com TODO data-stamped

```php
// FIXME: 2026-05-08 — no-op silencia gap até biz cadastrar conta.
// Solução permanente: ALTER fin_titulo_baixas.conta_bancaria_id NULLABLE
// + remover guard. Ver ADR 0175 (proposta).
if (! $contaBancaria) { ... }
```

### 4. Criar Pest test que valida o no-op detectado pelo health-check

```php
it('detecta gap quando biz tem payments mas zero contas bancárias', function () {
    // criar biz sem fin_contas_bancarias
    // criar TransactionPayment via Observer
    // assert: health-check report mostra esse biz como "gap"
});
```

## Quando este feedback NÃO se aplica

- Observer/Listener cujo no-op é **semanticamente correto** (ex: skip de evento irrelevante por config). Não cria drift.
- Componente atrás de feature flag explícita (gap consciente).

## Alternativas que evitam o no-op a princípio

1. **Soft constraint via DB**: deixar `conta_bancaria_id NULL` em `fin_titulo_baixas` — Observer cria baixa "sem conta" e reconciliação manual posterior. Próximo nível: ADR 0175 (proposta) implementa.

2. **Auto-criar stub on demand**: quando Observer detecta business sem conta, ele cria uma stub `_default` em vez de skip. Tradeoff: pode mascarar config falha.

3. **Reject no save**: Observer não-no-op, deixa exception subir → save da venda falha → UI mostra erro pro usuário "cadastre conta bancária primeiro". Tradeoff: bloqueia fluxo core (motivo do BUG-2 original).

A escolha entre (1)/(2)/(3) é arquitetural — não há solução universalmente certa. O importante é **NUNCA escolher (4) silencioso sem alerta**.

## Histórico

- **2026-05-08**: Commit `540a26a41` instala no-op (sem alerta, sem health-check, sem TODO data-stamped). Wagner co-autor.
- **2026-05-20 16:00 BRT**: Larissa reporta drift. Audit via `financeiro-bridge-auditor` localiza root cause em ~15min.
- **2026-05-20 16:17 BRT**: Backfill SQL 3 fases resolve histórico (17.412 titulos + 15.412 baixas + recalc).
- **2026-05-20 16:30 BRT**: Este feedback canon escrito + ADR 0175 proposta (fix arquitetural permanente).

## Refs

- Session log: [2026-05-20-financeiro-bridge-larissa-backfill-recovery.md](../sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md)
- RUNBOOK pareado: [bridge-sells-titulos-backfill.md](../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md)
- Agent canônico: [`.claude/agents/financeiro-bridge-auditor.md`](../../.claude/agents/financeiro-bridge-auditor.md)
- ADR 0175 proposta: [fix-observer-conta-bancaria-opcional](../decisions/proposals/fix-observer-conta-bancaria-opcional.md)
- Commit do bug original: `540a26a41` (2026-05-08)
- Files: `Modules/Financeiro/Services/TituloAutoService.php:209-220`, `Modules/Financeiro/Observers/TransactionPaymentObserver.php`
