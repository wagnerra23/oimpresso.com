<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Fiscal\Services\SpedIcmsIpiGeneratorService;
use Modules\NfeBrasil\Models\NfeEmissao;

uses(Tests\TestCase::class);

/**
 * Contrato da tela `Fiscal/Sped` (US-FISCAL-010 panorama + US-FISCAL-016/017 gerador).
 *
 * ⚠️ GUARD REVOGADO 2026-07-27 — o caso `Controller é placeholder — sem gerador SPED
 * real ainda` foi REMOVIDO. Ele assertava `! method_exists('exportSped')` e
 * `! method_exists('gerarEFD')`, e ficava VERDE apenas porque o gerador real nasceu
 * com OUTRO nome: `SpedController::gerar` (rota `fiscal.sped.icms-ipi`, entregue em
 * US-FISCAL-016 PR #8 / US-FISCAL-017 PR #9). Ou seja: o nome do teste afirmava
 * "não existe gerador" enquanto o gerador estava em produção atrás da feature-flag
 * `fiscal.sped_simples_only_lock` — verde acoplado a NOME, não a comportamento.
 * O Non-Goal de charter que ele defendia ("❌ Gerador SPED real") foi revogado no
 * mesmo PR (precedência: teste verde > casos > charter > SPEC · proibicoes.md).
 *
 * No lugar dele entra o contrato VIVO: o Controller expõe `gerar` e a rota de
 * download está registrada. Roda sempre (reflection + Route, zero hit DB).
 *
 * O comportamento de runtime do download (403 sem permissão · 503 com flag ligada ·
 * bypass superadmin · flag off libera) é defendido por `SimplesOnlyGateTest`, e o
 * default da flag por `SimplesOnlyGateConfigTest`.
 *
 * @see memory/requisitos/Fiscal/SPEC.md US-FISCAL-010 / 016 / 017
 * @see resources/js/Pages/Fiscal/Sped.casos.md
 */

it('UC-FSPED-03 · SpedController expõe gerar() e a rota de download está registrada', function () {
    $controller = new \Modules\Fiscal\Http\Controllers\SpedController();

    expect(method_exists($controller, 'index'))->toBeTrue('panorama dos 5 meses (US-FISCAL-010)')
        ->and(method_exists($controller, 'gerar'))->toBeTrue(
            'gerador EFD-ICMS/IPI entregue em US-FISCAL-016 — NÃO é mais placeholder'
        );

    expect(Route::has('fiscal.sped.icms-ipi'))->toBeTrue(
        'rota de download do TXT EFD registrada (GET /fiscal/sped/icms-ipi/{ano}/{mes})'
    );
});

it('UC-FSPED-11 · o cabeçalho do download declara o mesmo COD_VER do registro 0000 do arquivo', function () {
    $tenant = $this->seededTenant();
    $bizId = (int) $tenant->id;

    // Só a leitura (business + NF-e) é trocada: o TXT sai do gerador de produção.
    app()->instance(SpedIcmsIpiGeneratorService::class, new class extends SpedIcmsIpiGeneratorService {
        public function __construct()
        {
            parent::__construct(null);
        }

        protected function carregarBusiness(int $businessId): ?object
        {
            return (object) ['id' => $businessId, 'name' => 'CI TENANT 98 (FICTICIO)', 'state' => 'SC'];
        }

        protected function carregarEmissoes(int $businessId, CarbonImmutable $ini, CarbonImmutable $fim): Collection
        {
            return collect();
        }
    });
    config(['fiscal.sped_simples_only_lock' => false]);

    $user = $this->usuarioComPermissoes(['fiscal.sped.export'], $tenant);

    // Dois anos com versões diferentes: um valor fixo no cabeçalho não passa nos dois.
    foreach ([2025 => '019', 2026 => '020'] as $ano => $esperado) {
        $resposta = $this->actingAs($user)
            ->withSession(['business.id' => $bizId, 'user.business_id' => $bizId])
            ->get("/fiscal/sped/icms-ipi/{$ano}/1");

        $resposta->assertStatus(200)->assertHeader('X-Sped-Layout-Version', $esperado);

        $abertura = explode('|', strtok($resposta->getContent(), "\r\n"));
        expect($abertura[1])->toBe('0000')
            ->and($abertura[2])->toBe($esperado);
    }
});

it('UC-FSPED-01 · agregação de períodos NfeEmissao respeita scope per business', function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: NfeEmissao requer schema MySQL (ADR 0101)');
    }
    if (! Schema::hasTable('nfe_emissoes')) {
        $this->markTestSkipped('nfe_emissoes table missing');
    }

    // Até 2026-11-06 este caso contava `NfeEmissao::where('business_id','!=',1)` sem usuário
    // autenticado e sem semear nada: o escopo global no-opava e a contagem dava 0 por tabela
    // vazia. Agora o painel de verdade (GET /fiscal/sped) é chamado com notas das duas empresas.
    $tenant = $this->seededTenant();
    $bizA = (int) $tenant->id;
    $bizB = (int) $this->seededSupportClientTenant()->id;
    $limpar = fn () => DB::table('nfe_emissoes')->where('serie', '937')
        ->whereBetween('numero', [9_392_000, 9_392_999])->delete();
    $nota = fn (int $biz, int $numero, float $valor) => DB::table('nfe_emissoes')->insert([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '937', 'numero' => $numero,
        'status' => 'autorizada', 'valor_total' => $valor, 'emitido_em' => now(),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $doMes = fn (int $biz) => DB::table('nfe_emissoes')->where('business_id', $biz)
        ->where('status', 'autorizada')
        ->whereBetween('emitido_em', [now()->startOfMonth(), now()->endOfMonth()]);

    $limpar();
    try {
        $nota($bizA, 9_392_001, 10.00);
        $nota($bizA, 9_392_002, 20.00);
        $nota($bizB, 9_392_003, 500.00);

        $esperadoA = $doMes($bizA)->count();
        $valorA = (float) $doMes($bizA)->sum('valor_total');
        // Pré-condição: a vizinha tem nota no mês, senão não há o que vazar.
        expect($doMes($bizB)->count())->toBeGreaterThan(0);

        $user = $this->usuarioComPermissoes(['fiscal.sped.export'], $tenant);
        $this->actingAs($user)
            ->withSession(['business.id' => $bizA, 'user.business_id' => $bizA])
            ->get('/fiscal/sped')
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('Fiscal/Sped')
                    ->where('periodos.0.notasAutorizadas', $esperadoA)
                    ->where('periodos.0.valorAutorizado', fn ($v) => abs((float) $v - $valorA) < 0.001),
            );
    } finally {
        $limpar();
    }
});
