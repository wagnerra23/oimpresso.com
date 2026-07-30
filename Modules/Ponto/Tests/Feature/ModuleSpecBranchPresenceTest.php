<?php

namespace Modules\Ponto\Tests\Feature;

use App\Services\ModuleSpecGenerator;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Presença em branches não pode AFIRMAR ausência quando não deu pra medir.
 *
 * `checkBranchPresence` lia a saída vazia de `git ls-tree <branch>` como "o módulo não
 * estava nessa branch". Como `runGit` manda o stderr pro /dev/null, branch inexistente e
 * módulo ausente davam o mesmo vazio, e o render gravava ❌ nos dois — afirmação tirada de
 * um comando que nem rodou.
 *
 * Virou risco quando `main-wip-2026-04-22` sumiu do repo e do remoto: regravar as specs
 * trocaria ✅ por ❌ em 6 módulos cujo único registro sobrevivente é esse (Accounting,
 * AiAssistance, Grow, IProduction, Officeimpresso1, Writebot — todos ausentes também de
 * `origin/3.7-com-nfe`).
 *
 * Nota de ambiente: o CI faz checkout raso (`actions/checkout` sem `fetch-depth`), então
 * lá NENHUMA branch histórica existe e tudo sai indeterminado. Por isso os testes afirmam
 * a INVARIANTE (`null` ⟺ branch ausente), que vale nos dois ambientes, em vez de fixar um
 * resultado que só valeria num deles — e sem ramo de skip, pra nunca passar por não ter
 * rodado.
 */
class ModuleSpecBranchPresenceTest extends TestCase
{
    /** Módulo que existe de fato na árvore — o inspect precisa de um alvo real. */
    private const MODULO_VIVO = 'Ponto';

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

    #[\PHPUnit\Framework\Attributes\Test]
    public function presenca_e_indeterminada_se_e_somente_se_a_branch_sumiu(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $spec = $gen->inspect(self::MODULO_VIVO);

        $this->assertArrayNotHasKey('error', $spec, 'O módulo alvo tem de existir na árvore');
        $presenca = $spec['branch_presence'];
        $ausentes = $gen->branchesAusentes();

        foreach (ModuleSpecGenerator::BRANCHES_DE_INTERESSE as $branch) {
            $this->assertArrayHasKey($branch, $presenca);

            if (in_array($branch, $ausentes, true)) {
                $this->assertNull($presenca[$branch], "`{$branch}` sumiu: é indeterminado, não ❌");
                continue;
            }

            $this->assertIsBool($presenca[$branch], "`{$branch}` existe: a presença tem de ser medida");
        }

        $this->assertSame(
            $ausentes,
            array_values(array_filter(
                ModuleSpecGenerator::BRANCHES_DE_INTERESSE,
                fn (string $b): bool => $presenca[$b] === null
            )),
            'Branches ausentes têm de bater exatamente com as presenças indeterminadas'
        );
    }

    /**
     * O bug vivia aqui: `$pres[...] ?? false ? '✅' : '❌'` colapsava `null` em `❌`,
     * virando "não deu pra medir" em "medi, não estava lá".
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

    #[\PHPUnit\Framework\Attributes\Test]
    public function markdown_explica_o_n_d_quando_e_so_quando_ele_aparece(): void
    {
        $gen = app(ModuleSpecGenerator::class);
        $md = $gen->renderMarkdown($gen->inspect(self::MODULO_VIVO));

        $temIndeterminado = $gen->branchesAusentes() !== [];

        $this->assertSame($temIndeterminado, str_contains($md, '| n/d |'));
        $this->assertSame($temIndeterminado, str_contains($md, 'não é verificável'));
    }

    /**
     * Registro provado pelo REGISTRY, não por `app(Classe::class)` — o container resolve
     * qualquer classe concreta do disco, com ou sem o comando registrado.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function o_comando_module_specs_esta_registrado(): void
    {
        $this->assertContains('module:specs', array_keys(Artisan::all()));
    }

    /**
     * Bite-test do guard: com branch histórica sumida E arquivo que a registra, regravar
     * apagaria o único registro — então o comando aborta em vez de sobrescrever calado.
     */
    #[\PHPUnit\Framework\Attributes\Test]
    public function module_specs_aborta_quando_regravar_apagaria_registro_insubstituivel(): void
    {
        $gen = app(ModuleSpecGenerator::class);

        $this->assertNotEmpty(
            $gen->branchesAusentes(),
            'Pré-condição: alguma branch histórica precisa estar ausente pra exercer o guard'
        );

        $saida = Artisan::call('module:specs');

        $this->assertSame(1, $saida, 'Deve abortar com código de falha, não seguir e regravar');
        $this->assertStringContainsString('ABORTADO', Artisan::output());
    }
}
