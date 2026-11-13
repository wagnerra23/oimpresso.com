<?php

declare(strict_types=1);
// @covers-us US-ASSOC-001

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Associacoes\Entities\Associacao;
use Modules\Associacoes\Entities\Representante;
use Modules\Associacoes\Services\AssociacoesAcesso;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Regra de acesso do módulo (ADR 0451 D4), que as ondas 3 a 6 usam em toda consulta:
 * pacote da empresa → `associacoes.access` → alcance (rede inteira com `associacoes.rede`,
 * senão só as associações em que o usuário é representante). Tenant 98 × 99 (ADR 0358).
 *
 * ⚠️ SKIP em SQLite: schema UltimatePOS (LC-13). A lane é verticais-pest (MySQL).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('assoc_representantes')) {
        $this->markTestSkipped('Exige MySQL com as migrations do Modules/Associacoes (ADR 0358).');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    // Pacote ligado só para a 98 (idioma do ClienteVeiculosModuleGateTest: Mockery partial no container).
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')
        ->andReturnUsing(fn ($biz, $perm) => $perm === AssociacoesAcesso::PACOTE && (int) $biz === $this->b98);
    app()->instance(ModuleUtil::class, $fake);

    $this->acesso = app(AssociacoesAcesso::class);
});

function assocAcessoComo($t, $user): void
{
    $t->actingAs($user);
    session(['user.business_id' => (int) $user->business_id]);
}

function assocAcessoAssociacao(int $biz, string $nome): Associacao
{
    // SUPERADMIN: fixture grava na empresa do teste; o alcance é provado nos asserts.
    return Associacao::withoutGlobalScopes()->create(['business_id' => $biz, 'nome' => $nome.' '.uniqid(), 'cidade' => 'Cidade Fixture']);
}

it('US-ASSOC-001 · sem o pacote da empresa ninguém entra, nem com a permissão', function () {
    $u98 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededTenant());
    $u99 = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededSupportClientTenant());
    $semPermissao = $this->usuarioComPermissoes([], $this->seededTenant());

    expect($this->acesso->liberado($u98, $this->b98))->toBeTrue()
        ->and($this->acesso->liberado($u99, $this->b99))->toBeFalse()
        ->and($this->acesso->liberado($semPermissao, $this->b98))->toBeFalse()
        ->and($this->acesso->liberado(null, $this->b98))->toBeFalse();
});

it('US-ASSOC-001 · representante vê só a associação dele; a diretoria vê a rede', function () {
    $a = assocAcessoAssociacao($this->b98, 'Associação A');
    $b = assocAcessoAssociacao($this->b98, 'Associação B');

    $rep = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededTenant());
    $diretoria = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO, AssociacoesAcesso::PERMISSAO_REDE], $this->seededTenant());
    // SUPERADMIN: fixture do vínculo.
    Representante::withoutGlobalScopes()->create(['business_id' => $this->b98, 'associacao_id' => $a->id, 'user_id' => $rep->id]);

    assocAcessoComo($this, $rep);
    $ids = $this->acesso->restringir(Associacao::query(), $rep, 'id')->pluck('id')->map('intval')->all();
    expect($ids)->toBe([(int) $a->id])
        ->and($this->acesso->associacoesVisiveis($rep))->toBe([(int) $a->id]);

    // Discriminante: a diretoria enxerga as duas (alcance null = rede inteira).
    assocAcessoComo($this, $diretoria);
    expect($this->acesso->associacoesVisiveis($diretoria))->toBeNull()
        ->and($this->acesso->restringir(Associacao::query(), $diretoria, 'id')->pluck('id')->all())
        ->toContain($a->id)->toContain($b->id);
});

it('US-ASSOC-001 · usuário com acesso e sem vínculo não vê associação nenhuma', function () {
    assocAcessoAssociacao($this->b98, 'Associação C');
    $semVinculo = $this->usuarioComPermissoes([AssociacoesAcesso::PERMISSAO], $this->seededTenant());

    assocAcessoComo($this, $semVinculo);
    expect($this->acesso->associacoesVisiveis($semVinculo))->toBe([])
        ->and($this->acesso->restringir(Associacao::query(), $semVinculo, 'id')->count())->toBe(0);
});
