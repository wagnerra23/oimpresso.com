<?php

namespace Modules\PontoWr2\Tests\Feature;

use App\Services\ModuleManagerService;
use App\Services\ModuleSpecGenerator;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Testa o ModuleManagerService e ModuleSpecGenerator.
 * Não precisa HTTP — são unit-ish tests dos services.
 */
class ModuleManagerTest extends TestCase
{
    /** @test */
    public function list_retorna_os_modulos_do_diretorio(): void
    {
        $manager = app(ModuleManagerService::class);
        $list = $manager->list();

        $this->assertIsArray($list);
        $this->assertGreaterThan(10, count($list), 'Deve ter pelo menos 10 módulos em Modules/');

        // Shape esperado
        $sample = $list[0];
        $this->assertEqualsCanonicalizing(
            ['name', 'alias', 'version', 'description', 'area', 'active',
             'registered', 'has_migrations', 'migration_count', 'has_datacontroller', 'error'],
            array_keys($sample)
        );
    }

    /** @test */
    public function list_inclui_ponto_wr2_e_marca_como_ativo(): void
    {
        $manager = app(ModuleManagerService::class);
        $list = $manager->list();

        $ponto = collect($list)->firstWhere('name', 'PontoWr2');
        $this->assertNotNull($ponto, 'PontoWr2 deve estar na lista');
        $this->assertTrue($ponto['active'], 'PontoWr2 deve estar ativo');
        $this->assertTrue($ponto['has_migrations']);
    }

    /** @test */
    public function module_exists_funciona(): void
    {
        $manager = app(ModuleManagerService::class);
        $this->assertTrue($manager->moduleExists('PontoWr2'));
        $this->assertFalse($manager->moduleExists('ModuloInexistente123'));
    }

    /** @test */
    public function set_active_lanca_excecao_para_modulo_inexistente(): void
    {
        $manager = app(ModuleManagerService::class);
        $this->expectException(\InvalidArgumentException::class);
        $manager->setActive('ModuloInexistente123', true);
    }

    /** @test */
    public function spec_generator_inspeciona_ponto_wr2(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $spec = $gen->inspect('PontoWr2');

        $this->assertTrue($spec['exists_in_current']);
        $this->assertArrayHasKey('module_json', $spec);
        $this->assertArrayHasKey('routes', $spec);
        $this->assertArrayHasKey('controllers', $spec);
        $this->assertArrayHasKey('entities', $spec);
        $this->assertArrayHasKey('migrations', $spec);
        $this->assertArrayHasKey('permissions', $spec);
        $this->assertArrayHasKey('upos_hooks', $spec);

        // PontoWr2 tem pelo menos 10 controllers
        $this->assertGreaterThanOrEqual(10, count($spec['controllers']));
        // 8 migrations
        $this->assertGreaterThanOrEqual(8, count($spec['migrations']));
        // Permissões registradas
        $this->assertGreaterThanOrEqual(3, count($spec['permissions']['registered']));
    }

    /** @test */
    public function spec_markdown_render_tem_secoes_importantes(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $spec = $gen->inspect('PontoWr2');
        $md = $gen->renderMarkdown($spec);

        $this->assertStringContainsString('# Módulo: PontoWr2', $md);
        $this->assertStringContainsString('## Rotas', $md);
        $this->assertStringContainsString('## Controllers', $md);
        $this->assertStringContainsString('## Migrations', $md);
        $this->assertStringContainsString('## Permissões', $md);
        $this->assertStringContainsString('## Integridade do banco', $md);
    }

    /** @test */
    public function spec_detecta_modulo_perdido_em_branch_antiga(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        // Boleto foi perdido na migração 3.7 → 6.7
        $spec = $gen->inspect('Boleto');

        $this->assertFalse($spec['exists_in_current'], 'Boleto não existe em 6.7-react');
        // Mas deve estar presente em alguma branch antiga (se o git estiver acessível)
        if (!empty($spec['branch_presence'])) {
            $this->assertTrue(
                $spec['branch_presence']['main-wip-2026-04-22'] ?? false ||
                $spec['branch_presence']['origin/3.7-com-nfe'] ?? false,
                'Boleto deve estar em main-wip ou 3.7-com-nfe'
            );
        }
    }
}
