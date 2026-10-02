<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * API de Notificações do app das lojas (tela 16) — GET /api/app/notificacoes, só leitura,
 * e o `nao_lidas` do Início.
 *
 * Contrato: memory/requisitos/AppMobile/API-CONTRATO-v1.md §6.1. NÃO derivado do controller.
 *
 * Tier 0 (ADR 0093): a notificação é do usuário; a de OUTRO usuário nunca aparece. Controle
 * positivo em par: as do próprio usuário aparecem, senão o "não aparece" seria verde por vácuo.
 *
 * Tenant 98 (ADR 0358). Usuários criados aqui.
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    // O guard `api` do Passport precisa das chaves mesmo com Passport::actingAs; a lane não as gera.
    if (! file_exists(storage_path('oauth-public.key'))) {
        \Illuminate\Support\Facades\Artisan::call('passport:keys', ['--force' => true]);
    }
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('Requer schema MySQL UltimatePOS (ADR 0358).');
    }
    if (! Schema::hasTable('notifications')) {
        $this->markTestSkipped('Schema ausente (notifications).');
    }

    $this->tenant = $this->seededTenant();
});

function appNotUsuario(int $businessId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'APP Notif', 'username' => 'app_notif_' . uniqid(), 'password' => 'x',
        'business_id' => $businessId, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

function appNotCriar(User $u, string $tipo, array $data, bool $lida, string $quando): string
{
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id, 'type' => $tipo, 'notifiable_type' => $u->getMorphClass(), 'notifiable_id' => $u->id,
        'data' => json_encode($data), 'read_at' => $lida ? now() : null,
        'created_at' => $quando, 'updated_at' => $quando,
    ]);

    return $id;
}

it('lista só as notificações do usuário, mais nova primeiro, com texto sem HTML e nao_lidas', function () {
    $eu = appNotUsuario((int) $this->tenant->id);
    $outro = appNotUsuario((int) $this->tenant->id);
    $velha = appNotCriar($eu, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-1'], true, now()->subDays(2)->toDateTimeString());
    $nova = appNotCriar($eu, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-2'], false, now()->subHour()->toDateTimeString());
    $alheia = appNotCriar($outro, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-3'], false, now()->toDateTimeString());
    Passport::actingAs($eu, [], 'api');

    $r = $this->getJson('/api/app/notificacoes')->assertOk();

    expect(array_column($r->json('itens'), 'id'))->toBe([$nova, $velha]);
    expect($r->json('itens.0.origem'))->toBe('FIN');
    expect($r->json('itens.0.lida'))->toBeFalse();
    expect($r->json('itens.1.lida'))->toBeTrue();
    expect($r->json('itens.0.titulo'))->toContain('EXP-APP-2');
    expect($r->json('itens.0.titulo'))->not->toContain('<');
    expect($r->json('itens.0.destino'))->toBe(['tipo' => null, 'id' => null]);
    expect($r->json('nao_lidas'))->toBe(1);
    expect($r->json('tem_mais'))->toBeFalse();
    expect(array_column($r->json('itens'), 'id'))->not->toContain($alheia);
});

it('tarefa nova leva à tarefa no app (destino todo:<id>) com origem TAR', function () {
    $eu = appNotUsuario((int) $this->tenant->id);
    appNotCriar($eu, 'Modules\Essentials\Notifications\NewTaskNotification', ['assigned_by' => $eu->id, 'task_id' => 'APP-T1', 'id' => 4321], false, now()->toDateTimeString());
    Passport::actingAs($eu, [], 'api');

    $r = $this->getJson('/api/app/notificacoes')->assertOk();

    expect($r->json('itens.0.origem'))->toBe('TAR');
    expect($r->json('itens.0.destino'))->toBe(['tipo' => 'tarefa', 'id' => 'todo:4321']);
});

it('o Início traz nao_lidas do usuário do token', function () {
    $eu = appNotUsuario((int) $this->tenant->id);
    appNotCriar($eu, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-4'], false, now()->toDateTimeString());
    appNotCriar($eu, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-5'], false, now()->toDateTimeString());
    appNotCriar($eu, 'App\Notifications\RecurringExpenseNotification', ['ref_no' => 'EXP-APP-6'], true, now()->toDateTimeString());
    Passport::actingAs($eu, [], 'api');

    expect($this->getJson('/api/app/inicio')->assertOk()->json('nao_lidas'))->toBe(2);
});
