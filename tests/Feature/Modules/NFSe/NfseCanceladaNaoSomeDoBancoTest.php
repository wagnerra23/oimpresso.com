<?php

declare(strict_types=1);

// @covers-us US-NFSE-006
// Nota fiscal cancelada é registro fiscal: o número fica usado e a linha não sai do banco
// (Show.charter.md:73 — "NÃO faz forceDelete da nota (append-only fiscal — cancelada
// permanece no banco)"; mesma regra da NF-e em memory/proibicoes.md §FSM).
//
// ⚠️ Medido em 2026-11-16 (lote 1 PR 1.2 da auditoria NFSe): Modules\NFSe\Models\NfseEmissao
// não tem trava nenhuma — sem `deleting`/`forceDeleting`, sem observer. Hoje nenhum código
// do módulo chama forceDelete() numa nota, mas qualquer chamador (tinker, comando, limpeza
// de teste — os testes antigos faziam isso) apaga a cancelada sem erro. Este teste afirma o
// contrato; enquanto a trava não existir ele fica vermelho, e o conserto é de quem é dono
// do Model (lote 4 da auditoria), não deste arquivo.
//
// Empresa = 98 (ADR 0358). DatabaseTransactions: o rollback desfaz só o que o teste gravou.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NFSe\Models\NfseEmissao;

// `Tests\TestCase` vem do tests/Pest.php — repetir aqui derruba a lane.
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasTable('nfse_emissoes')) {
        $this->markTestSkipped('Requer MySQL com nfse_emissoes (lane nfse-pest).');
    }
    if (! \App\Business::find(98)) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358).');
    }
    session(['user.business_id' => 98]);
});

function nfsecancNota(string $status): int
{
    return (int) DB::table('nfse_emissoes')->insertGetId([
        'business_id'     => 98,
        'serie'           => 'RPS',
        'rps_numero'      => 'CANC-' . $status,
        'numero'          => '777',
        'competencia'     => '2026-10-01',
        'tomador_nome'    => 'TOMADOR FICTICIO LTDA',
        'descricao'       => 'Servico de teste',
        'valor_servicos'  => 100,
        'valor_iss'       => 2,
        'status'          => $status,
        'idempotency_key' => 'ct-nfsecanc-' . uniqid('', true),
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);
}

it('[T0] US-NFSE-006 · forceDelete() de nota cancelada nao tira a linha do banco', function () {
    $id = nfsecancNota('cancelada');

    try {
        NfseEmissao::withTrashed()->findOrFail($id)->forceDelete();
    } catch (\Throwable) {
        // Recusar com exceção cumpre o contrato: o que importa é a linha ficar.
    }

    $linha = DB::table('nfse_emissoes')->where('id', $id)->first();
    expect($linha)->not->toBeNull('forceDelete() apagou fisicamente uma NFS-e cancelada (registro fiscal).');
    expect($linha->status)->toBe('cancelada');
});

it('[T0] US-NFSE-006 · forceDelete() de nota emitida nao tira a linha do banco', function () {
    $id = nfsecancNota('emitida');

    try {
        NfseEmissao::withTrashed()->findOrFail($id)->forceDelete();
    } catch (\Throwable) {
        // idem
    }

    expect(DB::table('nfse_emissoes')->where('id', $id)->exists())
        ->toBeTrue('forceDelete() apagou fisicamente uma NFS-e emitida (registro fiscal).');
});
