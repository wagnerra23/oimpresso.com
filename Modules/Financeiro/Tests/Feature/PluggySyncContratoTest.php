<?php

declare(strict_types=1);
// Cobre UC-FCC-17..18 (resources/js/Pages/Financeiro/Conciliacao/Index.casos.md · US-FIN-074).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Http\Controllers\ConciliacaoController;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Sync do Pluggy → fin_extrato_lancamentos → Conciliação. Regra mestre de VALOR: o sinal de
 * cada lançamento é conferido por DOIS caminhos — a linha gravada (`tipo` + `valor`) e o valor
 * com sinal que a Conciliação monta para ela (`normalizeApi`, o mesmo método da tela).
 *
 * Tenant 98 (seededTenant, ADR 0358) contra o 99. NUNCA biz=4. HTTP do Pluggy é fake.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: fin_* depende de schema MySQL.');
    }
    if (! Schema::hasTable('fin_extrato_lancamentos') || ! Schema::hasTable('fin_contas_bancarias')) {
        $this->markTestSkipped('fin_extrato_lancamentos/fin_contas_bancarias ausentes — rode migrate.');
    }
    $this->b98 = (int) $this->seededTenant()->id;
    $this->b99 = (int) $this->seededSupportClientTenant()->id;

    config([
        'financeiro.pluggy.enabled'        => true,
        'financeiro.pluggy.client_id'      => 'pest-client',
        'financeiro.pluggy.client_secret'  => 'pest-secret',
        'financeiro.pluggy.base_url'       => 'https://api.pluggy.ai',
        'financeiro.pluggy.retry_times'    => 0,
        'financeiro.pluggy.force_mock'     => false,
        'financeiro.pluggy.sync_window_days' => 30,
    ]);
    Cache::flush();
});

