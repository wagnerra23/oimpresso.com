<?php

declare(strict_types=1);

namespace Modules\Cms\Tests\Feature;

use Illuminate\Support\Facades\Notification;
use Modules\Cms\Entities\CmsPage;
use Modules\Cms\Entities\CmsPageMeta;
use Modules\Cms\Entities\CmsSiteDetail;
use Modules\Cms\Notifications\NewLeadGeneratedNotification;
use Tests\TestCase;

/**
 * Teste-âncora do PAINEL do CMS (as suítes Wave2* cobrem só o site público).
 *
 * Defende os UC de `resources/js/Pages/Cms/Content/Index.casos.md`:
 *   UC-CMS-01 índice em Inertia · 02 403 · 03 401 · 04 título obrigatório ·
 *   05 meta_description derivada · 06 sanitização · 07 rename → 404 no antigo ·
 *   08 blocos só na home · 09 página de sistema não exclui · 10 exclusão limpa ·
 *   14 modo demo · 15 lead notificado · 16 honeypot · 18 cms:health.
 *
 * Convenção do repo: rodar no CT100 —
 *   docker exec oimpresso-staging php artisan test --filter=CmsPainelAdminTest
 *
 * NOTA [CC→CL]: escrito a partir do espelho local de Modules/Cms, sem execução.
 * Os helpers de sessão/permissão (`actingAsSuperadmin`, `actingAsUsuarioComum`)
 * devem apontar pro helper que o repo já usa nas suítes de painel — troque a
 * implementação abaixo se existir um TestCase base com isso pronto.
 */
class CmsPainelAdminTest extends TestCase
{
    private function actingAsSuperadmin(): self
    {
        // TODO [CL]: reusar o helper canônico do repo (SetSessionData + is_admin).
        $this->withSession(['user' => ['id' => 1, 'business_id' => 1], 'is_admin' => true]);

        return $this;
    }

    private function actingAsUsuarioComum(): self
    {
        $this->withSession(['user' => ['id' => 2, 'business_id' => 1], 'is_admin' => false]);

        return $this;
    }

    private function criaPagina(array $attrs = []): CmsPage
    {
        return CmsPage::create(array_merge([
            'type' => 'page',
            'title' => 'Pagina de teste cms',
            'content' => '<p>Conteúdo de teste</p>',
            'is_enabled' => 1,
        ], $attrs));
    }

    /** UC-CMS-01 */
    public function test_index_renderiza_inertia_cms_content_index(): void
    {
        $p1 = $this->criaPagina(['title' => 'Alfa cms', 'priority' => 2]);
        $p2 = $this->criaPagina(['title' => 'Beta cms', 'priority' => 1]);

        try {
            $response = $this->actingAsSuperadmin()
                ->get('/cms/cms-page?type=page', ['X-Inertia' => 'true']);

            $response->assertStatus(200);
            $payload = $response->json();

            $this->assertSame('Cms/Content/Index', $payload['component'] ?? null);
            $this->assertArrayHasKey('pages', $payload['props']);

            $titulos = array_column($payload['props']['pages'], 'title');
            $this->assertLessThan(
                array_search('Alfa cms', $titulos, true),
                array_search('Beta cms', $titulos, true),
                'priority asc: Beta (1) tem de vir antes de Alfa (2)'
            );
        } finally {
            $p1->delete();
            $p2->delete();
        }
    }

    /** UC-CMS-02 */
    public function test_usuario_sem_superadmin_recebe_403(): void
    {
        $response = $this->actingAsUsuarioComum()->get('/cms/cms-page?type=page');

        $response->assertStatus(403);
        $response->assertDontSee('cms_pages');
    }

    /** UC-CMS-03 */
    public function test_visitante_sem_sessao_nao_acessa_painel(): void
    {
        $response = $this->get('/cms/cms-page?type=page');

        $this->assertContains($response->getStatusCode(), [401, 302]);
    }

