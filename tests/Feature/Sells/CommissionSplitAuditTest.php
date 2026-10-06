<?php

declare(strict_types=1);

use App\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;
use Tests\Contract\AutosaveContractRunner;

/**
 * Split de comissão auditado — playbook comissões, thread 03.
 *
 * UC-COM-01 `[T0]` — trocar o split (`commission_split`) ou o comissionado (`commission_agent`)
 *                    de uma venda grava auditoria com usuário, antes → depois, no business da venda.
 * UC-COM-02 `[T0]` — o PATCH do split não alcança venda nem usuário de outro business.
 *
 * POR QUE EXISTE: o docblock do SellCommissionSplitController afirmava "audit log via Spatie
 * ActivityLog (já configurado em Transaction model)", mas o `logOnly` do Transaction não
 * listava nenhum dos dois campos (medido no main @23c3f080aa94, 2026-10-06). Trocar a comissão
 * de um vendedor só deixava `Log::info` em arquivo.
 *
 * ANTI-VÁCUO: cada contrato negativo tem controle positivo ao lado. "Salvar o mesmo split não
 * gera registro" só prova algo porque "trocar o split gera registro" passa no mesmo setup.
 * O mesmo vale para o 404/422 cross-tenant (o PATCH no próprio business devolve 200).
 *
 * Tenants: biz da lane (A) × segundo business semeado (B). Nunca biz=4 (ADR 0101/0358).
 *
 * @see app/Transaction.php::getActivitylogOptions
 * @see app/Http/Controllers/SellCommissionSplitController.php
 * @see prototipo-ui/cowork/Wagner/cowork-inbox/comissoes/playbook/03-split-auditado.md
 * @see memory/decisions/0192-auto-faturar-os-venda-jobsheet-observer.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'activity_log', 'users'] as $tabela) {
        if (! Schema::hasTable($tabela)) {
            $this->markTestSkipped("Schema ausente ({$tabela}).");
        }
    }
    if (! Schema::hasColumn('transactions', 'commission_split')) {
        $this->markTestSkipped('migration commission_split não aplicada.');
    }

    $ctx = AutosaveContractRunner::setupSellsContext($this);
    $this->businessA = $ctx['business'];
    $this->user = $ctx['user'];
    $this->vendaId = $ctx['transactionId'];

    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'sell.update', 'guard_name' => 'web']);
    $this->user->givePermissionTo('sell.update');
});

/** Entradas de auditoria `updated` da venda, mais recente primeiro. */
function comAuditUpdates(int $vendaId)
{
    return Activity::query()
        ->where('subject_type', Transaction::class)
        ->where('subject_id', $vendaId)
        ->where('log_name', 'sales.transaction')
        ->where('event', 'updated')
        ->orderByDesc('id')
        ->get();
}

function comSplit(int $mecanicoId, float $mecPct, ?int $balcaoId, float $balPct): array
{
    return ['mecanico_id' => $mecanicoId, 'mecanico_pct' => $mecPct, 'balcao_id' => $balcaoId, 'balcao_pct' => $balPct];
}

// ─── UC-COM-01 ───────────────────────────────────────────────────────────────

it('UC-COM-01 · trocar o split 70/30 → 100/0 pelo PATCH grava antes → depois com usuário e business', function () {
    $tx = Transaction::findOrFail($this->vendaId);
    $tx->commission_split = comSplit($this->user->id, 70.0, 999001, 30.0);
    $tx->save();
    $antes = comAuditUpdates($this->vendaId)->count();

    $this->patchJson("/sells/{$this->vendaId}/commission-split", [
        'commission_split' => comSplit($this->user->id, 100.0, null, 0.0),
    ])->assertOk();

    $logs = comAuditUpdates($this->vendaId);
    $this->assertSame($antes + 1, $logs->count(), 'O PATCH do split deveria gerar exatamente 1 registro de auditoria.');

    $log = $logs->first();
    $this->assertSame((int) $this->user->id, (int) $log->causer_id, 'A auditoria deveria registrar quem trocou o split.');
    $this->assertSame((int) $this->businessA->id, (int) $log->business_id, 'A auditoria deveria ficar no business da venda.');
    $this->assertNotNull($log->created_at);

    $old = $log->properties['old']['commission_split'] ?? null;
    $new = $log->properties['attributes']['commission_split'] ?? null;
    $this->assertIsArray($old, 'properties.old deveria trazer o split anterior.');
    $this->assertIsArray($new, 'properties.attributes deveria trazer o split novo.');
    $this->assertEquals(70.0, (float) $old['mecanico_pct']);
    $this->assertEquals(999001, (int) $old['balcao_id']);
    $this->assertEquals(100.0, (float) $new['mecanico_pct']);
    $this->assertNull($new['balcao_id']);
});

