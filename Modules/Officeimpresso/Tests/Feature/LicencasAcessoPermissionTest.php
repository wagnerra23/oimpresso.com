<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\DB;
use Modules\Officeimpresso\Http\Controllers\DataController;
use Spatie\Permission\Models\Permission;

uses(Tests\TestCase::class);

/**
 * Autorização da gestão de licenças desktop (Officeimpresso).
 *
 * Contexto (2026-07-29): o grupo de rotas /officeimpresso/* pedia apenas
 * `auth` — o menu escondia os links, mas QUALQUER usuário autenticado, de
 * qualquer business, abria a lista de máquinas de TODOS os clientes e podia
 * bloquear/liberar o Delphi de qualquer um deles pela URL direta. Esconder
 * link não é autorização.
 *
 * Ao mesmo tempo o suporte (que precisa ver a máquina do cliente pra dar
 * assistência) não enxergava nada, porque o menu só era montado pra
 * `superadmin`. Este teste trava os dois lados.
 *
 * Níveis:
 *   - `officeimpresso.access` ............... leitura de todas as empresas
 *   - `officeimpresso.licencas.gerenciar` ... liberar/bloquear máquina
 *   - `superadmin` .......................... empresa inteira + exclusão
 *
 * ⚠️ NÃO semear `session(['user.business_id' => ...])` antes da request. O
 * `SetSessionData` só monta a sessão quando ela ainda NÃO tem `user`
 * (SetSessionData.php:29) — semear na mão faz ele RETORNAR CEDO, e aí `currency`,
 * `business` e `financial_year` nunca entram. A `layouts/app.blade.php:61`
 * (`session('currency')['code']`) então estoura e a tela responde 500.
 * Enquanto os asserts eram `->not->toBe(403)` isso passava despercebido (500 não
 * é 403); com `assertOk()` aparece. O middleware preenche `user.business_id` a
 * partir do `Auth::user()` — o MESMO valor que se semeava — com o resto junto.
 *
 * NÃO usa RefreshDatabase — UltimatePOS legacy (100+ migrations/triggers não
 * rodam em sqlite). Roda contra DB real (CT 100 / dev). biz=1 (Wagner WR2) —
 * NUNCA biz=4 (ROTA LIVRE prod) — ADR 0101.
 *
 * @see Modules\Officeimpresso\Http\Controllers\LicencaComputadorController::authorizeAccess()
 * @see Modules\Officeimpresso\Tests\Feature\ClientesLiberarPermissionTest
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

defined('PERM_OI_ACCESS') || define('PERM_OI_ACCESS', 'officeimpresso.access');
defined('PERM_OI_GERENCIAR') || define('PERM_OI_GERENCIAR', 'officeimpresso.licencas.gerenciar');
defined('PERM_OI_CLIENTES') || define('PERM_OI_CLIENTES', 'officeimpresso.clientes.liberar');
defined('PERM_OI_EMPRESA') || define('PERM_OI_EMPRESA', 'officeimpresso.empresa.gerenciar');
defined('PERM_OI_EXCLUIR') || define('PERM_OI_EXCLUIR', 'officeimpresso.licencas.excluir');

// ID que não existe: o gate roda ANTES do service, então o caso "com permissão"
// atravessa a guarda e morre no findOrFail (try/catch → redirect), sem escrever
// em nenhuma licença real.
defined('LICENCA_INEXISTENTE') || define('LICENCA_INEXISTENTE', 999999999);

// Mesma razão, pro escopo empresa-inteira: businessbloqueado() faz TOGGLE real
// (alternarBloqueioEmpresa → save). Com id inexistente o findOrFail estoura e o
// controller cai no catch — o gate é exercido sem bloquear cliente nenhum.
defined('BUSINESS_INEXISTENTE') || define('BUSINESS_INEXISTENTE', 999999999);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: schema MySQL UltimatePOS necessário (ADR 0101).');
    }
});

it('declara as permissões no user_permissions (assináveis na UI de Funções)', function () {
    $values = array_column((new DataController())->user_permissions(), 'value');

    // Sem estar aqui, a permissão não vira checkbox na tela de Funções — e
    // permissão que não aparece na tela é permissão que ninguém consegue dar.
    expect($values)->toContain(PERM_OI_ACCESS)
        ->and($values)->toContain(PERM_OI_GERENCIAR)
        ->and($values)->toContain(PERM_OI_CLIENTES)
        ->and($values)->toContain(PERM_OI_EMPRESA)
        ->and($values)->toContain(PERM_OI_EXCLUIR);
});

it('concede acesso às licenças SEM abrir superadmin nem Financeiro (no-leak)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'superadmin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'financeiro.titulo.aprovar', 'guard_name' => 'web']);

    $user = makeOiAcessoTestUser($business->id);
    $user->givePermissionTo(PERM_OI_ACCESS);

    expect($user->can(PERM_OI_ACCESS))->toBeTrue()
        ->and($user->can('superadmin'))->toBeFalse()
        ->and($user->can('financeiro.titulo.aprovar'))->toBeFalse()
        // Ver NÃO dá direito de mexer.
        ->and($user->can(PERM_OI_GERENCIAR))->toBeFalse();

    $user->forceDelete();
});

it('barra usuário autenticado sem permissão nas telas de licença e log', function () {
    $business = $this->seededTenant();

    $user = makeOiAcessoTestUser($business->id);
    $this->actingAs($user);

    // Regressão do buraco: antes destas guardas, um autenticado qualquer abria
    // as três telas — inclusive a visão cross-empresa.
    $this->get('/officeimpresso/licenca_computador')->assertForbidden();
    $this->get('/officeimpresso/licenca_log')->assertForbidden();
    $this->get('/officeimpresso/businessall')->assertForbidden();

    $user->forceDelete();
});

it('libera as telas de leitura pra quem tem officeimpresso.access (caso do suporte)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);

    $user = makeOiAcessoTestUser($business->id);
    $user->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($user);

    // `->not->toBe(403)` era verde-que-não-prova: um 500 também não é 403.
    // Medido em 2026-08-19 (run 32290877986) — estas duas primeiras rotas
    // estavam em 500 (`ViewException: Undefined array key "REMOTE_ADDR"`,
    // `layouts/app.blade.php:56`) e este caso passava mesmo assim. `assertOk()`
    // exige que a tela ABRA, que é o que o enunciado promete.
    $this->get('/officeimpresso/licenca_computador')->assertOk();
    $this->get('/officeimpresso/licenca_log')->assertOk();
    $this->get('/officeimpresso/businessall')->assertOk();

    $user->forceDelete();
});

it('exige licencas.gerenciar pra liberar/bloquear máquina — ver não basta', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PERM_OI_GERENCIAR, 'guard_name' => 'web']);

    // Só leitura → 403 no toggle.
    $leitor = makeOiAcessoTestUser($business->id);
    $leitor->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($leitor);
    $this->get('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE . '/toggle-block')
        ->assertForbidden();
    $leitor->forceDelete();

    // Com a permissão de suporte → atravessa o gate.
    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_GERENCIAR);
    $this->actingAs($suporte);
    // Atravessar o gate tem desfecho CONHECIDO: o findOrFail do id inexistente
    // estoura e o controller cai no catch → `redirect()->back()->with('error')`.
    // Exigir o redirect MAIS o flash prova que a requisição chegou ao CORPO do
    // controller — `->not->toBe(403)` aceitaria um 500 de qualquer origem.
    $this->get('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE . '/toggle-block')
        ->assertRedirect()
        ->assertSessionHas('error');
    $suporte->forceDelete();
});

it('mexer em máquina NÃO concede escopo empresa-inteira (no-leak)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_GERENCIAR, 'guard_name' => 'web']);

    // Suporte pode mexer em máquina individual, mas não derrubar o cliente todo.
    // Desde 2026-07-30 isso deixou de ser superadmin-only e virou a permissão
    // própria PERM_OI_EMPRESA — que este nível NÃO tem.
    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_GERENCIAR);
    $this->actingAs($suporte);

    $this->get('/officeimpresso/licenca_computador/businessbloqueado/' . $business->id)
        ->assertForbidden();

    $suporte->forceDelete();
});

it('delega escopo empresa-inteira via officeimpresso.empresa.gerenciar', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_EMPRESA, 'guard_name' => 'web']);

    $gestor = makeOiAcessoTestUser($business->id);
    $gestor->givePermissionTo(PERM_OI_EMPRESA);
    $this->actingAs($gestor);

    // BUSINESS_INEXISTENTE de propósito: o gate roda ANTES do service, então o
    // caso "com permissão" atravessa a guarda e morre no findOrFail (catch →
    // redirect) — sem alternar o bloqueio de NENHUMA empresa real.
    // O redirect + o flash de erro são o desfecho conhecido desse caminho:
    // asseverar os dois prova que o corpo do controller rodou.
    $this->get('/officeimpresso/licenca_computador/businessbloqueado/' . BUSINESS_INEXISTENTE)
        ->assertRedirect()
        ->assertSessionHas('error');

    $gestor->forceDelete();
});

it('no-leak: gerir a empresa NÃO concede EXCLUIR licença (destrutivo)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_EMPRESA, 'guard_name' => 'web']);

    // Bloquear o cliente é reversível; apagar o registro não é. Quem tem uma
    // não ganha a outra de brinde.
    $gestor = makeOiAcessoTestUser($business->id);
    $gestor->givePermissionTo(PERM_OI_EMPRESA);
    $this->actingAs($gestor);

    $this->delete('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE)
        ->assertForbidden();

    $gestor->forceDelete();
});

it('delega exclusão via officeimpresso.licencas.excluir', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_EXCLUIR, 'guard_name' => 'web']);

    $user = makeOiAcessoTestUser($business->id);
    $user->givePermissionTo(PERM_OI_EXCLUIR);
    $this->actingAs($user);

    // Atravessa o gate e morre no find() → 404 JSON. Nenhuma licença real some.
    // O corpo do JSON entra na asserção de propósito: é o que distingue "o
    // service rodou e não achou" de um 404 de rota inexistente (ambos são
    // "não é 403").
    $this->delete('/officeimpresso/licenca_computador/' . LICENCA_INEXISTENTE)
        ->assertNotFound()
        ->assertJson(['error' => 'Computador não encontrado']);

    $user->forceDelete();
});

it('manda /officeimpresso pra primeira tela que o nível consegue abrir', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => PERM_OI_CLIENTES, 'guard_name' => 'web']);

    // Suporte (lê licenças) → Computadores.
    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($suporte);
    $this->get('/officeimpresso')->assertRedirect('/officeimpresso/computadores');
    $suporte->forceDelete();

    // Atendente (só credenciais OAuth) → Clientes. Um redirect fixo pra
    // /computadores jogaria este nível num 403 — o buraco que o #5044 fechou
    // no menu e que a porta de entrada não pode reabrir.
    $atendente = makeOiAcessoTestUser($business->id);
    $atendente->givePermissionTo(PERM_OI_CLIENTES);
    $this->actingAs($atendente);
    $this->get('/officeimpresso')->assertRedirect('/officeimpresso/client');
    $atendente->forceDelete();
});

it('nega a porta de entrada pra autenticado sem permissão do módulo', function () {
    $business = $this->seededTenant();

    $user = makeOiAcessoTestUser($business->id);
    $this->actingAs($user);

    // Não redireciona pra uma tela que negaria do mesmo jeito — nega aqui.
    $this->get('/officeimpresso')->assertForbidden();

    $user->forceDelete();
});

it('lista os links de licença no menu Blade pra quem tem access (não só superadmin)', function () {
    $business = $this->seededTenant();

    Permission::firstOrCreate(['name' => PERM_OI_ACCESS, 'guard_name' => 'web']);

    $suporte = makeOiAcessoTestUser($business->id);
    $suporte->givePermissionTo(PERM_OI_ACCESS);
    $this->actingAs($suporte);

    $html = view('officeimpresso::layouts.nav')->render();

    // Regressão real (2026-07-30): o suporte ABRIA /officeimpresso/computadores
    // — o controller aceita `access` — mas não via link NENHUM pra navegar,
    // porque esta nav (3ª fonte de menu do módulo, a que as telas Blade legacy
    // renderizam) ainda gateava tudo por @can('superadmin'). O #5044 corrigiu o
    // topnav.php e a sidebar e passou por aqui. Menu e guarda têm que contar a
    // mesma história.
    expect($html)->toContain('/officeimpresso/businessall')
        ->and($html)->toContain('/officeimpresso/computadores')
        ->and($html)->toContain('/officeimpresso/licenca_log');

    $suporte->forceDelete();
});

it('não lista os links de licença pra quem não tem access', function () {
    $business = $this->seededTenant();

    // Controle negativo: sem o teste abaixo, o de cima passaria com uma nav que
    // mostra tudo pra todo mundo — que é o outro lado do buraco do #5044.
    $user = makeOiAcessoTestUser($business->id);
    $this->actingAs($user);

    $html = view('officeimpresso::layouts.nav')->render();

    expect($html)->not->toContain('/officeimpresso/businessall')
        ->and($html)->not->toContain('/officeimpresso/licenca_log');

    $user->forceDelete();
});

/**
 * Cria um user de teste SEM nenhuma role (pra o Gate::before não fazer bypass).
 */
function makeOiAcessoTestUser(int $businessId): User
{
    return User::create([
        'business_id' => $businessId,
        'first_name'  => 'OI',
        'surname'     => 'Acesso',
        'username'    => 'oi_acesso_'.$businessId.'_'.uniqid(),
        'email'       => 'oi_acesso_'.$businessId.'_'.uniqid().'@test.local',
        'password'    => bcrypt('test12345'),
        'language'    => 'pt_BR',
    ]);
}
