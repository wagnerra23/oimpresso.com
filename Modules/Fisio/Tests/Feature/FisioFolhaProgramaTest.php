<?php

declare(strict_types=1);
// @covers-us US-FISIO-006
// Cobre UC-FPS-37 e UC-FPS-38 (Fisio/Pacientes/Show.casos.md) — B-12 da etapa 01-biblioteca.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Fisio\Entities\PortalToken;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Services\FolhaPrograma;
use Modules\Fisio\Services\PortalTokenService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Folha para imprimir do programa. Mesmas duas camadas do FisioProntuarioPdfTest:
 *
 * - CONTEÚDO: o HTML que a view renderiza com o que o serviço `FolhaPrograma` monta — é a mesma
 *   fonte que o dompdf recebe (ler o PDF binário pediria um extrator de texto, dependência nova).
 * - HTTP: a rota devolve o binário (`%PDF-`), 403 sem `fisio.access`, 404 para programa de outra
 *   clínica, uma linha no activity_log por folha, e o link do portal sai intacto.
 *
 * Os textos clínicos do paciente (queixa, prontuário) são marcadores únicos: o assert é que
 * nenhum deles aparece no papel. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['activity_log', 'arquivos', 'arquivos_audit_log', 'arquivos_dedupe'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Storage::fake(config('arquivos.disk_default', 'local'));
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);

    $this->p = F::paciente($this->b98, $this->u98->id, ['main_complaint' => 'QUEIXA-NAO-VAI-AO-PAPEL', 'condition_flags' => ['osteoporose']]);
    $this->ex = F::exercicio($this->b98, 'Ponte glútea', ['default_hold_s' => 5, 'steps' => ['Deite de barriga para cima', 'Eleve o quadril']]);
    $this->prog = F::programa($this->p, [$this->ex], $this->u98->id, ['crisis_items' => ['Respiração diafragmática']]);

    // SUPERADMIN: fixture de estado inicial (prontuário assinado do paciente); o escopo é provado nos asserts.
    Record::withoutGlobalScopes()->create([
        'business_id' => $this->b98, 'patient_id' => $this->p->id, 'kind' => Record::KIND_EVOLUCAO,
        'body' => ['conduta' => 'PRONTUARIO-NAO-VAI-AO-PAPEL', 'resposta_paciente' => 'ok'],
        'signed_by' => $this->u98->id, 'signed_crefito' => F::CREFITO, 'signed_at' => now(),
    ]);
    F::como($this, $this->u98);
});

function ffpHtml($t): string
{
    return view('fisio::pdf.folha-programa', app(FolhaPrograma::class)->montar($t->prog->fresh(), $t->b98))->render();
}

it('UC-FPS-37 · a folha traz o programa: nome, dose por extenso, passos, cuidado, parada e crise', function () {
    $html = ffpHtml($this);
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();

    expect($html)->toContain(e($contato->name))
        ->and($html)->toContain(e($this->ex->name))
        ->and($html)->toContain('3 séries × 12 repetições · segure 5 s · 1 vez ao dia')
        ->and($html)->toContain('Eleve o quadril')
        ->and($html)->toContain('Não prenda a respiração')
        ->and($html)->toContain('Pare os exercícios de hoje')
        ->and($html)->toContain('Respiração diafragmática');
});

it('UC-FPS-37 · a foto própria do exercício vai embutida no papel', function () {
    $this->postJson("/fisio/biblioteca/exercicios/{$this->ex->id}/midia", [
        'midia' => UploadedFile::fake()->image('ponte.jpg', 64, 64),
        'declaro_sem_paciente' => '1',
    ])->assertCreated();

    expect(ffpHtml($this))->toContain('data:image/jpeg;base64,');
});

it('UC-FPS-37 · a rota devolve o PDF do paciente da clínica, com rastro sem PII', function () {
    $r = $this->get("/fisio/programas/{$this->prog->id}/folha.pdf")->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');

    $log = DB::table('activity_log')->where('log_name', 'fisio')->where('description', 'programa_impresso')
        ->where('subject_id', $this->p->id)->get();
    expect($log)->toHaveCount(1);
    $props = json_decode((string) $log->first()->properties, true);
    expect($props['program_id'])->toBe($this->prog->id)
        ->and($props['itens'])->toBe(1)
        ->and((int) $log->first()->business_id)->toBe($this->b98);
});

it('UC-FPS-38 · o papel não leva queixa, prontuário nem marcador de condição', function () {
    $html = ffpHtml($this);

    expect(str_contains($html, 'QUEIXA-NAO-VAI-AO-PAPEL'))->toBeFalse()
        ->and(str_contains($html, 'PRONTUARIO-NAO-VAI-AO-PAPEL'))->toBeFalse()
        ->and(str_contains($html, 'Osteoporose'))->toBeFalse()
        ->and(str_contains($html, 'osteoporose'))->toBeFalse();
});

it('UC-FPS-38 · imprimir não revoga nem emite link do portal', function () {
    $emitido = F::emitirPortal($this->p->fresh());
    $antes = PortalToken::withoutGlobalScopes()->where('patient_id', $this->p->id)->count(); // SUPERADMIN: contagem de verificação

    $this->get("/fisio/programas/{$this->prog->id}/folha.pdf")->assertOk();

    expect(app(PortalTokenService::class)->validar($emitido['token'])?->id)->toBe($this->p->id)
        ->and(PortalToken::withoutGlobalScopes()->where('patient_id', $this->p->id)->count())->toBe($antes) // SUPERADMIN: idem
        ->and(ffpHtml($this))->toContain('use no celular o link e o PIN que a clínica enviou');
});

it('UC-FPS-38 · programa de outra clínica responde 404 e nada é registrado', function () {
    F::como($this, $this->u99);

    $this->get("/fisio/programas/{$this->prog->id}/folha.pdf")->assertNotFound();
    expect(DB::table('activity_log')->where('description', 'programa_impresso')->where('subject_id', $this->p->id)->count())->toBe(0);

    // Controle: a 99 imprime o programa do próprio paciente (o 404 de cima é do escopo, não da rota).
    $p99 = F::paciente($this->b99, $this->u99->id);
    $prog99 = F::programa($p99, [F::exercicio($this->b99)], $this->u99->id);
    $this->get("/fisio/programas/{$prog99->id}/folha.pdf")->assertOk();
});

it('UC-FPS-38 · sem fisio.access responde 403 e nada é registrado', function () {
    F::como($this, $this->usuarioComPermissoes([], $this->seededTenant()));

    $this->get("/fisio/programas/{$this->prog->id}/folha.pdf")->assertForbidden();
    expect(DB::table('activity_log')->where('description', 'programa_impresso')->where('subject_id', $this->p->id)->count())->toBe(0);
});
