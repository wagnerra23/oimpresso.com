<?php

declare(strict_types=1);
// Cobre UC-CONTA-01, UC-CONTA-02, UC-CONTA-03, UC-CONTA-04 (Configuracoes/Contas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/06, F3 — /account/account em Inertia atrás da flag useV2ConfiguracoesContas.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * O SQL do saldo é o mesmo da DataTable (AccountController::consultaContas): ContasBaselineTest trava a Blade.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('accounts') || ! Schema::hasTable('account_transactions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['account.access', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    // O saldo_texto usa num_f, que lê session('currency') — o SetSessionData do login real preenche.
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
});

function contasContratoConta(int $businessId, int $criadoPor, string $nome, int $encerrada = 0): int
{
    return DB::table('accounts')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'account_number' => 'N'.uniqid(),
        'created_by' => $criadoPor, 'is_closed' => $encerrada, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function contasContratoLancamento(int $contaId, int $criadoPor, string $tipo, float $valor, bool $apagado = false): void
{
    DB::table('account_transactions')->insert([
        'account_id' => $contaId, 'type' => $tipo, 'amount' => $valor, 'operation_date' => now(),
        'created_by' => $criadoPor, 'deleted_at' => $apagado ? now() : null, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Prop deferida `contas`, por id. */
function contasContratoLista($teste): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Contas/Index', 'X-Inertia-Partial-Data' => 'contas',
    ])->get('/account/account');
    $r->assertOk();

    return collect($r->json('props.contas') ?? [])->keyBy('id');
}

test('UC-CONTA-01 com a flag ligada, GET /account/account renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesContas']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/account/account');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Contas/Index');
});

test('UC-CONTA-02 com a flag desligada, ou com ?legacy=1, GET /account/account segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);
    $this->get('/account/account')->assertOk()->assertViewIs('account.index');

    config(['feature-flags.forced_on' => 'useV2ConfiguracoesContas']);
    $this->get('/account/account?legacy=1')->assertOk()->assertViewIs('account.index');
});

test('UC-CONTA-03 Tier 0 e valor — minhas contas, com o mesmo saldo e o mesmo texto da Blade', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesContas']);
    $outro = $this->seededSupportClientTenant();
    $minha = contasContratoConta($this->business->id, $this->user->id, 'Banco '.uniqid());
    contasContratoLancamento($minha, $this->user->id, 'credit', 1000.50);
    contasContratoLancamento($minha, $this->user->id, 'credit', 99.75);
    contasContratoLancamento($minha, $this->user->id, 'debit', 200.25);
    contasContratoLancamento($minha, $this->user->id, 'credit', 999.00, apagado: true);
    $encerrada = contasContratoConta($this->business->id, $this->user->id, 'Encerrada '.uniqid(), 1);
    $alheia = contasContratoConta($outro->id, $this->user->id, 'Alheia '.uniqid());

    $contas = contasContratoLista($this);

    expect($contas->has($minha))->toBeTrue();
    expect($contas->has($alheia))->toBeFalse();
    expect($contas[$encerrada]['encerrada'])->toBeTrue();
    expect($contas[$minha]['encerrada'])->toBeFalse();

    // Caminho 1: à mão. 1000,50 + 99,75 − 200,25 = 900,00 (o crédito apagado de 999,00 não entra).
    expect(round((float) $contas[$minha]['saldo'], 2))->toBe(900.0);
    // Caminho 2: o texto é o mesmo num_f da coluna da Blade para o número do banco.
    expect($contas[$minha]['saldo_texto'])->toBe((new \App\Utils\Util)->num_f(900.0, true));
    expect($contas[$minha]['saldo_texto'])->toContain('900,00');
});

test('UC-CONTA-04 Tier 0 — sem acesso a todos os locais, só as contas dos locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesContas']);
    $ligada = contasContratoConta($this->business->id, $this->user->id, 'Ligada '.uniqid());
    $solta = contasContratoConta($this->business->id, $this->user->id, 'Solta '.uniqid());
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Lay '.uniqid()]);
    $local = DB::table('business_locations')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil', 'state' => 'MT',
        'city' => 'Cuiabá', 'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
        'default_payment_accounts' => json_encode(['cash' => ['is_enabled' => '1', 'account' => (string) $ligada]]),
    ]);

    // `permitted_locations()` lê as permissões DIRETAS do usuário (`$user->permissions`), não as do papel.
    $usuario = $this->usuarioComPermissoes(['account.access'], $this->business);
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$local}", 'web');
    $usuario->givePermissionTo("location.{$local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs(\App\User::findOrFail($usuario->id));

    $contas = contasContratoLista($this);

    expect($contas->has($ligada))->toBeTrue();
    expect($contas->has($solta))->toBeFalse();
});
