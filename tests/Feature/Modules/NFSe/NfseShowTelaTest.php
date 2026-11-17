<?php

declare(strict_types=1);

// @covers-us US-NFSE-006
// Detalhe da NFS-e (/nfse/{id}) pela pilha HTTP inteira, no MySQL da lane nfse-pest.
// Contrato: resources/js/Pages/Nfse/Show.casos.md — UC-NFSD-05 e UC-NFSD-06.
// Auditoria NFSe 2026-11-05, lote 3, PR 3.2: a tela não dizia quando a nota foi cancelada
// nem em que ambiente (homologação/produção) a empresa emite.
//
// Sessão = 98 (fictício canônico, ADR 0358). Adversário = 99. Nunca biz=4.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

const NFSSHOW_TENANT = 98;
const NFSSHOW_ADVERSARIO = 99;

function nfsshowCriarNota(int $businessId, string $status, string $updatedAt): int
{
    Schema::disableForeignKeyConstraints();
    try {
        return (int) DB::table('nfse_emissoes')->insertGetId([
            'business_id'     => $businessId,
            'numero'          => (string) random_int(100000, 999999),
            'competencia'     => '2026-10-01',
            'tomador_nome'    => 'Tomador Ficticio Ltda',
            'descricao'       => 'Servico de teste',
            'valor_servicos'  => 100,
            'status'          => $status,
            'idempotency_key' => 'ct-nfsshow-' . uniqid('', true),
            'created_at'      => '2026-10-05 09:00:00',
            'updated_at'      => $updatedAt,
        ]);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

function nfsshowConfig(int $businessId, string $ambiente): void
{
    Schema::disableForeignKeyConstraints();
    try {
        DB::table('nfse_provider_configs')->where('business_id', $businessId)->delete();
        DB::table('nfse_provider_configs')->insert([
            'business_id'           => $businessId,
            'municipio_codigo_ibge' => '4205407',
            'ambiente'              => $ambiente,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    } finally {
        Schema::enableForeignKeyConstraints();
    }
}

function nfsshowProps($t, int $id): array
{
    $props = [];
    $t->actingAs($t->user)->withSession($t->sessao)
        ->get("/nfse/{$id}")
        ->assertOk()
        ->assertInertia(function (AssertableInertia $p) use (&$props) {
            $p->component('Nfse/Show');
            $props = $p->toArray()['props'];
        });

    return $props;
}

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes') || ! Schema::hasTable('nfse_provider_configs')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes e nfse_provider_configs (lane nfse-pest).');
    }
    if (! \App\Business::find(NFSSHOW_TENANT)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }

    $this->user = $this->usuarioComPermissoes(['nfse.view'], \App\Business::find(NFSSHOW_TENANT));
    $this->sessao = ['user.business_id' => NFSSHOW_TENANT, 'business.id' => NFSSHOW_TENANT];
    session($this->sessao);
});

it('UC-NFSD-05 · nota cancelada mostra quando foi cancelada; emitida nao', function () {
    $cancelada = nfsshowCriarNota(NFSSHOW_TENANT, 'cancelada', '2026-10-20 14:30:00');
    $emitida = nfsshowCriarNota(NFSSHOW_TENANT, 'emitida', '2026-10-20 14:30:00');

    expect(nfsshowProps($this, $cancelada)['nfse']['cancelada_em'])->toBe('20/10/2026 14:30');
    // Controle: a emitida tem o mesmo updated_at e não ganha data de cancelamento.
    expect(nfsshowProps($this, $emitida)['nfse'])->toHaveKey('cancelada_em');
    expect(nfsshowProps($this, $emitida)['nfse']['cancelada_em'])->toBeNull();
});

it('UC-NFSD-06 · o detalhe diz o ambiente do provedor da empresa da nota', function () {
    $nota = nfsshowCriarNota(NFSSHOW_TENANT, 'emitida', '2026-10-20 14:30:00');

    nfsshowConfig(NFSSHOW_TENANT, 'homologacao');
    // Tier 0: a configuração da empresa 99 (produção) não vaza para a nota da 98.
    nfsshowConfig(NFSSHOW_ADVERSARIO, 'producao');
    expect(nfsshowProps($this, $nota)['ambiente'])->toBe('homologacao');

    // Controle positivo: trocar o ambiente da 98 troca o que a tela mostra.
    nfsshowConfig(NFSSHOW_TENANT, 'producao');
    expect(nfsshowProps($this, $nota)['ambiente'])->toBe('producao');
});
