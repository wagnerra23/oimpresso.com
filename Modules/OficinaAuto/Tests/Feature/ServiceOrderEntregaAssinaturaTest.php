<?php
// @covers-us US-OFICINA-048

declare(strict_types=1);

use App\Http\Controllers\ServiceOrderFsmActionController;
use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\OficinaAuto\Database\Seeders\OficinaAutoFsmSeeder;
use Modules\OficinaAuto\Entities\OsAssinatura;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Http\Controllers\ServiceOrderAssinaturaController;
use Modules\OficinaAuto\Services\AssinaturaEntrega;
use Modules\OficinaAuto\Services\StageGateEvaluator;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

/**
 * US-OFICINA-048 · UC-FLX-22 — a entrega da OS exige a assinatura do cliente quando a empresa liga.
 *
 * Âncora: UC-FLX-22 (*"só conclui com foto do serviço e assinatura do cliente"*), modelo "Oficina
 * mecânica" do catálogo. A foto já é exigida (US-OFICINA-047, ServiceOrderEntregaFotoTest); aqui a
 * OS já tem a foto do serviço, então o único requisito em jogo é a assinatura.
 *
 * A regra é por empresa (`business.common_settings[oficina_entrega_exige_assinatura]`), **desligada
 * por padrão** — desligada, o checklist da entrega é o de antes (só a foto).
 *
 * Tenant 98 contra 99 (nunca biz=4; a Oficina tem piloto ao vivo no 164 e ele não é tocado).
 * ⚠️ SKIP em SQLite / sem schema: leia assertions, não "0 failed" (LC-13).
 */

