<?php

declare(strict_types=1);

/**
 * Contrato da política de privacidade pública do app oimpresso (`/privacidade`).
 *
 * UCs de `resources/js/Pages/Site/PrivacidadeApp.casos.md`. O texto da página É o contrato com a
 * loja de aplicativo, por isso UC-PRIVAPP-02/03/04 leem o texto publicado.
 *
 * Sem tenant e sem banco: a página não lê banco nem sessão (sqlite-safe).
 */
function privAppTexto(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Site/PrivacidadeApp.tsx'));
}

it('UC-PRIVAPP-01 — abre sem login, na URL estável', function () {
    $this->assertGuest();
    $this->get('/privacidade')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('Site/PrivacidadeApp', false));
});

it('UC-PRIVAPP-02 — cobre os dados do ERP e aponta para a política do ponto', function () {
    $texto = privAppTexto();
    $this->assertStringContainsString('Dados de clientes e fornecedores da empresa', $texto);
    $this->assertStringContainsString('href="/privacidade/ponto"', $texto);
});

it('UC-PRIVAPP-03 — diz que marcação de ponto não pode ser apagada', function () {
    $texto = privAppTexto();
    $this->assertStringContainsString('não podem ser apagadas nem', $texto);
    $this->assertDoesNotMatchRegularExpression('/apagamos (as )?(suas )?marca/iu', $texto);
});

it('UC-PRIVAPP-04 — declara que não usa localização em segundo plano', function () {
    $this->assertStringContainsString('não usa a localização em segundo plano', privAppTexto());
});
