<?php

/**
 * Contrato das rotas /documentacao (ADR 0256 — a página É a fonte, renderizada).
 *
 * O que cada caso defende:
 *   1. as 4 rotas exigem login (decisão [W] 2026-08-02: doc interna não fica pública);
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
    foreach (['/documentacao', '/documentacao/buscar', '/documentacao/programa', '/documentacao/qualquer-slug'] as $rota) {
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

it('/documentacao/programa resolve pra programa, nao pra documento de slug "programa"', function () {
    // Mesmo defeito de ordem que já mordeu a busca: /{slug} tem regex que casa
    // "programa", então declarar a rota depois dela daria 404 de documento.
    $rota = app('router')->getRoutes()->match(
        Illuminate\Http\Request::create('/documentacao/programa', 'GET')
    );

    expect($rota->getName())->toBe('documentacao.programa');
    expect($rota->getActionMethod())->toBe('programa');
});

it('slug com ":" e com "/" casa a rota do documento — o caso cuja ausencia deixou o briefing quebrado 20 dias', function () {
    // POR QUE ESTE CASO EXISTE (2026-08-25).
    // O indexador (IndexarMemoryGitParaDb) gera slug com dois-pontos e barra:
    //   briefing:<mod>  ·  charter:<Mod>/<Tela>  ·  casos:<Mod>/<Tela>
    // O regex da rota era `[A-Za-z0-9._-]+`, que rejeita ambos. Resultado: o tipo
    // `briefing` entrou no TIPOS_DOC em 2026-08-05 e por 20 dias apareceu na BUSCA
    // com um link que dava 404 — medido em producao (302 = rota casou e pediu login;
    // 404 = o regex recusou antes do auth):
    //     /documentacao/qualquer-slug     302   (controle positivo)
    //     /documentacao/briefing:Arquivos 404   (o `:` derrubava)
    // Os casos de ORDEM acima nao pegavam isso: exercem so slug ASCII simples.
    $casos = [
        'briefing:arquivos'      => 'so dois-pontos (o formato que quebrou em 2026-08-05)',
        'charter:Arquivos/Index' => 'dois-pontos + barra (trio de tela)',
        'casos:Arquivos/Index'   => 'dois-pontos + barra (trio de tela)',
    ];

    foreach ($casos as $slug => $porque) {
        $rota = app('router')->getRoutes()->match(
            Illuminate\Http\Request::create('/documentacao/' . $slug, 'GET')
        );

        expect($rota->getName())->toBe('documentacao.documento', $porque);
        expect($rota->parameter('slug'))->toBe($slug, $porque);
    }
});

it('CONTROLE NEGATIVO: /buscar e /programa continuam ganhando do {slug} mesmo com o regex ampliado', function () {
    // Ampliar o regex pra aceitar `/` faz o {slug} casar mais de um segmento. A
    // precedencia continua vindo da ORDEM de declaracao — este caso prova que
    // ampliar nao engoliu as rotas irmas, que e o unico risco real da mudanca.
    foreach (['buscar' => 'documentacao.buscar', 'programa' => 'documentacao.programa'] as $seg => $nome) {
        $rota = app('router')->getRoutes()->match(
            Illuminate\Http\Request::create('/documentacao/' . $seg, 'GET')
        );
        expect($rota->getName())->toBe($nome);
    }
});

it('o plano que a tela do programa renderiza existe e tem a estrutura que o controller parseia', function () {
    // Espelha a const PLANO do DocumentacaoController. Sem qualquer uma destas
    // subseções o controller aborta 503 — melhor descobrir aqui que em produção.
    $plano = base_path('memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md');

    expect(file_exists($plano))->toBeTrue();

    $conteudo = file_get_contents($plano);

    foreach (['D.3', 'D.4', 'D.5', 'D.7'] as $codigo) {
        expect($conteudo)->toMatch('/^###\s+' . preg_quote($codigo, '/') . '\s/m');
    }

    // O ESTADO não sai mais daqui: até a US-DOC-002 a tela lia a onda de uma célula escrita à
    // mão no `## Status vivo` (AR-DOC-068). Hoje ele vem das tasks MCP — ver UC-PROGRA-01.
});

it('UC-PROGRA-02 · a tela do programa NAO carrega a lista de estacoes escrita a mao', function () {
    // ESTE É O CASO QUE IMPORTA. A tela promete no rodapé que "renderiza o plano, não é
    // cópia commitada". Se alguém colar as estações/ondas na Blade pra "ficar mais
    // simples", a promessa vira mentira e a tela drifa do plano em silêncio — que é
    // exatamente o que a § Trilha D proíbe ("ponteiro > cópia").
    //
    // Bite-test: colar qualquer título de estação do plano na view quebra este caso.
    // Desde a US-DOC-002 a tela é o Programa.tsx — a Blade foi apagada no cutover.
    $view = base_path('resources/js/Pages/Documentacao/Programa.tsx');
    expect(file_exists($view))->toBeTrue();

    $blade = file_get_contents($view);
    $plano = file_get_contents(base_path('memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md'));

    preg_match('/^###\s+D\.4\s.*?$(.*?)(?=^#{2,3}\s)/ms', $plano, $secao);
    preg_match_all('/^\d+\.\s+\*\*(.+?):?\*\*/m', $secao[1] ?? '', $titulos);

    expect($titulos[1])->not->toBeEmpty('a D.4 do plano deveria ter estações numeradas');

    foreach ($titulos[1] as $titulo) {
        expect($blade)->not->toContain(trim($titulo));
    }
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
        glob(base_path('Modules/Forja/Database/Migrations/*.php')),
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

    // SUB-SEÇÕES (h4) entram no trilho. 14 seções deste guia são h4 — entre elas TODO o
    // B8 ("quem pode alterar o quê"), que existia na página e não na navegação enquanto o
    // sumário casava só h2|h3. Estar na página não é estar navegável.
    expect($ids)->toContain('b8-1');
    expect($ids)->toContain('b8-4');
    expect($ids)->toContain('b6-2');
    expect(array_column($sumario, 'nivel'))->toContain(4);

    // A âncora do sub-tópico deriva do CÓDIGO da seção ("B8.1" → `b8-1`), não de um
    // sufixo de desempate. Sufixo (`b8-2` no sentido de "segundo b8") mudaria sozinho
    // quando um irmão nascesse acima — âncora que se move não serve pra ser copiada.
    $porId = array_column($sumario, 'rotulo', 'id');
    expect($porId['b8-1'])->not->toStartWith('B8.1');       // o código não se repete no rótulo
    expect($porId['b8-1'])->toContain('Quem pode alterar');

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

    // 2026-09-02 (ADR UI-0031): o lembrete que o comentário anterior deixou marcado
    // DISPAROU — o DS passou a declarar `--accent` no escuro. A divergência local (0.74)
    // existia porque o dark HERDAVA oklch(0.55 …), que sobre este papel dá contraste 3.02
    // e reprova o AA de texto. O par escuro novo do DS é oklch(0.70 0.15 295) = 5.55,
    // acima de 4.5 — a causa da exceção sumiu, então a exceção sumiu junto e o `--accent`
    // do layout voltou a ser paridade pura com o DS, como no claro.
    $dsEscuro = $bloco($dsDark, '.cockpit[data-theme="dark"]');
    $localEscuro = $bloco($layout, ':root[data-theme="dark"]');

    foreach (['--paper' => '--bg', '--surface' => '--bg-2', '--ink' => '--text',
        '--ink-soft' => '--text-dim', '--ink-mute' => '--text-mute',
        '--rule' => '--border', '--rule-soft' => '--border-2',
        '--accent' => '--accent', '--accent-bg' => '--accent-soft'] as $local => $token) {
        // `toHaveKey($k, $v)` compara o VALOR no 2º argumento — não é mensagem. Pra dar
        // mensagem própria, o assert é sobre o booleano. (Custou um CI vermelho em 2026-09-02.)
        expect(array_key_exists($token, $dsEscuro))->toBeTrue(
            "o DS parou de declarar {$token} no dark — se foi de propósito, reconcilie o layout junto"
        );
        expect($localEscuro[$local])->toBe(
            $dsEscuro[$token],
            "{$local} (dark) divergiu de {$token} do DS"
        );
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

it('UC-INDEX-03 · o rail e derivado do frontmatter, com ordinal da ordem visivel na lente', function () {
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
    // Smoke HTTP de ponta a ponta: login + stack completo de middleware (AR-DOC-050) + Inertia.
    // `hasTable` ANTES do query: na lane sqlite (:memory:, sem migrate) o `User::query()`
    // lançaria "no such table" em vez de cair no skip. O contrato da tela NÃO depende deste
    // caso — quem o sustenta são UC-INDEX-01/02, que chamam o controller e sempre rodam.
    $user = Illuminate\Support\Facades\Schema::hasTable('users')
        ? User::query()->whereNotNull('email')->whereNotNull('business_id')->first()
        : null;

    if (! $user) {
        $this->markTestSkipped('Sem users no DB — este caso não executou.');
    }

    $this->actingAs($user)->get('/documentacao')
        ->assertOk()
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Documentacao/Index')
            ->where('fonte', 'memory/GUIA-DO-SISTEMA.md')
            ->has('html')
            ->has('nav.grupos'));
});

