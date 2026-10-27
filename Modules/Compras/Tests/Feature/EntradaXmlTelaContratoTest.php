<?php

declare(strict_types=1);

// @covers-us US-COM-003 — a tela de conferência da NF-e do fornecedor (rotas, permissão, Tier 0).

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Modules\NfeBrasil\Models\NfeDfeItem;
use Modules\NfeBrasil\Models\NfeDfeRecebido;
use Spatie\Permission\Models\Permission;
use Tests\Support\WithSeededTenant;

/**
 * Âncora: resources/js/Pages/Compras/EntradaXml.casos.md (UC-ENTRAD-01..05) · RUNBOOK-entrada-xml.md ·
 * ADR `proposals/2026-10-06-entrada-xml-compra.md` D2/D3. Tenant 98, vizinho 99 (ADR 0358).
 * A conta da compra mora no ImportarDfeComoCompraContratoTest; aqui é a porta HTTP.
 */
uses(Tests\TestCase::class, DatabaseTransactions::class, WithSeededTenant::class);

beforeEach(function () {
    if (DB::getDriverName() !== 'mysql' || ! Schema::hasColumn('nfe_dfe_recebidos', 'origem') || ! Schema::hasTable('permissions')) {
        $this->markTestSkipped('precisa do schema MySQL com a fase 1 da entrada por XML');
    }
    $this->biz = $this->seededTenant()->id;
    $this->outra = $this->seededSupportClientTenant()->id;
    config(['nfebrasil.entrada_xml_enabled' => true]);
    Storage::fake(config('nfebrasil.dfes_recebidos_disk', 'local'));

    $this->user = User::factory()->create(['business_id' => $this->biz, 'user_type' => 'user', 'allow_login' => 1, 'username' => 'entxml_'.uniqid()]);
    $this->user->givePermissionTo(Permission::firstOrCreate(['name' => 'compras.import_xml', 'guard_name' => 'web']));

    $this->nota = function (int $biz, string $chave, string $nome): int {
        $id = NfeDfeRecebido::withoutGlobalScopes()->create([ // SUPERADMIN: fixture grava no tenant escolhido
            'business_id' => $biz, 'chave_44' => $chave, 'origem' => 'upload', 'cnpj_emitente' => '11222333000181', # pii-allowlist: fictício
            'nome_emitente' => $nome, 'valor_total' => 120, 'data_emissao' => now(), 'status_manifestacao' => 'pendente',
        ])->id;
        NfeDfeItem::gravarDaNota($biz, $id, [[
            'codigo_fornecedor' => 'TS-777', 'descricao' => 'Diluente 5L', 'ncm' => '38140000', 'cfop' => '5405',
            'cst_csosn' => '060', 'quantidade' => 2, 'valor_unitario' => 60, 'valor_total' => 120, 'unidade' => 'GL',
        ]]);

        return $id;
    };
});

it('UC-ENTRAD-01 · flag desligada responde 404 e sem a permissão responde 403', function () {
    config(['nfebrasil.entrada_xml_enabled' => false]);
    $this->actingAs($this->user)->get('/compras/entrada-xml')->assertNotFound();

    config(['nfebrasil.entrada_xml_enabled' => true]);
    $sem = User::factory()->create(['business_id' => $this->biz, 'user_type' => 'user', 'allow_login' => 1, 'username' => 'entxml_sem_'.uniqid()]);
    $this->actingAs($sem)->get('/compras/entrada-xml')->assertForbidden();
});

it('UC-ENTRAD-02 · lista só as notas da empresa e a conferência traz CFOP de entrada e ST retida', function () {
    $minha = ($this->nota)($this->biz, '42261011222333000181550010000077771000000001', 'Tintas Sul');
    ($this->nota)($this->outra, '42261011222333000181550010000077771000000002', 'Alheia Ltda');

    $this->actingAs($this->user)->get('/compras/entrada-xml?nota='.$minha)->assertOk()
        ->assertInertia(fn (AssertableInertia $p) => $p->component('Compras/EntradaXml')
            ->where('notas', fn ($n) => collect($n)->pluck('fornecedor')->contains('Tintas Sul') && ! collect($n)->pluck('fornecedor')->contains('Alheia Ltda'))
            ->where('nota.id', $minha)
            ->where('nota.itens.0.cfop_entrada', '1403')
            ->where('nota.itens.0.st_retida', true));
});

it('UC-ENTRAD-03 · XML de outra empresa volta com o motivo e nada é gravado', function () {
    DB::table('business')->where('id', $this->biz)->update(['cnpj' => '44555666000172']); # pii-allowlist: fictício
    $xml = '<NFe xmlns="http://www.portalfiscal.inf.br/nfe"><infNFe Id="NFe42261011222333000181550010000077771000000003">'
        .'<emit><CNPJ>11222333000181</CNPJ><xNome>X</xNome></emit><dest><CNPJ>99888777000166</CNPJ></dest>' # pii-allowlist: fictício
        .'<total><ICMSTot><vNF>1.00</vNF></ICMSTot></total></infNFe></NFe>';

    $this->actingAs($this->user)->post('/compras/entrada-xml/upload', ['arquivo' => UploadedFile::fake()->createWithContent('nota.xml', $xml)])
        ->assertSessionHasErrors(['arquivo' => 'Esta nota foi emitida para outro CNPJ, não para esta empresa.']);
    expect(DB::table('nfe_dfe_recebidos')->where('chave_44', '42261011222333000181550010000077771000000003')->count())->toBe(0);
});

it('UC-ENTRAD-04 · gerar com item sem produto volta com o motivo e não cria compra', function () {
    $id = ($this->nota)($this->biz, '42261011222333000181550010000077771000000004', 'Tintas Sul');
    $local = DB::table('business_locations')->where('business_id', $this->biz)->value('id');

    $this->actingAs($this->user)->post("/compras/entrada-xml/{$id}/gerar", ['location_id' => $local])
        ->assertSessionHasErrors('gerar');
    expect(NfeDfeRecebido::withoutGlobalScopes()->find($id)->transaction_id)->toBeNull(); // SUPERADMIN: leitura do fixture
});

it('UC-ENTRAD-05 · a busca de produto para o vínculo só devolve produtos da empresa', function () {
    $nome = 'ProdutoBuscaEntXml'.uniqid();
    foreach ([$this->biz, $this->outra] as $b) {
        $p = DB::table('products')->insertGetId(['business_id' => $b, 'name' => $nome, 'type' => 'single', 'unit_id' => DB::table('units')->value('id'),
            'sku' => 'EX'.uniqid(), 'enable_stock' => 1, 'barcode_type' => 'C128', 'created_by' => $this->user->id, 'created_at' => now(), 'updated_at' => now()]);
        $g = DB::table('product_variations')->insertGetId(['product_id' => $p, 'name' => 'DUMMY', 'is_dummy' => 1]);
        DB::table('variations')->insert(['product_id' => $p, 'product_variation_id' => $g, 'name' => 'DUMMY', 'sub_sku' => 'EX'.uniqid(), 'default_purchase_price' => 0, 'dpp_inc_tax' => 0, 'profit_percent' => 0, 'default_sell_price' => 0, 'sell_price_inc_tax' => 0]);
    }

    $r = $this->actingAs($this->user)->getJson('/compras/entrada-xml/produtos?q='.$nome)->assertOk()->json();
    expect($r)->toHaveCount(1);
});
