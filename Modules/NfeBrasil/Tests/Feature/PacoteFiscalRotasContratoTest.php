<?php

declare(strict_types=1);

// @covers-us US-NFE-MANUAL + US-NFE-010 — lote 3.3 da auditoria NfeBrasil (N5-06, decisão [W] B-03):
// as rotas autenticadas de /nfe-brasil/* exigem o pacote `fiscal_module` (FiscalLiberado).

use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Modules\Fiscal\Services\FiscalPacote;
use Tests\Support\EstoqueFixture;

/**
 * Âncora (regra escrita, não o código): memory/proibicoes.md §Multi-tenant, Camada 1 —
 * habilitar módulo por empresa é compra de pacote no Superadmin. Molde:
 * Modules/Fiscal/Tests/Feature/FiscalPacoteGateTest.php.
 *
 * O usuário tem a permissão da ação em todos os casos, então o 403 só pode vir do pacote. O
 * par positivo (98, com pacote) mostra uma resposta que só o controller produz. As rotas
 * públicas assinadas seguem sem pacote: chegam ao controller (404 de nota/link inexistente),
 * nunca 403.
 *
 * MySQL · tenants 98 (com pacote) × 99 (sem pacote), ADR 0358. Fora do MySQL pula.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('MySQL-only: precisa das tabelas do NfeBrasil.');
    }

    // Religa a regra REAL do pacote (tests/Pest.php deixa ativo pros outros testes do módulo).
    app()->instance(FiscalPacote::class, new FiscalPacote());
    $util = \Mockery::mock(ModuleUtil::class)->makePartial();
    $util->shouldReceive('isSuperadminInstalled')->andReturn(true);
    $util->shouldReceive('hasThePermissionInSubscription')->andReturnUsing(
        fn ($biz, $perm, $cb = null) => $perm === FiscalPacote::PACOTE ? (int) $biz === 98 : true
    );
    app()->instance(ModuleUtil::class, $util);

    $this->b98 = $this->seededTenant();
    $this->b99 = $this->seededSupportClientTenant();
    if ((int) $this->b98->id !== 98) {
        $this->markTestSkipped('Tenant canônico não é o 98 neste ambiente.');
    }

    $this->como = function (int $biz, array $perms) {
        $b = $biz === 98 ? $this->b98 : $this->b99;

        return $this->actingAs($this->usuarioComPermissoes($perms, $b))
            ->withSession(['business.id' => $biz, 'user.business_id' => $biz]);
    };
});

afterEach(fn () => \Mockery::close());

it('3.3 · empresa sem o pacote não liga a emissão automática, mesmo com a permissão', function () {
    foreach ([98, 99] as $biz) {
        DB::table('nfe_business_configs')->updateOrInsert(
            ['business_id' => $biz],
            ['auto_emission_enabled' => false, 'tributacao_default' => json_encode(['cfop' => '5102']), 'updated_at' => now()],
        );
    }
    $ligado = fn (int $biz) => (bool) DB::table('nfe_business_configs')->where('business_id', $biz)->value('auto_emission_enabled');

    ($this->como)(99, ['nfe.tributacao.manage'])->post('/nfe-brasil/tributacao/auto-emission/toggle', ['enabled' => true])->assertForbidden();
    expect($ligado(99))->toBeFalse();

    // Par positivo: a mesma permissão, empresa com pacote — a escolha é gravada pelo controller.
    ($this->como)(98, ['nfe.tributacao.manage'])->post('/nfe-brasil/tributacao/auto-emission/toggle', ['enabled' => true])->assertRedirect();
    expect($ligado(98))->toBeTrue();
});

it('3.3 · empresa sem o pacote não emite nem lista notas da venda', function () {
    $venda = fn (int $biz): int => (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'final_total' => 10.00, 'total_before_tax' => 10.00,
        'invoice_no' => 'PAC33-' . uniqid(), 'transaction_date' => now(), 'created_by' => EstoqueFixture::userId($biz),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $tx99 = $venda(99);
    $tx98 = $venda(98);

    ($this->como)(99, ['nfebrasil.emit.manage', 'sell.view'])->postJson("/nfe-brasil/transactions/{$tx99}/emitir", ['modelo' => '65'])->assertForbidden();
    ($this->como)(99, ['nfebrasil.emit.manage', 'sell.view'])->getJson("/nfe-brasil/api/transactions/{$tx99}/emissoes")->assertForbidden();
    expect(DB::table('nfe_emissoes')->where('transaction_id', $tx99)->count())->toBe(0);

    // Par positivo: com pacote a lista chega ao controller, que devolve a venda do próprio 98.
    ($this->como)(98, ['nfebrasil.emit.manage', 'sell.view'])->getJson("/nfe-brasil/api/transactions/{$tx98}/emissoes")
        ->assertOk()->assertJsonPath('transaction_id', $tx98);
});

it('3.3 · as rotas públicas assinadas seguem sem pacote (chegam ao controller)', function () {
    // DANFE por link assinado, empresa 99 sem pacote, nota inexistente: 404 do controller, não 403.
    $danfe = URL::signedRoute('nfe-brasil.danfe-publica', ['business' => 99, 'emissao' => 999999999]);
    $this->get($danfe)->assertNotFound();

    // Página do contador, link inexistente: 404 do controller, não 403.
    $contador = URL::temporarySignedRoute('nfe-brasil.contador.revisao', now()->addHour(), ['business' => 99, 'link' => 999999999]);
    $this->get($contador)->assertNotFound();
});
