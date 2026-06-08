<?php

namespace Modules\Cms\Tests\Feature;

use Tests\TestCase;

class SitePricingDinamicoTest extends TestCase
{
    public function test_pricing_passa_packages_pra_inertia(): void
    {
        $response = $this->get('/pricing', ['X-Inertia' => 'true']);

        $response->assertStatus(200);
        $payload = $response->json();
        $this->assertSame('Site/Pricing', $payload['component'] ?? null);

        $props = $payload['props'] ?? [];
        $this->assertArrayHasKey('packages', $props);
        $this->assertArrayHasKey('permissions', $props);
    }
}
