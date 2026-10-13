<?php

declare(strict_types=1);
// Cobre UC-EMP-01, UC-EMP-02, UC-EMP-03, UC-EMP-04, UC-EMP-05, UC-EMP-06, UC-EMP-07, UC-EMP-08, UC-EMP-09, UC-EMP-10, UC-EMP-11, UC-EMP-12 (Configuracoes/Empresa/Index.casos.md).

use App\Business;
use App\Http\Controllers\BusinessController;
use App\Utils\BusinessUtil;
use App\Utils\Util;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thread sistema/playbook/08, F3-1 — /business/settings em Inertia atrás da flag useV2ConfiguracoesEmpresa.
 *
 * O ponto de risco é o salvar: postBusinessSettings não é parcial (EmpresaBaselineTest). A tela devolve o
 * `corpo` que o servidor montou; estes testes provam que devolver o corpo sem mexer não muda nenhum valor
 * — inclusive margem e pontos, que passam por num_uf (regra mestre de valor, proibicoes.md).
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'enabled_modules')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['business_settings.access'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'business.date_format' => 'd/m/Y', 'business.enabled_modules' => []]);
    session(['currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
    config(['feature-flags.forced_on' => 'useV2ConfiguracoesEmpresa']);
});

/** O que o objectToFormData do Inertia faz com o corpo: bool → "1"/"0", null → "", vazio some. */
function empFormData(array $v): array
{
    $saida = [];
    foreach ($v as $k => $x) {
        if (is_array($x)) {
            $x = empFormData($x);
            if ($x === []) {
                continue;
            }
        } elseif (is_bool($x)) {
            $x = $x ? '1' : '0';
        } elseif ($x === null) {
            $x = '';
        } else {
            $x = (string) $x;
        }
        $saida[$k] = $x;
    }

    return $saida;
}

function empCorpo($teste): array
{
    $r = $teste->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $teste->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');
    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Empresa/Index');

    return $r->json('props.corpo');
}

const EMP_COLUNAS = ['name', 'currency_id', 'start_date', 'default_profit_percent', 'default_sales_discount', 'amount_for_unit_rp',
    'min_order_total_for_rp', 'redeem_amount_per_unit_rp', 'min_order_total_for_redeem', 'enable_rp', 'enable_tooltip',
    'enable_product_expiry', 'stock_expiry_alert_days', 'on_product_expiry', 'enable_brand', 'enable_category', 'enable_racks',
    'purchase_in_diff_currency', 'time_zone', 'date_format', 'currency_precision', 'quantity_precision', 'code_1', 'code_label_1',
    'tax_label_1', 'tax_number_1', 'sku_prefix', 'expiry_type', 'stop_selling_before', 'enable_inline_tax', 'enable_sub_units'];
const EMP_JSON = ['pos_settings', 'common_settings', 'enabled_modules', 'custom_labels', 'keyboard_shortcuts', 'email_settings', 'sms_settings'];

function empLinha(int $id): array
{
    $l = (array) DB::table('business')->where('id', $id)->first();

    return array_merge(
        collect(EMP_COLUNAS)->mapWithKeys(fn ($c) => [$c => is_numeric($l[$c]) ? (float) $l[$c] : $l[$c]])->all(),
        collect(EMP_JSON)->mapWithKeys(fn ($c) => [$c => json_decode((string) $l[$c], true)])->all(),
    );
}

test('UC-EMP-01 com a flag ligada a tela abre em Inertia e o corpo não leva a senha do e-mail', function () {
    DB::table('business')->where('id', $this->business->id)->update(['email_settings' => json_encode(['mail_host' => 'smtp.empresa98.test', 'mail_password' => 'segredo-emp-98'])]);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Empresa/Index');
    // Desde a F3-5a o corpo traz e-mail e SMS para a aba Integrações, mas sem os segredos (UC-EMP-12).
    expect($r->json('props.corpo.email_settings.mail_host'))->toBe('smtp.empresa98.test');
    expect($r->json('props.corpo.email_settings'))->not->toHaveKey('mail_password');
    expect($r->getContent())->not->toContain('segredo-emp-98');
});

test('UC-EMP-02 com a flag desligada, ou com ?classico=1, a rota segue na Blade', function () {
    $this->get('/business/settings?classico=1')->assertOk()->assertViewIs('business.settings');

    config(['feature-flags.forced_on' => '']);
    $this->get('/business/settings')->assertOk()->assertViewIs('business.settings');
});

