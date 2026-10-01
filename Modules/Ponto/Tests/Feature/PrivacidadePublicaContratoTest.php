<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da política de privacidade pública do app de ponto (`/privacidade/ponto`).
 *
 * UCs de `resources/js/Pages/Ponto/Publico/Privacidade.casos.md`, derivados do charter +
 * ADR 0383 (sem biometria) + Portaria MTP 671/2021 (append-only). O texto da página É o
 * contrato com a loja de aplicativo, por isso UC-PRIV-02/03 leem o texto publicado.
 *
 * Sem tenant: a página não lê banco nem sessão.
 */

function privTexto(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Ponto/Publico/Privacidade.tsx'));
}

it('UC-PRIV-01 — abre sem login, na URL estável', function () {
    $this->assertGuest();
    $this->get('/privacidade/ponto')->assertOk();
    $this->assertInertiaComponent($this->inertiaGet('/privacidade/ponto'), 'Ponto/Publico/Privacidade');
});

it('UC-PRIV-02 — declara que o app não coleta biometria nem imagem (ADR 0383)', function () {
    $this->assertStringContainsString('não coleta biometria nem imagem', privTexto());
});

it('UC-PRIV-03 — diz que marcação não pode ser apagada e não promete excluí-la', function () {
    $texto = privTexto();
    $this->assertStringContainsString('não podem ser apagadas nem alteradas', $texto);
    $this->assertDoesNotMatchRegularExpression('/apagamos (as )?(suas )?marca/iu', $texto);
});

it('UC-PRIV-04 — não grava nada em GET', function () {
    $tabelas = array_values(array_filter(['ponto_marcacoes', 'ponto_intercorrencias'], fn ($t) => Schema::hasTable($t)));
    expect($tabelas)->not->toBeEmpty();
    $antes = array_map(fn ($t) => DB::table($t)->count(), $tabelas);

    $this->get('/privacidade/ponto')->assertOk();

    $this->assertSame($antes, array_map(fn ($t) => DB::table($t)->count(), $tabelas));
});
