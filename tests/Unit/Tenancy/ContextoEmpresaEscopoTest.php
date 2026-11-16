<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use App\Tenancy\ContextoEmpresa;
use App\Tenancy\ContextoEmpresaAusente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * ADR 0453 — o global scope de empresa (`HasBusinessScope` → `ScopeByBusiness`) diante de uma
 * consulta sem empresa no contexto, nos três modos de `tenancy.escopo_sem_contexto`.
 *
 * Sem banco: só inspeciona o SQL/bindings que o scope gera (`toSql()` aplica os global scopes).
 */
uses(Tests\TestCase::class);

function ctxEmpresaModelo(): Model
{
    return new class extends Model
    {
        use HasBusinessScope;

        protected $table = 'ctx_empresa_modelo';
    };
}

function ctxEmpresaFiltra(Model $m): bool
{
    return str_contains($m->newQuery()->toSql(), 'business_id');
}

beforeEach(function () {
    app()->forgetScopedInstances();
});

it('aberto (padrão): sem sessão e sem usuário não filtra — comportamento legado intacto', function () {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');

    expect(ctxEmpresaFiltra(ctxEmpresaModelo()))->toBeFalse();
});

it('fechado: consulta sem empresa no contexto lança', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    expect(fn () => ctxEmpresaModelo()->newQuery()->toSql())->toThrow(ContextoEmpresaAusente::class);
});

it('fechado: empresa definida no contexto filtra por ela (controle positivo)', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    app(ContextoEmpresa::class)->definir(98);

    $q = ctxEmpresaModelo()->newQuery();

    expect($q->toSql())->toContain('business_id')
        ->and($q->getBindings())->toBe([98]);
});

it('fechado: empresa da sessão web também vale', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    session(['user.business_id' => 99]);

    expect(ctxEmpresaModelo()->newQuery()->getBindings())->toBe([99]);
});

it('fechado: comoPlataforma libera a leitura sem filtro, só dentro do bloco', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    $ctx = app(ContextoEmpresa::class);

    $dentro = $ctx->comoPlataforma('teste ADR 0453', fn () => ctxEmpresaFiltra(ctxEmpresaModelo()));

    expect($dentro)->toBeFalse()
        ->and($ctx->emModoPlataforma())->toBeFalse()
        ->and(fn () => ctxEmpresaModelo()->newQuery()->toSql())->toThrow(ContextoEmpresaAusente::class);
});

it('log: sem empresa não filtra e registra o ponto', function () {
    config()->set('tenancy.escopo_sem_contexto', 'log');
    Log::spy();

    expect(ctxEmpresaFiltra(ctxEmpresaModelo()))->toBeFalse();

    Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => $msg === 'tenancy.escopo_sem_contexto')->once();
});

it('modo inválido cai no aberto', function () {
    config()->set('tenancy.escopo_sem_contexto', 'qualquer-coisa');

    expect(ContextoEmpresa::modo())->toBe('aberto');
});
