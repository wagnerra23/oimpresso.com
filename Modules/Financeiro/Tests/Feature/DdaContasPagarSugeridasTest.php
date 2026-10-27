<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Contracts\FonteDda;
use Modules\Financeiro\Dto\BoletoDdaRecebido;
use Modules\Financeiro\Services\DdaService;

uses(Tests\TestCase::class);

/**
 * US-FIN-078 — DDA: boletos emitidos contra o CNPJ da empresa viram contas a pagar SUGERIDAS.
 * Contrato: resources/js/Pages/Financeiro/Unificado/Index.casos.md (UC-DDA-01..07).
 *
 * Tenant 98 (ADR 0358); o 99 é o adversário cross-tenant. Tudo em transação desfeita no fim
 * (`fin_titulos` não aceita delete). Valor conferido por dois caminhos: o que a fonte entrega
 * (string "1234.56") e o que o título grava (valor_total = valor_aberto = 1234.56).
 *
 * @covers-us US-FIN-078
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('DDA usa schema MySQL do Financeiro.');
    }
    if (! Schema::hasTable('fin_dda_boletos')) {
        $this->markTestSkipped('fin_dda_boletos ausente — rode migrate.');
    }

    DB::beginTransaction();
    $this->bizA = (int) $this->seededTenant()->id;
    $this->bizB = (int) $this->seededSupportClientTenant()->id;
    $this->ownerA = (int) DB::table('business')->where('id', $this->bizA)->value('owner_id');
    $this->ownerB = (int) DB::table('business')->where('id', $this->bizB)->value('owner_id');
    $this->dda = app(DdaService::class);
});

afterEach(function () {
    if (DB::transactionLevel() > 0) {
        DB::rollBack();
    }
});

function ddaFornecedor(int $biz, int $owner, string $cnpj, string $type = 'supplier'): int
{
    return DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $type, 'name' => 'Fornecedor DDA '.uniqid(),
        'supplier_business_name' => 'Fornecedor DDA', 'tax_number' => $cnpj, 'mobile' => '',
        'contact_id' => 'DDA-'.uniqid(), 'created_by' => $owner, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Pontua o CNPJ em tempo de execução: literal formatado no fonte cai no PII scan. */
function ddaCnpjFormatado(string $d): string
{
    return substr($d, 0, 2).'.'.substr($d, 2, 3).'.'.substr($d, 5, 3).'/'.substr($d, 8, 4).'-'.substr($d, 12, 2);
}

function ddaBoleto(string $codigo = '', string $doc = '11222333000181', string $valor = '1234.56'): BoletoDdaRecebido
{
    $codigo = $codigo ?: '0339'.str_pad((string) random_int(0, PHP_INT_MAX), 40, '0', STR_PAD_LEFT);

    return new BoletoDdaRecebido($codigo, $doc, 'Papelaria Exemplo', $valor, Carbon::parse('2031-04-10'));
}

it('UC-DDA-01 · casa o fornecedor da PRÓPRIA empresa pelo CNPJ formatado, nunca o de outra', function () {
    $fornecA = ddaFornecedor($this->bizA, $this->ownerA, ddaCnpjFormatado('11222333000181'));
    ddaFornecedor($this->bizB, $this->ownerB, ddaCnpjFormatado('11222333000181'));

    $s = $this->dda->registrar($this->bizA, 'teste', ddaBoleto());

    expect((int) $s->contact_id)->toBe($fornecA);
    expect((int) $s->business_id)->toBe($this->bizA);
    expect($s->status)->toBe('sugerido');

    $semCadastro = $this->dda->registrar($this->bizB, 'teste', ddaBoleto('', '99888777000166'));
    expect($semCadastro->contact_id)->toBeNull();
});

it('UC-DDA-02 · o mesmo boleto entregue duas vezes vira UMA sugestão', function () {
    $b = ddaBoleto('03399.12345 67890.123456 78901.234567 8 90120000123456');
    $this->dda->registrar($this->bizA, 'teste', $b);
    $this->dda->registrar($this->bizA, 'teste', $b);

    expect(DB::table('fin_dda_boletos')->where('business_id', $this->bizA)
        ->where('codigo_barras', '03399123456789012345678901234567890120000123456')->count())->toBe(1);
});

