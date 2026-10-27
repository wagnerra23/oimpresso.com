<?php
// @covers-us US-COMVIS-003

declare(strict_types=1);

use App\Events\RoteiroDaOsConcluido;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Modules\ComunicacaoVisual\Services\RoteiroPadraoDaOs;
use Modules\Producao\Entities\Programacao;
use Modules\Producao\Services\SituacaoDaOrdem;
use Tests\Support\WithSeededTenant;

/**
 * A última etapa do roteiro concluída no PCP fecha a OS da gráfica (UC-CVOS-12..14 do
 * Index.casos.md). Âncora: decisão [W] 2026-10-27 ("siga com o fechamento da OS pela última
 * etapa"), passo antes da venda derivada (ADR 0192). Tenant 98 contra 99 (ADR 0358).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    foreach (['pcp_roteiros', 'pcp_roteiro_etapas', 'pcp_programacoes', 'comvis_os'] as $t) {
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
    $this->settingsAntes = DB::table('business')->where('id', $this->biz)->value('common_settings');
    $this->user = (int) DB::table('users')->where('business_id', $this->biz)->value('id');

    // OS aprovada com roteiro de 2 etapas (Impressão → Acabamento).
    $this->os = function (): int {
        $roteiro = DB::table('pcp_roteiros')->insertGetId(['business_id' => $this->biz, 'familia' => 'Comunicação visual',
            'nome' => 'Banner ' . uniqid(), 'ativo' => true, 'created_at' => now(), 'updated_at' => now()]);
        $e = fn ($bloco, $nome) => ['business_id' => $this->biz, 'roteiro_id' => $roteiro, 'bloco' => $bloco, 'trilha' => 0,
            'ordem' => 0, 'nome' => $nome, 'horas' => 1, 'junta' => null, 'opcional_por' => null, 'created_at' => now(), 'updated_at' => now()];
        DB::table('pcp_roteiro_etapas')->insert([$e(1, 'Impressão'), $e(2, 'Acabamento')]);
        app(RoteiroPadraoDaOs::class)->definir($this->biz, $roteiro);
        $item = ['material_id' => null, 'descricao' => 'Banner', 'largura_m' => 3.0, 'altura_m' => 1.0, 'quantidade' => 1,
            'area_m2' => 3.0, 'preco_unitario_m2' => 50.0, 'subtotal' => 150.0, 'observacoes' => null];
        $orc = app(OrcamentoGravador::class)->gravar($this->biz, [
            'data_emissao' => now()->toDateString(), 'data_validade' => now()->addDays(5)->toDateString(), 'contato_id' => null,
            'vendedor_id' => null, 'observacoes' => null, 'subtotal' => 150.0, 'desconto' => 0.0, 'extras' => 0.0,
            'custo_instalacao' => 0.0, 'custo_entrega' => 0.0, 'total' => 150.0, 'itens' => [$item],
        ]);
        [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz, (int) $orc->id, $this->user);

        return (int) $os->id;
    };
    // Conclui a etapa no PCP pelo mesmo ponto que o apontamento usa (SituacaoDaOrdem::aoConcluirEtapa).
    $this->concluir = function (int $osId, string $nome): void {
        $etapa = Programacao::query()->where('business_id', $this->biz)->where('os_tipo', 'comvis_os')
            ->where('os_id', $osId)->where('nome', $nome)->firstOrFail();
        $etapa->forceFill(['status' => 'concluida'])->save();
        app(SituacaoDaOrdem::class)->aoConcluirEtapa($this->biz, $etapa, $this->user);
    };
    $this->etapaDaOs = fn (int $osId) => DB::table('comvis_os')->where('id', $osId)->value('status_etapa');
});

afterEach(function () {
    if (isset($this->biz)) {
        DB::table('business')->where('id', $this->biz)->update(['common_settings' => $this->settingsAntes]);
    }
});

it('UC-CVOS-12 · concluir a última etapa do roteiro fecha a OS com a data de hoje', function () {
    config(['comunicacao-visual.fecha_os_pelo_roteiro' => true]);
    $os = ($this->os)();

    ($this->concluir)($os, 'Impressão');
    expect(($this->etapaDaOs)($os))->toBe('arte');

    ($this->concluir)($os, 'Acabamento');
    $linha = DB::table('comvis_os')->where('id', $os)->first(['status_etapa', 'data_conclusao']);
    expect($linha->status_etapa)->toBe('concluida')
        ->and(substr((string) $linha->data_conclusao, 0, 10))->toBe(now()->toDateString());
});

it('UC-CVOS-13 · com a flag desligada a OS não fecha; cancelada não reabre; repetir não muda nada', function () {
    $os = ($this->os)();
    ($this->concluir)($os, 'Impressão');
    ($this->concluir)($os, 'Acabamento');
    expect(($this->etapaDaOs)($os))->toBe('arte');

    config(['comunicacao-visual.fecha_os_pelo_roteiro' => true]);
    DB::table('comvis_os')->where('id', $os)->update(['status_etapa' => 'cancelada']);
    RoteiroDaOsConcluido::dispatch($this->biz, 'comvis_os', $os, $this->user);
    expect(($this->etapaDaOs)($os))->toBe('cancelada');
});

it('UC-CVOS-14 · o aviso de outra empresa ou de outro tipo de OS não fecha a OS [T0]', function () {
    config(['comunicacao-visual.fecha_os_pelo_roteiro' => true]);
    $os = ($this->os)();

    RoteiroDaOsConcluido::dispatch($this->outra, 'comvis_os', $os, $this->user);
    RoteiroDaOsConcluido::dispatch($this->biz, 'pcp_ordem', $os, $this->user);

    expect(($this->etapaDaOs)($os))->toBe('arte');
});