    /** UC-CMS-04 */
    public function test_store_sem_titulo_falha_validacao(): void
    {
        $antes = CmsPage::count();

        $response = $this->actingAsSuperadmin()->post('/cms/cms-page', [
            'type' => 'page',
            'content' => '<p>sem titulo</p>',
        ]);

        $response->assertSessionHasErrors('title');
        $this->assertSame($antes, CmsPage::count(), 'nada pode ser gravado sem título');
    }

    /** UC-CMS-05 */
    public function test_meta_description_vazia_recebe_160_chars_do_conteudo(): void
    {
        $texto = str_repeat('Cálculo automático por metro quadrado. ', 12);

        $this->actingAsSuperadmin()->post('/cms/cms-page', [
            'type' => 'page',
            'title' => 'Meta derivada cms',
            'content' => '<p>'.$texto.'</p>',
            'meta_description' => '',
            'is_enabled' => 1,
        ]);

        $page = CmsPage::where('title', 'Meta derivada cms')->first();

        try {
            $this->assertNotNull($page);
            $meta = (string) $page->getAttribute('meta_description');
            $this->assertNotSame('', $meta, 'meta_description vazia tem de ser derivada do conteúdo (R7)');
            $this->assertLessThanOrEqual(160, mb_strlen($meta));
            $this->assertStringNotContainsString('<', $meta, 'a descrição é texto puro, sem tags');
        } finally {
            $page?->delete();
        }
    }

    /** UC-CMS-06 */
    public function test_conteudo_publico_nao_carrega_script_nem_evento(): void
    {
        $page = $this->criaPagina([
            'title' => 'Sanitiza cms',
            'content' => '<p onclick="alert(1)">oi</p><script>alert(2)</script><iframe src="x"></iframe>',
        ]);

        try {
            $response = $this->get('/c/page/sanitiza-cms', ['X-Inertia' => 'true']);
            $response->assertStatus(200);

            $html = (string) ($response->json('props.page.content') ?? '');
            $this->assertStringNotContainsString('<script', $html);
            $this->assertStringNotContainsString('onclick', $html);
            $this->assertStringNotContainsString('<iframe', $html);
        } finally {
            $page->delete();
        }
    }

    /** UC-CMS-07 */
    public function test_renomear_pagina_muda_endereco_e_antigo_da_404(): void
    {
        $page = $this->criaPagina(['title' => 'Sobre nos cms']);

        try {
            $this->get('/c/page/sobre-nos-cms')->assertStatus(200);

            $this->actingAsSuperadmin()->put('/cms/cms-page/'.$page->id, [
                'type' => 'page',
                'title' => 'Sobre a empresa cms',
                'content' => '<p>Conteúdo de teste</p>',
                'is_enabled' => 1,
            ]);

            $this->get('/c/page/sobre-a-empresa-cms')->assertStatus(200);
            $this->get('/c/page/sobre-nos-cms')->assertStatus(404);
        } finally {
            $page->refresh()->delete();
        }
    }

    /** UC-CMS-08 */
    public function test_edit_home_expoe_page_meta_feature_e_industry(): void
    {
        $home = $this->criaPagina(['title' => 'Home de teste cms', 'layout' => 'home']);
        $livre = $this->criaPagina(['title' => 'Livre de teste cms']);
        CmsPageMeta::create([
            'cms_page_id' => $home->id,
            'meta_key' => 'feature',
            'meta_value' => json_encode(['title' => 'Recursos', 'content' => [['icon' => 'cloud', 'title' => 'Acesse de onde estiver', 'description' => 'x']]]),
        ]);

        try {
            $comHome = $this->actingAsSuperadmin()
                ->get('/cms/cms-page/'.$home->id.'/edit?type=page', ['X-Inertia' => 'true']);
            $comHome->assertStatus(200);
            $this->assertArrayHasKey('feature', $comHome->json('props.page_meta') ?? []);

            $semHome = $this->actingAsSuperadmin()
                ->get('/cms/cms-page/'.$livre->id.'/edit?type=page', ['X-Inertia' => 'true']);
            $semHome->assertStatus(200);
            $this->assertEmpty($semHome->json('props.page_meta') ?? [], 'página livre não tem blocos (R4)');
        } finally {
            CmsPageMeta::where('cms_page_id', $home->id)->delete();
            $home->delete();
            $livre->delete();
        }
    }

