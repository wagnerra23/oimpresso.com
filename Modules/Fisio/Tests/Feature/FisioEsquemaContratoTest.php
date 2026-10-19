<?php

declare(strict_types=1);
// @covers-us US-FISIO-003
// Cobre UC-FPI-02 (Fisio/Pacientes/Index.casos.md) e UC-FPS-04/UC-FPS-07 (Fisio/Pacientes/Show.casos.md) na camada de dados.

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fisio\Entities\Checkin;
use Modules\Fisio\Entities\Consent;
use Modules\Fisio\Entities\Exercise;
use Modules\Fisio\Entities\GameLedger;
use Modules\Fisio\Entities\Patient;
use Modules\Fisio\Entities\PortalToken;
use Modules\Fisio\Entities\Program;
use Modules\Fisio\Entities\ProgramItem;
use Modules\Fisio\Entities\Protocol;
use Modules\Fisio\Entities\Questionnaire;
use Modules\Fisio\Entities\Record;
use Modules\Fisio\Exceptions\DadoClinicoImutavelException;
use Modules\Fisio\Tests\Support\FisioFixtures as F;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato da CAMADA DE DADOS do Fisio (US-FISIO-003, T-02 do tasks.md): migrations, escopo
 * multi-tenant dos models e append-only. Sem rota nem tela — essas são das US seguintes e
 * têm os contratos HTTP próprios (FisioMultiTenantTest, FisioProntuarioTest...).
 *
 * DoD da US: "isolamento entre 2 businesses; UPDATE em registro assinado falha".
 *
 * Tenant 98 contra 99 (ADR 0358). ⚠️ SKIP em SQLite: schema UltimatePOS + triggers MySQL (LC-13).
 */
beforeEach(function () {
    if ($motivo = F::motivoDeSkip(['fisio_protocols', 'fisio_questionnaires'])) {
        $this->markTestSkipped($motivo);
    }
    F::configurar();
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;
    $this->u98 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededTenant());
    $this->u99 = $this->usuarioComPermissoes([F::PERM_ACESSO, F::PERM_EDITAR], $this->seededSupportClientTenant());
});

/** Uma linha em CADA tabela com business_id, para o business dado. @return array<string, int> */
function fesSemear(int $biz, int $userId): array
{
    $ex = F::exercicio(null);
    $p = F::paciente($biz, $userId);
    $prog = F::programa($p, [$ex], $userId);
    $item = F::itens($prog)[0];
    $chk = F::checkin($p, $prog, now()->subDay()->toDateString(), [$item], 2);
    $consent = F::consentir($p, 'whatsapp');

    // SUPERADMIN: fixture grava em duas empresas; o escopo é provado nos asserts.
    $reg = Record::withoutGlobalScopes()->create([
        'business_id' => $biz, 'patient_id' => $p->id, 'kind' => 'evolucao',
        'body' => ['conduta' => 'Ponte glútea 3x12', 'resposta_paciente' => 'Sem dor'],
        'signed_by' => $userId, 'signed_crefito' => F::CREFITO, 'signed_at' => now(),
    ]);
    // SUPERADMIN: idem.
    $xp = GameLedger::withoutGlobalScopes()->create(['business_id' => $biz, 'patient_id' => $p->id, 'date' => now()->toDateString(), 'reason' => 'checkin', 'xp' => 5]);
    // SUPERADMIN: idem.
    $prot = Protocol::withoutGlobalScopes()->create(['business_id' => $biz, 'name' => 'Lombar fase 1', 'region' => 'lombar', 'items' => [['exercise_id' => $ex->id]]]);
    // SUPERADMIN: idem.
    $q = Questionnaire::withoutGlobalScopes()->create(['business_id' => $biz, 'patient_id' => $p->id, 'instrument' => 'eva', 'answers' => ['dor' => 4], 'score' => 4, 'answered_at' => now()]);
    // SUPERADMIN: idem.
    $tok = PortalToken::withoutGlobalScopes()->create([
        'business_id' => $biz, 'patient_id' => $p->id, 'token_hash' => hash('sha256', uniqid('', true)),
        'pin_hash' => password_hash('1234', PASSWORD_DEFAULT), 'expires_at' => now()->addDays(30),
    ]);

    return [
        Patient::class => $p->id, Program::class => $prog->id, ProgramItem::class => $item, Checkin::class => $chk->id,
        Consent::class => $consent, Record::class => $reg->id, GameLedger::class => $xp->id, Protocol::class => $prot->id,
        Questionnaire::class => $q->id, PortalToken::class => $tok->id,
    ];
}

