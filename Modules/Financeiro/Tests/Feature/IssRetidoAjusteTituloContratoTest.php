<?php

declare(strict_types=1);

// @covers-us US-FIN-072 — NFS-e autorizada com retenção → o título cobra o líquido (UC-FLX-30).

use App\Transaction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Services\RetencaoNfseTituloService;
use Modules\Financeiro\Services\TituloAutoService;
use Modules\NFSe\Models\NfseEmissao;

uses(Tests\TestCase::class);

/**
 * US-FIN-072 · UC-FLX-30. Contrato: resources/js/Pages/Financeiro/ContasReceber/Index.casos.md
 * (UC-CRISS-01..07). REGRA MESTRE — valor: cada número é provado por DOIS caminhos.
 *
 * Caso-base, conta à mão:
 *   serviço 1.000,00 · ISS 5% retido = 1.000,00 × 0,05 = 50,00
 *   PIS 0,65% = 6,50 · COFINS 3% = 30,00 · CSLL 1% = 10,00 → federais 46,50
 *   retido total = 50,00 + 46,50 = 96,50 → título 1.000,00 − 96,50 = 903,50
 * O 2º caminho é `issRecalculo()`: refaz a conta em PHP a partir dos campos da nota, sem SQL.
 *
 * Tenant 98 (ADR 0358) e 99 como business B (nunca o 4). Tudo numa transação desfeita no fim
 * (`fin_titulos` não aceita delete). transaction_id fictício: sem FK, só liga nota ↔ título.
 */

const ISS_LIQUIDO_MAO = 903.50;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Usa o schema MySQL do Financeiro/NFSe.');
    }
    if (! Schema::hasTable('nfse_emissoes') || ! Schema::hasColumn('nfse_emissoes', 'valor_iss_retido')) {
        $this->markTestSkipped('nfse_emissoes.valor_iss_retido ausente — rode migrate (NfeBrasil 2026_10_20_000001).');
    }

    DB::beginTransaction();
    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    $this->ownerA = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $this->ownerB = (int) DB::table('business')->where('id', $this->bizB)->value('owner_id');
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function issChave(int $biz, bool $ativo): void
{
    $bruto = DB::table('business')->where('id', $biz)->value('common_settings');
    $s = is_string($bruto) ? (json_decode($bruto, true) ?: []) : [];
    $s[RetencaoNfseTituloService::CHAVE] = ['ativo' => $ativo];
    DB::table('business')->where('id', $biz)->update(['common_settings' => json_encode($s)]);
}

function issTitulo(int $biz, int $owner, int $txId, array $over = []): int
{
    return DB::table('fin_titulos')->insertGetId(array_merge([
        'business_id' => $biz, 'numero' => 'IS'.substr(uniqid(), -10), 'tipo' => 'receber',
        'status' => 'aberto', 'valor_total' => 1000, 'valor_aberto' => 1000, 'moeda' => 'BRL',
        'emissao' => '2031-03-01', 'vencimento' => '2031-03-31', 'competencia_mes' => '2031-03',
        'origem' => 'venda', 'origem_id' => $txId, 'created_by' => $owner,
        'created_at' => now(), 'updated_at' => now(),
    ], $over));
}

function issNota(int $biz, int $txId, array $over = []): NfseEmissao
{
    return NfseEmissao::withoutGlobalScopes()->create(array_merge([
        'business_id' => $biz, 'competencia' => '2031-03-01', 'tomador_nome' => 'Tomador ISS',
        'descricao' => 'Serviço — teste US-FIN-072', 'lc116_codigo' => '7.10',
        'valor_servicos' => 1000, 'aliquota_iss' => 0.05, 'valor_iss' => 0,
        'iss_retido' => true, 'valor_iss_retido' => 50,
        'retencao_federal_sujeito' => true, 'valor_ret_pis' => 6.50, 'valor_ret_cofins' => 30.00, 'valor_ret_csll' => 10.00,
        'status' => 'processando', 'idempotency_key' => hash('sha256', uniqid('iss', true)), 'transaction_id' => $txId,
    ], $over));
}

/** 2º caminho: a conta refeita em PHP a partir dos campos da nota, sem SQL. */
function issRecalculo(float $bruto, NfseEmissao $n): float
{
    $iss = $n->iss_retido ? round((float) $n->valor_servicos * (float) $n->aliquota_iss, 2) : 0.0;

    return round($bruto - $iss - (float) $n->valor_ret_pis - (float) $n->valor_ret_cofins - (float) $n->valor_ret_csll, 2);
}

function issLinha(int $id): object
{
    return DB::table('fin_titulos')->where('id', $id)->first();
}

function issMeta(int $id): array
{
    return json_decode((string) issLinha($id)->metadata, true) ?: [];
}

function issTx(): int
{
    return 9_800_000 + random_int(1, 99_999);
}

