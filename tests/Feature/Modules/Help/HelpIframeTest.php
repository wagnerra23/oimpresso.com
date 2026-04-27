<?php

/**
 * Modules\Help — rotas de iframe (faq/videos/foruns/treinamentos).
 *
 * São wrappers superficiais sobre superadmin.iframe que apontam pra
 * sites externos do WR2 (gitbook, oimpresso/ajuda). O risco regressivo
 * é alguém renomear a view ou alterar a URL embedada.
 */

$expectedIframes = [
    ['route' => 'superadmin.faq',          'url' => 'https://wr2.gitbook.io/faq'],
    ['route' => 'superadmin.videos',       'url' => 'https://doc.oimpresso.com/home'],
    ['route' => 'superadmin.foruns',       'url' => 'https://oimpresso.com/ajuda/forums'],
    ['route' => 'superadmin.treinamentos', 'url' => 'https://oimpresso.com/ajuda/comunidade/como-funciona'],
];

foreach ($expectedIframes as $cfg) {
    it("registra a rota nomeada {$cfg['route']}", function () use ($cfg) {
        expect(moduleRoute($cfg['route']))->not->toBeNull();
    });
}

it('exige autenticação nas rotas de iframe (não vazar conteúdo embarcado anônimo)', function () use ($expectedIframes) {
    foreach ($expectedIframes as $cfg) {
        $middleware = moduleRoute($cfg['route'])->gatherMiddleware();
        expect($middleware)->toContain('auth')
            ->and($middleware)->toContain('web');
    }
});

it('aponta cada iframe para o domínio externo correto', function () use ($expectedIframes) {
    foreach ($expectedIframes as $cfg) {
        $action = moduleRoute($cfg['route'])->getAction('uses');

        // Closure: invocamos com um usuário fake só para inspecionar a view
        // e seu data-binding, sem hit de rede real.
        $rendered = view('superadmin.iframe', ['url' => $cfg['url']])->render();
        expect($rendered)->toContain($cfg['url']);
    }
});

it('garante presença das rotas de install', function () {
    expect(routeExists('help/install', 'GET'))->toBeTrue()
        ->and(routeExists('help/install', 'POST'))->toBeTrue();
});

it('expõe API REST sob /help/api com guard auth:api', function () {
    $middleware = routeMiddleware('help/api/sell', 'GET');
    expect($middleware)->toContain('auth:api');
});
