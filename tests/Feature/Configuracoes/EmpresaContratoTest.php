<?php

declare(strict_types=1);
// Cobre UC-EMP-01, UC-EMP-02, UC-EMP-03, UC-EMP-04, UC-EMP-05 (Configuracoes/Empresa/Index.casos.md).

use App\Business;
use App\Http\Controllers\BusinessController;
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
    'purchase_in_diff_currency', 'time_zone', 'date_format', 'currency_precision', 'quantity_precision', 'code_1', 'code_label_1'];
const EMP_JSON = ['pos_settings', 'common_settings', 'enabled_modules', 'custom_labels', 'keyboard_shortcuts', 'email_settings', 'sms_settings'];

function empLinha(int $id): array
{
    $l = (array) DB::table('business')->where('id', $id)->first();

    return array_merge(
        collect(EMP_COLUNAS)->mapWithKeys(fn ($c) => [$c => is_numeric($l[$c]) ? (float) $l[$c] : $l[$c]])->all(),
        collect(EMP_JSON)->mapWithKeys(fn ($c) => [$c => json_decode((string) $l[$c], true)])->all(),
    );
}

test('UC-EMP-01 com a flag ligada a tela abre em Inertia e o corpo não leva e-mail nem SMS', function () {
    DB::table('business')->where('id', $this->business->id)->update(['email_settings' => json_encode(['mail_password' => 'segredo-emp-98'])]);

    $r = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/business/settings');

    $r->assertOk();
    expect($r->json('component'))->toBe('Configuracoes/Empresa/Index');
    expect($r->json('props.corpo'))->not->toHaveKeys(['email_settings', 'sms_settings']);
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
        'stock_expiry_alert_days' => 45, 'on_product_expiry' => 'keep_selling', 'purchase_in_diff_currency' => 0, 'date_format' => 'd/m/Y',
        // Completo como a Blade sempre manda: o salvar preenche com os padrões as chaves que faltam.
        'pos_settings' => json_encode(array_merge(app(\App\Utils\BusinessUtil::class)->defaultPosSettings(), ['disable_draft' => '1'])),
        'common_settings' => json_encode(['is_enabled_export' => '1', 'default_datatable_page_entries' => '50']),
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
        $b = new Business(['default_profit_percent' => $valor, 'amount_for_unit_rp' => $valor, 'currency_precision' => 0]);
        $corpo = $corpoDoSalvar->invoke($controller, $b);

        expect($util->num_uf($corpo['default_profit_percent']))->toEqual((float) $valor);
        expect($util->num_uf($corpo['amount_for_unit_rp']))->toEqual((float) $valor);
        expect($corpo['amount_for_unit_rp'])->not->toBe('0', 'empty("0") faria o salvar gravar 1 nos pontos');
    }
});
