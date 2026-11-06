<?php

declare(strict_types=1);

use App\Utils\ModuleUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Services\FiscalPacote;

uses(Tests\TestCase::class);

/**
 * Pacote `fiscal_module` + CheckUserLogin nas rotas operacionais do /fiscal.
 *
 * ÂNCORA (regra escrita, não o código): memory/proibicoes.md §Multi-tenant, Camada 1 —
 * habilitar módulo por empresa é compra de pacote no Superadmin; o código checa
 * `ModuleUtil::hasThePermissionInSubscription($business_id, 'X_module', ...)`. E a stack
 * canônica de rota web inclui `CheckUserLogin` (proibicoes.md §Sempre fazer).
 * Auditoria do módulo Fiscal 2026-11-05, lote 1.1.
 *
 * O usuário tem a permissão da tela (`fiscal.nfe.view`) em todos os casos: assim o 403
 * só pode vir do pacote ou do login liberado — e o par positivo (mesmo usuário, pacote
 * ativo) prova que é o pacote que decide, não outra camada.
 *
 * Tenants: 98 (fictício de teste, ADR 0358) com pacote · 99 (fictício) sem pacote.
 * Nunca biz=4. Roda no MySQL (CT 100 / lane MySQL); no SQLite do PR pula, como os
 * demais testes HTTP do módulo.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: rotas Fiscal exigem schema MySQL UltimatePOS (ADR 0358)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes ausente — rode as migrations do NfeBrasil');
    }

    // Religa a regra REAL do pacote (tests/Pest.php deixa o pacote sempre ativo pros
    // outros testes do módulo). Só a assinatura é trocada: ativa para o 98, ausente para o 99.
    app()->instance(FiscalPacote::class, new FiscalPacote());

    $util = \Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('isSuperadminInstalled')->andReturn(true);
    $util->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(
        fn ($biz, $perm, $cb = null) => $perm === FiscalPacote::PACOTE ? (int) $biz === 98 : true
    );
    app()->instance(ModuleUtil::class, $util);

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
});

function fiscalEntrarComo(\App\User $user, int $businessId): void
{
    test()->actingAs($user);
    session(['business.id' => $businessId, 'user.business_id' => $businessId]);
}

it('empresa SEM o pacote fiscal recebe 403 em /fiscal/nfe, mesmo com a permissão da tela', function () {
    $u99 = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view'], $this->b99);
    fiscalEntrarComo($u99, (int) $this->b99->id);

    $this->get('/fiscal/nfe')->assertStatus(403);
});

it('empresa COM o pacote fiscal abre /fiscal/nfe (o mesmo usuário-tipo passa)', function () {
    $u98 = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view'], $this->b98);
    fiscalEntrarComo($u98, (int) $this->b98->id);

    $this->get('/fiscal/nfe')->assertOk();
});

it('o pacote de uma empresa não libera a outra: usuário do 99 não entra com o 98 ativo', function () {
    expect(app(FiscalPacote::class)->ativo((int) $this->b98->id))->toBeTrue()
        ->and(app(FiscalPacote::class)->ativo((int) $this->b99->id))->toBeFalse();

    $u99 = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.dfe.manage'], $this->b99);
    fiscalEntrarComo($u99, (int) $this->b99->id);

    $this->get('/fiscal/dfe')->assertStatus(403);
    $this->post('/fiscal/acoes/dfe/lote', [])->assertStatus(403);
});

it('usuário com login desligado (allow_login=0) recebe 403 mesmo com pacote e permissão', function () {
    $u98 = $this->usuarioComPermissoes(['fiscal.access', 'fiscal.nfe.view'], $this->b98);
    $u98->forceFill(['allow_login' => 0])->save();
    fiscalEntrarComo($u98->fresh(), (int) $this->b98->id);

    $this->get('/fiscal/nfe')->assertStatus(403);
});
