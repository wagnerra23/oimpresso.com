<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Essentials\Entities\EssentialsLeave;
use Modules\Essentials\Entities\EssentialsLeaveType;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * HRM · Licenças — prova dos achados A2 (validação) e A3 (limite do tipo).
 *
 * Aterrissagem do esqueleto de `prototipo-ui/design-docs/cowork-inbox/hrm/HrmLicencaTest.php`
 * (PR-1 do pedido), reescrito no padrão que de fato RODA neste módulo:
 *
 *  - Pest, como `MultiTenantLeaveTest` — o esqueleto era PHPUnit com `RefreshDatabase`,
 *    que não é usado aqui (100+ migrations UltimatePOS + triggers MySQL).
 *  - Sem factory: o esqueleto chamava `EssentialsLeaveType::factory()`, que NAO EXISTE —
 *    `Modules/Essentials/Database/factories/` só tem `.gitkeep` e as entities não usam
 *    `HasFactory`. Criação direta via `create()`, como os testes vizinhos.
 *  - Tenant 98 (`seededTenant()`, ADR 0358), não `business_id => 999999` do esqueleto.
 *    O 99 é o adversário cross-tenant; biz=4 é proibido; biz=1 é a WR2, empresa real.
 *  - Datas no formato do NEGOCIO: o controller grava via `uf_date()`, que parseia com
 *    `session('business.date_format')` — default do schema: `m/d/Y`. O esqueleto cravava
 *    '10/09/2026' (d/m/Y): nesse default vira outra data, e '21/09/2026' nem parseia.
 *    Aqui a data é formatada a partir do business, então o teste vale em qualquer formato.
 *
 * @see prototipo-ui/design-docs/cowork-inbox/hrm/Licencas.casos.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */

const HRM_MARCADOR = 'HRM-LIC-TEST';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema essentials_leaves requer MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('essentials_leaves') || ! Schema::hasTable('essentials_leave_types')) {
        $this->markTestSkipped('Tabelas do Essentials ausentes — rode as migrations do módulo.');
    }
});

// Sem cleanup manual: `DatabaseTransactions` reverte tudo ao fim de cada caso —
// mesmo isolamento do `SalesTargetShiftCrossTenantTest`, o vizinho desta lane.
// Importa no CT 100, onde a base PERSISTE entre execuções.

/**
 * Admin do tenant canônico de teste (98), pronto para bater nas rotas do HRM.
 *
 * Usa a role `Admin#{biz}` em vez de permissões avulsas: o `Gate::before` do
 * AuthServiceProvider autoriza por ela todas as cláusulas de permissão dos
 * controllers (`can`/`is_admin`), inclusive o `superadmin` que a checagem de
 * assinatura consulta. É o mesmo caminho do `SalesTargetShiftCrossTenantTest`,
 * o vizinho desta lane — assim o teste isola o gate de TENANT, não o de permissão.
 *
 * `session()->flush()` antes do `actingAs` é obrigatório: as rotas do HRM rodam
 * `SetSessionData` DEPOIS do auth, e ele só reconstrói `user.business_id` quando o
 * bloco `user` está ausente ou meio-populado. Setar a sessão à mão faz o middleware
 * pular a reconstrução e o business_id chega nulo no controller.
 */
