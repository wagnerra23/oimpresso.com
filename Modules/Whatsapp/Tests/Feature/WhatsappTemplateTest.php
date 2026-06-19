<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\WhatsappTemplate;

uses(Tests\TestCase::class);

/**
 * US-WA-013 · WhatsappTemplate model — expandBody (placeholders) + isReadyToSend.
 *
 * Cobre lógica usada em:
 * - ZapiDriver/BaileysDriver::sendTemplate (expand → freeform)
 * - TemplatePicker UI (preview placeholders {{1}}, {{2}}, {{nome}})
 *
 * Padrão SQLite friendly.
 */

beforeEach(function () {
    // era-sqlite: este teste cria schema whatsapp_* manual (sqlite-friendly). No MySQL
    // persistente do nightly isso DROPA tabelas reais → corrompe os testes irmãos
    // (lever do floor SDD). Cobertura real é na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }
    Schema::dropIfExists('whatsapp_templates');
    Schema::create('whatsapp_templates', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('provider', 20)->default('zapi');
        $table->string('meta_template_id', 64)->nullable();
        $table->string('name', 64);
        $table->string('language', 10)->default('pt_BR');
        $table->string('category', 20);
        $table->string('status', 20);
        $table->json('components');
        $table->string('rejection_reason', 255)->nullable();
        $table->timestamp('last_synced_at')->nullable();
        $table->timestamps();
    });
});

function createTpl(array $attrs = []): WhatsappTemplate
{
    return WhatsappTemplate::query()
        ->withoutGlobalScope(ScopeByBusiness::class)
        ->create(array_merge([
            'business_id' => 1,
            'provider' => 'zapi',
            'name' => 'repair_status_ready',
            'language' => 'pt_BR',
            'category' => 'UTILITY',
            'status' => 'LOCAL',
            'components' => [
                ['type' => 'BODY', 'text' => 'Olá {{1}}, sua OS #{{2}} está pronta para retirada!'],
            ],
        ], $attrs));
}

it('expandBody substitui {{1}}, {{2}} pela ordem dos params (Meta-style)', function () {
    $tpl = createTpl();

    $expanded = $tpl->expandBody(['Maria', '#OS-42']);

    expect($expanded)->toBe('Olá Maria, sua OS #42 está pronta para retirada!');
});

it('expandBody substitui {{nome}} (named) por params associativos', function () {
    $tpl = createTpl([
        'components' => [
            ['type' => 'BODY', 'text' => 'Pedido {{pedido}} de {{cliente}} faturado em {{data}}.'],
        ],
    ]);

    $expanded = $tpl->expandBody([
        'pedido' => '#1234',
        'cliente' => 'João',
        'data' => '08/05/2026',
    ]);

    expect($expanded)->toBe('Pedido #1234 de João faturado em 08/05/2026.');
});

it('expandBody mantém placeholders se params vazio (preview cru pro UI picker)', function () {
    $tpl = createTpl();

    expect($tpl->expandBody([]))->toBe('Olá {{1}}, sua OS #{{2}} está pronta para retirada!');
});

it('isReadyToSend true para LOCAL e APPROVED', function () {
    $local = createTpl(['name' => 'tpl_local', 'status' => 'LOCAL']);
    $approved = createTpl(['name' => 'tpl_approved', 'provider' => 'meta_cloud', 'status' => 'APPROVED']);

    expect($local->isReadyToSend())->toBeTrue();
    expect($approved->isReadyToSend())->toBeTrue();
});

it('isReadyToSend false para PENDING/REJECTED/PAUSED/DISABLED (Meta workflow)', function () {
    foreach (['PENDING', 'REJECTED', 'PAUSED', 'DISABLED'] as $status) {
        $tpl = createTpl(['name' => 'tpl_'.strtolower($status), 'provider' => 'meta_cloud', 'status' => $status]);
        expect($tpl->isReadyToSend())->toBeFalse("status={$status} deveria bloquear envio");
    }
});

it('expandBody sem componente BODY retorna string vazia', function () {
    $tpl = createTpl([
        'components' => [
            ['type' => 'HEADER', 'text' => 'Sem body'],
            ['type' => 'FOOTER', 'text' => 'também sem body'],
        ],
    ]);

    expect($tpl->expandBody(['x']))->toBe('');
});
