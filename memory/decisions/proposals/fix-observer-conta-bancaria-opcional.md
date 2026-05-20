---
slug: 0175-fix-observer-conta-bancaria-opcional
number: 175
title: "Fix arquitetural — Observer Financeiro permite baixa sem fin_contas_bancarias (remove guard no-op)"
type: adr
status: proposto
authority: canonical
lifecycle: ativo
decided_by: [W]
decided_at: 2026-05-20
module: financeiro
quarter: 2026-Q2
tags: [financeiro, observer, fix-arquitetural, debito-tecnico, multi-tenant]
supersedes: []
supersedes_partially: []
superseded_by: []
related:
  - memory/decisions/0172-deprecar-modulo-accounting-fundir-financeiro.md
  - memory/decisions/0174-errata-deprecation-plan-accounting-ondas-3-4-skip.md
pii: false
review_triggers:
  - "Próximo cliente piloto onboarding sem fin_contas_bancarias"
  - "Wagner aprovar PR de implementação"
amends_implicitly:
  - commit 540a26a41 (2026-05-08 — guard no-op original)
ref_session: memory/sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md
ref_runbook: memory/requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md
ref_feedback: memory/reference/feedback-fin-bridge-no-op-account-gap.md
---

# ADR 0175 (proposta) — Fix arquitetural Observer Financeiro: baixa sem fin_contas_bancarias

## Status

**proposed** — Wagner aprova após validar Larissa em prod + planejar sprint próxima.

## Contexto

