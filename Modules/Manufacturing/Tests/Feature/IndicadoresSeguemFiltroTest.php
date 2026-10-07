<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Manufacturing\Services\ProductionService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * UC-OP-10 — os 4 indicadores da aba Ordens seguem o filtro de local e período
 * (decisão [W] 2026-10-06, US-MANU-004).
 *
 * O Valor total é dinheiro, então cada caso confere por DOIS caminhos independentes (regra mestre
 * de valor, proibicoes.md):
 *  (a) a conta à mão, com os valores da fixture escritos no teste;
 *  (b) a soma do `final_total` das ordens que a LISTA devolve com o mesmo filtro
 *      (`listProductions`) — o mesmo conjunto que a tabela mostra. Vale porque a fixture tem
 *      menos de 25 ordens, o teto da lista.
 *
 * Fixture (valores escolhidos para que cada filtro dê uma soma diferente):
 *  - L1, 2026-09-10, finalizada, 100
 *  - L1, 2026-09-20, rascunho,     40
 *  - L2, 2026-09-15, finalizada,   7
 *  - L1, 2026-08-05, finalizada,   3  (fora do período de setembro)
 *  - ordem da empresa 99 em L1, setembro, 500 (nunca pode entrar)
 *
 * Quando o filtro tem um local criado pela própria fixture, o número é absoluto; quando não tem,
 * o caso mede o DELTA sobre o que a empresa 98 já tinha, porque no CT 100 o banco persiste. Tenant 98 (fictício, ADR 0358); 99 é a outra empresa.
 * NUNCA biz=4. Tudo em transação desfeita. ⚠️ SKIP em SQLite: leia assertions, não "0 failed".
 *
 * @see Modules/Manufacturing/Services/ProductionService::summary()
 */
require_once __DIR__.'/../Support/receita-empresa-fixtures.php';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: a ordem depende do schema MySQL UltimatePOS.');
    }
    foreach (['business', 'business_locations', 'invoice_schemes', 'invoice_layouts', 'users', 'transactions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema incompleto — tabela {$t} ausente.");
        }
    }
});

function mfgIndLocal(int $biz, string $nome): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $biz, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $biz, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $biz, 'name' => $nome, 'country' => 'Brasil', 'state' => 'SC', 'city' => 'Teste',
        'zip_code' => '88000000', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

