<?php

declare(strict_types=1);
// @covers-us US-FISIO-062
// Cobre UC-FRA-01..05 (Fisio/Relatorio/Associacao.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Relatório agregado da associação (US-FISIO-062): só contagem e média, grupo com menos de 5
 * pessoas sai "<5". A fixture grava só o estado (pacientes, contato, dados sociais, agenda,
 * check-ins, questionários); os números saem do serviço pela rota real. Tenant 98 contra 99.
 * ⚠️ SKIP em SQLite (LC-13).
 *
 * Período fixo de outubro de 2026 (fora do "hoje"), para não depender do relógio.
 */
const FRA_DE = '2026-10-01';
const FRA_ATE = '2026-10-31';

beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_patient_social', 'fisio_appointments', 'fisio_appointment_types', 'fisio_questionnaires', 'fisio_settings'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    F::pacote([$this->b98, $this->b99]);
    $this->rel = $this->usuarioComPermissoes([F::PERM_ACESSO, 'fisio.relatorio'], $this->seededTenant());
    $this->tipo98 = (int) DB::table('fisio_appointment_types')->insertGetId(['business_id' => $this->b98, 'name' => 'Sessão', 'created_at' => now(), 'updated_at' => now()]);
    F::como($this, $this->rel);
});

/** Paciente atendido em outubro: contato com nascimento e cidade, dados sociais, presença na agenda e FIQR 1ª/última. */
function fraPaciente(object $t, int $biz, array $c = []): Patient
{
    $p = F::paciente($biz, $t->rel->id, ['contact_id' => F::contato($biz, $t->rel->id, $c['nome'] ?? 'Pessoa Relatorio')]);
    DB::table('contacts')->where('id', $p->contact_id)->update(['dob' => $c['dob'] ?? '1980-05-01', 'city' => $c['city'] ?? 'Tubarão', 'state' => 'SC']);
    DB::table('fisio_patient_social')->insert(['business_id' => $biz, 'patient_id' => $p->id, 'sexo' => $c['sexo'] ?? 'feminino',
        'situacao_trabalho' => $c['trabalho'] ?? 'afastado_inss', 'renda_familiar' => '1_a_2_sm', 'cns' => '123456789010000',
        'created_at' => now(), 'updated_at' => now()]);
    if ($biz === $t->b98) {
        DB::table('fisio_appointments')->insert(['business_id' => $biz, 'patient_id' => $p->id, 'professional_id' => $t->rel->id,
            'type_id' => $t->tipo98, 'starts_at' => '2026-10-10 09:00:00', 'ends_at' => '2026-10-10 09:50:00', 'status' => $c['status'] ?? 'realizado',
            'created_at' => now(), 'updated_at' => now()]);
    }
    foreach (($c['sem_fiqr'] ?? false) ? [] : [['2026-10-02 10:00:00', $c['fiqr1'] ?? 60], ['2026-10-28 10:00:00', $c['fiqr2'] ?? 40]] as [$quando, $score]) {
        DB::table('fisio_questionnaires')->insert(['business_id' => $biz, 'patient_id' => $p->id, 'instrument' => 'fiqr',
            'answers' => '{}', 'score' => $score, 'answered_at' => $quando, 'created_at' => now(), 'updated_at' => now()]);
    }

    return $p;
}

function fraRelatorio(object $t): array
{
    return F::props($t, $t->rel, '/fisio/relatorio-associacao?de='.FRA_DE.'&ate='.FRA_ATE, 'Fisio/Relatorio/Associacao', 'relatorio');
}

function fraLinha(array $linhas, string $codigo): int|string|null
{
    foreach ($linhas as $l) {
        if ($l['codigo'] === $codigo) {
            return $l['n'];
        }
    }

    return null;
}

it('UC-FRA-01 · conta cada pessoa uma vez e calcula presença e a evolução média da 1ª para a última aplicação', function () {
    foreach ([[60, 40], [70, 50], [50, 40], [80, 60], [40, 40], [90, 70]] as $i => [$a, $b]) {
        fraPaciente($this, $this->b98, ['fiqr1' => $a, 'fiqr2' => $b, 'status' => $i === 5 ? 'faltou' : 'realizado']);
    }
    // Atendido também por check-in: não conta duas vezes.
    $primeiro = Patient::query()->orderBy('id')->first();
    F::checkin($primeiro, null, '2026-10-05', [1], 3);

    $r = fraRelatorio($this);
    $fiqr = collect($r['evolucao'])->firstWhere('codigo', 'fiqr');
    expect($r['atendidos'])->toBe(6)
        ->and($r['suprimido'])->toBeFalse()
        ->and($r['presenca'])->toBe(['agendados' => 6, 'presentes' => 5, 'faltas' => 1, 'taxa' => 83])
        ->and($fiqr['n'])->toBe(6)
        // Os números chegam pelas props Inertia (JSON): 65.0 vira 65 e volta int. O cast compara o valor, não o tipo do JSON.
        ->and((float) $fiqr['primeira'])->toBe(65.0)
        ->and((float) $fiqr['ultima'])->toBe(50.0)
        ->and((float) $fiqr['diferenca'])->toBe(-15.0)
        ->and(fraLinha($r['perfil']['faixa_etaria'], '40_49'))->toBe(6)
        ->and(fraLinha($r['perfil']['situacao_trabalho'], 'afastado_inss'))->toBe(6)
        ->and(fraLinha($r['perfil']['municipio'], 'Tubarão/SC'))->toBe(6);
});

