<?php

/**
 * Modules\Repair — endpoint público /repair-status
 *
 * O cliente final consulta o status do reparo informando job_sheet_no
 * (ou invoice_no) sem login. O nº da OS funciona como token público.
 *
 * Cobertura:
 *  - /repair-status (GET) renderiza view sem autenticação
 *  - /post-repair-status (POST) com nº válido devolve success=true
 *  - /post-repair-status com nº inválido devolve success=false
 *  - rotas administrativas /repair/* exigem auth
 */

use Illuminate\Support\Facades\Schema;
use Modules\Repair\Entities\JobSheet;

beforeEach(function () {
    if (!Schema::hasTable('repair_job_sheets')) {
        $this->markTestSkipped('Migrações do módulo Repair não rodadas no ambiente de teste.');
    }
});

it('expõe a rota pública /repair-status sem middleware de autenticação', function () {
    $middleware = routeMiddleware('repair-status');

    expect(routeExists('repair-status'))->toBeTrue()
        ->and($middleware)->not->toContain('auth')
        ->and($middleware)->not->toContain('authh');
});

it('renderiza a página de consulta pública', function () {
    $response = $this->get('/repair-status');

    $response->assertStatus(200);
    $response->assertViewIs('repair::customer_repair.index');
});

it('responde sucesso quando o job_sheet_no informado existe', function () {
    $business = $this->makeBusiness();
    $sheet = JobSheet::create([
        'business_id' => $business->id,
        'job_sheet_no' => 'OS-' . uniqid(),
        'serial_no' => 'SN-001',
        'status_id' => 1,
    ]);

    $response = $this->postJson('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => $sheet->job_sheet_no,
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
});

it('responde insucesso quando o job_sheet_no não existe', function () {
    $response = $this->postJson('/post-repair-status', [
        'search_type' => 'job_sheet_no',
        'search_number' => 'OS-INEXISTENTE-' . uniqid(),
    ]);

    $response->assertOk();
    $response->assertJson(['success' => false]);
});

it('exige autenticação nas rotas administrativas /repair', function () {
    $response = $this->get('/repair/repair');

    expect($response->status())->toBeIn([302, 401]);
});
