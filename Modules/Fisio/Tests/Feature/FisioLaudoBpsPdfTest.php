<?php

declare(strict_types=1);
// @covers-us US-FISIO-060
// Cobre UC-FBP-13..UC-FBP-14 (memory/requisitos/Fisio/_telas/laudo-biopsicossocial.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\LaudoBiopsicossocial;
use Modules\Fisio\Services\Laudo\LaudoBiopsicossocialDocumento;
use Modules\Fisio\Services\Laudo\MatrizIfbrm;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * PDF do laudo biopsicossocial no layout do modelo SES-SC. Conteúdo pelo serviço e pelo HTML da
 * view; a rota pelo binário e pela linha no activity_log. Tenant 98 contra 99 (ADR 0358).
 * ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_laudos_bps', 'fisio_laudos_bps_equipe', 'fisio_settings', 'activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, (int) $this->seededSupportClientTenant()->id]);
    $this->p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
    $this->id = (int) $this->postJson("/fisio/pacientes/{$this->p->id}/laudos-bps")->assertCreated()->json('id');
    $matriz = array_fill_keys(MatrizIfbrm::itens(), 75);
    $matriz['3.4'] = 25; // domínio 3 vale 25: total = 7 × 75 + 25 = 550
    $this->putJson("/fisio/laudos-bps/{$this->id}", ['matriz' => $matriz,
        'identificacao' => ['unidade_aps' => 'UBS-FIXTURE', 'cid' => 'M79.7', 'natureza' => ['fisica']],
        'fatores' => ['ambientais' => 'TEXTO-AMBIENTAL']])->assertOk();
    $this->postJson("/fisio/laudos-bps/{$this->id}/equipe", ['area' => 'medicina', 'assinatura' => 'externa', 'nome' => 'MEDICA-FIXTURE', 'registro' => 'CRM-SC 00000'])->assertCreated();
});

it('UC-FBP-13 · o PDF segue o formulário; rascunho sai como documento de apoio; o vazio sai em branco', function () {
    $d = app(LaudoBiopsicossocialDocumento::class)->montar(LaudoBiopsicossocial::query()->findOrFail($this->id), $this->b98);
    expect($d['pontuacao']['total'])->toBe(550)
        ->and($d['pontuacao']['por_dominio']['3'])->toBe(25)
        ->and($d['finalizado'])->toBeFalse()
        ->and(array_column($d['areas'], 'codigo'))->toBe(['medicina', 'enfermagem', 'psicologia', 'servico_social', 'fisioterapia_to']);

    $html = view('fisio::pdf.laudo-biopsicossocial', $d)->render();
    expect($html)->toContain('Carteira de Identificação da Pessoa com Fibromialgia')
        ->and($html)->toContain('Documento de apoio, não finalizado')
        ->and($html)->toContain('Pontuação total dos oito componentes:</b> 550')
        ->and($html)->toContain('UBS-FIXTURE')
        ->and($html)->toContain('TEXTO-AMBIENTAL')
        ->and($html)->toContain('MEDICA-FIXTURE')
        ->and($html)->toContain('Deliberação 500/CIB/2026')
        ->and($html)->toContain('Área: Serviço Social<br>Conselho/registro nº ____________')
        // Conclusão em branco: nenhum ☒ ao lado de Sim/Não/SIM/NÃO.
        ->and(substr_count($html, '☒ Sim') + substr_count($html, '☒ Não') + substr_count($html, '☒ SIM') + substr_count($html, '☒ NÃO'))->toBe(0);
});

it('UC-FBP-14 · a rota baixa o PDF e grava uma linha no activity_log só com ids; outra empresa 404, sem log', function () {
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();
    F::como($this, $this->usuarioComPermissoes([F::PERM_ACESSO], $this->seededTenant()));
    $r = $this->get("/fisio/laudos-bps/{$this->id}/pdf")->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');
    $log = DB::table('activity_log')->where('log_name', 'fisio')->where('description', 'laudo_bps_exportado')->where('subject_id', $this->p->id)->sole();
    $props = json_decode((string) $log->properties, true);
    expect($props['laudo_bps_id'])->toBe($this->id)->and($props['total'])->toBe(550)->and($props['finalizado'])->toBeFalse()
        ->and(str_contains((string) $log->properties, (string) $contato->name))->toBeFalse()
        ->and(str_contains((string) $log->properties, 'MEDICA-FIXTURE'))->toBeFalse()
        ->and(str_contains((string) $log->properties, 'TEXTO-AMBIENTAL'))->toBeFalse();

    F::como($this, $this->u99);
    $this->get("/fisio/laudos-bps/{$this->id}/pdf")->assertNotFound();
    expect(DB::table('activity_log')->where('description', 'laudo_bps_exportado')->where('subject_id', $this->p->id)->count())->toBe(1);
});
