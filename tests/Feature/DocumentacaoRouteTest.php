<?php

/**
 * Contrato das rotas /documentacao (ADR 0256 — a página É a fonte, renderizada).
 *
 * O que cada caso defende:
 *   1. as 3 rotas exigem login (decisão [W] 2026-08-02: doc interna não fica pública);
 *   2. o documento fonte EXISTE no repo — defeito mais provável: alguém renomeia o
 *      GUIA e a página vira 503 silencioso em produção;
 *   3. `/documentacao/buscar` resolve pra BUSCA, não pra documento de slug "buscar" —
 *      é o defeito clássico de ordem de rota, e sem regex no {slug} ele acontece;
 *   4. os tipos filtrados EXISTEM no enum da tabela — se alguém trocar o enum na
 *      migration, o filtro vira uma lista de valores impossíveis e a busca devolve
 *      vazio pra sempre, sem erro nenhum;
 *   5. autenticado, a página responde e traz o conteúdo do dono.
 *
 * O caso 5 pula sem user semeado — declarado, não escondido: "0 failed" não prova
 * execução, então quem sustenta o contrato são os casos 2, 3 e 4, que sempre rodam.
 */

use App\User;

it('exige login nas tres rotas de documentacao', function () {
    foreach (['/documentacao', '/documentacao/buscar', '/documentacao/qualquer-slug'] as $rota) {
        $r = $this->get($rota);
        expect($r->getStatusCode())->toBe(302, "rota {$rota} deveria redirecionar pro login");
        expect($r->headers->get('Location'))->toContain('login');
    }
});

it('o documento fonte que a rota renderiza existe no repo', function () {
    // Espelha a const FONTE do DocumentacaoController.
    $fonte = base_path('memory/GUIA-DO-SISTEMA.md');

    expect(file_exists($fonte))->toBeTrue();

    $conteudo = file_get_contents($fonte);
    expect(strlen($conteudo))->toBeGreaterThan(500);
    // O controller remove o frontmatter; se o formato mudar, a remoção falha calada.
    expect($conteudo)->toStartWith('---');
});

it('/documentacao/buscar resolve pra busca, nao pra documento de slug "buscar"', function () {
    // Sem a ordem correta + regex no {slug}, a rota curinga engole a busca e o
    // usuário recebe "documento 'buscar' não encontrado". Aqui checamos o binding.
    $rota = app('router')->getRoutes()->match(
        Illuminate\Http\Request::create('/documentacao/buscar', 'GET')
    );

    expect($rota->getName())->toBe('documentacao.buscar');
    expect($rota->getActionMethod())->toBe('buscar');
});

it('os tipos filtrados existem no enum da tabela do acervo', function () {
    // Espelha TIPOS_DOC do controller. Se a migration mudar o enum e estes valores
    // sumirem, o whereIn não dá erro — só devolve vazio pra sempre. Este caso pega.
    $tiposDoController = ['adr', 'reference', 'spec', 'runbook'];

    $migrations = glob(base_path('Modules/Jana/Database/Migrations/*mcp_memory_documents*.php'));
    expect($migrations)->not->toBeEmpty();

    $sql = implode("\n", array_map('file_get_contents', $migrations));

    foreach ($tiposDoController as $tipo) {
        expect($sql)->toContain("'{$tipo}'", "tipo '{$tipo}' não aparece no enum das migrations");
    }
});

it('nenhum link do guia sai da rota como href relativo cru ou apontando pra caminho inexistente', function () {
    // O markdown foi escrito pra ser lido na árvore do git: os links são relativos a
    // `memory/`. Na web nada disso resolve sozinho — a rota reescreve cada um pro blob
    // do GitHub. Dois defeitos mediram 9 de 56 links errados em 2026-08-03: `../` era
    // apagado da string (virava `memory/README.md`, que não existe) e alvo não-`.md`
    // nem era reescrito (saía href relativo cru, 404 na própria página).
    //
    // Este caso NÃO toca banco nem sessão de propósito: roda sempre, em qualquer lane.
    $controller = new App\Http\Controllers\DocumentacaoController;
    $paraHtml = (new ReflectionClass($controller))->getMethod('paraHtml');
    $paraHtml->setAccessible(true);

    $html = $paraHtml->invoke($controller, file_get_contents(base_path('memory/GUIA-DO-SISTEMA.md')));

    preg_match_all('/href="([^"]+)"/', $html, $m);
    expect($m[1])->not->toBeEmpty();

    $blob = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

    $crus = array_values(array_unique(array_filter(
        $m[1],
        fn ($h) => ! preg_match('~^(https?:|[#]|mailto:)~', $h)
    )));
    expect($crus)->toBe([]);

    // Todo alvo reescrito tem que existir na árvore — link pro blob é promessa de arquivo.
    $inexistentes = [];
    foreach (array_unique($m[1]) as $href) {
        if (! str_starts_with($href, $blob)) {
            continue;
        }
        $caminho = rtrim(explode('#', substr($href, strlen($blob)))[0], '/');
        if (! file_exists(base_path($caminho))) {
            $inexistentes[] = $caminho;
        }
    }
    expect($inexistentes)->toBe([]);
});

