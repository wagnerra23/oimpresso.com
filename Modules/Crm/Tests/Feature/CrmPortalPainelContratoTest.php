<?php

declare(strict_types=1);
// Cobre UC-CRMPO-01, UC-CRMPO-02, UC-CRMPO-03, UC-CRMPO-18 (Crm/Portal/Painel/Index.casos.md).
// O aviso de OS do Início (caso 17 do portal) fica fora: depende da derivação de fase/orçamento da OS
// da thread 25. O id não é escrito aqui de propósito — a prova do placar procura o texto, e um comentário
// a satisfaria sem o comportamento existir.

use App\User;
use App\Utils\ModuleUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\Support\EstoqueFixture;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Contrato do Início do portal do contato (`/contact/contact-dashboard`) em Inertia — thread Crm/18,
 * MWART F3, atrás da flag useV2CrmPortalPainel (ligada aqui pelo override `feature-flags.forced_on`,
 * inerte em produção). A Blade segue em `?classico=1`.
 *
 * Os dois escopos andam juntos: `business_id` da sessão E `crm_contact_id` do usuário. Por isso todo
 * caso semeia o contato A, o contato B do MESMO negócio e o A' do negócio 99.
 *
 * Valor (regra mestre): a tela só MOSTRA. UC-CRMPO-01 prova os dois números contra valores semeados.
 * Tenant 98 (seededTenant, ADR 0358) e 99 (seededSupportClientTenant). NUNCA biz=4.
 * ⚠️ SKIP em SQLite: leia assertions, não "0 failed" (LC-13).
 *
 * @see Modules/Crm/Http/Controllers/DashboardController::index()
 */
const PORTC_ROTA = '/contact/contact-dashboard';
const PORTC_FLAG = 'useV2CrmPortalPainel';

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: transactions/contacts requerem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasColumn('users', 'crm_contact_id')) {
        $this->markTestSkipped('Coluna users.crm_contact_id ausente — migrations do Crm não rodaram.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-PORT18-'.uniqid());
    config(['feature-flags.forced_on' => PORTC_FLAG]);

    // O gate de assinatura (`crm_module`) não é o que este contrato prova: o pacote do tenant fictício
    // não o tem. O controller recebe o ModuleUtil por injeção — trocar no container basta.
    $fake = Mockery::mock(ModuleUtil::class)->makePartial();
    $fake->shouldReceive('hasThePermissionInSubscription')->andReturn(true);
    app()->instance(ModuleUtil::class, $fake);
});

function portcContato(int $biz, string $tipo, string $marca): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $biz, 'type' => $tipo, 'name' => 'Contato '.$marca.' '.uniqid(), 'mobile' => '0',
        'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Login de contato como o CrmUtil::creatContactPerson grava (user_type user_customer). */