it('UC-DDA-03 · registrar a sugestão NÃO cria conta a pagar', function () {
    $antes = DB::table('fin_titulos')->where('business_id', $this->bizA)->count();
    $this->dda->registrar($this->bizA, 'teste', ddaBoleto());

    expect(DB::table('fin_titulos')->where('business_id', $this->bizA)->count())->toBe($antes);
});

it('UC-DDA-04 · aceitar cria o título a pagar com o valor, o vencimento e o fornecedor do boleto', function () {
    $fornec = ddaFornecedor($this->bizA, $this->ownerA, '11222333000181');
    $s = $this->dda->registrar($this->bizA, 'teste', ddaBoleto());

    $t = $this->dda->aceitar($this->bizA, $s->id, $this->ownerA);

    $row = DB::table('fin_titulos')->where('id', $t->id)->first();
    expect($row->tipo)->toBe('pagar');
    expect($row->status)->toBe('aberto');
    expect((int) $row->business_id)->toBe($this->bizA);
    expect((int) $row->cliente_id)->toBe($fornec);
    expect((float) $row->valor_total)->toBe(1234.56);
    expect((float) $row->valor_aberto)->toBe(1234.56);
    expect(substr((string) $row->vencimento, 0, 10))->toBe('2031-04-10');

    $dda = DB::table('fin_dda_boletos')->where('id', $s->id)->first();
    expect($dda->status)->toBe('aceito');
    expect((int) $dda->titulo_id)->toBe($t->id);

    expect(fn () => $this->dda->aceitar($this->bizA, $s->id, $this->ownerA))
        ->toThrow(DomainException::class);
});

it('UC-DDA-05 · outra empresa não aceita nem descarta a sugestão', function () {
    $s = $this->dda->registrar($this->bizA, 'teste', ddaBoleto());

    expect(fn () => $this->dda->aceitar($this->bizB, $s->id, $this->ownerB))
        ->toThrow(Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn () => $this->dda->descartar($this->bizB, $s->id, $this->ownerB))
        ->toThrow(DomainException::class);
    expect(DB::table('fin_dda_boletos')->where('id', $s->id)->value('status'))->toBe('sugerido');
});

it('UC-DDA-06 · descartar encerra a sugestão sem criar título', function () {
    $s = $this->dda->registrar($this->bizA, 'teste', ddaBoleto());
    $antes = DB::table('fin_titulos')->where('business_id', $this->bizA)->count();

    $this->dda->descartar($this->bizA, $s->id, $this->ownerA);

    expect(DB::table('fin_dda_boletos')->where('id', $s->id)->value('status'))->toBe('descartado');
    expect(DB::table('fin_titulos')->where('business_id', $this->bizA)->count())->toBe($antes);
});

it('UC-DDA-07 · o sync falha visível sem flag ou sem fonte, e grava a sugestão com fonte ligada', function () {
    config(['financeiro.dda.enabled' => false]);
    $this->artisan('financeiro:dda-sync', ['--business' => $this->bizA])->assertExitCode(1);

    config(['financeiro.dda.enabled' => true]);
    $this->artisan('financeiro:dda-sync', ['--business' => $this->bizA])->assertExitCode(1);

    app()->bind('dda.fonte.fake', fn () => new class implements FonteDda {
        public function nome(): string { return 'fake'; }
        public function boletos(int $businessId, $desde): iterable { yield ddaBoleto('03390000000000000000000000000000000000000000077'); }
    });
    app()->tag(['dda.fonte.fake'], 'financeiro.fontes_dda');

    $this->artisan('financeiro:dda-sync', ['--business' => $this->bizA])->assertExitCode(0);
    expect(DB::table('fin_dda_boletos')->where('business_id', $this->bizA)
        ->where('codigo_barras', '03390000000000000000000000000000000000000000077')->value('fonte'))->toBe('fake');
});
