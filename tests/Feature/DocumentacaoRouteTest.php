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

it('os tipos filtrados existem no enum VIGENTE da tabela do acervo', function () {
    // TIPOS_DOC vem por reflexão, não copiado aqui: espelho escrito à mão drifa do
    // controller e o caso passa a defender uma lista que ninguém usa.
    $tiposDoController = (new ReflectionClass(App\Http\Controllers\DocumentacaoController::class))
        ->getConstant('TIPOS_DOC');

    expect($tiposDoController)->toBeArray()->not->toBeEmpty();

    // As migrations do enum se chamam `*_to_mcp_type_enum.php` — um glob por
    // `*mcp_memory_documents*` no NOME deixava as duas últimas de fora. Filtra por
    // CONTEÚDO e ordena por nome (= ordem de aplicação).
    $migrations = array_values(array_filter(
        glob(base_path('Modules/Jana/Database/Migrations/*.php')),
        fn ($f) => str_contains((string) file_get_contents($f), 'mcp_memory_documents')
    ));
    sort($migrations);
    expect($migrations)->not->toBeEmpty();

    // O enum VIGENTE é o da ÚLTIMA migration que o redefine. Procurar a string solta
    // em todos os arquivos passaria com o tipo aparecendo só no ENUM_ANTIGO de um
    // `down()` — ou seja, num tipo que foi REMOVIDO. Presença ≠ estado atual.
    $vigente = null;
    foreach ($migrations as $arquivo) {
        $src = (string) file_get_contents($arquivo);
        if (preg_match('/ENUM_NOVO\s*=\s*"([^"]+)"/', $src, $m)) {
            $vigente = $m[1];                                   // migration de expansão
        } elseif (preg_match("/->enum\('type',\s*\[(.*?)\]\)/s", $src, $m)) {
            $vigente = $m[1];                                   // create table original
        }
    }

    // Falha visível se o formato mudar — nunca "não achei, então passa".
    expect($vigente)->not->toBeNull();

    $enum = array_map(fn ($v) => trim($v, " \t\n'\""), explode(',', (string) $vigente));

    // Por diferença de conjuntos, NÃO `toContain($tipo, "mensagem")`: `toContain` é
    // VARIÁDICO no Pest, então a mensagem entra como segundo NEEDLE e o caso falha
    // sempre (proibicoes §5 2026-07-28). Era o estado do `main` até este PR — passava
    // despercebido porque este arquivo não roda em lane nenhuma. O diff também é
    // melhor diagnóstico: mostra exatamente qual tipo sumiu do enum.
    $foraDoEnum = array_values(array_diff($tiposDoController, $enum));

    expect($foraDoEnum)->toBe([]);
});

