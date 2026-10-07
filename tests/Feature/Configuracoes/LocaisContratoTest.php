<?php

declare(strict_types=1);
// Cobre UC-LOCAL-01, UC-LOCAL-02, UC-LOCAL-03, UC-LOCAL-04, UC-LOCAL-05 (Configuracoes/Locais/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Thread sistema/playbook/04, F3 — /business-location em Inertia atrás da flag useV2ConfiguracoesLocais.
 *
 * A flag é ligada pelo override de ambiente (`feature-flags.forced_on`, inerte em produção).
 * store/update/activateDeactivateLocation não mudaram: LocaisBaselineTest.
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business_locations') || ! Schema::hasColumn('business_locations', 'cnpj')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['business_settings.access', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    // enabled_modules: o SetSessionData do login real preenche; o opcoes (isModuleEnabled('account')) lê.
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id, 'business.enabled_modules' => []]);
});

function locContratoLocal(int $businessId, string $nome): int
{
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $businessId, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $businessId, 'name' => 'Lay '.uniqid()]);

    return DB::table('business_locations')->insertGetId([
        'business_id' => $businessId, 'name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá',
        'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
    ]);
}

function locContratoIds($teste): array
{
    $r = $teste->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia,
        'X-Inertia-Partial-Component' => 'Configuracoes/Locais/Index', 'X-Inertia-Partial-Data' => 'locais',
    ])->get('/business-location');
    $r->assertOk();

    return collect($r->json('props.locais') ?? [])->pluck('id')->all();
}

test('UC-LOCAL-01 com a flag ligada, GET /business-location renderiza Inertia (não a DataTable)', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);

    $r = $this->withHeaders([
        'X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest',
    ])->get('/business-location');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Locais/Index');
});

test('UC-LOCAL-02 com a flag desligada, GET /business-location segue na Blade', function () {
    config(['feature-flags.forced_on' => '']);

    $this->get('/business-location')->assertOk()->assertViewIs('business_location.index');
});

test('UC-LOCAL-03 Tier 0 — a lista adiada é do negócio e respeita os locais permitidos', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);
    $outro = $this->seededSupportClientTenant();
    $liberado = locContratoLocal($this->business->id, 'Liberado '.uniqid());
    $vedado = locContratoLocal($this->business->id, 'Vedado '.uniqid());
    $alheio = locContratoLocal($outro->id, 'Alheio '.uniqid());

    $todos = locContratoIds($this);
    expect($todos)->toContain($liberado);
    expect($todos)->toContain($vedado);
    expect($todos)->not->toContain($alheio);

    $restrito = $this->usuarioComPermissoes(['business_settings.access'], $this->business);
    $restrito->givePermissionTo(Permission::findOrCreate("location.{$liberado}", 'web'));
    $this->actingAs($restrito->fresh());

    $permitidos = locContratoIds($this);
    expect($permitidos)->toContain($liberado);
    expect($permitidos)->not->toContain($vedado);
    expect($permitidos)->not->toContain($alheio);
});

/** Monta o corpo exatamente como o gravar() do Index.tsx: campos de texto + pagamentos por forma + destaque. */
function locContratoCorpo(array $dados, array $formas): array
{
    $corpo = array_filter($dados, 'is_string');
    foreach ($formas as $f) {
        $p = $dados['default_payment_accounts'][$f] ?? [];
        if (! empty($p['is_enabled'])) {
            $corpo['default_payment_accounts'][$f]['is_enabled'] = '1';
        }
        $corpo['default_payment_accounts'][$f]['account'] = isset($p['account']) ? (string) $p['account'] : '';
    }
    foreach ($dados['featured_products'] as $v) {
        $corpo['featured_products'][] = $v;
    }

    return $corpo;
}

test('UC-LOCAL-04 editar pelo drawer devolve formas de pagamento e destaque intactos', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);
    $id = locContratoLocal($this->business->id, 'Ida e volta '.uniqid());
    $pagamentos = ['cash' => ['is_enabled' => '1', 'account' => ''], 'card' => ['is_enabled' => '1', 'account' => '']];
    DB::table('business_locations')->where('id', $id)->update([
        'default_payment_accounts' => json_encode($pagamentos), 'featured_products' => json_encode(['11', '12']),
        'cnpj' => '00000000000191',
    ]);
    $h = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Inertia-Partial-Component' => 'Configuracoes/Locais/Index'];

    $locais = collect($this->withHeaders($h + ['X-Inertia-Partial-Data' => 'locais'])->get('/business-location')->json('props.locais'))->keyBy('id');
    $formas = array_keys($this->withHeaders($h + ['X-Inertia-Partial-Data' => 'opcoes'])->get('/business-location')->json('props.opcoes.formas') ?? []);
    expect($formas)->toContain('cash');
    $dados = $locais[$id]['dados'];
    $dados['name'] = 'Renomeado pelo drawer';

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->put("/business-location/{$id}", locContratoCorpo($dados, $formas));
    expect($r->json('success'))->toBeTrue();

    $depois = DB::table('business_locations')->where('id', $id)->first();
    expect($depois->name)->toBe('Renomeado pelo drawer');
    expect($depois->cnpj)->toBe('00000000000191');
    expect(json_decode($depois->featured_products, true))->toBe(['11', '12']);
    $gravado = json_decode($depois->default_payment_accounts, true);
    expect($gravado['cash']['is_enabled'] ?? null)->toBe('1');
    expect($gravado['card']['is_enabled'] ?? null)->toBe('1');
});

test('UC-LOCAL-05 cadastrar pelo drawer grava no negócio com todas as formas ligadas e a permissão do local', function () {
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesLocais']);
    if (app(\App\Utils\ModuleUtil::class)->isSuperadminInstalled() && Schema::hasTable('subscriptions')) {
        DB::table('subscriptions')->insert([
            'business_id' => $this->business->id, 'package_id' => 0, 'package_price' => 0,
            'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'package_details' => json_encode([]), 'created_id' => $this->user->id,
            'status' => 'approved', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Lay '.uniqid()]);
    $h = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Inertia-Partial-Component' => 'Configuracoes/Locais/Index', 'X-Inertia-Partial-Data' => 'opcoes'];
    $formas = array_keys($this->withHeaders($h)->get('/business-location')->json('props.opcoes.formas') ?? []);
    expect($formas)->not->toBeEmpty();
    $nome = 'Nova filial '.uniqid();
    // O novo() do Index.tsx: todas as formas ligadas, sem conta, país Brasil.
    $dados = ['name' => $nome, 'country' => 'Brasil', 'state' => 'MT', 'city' => 'Cuiabá', 'zip_code' => '7806500',
        'invoice_scheme_id' => (string) $esq, 'sale_invoice_scheme_id' => (string) $esq, 'invoice_layout_id' => (string) $lay,
        'sale_invoice_layout_id' => (string) $lay, 'featured_products' => [],
        'default_payment_accounts' => array_fill_keys($formas, ['is_enabled' => '1', 'account' => ''])];

    $r = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/business-location', locContratoCorpo($dados, $formas));
    expect($r->json('success'))->toBeTrue();

    $local = DB::table('business_locations')->where('name', $nome)->first();
    expect($local)->not->toBeNull();
    expect((int) $local->business_id)->toBe((int) $this->business->id);
    expect(array_keys(json_decode($local->default_payment_accounts, true)))->toBe($formas);
    expect(Permission::where('name', "location.{$local->id}")->exists())->toBeTrue();
});