it('UC-COM-01 · limpar o split (null) pelo PATCH grava o valor anterior', function () {
    $tx = Transaction::findOrFail($this->vendaId);
    $tx->commission_split = comSplit($this->user->id, 70.0, 999001, 30.0);
    $tx->save();
    $antes = comAuditUpdates($this->vendaId)->count();

    $this->patchJson("/sells/{$this->vendaId}/commission-split", ['commission_split' => null])->assertOk();

    $logs = comAuditUpdates($this->vendaId);
    $this->assertSame($antes + 1, $logs->count(), 'Limpar o split deveria gerar 1 registro de auditoria.');
    $log = $logs->first();
    $this->assertIsArray($log->properties['old']['commission_split'] ?? null, 'O split apagado deveria ficar no antes.');
    // `??` devolveria o lado direito também quando a chave existe com null — que é o
    // caso esperado aqui. Presença e valor são conferidos separadamente.
    $novos = $log->properties['attributes'] ?? [];
    $this->assertArrayHasKey('commission_split', $novos, 'O depois deveria trazer commission_split (null).');
    $this->assertNull($novos['commission_split']);
});

it('UC-COM-01 · trocar o commission_agent da venda grava antes → depois', function () {
    $tx = Transaction::findOrFail($this->vendaId);
    $tx->commission_agent = null;
    $tx->save();
    $antes = comAuditUpdates($this->vendaId)->count();

    $tx->commission_agent = $this->user->id;
    $tx->save();

    $logs = comAuditUpdates($this->vendaId);
    $this->assertSame($antes + 1, $logs->count(), 'Trocar o comissionado deveria gerar 1 registro de auditoria.');
    $log = $logs->first();
    $this->assertArrayHasKey('commission_agent', $log->properties['old'] ?? []);
    $this->assertNull($log->properties['old']['commission_agent']);
    $this->assertSame((int) $this->user->id, (int) $log->properties['attributes']['commission_agent']);
});

it('UC-COM-01 · controle positivo: salvar o MESMO split não gera registro', function () {
    $split = comSplit($this->user->id, 100.0, null, 0.0);
    $this->patchJson("/sells/{$this->vendaId}/commission-split", ['commission_split' => $split])->assertOk();
    $antes = comAuditUpdates($this->vendaId)->count();
    $this->assertGreaterThan(0, $antes, 'Setup inválido: o primeiro PATCH deveria ter gerado auditoria.');

    $this->patchJson("/sells/{$this->vendaId}/commission-split", ['commission_split' => $split])->assertOk();

    $this->assertSame($antes, comAuditUpdates($this->vendaId)->count(), 'Split idêntico não deveria gerar registro (logOnlyDirty).');
});

// ─── UC-COM-02 ───────────────────────────────────────────────────────────────

it('UC-COM-02 · venda de outro business → 404 e nada muda; mesmo business → 200', function () {
    $businessB = \App\Business::where('id', '!=', $this->businessA->id)->first();
    if (! $businessB) {
        $this->markTestSkipped('Lane sem 2º business semeado — contrato cross-tenant não exercitável.');
    }
    $vendaB = DB::table('transactions')->insertGetId([
        'business_id' => $businessB->id,
        'created_by' => $this->user->id,
        'type' => 'sell',
        'status' => 'draft',
        'payment_status' => 'due',
        'invoice_no' => 'CT-COM-' . substr((string) microtime(true), -6),
        'transaction_date' => now(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $split = comSplit($this->user->id, 100.0, null, 0.0);

    $this->patchJson("/sells/{$vendaB}/commission-split", ['commission_split' => $split])->assertNotFound();
    $this->assertNull(DB::table('transactions')->where('id', $vendaB)->value('commission_split'));

    // Controle positivo: o mesmo payload no próprio business passa.
    $this->patchJson("/sells/{$this->vendaId}/commission-split", ['commission_split' => $split])->assertOk();
});

it('UC-COM-02 · mecânico ou balconista de outro business → 422 no campo certo', function () {
    $userB = \App\User::where('business_id', '!=', $this->businessA->id)->first();
    if (! $userB) {
        $this->markTestSkipped('Lane sem usuário em outro business — contrato cross-tenant não exercitável.');
    }

    $this->patchJson("/sells/{$this->vendaId}/commission-split", [
        'commission_split' => comSplit($userB->id, 100.0, null, 0.0),
    ])->assertStatus(422)->assertJsonValidationErrors(['commission_split.mecanico_id']);

    $this->patchJson("/sells/{$this->vendaId}/commission-split", [
        'commission_split' => comSplit($this->user->id, 70.0, $userB->id, 30.0),
    ])->assertStatus(422)->assertJsonValidationErrors(['commission_split.balcao_id']);

    $this->assertNull(DB::table('transactions')->where('id', $this->vendaId)->value('commission_split'));
});