function portcLogin(int $biz, int $contato): User
{
    $id = DB::table('users')->insertGetId([
        'username' => 'portc18_'.uniqid(), 'email' => 'portc18_'.uniqid().'@test.local', 'password' => bcrypt('x'),
        'business_id' => $biz, 'crm_contact_id' => $contato, 'first_name' => 'Daniela', 'last_name' => 'Teste',
        'user_type' => 'user_customer', 'allow_login' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

/** Transação do contato com pagamento opcional — a mesma forma que getContactInfo soma. */
function portcTransacao(int $biz, int $local, int $contato, string $tipo, string $total, ?string $pago = null): void
{
    $linha = [
        'business_id' => $biz, 'location_id' => $local, 'contact_id' => $contato, 'type' => $tipo,
        'status' => $tipo === 'purchase' ? 'received' : 'final', 'payment_status' => 'due',
        'invoice_no' => 'PORTC-'.uniqid(), 'transaction_date' => now(), 'total_before_tax' => $total,
        'final_total' => $total, 'created_by' => (int) DB::table('users')->min('id'),
        'created_at' => now(), 'updated_at' => now(),
    ];
    if (Schema::hasColumn('transactions', 'essentials_duration')) {
        $linha['essentials_duration'] = 0;
    }
    $tx = (int) DB::table('transactions')->insertGetId($linha);
    if ($pago !== null) {
        DB::table('transaction_payments')->insert([
            'transaction_id' => $tx, 'business_id' => $biz, 'amount' => $pago, 'method' => 'cash', 'is_return' => 0,
            'paid_on' => now(), 'created_by' => (int) DB::table('users')->min('id'), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}

/** As props da Page, como o browser recebe na primeira carga. */
function portcProps($teste, User $user): array
{
    $props = [];
    $teste->actingAs($user)->get(PORTC_ROTA)->assertOk()
        ->assertInertia(function (AssertableInertia $p) use (&$props) {
            $props = $p->toArray()['props'];
        });

    return $props;
}

it('UC-CRMPO-01 · vejo o meu saldo, e só o meu [T0]', function () {
    $biz = $this->business->id;
    $outro = $this->seededSupportClientTenant();
    $localOutro = EstoqueFixture::locationId($outro->id, '-PORT18-'.uniqid());

    $a = portcContato($biz, 'customer', 'A');
    $b = portcContato($biz, 'customer', 'B');
    $a2 = portcContato($outro->id, 'customer', 'A-linha');
    portcTransacao($biz, $this->local, $a, 'sell', '100.00', '40.00');
    portcTransacao($biz, $this->local, $b, 'sell', '900.00', '10.00');
    portcTransacao($outro->id, $localOutro, $a2, 'sell', '700.00');

    $props = portcProps($this, portcLogin($biz, $a));

    expect($props['vendas']['a_pagar'])->toBe('60.00');
    expect($props['vendas']['total'])->toBe('100.00');
    expect($props['vendas']['pago'])->toBe('40.00');
    $json = json_encode($props);
    foreach (['900.00', '890.00', '700.00', '1000.00'] as $alheio) {
        $this->assertStringNotContainsString($alheio, $json, "valor de outro contato apareceu nas props: {$alheio}");
    }
});

it('UC-CRMPO-02 · os blocos seguem o meu tipo', function () {
    $biz = $this->business->id;
    $cliente = portcContato($biz, 'customer', 'cli');
    $fornecedor = portcContato($biz, 'supplier', 'for');
    $ambos = portcContato($biz, 'both', 'amb');
    foreach ([$cliente, $ambos] as $c) {
        portcTransacao($biz, $this->local, $c, 'sell', '50.00');
    }
    foreach ([$fornecedor, $ambos] as $c) {
        portcTransacao($biz, $this->local, $c, 'purchase', '30.00');
    }
    portcTransacao($biz, $this->local, $ambos, 'opening_balance', '25.00');

    $p = portcProps($this, portcLogin($biz, $cliente));
    $this->assertArrayHasKey('vendas', $p, 'cliente deveria ver o bloco de vendas');
    $this->assertArrayNotHasKey('compras', $p, 'cliente não pode ver bloco de compras');
    $this->assertArrayNotHasKey('abertura', $p, 'sem saldo de abertura o bloco não vem');

    $p = portcProps($this, portcLogin($biz, $fornecedor));
    $this->assertArrayHasKey('compras', $p, 'fornecedor deveria ver o bloco de compras');
    $this->assertArrayNotHasKey('vendas', $p, 'fornecedor não pode ver bloco de vendas');
    expect($p['compras']['total'])->toBe('30.00');

    $p = portcProps($this, portcLogin($biz, $ambos));
    $this->assertArrayHasKey('vendas', $p, 'both deveria ver vendas');
    $this->assertArrayHasKey('compras', $p, 'both deveria ver compras');
    $this->assertArrayHasKey('abertura', $p, 'com saldo de abertura o bloco vem');
    expect($p['abertura']['total'])->toBe('25.00');
});

it('UC-CRMPO-03 · o portal abre na casca do contato, sem o shell do ERP', function () {
    $biz = $this->business->id;
    $contato = portcLogin($biz, portcContato($biz, 'customer', 'casca'));

    $this->actingAs($contato)->get(PORTC_ROTA)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Crm/Portal/Painel/Index', false)
            ->has('shell.portal.menu')
            ->where('shell.portal.contato.tipo', 'customer')
            ->missing('shell.menu')
            ->missing('shell.topnavs'));

    $this->actingAs($contato)->get(PORTC_ROTA.'?classico=1')->assertOk()->assertViewIs('crm::dashboard.index');

    config(['feature-flags.forced_on' => '']);
    app()->instance(\App\Services\FeatureFlagService::class, new class extends \App\Services\FeatureFlagService
    {
        public function isOn(string $flag, array $attrs = []): bool
        {
            return $flag === PORTC_FLAG ? false : parent::isOn($flag, $attrs);
        }
    });
    $this->actingAs($contato)->get(PORTC_ROTA)->assertOk()->assertViewIs('crm::dashboard.index');
});

it('UC-CRMPO-18 · operador não entra no portal; contato não entra no ERP [T0]', function () {
    $biz = $this->business->id;
    $operador = $this->usuarioComPermissoes(['crm.access_all_leads'], $this->business);
    // Recusa = 403 ou 404. Medido no CI (run 40498496667): o operador recebe 404 porque o
    // ContactSidebarMenu roda antes do CheckContactLogin e faz findOrFail no crm_contact_id vazio,
    // o mesmo de antes da thread 18. O que o caso exige é que a tela do portal não abra.
    $recusa = $this->actingAs($operador)->get(PORTC_ROTA);
    expect($recusa->getStatusCode())->toBeIn([403, 404]);
    $this->assertStringNotContainsString('Crm/Portal/Painel/Index', (string) $recusa->getContent(), 'o portal abriu para o operador');

    $contato = portcLogin($biz, portcContato($biz, 'customer', 'porta'));
    $resposta = $this->actingAs($contato)->get('/crm/leads');
    // Abrir a tela do ERP é 200; recusa é qualquer outro desfecho (403 do LeadController, ou um
    // middleware do grupo que barre antes). O caso não fixa o código, fixa que a tela não abra.
    expect($resposta->isSuccessful())->toBeFalse();
    $this->assertStringNotContainsString('Crm/Leads/Index', (string) $resposta->getContent(), 'a tela do ERP renderizou para o contato');
});
