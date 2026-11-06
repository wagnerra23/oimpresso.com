<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Http\Controllers\CockpitController;
use Modules\Fiscal\Listeners\InvalidaCockpitCacheListener;
use Illuminate\Support\Facades\Event;
use Modules\NfeBrasil\Events\NFCeAutorizada;
use Modules\NfeBrasil\Events\NFeAutorizada;
use Modules\NfeBrasil\Models\NfeCertificado;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeInutilizacao;

uses(Tests\TestCase::class);

/**
 * GAP-FISCAL-002 — Cache Redis 60s KPIs Cockpit (audit sênior 2026-05-25).
 *
 * Tests focados em (1) cache hit não re-executa queries DB, (2) cache key bate
 * entre CockpitController e InvalidaCockpitCacheListener, (3) listener invalida
 * a key correta quando NFeAutorizada/NFCeAutorizada dispara.
 *
 * Multi-tenant: cache key inclui businessId — biz=98 cache NÃO vê biz=99 cache.
 */

beforeEach(function () {
    // GAP-FISCAL-002 tests dividem-se em DB-touching (skipados em SQLite) e
    // pure-cache/config (rodam sempre). Cada test que toca DB declara
    // markTestSkipped() local quando necessário.
    Cache::flush();
});

it('cache key segue padrão fiscal:cockpit:kpis:biz:{id}', function () {
    $controller = new CockpitController;
    expect($controller->kpisCacheKey(98))->toBe('fiscal:cockpit:kpis:biz:98')
        ->and($controller->kpisCacheKey(99))->toBe('fiscal:cockpit:kpis:biz:99')
        ->and($controller->kpisCacheKey(999))->toBe('fiscal:cockpit:kpis:biz:999');
});

it('UC-FCKP-06 · cache key prefix bate com InvalidaCockpitCacheListener (consistency contract)', function () {
    $controller = new CockpitController;
    $listenerPrefix = InvalidaCockpitCacheListener::KEY_PREFIX;

    expect($controller->kpisCacheKey(98))->toStartWith($listenerPrefix);
});

it('TTL é 60s — alinha com janela 1min do audit sênior GAP-FISCAL-002', function () {
    expect(CockpitController::KPIS_CACHE_TTL_SECONDS)->toBe(60);
});

it('Cache::remember não re-executa callback quando key existe', function () {
    $callCount = 0;
    $key = 'fiscal:cockpit:kpis:biz:98';

    // Primeira chamada — executa callback
    $r1 = Cache::remember($key, 60, function () use (&$callCount) {
        $callCount++;
        return ['emitidas' => 42];
    });

    // Segunda chamada — pega do cache, NÃO executa
    $r2 = Cache::remember($key, 60, function () use (&$callCount) {
        $callCount++;
        return ['emitidas' => 999];
    });

    expect($callCount)->toBe(1, 'callback deve rodar apenas 1× em 2 chamadas dentro do TTL')
        ->and($r1)->toEqual($r2)
        ->and($r1['emitidas'])->toBe(42);

    Cache::forget($key);
});

it('UC-FCKP-06 · cache keys de businesses diferentes são INDEPENDENTES (multi-tenant ADR 0093)', function () {
    $controller = new CockpitController;

    Cache::put($controller->kpisCacheKey(98), ['emitidas' => 98], 60);
    Cache::put($controller->kpisCacheKey(99), ['emitidas' => 99], 60);

    expect(Cache::get($controller->kpisCacheKey(98))['emitidas'])->toBe(98)
        ->and(Cache::get($controller->kpisCacheKey(99))['emitidas'])->toBe(99);

    // Invalida só biz=98 — biz=99 sobrevive
    Cache::forget($controller->kpisCacheKey(98));
    expect(Cache::get($controller->kpisCacheKey(98)))->toBeNull()
        ->and(Cache::get($controller->kpisCacheKey(99)))->not->toBeNull();
});

