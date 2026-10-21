<?php
// @covers-us US-COMVIS-007

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\AgendaController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * Tela da agenda de instalação (US-COMVIS-007 · UC-FLX-21) — `GET /comunicacao-visual/agenda`.
 *
 * Âncora: SPEC US-COMVIS-007 DoD "Agenda calendário (semanal)" + UC-FLX-21 ("agenda equipe e veículo;
 * bloqueia se não houver janela"). Contrato: resources/js/Pages/ComunicacaoVisual/Agenda.casos.md
 * UC-CVAG-01..03. O bloqueio visível (UC-CVAG-04) é da tela e mora em tests/js/cv-agenda.test.tsx.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358): hoje = terça 20/10/2026, semana exibida 19–25/10.
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

    $os = fn (int $biz, string $etapa = 'instalacao') => DB::table('comvis_os')->insertGetId([
        'business_id' => $biz, 'numero' => 'AG-'.substr(uniqid(), -10), 'status_etapa' => $etapa,
        'valor_total' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->os = $os($this->biz->id);
    $this->osConcluida = $os($this->biz->id, 'concluida');
    $this->osOutra = $os($this->outra->id);

    $this->a = \App\User::factory()->create(['business_id' => $this->biz->id, 'first_name' => 'Instalador', 'last_name' => 'Gama']);
    $this->alheio = \App\User::factory()->create(['business_id' => $this->outra->id, 'first_name' => 'Alheio', 'last_name' => 'Noventa']);

    $inst = fn (int $biz, int $osId, string $de, string $ate, array $equipe, string $status = 'agendada') => DB::table('cv_instalacoes')->insertGetId([
        'business_id' => $biz, 'os_id' => $osId, 'status' => $status, 'veiculo' => 'Fiorino ABC1D23',
        'equipe_user_ids_json' => json_encode($equipe), 'data_agendada' => $de, 'data_fim' => $ate,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->naSemana = $inst($this->biz->id, $this->os, '2026-10-21 09:00:00', '2026-10-21 11:00:00', [$this->a->id]);
    $this->semanaSeguinte = $inst($this->biz->id, $this->os, '2026-10-28 09:00:00', '2026-10-28 11:00:00', [$this->a->id]);
    $this->cancelada = $inst($this->biz->id, $this->os, '2026-10-22 09:00:00', '2026-10-22 11:00:00', [$this->a->id], 'cancelada');
    $this->daOutra = $inst($this->outra->id, $this->osOutra, '2026-10-21 09:00:00', '2026-10-21 11:00:00', [$this->alheio->id]);
});

afterEach(fn () => Carbon::setTestNow());

/**
 * Abre a tela como a rota abriria e devolve o "page" Inertia (JSON). Com $parcial, pede só aquela prop —
 * é assim que o navegador busca uma prop deferida.
 */
function cvAgendaPagina($t, \App\User $user, array $query = [], ?string $parcial = null): array
{
    $t->actingAs($user);
    session(['user.business_id' => $user->business_id, 'business.id' => $user->business_id, 'user.id' => $user->id]);
    $request = Request::create('/comunicacao-visual/agenda', 'GET', $query);
    $request->headers->set('X-Inertia', 'true');
    if ($parcial !== null) {
        $request->headers->set('X-Inertia-Partial-Component', 'ComunicacaoVisual/Agenda');
        $request->headers->set('X-Inertia-Partial-Data', $parcial);
    }
    app()->instance('request', $request);

    try {
        $resposta = app()->call([app(AgendaController::class), 'index']);
    } catch (HttpException $e) {
        return ['status' => $e->getStatusCode()];
    }

    return ['status' => 200] + json_decode($resposta->toResponse($request)->getContent(), true);
}

it('UC-CVAG-01 · a semana lista só as instalações desta empresa que caem nela, sem as canceladas', function () {
    $user = $this->usuarioComPermissoes(['comvis.os.view'], $this->biz);

    $pagina = cvAgendaPagina($this, $user);
    expect($pagina['status'])->toBe(200)
        ->and($pagina['component'])->toBe('ComunicacaoVisual/Agenda')
        ->and($pagina['props']['semana'])->toBe('2026-10-19')
        // deferida: não vem no primeiro paint
        ->and(array_key_exists('instalacoes', $pagina['props']))->toBeFalse();

    $ids = array_column(cvAgendaPagina($this, $user, [], 'instalacoes')['props']['instalacoes'], 'id');
    expect($ids)->toContain($this->naSemana)
        ->and($ids)->not->toContain($this->semanaSeguinte)
        ->and($ids)->not->toContain($this->cancelada)
        ->and($ids)->not->toContain($this->daOutra);

    // Qualquer dia da semana seguinte abre a semana seguinte, que tem a outra instalação.
    $seguinte = cvAgendaPagina($this, $user, ['semana' => '2026-10-30'], 'instalacoes');
    expect(array_column($seguinte['props']['instalacoes'], 'id'))->toBe([$this->semanaSeguinte]);
    expect(cvAgendaPagina($this, $user, ['semana' => '2026-10-30'])['props']['semana'])->toBe('2026-10-26');
});

it('UC-CVAG-02 · equipe e OS do formulário são só desta empresa, e OS concluída fica fora', function () {
    $pagina = cvAgendaPagina($this, $this->usuarioComPermissoes(['comvis.os.update_status'], $this->biz));

    $equipe = array_column($pagina['props']['equipe'], 'id');
    $os = array_column($pagina['props']['os'], 'id');
    expect($equipe)->toContain($this->a->id)
        ->and($equipe)->not->toContain($this->alheio->id)
        ->and($os)->toContain($this->os)
        ->and($os)->not->toContain($this->osConcluida)
        ->and($os)->not->toContain($this->osOutra);
});

it('UC-CVAG-03 · sem permissão não abre; quem só vê abre sem poder agendar; quem atualiza a OS agenda', function () {
    $semNada = \App\User::factory()->create(['business_id' => $this->biz->id]);
    expect(cvAgendaPagina($this, $semNada)['status'])->toBe(403);

    $soVe = cvAgendaPagina($this, $this->usuarioComPermissoes(['comvis.os.view'], $this->biz));
    expect($soVe['status'])->toBe(200)->and($soVe['props']['podeAgendar'])->toBeFalse();

    $agenda = cvAgendaPagina($this, $this->usuarioComPermissoes(['comvis.os.update_status'], $this->biz));
    expect($agenda['status'])->toBe(200)->and($agenda['props']['podeAgendar'])->toBeTrue();
});
