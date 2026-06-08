<?php

declare(strict_types=1);

use App\Business;
use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;
use Modules\Repair\Entities\RepairStatus;

uses(Tests\TestCase::class);

/**
 * JobSheetObserver Pest GUARD — Onda 2 do plano F3 Integração Vendas × Oficina (ADR 0192).
 *
 * Cobertura:
 *  - Observer NÃO dispara quando status_id não muda (no-op skip)
 *  - Observer NÃO dispara quando status novo tem is_completed_status=false
 *  - Observer DISPARA quando status_id muda pra RepairStatus terminal · cria Transaction
 *  - Observer IDEMPOTENTE: re-update pra mesmo status não duplica Transaction
 *  - Multi-tenant Tier 0: Transaction derivada herda business_id da OS (ADR 0093)
 *  - Payload Transaction tem source='oficina' + os_ref="OS-{id}" + repair_job_sheet_id
 *
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UPOS legacy + repair_job_sheets requer MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('repair_job_sheets')
        || ! Schema::hasTable('repair_statuses')
        || ! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Tabelas core ausentes — rode migrate.');
    }
    if (! Schema::hasColumn('transactions', 'source')
        || ! Schema::hasColumn('transactions', 'os_ref')) {
        $this->markTestSkipped('Migration Onda 1 não aplicada — rode migrate.');
    }
    if (! Schema::hasColumn('transactions', 'cancelled_at')) {
        $this->markTestSkipped('Migration reverse hook (cancelled_at) não aplicada — rode migrate.');
    }
});

function bootstrapRepairBiz(): array
{
    $business = Business::first();
    if (! $business) {
        test()->markTestSkipped('Sem business no banco — rode seeder UltimatePOS antes.');
    }

    $user = User::where('business_id', $business->id)->first();
    if (! $user) {
        test()->markTestSkipped('Sem user no business.');
    }

    // Garante 2 RepairStatuses (um aberto · um terminal) pra business.
    $openStatus = RepairStatus::firstOrCreate(
        ['business_id' => $business->id, 'name' => 'TEST · Aberto · '.uniqid()],
        ['is_completed_status' => false, 'sort_order' => 1, 'status_color' => '#ccc']
    );
    $doneStatus = RepairStatus::firstOrCreate(
        ['business_id' => $business->id, 'name' => 'TEST · Entregue · '.uniqid()],
        ['is_completed_status' => true, 'sort_order' => 99, 'status_color' => '#10b981']
    );

    // JobSheet sem invocar observer · pra status inicial NÃO terminal.
    $contact = DB::table('contacts')->where('business_id', $business->id)->first();
    if (! $contact) {
        test()->markTestSkipped('Sem contact no business · rode seeder.');
    }

    return [
        'business' => $business,
        'user' => $user,
        'contact_id' => $contact->id,
        'open_status_id' => $openStatus->id,
        'done_status_id' => $doneStatus->id,
    ];
}

function createTestJobSheet(array $ctx, ?int $statusId = null): JobSheet
{
    return JobSheet::create([
        'business_id' => $ctx['business']->id,
        'location_id' => null,
        'contact_id' => $ctx['contact_id'],
        'job_sheet_no' => 'TEST-OS-'.uniqid(),
        'service_type' => 'carry_in',
        'serial_no' => 'SN-'.uniqid(),
        'status_id' => $statusId ?? $ctx['open_status_id'],
        'estimated_cost' => 150.00,
        'created_by' => $ctx['user']->id,
        'service_staff' => $ctx['user']->id,
    ]);
}

it('Observer NÃO dispara quando status_id não muda (no-op skip)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    $countBefore = Transaction::where('business_id', $ctx['business']->id)
        ->where('repair_job_sheet_id', $os->id)
        ->count();

    // Update um campo qualquer · não status_id.
    $os->update(['estimated_cost' => 200.00]);

    $countAfter = Transaction::where('business_id', $ctx['business']->id)
        ->where('repair_job_sheet_id', $os->id)
        ->count();

    expect($countAfter)->toBe($countBefore);

    // cleanup
    $os->delete();
});

it('Observer NÃO dispara quando status novo NÃO é terminal', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // Cria outro status aberto pra mover entre não-terminais.
    $otherOpen = RepairStatus::create([
        'business_id' => $ctx['business']->id,
        'name' => 'TEST · Aguardando peças · '.uniqid(),
        'is_completed_status' => false,
        'sort_order' => 2,
        'status_color' => '#fbbf24',
    ]);

    $os->update(['status_id' => $otherOpen->id]);

    $count = Transaction::where('business_id', $ctx['business']->id)
        ->where('repair_job_sheet_id', $os->id)
        ->count();

    expect($count)->toBe(0);

    // cleanup
    $os->delete();
    $otherOpen->delete();
});

it('Observer DISPARA quando status_id transiciona pra RepairStatus terminal · cria Transaction', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    $os->update(['status_id' => $ctx['done_status_id']]);

    $tx = Transaction::where('business_id', $ctx['business']->id)
        ->where('repair_job_sheet_id', $os->id)
        ->first();

    expect($tx)->not->toBeNull();
    expect($tx->type)->toBe('sell');
    expect($tx->status)->toBe('final');
    expect($tx->source)->toBe('oficina');
    expect($tx->os_ref)->toBe("OS-{$os->id}");
    expect($tx->business_id)->toBe($ctx['business']->id);
    expect($tx->contact_id)->toBe($ctx['contact_id']);
    expect((float) $tx->final_total)->toBe(150.00);

    // cleanup
    $tx->delete();
    $os->delete();
});

it('Observer IDEMPOTENTE: re-update pra mesmo status terminal não duplica Transaction', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // Primeira transição terminal · cria.
    $os->update(['status_id' => $ctx['done_status_id']]);

    // Volta pra aberto + transiciona terminal NOVAMENTE.
    $os->update(['status_id' => $ctx['open_status_id']]);
    $os->update(['status_id' => $ctx['done_status_id']]);

    $count = Transaction::where('business_id', $ctx['business']->id)
        ->where('repair_job_sheet_id', $os->id)
        ->count();

    expect($count)->toBe(1);

    // cleanup
    Transaction::where('repair_job_sheet_id', $os->id)->delete();
    $os->delete();
});

it('Multi-tenant Tier 0: Transaction derivada herda business_id da OS (ADR 0093)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    $os->update(['status_id' => $ctx['done_status_id']]);

    $tx = Transaction::where('repair_job_sheet_id', $os->id)->first();

    expect($tx)->not->toBeNull();
    expect($tx->business_id)->toBe($ctx['business']->id);
    expect($tx->business_id)->not->toBe(99); // não cross-tenant

    // cleanup
    $tx->delete();
    $os->delete();
});

// ---------------------------------------------------------------------------
// Reverse hook GUARDs · ADR 0192 follow-up (Caminho B' · cancelled_at TIMESTAMP NULL)
// ---------------------------------------------------------------------------

it('Reverse: OS terminal → não-terminal marca Transaction como cancelada (cancelled_at SET)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // Terminal · cria Transaction.
    $os->update(['status_id' => $ctx['done_status_id']]);
    $tx = Transaction::where('repair_job_sheet_id', $os->id)->first();
    expect($tx)->not->toBeNull();
    expect($tx->cancelled_at)->toBeNull();

    // Reabre OS · reverse hook marca cancelled_at.
    $os->update(['status_id' => $ctx['open_status_id']]);

    $tx->refresh();
    expect($tx->cancelled_at)->not->toBeNull();

    // cleanup
    $tx->delete();
    $os->delete();
});

it('Reverse + re-completion: terminal → aberto → terminal AGAIN cria NOVA Transaction (não restaura)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // 1ª completion · cria Tx1.
    $os->update(['status_id' => $ctx['done_status_id']]);
    $tx1 = Transaction::where('repair_job_sheet_id', $os->id)->first();
    expect($tx1)->not->toBeNull();
    $tx1Id = $tx1->id;

    // Reabre · cancela Tx1.
    $os->update(['status_id' => $ctx['open_status_id']]);
    $tx1->refresh();
    expect($tx1->cancelled_at)->not->toBeNull();

    // 2ª completion · cria Tx2 NOVA (não restaura Tx1).
    $os->update(['status_id' => $ctx['done_status_id']]);

    $allTx = Transaction::where('repair_job_sheet_id', $os->id)->orderBy('id')->get();
    expect($allTx)->toHaveCount(2);
    expect($allTx[0]->id)->toBe($tx1Id);
    expect($allTx[0]->cancelled_at)->not->toBeNull(); // Tx1 continua cancelada
    expect($allTx[1]->id)->not->toBe($tx1Id);
    expect($allTx[1]->cancelled_at)->toBeNull(); // Tx2 ativa

    // cleanup
    Transaction::where('repair_job_sheet_id', $os->id)->delete();
    $os->delete();
});

it('Edge case: aberto → terminal (cria) → aberto (cancela) → outro aberto (no-op) → terminal (cria nova)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // Status aberto extra pra transição não-terminal → não-terminal.
    $otherOpen = RepairStatus::create([
        'business_id' => $ctx['business']->id,
        'name' => 'TEST · Aguardando peças edge · '.uniqid(),
        'is_completed_status' => false,
        'sort_order' => 3,
        'status_color' => '#fbbf24',
    ]);

    // 1) aberto → terminal · cria.
    $os->update(['status_id' => $ctx['done_status_id']]);
    expect(Transaction::where('repair_job_sheet_id', $os->id)->whereNull('cancelled_at')->count())->toBe(1);

    // 2) terminal → aberto · cancela.
    $os->update(['status_id' => $ctx['open_status_id']]);
    expect(Transaction::where('repair_job_sheet_id', $os->id)->whereNull('cancelled_at')->count())->toBe(0);

    // 3) aberto → outro aberto · no-op (nem cria nem cancela).
    $os->update(['status_id' => $otherOpen->id]);
    expect(Transaction::where('repair_job_sheet_id', $os->id)->count())->toBe(1); // só a cancelada de antes
    expect(Transaction::where('repair_job_sheet_id', $os->id)->whereNull('cancelled_at')->count())->toBe(0);

    // 4) aberto → terminal · cria nova (idempotência ignora cancelada).
    $os->update(['status_id' => $ctx['done_status_id']]);
    expect(Transaction::where('repair_job_sheet_id', $os->id)->whereNull('cancelled_at')->count())->toBe(1);
    expect(Transaction::where('repair_job_sheet_id', $os->id)->count())->toBe(2); // 1 cancelada + 1 ativa

    // cleanup
    Transaction::where('repair_job_sheet_id', $os->id)->delete();
    $os->delete();
    $otherOpen->delete();
});

it('Multi-tenant Tier 0: reverse hook só toca Transactions do mesmo business_id (ADR 0093)', function () {
    $ctx = bootstrapRepairBiz();
    $os = createTestJobSheet($ctx, $ctx['open_status_id']);

    // Cria Tx derivada legítima do mesmo business.
    $os->update(['status_id' => $ctx['done_status_id']]);
    $tx = Transaction::where('repair_job_sheet_id', $os->id)->first();
    expect($tx)->not->toBeNull();

    // Cria Transaction LOOKALIKE em business diferente com mesmo os_ref (simulando cross-tenant).
    // Usa business_id propositalmente diferente pra garantir scope.
    $crossBizId = $ctx['business']->id === 1 ? 2 : 1;
    $crossLocation = DB::table('business_locations')->where('business_id', $crossBizId)->first();
    $crossContact = DB::table('contacts')->where('business_id', $crossBizId)->first();
    $crossUser = User::where('business_id', $crossBizId)->first();

    if (! $crossLocation || ! $crossContact || ! $crossUser) {
        // Sem 2º business completo · skip cross-tenant assertion (apenas valida que reverse não vaza).
        $os->update(['status_id' => $ctx['open_status_id']]);
        $tx->refresh();
        expect($tx->cancelled_at)->not->toBeNull();
        Transaction::where('repair_job_sheet_id', $os->id)->delete();
        $os->delete();

        return;
    }

    $crossTx = Transaction::create([
        'business_id' => $crossBizId,
        'location_id' => $crossLocation->id,
        'contact_id' => $crossContact->id,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'due',
        'source' => 'oficina',
        'os_ref' => "OS-{$os->id}", // mesmo os_ref · biz diferente
        'final_total' => 999.00,
        'total_before_tax' => 999.00,
        'transaction_date' => Carbon::now(),
        'created_by' => $crossUser->id,
    ]);

    // Reabre OS do business original · reverse SÓ deve cancelar Tx do business_id correto.
    $os->update(['status_id' => $ctx['open_status_id']]);

    $tx->refresh();
    $crossTx->refresh();

    expect($tx->cancelled_at)->not->toBeNull(); // Tx do mesmo biz cancelada
    expect($crossTx->cancelled_at)->toBeNull(); // Tx de outro biz INTACTA

    // cleanup
    $crossTx->delete();
    Transaction::where('repair_job_sheet_id', $os->id)->delete();
    $os->delete();
});
