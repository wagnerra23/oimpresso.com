<?php
// @covers-us US-OFICINA-014

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\OficinaAuto\Entities\ServiceOrder;
use Modules\OficinaAuto\Entities\Vehicle;
use Modules\OficinaAuto\Jobs\EnviarLinkAprovacaoWhatsappJob;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Tests\Support\WithSeededTenant;

/**
 * Lote 2.2 da auditoria OficinaAuto (2026-11-05) — envio do link de aprovação.
 *
 * Âncoras: CDC art. 40 (orçamento prévio aprovado pelo cliente) · auditoria eixo 5 A6
 * ("incrementAttempts é get+put, então requisições paralelas passam do limite") · eixo 4 M1
 * ("enviarAprovacao responde 'Orçamento enviado' mesmo quando o Job vai pular") · ADR 0093
 * (Job sem sessão filtra business_id). Tenant 98 contra 99 (ADR 0358).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS.');
    }
    foreach (['service_orders', 'vehicles', 'contacts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    if (! Schema::hasColumn('service_orders', 'approval_pin_attempts')) {
        $this->markTestSkipped('Migration do contador de PIN não rodou.');
    }
    try {
        $this->biz = (int) $this->seededTenant()->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    Cache::flush();

    $this->os = function (int $biz, ?int $contactId = null, string $status = 'orcamento'): ServiceOrder {
        // SUPERADMIN: teste monta a OS direto, fora de sessão HTTP.
        $v = Vehicle::withoutGlobalScopes()->create([
            'business_id' => $biz, 'plate' => 'LTA' . random_int(1000, 9999), 'vehicle_type' => 'automovel',
        ]);

        return ServiceOrder::withoutGlobalScopes()->create([
            'business_id' => $biz, 'vehicle_id' => $v->id, 'contact_id' => $contactId,
            'status' => $status, 'order_type' => 'manutencao', 'entered_at' => now(),
        ]);
    };
    $this->contato = fn (int $biz): int => (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'Cliente lote 2.2',
        'mobile' => '5548999990000',
        'created_by' => (int) DB::table('business')->where('id', $biz)->value('owner_id'),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->tentativas = fn (ServiceOrder $os): int => (int) DB::table('service_orders')
        ->where('id', $os->id)->value('approval_pin_attempts');
});

it('o contador de tentativas vive no banco: limpar o cache não destrava o PIN', function () {
    $svc = app(AprovacaoOsService::class);
    $os = ($this->os)($this->biz);
    $g = $svc->gerarTokenAprovacao($os);
    $errado = $g['pin'] === '0000' ? '1111' : '0000';

    for ($i = 0; $i < 7; $i++) {
        expect($svc->validarPin($os, $errado))->toBeFalse();
    }
    // O UPDATE condicional não deixa passar de 5, por mais chamadas que cheguem.
    expect(($this->tentativas)($os))->toBe(5);

    // Antes o contador era chave de cache e sumia aqui. O PIN segue no cache; o bloqueio não.
    $pinHash = Cache::get("oficina:aprovacao_pin:{$os->id}:{$os->business_id}");
    Cache::flush();
    Cache::put("oficina:aprovacao_pin:{$os->id}:{$os->business_id}", $pinHash, now()->addDay());

    expect($svc->tentativasRestantes($os))->toBe(0)
        ->and($svc->validarPin($os, $g['pin']))->toBeFalse();
});

it('janela de 30 min vencida recomeça a contagem', function () {
    $svc = app(AprovacaoOsService::class);
    $os = ($this->os)($this->biz);
    $g = $svc->gerarTokenAprovacao($os);
    DB::table('service_orders')->where('id', $os->id)->update([
        'approval_pin_attempts' => 5,
        'approval_pin_window_start_at' => now()->subMinutes(31)->toDateTimeString(),
    ]);

    expect($svc->tentativasRestantes($os))->toBe(5)
        ->and($svc->validarPin($os, $g['pin']))->toBeTrue()
        ->and(($this->tentativas)($os))->toBe(0);
});

it('a reserva de tentativa não toca a OS de mesmo id em outra empresa', function () {
    $svc = app(AprovacaoOsService::class);
    $os = ($this->os)($this->biz);
    $svc->gerarTokenAprovacao($os);

    // Mesmo id, business_id forjado: o PIN não existe e nada é contado.
    $forjada = (clone $os)->forceFill(['business_id' => $this->outra]);
    expect($svc->validarPin($forjada, '0000'))->toBeFalse()
        ->and(($this->tentativas)($os))->toBe(0);
});

it('o Job não usa cliente de outra empresa gravado na OS', function () {
    $contatoDaOutra = ($this->contato)($this->outra);
    $os = ($this->os)($this->biz, $contatoDaOutra);

    $diag = EnviarLinkAprovacaoWhatsappJob::diagnosticar($this->biz, $os);

    expect($diag['motivo'])->toBe('cliente não encontrado nesta empresa')
        ->and($diag['contato'])->toBeNull();
});

it('enviar aprovação sem cliente na OS não diz "enviado" ao operador', function () {
    Queue::fake();
    $user = $this->usuarioComPermissoes(['oficinaauto.service_order.update']);
    session(['user.business_id' => $this->biz]);
    $os = ($this->os)($this->biz, null, 'aberta');

    $this->actingAs($user)
        ->post("/oficina-auto/ordens-servico/{$os->id}/enviar-aprovacao")
        ->assertRedirect()
        ->assertSessionHas('status.success', 0);

    expect(session('status.msg'))->toContain('NÃO foi enviado')
        ->and(session('status.msg'))->toContain('a OS não tem cliente')
        // O estado do gate segue: a OS está em orçamento, só o aviso é honesto.
        ->and(ServiceOrder::withoutGlobalScopes()->find($os->id)->status)->toBe('orcamento');
});