it('as 11 tabelas existem e todas têm business_id indexado (nulo só na biblioteca)', function () {
    $tabelas = ['fisio_patients', 'fisio_records', 'fisio_consents', 'fisio_exercises', 'fisio_protocols', 'fisio_programs',
        'fisio_program_items', 'fisio_checkins', 'fisio_game_ledger', 'fisio_questionnaires', 'fisio_portal_tokens'];

    foreach ($tabelas as $t) {
        $col = collect(Schema::getColumns($t))->firstWhere('name', 'business_id');
        expect($col)->not->toBeNull("{$t} sem business_id")
            ->and($col['nullable'])->toBe(in_array($t, ['fisio_exercises', 'fisio_protocols'], true), "{$t}.business_id: nulo só na biblioteca e nos protocolos-modelo globais");
        $indexado = collect(Schema::getIndexes($t))->contains(fn ($i) => ($i['columns'][0] ?? null) === 'business_id');
        expect($indexado)->toBeTrue("{$t}.business_id sem índice");
    }
});

it('UC-FPI-02 · sob a sessão da 98, cada model com business_id enxerga só a 98; a 99 enxerga só a 99', function () {
    $d98 = fesSemear($this->b98, $this->u98->id);
    $d99 = fesSemear($this->b99, $this->u99->id);

    F::como($this, $this->u98);
    foreach ($d98 as $classe => $id) {
        expect($classe::query()->find($id))->not->toBeNull("{$classe} da 98 sumiu para a própria 98")
            ->and($classe::query()->find($d99[$classe]))->toBeNull("{$classe} da 99 vazou para a 98")
            ->and($classe::query()->pluck('business_id')->map('intval')->unique()->values()->all())->toBe([$this->b98]);
    }

    // Discriminante: a mesma consulta como 99 troca o filtro, não esvazia.
    F::como($this, $this->u99);
    foreach ($d99 as $classe => $id) {
        expect($classe::query()->find($id))->not->toBeNull()->and($classe::query()->find($d98[$classe]))->toBeNull();
    }

    // Controle negativo: sem escopo as duas linhas existem — o filtro de cima é real.
    foreach ($d98 as $classe => $id) {
        // SUPERADMIN: controle negativo do teste de isolamento.
        expect($classe::withoutGlobalScopes()->whereIn('id', [$id, $d99[$classe]])->count())->toBe(2);
    }
});

it('biblioteca: a sessão vê o global e o próprio, nunca o de outra empresa', function () {
    $global = F::exercicio(null);
    $de98 = F::exercicio($this->b98);
    $de99 = F::exercicio($this->b99);

    F::como($this, $this->u98);
    $ids = Exercise::query()->whereIn('id', [$global->id, $de98->id, $de99->id])->pluck('id')->map('intval')->sort()->values()->all();

    expect($ids)->toBe(collect([$global->id, $de98->id])->sort()->values()->all());
});

it('protocolo-modelo global: a clínica vê o modelo e o dela; a cópia não muda quando o modelo muda', function () {
    // SUPERADMIN: semeia o modelo global e o protocolo de outra empresa sem sessão.
    $modelo = Protocol::withoutGlobalScopes()->create(['business_id' => null, 'name' => 'Fibromialgia', 'region' => 'global', 'items' => [['ordem' => 1]]]);
    $copia98 = Protocol::withoutGlobalScopes()->create(['business_id' => $this->b98, 'origin_protocol_id' => $modelo->id, 'name' => 'Fibromialgia', 'region' => 'global', 'items' => [['ordem' => 1]]]);
    $de99 = Protocol::withoutGlobalScopes()->create(['business_id' => $this->b99, 'name' => 'Ombro', 'region' => 'ombro', 'items' => []]);

    F::como($this, $this->u98);
    $ids = Protocol::query()->whereIn('id', [$modelo->id, $copia98->id, $de99->id])->pluck('id')->map('intval')->sort()->values()->all();
    expect($ids)->toBe(collect([$modelo->id, $copia98->id])->sort()->values()->all());

    // SUPERADMIN: a responsável técnica revisa o modelo global.
    Protocol::withoutGlobalScopes()->whereKey($modelo->id)->update(['items' => json_encode([['ordem' => 1], ['ordem' => 2]])]);
    expect(Protocol::query()->findOrFail($copia98->id)->items)->toBe([['ordem' => 1]]);
});

