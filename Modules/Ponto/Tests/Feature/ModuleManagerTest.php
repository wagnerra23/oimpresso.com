<?php

namespace Modules\Ponto\Tests\Feature;

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
    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function list_inclui_ponto_wr2_e_marca_como_ativo(): void
    {
        $manager = app(ModuleManagerService::class);
        $list = $manager->list();

        $ponto = collect($list)->firstWhere('name', 'Ponto');
        $this->assertNotNull($ponto, 'Ponto deve estar na lista');
        $this->assertTrue($ponto['active'], 'Ponto deve estar ativo');
        $this->assertTrue($ponto['has_migrations']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function module_exists_funciona(): void
    {
        $manager = app(ModuleManagerService::class);
        $this->assertTrue($manager->moduleExists('Ponto'));
        $this->assertFalse($manager->moduleExists('ModuloInexistente123'));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function set_active_lanca_excecao_para_modulo_inexistente(): void
    {
        $manager = app(ModuleManagerService::class);
        $this->expectException(\InvalidArgumentException::class);
        $manager->setActive('ModuloInexistente123', true);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spec_generator_inspeciona_ponto_wr2(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $spec = $gen->inspect('Ponto');

        $this->assertTrue($spec['exists_in_current']);
        $this->assertArrayHasKey('module_json', $spec);
        $this->assertArrayHasKey('routes', $spec);
        $this->assertArrayHasKey('controllers', $spec);
        $this->assertArrayHasKey('entities', $spec);
        $this->assertArrayHasKey('migrations', $spec);
        $this->assertArrayHasKey('permissions', $spec);
        $this->assertArrayHasKey('upos_hooks', $spec);

        // Ponto tem pelo menos 10 controllers
        $this->assertGreaterThanOrEqual(10, count($spec['controllers']));
        // 8 migrations
        $this->assertGreaterThanOrEqual(8, count($spec['migrations']));
        // Permissões registradas
        $this->assertGreaterThanOrEqual(3, count($spec['permissions']['registered']));
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spec_markdown_render_tem_secoes_importantes(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $spec = $gen->inspect('Ponto');
        $md = $gen->renderMarkdown($spec);

        // Modulo renomeado PontoWr2 -> Ponto (module.json `"name": "Ponto"`). O teste
        // seguia cobrando o nome antigo e reprovava em 4 casos; ficou parado porque o
        // arquivo esta fora de qualquer lane. Medido no CT100 em 2026-08-23.
        $this->assertStringContainsString('# Módulo: Ponto', $md);
        $this->assertStringContainsString('## Rotas', $md);
        $this->assertStringContainsString('## Controllers', $md);
        $this->assertStringContainsString('## Migrations', $md);
        $this->assertStringContainsString('## Permissões', $md);
        $this->assertStringContainsString('## Integridade do banco', $md);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function spec_detecta_modulo_perdido_em_branch_antiga(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        // Boleto foi perdido na migração 3.7 → 6.7
        $spec = $gen->inspect('Boleto');

        $this->assertFalse($spec['exists_in_current'], 'Boleto não existe em 6.7-react');

        // A guarda `!empty(...)` NAO bastava (corrigido 2026-08-24). O CI faz checkout RASO:
        // as branches historicas nao existem, entao `branch_presence` vem preenchido com
        // `false`/`null` pra todas — nao-vazio, mas sem informacao. O assert entao exigia
        // presenca numa branch que o ambiente nao tem, e reprovava por AUSENCIA DE MEDICAO,
        // nao por ausencia do modulo. Medido no run 32719806595.
        //
        // O irmao `ModuleSpecBranchPresenceTest` ja resolveu isso do jeito certo: afirma a
        // INVARIANTE (`null` ⟺ branch ausente) via `branchesAusentes()`, que vale nos dois
        // ambientes. Mesmo idioma aqui — se nenhuma das duas branches esta acessivel, nao ha
        // o que afirmar, e dizer isso e mais honesto que inventar um veredito.
        $ausentes = $gen->branchesAusentes();
        $candidatas = ['main-wip-2026-04-22', 'origin/3.7-com-nfe'];
        $medidas = array_values(array_diff($candidatas, $ausentes));

        if ($medidas === []) {
            $this->markTestSkipped(
                'Nenhuma das branches historicas esta acessivel (checkout raso) — '
                .'sem medicao nao ha veredito sobre onde o Boleto ficou.'
            );
        }

        $achouEmAlguma = false;
        foreach ($medidas as $branch) {
            $achouEmAlguma = $achouEmAlguma || (bool) ($spec['branch_presence'][$branch] ?? false);
        }

        $this->assertTrue(
            $achouEmAlguma,
            'Boleto deveria estar em ao menos uma das branches MEDIDAS: '.implode(', ', $medidas)
        );
    }
}