test('UC-EMP-03 o corpo é do negócio da sessão', function () {
    $outro = $this->seededSupportClientTenant();
    DB::table('business')->where('id', $this->business->id)->update(['name' => 'Empresa 98 '.uniqid()]);
    $meu = DB::table('business')->where('id', $this->business->id)->value('name');

    $corpo = empCorpo($this);

    expect($corpo['name'])->toBe($meu);
    expect($corpo['name'])->not->toBe(DB::table('business')->where('id', $outro->id)->value('name'));
});

test('UC-EMP-04 devolver o corpo sem mexer não muda nada; trocar o nome só troca o nome', function () {
    DB::table('business')->where('id', $this->business->id)->update([
        'start_date' => '2021-03-01', 'default_profit_percent' => 25.5, 'default_sales_discount' => 12.25,
        'enable_rp' => 1, 'amount_for_unit_rp' => 0, 'min_order_total_for_rp' => 1234.5,
        'redeem_amount_per_unit_rp' => 0.25, 'min_order_total_for_redeem' => 2500,
        'enable_brand' => 1, 'enable_category' => 0, 'enable_racks' => 1, 'enable_tooltip' => 1, 'enable_product_expiry' => 0,
        'stock_expiry_alert_days' => 45, 'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null, 'expiry_type' => 'add_expiry',
        'tax_label_1' => 'Inscrição', 'tax_number_1' => 'NUM-FISCAL-98', 'sku_prefix' => 'EMP98', 'enable_inline_tax' => 1, 'enable_sub_units' => 0, 'purchase_in_diff_currency' => 0, 'date_format' => 'd/m/Y',
        // Completo como a Blade sempre manda: o salvar preenche com os padrões as chaves que faltam.
        'pos_settings' => json_encode(array_merge(app(\App\Utils\BusinessUtil::class)->defaultPosSettings(), ['disable_draft' => '1'])),
        'common_settings' => json_encode(['is_enabled_export' => '1', 'default_datatable_page_entries' => '50', 'default_credit_limit' => '1.500,00', 'enable_product_warranty' => '1']),
        'enabled_modules' => json_encode(['purchases', 'expenses']),
        'custom_labels' => json_encode(['payments' => ['custom_pay_1' => 'Pix']]),
        'keyboard_shortcuts' => json_encode(['pos' => ['express_checkout' => 'shift+e']]),
        'email_settings' => json_encode(['mail_password' => 'segredo-emp-98']),
        'sms_settings' => json_encode(['nexmo_secret' => 'segredo-sms-98']),
    ]);
    $antes = empLinha($this->business->id);

    $this->post('/business/update', empFormData(empCorpo($this)))->assertSessionHas('status.success', 1);
    expect(empLinha($this->business->id))->toEqual($antes);

    $this->post('/business/update', empFormData(array_merge(empCorpo($this), ['name' => 'Nome trocado 98'])))->assertSessionHas('status.success', 1);
    expect(empLinha($this->business->id))->toEqual(array_merge($antes, ['name' => 'Nome trocado 98']));
});

test('UC-EMP-05 número do corpo é lido pelo num_uf exatamente como está gravado', function () {
    $controller = app(BusinessController::class);
    $corpoDoSalvar = (new ReflectionMethod($controller, 'corpoDoSalvar'));
    $util = new Util;

    // 25000 e 1.5 com precisão 0 são os casos em que o @num_format da Blade não volta igual.
    foreach ([0, 0.25, 1, 1.5, 25.5, 99.99, 1234.5, 2500, 25000, 123456.7891] as $valor) {
        $b = new Business(['default_profit_percent' => $valor, 'default_sales_discount' => $valor, 'amount_for_unit_rp' => $valor, 'currency_precision' => 0]);
        $corpo = $corpoDoSalvar->invoke($controller, $b);

        expect($util->num_uf($corpo['default_profit_percent']))->toEqual((float) $valor);
        expect($util->num_uf($corpo['default_sales_discount']))->toEqual((float) $valor);
        expect($util->num_uf($corpo['amount_for_unit_rp']))->toEqual((float) $valor);
        expect($corpo['amount_for_unit_rp'])->not->toBe('0', 'empty("0") faria o salvar gravar 1 nos pontos');
    }
});

