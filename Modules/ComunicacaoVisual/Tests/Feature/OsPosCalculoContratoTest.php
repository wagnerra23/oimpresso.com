<?php
// @covers-us US-COMVIS-005

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Http\Controllers\OsPosCalculoController;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Modules\ComunicacaoVisual\Services\PosCalculoDaOs;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-005 — pós-cálculo da OS da gráfica: custo orçado × real e a margem de cada um
 * (UC-CVPOS-01..05 do Index.casos.md). Tenant 98 contra 99 (ADR 0358).
 *
 * Conta do arranjo, feita à mão (preço 300,00):
 *   orçado  = A 51,00 + máquina 18,00 + B 12,24                       = 81,24 → margem 218,76 (72,9%)
 *   real    = material 8,190 m² impresso × (63,24 ÷ 7,440 m²) = 69,615 → 69,62
 *             (2º caminho: as duas peças custam 8,50/m², 8,190 × 8,50 = 69,615 → 69,62)
 *             + máquina 2h30 × 9,00 = 22,50 + pessoa 1h × 15,00 = 15,00 = 107,12 → margem 192,88 (64,3%)
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['comvis_os', 'comvis_os_itens', 'comvis_apontamentos', 'pcp_postos', 'pcp_apontamentos'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}).");
        }
    }
    try {
        $this->B98 = $this->seededTenant();
        $this->biz = (int) $this->B98->id;
        $this->outra = (int) $this->seededSupportClientTenant()->id;
    } catch (\Throwable $e) {
        $this->markTestSkipped('Tenant de teste ausente: ' . $e->getMessage());
    }
    $this->operador = (int) DB::table('users')->where('business_id', $this->biz)->value('id');

    // Orçamento de 2 peças aprovado vira OS de 300,00; o custo previsto do dia é gravado nos itens.
    $this->os = function (int $biz): array {
        $item = fn ($d, $a, $sub) => ['material_id' => null, 'descricao' => $d, 'largura_m' => 1.0, 'altura_m' => $a,
            'quantidade' => 1, 'area_m2' => $a, 'preco_unitario_m2' => 30.0, 'subtotal' => $sub, 'observacoes' => null];
        $orc = app(OrcamentoGravador::class)->gravar($biz, [
            'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(5)->toDateString(), 'contato_id' => null,
            'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 300.0, 'desconto' => 0.0, 'extras' => 0.0,
            'custo_instalacao' => 0.0, 'custo_entrega' => 0.0, 'total' => 300.0,
            'itens' => [$item('Fachada A', 6.0, 180.0), $item('Faixa B', 1.44, 120.0)],
        ]);
        $ids = DB::table('comvis_orcamento_itens')->where('business_id', $biz)->where('orcamento_id', $orc->id)->orderBy('id')->pluck('id')->all();
        DB::table('comvis_orcamento_itens')->where('id', $ids[0])->update(['custo_previsto_m2' => '8.50', 'custo_previsto' => '51.00', 'custo_maquina_previsto' => '18.00']);
        DB::table('comvis_orcamento_itens')->where('id', $ids[1])->update(['custo_previsto_m2' => '8.50', 'custo_previsto' => '12.24']);
        [$os] = app(OrcamentoViraOs::class)->aprovar($biz, (int) $orc->id, null);

        return [(int) $os->id, $ids];
    };
    // Um trecho de etapa apontado: inicio + fim (ou só inicio, aberto); o m² impresso vai na linha fim.
    $this->hora = function (int $biz, int $osId, string $tipo, string $custo, string $de, ?string $ate, ?string $m2 = null): void {
        $posto = (int) DB::table('pcp_postos')->insertGetId(['business_id' => $biz, 'codigo' => 'POS-' . uniqid(), 'nome' => $tipo,
            'tipo_custo' => $tipo, 'custo_hora' => $custo, 'created_at' => now(), 'updated_at' => now()]);
        $base = ['business_id' => $biz, 'posto_id' => $posto, 'tipo_custo' => $tipo, 'custo_hora' => $custo,
            'user_id' => $this->operador, 'iniciado_por' => $this->operador];
        $inicio = (int) DB::table('pcp_apontamentos')->insertGetId($base + ['chave_idempotencia' => 'pc-' . uniqid(), 'tipo' => 'inicio',
            'os_tipo' => 'comvis_os', 'os_id' => $osId, 'momento' => $de]);
        if ($ate !== null) {
            DB::table('pcp_apontamentos')->insert($base + ['chave_idempotencia' => 'pc-' . uniqid(), 'tipo' => 'fim',
                'inicio_id' => $inicio, 'os_tipo' => 'comvis_os', 'os_id' => $osId, 'momento' => $ate, 'm2_impresso' => $m2]);
        }
    };
    $this->get = function (int $os, array $permissoes) {
        $user = $this->usuarioComPermissoes($permissoes, $this->B98);
        $this->actingAs($user);
        session(['user.business_id' => $this->biz, 'business.id' => $this->biz, 'user.id' => $user->id]);
        try {
            return app()->call([app(OsPosCalculoController::class), 'show'], ['os' => $os])->getStatusCode();
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $e->getStatusCode();
        }
    };
});

