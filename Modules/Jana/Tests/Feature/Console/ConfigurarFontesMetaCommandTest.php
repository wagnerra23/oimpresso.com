<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Drivers\Sql\SqlDriver;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaFonte;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do `jana:metas:configurar-fontes`.
 *
 * Medido em produção (2026-09-21): as 5 metas de biz=1 estavam SEM fonte, e por isso o
 * backfill reportava "não tem MetaFonte configurada" nas 5.
 *
 * O teste que mais importa aqui é o da MARGEM: ela tem de ficar de fora. `transaction_sell_lines`
 * não tem coluna de custo em produção, e `variations.default_purchase_price` é o custo de HOJE.
 * Uma fórmula inventada daria um % que parece certo e está errado.
 */
/**
 * Tenant fictício por construção (ADR 0358), resolvido pelo trait `WithSeededTenant` que já
 * vive no `Tests\TestCase` — ele SKIPA com mensagem acionável se o seed não rodou, em vez de
 * estourar FK no meio do teste. biz=4 é proibido sem exceção; biz=1 é empresa real.
 */
function bizFontes(): int
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
function bizFontesAlheio(): int
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
        ->whereNotIn('id', [bizFontes(), 1, 4])
        ->orderBy('id')
        ->value('id');

    if ($outro === null) {
        \PHPUnit\Framework\Assert::markTestSkipped(
            'Sem um segundo business para o caso cross-tenant — pular é honesto; passar seria vacuidade.'
        );
    }

    return (int) $outro;
}

function criaMetaFontes(string $nome, string $unidade = 'R$'): Meta
{
    return Meta::withoutGlobalScopes()->create([
        'business_id'    => bizFontes(),
        'slug'           => 'fontes-teste-'.uniqid(),
        'nome'           => $nome,
        'unidade'        => $unidade,
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);
}

it('está REGISTRADO no Artisan — registry vivo, não class_exists', function () {
    expect(array_keys(Artisan::all()))->toContain('jana:metas:configurar-fontes');
});

it('RECUSA rodar sem --business (Tier 0)', function () {
    expect(Artisan::call('jana:metas:configurar-fontes'))->toBe(1);
});

it('--dry-run NÃO grava fonte nenhuma', function () {
    $meta = criaMetaFontes('Faturamento mensal');

    Artisan::call('jana:metas:configurar-fontes', [
        '--business' => (string) bizFontes(),
        '--dry-run'  => true,
    ]);

    expect(MetaFonte::withoutGlobalScopes()->where('meta_id', $meta->id)->count())->toBe(0);
});

it('configura as 4 metas canônicas e PULA a margem', function () {
    $fat      = criaMetaFontes('Faturamento mensal');
    $ticket   = criaMetaFontes('Ticket médio');
    $vendas   = criaMetaFontes('Vendas no mês', 'qtd');
    $clientes = criaMetaFontes('Clientes atendidos', 'qtd');
    $margem   = criaMetaFontes('Margem de contribuição', '%');

    expect(Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizFontes()]))->toBe(0);

    foreach ([$fat, $ticket, $vendas, $clientes] as $m) {
        expect(MetaFonte::withoutGlobalScopes()->where('meta_id', $m->id)->count())
            ->toBe(1, "meta {$m->nome} deveria ter fonte");
    }

    // O ponto do teste: a margem NÃO é configurada. Inventar a fórmula é o defeito.
    expect(MetaFonte::withoutGlobalScopes()->where('meta_id', $margem->id)->count())->toBe(0);
});

it('as queries geradas PASSAM na validação Tier 0 do SqlDriver', function () {
    $meta = criaMetaFontes('Faturamento mensal');

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizFontes()]);

    $fonte = MetaFonte::withoutGlobalScopes()->where('meta_id', $meta->id)->firstOrFail();
    $query = $fonte->config_json['query'];

    // `validarQuery` recusa query de meta com business que não referencie :business_id —
    // é a garantia de isolamento dentro da própria query. Se o comando gerasse uma query
    // sem o bind, a apuração estouraria só na hora de rodar, em produção.
    expect($query)->toContain(':business_id');

    // `validarQuery` lança se a query for insegura. O `expect(...)->not->toThrow()` é o
    // que torna isso uma ASSERÇÃO — chamar o método solto deixaria o teste "risky",
    // porque o PHPUnit não veria asserção nenhuma no caminho feliz.
    expect(fn () => (new SqlDriver())->validarQuery($query, $meta))->not->toThrow(Exception::class);

    // A janela NÃO pode usar BETWEEN: `transaction_date` é DATETIME, e
    // `BETWEEN :ini AND :fim` compara contra meia-noite do dia final, descartando as
    // vendas do próprio último dia.
    expect($query)->not->toContain('BETWEEN');
    expect($query)->toContain('DATE_ADD(:data_fim, INTERVAL 1 DAY)');
});

it('é idempotente — rodar duas vezes não duplica nem muda a query', function () {
    $meta = criaMetaFontes('Vendas no mês', 'qtd');

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizFontes()]);
    $primeira = MetaFonte::withoutGlobalScopes()->where('meta_id', $meta->id)->firstOrFail();

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizFontes()]);
    $segunda = MetaFonte::withoutGlobalScopes()->where('meta_id', $meta->id)->get();

    expect($segunda)->toHaveCount(1);
    expect($segunda->first()->config_json['query'])->toBe($primeira->config_json['query']);
});

it('não configura meta de OUTRO business (Tier 0 cross-tenant)', function () {
    $alheia = Meta::withoutGlobalScopes()->create([
        'business_id'    => bizFontesAlheio(),
        'slug'           => 'fontes-teste-alheia-'.uniqid(),
        'nome'           => 'Faturamento mensal',
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizFontes()]);

    expect(MetaFonte::withoutGlobalScopes()->where('meta_id', $alheia->id)->count())->toBe(0);

    Meta::withoutGlobalScopes()->where('id', $alheia->id)->delete();
});