/** Props de uma resposta Inertia ANTES da serialização (sem renderizar view). */
function docInertiaProps(Inertia\Response $r): array
{
    $ref = new ReflectionClass($r);
    $props = $ref->getProperty('props');
    $props->setAccessible(true);
    $comp = $ref->getProperty('component');
    $comp->setAccessible(true);

    return ['component' => $comp->getValue($r), 'props' => $props->getValue($r)];
}

it('UC-INDEX-01 · a capa entrega o Guia convertido NO SERVIDOR, com sumario e rail', function () {
    // Chama o controller direto: roda em qualquer lane, sem sessão e sem banco. O que se prova
    // é o contrato da camada de render (AR-DOC-001/003/004/007), não o middleware.
    $r = (new App\Http\Controllers\DocumentacaoController)
        ->index(Illuminate\Http\Request::create('/documentacao'));
    ['component' => $componente, 'props' => $p] = docInertiaProps($r);

    expect($componente)->toBe('Documentacao/Index');
    expect($p['fonte'])->toBe('memory/GUIA-DO-SISTEMA.md');

    // HTML de verdade, e não o markdown cru: o cliente não roda parser (AR-DOC-003).
    expect($p['html'])->toContain('<h2');
    expect($p['html'])->not->toContain('slug: guia-do-sistema');
    expect(array_key_exists('markdown', $p))->toBeFalse();

    // Sumário derivado dos títulos, na forma que o React consome (AR-DOC-004).
    expect($p['sumario'])->not->toBeEmpty();
    expect(array_keys($p['sumario'][0]))->toBe(['id', 'nivel', 'codigo', 'rotulo']);

    // A capa não marca item no rail (AR-DOC-007), e o rail veio derivado.
    expect($p['atual'])->toBeNull();
    expect($p['nav']['grupos'])->not->toBeEmpty();

    // O link do git aponta pro arquivo dono em main — a tela não hospeda cópia.
    expect($p['blob'])->toBe('https://github.com/wagnerra23/oimpresso.com/blob/main/memory/GUIA-DO-SISTEMA.md');

    // Escopo derivado de TIPOS_DOC chega como prop: Inertia não enxerga View::share.
    $tipos = (new ReflectionClass(App\Http\Controllers\DocumentacaoController::class))->getConstant('TIPOS_DOC');
    expect($p['escopo']['tipos'])->toBe($tipos);
});

