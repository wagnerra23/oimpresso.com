<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Entities\Importacao;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato do histórico de importações (`/ponto/importacoes`).
 *
 * Cada teste cita o UC no TÍTULO do `it()` (G-2 do casos-gate, ADR 0264):
 *   Importacoes/Index.casos.md → UC-IMPIDX-01..03
 *
 * Os UC derivam do SDD §6.4 (CU-PONTO-11) e §6.5 (CU-PONTO-12) + US-PONTO-002 +
 * Portaria MTP 671/2021 Anexo I. NÃO do `.tsx`.
 *
 * ⚠️ UM UC nasce FAILING-FIRST por desenho:
 *   UC-IMPIDX-03 → SDD §9 D-8 na superfície da LISTA. O controller monta
 *                  `(int) ($i->linhas_criadas ?? 0)`; a coluna real é
 *                  `linhas_sucesso` e o `?? 0` esconde a ausência, então a lista
 *                  mostra `0/N` para toda importação. É a mesma raiz do
 *                  UC-IMPSHOW-04 em OUTRA superfície: correção no modelo deixa os
 *                  dois verdes; correção só no Show deixa este vermelho — e esse
 *                  é o sinal que se quer.
 *
 * Não duplica a tela irmã: dedup por hash, dedup escopada e 404 cross-tenant já
 * são UC-IMPSHOW-01..03 no `BancoHorasImportacaoContratoTest`.
 *
 * Tier 0: biz=1 (WR2 interno) — NUNCA biz=4 (ROTA LIVRE, ADR 0101). Sem
 * RefreshDatabase: a lane ponto-pest proíbe.
 *
 * Contrato: resources/js/Pages/Ponto/Importacoes/Index.casos.md
 *
 * @see \Modules\Ponto\Http\Controllers\ImportacaoController::index
 */

const IMPIDX_MARCADOR = 'SDD-IMPIDX-CONTRATO';
const IMPIDX_BIZ_ALHEIO = 99;
const IMPIDX_BIZ_NOME = 'IMPIDX Test Biz Adversario#99';

function impIdxPrecisaDe(array $tabelas): void
{
    foreach ($tabelas as $t) {
        if (! Schema::hasTable($t)) {
            test()->markTestSkipped("Tabela {$t} ausente — schema do Ponto não migrado nesta lane.");
        }
    }
}

/** Stub do biz fictício — sem ele o INSERT morre na FK (medido na run 30778424885). */
function impIdxGarantirBizAlheio(): void
{
    if (\App\Business::find(IMPIDX_BIZ_ALHEIO)) {
        return;
    }

    \App\Business::forceCreate([
        'id'                              => IMPIDX_BIZ_ALHEIO,
        'name'                            => IMPIDX_BIZ_NOME,
        'currency_id'                     => 1,
        'start_date'                      => now()->toDateString(),
        'default_profit_percent'          => 0,
        'owner_id'                        => 1,
        'stop_selling_before'             => 0,
        'weighing_scale_setting'          => '',
        'certificado'                     => '',
        'officeimpresso_numerodemaquinas' => 0,
    ]);
}

/**
 * `usuario_id` é NOT NULL na migration — omitir derruba o INSERT antes do caso
 * exercer qualquer coisa. Recebe o id por parâmetro porque `$this->admin` é
 * `protected` no PontoTestCase e não é alcançável de função global.
 *
 * O user é sempre o admin logado (do MEU business), mesmo quando a importação é do
 * business alheio: quem é alheio é o dado listado, não quem o inseriu na fixture.
 */
function impIdxCriar(int $businessId, int $usuarioId, array $extra = []): Importacao
{
    $imp = new Importacao();
    $imp->forceFill(array_merge([
        'business_id'        => $businessId,
        'usuario_id'         => $usuarioId,
        'tipo'               => 'AFD',
        'nome_arquivo'       => IMPIDX_MARCADOR . '-' . uniqid() . '.txt',
        'arquivo_path'       => "ponto/importacoes/{$businessId}/fixture.txt",
        'hash_arquivo'       => hash('sha256', uniqid('', true)),
        'tamanho_bytes'      => 1024,
        'estado'             => Importacao::ESTADO_CONCLUIDA,
        'linhas_total'       => 7,
        'linhas_processadas' => 7,
        'linhas_sucesso'     => 7,
        'linhas_erro'        => 0,
    ], $extra))->save();

    return $imp;
}

