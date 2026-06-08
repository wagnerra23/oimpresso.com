# ADR TECH-0001 (Financeiro) · Idempotência obrigatória em toda mutação financeira

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: ARQ-0002, `_Ideias/CobrancaRecorrente/README.md` §"Idempotência obrigatória"

## Contexto

Mutações financeiras NÃO podem duplicar:

- **Baixa**: dois cliques rápidos no botão "Confirmar pagamento" → 2 baixas → saldo errado, conciliação cresce dívida
- **Webhook gateway**: Asaas garante "at least once" → mesmo evento chega 2-5x normalmente
- **Listener evento Laravel**: queue retry 3x → listener tem que ser idempotente
- **Conciliação OFX**: subir mesmo arquivo 2x não pode dobrar movimentações
- **Auto-criação título de venda**: se observer roda 2x (raro mas acontece em re-deploy), 2 títulos pra mesma venda

Em fluxo financeiro, dupla execução = erro contábil que vira dor de cabeça pro contador (e desconfiança do cliente).

## Decisão

**Toda mutação financeira tem idempotency_key explícito + UNIQUE constraint no banco.**

| Cenário | Estratégia | Constraint |
|---|---|---|
| **Baixa** (frontend) | UUID gerado no submit, persistido em `fin_titulo_baixas.idempotency_key` | `UNIQUE (business_id, idempotency_key)` |
| **Webhook gateway** | `event_id` do provider (Asaas: `id`; Iugu: `id`; etc.) persistido em `pg_webhook_events.event_id` | `UNIQUE (provider, event_id)` |
| **Auto-criação título de venda** | `(origem='venda', origem_id=tx.id, parcela_numero=N)` | `UNIQUE (business_id, origem, origem_id, parcela_numero)` em `fin_titulos` |
| **Conciliação OFX** | SHA256 do arquivo | `UNIQUE (business_id, file_hash)` em `fin_conciliacao_runs` |
| **Match conciliação ↔ baixa** | `(conciliacao_run_id, ofx_fitid, titulo_id)` | `UNIQUE` |
| **Boleto remessa** | `titulo_id` enquanto `status IN (gerado, enviado)` | UNIQUE parcial (where status...) |
| **Listener evento** | Sempre `firstOrCreate` ou `updateOrInsert` na tabela target | (depende da tabela) |

Service layer SEMPRE valida `idempotency_key` antes de mutar:

```php
class BaixaService {
    public function registrar(Titulo $t, BaixaPayload $p): TituloBaixa
    {
        return DB::transaction(function () use ($t, $p) {
            $existing = TituloBaixa::where('business_id', $t->business_id)
                ->where('idempotency_key', $p->idempotencyKey)
                ->first();
            if ($existing) return $existing;  // 200 com row existente

            $t->lockForUpdate();  // evita race condition entre check e insert
            // ... validações ...
            return TituloBaixa::create([
                'idempotency_key' => $p->idempotencyKey,
                /* ... */
            ]);
        });
    }
}
```

## Consequências

**Positivas:**
- Webhook 2x = no-op
- Frontend pode retentar request com mesmo key sem medo
- Re-deploy em queue worker no meio de evento = 0 efeito colateral
- Conciliação OFX virou problema fácil (hash do arquivo)
- Audit trail: `idempotency_key` na tabela permite rastrear "qual request originou essa mutação?"

**Negativas:**
- Frontend precisa gerar UUID antes do submit (mais boilerplate, mas já é pattern do TanStack Query mutations)
- Index extra em cada tabela crítica
- Race condition entre `check` e `insert` exige `lockForUpdate` ou `INSERT IGNORE` (resolvido)

## Pattern obrigatório de teste

Toda Service de mutação tem teste `*IdempotenciaTest`:

```php
test('registrar baixa com mesma idempotency_key 2x retorna mesma row', function () {
    $t = Titulo::factory()->create();
    $key = Str::uuid();

    $b1 = (new BaixaService)->registrar($t, BaixaPayload::make(['valor' => 100, 'idempotencyKey' => $key]));
    $b2 = (new BaixaService)->registrar($t, BaixaPayload::make(['valor' => 100, 'idempotencyKey' => $key]));

    expect($b1->id)->toBe($b2->id);
    expect(TituloBaixa::count())->toBe(1);
});

test('100 requests concorrentes com mesma key = 1 baixa', function () {
    // pcntl_fork ou parallel HTTP client — só roda em CI Linux
})->skipOnWindows();
```

## Alternativas consideradas

- **Idempotência só nos webhooks (não em UI)** — rejeitado: usuário double-click é mais comum que webhook duplo
- **Locking pessimista no titulo** sem idempotency_key — rejeitado: lock cobre concorrência mas não retentativa
- **Baseado em hash do payload** (sem key explícito) — rejeitado: payload pode mudar (timestamp diferente) e dupla passa

## Referências

- `_Ideias/CobrancaRecorrente/README.md` — mesma decisão em PaymentGateway / Pix Automático
- Stripe API idempotency docs (referência canônica)
- `auto-memória: feedback_format_now_local_e_default_datetime.md` — bug de Edit silencioso ensinou: validar via grep no servidor pós-deploy
