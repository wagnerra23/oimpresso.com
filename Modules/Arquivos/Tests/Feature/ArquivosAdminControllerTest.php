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

    $proprio    = Tests\Support\WithSeededTenant::SEEDED_TENANT_ID;         // 98 — fictício
    $adversario = Tests\Support\WithSeededTenant::SUPPORT_CLIENT_TENANT_ID; // 99 — o outro

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

    $biz = Tests\Support\WithSeededTenant::SEEDED_TENANT_ID;

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

afterEach(function () {
    // Cleanup por id sentinela — sem RefreshDatabase, que dropa o schema e limparia o
    // seed compartilhado da lane (o próprio workflow veta arquivos que fazem isso).
    if (Schema::hasTable('arquivos_audit_log')) {
        DB::table('arquivos_audit_log')
            ->whereIn('arquivo_id', [
                arquivosTrilhaFixtureId(Tests\Support\WithSeededTenant::SEEDED_TENANT_ID),
                arquivosTrilhaFixtureId(Tests\Support\WithSeededTenant::SUPPORT_CLIENT_TENANT_ID),
            ])
            ->delete();
    }
});
