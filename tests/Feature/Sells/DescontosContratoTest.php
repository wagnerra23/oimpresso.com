<?php

declare(strict_types=1);

use App\Discount;
use App\Http\Requests\SalvarDescontoRequest;
use App\User;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Contrato do cadastro de Descontos (`/discount` resource → DiscountController).
 *
 * Thread 04 do playbook de Vendas + decisão D1 de [W] (2026-10-02): ver a lista e
 * criar/editar/excluir viram permissões separadas (`discount.view` × `discount.manage`).
 *
 * O que este arquivo prova:
 *  - ver × editar: quem só vê lista e recebe 403 em TODA escrita (UC-DSC-04);
 *  - a migration concede as duas permissões a quem tinha `discount.access` (UC-DSC-07);
 *  - a gravação continua gravando a MESMA linha de antes (UC-DSC-01, REGRA MESTRE de valor:
 *    desconto é valor — o PDV aplica sozinho). A referência é uma cópia CONGELADA do código
 *    do controller antes do FormRequest (`dscLinhaLegada`), e a 2ª prova é o valor escrito à
 *    mão. Se as duas divergirem do banco, o FormRequest mudou o que é gravado;
 *  - nome vazio segue sem gravar, exatamente como antes (UC-DSC-05) — o FormRequest não
 *    valida, de propósito, para não mudar o que é aceito; quem recusa com mensagem é a tela;
 *  - isolamento entre negócios em leitura e escrita (UC-DSC-06, [T0]).
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário, criado se faltar). Nunca biz=4.
 * Não roda local (proibicoes.md: Pest só no CT 100 / CI). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class);

const DSC_FORMATO_DATA = 'd/m/Y';

/** Operador com papel PRÓPRIO do negócio (nunca Admin#: o Gate::before liberaria tudo). */
function dscOperador(int $bizId, array $permissoes): User
{
    $user = User::factory()->create(['business_id' => $bizId]);

    $papel = Role::create([
        'name' => 'DscOp' . uniqid() . '#' . $bizId,
        'business_id' => $bizId,
        'guard_name' => 'web',
    ]);
    foreach ($permissoes as $p) {
        Permission::findOrCreate($p, 'web');
    }
    $papel->syncPermissions($permissoes);
    $user->assignRole($papel);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::findOrFail($user->id);
}

/**
 * Login com sessão DETERMINÍSTICA: formato de data do negócio fixado, para o `uf_date` do
 * request e o da referência lerem a mesma coisa. Com `user` na sessão o SetSessionData não
 * sobrescreve.
 */
function dscLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'business.date_format' => DSC_FORMATO_DATA,
        'business.time_format' => '24',
    ]);
}

/** Cabeçalho que o front legado (jQuery) e o Inertia mandam — o controller testa `ajax()`. */
function dscAjax(object $test): object
{
    return $test->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
}

function dscDesconto(int $bizId, array $extra = []): int
{
    return DB::table('discounts')->insertGetId(array_merge([
        'name' => 'DSC fixture ' . uniqid(),
        'business_id' => $bizId,
        'priority' => 1,
        'discount_type' => 'percentage',
        'discount_amount' => 10,
        'starts_at' => '2031-03-01 00:00:00',
        'ends_at' => '2031-03-31 23:59:00',
        'is_active' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ], $extra));
}

/**
 * CÓPIA CONGELADA do DiscountController@store|update ANTES do FormRequest (main 07e7853257).
 * Não importar nada do código de produção aqui — é a referência "antes".
 */
function dscLinhaLegada(Request $request): array
{
    $util = app(Util::class);
    $input = $request->only(['name', 'brand_id', 'category_id',
        'location_id', 'priority', 'discount_type', 'discount_amount', 'spg', ]);
    $variation_ids = $request->input('variation_ids');
    if (! empty($variation_ids)) {
        unset($input['brand_id']);
        unset($input['category_id']);
    }
    $input['starts_at'] = $request->has('starts_at') ? $util->uf_date($request->input('starts_at'), true) : null;
    $input['ends_at'] = $request->has('ends_at') ? $util->uf_date($request->input('ends_at'), true) : null;
    foreach (['is_active', 'applicable_in_cg'] as $checkbox) {
        $input[$checkbox] = $request->has($checkbox) ? 1 : 0;
    }

    return $input;
}

