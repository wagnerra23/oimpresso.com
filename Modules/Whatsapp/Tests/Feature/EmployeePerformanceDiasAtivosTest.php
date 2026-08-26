<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Services\EmployeePerformance\EmployeePerformanceRebuilder;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regressão de `EmployeePerformanceRebuilder::refreshCoverage()` — `dias_ativos_30d`.
 *
 * O QUE DEFENDE: de 2026-05-15 (nascimento do Service) a 2026-08-26 o cálculo era
 * `->select(DB::raw('DATE(m.created_at) as d'))->distinct()->count('d')`. O
 * `Builder::aggregate()` faz `cloneWithout(['columns'])` e descarta o `->select()`,
 * montando `select count(distinct 'd')` — alias que não existe naquele SELECT. O
 * MySQL respondia `SQLSTATE[42S22] 1054 Unknown column 'd' in 'SELECT'`, o
 * `catch (Throwable)` do método engolia, e `dias_ativos_30d` NUNCA era escrito:
 * ficava congelado no valor anterior da linha (e `null` — logo 0, pelo default da
 * coluna — em todo scorecard novo). Corrupção silenciosa de métrica derivada,
 * não crash — 164 ocorrências de
 * `[employee_performance.coverage_failed]` no laravel.log de prod na janela
 * 2026-06-21..2026-08-26, biz=1, nas 4 identidades.
 *
 * POR QUE NENHUM TESTE PEGOU: duas camadas independentes de invisibilidade —
 *   (a) o `catch (Throwable)` transforma a falha em `Log::warning`, então mesmo um
 *       teste que chamasse `rebuild()` passaria verde sem assertar o campo;
 *   (b) o `EmployeePerformanceRebuilderTest` (o único que exercita o Service) tem
 *       `markTestSkipped('era-sqlite: ...')` fora do SQLite — verde por NÃO-EXECUÇÃO
 *       em todo ambiente MySQL.
 * Por isso este arquivo nasce MySQL-real e entra na allowlist do `whatsapp-pest.yml`
 * no MESMO PR: teste que não roda na lane não defende nada.
 *
 * BITE-TEST: com o `->count('d')` de volta, o caso 1 vai a vermelho (0 !== 3).
 *
 * TENANT: 98 e 97 fictícios (ADR 0358 — biz=4 PROIBIDO). `employee_performance` tem
 * FK pra `business`, então o tenant precisa existir de fato; `App\Business` não tem
 * `HasFactory`, e o idioma que funciona é `find`-ou-`forceCreate` com as colunas
 * NOT NULL sem default do schema real (mesmo do ClienteVeiculosModuleGateTest).
 * `DatabaseTransactions` (nunca `RefreshDatabase`) — a lane roda sobre o schema
 * baseline semeado e um `migrate:fresh` derruba o seed.
 *
 * @see Modules/Whatsapp/Services/EmployeePerformance/EmployeePerformanceRebuilder.php
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('MySQL-only: o defeito é a compilação do agregado no grammar MySQL.');
    }
    foreach (['business', 'conversations', 'messages', 'employee_performance'] as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Schema real ausente (tabela {$t}) — esta suíte roda em MySQL semeado.");
        }
    }
});

function bizFicticio(int $id): Business
{
    return Business::find($id) ?: Business::forceCreate([
        'id' => $id,
        'name' => "Tenant ficticio {$id} (ADR 0358)",
        'currency_id' => 1,
        'start_date' => now()->toDateString(),
        'default_profit_percent' => 0,
        'owner_id' => 1,
        'stop_selling_before' => 0,
        'weighing_scale_setting' => '',
        'certificado' => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

function convDiasAtivos(int $bizId, string $ext): int
{
    return DB::table('conversations')->insertGetId([
        'business_id' => $bizId,
        'channel_id' => 9001,
        'customer_external_id' => $ext,
        'status' => 'open',
        'created_at' => now()->subDays(40),
        'updated_at' => now(),
    ]);
}

function msgDiasAtivos(int $bizId, int $convId, string $quando, array $over = []): void
{
    DB::table('messages')->insert(array_merge([
        'business_id' => $bizId,
        'conversation_id' => $convId,
        'direction' => 'outbound',
        'provider' => 'whatsapp_baileys',
        'provider_message_id' => null,
        'type' => 'text',
        'body' => 'Zelda: mensagem de fixture',
        'status' => 'sent',
        'is_internal_note' => 0,
        'created_at' => $quando,
        'updated_at' => $quando,
    ], $over));
}

it('conta DISTINCT DATE(created_at) dos ultimos 30d e grava em dias_ativos_30d', function () {
    $biz = bizFicticio(98);
    $conv = convDiasAtivos($biz->id, '5548900000001');

    // 3 dias distintos DENTRO da janela — com 2 mensagens no mesmo dia, pra provar
    // que o DISTINCT é por DIA e não por mensagem.
    msgDiasAtivos($biz->id, $conv, now()->subDays(1)->setTime(9, 0)->toDateTimeString());
    msgDiasAtivos($biz->id, $conv, now()->subDays(1)->setTime(17, 30)->toDateTimeString());
    msgDiasAtivos($biz->id, $conv, now()->subDays(5)->setTime(11, 0)->toDateTimeString());
    msgDiasAtivos($biz->id, $conv, now()->subDays(10)->setTime(14, 0)->toDateTimeString());

    // Ruído que NÃO pode entrar na conta:
    //   fora da janela de 30d · inbound · nota interna · outro atendente.
    msgDiasAtivos($biz->id, $conv, now()->subDays(60)->setTime(9, 0)->toDateTimeString());
    msgDiasAtivos($biz->id, $conv, now()->subDays(2)->setTime(9, 0)->toDateTimeString(), ['direction' => 'inbound']);
    msgDiasAtivos($biz->id, $conv, now()->subDays(3)->setTime(9, 0)->toDateTimeString(), ['is_internal_note' => 1]);
    msgDiasAtivos($biz->id, $conv, now()->subDays(4)->setTime(9, 0)->toDateTimeString(), ['body' => 'Outro: nao e a Zelda']);

    $perf = app(EmployeePerformanceRebuilder::class)->rebuild($biz->id, null, 'Zelda');

    // ANTES do fix: `null` (o atributo nunca chegava a ser escrito, porque a exception
    // 1054 estourava antes da atribuição e o catch a engolia). Medido no CT 100 em
    // 2026-08-26 com o `->count('d')` de volta: "Failed asserting that null is
    // identical to 3". Num scorecard que JÁ existe, o efeito é pior e mais silencioso:
    // o valor anterior fica congelado na linha.
    expect($perf->dias_ativos_30d)->toBe(3);
});

it('nao vaza dias ativos de outro tenant (Tier 0 — ADR 0093)', function () {
    $alvo = bizFicticio(98);
    $vizinho = bizFicticio(97);

    $convVizinho = convDiasAtivos($vizinho->id, '5548900000002');
    foreach ([1, 2, 3, 4, 5] as $d) {
        msgDiasAtivos($vizinho->id, $convVizinho, now()->subDays($d)->setTime(10, 0)->toDateTimeString());
    }

    $convAlvo = convDiasAtivos($alvo->id, '5548900000003');
    msgDiasAtivos($alvo->id, $convAlvo, now()->subDays(1)->setTime(10, 0)->toDateTimeString());

    $perf = app(EmployeePerformanceRebuilder::class)->rebuild($alvo->id, null, 'Zelda');

    // 5 dias existem no vizinho; o alvo só pode enxergar o 1 que é dele.
    expect($perf->dias_ativos_30d)->toBe(1);
});
