<?php

declare(strict_types=1);

use App\Http\Middleware\HandleInertiaRequests;
use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Auditoria\Http\Middleware\AuditoriaLiberado;
use Spatie\Activitylog\Models\Activity;

/**
 * Quem pode abrir /auditoria e /auditoria/{id}, testado pela requisição HTTP.
 *
 * Âncora: SPEC US-AUDIT-010 (AuditoriaController + permissões), ADR 0127 §princípio 5 e a regra
 * do menu em DataController::modifyAdminMenu. Camadas 1 e 3 de proibicoes.md §Multi-tenant.
 *
 * O pacote é fixado por mock do ModuleUtil: no CI a tabela `system` não tem `superadmin_version`,
 * e o ModuleUtil real liberaria qualquer pacote. O 403 traz a mensagem da camada que recusou, e
 * cada recusa tem o par que passa com a mesma requisição.
 *
 * `Inertia::defer`: visita normal devolve só o shell e nem executa a consulta. O que carrega
 * dado é a visita parcial, então os casos de leitura mandam os headers reais do cliente Inertia,
 * inclusive `X-Requested-With`, que o browser sempre envia junto (proibicoes.md §5 2026-09-08).
 *
 * Tenants fictícios 98 × 99 (ADR 0358), nunca biz=4. Transação revertida: o banco do CT 100
 * persiste entre runs.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a stack de rotas exige schema MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('activity_log') || ! Schema::hasColumn('activity_log', 'business_id')) {
        $this->markTestSkipped('activity_log sem business_id — rode as migrations.');
    }

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
});

/** Fixa só o pacote Auditoria; qualquer outra consulta de pacote segue liberada. */
function audGatePacote(bool $tem): void
{
    $util = \Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(
        fn ($biz, $perm = null, $cb = null) => $perm === AuditoriaLiberado::PACOTE ? $tem : true
    );
    app()->instance(ModuleUtil::class, $util);
}

function audGateSessao(User $user, int $biz): array
{
    return ['user.business_id' => $biz, 'user' => ['business_id' => $biz, 'id' => $user->id]];
}

function audGateLinha(int $biz): Activity
{
    return Activity::create([
        'log_name'    => 'auditoria-acesso-gate-test',
        'description' => 'gate de acesso',
        'event'       => 'updated',
        'business_id' => $biz,
        'properties'  => ['old' => ['status' => 'a'], 'attributes' => ['status' => 'b']],
    ]);
}

/** Visita normal, como o browser ao abrir a URL. */
function audGateGet(User $user, int $biz, string $url)
{
    return test()->actingAs($user)->withSession(audGateSessao($user, $biz))->get($url);
}

/** Visita parcial: a única que executa o `Inertia::defer` da prop pedida. */
function audGateParcial(User $user, int $biz, string $url, string $componente, string $prop)
{
    return test()->actingAs($user)
        ->withSession(audGateSessao($user, $biz))
        ->withHeaders([
            'X-Requested-With'            => 'XMLHttpRequest',
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => (string) app(HandleInertiaRequests::class)->version(request()),
            'X-Inertia-Partial-Component' => $componente,
            'X-Inertia-Partial-Data'      => $prop,
        ])
        ->get($url);
}

function audGateMotivo($resposta): ?string
{
    return $resposta->exception?->getMessage();
}

it('UC-AUDI-04 · com o pacote e sem auditoria.view, /auditoria recusa com 403 pela permissão', function () {
    audGatePacote(true);
    $semPerm = $this->usuarioComPermissoes([], $this->b98);

    $tela = audGateGet($semPerm, (int) $this->b98->id, '/auditoria');
    $tela->assertStatus(403);
    expect(audGateMotivo($tela))->toBe(AuditoriaLiberado::SEM_PERMISSAO);

    // A visita parcial, que é a que carrega a lista, também não passa.
    $parcial = audGateParcial($semPerm, (int) $this->b98->id, '/auditoria', 'Auditoria/Index', 'activities');
    $parcial->assertStatus(403);
    expect($parcial->getContent())->not->toContain('auditoria-acesso-gate-test');
});

