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

        $ponto = collect($list)->firstWhere('name', 'PontoWr2');
        $this->assertNotNull($ponto, 'PontoWr2 deve estar na lista');
        $this->assertTrue($ponto['active'], 'PontoWr2 deve estar ativo');
        $this->assertTrue($ponto['has_migrations']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function module_exists_funciona(): void
    {
        $manager = app(ModuleManagerService::class);
        $this->assertTrue($manager->moduleExists('PontoWr2'));
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    #[\PHPUnit\Framework\Attributes\Test]
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

    /**
     * Controle do discriminador: uma ref que não existe tem de ser reconhecida como
     * ausente, e uma que existe como presente. É o que separa "a branch sumiu" de
     * "o módulo não estava nela" — antes os dois casos davam saída vazia no ls-tree.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function branch_existe_distingue_ref_viva_de_ref_inexistente(): void
    {
        $gen = app(ModuleSpecGenerator::class);

        $this->assertTrue($gen->branchExiste('HEAD'), 'HEAD sempre existe num repo git');
        $this->assertFalse(
            $gen->branchExiste('branch-que-nunca-existiu-oimpresso-teste'),
            'Ref inventada não pode ser reportada como existente'
        );
    }

    /**
     * A invariante que impede a afirmação falsa: presença é `null` (indeterminado)
     * EXATAMENTE quando a branch sumiu — nunca `false`, que significaria "medi e não
     * estava lá". Sem ramo de skip: os dois lados asseguram, então o teste nunca
     * passa por não ter rodado.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function presenca_e_indeterminada_se_e_somente_se_a_branch_sumiu(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $presenca = $gen->inspect('PontoWr2')['branch_presence'];
        $ausentes = $gen->branchesAusentes();

        foreach (ModuleSpecGenerator::BRANCHES_DE_INTERESSE as $branch) {
            $this->assertArrayHasKey($branch, $presenca);

            if (in_array($branch, $ausentes, true)) {
                $this->assertNull($presenca[$branch], "`{$branch}` sumiu: presença é indeterminada, não ❌");
                continue;
            }

            $this->assertIsBool($presenca[$branch], "`{$branch}` existe: presença tem de ser medida");
        }

        $this->assertSame(
            $ausentes,
            array_values(array_filter(
                ModuleSpecGenerator::BRANCHES_DE_INTERESSE,
                fn (string $b): bool => $presenca[$b] === null
            )),
            'O conjunto de branches ausentes tem de bater com o de presenças indeterminadas'
        );
    }

    /**
     * O rótulo tem três estados. O bug vivia aqui: `$pres[...] ?? false ? '✅' : '❌'`
     * colapsava `null` em `❌`, transformando "não deu pra medir" em "medi, não estava lá".
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function rotulo_de_presenca_nunca_colapsa_indeterminado_em_ausente(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $rotulo = new \ReflectionMethod($gen, 'rotuloPresenca');
        $rotulo->setAccessible(true);

        $this->assertSame('✅', $rotulo->invoke($gen, true));
        $this->assertSame('❌', $rotulo->invoke($gen, false));
        $this->assertSame('n/d', $rotulo->invoke($gen, null));
        $this->assertNotSame($rotulo->invoke($gen, null), $rotulo->invoke($gen, false));
    }

    /**
     * Só há legenda de `n/d` quando existe `n/d` na tabela — nem sobra ruído, nem
     * aparece símbolo sem explicação.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function markdown_explica_o_n_d_quando_e_so_quando_ele_aparece(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $md = $gen->renderMarkdown($gen->inspect('PontoWr2'));

        $temIndeterminado = $gen->branchesAusentes() !== [];
        $temLegenda = str_contains($md, 'não é verificável');

        $this->assertSame($temIndeterminado, $temLegenda);
        $this->assertSame($temIndeterminado, str_contains($md, '| n/d |'));
    }
}
