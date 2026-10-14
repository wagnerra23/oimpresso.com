<?php

declare(strict_types=1);

// @covers-us R-NFE-022 — bloqueio por NCM padrão sempre oferece a saída e nunca emite sem NCM (playbook Fiscal thread 16).

use App\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Exceptions\NcmPadraoAusenteException;
use Modules\NfeBrasil\Services\NfeService;
use Modules\NfeBrasil\Services\Tributacao\SaudeFiscalService;

uses(Tests\TestCase::class);

/**
 * Casos derivados do aceite R-NFE-022 do playbook (D-SUPORTE: "NF-e sem NCM não é saída; a saída é o
 * NCM padrão da empresa"), não do NfeService. MySQL · tenant 98 (ADR 0358). O estado que o teste muda
 * (config fiscal e `business.ncm_padrao`) é guardado antes e devolvido depois. Notas criadas pelo
 * controle positivo são removidas por id.
 */

function blqBiz(): int
{
    return test()->seededTenant()->id;
}

function blqNcmPadrao(?string $ncm): void
{
    $biz = blqBiz();
    DB::table('nfe_business_configs')->updateOrInsert(
        ['business_id' => $biz],
        ['tributacao_default' => json_encode($ncm === null ? ['cfop' => '5102'] : ['ncm_default' => $ncm, 'cfop' => '5102']), 'updated_at' => now()],
    );
    // `business.ncm_padrao` é NOT NULL DEFAULT '' (mysql-schema.sql): "sem NCM" é string vazia.
    DB::table('business')->where('id', $biz)->update(['ncm_padrao' => $ncm ?? '']);
}

function blqVenda(): Transaction
{
    return (new Transaction())->forceFill(['id' => 9_160_001, 'business_id' => blqBiz(), 'type' => 'sell', 'final_total' => 10.00]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_business_configs') || ! Schema::hasColumn('business', 'ncm_padrao')) {
        $this->markTestSkipped('MySQL-only: precisa de nfe_business_configs e business.ncm_padrao reais.');
    }
    $biz = blqBiz();
    $this->blqConfig = DB::table('nfe_business_configs')->where('business_id', $biz)->first();
    $this->blqNcmBusiness = DB::table('business')->where('id', $biz)->value('ncm_padrao');
    $this->blqMaxNota = (int) DB::table('nfe_emissoes')->max('id');
});

afterEach(function () {
    if (! property_exists($this, 'blqConfig')) {
        return;
    }
    $biz = blqBiz();
    DB::table('nfe_emissoes')->where('business_id', $biz)->where('id', '>', $this->blqMaxNota)->delete();
    DB::table('nfe_business_configs')->where('business_id', $biz)->delete();
    if ($this->blqConfig !== null) {
        DB::table('nfe_business_configs')->insert((array) $this->blqConfig);
    }
    DB::table('business')->where('id', $biz)->update(['ncm_padrao' => $this->blqNcmBusiness]);
});

it('R-NFE-022 · bloqueio de NCM com saída e sem emissão', function () {
    blqNcmPadrao(null);
    $antes = DB::table('nfe_emissoes')->where('business_id', blqBiz())->count();

    try {
        app(NfeService::class)->emitirParaTransaction(blqVenda(), '65');
        $this->fail('Emitiu sem NCM padrão.');
    } catch (NcmPadraoAusenteException $e) {
        $acao = $e->acao();
        expect($acao['label'])->toBe('Escolher NCM padrão');
        expect($acao['campo'])->toBe('ncm_default');
        expect(str_ends_with((string) $acao['url'], '/nfe-brasil/tributacao/config-default#ncm_default'))->toBeTrue();
        // Quem já capturava RuntimeException com esta frase continua igual.
        expect($e)->toBeInstanceOf(RuntimeException::class);
        expect(str_contains($e->getMessage(), 'sem NCM padrão configurado'))->toBeTrue();
    }

    expect(DB::table('nfe_emissoes')->where('business_id', blqBiz())->count())->toBe($antes);
});

it('R-NFE-022 · a saída do erro é a mesma tela que a Saúde fiscal aponta', function () {
    blqNcmPadrao(null);

    $item = collect(app(SaudeFiscalService::class)->avaliar(blqBiz())['itens'])->firstWhere('tipo', 'ncm_padrao_invalido');

    expect($item)->not->toBeNull();
    expect($item['rota'])->toBe(NcmPadraoAusenteException::ROTA);
});

it('R-NFE-022 · controle positivo: com NCM padrão válido o bloqueio de NCM não acontece', function () {
    blqNcmPadrao('49111090');

    $erro = null;
    try {
        // Pode falhar adiante (certificado, SEFAZ) — o caso só prova que NÃO é o bloqueio de NCM.
        app(NfeService::class)->emitirParaTransaction(blqVenda(), '65');
    } catch (Throwable $e) {
        $erro = $e;
    }

    expect($erro)->not->toBeInstanceOf(NcmPadraoAusenteException::class);
});

it('R-NFE-022 · a emissão manual devolve 422 com a ação, não 500 com texto', function () {
    $biz = blqBiz();
    $local = DB::table('business_locations')->where('business_id', $biz)->value('id');
    $cliente = DB::table('contacts')->where('business_id', $biz)->value('id');
    $dono = DB::table('business')->where('id', $biz)->value('owner_id');
    if (! $local || ! $cliente || ! $dono) {
        $this->markTestSkipped('tenant 98 sem location/contato/dono para a venda de teste');
    }
    blqNcmPadrao(null);

    $tx = DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => $local, 'contact_id' => $cliente, 'created_by' => $dono,
        'type' => 'sell', 'status' => 'final', 'payment_status' => 'paid', 'final_total' => 10.00,
        'invoice_no' => 'BLQ16-' . uniqid(), 'transaction_date' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);

    try {
        $this->actingAs(test()->usuarioComPermissoes([]))
            ->withSession(['business.id' => $biz, 'user.business_id' => $biz]);

        $r = $this->postJson("/nfe-brasil/transactions/{$tx}/emitir", ['modelo' => '65']);

        $r->assertStatus(422);
        $r->assertJsonPath('error', NcmPadraoAusenteException::ERRO);
        $r->assertJsonPath('acao.label', 'Escolher NCM padrão');
        expect(str_ends_with((string) $r->json('acao.url'), '/nfe-brasil/tributacao/config-default#ncm_default'))->toBeTrue();
        expect(DB::table('nfe_emissoes')->where('business_id', $biz)->where('transaction_id', $tx)->count())->toBe(0);
    } finally {
        DB::table('transactions')->where('id', $tx)->delete();
    }
});