it('UC-INDEX-02 · Guia ausente no deploy: 503 nomeando o arquivo, nunca pagina vazia', function () {
    // Só a existência do Guia é falseada; o resto do filesystem segue real.
    $guia = base_path('memory/GUIA-DO-SISTEMA.md');
    Illuminate\Support\Facades\File::partialMock()
        ->shouldReceive('exists')
        ->andReturnUsing(fn (string $path) => $path === $guia ? false : file_exists($path));

    expect(fn () => (new App\Http\Controllers\DocumentacaoController)
        ->index(Illuminate\Http\Request::create('/documentacao')))
        ->toThrow(
            Symfony\Component\HttpKernel\Exception\HttpException::class,
            'Documento fonte ausente no deploy: memory/GUIA-DO-SISTEMA.md',
        );
});

it('o escopo que a pagina MOSTRA e derivado de TIPOS_DOC — nenhum tipo some calado', function () {
    // O defeito que este caso mata (2026-08-05): as views enumeravam
    // "adr · reference · spec · runbook" DIGITADO à mão, em 4 lugares. A lista ficou
    // mentindo duas vezes seguidas — `feature` entrou em 08-04, `briefing` em 08-05, e
    // nenhum dos rótulos acompanhou. Quem lia a página concluía que o acervo era menor
    // do que é. Não é presence-gate (não olha o texto do .blade): mede a DERIVAÇÃO —
    // se um tipo novo entrar em TIPOS_DOC sem chegar ao que a página mostra, cai aqui.
    $classe = new ReflectionClass(App\Http\Controllers\DocumentacaoController::class);
    $tipos = $classe->getConstant('TIPOS_DOC');
    $rotulos = $classe->getConstant('TIPOS_DOC_ROTULO');

    // 1. Todo tipo tem rótulo humano. O PHPStan já cobra isto — escopoEmProsa() indexa
    //    direto, sem fallback, então tipo sem rótulo derruba a análise estática nomeando
    //    o tipo. A invariante é importante demais pra depender de uma ferramenta só.
    $semRotulo = array_values(array_diff($tipos, array_keys($rotulos)));
    expect($semRotulo)->toBe([]);

    // 2. A prosa cobre TODOS os tipos. Por diferença de conjuntos, não por toContain
    //    com mensagem — o diagnóstico mostra exatamente qual tipo ficou de fora.
    $escopoEmProsa = $classe->getMethod('escopoEmProsa');
    $escopoEmProsa->setAccessible(true);
    $prosa = $escopoEmProsa->invoke(null);

    $foraDaProsa = array_values(array_filter(
        $tipos,
        fn (string $t): bool => ! str_contains($prosa, $rotulos[$t] ?? $t)
    ));
    expect($foraDaProsa)->toBe([]);

    // 3. O construtor PUBLICA os dois pra toda view da rota — incluindo o layout, que
    //    carrega o aria-label da busca e não recebe payload de método nenhum. Sem esta
    //    perna, a derivação existiria e não chegaria na tela.
    new App\Http\Controllers\DocumentacaoController;

    expect(Illuminate\Support\Facades\View::shared('escopoTipos'))->toBe($tipos);
    expect(Illuminate\Support\Facades\View::shared('escopoProsa'))->toBe($prosa);
});

