<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Baseline F2 (MWART, ADR 0104) de /account/account — o que a Blade lista e grava ANTES da tela React.
 *
 * Thread sistema/playbook/06 (Contas). O saldo da conta é valor (regra mestre): o caso de saldo prova o mesmo
 * número por dois caminhos — a coluna da DataTable e a soma crédito − débito feita à mão sobre o fixture. O saldo
 * de abertura do cadastro passa pelo `num_uf`, provado endpoint × `num_uf` direto.
 * Mapa: memory/requisitos/Configuracoes/contas-parity.md.
 *
 * Prefixo `contasBase` nas funções (o escopo global do Pest é compartilhado). Tenant 98 x 99 (ADR 0358).
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('accounts') || ! Schema::hasTable('account_transactions')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['account.access', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    // A coluna de saldo usa num_f, que lê session('currency') — o SetSessionData do login real preenche.
    session([
        'user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id,
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

function contasBaseConta(int $businessId, int $criadoPor, array $campos = []): int
{
    return DB::table('accounts')->insertGetId(array_merge([
        'business_id' => $businessId, 'name' => 'Conta '.uniqid(), 'account_number' => 'N'.uniqid(),
        'created_by' => $criadoPor, 'is_closed' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

function contasBaseLancamento(int $contaId, int $criadoPor, string $tipo, float $valor, bool $apagado = false): void
{
    DB::table('account_transactions')->insert([
        'account_id' => $contaId, 'type' => $tipo, 'amount' => $valor, 'operation_date' => now(),
        'created_by' => $criadoPor, 'deleted_at' => $apagado ? now() : null, 'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Linhas da DataTable, por nome da conta. */
function contasBaseLista($teste, string $status = 'active'): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders($teste->ajax)->get('/account/account?account_status='.$status);
    $r->assertOk();

    return collect($r->json('data'))->keyBy(fn ($l) => strip_tags((string) $l['name']));
}

test('baseline: a lista (DataTable) traz só as contas do negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = 'Caixa Meu '.uniqid();
    $alheia = 'Caixa Alheio '.uniqid();
    contasBaseConta($this->business->id, $this->user->id, ['name' => $minha]);
    contasBaseConta($outro->id, $this->user->id, ['name' => $alheia]);

    $nomes = contasBaseLista($this)->keys()->implode('|');

    expect($nomes)->toContain($minha);
    expect($nomes)->not->toContain($alheia);
});

test('baseline valor: o saldo é crédito menos débito, sem os lançamentos apagados — por dois caminhos', function () {
    $nome = 'Banco Saldo '.uniqid();
    $id = contasBaseConta($this->business->id, $this->user->id, ['name' => $nome]);
    contasBaseLancamento($id, $this->user->id, 'credit', 1000.50);
    contasBaseLancamento($id, $this->user->id, 'credit', 99.75);
    contasBaseLancamento($id, $this->user->id, 'debit', 200.25);
    contasBaseLancamento($id, $this->user->id, 'credit', 999.00, apagado: true);

    // Caminho 1: à mão. 1000,50 + 99,75 − 200,25 = 900,00 (o crédito apagado de 999,00 não entra).
    $aMao = 1000.50 + 99.75 - 200.25;
    expect(round($aMao, 2))->toBe(900.0);

    // Caminho 2: a coluna da DataTable — o número cru fica no data-orig-value, o texto já vem formatado pelo num_f.
    $celula = (string) contasBaseLista($this)[$nome]['balance'];
    preg_match('/data-orig-value="([^"]*)"/', $celula, $m);
    expect(round((float) $m[1], 2))->toBe(round($aMao, 2));
    expect(strip_tags($celula))->toContain('900,00');
});

test('baseline: o filtro de situação separa contas ativas e encerradas', function () {
    $ativa = 'Ativa '.uniqid();
    $fechada = 'Fechada '.uniqid();
    contasBaseConta($this->business->id, $this->user->id, ['name' => $ativa]);
    contasBaseConta($this->business->id, $this->user->id, ['name' => $fechada, 'is_closed' => 1]);

    $ativas = contasBaseLista($this, 'active')->keys()->implode('|');
    $encerradas = contasBaseLista($this, 'closed')->keys()->implode('|');

    expect($ativas)->toContain($ativa);
    expect($ativas)->not->toContain($fechada);
    expect($encerradas)->toContain($fechada);
    expect($encerradas)->not->toContain($ativa);
});

test('baseline: sem acesso a todos os locais, só aparecem as contas de pagamento dos locais permitidos', function () {
    $ligada = contasBaseConta($this->business->id, $this->user->id, ['name' => 'Ligada '.uniqid()]);
    $solta = contasBaseConta($this->business->id, $this->user->id, ['name' => 'Solta '.uniqid()]);
    $esq = DB::table('invoice_schemes')->insertGetId(['business_id' => $this->business->id, 'name' => 'Esq '.uniqid(), 'scheme_type' => 'blank']);
    $lay = DB::table('invoice_layouts')->insertGetId(['business_id' => $this->business->id, 'name' => 'Lay '.uniqid()]);
    $local = DB::table('business_locations')->insertGetId([
        'business_id' => $this->business->id, 'name' => 'Loja '.uniqid(), 'country' => 'Brasil', 'state' => 'MT',
        'city' => 'Cuiabá', 'zip_code' => '7806500', 'invoice_scheme_id' => $esq, 'invoice_layout_id' => $lay, 'is_active' => 1,
        'default_payment_accounts' => json_encode(['cash' => ['is_enabled' => '1', 'account' => (string) $ligada]]),
    ]);

    // `permitted_locations()` lê as permissões DIRETAS do usuário (`$user->permissions`), não as do papel.
    $usuario = $this->usuarioComPermissoes(['account.access'], $this->business);
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$local}", 'web');
    $usuario->givePermissionTo("location.{$local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs(\App\User::findOrFail($usuario->id));

    $ids = collect($this->withHeaders($this->ajax)->get('/account/account?account_status=active')->json('data'))
        ->pluck('name')->map(fn ($n) => strip_tags((string) $n))->implode('|');

    $nomeLigada = DB::table('accounts')->where('id', $ligada)->value('name');
    $nomeSolta = DB::table('accounts')->where('id', $solta)->value('name');
    expect($ids)->toContain($nomeLigada);
    expect($ids)->not->toContain($nomeSolta);
});

test('baseline valor: o saldo de abertura do cadastro é o num_uf do texto, gravado como crédito', function () {
    $util = new \App\Utils\Util;
    // Texto que a Blade (input_number) manda: vírgula decimal, ponto de milhar com 3 dígitos.
    $casos = ['1.234,56' => 1234.56, '500' => 500.0, '0,10' => 0.10];

    foreach ($casos as $texto => $esperado) {
        $nome = 'Nova '.uniqid();
        $r = $this->withHeaders($this->ajax)->post('/account/account', [
            'name' => $nome, 'account_number' => 'N'.uniqid(), 'opening_balance' => $texto,
        ]);
        expect($r->json('success'))->toBeTrue();

        $conta = DB::table('accounts')->where('business_id', $this->business->id)->where('name', $nome)->first();
        expect($conta)->not->toBeNull();
        expect((int) $conta->created_by)->toBe((int) $this->user->id);

        $abertura = DB::table('account_transactions')->where('account_id', $conta->id)->get();
        expect($abertura)->toHaveCount(1);
        expect($abertura[0]->type)->toBe('credit');
        expect($abertura[0]->sub_type)->toBe('opening_balance');
        // Caminho 1: o endpoint gravou. Caminho 2: num_uf direto sobre o mesmo texto.
        expect(round((float) $abertura[0]->amount, 2))->toBe($esperado);
        expect(round((float) $util->num_uf($texto), 2))->toBe($esperado);
    }

    // Sem saldo de abertura, nenhum lançamento.
    $nome = 'Sem abertura '.uniqid();
    $this->withHeaders($this->ajax)->post('/account/account', ['name' => $nome, 'account_number' => 'N'.uniqid()])->assertOk();
    $id = DB::table('accounts')->where('name', $nome)->value('id');
    expect(DB::table('account_transactions')->where('account_id', $id)->count())->toBe(0);
});

test('baseline: sem account.access a lista e o cadastro devolvem 403', function () {
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $antes = DB::table('accounts')->where('business_id', $this->business->id)->count();

    $this->get('/account/account')->assertForbidden();
    $this->withHeaders($this->ajax)->post('/account/account', ['name' => 'Não grava', 'account_number' => 'X'])->assertForbidden();
    expect(DB::table('accounts')->where('business_id', $this->business->id)->count())->toBe($antes);
});