it('UC-FCKP-06 · Listener invalida a key correta dado um event com business_id', function () {
    // Até 2026-11-05 este caso fazia o próprio Cache::forget e conferia o resultado — passava
    // com o listener vazio. Agora chama o listener de verdade com o evento de autorização.
    $controller = new CockpitController;
    Cache::put($controller->kpisCacheKey(98), ['emitidas' => 98], 60);
    Cache::put($controller->kpisCacheKey(99), ['emitidas' => 99], 60);

    (new InvalidaCockpitCacheListener)->handle(new NFeAutorizada(new NfeEmissao(['business_id' => 98])));

    expect(Cache::get($controller->kpisCacheKey(98)))->toBeNull('cache da empresa da nota deve cair');
    expect(Cache::get($controller->kpisCacheKey(99)))->not->toBeNull('cache da outra empresa não pode cair');
});

it('UC-FCKP-15 · o provider registra o listener nos eventos de autorização e de gravação', function () {
    Event::fake();

    Event::assertListening(NFeAutorizada::class, InvalidaCockpitCacheListener::class);
    Event::assertListening(NFCeAutorizada::class, InvalidaCockpitCacheListener::class);

    foreach ([NfeEmissao::class, NfeInutilizacao::class, NfeDfeRecebido::class, NfeCertificado::class] as $model) {
        foreach (['saved', 'deleted'] as $evento) {
            Event::assertListening(
                "eloquent.{$evento}: {$model}",
                [InvalidaCockpitCacheListener::class, 'aoGravarModelo'],
            );
        }
    }
});

it('UC-FCKP-15 · cancelar e inutilizar uma nota derrubam o cache da empresa, e só dela', function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_inutilizacoes')) {
        $this->markTestSkipped('precisa do schema MySQL de nfe_emissoes e nfe_inutilizacoes');
    }

    $controller = new CockpitController;
    $chave98 = $controller->kpisCacheKey(98);
    $chave99 = $controller->kpisCacheKey(99);
    $encher = function () use ($chave98, $chave99) {
        Cache::put($chave98, ['emitidas' => 98], 60);
        Cache::put($chave99, ['emitidas' => 99], 60);
    };

    // Nota autorizada do tenant fictício 98, gravada sem evento (é o estado de partida).
    $nota = DB::table('nfe_emissoes')->insertGetId([
        'business_id' => 98, 'modelo' => '55', 'serie' => '958', 'numero' => 9_580_001,
        'status' => 'autorizada', 'valor_total' => 10, 'emitido_em' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $inutilizacao = null;

    try {
        // Cancelamento — o mesmo update que NfeService::cancelar faz.
        $encher();
        NfeEmissao::withoutGlobalScopes()->findOrFail($nota)->update(['status' => 'cancelada']);
        expect(Cache::get($chave98))->toBeNull('cancelamento não derrubou o cache da empresa');
        expect(Cache::get($chave99))->not->toBeNull('cancelamento derrubou o cache de outra empresa');

        // Inutilização de nota avulsa — o update de NfeService (status + transaction_id null).
        $encher();
        NfeEmissao::withoutGlobalScopes()->findOrFail($nota)->update(['status' => 'inutilizada', 'transaction_id' => null]);
        expect(Cache::get($chave98))->toBeNull('inutilização avulsa não derrubou o cache da empresa');
        expect(Cache::get($chave99))->not->toBeNull('inutilização avulsa derrubou o cache de outra empresa');

        // Inutilização por faixa — NfeInutilizacaoService cria o registro e marca as notas
        // com UPDATE em massa (sem evento de model). O evento que conta é o do registro.
        $encher();
        $inutilizacao = NfeInutilizacao::withoutGlobalScopes()->create([
            'business_id' => 98, 'modelo' => '55', 'serie' => '958',
            'numero_de' => 9_580_001, 'numero_ate' => 9_580_001,
            'justificativa' => 'Teste automatizado de invalidação do cache do cockpit',
            'status' => 'autorizado', 'cstat' => '102', 'autorizada_em' => now(), 'payload_json' => [],
        ]);
        expect(Cache::get($chave98))->toBeNull('inutilização por faixa não derrubou o cache da empresa');
        expect(Cache::get($chave99))->not->toBeNull('inutilização por faixa derrubou o cache de outra empresa');
    } finally {
        DB::table('nfe_emissoes')->where('id', $nota)->delete();
        if ($inutilizacao !== null) {
            DB::table('nfe_inutilizacoes')->where('id', $inutilizacao->id)->delete();
        }
    }
});
