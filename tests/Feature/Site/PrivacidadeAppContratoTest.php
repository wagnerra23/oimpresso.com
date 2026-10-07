<?php

declare(strict_types=1);

/**
 * Contrato da política de privacidade pública do app oimpresso (`/privacidade`).
 *
 * UCs de `resources/js/Pages/Site/PrivacidadeApp.casos.md`. O texto da página É o contrato com a
 * loja de aplicativo, por isso UC-PRVAPP-02/03/04 leem o texto publicado.
 *
 * Sem tenant e sem banco: a página não lê banco nem sessão (sqlite-safe).
 */
function privAppTexto(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Site/PrivacidadeApp.tsx'));
}

it('UC-PRVAPP-01 — abre sem login, na URL estável', function () {
    // Requisição Inertia (como a navegação do app faz): devolve a página em JSON sem montar o
    // layout Blade, cujo view composer lê a tabela `system` — inexistente na lane sqlite.
    // A versão vem do PRÓPRIO middleware: sem o build no CI ela cai no parent::version(), e
    // um valor suposto aqui dá 409 (versão divergente) — foi o que aconteceu com '1'.
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->assertGuest();
    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])->get('/privacidade');

    $r->assertOk();
    $this->assertSame('Site/PrivacidadeApp', $r->json('component'));
});

it('UC-PRVAPP-02 — cobre os dados do ERP e aponta para a política do ponto', function () {
    $texto = privAppTexto();
    $this->assertStringContainsString('Dados de clientes e fornecedores da empresa', $texto);
    $this->assertStringContainsString('href="/privacidade/ponto"', $texto);
});

it('UC-PRVAPP-03 — diz que marcação de ponto não pode ser apagada', function () {
    $texto = privAppTexto();
    $this->assertStringContainsString('não podem ser apagadas nem', $texto);
    $this->assertDoesNotMatchRegularExpression('/apagamos (as )?(suas )?marca/iu', $texto);
});

it('UC-PRVAPP-04 — declara que não usa localização em segundo plano', function () {
    $this->assertStringContainsString('não usa a localização em segundo plano', privAppTexto());
});

it('UC-PRVAPP-05 — declara os registros de falhas, sem dado pessoal, guardados 90 dias', function () {
    $texto = privAppTexto();
    $this->assertStringContainsString('Registros de falhas do app', $texto);
    $this->assertStringContainsString('não leva seu nome, e-mail, senha nem o conteúdo', $texto);
    $this->assertStringContainsString('servidor próprio do oimpresso', $texto);
    $this->assertStringContainsString('apagados depois de 90 dias', $texto);
});
