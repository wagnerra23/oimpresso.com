<?php

declare(strict_types=1);

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Auditoria RecurringBilling b6 (decisão técnica 2026-11-23): apagar um contato ou um plano não
 * leva mais as assinaturas e faturas junto. As FKs contact_id (assinaturas e faturas) e plan_id
 * (assinaturas) passam de ON DELETE CASCADE para RESTRICT (migration 2026_11_23_100000).
 *
 * Dois caminhos:
 *  - a tela (DELETE /contacts/{id}): contato e plano usam soft delete; a exclusão com fatura é
 *    recusada com mensagem e nada some;
 *  - o hard delete (SQL direto): o banco recusa, e as faturas continuam lá.
 *
 * Tenant 98 (ADR 0358). Nunca biz=4. ⚠️ SKIP fora do MySQL: leia assertions (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do RecurringBilling (MySQL).');
    }
    $this->biz = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view', 'customer.delete'], $this->biz);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->biz->id, 'user.id' => $this->user->id, 'business.id' => $this->biz->id]);

    $this->contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->biz->id, 'type' => 'customer', 'name' => 'Cliente B6 '.uniqid(),
        'mobile' => '', 'contact_id' => 'RBB6-'.uniqid(), 'contact_status' => 'active',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->fatura = (int) DB::table('rb_invoices')->insertGetId([
        'business_id' => $this->biz->id, 'contact_id' => $this->contato,
        'numero_documento' => 'B6-'.uniqid(), 'valor' => '10.00', 'status' => 'open',
        'vencimento' => now()->addDays(5)->toDateString(), 'created_at' => now(), 'updated_at' => now(),
    ]);
});

it('b6 · as FKs contact_id e plan_id recusam a exclusão (RESTRICT)', function () {
    $regra = function (string $tabela, string $coluna): string {
        $fk = collect(Schema::getForeignKeys($tabela))->first(fn ($f) => $f['columns'] === [$coluna]);
        expect($fk)->not->toBeNull();

        return strtolower((string) $fk['on_delete']);
    };

    expect($regra('rb_subscriptions', 'contact_id'))->toBe('restrict')
        ->and($regra('rb_invoices', 'contact_id'))->toBe('restrict')
        ->and($regra('rb_subscriptions', 'plan_id'))->toBe('restrict');
});

it('b6 · pela tela, excluir contato com fatura é recusado com motivo e a fatura continua', function () {
    $resposta = $this->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->delete("/contacts/{$this->contato}");

    $resposta->assertOk()->assertJsonPath('success', false);
    expect((string) $resposta->json('msg'))->toContain('faturas de assinatura');

    expect(DB::table('contacts')->where('id', $this->contato)->whereNull('deleted_at')->exists())->toBeTrue()
        ->and(DB::table('rb_invoices')->where('id', $this->fatura)->exists())->toBeTrue();
});

it('b6 · hard delete do contato com fatura é recusado pelo banco e a fatura continua', function () {
    expect(fn () => DB::table('contacts')->where('id', $this->contato)->delete())
        ->toThrow(QueryException::class);

    expect(DB::table('contacts')->where('id', $this->contato)->exists())->toBeTrue()
        ->and(DB::table('rb_invoices')->where('id', $this->fatura)->where('contact_id', $this->contato)->exists())->toBeTrue();
});

it('b6 · hard delete do plano com assinatura é recusado pelo banco e a assinatura continua', function () {
    $plano = (int) DB::table('rb_plans')->insertGetId([
        'business_id' => $this->biz->id, 'name' => 'Plano B6', 'slug' => 'plano-b6-'.uniqid(),
        'valor' => '10.00', 'ciclo' => 'monthly', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $dados = [
        'business_id' => $this->biz->id, 'plan_id' => $plano, 'contact_id' => $this->contato,
        'status' => 'active', 'start_date' => now()->toDateString(),
        'next_due_date' => now()->addMonth()->toDateString(), 'billing_anchor_date' => now()->toDateString(),
        'created_at' => now(), 'updated_at' => now(),
    ];
    $dados = array_filter($dados, fn ($coluna) => Schema::hasColumn('rb_subscriptions', $coluna), ARRAY_FILTER_USE_KEY);
    $assinatura = (int) DB::table('rb_subscriptions')->insertGetId($dados);

    expect(fn () => DB::table('rb_plans')->where('id', $plano)->delete())
        ->toThrow(QueryException::class);

    expect(DB::table('rb_subscriptions')->where('id', $assinatura)->where('plan_id', $plano)->exists())->toBeTrue();
});
