# ADR ARQ-0001 (RecurringBilling) · 6 sub-módulos event-driven, não monolito

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: `Financeiro/adr/arq/0002-eventos-em-vez-de-chamadas-diretas.md`

## Contexto

Domínio de billing recorrente abrange responsabilidades muito diferentes:
- Lifecycle assinatura + geração fatura (state machine pesado)
- Cobrança em N gateways (integrações externas)
- Pix Automático com estados próprios do BCB (compliance específico)
- NFSe (emissão fiscal — CONFAZ + ABRASF + 5570 municípios)
- Recuperação de inadimplência (régua + multicanal + ML)
- Boleto CNAB (legacy mas presente em PME bancarizada)

Construir 1 módulo monolítico:
- Service Provider gigante (50+ classes)
- Tabela `recurring_billing_*` virando 30+ tabelas
- Difícil licenciar separadamente (alguns tenants só querem billing, não NFSe)
- Atualização de feature em uma área força redeploy de tudo
- Test suite cresce até ficar lento

## Decisão

**6 sub-módulos nwidart isolados**:

```
Modules/
├── RecurringBilling/    (núcleo: plans, contracts, invoices, proration)
├── PaymentGateway/      (adapters Asaas/Iugu/Pagar.me/Stripe/MP)
├── PixAutomatico/       (BCB JRC, dedicated)
├── NFSe/                (Focus/PlugNotas/NFEio adapters)
├── Dunning/             (régua multicanal)
└── Boleto/              (CNAB direto — opcional, compartilha com Financeiro)
```

Comunicação **só por evento Laravel** com listeners idempotentes (mesmo padrão Financeiro/ARQ-0002).

Cada sub-módulo:
- Tem seu prefix de tabela (`rb_`, `pg_`, `pa_`, `nfse_`, `dun_`, `bol_`)
- Tem suas permissões Spatie (`recurring-billing.*`, `payment-gateway.*`, etc.)
- Pode ser instalado/desinstalado isoladamente
- Fila de jobs separada (não atrapalha entre si)
- Test suite independente

## Consequências

**Positivas:**
- Tenant pode ativar só **PaymentGateway + Boleto** (cobrança avulsa) sem RecurringBilling
- Tenant pode ativar **RecurringBilling + Dunning** sem Pix Automático ainda
- Failures isoladas: NFSe provider down não derruba cobrança
- Time pode trabalhar em paralelo nos sub-módulos sem conflito
- License separada por sub-módulo (Officeimpresso pode vender combos)
- Onda 1 = só PaymentGateway + 1 adapter; entrega valor antes do resto

**Negativas:**
- Mais boilerplate (6 ServiceProviders, 6 sets de migrations, 6 modulestest)
- Documentação distribuída (este SPEC abrange os 6 — ok pra MVP)
- Cross-módulo via evento adiciona ~100ms de latência (aceitável)
- Tenant precisa entender que "PaymentGateway é dependência de RecurringBilling" — UI explicita

## Pattern obrigatório

```php
// Em RecurringBilling
namespace Modules\RecurringBilling\Listeners;

class CobraInvoice implements ShouldQueue {
    public string $queue = 'rb_charges';

    public function handle(InvoiceGenerated $event): void {
        // NÃO chama PaymentGateway::charge() direto
        // Em vez disso:
        event(new \Modules\PaymentGateway\Events\ChargeRequested(
            invoiceId: $event->invoice->id,
            providerHint: $event->invoice->contract->preferred_provider,
        ));
    }
}

// Em PaymentGateway
namespace Modules\PaymentGateway\Listeners;

class HandleChargeRequest implements ShouldQueue {
    public function handle(ChargeRequested $event): void {
        // ... cobra ...
        event(new PaymentSucceeded(/* ... */));
    }
}
```

## Estratégia de evolução

Ondas de implementação sugeridas (12-14 semanas total):

1. **Onda 1 — PaymentGateway + Asaas** (2 sem) — entrega valor isolado: cobrança avulsa
2. **Onda 2 — RecurringBilling núcleo** (3 sem) — depende de Onda 1
3. **Onda 3 — NFSe via Focus/PlugNotas** (2 sem) — independente, plugável
4. **Onda 4 — Dunning email-only** (1 sem) — Onda 3 desbloqueia
5. **Onda 5 — Pix Automático** (2 sem) — depende homologação PSP
6. **Onda 6 — Boleto CNAB direto** (3 sem) — opcional
7. **Onda 7 — 2º adapter** (1 sem) — Iugu/Pagar.me

Total realista: 12-14 semanas dev sênior.

## Alternativas consideradas

- **Monolito** — rejeitado: dor de manutenção, license inflexível, fila única
- **Pacotes Composer separados** — rejeitado: overhead, todos rodam só dentro do oimpresso
- **Microservices REST** — overkill: latência piora, infra adicional, sem ganho real

## Referências

- `_Ideias/CobrancaRecorrente/README.md`
- `Financeiro/adr/arq/0002` (mesmo padrão event-driven em outro módulo)
- `auto-memória: reference_ultimatepos_integracao.md`
- Lago (https://github.com/getlago/lago) — referência arquitetural
