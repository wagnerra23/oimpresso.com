<?php

declare(strict_types=1);

use App\Concerns\HasBusinessScope;
use App\Tenancy\ContextoEmpresa;
use App\Tenancy\ContextoEmpresaAusente;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Model;

/**
 * ADR 0453, fase 2 — job com `$businessId` roda com essa empresa no contexto (pipe do Bus
 * registrado no AppServiceProvider) e o contexto anterior volta no fim. Sem banco: inspeciona os
 * bindings que o global scope gera dentro do job, no modo `fechado`.
 */
uses(Tests\TestCase::class);

function ctxJobModelo(): Model
{
    return new class extends Model
    {
        use HasBusinessScope;

        protected $table = 'ctx_job_modelo';
    };
}

beforeEach(function () {
    app()->forgetScopedInstances();
    config()->set('tenancy.escopo_sem_contexto', 'fechado');
});

it('job com businessId público filtra pela empresa do job', function () {
    $job = new class
    {
        public int $businessId = 98;

        public array $bindings = [];

        public function handle(): void
        {
            $this->bindings = ctxJobModelo()->newQuery()->getBindings();
        }
    };

    app(Dispatcher::class)->dispatchNow($job);

    expect($job->bindings)->toBe([98])
        ->and(app(ContextoEmpresa::class)->atual())->toBeNull();
});

it('job com businessId protegido (construtor) também vale', function () {
    $job = new class(77)
    {
        public array $bindings = [];

        public function __construct(protected int $businessId) {}

        public function handle(): void
        {
            $this->bindings = ctxJobModelo()->newQuery()->getBindings();
        }
    };

    app(Dispatcher::class)->dispatchNow($job);

    expect($job->bindings)->toBe([77]);
});

it('contexto anterior volta depois do job (controle de vazamento entre jobs)', function () {
    $ctx = app(ContextoEmpresa::class);
    $ctx->definir(99);

    $job = new class
    {
        public int $businessId = 98;

        public ?int $dentro = null;

        public function handle(): void
        {
            $this->dentro = app(ContextoEmpresa::class)->atual();
        }
    };
    app(Dispatcher::class)->dispatchNow($job);

    expect($job->dentro)->toBe(98)->and($ctx->atual())->toBe(99);
});

it('job sem businessId não ganha empresa: no modo fechado a consulta lança', function () {
    $job = new class
    {
        public function handle(): void
        {
            ctxJobModelo()->newQuery()->toSql();
        }
    };

    expect(fn () => app(Dispatcher::class)->dispatchNow($job))->toThrow(ContextoEmpresaAusente::class);
});

it('businessId zero ou vazio é ignorado', function () {
    $job = new class
    {
        public ?int $businessId = 0;
    };

    expect(\App\Tenancy\DefinirContextoEmpresaDoJob::empresaDo($job))->toBeNull();
});