function hrmAdmin($teste): User
{
    $business = $teste->seededTenant();

    $user = User::where('business_id', $business->id)->first();
    if ($user === null) {
        $teste->markTestSkipped("Sem usuário no tenant {$business->id} — rode o seed mínimo.");
    }

    $role = Role::firstOrCreate(
        ['name' => 'Admin#'.$business->id, 'guard_name' => 'web'],
        ['business_id' => $business->id]
    );
    if (! $user->hasRole($role->name)) {
        $user->assignRole($role);
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    session()->flush();
    $teste->actingAs($user);

    return $user;
}

/**
 * Formata `Y-m-d` no formato de data do negócio — o mesmo que `uf_date()` espera.
 */
function hrmDataDoNegocio($teste, string $iso): string
{
    $formato = $teste->seededTenant()->date_format ?: 'm/d/Y';

    return \Carbon\Carbon::parse($iso)->format($formato);
}

function hrmCriarTipo(int $businessId, ?int $limite = null, ?string $intervalo = null): EssentialsLeaveType
{
    return EssentialsLeaveType::create([
        'business_id'          => $businessId,
        'leave_type'           => HRM_MARCADOR.'-'.uniqid(),
        'max_leave_count'      => $limite,
        'leave_count_interval' => $intervalo,
    ]);
}

function hrmCriarLicenca(int $businessId, int $userId, int $tipoId, string $inicio, string $fim, string $status): EssentialsLeave
{
    return EssentialsLeave::create([
        'business_id'              => $businessId,
        'user_id'                  => $userId,
        'essentials_leave_type_id' => $tipoId,
        'ref_no'                   => HRM_MARCADOR.'-'.uniqid(),
        'start_date'               => $inicio,
        'end_date'                 => $fim,
        'status'                   => $status,
        'reason'                   => HRM_MARCADOR.' fixture',
    ]);
}

function hrmContarLicencas(): int
{
    return (int) DB::table('essentials_leaves')->where('reason', 'like', HRM_MARCADOR.'%')->count();
}

// ------------------------------------------------------------------
// CONTROLE POSITIVO — o caminho legítimo continua passando.
// Sem este caso, um gate que recusasse TUDO ficaria verde nos demais.
// ------------------------------------------------------------------

it('aceita licença válida dentro do limite do tipo', function () {
    $admin = hrmAdmin($this);
    $tipo = hrmCriarTipo((int) $admin->business_id, 30, 'year');

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmDataDoNegocio($this, '2099-03-02'),
        'end_date'   => hrmDataDoNegocio($this, '2099-03-06'), // 5 dias, cabe em 30
        'reason'     => HRM_MARCADOR.' caminho feliz',
    ]);

    expect($r->status())->toBe(200);
    expect(hrmContarLicencas())->toBe(1);
});

// ------------------------------------------------------------------
// UC-HRM-02 · achado A2 — fim antes do início
// ------------------------------------------------------------------

it('UC-HRM-02 recusa licença com fim antes do início', function () {
    $admin = hrmAdmin($this);
    $tipo = hrmCriarTipo((int) $admin->business_id);

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmDataDoNegocio($this, '2099-09-10'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-01'),
        'reason'     => HRM_MARCADOR.' periodo invertido',
    ]);

    $r->assertStatus(422);
    $r->assertJsonPath('errors.end_date.0', 'O fim não pode ser antes do início.');
    expect(hrmContarLicencas())->toBe(0);
});

// ------------------------------------------------------------------
// UC-HRM-15 · achado A2 — motivo vazio e tipo ausente
// ------------------------------------------------------------------

it('UC-HRM-15 recusa licença sem motivo e sem tipo', function () {
    hrmAdmin($this);

    $r = $this->postJson('/hrm/leave', [
        'start_date' => hrmDataDoNegocio($this, '2099-09-01'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-02'),
        'reason'     => '',
    ]);

    $r->assertStatus(422);
    $r->assertJsonValidationErrors(['essentials_leave_type_id', 'reason']);
    expect(hrmContarLicencas())->toBe(0);
});

// ------------------------------------------------------------------
// UC-HRM-05 · Tier 0 (ADR 0093) — tipo de outro negócio
// ------------------------------------------------------------------

it('UC-HRM-05 recusa tipo de licença de outro negócio', function () {
    $admin = hrmAdmin($this);
    $tipoAlheio = hrmCriarTipo(99); // tenant adversário

    // Controle positivo: o tipo EXISTE de fato — a recusa é por ser de outro
    // negócio, não por id inexistente (que daria o mesmo 422 por outro motivo).
    expect(DB::table('essentials_leave_types')->where('id', $tipoAlheio->id)->exists())->toBeTrue();
    expect((int) $admin->business_id)->not->toBe(99);

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipoAlheio->id,
        'start_date' => hrmDataDoNegocio($this, '2099-09-01'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-02'),
        'reason'     => HRM_MARCADOR.' tipo alheio',
    ]);

    $r->assertStatus(422);
    $r->assertJsonValidationErrors(['essentials_leave_type_id']);
    expect(hrmContarLicencas())->toBe(0);
});

// ------------------------------------------------------------------
// Tier 0 (ADR 0093) — colaborador de outro negócio em employees[]
// O global scope filtra SELECT, não impede INSERT: sem o gate, criava
// licença no user de outro tenant.
// ------------------------------------------------------------------

it('recusa employees[] com colaborador de outro negócio', function () {
    $admin = hrmAdmin($this);
    $tipo = hrmCriarTipo((int) $admin->business_id);

    $alheio = DB::table('users')
        ->where('business_id', '!=', $admin->business_id)
        ->whereNotNull('business_id')
        ->first();
    if ($alheio === null) {
        $this->markTestSkipped('Sem usuário de outro negócio no seed para exercitar o cross-tenant.');
    }

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmDataDoNegocio($this, '2099-09-01'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-02'),
        'reason'     => HRM_MARCADOR.' colaborador alheio',
        'employees'  => [$alheio->id],
    ]);

    $r->assertStatus(422);
    $r->assertJsonValidationErrors(['employees.0']);
    expect(hrmContarLicencas())->toBe(0);
});

