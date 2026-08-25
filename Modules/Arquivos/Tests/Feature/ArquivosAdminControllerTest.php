<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Http\Controllers\ArquivosAdminController;

uses(Tests\TestCase::class);

/**
 * O CODIGO do controller, sem comentario nenhum.
 *
 * Os asserts abaixo procuram simbolo proibido. Sem isto eles acusam a PROSA que explica
 * a proibicao: em 2026-08-25 o assert de `withoutGlobalScopes` reprovou porque o docblock
 * da classe diz que NAO usa aquilo — o gate se autodenunciou (lapide 2026-07-26,
 * auto-silenciamento).
 *
 * O oraculo e o `token_get_all`, o tokenizer do proprio PHP. Regex sobre os delimitadores
 * de comentario nao serve: os mesmos caracteres aparecem dentro de string literal.
 *
 * `function_exists` porque funcao de arquivo de teste vive no escopo global e colide com
 * homonima de outra suite.
 */
if (! function_exists('arquivosCodigoSemComentarios')) {
    function arquivosCodigoSemComentarios(string $caminho): string
    {
        $codigo = '';

        foreach (token_get_all(file_get_contents($caminho)) as $t) {
            if (is_array($t) && in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $codigo .= is_array($t) ? $t[1] : $t;
        }

        return $codigo;
    }
}

/**
 * O corpo de UM método, sem comentários.
 *
 * Existe porque a partir do PR-2 o controller tem DOIS caminhos de leitura com regras
 * OPOSTAS de multi-tenant — acervo (model com global scope: `where` manual proibido) e
 * trilha (`DB::table` sem scope: `where` manual OBRIGATÓRIO). Um assert sobre o arquivo
 * inteiro não consegue dizer as duas coisas; escopado por método, consegue.
 *
 * `RuntimeException` quando o método some, em vez de string vazia: assert sobre vazio
 * PASSA, e um gate que se desliga sozinho quando o alvo é renomeado é o defeito que este
 * arquivo inteiro existe pra não cometer.
 */
if (! function_exists('arquivosCorpoDoMetodo')) {
    function arquivosCorpoDoMetodo(string $caminho, string $metodo): string
    {
        $codigo = arquivosCodigoSemComentarios($caminho);
        $ini = strpos($codigo, 'function ' . $metodo . '(');

        if ($ini === false) {
            throw new RuntimeException(
                "Método {$metodo}() não existe mais em {$caminho} — o assert que dependia dele "
                . 'ficaria verde sem medir nada. Aponte-o pro novo nome ou remova o teste.'
            );
        }

        // Fim = próxima declaração de método no mesmo nível de indentação, ou fim do arquivo.
        $resto = substr($codigo, $ini + 1);
        $fim = false;

        foreach (["\n    private function ", "\n    public function ", "\n    protected function "] as $marca) {
            $p = strpos($resto, $marca);
            if ($p !== false && ($fim === false || $p < $fim)) {
                $fim = $p;
            }
        }

        return $fim === false ? substr($codigo, $ini) : substr($codigo, $ini, $fim + 1);
    }
}

/**
 * Contrato da tela do acervo — US-ARQ-013 (onda 1 · PR-1).
 *
 * Defende o que o `Index.casos.md` declara em UC-INDEX-01 e na seção anti-regressão,
 * e o que o `Index.charter.md` põe em Non-Goals.
 *
 * O alvo aqui é o CONTROLLER — que ele não quebre o scope e não vaze o que o charter
 * proíbe.
 *
 * ⚠️ **Onde estes testes rodam — são DUAS lanes, e elas não são equivalentes:**
 *
 * | lane | banco | o que roda |
 * |---|---|---|
 * | `Pest Arquivos` (`modules-pest.yml`) | sqlite `:memory:`, **sem migrate** | tudo — mas 107 de 192 PULAM ("arquivos table missing") |
 * | `PHP / Pest (Arquivos · MySQL)` (`arquivos-pest.yml`) | MySQL semeado (biz=1 + biz=2) | **allowlist** de arquivos comprovadamente verdes |
 *
 * Medido em 2026-08-25 (run 32799606614). Os testes daqui dispensam banco de propósito:
 * lêem o código do controller e invocam `politica()`, que só toca config. Por isso valem
 * nas duas lanes.
 *
 * O que **não** valia até hoje: o `MultiTenantTest`, dono do isolamento cross-tenant,
 * pulava na lane sqlite e **não estava na allowlist MySQL** — logo o Tier 0 do módulo não
 * tinha prova em lugar nenhum do CI. Este PR o adiciona à allowlist, que é o mecanismo que
 * o próprio workflow instrui ("cada novo teste MySQL-only é adicionado AQUI — ratchet up").
 *
 * @see resources/js/Pages/Arquivos/Index.casos.md
 * @see memory/requisitos/Arquivos/RUNBOOK-index.md
 */

it('UC-INDEX-01 · o ACERVO nao quebra o global scope multi-tenant', function () {
    // Tier 0 (ADR 0093): o business_id vem da SESSÃO. Se alguém puser um
    // `withoutGlobalScopes` aqui, este teste é quem avisa.
    $arquivo = base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php');

    expect(arquivosCodigoSemComentarios($arquivo))->not->toContain('withoutGlobalScopes');

    // O `where` manual segue proibido — mas SÓ no caminho do acervo, que lê pelo model
    // `Arquivo` (global scope). Ali repeti-lo esconderia uma quebra do scope.
    //
    // ⚠️ Este assert valia pro ARQUIVO INTEIRO até o PR-2 e teve de ser escopado, porque
    // a trilha lê `arquivos_audit_log` — tabela SEM model, logo SEM global scope. Aplicado
    // ao arquivo todo, ele passaria a proibir a única defesa Tier 0 que aquela vista tem,
    // e obedecê-lo seria vazamento cross-tenant. É o caso da precedência de 2026-07-06:
    // gate que, cumprido ao pé da letra, produz o dano que ele existe pra impedir.
    // A contrapartida está no teste seguinte, que EXIGE o filtro na trilha.
    $acervo = arquivosCorpoDoMetodo($arquivo, 'buildAcervoPayload');

    expect($acervo)->not->toContain("where('business_id'");
    expect($acervo)->not->toContain("where('arquivos.business_id'");
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-02 · a TRILHA filtra por business_id — a tabela nao tem global scope', function () {
    // O espelho do assert acima. `arquivos_audit_log` não tem model: nenhum scope roda
    // sozinho. Se o filtro sumir daqui, a trilha passa a listar evento de outro tenant —
    // e a prova comportamental está mais abaixo, com dois businesses de verdade.
    $arquivo = base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php');
    $trilha = arquivosCorpoDoMetodo($arquivo, 'buildTrilhaPayload');

    expect($trilha)->toContain('business_id');
    expect($trilha)->toContain('businessIdDaSessao');
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-01 · o controller NAO carrega storage_path nem md5 (LGPD Art. 37)', function () {
    // Non-Goal do charter: caminho de disco e hash não saem pra tela. Antes do PR-2 este
    // assert cobria do método `linha()` até o fim do arquivo; agora cobre o arquivo
    // INTEIRO — a superfície cresceu (trilha) e o corte por posição deixaria de fora
    // qualquer método escrito acima. Apertar, não afrouxar.
    $codigo = arquivosCodigoSemComentarios(base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php'));

    expect($codigo)->not->toContain('storage_path');
    expect($codigo)->not->toContain('md5');
})->group('arquivos', 'lgpd');

it('UC-INDEX-01 · a tela e LEITURA PURA — nenhum caminho escreve, apaga ou enfileira', function () {
    // Anti-regressão do casos.md: "Nenhum caminho de upload nesta tela" +
    // "Excluir nunca chama hard-delete direto". Na onda 1 nem existe mutação.
    $codigo = arquivosCodigoSemComentarios(base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php'));

    foreach (['->delete(', '->save(', '->update(', 'dispatch(', 'forceDelete('] as $proibido) {
        expect($codigo)->not->toContain($proibido);
    }
})->group('arquivos');

it('a rota do acervo exige a permission arquivos.access', function () {
    // A permission existia declarada desde a Sprint 1 e NÃO tinha consumidor no repo.
    // Esta rota é o primeiro — se alguém tirar o can(), o gate de acesso some calado.
    $rotas = file_get_contents(base_path('Modules/Arquivos/Routes/web.php'));

    expect($rotas)->toContain('can:arquivos.access');
    expect($rotas)->toContain("->name('arquivos.index')");
})->group('arquivos');

it('a rota assinada de download e as 3 do Install seguem intactas', function () {
    // Regra 4 do pedido zero-toque: não tocar nelas. Teste de não-regressão.
    $rotas = file_get_contents(base_path('Modules/Arquivos/Routes/web.php'));

    expect($rotas)->toContain("->name('arquivos.download')");
    expect($rotas)->toContain("'signed'");
    expect($rotas)->toContain('throttle:60,1');
    expect(substr_count($rotas, 'InstallController::class'))->toBe(3);
})->group('arquivos');

it('UC-INDEX-01 · politica() devolve PRAZO e BASE LEGAL — nunca lista vazia', function () {
    // Este e o unico teste COMPORTAMENTAL do arquivo: ele invoca o metodo. Os outros
    // leem o fonte (presence-gate) e, por construcao, nao pegam defeito de runtime.
    //
    // Ele existe porque um pegou: o controller lia `config('retention.entities')`, um
    // namespace que NAO existe em lugar nenhum do repo. `politica()` devolvia `[]` e a
    // tela renderizava prazo SEM a lei ao lado — violando o Goal do charter ("prazo
    // sempre acompanhado da lei") em silencio, com todos os gates verdes.
    //
    // Morde duas coisas de uma vez: o namespace errado E o config nao registrado.
    $c = new ArquivosAdminController();
    $m = new ReflectionMethod($c, 'politica');
    $m->setAccessible(true);

    $politica = $m->invoke($c);

    expect($politica)->not->toBeEmpty();

    foreach ($politica as $item) {
        expect($item['dias'])->toBeGreaterThan(0);
        // Toda chave da policy tem lei mapeada — '—' significa contexto novo no
        // config sem a base legal correspondente, que e achado, nao detalhe.
        expect($item['lei'])->not->toBe('—');
    }
})->group('arquivos', 'lgpd');

it('a policy de retencao e ALCANCAVEL — o config do modulo esta registrado', function () {
    // O provider nao tinha `mergeConfigFrom` nenhum (medido 2026-08-24). Todo o resto do
    // modulo sobrevivia por default inline; so a policy, que nao tem default, ficava nula.
    expect(config('arquivos.retention_days_policy'))->toBeArray()->not->toBeEmpty();
    expect(config('arquivos.retention_days_policy.nfe-xml'))->toBe(1825);
})->group('arquivos');

it('UC-INDEX-01 · todo bucket que a Request aceita EXISTE no enum do banco', function () {
    // Comportamental, e nasceu de bug real. Ate 2026-08-25 havia QUATRO listas de bucket que
    // nao batiam: o enum do banco (7 valores, default `active`), o que o CuradorEngine grava
    // (4), o que a Request aceitava (`public`/`internal`/`sensitive`/`vault`) e os chips da
    // tela (`sensitive`/`common`/`public`). So `sensitive` existia nas quatro.
    //
    // Efeito medido no smoke de producao: arquivo real com `bucket=active` era REJEITADO pela
    // Request com 422, e os chips filtravam por valores que o banco nao tem — lista sempre
    // vazia. So o chip "Todos" funcionava. Nenhum gate pegaria: e divergencia entre camadas,
    // nao erro de sintaxe.
    $migration = file_get_contents(base_path(
        'Modules/Arquivos/Database/Migrations/2026_05_10_000001_create_arquivos_table.php'
    ));

    // O bloco `$table->enum('bucket', [...])` da migration e a fonte FISICA: o MySQL recusa
    // qualquer valor fora dele.
    preg_match("/enum\('bucket',\s*\[(.*?)\]/s", $migration, $m);
    expect($m)->not->toBeEmpty();

    preg_match_all("/'([a-z_]+)'/", $m[1], $mm);
    $doBanco = $mm[1];
    expect($doBanco)->toContain('active');

    $request = file_get_contents(base_path('Modules/Arquivos/Http/Requests/ListArquivosRequest.php'));
    preg_match("/Rule::in\(\[(.*?)\]\)/s", $request, $r);
    expect($r)->not->toBeEmpty();

    preg_match_all("/'([a-z_]+)'/", $r[1], $rr);

    foreach ($rr[1] as $aceito) {
        expect($doBanco)->toContain($aceito);
    }
})->group('arquivos');
it('UC-INDEX-01 · a tela tem entrada no sidebar, com as 3 camadas de habilitação', function () {
    // Nasceu de defeito real: `modifyAdminMenu()` era `no-op` até 2026-08-25, com um
    // comentário dizendo que o módulo "não tem tela própria". A rota respondia 200 em
    // produção e NINGUÉM a alcançava pelo menu — só por URL direta. Nenhum gate pegaria:
    // um método vazio é sintaticamente perfeito.
    //
    // ⚠️ LIMITE DESTE ASSERT: ele lê o CÓDIGO, não o menu renderizado. Prova que as 3
    // camadas continuam no caminho; NÃO prova que o item aparece pro usuário. Essa prova é
    // o smoke com olho humano — foi assim que o defeito foi achado, e não há substituto.
    $codigo = arquivosCodigoSemComentarios(
        base_path('Modules/Arquivos/Http/Controllers/DataController.php')
    );

    // Deixou de ser no-op.
    expect($codigo)->toContain('Menu::modify');
    expect($codigo)->toContain("url('/arquivos')");

    // Camada 1 — módulo no pacote do business.
    expect($codigo)->toContain('arquivos_module');
    expect($codigo)->toContain('hasThePermissionInSubscription');

    // Camada 3 — permission por função.
    expect($codigo)->toContain("can('arquivos.access')");

    // Proibição Tier 0: habilitar módulo por business NUNCA é hardcode de id.
    expect($codigo)->not->toMatch('/business_id\s*===?\s*\d+/');
})->group('arquivos', 'multi-tenant');
// =============================================================================
// Trilha (onda 1 · PR-2) — `arquivos_audit_log`, read-only.
//
// Os três primeiros testes daqui DISPENSAM banco (invocam método puro), então valem
// nas duas lanes. Os dois últimos precisam de MySQL semeado e pulam na lane sqlite —
// o lar deles é o `arquivos-pest.yml`, onde este arquivo já está na allowlist.
// =============================================================================

/**
 * id de arquivo fictício pras fixtures da trilha.
 *
 * Alto de propósito: `arquivos_audit_log.arquivo_id` NÃO tem FK (comentada na migration
 * 2026_05_10_000002) e a trilha não faz join com `arquivos` — então a linha existe sem
 * arquivo por trás, e o cleanup acha as fixtures por este id sem tocar dado de ninguém.
 */
if (! function_exists('arquivosTrilhaFixtureId')) {
    function arquivosTrilhaFixtureId(int $businessId): int
    {
        return 987654000 + $businessId;
    }
}

if (! function_exists('arquivosTrilhaPayloadDe')) {
    function arquivosTrilhaPayloadDe(array $filtros = []): array
    {
        $c = new ArquivosAdminController();
        $m = new ReflectionMethod($c, 'buildTrilhaPayload');
        $m->setAccessible(true);

        return $m->invoke($c, array_merge([
            'tab' => 'trilha', 'from' => null, 'to' => null, 'acao' => null, 'per_page' => 25,
        ], $filtros));
    }
}

it('UC-INDEX-02 · sem business_id na sessao a trilha devolve VAZIO — nunca tudo', function () {
    // Fail-closed, e é onde a trilha diverge do model de propósito: o global scope do
    // `Arquivo` faz `if ($businessId !== null)` e, sem sessão, deixa a query passar SEM
    // filtro. Repetir essa aposta numa tabela sem scope seria servir o log inteiro do
    // sistema. Este teste não toca o banco: o método retorna antes disso.
    session()->forget('user');
    session()->forget('business');

    $payload = arquivosTrilhaPayloadDe();

    expect($payload['eventos']['data'])->toBe([]);
    expect($payload['eventos']['total'])->toBe(0);
    expect($payload['acoes'])->toBe([]);
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-02 · a linha da trilha expoe id do arquivo, NUNCA o nome', function () {
    // Comportamental e sem banco: alimenta o formatador com uma linha crua e olha o que
    // sai. O charter proíbe filename em vista de governança, e o protótipo desenha a
    // coluna como `#id` — o contrato é o conjunto de chaves, não a intenção do docblock.
    $c = new ArquivosAdminController();
    $m = new ReflectionMethod($c, 'linhaTrilha');
    $m->setAccessible(true);

    $linha = $m->invoke($c, (object) [
        'id'         => 42,
        'created_at' => '2026-08-25 14:07:33',
        'action'     => 'signed_url_consumed',
        'arquivo_id' => 7,
        'quem'       => 'Wagner Rodrigues',
        'payload'    => '{"ip":"10.0.0.7","agent":"Mozilla"}',
    ]);

    expect(array_keys($linha))->toBe(['id', 'quando', 'acao', 'arquivo', 'quem', 'detalhe']);
    expect($linha['arquivo'])->toBe(7);
    expect($linha['quando'])->toBe('2026-08-25 14:07');
    expect($linha['detalhe'])->toBe('ip=10.0.0.7 · agent=Mozilla');
})->group('arquivos', 'lgpd');

it('UC-INDEX-02 · o detalhe resume o payload — bool vira palavra e lixo nao quebra a tela', function () {
    // `payload` é campo livre: cada gravador escreve o seu. O formatador precisa aguentar
    // o que os 4 call-sites de hoje escrevem E o que o quinto vier a escrever.
    $c = new ArquivosAdminController();
    $m = new ReflectionMethod($c, 'resumoPayload');
    $m->setAccessible(true);

    expect($m->invoke($c, null))->toBeNull();
    expect($m->invoke($c, ''))->toBeNull();
    // `false` é informação (o arquivo NÃO saiu do disco) — sumir com ela seria mentir.
    expect($m->invoke($c, '{"file_removed_from_disk":false,"retention_days":90}'))
        ->toBe('file_removed_from_disk=não · retention_days=90');
    // JSON quebrado não derruba a linha: vira texto truncado.
    expect($m->invoke($c, 'isto nao e json'))->toBe('isto nao e json');
})->group('arquivos');

it('UC-INDEX-02 · a trilha do tenant 98 NUNCA mostra evento do 99 (Tier 0, cross-tenant)', function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log ausente — a prova cross-tenant roda na lane MySQL.');
    }

    // Pela CLASSE, nunca pelo trait. `WithSeededTenant::SEEDED_TENANT_ID` é fatal em PHP 8.2+
    // ("Cannot access trait constant ... directly") — medido em 8.4. A primeira versão deste
    // arquivo fez isso no `afterEach` e derrubou os 13 testes da suíte, inclusive os antigos:
    // o teardown roda depois de CADA teste, então um Error ali reprova o arquivo inteiro.
    $proprio    = Tests\TestCase::SEEDED_TENANT_ID;         // 98 — fictício
    $adversario = Tests\TestCase::SUPPORT_CLIENT_TENANT_ID; // 99 — o outro

    foreach ([$proprio, $adversario] as $biz) {
        DB::table('arquivos_audit_log')->insert([
            'arquivo_id'  => arquivosTrilhaFixtureId($biz),
            'business_id' => $biz,
            'user_id'     => null,
            'action'      => 'upload',
            'payload'     => json_encode(['fixture' => 'trilha-tier0']),
            'created_at'  => now(),
        ]);
    }

    session(['user' => ['business_id' => $proprio]]);

    $payload  = arquivosTrilhaPayloadDe();
    $arquivos = array_column($payload['eventos']['data'], 'arquivo');

    // O evento do adversário não pode aparecer — e o do próprio TEM de aparecer, senão um
    // filtro quebrado (que zera tudo) passaria por isolamento.
    expect($arquivos)->toContain(arquivosTrilhaFixtureId($proprio));
    expect($arquivos)->not->toContain(arquivosTrilhaFixtureId($adversario));

    // As FACETAS também são query: se o `where` faltar só nelas, o vazamento vira um
    // número no chip. Sem filtro de ação, a soma delas tem de bater com o total da lista.
    $somaFacetas = array_sum(array_column($payload['acoes'], 'total'));
    expect($somaFacetas)->toBe($payload['eventos']['total']);
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-02 · o filtro de acao restringe a lista sem apagar os outros chips', function () {
    if (! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('arquivos_audit_log ausente — roda na lane MySQL.');
    }

    $biz = Tests\TestCase::SEEDED_TENANT_ID;

    foreach (['upload', 'restore'] as $acao) {
        DB::table('arquivos_audit_log')->insert([
            'arquivo_id'  => arquivosTrilhaFixtureId($biz),
            'business_id' => $biz,
            'user_id'     => null,
            'action'      => $acao,
            'payload'     => null,
            'created_at'  => now(),
        ]);
    }

    session(['user' => ['business_id' => $biz]]);

    $payload = arquivosTrilhaPayloadDe(['acao' => 'restore']);

    // Toda linha listada é da ação pedida.
    expect(array_unique(array_column($payload['eventos']['data'], 'acao')))->toBe(['restore']);

    // E os chips seguem mostrando as OUTRAS ações: faceta que só conta a si mesma deixa
    // de ser filtro — vira beco sem saída.
    expect(array_column($payload['acoes'], 'acao'))->toContain('upload');
})->group('arquivos');

// =============================================================================
// Cofre (onda 1 · PR-4) — saúde do armazenamento, read-only.
//
// A agregação NÃO mora no controller, e a razão é este arquivo: o achado de
// duplicado agrupa por hash, e o assert de LGPD acima reprova a menção a hash em
// QUALQUER método do controller. Afrouxá-lo pra caber seria trocar a defesa pela
// conveniência — então a query foi pro `CofreStatsReader` e o que o gate protege de
// verdade (hash e caminho não chegarem à tela) passou a ser defendido por assert
// COMPORTAMENTAL sobre o payload, mais abaixo. Presence-gate no controller,
// comportamento no payload: aperta, não afrouxa.
// =============================================================================

/**
 * Disco sentinela das fixtures do cofre.
 *
 * O cofre agrega o acervo INTEIRO do business, e a lane roda contra um banco que
 * persiste entre testes. Asserir sobre o total do tenant amarraria o teste ao que
 * outra suíte deixou pra trás; asserir sobre o card de um disco que só existe aqui
 * dá número exato — e mantém a prova cross-tenant afiada, porque o adversário grava
 * no MESMO disco: se vazar, o número deste card muda.
 */
if (! function_exists('arquivosCofreDisco')) {
    function arquivosCofreDisco(): string
    {
        return 'fixture-cofre';
    }
}

if (! function_exists('arquivosCofreInsere')) {
    function arquivosCofreInsere(int $businessId, array $over = []): void
    {
        DB::table('arquivos')->insert(array_merge([
            'business_id'     => $businessId,
            'disk'            => arquivosCofreDisco(),
            'storage_path'    => "biz-{$businessId}/fixture/padrao.xml",
            'original_name'   => 'fixture-padrao.xml',
            'mime_type'       => 'application/xml',
            'size_bytes'      => 1024,
            'md5'             => str_repeat('c', 32),
            'bucket'          => 'active',
            'sub_destination' => '__fixture-cofre',
            'created_at'      => now(),
            'updated_at'      => now(),
        ], $over));
    }
}

if (! function_exists('arquivosCofrePayload')) {
    function arquivosCofrePayload(): array
    {
        return (new Modules\Arquivos\Services\CofreStatsReader())->fetch();
    }
}

it('UC-INDEX-03 · sem business_id na sessao o cofre nao diz "0 achados" — diz que NAO MEDIU', function () {
    // Portão fail-closed. O global scope do `Arquivo` faz `if ($businessId !== null)`,
    // logo sem sessão a query passaria SEM filtro e o cofre somaria o acervo de todos
    // os tenants. Devolver zeros também seria errado por outro motivo: zero é a
    // resposta de um acervo limpo, e afirmar saúde sem ter medido é o defeito que a
    // flag `disponivel` existe pra impedir. Não toca o banco: retorna antes.
    session()->forget('user');
    session()->forget('business');

    $payload = arquivosCofrePayload();

    expect($payload['disponivel'])->toBeFalse();
    expect($payload['discos'])->toBe([]);
    expect($payload['orfaos']['total'])->toBe(0);
    expect($payload['duplicados']['grupos'])->toBe(0);
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-03 · o COFRE le pelo model e NAO repete o where de business_id', function () {
    // Espelho do assert do acervo, um arquivo adiante: `arquivos` TEM model, logo TEM
    // global scope, logo repetir o filtro esconderia uma quebra dele. É o oposto da
    // trilha — e é por isso que os três asserts existem separados.
    $reader = base_path('Modules/Arquivos/Services/CofreStatsReader.php');
    $codigo = arquivosCodigoSemComentarios($reader);

    expect($codigo)->toContain('Arquivo::query()');
    expect($codigo)->not->toContain("where('business_id'");
    expect($codigo)->not->toContain("where('arquivos.business_id'");
    expect($codigo)->not->toContain('withoutGlobalScopes');
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-03 · o cap do cofre vem da CONFIG que o vault cobra, nunca de um 50 escrito na tela', function () {
    // Se o cap virar literal aqui, a tela passa a mentir no dia em que alguém ajustar
    // ARQUIVOS_VAULT_MAX_FILE_SIZE_MB no .env — e o `VaultEncryptionService` seguiria
    // recusando por outro número. Comportamental: muda a config e olha o que sai.
    $m = new ReflectionMethod(Modules\Arquivos\Services\CofreStatsReader::class, 'capMb');
    $m->setAccessible(true);
    $reader = new Modules\Arquivos\Services\CofreStatsReader();

    config(['arquivos.vault_max_file_size_mb' => 7]);
    expect($m->invoke($reader))->toBe(7);

    // `<= 0` é RuntimeException no serviço (o cap não pode ser desligado). Numa vista
    // de leitura isso vira default: derrubar a página não é o jeito de noticiar .env torto.
    config(['arquivos.vault_max_file_size_mb' => 0]);
    expect($m->invoke($reader))->toBe(50);
})->group('arquivos');

it('UC-INDEX-03 · o controller registra a prop de UMA vista so — a que esta aberta', function () {
    // O RUNBOOK declara isto e nada defendia: `Inertia::defer` adia a execução, mas o
    // cliente busca TODAS as props deferidas no segundo request — registrar duas faria
    // quem está no cofre pagar o `paginate` do acervo. Comportamental sem banco: o
    // defer não executa, então só as CHAVES são observadas.
    $props = function (?string $tab): array {
        $request = Modules\Arquivos\Http\Requests\ListArquivosRequest::create(
            '/arquivos', 'GET', $tab === null ? [] : ['tab' => $tab]
        );
        $response = (new ArquivosAdminController())->index($request);

        $p = new ReflectionProperty($response, 'props');
        $p->setAccessible(true);

        return array_keys($p->getValue($response));
    };

    expect($props('cofre'))->toBe(['filtros', 'politica', 'cofre']);
    expect($props('trilha'))->toBe(['filtros', 'politica', 'trilha']);
    expect($props('acervo'))->toBe(['filtros', 'politica', 'acervo']);
    // `tab` ausente ou desconhecido cai no acervo por DECISÃO (o `match` tem default),
    // não por acidente de `if/else`.
    expect($props(null))->toBe(['filtros', 'politica', 'acervo']);
})->group('arquivos');

it('UC-INDEX-03 · o cofre do tenant 98 NUNCA conta arquivo do 99 (Tier 0, cross-tenant)', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('tabela arquivos ausente — a prova cross-tenant roda na lane MySQL.');
    }

    $proprio    = Tests\TestCase::SEEDED_TENANT_ID;         // 98 — fictício
    $adversario = Tests\TestCase::SUPPORT_CLIENT_TENANT_ID; // 99 — o outro

    arquivosCofreInsere($proprio, ['size_bytes' => 1000, 'md5' => str_repeat('1', 32)]);
    arquivosCofreInsere($adversario, ['size_bytes' => 9000, 'md5' => str_repeat('9', 32)]);
    arquivosCofreInsere($adversario, ['size_bytes' => 9000, 'md5' => str_repeat('8', 32)]);

    session(['user' => ['business_id' => $proprio]]);

    $card = collect(arquivosCofrePayload()['discos'])
        ->firstWhere('disco', arquivosCofreDisco());

    // 1 arquivo e 1000 bytes: os 2 do adversário, no MESMO disco, não podem somar.
    expect($card)->not->toBeNull();
    expect($card['arquivos'])->toBe(1);
    expect($card['bytes'])->toBe(1000);
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-03 · o duplicado separa registro repetido de disco ocupado duas vezes', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('tabela arquivos ausente — roda na lane MySQL.');
    }

    $biz = Tests\TestCase::SEEDED_TENANT_ID;
    session(['user' => ['business_id' => $biz]]);

    // PRÉ-CONDIÇÃO EXPLÍCITA, e não é zelo: os exemplos são os 5 grupos com mais
    // cópias, e a lane roda contra um banco que PERSISTE entre execuções. Se outra
    // suíte deixar duplicados no tenant fictício, os meus grupos (2 cópias cada) podem
    // simplesmente não estar no topo — e o teste falharia longe da causa. Aferido aqui,
    // a falha diz o que é: o ambiente, não o código.
    expect(arquivosCofrePayload()['duplicados']['grupos'])
        ->toBe(0, 'tenant ficticio ja tem duplicado de outra suite — o top-5 deixaria de ser deterministico');

    // Mesmo conteúdo, MESMO caminho — o caminho é derivado do hash, então isto é o
    // caso comum: registro repetido, um arquivo só no disco.
    $hashJunto = str_repeat('a', 32);
    arquivosCofreInsere($biz, ['md5' => $hashJunto, 'original_name' => 'junto-1.xml', 'storage_path' => 'biz/2026/08/junto.xml']);
    arquivosCofreInsere($biz, ['md5' => $hashJunto, 'original_name' => 'junto-2.xml', 'storage_path' => 'biz/2026/08/junto.xml']);

    // Mesmo conteúdo, caminhos DIFERENTES (meses diferentes) — aí sim ocupa 2×.
    $hashSeparado = str_repeat('b', 32);
    arquivosCofreInsere($biz, ['md5' => $hashSeparado, 'original_name' => 'sep-1.xml', 'storage_path' => 'biz/2026/07/sep.xml']);
    arquivosCofreInsere($biz, ['md5' => $hashSeparado, 'original_name' => 'sep-2.xml', 'storage_path' => 'biz/2026/08/sep.xml']);

    $exemplos = collect(arquivosCofrePayload()['duplicados']['exemplos']);

    $junto = $exemplos->first(fn ($g) => in_array('junto-1.xml', $g['nomes'], true));
    $sep   = $exemplos->first(fn ($g) => in_array('sep-1.xml', $g['nomes'], true));

    expect($junto)->not->toBeNull();
    expect($junto['copias'])->toBe(2);
    expect($junto['caminhos'])->toBe(1);   // registro repetido, NÃO desperdício de disco

    expect($sep)->not->toBeNull();
    expect($sep['copias'])->toBe(2);
    expect($sep['caminhos'])->toBe(2);     // aí sim: dois arquivos físicos
})->group('arquivos');