it('o sumario da pagina e derivado dos titulos, com ancora estavel nos codigos de secao', function () {
    // Se alguém trocar o sumário derivado por uma lista escrita à mão, ou quebrar a
    // âncora curta (`#a1`/`#b6`) que o próprio guia usa pra se referenciar, este caso cai.
    $controller = new App\Http\Controllers\DocumentacaoController;
    $classe = new ReflectionClass($controller);

    $paraHtml = $classe->getMethod('paraHtml');
    $paraHtml->setAccessible(true);
    $comSumario = $classe->getMethod('comSumario');
    $comSumario->setAccessible(true);

    $markdown = file_get_contents(base_path('memory/GUIA-DO-SISTEMA.md'));
    [$html, $sumario] = $comSumario->invoke($controller, $paraHtml->invoke($controller, $markdown));

    expect(count($sumario))->toBeGreaterThan(10);

    $ids = array_column($sumario, 'id');
    expect($ids)->toContain('a1');
    expect($ids)->toContain('b6');
    expect(array_unique($ids))->toHaveCount(count($ids));   // âncora duplicada rouba o link

    // Todo item do sumário tem título correspondente no HTML — sumário e página não
    // podem divergir, e só não divergem porque um é derivado do outro.
    foreach ($ids as $id) {
        expect($html)->toContain('id="' . $id . '"');
    }
});

it('resolve link de documento em subpasta contra a pasta dele, nao contra memory/', function () {
    // /documentacao/{slug} serve o acervo INTEIRO, e boa parte dele mora em subpasta
    // (memory/reference/…, memory/requisitos/<Mod>/…). Com a base fixa em `memory/`,
    // `../decisions/0275-….md` virava `decisions/0275-….md` — que não existe. Medido em
    // 2026-08-03: 482 links assim só em memory/reference/. O guia mora na raiz de memory/,
    // então nunca sentiu o defeito — foi por isso que ele passou pelo contrato anterior.
    //
    // Só filesystem + reflection: roda em qualquer lane, sem banco e sem sessão.
    $controller = new App\Http\Controllers\DocumentacaoController;
    $classe = new ReflectionClass($controller);

    $paraHtml = $classe->getMethod('paraHtml');
    $paraHtml->setAccessible(true);
    $pastaDe = $classe->getMethod('pastaDe');
    $pastaDe->setAccessible(true);

    expect($pastaDe->invoke($controller, 'memory/reference/x.md'))->toBe('memory/reference');
    expect($pastaDe->invoke($controller, 'README.md'))->toBe('');            // raiz do repo
    expect($pastaDe->invoke($controller, null))->toBe('memory');             // registro sem git_path

    // Documento real do acervo que sobe de pasta no link — não fixamos qual, pra o caso
    // não morrer quando alguém renomear um arquivo.
    $alvo = collect(glob(base_path('memory/reference/*.md')))
        ->first(fn ($f) => str_contains((string) file_get_contents($f), '](../'));

    expect($alvo)->not->toBeNull('nenhum doc de referência com link relativo — corpus mudou?');

    $conteudo = (string) file_get_contents($alvo);
    $gitPath = 'memory/reference/' . basename($alvo);
    $blob = 'https://github.com/wagnerra23/oimpresso.com/blob/main/';

    $inexistentes = function (string $html) use ($blob): array {
        preg_match_all('/href="([^"]+)"/', $html, $m);
        $faltando = [];
        foreach (array_unique($m[1]) as $href) {
            if (! str_starts_with($href, $blob)) {
                continue;
            }
            $caminho = rtrim(explode('#', substr($href, strlen($blob)))[0], '/');
            if ($caminho !== '' && ! file_exists(base_path($caminho))) {
                $faltando[] = $caminho;
            }
        }

        return $faltando;
    };

    // Com a pasta do próprio documento, todo alvo tem que existir na árvore.
    $certo = $paraHtml->invoke($controller, $conteudo, $pastaDe->invoke($controller, $gitPath));
    expect($inexistentes($certo))->toBe([]);

    // E o contrário prova que o caso mede o que diz medir: com a base antiga o mesmo
    // documento produz alvo inexistente. Sem esta linha, o caso passaria mesmo que a
    // correção fosse revertida por um default silencioso.
    $errado = $paraHtml->invoke($controller, $conteudo, 'memory');
    expect($inexistentes($errado))->not->toBe([]);
});

it('responde 200 e renderiza o conteudo do dono quando autenticado', function () {
    $user = User::query()->whereNotNull('email')->first();
    if (! $user) {
        $this->markTestSkipped('Sem users no DB — este caso não executou.');
    }

    $r = $this->actingAs($user)->get('/documentacao');

    expect($r->getStatusCode())->toBe(200);

    $html = $r->getContent();
    expect($html)->toContain('memory/GUIA-DO-SISTEMA.md');   // veio do markdown, não de HTML commitado
    expect($html)->not->toContain('slug: guia-do-sistema');  // frontmatter não vazou
    expect($html)->toContain('<h2');                          // markdown virou HTML de verdade
    expect($html)->toContain('documentacao/buscar');           // a busca está oferecida
});
