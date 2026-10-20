<?php
// @covers-us US-SELL-070

declare(strict_types=1);

use App\Jobs\QuoteFollowUpJob;
use App\Services\Sells\QuoteAccessRecorder;
use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Termômetro de acesso da proposta — US-SELL-070 · UC-FLX-04 (catálogo da tela de fluxos).
 * Casos UC-QUO-05..08 de `resources/js/Pages/Sells/Quotations.casos.md`.
 *
 * Conta à mão (relógio congelado em 2026-10-20 10:00):
 *   abre às 10:00 → first=10:00 · last=10:00 · count=1
 *   abre às 11:30 → first=10:00 · last=11:30 · count=2
 *   HEAD, prefetch, usuário logado da mesma empresa, venda final → count segue 0 (sem linha)
 *   job 48 h: A enviado há 50 h sem abertura → 1 tarefa · B enviado há 50 h e aberto → 0 ·
 *             C enviado há 10 h → 0 · D do business 99 há 50 h → 0 no job do 98 · rodar de novo → segue 1
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function qaccContato(int $bizId): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $bizId, 'type' => 'customer', 'name' => 'QACC Cliente', 'contact_status' => 'active',
        'created_by' => EstoqueFixture::userId($bizId), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Orçamento (draft + quotation, sem etapa FSM = cotação legada tratada como enviada). */
function qaccOrcamento(int $bizId, Carbon $criadoEm, string $status = 'draft', ?string $subStatus = 'quotation'): Transaction
{
    $id = (int) DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'contact_id' => qaccContato($bizId),
        'type' => 'sell',
        'status' => $status,
        'sub_status' => $subStatus,
        'is_quotation' => $subStatus === 'quotation' ? 1 : 0,
        'is_direct_sale' => 0,
        'payment_status' => 'due',
        'invoice_no' => 'QACC-' . uniqid(),
        'invoice_token' => 'qacc' . bin2hex(random_bytes(12)),
        'transaction_date' => $criadoEm,
        'total_before_tax' => 100,
        'final_total' => 100,
        'created_by' => EstoqueFixture::userId($bizId),
        'essentials_duration' => 0,
        'created_at' => $criadoEm,
        'updated_at' => $criadoEm,
    ]);

    return Transaction::withoutGlobalScopes()->findOrFail($id);
}

function qaccAcesso(int $txId): ?object
{
    return DB::table('transaction_quote_accesses')->where('transaction_id', $txId)->first();
}

function qaccTarefas(int $txId): int
{
    return (int) DB::table('crm_followup_invoices')->where('transaction_id', $txId)->count();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — roda na lane MySQL / CT 100.');
    }
    foreach (['transactions', 'contacts', 'transaction_quote_accesses', 'crm_schedules', 'crm_schedule_users', 'crm_followup_invoices', 'sale_stage_history'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — roda na lane MySQL / CT 100.");
        }
    }
    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);
    Carbon::setTestNow(Carbon::parse('2026-10-20 10:00:00'));
});

afterEach(fn () => Carbon::setTestNow());

it('UC-QUO-05 a primeira abertura grava data e 1; a segunda soma e mantém a primeira', function () {
    $orc = qaccOrcamento($this->bizId, now()->subHours(2));
    $rec = new QuoteAccessRecorder();

    expect($rec->record($orc, Request::create('/quote/x', 'GET')))->toBeTrue();
    $a = qaccAcesso($orc->id);
    expect((int) $a->open_count)->toBe(1)
        ->and((int) $a->business_id)->toBe($this->bizId)
        ->and((string) $a->first_opened_at)->toBe('2026-10-20 10:00:00');

    Carbon::setTestNow(Carbon::parse('2026-10-20 11:30:00'));
    $rec->record($orc, Request::create('/quote/x', 'GET'));
    $a = qaccAcesso($orc->id);
    expect((int) $a->open_count)->toBe(2)
        ->and((string) $a->first_opened_at)->toBe('2026-10-20 10:00:00')
        ->and((string) $a->last_opened_at)->toBe('2026-10-20 11:30:00');
});