afterEach(function () {
    try {
        DB::table('ponto_importacoes')
            ->where('nome_arquivo', 'like', IMPIDX_MARCADOR . '%')
            ->delete();

        // Só o stub deste arquivo (filtro por nome próprio).
        \App\Business::where('id', IMPIDX_BIZ_ALHEIO)
            ->where('name', IMPIDX_BIZ_NOME)
            ->delete();
    } catch (\Throwable $e) {
        // schema ausente — cleanup best-effort
    }
});

// =====================================================================
// Importacoes/Index
// =====================================================================

it('UC-IMPIDX-01 · o histórico traz as importações do meu empregador', function () {
    $this->actAsAdmin();
    impIdxPrecisaDe(['ponto_importacoes']);

    $imp = impIdxCriar($this->business->id, $this->admin->id);

    $resp = $this->get('/ponto/importacoes');
    $resp->assertStatus(200);

    $linhas = collect($resp->json('props.importacoes.data') ?? []);

    // Pré-condição anti-vácuo (proibicoes.md §5 2026-07-24 LC-13).
    expect($linhas)->not->toBeEmpty('O histórico veio vazio — o caso não exerceu nada.');

    $minha = $linhas->firstWhere('id', $imp->id);
    expect($minha)->not->toBeNull(
        'A importação que acabei de registrar tem de aparecer no histórico (CU-PONTO-11).'
    );
    expect($minha['nome_arquivo'])->toStartWith(IMPIDX_MARCADOR,
        'A linha tem de trazer o nome do arquivo — é o elo com a origem da marcação '
        . '(Portaria 671/2021 Anexo I).'
    );
});

it('UC-IMPIDX-02 · importação de outro empregador não aparece no histórico', function () {
    $this->actAsAdmin();
    impIdxPrecisaDe(['ponto_importacoes']);
    impIdxGarantirBizAlheio();

    $minha  = impIdxCriar($this->business->id, $this->admin->id);
    $alheia = impIdxCriar(IMPIDX_BIZ_ALHEIO, $this->admin->id);

    $resp = $this->get('/ponto/importacoes');
    $resp->assertStatus(200);

    $ids = collect($resp->json('props.importacoes.data') ?? [])->pluck('id')->all();

    // Pré-condição anti-vácuo: sem a minha na lista, "a alheia não está" seria
    // verdade por lista vazia, não por isolamento.
    expect($ids)->toContain($minha->id,
        'A minha importação tem de estar na lista — senão o caso não exerce isolamento.'
    );
    expect($ids)->not->toContain($alheia->id,
        'Arquivo importado por OUTRO empregador não pode aparecer no meu histórico '
        . '(ADR 0093 · CU-PONTO-12).'
    );
});

it('UC-IMPIDX-03 · a contagem exibida na lista reflete o que foi processado', function () {
    $this->actAsAdmin();
    impIdxPrecisaDe(['ponto_importacoes']);

    // Importação 100% bem-sucedida: 7 linhas processadas, 7 com sucesso.
    $imp = impIdxCriar($this->business->id, $this->admin->id);

    // Pré-condição anti-vácuo: o dado TEM de estar gravado, senão "a lista mostra 0"
    // seria verdade por não haver sucesso nenhum a exibir.
    $gravado = DB::table('ponto_importacoes')->where('id', $imp->id)->first();
    expect((int) $gravado->linhas_sucesso)->toBe(7,
        'A fixture precisa gravar 7 linhas com sucesso — senão o caso não exerce nada.'
    );

    $resp = $this->get('/ponto/importacoes');
    $resp->assertStatus(200);

    $minha = collect($resp->json('props.importacoes.data') ?? [])->firstWhere('id', $imp->id);
    expect($minha)->not->toBeNull('A importação precisa aparecer na lista.');

    // "Não é zero quando houve sucesso" — não uma igualdade com o número exato: assim
    // o assert vale para qualquer correção (renomear a leitura, accessor, ou expor
    // `linhas_sucesso` direto).
    expect((int) $minha['linhas_criadas'])->toBeGreaterThan(0,
        'Importação que registrou 7 linhas com sucesso não pode exibir ZERO na lista. O '
        . 'controller lê `linhas_criadas`, que não existe na tabela (a coluna é '
        . '`linhas_sucesso`) e o `?? 0` esconde a ausência — SDD §9 D-8, agora na superfície '
        . 'da lista.'
    );
});
