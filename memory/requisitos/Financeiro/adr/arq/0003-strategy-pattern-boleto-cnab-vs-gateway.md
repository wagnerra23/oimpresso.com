# ADR ARQ-0003 (Financeiro) · Boleto via Strategy: CNAB direto OU gateway

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, ARQ-0002, `_Ideias/CobrancaRecorrente/README.md`

## Contexto

Tenant tem 3 perfis distintos pra emitir boleto:

1. **PME 1-5 funcionários** — quer 1 clique e zero conhecimento bancário. Pega gateway (Asaas / Iugu / Pagar.me) que cobra ~R$ 1,99 por boleto pago, retorna URL pronta, webhook automático.
2. **PME 10-50 funcionários, com conta empresa em banco grande** — já tem convênio CNAB com Sicoob/BB/Itaú. Não vai pagar fee por boleto (custo zero por boleto registrado direto). Quer remessa CNAB diária.
3. **Multi-banco / Enterprise** — precisa misturar: 80% por gateway pra agilidade, 20% boleto direto Itaú pro cliente VIP que exige.

Forçar 1 caminho perde mercado:
- Só gateway: PME bancarizada não paga fee
- Só CNAB: microempresário desiste no setup (homologação CNAB leva 2-4 semanas com gerente)

## Decisão

Usar **Strategy Pattern** com escolha por business:

```php
interface BoletoStrategy {
    public function emitir(Titulo $t): BoletoEmitido;
    public function cancelar(BoletoRemessa $r): void;
    public function statusAtual(BoletoRemessa $r): BoletoStatus;
}

class GatewayStrategy implements BoletoStrategy { /* Asaas / Iugu / Pagar.me */ }
class CnabDirectStrategy implements BoletoStrategy { /* eduardokum/laravel-boleto */ }
class HybridStrategy implements BoletoStrategy {
    // delega por regra: titulo.cliente.classe == 'VIP' → CnabDirect, senão Gateway
}
```

`BoletoStrategyFactory::for($business)` lê config `fin_business_settings.boleto_strategy` e retorna concreta.

## Consequências

**Positivas:**
- Tenant escolhe e troca sem migração de dados (tabela `fin_boleto_remessas` é igual; muda só `provider`)
- Onboarding: free/pro defaults Gateway (zero homologação); enterprise pode CNAB direto
- A/B test trivial: `HybridStrategy` decide por regra
- Lib `eduardokum/laravel-boleto` cobre 7 bancos majores BR; Asaas/Iugu cobrem o resto
- **Take rate** (revenue thesis): 0,5% capped R$ 9,90 só faz sentido em GatewayStrategy (oimpresso intermedia). CnabDirect = sem take rate (cliente bancariza direto)

**Negativas:**
- Mais código (3 strategies + factory + tests por strategy)
- Webhook idempotency precisa funcionar em N providers (resolvido via tabela `pg_webhook_events` compartilhada — ADR TECH-0001)
- Status mapping diferente por provider (Asaas tem 8 status, Iugu tem 6, CNAB retorna código numérico) — normalizar em enum interno `BoletoStatus`

## Alternativas consideradas

- **Forçar gateway só** — rejeitado: perde mercado de PME bancarizada
- **Forçar CNAB só** — rejeitado: barreira de entrada alta (homologação)
- **Lib única** (ex: só `eduardokum/laravel-boleto`) — rejeitado: lib não cobre PIX moderno + webhook gateway
- **Adapter per banco** (não por categoria) — rejeitado: code explosion (BB, Itaú, Sicoob, Bradesco, Santander, Caixa, Banrisul, Sicredi…)

## Pattern obrigatório de teste

Cada concrete strategy tem teste contract (`BoletoStrategyContractTest`) garantindo:
- `emitir` retorna `BoletoRemessa` com `status='gerado'`
- `emitir` é idempotente por `titulo_id`
- `statusAtual` mapeia para enum interno
- `cancelar` muda status para `cancelado`

## Referências

- `_Ideias/CobrancaRecorrente/README.md` — pattern Adapter idêntico em PaymentGateway
- `eduardokum/laravel-boleto` — lib CNAB
- Asaas API docs (cobranças/webhooks)
