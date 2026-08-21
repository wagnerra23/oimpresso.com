<?php

declare(strict_types=1);

use App\Business;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Modules\Superadmin\Entities\Package;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

// @covers-us US-SUPER-002

/**
 * Contrato da tela `/superadmin/packages` (grade comercial) — onda SA-O4c.
 *
 * Os UCs vêm do contrato, não do código:
 *   Modules/Superadmin/Resources/js/Pages/superadmin/Pacotes/Index.casos.md
 *   memory/requisitos/Superadmin/RUNBOOK-pacotes.md
 *
 * O assunto desta suíte é a convenção `0 = ilimitado` e a fronteira com a vitrine pública
 * `/pricing`. As duas são invisíveis no código e caras quando erram: a primeira faz a tela
 * dizer o oposto do dado; a segunda vaza grade privada para o site.
 *
 * ⚠️ SKIP em SQLite: a tela precisa do schema UltimatePOS real. Em sqlite estes casos PULAM e
 * o arquivo sai exit 0 sem provar nada — leia *assertions*, não "0 failed" (LC-13).
 *
 * @see Modules/Superadmin/Http/Controllers/PackagesController::index()
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md §exceções Superadmin
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: /superadmin/packages requer schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('packages') || ! Schema::hasTable('business')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode migrations primeiro.');
    }

    // O gate de ROTA é o middleware `Superadmin`, que compara o USERNAME com
    // `config('constants.administrator_usernames')` — não é Spatie nem Bouncer.
    config(['constants.administrator_usernames' => 'pac_superadmin_test']);

    pacFixtures();
});

/** Tenant do teste. NUNCA biz=4 (ROTA LIVRE, produção) — ADR 0358. */
const BIZ_PAC = 98;

const ROTA_PAC = '/superadmin/packages';

function pacSuperadmin(): User
{
    Business::firstOrCreate(['id' => BIZ_PAC], ['name' => 'Tenant fictício pacotes', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'pac_superadmin_test'],
        [
            'email' => 'pac_superadmin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_PAC,
            'first_name' => 'Pac',
            'last_name' => 'Superadmin',
        ]
    );

    $permission = Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);

    if (! $user->hasPermissionTo('superadmin')) {
        $user->givePermissionTo($permission);
    }

    return $user;
}

function pacAdminDeNegocio(): User
{
    Business::firstOrCreate(['id' => BIZ_PAC], ['name' => 'Tenant fictício pacotes', 'currency_id' => 1]);

    $user = User::firstOrCreate(
        ['username' => 'pac_admin_test'],
        [
            'email' => 'pac_admin@test.local',
            'password' => bcrypt('secret'),
            'business_id' => BIZ_PAC,
            'first_name' => 'Admin',
            'last_name' => 'Negocio',
        ]
    );

    $user->syncRoles([]);
    $user->syncPermissions([]);

    return $user;
}

/**
 * DQE do F1 §2: um pacote de cada tipo que a tela precisa distinguir.
 *
 * Os preços são números sintéticos e neutros. Nenhum literal monetário entra aqui — Tier 0.
 */
