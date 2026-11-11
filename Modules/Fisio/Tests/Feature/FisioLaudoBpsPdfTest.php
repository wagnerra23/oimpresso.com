<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-10..UC-FBP-11 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Services\LaudoBiopsicossocialDocumento;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * PDF do laudo biopsicossocial: só seção registrada, a correção mais recente no lugar da
 * anterior, item sem registro em branco para assinatura à mão; uma linha no activity_log só
 * com ids. Conteúdo pelo serviço e pelo HTML da view; a rota pelo binário. Mesmo desenho do
 * FisioLaudoEvolucaoTest. Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_secoes', 'fisio_settings', 'activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
    $this->laudoId = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
    $base = "/fisio/laudos-bps/{$this->laudoId}/secoes";

    // Atividades: registrada, depois corrigida. Fatores: externa registrada. Impedimentos: só rascunho.
    $this->putJson("{$base}/atividades", ['texto' => 'TEXTO-ORIGINAL-ATIVIDADES', 'assinatura' => 'sistema'])->assertOk();
    $this->postJson("{$base}/atividades/registrar")->assertOk();
    $this->putJson("{$base}/atividades", ['texto' => 'TEXTO-CORRIGIDO-ATIVIDADES', 'assinatura' => 'sistema'])->assertOk();
    $this->postJson("{$base}/atividades/registrar")->assertOk();
    $this->putJson("{$base}/fatores", ['texto' => 'TEXTO-PSICOLOGIA', 'assinatura' => 'externa',
        'autor_nome' => 'Psicóloga Fixture', 'conselho' => 'CRP', 'registro_conselho' => 'CRP-12/00000'])->assertOk();
    $this->postJson("{$base}/fatores/registrar")->assertOk();
    $this->putJson("{$base}/impedimentos", ['texto' => 'RASCUNHO-NAO-REGISTRADO', 'assinatura' => 'externa'])->assertOk();
});

it('UC-FBP-10 · o PDF traz só o registrado, na ordem da LBI; o que falta sai em branco para assinar à mão', function () {
    $laudo = LaudoBiopsicossocial::query()->findOrFail($this->laudoId);
    $d = app(LaudoBiopsicossocialDocumento::class)->montar($laudo, $this->b98);

    expect(array_column($d['secoes'], 'item'))->toBe(['impedimentos', 'fatores', 'atividades', 'participacao'])
        ->and(array_column($d['secoes'], 'inciso'))->toBe(['I', 'II', 'III', 'IV'])
        ->and($d['secoes'][0]['registrada'])->toBeNull()
        ->and($d['secoes'][1]['registrada']['externa'])->toBeTrue()
        ->and($d['secoes'][2]['registrada']['texto'])->toBe('TEXTO-CORRIGIDO-ATIVIDADES')
        ->and($d['secoes'][2]['registrada']['corrige'])->toBeTrue()
        ->and($d['secoes'][2]['registrada']['registro'])->toBe(F::CREFITO)
        ->and($d['secoes'][3]['registrada'])->toBeNull();

    $html = view('fisio::pdf.laudo-biopsicossocial', $d)->render();
    expect($html)->toContain('Laudo biopsicossocial integrado')
        ->and($html)->toContain('TEXTO-CORRIGIDO-ATIVIDADES')
        ->and($html)->toContain('TEXTO-PSICOLOGIA')
        ->and($html)->toContain('CRP CRP-12/00000')
        ->and($html)->toContain('o enquadramento cabe à perícia')
        ->and($html)->toContain('CRESS (ou outro conselho)');
    expect(str_contains($html, 'TEXTO-ORIGINAL-ATIVIDADES'))->toBeFalse()
        ->and(str_contains($html, 'RASCUNHO-NAO-REGISTRADO'))->toBeFalse();
});

it('UC-FBP-11 · a rota baixa o PDF e grava uma linha no activity_log só com ids; outra empresa 404, sem log', function () {
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();
    F::como($this, $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant()));
    $r = $this->get("/fisio/laudos-bps/{$this->laudoId}/pdf")->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');
    $log = DB::table('activity_log')->where('log_name', 'fisio')->where('description', 'laudo_bps_exportado')->where('subject_id', $this->p->id)->sole();
    $props = json_decode((string) $log->properties, true);
    expect($props['laudo_bps_id'])->toBe($this->laudoId)
        ->and($props['secoes_registradas'])->toBe(2)
        ->and(str_contains((string) $log->properties, (string) $contato->name))->toBeFalse()
        ->and(str_contains((string) $log->properties, 'TEXTO-'))->toBeFalse();

    F::como($this, $this->u99);
    $this->get("/fisio/laudos-bps/{$this->laudoId}/pdf")->assertNotFound();
    expect(DB::table('activity_log')->where('description', 'laudo_bps_exportado')->where('subject_id', $this->p->id)->count())->toBe(1);
});
