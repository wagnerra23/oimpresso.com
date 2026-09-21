<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Teste de INTEGRAÇÃO da cadeia inteira — a pergunta que os testes por comando não
 * respondem: *"depois de rodar os dois, o gráfico aparece?"*.
 *
 * Provar cada peça isolada não prova a cadeia. Aqui se roda o fluxo real —
 * `configurar-fontes` → `backfill-apuracoes` → o que o `IndexController` entrega à tela —
 * e se afere o critério que o componente usa de verdade.
 *
 * O critério não é opinião: `Index.tsx:99` faz `if (dados.length < 2) return "Sem
 * histórico"`. Então **≥ 2 pontos** é a fronteira entre a curva existir e não existir.
 */
function bizCadeia(): int
{
    return 98;
}

it('CADEIA COMPLETA: sem fonte → configurar → backfill → a tela tem ≥2 pontos e desenha', function () {
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id'    => bizCadeia(),
        'slug'           => 'cadeia-teste-'.uniqid(),
        'nome'           => 'Faturamento mensal',
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);

    // ── Estado inicial: é o de produção hoje — meta sem fonte, sem apuração.
    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);

    // ── Passo 1: configurar a fonte.
    expect(Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizCadeia()]))->toBe(0);

    // ── Passo 2: backfill de 12 janelas.
    expect(Artisan::call('jana:metas:backfill-apuracoes', [
        '--business' => (string) bizCadeia(),
        '--meta'     => (string) $meta->id,
        '--janelas'  => 12,
    ]))->toBe(0);

    // ── Passo 3: o que o `IndexController` entrega à tela. Réplica do eager-load real
    //    (`IndexController:73`): orderBy('data_ref') + limit(12).
    $daTela = MetaApuracao::withoutGlobalScopes()
        ->where('meta_id', $meta->id)
        ->orderBy('data_ref')
        ->limit(12)
        ->get();

    // O CRITÉRIO REAL do componente, não um proxy.
    expect($daTela->count())->toBeGreaterThanOrEqual(2);
    expect($daTela->count())->toBe(12);

    // Cada ponto é uma janela distinta — 12 linhas com a MESMA data_ref não desenham
    // série nenhuma, desenham um ponto repetido.
    $datas = $daTela->pluck('data_ref')->map(fn ($d) => (string) $d)->unique();
    expect($datas)->toHaveCount(12);

    // E vêm em ordem cronológica: a curva lida da esquerda para a direita depende disso.
    $ordenadas = $daTela->pluck('data_ref')->map(fn ($d) => (string) $d)->values()->all();
    $copia     = $ordenadas;
    sort($copia);
    expect($ordenadas)->toBe($copia);
});

it('CONTROLE: sem o passo de configurar-fontes, o backfill NÃO produz ponto nenhum', function () {
    // Sem este controle, o teste acima passaria mesmo que `configurar-fontes` fosse
    // inócuo — bastaria o backfill funcionar sozinho. Aqui se prova que o passo 1 é
    // NECESSÁRIO: sem fonte o Service lança e nada é gravado.
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id'    => bizCadeia(),
        'slug'           => 'cadeia-controle-'.uniqid(),
        'nome'           => 'Meta sem definição canônica que o configurar-fontes ignora',
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizCadeia()]);

    // O nome não casa nenhuma definição, então segue sem fonte — e o backfill falha.
    $code = Artisan::call('jana:metas:backfill-apuracoes', [
        '--business' => (string) bizCadeia(),
        '--meta'     => (string) $meta->id,
        '--janelas'  => 3,
    ]);

    expect($code)->toBe(1);
    expect(Artisan::output())->toContain('não tem MetaFonte configurada');
    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);
});
