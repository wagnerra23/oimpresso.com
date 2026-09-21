<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaFonte;

/**
 * Contrato do `jana:metas:backfill-apuracoes`.
 *
 * O comando nasceu porque as 5 metas de biz=1 estavam em "Aguardando apuração…" e
 * nenhum dos dois caminhos existentes resolvia: o `reapurar` só faz a janela de HOJE
 * (um ponto; o `Sparkline` exige >= 2) e o `ApurarMetaJob` cai numa fila cujo worker
 * está atrás de um gate desligado por padrão.
 *
 * ⚠️ O registro é provado por `Artisan::all()`, NÃO por `class_exists`. Essa distinção
 * é canon aqui: em 2026-07-28 dois comandos ficaram mortos por 2,4 meses porque o teste
 * media o disco (`app(Class::class)`) em vez do registry vivo.
 *
 * Tenant 98 — fictício por construção (ADR 0358). biz=4 é proibido em teste sem exceção;
 * biz=1 é produção real.
 */
// Os 41 testes de `Modules/Jana/Tests/Feature` declaram isto — o `Pest.php` só aplica
// `TestCase` em `tests/Feature`, e o comentário dele avisa que módulo com suite própria
// precisa declarar. Sem a linha, o teste roda sem bootstrap e morre em
// `connection() on null` (foi o que aconteceu na primeira rodada: 6 failed, 0 assertions).
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tenant fictício por construção (ADR 0358). Função em vez de `const` porque constante
 * de topo em arquivo Pest é global e colide entre arquivos da mesma suite.
 */
function bizTesteBackfill(): int
{
    return 98;
}

it('está REGISTRADO no Artisan — registry vivo, não class_exists', function () {
    expect(array_keys(Artisan::all()))->toContain('jana:metas:backfill-apuracoes');
});

it('RECUSA rodar sem --business (Tier 0: o CLI não tem session)', function () {
    $code = Artisan::call('jana:metas:backfill-apuracoes');

    expect($code)->toBe(1);
    expect(Artisan::output())->toContain('--business');
});

it('RECUSA --business não numérico, em vez de tratar como 0', function () {
    $code = Artisan::call('jana:metas:backfill-apuracoes', ['--business' => 'all']);

    // "all" parece inofensivo e seria o padrão de outros comandos do módulo — aqui
    // ele é recusado de propósito: apurar TODOS os tenants de uma vez é escrita em
    // massa cross-tenant, e isso não se faz por engano de digitação.
    expect($code)->toBe(1);
});

it('--dry-run calcula de VERDADE e NÃO grava — o contrato central', function () {
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id'     => bizTesteBackfill(),
        'slug'            => 'backfill-teste-'.uniqid(),
        'nome'            => 'Meta de teste do backfill',
        'unidade'         => 'R$',
        'tipo_agregacao'  => 'soma',
        'ativo'           => true,
        'origem'          => 'manual',
    ]);

    MetaFonte::withoutGlobalScopes()->create([
        'meta_id'     => $meta->id,
        'driver'      => 'sql',
        'config_json' => ['query' => 'SELECT 123.45 AS valor, :business_id AS biz'],
        'cadencia'    => 'diaria',
    ]);

    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);

    $code = Artisan::call('jana:metas:backfill-apuracoes', [
        '--business' => (string) bizTesteBackfill(),
        '--meta'     => (string) $meta->id,
        '--janelas'  => 3,
        '--dry-run'  => true,
    ]);

    $saida = Artisan::output();

    expect($code)->toBe(0);
    expect($saida)->toContain('DRY-RUN');
    // CONTROLE: o dry-run precisa provar que CALCULOU, não que pulou — se ele
    // silenciosamente não rodasse o driver, também não gravaria, e o assert de
    // "0 linhas" passaria por não-execução.
    expect($saida)->toContain('123,45');
    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);
});

it('sem --dry-run GRAVA uma linha por janela, e repetir não duplica (idempotente)', function () {
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id'     => bizTesteBackfill(),
        'slug'            => 'backfill-teste-'.uniqid(),
        'nome'            => 'Meta de teste do backfill',
        'unidade'         => 'R$',
        'tipo_agregacao'  => 'soma',
        'ativo'           => true,
        'origem'          => 'manual',
    ]);

    MetaFonte::withoutGlobalScopes()->create([
        'meta_id'     => $meta->id,
        'driver'      => 'sql',
        'config_json' => ['query' => 'SELECT 10.00 AS valor, :business_id AS biz'],
        'cadencia'    => 'diaria',
    ]);

    $args = ['--business' => (string) bizTesteBackfill(), '--meta' => (string) $meta->id, '--janelas' => 4];

    expect(Artisan::call('jana:metas:backfill-apuracoes', $args))->toBe(0);

    $apos1 = MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count();
    expect($apos1)->toBe(4);

    // Segunda passada: updateOrCreate na chave (meta_id, data_ref, hash) — mesma
    // contagem, nunca 8.
    expect(Artisan::call('jana:metas:backfill-apuracoes', $args))->toBe(0);

    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe($apos1);
});

it('não toca meta de OUTRO business (Tier 0 cross-tenant)', function () {
    $alheia = Meta::withoutGlobalScopes()->create([
        'business_id'     => (bizTesteBackfill() + 1),
        'slug'            => 'backfill-teste-alheia-'.uniqid(),
        'nome'            => 'Meta de outro tenant',
        'unidade'         => 'R$',
        'tipo_agregacao'  => 'soma',
        'ativo'           => true,
        'origem'          => 'manual',
    ]);

    MetaFonte::withoutGlobalScopes()->create([
        'meta_id'     => $alheia->id,
        'driver'      => 'sql',
        'config_json' => ['query' => 'SELECT 99.00 AS valor, :business_id AS biz'],
        'cadencia'    => 'diaria',
    ]);

    Artisan::call('jana:metas:backfill-apuracoes', ['--business' => (string) bizTesteBackfill(), '--janelas' => 2]);

    expect(MetaApuracao::withoutGlobalScopes()->where('meta_id', $alheia->id)->count())->toBe(0);

    MetaFonte::withoutGlobalScopes()->where('meta_id', $alheia->id)->delete();
    Meta::withoutGlobalScopes()->where('id', $alheia->id)->delete();
});