/** Colunas que a gravação toca, normalizadas como o MySQL devolve. */
function dscLinhaGravada(int $id): array
{
    $r = (array) DB::table('discounts')->where('id', $id)->first();

    return [
        'name' => $r['name'],
        'brand_id' => $r['brand_id'] === null ? null : (int) $r['brand_id'],
        'category_id' => $r['category_id'] === null ? null : (int) $r['category_id'],
        'location_id' => $r['location_id'] === null ? null : (int) $r['location_id'],
        'priority' => $r['priority'] === null ? null : (int) $r['priority'],
        'discount_type' => $r['discount_type'],
        'discount_amount' => number_format((float) $r['discount_amount'], 4, '.', ''),
        'spg' => $r['spg'],
        'starts_at' => $r['starts_at'],
        'ends_at' => $r['ends_at'],
        'is_active' => (int) $r['is_active'],
        'applicable_in_cg' => (int) $r['applicable_in_cg'],
    ];
}

/** Normaliza a referência legada para a mesma forma de `dscLinhaGravada`. */
function dscNormaliza(array $in): array
{
    $int = fn ($v) => ($v === null || $v === '') ? null : (int) $v;

    return [
        'name' => $in['name'] ?? null,
        'brand_id' => $int($in['brand_id'] ?? null),
        'category_id' => $int($in['category_id'] ?? null),
        'location_id' => $int($in['location_id'] ?? null),
        'priority' => $int($in['priority'] ?? null),
        'discount_type' => $in['discount_type'] ?? null,
        'discount_amount' => number_format((float) ($in['discount_amount'] ?? 0), 4, '.', ''),
        'spg' => ($in['spg'] ?? '') === '' ? null : $in['spg'],
        'starts_at' => $in['starts_at'],
        'ends_at' => $in['ends_at'],
        'is_active' => (int) $in['is_active'],
        'applicable_in_cg' => (int) $in['applicable_in_cg'],
    ];
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['discounts', 'discount_variations', 'business', 'users', 'roles', 'permissions'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->editor = dscOperador($this->bizId, ['discount.view', 'discount.manage']);
    $this->leitor = dscOperador($this->bizId, ['discount.view']);
});

it('UC-DSC-01 [V0] criar grava a mesma linha que o controller gravava antes do FormRequest', function () {
    dscLogin($this, $this->editor);

    $payload = [
        'name' => 'Semana da comunicação visual',
        'brand_id' => '7',
        'category_id' => '9',
        'location_id' => '3',
        'priority' => '2',
        'discount_type' => 'percentage',
        'discount_amount' => '12.5',
        'spg' => '',
        'starts_at' => '17/03/2031 10:30',
        'ends_at' => '24/03/2031 18:00',
        'is_active' => '1',
    ];

    $antes = (int) DB::table('discounts')->max('id');
    dscAjax($this)->post('/discount', $payload)->assertOk()->assertJson(['success' => true]);
    $id = (int) DB::table('discounts')->where('business_id', $this->bizId)->max('id');
    expect($id)->toBeGreaterThan($antes);

    $gravada = dscLinhaGravada($id);

    // Prova 1 — a cópia congelada do código antigo monta a mesma linha.
    $referencia = dscNormaliza(dscLinhaLegada(Request::create('/discount', 'POST', $payload)));
    expect($gravada)->toEqual($referencia);

    // Prova 2 — à mão: números crus (sem num_uf), data no formato do negócio, checkbox ausente = 0.
    expect($gravada['discount_amount'])->toBe('12.5000');
    expect($gravada['priority'])->toBe(2);
    expect($gravada['starts_at'])->toBe('2031-03-17 10:30:00');
    expect($gravada['ends_at'])->toBe('2031-03-24 18:00:00');
    expect($gravada['is_active'])->toBe(1);
    expect($gravada['applicable_in_cg'])->toBe(0);
    expect((int) DB::table('discounts')->where('id', $id)->value('business_id'))->toBe($this->bizId);
});

