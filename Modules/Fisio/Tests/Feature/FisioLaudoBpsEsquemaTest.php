<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-01..UC-FBP-02 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialSecao as Secao;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Camada de dados do laudo biopsicossocial (Lei 15.176/2025; LBI art. 2º, §1º).
 * Contrato: seção registrada não muda nem se apaga (trigger + model), correção é seção nova;
 * a empresa 99 não enxerga o laudo da 98. Tenant 98 contra 99 (ADR 0358).
 * ⚠️ SKIP em SQLite: schema UltimatePOS + trigger MySQL (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_secoes'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->p = F::paciente($this->b98, $this->u98->id);
    // SUPERADMIN: fixture de estado inicial na 98; o escopo é provado nos asserts.
    $this->laudo = LaudoBiopsicossocial::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'patient_id' => $this->p->id, 'created_by' => $this->u98->id,
    ]);
});

function fbpSecao(object $t, array $attrs = []): Secao
{
    // SUPERADMIN: fixture de estado inicial na 98.
    return Secao::withoutGlobalScopes()->create(array_merge([
        'business_id' => $t->b98, 'laudo_id' => $t->laudo->id, 'item' => 'atividades',
        'texto' => 'Limitação para permanecer em pé por mais de 20 minutos.',
        'autor_nome' => 'Fisioterapeuta Fixture', 'conselho' => 'CREFITO', 'registro_conselho' => F::CREFITO,
        'assinatura' => Secao::ASSINATURA_SISTEMA,
    ], $attrs));
}

it('UC-FBP-01 · seção registrada: UPDATE e DELETE falham no banco e no model; o rascunho segue editável', function () {
    $registrada = fbpSecao($this, ['registrado_por' => $this->u98->id, 'registrado_em' => now()]);

    expect(fn () => DB::table('fisio_laudos_bps_secoes')->where('id', $registrada->id)->update(['texto' => 'mexi']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps_secoes')->where('id', $registrada->id)->update(['registrado_em' => null]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps_secoes')->where('id', $registrada->id)->delete())->toThrow(QueryException::class);

    $registrada->texto = 'outro';
    expect(fn () => $registrada->save())->toThrow(DadoClinicoImutavelException::class)
        ->and(DB::table('fisio_laudos_bps_secoes')->where('id', $registrada->id)->value('texto'))
        ->toBe('Limitação para permanecer em pé por mais de 20 minutos.');

    // Correção é seção nova apontando a original.
    $correcao = fbpSecao($this, ['amends_id' => $registrada->id, 'texto' => 'Correção: 15 minutos.',
        'registrado_por' => $this->u98->id, 'registrado_em' => now()]);
    expect((int) $correcao->amends_id)->toBe($registrada->id);

    // Controle: rascunho (sem registrado_em) aceita UPDATE — o trigger é sobre o registrado.
    $rascunho = fbpSecao($this, ['item' => 'fatores', 'assinatura' => Secao::ASSINATURA_EXTERNA, 'conselho' => 'CRP']);
    expect(DB::table('fisio_laudos_bps_secoes')->where('id', $rascunho->id)->update(['texto' => 'revisado']))->toBe(1);
});

it('UC-FBP-02 · a empresa 99 não enxerga o laudo nem as seções da 98', function () {
    $secao = fbpSecao($this);

    F::como($this, $this->u99);
    expect(LaudoBiopsicossocial::query()->whereKey($this->laudo->id)->exists())->toBeFalse()
        ->and(Secao::query()->whereKey($secao->id)->exists())->toBeFalse();

    F::como($this, $this->u98);
    expect(LaudoBiopsicossocial::query()->whereKey($this->laudo->id)->exists())->toBeTrue()
        ->and($this->laudo->secoes()->pluck('id')->all())->toBe([$secao->id]);
});