/**
 * Plano mínimo com as cinco estruturas que `programa()` exige (D.3–D.7). As estações e a
 * onda têm nomes que NÃO existem no plano real — é assim que o UC-PROGRA-02 prova que o
 * payload vem do arquivo, e não de lista escrita no código.
 */
function programaPlanoFixture(string $estacao = 'Estação Fixtura Alfa', bool $semD4 = false): string
{
    $d4 = $semD4 ? '' : "1. **{$estacao}:** corpo da estação.\n2. **Estação Fixtura Beta:** outro corpo.\n";

    return "---\nlast_updated: \"2030-01-02\"\n---\n\n## Trilha D — documentação técnica e operacional\n\n"
        . "### D.3 Ondas\n\n| Onda | Escopo | Saída | Gate |\n|---|---|---|---|\n"
        . "| **D0 · Onda Fixtura** | escopo x | saída x | gate x |\n| **D1 · Outra Fixtura** | e | s | g |\n\n"
        . "### D.4 Ciclo\n\n{$d4}\n"
        . "### D.5 Caminhos\n\n| Tipo | Caminho | Responde |\n|---|---|---|\n| máquina | a → b | o quê |\n\n"
        . "### D.6 Batimento\n\n| Momento | Máquina | Efeito |\n|---|---|---|\n| PR | gate | barra |\n\n"
        . "### D.7 Pronto\n\n- critério fixtura um;\n- critério fixtura dois.\n\n## Próxima seção\n";
}