it('UC-DSC-01 [V0] editar grava a mesma linha que o controller gravava antes do FormRequest', function () {
    dscLogin($this, $this->editor);
    $id = dscDesconto($this->bizId, ['is_active' => 1, 'applicable_in_cg' => 1]);

    // Sem `is_active`/`applicable_in_cg` no payload = checkbox desmarcado (vira 0, como antes).
    $payload = [
        'name' => 'Queima de banner 440g',
        'location_id' => '5',
        'priority' => '3',
        'discount_type' => 'fixed',
        'discount_amount' => '15',
        'starts_at' => '15/08/2031 00:00',
        'ends_at' => '20/09/2031 23:59',
    ];

    dscAjax($this)->put("/discount/{$id}", $payload)->assertOk()->assertJson(['success' => true]);

    $referencia = dscNormaliza(dscLinhaLegada(Request::create("/discount/{$id}", 'PUT', $payload)));
    $gravada = dscLinhaGravada($id);
    expect($gravada)->toEqual($referencia);
    expect($gravada['discount_amount'])->toBe('15.0000');
    expect($gravada['is_active'])->toBe(0);
    expect($gravada['applicable_in_cg'])->toBe(0);
});

it('UC-DSC-02 escolher produtos apaga marca e categoria (o servidor guarda um ou outro)', function () {
    dscLogin($this, $this->editor);

    $payload = [
        'name' => 'Cartão de visita 1.000 un',
        'brand_id' => '7',
        'category_id' => '9',
        'variation_ids' => ['987654'],
        'discount_type' => 'fixed',
        'discount_amount' => '20',
        'starts_at' => '27/03/2031 00:00',
        'ends_at' => '27/04/2031 00:00',
        'is_active' => '1',
    ];
    dscAjax($this)->post('/discount', $payload)->assertOk()->assertJson(['success' => true]);
    $id = (int) DB::table('discounts')->where('business_id', $this->bizId)->max('id');

    $gravada = dscLinhaGravada($id);
    expect($gravada['brand_id'])->toBeNull();
    expect($gravada['category_id'])->toBeNull();
    expect(DB::table('discount_variations')->where('discount_id', $id)->pluck('variation_id')->map(fn ($v) => (int) $v)->all())
        ->toBe([987654]);

    // A montagem do FormRequest isolada dá o mesmo resultado (mesma regra nos dois métodos).
    $fr = SalvarDescontoRequest::create('/discount', 'POST', $payload);
    $dados = $fr->dadosParaGravar(app(Util::class));
    expect(array_key_exists('brand_id', $dados))->toBeFalse();
    expect(array_key_exists('category_id', $dados))->toBeFalse();
});

it('UC-DSC-03 desativar em massa e reativar mudam só a situação', function () {
    dscLogin($this, $this->editor);
    $a = dscDesconto($this->bizId);
    $b = dscDesconto($this->bizId);

    $this->post('/discount/mass-deactivate', ['selected_discounts' => "{$a},{$b}"])->assertRedirect();
    expect(DB::table('discounts')->whereIn('id', [$a, $b])->pluck('is_active')->map(fn ($v) => (int) $v)->all())->toBe([0, 0]);

    dscAjax($this)->get("/discount/activate/{$a}")->assertOk()->assertJson(['success' => true]);
    expect((int) DB::table('discounts')->where('id', $a)->value('is_active'))->toBe(1);
    expect((int) DB::table('discounts')->where('id', $b)->value('is_active'))->toBe(0);
    expect(DB::table('discounts')->where('id', $a)->exists())->toBeTrue();
});

