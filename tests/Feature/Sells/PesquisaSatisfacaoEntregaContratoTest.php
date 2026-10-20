<?php
// @covers-us US-SELL-071

declare(strict_types=1);

use App\Jobs\PesquisaSatisfacaoEntregaJob;
use App\Mail\PesquisaSatisfacaoEntregaMail;
use App\Services\Sells\PesquisaEntregaConfig;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Pesquisa de satisfação depois da entrega — US-SELL-071 · UC-FLX-25 (catálogo da tela de fluxos).
 * Casos UC-VSHOW-09..13 de `resources/js/Pages/Sells/Show.casos.md`.
 *
 * Conta à mão (relógio congelado em 2026-10-20 10:00):
 *   A entregue 18/10 10:00 (D+2) → 1 pesquisa enviada por e-mail
 *   B entregue 19/10 10:00 (D+1) → 0 · C entregue 18/10 mas cancelada depois → 0
 *   rodar de novo → segue 1 pesquisa para A e 0 e-mails novos
 *   D entregue D+2 com cliente que recusou WhatsApp e e-mail → linha nao_enviado / opt_out, 0 e-mail
 *   nota 2 (limite padrão 2) → 1 atendimento ligado à venda; nota 4 → 0; rodar de novo → segue 1
 *   pesquisa desligada (default) → 0 linhas · venda do business 99 → 0 no job do 98
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function pesqEtapas(int $bizId): array
{
    $processId = (int) DB::table('sale_processes')->insertGetId([
        'business_id' => $bizId, 'key' => 'pesq-'.uniqid(), 'name' => 'PESQ teste',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $ids = [];
    foreach (['invoiced', 'delivered', 'cancelled'] as $i => $key) {
        $ids[$key] = (int) DB::table('sale_process_stages')->insertGetId([
            'process_id' => $processId, 'key' => $key, 'name' => $key, 'sort_order' => $i,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $ids;
}

function pesqContato(int $bizId, ?bool $whatsapp = null, ?bool $email = null): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => 'PESQ Cliente', 'contact_status' => 'active',
        'email' => 'pesq.cliente@example.test', 'whatsapp_consent' => $whatsapp, 'email_consent' => $email,
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Venda que entrou em `delivered` em $entregueEm; $etapaAtual é onde está agora. */
function pesqVenda(int $bizId, array $etapas, Carbon $entregueEm, string $etapaAtual = 'delivered', ?int $contatoId = null): int
{
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => $contatoId ?? pesqContato($bizId),
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'is_direct_sale' => 0,
        'invoice_no' => 'PESQ-'.uniqid(),
        'transaction_date' => $entregueEm->copy()->subDays(3),
        'total_before_tax' => 100, 'final_total' => 100,
        'created_by' => EstoqueFixture::userId($bizId),
        'current_stage_id' => $etapas[$etapaAtual],
        'essentials_duration' => 0,
        'created_at' => $entregueEm->copy()->subDays(3), 'updated_at' => $entregueEm,
    ]);
    DB::table('sale_stage_history')->insert([
        'business_id' => $bizId, 'transaction_id' => $id, 'action_id' => null,
        'from_stage_id' => $etapas['invoiced'], 'to_stage_id' => $etapas['delivered'], 'executed_at' => $entregueEm,
    ]);

    return $id;
}

function pesqLinha(int $txId): ?object
{
    return DB::table('transaction_satisfaction_surveys')->where('transaction_id', $txId)->first();
}

function pesqRodar(int $bizId): array
{
    return (new PesquisaSatisfacaoEntregaJob($bizId))->handle(app(PesquisaEntregaConfig::class));
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'contacts', 'sale_processes', 'sale_process_stages', 'sale_stage_history', 'transaction_satisfaction_surveys', 'crm_schedules', 'crm_followup_invoices'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — roda na lane MySQL / CT 100.");
        }
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
    Mail::fake();
    $this->etapas = pesqEtapas($this->bizId);
});

afterEach(fn () => Carbon::setTestNow());

it('UC-VSHOW-09 pesquisa desligada (padrão) não envia nada', function () {
    $a = pesqVenda($this->bizId, $this->etapas, now()->subDays(2));

    expect(app(PesquisaEntregaConfig::class)->de($this->bizId)['ativo'])->toBeFalse();
    expect(pesqRodar($this->bizId))->toBe(['enviadas' => 0, 'nao_enviadas' => 0, 'atendimentos' => 0]);
    expect(pesqLinha($a))->toBeNull();
    Mail::assertNothingSent();
});

it('UC-VSHOW-10 [T0] entregue há 2 dias recebe uma pesquisa; há 1 dia, cancelada ou de outro business não', function () {
    app(PesquisaEntregaConfig::class)->definir($this->bizId, true);
    app(PesquisaEntregaConfig::class)->definir($this->outroBizId, true);
    $a = pesqVenda($this->bizId, $this->etapas, now()->subDays(2));
    $b = pesqVenda($this->bizId, $this->etapas, now()->subDays(1));
    $c = pesqVenda($this->bizId, $this->etapas, now()->subDays(2), 'cancelled');
    $d = pesqVenda($this->outroBizId, pesqEtapas($this->outroBizId), now()->subDays(2));

    expect(pesqRodar($this->bizId)['enviadas'])->toBe(1);
    $linha = pesqLinha($a);
    expect($linha->status)->toBe('enviado')
        ->and($linha->canal)->toBe('email')
        ->and((int) $linha->business_id)->toBe($this->bizId);
    expect(pesqLinha($b))->toBeNull()->and(pesqLinha($c))->toBeNull()->and(pesqLinha($d))->toBeNull();
    Mail::assertSent(PesquisaSatisfacaoEntregaMail::class, 1);
});

it('UC-VSHOW-11 rodar de novo não manda outra pesquisa para a mesma venda', function () {
    app(PesquisaEntregaConfig::class)->definir($this->bizId, true);
    $a = pesqVenda($this->bizId, $this->etapas, now()->subDays(2));

    expect(pesqRodar($this->bizId)['enviadas'])->toBe(1);
    Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00'));
    expect(pesqRodar($this->bizId)['enviadas'])->toBe(0);
    expect(DB::table('transaction_satisfaction_surveys')->where('transaction_id', $a)->count())->toBe(1);
    Mail::assertSent(PesquisaSatisfacaoEntregaMail::class, 1);
});

it('UC-VSHOW-12 cliente que recusou WhatsApp e e-mail não recebe; fica o motivo', function () {
    app(PesquisaEntregaConfig::class)->definir($this->bizId, true);
    $d = pesqVenda($this->bizId, $this->etapas, now()->subDays(2), 'delivered', pesqContato($this->bizId, false, false));

    expect(pesqRodar($this->bizId))->toMatchArray(['enviadas' => 0, 'nao_enviadas' => 1]);
    $linha = pesqLinha($d);
    expect($linha->status)->toBe('nao_enviado')->and($linha->motivo)->toBe('opt_out');
    Mail::assertNothingSent();
});

it('UC-VSHOW-13 nota igual ou abaixo do limite abre um atendimento ligado à venda, uma vez', function () {
    app(PesquisaEntregaConfig::class)->definir($this->bizId, true);
    $baixa = pesqVenda($this->bizId, $this->etapas, now()->subDays(2));
    $boa = pesqVenda($this->bizId, $this->etapas, now()->subDays(2));
    pesqRodar($this->bizId);
    DB::table('transaction_satisfaction_surveys')->where('transaction_id', $baixa)->update(['nota' => 2, 'status' => 'respondido']);
    DB::table('transaction_satisfaction_surveys')->where('transaction_id', $boa)->update(['nota' => 4, 'status' => 'respondido']);

    expect(pesqRodar($this->bizId)['atendimentos'])->toBe(1);
    $tarefaId = (int) pesqLinha($baixa)->atendimento_schedule_id;
    expect($tarefaId)->toBeGreaterThan(0)->and(pesqLinha($boa)->atendimento_schedule_id)->toBeNull();
    $tarefa = DB::table('crm_schedules')->where('id', $tarefaId)->first();
    expect((int) $tarefa->business_id)->toBe($this->bizId)
        ->and((int) DB::table('crm_followup_invoices')->where('follow_up_id', $tarefaId)->value('transaction_id'))->toBe($baixa);

    expect(pesqRodar($this->bizId)['atendimentos'])->toBe(0);
    expect(DB::table('crm_followup_invoices')->where('transaction_id', $baixa)->count())->toBe(1);
});
