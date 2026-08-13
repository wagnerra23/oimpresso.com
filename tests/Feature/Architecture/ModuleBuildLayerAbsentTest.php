<?php

declare(strict_types=1);

// Tests\TestCase já é aplicado globalmente em tests/Pest.php (uses(TestCase::class)->in('Feature')).
// NÃO redeclarar aqui — Pest 4 lança TestCaseAlreadyInUse.

use App\Services\ModuleSpecGenerator;

/**
 * ARCHITECTURE TEST — a camada de build POR MÓDULO não existe, e nada a reintroduz.
 *
 * ⚠️ Glob de path NÃO entra neste docblock: `asterisco-barra` fecha o comentário e o
 * arquivo deixa de compilar (incidente 2026-07-28). Use `Modules/<X>/` na prosa.
 *
 * A REGRA: o bundle do app é da RAIZ (`vite.config.js` + `vite.inertia.config.mjs`).
 * Laravel Mix saiu do projeto — `laravel-mix` não está em nenhuma dependência do
 * `package.json` raiz. Módulo não tem build próprio.
 *
 * O QUE FOI REMOVIDO em 2026-08-12 (#5680), e por que este teste existe:
 * 12 `Modules/<X>/webpack.mix.js` + 3 `vite.config.js` + 16 `package.json`, mais as 4
 * entradas de `config('modules.stubs.files')` que os recriariam no próximo
 * `module:make`, mais as flags `has_mix`/`has_vite` do `ModuleSpecGenerator`, que
 * escreviam `Build: Laravel Mix` em 15 docs — afirmando um pipeline que ninguém roda.
 *
 * A LINHA QUE NÃO PODE SER CRUZADA (é o motivo do caso 2):
 * `Modules/<X>/Resources/assets/` FICA. Ele não é entrada de build — é a fonte de
 * `php artisan module:publish`, e serve conteúdo VIVO: o CSS/JS/imagens do site público
 * do Cms, o JS do CRM, o `easy.qrcode.min.js` da tela de QR do catálogo e o `.xls` de
 * importação de ponto do Essentials. A prova de que é publish e não build está na FORMA:
 * `public/modules/<x>/` é cópia 1:1 da pasta — com `.gitkeep`, com imagens, e com `sass/`
 * preservada literal, que um bundler jamais produziria (ele emitiria `css/`).
 *
 * POR QUE COMPORTAMENTO E NÃO GREP: os casos 1 e 4 exercem o CONSUMIDOR real — o config
 * resolvido pelo container e o markdown que o gerador de fato emite. Grepar o fonte por
 * `has_mix` mediria PRESENÇA, não comportamento, e é a classe que o ledger registra
 * como LC-11 (presence-gate). O caso 5 é o controle negativo: prova que a remoção não
 * desligou a seção Assets inteira — sem ele, "não emite Laravel Mix" passaria mesmo que
 * a seção tivesse sumido por engano.
 */
it('scaffold de módulo novo não gera build próprio (stubs.files)', function () {
    $files = config('modules.stubs.files');

    expect($files)->toBeArray()
        ->and($files)->not->toHaveKey('vite')
        ->and($files)->not->toHaveKey('package')
        ->and($files)->not->toHaveKey('assets/js/app')
        ->and($files)->not->toHaveKey('assets/sass/app');

    // O replacement órfão também sai: ele só servia ao arquivo que não nasce mais.
    expect(config('modules.stubs.replacements'))->not->toHaveKey('vite');
});

it('Resources/assets CONTINUA sendo gerado — é a fonte do module:publish, não build', function () {
    // Controle negativo do caso anterior: se alguém "limpar" o scaffold longe demais,
    // módulo novo nasce sem a pasta que o publish consome, e o efeito só aparece em
    // produção (asset 404). Este caso é o que impede a limpeza de passar do ponto.
    expect(config('modules.paths.generator.assets'))
        ->toMatchArray(['path' => 'Resources/assets', 'generate' => true]);
});

it('nenhum módulo tem config de build próprio na árvore', function () {
    $reintroduzidos = [];

    foreach (glob(base_path('Modules/*'), GLOB_ONLYDIR) as $dir) {
        foreach (['webpack.mix.js', 'vite.config.js', 'vite.config.ts'] as $arquivo) {
            if (file_exists("{$dir}/{$arquivo}")) {
                $reintroduzidos[] = basename($dir) . "/{$arquivo}";
            }
        }
    }

    expect($reintroduzidos)->toBe([]);
});

it('module:specs não afirma "Build: Laravel Mix" — o pipeline não existe', function () {
    $gerador = app(ModuleSpecGenerator::class);

    // Módulo REAL com assets de verdade: o Cms tem css/js/imagens em Resources/assets,
    // então é o caso onde a seção Assets é de fato emitida — testar um módulo sem assets
    // passaria por vacuidade (a seção nem sairia) e não provaria nada.
    $markdown = $gerador->renderMarkdown($gerador->inspect('Cms'));

    expect($markdown)
        ->not->toContain('Build: **Laravel Mix**')
        ->not->toContain('Build: **Vite**')
        ->not->toContain('webpack.mix.js');
});

it('CONTROLE NEGATIVO — a seção Assets continua sendo emitida (a remoção não a matou)', function () {
    $gerador = app(ModuleSpecGenerator::class);
    $markdown = $gerador->renderMarkdown($gerador->inspect('Cms'));

    // Sem este caso, o teste anterior passaria mesmo que a condição da seção tivesse
    // sido quebrada e o bloco inteiro sumisse — "não contém Laravel Mix" é trivialmente
    // verdadeiro num markdown que perdeu a seção.
    expect($markdown)->toContain('## Assets (JS / CSS)');
});
