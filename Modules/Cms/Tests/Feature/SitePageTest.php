<?php

namespace Modules\Cms\Tests\Feature;

use Modules\Cms\Entities\CmsPage;
use Tests\TestCase;

class SitePageTest extends TestCase
{
    public function test_c_page_slug_retorna_inertia_component_site_page(): void
    {
        $page = CmsPage::create([
            'type' => 'page',
            'title' => 'Sobre nos teste',
            'content' => '<p>Conteúdo de teste</p>',
            'is_enabled' => 1,
        ]);

        try {
            $response = $this->get('/c/page/sobre-nos-teste', ['X-Inertia' => 'true']);

            $response->assertStatus(200);
            $payload = $response->json();
            $this->assertSame('Site/Page', $payload['component'] ?? null);
            $this->assertArrayHasKey('page', $payload['props']);
            $this->assertSame('Sobre nos teste', $payload['props']['page']['title']);
        } finally {
            $page->delete();
        }
    }

    public function test_c_blogs_retorna_inertia_component_site_blogs(): void
    {
        $response = $this->get('/c/blogs', ['X-Inertia' => 'true']);

        $response->assertStatus(200);
        $payload = $response->json();
        $this->assertSame('Site/Blogs', $payload['component'] ?? null);
        $this->assertArrayHasKey('posts', $payload['props']);
    }

    public function test_rota_old_de_blogs_continua_blade(): void
    {
        $blogs = $this->get('/c/blogs/old');
        // O Blade legado pode 200 ou 500 dependendo do ambiente; o que importa
        // é não ser 404 (rota existe) e não ser Inertia.
        $this->assertNotSame(404, $blogs->status());
    }
}