it('UC-FRA-02 · grupo com menos de 5 sai "<5", sem média; período com menos de 5 atendidos mostra só o total', function () {
    // 6 atendidos: 2 homens aposentados de Laguna, 4 mulheres de Tubarão; só 3 responderam o FIQR duas vezes.
    foreach (range(1, 6) as $i) {
        fraPaciente($this, $this->b98, [
            ...($i <= 2 ? ['sexo' => 'masculino', 'city' => 'Laguna', 'trabalho' => 'aposentado'] : []),
            'sem_fiqr' => $i > 3,
        ]);
    }

    $r = fraRelatorio($this);
    $fiqr = collect($r['evolucao'])->firstWhere('codigo', 'fiqr');
    expect($r['atendidos'])->toBe(6)
        ->and(fraLinha($r['perfil']['sexo'], 'masculino'))->toBe('<5')
        ->and(fraLinha($r['perfil']['sexo'], 'feminino'))->toBe('<5')
        ->and(fraLinha($r['perfil']['situacao_trabalho'], 'aposentado'))->toBe('<5')
        // Nenhum município chega a 5: os dois somam em "Outros municípios", sem o nome.
        ->and(fraLinha($r['perfil']['municipio'], 'Laguna/SC'))->toBeNull()
        ->and(fraLinha($r['perfil']['municipio'], 'Tubarão/SC'))->toBeNull()
        ->and(fraLinha($r['perfil']['municipio'], '_outros'))->toBe(6)
        ->and($fiqr['n'])->toBe('<5')
        ->and($fiqr['primeira'])->toBeNull()
        ->and($fiqr['diferenca'])->toBeNull();

    // Nenhum atendimento no dia: total 0, nada mais.
    $vazio = F::props($this, $this->rel, '/fisio/relatorio-associacao?de=2026-10-01&ate=2026-10-01', 'Fisio/Relatorio/Associacao', 'relatorio');
    expect($vazio['atendidos'])->toBe(0)->and($vazio['suprimido'])->toBeTrue()->and($vazio['perfil'])->toBe([])->and($vazio['evolucao'])->toBe([]);

    // No dia 02/10 só os 3 do FIQR foram atendidos: só o total, como "<5".
    $poucos = F::props($this, $this->rel, '/fisio/relatorio-associacao?de=2026-10-02&ate=2026-10-02', 'Fisio/Relatorio/Associacao', 'relatorio');
    expect($poucos['atendidos'])->toBe('<5')->and($poucos['suprimido'])->toBeTrue()->and($poucos['presenca'])->toBeNull()->and($poucos['perfil'])->toBe([]);
});

it('UC-FRA-03 · sem fisio.relatorio 403 na tela e no PDF; a 99 não entra na contagem da 98 [T0]', function () {
    foreach (range(1, 5) as $_) {
        fraPaciente($this, $this->b98);
    }
    foreach (range(1, 3) as $_) {
        fraPaciente($this, $this->b99);
    }
    expect(fraRelatorio($this)['atendidos'])->toBe(5);

    $semPermissao = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    F::como($this, $semPermissao);
    $this->get('/fisio/relatorio-associacao')->assertForbidden();
    $this->get('/fisio/relatorio-associacao.pdf')->assertForbidden();
});

it('UC-FRA-04 · nada de paciente sai no relatório: sem nome, CNS ou ids', function () {
    foreach (range(1, 5) as $i) {
        fraPaciente($this, $this->b98, ['nome' => "Sigilosa Relatorio {$i}"]);
    }
    $json = json_encode(fraRelatorio($this));

    expect($json)->not->toContain('Sigilosa')->not->toContain('123456789010000')->not->toContain('patient_id');
});

it('UC-FRA-05 · o PDF sai com os números do período e uma linha no activity_log sem paciente', function () {
    foreach (range(1, 5) as $_) {
        fraPaciente($this, $this->b98);
    }
    $antes = DB::table('activity_log')->where('description', 'relatorio_associacao_exportado')->count();

    $r = $this->get('/fisio/relatorio-associacao.pdf?de='.FRA_DE.'&ate='.FRA_ATE)->assertOk();
    expect($r->headers->get('content-type'))->toContain('application/pdf')
        ->and(substr((string) $r->getContent(), 0, 4))->toBe('%PDF');

    $log = DB::table('activity_log')->where('description', 'relatorio_associacao_exportado')->orderByDesc('id')->first();
    expect(DB::table('activity_log')->where('description', 'relatorio_associacao_exportado')->count())->toBe($antes + 1)
        ->and(json_decode((string) $log->properties, true))->toBe(['business_id' => $this->b98, 'de' => FRA_DE, 'ate' => FRA_ATE])
        ->and($log->subject_id)->toBeNull();

    $this->get('/fisio/relatorio-associacao?de=2026-10-31&ate=2026-10-01')->assertSessionHasErrors('ate');
});
