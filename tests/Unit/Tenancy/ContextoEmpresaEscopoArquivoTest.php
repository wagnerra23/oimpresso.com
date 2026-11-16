<?php

declare(strict_types=1);

use App\Tenancy\ContextoEmpresa;
use App\Tenancy\ContextoEmpresaAusente;
use Modules\Arquivos\Entities\Arquivo;

/**
 * ADR 0453 — escopo de empresa do Arquivo (Modules/Arquivos) lê o ContextoEmpresa.
 * Sem banco: inspeciona os bindings da query e dispara o evento `creating` à mão.
 *
 * Contrato (lote L2 da auditoria Arquivos 2026-11-05, D1):
 *  - aberto (padrão): legado — sessão `user.business_id`, depois `business.id`; sem sessão, não filtra.
 *  - fechado: empresa do contexto (job/comando/API); sem empresa, lança — na consulta e no create.
 *  - comoPlataforma(): não filtra.
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    app()->forgetScopedInstances();
});

function arquivoDispararCreating(Arquivo $arquivo): void
{
    (fn () => $this->fireModelEvent('creating'))->call($arquivo);
}

it('aberto: sem sessão não filtra (legado intacto)', function () {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');

    expect(Arquivo::query()->getBindings())->toBe([]);
});

it('aberto: com sessão filtra pela empresa da sessão (controle positivo)', function () {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');
    session(['user.business_id' => 98]);

    expect(Arquivo::query()->getBindings())->toBe([98]);
});

it('aberto: só `business.id` na sessão continua filtrando (legado do Arquivo)', function () {
    config()->set('tenancy.escopo_sem_contexto', 'aberto');
    session(['business.id' => 99]);

    expect(Arquivo::query()->getBindings())->toBe([99]);
});

it('fechado: sem empresa no contexto a consulta lança', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    expect(fn () => Arquivo::query()->toSql())->toThrow(ContextoEmpresaAusente::class);
});

it('fechado: empresa do contexto (job/comando) filtra', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    app(ContextoEmpresa::class)->definir(98);

    expect(Arquivo::query()->getBindings())->toBe([98]);
});

it('fechado: comoPlataforma não filtra', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    $bindings = app(ContextoEmpresa::class)->comoPlataforma('teste ADR 0453 Arquivos', fn () => Arquivo::query()->getBindings());

    expect($bindings)->toBe([]);
});

it('fechado: create sem empresa lança em vez de gravar business_id = 0', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');

    expect(fn () => arquivoDispararCreating(new Arquivo()))->toThrow(ContextoEmpresaAusente::class);
});

it('create sem business_id explícito usa a empresa do contexto', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    app(ContextoEmpresa::class)->definir(98);

    $arquivo = new Arquivo();
    arquivoDispararCreating($arquivo);

    expect($arquivo->business_id)->toBe(98);
});

it('create com business_id explícito não é sobrescrito pelo contexto', function () {
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
    app(ContextoEmpresa::class)->definir(98);

    $arquivo = new Arquivo(['business_id' => 99]);
    arquivoDispararCreating($arquivo);

    expect($arquivo->business_id)->toBe(99);
});
