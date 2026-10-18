<?php

declare(strict_types=1);
// Cobre UC-FPS-03, UC-FPS-04, UC-FPS-05 (Fisio/Pacientes/Show.casos.md).

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Prontuário (Res. COFFITO 414/2012): cada registro é carimbado com nome, CREFITO, data e
 * hora; registro assinado NÃO aceita UPDATE nem DELETE; correção é um novo registro
 * `kind='adendo'` apontando `amends_id` para o original, que fica intacto.
 *
 * Duas camadas de prova, como o fundo do PCP (ProducaoFundoContratoTest): a HTTP (rota não
 * oferece edição) e a de banco (trigger recusa UPDATE/DELETE em registro assinado). Limpeza
 * é o rollback da transação (trigger não dispara em rollback).
 *
 * Tenant 98 contra 99. ⚠️ SKIP em SQLite: o append-only é trigger MySQL (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip()) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    Carbon::setTestNow('2026-10-14 15:30:00');
    $this->b98 = (int) $this->seededTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::pacote([$this->b98]);
    F::como($this, $this->u98);
    $this->p = F::paciente($this->b98, $this->u98->id);
});

afterEach(fn () => Carbon::setTestNow());

function fprAvaliacao(array $mudar = []): array
{
    return array_replace_recursive([
        'kind' => 'avaliacao',
        'crefito' => F::CREFITO,
        'body' => [
            'queixa_principal' => 'Dor lombar há 3 meses',
            'historia_doenca' => 'Início após mudança de emprego',
            'exame_fisico' => 'Flexão limitada, Lasègue negativo',
            'diagnostico_cinesiologico' => 'Disfunção do controle motor lombopélvico',
            'objetivos' => ['reduzir dor', 'voltar a correr'],
            'plano_tratamento' => 'Cinesioterapia 3x/semana por 6 semanas',
        ],
    ], $mudar);
}

it('UC-FPS-03 · avaliação assinada nasce carimbada com nome, CREFITO e data/hora do servidor', function () {
    $r = $this->postJson("/fisio/pacientes/{$this->p->id}/registros", fprAvaliacao(['signed_at' => '2020-01-01 00:00:00', 'signed_by' => 999]))
        ->assertCreated();

    $reg = Record::query()->findOrFail($r->json('id'));
    expect($reg->kind)->toBe('avaliacao')
        ->and((int) $reg->business_id)->toBe($this->b98)
        ->and((int) $reg->signed_by)->toBe($this->u98->id)            // do usuário logado, não do corpo
        ->and($reg->signed_crefito)->toBe(F::CREFITO)
        ->and($reg->signed_at->toDateTimeString())->toBe('2026-10-14 15:30:00') // relógio do servidor, não do corpo
        ->and($reg->amends_id)->toBeNull();
});

dataset('campos minimos da avaliacao', [
    'queixa_principal', 'historia_doenca', 'exame_fisico', 'diagnostico_cinesiologico', 'objetivos', 'plano_tratamento',
]);

it('UC-FPS-03 · avaliação sem campo mínimo da Res. 414 é recusada e nada é gravado', function (string $campo) {
    $payload = fprAvaliacao();
    unset($payload['body'][$campo]);

    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", $payload)->assertStatus(422)->assertJsonValidationErrors("body.{$campo}");

    expect(Record::query()->where('patient_id', $this->p->id)->count())->toBe(0);
})->with('campos minimos da avaliacao');

it('UC-FPS-03 · sem CREFITO não assina — evolução e avaliação recusadas', function () {
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", fprAvaliacao(['crefito' => '']))->assertStatus(422)->assertJsonValidationErrors('crefito');
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", ['kind' => 'evolucao', 'body' => ['conduta' => 'x', 'resposta_paciente' => 'y']])
        ->assertStatus(422)->assertJsonValidationErrors('crefito');

    expect(Record::query()->where('patient_id', $this->p->id)->count())->toBe(0);
});

it('UC-FPS-03 · kind telemonitoramento não é criável pela tela do profissional (só o portal gera)', function () {
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", ['kind' => 'telemonitoramento', 'crefito' => F::CREFITO, 'body' => ['x' => 1]])
        ->assertStatus(422)->assertJsonValidationErrors('kind');
});

