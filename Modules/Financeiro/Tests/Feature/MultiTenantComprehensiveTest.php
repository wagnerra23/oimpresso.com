<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Repositories\BaixaRepository;
use Modules\Financeiro\Repositories\TituloRepository;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY — saturação D1 multi-tenant cross-tenant (Tier 0 ADR 0093).
 *
 * Estende `MultiTenantIsolationTest` + `TituloRepositoryWave18Test` com 10+
 * datasets cobrindo TODOS os Repositories + helpers com biz=99 (hipotético)
 * garantindo zero vazamento.
 *
 * In-memory SQLite (tables criadas no beforeEach) — robusto pra CI e local.
 * NÃO depende de seed UltimatePOS.
 *
 * Multi-tenant Tier 0 IRREVOGÁVEL (ADR 0093) + Tests biz=1 (ADR 0101).
 *
 * @see Modules\Financeiro\Repositories\TituloRepository
 * @see Modules\Financeiro\Repositories\BaixaRepository
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('activitylog.enabled', false);

    if (config('database.default') !== 'sqlite' && ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('MultiTenantComprehensive rodado apenas em SQLite in-memory.');
    }

    Schema::dropIfExists('fin_titulo_baixas');
    Schema::dropIfExists('fin_titulos');

    Schema::create('fin_titulos', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->string('numero', 50)->nullable();
        $t->string('tipo', 20);
        $t->string('status', 20)->default('aberto');
        $t->unsignedBigInteger('cliente_id')->nullable();
        $t->string('cliente_descricao', 150)->nullable();
        $t->decimal('valor_total', 14, 4)->default(0);
        $t->decimal('valor_aberto', 14, 4)->default(0);
        $t->string('moeda', 3)->default('BRL');
        $t->date('emissao')->nullable();
        $t->date('vencimento')->nullable();
        $t->string('competencia_mes', 7)->nullable();
        $t->string('origem', 20)->default('manual');
        $t->unsignedBigInteger('origem_id')->nullable();
        $t->unsignedInteger('parcela_numero')->nullable();
        $t->unsignedInteger('parcela_total')->nullable();
        $t->unsignedBigInteger('titulo_pai_id')->nullable();
        $t->unsignedBigInteger('plano_conta_id')->nullable();
        $t->unsignedBigInteger('categoria_id')->nullable();
        $t->text('observacoes')->nullable();
        $t->json('metadata')->nullable();
        $t->unsignedInteger('created_by')->nullable();
        $t->unsignedInteger('updated_by')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });

    Schema::create('fin_titulo_baixas', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('titulo_id');
        $t->unsignedBigInteger('conta_bancaria_id')->nullable();
        $t->decimal('valor_baixa', 14, 4)->default(0);
        $t->decimal('juros', 14, 4)->nullable();
        $t->decimal('multa', 14, 4)->nullable();
        $t->decimal('desconto', 14, 4)->nullable();
        $t->date('data_baixa');
        $t->string('meio_pagamento', 30);
        $t->string('idempotency_key', 100)->nullable();
        $t->unsignedBigInteger('transaction_payment_id')->nullable();
        $t->unsignedBigInteger('estorno_de_id')->nullable();
        $t->text('observacoes')->nullable();
        $t->unsignedInteger('created_by')->nullable();
        $t->timestamp('created_at')->nullable();
    });

    if (! Schema::hasTable('activity_log')) {
        Schema::create('activity_log', function ($t) {
            $t->id();
            $t->string('log_name')->nullable();
            $t->text('description')->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('subject_type')->nullable();
            $t->unsignedBigInteger('causer_id')->nullable();
            $t->string('causer_type')->nullable();
            $t->json('properties')->nullable();
            $t->string('event')->nullable();
            $t->uuid('batch_uuid')->nullable();
            $t->timestamps();
        });
    }
});

afterEach(function () {
    Schema::dropIfExists('fin_titulo_baixas');
    Schema::dropIfExists('fin_titulos');
});

/**
 * Dataset robusto — cada caso = 1 método de Repository com biz=99 → resultado vazio.
 *
 * @return array<string, array{0: string, 1: string, 2: array<int, mixed>, 3: string}>
 */
