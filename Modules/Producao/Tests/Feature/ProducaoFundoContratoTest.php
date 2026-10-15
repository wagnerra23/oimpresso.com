<?php

declare(strict_types=1);
// Cobre UC-PCV-28 (Producao.casos.md).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Apontamento;
use Modules\Producao\Entities\Evento;
use Modules\Producao\Entities\Posto;

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

function pcpPosto(int $biz, string $codigo, string $tipo, string $custo): Posto
{
    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts, não aqui.
    return Posto::withoutGlobalScopes()->create(['business_id' => $biz, 'codigo' => $codigo, 'nome' => $codigo,
        'tipo_custo' => $tipo, 'custo_hora' => $custo]);
}

function pcpComo(int $userId, int $biz): void
{
    test()->actingAs(\App\User::find($userId));
    session(['user.business_id' => $biz]);
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

it('UC-PCV-28 · cada empresa vê só a sua produção', function () {
    foreach ([$this->b98 => $this->u98, $this->b99 => $this->u99] as $biz => $user) {
        $posto = pcpPosto($biz, 'ROLAND', Posto::MAQUINA, '42.00'); // mesmo código nas duas: único por empresa
        // SUPERADMIN: fixture grava nas duas empresas; o escopo é provado nos asserts.
        Apontamento::withoutGlobalScopes()->create(['business_id' => $biz, 'chave_idempotencia' => "ch-28-{$biz}", 'tipo' => 'inicio',
            'os_tipo' => 'repair_job_sheet', 'os_id' => 28, 'posto_id' => $posto->id, 'tipo_custo' => Posto::MAQUINA,
            'custo_hora' => '42.00', 'maquina_id' => $posto->id, 'iniciado_por' => $user, 'momento' => now()]);
        Evento::withoutGlobalScopes()->create(['business_id' => $biz, 'tipo' => 'etapa_iniciada', 'os_tipo' => 'repair_job_sheet', 'os_id' => 28]);
    }

    pcpComo($this->u98, $this->b98);
    expect(Posto::query()->where('codigo', 'ROLAND')->pluck('business_id')->map('intval')->all())->toBe([$this->b98])
        ->and(Apontamento::query()->where('os_id', 28)->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98])
        ->and(Evento::query()->where('os_id', 28)->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);

    // O posto da 99, lido pelo id a partir da 98, não existe para ela.
    $postoAlheio = Posto::withoutGlobalScopes()->where('business_id', $this->b99)->where('codigo', 'ROLAND')->value('id');
    expect(Posto::query()->find($postoAlheio))->toBeNull();

    // Discriminante: a mesma consulta como 99 devolve só a 99 (o filtro troca, não esvazia).
    pcpComo($this->u99, $this->b99);
    expect(Posto::query()->where('codigo', 'ROLAND')->pluck('business_id')->map('intval')->all())->toBe([$this->b99]);

    // Controle negativo: sem escopo os dois registros existem — o filtro de cima é real.
    // SUPERADMIN: controle negativo do teste de isolamento.
    expect(Posto::withoutGlobalScopes()->where('codigo', 'ROLAND')->whereIn('business_id', [$this->b98, $this->b99])->count())->toBe(2);
});

