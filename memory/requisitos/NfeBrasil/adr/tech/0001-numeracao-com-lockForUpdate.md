# ADR TECH-0001 (NfeBrasil) · Numeração sequencial com `lockForUpdate`

- **Status**: accepted
- **Data**: 2026-04-24
- **Decisores**: Wagner
- **Categoria**: tech
- **Relacionado**: R-NFE-003

## Contexto

NFe/NFC-e exige **numeração sequencial sem gap**:
- SEFAZ aceita números fora de ordem mas auditor questiona gaps em SPED
- Dupla numeração (2 NFe com mesmo número) → uma das duas é rejeitada / inutilizada
- Volume típico de loja varejo BR: 200-2000 NFC-e/dia, ~40 emissões/min em pico

Concorrência é o problema:

```
Worker A: SELECT MAX(numero) FROM nfe_emissoes WHERE business=X, modelo=65, serie=1; -- 100
Worker B: SELECT MAX(numero) FROM nfe_emissoes WHERE business=X, modelo=65, serie=1; -- 100
Worker A: INSERT numero=101
Worker B: INSERT numero=101  -- DUPLA!
```

Estratégias possíveis:

1. **`AUTO_INCREMENT`** — fácil mas global do MySQL, não é por business
2. **Tabela contador** com `UPDATE counter SET v = v+1 WHERE business=X, modelo=65 RETURNING v` — funciona mas tem latência adicional
3. **`SELECT ... FOR UPDATE`** seguido de `MAX(numero) + 1` em transação — funciona, latência mínima
4. **Redis incr atômico** — rápido, mas separa fonte de verdade do MySQL (problema em recovery)
5. **Pré-alocar lote de números** — complica devolução de não-usados

## Decisão

**Caminho 3** com tabela counter dedicada (combinação do 2 + 3).

```sql
CREATE TABLE nfe_number_sequences (
    business_id INT UNSIGNED NOT NULL,
    modelo CHAR(2) NOT NULL,
    serie INT UNSIGNED NOT NULL,
    last_number BIGINT UNSIGNED NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    PRIMARY KEY (business_id, modelo, serie)
);
```

Service:

```php
class NumberSequenceService {
    public function next(Business $b, string $modelo, int $serie): int {
        return DB::transaction(function () use ($b, $modelo, $serie) {
            $row = DB::table('nfe_number_sequences')
                ->where(['business_id' => $b->id, 'modelo' => $modelo, 'serie' => $serie])
                ->lockForUpdate()
                ->first();

            $next = $row ? $row->last_number + 1 : 1;

            DB::table('nfe_number_sequences')->upsert(
                [['business_id' => $b->id, 'modelo' => $modelo, 'serie' => $serie, 'last_number' => $next]],
                ['business_id', 'modelo', 'serie'],
                ['last_number' => $next, 'updated_at' => now()]
            );

            return $next;
        });
    }
}
```

## Consequências

**Positivas:**
- Sem gap, sem dupla — invariante garantida pelo banco
- Por business + modelo + serie (multi-tenant)
- Funciona em qualquer banco SQL (não Redis-dependent)
- Recovery trivial: tabela `nfe_number_sequences` é parte do backup normal
- Auditoria: alterar manualmente `last_number` é audit-logged

**Negativas:**
- `lockForUpdate` serializa workers do mesmo (business, modelo, serie) — throughput max ~100/s por chave (suficiente; pico real é 10/s)
- Falha de transação devolve número (libera para próxima) — gap é prevenido pela transação não comitada (não há "delete row + retomar")
- Workers cross-business não bloqueiam entre si (lock por chave)

## Tests obrigatórios (R-NFE-003)

```php
test('50 jobs concorrentes geram 50 números distintos sem gap', function () {
    $business = Business::factory()->create();
    $promises = [];

    for ($i = 0; $i < 50; $i++) {
        $promises[] = Process::start("php artisan nfe:emitir --transaction=$i");
    }

    Process::wait($promises);

    $numeros = NfeEmissao::where('business_id', $business->id)->pluck('numero')->sort();
    expect($numeros->unique())->toHaveCount(50);
    expect($numeros->last() - $numeros->first())->toBe(49);  // sem gap
})->skipOnWindows();  // pcntl_fork não roda Windows
```

## Política de gap "legítimo"

Gap **só pode aparecer** em caso de:
- NFe inutilizada (evento `WS-NFeInutilizacao` autorizada SEFAZ) — registrar em `nfe_eventos`
- Falha pós-INSERT pré-envio SEFAZ — número fica órfão; SPED reporta como inutilizada

Worker JAMAIS escolhe número arbitrário para "tampar buraco" — gap permanece.

## Alternativas consideradas

- **`AUTO_INCREMENT`** — rejeitado: global MySQL, não por business
- **Redis incr** — rejeitado: dual source of truth + recovery complica
- **Pré-alocar lote** — rejeitado: gerencia "número não usado" → bug-prone
- **`SELECT ... FOR UPDATE` em `nfe_emissoes`** — rejeitado: tabela grande, lock cobre muito; counter dedicado é cirúrgico

## Referências

- R-NFE-003 (SPEC)
- ARQ-0001 (módulo isolado)
- Manual SEFAZ — política de numeração
