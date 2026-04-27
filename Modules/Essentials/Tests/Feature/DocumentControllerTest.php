<?php

namespace Modules\Essentials\Tests\Feature;

/**
 * Feature tests para Modules/Essentials/Http/Controllers/DocumentController.
 *
 * Pages Inertia: Essentials/Documents/Index.
 *
 * Contratos cobertos:
 *   - GET /essentials/document exige autenticação
 *   - Index Inertia retorna props `documents`, `memos`, `initialTab`, `me`
 *   - Query param `?type=memos` muda `initialTab` para 'memos'
 *   - Default `initialTab=documents` quando ausente
 *
 * NÃO testamos store (upload de arquivo real exige fixture binária + Storage
 * fake) nem destroy (estado mutável). Reservar pra integração quando o pipeline
 * tiver factory de Document.
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class DocumentControllerTest extends EssentialsTestCase
{
    public function test_index_exige_autenticacao(): void
    {
        $this->get('/essentials/document')->assertRedirect('/login');
    }

    public function test_index_retorna_inertia_com_props_esperadas(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/document');

        $this->assertInertiaComponent($response, 'Essentials/Documents/Index');

        $props = $response->json('props');
        $this->assertArrayHasKey('documents', $props);
        $this->assertArrayHasKey('memos', $props);
        $this->assertArrayHasKey('initialTab', $props);
        $this->assertArrayHasKey('me', $props);

        $this->assertIsArray($props['documents']);
        $this->assertIsArray($props['memos']);
        $this->assertSame('documents', $props['initialTab']);
        $this->assertIsInt($props['me']);
    }

    public function test_tipo_memos_via_query_string_define_initial_tab(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/document', ['type' => 'memos']);

        $this->assertInertiaComponent($response, 'Essentials/Documents/Index');
        $this->assertSame('memos', $response->json('props.initialTab'));
    }

    public function test_tipo_invalido_volta_para_documents_default(): void
    {
        $this->actAsAdmin();
        $response = $this->inertiaGet('/essentials/document', ['type' => 'desconhecido']);

        $this->assertSame('documents', $response->json('props.initialTab'));
    }

    public function test_destroy_sem_login_redireciona(): void
    {
        $response = $this->delete('/essentials/document/9999999');
        $this->assertContains($response->status(), [302, 401, 419]);
    }

    public function test_download_exige_autenticacao(): void
    {
        $this->get('/essentials/document/download/9999999')->assertRedirect('/login');
    }
}
