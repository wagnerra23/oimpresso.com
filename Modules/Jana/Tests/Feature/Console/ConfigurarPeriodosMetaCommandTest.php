<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Services\ApuracaoService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do `jana:metas:configurar-periodos`.
 *
 * Sem período, `ApuracaoService::projecao()` devolve `null` e o farol vira `cinza` — os
 * cards mostram o realizado e nada de "% do alvo". Medido em produção: as 5 metas de
 * biz=1 estavam sem período.
 *
 * O teste que carrega o peso é o de que o comando NÃO INVENTA ALVO. `valor_alvo` é NOT
 * NULL e alvo é decisão de negócio — derivar do histórico produziria um número que
 * ninguém escolheu, contra o qual um farol vermelho não significa nada.
 */
function bizPeriodos(): int
{
    return 98;
}

function criaMetaPeriodo(string $nome, string $unidade = 'R$'): Meta
{
    return Meta::withoutGlobalScopes()->create([
        'business_id'    => bizPeriodos(),
        'slug'           => 'periodo-teste-'.uniqid(),
        'nome'           => $nome,
        'unidade'        => $unidade,
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);
}

it('está REGISTRADO no Artisan — registry vivo, não class_exists', function () {
    expect(array_keys(Artisan::all()))->toContain('jana:metas:configurar-periodos');
});

it('RECUSA rodar sem --business (Tier 0)', function () {
    expect(Artisan::call('jana:metas:configurar-periodos'))->toBe(1);
});

it('NÃO INVENTA ALVO — sem --alvo-*, nenhum período é criado', function () {
    $meta = criaMetaPeriodo('Faturamento mensal');

    expect(Artisan::call('jana:metas:configurar-periodos', ['--business' => (string) bizPeriodos()]))->toBe(0);

    // O ponto do teste. Um comando "prestativo" derivaria o alvo da média histórica —
    // e o farol passaria a medir contra um número que o dono nunca escolheu.
    expect(MetaPeriodo::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);
    expect(Artisan::output())->toContain('--alvo-faturamento');
});

it('com --alvo-* cria o período do mês corrente e o farol SAI de cinza', function () {
    $meta = criaMetaPeriodo('Faturamento mensal');

    // ANTES: sem período, o farol é cinza por contrato — `projecao()` devolve null.
    expect(app(ApuracaoService::class)->farol($meta->fresh()))->toBe('cinza');

    expect(Artisan::call('jana:metas:configurar-periodos', [
        '--business'         => (string) bizPeriodos(),
        '--alvo-faturamento' => '5000',
    ]))->toBe(0);

    $p = MetaPeriodo::withoutGlobalScopes()->where('meta_id', $meta->id)->firstOrFail();

    expect((float) $p->valor_alvo)->toBe(5000.0);
    expect($p->tipo_periodo)->toBe('mes');
    expect((string) $p->data_ini)->toContain(now()->startOfMonth()->format('Y-m-d'));
    expect((string) $p->data_fim)->toContain(now()->endOfMonth()->format('Y-m-d'));
    expect($p->trajetoria)->toBe('linear');
});

it('RECUSA alvo não numérico ou <= 0, em vez de gravar lixo', function () {
    criaMetaPeriodo('Faturamento mensal');

    expect(Artisan::call('jana:metas:configurar-periodos', [
        '--business' => (string) bizPeriodos(), '--alvo-faturamento' => 'muito',
    ]))->toBe(1);

    expect(Artisan::call('jana:metas:configurar-periodos', [
        '--business' => (string) bizPeriodos(), '--alvo-faturamento' => '0',
    ]))->toBe(1);
});

it('RECUSA trajetória fora do enum do schema', function () {
    expect(Artisan::call('jana:metas:configurar-periodos', [
        '--business' => (string) bizPeriodos(), '--trajetoria' => 'exponencialzinha',
    ]))->toBe(1);
});

it('--dry-run NÃO grava', function () {
    $meta = criaMetaPeriodo('Vendas no mês', 'qtd');

    Artisan::call('jana:metas:configurar-periodos', [
        '--business' => (string) bizPeriodos(), '--alvo-vendas' => '20', '--dry-run' => true,
    ]);

    expect(MetaPeriodo::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);
});

it('é idempotente — rodar duas vezes não duplica o período da mesma janela', function () {
    $meta = criaMetaPeriodo('Clientes atendidos', 'qtd');
    $args = ['--business' => (string) bizPeriodos(), '--alvo-clientes' => '15'];

    Artisan::call('jana:metas:configurar-periodos', $args);
    Artisan::call('jana:metas:configurar-periodos', $args);

    expect(MetaPeriodo::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(1);
});

it('não configura meta de OUTRO business (Tier 0 cross-tenant)', function () {
    $alheia = Meta::withoutGlobalScopes()->create([
        'business_id'    => bizPeriodos() + 1,
        'slug'           => 'periodo-alheia-'.uniqid(),
        'nome'           => 'Faturamento mensal',
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);

    Artisan::call('jana:metas:configurar-periodos', [
        '--business' => (string) bizPeriodos(), '--alvo-faturamento' => '5000',
    ]);

    expect(MetaPeriodo::withoutGlobalScopes()->where('meta_id', $alheia->id)->count())->toBe(0);

    Meta::withoutGlobalScopes()->where('id', $alheia->id)->delete();
});
