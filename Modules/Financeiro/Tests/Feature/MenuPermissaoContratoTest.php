<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Financeiro\Models\Advisor;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Menu do Financeiro só mostra o que a pessoa consegue abrir (auditoria 2026-11-05, D14).
 *
 * O `modifyAdminMenu` só checava `financeiro.access` e mandava 17 links, boa parte 403 para
 * quem não tinha a permissão da tela. Roda o caminho real: a requisição passa pelo
 * `AdminSidebarMenu` e o assert lê o `shell.menu` que o React recebe.
 *
 * Tenant 98 (ADR 0358), nunca biz=4 nem biz=1.
 * ⚠️ Skip fora do MySQL: leia as assertions, não "0 failed" (LC-13).
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('Schema real do UltimatePOS (MySQL).');
    }
    if (! Schema::hasTable('advisors')) {
        $this->markTestSkipped('Tabela advisors ausente.');
    }
    $this->biz = $this->seededTenant();

    // O AdminSidebarMenu só chama o modifyAdminMenu de módulo instalado (`system.financeiro_version`).
    // Sem a chave no banco do CI, o menu do Financeiro nem é montado e os asserts leem uma lista vazia.
    if (empty(\App\System::getProperty('financeiro_version'))) {
        DB::table('system')->insert(['key' => 'financeiro_version', 'value' => 'teste']);
    }
});

function fmenuEntrar(object $test, array $permissoes): User
{
    $user = $test->usuarioComPermissoes(array_merge(['financeiro.access', 'financeiro.advisor.grant'], $permissoes), $test->biz);

    if (class_exists(\Modules\Superadmin\Entities\Subscription::class)) {
        $pacote = \Modules\Superadmin\Entities\Package::query()->first()
            ?: \Modules\Superadmin\Entities\Package::query()->forceCreate([
                'name' => 'Pacote de teste (Financeiro)', 'description' => 'fixture', 'location_count' => 0,
                'user_count' => 0, 'product_count' => 0, 'invoice_count' => 0, 'interval' => 'months',
                'interval_count' => 1, 'trial_days' => 0, 'price' => 0, 'custom_permissions' => [],
                'created_by' => $user->id, 'is_active' => 1,
            ]);
        $assinatura = \Modules\Superadmin\Entities\Subscription::active_subscription($test->biz->id)
            ?: \Modules\Superadmin\Entities\Subscription::query()->forceCreate([
                'business_id' => $test->biz->id, 'package_id' => $pacote->id,
                'start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addMonth()->toDateString(),
                'package_price' => 0, 'package_details' => [], 'created_id' => $user->id, 'status' => 'approved',
            ]);
        $assinatura->package_details = array_merge((array) $assinatura->package_details, ['financeiro_module' => 1]);
        $assinatura->save();
    }

    session(['user.business_id' => $test->biz->id, 'user.id' => $user->id, 'business.id' => $test->biz->id,
        'business' => ['id' => $test->biz->id, 'name' => 'Teste', 'currency_symbol' => 'R$']]);
    $test->actingAs($user);

    return $user;
}

/** Hrefs de todas as entradas e abas que chegaram à tela. */
function fmenuHrefs(object $test): array
{
    $resposta = $test->get('/financeiro/configuracoes/contador');
    $resposta->assertOk();

    $hrefs = [];
    $varrer = function (array $itens) use (&$varrer, &$hrefs) {
        foreach ($itens as $item) {
            $hrefs[] = (string) ($item['href'] ?? '');
            foreach ((array) ($item['ghosts'] ?? []) as $aba) {
                $hrefs[] = (string) ($aba['href'] ?? '');
            }
            $varrer((array) ($item['children'] ?? []));
        }
    };
    $varrer((array) data_get($resposta->viewData('page'), 'props.shell.menu', []));

    // A entrada do sidebar vem com url() (absoluta) e as abas com caminho; compara só o caminho.
    $hrefs = array_map(fn ($h) => (string) (parse_url($h, PHP_URL_PATH) ?? $h), $hrefs);

    return array_values(array_filter($hrefs, fn ($h) => str_contains($h, '/financeiro/') || str_contains($h, '/settings/payment-gateways')));
}

it('só com a permissão do contador, o menu mostra só o contador', function () {
    fmenuEntrar($this, []);

    $hrefs = fmenuHrefs($this);

    expect($hrefs)->toContain('/financeiro/configuracoes/contador')
        ->and($hrefs)->not->toContain('/financeiro/caixa')
        ->and($hrefs)->not->toContain('/financeiro/cobranca')
        ->and($hrefs)->not->toContain('/financeiro/unificado')
        ->and($hrefs)->not->toContain('/financeiro/dre')
        ->and($hrefs)->not->toContain('/settings/payment-gateways');
});

it('com relatórios, entram DRE, relatórios e contabilidade, e não as contas', function () {
    fmenuEntrar($this, ['financeiro.relatorios.view']);

    $hrefs = fmenuHrefs($this);

    expect($hrefs)->toContain('/financeiro/dre')
        ->and($hrefs)->toContain('/financeiro/relatorios')
        ->and($hrefs)->toContain('/financeiro/exportacao-contabil')
        ->and($hrefs)->not->toContain('/financeiro/contas-pagar')
        ->and($hrefs)->not->toContain('/financeiro/fluxo');
});

it('com o caixa, a entrada aparece sem as abas que dariam 403', function () {
    fmenuEntrar($this, ['view_cash_register']);

    $hrefs = fmenuHrefs($this);

    expect($hrefs)->toContain('/financeiro/caixa')
        ->and($hrefs)->not->toContain('/financeiro/conciliacao')
        ->and($hrefs)->not->toContain('/financeiro/contas-bancarias')
        ->and($hrefs)->not->toContain('/financeiro/extrato');
});

it('todo link que o menu mostra abre sem 403', function () {
    fmenuEntrar($this, ['financeiro.relatorios.view', 'financeiro.contas_pagar.view', 'view_cash_register']);

    foreach (array_unique(fmenuHrefs($this)) as $href) {
        expect($this->get($href)->status())->not->toBe(403, "o menu mostrou {$href}, que dá 403");
    }
});

it('o log do login do contador não carrega o e-mail', function () {
    $email = 'log.'.uniqid().'@teste.local';
    Advisor::create([
        'cnpj_contador' => (string) random_int(10000000000000, 99999999999999), 'nome' => 'Contador Log',
        'email' => $email, 'password_hash' => Hash::make('senha-correta-1'),
        'referral_code' => Advisor::generateReferralCode(), 'ativo' => true,
    ]);
    Log::spy();

    $this->withServerVariables(['REMOTE_ADDR' => '10.30.'.random_int(1, 250).'.'.random_int(1, 250)]);
    $this->from('/advisor/login')->post('/advisor/login', ['email' => $email, 'password' => 'senha-errada-1']);
    $this->from('/advisor/login')->post('/advisor/login', ['email' => $email, 'password' => 'senha-correta-1'])
        ->assertRedirect('/advisor');

    foreach (['warning', 'info'] as $nivel) {
        Log::shouldNotHaveReceived($nivel, fn ($mensagem, $contexto = []) => str_contains(
            (string) json_encode($contexto),
            $email
        ));
    }
});
