<?php

declare(strict_types=1);
// Etapa 07 P-07 · exportação para a universidade e travamento (UC-FES-12..15, Fisio/Pesquisa/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Services\Pesquisa\EstudoService;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Exportação sem identificação (§4.4): o que sai é medido no CSV devolvido pela rota real; a
 * fixture só grava estudo, check-ins e respostas. Tenant 98 contra 99. ⚠️ SKIP em SQLite (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_studies', 'fisio_study_participants', 'fisio_study_moments'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-11-01 09:00:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->pesq = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR, 'fisio.pesquisa'], $this->seededTenant());
    $this->clin = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $svc = app(EstudoService::class);
    $dados = ['title' => 'Fibro', 'cep_opinion_number' => '1', 'protocol_version' => 'p1', 'tcle_version' => 't1', 'starts_on' => '2026-11-01'];
    $this->estudo = $svc->criar($this->b98, $dados, [
        ['label' => 'Entrada', 'day_offset' => 0, 'window_days' => 3, 'instruments' => ['eva', 'rmdq']],
    ], null);
    $this->e99 = $svc->criar($this->b99, $dados, [], null);
    $contato = F::contato($this->b98, $this->clin->id, 'Maria Sigilosa Exportacao', '48911112222');
    $this->p = F::paciente($this->b98, $this->clin->id, ['contact_id' => $contato]);
    $this->prog = F::programa($this->p, [F::exercicio(null, 'Ponte'), F::exercicio(null, 'Agachamento')], $this->clin->id);
    $this->part = $svc->incluir($this->estudo, $this->p, null, null);
    DB::table('fisio_questionnaires')->insert(['business_id' => $this->b98, 'patient_id' => $this->p->id, 'instrument' => 'eva',
        'answers' => '{}', 'score' => 6, 'answered_at' => '2026-11-02 10:00:00', 'created_at' => now(), 'updated_at' => now()]);
    $itens = F::itens($this->prog);
    F::checkin($this->p, $this->prog, '2026-11-01', [$itens[0]], 0);   // dor zero é zero
    F::checkin($this->p, $this->prog, '2026-11-03', [$itens[0], $itens[1]], 7); // 02/11 sem check-in
    Carbon::setTestNow('2026-11-03 18:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function fexBaixar($t, int $estudo, string $arquivo): array
{
    $r = $t->get("/fisio/pesquisa/estudos/{$estudo}/exportar/{$arquivo}")->assertOk();
    expect($r->headers->get('content-type'))->toContain('text/csv');

    return array_map(fn ($l) => str_getcsv($l, ',', '"', ''), array_values(array_filter(explode("\n", $r->streamedContent() ?: $r->getContent()))));
}

it('UC-FES-12 · momentos em formato longo, com o código e sem identificação; não respondido sai NA', function () {
    F::como($this, $this->pesq);
    $linhas = fexBaixar($this, $this->estudo->id, 'momentos');
    $bruto = implode("\n", array_map(fn ($l) => implode(',', $l), $linhas));

    expect($linhas[0])->toBe(['participante', 'momento', 'dia_alvo', 'instrumento', 'versao_instrumento', 'valor', 'dia_resposta'])
        ->and($linhas[1])->toBe([$this->part->code, 'Entrada', '0', 'eva', $linhas[1][4], '6', '1'])
        ->and($linhas[2])->toBe([$this->part->code, 'Entrada', '0', 'rmdq', 'NA', 'NA', 'NA'])
        ->and($bruto)->not->toContain('Sigilosa')->not->toContain('48911112222')->not->toContain('2026-11');
});

it('UC-FES-13 · série diária: dia sem check-in é NA, dor zero é 0', function () {
    F::como($this, $this->pesq);
    $linhas = fexBaixar($this, $this->estudo->id, 'diario');

    expect($linhas[0])->toBe(['participante', 'dia_estudo', 'dor', 'crise', 'itens_feitos', 'itens_prescritos'])
        ->and(array_slice($linhas, 1))->toBe([
            [$this->part->code, '0', '0', '0', '1', '2'],
            [$this->part->code, '1', 'NA', 'NA', 'NA', 'NA'],
            [$this->part->code, '2', '7', '0', '2', '2'],
        ]);
});

it('UC-FES-14 · só o pesquisador exporta, cada download vai ao log, outra clínica dá 404 [T0]', function () {
    F::como($this, $this->clin);
    $this->get("/fisio/pesquisa/estudos/{$this->estudo->id}/exportar/momentos")->assertForbidden();

    F::como($this, $this->pesq);
    $antes = DB::table('activity_log')->where('description', 'estudo_exportado')->count();
    fexBaixar($this, $this->estudo->id, 'dicionario');
    $this->get("/fisio/pesquisa/estudos/{$this->e99->id}/exportar/momentos")->assertNotFound();
    $log = DB::table('activity_log')->where('description', 'estudo_exportado')->orderByDesc('id')->first();

    expect(DB::table('activity_log')->where('description', 'estudo_exportado')->count())->toBe($antes + 1)
        ->and(json_decode((string) $log->properties, true))->toMatchArray(['study_id' => $this->estudo->id, 'arquivo' => 'dicionario']);
});

it('UC-FES-15 · travar exige motivo; depois, mudar exige justificativa e a exportação ignora o que veio depois', function () {
    F::como($this, $this->pesq);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/travar", [])->assertStatus(422);
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/travar", ['motivo' => 'Fim da coleta'])->assertOk();
    $this->postJson("/fisio/pesquisa/estudos/{$this->estudo->id}/travar", ['motivo' => 'de novo'])->assertStatus(422);

    $url = "/fisio/pesquisa/estudos/{$this->estudo->id}/participantes/{$this->part->id}/saida";
    $this->postJson($url, ['tipo' => 'outro', 'motivo' => 'mudou de cidade'])->assertStatus(422);
    $this->postJson($url, ['tipo' => 'outro', 'motivo' => 'mudou de cidade', 'justificativa' => 'Correção pedida pela pesquisadora'])->assertOk();
    expect(DB::table('activity_log')->where('description', 'estudo_alterado_apos_travamento')->where('subject_id', $this->estudo->id)->count())->toBe(1);

    // Resposta dentro da janela, mas gravada depois do travamento: não entra.
    Carbon::setTestNow('2026-11-04 09:00:00');
    DB::table('fisio_questionnaires')->insert(['business_id' => $this->b98, 'patient_id' => $this->p->id, 'instrument' => 'rmdq',
        'answers' => '{}', 'score' => 12, 'answered_at' => '2026-11-03 20:00:00', 'created_at' => now(), 'updated_at' => now()]);
    $momentos = fexBaixar($this, $this->estudo->id, 'momentos');
    expect($momentos[2])->toBe([$this->part->code, 'Entrada', '0', 'rmdq', 'NA', 'NA', 'NA']);
});