    /** UC-CMS-09 — hoje o Blade só esconde o botão; a rota tem de recusar. */
    public function test_destroy_recusa_pagina_de_sistema(): void
    {
        $page = $this->criaPagina(['title' => 'Contato de sistema cms', 'layout' => 'contact']);

        try {
            $this->actingAsSuperadmin()
                ->deleteJson('/cms/cms-page/'.$page->id.'?type=page')
                ->assertJson(['success' => false]);

            $this->assertNotNull(CmsPage::find($page->id), 'página de sistema não pode ser excluída (R3)');
        } finally {
            CmsPage::where('id', $page->id)->delete();
        }
    }

    /** UC-CMS-10 */
    public function test_destroy_pagina_livre_remove_registro(): void
    {
        $page = $this->criaPagina(['title' => 'Livre excluir cms']);

        $this->actingAsSuperadmin()
            ->deleteJson('/cms/cms-page/'.$page->id.'?type=page')
            ->assertJson(['success' => true]);

        $this->assertNull(CmsPage::find($page->id));
    }

    /**
     * UC-CMS-19 — 🔴 reprova no main de hoje: Store/UpdateCmsPageRequest validam
     * 'in:page,post,banner' enquanto o domínio do módulo é page|blog|testimonial
     * (nav.blade, CmsController, CmsPageRepository, StoreBlogPostRequest,
     * CmsServiceProvider::getPagesCount('blog'), migração de dados padrão).
     */
    public function test_store_aceita_os_tres_tipos_do_dominio(): void
    {
        foreach (['page', 'blog', 'testimonial'] as $tipo) {
            $titulo = 'Tipo '.$tipo.' cms';

            $this->actingAsSuperadmin()->post('/cms/cms-page', [
                'type' => $tipo,
                'title' => $titulo,
                'content' => '<p>x</p>',
                'is_enabled' => 1,
            ])->assertSessionDoesntHaveErrors('type');

            $page = CmsPage::where('title', $titulo)->first();
            $this->assertNotNull($page, "type={$tipo} tem de ser aceito (domínio real do módulo)");
            $this->assertSame($tipo, $page->getAttribute('type'));
            $page->delete();
        }
    }

    /** UC-CMS-14 */
    public function test_modo_demo_bloqueia_escrita(): void
    {
        config(['app.env' => 'demo', 'constants.is_demo' => true]);
        $antes = CmsPage::count();

        $this->actingAsSuperadmin()->post('/cms/cms-page', [
            'type' => 'page',
            'title' => 'Demo nao grava cms',
            'content' => '<p>x</p>',
        ]);

        $this->assertSame($antes, CmsPage::count(), 'modo demo não grava (R12)');
    }

    /** UC-CMS-15 */
    public function test_contato_publico_notifica_quando_ha_email_configurado(): void
    {
        Notification::fake();
        CmsSiteDetail::createOrUpdateSiteDetails(['notifiable_email' => 'avisos@exemplo.com']);

        $this->post('/c/submit-contact-form', [
            'name' => 'Marcos Prado',
            'email' => 'marcos@exemplo.com',
            'mobile' => '11988124409',
            'message' => 'Quero entender o cálculo por metro quadrado.',
        ])->assertStatus(200);

        Notification::assertSentOnDemand(NewLeadGeneratedNotification::class);
    }

    /** UC-CMS-16 */
    public function test_honeypot_preenchido_rejeita_submissao(): void
    {
        Notification::fake();

        $response = $this->post('/c/submit-contact-form', [
            'name' => 'Bot',
            'email' => 'bot@exemplo.com',
            'message' => 'spam',
            '_gotcha' => 'preenchido por bot',
        ]);

        $response->assertSessionHasErrors('_gotcha');
        Notification::assertNothingSent();
    }

    /** UC-CMS-18 */
    public function test_cms_health_falha_sem_pagina_publicada(): void
    {
        CmsPage::where('is_enabled', 1)->update(['is_enabled' => 0]);

        $this->artisan('cms:health', ['--detail' => true])
            ->assertExitCode(1);
    }
}
