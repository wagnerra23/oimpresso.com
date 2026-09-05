<?php

declare(strict_types=1);

use App\Transaction;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * UC-PUREDT-02 — IDOR de escrita cross-tenant em DINHEIRO (Purchase).
 *
 * `PurchaseController@update` fazia `Transaction::findOrFail($id)->update()` SEM `where
 * business_id`. Como `App\Transaction` NÃO tem global scope, um usuário do negócio A
 * alterava o lançamento financeiro do negócio B. O fix escopou a busca; ESTE arquivo é a
 * defesa que impede o retorno — e até 2026-09-05 ele NÃO EXECUTAVA EM LANE NENHUMA (o
 * `beforeEach` pulava fora de sqlite, e a allowlist sqlite não tinha uma linha Purchase).
 *
 * Tenants (ADR 0358): **98** = tenant canônico de teste, aqui no papel de atacante e de
 * dono legítimo do controle positivo · **99** = a outra empresa fictícia, a vítima.
 * biz=1 (WR2, empresa REAL) e biz=4 (ROTA LIVRE, cliente real) NÃO aparecem aqui.
 *
 * ⚠️ SEM `DatabaseTransactions`, e isso é medido, não estilo: o `catch (\Exception)` do
 * `update()` chama `DB::rollBack()`, e o `firstOrFail()` cross-tenant estoura ANTES do
 * `DB::beginTransaction()` do próprio método. Sob `DatabaseTransactions` esse rollback
 * derruba a transação DO TESTE (medido no CT 100: `transactionLevel` 1 → 0) e a fixture da
 * vítima SOME — a asserção "o dado permanece intacto" viraria um null-pointer. A fixture
 * precisa estar COMMITADA pra sobreviver ao rollback do controller; daí a limpeza
 * explícita do `afterEach`.
 *
 * @covers-uc UC-PUREDT-02
 *
 * @see resources/js/Pages/Purchase/Edit.casos.md (UC-PUREDT-02)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0358-doutrina-de-teste-tenant-98-supersede-0101.md
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
        test()->markTestSkipped(
            'Precisa do schema UltimatePOS em MySQL real. Lane dona: compras-pest.yml '
            . '(.github/actions/pest-mysql-setup). Não roda em sqlite :memory:.'
        );
    }

    $this->idorLixo = [];

    // biz=99 (vítima) — o seed do CI só cria 1/2/98. O helper é idempotente e resolve o
    // FK circular business.owner_id <-> users.business_id.
    $this->bizVitima = $this->seededSupportClientTenant();
    $this->bizAtacante = $this->seededTenant();

    $this->locAtacante = idorLocation((int) $this->bizAtacante->id);
    $this->locVitima = idorLocation((int) $this->bizVitima->id);

    $this->atacante = idorUsuario((int) $this->bizAtacante->id);
    $this->donoVitima = idorUsuario((int) $this->bizVitima->id);
});

afterEach(function () {
    foreach ($this->idorLixo ?? [] as $txId) {
        // fin_titulos referencia a compra por `origem_id` (não `transaction_id`) e tem FK
        // pra users em `created_by` — apagar fora de ordem estoura 1451.
        DB::table('fin_titulos')->where('origem_id', $txId)->delete();
        DB::table('purchase_lines')->where('transaction_id', $txId)->delete();
        DB::table('transactions')->where('id', $txId)->delete();
    }
});