it('item do programa herda o business do programa; business divergente é recusado', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $prog = F::programa($p, [F::exercicio(null)], $this->u98->id);

    // SUPERADMIN: verificação da coluna copiada.
    expect((int) ProgramItem::withoutGlobalScopes()->whereKey(F::itens($prog)[0])->value('business_id'))->toBe($this->b98);

    expect(fn () => ProgramItem::query()->create(['program_id' => $prog->id, 'exercise_id' => F::exercicio(null)->id, 'business_id' => $this->b99, 'position' => 2]))
        ->toThrow(LogicException::class);
    expect(F::itens($prog))->toHaveCount(1);
});
it('UC-FPS-04 · registro assinado: UPDATE e DELETE falham no banco (trigger) e no Eloquent; o original fica intacto', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
    $reg = Record::query()->create([
        'business_id' => $this->b98, 'patient_id' => $p->id, 'kind' => 'evolucao',
        'body' => ['conduta' => 'Agachamento 3x15', 'resposta_paciente' => 'Ok'],
        'signed_by' => $this->u98->id, 'signed_crefito' => F::CREFITO, 'signed_at' => now(),
    ]);

    expect(fn () => DB::table('fisio_records')->where('id', $reg->id)->update(['body' => json_encode(['conduta' => 'mexi'])]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_records')->where('id', $reg->id)->update(['signed_at' => null]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_records')->where('id', $reg->id)->delete())->toThrow(QueryException::class);

    $reg->body = ['conduta' => 'outra'];
    expect(fn () => $reg->save())->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => Record::query()->findOrFail($reg->id)->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(Record::query()->findOrFail($reg->id)->body['conduta'])->toBe('Agachamento 3x15');

    // Correção é adendo apontando o original.
    $adendo = Record::query()->create([
        'business_id' => $this->b98, 'patient_id' => $p->id, 'kind' => 'adendo', 'amends_id' => $reg->id,
        'body' => ['texto' => 'Correção: 3x10.'], 'signed_by' => $this->u98->id, 'signed_crefito' => F::CREFITO, 'signed_at' => now(),
    ]);
    expect($reg->adendos()->pluck('id')->all())->toBe([$adendo->id]);

    // Controle: registro AINDA NÃO assinado aceita UPDATE (o trigger é sobre o assinado, não geral)...
    $rascunho = Record::query()->create(['business_id' => $this->b98, 'patient_id' => $p->id, 'kind' => 'evolucao', 'body' => ['conduta' => 'x']]);
    expect(DB::table('fisio_records')->where('id', $rascunho->id)->update(['body' => json_encode(['conduta' => 'y'])]))->toBe(1);
    // ...e a tabela mutável do módulo aceita UPDATE.
    expect(DB::table('fisio_patients')->where('id', $p->id)->update(['main_complaint' => 'nova']))->toBe(1);
});

it('ledger do jogo: append-only no banco e no Eloquent; razão fora do enum é recusada', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    F::como($this, $this->u98);
    $l = GameLedger::query()->create(['business_id' => $this->b98, 'patient_id' => $p->id, 'date' => now()->toDateString(), 'reason' => 'exercicio_concluido', 'xp' => 10]);

    expect(fn () => DB::table('fisio_game_ledger')->where('id', $l->id)->update(['xp' => 999]))->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_game_ledger')->where('id', $l->id)->delete())->toThrow(QueryException::class)
        ->and(fn () => DB::table('fisio_game_ledger')->insert(['business_id' => $this->b98, 'patient_id' => $p->id, 'date' => now()->toDateString(), 'reason' => 'bonus_por_exceder', 'xp' => 50]))
        ->toThrow(QueryException::class);

    $l->xp = 999;
    expect(fn () => $l->save())->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => $l->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(F::xp($p))->toBe(10);
});

it('UC-FPS-07 · paciente, programa e check-in não são apagados pelo Eloquent (guarda de 20 anos)', function () {
    $p = F::paciente($this->b98, $this->u98->id);
    $prog = F::programa($p, [F::exercicio(null)], $this->u98->id);
    $chk = F::checkin($p, $prog, now()->subDay()->toDateString(), F::itens($prog), 3);
    F::como($this, $this->u98);

    expect(fn () => Patient::query()->findOrFail($p->id)->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => Program::query()->findOrFail($prog->id)->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(fn () => Checkin::query()->findOrFail($chk->id)->delete())->toThrow(DadoClinicoImutavelException::class)
        ->and(DB::table('fisio_patients')->where('id', $p->id)->exists())->toBeTrue()
        ->and(DB::table('fisio_checkins')->where('id', $chk->id)->exists())->toBeTrue();

    // Controle: item de programa é removível (o editor remove exercício do programa).
    expect(ProgramItem::query()->findOrFail(F::itens($prog)[0])->delete())->toBeTrue();
});

it('únicos: um paciente por contact no business; um check-in por paciente e data', function () {
    $p = F::paciente($this->b98, $this->u98->id);

    expect(fn () => F::paciente($this->b98, $this->u98->id, ['contact_id' => $p->contact_id]))->toThrow(QueryException::class);

    $prog = F::programa($p, [F::exercicio(null)], $this->u98->id);
    $dia = now()->subDay()->toDateString();
    F::checkin($p, $prog, $dia, [], 2);
    expect(fn () => F::checkin($p, $prog, $dia, [], 5))->toThrow(QueryException::class)
        ->and(DB::table('fisio_checkins')->where('patient_id', $p->id)->count())->toBe(1);

    // Controle: outro dia do mesmo paciente não colide (o único é paciente + data, não paciente).
    $outroDia = F::checkin($p, $prog, now()->subDays(2)->toDateString(), [], 1);
    expect($outroDia->id)->toBeInt();
});