<?php

declare(strict_types=1);

use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Services\IndicadoresOsService;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Contrato dos indicadores da OS no Painel do Repair (UC-RDSH-06..10).
 *
 * Fonte única: a FSM (ADR 0143) — `sale_stage_history` + `repair_job_sheets.current_stage_id`.
 *
 * ⚠️ ESTRATÉGIA: o "agora" do serviço é fixado em 2031 e toda transição de fixture cai em
 * dez/2030. A janela de 90 dias então só enxerga as transições DESTE teste, e os números
 * podem ser exatos sem medir o tráfego de outras sessões no banco compartilhado. As etapas
 * vêm de um processo próprio do teste, com nomes únicos, então as linhas da resposta são
 * achadas pelo rótulo — nenhuma etapa do processo semeado do tenant se mistura.
 *
 * Tenant 98 (fictício) · vizinho 99. biz=4 é proibido (ADR 0358).
 *
 * @covers-us US-REPA-006
 *
 * @see resources/js/Pages/Repair/Dashboard/Index.casos.md
 */
uses(Tests\TestCase::class, WithSeededTenant::class);

const RIND_MARCA = 'TEST-CONTRATO-RIND-';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o schema UltimatePOS exige MySQL (ADR 0358)');
    }
    foreach (['repair_job_sheets', 'sale_processes', 'sale_process_stages', 'sale_stage_history'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente");
        }
    }
    $this->rindAgora = Carbon::parse('2031-01-15 12:00:00');
    $this->rindT0 = Carbon::parse('2030-12-01 08:00:00');
});

afterEach(function () {
    // O afterEach roda mesmo com o teste pulado: na lane SQLite as tabelas nem existem.
    try {
        $os = DB::table('repair_job_sheets')->where('job_sheet_no', 'like', RIND_MARCA.'%')->pluck('id');
        $proc = DB::table('sale_processes')->where('key', 'like', 'test_rind_%')->pluck('id');
        $etapas = DB::table('sale_process_stages')->whereIn('process_id', $proc)->pluck('id');
        DB::table('sale_stage_history')->whereIn('to_stage_id', $etapas)->delete();
        DB::table('repair_job_sheets')->whereIn('id', $os)->delete();
        DB::table('sale_process_stages')->whereIn('id', $etapas)->delete();
        DB::table('sale_processes')->whereIn('id', $proc)->delete();
    } catch (\Throwable $e) {
        // ambiente sem as tabelas — nada a limpar
    }
    config(['mwart.repair_dashboard_index.enabled' => false, 'mwart.repair_dashboard_index.business_ids' => []]);
});

/**
 * Processo de teste com as chaves que o serviço lê. Nomes únicos por run.
 *
 * @return array<string, array{id:int, label:string}>
 */