/** PNG 1×1 válido — o contrato aceita data URL `data:image/png;base64,…`. */
const ASSIN_PNG_1X1 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS.');
    }
    foreach (['service_orders', 'vehicles', 'arquivos', 'oficina_os_assinaturas', 'sale_processes', 'sale_process_stages', 'sale_stage_history'] as $t) {
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
    Storage::fake(config('arquivos.disk_default', 'local'));
    (new OficinaAutoFsmSeeder())->runForBusiness($this->biz);
    session(['user.business_id' => $this->biz]);

    $this->etapa = fn (string $key) => (int) DB::table('sale_process_stages as s')
        ->join('sale_processes as p', 'p.id', '=', 's.process_id')
        ->where('p.business_id', $this->biz)->where('p.key', 'oficina_mecanica_os')->where('s.key', $key)
        ->value('s.id');

    $veiculo = DB::table('vehicles')->insertGetId([
        'business_id' => $this->biz, 'plate' => 'ASS' . random_int(1000, 9999), 'vehicle_type' => 'caminhao',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = DB::table('service_orders')->insertGetId([
        'business_id' => $this->biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => 'aberta',
        'current_stage_id' => ($this->etapa)('pronto_retirada'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    // Execução começou há 1 h e a foto do serviço chegou há 10 min: a foto já está cumprida.
    DB::table('sale_stage_history')->insert([
        'business_id' => $this->biz, 'transaction_id' => $this->os, 'action_id' => null, 'from_stage_id' => null,
        'to_stage_id' => ($this->etapa)('em_execucao'), 'user_id' => null,
        'payload_snapshot' => json_encode(['teste' => 'entrega-assinatura']), 'executed_at' => now()->subHour(),
    ]);
    assinAnexo($this->biz, ServiceOrder::class, $this->os, now()->subMinutes(10));

    // O teste liga/desliga o ajuste do 98: guarda o JSON para devolver como estava.
    $this->settings98 = DB::table('business')->where('id', $this->biz)->value('common_settings');

    Permission::firstOrCreate(['name' => 'oficinaauto.service_order.update', 'guard_name' => 'web']);
    $this->user = User::factory()->create(['business_id' => $this->biz]);
    $this->user->givePermissionTo('oficinaauto.service_order.update');
});

afterEach(function () {
    if (property_exists($this, 'settings98') && isset($this->biz)) {
        DB::table('business')->where('id', $this->biz)->update(['common_settings' => $this->settings98]);
    }
});

function assinAnexo(int $biz, string $tipo, int $dono, \DateTimeInterface $quando): void
{
    DB::table('arquivos')->insert([
        'business_id' => $biz, 'arquivable_type' => $tipo, 'arquivable_id' => $dono,
        'disk' => 'arquivos', 'storage_path' => 'tests/entrega-assin-' . uniqid() . '.png',
        'original_name' => 'foto', 'mime_type' => 'image/png', 'size_bytes' => 68, 'md5' => md5(uniqid('', true)),
        'bucket' => 'active', 'created_at' => $quando, 'updated_at' => $quando,
    ]);
}

function assinOs(object $t): ServiceOrder
{
    return ServiceOrder::withoutGlobalScopes()->findOrFail($t->os); // teste: OS do 98 carregada explícita
}

function assinGate(object $t): array
{
    return app(StageGateEvaluator::class)->evaluate(assinOs($t), 'oficina_mecanica_os', 'entregar');
}

/** Executa `entregar` pela web (mesmo método da rota `fsm/execute`). */
function assinEntregar(object $t)
{
    $t->actingAs($t->user);

    return app()->call([app(ServiceOrderFsmActionController::class), 'execute'], [
        'request' => Request::create('/x', 'POST', ['action_key' => 'entregar']),
        'order' => assinOs($t),
    ]);
}

/** Grava a assinatura pela web (mesmo método da rota `ordens-servico/{order}/assinatura`). */
function assinSalvar(object $t, array $dados)
{
    $t->actingAs($t->user);

    return app()->call([app(ServiceOrderAssinaturaController::class), 'store'], [
        'request' => Request::create('/x', 'POST', $dados),
        'order' => assinOs($t),
    ]);
}

function assinEtapaAtual(object $t): int
{
    return (int) DB::table('service_orders')->where('id', $t->os)->value('current_stage_id');
}

it('UC-OSH-17 · desligada por padrão: o checklist da entrega é só a foto e a OS é entregue sem assinatura', function () {
    expect(app(AssinaturaEntrega::class)->exigida($this->biz))->toBeFalse();
    expect(collect(assinGate($this)['requirements'])->pluck('key')->all())->toBe(['servico_foto']);

    $r = assinEntregar($this);
    expect($r->getStatusCode())->toBe(200)
        ->and(assinEtapaAtual($this))->toBe(($this->etapa)('entregue'));
});

it('UC-OSH-18 · ligada e sem assinatura: entregar é recusado (422) e a OS fica em Pronto p/ retirar', function () {
    app(AssinaturaEntrega::class)->definir($this->biz, true);

    $gate = assinGate($this);
    $pendentes = collect($gate['requirements'])->where('blocking', true)->where('ok', false)->pluck('key')->all();
    expect($pendentes)->toBe(['assinatura'])->and($gate['satisfied'])->toBeFalse();

    $r = assinEntregar($this);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['gate']['blocking_unmet'])->toBe(1);
    expect(assinEtapaAtual($this))->toBe(($this->etapa)('pronto_retirada'));
});

it('UC-OSH-19 · ligada: com a assinatura salva no quadro, a OS é entregue — e a assinatura não vira foto da OS', function () {
    app(AssinaturaEntrega::class)->definir($this->biz, true);
    $fotosAntes = DB::table('arquivos')->where('arquivable_type', ServiceOrder::class)->where('arquivable_id', $this->os)->count();

    $s = assinSalvar($this, ['assinatura_png' => ASSIN_PNG_1X1, 'recebido_por' => 'Cliente Teste']);
    expect($s->getStatusCode())->toBe(201)
        ->and($s->getData(true)['assinatura']['recebido_por'])->toBe('Cliente Teste');

    $linha = OsAssinatura::withoutGlobalScopes()->where('service_order_id', $this->os)->firstOrFail(); // teste
    expect($linha->business_id)->toBe($this->biz);
    $anexo = DB::table('arquivos')->where('arquivable_type', OsAssinatura::class)->where('arquivable_id', $linha->id)->first();
    expect($anexo)->not->toBeNull()
        ->and((int) $anexo->business_id)->toBe($this->biz)
        ->and($anexo->mime_type)->toBe('image/png');
    // O anexo é da assinatura, não da OS: a galeria do laudo e a regra "foto do serviço" não o veem.
    expect(DB::table('arquivos')->where('arquivable_type', ServiceOrder::class)->where('arquivable_id', $this->os)->count())->toBe($fotosAntes);

    expect(assinGate($this)['satisfied'])->toBeTrue();
    $r = assinEntregar($this);
    expect($r->getStatusCode())->toBe(200)
        ->and(assinEtapaAtual($this))->toBe(($this->etapa)('entregue'));
});

it('UC-OSH-20 · imagem que não é PNG (ou vazia) é recusada e nada é gravado', function () {
    app(AssinaturaEntrega::class)->definir($this->biz, true);

    $r = assinSalvar($this, ['assinatura_png' => 'data:image/jpeg;base64,' . base64_encode('nao-e-png')]);
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData(true)['errors'])->toHaveKey('assinatura_png');

    $vazio = fn () => assinSalvar($this, ['assinatura_png' => '']);
    expect($vazio)->toThrow(ValidationException::class);

    expect(OsAssinatura::withoutGlobalScopes()->where('service_order_id', $this->os)->exists())->toBeFalse(); // teste
    expect(assinGate($this)['satisfied'])->toBeFalse();
});

it('UC-OSH-21 · assinatura de outra empresa apontando para a OS não conta', function () {
    app(AssinaturaEntrega::class)->definir($this->biz, true);

    $alheia = DB::table('oficina_os_assinaturas')->insertGetId([
        'business_id' => $this->outra, 'service_order_id' => $this->os, 'recebido_por' => null,
        'user_id' => null, 'created_at' => now(), 'updated_at' => now(),
    ]);
    assinAnexo($this->outra, OsAssinatura::class, $alheia, now());

    expect(collect(assinGate($this)['requirements'])->firstWhere('key', 'assinatura')['ok'])->toBeFalse();
    expect(assinEntregar($this)->getStatusCode())->toBe(422);
});

it('UC-OSH-22 · o comando liga e desliga a exigência por empresa, sem tocar a outra', function () {
    $outraAntes = DB::table('business')->where('id', $this->outra)->value('common_settings');

    expect(Artisan::call('oficina:entrega-assinatura', ['biz' => $this->biz, '--ligar' => true]))->toBe(0);
    expect(app(AssinaturaEntrega::class)->exigida($this->biz))->toBeTrue()
        ->and(app(AssinaturaEntrega::class)->exigida($this->outra))->toBeFalse();

    expect(Artisan::call('oficina:entrega-assinatura', ['biz' => $this->biz, '--desligar' => true]))->toBe(0);
    expect(app(AssinaturaEntrega::class)->exigida($this->biz))->toBeFalse();
    expect(DB::table('business')->where('id', $this->outra)->value('common_settings'))->toBe($outraAntes);
});