it('UC-CRISS-01 · chave desligada (o default) → autorizar a nota não muda o título', function () {
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx);

    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    expect((float) issLinha($id)->valor_total)->toBe(1000.0);
    expect((float) issLinha($id)->valor_aberto)->toBe(1000.0);
    expect(issMeta($id))->not->toHaveKey('retencao_nfse');
});

it('UC-CRISS-02 · chave ligada → título cobra o líquido exato e não desconta duas vezes', function () {
    issChave($this->bizA, true);
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx);
    $nota = issNota($this->bizA, $tx);

    $nota->update(['status' => 'emitida']);

    expect(issRecalculo(1000.0, $nota))->toBe(ISS_LIQUIDO_MAO); // os dois caminhos concordam
    expect((float) issLinha($id)->valor_total)->toBe(ISS_LIQUIDO_MAO);
    expect((float) issLinha($id)->valor_aberto)->toBe(ISS_LIQUIDO_MAO);
    $m = issMeta($id)['retencao_nfse'];
    expect($m['estado'])->toBe('aplicado');
    expect((float) $m['bruto'])->toBe(1000.0);
    expect((float) $m['retido_total'])->toBe(96.5);
    expect((float) $m['retencoes']['iss'])->toBe(50.0);
    expect((float) $m['retencoes']['csll'])->toBe(10.0);

    // reprocessar a autorização: idempotente
    $nota->update(['status' => 'processando']);
    $nota->update(['status' => 'emitida']);
    expect((float) issLinha($id)->valor_total)->toBe(ISS_LIQUIDO_MAO);
});

it('UC-CRISS-03 · título já com baixa parcial → valor intacto, ajuste fica pendente', function () {
    issChave($this->bizA, true);
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx, ['status' => 'parcial', 'valor_aberto' => 400]);

    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    expect((float) issLinha($id)->valor_total)->toBe(1000.0);
    expect((float) issLinha($id)->valor_aberto)->toBe(400.0);
    $m = issMeta($id)['retencao_nfse'];
    expect($m['estado'])->toBe('pendente');
    expect($m['motivo'])->toBe('pendente_baixa');
    expect((float) $m['retido_total'])->toBe(96.5);
});

it('UC-CRISS-04 · cancelar a NFS-e devolve o título ao bruto', function () {
    issChave($this->bizA, true);
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx);
    $nota = issNota($this->bizA, $tx);
    $nota->update(['status' => 'emitida']);
    expect((float) issLinha($id)->valor_total)->toBe(ISS_LIQUIDO_MAO);

    $nota->update(['status' => 'cancelada']);

    // 903,50 + 96,50 = 1.000,00
    expect((float) issLinha($id)->valor_total)->toBe(1000.0);
    expect((float) issLinha($id)->valor_aberto)->toBe(1000.0);
    expect(issMeta($id)['retencao_nfse']['estado'])->toBe('estornado');
});

it('UC-CRISS-05 · nota de um negócio não toca o título de outro com o mesmo transaction_id', function () {
    issChave($this->bizA, true);
    issChave($this->bizB, true);
    $tx = issTx();
    $idB = issTitulo($this->bizB, $this->ownerB, $tx);

    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    expect((float) issLinha($idB)->valor_total)->toBe(1000.0);
    expect(issMeta($idB))->not->toHaveKey('retencao_nfse');
});

it('UC-CRISS-06 · dry-run lista o antes→depois das notas já autorizadas e não grava nada', function () {
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx);
    // nota autorizada ANTES (chave desligada): o observer não ajustou
    issNota($this->bizA, $tx, ['valor_iss_retido' => null])->update(['status' => 'emitida']);
    $antes = (array) issLinha($id);

    $rc = Artisan::call('financeiro:iss-retido-ajuste', ['--business' => $this->bizA, '--dry-run' => true]);
    $saida = Artisan::output();

    expect($rc)->toBe(0);
    expect($saida)->toContain('903,50');
    expect($saida)->toContain('aplicar');
    expect((array) issLinha($id))->toEqual($antes);
    expect(Artisan::call('financeiro:iss-retido-ajuste', ['--business' => $this->bizA]))->toBe(1);
});

it('UC-CRISS-07 · re-sincronizar a venda mantém o título no líquido', function () {
    issChave($this->bizA, true);
    $tx = issTx();
    $id = issTitulo($this->bizA, $this->ownerA, $tx);
    issNota($this->bizA, $tx)->update(['status' => 'emitida']);

    $venda = (new Transaction())->forceFill([
        'id' => $tx, 'business_id' => $this->bizA, 'type' => 'sell', 'status' => 'final',
        'payment_status' => 'due', 'final_total' => 1000, 'transaction_date' => '2031-03-01',
        'created_by' => $this->ownerA,
    ]);
    app(TituloAutoService::class)->sincronizarDeTransacao($venda);

    expect((float) issLinha($id)->valor_total)->toBe(ISS_LIQUIDO_MAO);
    expect((float) issLinha($id)->valor_aberto)->toBe(ISS_LIQUIDO_MAO);
    expect(issMeta($id)['retencao_nfse']['estado'])->toBe('aplicado');
});
