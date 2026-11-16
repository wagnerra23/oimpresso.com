<?php

declare(strict_types=1);

use App\Tenancy\ContextoEmpresa;
use App\Tenancy\ContextoEmpresaAusente;
use Illuminate\Database\Eloquent\Model;
use Modules\Financeiro\Models\Concerns\BusinessScope as EscopoFinanceiro;
use Modules\NFSe\Models\Concerns\NfseBusinessScope as EscopoNfse;

/**
 * ADR 0453, fase 2 — scopes de empresa dos módulos Financeiro (`BusinessScope`) e NFSe
 * (`NfseBusinessScope`) leem o ContextoEmpresa. Sem banco: inspeciona os bindings gerados.
 *
 * Contrato por modo:
 *  - aberto (padrão): legado — só a sessão web; sem sessão, não filtra.
 *  - fechado: empresa do contexto (job/API/sessão); sem empresa, lança.
 */
uses(Tests\TestCase::class);

dataset('escopos de módulo', [
    'Financeiro' => [fn () => new class extends Model
    {
        use EscopoFinanceiro;

        protected $table = 'ctx_fin_modelo';
    }],
    'NFSe' => [fn () => new class extends Model
    {
        use EscopoNfse;

        protected $table = 'ctx_nfse_modelo';
    }],
]);

beforeEach(function () {
    app()->forgetScopedInstances();
});

it('aberto: sem sessão não filtra (legado intacto)', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');

    expect($modelo()->newQuery()->getBindings())->toBe([]);
})->with('escopos de módulo');

it('aberto: com sessão filtra pela empresa da sessão (controle positivo)', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');
    session(['user.business_id' => 98]);

    expect($modelo()->newQuery()->getBindings())->toBe([98]);
})->with('escopos de módulo');

it('aberto: empresa definida só no contexto (job) NÃO muda o legado', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');
    app(ContextoEmpresa::class)->definir(77);

    expect($modelo()->newQuery()->getBindings())->toBe([]);
})->with('escopos de módulo');

it('fechado: sem empresa no contexto lança', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    expect(fn () => $modelo()->newQuery()->toSql())->toThrow(ContextoEmpresaAusente::class);
})->with('escopos de módulo');

it('fechado: empresa do contexto (job/API) filtra', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    app(ContextoEmpresa::class)->definir(77);

    expect($modelo()->newQuery()->getBindings())->toBe([77]);
})->with('escopos de módulo');

it('fechado: comoPlataforma não filtra', function (Closure $modelo) {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    $bindings = app(ContextoEmpresa::class)->comoPlataforma('teste ADR 0453', fn () => $modelo()->newQuery()->getBindings());

    expect($bindings)->toBe([]);
})->with('escopos de módulo');