/** Conta bancária do tenant; com `$pluggyAccountId` ela fica vinculada ao Pluggy. */
function pluggyConta(int $biz, ?string $pluggyAccountId): int
{
    $accountId = DB::table('accounts')->insertGetId([
        'business_id' => $biz, 'name' => 'PEST-PLUGGY-'.uniqid(), 'account_number' => '999',
        'created_at'  => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('fin_contas_bancarias')->insertGetId([
        'business_id'               => $biz,
        'account_id'                => $accountId,
        'banco_codigo'              => '341',
        'agencia'                   => '0001',
        'carteira'                  => '109',
        'beneficiario_documento'    => '00.000.000/0000-00', // pii-allowlist — CNPJ fixture de teste (nao-PII real)
        'beneficiario_razao_social' => 'Teste Pluggy',
        'metadata'                  => $pluggyAccountId ? json_encode(['pluggy' => ['account_id' => $pluggyAccountId]]) : null,
        'created_at'                => now(),
        'updated_at'                => now(),
    ]);
}

/**
 * Pluggy fake: 2 páginas — débito de 50,00 sem `type` e crédito de 120,00; 2ª página com débito
 * de cartão. `$runs` repete as 2 páginas: um 2º `Http::fake` NÃO substitui o 1º (o stub antigo
 * casa primeiro), então os runs do mesmo teste precisam caber numa sequência só.
 */
function pluggyFakeDuasPaginas(string $sufixo, int $runs = 1): void
{
    $seq = Http::sequence();
    for ($i = 0; $i < $runs; $i++) {
        $seq->push(['page' => 1, 'totalPages' => 2, 'results' => [
            ['id' => "tx-deb-{$sufixo}", 'date' => '2031-03-10', 'description' => 'PEST boleto pago', 'amount' => -50.00],
            ['id' => "tx-cre-{$sufixo}", 'date' => '2031-03-11', 'description' => 'PEST PIX recebido', 'amount' => 120.00, 'type' => 'CREDIT'],
        ]], 200)->push(['page' => 2, 'totalPages' => 2, 'results' => [
            // Cartão: amount positivo, mas `type` DEBIT — o `type` vale.
            ['id' => "tx-car-{$sufixo}", 'date' => '2031-03-12', 'description' => 'PEST compra cartão', 'amount' => 30.00, 'type' => 'DEBIT'],
        ]], 200);
    }

    Http::fake([
        'api.pluggy.ai/auth' => Http::response(['apiKey' => 'fake-key'], 200),
        'api.pluggy.ai/transactions*' => $seq,
    ]);
}

/** Valor com sinal que a Conciliação monta para a linha (mesmo método da tela). */
function pluggyValorNaConciliacao(int $linhaId): float
{
    $linha = DB::table('fin_extrato_lancamentos')->where('id', $linhaId)->first();
    $ref = new ReflectionMethod(ConciliacaoController::class, 'normalizeApi');
    $ref->setAccessible(true);

    return (float) $ref->invoke(new ConciliacaoController(), $linha)['valor'];
}

it('UC-FCC-17 · extrato do Pluggy chega com tipo C/D e o sinal certo na conciliação, lendo todas as páginas', function () {
    $sufixo = uniqid();
    $conta = pluggyConta($this->b98, "acc-{$sufixo}");
    pluggyFakeDuasPaginas($sufixo);

    expect(Artisan::call('financeiro:pluggy-sync', ['--conta' => $conta]))->toBe(0);

    $linhas = DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)
        ->orderBy('data')->get()->keyBy('descricao');
    expect($linhas)->toHaveCount(3); // a 2ª página entrou

    // Caminho 1: a linha gravada.
    expect($linhas['PEST boleto pago']->tipo)->toBe('D');
    expect((float) $linhas['PEST boleto pago']->valor)->toBe(50.00);
    expect($linhas['PEST PIX recebido']->tipo)->toBe('C');
    expect((float) $linhas['PEST PIX recebido']->valor)->toBe(120.00);
    expect($linhas['PEST compra cartão']->tipo)->toBe('D');

    // Caminho 2: o valor que a Conciliação mostra.
    expect(pluggyValorNaConciliacao((int) $linhas['PEST boleto pago']->id))->toBe(-50.00);
    expect(pluggyValorNaConciliacao((int) $linhas['PEST PIX recebido']->id))->toBe(120.00);
    expect(pluggyValorNaConciliacao((int) $linhas['PEST compra cartão']->id))->toBe(-30.00);

    // Soma à mão das fixtures (−50 + 120 − 30 = 40) = soma pela Conciliação.
    $soma = $linhas->sum(fn ($l) => pluggyValorNaConciliacao((int) $l->id));
    expect(round($soma, 2))->toBe(40.00);
});

it('UC-FCC-18 · com a flag desligada o comando não chama a API nem grava', function () {
    config(['financeiro.pluggy.enabled' => false]);
    $conta = pluggyConta($this->b98, 'acc-off-'.uniqid());
    Http::fake();

    expect(Artisan::call('financeiro:pluggy-sync'))->toBe(0);

    Http::assertNothingSent();
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta)->count())->toBe(0);
});

it('UC-FCC-18 · sync grava no business da própria conta, ignora conta sem vínculo e não duplica no 2º run [T0]', function () {
    $sufixo = uniqid();
    $conta98 = pluggyConta($this->b98, "acc-{$sufixo}");
    $conta99SemVinculo = pluggyConta($this->b99, null);
    pluggyFakeDuasPaginas($sufixo, runs: 2);

    expect(Artisan::call('financeiro:pluggy-sync'))->toBe(0);
    expect(Artisan::call('financeiro:pluggy-sync'))->toBe(0);

    $linhas = DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta98)->get();
    expect($linhas)->toHaveCount(3); // 2º run atualiza, não insere
    expect($linhas->pluck('business_id')->unique()->values()->all())->toBe([$this->b98]);
    expect(DB::table('fin_extrato_lancamentos')->where('conta_bancaria_id', $conta99SemVinculo)->count())->toBe(0);
});
