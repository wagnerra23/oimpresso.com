<?php
// @covers-us US-AUTO-014

declare(strict_types=1);

use App\Contracts\Atendimento\AvisaClienteWhatsapp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Services\LembreteDeRevisao;
use Tests\Support\WithSeededTenant;

/**
 * US-AUTO-014 — lembrete de revisão do veículo por km estimado e por data (UC-OVREV-01 e UC-OVREV-02
 * do Vehicles/Edit.casos.md).
 *
 * Âncora: decisão [W] 2026-10-26 registrada no SPEC — "estimar o km pela média diária entre as duas
 * últimas OS e avisar quando faltarem ~500 km; campo opcional de data da próxima revisão, que avisa 7
 * dias antes; envio só para cliente com opt-in; usar o mecanismo de WhatsApp da frente CRM".
 * O envio é um dublê do contrato AvisaClienteWhatsapp (o Modules/Whatsapp o liga). Tenant 98 × 99.
 * Nunca biz=4 nem biz=164 (piloto da oficina).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['vehicles', 'service_orders', 'oficina_lembretes_revisao', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    if (! Schema::hasColumn('vehicles', 'next_service_date') || ! Schema::hasColumn('contacts', 'whatsapp_consent')) {
        $this->markTestSkipped('vehicles.next_service_date / contacts.whatsapp_consent ausentes.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->hoje = Carbon::parse('2026-10-21 10:00:00');
    $dono = (int) DB::table('business')->where('id', $this->biz)->value('owner_id');
    $this->contato = fn (int $biz, ?bool $consente) => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente Teste', 'mobile' => '48999990000',
        'whatsapp_consent' => $consente, 'contact_status' => 'active', 'created_by' => $dono,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->veiculo = fn (int $biz, int $contato, ?int $km, ?string $data = null) => (int) DB::table('vehicles')->insertGetId([
        'business_id' => $biz, 'plate' => 'REV' . random_int(1000, 9999), 'vehicle_type' => 'caminhao', 'contact_id' => $contato,
        'next_service_km' => $km, 'next_service_date' => $data, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = fn (int $biz, int $veiculo, int $km, string $quando) => DB::table('service_orders')->insert([
        'business_id' => $biz, 'vehicle_id' => $veiculo, 'order_type' => 'mecanica', 'status' => 'entregue',
        'mileage_at_service' => $km, 'entered_at' => $quando, 'created_at' => now(), 'updated_at' => now(),
    ]);
    // dublê do envio: grava o que pediram e aceita (ou recusa)
    $this->enviados = new ArrayObject();
    $this->aceita = true;
    $teste = $this;
    app()->instance(AvisaClienteWhatsapp::class, new class($teste) implements AvisaClienteWhatsapp {
        public function __construct(private $t) {}

        public function avisar(int $businessId, int $contactId, string $texto): array
        {
            $this->t->enviados->append([$businessId, $contactId, $texto]);

            return $this->t->aceita ? ['enviado' => true, 'motivo' => null] : ['enviado' => false, 'motivo' => 'sem_canal'];
        }
    });
});

/** Segundo caminho do km de hoje: km da última OS + km por dia × dias desde ela. */
function revKmHoje(int $km1, string $d1, int $km2, string $d2, string $hoje): int
{
    $porDia = ($km2 - $km1) / Carbon::parse($d1)->diffInDays(Carbon::parse($d2));

    return (int) round($km2 + $porDia * Carbon::parse($d2)->diffInDays(Carbon::parse($hoje)));
}

it('UC-OVREV-01 · estima o km pela média das duas últimas OS e avisa quando faltam até 500 km', function () {
    $c = ($this->contato)($this->biz, true);
    $perto = ($this->veiculo)($this->biz, $c, 105400);
    $longe = ($this->veiculo)($this->biz, $c, 106000);
    foreach ([$perto, $longe] as $v) {
        ($this->os)($this->biz, $v, 100000, '2026-09-01 08:00:00');
        ($this->os)($this->biz, $v, 103000, '2026-10-01 08:00:00');
    }
    $svc = app(LembreteDeRevisao::class);

    // conta à mão: 3.000 km em 30 dias = 100 km/dia; 20 dias depois → 105.000 km
    expect(revKmHoje(100000, '2026-09-01', 103000, '2026-10-01', '2026-10-21'))->toBe(105000);
    expect($svc->kmEstimado($this->biz, $perto, $this->hoje))->toBe(105000);

    $devidos = $svc->pendentes($this->biz, $this->hoje);
    expect(array_column($devidos, 'vehicle_id'))->toBe([$perto])      // 400 km para a meta; o outro está a 1.000
        ->and($devidos[0]['tipo'])->toBe('km')
        ->and($devidos[0]['alvo'])->toBe('105400')
        ->and($devidos[0]['texto'])->toContain('105.400 km');
});

