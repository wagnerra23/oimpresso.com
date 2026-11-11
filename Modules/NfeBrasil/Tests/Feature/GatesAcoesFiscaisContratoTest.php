<?php

declare(strict_types=1);

// @covers-us US-NFE-MANUAL + US-NFE-010 + US-NFE-041 + US-SELL-030 — lote 3.2 da auditoria (N5-04/N5-05):
// as ações que só exigiam login passam a exigir permissão (Modules/NfeBrasil/Http/Middleware/PermissaoNfe.php).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\NfeBrasil\Models\NfeEmissao;
use Spatie\Permission\Models\Role;
use Tests\Support\EstoqueFixture;

/**
 * Cada caso tem as duas metades: sem a permissão, 403 e nada muda; com a permissão, a
 * requisição chega ao controller — provado por uma resposta que só o controller produz
 * (404 `transaction_not_found`/`emissao_not_found` do guard cross-tenant, 422
 * `sem_certificado`, a gravação do toggle, o flash de erro do template). Status sozinho não
 * prova chegada (lápide §5 2026-09-27).
 *
 * MySQL · tenants 98 × 99 (ADR 0358). Fora do MySQL pula; leia assertions (LC-13).
 */
uses(Tests\TestCase::class, DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('nfe_emissoes') || ! Schema::hasTable('nfe_business_configs')) {
        $this->markTestSkipped('MySQL-only: precisa das tabelas do NfeBrasil.');
    }
    $this->biz = $this->seededTenant()->id;
    $this->outra = $this->seededSupportClientTenant()->id;

    $this->como = fn (array $perms) => $this->actingAs($this->usuarioComPermissoes($perms))
        ->withSession(['business.id' => $this->biz, 'user.business_id' => $this->biz]);

    $this->venda = fn (int $biz): int => (int) DB::table('transactions')->insertGetId([
        'business_id' => $biz, 'location_id' => EstoqueFixture::locationId($biz), 'type' => 'sell',
        'status' => 'final', 'payment_status' => 'paid', 'final_total' => 10.00, 'total_before_tax' => 10.00,
        'invoice_no' => 'GATE32-' . uniqid(), 'transaction_date' => now(), 'created_by' => EstoqueFixture::userId($biz),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->nota = fn (int $biz, int $tx): NfeEmissao => NfeEmissao::withoutGlobalScopes()->create([
        // SUPERADMIN: fixture do teste grava nos dois tenants de propósito.
        'business_id' => $biz, 'transaction_id' => $tx, 'modelo' => 65, 'serie' => '1',
        'numero' => random_int(900000, 999999), 'status' => 'autorizada', 'cstat' => '100', 'valor_total' => 10.00,
    ]);
});

it('3.2 · ligar a emissão automática exige nfe.tributacao.manage', function () {
    DB::table('nfe_business_configs')->updateOrInsert(['business_id' => $this->biz], ['auto_emission_enabled' => false, 'tributacao_default' => json_encode(['cfop' => '5102']), 'updated_at' => now()]);
    $valor = fn () => (bool) DB::table('nfe_business_configs')->where('business_id', $this->biz)->value('auto_emission_enabled');

    ($this->como)(['sell.create'])->post('/nfe-brasil/tributacao/auto-emission/toggle', ['enabled' => true])->assertForbidden();
    expect($valor())->toBeFalse();

    // Chegada ao controller: a escolha da empresa foi gravada.
    ($this->como)(['nfe.tributacao.manage'])->post('/nfe-brasil/tributacao/auto-emission/toggle', ['enabled' => true])->assertRedirect();
    expect($valor())->toBeTrue();
});

it('3.2 · aplicar template exige nfe.tributacao.manage', function () {
    $antes = DB::table('nfe_business_configs')->where('business_id', $this->biz)->first();

    ($this->como)(['sell.create'])->post('/nfe-brasil/tributacao/templates/nao-existe/aplicar')->assertForbidden();
    expect(DB::table('nfe_business_configs')->where('business_id', $this->biz)->first())->toEqual($antes);

    // Chegada ao controller: o template inexistente volta com o erro que só o serviço produz.
    ($this->como)(['nfe.tributacao.manage'])->post('/nfe-brasil/tributacao/templates/nao-existe/aplicar')
        ->assertRedirect()
        ->assertSessionHas('error', "Template tributário 'nao-existe' não encontrado.");
});

