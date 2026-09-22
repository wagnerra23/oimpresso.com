<?php

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * O VALOR apurado está certo? — o teste que os outros desta trilha não fazem.
 *
 * Os demais provam FORMA: a query passa na validação Tier 0, a cadeia produz 12 pontos,
 * o comando é idempotente. Nenhum prova o RECORTE: se o filtro `status='final'`
 * desaparecesse da query gerada, todos continuariam verdes — 12 pontos, só que com
 * valores errados. Forma certa e número errado é o pior desfecho, porque o dono decide
 * em cima do número.
 *
 * ⚠️ Isto importa mais do que parece porque `ConfigurarFontesMetaCommand` é hoje o
 * ÚNICO caminho de escrita de `MetaFonte` que existe. Medido no repo inteiro:
 * `jana.fontes.update` tem **0** chamadas em `.tsx`, `.blade.php`, `.ts` e `.js` — a
 * rota PATCH existe e nenhuma tela a usa; a view `fontes/show.blade.php` é
 * somente-leitura e o editor é a US-COPI-040, ainda não feita. Controle positivo da
 * mesma sonda: `metas.store` devolve 1 chamada, então ela discrimina.
 *
 * Cada linha da fixture existe para derrubar UM pedaço do recorte se ele sumir.
 */
/**
 * Tenant fictício por construção (ADR 0358), resolvido pelo trait `WithSeededTenant` que já
 * vive no `Tests\TestCase` — ele SKIPA com mensagem acionável se o seed não rodou, em vez de
 * estourar FK no meio do teste. biz=4 é proibido sem exceção; biz=1 é empresa real.
 */
function bizRecorte(): int
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
function bizRecorteAlheio(): int
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
        ->whereNotIn('id', [bizRecorte(), 1, 4])
        ->orderBy('id')
        ->value('id');

    if ($outro === null) {
        \PHPUnit\Framework\Assert::markTestSkipped(
            'Sem um segundo business para o caso cross-tenant — pular é honesto; passar seria vacuidade.'
        );
    }

    return (int) $outro;
}

/**
 * `transactions.contact_id` tem FK para `contacts` — inventar um id qualquer estoura
 * em `Integrity constraint violation`. Os contatos são criados de verdade, no tenant
 * fictício, e o `DatabaseTransactions` os desfaz no fim.
 */
function contatoRecorte(string $apelido): int
{
    // ⚠️ SEM cache estático. Um `static $cache` aqui sobrevive de um `it()` para o
    // seguinte, mas o `DatabaseTransactions` faz rollback do contato entre eles — o id
    // guardado vira órfão e o próximo insert estoura em FK. O cache é por TESTE, então
    // a busca também: `firstOrCreate` na chave, não memória de processo.
    $nome = 'Recorte '.$apelido;

    $id = DB::table('contacts')
        ->where('business_id', bizRecorte())
        ->where('name', $nome)
        ->value('id');

    return (int) ($id ?? DB::table('contacts')->insertGetId([
        'business_id' => bizRecorte(),
        'type'        => 'customer',
        'name'        => $nome,
        'mobile'      => '11900000000',
        'created_by'  => 1,
    ]));
}

function insereVenda(array $over = []): int
{
    $contato = $over['contato'] ?? 'a';
    unset($over['contato']);

    return (int) DB::table('transactions')->insertGetId(array_merge([
        'business_id'          => bizRecorte(),
        'type'                 => 'sell',
        'status'               => 'final',
        'transaction_date'     => now()->startOfMonth()->addDays(2)->toDateTimeString(),
        'final_total'          => 100,
        'created_by'           => 1,
        'essentials_duration'  => 0,
        'contact_id'           => contatoRecorte($contato),
    ], $over));
}

