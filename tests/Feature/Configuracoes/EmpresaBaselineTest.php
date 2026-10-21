<?php

declare(strict_types=1);

use App\Business;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /business/settings — o comportamento da Blade ANTES da tela React.
 *
 * Thread sistema/playbook/08. Trava o que getBusinessSettings/postBusinessSettings fazem hoje, para a
 * F3 (ramo Inertia atrás da flag useV2ConfiguracoesEmpresa) não mudar nada disso sem um teste cair.
 * Mapa campo-a-campo: memory/requisitos/Configuracoes/empresa-parity.md (itens `alta`).
 *
 * O teste mais importante é o da SUBSTITUIÇÃO: o POST não é parcial. Checkbox ausente vira 0,
 * `common_settings` ausente vira [] e `enabled_modules` ausente vira null. Uma tela que mande só a
 * aba aberta apaga as outras — é isso que este teste deixa explícito para a F3.
 *
 * Tenant de teste 98 x cliente fictício 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('business') || ! Schema::hasColumn('business', 'enabled_modules')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['business_settings.access'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
    // Com `user` já na sessão o SetSessionData não roda, e o @num_format da Blade lê session('currency')
    // (500 "array offset on null" no settings_business:29). Mesma linha do ImpostosBaselineTest.
    session(['currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ',']]);
});

/** Corpo mínimo que o postBusinessSettings aceita sem cair no catch (chaves que ele lê sem `?? `). */
function empresaCorpo(Business $b, array $campos = []): array
{
    return array_merge([
        'name' => $b->name,
        'currency_id' => $b->currency_id,
        'time_zone' => $b->time_zone ?: 'America/Sao_Paulo',
        'fy_start_month' => $b->fy_start_month ?: 1,
        'accounting_method' => $b->accounting_method ?: 'fifo',
        'date_format' => $b->date_format ?: 'd/m/Y',
        'time_format' => $b->time_format ?: '24',
        'on_product_expiry' => 'keep_selling',
        'custom_labels' => [],
    ], $campos);
}

test('baseline: sem business_settings.access a tela e o salvar devolvem 403 e nada muda', function () {
    $semPermissao = $this->usuarioComPermissoes([], $this->business);
    $this->actingAs($semPermissao);
    $antes = DB::table('business')->where('id', $this->business->id)->value('name');

    $this->get('/business/settings')->assertForbidden();
    $this->post('/business/update', empresaCorpo($this->business, ['name' => 'Não grava']))->assertForbidden();

    expect(DB::table('business')->where('id', $this->business->id)->value('name'))->toBe($antes);
});

test('baseline: a tela abre a Blade com o negócio da sessão', function () {
    $r = $this->get('/business/settings');

    $r->assertOk();
    $r->assertViewIs('business.settings');
    expect((int) $r->viewData('business')->id)->toBe((int) $this->business->id);
});

test('baseline: salvar grava no negócio da sessão e não alcança outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $nomeOutro = DB::table('business')->where('id', $outro->id)->value('name');
    $novo = 'Empresa Baseline '.uniqid();

    $this->post('/business/update', empresaCorpo($this->business, ['name' => $novo]))
        ->assertRedirect()
        ->assertSessionHas('status.success', 1);

    expect(DB::table('business')->where('id', $this->business->id)->value('name'))->toBe($novo);
    expect(DB::table('business')->where('id', $outro->id)->value('name'))->toBe($nomeOutro);
});

test('baseline: salvar é substituição total — checkbox, common_settings e enabled_modules ausentes são zerados', function () {
    DB::table('business')->where('id', $this->business->id)->update([
        'enable_brand' => 1,
        'enable_category' => 1,
        'common_settings' => json_encode(['default_datatable_page_entries' => 50]),
        'enabled_modules' => json_encode(['purchases', 'expenses']),
    ]);

    // Contraprova: quando o corpo traz os campos, eles ficam.
    $this->post('/business/update', empresaCorpo($this->business, [
        'enable_brand' => 1, 'enable_category' => 1,
        'common_settings' => ['default_datatable_page_entries' => 50],
        'enabled_modules' => ['purchases', 'expenses'],
    ]))->assertSessionHas('status.success', 1);

    $linha = DB::table('business')->where('id', $this->business->id)->first();
    expect((int) $linha->enable_brand)->toBe(1);
    expect(json_decode($linha->enabled_modules, true))->toBe(['purchases', 'expenses']);

    // O corpo de uma aba só: tudo que não veio é sobrescrito.
    $this->post('/business/update', empresaCorpo($this->business))->assertSessionHas('status.success', 1);

    $linha = DB::table('business')->where('id', $this->business->id)->first();
    expect((int) $linha->enable_brand)->toBe(0);
    expect((int) $linha->enable_category)->toBe(0);
    expect(json_decode((string) $linha->common_settings, true))->toBe([]);
    expect($linha->enabled_modules)->toBeNull();
});

/**
 * A Blade edita 8 chaves de `common_settings`; outras features gravam as delas no mesmo JSON (régua de
 * cobrança, pesquisa de entrega, ISS retido, reserva de insumo, requisição por estoque mínimo). O salvar
 * da Blade só pode substituir as 8 que ela desenha — trocar o array inteiro apagava as outras.
 */
test('salvar pela Blade não apaga as chaves de common_settings que outras features gravam', function () {
    $deOutrasFeatures = [
        'financeiro_regua_cobranca' => ['ativo' => true],
        'sells_pesquisa_entrega' => ['ativo' => true],
        'financeiro_iss_retido_ajuste' => ['ativo' => true],
        'producao_reserva_insumo' => ['ativo' => true],
        'compras_requisicao_estoque_minimo' => ['ativo' => true],
    ];
    DB::table('business')->where('id', $this->business->id)->update([
        'common_settings' => json_encode($deOutrasFeatures + ['default_datatable_page_entries' => 25, 'enable_product_warranty' => '1']),
    ]);

    // O que a Blade manda: os campos de texto sempre, o checkbox só quando marcado (aqui, desmarcado).
    $this->post('/business/update', empresaCorpo($this->business, [
        'common_settings' => ['default_datatable_page_entries' => '50', 'default_credit_limit' => ''],
    ]))->assertSessionHas('status.success', 1);

    $gravado = json_decode((string) DB::table('business')->where('id', $this->business->id)->value('common_settings'), true);
    foreach ($deOutrasFeatures as $chave => $valor) {
        expect($gravado[$chave] ?? null)->toBe($valor);
    }
    expect($gravado['default_datatable_page_entries'])->toBe('50');
    // Desmarcar checkbox da Blade continua removendo a chave: mesclar tudo deixaria a garantia presa em ligada.
    expect($gravado)->not->toHaveKey('enable_product_warranty');
});

test('as chaves de common_settings que o salvar trata como da Blade são as que os parciais da Blade desenham', function () {
    $naBlade = [];
    foreach (glob(resource_path('views/business/partials/*.blade.php')) as $parcial) {
        preg_match_all('/common_settings\[([a-z0-9_]+)\]/', (string) file_get_contents($parcial), $m);
        $naBlade = array_merge($naBlade, $m[1]);
    }
    $naBlade = array_values(array_unique($naBlade));
    sort($naBlade);

    $doSalvar = \App\Http\Controllers\BusinessController::CHAVES_COMMON_DA_BLADE;
    sort($doSalvar);

    expect($naBlade)->not->toBe([]);
    expect($doSalvar)->toBe($naBlade);
});
