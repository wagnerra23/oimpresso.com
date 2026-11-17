<?php

declare(strict_types=1);

// @covers-us US-NFSE-008
// Lista de NFS-e (/nfse) pela pilha HTTP inteira, no MySQL da lane nfse-pest.
// Contrato: resources/js/Pages/Nfse/Index.casos.md — UC-NFSL-04 e UC-NFSL-05.
// Auditoria NFSe 2026-11-05, lote 3, PR 3.1:
//   - a tela manda a competência como AAAA-MM (<input type="month">) e a regra pedia Y-m-d:
//     todo filtro de competência voltava com erro de validação;
//   - a prop `notas` levava xml_envio e xml_retorno (a DPS e a resposta da Receita) de cada nota.
//
// Sessão = 98 (fictício canônico, ADR 0358). Adversário = 99. Nunca biz=4.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

const NFSIDX_TENANT = 98;
const NFSIDX_ADVERSARIO = 99;

function nfsidxCriarNota(int $businessId, string $competencia, string $tomador): int
{
    Schema::disableForeignKeyConstraints();
    try {
        return (int) DB::table('nfse_emissoes')->insertGetId([
            'business_id'     => $businessId,
            'competencia'     => $competencia,
            'tomador_nome'    => $tomador,
            'descricao'       => 'Servico de teste',
            'valor_servicos'  => 100,
            'status'          => 'emitida',
            'xml_envio'       => '<DPS>conteudo-de-envio</DPS>',
            'xml_retorno'     => '<NFSe>conteudo-de-retorno</NFSe>',
            'idempotency_key' => 'ct-nfsidx-' . uniqid('', true),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

/** Abre a lista e devolve as linhas da prop deferida `notas` (2º pedido do Inertia). */
function nfsidxLinhas($t, string $url): array
{
    $linhas = null;
    $t->actingAs($t->user)->withSession($t->sessao)
        ->get($url)
        ->assertOk()
        // Closure comum, não arrow fn: a arrow fn captura $linhas por valor, e o
        // `use (&$linhas)` de dentro passaria a referenciar essa cópia.
        ->assertInertia(function (AssertableInertia $p) use (&$linhas) {
            $p->component('Nfse/Index')
                ->missing('notas') // deferida: não vem no 1º pedido
                ->loadDeferredProps(function (AssertableInertia $r) use (&$linhas) {
                    $linhas = $r->toArray()['props']['notas']['data'];
                });
        });

    expect($linhas)->toBeArray('A prop deferida `notas` nao chegou no 2o pedido.');

    return $linhas;
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSIDX_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    $this->user = $this->usuarioComPermissoes(['nfse.view'], \App\Business::find(NFSIDX_TENANT));
    $this->sessao = ['user.business_id' => NFSIDX_TENANT, 'business.id' => NFSIDX_TENANT];
    session($this->sessao);

    $tag = uniqid();
    $this->tomSet = "Tomador Setembro {$tag}";
    $this->tomOut = "Tomador Outubro {$tag}";
    $this->tomAlheio = "Tomador Alheio {$tag}";
    $this->notaSet = nfsidxCriarNota(NFSIDX_TENANT, '2026-09-01', $this->tomSet);
    $this->notaOut = nfsidxCriarNota(NFSIDX_TENANT, '2026-10-01', $this->tomOut);
    $this->notaAlheia = nfsidxCriarNota(NFSIDX_ADVERSARIO, '2026-09-01', $this->tomAlheio);
});

it('UC-NFSL-04 · o filtro de competencia da tela (AAAA-MM) filtra a lista pelo mes', function () {
    // Antes: 302 com erro de validação em `de` (a regra pedia Y-m-d).
    $ids = array_column(nfsidxLinhas($this, '/nfse?de=2026-09&ate=2026-09'), 'id');

    expect($ids)->toContain($this->notaSet);
    expect($ids)->not->toContain($this->notaOut);
    // Tier 0: a nota de setembro da empresa 99 não entra na lista da 98.
    expect($ids)->not->toContain($this->notaAlheia);

    // Controle positivo: outubro traz a de outubro e não a de setembro.
    $out = array_column(nfsidxLinhas($this, '/nfse?de=2026-10&ate=2026-10'), 'id');
    expect($out)->toContain($this->notaOut);
    expect($out)->not->toContain($this->notaSet);
});

it('UC-NFSL-05 · a lista nao leva o XML da nota e mostra a competencia como mes/ano', function () {
    $linhas = nfsidxLinhas($this, '/nfse?de=2026-09&ate=2026-09');
    $linha = collect($linhas)->firstWhere('id', $this->notaSet);

    expect($linha)->not->toBeNull('Controle positivo caiu: a nota de setembro nao veio na lista.');
    expect($linha)->not->toHaveKey('xml_envio');
    expect($linha)->not->toHaveKey('xml_retorno');
    expect($linha)->not->toHaveKey('descricao');
    expect($linha['competencia'])->toBe('09/2026');
    expect($linha['tomador_nome'])->toBe($this->tomSet);
});
