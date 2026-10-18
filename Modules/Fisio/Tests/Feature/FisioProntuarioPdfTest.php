<?php

declare(strict_types=1);
// @covers-us US-FISIO-005
// Cobre UC-FPS-19 e UC-FPS-20 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Services\ProntuarioExportacao;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Exportação do prontuário em PDF. Duas camadas, como o resto da suíte:
 *
 * - CONTEÚDO: o serviço `ProntuarioExportacao` monta o que vai para o PDF; o teste lê esse
 *   conjunto (ordem, carimbo, adendo apontando o original) e o HTML que a view renderiza com
 *   ele. Ler o PDF binário exigiria um extrator de texto (dependência nova) — o HTML é a mesma
 *   fonte que o dompdf recebe.
 * - HTTP: a rota devolve `application/pdf` com o binário (`%PDF-`), 403 sem `fisio.access`,
 *   404 para paciente de outra clínica, e cada exportação grava UMA linha no activity_log sem
 *   PII nas propriedades.
 *
 * Os registros entram pelas rotas reais (RegistrosController) ou, para o telemonitoramento e o
 * registro não assinado (que a tela não cria), por fixture com `withoutGlobalScopes` — estado
 * inicial, não o que se mede. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['activity_log'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::pacote([$this->b98, $this->b99]);
    $this->p = F::paciente($this->b98, $this->u98->id, ['main_complaint' => 'Lombalgia mecânica', 'goals' => ['voltar a correr 5 km'], 'condition_flags' => ['osteoporose']]);
    F::como($this, $this->u98);
});

afterEach(fn () => Carbon::setTestNow());

/** Assina um registro pela rota real em um instante fixo do relógio do servidor. */
function fppAssinar($t, int $pacienteId, array $payload, string $quando): int
{
    Carbon::setTestNow($quando);

    return (int) $t->postJson("/fisio/pacientes/{$pacienteId}/registros", $payload + ['crefito' => F::CREFITO])->assertCreated()->json('id');
}

function fppRegistros($t): array
{
    $p = $t->p;
    $aval = fppAssinar($t, $p->id, ['kind' => 'avaliacao', 'body' => [
        'queixa_principal' => 'Dor lombar há 3 meses', 'historia_doenca' => 'Início após mudança de emprego',
        'exame_fisico' => 'Flexão limitada', 'diagnostico_cinesiologico' => 'Disfunção do controle motor lombopélvico',
        'objetivos' => ['reduzir dor'], 'plano_tratamento' => 'Cinesioterapia 3x/semana',
    ]], '2026-10-01 09:00:00');
    $evo = fppAssinar($t, $p->id, ['kind' => 'evolucao', 'body' => ['conduta' => 'Ponte glútea 3x12', 'resposta_paciente' => 'Sem dor']], '2026-10-03 10:15:00');
    $adendo = fppAssinar($t, $p->id, ['kind' => 'adendo', 'amends_id' => $evo, 'body' => ['texto' => 'Foram 3x10, não 3x12']], '2026-10-04 08:30:00');

    // Telemonitoramento: só o portal cria. Estado inicial por fixture.
    // SUPERADMIN: fixture de estado inicial; o escopo é provado nos asserts.
    $tele = Record::withoutGlobalScopes()->create([
        'business_id' => $t->b98, 'patient_id' => $p->id, 'kind' => Record::KIND_TELEMONITORAMENTO,
        'body' => ['pain' => 3, 'effort' => 'adequado'], 'modality' => 'assincrona', 'channel' => 'portal',
        'signed_by' => $t->u98->id, 'signed_crefito' => F::CREFITO, 'signed_at' => '2026-10-02 18:00:00',
    ])->id;

    // Registro sem assinatura (rascunho): não pertence ao prontuário exportado.
    // SUPERADMIN: fixture de estado inicial.
    $rascunho = Record::withoutGlobalScopes()->create([
        'business_id' => $t->b98, 'patient_id' => $p->id, 'kind' => Record::KIND_EVOLUCAO,
        'body' => ['conduta' => 'RASCUNHO-NAO-ASSINADO', 'resposta_paciente' => '-'],
    ])->id;

    Carbon::setTestNow('2026-10-18 14:00:00');

    return compact('aval', 'evo', 'adendo', 'tele', 'rascunho');
}

