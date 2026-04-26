<?php

namespace Modules\Cms\Tests\Feature;

use Tests\TestCase;

class SiteHomeTest extends TestCase
{
    public function test_home_publica_responde_200_sem_auth(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_home_renderiza_componente_inertia_site_home(): void
    {
        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(200);
        $response->assertHeader('X-Inertia', 'true');

        $payload = $response->json();
        $this->assertSame('Site/Home', $payload['component'] ?? null);
        $this->assertArrayHasKey('props', $payload);
    }

    public function test_home_passa_props_esperadas_para_o_react(): void
    {
        $response = $this->get('/', ['X-Inertia' => 'true']);
        $props = $response->json('props');

        // PR3 consome essas props no React; aqui só garantimos o contrato.
        $this->assertArrayHasKey('testimonials', $props);
        $this->assertArrayHasKey('page', $props);
        $this->assertArrayHasKey('faqs', $props);
        $this->assertArrayHasKey('statistics', $props);
    }

    public function test_rota_old_mantem_blade_legado_funcionando(): void
    {
        $response = $this->get('/old');

        $response->assertStatus(200);
    }

    public function test_pricing_publico_renderiza_componente_inertia_site_pricing(): void
    {
        $response = $this->get('/pricing', ['X-Inertia' => 'true']);

        $response->assertStatus(200);
        $payload = $response->json();
        $this->assertSame('Site/Pricing', $payload['component'] ?? null);
        $this->assertArrayHasKey('packages', $payload['props']);
        $this->assertArrayHasKey('permissions', $payload['props']);
    }

    public function test_pricing_old_mantem_blade_legado_funcionando(): void
    {
        $response = $this->get('/pricing/old');

        $response->assertStatus(200);
    }
}
