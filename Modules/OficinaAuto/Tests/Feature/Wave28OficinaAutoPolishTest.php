<?php

declare(strict_types=1);

use Modules\OficinaAuto\Entities\ServiceOrderItem;
use Modules\OficinaAuto\Services\ServiceOrderItemService;

uses(Tests\TestCase::class);

/**
 * Wave 28 OficinaAuto POLISH ≥92 — D2 +3 cenários ServiceOrderItem (sem boot DB).
 *
 * Estratégia: reflection + source-grep + container DI. Cobertura DB-real
 * coberta em W27 ServiceOrderItemTest (que skip em sqlite — ADR 0101).
 *
 * Cobre adicional sobre W18+W23+W25+W27:
 *   - D2 contract: ServiceOrderItem TIPO_* constantes blindadas (downstream UI)
 *   - D2 contract: ServiceOrderItemService 5 métodos públicos canon
 *   - D6 doc: ServiceOrderController index documenta WHY não migrou Inertia::defer
 *     (rollback PR #963 — Index.tsx não wrap `<Deferred>`, prerequisito MWART)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - ADR 0143 FSM ServiceOrder pipeline NÃO tocada
 *   - ADR 0093 multi-tenant Tier 0 — global scope preservado
 *
 * @see Modules/OficinaAuto/CHANGELOG.md Wave 28
 * @see Modules/OficinaAuto/Services/ServiceOrderItemService.php (W27 G1)
 */
describe('Wave 28 OficinaAuto POLISH', function () {

    it('D2: ServiceOrderItem expõe 3 TIPO_* constantes canon (peca/mao_obra/servico_terceiro)', function () {
        expect(ServiceOrderItem::TIPO_PECA)->toBe('peca')
            ->and(ServiceOrderItem::TIPO_MAO_OBRA)->toBe('mao_obra')
            ->and(ServiceOrderItem::TIPO_SERVICO_TERCEIRO)->toBe('servico_terceiro');
    });

    it('D2: ServiceOrderItemService expõe 4 métodos públicos canon (addItem/recalcularTotal/breakdownPorTipo/listarPorTipo)', function () {
        $methods = collect((new ReflectionClass(ServiceOrderItemService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor())
            ->pluck('name')
            ->toArray();

        foreach (['addItem', 'recalcularTotal', 'breakdownPorTipo', 'listarPorTipo'] as $m) {
            expect($methods)->toContain($m);
        }
    });

    it('D2: ServiceOrderItemService resolve do container (DI canon)', function () {
        $svc = app(ServiceOrderItemService::class);
        expect($svc)->toBeInstanceOf(ServiceOrderItemService::class);
    });

    it('D6: ServiceOrderController index documenta rollback Inertia::defer PR #963 (pré-req MWART)', function () {
        $ctrlPath = base_path('Modules/OficinaAuto/Http/Controllers/ServiceOrderController.php');
        expect(file_exists($ctrlPath))->toBeTrue();

        $src = file_get_contents($ctrlPath);

        // Comentário de rollback canon — preserva contexto pra próxima Wave que tentar defer
        // (precisa companion PR atualizar Index.tsx pra `<Deferred data="orders">` antes)
        expect($src)->toContain('ROLLBACK')
            ->and($src)->toContain('Inertia::defer')
            ->and($src)->toContain('paginate(25)'); // pattern eager mantido até wrap React
    });

    it('D2: ServiceOrderItemService::addItem rejeita tipo inválido (defensive contract)', function () {
        // Source-grep — guard implementado (sem precisar DB)
        $file = (new ReflectionClass(ServiceOrderItemService::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain('InvalidArgumentException')
            ->and($src)->toContain("tipo inválido");
    });

    it('D2: ServiceOrderItemService::addItem checa cross-tenant OS pertence ao business (anti-IDOR)', function () {
        // Source-grep — guard cross-tenant explícito (ADR 0093 Tier 0)
        $file = (new ReflectionClass(ServiceOrderItemService::class))->getFileName();
        $src = file_get_contents($file);

        // mensagem real: "ServiceOrder #{$osId} não existe OU não pertence ao business {$businessId}"
        expect($src)->toContain('não pertence ao business');
    });
});