it('UC-DSC-04 quem só vê lista, mas recebe 403 em toda escrita', function () {
    $id = dscDesconto($this->bizId, ['is_active' => 0]);
    dscLogin($this, $this->leitor);

    dscAjax($this)->get('/discount')->assertOk();

    $antes = dscLinhaGravada($id);
    dscAjax($this)->post('/discount', ['name' => 'X', 'discount_amount' => '1'])->assertForbidden();
    dscAjax($this)->put("/discount/{$id}", ['name' => 'Y', 'discount_amount' => '99'])->assertForbidden();
    dscAjax($this)->delete("/discount/{$id}")->assertForbidden();
    dscAjax($this)->get("/discount/activate/{$id}")->assertForbidden();
    $this->post('/discount/mass-deactivate', ['selected_discounts' => (string) $id])->assertForbidden();
    dscAjax($this)->get('/discount/create')->assertForbidden();

    // Âncora positiva: o 403 veio da trava, não de a linha não existir — ela segue intacta.
    expect(dscLinhaGravada($id))->toEqual($antes);
    expect(DB::table('discounts')->where('id', $id)->exists())->toBeTrue();
});

it('UC-DSC-04 sem nenhuma das duas permissões a tela devolve 403', function () {
    $nada = dscOperador($this->bizId, []);
    dscLogin($this, $nada);

    dscAjax($this)->get('/discount')->assertForbidden();
});

it('UC-DSC-05 nome vazio segue sem gravar nada, igual a antes (o FormRequest não mudou o que é aceito)', function () {
    dscLogin($this, $this->editor);

    // Medido no código: o ConvertEmptyStringsToNull (app/Http/Kernel.php) transforma '' em null
    // e `discounts.name` é NOT NULL — o INSERT falha, o catch do controller devolve
    // success:false e nada é gravado. Era assim antes do FormRequest e continua assim: ele
    // não valida, de propósito. Quem recusa com mensagem é a tela, antes de enviar.
    $payload = ['name' => '', 'discount_type' => 'fixed', 'discount_amount' => '1',
        'starts_at' => '01/03/2031 00:00', 'ends_at' => '02/03/2031 00:00'];
    $antes = DB::table('discounts')->where('business_id', $this->bizId)->count();

    dscAjax($this)->post('/discount', $payload)->assertOk()->assertJson(['success' => false]);

    expect(DB::table('discounts')->where('business_id', $this->bizId)->count())->toBe($antes);

    // Âncora positiva: o mesmo operador, com nome preenchido, grava — a recusa veio do nome.
    $payload['name'] = 'Com nome';
    dscAjax($this)->post('/discount', $payload)->assertOk()->assertJson(['success' => true]);
    expect(DB::table('discounts')->where('business_id', $this->bizId)->count())->toBe($antes + 1);
});

it('UC-DSC-06 [T0] desconto de outro negócio não aparece nem é alcançado pela escrita', function () {
    $alheio = dscDesconto($this->outroBizId, ['name' => 'DSC alheio 99', 'is_active' => 0]);
    $meu = dscDesconto($this->bizId, ['name' => 'DSC meu 98']);
    $antes = dscLinhaGravada($alheio);
    dscLogin($this, $this->editor);

    $json = dscAjax($this)->get('/discount')->assertOk()->json();
    $ids = array_map(fn ($r) => (int) $r['id'], $json['data'] ?? []);
    // Âncora positiva: a lista funciona e traz o desconto do próprio negócio.
    expect(in_array($meu, $ids, true))->toBeTrue();
    expect(in_array($alheio, $ids, true))->toBeFalse();

    dscAjax($this)->put("/discount/{$alheio}", ['name' => 'invadido', 'discount_amount' => '99'])->assertOk()->assertJson(['success' => false]);
    dscAjax($this)->delete("/discount/{$alheio}")->assertOk()->assertJson(['success' => false]);
    dscAjax($this)->get("/discount/activate/{$alheio}")->assertOk();
    $this->post('/discount/mass-deactivate', ['selected_discounts' => (string) $alheio]);

    expect(dscLinhaGravada($alheio))->toEqual($antes);
    expect(DB::table('discounts')->where('id', $alheio)->exists())->toBeTrue();
});