it('UC-QUO-06 HEAD, prefetch, usuário da própria empresa e venda que não é orçamento não contam', function () {
    $orc = qaccOrcamento($this->bizId, now()->subHours(2));
    $rec = new QuoteAccessRecorder();

    expect($rec->record($orc, Request::create('/quote/x', 'HEAD')))->toBeFalse();
    $prefetch = Request::create('/quote/x', 'GET');
    $prefetch->headers->set('Sec-Purpose', 'prefetch');
    expect($rec->record($orc, $prefetch))->toBeFalse();

    $this->actingAs($this->usuarioComPermissoes([]));
    expect($rec->record($orc, Request::create('/quote/x', 'GET')))->toBeFalse();
    auth()->logout();

    $final = qaccOrcamento($this->bizId, now()->subHours(2), 'final', null);
    expect($rec->record($final, Request::create('/quote/x', 'GET')))->toBeFalse();

    expect(qaccAcesso($orc->id))->toBeNull()->and(qaccAcesso($final->id))->toBeNull();
    // Controle positivo: a mesma cotação conta num GET comum sem login.
    expect($rec->record($orc, Request::create('/quote/x', 'GET')))->toBeTrue();
});

it('UC-QUO-07 o link público abre igual quando gravar o acesso falha', function () {
    $orc = qaccOrcamento($this->bizId, now()->subHours(2));

    $normal = $this->get('/quote/' . $orc->invoice_token);
    $normal->assertOk();
    expect((int) qaccAcesso($orc->id)->open_count)->toBe(1);

    app()->instance(QuoteAccessRecorder::class, new class extends QuoteAccessRecorder {
        public function record(Transaction $transaction, Request $request): bool
        {
            throw new RuntimeException('falha simulada');
        }
    });
    $comFalha = $this->get('/quote/' . $orc->invoice_token);
    $comFalha->assertOk();
    expect($comFalha->getStatusCode())->toBe($normal->getStatusCode())
        ->and((int) qaccAcesso($orc->id)->open_count)->toBe(1);

    $this->call('HEAD', '/quote/' . $orc->invoice_token);
    expect((int) qaccAcesso($orc->id)->open_count)->toBe(1);
});

it('UC-QUO-08 [T0] só o orçamento enviado há mais de 48 h sem abertura ganha uma tarefa, uma vez, só no business do job', function () {
    $a = qaccOrcamento($this->bizId, now()->subHours(50));
    $b = qaccOrcamento($this->bizId, now()->subHours(50));
    (new QuoteAccessRecorder())->record($b, Request::create('/quote/x', 'GET'));
    $c = qaccOrcamento($this->bizId, now()->subHours(10));
    $d = qaccOrcamento($this->outroBizId, now()->subHours(50));

    expect((new QuoteFollowUpJob($this->bizId))->handle())->toBe(1);
    expect(qaccTarefas($a->id))->toBe(1)
        ->and(qaccTarefas($b->id))->toBe(0)
        ->and(qaccTarefas($c->id))->toBe(0)
        ->and(qaccTarefas($d->id))->toBe(0);

    $tarefa = DB::table('crm_schedules')->where('id', qaccAcesso($a->id)->followup_schedule_id)->first();
    expect((int) $tarefa->business_id)->toBe($this->bizId)
        ->and((int) $tarefa->created_by)->toBe((int) $a->created_by)
        ->and((int) DB::table('crm_schedule_users')->where('schedule_id', $tarefa->id)->value('user_id'))->toBe((int) $a->created_by);

    // Rodar de novo não duplica.
    expect((new QuoteFollowUpJob($this->bizId))->handle())->toBe(0);
    expect(qaccTarefas($a->id))->toBe(1);
    expect(qaccAcesso($d->id))->toBeNull();
});
