<?php

declare(strict_types=1);

use Modules\Jana\Http\Controllers\DataController;

uses(Tests\TestCase::class);

/**
 * A chave de assinatura do módulo Jana é `jana_module`, e os DOIS consumidores concordam.
 *
 * ## Por que existe
 *
 * Decisão [W] em 2026-08-26, textual: *"copiloto_module é erro"*. Até ali o gate lia
 * `copiloto_module` — sobra da migração parcial da ADR 0088, que renomeou o PHP e deixou a
 * fachada. O sinal de que era sobra e não decisão: a linha logo abaixo, no MESMO arquivo,
 * já lê a permission `jana.access`. A dimensão de permissions andou; a chave de módulo ficou.
 *
 * ## O que o teste defende
 *
 * Não é a existência da string — é o **acordo entre os dois consumidores**. O gate do menu
 * (`DataController::modifyAdminMenu`) e o atalho do sidebar
 * (`HandleInertiaRequests::sidebarShortcuts`) perguntam pela mesma chave em arquivos
 * diferentes. Se uma delas mudar sozinha, o usuário passa a ver o atalho sem o item de menu
 * (ou o contrário) — divergência que nenhum dos dois lados denuncia, porque cada um responde
 * corretamente à pergunta que faz.
 *
 * ## Honestidade sobre a forma
 *
 * O 1º caso é **comportamental**: chama o método e lê o que ele devolve. Os outros dois são
 * de **fonte**, e digo por quê em vez de disfarçar: a chave que o gate passa é um literal
 * dentro de um `if`, e exercitá-la ao vivo exigiria montar business + subscription +
 * package_details + sessão — fixture que provaria o Laravel, não o acordo. O que interessa
 * aqui é que as duas strings sejam a mesma, e isso é uma propriedade do código.
 *
 * @see memory/decisions/0088-module-rename-php-only.md
 */
it('a chave DECLARADA no painel de pacotes e jana_module', function () {
    $declarado = (new DataController())->superadmin_package();

    expect($declarado)->toBeArray()->not->toBeEmpty();
    expect($declarado[0]['name'])->toBe('jana_module');
})->group('jana');

it('o gate do MENU e o atalho do SIDEBAR perguntam pela MESMA chave', function () {
    $menu    = file_get_contents(base_path('Modules/Jana/Http/Controllers/DataController.php'));
    $sidebar = file_get_contents(base_path('app/Http/Middleware/HandleInertiaRequests.php'));

    // Extrai o 2º argumento de cada `hasThePermissionInSubscription(...)`, que é a chave.
    $chaves = static function (string $fonte): array {
        preg_match_all(
            '/hasThePermissionInSubscription\s*\(\s*[^,]+,\s*[\'"]([a-z0-9_]+_module)[\'"]/i',
            $fonte,
            $m
        );

        return array_values(array_unique($m[1]));
    };

    $doMenu    = $chaves($menu);
    $doSidebar = $chaves($sidebar);

    // Controle: se o regex parar de casar, o teste vira verde vazio — o assert de
    // não-vazio é o que impede "0 chaves encontradas" de passar por acordo.
    expect($doMenu)->not->toBeEmpty();
    expect($doSidebar)->not->toBeEmpty();

    expect($doMenu)->toContain('jana_module');
    expect($doSidebar)->toContain('jana_module');
})->group('jana');

it('nenhum consumidor de codigo ainda le a chave legada copiloto_module', function () {
    // Só os dois arquivos que consomem — não o repo inteiro: session log e handoff citam a
    // chave antiga como FATO DATADO, e apagar história não é conserto.
    foreach ([
        'Modules/Jana/Http/Controllers/DataController.php',
        'app/Http/Middleware/HandleInertiaRequests.php',
    ] as $arquivo) {
        $fonte = file_get_contents(base_path($arquivo));

        // Remove comentários de linha antes de procurar: o próprio conserto EXPLICA a troca
        // citando o nome antigo, e um assert cru acusaria a explicação.
        $semComentario = preg_replace('~^\s*//.*$~m', '', $fonte);

        expect($semComentario)->not->toContain('copiloto_module');
    }
})->group('jana');