/** Faz `programa()` ler a fixture no lugar do plano real; o resto do filesystem segue real. */
function programaComPlano(?string $conteudo): Inertia\Response
{
    $plano = base_path('memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md');
    Illuminate\Support\Facades\File::partialMock()
        ->shouldReceive('exists')
        ->andReturnUsing(fn (string $path) => $path === $plano ? $conteudo !== null : file_exists($path));
    Illuminate\Support\Facades\File::shouldReceive('get')
        ->andReturnUsing(fn (string $path) => $path === $plano ? $conteudo : file_get_contents($path));

    return (new App\Http\Controllers\DocumentacaoController)
        ->programa(Illuminate\Http\Request::create('/documentacao/programa'));
}

/** Invoca a projeção PURA do estado (sem banco) — é o que o UC-PROGRA-01/03 exercem. */
function programaEstado(?array $tasks, array $ondas): array
{
    $controller = new App\Http\Controllers\DocumentacaoController;
    $m = (new ReflectionClass($controller))->getMethod('estadoDoPrograma');
    $m->setAccessible(true);

    return $m->invoke($controller, $tasks, $ondas);
}

it('a tela do programa é Inertia e entrega a § Trilha D lida do plano real', function () {
    ['component' => $componente, 'props' => $p] = docInertiaProps(
        (new App\Http\Controllers\DocumentacaoController)
            ->programa(Illuminate\Http\Request::create('/documentacao/programa'))
    );

    expect($componente)->toBe('Documentacao/Programa');
    expect($p['fonte'])->toBe('memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md');
    foreach (['ondas', 'estacoes', 'caminhos', 'batimento', 'dod'] as $bloco) {
        expect($p[$bloco])->not->toBeEmpty("o bloco {$bloco} veio vazio do plano real");
    }
    expect($p['atual'])->toBeNull();   // o Programa não marca item no rail (AR-DOC-066)
    expect($p['estado'])->toHaveKey('disponivel');
});

// @covers-us US-INFRA-048
it('UC-PROGRA-01 · o estado vem das tasks MCP do programa, agrupado por onda — nunca do plano', function () {
    // A 1ª parte do gate da US-INFRA-048 é o PLANO LIGADO AO MCP. Aqui a ligação é provada no
    // consumidor real: tasks com parent_plan=programa-ondas, marcadas com `onda:`, viram o
    // estado de cada onda. Onda sem task fica `sem_task` — não herda estado de ninguém.
    $ondas = [
        ['codigo' => 'D0', 'nome' => 'a'], ['codigo' => 'D1', 'nome' => 'b'],
        ['codigo' => 'D2', 'nome' => 'c'], ['codigo' => 'D3', 'nome' => 'd'],
    ];
    $e = programaEstado([
        ['id' => 'US-X-1', 'status' => 'doing', 'onda' => 'D0'],
        ['id' => 'US-X-2', 'status' => 'done', 'onda' => 'D0'],
        ['id' => 'US-X-3', 'status' => 'todo', 'onda' => 'D1'],
        ['id' => 'US-X-4', 'status' => 'done', 'onda' => 'D2'],
        ['id' => 'US-X-5', 'status' => 'todo', 'onda' => null],
    ], $ondas);

    expect($e['disponivel'])->toBeTrue();
    expect($e['total'])->toBe(5);
    expect($e['ondas']['D0']['estado'])->toBe('andamento');   // task aberta vence a concluída
    expect($e['ondas']['D1']['estado'])->toBe('fila');
    expect($e['ondas']['D2']['estado'])->toBe('concluida');
    expect($e['ondas']['D3']['estado'])->toBe('sem_task');
    expect(array_column($e['semOnda'], 'id'))->toBe(['US-X-5']);

    // E nada de status chumbado onde o SPEC proíbe: nem no .tsx nem nos parsers do plano.
    $tsx = file_get_contents(base_path('resources/js/Pages/Documentacao/Programa.tsx'));
    expect(str_contains($tsx, 'doing'))->toBeFalse();
    expect(str_contains($tsx, 'em execução'))->toBeFalse();
});

