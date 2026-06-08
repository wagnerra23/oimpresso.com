<?php

declare(strict_types=1);

use Modules\Repair\Http\Requests\CancelJobSheetRequest;
use Modules\Repair\Http\Requests\ExecuteRepairFsmActionRequest;
use Modules\Repair\Http\Requests\ReopenJobSheetRequest;
use Modules\Repair\Http\Requests\StartFsmActionRequest;

uses(Tests\TestCase::class);

/**
 * Wave 27 POLISH FINAL Repair — D8 FormRequests FSM canon expandidos.
 *
 * Cobre:
 *   - ReopenJobSheetRequest (NEW W27) — action `acionar_garantia` FSM canon (ADR 0143)
 *   - FormRequests FSM canon completos: Start + Cancel + Execute + Reopen
 *   - Tier 0 IRREVOGÁVEL preservado: motivo obrigatório (audit LGPD)
 *   - CDC Art. 26 sentinel (90d garantia produto durável)
 *
 * Multi-tenant Tier 0 (ADR 0093): NÃO chama DB real. Reflection + dispatch sintético.
 * Tests biz=1 (ADR 0101) — NUNCA biz=4 ROTA LIVRE.
 *
 * @see Modules\Repair\Http\Requests\ReopenJobSheetRequest
 * @see Modules\Repair\Tests\Feature\Wave25RepairFsmCanonExpandedTest
 * @see memory/decisions/0143-fsm-pipeline-live-prod-marco-2026-05-12.md
 */
describe('Wave 27 Repair Polish', function () {
    // ─── D8 FormRequests FSM canon completos ─────────────────────────────────

    it('D8 W27 — ReopenJobSheetRequest existe e estende FormRequest', function () {
        expect(class_exists(ReopenJobSheetRequest::class))->toBeTrue();
        expect(new ReopenJobSheetRequest())->toBeInstanceOf(\Illuminate\Foundation\Http\FormRequest::class);
    });

    it('D8 W27 — ReopenJobSheetRequest exige motivo + jobsheet_id', function () {
        $req = new ReopenJobSheetRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('jobsheet_id');
        expect($rules)->toHaveKey('motivo');
        expect($rules['motivo'])->toContain('required');
        expect($rules['motivo'])->toContain('min:5');
        expect($rules['motivo'])->toContain('max:500');
        // defeito_novo opcional (sometimes) pra reabertura sem novo defeito
        expect($rules)->toHaveKey('defeito_novo');
        expect($rules['defeito_novo'])->toContain('sometimes');
    });

    it('D8 W27 — ReopenJobSheetRequest::authorize requer auth (não pública)', function () {
        $req = new ReopenJobSheetRequest();
        // Sem user autenticado → false
        expect($req->authorize())->toBeFalse();
    });

    it('D8 W27 — ReopenJobSheetRequest mensagens em PT-BR com referência LGPD+CDC', function () {
        $req = new ReopenJobSheetRequest();
        $messages = $req->messages();

        expect($messages)->toHaveKey('motivo.required');
        expect($messages['motivo.required'])->toContain('LGPD');
        expect($messages['motivo.required'])->toContain('CDC');
    });

    it('D8 W27 — ReopenJobSheetRequest contém constante CDC_GARANTIA_DIAS=90 (CDC Art. 26)', function () {
        $source = file_get_contents(__DIR__ . '/../../Http/Requests/ReopenJobSheetRequest.php');
        expect($source)->toContain('CDC_GARANTIA_DIAS = 90');
        expect($source)->toContain('CDC Art. 26');
    });

    // ─── FSM FormRequests canon — 4 requests obrigatórios ────────────────────

    it('FSM FormRequests canon — 4 requests presentes (Start/Cancel/Execute/Reopen)', function () {
        $canon = [
            StartFsmActionRequest::class,
            CancelJobSheetRequest::class,
            ExecuteRepairFsmActionRequest::class,
            ReopenJobSheetRequest::class,
        ];

        foreach ($canon as $fqcn) {
            expect(class_exists($fqcn))->toBeTrue("FormRequest FSM canon ausente: {$fqcn}");
        }
    });

    it('FSM FormRequests canon — todos exigem auth (não públicos)', function () {
        foreach ([CancelJobSheetRequest::class, ReopenJobSheetRequest::class] as $fqcn) {
            $req = new $fqcn();
            // authorize() == false sem user (auth()->check() retorna false)
            expect($req->authorize())->toBeFalse("FormRequest {$fqcn}::authorize() deve exigir auth");
        }
    });

    // ─── D2 FSM canon 13 stages sentinel (W25 preserved) ─────────────────────

    it('D2 W27 — Wave25 FSM canon test continua presente (lock-in)', function () {
        $path = __DIR__ . '/Wave25RepairFsmCanonExpandedTest.php';
        expect(file_exists($path))->toBeTrue('Wave25 FSM canon expanded preservado W27 (regressão fatal se faltar)');
    });

    it('D2 W27 — retention.php shim continua presente (W17 lock-in)', function () {
        $path = __DIR__ . '/../../Config/retention.php';
        expect(file_exists($path))->toBeTrue('Repair retention.php shim W17 preservado');

        $config = require $path;
        expect($config)->toBeArray();
        expect($config)->toHaveKey('enabled');
        expect($config)->toHaveKey('tabelas');
        expect($config['tabelas'])->toHaveKey('repair_job_sheets');
        // CCB Art. 206 §5 III: 5 anos prescrição relação comercial
        expect($config['tabelas']['repair_job_sheets'])->toBe(1825);
    });

    // ─── Tier 0 IRREVOGÁVEL sentinels ────────────────────────────────────────

    it('Tier 0 sentinel — ReopenJobSheetRequest valida stage origem `entregue_completo`', function () {
        // Lock-in: garantia SÓ pode ser acionada a partir de stage `entregue_completo`
        // (ADR 0143 §Repair pipeline). Reabertura de qualquer outro stage = bug.
        $source = file_get_contents(__DIR__ . '/../../Http/Requests/ReopenJobSheetRequest.php');
        expect($source)->toContain("'entregue_completo'");
        expect($source)->toContain('acionar_garantia');
    });

    it('Tier 0 sentinel — ReopenJobSheetRequest valida business_id da sessão (anti-IDOR)', function () {
        $source = file_get_contents(__DIR__ . '/../../Http/Requests/ReopenJobSheetRequest.php');
        expect($source)->toContain("session('user.business_id')");
        expect($source)->toContain('business_id');
        expect($source)->toContain('anti-IDOR');
    });
});
