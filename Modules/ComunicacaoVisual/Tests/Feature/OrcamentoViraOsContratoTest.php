<?php
// @covers-us US-COMVIS-020

declare(strict_types=1);

use App\Media;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\ComunicacaoVisual\Entities\Orcamento;
use Modules\ComunicacaoVisual\Entities\Os;
use Modules\ComunicacaoVisual\Http\Controllers\OrcamentoController;
use Modules\ComunicacaoVisual\Services\OrcamentoCalculator;
use Modules\ComunicacaoVisual\Services\OrcamentoGravador;
use Modules\ComunicacaoVisual\Services\OrcamentoViraOs;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\WithSeededTenant;

/**
 * US-COMVIS-020 · UC-FLX-01 — orçamento aprovado vira OS sem redigitar.
 *
 * Âncora: memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-020 (aceite) e o UC-FLX-01 do
 * catálogo de fluxos ("Aprovado: cria a OS com itens, medidas e arquivos do orçamento").
 * Contrato: resources/js/Pages/ComunicacaoVisual/Index.casos.md UC-CVOS-01..07.
 *
 * Arranjo (tenant 98 contra 99, ADR 0358), conta à mão:
 *   Banner  3,000 × 1,500 × 1 = 4,500 m² × 60,00 = 270,00
 *   Adesivo 2,000 × 1,000 × 3 = 6,000 m² × 40,00 = 240,00
 *   subtotal 510,00 + instalação 200,00 = total 710,00
 * ⚠️ SKIP em SQLite (lane sem as tabelas comvis_*): leia assertions, não "0 failed" (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('comvis_os_itens')) {
        $this->markTestSkipped('Requer MySQL com as tabelas comvis_* migradas.');
    }
    try {
        $this->biz = $this->seededTenant();
        $this->outra = $this->seededSupportClientTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente: '.$e->getMessage());
    }

    $this->payload = [
        'data_emissao' => '2026-10-20', 'custo_instalacao' => 200, 'custo_entrega' => 0,
        'observacoes' => 'Fachada loja centro',
        'itens' => [
            ['descricao' => 'Banner lona 440g', 'largura_m' => 3, 'altura_m' => 1.5, 'quantidade' => 1, 'preco_unitario_m2' => 60, 'observacoes' => 'ilhós a cada 50cm'],
            ['descricao' => 'Adesivo vitrine', 'largura_m' => 2, 'altura_m' => 1, 'quantidade' => 3, 'preco_unitario_m2' => 40],
        ],
    ];
    $this->orc = app(OrcamentoGravador::class)->gravar($this->biz->id, app(OrcamentoCalculator::class)->calcular($this->payload), 'enviado');
});

function anexar(int $biz, string $modelType, int $modelId, string $arquivo): void
{
    Media::create(['business_id' => $biz, 'file_name' => $arquivo, 'model_type' => $modelType, 'model_id' => $modelId]);
}

it('UC-CVOS-01 · aprovar cria a OS com os itens e as medidas do orçamento', function () {
    [$os, $criada] = app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);

    expect($criada)->toBeTrue()
        ->and($os->business_id)->toBe($this->biz->id)
        ->and($os->orcamento_id)->toBe($this->orc->id)
        ->and($os->status_etapa)->toBe('arte')
        ->and($os->numero)->toStartWith('OS-'.now()->year.'-')
        ->and(DB::table('comvis_orcamentos')->where('id', $this->orc->id)->value('status'))->toBe('aprovado');

    $itens = DB::table('comvis_os_itens')->where('os_id', $os->id)->orderBy('ordem')->get();
    expect($itens)->toHaveCount(2)
        ->and($itens[0]->descricao)->toBe('Banner lona 440g')
        ->and(number_format((float) $itens[0]->largura_m, 3, '.', ''))->toBe('3.000')
        ->and(number_format((float) $itens[0]->altura_m, 3, '.', ''))->toBe('1.500')
        ->and((int) $itens[0]->quantidade)->toBe(1)
        ->and(number_format((float) $itens[0]->area_m2, 3, '.', ''))->toBe('4.500')
        ->and($itens[0]->observacoes)->toBe('ilhós a cada 50cm')
        ->and((int) $itens[1]->quantidade)->toBe(3)
        ->and(number_format((float) $itens[1]->area_m2, 3, '.', ''))->toBe('6.000')
        ->and((int) $itens[0]->business_id)->toBe($this->biz->id);
});

it('UC-CVOS-02 · a OS vale o total do orçamento e nenhum valor do orçamento muda', function () {
    $antes = DB::table('comvis_orcamentos')->where('id', $this->orc->id)->first(['subtotal', 'total', 'desconto', 'extras', 'custo_instalacao']);
    $itensAntes = DB::table('comvis_orcamento_itens')->where('orcamento_id', $this->orc->id)->orderBy('ordem')->pluck('subtotal')->all();

    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);

    // 1º caminho: conta à mão. 2º caminho: o calculator refaz a mesma conta.
    $aMao = round(3 * 1.5 * 1 * 60 + 2 * 1 * 3 * 40 + 200, 2);
    expect($aMao)->toBe(710.0)
        ->and(app(OrcamentoCalculator::class)->calcular($this->payload)['total'])->toBe($aMao)
        ->and(number_format((float) $os->valor_total, 2, '.', ''))->toBe('710.00');

    $depois = DB::table('comvis_orcamentos')->where('id', $this->orc->id)->first(['subtotal', 'total', 'desconto', 'extras', 'custo_instalacao']);
    expect((array) $depois)->toBe((array) $antes)
        ->and(DB::table('comvis_orcamento_itens')->where('orcamento_id', $this->orc->id)->orderBy('ordem')->pluck('subtotal')->all())->toBe($itensAntes)
        ->and(number_format((float) $depois->total, 2, '.', ''))->toBe('710.00');
});

it('UC-CVOS-03 · aprovar de novo devolve a mesma OS, sem duplicar itens nem arquivos', function () {
    anexar($this->biz->id, Orcamento::class, $this->orc->id, 'arte-fachada.pdf');
    $svc = app(OrcamentoViraOs::class);

    [$os1, $criada1] = $svc->aprovar($this->biz->id, $this->orc->id);
    [$os2, $criada2] = $svc->aprovar($this->biz->id, $this->orc->id);

    expect($criada1)->toBeTrue()->and($criada2)->toBeFalse()->and($os2->id)->toBe($os1->id)
        ->and(DB::table('comvis_os')->where('business_id', $this->biz->id)->where('orcamento_id', $this->orc->id)->count())->toBe(1)
        ->and(DB::table('comvis_os_itens')->where('os_id', $os1->id)->count())->toBe(2)
        ->and(DB::table('media')->where('model_type', Os::class)->where('model_id', $os1->id)->count())->toBe(1);
});

it('UC-CVOS-04 · os arquivos do orçamento passam para a OS, e só os da empresa', function () {
    anexar($this->biz->id, Orcamento::class, $this->orc->id, 'arte-fachada.pdf');
    anexar($this->biz->id, Orcamento::class, $this->orc->id, 'foto-local.jpg');
    // Anexo com o mesmo model_id mas de outra empresa: nunca entra.
    anexar($this->outra->id, Orcamento::class, $this->orc->id, 'alheio.pdf');

    [$os] = app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id);

    $arquivos = DB::table('media')->where('model_type', Os::class)->where('model_id', $os->id)->orderBy('id')->get();
    expect($arquivos->pluck('file_name')->all())->toBe(['arte-fachada.pdf', 'foto-local.jpg'])
        ->and($arquivos->pluck('business_id')->map(fn ($v) => (int) $v)->unique()->all())->toBe([$this->biz->id]);
});

it('UC-CVOS-05 · a empresa 99 não aprova nem gera OS do orçamento da 98', function () {
    expect(fn () => app(OrcamentoViraOs::class)->aprovar($this->outra->id, $this->orc->id))
        ->toThrow(ModelNotFoundException::class);

    expect(DB::table('comvis_os')->where('orcamento_id', $this->orc->id)->count())->toBe(0)
        ->and(DB::table('comvis_orcamentos')->where('id', $this->orc->id)->value('status'))->toBe('enviado');
});

it('UC-CVOS-06 · orçamento recusado não vira OS', function () {
    DB::table('comvis_orcamentos')->where('id', $this->orc->id)->update(['status' => 'recusado']);

    expect(fn () => app(OrcamentoViraOs::class)->aprovar($this->biz->id, $this->orc->id))
        ->toThrow(\DomainException::class);

    expect(DB::table('comvis_os')->where('orcamento_id', $this->orc->id)->count())->toBe(0)
        ->and(DB::table('comvis_orcamentos')->where('id', $this->orc->id)->value('status'))->toBe('recusado');
});

it('UC-CVOS-07 · só quem pode criar orçamento aprova', function () {
    $aprovar = function (array $permissoes) {
        $user = $this->usuarioComPermissoes($permissoes, $this->biz);
        $this->actingAs($user);
        session(['user.business_id' => $this->biz->id, 'business.id' => $this->biz->id, 'user.id' => $user->id]);

        return app()->call([app(OrcamentoController::class), 'aprovar'], ['id' => $this->orc->id]);
    };

    try {
        $aprovar(['comvis.orcamento.view']);
        $this->fail('Sem comvis.orcamento.create a aprovação deveria ser recusada.');
    } catch (HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }
    expect(DB::table('comvis_os')->where('orcamento_id', $this->orc->id)->count())->toBe(0);

    $resp = $aprovar(['comvis.orcamento.create']);
    expect($resp->getStatusCode())->toBe(201)
        ->and(DB::table('comvis_os')->where('orcamento_id', $this->orc->id)->count())->toBe(1);
});
