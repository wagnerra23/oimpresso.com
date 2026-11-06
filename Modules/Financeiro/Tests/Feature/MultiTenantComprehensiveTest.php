<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Financeiro\Repositories\BaixaRepository;
use Modules\Financeiro\Repositories\TituloRepository;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Wave 18 RETRY — saturação D1 multi-tenant cross-tenant (Tier 0 ADR 0093).
 *
 * Cada método dos Repositories chamado com a empresa 99 devolve vazio, e com a
 * empresa 98 devolve o dado — os dois lados no MESMO banco.
 *
 * 2026-11-05 (lote 2.3 da auditoria): até aqui o arquivo só rodava em SQLite
 * :memory: com tabelas recriadas e VAZIAS, e nenhuma lane SQLite o incluía —
 * os 17 casos pulavam em toda lane. E mesmo rodando, "zero para a empresa 99"
 * numa tabela vazia não prova nada. Agora roda no MySQL da lane do Financeiro:
 *   - semeia títulos e uma baixa na empresa fictícia 98 (ADR 0358), dentro de
 *     transação (DatabaseTransactions — some no fim);
 *   - tira o business_id da sessão, para o escopo global NÃO filtrar: o único
 *     filtro que sobra é o where(business_id) explícito do Repository, que é o
 *     que este teste diz provar (defesa em profundidade);
 *   - controle positivo: o mesmo método com 98 tem que achar o dado, senão o
 *     "zero" da 99 não distingue isolamento de fixture que não casou.
 *
 * Os casos D4/D8/D9 não tocam banco e só pulavam pelo guard antigo.
 *
 * @see Modules\Financeiro\Repositories\TituloRepository
 * @see Modules\Financeiro\Repositories\BaixaRepository
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    config()->set('otel.enabled', false);
    config()->set('activitylog.enabled', false);
});

/** Semeia na empresa 98 um dado que casa com cada caso do dataset. */
function mtcSemeia98(): void
{
    if (DB::connection()->getDriverName() !== 'mysql') {
        test()->markTestSkipped('D1 precisa do schema MySQL da lane do Financeiro (fin_*).');
    }
    $biz = 98;
    if (! DB::table('business')->where('id', $biz)->exists()) {
        test()->markTestSkipped('Tenant fictício 98 ausente — seed .github/actions/pest-mysql-setup.');
    }
    $uid = (int) (DB::table('users')->where('business_id', $biz)->value('id') ?? 1);
    $hoje = now();
    $titulo = fn (array $over) => DB::table('fin_titulos')->insertGetId($over + [
        'business_id' => $biz, 'numero' => 'MTC-'.bin2hex(random_bytes(4)), 'status' => 'aberto',
        'cliente_descricao' => 'MTC teste', 'valor_total' => 100, 'valor_aberto' => 100, 'moeda' => 'BRL',
        'emissao' => $hoje->toDateString(), 'competencia_mes' => $hoje->format('Y-m'), 'origem' => 'manual',
        'created_by' => $uid, 'created_at' => $hoje, 'updated_at' => $hoje,
    ]);

    // receber em dia, de venda 9999 parcela 1 (acharPorOrigem, aging em_dia, totaisAbertos)
    $t1 = $titulo(['tipo' => 'receber', 'vencimento' => $hoje->copy()->addDays(10)->toDateString(),
        'origem' => 'venda', 'origem_id' => 9999, 'parcela_numero' => 1]);
    // receber vencido há 45 dias (vencidosAntigos 30)
    $titulo(['tipo' => 'receber', 'vencimento' => $hoje->copy()->subDays(45)->toDateString()]);
    // pagar em dia (totaisAbertos pagar, aging pagar)
    $titulo(['tipo' => 'pagar', 'vencimento' => $hoje->copy()->addDays(5)->toDateString()]);

    // baixa de ontem no título a receber (listarPaginado, historicoRecente, totaisPorTipoPeriodo, idempotency)
    DB::table('fin_titulo_baixas')->insert([
        'business_id' => $biz, 'titulo_id' => $t1, 'valor_baixa' => 10,
        'data_baixa' => $hoje->copy()->subDay()->toDateString(), 'meio_pagamento' => 'pix',
        'idempotency_key' => 'tp_fake-99999', 'created_by' => $uid, 'created_at' => $hoje,
    ]);

    // Sem business_id na sessão o BusinessScopeImpl não filtra: sobra só o where explícito.
    session()->forget(['user.business_id', 'business.id']);
}

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
    'BaixaRepository::totaisPorTipoPeriodo' => [BaixaRepository::class, 'totaisPorTipoPeriodo', ['receber', date('Y-m-d', strtotime('-7 days')), date('Y-m-d')], 'array-totals'],
    'BaixaRepository::acharPorIdempotencyKey' => [BaixaRepository::class, 'acharPorIdempotencyKey', ['tp_fake-99999'], 'null'],
]);

it('D1 — cross-tenant biz=99 retorna zero em todos métodos Repository', function (
    string $repoClass,
    string $metodo,
    array $args,
    string $tipoEsperado,
) {
    mtcSemeia98();
    $repo = app($repoClass);

    $conta = fn ($r) => match ($tipoEsperado) {
        'paginator'    => $r->total(),
        'collection'   => $r->count(),
        'array-totals' => $r['count'],
        'null'         => $r === null ? 0 : 1,
        'array-aging'  => $r['em_dia']['count'],
    };

    // Controle positivo: com a dona do dado (98) o método acha.
    expect($conta($repo->{$metodo}(98, ...$args)))
        ->toBeGreaterThan(0, "{$repoClass}::{$metodo}(98,...) deveria achar o dado semeado — sem isso o zero da 99 não prova isolamento.");

    // Isolamento: com a outra empresa (99), nada.
    expect($conta($repo->{$metodo}(99, ...$args)))
        ->toBe(0, "{$repoClass}::{$metodo}(99,...) vazou dado da empresa 98.");
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