test('UC-EMP-06 editar as abas Impostos, Produtos e Contatos grava só o que mudou', function () {
    DB::table('business')->where('id', $this->business->id)->update([
        'enable_rp' => 0, 'default_profit_percent' => 25.5, 'enable_brand' => 1, 'enable_racks' => 0, 'date_format' => 'd/m/Y',
        // "Manter vendendo" sempre anula stop_selling_before no salvar; fixo aqui para o estado inicial não vir do seed.
        'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null,
        'common_settings' => json_encode(['is_enabled_export' => '1']),
        'email_settings' => json_encode(['mail_password' => 'segredo-emp-98']),
    ]);
    $antes = empLinha($this->business->id);
    $corpo = empCorpo($this);

    $corpo['tax_label_1'] = 'Inscrição 98';
    $corpo['sku_prefix'] = 'NOVO98';
    $corpo['enable_racks'] = true;
    $corpo['enable_brand'] = false;
    // O limite vai como o operador digitou: a Blade grava o texto e o cadastro de contato o lê como sugestão.
    $corpo['common_settings'] = ['is_enabled_export' => '1', 'default_credit_limit' => '2.000,50', 'enable_product_warranty' => '1'];

    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);

    expect(empLinha($this->business->id))->toEqual(array_merge($antes, [
        'tax_label_1' => 'Inscrição 98', 'sku_prefix' => 'NOVO98', 'enable_racks' => 1.0, 'enable_brand' => 0.0,
        'common_settings' => ['is_enabled_export' => '1', 'default_credit_limit' => '2.000,50', 'enable_product_warranty' => '1'],
    ]));
});

test('UC-EMP-07 chave secreta de pagamento não vai para a tela e o salvar da tela a preserva', function () {
    $pos = array_merge(app(BusinessUtil::class)->defaultPosSettings(), [
        'stripe_public_key' => 'pk_publica_98', 'stripe_secret_key' => 'sk_segredo_98', 'razor_pay_key_secret' => 'rz_segredo_98',
    ]);
    DB::table('business')->where('id', $this->business->id)->update(['pos_settings' => json_encode($pos), 'date_format' => 'd/m/Y']);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');
    expect($r->getContent())->not->toContain('sk_segredo_98');
    expect($r->getContent())->not->toContain('rz_segredo_98');
    expect($r->json('props.corpo.pos_settings.stripe_public_key'))->toBe('pk_publica_98');

    $corpo = $r->json('props.corpo');
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    $gravado = json_decode((string) DB::table('business')->where('id', $this->business->id)->value('pos_settings'), true);
    expect($gravado['stripe_secret_key'])->toBe('sk_segredo_98');
    expect($gravado['razor_pay_key_secret'])->toBe('rz_segredo_98');

    // Controle: a Blade manda a chave (vazia vira null) e assim limpa — a preservação vale só para a chave AUSENTE.
    $corpo['pos_settings']['stripe_secret_key'] = '';
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    $gravado = json_decode((string) DB::table('business')->where('id', $this->business->id)->value('pos_settings'), true);
    expect($gravado['stripe_secret_key'] ?? null)->toBeNull();
    expect($gravado['razor_pay_key_secret'])->toBe('rz_segredo_98');
});

test('UC-EMP-08 editar Vendas e Pagamento grava só o que mudou e desmarcar remove a chave de pos_settings', function () {
    $pos = array_merge(app(BusinessUtil::class)->defaultPosSettings(), [
        'allow_overselling' => '1', 'stripe_secret_key' => 'sk_segredo_98', 'cash_denominations' => '100,50',
    ]);
    DB::table('business')->where('id', $this->business->id)->update([
        'pos_settings' => json_encode($pos), 'date_format' => 'd/m/Y', 'default_sales_discount' => 0,
        'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null, 'sell_price_tax' => 'includes',
    ]);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');
    // A tela sabe QUE a chave existe, sem receber o valor.
    expect($r->json('props.segredos_gravados'))->toBe(['stripe_secret_key']);
    $corpo = $r->json('props.corpo');
    $antes = empLinha($this->business->id);
    $mudou = ['pos_settings' => 1, 'default_sales_discount' => 1];

    $corpo['default_sales_discount'] = '7,5';
    $corpo['sell_price_tax'] = 'excludes';
    unset($corpo['pos_settings']['allow_overselling']);
    $corpo['pos_settings']['enable_msp'] = '1';
    $corpo['pos_settings']['amount_rounding_method'] = '0.05';
    $corpo['pos_settings']['enable_cash_denomination_for_payment_methods'] = ['cash', 'card'];

    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);

    $depois = empLinha($this->business->id);
    expect((float) DB::table('business')->where('id', $this->business->id)->value('default_sales_discount'))->toBe(7.5);
    expect(DB::table('business')->where('id', $this->business->id)->value('sell_price_tax'))->toBe('excludes');
    expect($depois['pos_settings'])->not->toHaveKey('allow_overselling');
    expect($depois['pos_settings']['enable_msp'])->toBe('1');
    expect($depois['pos_settings']['amount_rounding_method'])->toBe('0.05');
    expect($depois['pos_settings']['enable_cash_denomination_for_payment_methods'])->toBe(['cash', 'card']);
    expect($depois['pos_settings']['cash_denominations'])->toBe('100,50');
    expect($depois['pos_settings']['stripe_secret_key'])->toBe('sk_segredo_98');
    expect(array_diff_key($depois, $mudou))->toEqual(array_diff_key($antes, $mudou));
});

