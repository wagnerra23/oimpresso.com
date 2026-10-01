<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Ponto\Tests\Feature\PontoTestCase;

uses(PontoTestCase::class);

/**
 * Contrato da página pública de exclusão de conta e dados do app de ponto.
 *
 * UCs de `resources/js/Pages/Ponto/Publico/Exclusao.casos.md` (charter + Portaria MTP
 * 671/2021). O texto publicado É o contrato com a loja, por isso UC-EXCL-02/03 o leem.
 * Sem tenant: a página não lê banco nem sessão.
 */

function exclTexto(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Ponto/Publico/Exclusao.tsx'));
}

it('UC-EXCL-01 — abre sem login, na URL estável', function () {
    $this->assertGuest();
    $this->get('/privacidade/ponto/exclusao')->assertOk();
    $this->assertInertiaComponent($this->inertiaGet('/privacidade/ponto/exclusao'), 'Ponto/Publico/Exclusao');
});

it('UC-EXCL-02 — diz que a conta é do empregador e oferece canal', function () {
    $texto = exclTexto();
    $this->assertStringContainsString('gerenciada pelo seu', $texto);
    $this->assertStringContainsString('mailto:lgpd@oimpresso.com.br', $texto);
});

it('UC-EXCL-03 — explica a retenção legal e não promete apagar marcação', function () {
    $texto = exclTexto();
    $this->assertStringContainsString('não pode ser apagado nem', $texto);
    $this->assertDoesNotMatchRegularExpression('/descartad|apagamos (as )?(suas )?marca/iu', $texto);
});

it('UC-EXCL-04 — não grava nada em GET', function () {
    $tabelas = array_values(array_filter(['ponto_marcacoes', 'ponto_intercorrencias'], fn ($t) => Schema::hasTable($t)));
    expect($tabelas)->not->toBeEmpty();
    $antes = array_map(fn ($t) => DB::table($t)->count(), $tabelas);

    $this->get('/privacidade/ponto/exclusao')->assertOk();

    $this->assertSame($antes, array_map(fn ($t) => DB::table($t)->count(), $tabelas));
});
