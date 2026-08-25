<?php

declare(strict_types=1);

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

it('UC-INDEX-01 · o controller NAO quebra o global scope multi-tenant', function () {
    // Tier 0 (ADR 0093): o business_id vem da SESSÃO. Se alguém puser um
    // `withoutGlobalScopes` aqui, este teste é quem avisa.
    $codigo = arquivosCodigoSemComentarios(base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php'));

    expect($codigo)->not->toContain('withoutGlobalScopes');
    expect($codigo)->not->toContain("where('business_id'");
})->group('arquivos', 'multi-tenant');

it('UC-INDEX-01 · a linha do acervo NAO carrega storage_path nem md5 (LGPD Art. 37)', function () {
    // Non-Goal do charter: PII e caminho vivem só em `arquivos_audit_log`. Uma vista de
    // governança que os renderize é vazamento — e o `casos.md` declara isso.
    $r = new ReflectionMethod(ArquivosAdminController::class, 'linha');
    $r->setAccessible(true);

    $codigo = arquivosCodigoSemComentarios(base_path('Modules/Arquivos/Http/Controllers/ArquivosAdminController.php'));
    $corpo = substr($codigo, (int) strpos($codigo, 'private function linha('));

    expect($corpo)->not->toContain('storage_path');
    expect($corpo)->not->toContain('md5');
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
