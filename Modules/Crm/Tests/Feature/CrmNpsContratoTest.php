<?php
// @covers-us US-CRM-097

declare(strict_types=1);

use App\Contracts\Atendimento\AvisaClienteWhatsapp;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Services\Nps\PesquisaNps;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * NPS pós-venda e pós-OS — US-CRM-097 (critérios 1-6 do SPEC Crm).
 *
 * Conta à mão (relógio em 2026-10-27 10:00):
 *   venda entregue ontem → 1 pesquisa enviada pelo contrato, com link /nps/{token} de 40 chars
 *   OS em entregue_completo ontem → 1 pesquisa origem=os
 *   2ª venda do MESMO cliente → 0 (1 pesquisa a cada 90 dias)
 *   venda entregue há 10 dias → 0 (janela de 7) · venda do business 99 → 0 no job do 98
 *   notas 10, 9, 8, 3 → 2 promotores, 1 neutro, 1 detrator → NPS = (2 − 1) / 4 × 100 = 25
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: verticais-pest.yml (MySQL).
 */
function npsEtapas(int $bizId): array
{
    $processId = (int) DB::table('sale_processes')->insertGetId([
        'business_id' => $bizId, 'key' => 'nps-'.uniqid(), 'name' => 'NPS teste', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];
    foreach (['invoiced', 'delivered', 'entregue_completo'] as $i => $key) {
        $ids[$key] = (int) DB::table('sale_process_stages')->insertGetId([
            'process_id' => $processId, 'key' => $key, 'name' => $key, 'sort_order' => $i, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ids;
}

function npsContato(int $bizId): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => 'NPS Cliente', 'contact_status' => 'active',
        'mobile' => '48999990000', 'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function npsVenda(int $bizId, array $etapas, Carbon $quando, ?int $contato = null, string $etapa = 'delivered'): int
{
    return (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => $contato ?? npsContato($bizId), 'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid',
        'is_direct_sale' => 0, 'invoice_no' => 'NPS-'.uniqid(), 'transaction_date' => $quando->copy()->subDays(3),
        'total_before_tax' => 100, 'final_total' => 100, 'created_by' => EstoqueFixture::userId($bizId),
        'current_stage_id' => $etapas[$etapa], 'essentials_duration' => 0,
        'created_at' => $quando->copy()->subDays(3), 'updated_at' => $quando,
    ]);
}

function npsOs(int $bizId, array $etapas, Carbon $quando): int
{
    return (int) DB::table('repair_job_sheets')->insertGetId([
        'business_id' => $bizId, 'location_id' => EstoqueFixture::locationId($bizId), 'contact_id' => npsContato($bizId),
        'job_sheet_no' => 'NPS-OS-'.uniqid(), 'service_type' => 'carry_in', 'serial_no' => 'SN-NPS', 'status_id' => 1,
        'current_stage_id' => $etapas['entregue_completo'], 'created_by' => EstoqueFixture::userId($bizId),
        'created_at' => $quando->copy()->subDays(5), 'updated_at' => $quando,
    ]);
}

/** Canal falso no lugar do Modules/Whatsapp: guarda o que seria enviado. */
function npsCanal(bool $aceita = true): object
{
    $canal = new class($aceita) implements AvisaClienteWhatsapp
    {
        public array $enviados = [];

        public function __construct(private bool $aceita) {}

        public function avisar(int $businessId, int $contactId, string $texto): array
        {
            $this->enviados[] = compact('businessId', 'contactId', 'texto');

            return $this->aceita ? ['enviado' => true, 'motivo' => null] : ['enviado' => false, 'motivo' => 'sem_celular'];
        }
    };
    app()->instance(AvisaClienteWhatsapp::class, $canal);

    return $canal;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'contacts', 'sale_processes', 'sale_process_stages', 'repair_job_sheets', 'crm_nps_pesquisas'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — roda na lane MySQL / CT 100.");
        }
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);
    Carbon::setTestNow(Carbon::parse('2026-10-27 10:00:00'));
    $this->etapas = npsEtapas($this->bizId);
    $this->nps = app(PesquisaNps::class);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-NPS-01 [T0] venda entregue na janela recebe a pesquisa pelo WhatsApp; antiga e de outra empresa não', function () {
    $canal = npsCanal();
    $a = npsVenda($this->bizId, $this->etapas, now()->subDay());
    $velha = npsVenda($this->bizId, $this->etapas, now()->subDays(10));
    npsVenda($this->outroBizId, npsEtapas($this->outroBizId), now()->subDay());

    expect($this->nps->enviarPendentes($this->bizId))->toBe(['enviadas' => 1, 'nao_enviadas' => 0]);

    $linha = DB::table('crm_nps_pesquisas')->where('origem_tipo', 'venda')->where('origem_id', $a)->first();
    expect((int) $linha->business_id)->toBe($this->bizId)
        ->and($linha->status)->toBe('enviado')
        ->and(strlen($linha->token))->toBe(40);
    expect($canal->enviados)->toHaveCount(1);
    expect($canal->enviados[0]['businessId'])->toBe($this->bizId);
    expect($canal->enviados[0]['texto'])->toContain('De 0 a 10')->toContain('/nps/'.$linha->token);
    expect(DB::table('crm_nps_pesquisas')->where('origem_id', $velha)->where('origem_tipo', 'venda')->exists())->toBeFalse();
    expect(DB::table('crm_nps_pesquisas')->where('business_id', $this->outroBizId)->count())->toBe(0);
});

