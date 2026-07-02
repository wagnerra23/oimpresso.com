<?php

declare(strict_types=1);

// @covers-us UC-EST-04

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Vestuario\Services\DevolucaoService;
use Tests\Support\EstoqueFixture;

uses(DatabaseTransactions::class);

/**
 * UC-EST-04 — Devolução Vestuario REINTEGRA estoque. DOC-RAIZ-ESTOQUE §3 (`sell_return` → ENTRA).
 *
 * Era RED-SPEC (bug Tier 0: `Modules\Vestuario\Services\DevolucaoService::registrarDevolucao` não
 * reintegrava `qty_available`). CORRIGIDO (Wagner-aprovado 2026-07-02, regra mestre VALOR/ESTOQUE):
 * o service agora reintegra a quantidade devolvida no LOCAL da venda, via ProductUtil (auditável —
 * LogsActivity + respeita enable_stock: INV-1/INV-5), dentro do DB::transaction (INV-3), com guard
 * Tier 0 cross-tenant (ADR 0093). Vale pra todos os tipos (o item físico volta; a reposição de uma
 * troca é venda separada, não modelada aqui).
 *
 * biz=1 dogfood (ADR 0101). Skip gracioso em sqlite.
 *
 * @see Modules/Vestuario/Services/DevolucaoService.php::reintegrarEstoque
 * @see app/Utils/TransactionUtil.php::addSellReturn (o núcleo espelhado — linha 6189)
 */
beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('vestuario_devolucoes')) {
        $this->markTestSkipped('Schema UltimatePOS/Vestuario ausente — rode na lane MySQL (estoque-pest) ou CT 100.');
    }

    $this->biz = EstoqueFixture::businessId();
    $this->userId = EstoqueFixture::userId($this->biz);
    session(['user.business_id' => $this->biz]);
});

it('devolução estorno_dinheiro REINTEGRA qty_available pela quantidade devolvida', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 8.0); // saldo pós-venda de 5

    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);

    app(DevolucaoService::class)->registrarDevolucao($this->biz, [
        'transaction_id' => $venda['transaction_id'],
        'transaction_sell_line_id' => $venda['sell_line_id'],
        'quantidade_devolvida' => 2,
        'valor_devolvido' => 40.0,
        'tipo' => 'estorno_dinheiro',
        'aprovacao_supervisor' => true,
        'motivo' => 'Defeito — item volta pro estoque',
        'processed_by_user_id' => $this->userId,
    ]);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(10.0); // 8 + 2
});

it('devolução credito_ficha também REINTEGRA (estoque é reason-agnostic)', function () {
    $loc = EstoqueFixture::locationId($this->biz);
    $produto = EstoqueFixture::singleProduct($this->biz);
    EstoqueFixture::setStock($produto, 0, $loc, 5.0);

    $venda = EstoqueFixture::saleWithLine($produto, 0, $loc, 5.0);
    $contactId = (int) (DB::table('contacts')->where('business_id', $this->biz)->value('id') ?? 1);

    app(DevolucaoService::class)->registrarDevolucao($this->biz, [
        'transaction_id' => $venda['transaction_id'],
        'transaction_sell_line_id' => $venda['sell_line_id'],
        'contact_id' => $contactId,
        'quantidade_devolvida' => 1,
        'valor_devolvido' => 20.0,
        'tipo' => 'credito_ficha',
        'motivo' => 'Arrependimento CDC Art. 49',
        'processed_by_user_id' => $this->userId,
    ]);

    expect(EstoqueFixture::currentStock($produto, 0, $loc))->toBe(6.0); // 5 + 1
});

it('Tier 0: devolver sell_line de OUTRO business é rejeitado (não reintegra)', function () {
    $biz2 = EstoqueFixture::secondBusinessId();
    if ($biz2 === null) {
        $this->markTestSkipped('Sem 2º business semeado pra testar cross-tenant.');
    }

    // Venda pertence ao biz=2; tentamos devolver "como" biz=1.
    $loc2 = EstoqueFixture::locationId($biz2);
    $prod2 = EstoqueFixture::singleProduct($biz2);
    EstoqueFixture::setStock($prod2, 0, $loc2, 8.0);
    $venda2 = EstoqueFixture::saleWithLine($prod2, 0, $loc2, 5.0);

    expect(fn () => app(DevolucaoService::class)->registrarDevolucao($this->biz, [
        'transaction_id' => $venda2['transaction_id'],
        'transaction_sell_line_id' => $venda2['sell_line_id'],
        'quantidade_devolvida' => 2,
        'valor_devolvido' => 40.0,
        'tipo' => 'estorno_dinheiro',
        'aprovacao_supervisor' => true,
        'motivo' => 'Tentativa cross-tenant',
        'processed_by_user_id' => $this->userId,
    ]))->toThrow(InvalidArgumentException::class);

    // Saldo do biz=2 intocado (transação abortada — nada reintegrado).
    expect(EstoqueFixture::currentStock($prod2, 0, $loc2))->toBe(8.0);
});
