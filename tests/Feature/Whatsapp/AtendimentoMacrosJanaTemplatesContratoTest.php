<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Http\Controllers\Admin\MacrosController;
use Modules\Whatsapp\Http\Controllers\Admin\SettingsController;
use Modules\Whatsapp\Http\Requests\BusinessSettingsRequest;

/**
 * Contrato das telas Atendimento/Macros/Index e Atendimento/JanaTemplates.
 *
 * UCs derivados dos charters (Index.charter.md · JanaTemplates.charter.md), não do .tsx.
 * Tenants fictícios 98 e 99 (ADR 0358) — nunca biz=4.
 *
 * Seed e conferência por DB::table (caminho independente do Eloquent sob teste), pra que o
 * isolamento provado seja o do controller, e não o do mesmo model que gravou a linha.
 *
 * Roda na lane sqlite (.github/ci-sqlite-pest.list): schema sintético no beforeEach,
 * markTestSkipped fora de sqlite (mesmo idioma do MacroVariantsCrudTest).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'sqlite') {
        test()->markTestSkipped('schema sintético — lane sqlite (ci-sqlite-pest.list).');
    }

    foreach (['macro_variants', 'macros', 'whatsapp_business_configs'] as $t) {
        Schema::dropIfExists($t);
    }

    Schema::create('macros', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->string('label', 80);
        $table->string('shortcut', 30)->nullable();
        $table->text('body');
        $table->json('actions_json')->nullable();
        $table->unsignedBigInteger('created_by_user_id')->nullable();
        $table->unsignedInteger('used_count')->default(0);
        $table->timestamps();
        $table->unique(['business_id', 'shortcut'], 'macros_business_shortcut_uniq');
    });

    Schema::create('macro_variants', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->unsignedBigInteger('macro_id');
        $table->string('label', 80);
        $table->text('body');
        $table->unsignedSmallInteger('weight')->default(50);
        $table->boolean('active')->default(true);
        $table->unsignedInteger('sent_count')->default(0);
        $table->unsignedInteger('response_count')->default(0);
        $table->timestamps();
    });

    Schema::create('whatsapp_business_configs', function ($table) {
        $table->bigIncrements('id');
        $table->unsignedInteger('business_id');
        $table->uuid('business_uuid')->unique();
        $table->string('driver', 20)->default('zapi');
        $table->string('display_phone', 20)->nullable();
        $table->boolean('bot_enabled')->default(false);
        $table->string('template_repair_ready_name', 64)->nullable();
        $table->string('template_repair_waiting_parts_name', 64)->nullable();
        $table->string('template_billing_due_name', 64)->nullable();
        $table->string('template_billing_paid_name', 64)->nullable();
        $table->timestamps();
    });
});

function amjtProps($response): array
{
    $ref = new \ReflectionClass($response);
    $prop = $ref->getProperty('props');
    $prop->setAccessible(true);

    return $prop->getValue($response);
}

function amjtResolve($prop): array
{
    if (is_object($prop) && is_callable($prop)) {
        $prop = $prop();
    }

    return is_array($prop) ? $prop : [];
}

function amjtSeedMacro(int $biz, string $label, ?string $shortcut, int $used = 0): int
{
    return (int) DB::table('macros')->insertGetId([
        'business_id' => $biz, 'label' => $label, 'shortcut' => $shortcut,
        'body' => "corpo {$label}", 'used_count' => $used,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function amjtSettingsRequest(array $data): BusinessSettingsRequest
{
    $req = BusinessSettingsRequest::create('/atendimento/canais/jana-templates', 'PUT', $data);
    $req->setContainer(app())->setRedirector(app('redirect'));
    $req->setLaravelSession(app('session.store'));
    $req->setValidator(validator($req->all(), $req->rules()));

    return $req;
}

it('UC-MAC-01 — a lista traz só as macros do business, mais usadas primeiro, com a contagem de variantes', function () {
    session()->put('user.business_id', 98);
    $pouco = amjtSeedMacro(98, 'Saudação', 'oi', 2);
    $muito = amjtSeedMacro(98, 'Pedir CNPJ', 'cnpj', 9);
    DB::table('macro_variants')->insert([
        ['business_id' => 98, 'macro_id' => $muito, 'label' => 'A', 'body' => 'a'],
        ['business_id' => 98, 'macro_id' => $muito, 'label' => 'B', 'body' => 'b'],
    ]);

    $props = amjtProps(app(MacrosController::class)->index(Request::create('/atendimento/macros', 'GET')));
    $macros = amjtResolve($props['macros']);

    expect(array_column($macros, 'id'))->toBe([$muito, $pouco]);
    expect($macros[0]['variants_count'])->toBe(2);
    expect($macros[1]['variants_count'])->toBe(0);
});

it('UC-MAC-02 — macro de outro business não aparece na lista e não é editável nem removível (Tier 0)', function () {
    $alheia = amjtSeedMacro(99, 'Alheia', 'alheia', 50);
    DB::table('macro_variants')->insert(['business_id' => 99, 'macro_id' => $alheia, 'label' => 'X', 'body' => 'x']);
    session()->put('user.business_id', 98);
    $propria = amjtSeedMacro(98, 'Própria', 'propria');

    $controller = app(MacrosController::class);
    $macros = amjtResolve(amjtProps($controller->index(Request::create('/x', 'GET')))['macros']);
    expect(array_column($macros, 'id'))->toBe([$propria]);
    expect($macros[0]['variants_count'])->toBe(0);

    expect(fn () => $controller->update(
        Request::create('/x', 'PUT', ['label' => 'hack', 'body' => 'pwn']),
        $alheia,
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect(fn () => $controller->destroy(Request::create('/x', 'DELETE'), $alheia))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $intacta = DB::table('macros')->where('id', $alheia)->first();
    expect($intacta)->not->toBeNull();
    expect($intacta->label)->toBe('Alheia');
});

it('UC-MAC-03 — criar normaliza o atalho e recusa atalho repetido no mesmo business, mas não em outro', function () {
    session()->put('user.business_id', 98);
    $controller = app(MacrosController::class);

    $controller->store(Request::create('/x', 'POST', ['label' => 'Pedir CNPJ', 'shortcut' => '/CNPJ ', 'body' => 'Envie o CNPJ.']));
    expect(DB::table('macros')->where('business_id', 98)->value('shortcut'))->toBe('cnpj');

    try {
        $controller->store(Request::create('/x', 'POST', ['label' => 'Outra', 'shortcut' => 'cnpj', 'body' => 'x']));
        test()->fail('atalho repetido no mesmo business deveria ser recusado');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(422);
    }
    expect(DB::table('macros')->where('business_id', 98)->count())->toBe(1);

    session()->put('user.business_id', 99);
    $controller->store(Request::create('/x', 'POST', ['label' => 'Outro business', 'shortcut' => 'cnpj', 'body' => 'y']));
    expect(DB::table('macros')->where('business_id', 99)->where('shortcut', 'cnpj')->count())->toBe(1);
});

it('UC-MAC-04 — editar mantém o próprio atalho e remover tira a macro só do business', function () {
    session()->put('user.business_id', 98);
    $id = amjtSeedMacro(98, 'Saudação', 'oi');
    $controller = app(MacrosController::class);

    $controller->update(Request::create('/x', 'PUT', ['label' => 'Saudação nova', 'shortcut' => 'oi', 'body' => 'Olá!']), $id);
    $row = DB::table('macros')->where('id', $id)->first();
    expect($row->label)->toBe('Saudação nova');
    expect($row->shortcut)->toBe('oi');

    $controller->destroy(Request::create('/x', 'DELETE'), $id);
    expect(DB::table('macros')->where('id', $id)->exists())->toBeFalse();
});

it('UC-JTPL-01 — a tela mostra o bot e os 4 templates do business; sem configuração, vem vazia', function () {
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 99, 'business_uuid' => '99999999-0000-0000-0000-000000000099',
        'bot_enabled' => true, 'template_repair_ready_name' => 'alheio_pronto',
    ]);
    session()->put('user.business_id', 98);
    $controller = app(SettingsController::class);

    expect(amjtProps($controller->show())['config'])->toBeNull();

    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 98, 'business_uuid' => '98989898-0000-0000-0000-000000000098',
        'bot_enabled' => false, 'template_repair_ready_name' => 'os_pronta',
        'template_billing_due_name' => 'boleto_vence',
    ]);
    $config = amjtProps($controller->show())['config'];

    expect(array_keys($config))->toBe([
        'bot_enabled', 'template_repair_ready_name', 'template_repair_waiting_parts_name',
        'template_billing_due_name', 'template_billing_paid_name',
    ]);
    expect($config['bot_enabled'])->toBeFalse();
    expect($config['template_repair_ready_name'])->toBe('os_pronta');
    expect($config['template_billing_due_name'])->toBe('boleto_vence');
});

it('UC-JTPL-02 — salvar grava só o bot e os 4 nomes de template, sem tocar driver nem telefone', function () {
    session()->put('user.business_id', 98);
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 98, 'business_uuid' => '98989898-0000-0000-0000-000000000098',
        'driver' => 'meta_cloud', 'display_phone' => '5548000000000', 'bot_enabled' => false,
    ]);

    app(SettingsController::class)->update(amjtSettingsRequest([
        'bot_enabled' => true,
        'template_repair_ready_name' => 'os_pronta',
        'template_repair_waiting_parts_name' => 'os_aguarda_peca',
        'template_billing_due_name' => 'boleto_vence',
        'template_billing_paid_name' => 'boleto_pago',
        'driver' => 'baileys',
        'display_phone' => '5548111111111',
    ]));

    $row = DB::table('whatsapp_business_configs')->where('business_id', 98)->first();
    expect((bool) $row->bot_enabled)->toBeTrue();
    expect($row->template_repair_waiting_parts_name)->toBe('os_aguarda_peca');
    expect($row->template_billing_paid_name)->toBe('boleto_pago');
    expect($row->driver)->toBe('meta_cloud');
    expect($row->display_phone)->toBe('5548000000000');
});

it('UC-JTPL-03 — salvar no business sem configuração cria a dele e não altera a de outro business (Tier 0)', function () {
    DB::table('whatsapp_business_configs')->insert([
        'business_id' => 99, 'business_uuid' => '99999999-0000-0000-0000-000000000099',
        'bot_enabled' => false, 'template_repair_ready_name' => 'alheio_pronto',
    ]);
    session()->put('user.business_id', 98);

    app(SettingsController::class)->update(amjtSettingsRequest([
        'bot_enabled' => true,
        'template_repair_ready_name' => 'os_pronta',
    ]));

    $nossa = DB::table('whatsapp_business_configs')->where('business_id', 98)->first();
    expect($nossa)->not->toBeNull();
    expect($nossa->template_repair_ready_name)->toBe('os_pronta');

    $alheia = DB::table('whatsapp_business_configs')->where('business_id', 99)->first();
    expect((bool) $alheia->bot_enabled)->toBeFalse();
    expect($alheia->template_repair_ready_name)->toBe('alheio_pronto');
    expect(DB::table('whatsapp_business_configs')->count())->toBe(2);
});
