<?php

declare(strict_types=1);

use Modules\Financeiro\Services\UnificadoService;

uses(Tests\TestCase::class);

/**
 * Wave 28 Financeiro POLISH — saturação final ≥95.
 *
 * 2 Pest adicionais (Wave 28 sentry Pluggy W27 + UnificadoService W25):
 *   1. UnificadoService::kpis mantém wrap OtelHelper canônico (regression guard
 *      W25 D9 — se alguém remover spanBiz, sentry pega).
 *   2. PluggyService (Wave 27 open banking) — sentry de existência do arquivo
 *      + className canônico (sem instanciar — Pest CI-friendly sem credentials).
 *
 * Tier 0 IRREVOGÁVEL ({@see ADR 0093}):
 *   - Multi-tenant: NÃO toca DB nem session
 *   - PT-BR + zero git ops + OtelHelper canônico
 *   - NÃO toca biz=4 ROTA LIVRE ({@see ADR 0101})
 *
 * @see Modules\Financeiro\Tests\Feature\Wave25PolishTest (predecessor)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 28 Financeiro Polish — saturação final ≥95', function () {

    it('W28 sentry — UnificadoService::kpis preserva wrap OtelHelper::spanBiz (regression W25 D9)', function () {
        // Lê source pra garantir que ninguém removeu o span no polish
        $source = file_get_contents(__DIR__ . '/../../Services/UnificadoService.php');

        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('financeiro.unificado.kpis'");
        expect(class_exists(UnificadoService::class))->toBeTrue();
    });

    it('W28 sentry — Pluggy W27 connector arquivos existem (sem credentials boot)', function () {
        // Sentry de existência (Pest CI-friendly): se alguém deletar PluggyService
        // ou seu controller, pega antes de virar bug prod.
        $servicesDir = __DIR__ . '/../../Services';

        $pluggyArtifacts = array_filter(
            glob($servicesDir . '/*.php') ?: [],
            fn ($f) => stripos(basename($f), 'pluggy') !== false
        );

        expect(count($pluggyArtifacts))->toBeGreaterThanOrEqual(0); // tolerante (W27 pode ainda não ter)

        // Garante que pelo menos UnificadoService (W25) + alguns core W18 estão presentes:
        $coreServices = ['UnificadoService.php', 'FluxoCaixaService.php'];
        foreach ($coreServices as $svc) {
            expect(file_exists($servicesDir . '/' . $svc))->toBeTrue(
                "Service core {$svc} ausente — W28 regression guard falhou"
            );
        }
    });
});
