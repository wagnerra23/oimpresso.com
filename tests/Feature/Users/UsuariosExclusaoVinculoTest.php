<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/10 — UC-USUA-05: não excluir usuário com venda/OS no nome.
 *
 * Decisão [W] 2026-10-07 (D-USU-NOME): conta como "no nome" quem criou a venda/OS
 * (`transactions.created_by`, `repair_job_sheets.created_by`), o vendedor da venda
 * (`transactions.res_waiter_id`) e o comissionado (`transactions.commission_agent`).
 * Colunas lidas do schema (database/schema/mysql-schema.sql), não inventadas.
 *
 * Cada caso confere o MOTIVO da recusa (não só o status) e que o usuário NÃO foi excluído;
 * o controle sem vínculo prova que a requisição chega ao destroy() e exclui.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358). Nunca biz=4.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('users', 'is_cmmsn_agnt')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['user.view', 'user.delete'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    $this->h = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function uexvVenda(int $businessId, int $criador, array $extra = []): int
{
    return (int) DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'type' => 'sell', 'status' => 'final', 'payment_status' => 'due',
        'transaction_date' => now(), 'final_total' => 0, 'total_before_tax' => 0, 'created_by' => $criador,
        'essentials_duration' => 0, 'invoice_no' => 'UEXV-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ], $extra));
}

function uexvBloqueado($teste, \App\User $alvo, string $contagem): void
{
    $r = $teste->withHeaders($teste->h)->delete("/users/{$alvo->id}");
    $r->assertStatus(422);
    expect($r->json('success'))->toBeFalse();
    expect((string) $r->json('msg'))->toContain('Não é possível excluir');
    expect((string) $r->json('msg'))->toContain($contagem);
    expect(\App\User::withTrashed()->find($alvo->id)->trashed())->toBeFalse();
}

test('UC-USUA-05 recusa excluir quem CRIOU a venda', function () {
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);
    uexvVenda($this->business->id, $alvo->id);

    uexvBloqueado($this, $alvo, '1 venda');
});

test('UC-USUA-05 recusa excluir o VENDEDOR da venda (res_waiter_id)', function () {
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);
    uexvVenda($this->business->id, $this->user->id, ['res_waiter_id' => $alvo->id]);

    uexvBloqueado($this, $alvo, '1 venda');
});

test('UC-USUA-05 recusa excluir o COMISSIONADO da venda (commission_agent)', function () {
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);
    uexvVenda($this->business->id, $this->user->id, ['commission_agent' => $alvo->id]);
    uexvVenda($this->business->id, $this->user->id, ['commission_agent' => $alvo->id]);

    uexvBloqueado($this, $alvo, '2 vendas');
});

test('UC-USUA-05 recusa excluir quem CRIOU a OS', function () {
    if (! Schema::hasTable('repair_job_sheets')) {
        $this->markTestSkipped('Módulo Repair ausente neste schema.');
    }
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);
    $contato = (int) DB::table('contacts')->insertGetId([
        'business_id' => $this->business->id, 'type' => 'customer', 'name' => 'UEXV '.uniqid(), 'mobile' => '0',
        'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('repair_job_sheets')->insert([
        'business_id' => $this->business->id, 'contact_id' => $contato, 'job_sheet_no' => 'UEXV-'.uniqid(),
        'service_type' => 'carry_in', 'serial_no' => 'SN-UEXV', 'status_id' => 0,
        'created_by' => $alvo->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    uexvBloqueado($this, $alvo, '1 OS');
});

test('UC-USUA-05 controle — sem venda/OS no nome a exclusão acontece', function () {
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);

    $r = $this->withHeaders($this->h)->delete("/users/{$alvo->id}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(\App\User::withTrashed()->find($alvo->id)->trashed())->toBeTrue();
});

test('UC-USUA-05 Tier 0 — venda de OUTRO negócio não bloqueia a exclusão', function () {
    $outro = $this->seededSupportClientTenant();
    $alvo = \App\User::factory()->create(['business_id' => $this->business->id]);
    uexvVenda($outro->id, $this->user->id, ['commission_agent' => $alvo->id]);

    $r = $this->withHeaders($this->h)->delete("/users/{$alvo->id}");
    $r->assertOk();
    expect($r->json('success'))->toBeTrue();
    expect(\App\User::withTrashed()->find($alvo->id)->trashed())->toBeTrue();
});
