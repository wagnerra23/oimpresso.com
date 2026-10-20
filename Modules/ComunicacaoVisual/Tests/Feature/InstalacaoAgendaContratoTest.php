<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\ComunicacaoVisual\Http\Controllers\InstalacaoController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-007 · UC-FLX-21 — agenda da equipe de instalação: agenda equipe e veículo; bloqueia
 * se não houver janela.
 *
 * Âncora: memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-007 (aceite "equipe + veículo +
 * bloqueio") e o UC-FLX-21 do catálogo de fluxos. Contrato:
 * resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVINST-01..07.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): OS da 98 na etapa instalacao, 2 instaladores da 98,
 * 1 user da 99. A agenda de referência ocupa 21/10/2026 09:00–11:00 com o instalador A e o
 * veículo "Fiorino ABC1D23".
 * ⚠️ SKIP em SQLite ou sem a migration da agenda: leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasColumn('cv_instalacoes', 'os_id')) {
        $this->markTestSkipped('Requer MySQL com cv_instalacoes.os_id (migration da agenda).');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }
    Carbon::setTestNow('2026-10-20 08:00:00');

    $os = fn (int $biz) => DB::table('comvis_os')->insertGetId([
        'business_id' => $biz, 'numero' => 'IT-'.substr(uniqid(), -10), 'status_etapa' => 'instalacao',
        'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = $os($this->biz->id);
    $this->os2 = $os($this->biz->id);
    $this->osOutra = $os($this->outra->id);

    $this->a = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Alfa']);
    $this->b = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Beta']);
    $this->alheio = \App\User::factory()->create(['business_id' => $this->outra->id]);

    $this->coordenador = $this->usuarioComPermissoes(['comvis.os.update_status', 'comvis.os.view'], $this->biz);
});

afterEach(fn () => Carbon::setTestNow());

/** Chama o controller como a rota chamaria, com a sessão da empresa do usuário. */
function cvInst($t, \App\User $user, string $acao, array $dados = [], array $params = []): \Illuminate\Http\JsonResponse
{
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    app()->instance('request', Request::create('/comunicacao-visual/api/x', $acao === 'index' ? 'GET' : 'POST', $dados));

    try {
        return app()->call([app(InstalacaoController::class), $acao], $params);
    } catch (AuthorizationException) {
        return response()->json(['message' => 'forbidden'], 403);
    } catch (HttpException $e) {
        return response()->json(['message' => $e->getMessage()], $e->getStatusCode());
    } catch (ValidationException $e) {
        return response()->json(['message' => $e->getMessage(), 'errors' => $e->errors()], 422);
    }
}

function cvAgendar($t, int $os, string $inicio, string $fim, array $equipe, ?string $veiculo = null, ?\App\User $user = null)
{
    return cvInst($t, $user ?? $t->coordenador, 'agendar', [
        'inicio' => "2026-10-21 {$inicio}", 'fim' => "2026-10-21 {$fim}", 'equipe' => $equipe, 'veiculo' => $veiculo,
    ], ['os' => $os]);
}

/** Instalações nas OS deste teste (o banco do CT 100 persiste entre runs: nunca contar a tabela inteira). */
function cvInstCount($t): int
{
    return DB::table('cv_instalacoes')->where('business_id', $t->biz->id)->whereIn('os_id', [$t->os, $t->os2])->count();
}

it('UC-CVINST-01 · agenda equipe e veículo na OS e a agenda da janela lista a instalação', function () {
    $r = cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id, $this->b->id], 'Fiorino ABC1D23');
    expect($r->getStatusCode())->toBe(201);

    $linha = DB::table('cv_instalacoes')->where('id', $r->getData()->id)->first();
    expect((int) $linha->business_id)->toBe($this->biz->id)
        ->and((int) $linha->os_id)->toBe($this->os)
        ->and($linha->ordem_id)->toBeNull()
        ->and($linha->status)->toBe('agendada')
        ->and($linha->veiculo)->toBe('Fiorino ABC1D23')
        ->and(Carbon::parse($linha->data_agendada)->format('Y-m-d H:i'))->toBe('2026-10-21 09:00')
        ->and(Carbon::parse($linha->data_fim)->format('Y-m-d H:i'))->toBe('2026-10-21 11:00')
        ->and(array_map('intval', json_decode($linha->equipe_user_ids_json, true)))->toBe([$this->a->id, $this->b->id]);

    // Instalação da 99 no mesmo dia não aparece na agenda da 98.
    DB::table('cv_instalacoes')->insert(['business_id' => $this->outra->id, 'os_id' => $this->osOutra, 'status' => 'agendada',
        'data_agendada' => '2026-10-21 09:00:00', 'data_fim' => '2026-10-21 11:00:00', 'created_at' => now(), 'updated_at' => now()]);

    $lista = cvInst($this, $this->coordenador, 'index', ['de' => '2026-10-21', 'ate' => '2026-10-21'])->getData(true)['data'];
    expect($lista)->toHaveCount(1)
        ->and($lista[0]['os_id'])->toBe($this->os)
        ->and($lista[0]['veiculo'])->toBe('Fiorino ABC1D23')
        ->and(array_column($lista[0]['equipe'], 'nome'))->toBe(['Instalador Alfa', 'Instalador Beta'])
        ->and(DB::table('comvis_os')->where('id', $this->os)->value('numero'))->toBe($lista[0]['os_numero']);

    expect(cvInst($this, $this->coordenador, 'index', ['de' => '2026-10-01', 'ate' => '2027-03-01'])->getStatusCode())->toBe(422);
});