it('UC-CVPOS-01 · custo orçado, custo real e as duas margens saem da conta feita à mão [V0]', function () {
    [$os] = ($this->os)($this->biz);
    ($this->hora)($this->biz, $os, 'maquina', '9.00', '2026-10-20 09:00:00', '2026-10-20 10:00:00', '6.750');
    ($this->hora)($this->biz, $os, 'maquina', '9.00', '2026-10-20 10:00:00', '2026-10-20 11:30:00', '1.440');
    ($this->hora)($this->biz, $os, 'pessoa', '15.00', '2026-10-20 13:00:00', '2026-10-20 14:00:00');

    $r = app(PosCalculoDaOs::class)->daOs($this->biz, $os);

    expect($r['preco'])->toBe('300.00')
        ->and($r['m2_impresso'])->toBe('8.190')
        ->and($r['orcado'])->toBe(['material' => '63.24', 'maquina' => '18.00', 'total' => '81.24'])
        ->and($r['real'])->toBe(['material' => '69.62', 'maquina' => '22.50', 'pessoa' => '15.00', 'terceiros' => '0.00', 'total' => '107.12'])
        ->and($r['margem_orcada'])->toBe(['valor' => '218.76', 'pct' => 72.9])
        ->and($r['margem_real'])->toBe(['valor' => '192.88', 'pct' => 64.3]);
});

it('UC-CVPOS-02 · trecho aberto não entra; etapa concluída sem m² conta a hora e não o material [V0]', function () {
    [$os] = ($this->os)($this->biz);
    ($this->hora)($this->biz, $os, 'maquina', '9.00', '2026-10-20 08:00:00', '2026-10-20 09:00:00', '7.440');
    ($this->hora)($this->biz, $os, 'pessoa', '15.00', '2026-10-20 09:00:00', '2026-10-20 10:00:00');
    ($this->hora)($this->biz, $os, 'maquina', '9.00', '2026-10-20 10:00:00', null);

    $r = app(PosCalculoDaOs::class)->daOs($this->biz, $os);

    expect($r['real'])->toBe(['material' => '63.24', 'maquina' => '9.00', 'pessoa' => '15.00', 'terceiros' => '0.00', 'total' => '87.24']);
});

it('UC-CVPOS-03 · peça sem custo deixa o orçado vazio; nenhuma com custo deixa o real vazio', function () {
    [$os, [$a, $b]] = ($this->os)($this->biz);
    DB::table('comvis_orcamento_itens')->where('id', $a)->update(['custo_previsto' => null]);
    ($this->hora)($this->biz, $os, 'maquina', '9.00', '2026-10-20 08:00:00', '2026-10-20 09:00:00', '2.880');

    $r = app(PosCalculoDaOs::class)->daOs($this->biz, $os);

    // o material usa o custo médio só das peças com custo: 2,880 × (12,24 ÷ 1,440) = 24,48
    expect($r['orcado'])->toBeNull()->and($r['margem_orcada'])->toBeNull()
        ->and($r['real']['material'])->toBe('24.48')
        ->and($r['itens_com_custo'])->toBe(1)->and($r['itens'])->toBe(2);

    DB::table('comvis_orcamento_itens')->where('id', $b)->update(['custo_previsto' => null]);
    $r = app(PosCalculoDaOs::class)->daOs($this->biz, $os);
    expect($r['real']['material'])->toBeNull()->and($r['real']['total'])->toBeNull()->and($r['margem_real'])->toBeNull();
});

it('UC-CVPOS-04 · OS, apontamento e hora de outra empresa nunca entram [T0]', function () {
    [$minha] = ($this->os)($this->biz);
    [$alheia] = ($this->os)($this->outra);
    // a outra empresa aponta no mesmo id de OS: nem a hora nem o m² dela entram
    ($this->hora)($this->outra, $minha, 'maquina', '9.00', '2026-10-20 09:00:00', '2026-10-20 19:00:00', '50.000');

    $r = app(PosCalculoDaOs::class)->daOs($this->biz, $minha);
    expect(app(PosCalculoDaOs::class)->daOs($this->biz, $alheia))->toBeNull()
        ->and($r['real']['maquina'])->toBe('0.00')->and($r['m2_impresso'])->toBe('0.000');

    config(['comunicacao-visual.pos_calculo' => true]);
    expect(($this->get)($alheia, ['comvis.material.manage']))->toBe(404);
});

it('UC-CVPOS-05 · com a flag desligada é 404; sem quem vê custo é 403', function () {
    [$os] = ($this->os)($this->biz);

    config(['comunicacao-visual.pos_calculo' => false]);
    expect(($this->get)($os, ['comvis.material.manage']))->toBe(404);

    config(['comunicacao-visual.pos_calculo' => true]);
    expect(($this->get)($os, ['comvis.orcamento.view']))->toBe(403)
        ->and(($this->get)($os, ['comvis.material.manage']))->toBe(200);
});
