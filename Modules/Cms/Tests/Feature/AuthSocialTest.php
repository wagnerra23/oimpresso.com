<?php

namespace Modules\Cms\Tests\Feature;

use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthSocialTest extends TestCase
{
    public function test_rota_de_redirect_existe_para_google(): void
    {
        // Sem GOOGLE_CLIENT_ID configurado, deve redirecionar pra /login com mensagem.
        $response = $this->get('/auth/google/redirect');

        $response->assertStatus(302);
    }

    public function test_rota_de_redirect_existe_para_microsoft(): void
    {
        $response = $this->get('/auth/microsoft/redirect');

        $response->assertStatus(302);
    }

    public function test_provider_invalido_retorna_404(): void
    {
        $response = $this->get('/auth/facebook/redirect');

        // O regex where('provider', 'google|microsoft') já barra outros providers.
        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_pagina_de_login_renderiza_inertia_site_login(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Site/Login'));
    }

    public function test_pagina_de_register_renderiza_inertia_site_register(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page->component('Site/Register'));
    }
}
