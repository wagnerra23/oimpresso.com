<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Restaurar arquivo excluído e simular a retenção: SÓ superadmin.
 *
 * Decisão [W] 2026-10-01, textual: "Superadmin" (`_DECISOES-W-2026-10-01b.md`, PR #8394).
 * Até aqui as duas ações pediam `arquivos.restore` / `arquivos.governanca`, que estavam no
 * catálogo delegável — e o `Gate::before` dá ao `Admin#{biz}` TODA permissão fora de
 * superadmin/backup/manage_modules/plataforma. Logo o dono de qualquer negócio passava.
 *
 * Prova pelo caminho HTTP real (middleware `can:` resolvido pelo router), não por leitura
 * do arquivo de rotas. Sem o stack de sessão do UltimatePOS (`authh`, `SetSessionData`,
 * `AdminSidebarMenu`, `language`, `timezone`): a sessão do business vai por `withSession`.
 *
 * CONTROLE: o mesmo admin PASSA em `arquivos.access` — sem isso o 403 poderia vir de o
 * `Admin#98` não estar sendo reconhecido, e o teste mediria a coisa errada.
 *
 * TENANT: 98 canônico (ADR 0358). NUNCA biz=4, NUNCA biz=1.
 * NÃO RODADO LOCAL: Pest é CT 100/CI only (proibicoes.md §Ambiente).
 */
const ASO_BIZ = Tests\TestCase::SEEDED_TENANT_ID;

if (! function_exists('asoSemStackDeSessao')) {
    function asoSemStackDeSessao(): array
    {
        return [
            App\Http\Middleware\IsInstalled::class,
            App\Http\Middleware\SetSessionData::class,
            App\Http\Middleware\AdminSidebarMenu::class,
            App\Http\Middleware\Language::class,
            App\Http\Middleware\Timezone::class,
        ];
    }
}

beforeEach(function () {
    if (! Schema::hasTable('roles') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('Sem tabelas Spatie — lane sem schema MySQL.');
    }

    $user = User::where('business_id', ASO_BIZ)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user em business_id=98 (tenant canônico ADR 0358).');
    }

    $attrs = ['name' => 'Admin#'.ASO_BIZ, 'guard_name' => 'web'];
    if (Schema::hasColumn('roles', 'business_id')) {
        $attrs['business_id'] = ASO_BIZ;
    }
    $user->assignRole(Role::firstOrCreate($attrs));

    // Ninguém é superadmin por padrão neste teste.
    config(['constants.administrator_usernames' => 'ninguem-aso-'.uniqid()]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->user = $user->fresh();
    $this->sessao = ['user' => ['business_id' => ASO_BIZ, 'id' => $user->id]];
});

if (! function_exists('asoVirarSuperadmin')) {
    function asoVirarSuperadmin(User $user): void
    {
        config(['constants.administrator_usernames' => $user->username]);
    }
}

if (! function_exists('asoArquivoExcluido')) {
    function asoArquivoExcluido(int $businessId): int
    {
        DB::table('arquivos')->insert([
            'business_id'     => $businessId,
            'disk'            => 'fixture-superadmin',
            'storage_path'    => "biz-{$businessId}/fixture/superadmin-only.pdf",
            'original_name'   => 'fixture-superadmin-only.pdf',
            'mime_type'       => 'application/pdf',
            'size_bytes'      => 1024,
            'md5'             => str_repeat('d', 32),
            'bucket'          => 'active',
            'sub_destination' => '__fixture-superadmin',
            'created_at'      => now(),
            'updated_at'      => now(),
            'deleted_at'      => now()->subDay(),
        ]);

        return (int) DB::table('arquivos')->where('disk', 'fixture-superadmin')->where('business_id', $businessId)->max('id');
    }
}

it('CONTROLE · o Admin#98 segue com arquivos.access (bypass do Gate::before vivo)', function () {
    expect($this->user->can('arquivos.access'))->toBeTrue();
    expect($this->user->can('superadmin'))->toBeFalse();
})->group('arquivos', 'multi-tenant');

it('admin de negocio (Admin#98) toma 403 ao simular a retencao', function () {
    Bus::fake();

    $this->withoutMiddleware(asoSemStackDeSessao())
        ->actingAs($this->user)
        ->withSession($this->sessao)
        ->post('/arquivos/retencao/simular', ['retention_days' => 365])
        ->assertForbidden();

    Bus::assertNothingDispatched();
})->group('arquivos', 'multi-tenant');

it('admin de negocio (Admin#98) toma 403 ao restaurar — mesmo com arquivos.restore concedida no papel', function () {
    if (! Schema::hasTable('arquivos')) {
        $this->markTestSkipped('tabela arquivos ausente — roda na lane MySQL.');
    }

    // Concessão "herdada" do intervalo em que a permissão era delegável: não pode abrir nada.
    $this->user->givePermissionTo(Permission::findOrCreate('arquivos.restore', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $id = asoArquivoExcluido(ASO_BIZ);

    $this->withoutMiddleware(asoSemStackDeSessao())
        ->actingAs($this->user->fresh())
        ->withSession($this->sessao)
        ->post("/arquivos/{$id}/restaurar", ['reason' => 'tentativa do dono'])
        ->assertForbidden();

    expect(DB::table('arquivos')->where('id', $id)->value('deleted_at'))->not->toBeNull();
})->group('arquivos', 'multi-tenant');

it('superadmin passa: simula (job despachado com o business da sessao 98)', function () {
    Bus::fake();
    asoVirarSuperadmin($this->user);

    $resp = $this->withoutMiddleware(asoSemStackDeSessao())
        ->actingAs($this->user)
        ->withSession($this->sessao)
        ->post('/arquivos/retencao/simular', ['retention_days' => 365]);

    expect($resp->status())->not->toBe(403);
    Bus::assertDispatchedAfterResponse(
        Modules\Arquivos\Jobs\SimularRetencaoJob::class,
        fn ($job) => $job->businessId === ASO_BIZ && $job->dryRun === true
    );
})->group('arquivos', 'multi-tenant');

it('superadmin passa: restaura arquivo do proprio business (98)', function () {
    if (! Schema::hasTable('arquivos') || ! Schema::hasTable('arquivos_audit_log')) {
        $this->markTestSkipped('tabelas do Arquivos ausentes — roda na lane MySQL.');
    }

    asoVirarSuperadmin($this->user);
    $id = asoArquivoExcluido(ASO_BIZ);

    $resp = $this->withoutMiddleware(asoSemStackDeSessao())
        ->actingAs($this->user)
        ->withSession($this->sessao)
        ->post("/arquivos/{$id}/restaurar", ['reason' => 'restauro pelo superadmin']);

    expect($resp->status())->not->toBe(403);
    expect(DB::table('arquivos')->where('id', $id)->value('deleted_at'))->toBeNull();
})->group('arquivos', 'multi-tenant');

it('arquivos:revogar-permissoes-superadmin · --dry-run lista e NAO escreve; sem flag remove; 2a vez e no-op', function () {
    $perm = Permission::findOrCreate('arquivos.governanca', 'web');
    $papel = Role::where('name', 'Admin#'.ASO_BIZ)->first();
    $papel->givePermissionTo($perm);
    $this->user->givePermissionTo($perm);

    $this->artisan('arquivos:revogar-permissoes-superadmin', ['--dry-run' => true])
        ->expectsOutputToContain('Seriam removidos: 1 vínculo(s) de papel · 1 vínculo(s) direto(s) de usuário')
        ->assertExitCode(0);
    expect(DB::table('role_has_permissions')->where('permission_id', $perm->id)->count())->toBe(1);

    $this->artisan('arquivos:revogar-permissoes-superadmin')->assertExitCode(0);
    expect(DB::table('role_has_permissions')->where('permission_id', $perm->id)->count())->toBe(0);
    expect(DB::table('model_has_permissions')->where('permission_id', $perm->id)->count())->toBe(0);
    expect(DB::table('permissions')->whereIn('name', ['arquivos.restore', 'arquivos.governanca'])->count())->toBe(0);

    $this->artisan('arquivos:revogar-permissoes-superadmin')
        ->expectsOutputToContain('0 vínculo(s) de papel · 0 vínculo(s) direto(s) de usuário · 0 permissão(ões)')
        ->assertExitCode(0);
})->group('arquivos');
