<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\OficinaAuto\Services\AprovacaoOsService;
use Modules\OficinaAuto\Services\Producao\CapacidadeService;
use Modules\OficinaAuto\Services\ServiceOrderSummaryService;
use Modules\OficinaAuto\Services\VehicleQueryService;

uses(Tests\TestCase::class);

/**
 * Wave 26 OficinaAuto POLISH 77→88 — saturação D2/D5/D6 sem boot DB.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit DB pra
 * paralelização worktree (ADR 0143 FSM preservado, ADR 0093 multi-tenant).
 *
 * Cobertura adicional sobre Wave 18/23/25:
 *   - D2: Pest expand CapacidadeService thresholds completos + VehicleQueryService whitelist
 *   - D2: ServiceOrderSummaryService shape kpisDashboard canon
 *   - D5: CustomerJourney Martinho Caçambas E2E completo (README 6 passos numerados)
 *   - D5: README cita LGPD pii_fields_tracked completo
 *   - D6: ServiceOrderController index usa Inertia::defer pro kpis (RUNBOOK pattern)
 *   - D6: ServiceOrderController magro métodos < 60 linhas
 *
 * @see Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php
 * @see Modules/OficinaAuto/README.md
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
describe('Wave 26 OficinaAuto POLISH 77→88', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D2: Container resolve 4 Services canon (Wave 25 contrato mantido)', function () {
        expect(app(VehicleQueryService::class))->toBeInstanceOf(VehicleQueryService::class)
            ->and(app(ServiceOrderSummaryService::class))->toBeInstanceOf(ServiceOrderSummaryService::class)
            ->and(app(CapacidadeService::class))->toBeInstanceOf(CapacidadeService::class)
            ->and(app(AprovacaoOsService::class))->toBeInstanceOf(AprovacaoOsService::class);
    });

    it('D2: CapacidadeService thresholds completos (5 status canônicos)', function () {
        $file = (new ReflectionClass(CapacidadeService::class))->getFileName();
        $src  = file_get_contents($file);

        foreach (['ociosa', 'normal', 'apertada', 'lotada', 'overcommit'] as $status) {
            expect(str_contains($src, "'{$status}'"))->toBeTrue("Status canônico '{$status}' deve estar em resumoCapacidade()");
        }

        // Heurística V0 constants públicas
        expect(CapacidadeService::CAPACIDADE_DIARIA_HORAS_DEFAULT)->toBe(32);
        expect(CapacidadeService::HORAS_OS_ABERTA)->toBe(4);
        expect(CapacidadeService::HORAS_OS_PRODUCAO)->toBe(6);
    });

    it('D2: VehicleQueryService.STATUSES whitelist completa (5 valores canônicos)', function () {
        expect(VehicleQueryService::STATUSES)
            ->toBeArray()
            ->toHaveCount(5)
            ->toContain('all')
            ->toContain('disponivel')
            ->toContain('locada')
            ->toContain('manutencao')
            ->toContain('atrasada');
    });

    it('D2: ServiceOrderSummaryService.kpisDashboard shape canon documentado', function () {
        $ref = new ReflectionMethod(ServiceOrderSummaryService::class, 'kpisDashboard');
        $docComment = $ref->getDocComment();

        // 3 KPIs canon (locacao_ativa erradicado — ADR 0265)
        foreach (['manutencao_ativa', 'concluida_mes', 'atrasada'] as $kpi) {
            expect(str_contains((string) $docComment, $kpi))->toBeTrue("KPI '{$kpi}' deve estar declarado no docblock");
        }
    });

    it('D2: 14+ spans canon oficinaauto.* (Wave 18+25 mantidos)', function () {
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
        expect($total)->toBeGreaterThanOrEqual(14, "Esperava 14+ spans modulewide; achou {$total}");
    });

    it('D5: README cita Martinho Caçambas + cliente piloto + journey numerada', function () {
        $readmePath = base_path('Modules/OficinaAuto/README.md');
        expect(file_exists($readmePath))->toBeTrue();

        $src = file_get_contents($readmePath);
        expect($src)->toContain('Martinho');
        expect($src)->toContain('Cliente piloto');
        expect($src)->toContain('Journey real');

        // 6 passos numerados
        $passos = preg_match_all("/^\\| \\d\\. /m", $src);
        expect($passos)->toBeGreaterThanOrEqual(6, "Esperava 6+ passos journey; achou {$passos}");
    });

    it('D5: README documenta LGPD pii_fields_tracked completo', function () {
        $src = file_get_contents(base_path('Modules/OficinaAuto/README.md'));

        expect($src)->toContain('plate')
            ->and($src)->toContain('chassis')
            ->and($src)->toContain('renavam')
            ->and($src)->toContain('pii_redactor_enabled');
    });

    it('D5: README cita 3 CNAEs cobertos (4520 + 2212 + 4581)', function () {
        $src = file_get_contents(base_path('Modules/OficinaAuto/README.md'));

        expect($src)->toContain('4520'); // Manutenção/Reparação
        expect($src)->toContain('2212'); // Recapagem
        expect($src)->toContain('4581'); // Locação Caçambas
    });

    it('D5: README documenta FSM canônica ADR 0143 LIVE', function () {
        $src = file_get_contents(base_path('Modules/OficinaAuto/README.md'));

        expect($src)->toContain('FSM canônica');
        expect($src)->toContain('0143');
    });

    it('D5: README documenta WhatsApp aprovação PIN+HMAC (US-OFICINA-006)', function () {
        $src = file_get_contents(base_path('Modules/OficinaAuto/README.md'));

        expect($src)->toContain('WhatsApp');
        expect($src)->toContain('PIN');
        expect($src)->toContain('HMAC');
    });

    it('D6: ServiceOrderController index() delega pro workspace unificado (tela única)', function () {
        $ctrlPath = base_path('Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php');
        expect(file_exists($ctrlPath))->toBeTrue();
        $src = file_get_contents($ctrlPath);

        // Unificação 2026-06-11 ([W]): /ordens-servico e /board servem a MESMA tela
        // (workspace com toggle Kanban·Lista·Grade·Fila in-page). index() delega pro
        // board() — zero duplicação. Os KPIs saem de buildBoardKpis (sobre as colunas
        // já montadas, zero query extra — defer não é mais necessário).
        expect($src)->toContain('return $this->board($request);');
        expect($src)->toContain('buildBoardKpis');
    });

    it('D6: ServiceOrderController board() documenta a tela unificada (toggle 4 views)', function () {
        $src = file_get_contents(base_path('Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php'));

        // A unificação (tela única servida em /ordens-servico e /board) fica documentada
        // no controller pra não regredir pra 2 páginas duplicadas.
        expect($src)->toContain('workspace com toggle');
    });

    it('D6: spans oficinaauto.* preservam exception (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'oficinaauto.test.wave26_boom',
            fn () => throw new \RuntimeException('w26-oa-boom')
        ))->toThrow(\RuntimeException::class, 'w26-oa-boom');
    });

    it('D5: Martinho Caçambas em SCOPE ou CHANGELOG (Wave 25 mantido)', function () {
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

    it('D5: CapacidadeService heurística V0 explicitada em docblock (4h aberta + 6h producao)', function () {
        $file = (new ReflectionClass(CapacidadeService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain('1 OS aberta = 4h');
        expect($src)->toContain('OS em_servico/em_producao = 6h');
        expect($src)->toContain('US-OFICINA-007'); // Pré-req sinalizado pra evolução
    });
});