/** business_location do tenant (FK obrigatória de `transactions`), criada se faltar. */
function idorLocation(int $bizId): int
{
    $existente = DB::table('business_locations')->where('business_id', $bizId)->value('id');
    if ($existente) {
        return (int) $existente;
    }

    // invoice_scheme_id/invoice_layout_id são NOT NULL + FK; reusa qualquer um (a FK só
    // exige existência), espelhando Modules/Compras/Tests/Feature/MultiTenantTest.
    $scheme = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => $bizId, 'name' => 'IDOR Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layout = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => $bizId, 'name' => 'IDOR Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    return (int) DB::table('business_locations')->insertGetId([
        'business_id' => $bizId, 'name' => 'Loc IDOR ' . $bizId, 'location_id' => 'IDOR' . $bizId,
        'country' => 'BR', 'state' => 'SC', 'city' => 'Test City', 'zip_code' => '0000000',
        'invoice_scheme_id' => $scheme, 'invoice_layout_id' => $layout, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Usuário do tenant com `purchase.update`. Username DETERMINÍSTICO de propósito: no CT 100
 * a base persiste entre runs e `fin_titulos.created_by` é FK pra `users` — criar um user
 * novo a cada run vazaria linha que a limpeza não consegue apagar.
 */
function idorUsuario(int $bizId): User
{
    $perm = Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']);
    // roles.business_id é NOT NULL + FK (proibicoes §FSM) — sufixo #biz é convenção UPOS.
    $role = Role::firstOrCreate(
        ['name' => 'idor-purchase-test#' . $bizId, 'guard_name' => 'web'],
        ['business_id' => $bizId]
    );
    $role->givePermissionTo($perm);

    // user_type='user' + allow_login=1: sem eles o middleware CheckUserLogin aborta 403.
    $user = User::where('business_id', $bizId)->where('username', 'idor_purchase_' . $bizId)->first()
        ?? User::factory()->create([
            'business_id' => $bizId, 'username' => 'idor_purchase_' . $bizId,
            'user_type' => 'user', 'allow_login' => 1,
        ]);
    $user->assignRole($role);

    return $user;
}

function idorCriarCompra(int $bizId, int $locId, int $userId, float $total): Transaction
{
    return Transaction::forceCreate([
        'business_id' => $bizId, 'location_id' => $locId, 'type' => 'purchase',
        'status' => 'received', 'payment_status' => 'due',
        'transaction_date' => now()->toDateTimeString(), 'ref_no' => 'IDOR-' . uniqid(),
        'final_total' => $total, 'total_before_tax' => $total, 'tax_amount' => 0,
        'discount_amount' => 0, 'created_by' => $userId,
        'essentials_duration' => 0, // NOT NULL sem default no schema real
    ]);
}

/**
 * Payload do form de edição.
 *
 * A data vai em `m/d/Y H:i` porque `Util::uf_date` monta o formato a partir de
 * `session('business.date_format')`, que o middleware `SetSessionData` popula do banco. Por
 * isso os testes usam `actingAs()` PURO, sem `withSession(['user' => ...])`: pré-setar o
 * bloco `user` faz o middleware pular a população inteira, `date_format` fica null e o
 * formato degenera pra `' H:i'` (null concatenado) — medido, o Carbon estoura
 * "Unexpected data found. Trailing data" e o request morre por motivo errado.
 */
function idorPayload(string $refNo, string $valor): array
{
    return [
        'ref_no' => $refNo, 'status' => 'received',
        'transaction_date' => now()->format('m/d/Y H:i'),
        'total_before_tax' => $valor, 'discount_type' => 'fixed', 'discount_amount' => '0',
        'tax_amount' => '0', 'shipping_charges' => '0', 'final_total' => $valor,
        'exchange_rate' => 1, 'purchases' => [],
    ];
}

// ─── O EXPLOIT FECHADO ──────────────────────────────────────────────────────

it('UC-PUREDT-02 · cross-tenant — o update do biz=98 não alcança a compra do biz=99, e o valor da vítima fica intacto', function () {
    $vitima = idorCriarCompra(
        (int) $this->bizVitima->id, $this->locVitima, (int) $this->donoVitima->id, 5000.0
    );
    $this->idorLixo[] = $vitima->id;
    $refOriginal = $vitima->ref_no;

    $this->actingAs($this->atacante)->put('/purchases/' . $vitima->id, idorPayload('HACKED', '999999,00'));

    // NÃO é 404: o `catch (\Exception)` do controller engole o ModelNotFoundException antes
    // do handler do Laravel e devolve `back()` com erro (medido no CT 100 — status 302).
    // O que prova o scope é a ORIGEM da recusa: a busca escopada não resolveu.
    $status = session('status');
    expect($status['success'] ?? null)->toBe(0, 'O update cross-tenant NÃO pode reportar sucesso.');
    // `toContain` do Pest é VARIÁDICO — um 2º argumento viraria outra agulha, não mensagem
    // (medido: o teste falhou procurando a própria mensagem). Daí o str_contains explícito,
    // que é a forma de manter o diagnóstico acionável.
    expect(str_contains((string) ($status['msg'] ?? ''), 'No query results for model'))->toBeTrue(
        'A recusa tem que vir da busca ESCOPADA não resolver — não de uma falha lateral. msg='
        . json_encode($status['msg'] ?? null)
    );

    // A prova que importa: o lançamento financeiro da vítima não foi tocado.
    $depois = DB::table('transactions')->where('id', $vitima->id)->first();
    expect($depois)->not->toBeNull('A compra da vítima sumiu — a fixture não sobreviveu ao request.');
    expect((float) $depois->final_total)->toBe(
        5000.0,
        'VAZAMENTO TIER 0: o final_total do biz=99 foi alterado por um usuário do biz=98.'
    );
    expect($depois->ref_no)->toBe(
        $refOriginal,
        'VAZAMENTO TIER 0: o ref_no do biz=99 foi alterado por um usuário do biz=98.'
    );
    expect((int) $depois->business_id)->toBe((int) $this->bizVitima->id);
});

// ─── CONTROLE POSITIVO (sem ele, um abort incondicional passaria) ────────────

it('UC-PUREDT-02 · controle positivo — o mesmo usuário atualiza normalmente a compra do próprio negócio', function () {
    $propria = idorCriarCompra(
        (int) $this->bizAtacante->id, $this->locAtacante, (int) $this->atacante->id, 100.0
    );
    $this->idorLixo[] = $propria->id;

    $resposta = $this->actingAs($this->atacante)
        ->put('/purchases/' . $propria->id, idorPayload('ATUALIZADO', '150,00'));

    $resposta->assertRedirect('purchases');
    expect(session('status')['success'] ?? null)->toBe(
        1,
        'O update same-tenant DEVE ter sucesso — senão o teste acima passa por bloqueio geral, não por scope.'
    );

    $depois = DB::table('transactions')->where('id', $propria->id)->first();
    expect((float) $depois->final_total)->toBe(150.0);
    expect($depois->ref_no)->toBe('ATUALIZADO');
});

// ─── ANTI-REGRESSÃO DE FONTE (a catraca não pode reverter pro findOrFail nu) ──

it('UC-PUREDT-02 · Controller@update scopa a busca por business_id (sem findOrFail nu)', function () {
    $source = file_get_contents(base_path('app/Http/Controllers/PurchaseController.php'));

    // Recorta o corpo do método update() pra não pegar matches de outros métodos.
    $start = strpos($source, 'public function update(Request $request, $id)');
    $end = strpos($source, 'public function destroy($id)', $start);
    $updateBody = substr($source, $start, $end - $start);

    expect($updateBody)->toMatch("/Transaction::where\\('business_id', \\\$business_id\\)/");
    expect($updateBody)->not->toContain('Transaction::findOrFail($id)');
});
