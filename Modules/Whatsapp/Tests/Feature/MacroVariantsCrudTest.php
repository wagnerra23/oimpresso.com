<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Whatsapp\Entities\Macro;
use Modules\Whatsapp\Entities\MacroVariant;
use Modules\Whatsapp\Http\Controllers\Admin\MacroVariantsController;

uses(Tests\TestCase::class);

/**
 * R-WA-049-CRUD — GUARD tests pra MacroVariants CRUD (gap P2 #18 A/B testing).
 *
 * Cobre:
 *  001. CRUD basic (store, update, destroy, mark_winner)
 *  002. validação weight 0-100 + label/body required
 *  003. Tier 0 (ADR 0093) — biz=1 não enxerga/edita variante de biz=99
 *  004. mark_winner desativa outras variantes da mesma macro
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-049
 */
beforeEach(function () {
    foreach (['macro_variants', 'macros'] as $t) {
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
        $table->index(['business_id'], 'macros_business_idx');
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
        $table->index(['business_id', 'macro_id', 'active'], 'mv_biz_macro_active_idx');
    });
});

it('R-WA-049-CRUD-001 — store + update + destroy variant funciona', function () {
    session()->put('user.business_id', 1);
    session()->put('user.id', 42);

    $macro = Macro::query()->create([
        'business_id' => 1,
        'label' => 'Oi',
        'shortcut' => 'oi',
        'body' => 'Olá! Em que posso ajudar?',
    ]);

    $controller = app(MacroVariantsController::class);

    // STORE
    $req = Request::create('/test', 'POST', [
        'label' => 'Versão A — formal',
        'body' => 'Bom dia. Como posso auxiliá-lo?',
        'weight' => 70,
        'active' => true,
    ]);
    $controller->store($req, $macro->id);

    $variant = MacroVariant::query()->where('business_id', 1)->first();
    expect($variant)->not->toBeNull();
    expect($variant->macro_id)->toBe($macro->id);
    expect($variant->label)->toBe('Versão A — formal');
    expect($variant->weight)->toBe(70);
    expect($variant->active)->toBeTrue();

    // UPDATE
    $reqUpd = Request::create('/test', 'PUT', [
        'label' => 'Versão A — formal (revisada)',
        'body' => 'Texto novo.',
        'weight' => 80,
        'active' => true,
    ]);
    $controller->update($reqUpd, $macro->id, $variant->id);
    $variant->refresh();
    expect($variant->label)->toBe('Versão A — formal (revisada)');
    expect($variant->weight)->toBe(80);

    // DESTROY
    $controller->destroy(Request::create('/test', 'DELETE'), $macro->id, $variant->id);
    expect(MacroVariant::query()->where('business_id', 1)->count())->toBe(0);
});

it('R-WA-049-CRUD-002 — validação rejeita label vazio e weight fora 0-100', function () {
    session()->put('user.business_id', 1);

    $macro = Macro::query()->create([
        'business_id' => 1, 'label' => 'M', 'body' => 'x',
    ]);

    $controller = app(MacroVariantsController::class);

    // label vazio → 422
    expect(fn () => $controller->store(
        Request::create('/test', 'POST', ['label' => '', 'body' => 'x', 'weight' => 50]),
        $macro->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    // weight 150 → 422 (fora do between 0,100)
    expect(fn () => $controller->store(
        Request::create('/test', 'POST', ['label' => 'A', 'body' => 'x', 'weight' => 150]),
        $macro->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    // weight -10 → 422
    expect(fn () => $controller->store(
        Request::create('/test', 'POST', ['label' => 'A', 'body' => 'x', 'weight' => -10]),
        $macro->id,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);

    // weight=0 (pausa manual) → OK
    $controller->store(
        Request::create('/test', 'POST', ['label' => 'Pausada', 'body' => 'x', 'weight' => 0]),
        $macro->id,
    );
    $v = MacroVariant::query()->withoutGlobalScope(ScopeByBusiness::class)
        ->where('business_id', 1)->where('weight', 0)->first();
    expect($v)->not->toBeNull();
});

it('R-WA-049-CRUD-003 — Tier 0 (ADR 0093): biz=1 não acessa variante de biz=99', function () {
    // Cria macro+variante em biz=99 SEM autenticar
    $macroAlien = new Macro([
        'business_id' => 99, 'label' => 'Alien', 'body' => 'x',
    ]);
    $macroAlien->save();

    $variantAlien = new MacroVariant([
        'business_id' => 99,
        'macro_id' => $macroAlien->id,
        'label' => 'Alien V',
        'body' => 'cross-tenant',
        'weight' => 50,
    ]);
    $variantAlien->save();

    // Autenticado em biz=1
    session()->put('user.business_id', 1);

    // Listar não enxerga variante alheia
    $count = MacroVariant::query()->count();
    expect($count)->toBe(0);

    // index do Controller alheio → ModelNotFound (macro_id não pertence a biz=1)
    $controller = app(MacroVariantsController::class);
    expect(fn () => $controller->index(Request::create('/x', 'GET'), $macroAlien->id))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    // update direto na variante alheia tbm falha (findVariantOrFail)
    expect(fn () => $controller->update(
        Request::create('/x', 'PUT', ['label' => 'hack', 'body' => 'pwn']),
        $macroAlien->id,
        $variantAlien->id,
    ))->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
});

it('R-WA-049-CRUD-004 — mark_winner desativa outras + bump weight 100 + preserva histórico', function () {
    session()->put('user.business_id', 1);

    $macro = Macro::query()->create([
        'business_id' => 1, 'label' => 'M', 'body' => 'x',
    ]);

    $vA = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'A', 'body' => 'a', 'weight' => 50, 'active' => true,
        'sent_count' => 100, 'response_count' => 60,
    ]);
    $vB = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'B', 'body' => 'b', 'weight' => 50, 'active' => true,
        'sent_count' => 100, 'response_count' => 20,
    ]);
    $vC = MacroVariant::query()->create([
        'business_id' => 1, 'macro_id' => $macro->id,
        'label' => 'C', 'body' => 'c', 'weight' => 50, 'active' => true,
        'sent_count' => 100, 'response_count' => 10,
    ]);

    $controller = app(MacroVariantsController::class);
    $controller->markWinner(Request::create('/x', 'POST'), $macro->id, $vA->id);

    $vA->refresh();
    $vB->refresh();
    $vC->refresh();

    expect($vA->active)->toBeTrue();
    expect($vA->weight)->toBe(100);
    // Histórico preservado mesmo após bump
    expect($vA->sent_count)->toBe(100);
    expect($vA->response_count)->toBe(60);

    expect($vB->active)->toBeFalse();
    expect($vC->active)->toBeFalse();
    // Outras mantêm seus pesos (apenas desativadas — histórico intacto)
    expect($vB->sent_count)->toBe(100);
});
