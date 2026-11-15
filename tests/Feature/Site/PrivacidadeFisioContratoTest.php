<?php

declare(strict_types=1);

/**
 * Contrato da política de privacidade do Portal do Paciente do Fisio e do app Uniafibro
 * (`/fisio/privacidade`).
 *
 * UCs de `resources/js/Pages/Site/PrivacidadeFisio.casos.md`. O texto da página É o contrato com o
 * Google Play, por isso UC-PRVFIS-02..05 leem o texto publicado.
 *
 * Sem tenant e sem banco: a página não lê banco nem sessão (sqlite-safe).
 */
function privFisioTexto(): string
{
    return (string) file_get_contents(base_path('resources/js/Pages/Site/PrivacidadeFisio.tsx'));
}

it('UC-PRVFIS-01 — abre sem login, na URL estável', function () {
    // Mesmo desenho do UC-PRVAPP-01: requisição Inertia, versão vinda do próprio middleware.
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->assertGuest();
    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])->get('/fisio/privacidade');

    $r->assertOk();
    $this->assertSame('Site/PrivacidadeFisio', $r->json('component'));
});

it('UC-PRVFIS-02 — clínica controladora, oimpresso operador', function () {
    $texto = privFisioTexto();
    $this->assertStringContainsString('Controladora: a clínica ou o profissional que atende você.', $texto);
    $this->assertStringContainsString('Operador: o oimpresso (WR2 Sistemas).', $texto);
});

it('UC-PRVFIS-03 — guarda de 20 anos, sem prometer apagar dado clínico', function () {
    $texto = privFisioTexto();
    $this->assertStringContainsString('no mínimo 20 anos', $texto);
    $this->assertStringContainsString('dados clínicos não podem ser apagados antes desse prazo', $texto);
    $this->assertDoesNotMatchRegularExpression('/apagamos (o )?(seu )?prontu/iu', $texto);
});

it('UC-PRVFIS-04 — IA de transcrição opcional, conferida pelo profissional', function () {
    $texto = privFisioTexto();
    $this->assertStringContainsString('Ele vem desligado', $texto);
    $this->assertStringContainsString('OpenAI ou Anthropic', $texto);
    $this->assertStringContainsString('depois que o profissional confere', $texto);
});

it('UC-PRVFIS-05 — declara que o app não faz teleconsulta por vídeo', function () {
    $this->assertStringContainsString('não faz teleconsulta por vídeo', privFisioTexto());
});