it('3.2 · listar as notas da venda exige quem vê a venda; a venda do 99 segue 404', function () {
    $minha = ($this->venda)($this->biz);
    $alheia = ($this->venda)($this->outra);

    ($this->como)(['product.view'])->getJson("/nfe-brasil/api/transactions/{$minha}/emissoes")->assertForbidden();

    ($this->como)(['sell.view'])->getJson("/nfe-brasil/api/transactions/{$minha}/emissoes")
        ->assertOk()->assertJsonPath('transaction_id', $minha);
    ($this->como)(['sell.view'])->getJson("/nfe-brasil/api/transactions/{$alheia}/emissoes")
        ->assertNotFound()->assertJsonPath('error', 'transaction_not_found');
});

it('3.2 · status NFC-e (JSON e tela) exige quem vê a venda', function () {
    $minha = ($this->venda)($this->biz);
    $alheia = ($this->venda)($this->outra);
    ($this->nota)($this->outra, $alheia);

    ($this->como)(['product.view'])->getJson("/nfe-brasil/api/transactions/{$minha}/nfe-status")->assertForbidden();
    ($this->como)(['product.view'])->get("/nfe-brasil/transactions/{$minha}/status")->assertForbidden();

    // Chegada ao controller: a nota do 99 não aparece para o 98 (status null + mensagem do controller).
    ($this->como)(['nfebrasil.consult.view'])->getJson("/nfe-brasil/api/transactions/{$alheia}/nfe-status")
        ->assertOk()->assertJsonPath('status', null)->assertJsonPath('transaction_id', $alheia);
    ($this->como)(['sell.view'])->get("/nfe-brasil/transactions/{$minha}/status")->assertOk();
});

it('3.2 · DANFE exige quem vê a nota; a nota do 99 segue 404', function () {
    $alheia = ($this->nota)($this->outra, ($this->venda)($this->outra));

    ($this->como)(['product.view'])->get("/nfe-brasil/emissoes/{$alheia->id}/danfe-pdf")->assertForbidden();
    ($this->como)(['fiscal.nfe.view'])->get("/nfe-brasil/emissoes/{$alheia->id}/danfe-pdf")->assertNotFound();
});

it('3.2 · reenviar DANFE por e-mail exige quem emite; a nota do 99 segue 404', function () {
    $alheia = ($this->nota)($this->outra, ($this->venda)($this->outra));

    // Só ver a venda não basta para mandar e-mail ao cliente.
    ($this->como)(['sell.view'])->postJson("/nfe-brasil/emissoes/{$alheia->id}/reenviar-email")->assertForbidden();
    ($this->como)(['nfebrasil.emit.manage'])->postJson("/nfe-brasil/emissoes/{$alheia->id}/reenviar-email")
        ->assertNotFound()->assertJsonPath('error', 'emissao_not_found');
});

it('3.2 · testar o certificado na SEFAZ exige a configuração fiscal', function () {
    DB::table('nfe_certificados')->where('business_id', $this->biz)->update(['ativo' => false]);

    ($this->como)(['sell.create'])->postJson('/nfe-brasil/configuracao/certificado/testar')->assertForbidden();
    // Chegada ao controller: sem A1 ativo, o controller recusa antes de chamar a SEFAZ.
    ($this->como)(['nfe.configuracao.manage'])->postJson('/nfe-brasil/configuracao/certificado/testar')
        ->assertStatus(422)->assertJsonPath('error', 'sem_certificado');
});

it('3.2 · a lista de inutilizações exige o papel fiscal.inutilizar ou consultar notas, e só mostra o 98', function () {
    $linha = fn (int $biz) => DB::table('nfe_inutilizacoes')->insertGetId([
        'business_id' => $biz, 'modelo' => '55', 'serie' => '9', 'numero_de' => 1, 'numero_ate' => 2,
        'justificativa' => 'Teste do lote 3.2 da auditoria', 'status' => 'pendente', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $minha = $linha($this->biz);
    $alheia = $linha($this->outra);

    ($this->como)(['sell.create'])->getJson('/nfe-brasil/inutilizacoes?serie=9')->assertForbidden();

    $u = $this->usuarioComPermissoes([]);
    $u->assignRole(Role::firstOrCreate(['name' => "fiscal.inutilizar#{$this->biz}", 'business_id' => $this->biz, 'guard_name' => 'web']));
    $ids = $this->actingAs($u)->withSession(['business.id' => $this->biz, 'user.business_id' => $this->biz])
        ->getJson('/nfe-brasil/inutilizacoes?serie=9')->assertOk()->json('data.*.id');

    expect($ids)->toContain($minha)->not->toContain($alheia);
});