it('UC-CVINST-02 · alguém da equipe já ocupado na janela bloqueia, dizendo com quem e quando', function () {
    cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id], 'Fiorino ABC1D23');
    $antes = cvInstCount($this);

    $r = cvAgendar($this, $this->os2, '10:00', '12:00', [$this->b->id, $this->a->id], 'Strada XYZ9K88');

    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData()->message)->toContain('Instalador Alfa')
        ->and($r->getData()->message)->toContain('21/10/2026 09:00 a 11:00')
        ->and(cvInstCount($this))->toBe($antes);
});

it('UC-CVINST-03 · o mesmo veículo na janela bloqueia, mesmo escrito de outro jeito', function () {
    cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id], 'Fiorino ABC1D23');
    $antes = cvInstCount($this);

    $r = cvAgendar($this, $this->os2, '10:30', '12:00', [$this->b->id], ' fiorino abc-1d23 ');

    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData()->message)->toContain('veículo Fiorino ABC1D23')
        ->and(cvInstCount($this))->toBe($antes);
});

it('UC-CVINST-04 · horário encostado e instalação cancelada não bloqueiam', function () {
    cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id], 'Fiorino ABC1D23');

    // Começa exatamente quando a outra termina, com a mesma pessoa e o mesmo veículo.
    expect(cvAgendar($this, $this->os2, '11:00', '13:00', [$this->a->id], 'Fiorino ABC1D23')->getStatusCode())->toBe(201)
        ->and(cvAgendar($this, $this->os2, '07:00', '09:00', [$this->a->id], 'Fiorino ABC1D23')->getStatusCode())->toBe(201);

    // Cancelada não ocupa a agenda.
    DB::table('cv_instalacoes')->where('business_id', $this->biz->id)->where('os_id', $this->os)->update(['status' => 'cancelada']);
    expect(cvAgendar($this, $this->os2, '09:30', '10:30', [$this->a->id], 'Fiorino ABC1D23')->getStatusCode())->toBe(201);
});

it('UC-CVINST-05 · equipe de outra empresa é recusada e a agenda da outra empresa nunca conflita', function () {
    $antes = cvInstCount($this);
    $r = cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id, $this->alheio->id], 'Fiorino ABC1D23');
    expect($r->getStatusCode())->toBe(422)
        ->and($r->getData()->message)->toContain('desta empresa')
        ->and(cvInstCount($this))->toBe($antes);

    // A 99 ocupa o mesmo veículo e o mesmo id de pessoa na mesma janela: a 98 agenda assim mesmo.
    DB::table('cv_instalacoes')->insert(['business_id' => $this->outra->id, 'os_id' => $this->osOutra, 'status' => 'agendada',
        'equipe_user_ids_json' => json_encode([$this->a->id]), 'veiculo' => 'Fiorino ABC1D23',
        'data_agendada' => '2026-10-21 09:00:00', 'data_fim' => '2026-10-21 11:00:00', 'created_at' => now(), 'updated_at' => now()]);
    expect(cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id], 'Fiorino ABC1D23')->getStatusCode())->toBe(201);
});

it('UC-CVINST-06 · OS de outra empresa devolve 404 e nada é agendado', function () {
    $r = cvAgendar($this, $this->osOutra, '09:00', '11:00', [$this->a->id], 'Fiorino ABC1D23');

    expect($r->getStatusCode())->toBe(404)
        ->and(DB::table('cv_instalacoes')->where('os_id', $this->osOutra)->count())->toBe(0);
});

it('UC-CVINST-07 · sem permissão não agenda nem lista', function () {
    $soVer = $this->usuarioComPermissoes(['comvis.os.view'], $this->biz);
    $nada = $this->usuarioComPermissoes([], $this->biz);

    expect(cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id], null, $soVer)->getStatusCode())->toBe(403)
        ->and(cvInstCount($this))->toBe(0)
        ->and(cvInst($this, $nada, 'index', ['de' => '2026-10-21', 'ate' => '2026-10-21'])->getStatusCode())->toBe(403)
        ->and(cvInst($this, $soVer, 'index', ['de' => '2026-10-21', 'ate' => '2026-10-21'])->getStatusCode())->toBe(200)
        ->and(cvAgendar($this, $this->os, '09:00', '11:00', [$this->a->id])->getStatusCode())->toBe(201);
});