it('UC-INDEX-03 · o payload do cofre NAO carrega hash nem caminho de disco (LGPD Art. 37)', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('tabela arquivos ausente — roda na lane MySQL.');
    }

    // A contrapartida comportamental do presence-gate do controller: lá se lê o fonte,
    // aqui se olha o que de fato SAI. O leitor precisa do hash pra agrupar — o que o
    // charter proíbe é ele chegar à tela.
    $biz  = Tests\TestCase::SEEDED_TENANT_ID;
    $hash = str_repeat('d', 32);
    $caminho = 'biz-98/2026/08/segredo-no-caminho.xml';

    session(['user' => ['business_id' => $biz]]);

    // Mesma pré-condição do teste acima, e pelo mesmo motivo: o controle positivo lá
    // embaixo (`visivel.xml` PRECISA aparecer) só é confiável se o grupo estiver entre
    // os 5 exibidos.
    expect(arquivosCofrePayload()['duplicados']['grupos'])
        ->toBe(0, 'tenant ficticio ja tem duplicado de outra suite — o controle positivo deixaria de valer');

    arquivosCofreInsere($biz, ['md5' => $hash, 'storage_path' => $caminho, 'original_name' => 'visivel.xml']);
    arquivosCofreInsere($biz, ['md5' => $hash, 'storage_path' => $caminho, 'original_name' => 'visivel-2.xml']);

    $json = json_encode(arquivosCofrePayload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    expect($json)->not->toContain($hash);
    expect($json)->not->toContain($caminho);
    expect($json)->not->toContain('segredo-no-caminho');
    // Controle positivo: se a asserção acima passasse com payload vazio, ela não
    // provaria nada — o nome do arquivo TEM de estar lá (o acervo já o mostra).
    expect($json)->toContain('visivel.xml');
})->group('arquivos', 'lgpd');

afterEach(function () {
    // Cleanup por id sentinela — sem RefreshDatabase, que dropa o schema e limparia o
    // seed compartilhado da lane (o próprio workflow veta arquivos que fazem isso).
    if (Schema::hasTable('arquivos_audit_log')) {
        DB::table('arquivos_audit_log')
            ->whereIn('arquivo_id', [
                arquivosTrilhaFixtureId(Tests\TestCase::SEEDED_TENANT_ID),
                arquivosTrilhaFixtureId(Tests\TestCase::SUPPORT_CLIENT_TENANT_ID),
            ])
            ->delete();
    }

    // Fixtures do cofre — marcadas por `sub_destination`, que nenhum caminho de
    // produção escreve. `delete()` direto na tabela porque o model tem SoftDeletes:
    // um soft-delete deixaria a linha lá, e o próximo teste contaria o lixo do anterior.
    if (Schema::hasTable('arquivos')) {
        DB::table('arquivos')->where('sub_destination', '__fixture-cofre')->delete();
    }
});
