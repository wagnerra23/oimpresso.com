# ADR ARQ-0002 (Financeiro) · Comunicação cross-módulo via eventos, não chamadas diretas

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: arq
- **Relacionado**: ARQ-0001, `_Ideias/CobrancaRecorrente/README.md` (mesmo princípio em RecurringBilling)

## Contexto

Financeiro consome eventos de 3+ módulos:
- Core UltimatePOS — `Transaction` (sells/purchases) → cria título
- NfeBrasil — `NfeAutorizada` → anexa chave fiscal
- RecurringBilling — `RecurringInvoiceGenerated` → cria título a receber
- PontoWr2 — `FolhaFechada` → cria N títulos a pagar

E publica 5+ eventos consumidos por outros módulos: `TituloCriado`, `TituloBaixado`, `TituloCancelado`, `BoletoEmitido`, `BoletoPago`.

Se cada módulo chamar método público do outro direto (`Financeiro::criarTitulo(...)`), temos:
- Dependência cíclica (NfeBrasil precisa Financeiro pra criar título de venda; Financeiro precisa NfeBrasil pra DRE com NF) → impossível resolver em ServiceProvider
- Falha sincrôna: NfeBrasil down derruba venda
- Teste impossível sem mock de tudo

## Decisão

Comunicação **só por eventos Laravel** com listeners em queue. Regras:

1. Nenhum módulo importa classe de outro módulo (exceção: contracts em `App\Contracts\` se virar necessário)
2. Listeners são **idempotentes** (UNIQUE constraint ou `firstOrCreate`)
3. Listener falhar não derruba publisher (default: queue retry 3x, depois `failed_jobs`)
4. Payload do evento é **valor**, não Eloquent (snapshot dos dados na hora do evento — evita estado mutado)

## Consequências

**Positivas:**
- Módulos plugáveis: desabilitar NfeBrasil não quebra venda (Financeiro só não terá `nfe_chave` no título)
- Testável isoladamente (`Event::fake()` + assert `dispatched`)
- Auditoria via `pg_webhook_events` + Laravel Horizon
- Refatoração: trocar implementação interna sem mexer em consumers

**Negativas:**
- Latência: 50-200ms até listener processar (aceitável; não é caminho crítico)
- Eventual consistency: tela de venda mostra "Financeiro pendente" por 1-2s antes do título aparecer
- Boilerplate: cada integração precisa Event + Listener + teste

## Alternativas consideradas

- **Chamadas síncronas** — rejeitado: acoplamento + falha cascata
- **Service Bus externo (Redis Streams, RabbitMQ)** — rejeitado por enquanto: Laravel Queue + Horizon resolve até 1k events/min; só upgrade se medir gargalo
- **Domain Events com EventStore** — rejeitado: overkill pra single-tenant-per-business; volta na pauta se virar event-sourced

## Pattern obrigatório

```php
// Publisher (Financeiro)
event(new TituloBaixado($titulo, $baixa));  // sync dispatch, listeners decidem queue

// Listener idempotente (em outro módulo)
class AnotaBaixaNoBI implements ShouldQueue
{
    public string $queue = 'bi';
    public int $tries = 3;

    public function handle(TituloBaixado $event): void
    {
        BiBaixa::firstOrCreate(
            ['titulo_baixa_id' => $event->baixa->id],  // idempotência
            [/* ... payload ... */]
        );
    }
}
```

## Referências

- ARQ-0001 (este módulo)
- `_Ideias/CobrancaRecorrente/README.md` §"Comunicação por eventos"
- `auto-memória: reference_db_schema.md` — eventos `UserCreatedOrModified` core já segue esse pattern