it('UC-OVREV-01 · uma OS só, ou km que não cresce, não estima e não avisa por km', function () {
    $c = ($this->contato)($this->biz, true);
    $umaOs = ($this->veiculo)($this->biz, $c, 100100);
    ($this->os)($this->biz, $umaOs, 100000, '2026-10-01 08:00:00');
    $semAndar = ($this->veiculo)($this->biz, $c, 100100);
    ($this->os)($this->biz, $semAndar, 100000, '2026-09-01 08:00:00');
    ($this->os)($this->biz, $semAndar, 100000, '2026-10-01 08:00:00');

    $svc = app(LembreteDeRevisao::class);
    expect($svc->kmEstimado($this->biz, $umaOs, $this->hoje))->toBeNull()
        ->and($svc->kmEstimado($this->biz, $semAndar, $this->hoje))->toBeNull()
        ->and($svc->pendentes($this->biz, $this->hoje))->toBe([]);
});

it('UC-OVREV-02 · a data da próxima revisão avisa a partir de 7 dias antes', function () {
    $c = ($this->contato)($this->biz, true);
    $em7 = ($this->veiculo)($this->biz, $c, null, '2026-10-28');
    ($this->veiculo)($this->biz, $c, null, '2026-10-29');   // 8 dias: ainda não

    $devidos = app(LembreteDeRevisao::class)->pendentes($this->biz, $this->hoje);
    expect(array_column($devidos, 'vehicle_id'))->toBe([$em7])
        ->and($devidos[0]['tipo'])->toBe('data')
        ->and($devidos[0]['texto'])->toContain('28/10/2026');
});

it('UC-OVREV-02 · só cliente com opt-in explícito recebe; nulo ou recusado não', function () {
    ($this->veiculo)($this->biz, ($this->contato)($this->biz, null), null, '2026-10-22');
    ($this->veiculo)($this->biz, ($this->contato)($this->biz, false), null, '2026-10-22');
    $sim = ($this->veiculo)($this->biz, ($this->contato)($this->biz, true), null, '2026-10-22');

    expect(array_column(app(LembreteDeRevisao::class)->pendentes($this->biz, $this->hoje), 'vehicle_id'))->toBe([$sim]);
});

it('UC-OVREV-02 · avisa uma vez por meta; recusado pelo envio não conta como avisado', function () {
    $c = ($this->contato)($this->biz, true);
    $v = ($this->veiculo)($this->biz, $c, null, '2026-10-22');
    $svc = app(LembreteDeRevisao::class);

    $this->aceita = false;
    expect($svc->enviar($this->biz, $this->hoje))->toBe(['devidos' => 1, 'enviados' => 0, 'recusados' => 1]);
    expect(DB::table('oficina_lembretes_revisao')->where('vehicle_id', $v)->exists())->toBeFalse();

    $this->aceita = true;
    expect($svc->enviar($this->biz, $this->hoje))->toBe(['devidos' => 1, 'enviados' => 1, 'recusados' => 0]);
    expect($this->enviados[1][1])->toBe($c);
    expect($svc->enviar($this->biz, $this->hoje)['devidos'])->toBe(0);

    // nova meta, novo aviso
    DB::table('vehicles')->where('id', $v)->update(['next_service_date' => '2026-10-25']);
    expect($svc->pendentes($this->biz, $this->hoje))->toHaveCount(1);
});

it('UC-OVREV-01 · veículo, contato e OS de outra empresa não entram [T0]', function () {
    // veículo da 98 apontando para contato da 99
    ($this->veiculo)($this->biz, ($this->contato)($this->outra, true), null, '2026-10-22');
    // OS da 99 no veículo da 98 não entra na média
    $c = ($this->contato)($this->biz, true);
    $v = ($this->veiculo)($this->biz, $c, 105400);
    ($this->os)($this->biz, $v, 103000, '2026-10-01 08:00:00');
    ($this->os)($this->outra, $v, 100000, '2026-09-01 08:00:00');

    $svc = app(LembreteDeRevisao::class);
    expect($svc->kmEstimado($this->biz, $v, $this->hoje))->toBeNull()
        ->and($svc->pendentes($this->biz, $this->hoje))->toBe([])
        ->and($svc->enviar($this->biz, $this->hoje)['enviados'])->toBe(0)
        ->and(count($this->enviados))->toBe(0);
});

it('UC-OVREV-02 · sem o Modules/Whatsapp ligado, nada sai e nada é registrado; dry-run só conta', function () {
    ($this->veiculo)($this->biz, ($this->contato)($this->biz, true), null, '2026-10-22');
    app()->forgetInstance(AvisaClienteWhatsapp::class);
    app()->offsetUnset(AvisaClienteWhatsapp::class);

    expect(app(LembreteDeRevisao::class)->enviar($this->biz, $this->hoje))->toBe(['devidos' => 1, 'enviados' => 0, 'recusados' => 0]);
    expect(DB::table('oficina_lembretes_revisao')->where('business_id', $this->biz)->exists())->toBeFalse();

    $this->artisan('oficinaauto:lembretes-revisao', ['--business' => $this->biz, '--dry-run' => true])->assertSuccessful();
    expect(DB::table('oficina_lembretes_revisao')->where('business_id', $this->biz)->exists())->toBeFalse();
});