function pacFixtures(): void
{
    $casos = [
        // nome, is_active, is_private, location_count, price
        ['Pacote fictício ilimitado SA-O4c', 1, 0, 0, 100],
        ['Pacote fictício inativo SA-O4c', 0, 0, 1, 100],
        ['Pacote fictício privado SA-O4c', 1, 1, 2, 0],
    ];

    foreach ($casos as [$nome, $ativo, $privado, $locais, $preco]) {
        if (DB::table('packages')->where('name', $nome)->exists()) {
            continue;
        }

        DB::table('packages')->insert([
            'name' => $nome,
            'description' => 'Só para o contrato da tela de Pacotes.',
            'location_count' => $locais,
            'user_count' => 3,
            'product_count' => 0,
            'invoice_count' => 0,
            'interval' => 'months',
            'interval_count' => 1,
            'trial_days' => 14,
            'price' => $preco,
            'custom_permissions' => json_encode([]),
            'created_by' => 1,
            'sort_order' => 900,
            'is_active' => $ativo,
            'is_private' => $privado,
            'is_one_time' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

/** Pede a prop deferred — sem isso o payload nem é calculado. */
function pacPayload(): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $resposta = test()->actingAs(pacSuperadmin())->get(ROTA_PAC, [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) $versao,
        'X-Inertia-Partial-Data' => 'pacotes',
        'X-Inertia-Partial-Component' => 'superadmin/Pacotes/Index',
    ]);

    $resposta->assertOk();

    return (array) $resposta->json('props.pacotes');
}

/** Acha um pacote do payload pelo nome — os UCs falam de pacote, não de índice de array. */
function pacPorNome(array $payload, string $nome): ?array
{
    foreach ($payload as $p) {
        if (($p['nome'] ?? null) === $nome) {
            return $p;
        }
    }

    return null;
}

// ── UC-SAPAC-01 · responde Inertia, não Blade ───────────────────────────────

it('UC-SAPAC-01 · a grade responde Inertia com o componente da tela nova', function () {
    $this->actingAs(pacSuperadmin())
        ->get(ROTA_PAC)
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('superadmin/Pacotes/Index'));
});

// ── UC-SAPAC-02 · admin barrado ENQUANTO superadmin passa ───────────────────

it('UC-SAPAC-02 · admin de negócio é barrado enquanto o superadmin passa', function () {
    $barrado = $this->actingAs(pacAdminDeNegocio())->get(ROTA_PAC);
    expect($barrado->getStatusCode())->toBeIn([302, 403]);

    $this->actingAs(pacSuperadmin())->get(ROTA_PAC)->assertOk();
});

// ── UC-SAPAC-03 · `0` chega como NÚMERO, não como texto ─────────────────────

it('UC-SAPAC-03 · limite 0 chega cru no payload, sem tradução no backend', function () {
    $pacote = pacPorNome(pacPayload(), 'Pacote fictício ilimitado SA-O4c');

    expect($pacote)->not->toBeNull()
        ->and($pacote['locais'])->toBe(0)
        ->and($pacote['locais'])->toBeInt();

    // O que este caso protege: mandar "locais ilimitados" pronto do backend tiraria da tela a
    // decisão que só ela pode tomar (ela precisa do NÚMERO pra escolher entre "ilimitado" e o
    // plural correto). É a "simplificação" que faria a tela desenhar `0 locais` — o oposto do
    // que a coluna significa.
    foreach (['locais', 'usuarios', 'produtos', 'faturas'] as $campo) {
        expect($pacote[$campo])->toBeInt();
    }
});

// ── UC-SAPAC-04 · catálogo completo — inativo e privado inclusos ────────────

it('UC-SAPAC-04 · a grade mostra pacote inativo e pacote privado', function () {
    $payload = pacPayload();

    $inativo = pacPorNome($payload, 'Pacote fictício inativo SA-O4c');
    $privado = pacPorNome($payload, 'Pacote fictício privado SA-O4c');

    expect($inativo)->not->toBeNull()
        ->and($inativo['ativo'])->toBeFalse()
        ->and($privado)->not->toBeNull()
        ->and($privado['privado'])->toBeTrue();
});

// ── UC-SAPAC-05 · esta tela ≠ vitrine pública ──────────────────────────────

it('UC-SAPAC-05 · pacote privado aparece aqui e NÃO na consulta do /pricing', function () {
    $aqui = collect(pacPayload())->pluck('nome');

    // A MESMA consulta que o PricingController usa (`Package::listPackages(true)`).
    $vitrine = Package::listPackages(true)->pluck('name');

    expect($aqui)->toContain('Pacote fictício privado SA-O4c')
        ->and($vitrine)->not->toContain('Pacote fictício privado SA-O4c');

    // Sem este caso, "unificar as duas consultas pra não repetir código" vazaria grade privada
    // para o site público — quebra a R7 do F1.
});

// ── UC-SAPAC-06 · assinantes é contagem real, e histórica ──────────────────

it('UC-SAPAC-06 · assinatura vencida ainda conta como assinante do pacote', function () {
    if (! Schema::hasTable('subscriptions')) {
        $this->markTestSkipped('sem tabela subscriptions.');
    }

    $pacoteId = DB::table('packages')->where('name', 'Pacote fictício inativo SA-O4c')->value('id');

    DB::table('subscriptions')->updateOrInsert(
        ['payment_transaction_id' => 'sa-o4c-historica'],
        [
            'business_id' => BIZ_PAC,
            'package_id' => $pacoteId,
            'start_date' => now()->subDays(90)->toDateString(),
            'end_date' => now()->subDays(30)->toDateString(),
            'package_price' => 100,
            'package_details' => json_encode(['interval' => 'months', 'interval_count' => 1]),
            'created_id' => 1,
            'status' => 'expired',
            'created_at' => now(),
            'updated_at' => now(),
        ]
    );

    $pacote = pacPorNome(pacPayload(), 'Pacote fictício inativo SA-O4c');

    // A contagem existe pra responder "dá pra excluir este pacote?". Contar só vigentes diria
    // que sim quando há contrato histórico preso a ele.
    expect($pacote['assinantes'])->toBeGreaterThanOrEqual(1);
});

// ── UC-SAPAC-07 · módulos liberados chegam com rótulo, e só os ligados ─────

it('UC-SAPAC-07 · só a permissão LIGADA vira módulo no card', function () {
    // Uma ligada e uma desligada no MESMO pacote: sem a desligada, o caso ficaria verde numa
    // implementação que devolvesse todas as chaves.
    DB::table('packages')
        ->where('name', 'Pacote fictício privado SA-O4c')
        ->update(['custom_permissions' => json_encode([
            'fictício_ligado_module' => 1,
            'fictício_desligado_module' => 0,
        ])]);

    $pacote = pacPorNome(pacPayload(), 'Pacote fictício privado SA-O4c');

    expect($pacote['modulos'])->toContain('fictício_ligado_module')
        ->and($pacote['modulos'])->not->toContain('fictício_desligado_module');

    // O `toContain` acima usa a CHAVE porque nenhum módulo real declara esta permissão
    // fictícia — e o fallback "chave sem rótulo conhecido cai no próprio nome" é deliberado:
    // sumir da tela seria pior que aparecer feio.
});

// ── UC-SAPAC-08 · a grade não escreve ──────────────────────────────────────

it('UC-SAPAC-08 · abrir a grade não muda nenhuma linha de packages', function () {
    $antes = DB::table('packages')
        ->orderBy('id')
        ->get(['id', 'name', 'price', 'is_active', 'sort_order'])
        ->map(fn ($p) => (array) $p)
        ->all();

    pacPayload();

    $depois = DB::table('packages')
        ->orderBy('id')
        ->get(['id', 'name', 'price', 'is_active', 'sort_order'])
        ->map(fn ($p) => (array) $p)
        ->all();

    expect($depois)->toBe($antes);
});