it('UC-FPS-19 · exporta todos os registros assinados em ordem cronológica, com carimbo; rascunho fica de fora', function () {
    $ids = fppRegistros($this);

    $dados = app(ProntuarioExportacao::class)->montar($this->p->fresh(), $this->b98);

    expect(array_column($dados['registros'], 'id'))->toBe([$ids['aval'], $ids['tele'], $ids['evo'], $ids['adendo']])
        ->and(array_column($dados['registros'], 'tipo'))->toBe(['Avaliação', 'Telemonitoramento', 'Evolução', 'Adendo']);

    $nomeU98 = trim($this->u98->first_name.' '.$this->u98->last_name);
    foreach ($dados['registros'] as $r) {
        expect($r['crefito'])->toBe(F::CREFITO)
            ->and($r['assinado_por'])->toBe($nomeU98);
    }
    expect($dados['registros'][0]['assinado_em'])->toBe('01/10/2026 09:00')
        ->and($dados['registros'][3]['corrige'])->toBe($ids['evo'])        // adendo aponta o original
        ->and($dados['registros'][2]['corrige'])->toBeNull();
});

it('UC-FPS-19 · o HTML do PDF traz identificação, perfil clínico, carimbos e o rodapé da empresa', function () {
    $ids = fppRegistros($this);
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();
    $empresa = (string) DB::table('business')->where('id', $this->b98)->value('name');

    $html = view('fisio::pdf.prontuario', app(ProntuarioExportacao::class)->montar($this->p->fresh(), $this->b98))->render();

    expect($html)->toContain(e($contato->name))
        ->and($html)->toContain('48999990000')
        ->and($html)->toContain('Lombalgia mecânica')
        ->and($html)->toContain('voltar a correr 5 km')
        ->and($html)->toContain('Osteoporose')
        ->and($html)->toContain('Ponte glútea 3x12')
        ->and($html)->toContain('Foram 3x10, não 3x12')
        ->and($html)->toContain("corrige o registro nº {$ids['evo']}")
        ->and($html)->toContain(F::CREFITO)
        ->and($html)->toContain('03/10/2026 10:15')
        ->and($html)->toContain(e($empresa))
        ->and($html)->toContain('18/10/2026 14:00');
    expect(str_contains($html, 'RASCUNHO-NAO-ASSINADO'))->toBeFalse();

    // A ordem no documento é a cronológica: a avaliação vem antes do adendo.
    expect(strpos($html, 'Dor lombar há 3 meses'))->toBeLessThan(strpos($html, 'Foram 3x10, não 3x12'));
});

it('UC-FPS-19 · a rota devolve o PDF para download, inclusive de paciente bloqueado', function () {
    fppRegistros($this);
    $this->p->blocked_at = now();
    $this->p->save();

    $r = $this->get("/fisio/pacientes/{$this->p->id}/prontuario.pdf")->assertOk();

    expect($r->headers->get('Content-Type'))->toContain('application/pdf')
        ->and((string) $r->headers->get('Content-Disposition'))->toContain('attachment')
        ->and(substr((string) $r->getContent(), 0, 5))->toBe('%PDF-');
});

it('UC-FPS-20 · cada exportação grava uma linha no activity_log, com autor e clínica, sem PII', function () {
    fppRegistros($this);
    $contato = DB::table('contacts')->where('id', $this->p->contact_id)->first();

    $this->get("/fisio/pacientes/{$this->p->id}/prontuario.pdf")->assertOk();
    $this->get("/fisio/pacientes/{$this->p->id}/prontuario.pdf")->assertOk();

    $logs = DB::table('activity_log')->where('log_name', 'fisio')->where('description', 'prontuario_exportado')
        ->where('subject_id', $this->p->id)->orderBy('id')->get();
    expect($logs)->toHaveCount(2);

    $log = $logs->first();
    $props = (string) $log->properties;
    expect((int) $log->causer_id)->toBe($this->u98->id)
        ->and((int) $log->business_id)->toBe($this->b98)
        ->and(json_decode($props, true)['registros'])->toBe(4)
        ->and(str_contains($props, (string) $contato->name))->toBeFalse()
        ->and(str_contains($props, '48999990000'))->toBeFalse()
        ->and(str_contains($props, 'Ponte glútea'))->toBeFalse();
});

it('UC-FPS-20 · paciente de outra clínica responde 404 e nada é registrado', function () {
    fppRegistros($this);
    F::como($this, $this->u99);

    $this->get("/fisio/pacientes/{$this->p->id}/prontuario.pdf")->assertNotFound();

    expect(DB::table('activity_log')->where('description', 'prontuario_exportado')->where('subject_id', $this->p->id)->count())->toBe(0);

    // Controle: a 99 exporta o próprio paciente (o 404 de cima é do escopo, não da rota).
    $p99 = F::paciente($this->b99, $this->u99->id);
    $this->get("/fisio/pacientes/{$p99->id}/prontuario.pdf")->assertOk();
});

it('UC-FPS-20 · sem fisio.access responde 403 e nada é registrado', function () {
    F::como($this, $this->usuarioComPermissoes([], $this->seededTenant()));

    $this->get("/fisio/pacientes/{$this->p->id}/prontuario.pdf")->assertForbidden();

    expect(DB::table('activity_log')->where('description', 'prontuario_exportado')->where('subject_id', $this->p->id)->count())->toBe(0);
});
