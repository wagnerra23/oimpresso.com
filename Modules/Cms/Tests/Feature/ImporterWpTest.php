<?php

namespace Modules\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Cms\Entities\CmsPage;
use Tests\TestCase;

class ImporterWpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Conexão SQLite in-memory simulando o WP officeimpresso.
        config()->set('database.connections.wp_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        DB::connection('wp_test')->statement('
            CREATE TABLE posts (
                ID INTEGER PRIMARY KEY,
                post_title TEXT,
                post_content TEXT,
                post_excerpt TEXT,
                post_type TEXT,
                post_status TEXT,
                post_date TEXT,
                post_name TEXT
            )
        ');

        DB::connection('wp_test')->table('posts')->insert([
            [
                'ID' => 1,
                'post_title' => 'O que e ERP fixture',
                'post_content' => str_repeat('Lorem ipsum conteudo grande. ', 20).'[muffin row]inner[/muffin]',
                'post_excerpt' => 'Resumo do post',
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_date' => '2024-01-01 10:00:00',
                'post_name' => 'o-que-e-erp-fixture',
            ],
            [
                'ID' => 2,
                'post_title' => 'Pagina teste fixture',
                'post_content' => str_repeat('Conteudo institucional. ', 20),
                'post_excerpt' => '',
                'post_type' => 'page',
                'post_status' => 'publish',
                'post_date' => '2024-01-02 10:00:00',
                'post_name' => 'pagina-teste-fixture',
            ],
            [
                'ID' => 3,
                'post_title' => 'Pequeno demais',
                'post_content' => 'curto',
                'post_excerpt' => '',
                'post_type' => 'post',
                'post_status' => 'publish',
                'post_date' => '2024-01-03 10:00:00',
                'post_name' => 'pequeno',
            ],
            [
                'ID' => 4,
                'post_title' => 'Rascunho ignorado',
                'post_content' => str_repeat('rascunho ', 30),
                'post_excerpt' => '',
                'post_type' => 'post',
                'post_status' => 'draft',
                'post_date' => '2024-01-04 10:00:00',
                'post_name' => 'rascunho',
            ],
        ]);

        // Limpa qualquer fixture remanescente do teste anterior.
        CmsPage::whereIn('title', ['O que e ERP fixture', 'Pagina teste fixture'])->delete();
    }

    protected function tearDown(): void
    {
        CmsPage::whereIn('title', ['O que e ERP fixture', 'Pagina teste fixture'])->delete();
        parent::tearDown();
    }

    public function test_comando_importa_posts_e_paginas_publicados_e_ignora_curtos(): void
    {
        $this->artisan('cms:import-wp-officeimpresso', ['--connection' => 'wp_test'])
            ->expectsOutputToContain('Imported 1 posts, 1 pages')
            ->assertSuccessful();

        $this->assertDatabaseHas('cms_pages', [
            'title' => 'O que e ERP fixture',
            'type' => 'blog',
        ]);
        $this->assertDatabaseHas('cms_pages', [
            'title' => 'Pagina teste fixture',
            'type' => 'page',
        ]);

        $imported = CmsPage::where('title', 'O que e ERP fixture')->first();
        $this->assertNotNull($imported);
        $this->assertStringNotContainsString('[muffin', $imported->content);
        $this->assertStringNotContainsString('[/muffin]', $imported->content);
    }

    public function test_comando_e_idempotente(): void
    {
        $this->artisan('cms:import-wp-officeimpresso', ['--connection' => 'wp_test'])->assertSuccessful();
        $count1 = CmsPage::whereIn('title', ['O que e ERP fixture', 'Pagina teste fixture'])->count();

        $this->artisan('cms:import-wp-officeimpresso', ['--connection' => 'wp_test'])->assertSuccessful();
        $count2 = CmsPage::whereIn('title', ['O que e ERP fixture', 'Pagina teste fixture'])->count();

        $this->assertSame($count1, $count2, 'Rerun não deve duplicar registros');
    }
}
