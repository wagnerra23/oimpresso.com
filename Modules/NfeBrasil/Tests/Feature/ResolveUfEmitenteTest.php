<?php

declare(strict_types=1);

// @covers-us US-NFE-050 — a config do Tools na manifestação (ManifestacaoService/DistribuicaoDfeService).

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Services\Concerns\ResolveUfEmitente;
use Modules\NfeBrasil\Services\Manifestacao\DistribuicaoDfeService;
use Modules\NfeBrasil\Services\Manifestacao\ManifestacaoService;

uses(Tests\TestCase::class);

/**
 * UF do emitente na config do `Tools` — e o defeito que ela conserta.
 *
 * =====================================================================================
 * O DEFEITO
 * =====================================================================================
 * Os dois `buildConfig` da manifestação liam `business.state`. A coluna **não existe** —
 * medido em 2026-09-04 nos três lugares, com o mesmo número nos três: schema canônico
 * `database/schema/mysql-schema.sql` (133 colunas), staging CT 100 (133) e produção Hostinger
 * (133), nenhuma chamada `state`, e nenhuma das 43 migrations que tocam `business` a cria.
 *
 * Consequência: `SQLSTATE[42S22] Unknown column 'state'` em **toda** manifestação, por
 * **qualquer** caminho — a tela `/fiscal/dfe` e o `ManifestacaoController::bulkConfirmar`.
 * Produção tinha 0 DF-e recebidas, então o defeito nunca chegou a causar dano; ficou armado.
 *
 * =====================================================================================
 * A REGRA
 * =====================================================================================
 * A UF do emitente sempre morou em `business_locations.state`, e o caminho de EMISSÃO já lia
 * dali (`NfeService::resolverUF()`). A manifestação é que nunca usou. O `varchar(100)` aceita
 * nome por extenso, que o Tools rejeitaria — daí a validação de sigla com fallback.
 *
 * =====================================================================================
 * POR QUE ESTE ARQUIVO ALTERA, E NÃO INSERE
 * =====================================================================================
 * `business_locations` tem FK em `invoice_scheme_id`, e `invoice_schemes` está **vazia** no
 * staging (medido: 0 linhas) — a própria location semeada carrega `invoice_scheme_id = 0`, que
 * não existe. Ou seja: o seed foi feito com as FK desligadas, e nenhum `insert` honesto passa.
 * Então o teste **altera o `state` da location existente e restaura no `afterEach`**, sem criar
 * linha nenhuma. Duas tentativas anteriores (insert mínimo e clone do molde) morreram em `1452`.
 *
 * Tenants: `biz=98` tem location, `biz=99` não tem — medido em 2026-09-04. Nunca `biz=4`,
 * que é cliente real (ADR 0358).
 */
const UF_BIZ_COM_LOCATION = 98;

const UF_BIZ_SEM_LOCATION = 99;

function ufBootstrap(): void
{
    if (! Schema::hasTable('business_locations')) {
        test()->markTestSkipped('Tabela business_locations ausente.');
    }

    if (! DB::table('business_locations')->where('business_id', UF_BIZ_COM_LOCATION)->exists()) {
        test()->markTestSkipped('Tenant fictício sem business_location neste ambiente.');
    }

    if (DB::table('business_locations')->where('business_id', UF_BIZ_SEM_LOCATION)->exists()) {
        test()->markTestSkipped('Tenant que deveria estar sem location tem uma — ambiente sujo.');
    }
}

/** Expõe o método protegido do trait — é o objeto sob teste. */
function ufSonda(): object
{
    return new class
    {
        use ResolveUfEmitente;

        public function uf(int $businessId): string
        {
            return $this->resolverUfEmitente($businessId);
        }
    };
}

/** Troca a UF da location existente. O `afterEach` repõe a original. */
function ufDefinir(string $state): void
{
    DB::table('business_locations')
        ->where('business_id', UF_BIZ_COM_LOCATION)
        ->update(['state' => $state]);
}

beforeEach(function () {
    if (! Schema::hasTable('business_locations')) {
        return; // a lane SQLite não tem a tabela; o `ufBootstrap` de cada caso skipa.
    }

    $this->ufOriginal = DB::table('business_locations')
        ->where('business_id', UF_BIZ_COM_LOCATION)
        ->orderBy('id')
        ->value('state');
});

afterEach(function () {
    // Guardado: na lane SQLite as tabelas do NfeBrasil não existem e o teste SKIPA — sem esta
    // guarda o próprio `afterEach` estoura com `no such table`, e o skip vira falha.
    if (Schema::hasTable('business_locations') && isset($this->ufOriginal)) {
        DB::table('business_locations')
            ->where('business_id', UF_BIZ_COM_LOCATION)
            ->update(['state' => $this->ufOriginal]);
    }
});

it('a UF do emitente vem da location do business', function () {
    ufBootstrap();
    ufDefinir('SC');

    expect(ufSonda()->uf(UF_BIZ_COM_LOCATION))->toBe('SC');
});

it('nome de UF por extenso não passa — o Tools exige a sigla', function () {
    ufBootstrap();
    ufDefinir('São Paulo'); // `state` é varchar(100) e aceita isto

    expect(ufSonda()->uf(UF_BIZ_COM_LOCATION))->toBe('SP', 'cai no padrão em vez de mandar lixo pro Tools');
});

it('business sem location cai no padrão em vez de estourar', function () {
    ufBootstrap();

    expect(ufSonda()->uf(UF_BIZ_SEM_LOCATION))->toBe('SP');
});

it('a UF é escopada por business — location alheia não vaza (ADR 0093)', function () {
    ufBootstrap();
    ufDefinir('SC'); // o tenant COM location fica com uma UF que não é o padrão

    // Sem o escopo por `business_id`, o `orderBy('id')->value('state')` devolveria a primeira
    // location de QUALQUER business — a 'SC' acima — e o tenant sem location herdaria ela.
    // Remover o `where('business_id', …)` do trait derruba exatamente este caso.
    expect(ufSonda()->uf(UF_BIZ_COM_LOCATION))->toBe('SC')
        ->and(ufSonda()->uf(UF_BIZ_SEM_LOCATION))->toBe('SP', 'o tenant sem location não pode herdar a UF do vizinho');
});

it('a query de config NÃO pede coluna inexistente em `business` (bite-test do defeito)', function () {
    ufBootstrap();

    // Não asserta o texto da query: executa o caminho real e falha se o SQL for inválido.
    // Repor `'state'` no `select()` de qualquer um dos dois serviços derruba este caso com
    // `SQLSTATE[42S22] Unknown column 'state'`.
    foreach ([ManifestacaoService::class, DistribuicaoDfeService::class] as $classe) {
        $metodo = new ReflectionMethod($classe, 'buildConfig');
        $metodo->setAccessible(true);

        $config = $metodo->invoke(
            (new ReflectionClass($classe))->newInstanceWithoutConstructor(),
            UF_BIZ_COM_LOCATION,
        );

        expect($config)->toBeArray()
            ->and($config['siglaUF'])->toMatch('/^[A-Z]{2}$/', "siglaUF inválida em {$classe}");
    }
});