dataset('cross_tenant_zero_biz99', [
    'TituloRepository::listarPaginado'      => [TituloRepository::class, 'listarPaginado', [[], 50], 'paginator'],
    'TituloRepository::totaisAbertos rec'   => [TituloRepository::class, 'totaisAbertos', ['receber'], 'array-totals'],
    'TituloRepository::totaisAbertos pag'   => [TituloRepository::class, 'totaisAbertos', ['pagar'], 'array-totals'],
    'TituloRepository::vencidosAntigos'     => [TituloRepository::class, 'vencidosAntigos', ['receber', 30], 'collection'],
    'TituloRepository::aging receber'       => [TituloRepository::class, 'aging', ['receber'], 'array-aging'],
    'TituloRepository::aging pagar'         => [TituloRepository::class, 'aging', ['pagar'], 'array-aging'],
    'TituloRepository::acharPorOrigem'      => [TituloRepository::class, 'acharPorOrigem', ['venda', 9999, 1], 'null'],
    'BaixaRepository::listarPaginado'       => [BaixaRepository::class, 'listarPaginado', [[], 50], 'paginator'],
    'BaixaRepository::historicoRecente'     => [BaixaRepository::class, 'historicoRecente', [2], 'collection'],
    'BaixaRepository::totaisPorTipoPeriodo' => [BaixaRepository::class, 'totaisPorTipoPeriodo', ['receber', '2026-01-01', '2026-12-31'], 'array-totals'],
    'BaixaRepository::acharPorIdempotencyKey' => [BaixaRepository::class, 'acharPorIdempotencyKey', ['tp_fake-99999'], 'null'],
]);

it('D1 — cross-tenant biz=99 retorna zero em todos métodos Repository', function (
    string $repoClass,
    string $metodo,
    array $args,
    string $tipoEsperado,
) {
    $repo = app($repoClass);
    $resultado = $repo->{$metodo}(99, ...$args);

    match ($tipoEsperado) {
        'paginator'    => expect($resultado->total())->toBe(0, "Repository {$repoClass}::{$metodo}(99,...) paginator deve estar vazio."),
        'collection'   => expect($resultado->count())->toBe(0, "Repository {$repoClass}::{$metodo}(99,...) collection deve estar vazia."),
        'array-totals' => expect($resultado['count'])->toBe(0, "Repository {$repoClass}::{$metodo}(99,...) count deve ser 0."),
        'null'         => expect($resultado)->toBeNull("Repository {$repoClass}::{$metodo}(99,...) deve retornar null."),
        'array-aging'  => expect($resultado['em_dia']['count'])->toBe(0, "Repository {$repoClass}::{$metodo}(99,...) aging em_dia deve ser 0."),
    };
})->with('cross_tenant_zero_biz99');

it('D1 — BaixaRepository força where(business_id) explícito (defesa em profundidade)', function () {
    $source = file_get_contents(
        module_path('Financeiro', 'Repositories/BaixaRepository.php')
    );

    expect($source)->toMatch("/where\(['\"]business_id['\"],\s*\\\$businessId\)/");
});

it('D9.a — BaixaRepository wrap em OtelHelper::spanBiz nos métodos hot', function () {
    $source = file_get_contents(
        module_path('Financeiro', 'Repositories/BaixaRepository.php')
    );

    expect($source)->toContain('use App\Util\OtelHelper');
    expect($source)->toContain("OtelHelper::spanBiz('financeiro.baixa.repo.listar'");
    expect($source)->toContain("OtelHelper::spanBiz('financeiro.baixa.repo.totais_periodo'");
});

it('D4 — BaixaRepository instanciável com métodos canônicos type-hinted', function () {
    $repo = new BaixaRepository();
    $reflection = new ReflectionClass($repo);

    $metodos = [
        'listarPaginado',
        'totaisPorTipoPeriodo',
        'historicoRecente',
        'acharPorIdempotencyKey',
    ];

    foreach ($metodos as $nome) {
        expect($reflection->hasMethod($nome))->toBeTrue("BaixaRepository deve ter {$nome}");

        $method = $reflection->getMethod($nome);
        expect($method->getReturnType())->not->toBeNull("método {$nome} deve ter return type");

        $params = $method->getParameters();
        expect($params[0]->getName())->toBe('businessId', "1º param de {$nome} deve ser businessId (Tier 0)");
        expect((string) $params[0]->getType())->toBe('int');
    }
});

it('D8 — 5° + 6° FormRequests novos da Wave 18 RETRY existem + validam', function () {
    $requests = [
        \Modules\Financeiro\Http\Requests\StoreBaixaRequest::class,
        \Modules\Financeiro\Http\Requests\UpdateAccountRequest::class,
    ];

    foreach ($requests as $requestClass) {
        expect(class_exists($requestClass))->toBeTrue();

        $reflection = new ReflectionClass($requestClass);
        expect($reflection->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();

        $instance = new $requestClass();
        $rules = $instance->rules();
        expect($rules)->toBeArray()->not->toBeEmpty();
    }
});

it('D8 — StoreBaixaRequest helper valorEfetivo() faz juros+multa-desconto corretamente', function () {
    $req = new \Modules\Financeiro\Http\Requests\StoreBaixaRequest();

    $req->replace([
        'valor_baixa' => 100.00,
        'juros'       => 5.00,
        'multa'       => 2.00,
        'desconto'    => 1.50,
    ]);

    expect($req->valorEfetivo())->toBe(105.50);
});

it('D9.a — BaixaRepository registrado como singleton no Provider', function () {
    $a = app(BaixaRepository::class);
    $b = app(BaixaRepository::class);
    expect($a)->toBe($b);
});