// ------------------------------------------------------------------
// UC-HRM-03 · achado A3 — pedido que estoura o limite do tipo
// 30 dias/ano, 22 já aprovados, pede 15 -> 37 > 30, saldo restante 8
// ------------------------------------------------------------------

it('UC-HRM-03 recusa pedido que estoura o limite do tipo e diz o saldo', function () {
    $admin = hrmAdmin($this);
    $bid = (int) $admin->business_id;
    $tipo = hrmCriarTipo($bid, 30, 'year');

    hrmCriarLicenca($bid, (int) $admin->id, (int) $tipo->id, '2099-01-06', '2099-01-27', 'approved'); // 22 dias
    expect(hrmContarLicencas())->toBe(1);

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmDataDoNegocio($this, '2099-09-07'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-21'), // 15 dias
        'reason'     => HRM_MARCADOR.' estoura limite',
    ]);

    $r->assertStatus(422);
    expect($r->json('msg'))->toContain('8 dia(s)');  // saldo restante dito na mensagem
    expect($r->json('msg'))->toContain('15 dia(s)'); // e quantos foram pedidos
    expect(hrmContarLicencas())->toBe(1);            // nada novo gravado
});

// ------------------------------------------------------------------
// UC-HRM-19 — limite 0 significa SEM limite
// ------------------------------------------------------------------

it('UC-HRM-19 trata limite 0 como sem limite', function () {
    $admin = hrmAdmin($this);
    $bid = (int) $admin->business_id;
    $tipo = hrmCriarTipo($bid, 0, 'year');

    hrmCriarLicenca($bid, (int) $admin->id, (int) $tipo->id, '2099-01-06', '2099-03-27', 'approved');

    $r = $this->postJson('/hrm/leave', [
        'essentials_leave_type_id' => $tipo->id,
        'start_date' => hrmDataDoNegocio($this, '2099-09-07'),
        'end_date'   => hrmDataDoNegocio($this, '2099-09-21'),
        'reason'     => HRM_MARCADOR.' sem limite',
    ]);

    expect($r->status())->toBe(200);
    expect(hrmContarLicencas())->toBe(2);
});

// ------------------------------------------------------------------
// UC-HRM-09 · achado A3 — aprovar também estoura
// ------------------------------------------------------------------

it('UC-HRM-09 recusa aprovação que estoura o limite do tipo', function () {
    $admin = hrmAdmin($this);
    $bid = (int) $admin->business_id;
    $tipo = hrmCriarTipo($bid, 30, 'year');

    hrmCriarLicenca($bid, (int) $admin->id, (int) $tipo->id, '2099-01-06', '2099-01-27', 'approved'); // 22
    $pendente = hrmCriarLicenca($bid, (int) $admin->id, (int) $tipo->id, '2099-09-07', '2099-09-21', 'pending'); // 15

    $r = $this->postJson('/hrm/change-status', [
        'leave_id'    => $pendente->id,
        'status'      => 'approved',
        'status_note' => HRM_MARCADOR.' tentativa de aprovar',
    ]);

    $r->assertStatus(422);
    expect($r->json('msg'))->toContain('8 dia(s)');

    // a licença continua pendente — a recusa não gravou nada
    expect(DB::table('essentials_leaves')->where('id', $pendente->id)->value('status'))->toBe('pending');
});

// ------------------------------------------------------------------
// Contrato preservado — aprovar dentro do limite segue funcionando.
// O /hrm/change-status grava status + status_note e notifica o colaborador;
// o PR-3 só acrescentou a guarda de saldo ANTES disso.
// ------------------------------------------------------------------

it('aprova normalmente quando cabe no limite, gravando status e observação', function () {
    $admin = hrmAdmin($this);
    $bid = (int) $admin->business_id;
    $tipo = hrmCriarTipo($bid, 30, 'year');

    $pendente = hrmCriarLicenca($bid, (int) $admin->id, (int) $tipo->id, '2099-09-07', '2099-09-11', 'pending'); // 5 dias

    $r = $this->postJson('/hrm/change-status', [
        'leave_id'    => $pendente->id,
        'status'      => 'approved',
        'status_note' => HRM_MARCADOR.' aprovado ok',
    ]);

    expect($r->status())->toBe(200);

    $linha = DB::table('essentials_leaves')->where('id', $pendente->id)->first();
    expect($linha->status)->toBe('approved');
    expect($linha->status_note)->toBe(HRM_MARCADOR.' aprovado ok');
});
