<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-02..UC-FBP-03 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Entities\LaudoBiopsicossocialEquipe as Equipe;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Services\Laudo\MatrizIfbrm;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Camada de dados do laudo biopsicossocial (modelo SES-SC, Deliberação 500/CIB/2026).
 * Contrato: laudo finalizado não muda, nem a equipe dele (trigger + model); correção é laudo novo;
 * a empresa 99 não vê o laudo da 98. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_equipe'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    $this->p = F::paciente($this->b98, $this->u98->id);
    // SUPERADMIN: fixture de estado inicial na 98; o escopo é provado nos asserts.
    $this->laudo = LaudoBiopsicossocial::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'patient_id' => $this->p->id, 'modelo' => MatrizIfbrm::MODELO,
        'dados' => ['fatores' => ['ambientais' => 'Texto original']], 'created_by' => $this->u98->id,
    ]);
    // SUPERADMIN: idem.
    $this->membro = Equipe::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'laudo_id' => $this->laudo->id, 'area' => 'medicina',
        'nome' => 'Médica Fixture', 'registro' => 'CRM-SC 00000', 'assinatura' => 'externa', 'incluido_por' => $this->u98->id,
    ]);
});

it('UC-FBP-02 · laudo finalizado: laudo e equipe não mudam no banco nem no model; antes de finalizar, mudam', function () {
    // Controle: em rascunho, laudo e equipe aceitam UPDATE.
    expect(DB::table('fisio_laudos_bps')->where('id', $this->laudo->id)->update(['dados' => json_encode(['fatores' => ['ambientais' => 'Revisto']])]))->toBe(1)
        ->and(DB::table('fisio_laudos_bps_equipe')->where('id', $this->membro->id)->update(['registro' => 'CRM-SC 11111']))->toBe(1);

    DB::table('fisio_laudos_bps')->where('id', $this->laudo->id)->update(['finalizado_por' => $this->u98->id, 'finalizado_em' => now()]);

    expect(fn () => DB::table('fisio_laudos_bps')->where('id', $this->laudo->id)->update(['dados' => json_encode([])]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps')->where('id', $this->laudo->id)->update(['finalizado_em' => null]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps')->where('id', $this->laudo->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps_equipe')->where('id', $this->membro->id)->update(['nome' => 'Outra']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps_equipe')->where('id', $this->membro->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_laudos_bps_equipe')->insert(['business_id' => $this->b98, 'laudo_id' => $this->laudo->id, 'area' => 'psicologia',
            'nome' => 'X', 'registro' => 'Y', 'assinatura' => 'externa', 'incluido_por' => $this->u98->id]))->toThrow(QueryException::class);

    // SUPERADMIN: leitura de verificação.
    $finalizado = LaudoBiopsicossocial::withoutGlobalScopes()->findOrFail($this->laudo->id);
    $finalizado->dados = [];
    expect(fn () => $finalizado->save())->toThrow(DadoClinicoImutavelException::class)
        ->and(LaudoBiopsicossocial::withoutGlobalScopes()->findOrFail($this->laudo->id)->dados['fatores']['ambientais'])->toBe('Revisto');

    // Correção é laudo novo apontando o anterior.
    // SUPERADMIN: fixture.
    $novo = LaudoBiopsicossocial::withoutGlobalScopes()->create(['business_id' => $this->b98, 'patient_id' => $this->p->id,
        'modelo' => MatrizIfbrm::MODELO, 'created_by' => $this->u98->id, 'amends_id' => $this->laudo->id]);
    expect((int) $novo->amends_id)->toBe($this->laudo->id);
});

it('UC-FBP-03 · a empresa 99 não vê o laudo nem a equipe da 98', function () {
    F::como($this, $this->u99);
    expect(LaudoBiopsicossocial::query()->whereKey($this->laudo->id)->exists())->toBeFalse()
        ->and(Equipe::query()->whereKey($this->membro->id)->exists())->toBeFalse();

    F::como($this, $this->u98);
    expect(LaudoBiopsicossocial::query()->whereKey($this->laudo->id)->exists())->toBeTrue()
        ->and($this->laudo->equipe()->pluck('id')->all())->toBe([$this->membro->id]);
});