it('UC-FPS-04 · registro assinado: UPDATE e DELETE falham no banco; a rota de edição não existe', function () {
    $id = $this->postJson("/fisio/pacientes/{$this->p->id}/registros", [
        'kind' => 'evolucao', 'crefito' => F::CREFITO, 'body' => ['conduta' => 'Ponte glútea 3x12', 'resposta_paciente' => 'Sem dor'],
    ])->assertCreated()->json('id');

    expect(fn () => DB::table('fisio_records')->where('id', $id)->update(['body' => json_encode(['conduta' => 'mexi'])]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_records')->where('id', $id)->update(['signed_at' => now()->subYear()]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_records')->where('id', $id)->delete())->toThrow(QueryException::class);

    // Pelo Eloquent também (o model não pode contornar — save() de registro assinado lança).
    // `toThrow(Throwable::class)` NÃO servia: Throwable é interface, `class_exists` dá false e o
    // Pest passa a tratar o argumento como TRECHO DA MENSAGEM (Mixins/Expectation.php::toThrow) —
    // o assert exigia "Throwable" no texto do erro. A classe concreta da camada do model é mais
    // estrita que a intenção original (qualquer lançamento), não mais frouxa.
    $reg = Record::query()->findOrFail($id);
    $reg->body = ['conduta' => 'outra'];
    expect(fn () => $reg->save())->toThrow(DadoClinicoImutavelException::class);

    // HTTP: não há PUT/PATCH/DELETE de registro.
    $this->putJson("/fisio/pacientes/{$this->p->id}/registros/{$id}", ['body' => ['conduta' => 'x']])->assertStatus(405);
    $this->deleteJson("/fisio/pacientes/{$this->p->id}/registros/{$id}")->assertStatus(405);

    expect(DB::table('fisio_records')->where('id', $id)->value('body'))->toContain('Ponte glútea 3x12');

    // Controle: a tabela mutável do módulo aceita update (o bloqueio não é geral).
    expect(DB::table('fisio_patients')->where('id', $this->p->id)->update(['main_complaint' => 'nova']))->toBe(1);
});

it('UC-FPS-05 · correção é adendo com amends_id; o original fica intacto e a timeline mostra os dois', function () {
    $orig = $this->postJson("/fisio/pacientes/{$this->p->id}/registros", [
        'kind' => 'evolucao', 'crefito' => F::CREFITO, 'body' => ['conduta' => 'Agachamento 3x15', 'resposta_paciente' => 'Ok'],
    ])->json('id');

    Carbon::setTestNow('2026-10-14 16:10:00');
    $adendo = $this->postJson("/fisio/pacientes/{$this->p->id}/registros", [
        'kind' => 'adendo', 'amends_id' => $orig, 'crefito' => F::CREFITO,
        'body' => ['texto' => 'Correção: foram 3x10, não 3x15.'],
    ])->assertCreated()->json('id');

    $a = Record::query()->findOrFail($adendo);
    expect($a->kind)->toBe('adendo')->and((int) $a->amends_id)->toBe((int) $orig)
        ->and($a->signed_at->toDateTimeString())->toBe('2026-10-14 16:10:00')
        ->and(Record::query()->findOrFail($orig)->body['conduta'])->toBe('Agachamento 3x15');

    $timeline = collect(F::props($this, $this->u98, "/fisio/pacientes/{$this->p->id}", 'Fisio/Pacientes/Show', 'prontuario'));
    expect($timeline->pluck('id')->map('intval')->all())->toContain((int) $orig)->toContain((int) $adendo)
        ->and($timeline->firstWhere('id', $adendo)['amends_id'])->toBe((int) $orig);
});

it('UC-FPS-05 · adendo sem amends_id, ou apontando registro de outro paciente/business, é recusado', function () {
    $outroPaciente = F::paciente($this->b98, $this->u98->id);
    $deOutro = $this->postJson("/fisio/pacientes/{$outroPaciente->id}/registros", [
        'kind' => 'evolucao', 'crefito' => F::CREFITO, 'body' => ['conduta' => 'x', 'resposta_paciente' => 'y'],
    ])->json('id');

    $u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
    F::trocarPacote((int) $u99->business_id, true);
    $p99 = F::paciente((int) $u99->business_id, $u99->id);
    F::como($this, $u99);
    $da99 = $this->postJson("/fisio/pacientes/{$p99->id}/registros", [
        'kind' => 'evolucao', 'crefito' => F::CREFITO, 'body' => ['conduta' => 'x', 'resposta_paciente' => 'y'],
    ])->json('id');

    F::como($this, $this->u98);
    $base = ['kind' => 'adendo', 'crefito' => F::CREFITO, 'body' => ['texto' => 'corrige']];
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", $base)->assertStatus(422)->assertJsonValidationErrors('amends_id');
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", $base + ['amends_id' => $deOutro])->assertStatus(422)->assertJsonValidationErrors('amends_id');
    $this->postJson("/fisio/pacientes/{$this->p->id}/registros", $base + ['amends_id' => $da99])->assertStatus(422)->assertJsonValidationErrors('amends_id');

    expect(Record::query()->where('patient_id', $this->p->id)->count())->toBe(0);
});
