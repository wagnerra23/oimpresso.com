<?php

declare(strict_types=1);

use App\Console\Commands\Sells\SmokeDailyCommand;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Smoke diário dos clientes vivos — `SmokeDailyCommand::avaliarClientesVivos`.
 *
 * ÂNCORA: decisão [W] 2026-11-15 (sessão guardião dos clientes vivos): o smoke diário acusa
 * quando a ROTA LIVRE para de vender por mais que a janela (padrão 5 dias, medida em produção:
 * maior lacuna sem venda em 365 dias = 3), com ALERT no log e o comando saindo FAILURE; a WR2
 * é só informativa enquanto o business dela for placeholder.
 *
 * Tenants: 98 (canônico, ADR 0358) e um business inexistente para o caso "zero vendas".
 * Nunca biz=4 — o business da ROTA LIVRE entra por parâmetro.
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

function sdcVenda(int $bizId, string $quando): void
{
    $userId = (int) DB::table('users')->where('business_id', $bizId)->value('id');
    DB::table('transactions')->insert([
        'business_id' => $bizId,
        'location_id' => EstoqueFixture::locationId($bizId),
        'created_by' => $userId,
        'type' => 'sell',
        'status' => 'final',
        'payment_status' => 'paid',
        'invoice_no' => 'SDC' . strtoupper(substr(uniqid(), -6)),
        'transaction_date' => $quando,
        'total_before_tax' => 10,
        'final_total' => 10,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sdcVendasNaJanela(int $bizId, int $dias): int
{
    return DB::table('transactions')
        ->where('business_id', $bizId)->where('type', 'sell')->where('status', 'final')
        ->whereDate('transaction_date', '>=', now()->subDays($dias))
        ->count();
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('transactions')) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->bizId = (int) $this->seededTenant()->id;
});

it('acusa ALERT cliente=rota-livre quando o business não vendeu nada na janela', function () {
    $semVendas = (int) DB::table('business')->max('id') + 1000;

    $r = SmokeDailyCommand::avaliarClientesVivos($semVendas, 5, $this->bizId);

    expect($r['falhas'])->toContain("ALERT cliente=rota-livre: biz={$semVendas} zero vendas em 5 dias");
    expect(implode("\n", $r['linhas']))->toContain("rota-livre biz={$semVendas} vendas em 5d=0");
});

it('não acusa quando há venda final dentro da janela, e conta exatamente a venda inserida', function () {
    $antes = sdcVendasNaJanela($this->bizId, 5);
    sdcVenda($this->bizId, now()->subDays(2)->toDateTimeString());

    $r = SmokeDailyCommand::avaliarClientesVivos($this->bizId, 5, $this->bizId);

    expect(collect($r['falhas'])->filter(fn ($f) => str_contains($f, 'cliente=rota-livre'))->all())->toBe([]);
    $esperado = $antes + 1;
    expect(implode("\n", $r['linhas']))->toContain("rota-livre biz={$this->bizId} vendas em 5d={$esperado}");
});

it('venda fora da janela não conta: a janela é o que decide, não a existência de venda', function () {
    $antes = sdcVendasNaJanela($this->bizId, 5);
    sdcVenda($this->bizId, now()->subDays(20)->toDateTimeString());

    $r = SmokeDailyCommand::avaliarClientesVivos($this->bizId, 5, $this->bizId);

    expect(implode("\n", $r['linhas']))->toContain("rota-livre biz={$this->bizId} vendas em 5d={$antes}");
});

it('WR2 placeholder nunca reprova por falta de marcação: só informa', function () {
    $r = SmokeDailyCommand::avaliarClientesVivos($this->bizId, 5, (int) DB::table('business')->max('id') + 1000);

    expect(collect($r['falhas'])->filter(fn ($f) => str_contains($f, 'cliente=wr2'))->all())->toBe([]);
    expect(implode("\n", $r['linhas']))->toMatch('/clientes vivos: wr2 (biz=\d+ marcações em 7d=0|— módulo Ponto desabilitado)/');
});

it('fora de produção o comando não roda o check de clientes vivos', function () {
    $this->artisan('sells:smoke-daily')
        ->expectsOutputToContain('clientes vivos: só em produção');
});
