# ADR TECH-0003 (RecurringBilling) · Proração mid-cycle (upgrade/downgrade)

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: US-RB-006

## Contexto

Cliente assina **plano A R$ 200/mês** dia 1. Faz upgrade pro **plano B R$ 350/mês** dia 15 (meio do ciclo). Cobrar:

- ❌ R$ 350 imediato (cobrança dupla)
- ❌ R$ 350 só no próximo ciclo (cliente recebe upgrade grátis 2 semanas)
- ✅ Crédito de R$ 100 (15 dias do A não usado) + débito de R$ 175 (15 dias do B) = +R$ 75 hoje

Downgrade tem espelho:
- Plano B → A no dia 15: crédito R$ 175 (B não usado) - débito R$ 100 (A novo) = +R$ 75 crédito (aplicado no próximo ciclo)

Casos edge:
- Mudança em D-1 do vencimento (proração quase zero)
- Trial restante (não cobra proração até trial acabar)
- Upgrade durante past_due (cobrança recente falhou)
- Reajuste anual (técnicamente proração também)

## Decisão

**Service puro `ProrationService` com 6 cenários cobertos por testes.**

Service não tem side effects — recebe dados, retorna `ProrationResult`. Quem chama (controller/job) decide se aplica como cobrança imediata, crédito futuro, etc.

```php
class ProrationService {
    public function calculate(
        Contract $contract,
        Plan $newPlan,
        CarbonInterface $changeDate
    ): ProrationResult {
        $cicloAtualInicio = $this->cicloInicio($contract);
        $cicloAtualFim = $contract->next_billing_date;

        $diasTotalCiclo = $cicloAtualInicio->diffInDays($cicloAtualFim);
        $diasUsadosPlanoAntigo = $cicloAtualInicio->diffInDays($changeDate);
        $diasRestantesNoNovo = $changeDate->diffInDays($cicloAtualFim);

        $valorAntigoUsado = $contract->plan->valor * ($diasUsadosPlanoAntigo / $diasTotalCiclo);
        $valorNovoRestante = $newPlan->valor * ($diasRestantesNoNovo / $diasTotalCiclo);

        $valorJaPago = $contract->valor_pago_ciclo_atual();
        $credito = $valorJaPago - $valorAntigoUsado;
        $diferenca = $valorNovoRestante - $credito;

        return new ProrationResult(
            credito_planoOld: $credito,
            debito_planoNew: $valorNovoRestante,
            diferenca_a_cobrar_hoje: max(0, $diferenca),
            credito_proximo_ciclo: max(0, -$diferenca),
            metadata: [/* dias, valores intermediários */],
        );
    }
}
```

## 6 cenários cobertos por teste

| # | Cenário | Resultado esperado |
|---|---|---|
| 1 | Upgrade R$ 200 → R$ 350 no dia 15 (ciclo 30d) | `diferenca_a_cobrar_hoje = +R$ 75` |
| 2 | Downgrade R$ 350 → R$ 200 no dia 15 | `credito_proximo_ciclo = +R$ 75` |
| 3 | Upgrade em D-1 do vencimento | `diferenca_a_cobrar_hoje ≈ R$ 5` (1 dia de B) |
| 4 | Mudança durante trial (10 dias restantes) | `diferenca = 0` (não cobra; trial mantido) |
| 5 | Mudança durante past_due (cobrança original falhou) | `diferenca = valor_novo_proximo_ciclo` (não tenta proração até resolver) |
| 6 | Mudança no D=0 (mesmo dia início ciclo) | `diferenca = valor_novo_completo` (cobra ciclo completo do novo) |

## Aplicação do resultado

```php
class ApplyProrationService {
    public function apply(Contract $c, Plan $newPlan, ProrationResult $r): void {
        DB::transaction(function () use ($c, $newPlan, $r) {
            $c->update([
                'plan_id' => $newPlan->id,
                'metadata' => array_merge($c->metadata, ['plan_history' => /* ... */]),
            ]);

            ProrationEvent::create([
                'contract_id' => $c->id,
                'tipo' => $r->isUpgrade() ? 'upgrade' : 'downgrade',
                'credito' => $r->credito_planoOld,
                'debito' => $r->debito_planoNew,
                'diferenca' => $r->diferenca_a_cobrar_hoje,
                'aplicado_em' => now(),
            ]);

            if ($r->diferenca_a_cobrar_hoje > 0) {
                Invoice::createImediata($c, $r->diferenca_a_cobrar_hoje);
            } elseif ($r->credito_proximo_ciclo > 0) {
                $c->update(['credito_pendente' => $c->credito_pendente + $r->credito_proximo_ciclo]);
            }
        });
    }
}
```

## Consequências

**Positivas:**
- Cobrança justa (cliente paga proporcional)
- Service puro = teste rápido (zero DB)
- 6 cenários cobertos = baixo risco regressão
- Audit `rb_proration_events` rastreia toda mudança
- Crédito acumulável (cliente faz vários downgrades em sequência)

**Negativas:**
- Cálculo com 30/31/28 dias diferentes — usar `diffInDays` real (não constante 30)
- Dia parcial: arredondar pra dia inteiro (proração diária, não horária)
- Reembolso parcial pode resultar em valor 0,01 — arredondamento amigável

## Decisões em aberto

- [ ] Reajuste anual (IPCA) é proração ou ciclo separado?
- [ ] Crédito não usado em 12 meses: prescreve ou acumula indefinido?
- [ ] Cancelamento mid-cycle gera reembolso proporcional? Decisão de produto

## Tests obrigatórios

```php
test('upgrade R$ 200 → R$ 350 no dia 15 cobra R$ 75 hoje', function () {
    $contract = Contract::factory()->create([
        'plan_id' => Plan::factory()->create(['valor' => 200])->id,
        'next_billing_date' => now()->addDays(15),
    ]);
    $newPlan = Plan::factory()->create(['valor' => 350]);

    $r = (new ProrationService)->calculate($contract, $newPlan, now());

    expect($r->diferenca_a_cobrar_hoje)->toBe(75.00);
});

// + 5 testes pros outros cenários
```

## Alternativas consideradas

- **Não proração** (cobra novo ciclo cheio sempre) — rejeitado: cliente reclama
- **Proração só se diferença > R$ 10** — futuro: pode adicionar threshold via config
- **Cancelar contrato + criar novo** — rejeitado: perde histórico, audit confuso

## Referências

- US-RB-006 (SPEC)
- Stripe proration docs (referência)
- Lago proration logic (open-source benchmark)
