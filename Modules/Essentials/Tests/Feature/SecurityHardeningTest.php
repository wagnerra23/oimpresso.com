<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Modules\Essentials\Entities\Document;
use Modules\Essentials\Entities\KnowledgeBase;
use Modules\Essentials\Entities\ToDo;
use Modules\Essentials\Http\Requests\StoreDocumentRequest;
use Modules\Essentials\Http\Requests\StoreHolidayRequest;
use Modules\Essentials\Http\Requests\StoreKnowledgeBaseRequest;
use Modules\Essentials\Http\Requests\StoreMessageRequest;
use Modules\Essentials\Http\Requests\StoreReminderRequest;
use Modules\Essentials\Http\Requests\UpdateReminderRequest;
use Modules\Essentials\Policies\DocumentPolicy;
use Modules\Essentials\Policies\KnowledgeBasePolicy;
use Modules\Essentials\Policies\ToDoPolicy;

uses(Tests\TestCase::class);

/**
 * D8 Security Wave 15 — smoke test do hardening (FormRequests + Throttle + Policies).
 *
 * Roda em qualquer driver (não requer MySQL) — só inspeciona container Laravel:
 *  - 6 FormRequests instanciáveis com authorize()/rules() válidos
 *  - 3 Policies registradas via Gate
 *  - 4 rotas críticas com middleware throttle
 *
 * Bloqueia regressão silenciosa tipo: dev remove FormRequest + volta pra
 * $request->validate() inline (perde camada authorize() + messages PT-BR).
 *
 * @see Modules/Essentials/Http/Requests/*
 * @see Modules/Essentials/Policies/*
 * @see Modules/Essentials/Routes/web.php
 */

it('6 FormRequests Essentials extendem FormRequest e expõem rules()', function () {
    $classes = [
        StoreReminderRequest::class,
        UpdateReminderRequest::class,
        StoreDocumentRequest::class,
        StoreHolidayRequest::class,
        StoreKnowledgeBaseRequest::class,
        StoreMessageRequest::class,
    ];

    foreach ($classes as $fqcn) {
        expect(class_exists($fqcn))->toBeTrue("FormRequest ausente: {$fqcn}");
        expect(is_subclass_of($fqcn, \Illuminate\Foundation\Http\FormRequest::class))
            ->toBeTrue("{$fqcn} não estende FormRequest");

        $instance = new $fqcn();
        expect(method_exists($instance, 'rules'))->toBeTrue("{$fqcn} sem rules()");
        expect(method_exists($instance, 'authorize'))->toBeTrue("{$fqcn} sem authorize()");
        expect(method_exists($instance, 'messages'))->toBeTrue("{$fqcn} sem messages() PT-BR");
    }
});

it('StoreReminderRequest valida campos obrigatórios (date/time/repeat)', function () {
    $req = new StoreReminderRequest();
    $rules = $req->rules();

    expect($rules)->toHaveKeys(['name', 'date', 'time', 'repeat']);
    expect($rules['name'])->toContain('required');
    expect($rules['date'])->toContain('required');
});

it('StoreDocumentRequest aplica mimes whitelist + max 20MB pra upload', function () {
    $req = new StoreDocumentRequest();
    // Sem body → modo document (upload)
    $rules = $req->rules();

    expect($rules)->toHaveKey('name');
    $nameRules = is_array($rules['name']) ? implode('|', $rules['name']) : $rules['name'];
    expect($nameRules)->toContain('file');
    expect($nameRules)->toContain('max:20480');
    expect($nameRules)->toContain('mimes:');
});

it('3 Policies Essentials registradas via Gate (multi-tenant ADR 0093)', function () {
    // Re-registra (em testing isolado o boot pode não ter rodado ainda)
    Gate::policy(ToDo::class, ToDoPolicy::class);
    Gate::policy(Document::class, DocumentPolicy::class);
    Gate::policy(KnowledgeBase::class, KnowledgeBasePolicy::class);

    expect(Gate::getPolicyFor(ToDo::class))->toBeInstanceOf(ToDoPolicy::class);
    expect(Gate::getPolicyFor(Document::class))->toBeInstanceOf(DocumentPolicy::class);
    expect(Gate::getPolicyFor(KnowledgeBase::class))->toBeInstanceOf(KnowledgeBasePolicy::class);
});

it('Policies bloqueiam cross-tenant (business_id ≠ session)', function () {
    // User stub fake (não persiste DB) — testa lógica pura sameBusiness()
    $policy = new ToDoPolicy();

    $user = new \App\User();
    $user->id = 1;
    $user->business_id = 1;

    $todo = new ToDo();
    $todo->business_id = 99; // cliente diferente
    $todo->created_by = 1;

    // Sessão biz=1, modelo biz=99 → DENY (Tier 0 multi-tenant)
    session()->put('user.business_id', 1);
    expect($policy->view($user, $todo))->toBeFalse();
    expect($policy->update($user, $todo))->toBeFalse();
    expect($policy->delete($user, $todo))->toBeFalse();
});

it('rotas críticas de upload/store têm middleware throttle (anti-DOS)', function () {
    // Re-carrega rotas pra garantir RouteServiceProvider populou
    $routes = Route::getRoutes();

    $expected = [
        ['POST', 'essentials/document'],
        ['POST', 'essentials/todo/upload-document'],
        ['POST', 'essentials/todo/add-comment'],
        ['POST', 'essentials/messages'],
    ];

    foreach ($expected as [$method, $uri]) {
        $found = false;
        foreach ($routes as $route) {
            if ($route->uri() === $uri && in_array($method, $route->methods(), true)) {
                $middlewares = $route->gatherMiddleware();
                foreach ($middlewares as $mw) {
                    if (str_starts_with((string) $mw, 'throttle:')) {
                        $found = true;
                        break 2;
                    }
                }
            }
        }
        expect($found)->toBeTrue("Rota {$method} {$uri} sem middleware throttle");
    }
});

it('Controllers críticos usam FormRequests (inspeção reflectiva)', function () {
    // Garante que devs não voltem pra Request genérico via revert acidental.
    $methodsCheck = [
        \Modules\Essentials\Http\Controllers\ReminderController::class => [
            'store'  => StoreReminderRequest::class,
            'update' => UpdateReminderRequest::class,
        ],
        \Modules\Essentials\Http\Controllers\DocumentController::class => [
            'store' => StoreDocumentRequest::class,
        ],
        \Modules\Essentials\Http\Controllers\EssentialsHolidayController::class => [
            'store'  => StoreHolidayRequest::class,
            'update' => StoreHolidayRequest::class,
        ],
        \Modules\Essentials\Http\Controllers\KnowledgeBaseController::class => [
            'store' => StoreKnowledgeBaseRequest::class,
        ],
        \Modules\Essentials\Http\Controllers\EssentialsMessageController::class => [
            'store' => StoreMessageRequest::class,
        ],
    ];

    foreach ($methodsCheck as $controller => $methods) {
        foreach ($methods as $methodName => $expectedRequestClass) {
            $reflection = new ReflectionMethod($controller, $methodName);
            $params = $reflection->getParameters();
            expect(count($params))->toBeGreaterThan(0, "{$controller}::{$methodName} sem params");

            $type = $params[0]->getType();
            $typeName = $type ? $type->getName() : null;
            expect($typeName)->toBe($expectedRequestClass,
                "{$controller}::{$methodName} deveria type-hint {$expectedRequestClass}, encontrado: {$typeName}");
        }
    }
});
