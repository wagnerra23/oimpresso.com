<?php
// @covers-us US-AUTO-006

declare(strict_types=1);

use App\Contracts\Comissao\TecnicosDaOs;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Services\MecanicosDaOs;
use Tests\Support\WithSeededTenant;

/**
 * US-AUTO-006 — vários mecânicos por OS com horas digitadas (UC-OSMEC-01..04 do ServiceOrders/Show.casos.md).
 *
 * Âncora: decisão [W] 2026-10-26 — "vários mecânicos por OS com horas digitadas, alimentando a comissão
 * por papel de técnico". Tenant 98 × 99. Nunca biz=4 nem biz=164 (piloto da oficina).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['service_orders', 'users', 'oficina_os_mecanicos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->mecanico = fn (int $biz, string $nome) => (int) DB::table('users')->insertGetId([
        'first_name' => $nome, 'username' => 'mec_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $biz, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = function (int $biz, ?int $transactionId = null): int {
        $veiculo = (int) DB::table('vehicles')->insertGetId([
            'business_id' => $biz, 'plate' => 'MEC' . random_int(1000, 9999), 'vehicle_type' => 'auto',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return (int) DB::table('service_orders')->insertGetId([
            'business_id' => $biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => 'em_execucao',
            'transaction_id' => $transactionId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    };
    $this->svc = app(MecanicosDaOs::class);
});

it('UC-OSMEC-01 · grava vários mecânicos com as horas de cada um, e regravar troca a lista', function () {
    [$ana, $beto, $caio] = [($this->mecanico)($this->biz, 'Ana'), ($this->mecanico)($this->biz, 'Beto'), ($this->mecanico)($this->biz, 'Caio')];
    $os = ($this->os)($this->biz);

    $lista = $this->svc->definir($this->biz, $os, [['user_id' => $ana, 'horas' => '2.5'], ['user_id' => $beto, 'horas' => 1]]);
    expect(array_column($lista, 'horas', 'user_id'))->toBe([$ana => 2.5, $beto => 1.0]);

    $this->svc->definir($this->biz, $os, [['user_id' => $caio, 'horas' => 3]]);
    expect(DB::table('oficina_os_mecanicos')->where('service_order_id', $os)->pluck('user_id')->all())->toBe([$caio])
        ->and($this->svc->definir($this->biz, $os, []))->toBe([]);
});

it('UC-OSMEC-02 · horas zero, negativas, acima do limite ou mecânico repetido são recusados e nada muda', function () {
    $ana = ($this->mecanico)($this->biz, 'Ana');
    $os = ($this->os)($this->biz);
    $this->svc->definir($this->biz, $os, [['user_id' => $ana, 'horas' => 4]]);

    foreach ([[['user_id' => $ana, 'horas' => 0]], [['user_id' => $ana, 'horas' => -1]], [['user_id' => $ana, 'horas' => 1000]],
        [['user_id' => $ana, 'horas' => 1], ['user_id' => $ana, 'horas' => 2]]] as $ruim) {
        expect(fn () => $this->svc->definir($this->biz, $os, $ruim))->toThrow(InvalidArgumentException::class);
    }
    expect((float) DB::table('oficina_os_mecanicos')->where('service_order_id', $os)->value('horas'))->toBe(4.0);
});

it('UC-OSMEC-03 · mecânico ou OS de outra empresa são recusados `[T0]`', function () {
    $alheio = ($this->mecanico)($this->outra, 'Alheio');
    $osAlheia = ($this->os)($this->outra);
    $ana = ($this->mecanico)($this->biz, 'Ana');

    expect(fn () => $this->svc->definir($this->biz, ($this->os)($this->biz), [['user_id' => $alheio, 'horas' => 1]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => $this->svc->definir($this->biz, $osAlheia, [['user_id' => $ana, 'horas' => 1]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(DB::table('oficina_os_mecanicos')->where('service_order_id', $osAlheia)->count())->toBe(0);
});

it('UC-OSMEC-04 · a comissão lê as horas pela venda da OS, só na empresa dela, e o recurso nasce desligado', function () {
    [$ana, $beto] = [($this->mecanico)($this->biz, 'Ana'), ($this->mecanico)($this->biz, 'Beto')];
    $os = ($this->os)($this->biz, 987654321);
    $this->svc->definir($this->biz, $os, [['user_id' => $ana, 'horas' => 3], ['user_id' => $beto, 'horas' => 1.5]]);

    $contrato = app(TecnicosDaOs::class);
    expect($contrato)->toBeInstanceOf(MecanicosDaOs::class)
        ->and($contrato->daVenda($this->biz, 987654321))->toBe([['user_id' => $ana, 'horas' => 3.0], ['user_id' => $beto, 'horas' => 1.5]])
        ->and($contrato->daVenda($this->outra, 987654321))->toBe([])
        ->and($contrato->daVenda($this->biz, 123))->toBe([])
        ->and($this->svc->ligado())->toBeFalse();
});
