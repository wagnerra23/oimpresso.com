<?php

declare(strict_types=1);

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\RecurringBilling\Http\Controllers\SubscriptionEventController;
use Modules\RecurringBilling\Http\Controllers\SubscriptionNoteController;
use Modules\RecurringBilling\Models\SubscriptionEvent;

uses(Tests\TestCase::class);

/**
 * Auditoria RecurringBilling d2 (2026-11-19) — timeline e notas da assinatura:
 *  - POST /{id}/events aceitava kinds de sistema (event-charge, event-nf...) e deixava qualquer
 *    usuário forjar "cobrança disparada" / "NF emitida" na timeline;
 *  - SubscriptionEvent era append-only só por ausência de rota (o model aceitava update/delete);
 *  - DELETE /{id}/notes/{noteId} apagava nota de qualquer autor.
 *
 * SQLite in-memory (lane modules-pest). Tenant 98 (ADR 0358); nunca o 4.
 * As permissões são decididas por um Gate::before do teste, para medir a regra do controller.
 */
beforeEach(function () {
    if (config('database.default') !== 'sqlite' || ! str_contains((string) config('database.connections.sqlite.database'), ':memory:')) {
        $this->markTestSkipped('Rodado em SQLite in-memory (schema montado aqui).');
    }

    foreach (['rb_subscription_events', 'rb_subscription_notes', 'rb_subscriptions', 'users'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('users', function ($t) {
        $t->increments('id');
        $t->string('username')->unique();
        $t->string('first_name')->nullable();
        $t->string('password');
        $t->integer('business_id')->nullable();
        $t->rememberToken();
        $t->softDeletes();
        $t->timestamps();
    });
    Schema::create('rb_subscriptions', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedInteger('contact_id')->nullable();
        $t->string('status', 20)->default('active');
        $t->json('metadata')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('rb_subscription_events', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->string('kind', 20);
        $t->string('by_actor', 64);
        $t->text('body');
        $t->dateTime('occurred_at');
        $t->timestamps();
    });
    Schema::create('rb_subscription_notes', function ($t) {
        $t->id();
        $t->unsignedInteger('business_id')->index();
        $t->unsignedBigInteger('subscription_id');
        $t->unsignedInteger('user_id');
        $t->text('body');
        $t->boolean('is_pinned')->default(false);
        $t->timestamps();
        $t->softDeletes();
    });

    $this->sub = (int) DB::table('rb_subscriptions')->insertGetId(['business_id' => 98, 'contact_id' => 501,
        'status' => 'active', 'metadata' => '[]', 'created_at' => now(), 'updated_at' => now()]);
    session(['user.business_id' => 98]);

    // Tabelas Spatie vazias: o Gate::before do PermissionServiceProvider consulta `permissions`
    // e, sem a tabela, o can() morre antes do nosso before (idioma do Wave21NewSubscriptionTest).
    foreach (['role_has_permissions', 'model_has_roles', 'model_has_permissions', 'roles', 'permissions'] as $t) {
        Schema::dropIfExists($t);
    }
    Schema::create('permissions', function ($t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('roles', function ($t) {
        $t->bigIncrements('id');
        $t->string('name');
        $t->string('guard_name');
        $t->timestamps();
        $t->unique(['name', 'guard_name']);
    });
    Schema::create('model_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['permission_id', 'model_id', 'model_type'], 'mhp_pk_rbtl');
    });
    Schema::create('model_has_roles', function ($t) {
        $t->unsignedBigInteger('role_id');
        $t->string('model_type');
        $t->unsignedBigInteger('model_id');
        $t->primary(['role_id', 'model_id', 'model_type'], 'mhr_pk_rbtl');
    });
    Schema::create('role_has_permissions', function ($t) {
        $t->unsignedBigInteger('permission_id');
        $t->unsignedBigInteger('role_id');
        $t->primary(['permission_id', 'role_id']);
    });
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    // notes.write: o gate de rota do PR 1 do lote (d1). A regra de autor/gestor é medida à parte.
    $this->permitidas = new ArrayObject(['recurringbilling.notes.write']);
    $permitidas = $this->permitidas;
    Gate::before(function ($user, string $ability) use ($permitidas) {
        return in_array($ability, $permitidas->getArrayCopy(), true) ? true : null;
    });

    $this->usuario = fn (string $nome) => User::forceCreate(['username' => 'rbtl_'.$nome.'_'.uniqid(),
        'first_name' => $nome, 'password' => bcrypt('x'), 'business_id' => 98]);
    $this->nota = fn (int $autorId) => (int) DB::table('rb_subscription_notes')->insertGetId(['business_id' => 98,
        'subscription_id' => $this->sub, 'user_id' => $autorId, 'body' => 'nota de teste',
        'created_at' => now(), 'updated_at' => now()]);
});

afterEach(function () {
    session()->flush();
});

it('d2 · POST /events recusa kind de sistema (cobrança, NF) e não grava nada', function () {
    $this->actingAs(($this->usuario)('Ana'));

    foreach ([SubscriptionEvent::KIND_CHARGE, SubscriptionEvent::KIND_NF, SubscriptionEvent::KIND_STATUS] as $kind) {
        $request = Request::create("/recurring-billing/{$this->sub}/events", 'POST', ['body' => 'forjado', 'kind' => $kind]);
        expect(fn () => (new SubscriptionEventController())->store($request, $this->sub))
            ->toThrow(ValidationException::class);
    }

    expect(DB::table('rb_subscription_events')->count())->toBe(0);
});

it('d2 · POST /events grava nota humana (com ou sem kind=note)', function () {
    $this->actingAs(($this->usuario)('Ana'));

    $comKind = (new SubscriptionEventController())->store(
        Request::create('/x', 'POST', ['body' => 'ligou pedindo 2ª via', 'kind' => 'note']), $this->sub);
    $semKind = (new SubscriptionEventController())->store(Request::create('/x', 'POST', ['body' => 'sem kind']), $this->sub);

    expect($comKind->getStatusCode())->toBe(201)
        ->and($semKind->getStatusCode())->toBe(201)
        ->and(DB::table('rb_subscription_events')->pluck('kind')->all())->toBe(['note', 'note']);
});

it('d2 · evento da timeline não se altera nem se apaga pelo model', function () {
    $evento = SubscriptionEvent::create(['business_id' => 98, 'subscription_id' => $this->sub,
        'kind' => SubscriptionEvent::KIND_CHARGE, 'by_actor' => 'sistema', 'body' => 'Cobrança disparada',
        'occurred_at' => now()]);

    expect(fn () => $evento->update(['body' => 'reescrito']))->toThrow(LogicException::class)
        ->and(fn () => $evento->delete())->toThrow(LogicException::class)
        ->and(DB::table('rb_subscription_events')->where('id', $evento->id)->value('body'))->toBe('Cobrança disparada');
});

it('d2 · nota de outro usuário não é apagada (403); o autor apaga a própria', function () {
    $autor = ($this->usuario)('Autor');
    $outro = ($this->usuario)('Outro');
    $nota = ($this->nota)($autor->id);

    $this->actingAs($outro);
    $negado = (new SubscriptionNoteController())->destroy($this->sub, $nota);
    expect($negado->getStatusCode())->toBe(403)
        ->and(DB::table('rb_subscription_notes')->where('id', $nota)->value('deleted_at'))->toBeNull();

    $this->actingAs($autor);
    $ok = (new SubscriptionNoteController())->destroy($this->sub, $nota);
    expect($ok->getStatusCode())->toBe(200)
        ->and(DB::table('rb_subscription_notes')->where('id', $nota)->value('deleted_at'))->not->toBeNull();
});

it('d2 · quem gerencia a assinatura apaga nota de outro usuário', function () {
    $nota = ($this->nota)(($this->usuario)('Autor')->id);

    $this->actingAs(($this->usuario)('Gestor'));
    $this->permitidas->append('recurringbilling.subscriptions.manage');

    $r = (new SubscriptionNoteController())->destroy($this->sub, $nota);
    expect($r->getStatusCode())->toBe(200)
        ->and(DB::table('rb_subscription_notes')->where('id', $nota)->value('deleted_at'))->not->toBeNull();
});
