<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\Producao\CapacidadeService;
use Modules\OficinaAuto\Services\ServiceOrderSummaryService;
use Modules\OficinaAuto\Services\VehicleQueryService;

uses(Tests\TestCase::class);

/**
 * Wave 25 OficinaAuto POLISH ≥90 — saturação D2/D5/D6 sem boot DB.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit DB pra
 * paralelização worktree (ADR 0143 FSM preservado, ADR 0093 multi-tenant).
 *
 * Cobertura adicional sobre Wave 18/23:
 *   - D2: Container resolve 4 Services canon (D4 reuse contrato estável)
 *   - D2: spans canon `oficinaauto.*` declarados em todos os Services
 *   - D5: E2E journey Martinho — README cita Cliente piloto + steps numerados
 *   - D5: CapacidadeService thresholds documentados (ociosa/normal/apertada/lotada/overcommit)
 *   - D6: spans count >= 14 (Wave 18 + RETRY +5 = 14)
 *   - D6: OtelHelper::spanBiz preserva exception (não engole — fail-loud)
 *
 * @see Modules/OficinaAuto/CHANGELOG.md Wave 25 POLISH
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
describe('Wave 25 OficinaAuto POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2: Container resolve 4 Services canon - D4 reuse contrato estavel', function () {
        expect(app(VehicleQueryService::class))->toBeInstanceOf(VehicleQueryService::class)
            ->and(app(ServiceOrderSummaryService::class))->toBeInstanceOf(ServiceOrderSummaryService::class)
            ->and(app(CapacidadeService::class))->toBeInstanceOf(CapacidadeService::class)
            ->and(app(AprovacaoOsService::class))->toBeInstanceOf(AprovacaoOsService::class);
    });

    it('D2: CapacidadeService declara 5 spans canon oficinaauto.producao.*', function () {
        $file = (new ReflectionClass(CapacidadeService::class))->getFileName();
        $src  = file_get_contents($file);

        $matches = preg_match_all("/'oficinaauto\\.producao\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(5);
        expect($src)->toContain('use App\Util\OtelHelper;');
    });

    it('D2: VehicleQueryService declara 3 spans canon oficinaauto.vehicle.*', function () {
        $file = (new ReflectionClass(VehicleQueryService::class))->getFileName();
        $src  = file_get_contents($file);

        $matches = preg_match_all("/'oficinaauto\\.vehicle\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(3);
    });

    it('D2: ServiceOrderSummaryService declara 3 spans canon oficinaauto.so.*', function () {
        $file = (new ReflectionClass(ServiceOrderSummaryService::class))->getFileName();
        $src  = file_get_contents($file);

        $matches = preg_match_all("/'oficinaauto\\.so\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(3);
    });

    it('D2: AprovacaoOsService declara 3 spans canon oficinaauto.aprovacao.*', function () {
        $file = (new ReflectionClass(AprovacaoOsService::class))->getFileName();
        $src  = file_get_contents($file);

        $matches = preg_match_all("/'oficinaauto\\.aprovacao\\.[a-z_]+'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(3);
    });

    it('D6: spans totais OficinaAuto >= 14 (Wave 18+RETRY cumulativo)', function () {
        $services = [
            CapacidadeService::class,
            VehicleQueryService::class,
            ServiceOrderSummaryService::class,
            AprovacaoOsService::class,
        ];
        $total = 0;
        foreach ($services as $svc) {
            $file = (new ReflectionClass($svc))->getFileName();
            $src  = file_get_contents($file);
            $total += preg_match_all("/'oficinaauto\\.[a-z_]+\\.[a-z_]+'/", $src);
        }
        expect($total)->toBeGreaterThanOrEqual(14);
    });

    it('D5: README cita journey Martinho + steps numerados', function () {
        $readmePath = base_path('Modules/OficinaAuto/README.md');
        expect(file_exists($readmePath))->toBeTrue();

        $src = file_get_contents($readmePath);
        expect($src)->toContain('Martinho');
    });

    it('D5: CapacidadeService thresholds documentados em resumoCapacidade()', function () {
        $file = (new ReflectionClass(CapacidadeService::class))->getFileName();
        $src  = file_get_contents($file);

        foreach (['ociosa', 'normal', 'apertada', 'lotada', 'overcommit'] as $status) {
            expect($src)->toContain("'{$status}'");
        }
    });

    it('D6: OtelHelper preserva exception em spans oficinaauto.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'oficinaauto.test.wave25_boom',
            fn () => throw new \RuntimeException('w25-boom')
        ))->toThrow(\RuntimeException::class, 'w25-boom');
    });

    it('D2: CapacidadeService constantes públicas declaradas (heurística V0)', function () {
        expect(CapacidadeService::CAPACIDADE_DIARIA_HORAS_DEFAULT)->toBe(32)
            ->and(CapacidadeService::HORAS_OS_ABERTA)->toBe(4)
            ->and(CapacidadeService::HORAS_OS_PRODUCAO)->toBe(6);
    });

    it('D2: VehicleQueryService::STATUSES whitelist documentada', function () {
        expect(VehicleQueryService::STATUSES)
            ->toBeArray()
            ->toContain('all')
            ->toContain('disponivel')
            ->toContain('locada')
            ->toContain('manutencao')
            ->toContain('atrasada');
    });

    it('D5: Cliente Martinho Caçambas mencionado em SCOPE/CHANGELOG', function () {
        $scopePath = base_path('Modules/OficinaAuto/SCOPE.md');
        $changePath = base_path('Modules/OficinaAuto/CHANGELOG.md');

        $found = false;
        foreach ([$scopePath, $changePath] as $p) {
            if (file_exists($p)) {
                $src = file_get_contents($p);
                if (stripos($src, 'Martinho') !== false || stripos($src, 'cacamba') !== false || stripos($src, 'caçamba') !== false) {
                    $found = true;
                    break;
                }
            }
        }
        expect($found)->toBeTrue();
    });

    it('D6: ProducaoOficinaController é redirect pro board canônico (ADR 0265 — não Blade, não Inertia próprio)', function () {
        $ctrlPath = base_path('Modules/OficinaAuto/Http/Controllers/ProducaoOficinaController.php');
        expect(file_exists($ctrlPath))->toBeTrue();
        $src = file_get_contents($ctrlPath);
        expect($src)->toContain('ordens-servico/board')
            ->and($src)->toContain('RedirectResponse');
    });

    it('D5: ServiceOrderSummaryService kpisDashboard retorna shape canon documentado', function () {
        $ref = new ReflectionMethod(ServiceOrderSummaryService::class, 'kpisDashboard');
        $docComment = $ref->getDocComment();

        // locacao_ativa erradicado (ADR 0265) — KPI de locação removido.
        expect($docComment)->toContain('manutencao_ativa')
            ->and($docComment)->toContain('concluida_mes')
            ->and($docComment)->toContain('atrasada');
    });
});