test('UC-EMP-09 PDV: balança e atalhos voltam iguais; desmarcar opção com padrão volta 0; a balança grava o índice', function () {
    $balanca = ['label_prefix' => '21', 'product_sku_length' => '4', 'qty_length' => '2', 'qty_length_decimal' => '2'];
    DB::table('business')->where('id', $this->business->id)->update([
        'date_format' => 'd/m/Y', 'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null,
        'weighing_scale_setting' => json_encode($balanca),
        'keyboard_shortcuts' => json_encode(['pos' => ['express_checkout' => 'shift+e', 'draft' => 'shift+d']]),
        'pos_settings' => json_encode(array_merge(app(BusinessUtil::class)->defaultPosSettings(), ['disable_draft' => '1', 'enable_weighing_scale' => '1'])),
    ]);
    $antes = empLinha($this->business->id);

    // 1) Devolver o corpo sem mexer: balança e atalhos idênticos.
    $corpo = empCorpo($this);
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    $depois = empLinha($this->business->id);
    expect(json_decode((string) DB::table('business')->where('id', $this->business->id)->value('weighing_scale_setting'), true))->toEqual($balanca);
    expect($depois['keyboard_shortcuts'])->toEqual($antes['keyboard_shortcuts']);
    expect($depois['pos_settings'])->toEqual($antes['pos_settings']);

    // 2) Editar: o seletor "6 dígitos de SKU" grava o índice 5 (o SellPosController lê índice + 1).
    $corpo = empCorpo($this);
    $corpo['weighing_scale_setting']['product_sku_length'] = '5';
    $corpo['shortcuts']['pos']['draft'] = 'ctrl+d';
    unset($corpo['pos_settings']['disable_draft']);
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);

    $gravada = json_decode((string) DB::table('business')->where('id', $this->business->id)->value('weighing_scale_setting'), true);
    expect($gravada)->toEqual(array_merge($balanca, ['product_sku_length' => '5']));
    $linha = empLinha($this->business->id);
    expect($linha['keyboard_shortcuts']['pos'])->toEqual(['express_checkout' => 'shift+e', 'draft' => 'ctrl+d']);
    // disable_draft tem padrão 0: desmarcar na Blade também remove a chave e o salvar repõe 0.
    expect((string) $linha['pos_settings']['disable_draft'])->toBe('0');
    expect($linha['pos_settings']['enable_weighing_scale'])->toBe('1');
});

test('UC-EMP-10 Compras: taxa de câmbio volta igual, grava o digitado e desligar zera moeda e taxa como a Blade', function () {
    $moeda = (int) DB::table('business')->where('id', $this->business->id)->value('currency_id');
    DB::table('business')->where('id', $this->business->id)->update([
        'date_format' => 'd/m/Y', 'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null,
        'purchase_in_diff_currency' => 1, 'purchase_currency_id' => $moeda, 'p_exchange_rate' => 5.125,
        'enable_lot_number' => 1, 'common_settings' => json_encode(['enable_purchase_order' => '1']),
    ]);
    $taxa = fn () => (float) DB::table('business')->where('id', $this->business->id)->value('p_exchange_rate');

    // 1) Sem editar: taxa, moeda e as opções ficam iguais.
    $antes = empLinha($this->business->id);
    $this->post('/business/update', empFormData(empCorpo($this)))->assertSessionHas('status.success', 1);
    expect($taxa())->toBe(5.125);
    expect((int) DB::table('business')->where('id', $this->business->id)->value('purchase_currency_id'))->toBe($moeda);
    expect(empLinha($this->business->id))->toEqual($antes);

    // 2) Editar: o campo é type=number, então chega com ponto decimal e o salvar grava cru.
    $corpo = empCorpo($this);
    $corpo['p_exchange_rate'] = '5.25';
    $corpo['common_settings']['enable_purchase_requisition'] = '1';
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    expect($taxa())->toBe(5.25);
    expect(empLinha($this->business->id)['common_settings'])->toEqual(['enable_purchase_order' => '1', 'enable_purchase_requisition' => '1']);

    // 3) Desligar outra moeda: como na Blade, moeda vazia e taxa 1.
    $corpo = empCorpo($this);
    $corpo['purchase_in_diff_currency'] = false;
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    expect($taxa())->toBe(1.0);
    expect(DB::table('business')->where('id', $this->business->id)->value('purchase_currency_id'))->toBeNull();
});

