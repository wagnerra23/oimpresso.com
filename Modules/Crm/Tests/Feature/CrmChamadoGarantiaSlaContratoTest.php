<?php
// @covers-us US-CRM-094

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Crm\Notifications\ScheduleNotification;
use Modules\Crm\Services\ChamadoGarantiaService;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Chamado de garantia com SLA — US-CRM-094 (lacuna P3, Crm/SPEC.md).
 *
 * Contrato vem da US, não do código:
 *   - o cliente abre o chamado ligado à venda OU à OS; sem nenhuma das duas, recusa;
 *   - prazo pela política do tipo; sem política do tipo, pela padrão da empresa; sem nenhuma,
 *     pelo prazo do config;
 *   - fila e responsável: pedido > política > quem abriu;
 *   - venda, OS, cliente, tipo e responsável de outra empresa são recusados (ADR 0093);
 *   - flag desligada não abre nada.
 *
 * Conta à mão (relógio fixo 2026-10-27 09:00): política do tipo 24h → vence 28/10 09:00;
 * padrão 72h → 30/10 09:00; sem política, config 48h → 29/10 09:00.
 *
 * Tenants: 98 (ADR 0358) × 99 (adversário). Nunca biz=4. Lane: verticais-pest (MySQL).
 */
const GAR_AGORA = '2026-10-27 09:00:00';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('crm_schedules requer schema MySQL UltimatePOS.');
    }
    foreach (['crm_schedules', 'crm_sla_politicas', 'repair_job_sheets', 'transactions', 'categories'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
    config(['crm.garantia_sla.enabled' => true, 'crm.garantia_sla.prazo_padrao_horas' => 48]);
    $this->biz = EstoqueFixture::businessId();
    $this->outro = EstoqueFixture::secondBusinessId();
    $this->user = EstoqueFixture::userId($this->biz);
    $this->agora = CarbonImmutable::parse(GAR_AGORA);
});