it('UC-AUDI-04 · com o pacote e auditoria.view, /auditoria abre e a lista carrega', function () {
    audGatePacote(true);
    $linha = audGateLinha((int) $this->b98->id);
    $comPerm = $this->usuarioComPermissoes([AuditoriaLiberado::PERMISSAO], $this->b98);

    audGateGet($comPerm, (int) $this->b98->id, '/auditoria')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auditoria/Index'));

    $parcial = audGateParcial($comPerm, (int) $this->b98->id, '/auditoria', 'Auditoria/Index', 'activities');
    $parcial->assertStatus(200);
    expect(collect($parcial->json('props.activities.data'))->pluck('id')->all())->toContain($linha->id);
});

it('UC-AUDI-04 · empresa sem o pacote recebe 403 mesmo com auditoria.view', function () {
    audGatePacote(false);
    $comPerm = $this->usuarioComPermissoes([AuditoriaLiberado::PERMISSAO], $this->b98);

    $tela = audGateGet($comPerm, (int) $this->b98->id, '/auditoria');
    $tela->assertStatus(403);
    expect(audGateMotivo($tela))->toBe(AuditoriaLiberado::SEM_PACOTE);
});

it('UC-AUDI-04 · superadmin abre sem o pacote e sem auditoria.view', function () {
    audGatePacote(false);
    $superadmin = $this->usuarioComPermissoes(['superadmin'], $this->b98);

    audGateGet($superadmin, (int) $this->b98->id, '/auditoria')
        ->assertStatus(200)
        ->assertInertia(fn (AssertableInertia $page) => $page->component('Auditoria/Index'));
});

it('UC-AUDD-03 · sem auditoria.view, /auditoria/{id} da própria empresa recusa com 403', function () {
    audGatePacote(true);
    $linha = audGateLinha((int) $this->b98->id);
    $semPerm = $this->usuarioComPermissoes([], $this->b98);
    $comPerm = $this->usuarioComPermissoes([AuditoriaLiberado::PERMISSAO], $this->b98);
    $url = "/auditoria/{$linha->id}";

    $recusada = audGateParcial($semPerm, (int) $this->b98->id, $url, 'Auditoria/Detail', 'activity');
    $recusada->assertStatus(403);
    expect(audGateMotivo($recusada))->toBe(AuditoriaLiberado::SEM_PERMISSAO);

    // Par: a mesma requisição, com a permissão, traz a linha.
    $aceita = audGateParcial($comPerm, (int) $this->b98->id, $url, 'Auditoria/Detail', 'activity');
    $aceita->assertStatus(200);
    expect($aceita->json('props.activity.id'))->toBe($linha->id);
});

it('UC-AUDD-11 · pela rota, linha da empresa 99 lida por usuário da 98 vem nula e sem dado nenhum', function () {
    audGatePacote(true);
    $linha99 = audGateLinha((int) $this->b99->id);
    $url = "/auditoria/{$linha99->id}";

    // Âncora: o id existe e a dona o abre pela mesma requisição.
    $dona = $this->usuarioComPermissoes([AuditoriaLiberado::PERMISSAO], $this->b99);
    $aberta = audGateParcial($dona, (int) $this->b99->id, $url, 'Auditoria/Detail', 'activity');
    $aberta->assertStatus(200);
    expect($aberta->json('props.activity.id'))->toBe($linha99->id);

    $outra = $this->usuarioComPermissoes([AuditoriaLiberado::PERMISSAO], $this->b98);
    $cruzada = audGateParcial($outra, (int) $this->b98->id, $url, 'Auditoria/Detail', 'activity');
    $cruzada->assertStatus(200);
    expect(array_key_exists('activity', $cruzada->json('props')))->toBeTrue();
    expect($cruzada->json('props.activity'))->toBeNull();
    expect($cruzada->getContent())->not->toContain('auditoria-acesso-gate-test');
});
