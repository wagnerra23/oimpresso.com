<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\FolhaCompetencia;
use Modules\Essentials\Entities\FolhaItem;
use Modules\Essentials\Entities\FolhaRubrica;
use Modules\Essentials\Entities\FolhaRubricaVigencia;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Folha CLT · contrato do modelo de verba — passo 3 do §9 da ADR-mãe da folha
 * (memory/decisions/proposals/2026-09-05-folha-com-encargos-modelo-e-fronteira.md).
 *
 * Âncora: §2.2 (três incidências independentes), §2.3 (incidência por competência), §2.5 (duas
 * competências no item) e §2.7 (o contrato mínimo). Nenhum caso calcula valor: o motor é o passo 5.
 *
 * Tier 0 (ADR 0093 + ADR 0358): tenant de teste 98, tenant alheio 2 (os dois semeados pela action
 * pest-mysql-setup). Nunca biz=4. DatabaseTransactions: a lane compartilha o seed.
 *
 * @group folha
 * @covers-us US-ESS-015
 */
const FOLHA_BIZ = 98;
const FOLHA_BIZ_ALHEIO = 2;

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (lane essentials-pest).');
    }
    if (! Schema::hasTable('essentials_folha_itens')) {
        $this->markTestSkipped('Tabelas da folha ausentes — rode o migrate do módulo Essentials.');
    }
});

function folhaRubrica(int $biz, string $codigo, array $vigencias): FolhaRubrica
{
    $r = FolhaRubrica::create([
        'business_id' => $biz, 'codigo' => $codigo, 'descricao' => $codigo,
        'natureza' => '1000', 'sentido' => 'provento',
    ]);
    foreach ($vigencias as $v) {
        FolhaRubricaVigencia::create(['business_id' => $biz, 'rubrica_id' => $r->id] + $v);
    }

    return $r;
}

it('UC-FOLHA-VERBA-01: as três incidências são independentes (aviso prévio indenizado é não, não, sim)', function () {
    $r = folhaRubrica(FOLHA_BIZ, 'T-AVISO-IND', [[
        'vigencia_inicio' => '2026-01', 'vigencia_fim' => null,
        'incide_inss' => false, 'incide_irrf' => false, 'incide_fgts' => true,
    ]]);

    $v = $r->fresh()->vigenciaEm('2026-10');

    expect($v)->not->toBeNull()
        ->and($v->incide_inss)->toBeFalse()
        ->and($v->incide_irrf)->toBeFalse()
        ->and($v->incide_fgts)->toBeTrue();
});

it('UC-FOLHA-VERBA-02: a incidência é resolvida pela competência pedida, não pela vigente hoje', function () {
    $r = folhaRubrica(FOLHA_BIZ, 'T-VIG', [
        ['vigencia_inicio' => '2025-01', 'vigencia_fim' => '2025-12', 'incide_inss' => true, 'incide_irrf' => true, 'incide_fgts' => true],
        ['vigencia_inicio' => '2026-01', 'vigencia_fim' => null, 'incide_inss' => true, 'incide_irrf' => false, 'incide_fgts' => true],
    ]);

    expect($r->vigenciaEm('2025-12')->incide_irrf)->toBeTrue()
        ->and($r->vigenciaEm('2026-03')->incide_irrf)->toBeFalse()
        ->and($r->vigenciaEm('2024-12'))->toBeNull();
});

it('UC-FOLHA-VERBA-03: o item retroativo guarda as duas competências e lê a vigência da apuração', function () {
    $r = folhaRubrica(FOLHA_BIZ, 'T-RETRO', [
        ['vigencia_inicio' => '2026-01', 'vigencia_fim' => '2026-05', 'incide_inss' => true, 'incide_irrf' => true, 'incide_fgts' => true],
        ['vigencia_inicio' => '2026-06', 'vigencia_fim' => null, 'incide_inss' => false, 'incide_irrf' => true, 'incide_fgts' => true],
    ]);
    $userId = DB::table('users')->where('business_id', FOLHA_BIZ)->value('id');

    $item = FolhaItem::create([
        'business_id' => FOLHA_BIZ, 'user_id' => $userId, 'rubrica_id' => $r->id, 'valor' => '100.0000',
        'competencia_pagamento' => '2026-10', 'competencia_apuracao' => '2026-05',
        'proveniencia' => 'manual', 'proveniencia_ref' => (string) $userId,
    ])->fresh();

    expect($item->competencia_pagamento)->toBe('2026-10')
        ->and($item->competencia_apuracao)->toBe('2026-05')
        // na apuração (05) incidia INSS; na de pagamento (10) já não incidiria
        ->and($item->vigencia()->incide_inss)->toBeTrue()
        ->and($r->vigenciaEm('2026-10')->incide_inss)->toBeFalse();
});

it('UC-FOLHA-VERBA-04: competência fora do formato AAAA-MM é recusada', function (string $ruim) {
    expect(fn () => FolhaCompetencia::create(['business_id' => FOLHA_BIZ, 'competencia' => $ruim]))
        ->toThrow(InvalidArgumentException::class);
})->with(['2026-13', '202610', '2026-1', '26-10']);

it('UC-FOLHA-VERBA-05: item não aceita rubrica de outra empresa (Tier 0)', function () {
    $alheia = folhaRubrica(FOLHA_BIZ_ALHEIO, 'T-ALHEIA', []);
    $userId = DB::table('users')->where('business_id', FOLHA_BIZ)->value('id');

    expect(fn () => FolhaItem::create([
        'business_id' => FOLHA_BIZ, 'user_id' => $userId, 'rubrica_id' => $alheia->id, 'valor' => '1.0000',
        'competencia_pagamento' => '2026-10', 'competencia_apuracao' => '2026-10', 'proveniencia' => 'manual',
    ]))->toThrow(InvalidArgumentException::class, 'não pertence à empresa');

    expect(FolhaItem::withoutGlobalScopes()->where('rubrica_id', $alheia->id)->count())->toBe(0);
});

it('UC-FOLHA-VERBA-06: o catálogo de rubricas é por empresa e o scope esconde a alheia', function () {
    folhaRubrica(FOLHA_BIZ, 'T-MESMO-COD', []);
    folhaRubrica(FOLHA_BIZ_ALHEIO, 'T-MESMO-COD', []);

    $user = User::where('business_id', FOLHA_BIZ)->firstOrFail();
    $this->actingAs($user);
    session(['user.business_id' => FOLHA_BIZ]);

    expect(FolhaRubrica::where('codigo', 'T-MESMO-COD')->pluck('business_id')->all())->toBe([FOLHA_BIZ]);
});

it('UC-FOLHA-VERBA-07: a flag da folha CLT nasce desligada', function () {
    expect(config('essentials.folha_clt.enabled'))->toBeFalse();
});
