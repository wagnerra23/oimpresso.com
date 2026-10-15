<?php

declare(strict_types=1);
// Fundo do PCP: append-only. UC-PCV-28 entra com as entities (PR seguinte).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Fundo do PCP — thread 02 + E02 do playbook de Produção (ADR 0431). Os casos vêm do
 * `Producao.casos.md` / `Producao-celular.casos.md`, não do código.
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99, criado aqui se faltar. NUNCA biz=4.
 * Append-only: limpeza é o rollback da transação (trigger não dispara em rollback).
 * ⚠️ SKIP em SQLite (trigger e FK do schema MySQL): leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: o fundo do PCP depende de trigger MySQL.');
    }
    if (! Schema::hasTable('pcp_apontamentos')) {
        $this->markTestSkipped('Tabelas pcp_* ausentes — migrations do Modules/Producao não rodaram.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = pcpGarantirBiz99();
    $this->u98 = pcpUsuario($this->b98, 'diego');
    $this->u99 = pcpUsuario($this->b99, 'outro');
});

function pcpGarantirBiz99(): int
{
    if (! DB::table('business')->where('id', 99)->exists()) {
        $dono = pcpUsuario(null, 'dono99');
        DB::table('business')->insert(['id' => 99, 'name' => 'PCP Tenant 99 (ficticio)', 'currency_id' => (int) (DB::table('currencies')->value('id') ?? 1),
            'owner_id' => $dono, 'stop_selling_before' => 0, 'weighing_scale_setting' => '', 'certificado' => '',
            'officeimpresso_numerodemaquinas' => 0, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('users')->where('id', $dono)->update(['business_id' => 99]);
    }

    return 99;
}

function pcpUsuario(?int $biz, string $nome): int
{
    return (int) DB::table('users')->insertGetId(['first_name' => 'PCP '.$nome, 'username' => 'pcp-'.$nome.'-'.uniqid(),
        'password' => bcrypt('x'), 'business_id' => $biz, 'created_at' => now(), 'updated_at' => now()]);
}

it('fundo · apontamento e evento são append-only (trigger, como o Ponto)', function () {
    $posto = DB::table('pcp_postos')->insertGetId(['business_id' => $this->b98, 'codigo' => 'LAM-AO', 'nome' => 'Laminação',
        'tipo_custo' => 'pessoa', 'custo_hora' => '28.00', 'created_at' => now(), 'updated_at' => now()]);
    $ap = DB::table('pcp_apontamentos')->insertGetId(['business_id' => $this->b98, 'chave_idempotencia' => 'ch-ao', 'tipo' => 'inicio',
        'posto_id' => $posto, 'tipo_custo' => 'pessoa', 'custo_hora' => '28.00', 'user_id' => $this->u98,
        'iniciado_por' => $this->u98, 'momento' => now()]);
    $ev = DB::table('pcp_eventos')->insertGetId(['business_id' => $this->b98, 'tipo' => 'x']);

    expect(fn () => DB::table('pcp_apontamentos')->where('id', $ap)->update(['justificativa' => 'mexi']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_apontamentos')->where('id', $ap)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_eventos')->where('id', $ev)->update(['tipo' => 'y']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('pcp_eventos')->where('id', $ev)->delete())->toThrow(QueryException::class);

    // Controle: a tabela mutável do fundo aceita update (o bloqueio não é geral) e a linha segue lá.
    expect(DB::table('pcp_postos')->where('id', $posto)->update(['custo_hora' => '30.00']))->toBe(1)
        ->and(DB::table('pcp_apontamentos')->where('id', $ap)->value('justificativa'))->toBeNull();
});