function rindProcesso(int $businessId): array
{
    $tag = uniqid();
    $procId = (int) DB::table('sale_processes')->insertGetId([
        'business_id' => $businessId,
        'key' => 'test_rind_'.$tag,
        'name' => 'TEST RIND '.$tag,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $etapas = [];
    foreach ([
        ['em_execucao', 0, false],
        [IndicadoresOsService::ETAPA_CONCLUIDA, 1, false],
        [IndicadoresOsService::ETAPA_ENTREGUE, 2, true],
        [IndicadoresOsService::ETAPA_GARANTIA, 3, true],
    ] as [$key, $ordem, $terminal]) {
        $label = "RIND {$key} {$tag}";
        $etapas[$key] = ['id' => (int) DB::table('sale_process_stages')->insertGetId([
            'process_id' => $procId,
            'key' => $key,
            'name' => $label,
            'sort_order' => $ordem,
            'is_terminal' => $terminal,
            'created_at' => now(),
            'updated_at' => now(),
        ]), 'label' => $label];
    }

    return $etapas;
}

/** OS gravada direto (DB::table) — `current_stage_id` não passa pelo Eloquent guardado. */
function rindOs(int $businessId, string $sufixo, int $etapaAtual, Carbon $abertaEm, ?Carbon $entrega = null, ?int $tecnico = null): ?int
{
    $locId = DB::table('business_locations')->where('business_id', $businessId)->value('id');
    $contactId = DB::table('contacts')->where('business_id', $businessId)->value('id');
    $statusId = DB::table('repair_statuses')->where('business_id', $businessId)->value('id');
    $autorId = DB::table('users')->where('business_id', $businessId)->value('id');
    if (! $locId || ! $contactId || ! $statusId || ! $autorId) {
        return null;
    }

    return (int) DB::table('repair_job_sheets')->insertGetId([
        'business_id' => $businessId,
        'location_id' => $locId,
        'contact_id' => $contactId,
        'job_sheet_no' => RIND_MARCA.$sufixo.'-'.uniqid(),
        'service_type' => 'carry_in',
        'serial_no' => 'SN-'.$sufixo,
        'status_id' => $statusId,
        'current_stage_id' => $etapaAtual,
        'delivery_date' => $entrega,
        'service_staff' => $tecnico,
        'created_by' => $autorId,
        'created_at' => $abertaEm,
        'updated_at' => $abertaEm,
    ]);
}

function rindPassa(int $businessId, int $osId, int $etapaId, Carbon $quando): void
{
    DB::table('sale_stage_history')->insert([
        'business_id' => $businessId,
        'transaction_id' => $osId,
        'action_id' => null,
        'from_stage_id' => null,
        'to_stage_id' => $etapaId,
        'executed_at' => $quando,
    ]);
}

function rindLinha(array $lista, string $label): ?array
{
    return collect($lista)->firstWhere('label', $label);
}

/**
 * Cenário comum: OS A (10h em execução, entregue, garantia), OS B (20h em execução,
 * concluída, não entregue), OS F (entregue, sem garantia). A e B do mesmo técnico.
 */
function rindCenario($test, int $biz): array
{
    $e = rindProcesso($biz);
    $t0 = $test->rindT0;
    $tecnico = (int) DB::table('users')->where('business_id', $biz)->value('id');

    $a = rindOs($biz, 'A', $e[IndicadoresOsService::ETAPA_GARANTIA]['id'], $t0->copy(), null, $tecnico);
    if ($a === null) {
        test()->markTestSkipped('Tenant sem contact/status/location/user — sem como criar OS de fixture.');
    }
    $b = rindOs($biz, 'B', $e[IndicadoresOsService::ETAPA_CONCLUIDA]['id'], $t0->copy(), null, $tecnico);
    $f = rindOs($biz, 'F', $e[IndicadoresOsService::ETAPA_ENTREGUE]['id'], $t0->copy());

    rindPassa($biz, $a, $e['em_execucao']['id'], $t0->copy());
    rindPassa($biz, $a, $e[IndicadoresOsService::ETAPA_CONCLUIDA]['id'], $t0->copy()->addHours(10));
    rindPassa($biz, $a, $e[IndicadoresOsService::ETAPA_ENTREGUE]['id'], $t0->copy()->addHours(12));
    rindPassa($biz, $a, $e[IndicadoresOsService::ETAPA_GARANTIA]['id'], $t0->copy()->addDays(5));

    rindPassa($biz, $b, $e['em_execucao']['id'], $t0->copy());
    rindPassa($biz, $b, $e[IndicadoresOsService::ETAPA_CONCLUIDA]['id'], $t0->copy()->addHours(20));

    rindPassa($biz, $f, $e[IndicadoresOsService::ETAPA_ENTREGUE]['id'], $t0->copy()->addDays(2));

    return ['etapas' => $e, 'tecnico' => $tecnico, 'os' => compact('a', 'b', 'f')];
}

it('UC-RDSH-06 · tempo médio por etapa vem das transições da FSM', function () {
    $biz = (int) $this->seededTenant()->id;
    $c = rindCenario($this, $biz);

    $ind = app(IndicadoresOsService::class)->montar($biz, $this->rindAgora);

    // A ficou 10h em execução, B 20h: média 15h sobre 2 passagens. Uma média por OS, ou
    // um par de linhas trocado entre OS, daria outro número.
    $exec = rindLinha($ind['tempo_por_etapa'], $c['etapas']['em_execucao']['label']);
    expect($exec)->not->toBeNull();
    expect($exec['amostras'])->toBe(2);
    expect($exec['horas_media'])->toBe(15.0);

    // Só A saiu de "concluído" (para entregue, 2h depois). B ainda está lá: etapa sem saída
    // não entra na média.
    $concl = rindLinha($ind['tempo_por_etapa'], $c['etapas'][IndicadoresOsService::ETAPA_CONCLUIDA]['label']);
    expect($concl['amostras'])->toBe(1);
    expect($concl['horas_media'])->toBe(2.0);
});

it('UC-RDSH-07 · OS parada além do prazo conta na etapa onde está, e só se o prazo venceu', function () {
    $biz = (int) $this->seededTenant()->id;
    $e = rindProcesso($biz);

    $vencida = rindOs($biz, 'VENC', $e['em_execucao']['id'], $this->rindT0, $this->rindAgora->copy()->subDay());
    if ($vencida === null) {
        test()->markTestSkipped('Tenant sem contact/status/location/user — sem como criar OS de fixture.');
    }
    rindOs($biz, 'NOPRAZO', $e['em_execucao']['id'], $this->rindT0, $this->rindAgora->copy()->addDay());
    rindOs($biz, 'SEMPRAZO', $e['em_execucao']['id'], $this->rindT0, null);
    // Entregue com prazo vencido: etapa terminal não é "parada".
    rindOs($biz, 'ENTREGUE', $e[IndicadoresOsService::ETAPA_ENTREGUE]['id'], $this->rindT0, $this->rindAgora->copy()->subDays(3));

    $ind = app(IndicadoresOsService::class)->montar($biz, $this->rindAgora);

    expect(rindLinha($ind['paradas_por_etapa'], $e['em_execucao']['label'])['count'] ?? null)->toBe(1);
    expect(rindLinha($ind['paradas_por_etapa'], $e[IndicadoresOsService::ETAPA_ENTREGUE]['label']))->toBeNull();
});

it('UC-RDSH-08 · retorno em garantia divide pelas OS entregues na mesma janela', function () {
    $biz = (int) $this->seededTenant()->id;
    $c = rindCenario($this, $biz);

    // G: garantia acionada na janela, mas entregue ANTES dela. Fica fora do numerador,
    // senão a taxa podia passar de 100%.
    $g = rindOs($biz, 'G', $c['etapas'][IndicadoresOsService::ETAPA_GARANTIA]['id'], $this->rindT0->copy()->subYear());
    rindPassa($biz, $g, $c['etapas'][IndicadoresOsService::ETAPA_ENTREGUE]['id'], $this->rindT0->copy()->subMonths(6));
    rindPassa($biz, $g, $c['etapas'][IndicadoresOsService::ETAPA_GARANTIA]['id'], $this->rindT0->copy()->addDays(3));

    $ind = app(IndicadoresOsService::class)->montar($biz, $this->rindAgora);

    expect($ind['garantia'])->toBe(['entregues' => 2, 'acionadas' => 1, 'taxa' => 50.0]);
});

it('UC-RDSH-09 · produtividade por técnico conta OS concluídas e o tempo desde a abertura', function () {
    $biz = (int) $this->seededTenant()->id;
    $c = rindCenario($this, $biz);

    $ind = app(IndicadoresOsService::class)->montar($biz, $this->rindAgora);

    // A concluiu 10h após abrir, B 20h. F nunca passou por "concluído" e não conta.
    expect($ind['produtividade'])->toHaveCount(1);
    expect($ind['produtividade'][0]['concluidas'])->toBe(2);
    expect($ind['produtividade'][0]['horas_media'])->toBe(15.0);
    expect($ind['produtividade'][0]['label'])->not->toBe('');
});

it('UC-RDSH-10 · indicadores não contam transição nem OS de outro tenant', function () {
    $biz = (int) $this->seededTenant()->id;
    $vizinho = (int) $this->seededSupportClientTenant()->id;
    $c = rindCenario($this, $biz);

    // OS do vizinho "passando" pelas etapas do processo do 98, com o histórico gravado nos
    // dois business_id: nenhuma das duas linhas pode entrar.
    $osVizinha = rindOs($vizinho, 'VIZ', $c['etapas']['em_execucao']['id'], $this->rindT0, $this->rindAgora->copy()->subDay());
    if ($osVizinha === null) {
        test()->markTestSkipped('Tenant 99 sem contact/status/location/user — sem como criar OS vizinha.');
    }
    foreach ([$biz, $vizinho] as $dono) {
        rindPassa($dono, $osVizinha, $c['etapas'][IndicadoresOsService::ETAPA_ENTREGUE]['id'], $this->rindT0->copy()->addDay());
    }

    $ind = app(IndicadoresOsService::class)->montar($biz, $this->rindAgora);

    expect($ind['garantia']['entregues'])->toBe(2);
    expect(rindLinha($ind['paradas_por_etapa'], $c['etapas']['em_execucao']['label']))->toBeNull();
});

it('UC-RDSH-11 · o painel entrega os indicadores como prop adiada', function () {
    $biz = $this->seededTenant();
    $user = User::factory()->create(['business_id' => $biz->id, 'username' => 'rind_'.uniqid()]);
    foreach (['access_all_locations', 'repair.view'] as $name) {
        $user->givePermissionTo(Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']));
    }
    config(['mwart.repair_dashboard_index.enabled' => true, 'mwart.repair_dashboard_index.business_ids' => []]);
    session(['user.business_id' => $biz->id, 'user.id' => $user->id, 'business.id' => $biz->id]);
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $primeira = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])
        ->get('/repair/dashboard');
    if ($primeira->status() === 403) {
        test()->markTestSkipped('Gate de assinatura/módulo barra repair_module neste ambiente.');
    }
    $primeira->assertOk();
    // Adiada: não vem na primeira resposta, e o cliente é avisado de que deve buscar.
    expect($primeira->json('props'))->not->toHaveKey('indicadores');
    expect(collect($primeira->json('deferredProps') ?? [])->flatten()->all())->toContain('indicadores');

    $parcial = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $versao,
            'X-Inertia-Partial-Component' => 'Repair/Dashboard/Index',
            'X-Inertia-Partial-Data' => 'indicadores',
        ])
        ->get('/repair/dashboard');

    $parcial->assertOk();
    expect($parcial->json('props.indicadores'))->toHaveKeys(
        ['janela_dias', 'tempo_por_etapa', 'paradas_por_etapa', 'garantia', 'produtividade']
    );
});