test('UC-EMP-11 Sistema e Prefixos: voltam iguais; trocar um prefixo não apaga os outros', function () {
    DB::table('business')->where('id', $this->business->id)->update([
        'date_format' => 'd/m/Y', 'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null,
        'theme_color' => 'purple', 'stock_expiry_alert_days' => 45, 'enable_tooltip' => 1,
        'ref_no_prefixes' => json_encode(['purchase' => 'CP', 'sell_return' => 'DV']),
        'common_settings' => json_encode(['default_datatable_page_entries' => '50']),
    ]);
    $prefixos = fn () => json_decode((string) DB::table('business')->where('id', $this->business->id)->value('ref_no_prefixes'), true);
    $antes = empLinha($this->business->id);

    $this->post('/business/update', empFormData(empCorpo($this)))->assertSessionHas('status.success', 1);
    expect(empLinha($this->business->id))->toEqual($antes);
    expect(DB::table('business')->where('id', $this->business->id)->value('theme_color'))->toBe('purple');
    expect($prefixos())->toEqual(['purchase' => 'CP', 'sell_return' => 'DV']);

    // A tela manda o objeto completo ao editar, como a Blade (17 chaves, vazio onde não há).
    $corpo = empCorpo($this);
    $completo = array_fill_keys(['purchase', 'purchase_return', 'purchase_requisition', 'purchase_order', 'stock_transfer',
        'stock_adjustment', 'sell_return', 'expense', 'contacts', 'purchase_payment', 'sell_payment', 'expense_payment',
        'business_location', 'username', 'subscription', 'draft', 'sales_order'], '');
    $corpo['ref_no_prefixes'] = array_merge($completo, $corpo['ref_no_prefixes'], ['purchase' => 'COMP']);
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);

    $gravado = $prefixos();
    expect($gravado['purchase'])->toBe('COMP');
    expect($gravado['sell_return'])->toBe('DV');
    expect(empLinha($this->business->id)['common_settings'])->toEqual(['default_datatable_page_entries' => '50']);
});

test('UC-EMP-12 E-mail e SMS: segredo não vai para a tela, salvar da tela o preserva e só troca o que foi digitado', function () {
    DB::table('business')->where('id', $this->business->id)->update([
        'date_format' => 'd/m/Y', 'on_product_expiry' => 'keep_selling', 'stop_selling_before' => null,
        'email_settings' => json_encode(['mail_driver' => 'smtp', 'mail_host' => 'smtp.empresa98.test', 'mail_password' => 'senha-emp-98']),
        'sms_settings' => json_encode(['sms_service' => 'other', 'url' => 'https://sms.empresa98.test', 'header_1' => 'Authorization',
            'header_val_1' => 'Bearer token-emp-98', 'nexmo_secret' => 'nx-emp-98']),
    ]);
    $gravado = fn (string $c) => json_decode((string) DB::table('business')->where('id', $this->business->id)->value($c), true);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');
    foreach (['senha-emp-98', 'token-emp-98', 'nx-emp-98'] as $segredo) {
        expect($r->getContent())->not->toContain($segredo);
    }
    expect($r->json('props.segredos_gravados'))->toContain('email.mail_password');
    expect($r->json('props.segredos_gravados'))->toContain('sms.header_val_1');
    expect($r->json('props.corpo.sms_settings.header_1'))->toBe('Authorization');

    // 1) Sem editar: tudo continua gravado.
    $corpo = $r->json('props.corpo');
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    expect($gravado('email_settings')['mail_password'])->toBe('senha-emp-98');
    expect($gravado('sms_settings')['header_val_1'])->toBe('Bearer token-emp-98');
    expect($gravado('sms_settings')['nexmo_secret'])->toBe('nx-emp-98');

    // 2) Trocar o servidor e digitar outra senha: muda só isso.
    $corpo['email_settings']['mail_host'] = 'smtp2.empresa98.test';
    $corpo['email_settings']['mail_password'] = 'senha-nova-98';
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    expect($gravado('email_settings')['mail_host'])->toBe('smtp2.empresa98.test');
    expect($gravado('email_settings')['mail_password'])->toBe('senha-nova-98');
    expect($gravado('sms_settings')['header_val_1'])->toBe('Bearer token-emp-98');

    // Controle: a Blade manda a chave (vazia vira null) e assim limpa — a preservação vale só para a chave AUSENTE.
    $corpo['email_settings']['mail_password'] = '';
    $this->post('/business/update', empFormData($corpo))->assertSessionHas('status.success', 1);
    expect($gravado('email_settings')['mail_password'] ?? null)->toBeNull();
});
