<?php

declare(strict_types=1);

use App\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Auditoria\Services\AuditEntryService;
use Spatie\Activitylog\Models\Activity;

/**
 * Contrato das telas Auditoria/Index + Auditoria/Detail (casos.md ao lado dos .tsx).
 *
 * UCs: UC-AUDI-01..03, UC-AUDI-10..11 (Index.casos.md) · UC-AUDD-01..02 (Detail.casos.md).
 * Âncora: SDD-auditoria-v1.0 §6 (CU-AUD-01/02) + charters + SPEC US-AUDIT-010.
 *
 * Exercita o MESMO serviço que o AuditoriaController chama (list/find/normalizeFilters)
 * — a fronteira Tier 0 (business_id) mora nele. Tenants fictícios 98 × 99 (ADR 0358);
 * nunca biz=4. Tudo dentro de transação revertida: activity_log é append-only e o
 * banco do CT 100 persiste entre runs.
 */

uses(DatabaseTransactions::class);

const AUD_TELA_BIZ = 98;
const AUD_TELA_OUTRO = 99;
const AUD_TELA_LOG = 'auditoria-telas-contrato-test';

beforeEach(function () {
    try {
        $ok = Schema::hasTable('activity_log') && Schema::hasColumn('activity_log', 'business_id');
    } catch (\Throwable $e) {
        $ok = false;
    }
    if (! $ok) {
        $this->markTestSkipped('activity_log sem business_id — rode as migrations (2021_03_16_add_business_id).');
    }

    $this->svc = app(AuditEntryService::class);
});

function audTelaActivity(int $biz, string $event, array $properties = []): Activity
{
    return Activity::create([
        'log_name'    => AUD_TELA_LOG,
        'description' => "contrato tela auditoria {$event}",
        'event'       => $event,
        'business_id' => $biz,
        'properties'  => $properties,
    ]);
}

it('UC-AUDI-01 · lista só o próprio business, paginada, mais recente primeiro', function () {
    $a = audTelaActivity(AUD_TELA_BIZ, 'created');
    $b = audTelaActivity(AUD_TELA_OUTRO, 'created');
    $c = audTelaActivity(AUD_TELA_BIZ, 'updated');

    $page = $this->svc->list(AUD_TELA_BIZ);
    $ids = collect($page->items())->pluck('id')->all();

    expect($page)->toBeInstanceOf(LengthAwarePaginator::class);
    // âncora positiva: as minhas estão lá (não é verde por lista vazia)
    expect(in_array($a->id, $ids, true))->toBeTrue();
    expect(in_array($c->id, $ids, true))->toBeTrue();
    // ordem: a mais nova (c) vem antes da mais antiga (a)
    expect(array_search($c->id, $ids, true))->toBeLessThan(array_search($a->id, $ids, true));
    // Tier 0: a do outro business não vaza
    expect(in_array($b->id, $ids, true))->toBeFalse();
    foreach ($page->items() as $item) {
        expect((int) $item->business_id)->toBe(AUD_TELA_BIZ);
    }
});

it('UC-AUDI-02 · filtro restringe e whitelist descarta chave estranha', function () {
    $criado = audTelaActivity(AUD_TELA_BIZ, 'created');
    $alterado = audTelaActivity(AUD_TELA_BIZ, 'updated');

    $filtros = $this->svc->normalizeFilters([
        'event'       => 'updated',
        'business_id' => AUD_TELA_OUTRO,
        'foo'         => 'bar',
    ]);
    expect($filtros)->toBe(['event' => 'updated']);

    $ids = collect($this->svc->list(AUD_TELA_BIZ, $filtros)->items())->pluck('id')->all();

    expect(in_array($alterado->id, $ids, true))->toBeTrue();
    expect(in_array($criado->id, $ids, true))->toBeFalse();
});

it('UC-AUDI-03 · redirect 301 legado preserva os filtros', function () {
    $this->get('/reports/activity-log?event=updated')
        ->assertStatus(301)
        ->assertRedirect('/auditoria?event=updated');
});

it('UC-AUDD-01 · detalhe traz old e attributes do próprio business', function () {
    $act = audTelaActivity(AUD_TELA_BIZ, 'updated', [
        'old'        => ['status' => 'rascunho'],
        'attributes' => ['status' => 'final'],
    ]);

    $achado = $this->svc->find(AUD_TELA_BIZ, $act->id);

    expect($achado->id)->toBe($act->id);
    expect($achado->properties['old']['status'])->toBe('rascunho');
    expect($achado->properties['attributes']['status'])->toBe('final');
});

