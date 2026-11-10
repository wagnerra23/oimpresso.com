<?php

declare(strict_types=1);
// Cobre UC-PCCH-12 (Producao/Postos/Index.casos.md). Regra mestre de valor: os casos abaixo são os
// conferidos à mão no corpo do PR, por um segundo caminho (Decimal do Python, ROUND_HALF_UP).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Producao\Entities\Posto;
use Modules\Producao\Services\CustoHoraDecimal;

uses(Tests\TestCase::class, DatabaseTransactions::class);

it('UC-PCCH-12 · a terceira casa arredonda sem passar por float', function (string $digitado, string $esperado, int $centavos) {
    expect(CustoHoraDecimal::comDuasCasas($digitado))->toBe($esperado)
        ->and(CustoHoraDecimal::emCentavos($digitado))->toBe($centavos);
})->with([
    ['1.005', '1.01', 101],
    ['1.015', '1.02', 102],
    ['2.675', '2.68', 268],
    ['12.345', '12.35', 1235],
    ['30.001', '30.00', 3000],
    ['99998.995', '99999.00', 9999900],
    ['7', '7.00', 700],
    ['.5', '0.50', 50],
    ['30.50', '30.50', 3050],
]);

it('UC-PCCH-12 · valor malformado não vira zero em silêncio', function () {
    expect(fn () => CustoHoraDecimal::emCentavos('1e2'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => CustoHoraDecimal::emCentavos(''))->toThrow(InvalidArgumentException::class);
});

describe('pelas rotas', function () {
    beforeEach(function () {
        $this->partialMock(\App\Utils\ModuleUtil::class, function ($m) {
            $m->shouldReceive('hasThePermissionInSubscription')->byDefault()->passthru();
            $m->shouldReceive('hasThePermissionInSubscription')->withSomeOfArgs('producao_module')->andReturn(true);
        });
        if (DB::connection()->getDriverName() === 'sqlite') {
            $this->markTestSkipped('SQLite-incompatível: schema MySQL do UltimatePOS.');
        }
        if (! Schema::hasTable('pcp_colaborador_custos') || ! Schema::hasColumn('pcp_postos', 'custo_hora_direto')) {
            $this->markTestSkipped('Tabelas pcp_* ausentes nesta lane.');
        }
        $this->B98 = $this->seededTenant();
        $this->gestor = $this->usuarioComPermissoes(['producao.access', 'producao.desempenho.ver'], $this->B98);
    });

    it('UC-PCCH-12 · o custo-hora da pessoa grava arredondado e recusa notação científica', function () {
        $pessoa = $this->usuarioComPermissoes([], $this->B98);

        $this->actingAs($this->gestor)->put("/producao/colaboradores/{$pessoa->id}/custo-hora", ['custo_hora' => '1.005'])->assertSessionHasNoErrors();
        expect((string) DB::table('pcp_colaborador_custos')->where('user_id', $pessoa->id)->value('custo_hora'))->toBe('1.01');

        $this->actingAs($this->gestor)->put("/producao/colaboradores/{$pessoa->id}/custo-hora", ['custo_hora' => '1e2'])->assertSessionHasErrors('custo_hora');
        expect((string) DB::table('pcp_colaborador_custos')->where('user_id', $pessoa->id)->value('custo_hora'))->toBe('1.01');
    });

    it('UC-PCCH-12 · o custo direto só sai quando o custo-hora muda em centavos', function () {
        // SUPERADMIN: fixture sem usuário logado; o escopo não é o que este caso prova.
        $posto = Posto::withoutGlobalScopes()->create(['business_id' => (int) $this->B98->id, 'codigo' => 'CHD-'.uniqid(), 'nome' => 'Plotter',
            'tipo_custo' => 'maquina', 'custo_hora' => '50.00', 'custo_hora_direto' => '40.00']);
        $payload = ['codigo' => $posto->codigo, 'nome' => 'Plotter', 'tipo_custo' => 'maquina'];
        $direto = fn () => DB::table('pcp_postos')->where('id', $posto->id)->value('custo_hora_direto');

        $this->actingAs($this->gestor)->put("/producao/postos/{$posto->id}", $payload + ['custo_hora' => '50.001'])->assertSessionHasNoErrors();
        expect((string) $direto())->toBe('40.00');

        $this->actingAs($this->gestor)->put("/producao/postos/{$posto->id}", $payload + ['custo_hora' => '50.01'])->assertSessionHasNoErrors();
        expect($direto())->toBeNull();
    });
});