it('UC-DSC-07 a migration concede ver e editar a quem tinha discount.access — papel e direto', function () {
    Permission::findOrCreate('discount.access', 'web');

    $papelAntigo = Role::create(['name' => 'DscAntigo' . uniqid() . '#' . $this->bizId, 'business_id' => $this->bizId, 'guard_name' => 'web']);
    $papelAntigo->syncPermissions(['discount.access']);
    $papelSem = Role::create(['name' => 'DscSem' . uniqid() . '#' . $this->bizId, 'business_id' => $this->bizId, 'guard_name' => 'web']);
    $papelSem->syncPermissions([]);
    $usuarioDireto = User::factory()->create(['business_id' => $this->bizId]);
    $usuarioDireto->givePermissionTo('discount.access');

    $migration = require base_path('database/migrations/2026_10_02_120000_add_discount_view_manage_permissions.php');
    $migration->up();
    $migration->up(); // idempotente: re-run não estoura nem duplica

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $papelAntigo = Role::findById($papelAntigo->id, 'web');
    $papelSem = Role::findById($papelSem->id, 'web');

    expect($papelAntigo->hasPermissionTo('discount.view'))->toBeTrue();
    expect($papelAntigo->hasPermissionTo('discount.manage'))->toBeTrue();
    expect($papelSem->hasPermissionTo('discount.view'))->toBeFalse();
    expect($papelSem->hasPermissionTo('discount.manage'))->toBeFalse();

    $direto = User::findOrFail($usuarioDireto->id);
    expect($direto->hasDirectPermission('discount.view'))->toBeTrue();
    expect($direto->hasDirectPermission('discount.manage'))->toBeTrue();

    $viewId = (int) DB::table('permissions')->where('name', 'discount.view')->where('guard_name', 'web')->value('id');
    expect(DB::table('role_has_permissions')->where('role_id', $papelAntigo->id)->where('permission_id', $viewId)->count())->toBe(1);
});

/** Versão Inertia igual à do servidor — evita o 409 antes de o controller rodar. */
function dscInertiaVersion(): string
{
    $manifest = public_path('build-inertia/manifest.json');

    return file_exists($manifest) ? md5_file($manifest) : '1';
}

/** Visita como o navegador faz: o Inertia manda X-Inertia E X-Requested-With. */
function dscVisita(object $test, array $parcial = []): array
{
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => dscInertiaVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ];
    if ($parcial !== []) {
        $headers['X-Inertia-Partial-Component'] = 'Discount/Index';
        $headers['X-Inertia-Partial-Data'] = implode(',', $parcial);
    }
    $response = $test->withHeaders($headers)->get('/discount');
    $response->assertOk();
    $page = json_decode($response->getContent(), true);

    // Pré-condição anti-vácuo: a Page certa, não o JSON do DataTable do ramo ajax.
    expect($page['component'] ?? null)->toBe('Discount/Index');

    return $page['props'];
}

it('UC-DSC-08 a tela React renderiza com os dados do negócio e a permissão de editar', function () {
    $meu = dscDesconto($this->bizId, ['name' => 'DSC tela 98', 'discount_amount' => 12.5]);
    $alheio = dscDesconto($this->outroBizId, ['name' => 'DSC tela 99']);

    dscLogin($this, $this->editor);
    $props = dscVisita($this);
    expect($props['permissoes']['editar'])->toBeTrue();
    expect(array_key_exists('descontos', $props))->toBeFalse(); // deferida: não vem na 1ª carga

    $lista = dscVisita($this, ['descontos'])['descontos'];
    $ids = array_map(fn ($d) => (int) $d['id'], $lista);
    expect(in_array($meu, $ids, true))->toBeTrue();
    expect(in_array($alheio, $ids, true))->toBeFalse();

    $linha = collect($lista)->firstWhere('id', $meu);
    expect($linha['valor'])->toBe('12.5000'); // cru do banco, sem conta nenhuma
    expect($linha['ativo'])->toBeTrue();

    // Quem só vê abre a mesma tela, com a gravação desabilitada.
    dscLogin($this, $this->leitor);
    expect(dscVisita($this)['permissoes']['editar'])->toBeFalse();
});
