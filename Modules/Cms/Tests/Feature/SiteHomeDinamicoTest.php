<?php

namespace Modules\Cms\Tests\Feature;

use Tests\TestCase;

class SiteHomeDinamicoTest extends TestCase
{
    public function test_home_passa_props_que_o_react_consome_em_pr3(): void
    {
        $response = $this->get('/', ['X-Inertia' => 'true']);

        $response->assertStatus(200);
        $payload = $response->json();
        $this->assertSame('Site/Home', $payload['component'] ?? null);

        $props = $payload['props'] ?? [];
        $this->assertArrayHasKey('page', $props);
        $this->assertArrayHasKey('testimonials', $props);
        $this->assertArrayHasKey('faqs', $props);
        $this->assertArrayHasKey('statistics', $props);
    }

    public function test_home_funciona_quando_cms_pages_estao_vazias_usando_fallback(): void
    {
        // Mesmo sem registro em cms_pages, a home deve renderizar (fallback hardcoded).
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
