<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Modules\Essentials\Entities\EssentialsUserShift;
use Modules\Essentials\Entities\Shift;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * PR-5 do pedido HRM (HRM-O6, achado A4) — a exclusão que não existia.
 *
 * `EssentialsLeaveTypeController::destroy()` tinha corpo VAZIO (e nem recebia $id);
 * `ShiftController::destroy($id)` tinha corpo `//`. As rotas do Route::resource
 * (`DELETE /hrm/leave-type/{id}` e `DELETE /hrm/shift/{id}`) existiam e devolviam
 * 200 sem apagar nada — o F1 do Cowork registra os dois casos como defeito nomeado
 * ("o cadastro só cresce" · "turno errado fica no cadastro para sempre").
 *
 * Nenhuma tabela dependente tem constraint de FK (só KEY de índice, medido em
 * database/schema/mysql-schema.sql), então o banco não recusa nada e a guarda de uso
 * TEM de viver na aplicação — senão a exclusão deixa licença e marcação órfãs.
 *
 * Tenant: 98 (canônico, empresa FICTÍCIA) vs 99 (adversário cross-tenant) —
 * ADR 0358, que supersede a 0101 e tira o biz=1 do papel de default. NUNCA biz=4.
 * ADR 0093 Tier 0 IRREVOGÁVEL.
 *
 * Admin#<biz> no acting user → Gate::before (AuthServiceProvider) autoriza as
 * cláusulas de permissão dos controllers, isolando o teste no comportamento de
 * exclusão e no gate de TENANT, não no de permissão.
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema UltimatePOS requer MySQL (ADR 0358).');
    }

    $tabelas = ['essentials_leave_types', 'essentials_leaves', 'essentials_shifts', 'essentials_user_shifts', 'essentials_attendances'];
    foreach ($tabelas as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Tabela {$tbl} ausente — rode migrate Modules/Essentials.");
        }
    }

    // Tenant canônico 98 + adversário 99 (este é criado idempotente pelo helper).
    $this->tenant = $this->seededTenant();
    $this->adversario = $this->seededSupportClientTenant();

    $user = User::where('business_id', $this->tenant->id)->first();
    if (! $user) {
        $this->markTestSkipped('Sem user no tenant canônico — seed mínimo não rodou.');
    }
    $this->actor = $user;

    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$this->tenant->id, 'guard_name' => 'web'],
        ['business_id' => $this->tenant->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // NÃO setar session manualmente: SetSessionData roda DEPOIS do auth e reconstrói
    // user.business_id do usuário autenticado (padrão do SalesTargetShiftCrossTenantTest).
    session()->flush();
    $this->actingAs($user);
});

function hrmTipo(int $bizId): EssentialsLeaveType
{
    // INSERT não é filtrado pelo global scope — business_id explícito permite cross-tenant.
    return EssentialsLeaveType::create([
        'business_id' => $bizId,
        'leave_type' => 'A4-tipo-'.$bizId.'-'.uniqid(),
        'max_leave_count' => 10,
        'leave_count_interval' => 'year',
    ]);
}

function hrmTurno(int $bizId): Shift
{
    return Shift::create([
        'business_id' => $bizId,
        'name' => 'A4-turno-'.$bizId.'-'.uniqid(),
        'type' => 'fixed_shift',
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
    ]);
}

function hrmExiste(string $tabela, int $id): bool
{
    return DB::table($tabela)->where('id', $id)->exists();
}

// ── Tipo de licença ─────────────────────────────────────────────────────────────

it('UC-TIPOS-07: tipo de licença SEM uso: DELETE apaga de fato (antes respondia 200 sem apagar)', function () {
    $tipo = hrmTipo($this->tenant->id);

    $resp = $this->deleteJson('/hrm/leave-type/'.$tipo->id);

    $resp->assertOk();
    expect($resp->json('success'))->toBeTrue();
    expect(hrmExiste('essentials_leave_types', $tipo->id))->toBeFalse();
});

it('UC-TIPOS-06: tipo de licença EM USO: 422 dizendo QUANTAS licenças travam, e NÃO apaga', function () {
    $tipo = hrmTipo($this->tenant->id);

    foreach ([1, 2, 3] as $i) {
        EssentialsLeave::create([
            'business_id' => $this->tenant->id,
            'user_id' => $this->actor->id,
            'essentials_leave_type_id' => $tipo->id,
            'start_date' => '2026-01-0'.$i,
            'end_date' => '2026-01-0'.$i,
            'status' => 'approved',
        ]);
    }

    $resp = $this->deleteJson('/hrm/leave-type/'.$tipo->id);

    $resp->assertStatus(422);
    expect($resp->json('success'))->toBeFalse();
    // A contagem É o conteúdo do erro — não um "algo deu errado" genérico.
    expect($resp->json('blocked_by.leaves'))->toBe(3);
    expect($resp->json('msg'))->toContain('3');
    expect(hrmExiste('essentials_leave_types', $tipo->id))->toBeTrue();
});

it('UC-TIPOS-08: tipo de licença cross-tenant: tipo do biz adversário → 404 e continua existindo', function () {
    $alheio = hrmTipo($this->adversario->id);

    $resp = $this->deleteJson('/hrm/leave-type/'.$alheio->id);

    $resp->assertNotFound();
    expect(hrmExiste('essentials_leave_types', $alheio->id))->toBeTrue();
});
// ── Turno ───────────────────────────────────────────────────────────────────────
//
// Desde 2026-09-29 o cadastro de turno do HRM CEDE ao Ponto (D4 [W]; ADR 0014, emenda
// 2026-09-29): toda rota `/hrm/shift…` é 301 para `/ponto/escalas`. A guarda de exclusão do
// ShiftController continua no código, mas o HTTP não chega mais nela. O que estes casos provam
// agora é o contrato da emenda: a rota responde 301 e o dado NÃO é apagado, com ou sem uso e
// em qualquer tenant (a emenda manda migrar/apagar o dado só em PR próprio, com dupla prova).

it('turno SEM uso: DELETE vira 301 para /ponto/escalas e NÃO apaga (D4, emenda 2026-09-29)', function () {
    $turno = hrmTurno($this->tenant->id);

    $resp = $this->deleteJson('/hrm/shift/'.$turno->id);

    $resp->assertStatus(301)->assertRedirect('/ponto/escalas');
    expect(hrmExiste('essentials_shifts', $turno->id))->toBeTrue();
});

it('turno com VÍNCULO de colaborador: DELETE vira 301 e o turno e o vínculo continuam', function () {
    $turno = hrmTurno($this->tenant->id);

    $vinculo = EssentialsUserShift::create([
        'user_id' => $this->actor->id,
        'essentials_shift_id' => $turno->id,
        'start_date' => '2026-01-01',
    ]);

    $resp = $this->deleteJson('/hrm/shift/'.$turno->id);

    $resp->assertStatus(301)->assertRedirect('/ponto/escalas');
    expect(hrmExiste('essentials_shifts', $turno->id))->toBeTrue();
    expect(hrmExiste('essentials_user_shifts', $vinculo->id))->toBeTrue();
});

it('turno cross-tenant: DELETE no turno do biz adversário vira 301 e ele continua existindo', function () {
    $alheio = hrmTurno($this->adversario->id);

    $resp = $this->deleteJson('/hrm/shift/'.$alheio->id);

    $resp->assertStatus(301)->assertRedirect('/ponto/escalas');
    expect(hrmExiste('essentials_shifts', $alheio->id))->toBeTrue();
});