it('UC-PROGRA-02 · mudar o plano muda o payload sem tocar PHP nem TSX', function () {
    ['props' => $p] = docInertiaProps(programaComPlano(programaPlanoFixture('Estação Renomeada Gama')));

    expect(array_column($p['estacoes'], 'titulo'))->toBe(['Estação Renomeada Gama', 'Estação Fixtura Beta']);
    expect(array_column($p['ondas'], 'codigo'))->toBe(['D0', 'D1']);
    expect($p['ondas'][0]['nome'])->toBe('Onda Fixtura');
    expect($p['dod'])->toBe(['critério fixtura um', 'critério fixtura dois']);
    expect($p['atualizadoEm'])->toBe('2030-01-02');
});

it('UC-PROGRA-03 · sem MCP a tela diz que não sabe — nenhuma onda ganha estado default', function () {
    $e = programaEstado(null, [['codigo' => 'D0', 'nome' => 'a']]);

    expect($e['disponivel'])->toBeFalse();
    expect($e['ondas'])->toBe([]);
    expect($e['total'])->toBe(0);
});

it('UC-PROGRA-04 · a tela não tem caminho de escrita', function () {
    $tsx = file_get_contents(base_path('resources/js/Pages/Documentacao/Programa.tsx'));

    foreach (['<form', 'useForm', 'router.post', 'router.put', 'router.patch', 'router.delete', 'method="post"', 'axios', 'fetch('] as $escrita) {
        expect(str_contains($tsx, $escrita))->toBeFalse("o Programa.tsx ganhou um caminho de escrita: {$escrita}");
    }
});

it('UC-PROGRA-05 · plano ausente no deploy dá 503 nomeando o arquivo', function () {
    expect(fn () => programaComPlano(null))->toThrow(
        Symfony\Component\HttpKernel\Exception\HttpException::class,
        'Plano ausente no deploy: memory/requisitos/_Governanca/programa-ondas/PLANO-MESTRE.md',
    );
});

it('UC-PROGRA-05 · subseção do plano vazia dá 503 nomeando qual, nunca tela com seção vazia', function () {
    expect(fn () => programaComPlano(programaPlanoFixture(semD4: true)))->toThrow(
        Symfony\Component\HttpKernel\Exception\HttpException::class,
        'Estrutura ausente na § Trilha D do plano: D.4 estações',
    );
});

it('UC-PROGRA-06 · o payload não carrega tenant nem segredo', function () {
    ['props' => $p] = docInertiaProps(programaComPlano(programaPlanoFixture()));
    $json = json_encode($p, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    expect($json)->not->toContain('business_id');
    expect($json)->not->toMatch('/\b\d{1,3}(?:\.\d{1,3}){3}\b/');   // IP de host
    expect($json)->not->toMatch('/(?:token|password|senha|secret)"\s*:/i');

    // O estado leva só id e balde da task — nada de dono, descrição ou tenant.
    $e = programaEstado([['id' => 'US-X-1', 'status' => 'doing', 'onda' => 'D0']], [['codigo' => 'D0', 'nome' => 'a']]);
    expect(array_keys($e['ondas']['D0']['tasks'][0]))->toBe(['id', 'balde']);
});