it('UC-AUDD-02 · cross-tenant não abre', function () {
    $act = audTelaActivity(AUD_TELA_BIZ, 'updated', [
        'old'        => ['status' => 'a'],
        'attributes' => ['status' => 'b'],
    ]);

    // âncora positiva: o dono abre (prova que o id existe e o 404 abaixo é do escopo)
    expect($this->svc->find(AUD_TELA_BIZ, $act->id)->id)->toBe($act->id);

    expect(fn () => $this->svc->find(AUD_TELA_OUTRO, $act->id))
        ->toThrow(ModelNotFoundException::class);
});

function audTelaAutor(int $biz, string $primeiroNome): User
{
    return User::factory()->create([
        'business_id' => $biz,
        'first_name'  => $primeiroNome,
        'last_name'   => 'Fixture',
    ]);
}

function audTelaPor(int $biz, ?User $autor, string $kind = 'user'): Activity
{
    return Activity::create([
        'log_name'    => AUD_TELA_LOG,
        'description' => 'contrato coluna por',
        'event'       => 'updated',
        'business_id' => $biz,
        'causer_type' => $autor ? User::class : null,
        'causer_id'   => $autor?->id,
        'causer_kind' => $kind,
    ]);
}

function audTelaLinha(LengthAwarePaginator $pagina, Activity $activity): Activity
{
    return collect($pagina->items())->firstWhere('id', $activity->id);
}

it('UC-AUDI-10 · nome do autor na linha, numa só consulta e sem expor o usuário', function () {
    $biz = (int) $this->seededTenant()->id;
    $ana = audTelaAutor($biz, 'Ana');
    $bia = audTelaAutor($biz, 'Bia');
    $desligada = audTelaAutor($biz, 'Cleo');
    $desligada->delete();

    $daAna = audTelaPor($biz, $ana);
    $daBia = audTelaPor($biz, $bia);
    $daCleo = audTelaPor($biz, $desligada);

    DB::enableQueryLog();
    $pagina = $this->svc->list($biz);
    $consultasDeUsuario = collect(DB::getQueryLog())
        ->filter(fn (array $q) => str_contains($q['query'], 'from `users`'))
        ->count();
    DB::disableQueryLog();

    expect(audTelaLinha($pagina, $daAna)->causer_label)->toBe('Ana Fixture');
    expect(audTelaLinha($pagina, $daBia)->causer_label)->toBe('Bia Fixture');
    expect(audTelaLinha($pagina, $daCleo)->causer_label)->toBe('Cleo Fixture');
    expect($consultasDeUsuario)->toBe(1);

    $payload = audTelaLinha($pagina, $daAna)->toArray();
    expect(array_key_exists('causer', $payload))->toBeFalse();
    expect(json_encode($payload))->not->toContain((string) $ana->email);
});

it('UC-AUDI-11 · sem usuário, o rótulo diz de onde veio a ação', function () {
    $biz = (int) $this->seededTenant()->id;
    $autor = audTelaAutor($biz, 'Dani');

    $rotina = audTelaPor($biz, null, 'system');
    $ia = audTelaPor($biz, null, 'agent');
    $api = audTelaPor($biz, null, 'api');
    $iaEmNomeDe = audTelaPor($biz, $autor, 'agent');

    $pagina = $this->svc->list($biz);

    expect(audTelaLinha($pagina, $rotina)->causer_label)->toBe('Sistema');
    expect(audTelaLinha($pagina, $ia)->causer_label)->toBe('IA');
    expect(audTelaLinha($pagina, $api)->causer_label)->toBe('API');
    expect(audTelaLinha($pagina, $iaEmNomeDe)->causer_label)->toBe('IA · Dani Fixture');
});

it('UC-AUDI-11 · autor de outro business aparece só pelo id', function () {
    $biz = (int) $this->seededTenant()->id;
    $vizinho = audTelaAutor((int) $this->seededSupportClientTenant()->id, 'Eva');
    $daCasa = audTelaAutor($biz, 'Fabi');

    $alheio = audTelaPor($biz, $vizinho);
    $proprio = audTelaPor($biz, $daCasa);

    $pagina = $this->svc->list($biz);

    // Controle: o mesmo caminho resolve o nome de quem é da casa.
    expect(audTelaLinha($pagina, $proprio)->causer_label)->toBe('Fabi Fixture');
    expect(audTelaLinha($pagina, $alheio)->causer_label)->toBe('Usuário #'.$vizinho->id);
});
