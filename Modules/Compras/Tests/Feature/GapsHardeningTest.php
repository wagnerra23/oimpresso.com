<?php

declare(strict_types=1);

use Modules\Compras\Http\Controllers\ComprasController;
use Modules\Compras\Http\Requests\ListarComprasRequest;

uses(Tests\TestCase::class);

/**
 * Audit sênior 2026-05-25 — Onda ESTABILIZAR Compras (Gaps #2 #3 #4 #6).
 *
 * Tests source-grep + contract — rodam em SQLite sem dependência de
 * schema MySQL UPos. Smoke completo (HTTP 429 throttle real) fica em
 * CI MySQL pós-merge.
 */

it('Gap #2: ComprasController index usa user->business_id do auth (não session direto)', function () {
    $src = file_get_contents(
        (new ReflectionClass(ComprasController::class))->getFileName(),
    );

    // index() deve ler business_id do auth user — defesa layer 1 (Laracopilot 2026)
    expect(str_contains($src, '$user->business_id') || str_contains($src, 'auth()->user()->business_id'))
        ->toBeTrue('source deve referenciar user->business_id ou auth()->user()->business_id');
    expect(str_contains($src, 'abort_if($businessId <= 0'))->toBeTrue('guard contra business_id zerado');
});

it('Gap #2: index faz cross-check session vs auth (defense layer 2)', function () {
    $src = file_get_contents(
        (new ReflectionClass(ComprasController::class))->getFileName(),
    );

    // Cross-check session('user.business_id') vs auth — detecta drift
    expect(str_contains($src, 'Business drift detectado'))->toBeTrue('msg explicativa quando session ≠ auth');
    expect(str_contains($src, "session('user.business_id', 0)"))->toBeTrue('lê session com default 0 pra comparar');
});

it('Gap #2: show() também aplica guard business_id (não só index)', function () {
    $src = file_get_contents(
        (new ReflectionClass(ComprasController::class))->getFileName(),
    );

    // Conta ocorrências do guard — deve aparecer em index E show
    expect(substr_count($src, 'abort_if($businessId <= 0'))->toBeGreaterThanOrEqual(2);
});

it('Gap #3: route /compras tem middleware throttle 60/1', function () {
    $src = file_get_contents(base_path('Modules/Compras/Routes/web.php'));
    expect($src)->toContain("'throttle:60,1'");
});

it('Gap #4: ListarComprasRequest existe + é FormRequest', function () {
    expect(class_exists(ListarComprasRequest::class))->toBeTrue();

    $ref = new ReflectionClass(ListarComprasRequest::class);
    expect($ref->isSubclassOf(\Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
});

it('Gap #4: ListarComprasRequest.authorize() exige permission compras.view', function () {
    $request = new ListarComprasRequest;

    // Sem usuário autenticado deve negar
    expect($request->authorize())->toBeFalse();
});

it('Gap #4: ListarComprasRequest validation rules cobrem 6 filtros canônicos', function () {
    $request = new ListarComprasRequest;
    $rules = $request->rules();

    expect($rules)->toHaveKey('q')
        ->and($rules)->toHaveKey('stage')
        ->and($rules)->toHaveKey('sort')
        ->and($rules)->toHaveKey('dir')
        ->and($rules)->toHaveKey('per_page')
        ->and($rules)->toHaveKey('compra_id');
});

it('Gap #4: stage whitelist anti-SQLi/IDOR (5 valores enum)', function () {
    $request = new ListarComprasRequest;
    $rules = $request->rules();

    // stage rule deve conter 'in:all,received,ordered,pending,draft'
    $stageRules = is_array($rules['stage']) ? implode(',', $rules['stage']) : $rules['stage'];
    expect($stageRules)->toContain('in:all,received,ordered,pending,draft');
});

it('Gap #4: sort whitelist anti-SQLi (4 columns enum)', function () {
    $request = new ListarComprasRequest;
    $rules = $request->rules();

    $sortRules = is_array($rules['sort']) ? implode(',', $rules['sort']) : $rules['sort'];
    expect($sortRules)->toContain('in:transaction_date,ref_no,final_total,contact_name');
});

it('Gap #4: per_page whitelist (anti-DOS via per_page=999999)', function () {
    $request = new ListarComprasRequest;
    $rules = $request->rules();

    $perPageRules = is_array($rules['per_page']) ? implode(',', $rules['per_page']) : $rules['per_page'];
    expect($perPageRules)->toContain('in:10,25,50,100');
});

it('Gap #4: filtros() helper aplica defaults canônicos', function () {
    // Validator manual (não dá pra resolver request via DI sem session)
    $request = new ListarComprasRequest;
    $request->replace([]);

    $filtros = $request->filtros();
    expect($filtros)->toHaveKey('q')
        ->and($filtros)->toHaveKey('stage')
        ->and($filtros)->toHaveKey('sort')
        ->and($filtros)->toHaveKey('dir')
        ->and($filtros)->toHaveKey('per_page')
        ->and($filtros['stage'])->toBe('all')
        ->and($filtros['sort'])->toBe('transaction_date')
        ->and($filtros['dir'])->toBe('desc')
        ->and($filtros['per_page'])->toBe(25);
});

it('Gap #4: filtros() normaliza dir inválido → desc', function () {
    $request = new ListarComprasRequest;
    $request->replace(['dir' => 'sideways']);

    $filtros = $request->filtros();
    expect($filtros['dir'])->toBe('desc', 'dir inválido cai pra default desc');
});

it('Gap #4: filtros() aceita asc explícito', function () {
    $request = new ListarComprasRequest;
    $request->replace(['dir' => 'asc']);

    $filtros = $request->filtros();
    expect($filtros['dir'])->toBe('asc');
});

it('Gap #4: compraId() retorna 0 quando ausente (não null) — int safe', function () {
    $request = new ListarComprasRequest;
    $request->replace([]);

    expect($request->compraId())->toBe(0);
});

it('Gap #2: ComprasController não usa mais "(int) session(\'user.business_id\')" em index', function () {
    $src = file_get_contents(
        (new ReflectionClass(ComprasController::class))->getFileName(),
    );

    // Especificamente em index() — show() pode ainda referenciar pra cross-check
    // mas o source-of-truth é auth()
    $indexBlock = substr($src, strpos($src, 'public function index'), 2000);
    expect($indexBlock)->not->toContain("(int) session('user.business_id')",
        'pré-refactor business_id vinha de session — agora vem de auth()');
});
