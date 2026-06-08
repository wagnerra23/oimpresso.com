# ADR ARQ-0004 (RecurringBilling) · Take rate vs Merchant of Record

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq (decisão de produto/precificação/legal)
- **Relacionado**: `Financeiro/adr/arq/0004-revenue-modelo-hibrido-subscription-take-rate.md`, R-RB-014

## Contexto

Quem é o **merchant of record (MoR)** define quem aparece no extrato do cliente final, quem emite NFSe, e quem responde por chargebacks/disputes:

| Modelo | MoR | Como funciona | Quem emite NFSe |
|---|---|---|---|
| **Gateway próprio (oimpresso)** | oimpresso | Credencial Asaas é nossa; recebemos dinheiro; repassamos pro tenant | oimpresso emite p/ cliente final |
| **Merchant-of-record do cliente** | Tenant | Credencial Asaas é do tenant; tenant recebe direto; oimpresso só dispara | Tenant emite |
| **Híbrido** (BR comum) | Depende do plano | Tenant Pro: gateway próprio; Tenant Enterprise: MoR próprio | Depende |

Trade-offs:

**Gateway próprio (oimpresso = MoR):**
- ✅ Take rate fácil (já estamos no fluxo do dinheiro)
- ✅ NFSe oimpresso emite — tenant não precisa configurar Focus/PlugNotas
- ❌ Compliance pesado: oimpresso vira "agente de pagamento" (precisa licença BCB ou parceria com instituição licenciada)
- ❌ Risco de chargeback recai em oimpresso
- ❌ Reforma tributária 2026: split-payment afeta — oimpresso retém CBS/IBS na fonte
- ❌ Custo aumenta com escala (mais transações = mais compliance)

**Tenant = MoR:**
- ✅ Tenant tem controle total (cliente vê CNPJ tenant no extrato)
- ✅ Compliance fica com tenant (oimpresso = "software")
- ✅ Sem licença BCB necessária
- ❌ Sem take rate (oimpresso fica só com subscription)
- ❌ Tenant precisa configurar Asaas/Iugu próprio (fricção onboarding)
- ❌ Tenant emite NFSe (pode não querer)

## Decisão

**Modelo dual com escolha por business:**

| Plano | MoR | Take rate | NFSe emitida por |
|---|---|---|---|
| **Starter R$ 149** | Tenant (gateway do cliente) | 0% | Tenant (Focus/PlugNotas tenant) |
| **Pro R$ 449** | oimpresso (gateway próprio padrão) OU Tenant (opt-out) | 0,8% capped R$ 19,90 | oimpresso (próprio) ou Tenant |
| **Enterprise R$ 999** | Tenant (cliente Enterprise prefere MoR próprio) | 0% | Tenant |

Defaults inteligentes:
- **Onboarding Starter:** "configure seu Asaas" — tenant aprende setup
- **Onboarding Pro:** "use nosso gateway" — friction zero, ativação no mesmo dia
- **Enterprise:** "configuração assistida do gateway próprio" — onboarding com support

Implementação:
- `pg_credentials.owner_type` enum (`oimpresso` | `tenant`)
- Take rate calculado só se `owner_type=oimpresso` (R-RB-014)
- NFSe `nfse_emissoes.emitter_business_id` aponta pra tenant ou pro business interno do oimpresso

## Compliance e legal (consequências MoR oimpresso)

Para oimpresso ser MoR no plano Pro, precisamos:

1. **Parceria com instituição licenciada BCB** (Asaas, PagBank, Iugu são instituições de pagamento — podemos usar credencial deles em modelo "agregador")
2. **CNPJ próprio recebendo**: oimpresso emite NFSe pelo serviço de software/intermediação ao tenant; tenant emite NFSe ao cliente final
3. **Conta-escrow**: dinheiro fica em "conta intermediária" até repassar pro tenant (D+1 ou D+30 dependendo do contrato)
4. **Compliance LGPD/PCI**: já é obrigação independente, mas MoR aumenta atenção
5. **Reforma Tributária 2026**: split-payment vai obrigar repasse de CBS/IBS direto pra Receita; afeta operação MoR

Plano: usar **Asaas como agregador licenciado** — oimpresso é "marketplace" no Asaas. Asaas cuida do compliance bancário; oimpresso só calcula split.

## Pattern de cobrança com take rate

```php
class TakeRateCalculator {
    public function calcular(ChargeAttempt $charge): ?Money {
        $cred = $charge->paymentMethod->credential;
        if ($cred->owner_type !== 'oimpresso') return null;  // sem take rate

        $valor = $charge->invoice->valor;
        $rate = 0.008;
        $cap = 19.90;

        $fee = min($valor * $rate, $cap);
        return Money::brl($fee);
    }
}

// Listener
class CalcularTakeRateAoPagamento implements ShouldQueue {
    public function handle(InvoicePaid $event): void {
        $fee = (new TakeRateCalculator)->calcular($event->charge);
        if ($fee === null) return;

        RbRevenueEvent::create([
            'business_id' => $event->charge->business_id,  // do tenant
            'invoice_id' => $event->invoice->id,
            'fee_calculado' => $fee->amount,
            'mes_competencia' => now()->format('Y-m'),
        ]);
    }
}
```

## Consequências

**Positivas:**
- Tenant pequeno paga só subscription (Starter)
- Tenant médio (Pro) tem opção: economizar via gateway próprio OU pagar take rate por conveniência
- Tenant grande (Enterprise) controla tudo
- Receita previsível subscription + variável take rate
- NFSe automatizada onde MoR é oimpresso = enorme valor pra Pro (tenant não configura nada)

**Negativas:**
- Complexidade de billing oimpresso: medir GMV via gateway próprio + fechar mês + cobrar
- Compliance: sair do escopo "software puro" pra "software + agregador" — atenção legal
- Conflito ético potencial: oimpresso vê o GMV de cada tenant (transparência)
- Tenant pode perceber take rate como "imposto adicional" — comunicação chave

## Decisões em aberto

- [ ] Asaas oferece "marketplace mode" hoje? Confirmar antes de comprometer
- [ ] Stripe Connect como alternativa? Stripe BR é caro mas robusto
- [ ] Mudar plano (Pro → Starter): quem emite NFSe das próximas faturas se MoR muda? Migração delicada
- [ ] Take rate sobre estorno: aplica? Devolve? Política clara

## Alternativas consideradas

- **MoR oimpresso pra todos os planos** — rejeitado: compliance pesado, custo alto pra Starter
- **MoR tenant pra todos os planos** — rejeitado: perde take rate; perde diferencial de "billing chave-na-mão"
- **Take rate sem MoR (taxa sobre GMV mesmo gateway tenant)** — rejeitado: tenant não aceita pagar fee em algo que oimpresso não toca

## Referências

- `Financeiro/adr/arq/0004` — mesmo modelo, contexto diferente (Boleto)
- R-RB-014 (SPEC)
- Asaas marketplace mode docs
- Stripe Connect (referência)
- Reforma Tributária 2026 split-payment