function garContato(int $biz): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => 'customer', 'name' => 'GAR Cliente', 'mobile' => '0',
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function garVenda(int $biz, int $contato): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'contact_id' => $contato,
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'invoice_no' => 'GAR-'.uniqid(),
        'transaction_date' => now(), 'total_before_tax' => 100, 'final_total' => 100,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function garOs(int $biz, int $contato): int
{
    return (int) DB::table('repair_job_sheets')->insertGetId([
        'business_id' => $biz, 'contact_id' => $contato, 'job_sheet_no' => 'GAR-'.uniqid(),
        'service_type' => 'carry_in', 'serial_no' => 'SN', 'status_id' => 0,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function garTipo(int $biz, string $nome): int
{
    return (int) DB::table('categories')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'category_type' => 'followup_category', 'parent_id' => 0,
        'created_by' => EstoqueFixture::userId($biz), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function garPolitica(int $biz, ?int $tipo, int $horas, ?string $fila = null, ?int $responsavel = null): void
{
    DB::table('crm_sla_politicas')->insert([
        'business_id' => $biz, 'followup_category_id' => $tipo, 'prazo_horas' => $horas, 'fila' => $fila,
        'responsavel_user_id' => $responsavel, 'ativo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function garAbrir(object $t, array $dados)
{
    return app(ChamadoGarantiaService::class)->abrir($t->biz, $t->user, $dados, $t->agora);
}

it('UC-GAR-01 abre chamado ligado à venda com prazo da política do tipo, fila e responsável', function () {
    $c = garContato($this->biz);
    $venda = garVenda($this->biz, $c);
    $tipo = garTipo($this->biz, 'Defeito de fábrica');
    garPolitica($this->biz, $tipo, 24, 'Assistência', $this->user);
    garPolitica($this->biz, null, 72);

    $ch = garAbrir($this, ['contact_id' => $c, 'transaction_id' => $venda, 'followup_category_id' => $tipo, 'relato' => 'Tela parou de ligar']);

    $linha = DB::table('crm_schedules')->find($ch->id);
    expect((int) $linha->business_id)->toBe($this->biz)
        ->and((int) $linha->garantia)->toBe(1)
        ->and($linha->sla_vence_em)->toBe('2026-10-28 09:00:00')
        ->and($linha->end_datetime)->toBe('2026-10-28 09:00:00')
        ->and($linha->fila)->toBe('Assistência')
        ->and($linha->status)->toBe('open');
    expect(DB::table('crm_followup_invoices')->where('follow_up_id', $ch->id)->value('transaction_id'))->toEqual($venda);
    expect(DB::table('crm_schedule_users')->where('schedule_id', $ch->id)->pluck('user_id')->map(fn ($u) => (int) $u)->all())->toBe([$this->user]);
});

it('UC-GAR-02 sem política do tipo usa a padrão da empresa; sem nenhuma usa o config', function () {
    $c = garContato($this->biz);
    $os = garOs($this->biz, $c);

    $semPolitica = garAbrir($this, ['contact_id' => $c, 'repair_job_sheet_id' => $os, 'relato' => 'Voltou com o mesmo ruído']);
    expect(DB::table('crm_schedules')->where('id', $semPolitica->id)->value('sla_vence_em'))->toBe('2026-10-29 09:00:00')
        ->and((int) DB::table('crm_schedules')->where('id', $semPolitica->id)->value('repair_job_sheet_id'))->toBe($os);

    garPolitica($this->biz, null, 72, 'Balcão');
    $padrao = garAbrir($this, ['contact_id' => $c, 'repair_job_sheet_id' => $os, 'relato' => 'Voltou com o mesmo ruído']);
    $linha = DB::table('crm_schedules')->find($padrao->id);
    expect($linha->sla_vence_em)->toBe('2026-10-30 09:00:00')->and($linha->fila)->toBe('Balcão');
});

it('UC-GAR-03 recusa chamado sem venda nem OS e com relato curto', function () {
    $c = garContato($this->biz);
    $antes = DB::table('crm_schedules')->where('business_id', $this->biz)->count();

    expect(fn () => garAbrir($this, ['contact_id' => $c, 'relato' => 'Defeito no produto']))
        ->toThrow(ValidationException::class);
    expect(fn () => garAbrir($this, ['contact_id' => $c, 'transaction_id' => garVenda($this->biz, $c), 'relato' => 'x']))
        ->toThrow(ValidationException::class);
    expect(DB::table('crm_schedules')->where('business_id', $this->biz)->count())->toBe($antes);
});

it('UC-GAR-04 recusa venda, OS, tipo e responsável de outra empresa (Tier 0)', function () {
    if ($this->outro === null) {
        $this->markTestSkipped('Só um business semeado — sem adversário 99.');
    }
    $c = garContato($this->biz);
    $antes = DB::table('crm_schedules')->count();

    $casos = [
        // Venda e OS da outra empresa apontando para o MESMO contact_id: só o filtro de
        // business_id recusa. Com cliente diferente o teste passaria sem o filtro.
        'venda' => ['transaction_id' => garVenda($this->outro, $c)],
        'os' => ['repair_job_sheet_id' => garOs($this->outro, $c)],
        'tipo' => ['transaction_id' => garVenda($this->biz, $c), 'followup_category_id' => garTipo($this->outro, 'Outro')],
        'responsável' => ['transaction_id' => garVenda($this->biz, $c), 'responsavel_user_id' => EstoqueFixture::userId($this->outro)],
    ];
    foreach ($casos as $nome => $extra) {
        try {
            garAbrir($this, ['contact_id' => $c, 'relato' => 'Defeito no produto'] + $extra);
            $this->fail("aceitou {$nome} de outra empresa");
        } catch (ValidationException $e) {
            expect($e->errors())->not->toBeEmpty();
        }
    }
    expect(DB::table('crm_schedules')->count())->toBe($antes);
});

it('UC-GAR-05 flag desligada não abre chamado', function () {
    config(['crm.garantia_sla.enabled' => false]);
    $c = garContato($this->biz);
    $antes = DB::table('crm_schedules')->count();

    expect(fn () => garAbrir($this, ['contact_id' => $c, 'transaction_id' => garVenda($this->biz, $c), 'relato' => 'Defeito no produto']))
        ->toThrow(RuntimeException::class);
    expect(DB::table('crm_schedules')->count())->toBe($antes);
});

/** Chamado de garantia com prazo fixo e responsável, gravado direto (fatia 2 testa só o alerta). */
function garChamado(int $biz, string $venceEm, ?string $status = 'open'): int
{
    $c = garContato($biz);
    $resp = EstoqueFixture::userId($biz);
    $id = (int) DB::table('crm_schedules')->insertGetId([
        'business_id' => $biz, 'contact_id' => $c, 'title' => 'Garantia: GAR teste', 'status' => $status,
        'schedule_type' => 'call', 'start_datetime' => '2026-10-26 09:00:00', 'end_datetime' => $venceEm,
        'allow_notification' => 0, 'created_by' => $resp, 'garantia' => 1, 'sla_vence_em' => $venceEm,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('crm_schedule_users')->insert(['schedule_id' => $id, 'user_id' => $resp, 'created_at' => now(), 'updated_at' => now()]);

    return $id;
}

function garAlertado(int $id): ?string
{
    return DB::table('crm_schedules')->where('id', $id)->value('sla_alertado_em');
}

// Relógio 2026-10-27 09:00, janela de 4h → alerta quem vence até 13:00.
it('UC-GAR-06 avisa o responsável quando o prazo vence na janela ou já venceu, uma vez só', function () {
    Notification::fake();
    config(['crm.garantia_sla.alerta_antes_horas' => 4]);
    $vencendo = garChamado($this->biz, '2026-10-27 12:00:00');
    $vencido = garChamado($this->biz, '2026-10-26 18:00:00');
    $servico = app(ChamadoGarantiaService::class);

    expect($servico->alertarVencendo($this->biz, $this->agora))->toBe(2);
    expect(garAlertado($vencendo))->toBe(GAR_AGORA)->and(garAlertado($vencido))->toBe(GAR_AGORA);
    Notification::assertSentTimes(ScheduleNotification::class, 2);

    expect($servico->alertarVencendo($this->biz, $this->agora->addHour()))->toBe(0);
    Notification::assertSentTimes(ScheduleNotification::class, 2);
});

it('UC-GAR-07 não avisa prazo fora da janela, chamado concluído ou cancelado, nem com a flag desligada', function () {
    Notification::fake();
    config(['crm.garantia_sla.alerta_antes_horas' => 4]);
    $longe = garChamado($this->biz, '2026-10-27 14:00:00');
    $concluido = garChamado($this->biz, '2026-10-27 10:00:00', 'completed');
    $cancelado = garChamado($this->biz, '2026-10-27 10:00:00', 'cancelled');
    $servico = app(ChamadoGarantiaService::class);

    config(['crm.garantia_sla.enabled' => false]);
    $desligadoId = garChamado($this->biz, '2026-10-27 10:00:00');
    expect($servico->alertarVencendo($this->biz, $this->agora))->toBe(0);
    expect(garAlertado($desligadoId))->toBeNull();

    config(['crm.garantia_sla.enabled' => true]);
    $servico->alertarVencendo($this->biz, $this->agora);
    expect(garAlertado($longe))->toBeNull()
        ->and(garAlertado($concluido))->toBeNull()
        ->and(garAlertado($cancelado))->toBeNull()
        ->and(garAlertado($desligadoId))->toBe(GAR_AGORA);
});

it('UC-GAR-08 o alerta de uma empresa não toca chamado de outra (Tier 0)', function () {
    if ($this->outro === null) {
        $this->markTestSkipped('Só um business semeado — sem adversário 99.');
    }
    Notification::fake();
    $doOutro = garChamado($this->outro, '2026-10-27 10:00:00');
    $meu = garChamado($this->biz, '2026-10-27 10:00:00');

    app(ChamadoGarantiaService::class)->alertarVencendo($this->biz, $this->agora);

    expect(garAlertado($meu))->toBe(GAR_AGORA)->and(garAlertado($doOutro))->toBeNull();
});
