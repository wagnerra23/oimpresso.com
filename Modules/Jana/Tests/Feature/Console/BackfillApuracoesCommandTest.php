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
 * ⚠️ O tenant NÃO é escrito à mão aqui. Ele vem de `seededTenant()` (trait `WithSeededTenant`,
 * já no `Tests\TestCase`), que é quem sabe qual id o ambiente semeou — e que PULA com mensagem
 * acionável quando o seed não rodou, em vez de estourar FK no meio do teste. Fixar o número
 * fazia o teste passar no CT 100 (base clone de produção) e quebrar no CI (base fresca).
 * biz=4 é proibido em teste sem exceção; biz=1 é produção real.
 */
// Os 41 testes de `Modules/Jana/Tests/Feature` declaram isto — o `Pest.php` só aplica
// `TestCase` em `tests/Feature`, e o comentário dele avisa que módulo com suite própria
// precisa declarar. Sem a linha, o teste roda sem bootstrap e morre em
// `connection() on null` (foi o que aconteceu na primeira rodada: 6 failed, 0 assertions).
uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Tenant fictício por construção (ADR 0358), resolvido pelo trait `WithSeededTenant` que já
 * vive no `Tests\TestCase` — ele SKIPA com mensagem acionável se o seed não rodou, em vez de
 * estourar FK no meio do teste. biz=4 é proibido sem exceção; biz=1 é empresa real.
 */
function bizTesteBackfill(): int
{
    return (int) test()->seededTenant()->id;
}

/**
 * O adversário do caso cross-tenant, resolvido por CONSULTA — nunca por id escrito à mão.
 *
 * Medido no `.github/actions/pest-mysql-setup`: o CI semeia biz=1, biz=2 e biz=98, e declara
 * o biz=2 como "FV-F2 — Tier 0 cross-tenant". O 99 (SUPPORT_CLIENT_TENANT_ID) NÃO é semeado
 * lá; existe no CT 100, que é clone de produção. Era exatamente daí que vinha o defeito:
 * somar 1 ao canônico dá 99 — que existe no CT 100 e não no CI. Verde de um lado, FK
 * violation do outro, e a diferença invisível em qualquer leitura do teste.
 *
 * Sem um segundo business não há com quem colidir, e isso NÃO é o mesmo que "não colidiu":
 * o caso é PULADO em vez de passar por vacuidade.
 */
function bizTesteBackfillAlheio(): int
{
    // Preferência: o CLIENTE fictício do trait — o papel cross-tenant canônico. A constante
    // é lida pela CLASSE que usa o trait: em PHP 8.4, `Trait::CONST` direto é erro de RUNTIME
    // ("Cannot access trait constant directly"), e o `php -l` NÃO pega, porque é sintaxe válida.
    $outro = \App\Business::withoutGlobalScopes()
        ->whereKey(\Tests\TestCase::SUPPORT_CLIENT_TENANT_ID)
        ->value('id');

    // Ausente (o CI não o semeia): o menor business que não seja o seeded NEM tenant REAL.
    // biz=1 é a WR2 Sistemas e biz=4 é a ROTA LIVRE — as duas proibidas em teste, e no CT 100
    // a base é clone de produção que não se limpa entre runs. `orderBy` porque sem ordem
    // explícita a escolha é indefinida e o teste vira flaky por construção.
    $outro ??= \App\Business::withoutGlobalScopes()
        ->whereNotIn('id', [bizTesteBackfill(), 1, 4])
        ->orderBy('id')
        ->value('id');

    if ($outro === null) {
        \PHPUnit\Framework\Assert::markTestSkipped(
            'Sem um segundo business para o caso cross-tenant — pular é honesto; passar seria vacuidade.'
        );
    }

    return (int) $outro;
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
        'business_id'     => bizTesteBackfillAlheio(),
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