it('UC-NPS-02 OS entregue também recebe a pesquisa', function () {
    npsCanal();
    $os = npsOs($this->bizId, $this->etapas, now()->subDay());

    $this->nps->enviarPendentes($this->bizId);

    expect(DB::table('crm_nps_pesquisas')->where('business_id', $this->bizId)->where('origem_tipo', 'os')->where('origem_id', $os)->value('status'))
        ->toBe('enviado');
});

it('UC-NPS-03 mesmo cliente recebe uma pesquisa só a cada 90 dias, e rodar de novo não repete', function () {
    $canal = npsCanal();
    $cliente = npsContato($this->bizId);
    npsVenda($this->bizId, $this->etapas, now()->subDays(2), $cliente);
    npsVenda($this->bizId, $this->etapas, now()->subDay(), $cliente);

    $this->nps->enviarPendentes($this->bizId);
    $this->nps->enviarPendentes($this->bizId);

    expect(DB::table('crm_nps_pesquisas')->where('business_id', $this->bizId)->where('contact_id', $cliente)->count())->toBe(1);
    expect($canal->enviados)->toHaveCount(1);
});

it('UC-NPS-04 sem canal ligado ou recusado pelo canal fica não enviado com o motivo', function () {
    app()->forgetInstance(AvisaClienteWhatsapp::class);
    app()->offsetUnset(AvisaClienteWhatsapp::class);
    $a = npsVenda($this->bizId, $this->etapas, now()->subDay());
    expect($this->nps->enviarPendentes($this->bizId))->toBe(['enviadas' => 0, 'nao_enviadas' => 1]);
    expect(DB::table('crm_nps_pesquisas')->where('origem_id', $a)->where('origem_tipo', 'venda')->value('motivo'))->toBe('sem_canal');

    npsCanal(aceita: false);
    $b = npsVenda($this->bizId, $this->etapas, now()->subDay());
    $this->nps->enviarPendentes($this->bizId);
    expect(DB::table('crm_nps_pesquisas')->where('origem_id', $b)->where('origem_tipo', 'venda')->value('motivo'))->toBe('sem_celular');
});

it('UC-NPS-05 a primeira resposta vale; nota fora de 0-10 e token desconhecido são recusados', function () {
    npsCanal();
    $a = npsVenda($this->bizId, $this->etapas, now()->subDay());
    $this->nps->enviarPendentes($this->bizId);
    $token = (string) DB::table('crm_nps_pesquisas')->where('origem_id', $a)->where('origem_tipo', 'venda')->value('token');

    expect($this->nps->responder($token, 11, null))->toBe('nao_encontrada');
    expect($this->nps->responder(str_repeat('z', 40), 9, null))->toBe('nao_encontrada');
    expect($this->nps->responder($token, 9, '  Atendimento ótimo  '))->toBe('ok');
    expect($this->nps->responder($token, 0, 'mudei de ideia'))->toBe('ja_respondida');

    $linha = DB::table('crm_nps_pesquisas')->where('token', $token)->first();
    expect((int) $linha->nota)->toBe(9)
        ->and($linha->comentario)->toBe('Atendimento ótimo')
        ->and($linha->status)->toBe('respondido');
});

it('UC-NPS-06 [T0] resumo calcula o NPS só com respostas da empresa', function () {
    npsCanal();
    foreach ([10, 9, 8, 3] as $nota) {
        $id = npsVenda($this->bizId, $this->etapas, now()->subDay());
        $this->nps->enviarPendentes($this->bizId);
        $token = (string) DB::table('crm_nps_pesquisas')->where('origem_id', $id)->where('origem_tipo', 'venda')->value('token');
        $this->nps->responder($token, $nota, null);
    }
    $outra = npsVenda($this->outroBizId, npsEtapas($this->outroBizId), now()->subDay());
    $this->nps->enviarPendentes($this->outroBizId);
    $this->nps->responder((string) DB::table('crm_nps_pesquisas')->where('business_id', $this->outroBizId)->where('origem_id', $outra)->value('token'), 0, null);

    expect($this->nps->resumo($this->bizId, now()->subDays(30)))->toBe([
        'respostas' => 4, 'promotores' => 2, 'neutros' => 1, 'detratores' => 1, 'nps' => 25, 'enviadas' => 4,
    ]);
});

it('UC-NPS-07 comando com a flag desligada não envia nada', function () {
    $canal = npsCanal();
    npsVenda($this->bizId, $this->etapas, now()->subDay());
    config(['crm.nps_enabled' => false]);

    $this->artisan('crm:nps-enviar', ['--business' => $this->bizId])->assertSuccessful();

    expect($canal->enviados)->toBe([])
        ->and(DB::table('crm_nps_pesquisas')->where('business_id', $this->bizId)->count())->toBe(0);
});
