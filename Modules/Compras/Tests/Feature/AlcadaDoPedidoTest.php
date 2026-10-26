<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Compras\Services\AlcadaDoPedido;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * US-COM-032 — aprovação do pedido de compra por alçada de valor (decisão [W] 2026-10-26).
 *
 * Contrato (SPEC Compras US-COM-032): limites por empresa; comprador até 1.000,00; gerente até 10.000,00;
 * acima, diretoria. Escrito à mão:
 *   500,00   → não precisa         1.000,00 → não precisa (limite é "até")
 *   5.000,00 → gerente            10.000,00 → gerente       20.000,00 → diretoria
 * Pendente ou recusado não vira compra recebida (POST /purchases com o pedido = 422).
 * Desligada (padrão), nada muda.
 *
 * Tenant 98 (ADR 0358); adversário 99 ou o biz=2 do seed, nunca 1 nem 4.
 *
 * @covers-us US-COM-032
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! \Schema::hasTable('compras_aprovacoes_pedido')) {
        $this->markTestSkipped('Schema MySQL com as migrations de Compras ausente — rode no CT 100 / lane compras-pest.');
    }
    $this->biz = EstoqueFixture::businessId();
    $this->loc = EstoqueFixture::locationId($this->biz, '-ALC');
    $this->svc = app(AlcadaDoPedido::class);
    $user = fn (array $perms) => tap(App\User::factory()->create([
        'business_id' => $this->biz, 'username' => 'alc_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1,
    ]), function ($u) use ($perms) {
        foreach ($perms as $p) {
            $u->givePermissionTo(Permission::findOrCreate($p, 'web'));
        }
    });
    $this->comprador = $user(['purchase.create', 'purchase_order.create']);
    $this->gerente = $user([AlcadaDoPedido::PERM_GERENTE]);
    $this->diretoria = $user([AlcadaDoPedido::PERM_DIRETORIA]);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

function alcPedido($t, string $total, ?int $biz = null): int
{
    $biz ??= $t->biz;

    return DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $biz === $t->biz ? $t->loc : EstoqueFixture::locationId($biz, '-ALC'),
        'type' => 'purchase_order', 'status' => 'ordered', 'final_total' => $total, 'transaction_date' => now(),
        'created_by' => EstoqueFixture::userId($biz), 'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('desligada, nenhum pedido precisa de aprovação', function () {
    expect($this->svc->nivelExigido($this->biz, '99999.00'))->toBeNull();
    expect($this->svc->registrar($this->biz, alcPedido($this, '99999.00'), $this->comprador->id))->toBeNull();
});

it('o nível sai do valor e dos limites da empresa', function () {
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');

    expect($this->svc->nivelExigido($this->biz, '500.00'))->toBeNull();
    expect($this->svc->nivelExigido($this->biz, '1000.00'))->toBeNull();
    expect($this->svc->nivelExigido($this->biz, '5000.00'))->toBe('gerente');
    expect($this->svc->nivelExigido($this->biz, '10000.00'))->toBe('gerente');
    expect($this->svc->nivelExigido($this->biz, '20000.00'))->toBe('diretoria');
    expect(fn () => $this->svc->definir($this->biz, true, '5000.00', '1000.00'))->toThrow(InvalidArgumentException::class);
});

it('pedido do comprador acima do limite fica pendente e o gerente aprova', function () {
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');
    $p = alcPedido($this, '5000.00');

    expect($this->svc->registrar($this->biz, $p, $this->comprador->id))->toBe('pendente');
    expect($this->svc->bloqueiaRecebimento($this->biz, [$p]))->toBe([$p]);
    expect(fn () => $this->svc->decidir($this->biz, $p, $this->comprador, true))->toThrow(InvalidArgumentException::class, 'Você não tem alçada para este valor.');

    $this->svc->decidir($this->biz, $p, $this->gerente, true);
    expect($this->svc->bloqueiaRecebimento($this->biz, [$p]))->toBe([]);
});

it('acima do limite do gerente só a diretoria aprova; o gerente que pede já fica pendente', function () {
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');
    $p = alcPedido($this, '20000.00');

    expect($this->svc->registrar($this->biz, $p, $this->gerente->id))->toBe('pendente');
    expect(fn () => $this->svc->decidir($this->biz, $p, $this->gerente, true))->toThrow(InvalidArgumentException::class);
    $this->svc->decidir($this->biz, $p, $this->diretoria, true);
    expect(DB::table('compras_aprovacoes_pedido')->where('transaction_id', $p)->value('status'))->toBe('aprovado');

    // Quem pede e já tem a alçada aprova na hora.
    expect($this->svc->registrar($this->biz, alcPedido($this, '5000.00'), $this->gerente->id))->toBe('aprovado');
});

it('recusar exige motivo e o pedido recusado segue bloqueado', function () {
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');
    $p = alcPedido($this, '5000.00');
    $this->svc->registrar($this->biz, $p, $this->comprador->id);

    expect(fn () => $this->svc->decidir($this->biz, $p, $this->gerente, false, ' '))->toThrow(InvalidArgumentException::class, 'Diga o motivo da recusa.');
    $this->svc->decidir($this->biz, $p, $this->gerente, false, 'Preço acima do mercado');
    expect($this->svc->bloqueiaRecebimento($this->biz, [$p]))->toBe([$p]);
});

it('a compra a partir de pedido pendente é recusada com 422', function () {
    Event::fake([App\Events\PurchaseCreatedOrModified::class]);
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');
    $p = alcPedido($this, '5000.00');
    $this->svc->registrar($this->biz, $p, $this->comprador->id);

    $resp = $this->actingAs($this->comprador)->post('/purchases', [
        'status' => 'received', 'purchase_order_ids' => [$p], 'purchases' => [],
    ]);

    $resp->assertStatus(422);
});

it('usuário de outra empresa não decide o pedido', function () {
    $outra = (int) DB::table('business')->whereIn('id', [99, 2])->where('id', '!=', $this->biz)->orderByDesc('id')->value('id');
    if ($outra <= 0) {
        $this->markTestSkipped('Sem empresa adversária fictícia (99 ou 2) neste ambiente.');
    }
    $this->svc->definir($this->biz, true, '1000.00', '10000.00');
    $p = alcPedido($this, '5000.00');
    $this->svc->registrar($this->biz, $p, $this->comprador->id);
    $alheio = App\User::factory()->create(['business_id' => $outra, 'username' => 'alc_x_'.uniqid(), 'user_type' => 'user', 'allow_login' => 1]);
    $alheio->givePermissionTo(Permission::findOrCreate(AlcadaDoPedido::PERM_DIRETORIA, 'web'));

    expect(fn () => $this->svc->decidir($this->biz, $p, $alheio, true))->toThrow(InvalidArgumentException::class, 'Você não tem alçada para este valor.');
});