[Commit `540a26a41`](https://github.com/wagnerra23/oimpresso.com/commit/540a26a41) (2026-05-08, autoria Wagner + Claude Sonnet 4.6) instalou **no-op gracioso** em `Modules/Financeiro/Services/TituloAutoService::registrarPagamento()` quando o business não tem `fin_contas_bancarias` cadastrada.

Motivo na época: BUG-2 — `DomainException` bloqueava `TransactionPayment::create()` no UltimatePOS core de Larissa biz=4 (ela usava só PIX, nunca cadastrou conta). Decisão: degradar exception → no-op + Log::info pra não quebrar fluxo Sells/Purchases.

**Consequência não-prevista**: por **12 dias** (2026-05-08 → 2026-05-20) Larissa lançou 15.932 pagamentos via Sells, todos silenciados, ZERO baixas em `fin_titulo_baixas`. Tela `/financeiro/contas-receber` mostrava R$ [redacted Tier 0]k a receber em vez de R$ [redacted Tier 0]k real (drift 35x).

Diagnosticado e mitigado em sessão 2026-05-20 via **backfill SQL** ([RUNBOOK bridge-sells-titulos-backfill.md](../../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md)) — 17.412 titulos + 15.412 baixas + stub `fin_contas_bancarias` (id=20). Wagner aprovou explicitamente "vai A" + "go fase 2".

Esta ADR formaliza o **fix arquitetural permanente** que remove a dependência implícita de `fin_contas_bancarias`.

## Decisão

Remover o guard no-op + tornar `conta_bancaria_id` NULL-aceito em `fin_titulo_baixas`. Implementação canônica:

### 1. Migration `ALTER TABLE`

```php
// 2026_XX_XX_make_titulo_baixa_conta_bancaria_optional.php
Schema::table('fin_titulo_baixas', function (Blueprint $table) {
    $table->unsignedInteger('conta_bancaria_id')->nullable()->change();
});
```

### 2. Remover guard em `TituloAutoService::registrarPagamento()`

Mudança:

```diff
-$contaBancaria = $this->resolverContaBancaria($tx->business_id, $tp->account_id);
-if (! $contaBancaria) {
-    \Log::info('TituloAutoService.registrarPagamento: skip — biz sem fin_contas_bancarias', [...]);
-    return null;
-}
+$contaBancaria = $this->resolverContaBancaria($tx->business_id, $tp->account_id);
+// conta_bancaria_id pode ser null — baixa registrada sem vinculação bancária formal.
+// Cliente pode atrelar conta posteriormente via UI "/financeiro/contas-bancarias".
```

### 3. Update INSERT pra usar `?->id`

```diff
TituloBaixa::create([
    ...
-   'conta_bancaria_id' => $contaBancaria->id,
+   'conta_bancaria_id' => $contaBancaria?->id,
    ...
]);
```

### 4. Pest test

```php
it('Observer cria fin_titulo_baixa mesmo sem fin_contas_bancarias cadastrada', function () {
    $biz = createBusinessSemContaBancaria();
    $tx = createTransaction(['business_id' => $biz->id, 'type' => 'sell', 'status' => 'final', 'final_total' => 100]);
    
    $tp = TransactionPayment::create([
        'business_id' => $biz->id,
        'transaction_id' => $tx->id,
        'amount' => 100,
        'method' => 'pix',
        // sem account_id
    ]);
    
    // Observer dispara automaticamente
    expect(TituloBaixa::where('transaction_payment_id', $tp->id)->exists())->toBeTrue();
    expect(TituloBaixa::where('transaction_payment_id', $tp->id)->first()->conta_bancaria_id)->toBeNull();
});
```

### 5. UI fallback ("conta indefinida")

Em `/financeiro/contas-receber` + `/financeiro/unificado` + drawer titulo, quando `baixa.conta_bancaria_id IS NULL`:
- Mostrar pill cinza "conta indefinida" (link → CTA "vincular conta agora" → modal cadastro `fin_contas_bancarias`)
- Tooltip: "Pagamento registrado sem vinculação bancária. Cadastre conta pra organizar caixa."

### 6. Health-check command (defesa em profundidade)

```bash
php artisan financeiro:health-check
```

Detecta:
- Businesses com `fin_titulo_baixas` mas zero `fin_contas_bancarias`
- Sugere CTA UI "cadastre conta"
- Roda em cron daily 06:00 BRT, alerta Slack se gap > 10 baixas

## Justificativa

3 pilares:

1. **Realidade Brasileira PME**: muitos clientes (especialmente vestuário, micro-comércio, autônomos) operam SÓ com PIX/dinheiro. Boleto bancário (uso original de `fin_contas_bancarias`) é minoria. Forçar cadastro é fricção de onboarding.

2. **Princípio mínimo viável**: lançar pagamento (`TransactionPayment::create()`) é ação core UltimatePOS. Financeiro é módulo opcional — não deve **silenciar** o ciclo. Ou aceita (proposta atual) ou bloqueia explicitamente (UX clara) — silenciar é o pior dos mundos (caso real Larissa: 15.932 silenciados, 12 dias invisível).

3. **Cliente como sinal qualificado** [ADR 0105](../0105-cliente-como-sinal-guiar-sem-mandar.md): Larissa biz=4 forneceu o sinal "não consigo ver quem está devendo". Backlog atual já tem essa US implícita — esta ADR formaliza solução.

## Consequências

### Positivas

- **Próximo cliente piloto onboarding** (Vestuario, OficinaAuto Martinho, etc.) NÃO precisa cadastrar conta antes de usar Financeiro
- **Workflow Larissa preservado**: lança pagamento via Sells → fin_titulo_baixas gerada automaticamente → "quem está devendo" atualizado em tempo real
- **Reconciliação posterior**: cliente cadastra conta depois → comando `php artisan financeiro:vincular-baixas-sem-conta {biz} {conta_id}` UPDATE em massa
- **Remove débito técnico documentado em [feedback-fin-bridge-no-op-account-gap.md](../../reference/feedback-fin-bridge-no-op-account-gap.md)**

### Negativas

- **Relatórios de fluxo de caixa por conta** ficam parciais quando `conta_bancaria_id NULL` (UI pode agrupar separadamente como "indefinida")
- **Migration ALTER TABLE em prod**: precisa janela curta de manutenção (typically < 1s pra ALTER nullable). Hostinger MariaDB 11.8 suporta `ALGORITHM=INSTANT` pra essa operação.
- **Backward compat com baixas pré-2026-05-20**: as 15.412 baixas que fiz no backfill apontam pra conta stub `id=20` (`ROTA LIVRE`, `ativo_para_boleto=0`). Após este fix, posso opcionalmente fazer `UPDATE fin_titulo_baixas SET conta_bancaria_id=NULL WHERE conta_bancaria_id=20 AND business_id=4` pra normalizar — ou manter stub como "marker histórico".

### Neutras

- Pest test atual `TransactionPaymentObserverTest.php` (que valida que save funciona sem conta) precisa update — verifica que **TituloBaixa É criada** agora (não mais "no-op"). Mudança simples.

## Implementação proposta

PR único `feat(financeiro): Onda ZZ fix Observer guard - conta_bancaria_id opcional` com:

- 1 migration
- 1 service edit (`TituloAutoService.php` -10/+3 linhas)
- 1 model nullable (`fin_titulo_baixas conta_bancaria_id`)
- 2 Pest tests (existente atualizado + 1 novo)
- 1 frontend FinUI tweak (pill "conta indefinida" com CTA)
- 1 artisan command `financeiro:health-check`
- Documentação RUNBOOK atualizado (remove fase 2-pre stub conta)

**Estimate**: 4h IA-pair + 2h testes + 1h smoke prod (incluindo deploy + validação Larissa). Total: ~7h.

**Risk**: BAIXO — ALTER nullable é INSTANT MariaDB 11.8, sem lock contention. Migration reversível trivialmente.

## Critério SUCCESS

Após merge + deploy:

1. Pest test `it('Observer cria fin_titulo_baixa mesmo sem fin_contas_bancarias cadastrada')` passa em CI
2. Smoke prod: biz teste novo (criar via superadmin) sem conta → lança venda → lança payment → confirma baixa criada com `conta_bancaria_id IS NULL`
3. Larissa biz=4 confirma que próximo pagamento NOVO no Sells gera baixa imediata (sem precisar stub `id=20`)
4. `php artisan financeiro:health-check` retorna `[]` (zero gaps) em todos os businesses ativos

## Refs

- [Session log canônico](../../sessions/2026-05-20-financeiro-bridge-larissa-backfill-recovery.md) — diagnóstico + backfill
- [RUNBOOK pareado](../../requisitos/Financeiro/RUNBOOK-bridge-sells-titulos-backfill.md) — manual workflow atual (será simplificado pós-implementação desta ADR)
- [feedback canon](../../reference/feedback-fin-bridge-no-op-account-gap.md) — lição
- [Agent auditor](../../../.claude/agents/financeiro-bridge-auditor.md) — detecção automatizada
- [ADR 0093 multi-tenant Tier 0](../0093-multi-tenant-isolation-tier-0.md)
- [ADR 0105 cliente como sinal](../0105-cliente-como-sinal-guiar-sem-mandar.md)
- [ADR 0172 deprecar Accounting](../0172-deprecar-modulo-accounting-fundir-financeiro.md) (motivação dual: Financeiro substitui Accounting → não pode falhar silenciosamente)
- Commit original do bug: `540a26a41`
- File chave: `Modules/Financeiro/Services/TituloAutoService.php:209-220`
