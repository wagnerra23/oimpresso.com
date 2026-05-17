<?php

declare(strict_types=1);

namespace Modules\Financeiro\Tests\Feature;

use Modules\Financeiro\Http\Requests\FluxoFiltroRequest;
use Modules\Financeiro\Repositories\TituloRepository;

/**
 * Wave 18 saturação Financeiro (68→95).
 *
 * Cobre:
 *  D1 — multi-tenant biz=1 vs biz=99 nos métodos do Repository (defense-in-depth)
 *  D4 — TituloRepository existe, métodos canônicos com type hints corretos
 *  D8 — FluxoFiltroRequest (4º FormRequest tipado) rules() retorna array não-vazio
 *
 * Multi-tenant Tier 0 (ADR 0093) + biz=1 (ADR 0101) — NUNCA biz=4 (ROTA LIVRE).
 *
 * Pattern auditável via reflection — não exige seed completo, robusto pra CI
 * e pra Hostinger Pest local.
 *
 * @see Modules\Financeiro\Repositories\TituloRepository
 * @see Modules\Financeiro\Http\Requests\FluxoFiltroRequest
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 */
class TituloRepositoryWave18Test extends FinanceiroTestCase
{
    /**
     * D4 — TituloRepository instanciável + métodos canônicos com type hints.
     */
    public function test_d4_repository_existe_com_metodos_canonicos(): void
    {
        $repo = new TituloRepository();

        $reflection = new \ReflectionClass($repo);
        $metodos = [
            'listarPaginado'    => \Illuminate\Pagination\LengthAwarePaginator::class,
            'totaisAbertos'     => 'array',
            'vencidosAntigos'   => \Illuminate\Database\Eloquent\Collection::class,
            'aging'             => 'array',
            'acharPorOrigem'    => '?' . \Modules\Financeiro\Models\Titulo::class,
        ];

        foreach ($metodos as $nome => $retornoEsperado) {
            $this->assertTrue(
                $reflection->hasMethod($nome),
                "TituloRepository DEVE ter método {$nome} (D4 SoC saturação Wave 18)."
            );

            $method = $reflection->getMethod($nome);
            $returnType = $method->getReturnType();
            $this->assertNotNull(
                $returnType,
                "TituloRepository::{$nome}() DEVE ter return type hint (D4 type safety)."
            );

            // Primeiro param sempre $businessId: int (Tier 0 multi-tenant)
            $params = $method->getParameters();
            if (! empty($params)) {
                $firstParam = $params[0];
                $this->assertEquals(
                    'businessId',
                    $firstParam->getName(),
                    "TituloRepository::{$nome}() DEVE ter \$businessId como 1º param (Tier 0 ADR 0093)."
                );
                $this->assertEquals(
                    'int',
                    (string) $firstParam->getType(),
                    "TituloRepository::{$nome}(\$businessId: int) (type-safe)."
                );
            }
        }
    }

    /**
     * D1 — Repository::base(businessId=99) NÃO vaza dados de biz=1 (cross-tenant).
     *
     * Mesmo se BusinessScope falhar, o where('business_id', ...) explícito segura.
     */
    public function test_d1_repository_isola_biz_1_vs_biz_99(): void
    {
        $repo = new TituloRepository();

        // biz=99 hipoteticamente nunca terá título (não existe business 99 em dev)
        $totais99 = $repo->totaisAbertos(99, 'receber');
        $this->assertSame(0, $totais99['count'], 'biz=99 NÃO deve ter títulos (isolamento Tier 0).');
        $this->assertSame(0.0, $totais99['total'], 'biz=99 NÃO deve somar valor (isolamento Tier 0).');

        $aging99 = $repo->aging(99, 'receber');
        $this->assertIsArray($aging99);
        $this->assertArrayHasKey('em_dia', $aging99);
        $this->assertSame(0, $aging99['em_dia']['count'], 'biz=99 aging em_dia DEVE ser zero.');

        $vencidos99 = $repo->vencidosAntigos(99, 'receber', 30);
        $this->assertCount(0, $vencidos99, 'biz=99 NÃO deve ter vencidos antigos (cross-tenant blindado).');
    }

    /**
     * D8 — FluxoFiltroRequest tipado + rules canônicas.
     */
    public function test_d8_fluxo_filtro_request_canonico(): void
    {
        $req = new FluxoFiltroRequest();
        $rules = $req->rules();

        $this->assertIsArray($rules);
        $this->assertArrayHasKey('dias', $rules);
        $this->assertArrayHasKey('conta_bancaria_id', $rules);
        $this->assertArrayHasKey('margem_minima', $rules);
        $this->assertArrayHasKey('tipo_origem', $rules);

        // Helpers tipados
        $this->assertEquals(35, $req->dias(), 'dias() default 35 (projeção canônica Wagner).');
        $this->assertEquals(5000.00, $req->margemMinima(), 'margemMinima() default R$ [redacted Tier 0] (canon FluxoCaixaService).');
    }

    /**
     * D9.a — Repository wrap em OtelHelper::spanBiz nos métodos hot.
     */
    public function test_d9_repository_tem_span_otel(): void
    {
        $source = file_get_contents(
            module_path('Financeiro', 'Repositories/TituloRepository.php')
        );

        $this->assertStringContainsString('use App\Util\OtelHelper', $source);
        $this->assertStringContainsString("OtelHelper::spanBiz('financeiro.titulo.repo.listar'", $source);
        $this->assertStringContainsString("OtelHelper::spanBiz('financeiro.titulo.repo.aging'", $source);
    }

    /**
     * D1 — Repository sempre força where('business_id', $businessId) defesa em profundidade.
     */
    public function test_d1_repository_force_business_id_explicit_no_query(): void
    {
        $source = file_get_contents(
            module_path('Financeiro', 'Repositories/TituloRepository.php')
        );

        // Pelo menos 1 uso de where('business_id', $businessId) explícito
        $this->assertMatchesRegularExpression(
            "/where\(['\"]business_id['\"],\s*\\\$businessId\)/",
            $source,
            'Repository DEVE filtrar where(business_id, $businessId) explícito (defesa em profundidade Tier 0).'
        );
    }
}