function mfgIndOrdem(int $biz, int $userId, int $local, string $data, bool $final, float $valor): void
{
    DB::table('transactions')->insert([
        'business_id' => $biz, 'location_id' => $local, 'type' => 'production_purchase', 'status' => 'received',
        'transaction_date' => $data.' 10:00:00', 'created_by' => $userId, 'essentials_duration' => 0,
        'ref_no' => 'OP-IND-'.random_int(10000, 99999), 'final_total' => $valor,
        'mfg_is_final' => $final ? 1 : 0, 'mfg_wasted_units' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Monta a fixture e devolve [L1, L2]. */
function mfgIndFixture(): array
{
    $user = mfgEmpUsuario('mfg_indicadores_test');
    mfgEmpNegocio(MFG_EMP_OUTRO);
    $l1 = mfgIndLocal(MFG_EMP_BIZ, 'Matriz IND');
    $l2 = mfgIndLocal(MFG_EMP_BIZ, 'Filial IND');
    $outro = mfgIndLocal(MFG_EMP_OUTRO, 'Outra empresa IND');

    mfgIndOrdem(MFG_EMP_BIZ, $user->id, $l1, '2026-09-10', true, 100);
    mfgIndOrdem(MFG_EMP_BIZ, $user->id, $l1, '2026-09-20', false, 40);
    mfgIndOrdem(MFG_EMP_BIZ, $user->id, $l2, '2026-09-15', true, 7);
    mfgIndOrdem(MFG_EMP_BIZ, $user->id, $l1, '2026-08-05', true, 3);
    mfgIndOrdem(MFG_EMP_OUTRO, $user->id, $outro, '2026-09-12', true, 500);

    return [$l1, $l2];
}

/** Delta entre dois retratos do summary(). */
function mfgIndDelta(array $depois, array $antes): array
{
    return [
        'total_count' => $depois['total_count'] - $antes['total_count'],
        'final_count' => $depois['final_count'] - $antes['final_count'],
        'pending_count' => $depois['pending_count'] - $antes['pending_count'],
        'total_value' => (float) $depois['total_value'] - (float) $antes['total_value'],
    ];
}

/** Caminho (b): soma o final_total das ordens que a LISTA devolve com o mesmo filtro. */
function mfgIndSomaDaLista(ProductionService $svc, array $filtros): float
{
    return (float) $svc->listProductions(MFG_EMP_BIZ, $filtros)->sum('final_total');
}

describe('UC-OP-10 — os indicadores seguem o filtro de local e período', function () {
    it('UC-OP-10 local + período: só as ordens de L1 em setembro entram nos 4 números', function () {
        $svc = new ProductionService();
        // L1 nasce na fixture: antes dela não há ordem nesse local, então o número é absoluto.
        [$l1] = mfgIndFixture();

        $filtro = ['location_id' => $l1, 'start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
        $d = $svc->summary(MFG_EMP_BIZ, $filtro);
        $d['total_value'] = (float) $d['total_value'];

        // (a) à mão: L1 em setembro = 100 (finalizada) + 40 (rascunho). A de agosto (3), a de L2 (7)
        // e a da empresa 99 (500) ficam de fora.
        expect($d['total_count'])->toBe(2);
        expect($d['final_count'])->toBe(1);
        expect($d['pending_count'])->toBe(1);
        expect($d['total_value'])->toEqualWithDelta(140.0, 0.0001);

        // (b) a lista com o mesmo filtro soma o mesmo valor.
        expect(mfgIndSomaDaLista($svc, $filtro))->toEqualWithDelta($d['total_value'], 0.0001);
    });

    it('UC-OP-10 só o local: entra L1 de qualquer data, sai L2', function () {
        $svc = new ProductionService();
        [$l1] = mfgIndFixture();

        $d = $svc->summary(MFG_EMP_BIZ, ['location_id' => $l1]);

        // (a) à mão: 100 + 40 + 3 = 143, três ordens, uma delas rascunho.
        expect($d['total_count'])->toBe(3);
        expect($d['final_count'])->toBe(2);
        expect($d['pending_count'])->toBe(1);
        expect((float) $d['total_value'])->toEqualWithDelta(143.0, 0.0001);

        // (b) mesmo filtro na lista.
        expect(mfgIndSomaDaLista($svc, ['location_id' => $l1]))->toEqualWithDelta(143.0, 0.0001);
    });

    it('UC-OP-10 só o período: setembro de todos os locais, sem agosto e sem a outra empresa', function () {
        $svc = new ProductionService();
        $setembro = ['start_date' => '2026-09-01', 'end_date' => '2026-09-30'];
        $antes = $svc->summary(MFG_EMP_BIZ, $setembro);
        $antesLista = mfgIndSomaDaLista($svc, $setembro);
        mfgIndFixture();

        $d = mfgIndDelta($svc->summary(MFG_EMP_BIZ, $setembro), $antes);

        // (a) à mão: 100 + 40 + 7 = 147.
        expect($d['total_count'])->toBe(3);
        expect($d['final_count'])->toBe(2);
        expect($d['pending_count'])->toBe(1);
        expect($d['total_value'])->toEqualWithDelta(147.0, 0.0001);

        // (b) o que a lista passou a somar é o mesmo delta.
        expect(mfgIndSomaDaLista($svc, $setembro) - $antesLista)->toEqualWithDelta(147.0, 0.0001);
    });

    it('UC-OP-10 "Só finalizadas" não recorta os indicadores; uma data só não recorta o período', function () {
        $svc = new ProductionService();
        [$l1] = mfgIndFixture();

        // is_final é ignorado: o rascunho de 40 continua contado e "Pendentes" não zera.
        $comFinal = $svc->summary(MFG_EMP_BIZ, ['location_id' => $l1, 'is_final' => true]);
        expect($comFinal['pending_count'])->toBe(1);
        expect((float) $comFinal['total_value'])->toEqualWithDelta(143.0, 0.0001);

        // Só a data inicial: o período não vale (mesma regra da lista), então conta todas de L1.
        $umaData = $svc->summary(MFG_EMP_BIZ, ['location_id' => $l1, 'start_date' => '2026-09-01']);
        expect($umaData['total_count'])->toBe(3);
    });

    it('UC-OP-10 sem filtro continua contando todas as ordens da empresa (contador da aba)', function () {
        $svc = new ProductionService();
        $antes = $svc->summary(MFG_EMP_BIZ);
        mfgIndFixture();

        $d = mfgIndDelta($svc->summary(MFG_EMP_BIZ), $antes);

        // (a) à mão: as 4 ordens da empresa 98, 100 + 40 + 7 + 3 = 150. A de 500 é da 99.
        expect($d['total_count'])->toBe(4);
        expect($d['total_value'])->toEqualWithDelta(150.0, 0.0001);

        // E chamar sem filtros dá o mesmo que passar um filtro vazio.
        expect($svc->summary(MFG_EMP_BIZ, []))->toEqual($svc->summary(MFG_EMP_BIZ));
    });
});
