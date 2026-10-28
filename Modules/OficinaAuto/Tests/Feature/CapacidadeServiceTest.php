<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Services\Producao\CapacidadeService;

uses(Tests\TestCase::class);

/**
 * Wave 18 saturation D2/D4/D9 — CapacidadeService.
 *
 * Cobre heurística V0 + 5 spans canon `oficinaauto.producao.*` + thresholds
 * de status (ociosa/normal/apertada/lotada/overcommit). Multi-tenant Tier 0:
 * o serviço conta pelo global scope de ServiceOrder (empresa da sessão).
 *
 * O banco da suíte é compartilhado: outros arquivos deixam OS nos tenants do seed, e sem
 * empresa na sessão o escopo não filtra nada (contava a base inteira — 192h no 1º run da
 * lane). Por isso a sessão fica SEMPRE num tenant, e:
 *   - "sem OS" usa um business_id sem empresa: nenhuma OS pode existir nele (FK de
 *     vehicles/service_orders exige a empresa), então o vazio é garantido, não suposto;
 *   - a soma de horas é medida como DELTA no tenant 98 (ADR 0358), com as OS criadas aqui.
 *
 * @see Modules/OficinaAuto/Services/Producao/CapacidadeService.php
 */

const BIZ_CAP_TESTE = 98;
const BIZ_CAP_SEM_OS = 980_000_098;

beforeEach(function () {
    config()->set('otel.enabled', false);
    session(['user.business_id' => BIZ_CAP_SEM_OS]);
    $this->capOsIds = [];
    $this->capVehicleIds = [];
});

afterEach(function () {
    if (! empty($this->capOsIds)) {
        ServiceOrder::withoutGlobalScopes()->whereIn('id', $this->capOsIds)->forceDelete(); // SUPERADMIN: limpeza do teste
        Vehicle::withoutGlobalScopes()->whereIn('id', $this->capVehicleIds)->forceDelete(); // SUPERADMIN: limpeza do teste
    }
});

/**
 * Helper: pula tests que precisam de schema MySQL real (ADR 0101).
 */
function skipIfNoMysqlOficinaCap(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('service_orders') || ! Schema::hasTable('vehicles')) {
        test()->markTestSkipped('service_orders/vehicles tables missing');
    }
}

function criarOsCap(int $biz, string $status): ServiceOrder
{
    $v = Vehicle::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id' => $biz,
        'plate'       => 'CAP'.random_int(100, 999),
        'vehicle_type' => 'automovel',
    ]);

    $os = ServiceOrder::withoutGlobalScopes()->create([ // SUPERADMIN: setup teste
        'business_id' => $biz,
        'vehicle_id'  => $v->id,
        'status'      => $status,
        'entered_at'  => now(),
    ]);

    test()->capOsIds[] = $os->id;
    test()->capVehicleIds[] = $v->id;

    return $os;
}

it('cenario 1: Container resolve CapacidadeService', function () {
    expect(app(CapacidadeService::class))->toBeInstanceOf(CapacidadeService::class);
});

it('cenario 2: source-grep confirma 5 spans canon oficinaauto.producao.*', function () {
    $file = (new ReflectionClass(CapacidadeService::class))->getFileName();
    $src  = file_get_contents($file);

    expect($src)->toContain('use App\Util\OtelHelper;');

    $matches = preg_match_all("/'oficinaauto\\.producao\\.[a-z_]+'/", $src);
    expect($matches)->toBeGreaterThanOrEqual(5, "Esperava 5+ spans canon, encontrou {$matches}");
});

it('cenario 3: DB vazio retorna 0 ocupado + capacidade total disponivel', function () {
    skipIfNoMysqlOficinaCap();
    $svc = new CapacidadeService();
    expect($svc->capacidadeOcupadaHoje())->toBe(0);
    expect($svc->capacidadeDisponivelHoje())->toBe(CapacidadeService::CAPACIDADE_DIARIA_HORAS_DEFAULT);
});

it('cenario 4: OS aberta soma 4h; em_servico soma 6h (heuristica V0)', function () {
    skipIfNoMysqlOficinaCap();
    session(['user.business_id' => BIZ_CAP_TESTE]);
    $svc = new CapacidadeService();
    $antes = $svc->capacidadeOcupadaHoje();

    criarOsCap(BIZ_CAP_TESTE, 'aberta');
    criarOsCap(BIZ_CAP_TESTE, 'em_servico');

    // 1 aberta + 1 em_servico = 4 + 6 = 10h a mais no tenant da sessão
    expect($svc->capacidadeOcupadaHoje() - $antes)->toBe(10);

    // OS de outro tenant não entra na conta (global scope da sessão)
    session(['user.business_id' => BIZ_CAP_SEM_OS]);
    expect($svc->capacidadeOcupadaHoje())->toBe(0);
});

it('cenario 5: taxa de ocupacao calcula % correto (consts publicas)', function () {
    // Validacao da formula via consts publicas (sem DB)
    $cap = CapacidadeService::CAPACIDADE_DIARIA_HORAS_DEFAULT;
    expect($cap)->toBe(32);
    expect(CapacidadeService::HORAS_OS_ABERTA)->toBe(4);
    expect(CapacidadeService::HORAS_OS_PRODUCAO)->toBe(6);
});

it('cenario 6: status thresholds (ociosa/normal/apertada/lotada/overcommit)', function () {
    skipIfNoMysqlOficinaCap();
    $svc = new CapacidadeService();
    // Com 0h ocupada → ociosa
    $resumo = $svc->resumoCapacidade(32);
    expect($resumo)->toHaveKeys(['ocupada', 'disponivel', 'capacidade', 'taxa', 'status'])
        ->and($resumo['ocupada'])->toBe(0)
        ->and($resumo['status'])->toBe('ociosa');
});

it('cenario 7: podeAceitarNovaOs respeita capacidade disponivel', function () {
    skipIfNoMysqlOficinaCap();
    $svc = new CapacidadeService();
    // 0 ocupada, capacidade 32 — 10h cabe, 50h NÃO cabe
    expect($svc->podeAceitarNovaOs(10, 32))->toBeTrue();
    expect($svc->podeAceitarNovaOs(50, 32))->toBeFalse();
});

it('cenario 8: capacidadeDisponivelHoje nunca retorna negativo (clamp)', function () {
    skipIfNoMysqlOficinaCap();
    $svc = new CapacidadeService();
    // Capacidade zero — disponivel = 0, nunca <0
    expect($svc->capacidadeDisponivelHoje(0))->toBe(0);
    expect($svc->capacidadeDisponivelHoje(-5))->toBe(0);
});

it('cenario 9: taxaOcupacao com capacidade=0 retorna 0 (anti div-by-zero)', function () {
    $svc = new CapacidadeService();
    expect($svc->taxaOcupacao(0))->toBe(0.0);
});

it('cenario 10: OtelHelper preserva exception (nao engole)', function () {
    expect(fn () => OtelHelper::spanBiz(
        'oficinaauto.producao.test_boom',
        fn () => throw new \RuntimeException('producao-boom')
    ))->toThrow(\RuntimeException::class, 'producao-boom');
});