it('o valor apurado respeita o RECORTE — status, type e business', function () {
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id'    => bizRecorte(),
        'slug'           => 'recorte-teste-'.uniqid(),
        'nome'           => 'Faturamento mensal',
        'unidade'        => 'R$',
        'tipo_agregacao' => 'soma',
        'ativo'          => true,
        'origem'         => 'manual',
    ]);

    // ── As que DEVEM entrar: 100 + 200 = 300 ──
    insereVenda(['final_total' => 100, 'contato' => 'a']);
    insereVenda(['final_total' => 200, 'contato' => 'b']);

    // ── As que NÃO devem entrar, uma por pedaço do filtro ──
    insereVenda(['final_total' => 999, 'status' => 'draft']);                       // status
    insereVenda(['final_total' => 888, 'type'   => 'purchase']);                    // type
    insereVenda(['final_total' => 777, 'business_id' => bizRecorteAlheio()]);         // business (Tier 0)
    insereVenda(['final_total' => 666, 'transaction_date' => now()->startOfMonth()->subDays(3)->toDateTimeString()]); // janela

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizRecorte()]);
    Artisan::call('jana:metas:backfill-apuracoes', [
        '--business' => (string) bizRecorte(),
        '--meta'     => (string) $meta->id,
        '--janelas'  => 1,
    ]);

    $apurado = (float) MetaApuracao::withoutGlobalScopes()
        ->where('meta_id', $meta->id)
        ->orderByDesc('data_ref')
        ->value('valor_realizado');

    // 300, não 1299. Cada uma das 4 linhas descartadas move este número se o filtro
    // correspondente sumir da query gerada — é o que torna o assert discriminante.
    expect($apurado)->toBe(300.0);
});

it('CONTAGEM e CLIENTES DISTINTOS respeitam o mesmo recorte', function () {
    $vendas = Meta::withoutGlobalScopes()->create([
        'business_id' => bizRecorte(), 'slug' => 'recorte-v-'.uniqid(),
        'nome' => 'Vendas no mês', 'unidade' => 'qtd',
        'tipo_agregacao' => 'contagem', 'ativo' => true, 'origem' => 'manual',
    ]);
    $clientes = Meta::withoutGlobalScopes()->create([
        'business_id' => bizRecorte(), 'slug' => 'recorte-c-'.uniqid(),
        'nome' => 'Clientes atendidos', 'unidade' => 'qtd',
        'tipo_agregacao' => 'contagem', 'ativo' => true, 'origem' => 'manual',
    ]);

    // 3 vendas finais, mas só 2 clientes distintos — separa COUNT(*) de COUNT(DISTINCT).
    insereVenda(['final_total' => 10, 'contato' => 'x']);
    insereVenda(['final_total' => 20, 'contato' => 'x']);
    insereVenda(['final_total' => 30, 'contato' => 'y']);
    insereVenda(['final_total' => 99, 'contato' => 'z', 'status' => 'draft']);

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizRecorte()]);

    foreach ([$vendas, $clientes] as $m) {
        Artisan::call('jana:metas:backfill-apuracoes', [
            '--business' => (string) bizRecorte(), '--meta' => (string) $m->id, '--janelas' => 1,
        ]);
    }

    $v = (float) MetaApuracao::withoutGlobalScopes()->where('meta_id', $vendas->id)->value('valor_realizado');
    $c = (float) MetaApuracao::withoutGlobalScopes()->where('meta_id', $clientes->id)->value('valor_realizado');

    expect($v)->toBe(3.0);  // a draft fica de fora
    expect($c)->toBe(2.0);  // 601 conta uma vez só
});

it('TICKET MÉDIO é soma/contagem, não a média de um campo qualquer', function () {
    $meta = Meta::withoutGlobalScopes()->create([
        'business_id' => bizRecorte(), 'slug' => 'recorte-t-'.uniqid(),
        'nome' => 'Ticket médio', 'unidade' => 'R$',
        'tipo_agregacao' => 'media', 'ativo' => true, 'origem' => 'manual',
    ]);

    // 100 + 200 + 300 = 600 em 3 vendas -> 200.
    insereVenda(['final_total' => 100]);
    insereVenda(['final_total' => 200]);
    insereVenda(['final_total' => 300]);
    insereVenda(['final_total' => 9000, 'status' => 'draft']); // moveria a média se entrasse

    Artisan::call('jana:metas:configurar-fontes', ['--business' => (string) bizRecorte()]);
    Artisan::call('jana:metas:backfill-apuracoes', [
        '--business' => (string) bizRecorte(), '--meta' => (string) $meta->id, '--janelas' => 1,
    ]);

    expect((float) MetaApuracao::withoutGlobalScopes()->where('meta_id', $meta->id)->value('valor_realizado'))
        ->toBe(200.0);
});
