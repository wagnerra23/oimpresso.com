<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Ponto\Entities\BancoHorasMovimento;
use Modules\Ponto\Entities\BancoHorasSaldo;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Entities\Rep;

/**
 * Wave 18 D1 — Multi-tenant global scope nas 5 Entities Ponto que faltavam trait.
 *
 * Valida que `HasBusinessScope` aplica `ScopeByBusiness` automatico em:
 * Rep, Importacao, BancoHorasMovimento, BancoHorasSaldo, Marcacao.
 *
 * Cobertura Tier 0 ([ADR 0093]): scope global obrigatorio em qualquer Eloquent
 * Model que toca dados de negocio. Pest valida que `getGlobalScopes()` retorna
 * `ScopeByBusiness` (smoke estrutural — query real com session() depende de
 * MySQL + biz seeds).
 *
 * SQLite skip — testes que dependem de schema Ponto (`ponto_marcacoes` etc)
 * requerem MySQL real (ADR 0101) por causa de triggers append-only.
 *
 * @see ADR 0093 multi-tenant Tier 0 IRREVOGAVEL
 * @see ADR 0094 Constituicao v2 principio duro 6
 */
uses(Tests\TestCase::class);

it('Rep tem ScopeByBusiness aplicado via HasBusinessScope (Wave 18 D1)', function () {
    $scopes = (new Rep)->getGlobalScopes();

    expect(array_key_exists(ScopeByBusiness::class, $scopes))->toBeTrue(
        'Rep DEVE ter ScopeByBusiness — cross-tenant leak permite spoofing NSR.'
    );
});

it('Importacao tem ScopeByBusiness aplicado via HasBusinessScope (Wave 18 D1)', function () {
    $scopes = (new Importacao)->getGlobalScopes();

    expect(array_key_exists(ScopeByBusiness::class, $scopes))->toBeTrue(
        'Importacao DEVE ter ScopeByBusiness — cross-tenant exporia arquivos AFD outras empresas.'
    );
});

it('BancoHorasMovimento tem ScopeByBusiness aplicado via HasBusinessScope (Wave 18 D1)', function () {
    $scopes = (new BancoHorasMovimento)->getGlobalScopes();

    expect(array_key_exists(ScopeByBusiness::class, $scopes))->toBeTrue(
        'BancoHorasMovimento DEVE ter ScopeByBusiness — leak vaza saldo HE entre empresas (CLT).'
    );
});

it('BancoHorasSaldo tem ScopeByBusiness aplicado via HasBusinessScope (Wave 18 D1)', function () {
    $scopes = (new BancoHorasSaldo)->getGlobalScopes();

    expect(array_key_exists(ScopeByBusiness::class, $scopes))->toBeTrue(
        'BancoHorasSaldo DEVE ter ScopeByBusiness — saldo agregado por colaborador per business.'
    );
});

it('Marcacao tem ScopeByBusiness aplicado via HasBusinessScope (Wave 18 D1)', function () {
    $scopes = (new Marcacao)->getGlobalScopes();

    expect(array_key_exists(ScopeByBusiness::class, $scopes))->toBeTrue(
        'Marcacao DEVE ter ScopeByBusiness — exposicao cross-tenant viola Portaria 671 (acesso fiscal por empresa).'
    );
});

it('Marcacao mantem boot custom UUID — trait HasBusinessScope nao quebra boot override', function () {
    // Smoke estrutural — Marcacao::creating() injeta UUID se key vazio.
    // Trait HasBusinessScope usa bootHasBusinessScope() (Eloquent magic) — convive.
    $marcacao = new Marcacao;

    expect($marcacao->getKeyName())->toBe('id');
    expect($marcacao->incrementing)->toBeFalse('UUID string key (nao auto-increment).');
    expect($marcacao->getKeyType())->toBe('string');
});

it('Marcacao append-only preservado — update() lanca RuntimeException (Wave 18 trait nao quebra defesa)', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: schema Ponto requer MySQL.');
    }
    if (! Schema::hasTable('ponto_marcacoes')) {
        $this->markTestSkipped('Schema Ponto ausente.');
    }

    $marcacao = new Marcacao(['business_id' => 99]);

    expect(fn () => $marcacao->update(['tipo' => Marcacao::TIPO_SAIDA]))
        ->toThrow(RuntimeException::class, 'append-only');
});