it('o trio de feature chega ao acervo: o tipo que o indexador produz é o que o filtro aceita', function () {
    // O par tem DOIS lados e falhar em qualquer um é silencioso: sem o glob o doc não
    // entra na tabela; sem o tipo em TIPOS_DOC ele entra e nunca aparece em
    // /documentacao. É o que acontece hoje com charter/casos — indexados desde
    // 2026-08-02, fora deste filtro por decisão.
    $indexador = (string) file_get_contents(
        base_path('Modules/Jana/Services/Mcp/IndexarMemoryGitParaDb.php')
    );

    expect($indexador)->toContain('memory/requisitos/*/features/*/*.md');
    expect($indexador)->toContain("'type'   => 'feature'");

    $tiposDoController = (new ReflectionClass(App\Http\Controllers\DocumentacaoController::class))
        ->getConstant('TIPOS_DOC');

    expect($tiposDoController)->toContain('feature');
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

it('a paleta da documentacao nao drifa dos tokens do DS', function () {
    // A página é editorial e standalone: não carrega o CSS do app, então os tokens do DS
    // estão ESPELHADOS no :root do layout (o arquivo do DS escopa tudo em `.cockpit` e
    // traz ~80 tokens de tela de ERP que uma página de leitura não usa). Espelho sem
    // trava vira cópia que apodrece — este caso é a trava: mexeu no token do DS e não
    // no layout (ou o contrário), cai aqui.
    //
    // Roda sempre: só lê arquivo do repo, sem banco e sem sessão.
    $bloco = function (string $arquivo, string $seletor): array {
        $css = file_get_contents(base_path($arquivo));
        // preg_quote no seletor porque ele tem [ ] " . — e o corpo vai até a primeira `}`.
        expect(preg_match('/' . preg_quote($seletor, '/') . '\s*\{([^}]*)\}/', $css, $m))
            ->toBe(1, "bloco '{$seletor}' não encontrado em {$arquivo}");

        // `;` opcional: o último par antes da chave pode não tê-lo, e o caso não pode
        // depender do estilo de escrita de quem editar o CSS.
        preg_match_all('/--([a-z0-9-]+)\s*:\s*([^;]+);?/i', $m[1], $vars, PREG_SET_ORDER);

        return collect($vars)->mapWithKeys(
            fn ($v) => ['--' . $v[1] => trim(preg_replace('/\s+/', ' ', $v[2]))]
        )->all();
    };

    $ds = 'resources/css/tokens/_generated-cockpit-light.css';
    $dsDark = 'resources/css/tokens/_generated-cockpit-dark.css';
    $layout = 'resources/views/documentacao/layout.blade.php';

    // Mapa dos nomes locais → token do DS. Os nomes diferem de propósito: o DS chama
    // `--surface` o branco puro, e aqui `--surface` é o cinza de fundo de código.
    $mapa = [
        '--paper' => '--bg',
        '--surface' => '--bg-2',
        '--ink' => '--text',
        '--ink-soft' => '--text-dim',
        '--ink-mute' => '--text-mute',
        '--rule' => '--border',
        '--rule-soft' => '--border-2',
        '--accent' => '--accent',
        '--accent-bg' => '--accent-soft',
    ];

    $dsLight = $bloco($ds, '.cockpit');
    $localLight = $bloco($layout, ':root');

    foreach ($mapa as $local => $token) {
        expect($localLight)->toHaveKey($local);
        expect($dsLight)->toHaveKey($token);
        expect($localLight[$local])->toBe(
            $dsLight[$token],
            "{$local} do layout divergiu de {$token} do DS — rode `npm run tokens:build` e reconcilie"
        );
    }

    // Tipografia: a stack tem que ser a MESMA do DS (IBM Plex à frente), senão a página
    // desenha noutra fonte que o resto do produto.
    expect($localLight['--sans'])->toBe($dsLight['--font-sans']);
    expect($localLight['--mono'])->toBe($dsLight['--font-mono']);

    // No escuro o DS não redeclara accent nem fontes — só os neutros e o accent-soft.
    // O accent local (0.74) é divergência DECLARADA: aqui ele é cor de link em texto
    // corrido, uso que o DS não cobre. Se um dia o DS passar a declarar accent no dark,
    // esta linha vira o lembrete de reconciliar conscientemente.
    $dsEscuro = $bloco($dsDark, '.cockpit[data-theme="dark"]');
    $localEscuro = $bloco($layout, ':root[data-theme="dark"]');

    foreach (['--paper' => '--bg', '--surface' => '--bg-2', '--ink' => '--text',
        '--ink-soft' => '--text-dim', '--ink-mute' => '--text-mute',
        '--rule' => '--border', '--rule-soft' => '--border-2',
        '--accent-bg' => '--accent-soft'] as $local => $token) {
        expect($localEscuro[$local])->toBe(
            $dsEscuro[$token],
            "{$local} (dark) divergiu de {$token} do DS"
        );
    }

    expect($dsEscuro)->not->toHaveKey('--accent');          // premissa da divergência acima
    expect($localEscuro['--accent'])->toBe('oklch(0.74 0.13 295)');
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

it('o rail e derivado do frontmatter, com ordinal da ordem visivel na lente', function () {
    // O rail não tem lista escrita à mão: sai do `nav_group`/`nav_order`/`lente`. Dois
    // defeitos que este caso existe pra pegar:
    //   1. ordinal saindo de `nav_order` em vez da ordem VISÍVEL — filtrar a lente
    //      deixaria buracos (1, 3, 7) e o leitor acharia que sumiu conteúdo;
    //   2. documento sem `nav_group` vazando pro menu — o opt-in é o que impede os
    //      ~130 arquivos de referência legados de virarem menu sem ninguém decidir.
    //
    // Só filesystem + reflection: roda em qualquer lane, sem banco e sem sessão.
    $controller = new App\Http\Controllers\DocumentacaoController;
    $navegacao = (new ReflectionClass($controller))->getMethod('navegacao');
    $navegacao->setAccessible(true);

    $tudo = $navegacao->invoke($controller, null);

    expect($tudo['grupos'])->not->toBeEmpty();
    expect($tudo['linear'])->not->toBeEmpty();

    // Ordinal = posição visível, sempre 1..N sem buraco.
    expect(array_column($tudo['linear'], 'ordinal'))->toBe(range(1, count($tudo['linear'])));

    // Todo item aponta pra uma URL resolvível — o id é o MESMO slug que o indexador gera
    // pro acervo, por isso o rail linka direto, sem tabela de-para.
    foreach ($tudo['linear'] as $item) {
        expect($item['id'])->toStartWith('reference-');
        expect($item['rotulo'])->not->toBe('');
    }

    // Grupo vazio não vira cabeçalho órfão.
    foreach ($tudo['grupos'] as $grupo) {
        expect($grupo['itens'])->not->toBeEmpty();
    }

    // A lente filtra de verdade — e continua sem buraco no ordinal.
    $operar = $navegacao->invoke($controller, 'operar');
    expect(array_column($operar['linear'], 'ordinal'))->toBe(range(1, count($operar['linear'])));
    expect(count($operar['linear']))->toBeLessThanOrEqual(count($tudo['linear']));

    // Domínio é UMA página vista por dois públicos — nunca duas cópias. Se um doc de
    // domínio aparecer só numa lente, alguém quebrou essa regra.
    $construir = $navegacao->invoke($controller, 'construir');
    $idsDominio = fn (array $nav) => collect($nav['linear'])
        ->filter(fn ($d) => $d['grupo'] === 'dominio')->pluck('id')->sort()->values()->all();

    expect($idsDominio($operar))->toBe($idsDominio($construir));
});

it('documento sem nav_group nao entra no rail', function () {
    // Contra-prova do opt-in: a pasta tem MUITO mais arquivo do que o rail mostra. Se um
    // dia o filtro cair, este caso vira vermelho na hora.
    $controller = new App\Http\Controllers\DocumentacaoController;
    $navegacao = (new ReflectionClass($controller))->getMethod('navegacao');
    $navegacao->setAccessible(true);

    $noRail = count($navegacao->invoke($controller, null)['linear']);
    $arquivos = glob(base_path('memory/reference/*.md'));

    expect(count($arquivos))->toBeGreaterThan($noRail);

    // E o rail tem exatamente os que declaram nav_group — nem a mais, nem a menos.
    $comGrupo = 0;
    foreach ($arquivos as $arquivo) {
        if (preg_match('/^nav_group:\s*\S+/m', (string) file_get_contents($arquivo))) {
            $comGrupo++;
        }
    }
    expect($noRail)->toBe($comGrupo);
});

it('responde 200 e renderiza o conteudo do dono quando autenticado', function () {
    // `hasTable` ANTES do query: na lane sqlite (:memory:, sem migrate) o
    // `User::query()` lançava "no such table: users" e derrubava o caso, em vez de
    // cair no skip que o cabeçalho deste arquivo já promete. Promessa não testada
    // apodrece calada — e era ela que impedia o arquivo de entrar na lane.
    $user = Illuminate\Support\Facades\Schema::hasTable('users')
        ? User::query()->whereNotNull('email')->first()
        : null;

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
