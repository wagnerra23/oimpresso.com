<?php

// @covers-us US-SELL-017

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Pílulas "Faturada" e "Cancelada" da consulta de vendas (`GET /sells-list-json?fiscal_status=…`
 * → `SellController@inertiaList`). Até 2026-11-04 elas filtravam só a página carregada no
 * navegador (`Index.tsx`, "filtra client-side"): total, paginação e rodapé contavam as outras
 * vendas. Agora o servidor filtra pela emissão da venda — o mesmo critério do `fiscal_status`
 * que a linha já mostrava (emissão única por venda, sem a apagada).
 *
 * UC: resources/js/Pages/Sells/Index.casos.md (UC-SIDX-07). Fontes: US-SELL-017 (total do filtro
 * inteiro, não da página) + plano mestre de Sells, Onda 1 / PR-1c (aprovado por [W]).
 *
 * Fixture identificada pelo prefixo no invoice_no (a lane MySQL persiste dado alheio no tenant 98).
 * Tenants 98 × 99 (ADR 0358). Nunca biz=4. Não roda local (CT 100 / CI).
 */
uses(DatabaseTransactions::class);

function pnfUsuario(int $bizId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'PNF Pilula',
        'username' => 'pnf_' . uniqid(),
        'password' => bcrypt('ci'),
        'business_id' => $bizId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $user = User::findOrFail($id);
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (['direct_sell.view', 'access_all_locations'] as $p) {
        Permission::findOrCreate($p, 'web');
        $user->givePermissionTo($p);
    }
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

function pnfLogin(object $test, User $user): void
{
    $test->actingAs($user);
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);
}

function pnfVenda(object $test, int $bizId, int $locationId, int $userId, string $sufixo, float $total): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'sell',
        'status' => 'final',
        'is_direct_sale' => 1,
        'payment_status' => 'due',
        'invoice_no' => $test->prefixo . $sufixo,
        'transaction_date' => now(),
        'total_before_tax' => $total,
        'final_total' => $total,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function pnfNota(int $bizId, int $transactionId, string $status, bool $apagada = false): void
{
    DB::table('nfe_emissoes')->insert([
        'business_id' => $bizId, 'transaction_id' => $transactionId, 'modelo' => '55', 'serie' => '9',
        'numero' => random_int(800000, 999999), 'status' => $status, 'valor_total' => 10.00,
        'emitido_em' => now(), 'metadata' => json_encode([]), 'created_at' => now(), 'updated_at' => now(),
        'deleted_at' => $apagada ? now() : null,
    ]);
}

/** JSON da consulta como o front pede, restrito à fixture pela busca. */
function pnfJson(object $test, string $query = ''): array
{
    return $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells-list-json?per_page=200&limit=200&q=' . urlencode($test->prefixo) . $query)
        ->assertOk()
        ->json();
}

/** Sufixos (só os desta fixture) devolvidos pela consulta, em ordem. */
function pnfSufixos(array $json, string $prefixo): array
{
    $nos = [];
    foreach ($json['data'] as $row) {
        $no = (string) $row['invoice_no'];
        if (str_starts_with($no, $prefixo)) {
            $nos[] = substr($no, strlen($prefixo));
        }
    }
    sort($nos);

    return $nos;
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (ADR 0101).');
    }
    foreach (['transactions', 'business', 'business_locations', 'users', 'nfe_emissoes'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema ausente ({$t}) — roda na lane MySQL / CT 100.");
        }
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->prefixo = 'PNF' . strtoupper(substr(uniqid(), -6)) . '-';
    $loc = EstoqueFixture::locationId($this->bizId);
    $locOutro = EstoqueFixture::locationId($this->outroBizId);

    $this->user = pnfUsuario($this->bizId);
    $this->estranho = pnfUsuario($this->outroBizId);

    // Empresa do teste: uma venda em cada situação da nota.
    pnfNota($this->bizId, pnfVenda($this, $this->bizId, $loc, $this->user->id, 'fat', 120), 'autorizada');
    pnfNota($this->bizId, pnfVenda($this, $this->bizId, $loc, $this->user->id, 'can', 70), 'cancelada');
    pnfNota($this->bizId, pnfVenda($this, $this->bizId, $loc, $this->user->id, 'pen', 30), 'pendente');
    pnfVenda($this, $this->bizId, $loc, $this->user->id, 'sem', 15);
    // Nota apagada (SoftDeletes) não conta como faturada — a linha também não a mostra.
    pnfNota($this->bizId, pnfVenda($this, $this->bizId, $loc, $this->user->id, 'apg', 40), 'autorizada', apagada: true);
    // Adversarial: nota de OUTRA empresa apontando para venda desta — não marca a venda.
    pnfNota($this->outroBizId, pnfVenda($this, $this->bizId, $loc, $this->user->id, 'xno', 55), 'autorizada');
    // Outra empresa com venda faturada de verdade.
    pnfNota($this->outroBizId, pnfVenda($this, $this->outroBizId, $locOutro, $this->estranho->id, 'xft', 99), 'autorizada');

    // Anti-vácuo: as 7 vendas existem com o prefixo — o que some da lista some pelo filtro.
    expect(DB::table('transactions')->where('invoice_no', 'like', $this->prefixo . '%')->count())->toBe(7);
});

it('UC-SIDX-07 a pílula Faturada traz só venda com nota autorizada, de todas as páginas, com total e rodapé do filtro', function () {
    pnfLogin($this, $this->user);

    // Controle: sem a pílula vêm as 6 vendas da empresa (a da outra empresa nunca).
    $todas = pnfJson($this);
    expect(pnfSufixos($todas, $this->prefixo))->toBe(['apg', 'can', 'fat', 'pen', 'sem', 'xno']);

    $json = pnfJson($this, '&fiscal_status=autorizada');
    expect(pnfSufixos($json, $this->prefixo))->toBe(['fat']);
    expect($json['meta']['total'])->toBe(1);
    expect($json['totals']['count'])->toBe(1);
    expect((float) $json['totals']['sum_final_total'])->toBe(120.0);
    expect($json['data'][0]['fiscal_status'])->toBe('autorizada');
});

it('UC-SIDX-07 a pílula Cancelada traz só venda com nota cancelada', function () {
    pnfLogin($this, $this->user);

    $json = pnfJson($this, '&fiscal_status=cancelada');
    expect(pnfSufixos($json, $this->prefixo))->toBe(['can']);
    expect($json['meta']['total'])->toBe(1);
    expect($json['totals']['count'])->toBe(1);
    expect((float) $json['totals']['sum_final_total'])->toBe(70.0);
});

it('UC-SIDX-07 valor fora da lista não filtra', function () {
    pnfLogin($this, $this->user);

    foreach (['pendente', 'rejeitada', "autorizada' OR 1=1 --"] as $valor) {
        $json = pnfJson($this, '&fiscal_status=' . urlencode($valor));
        expect(pnfSufixos($json, $this->prefixo))->toBe(['apg', 'can', 'fat', 'pen', 'sem', 'xno']);
    }
});

it('UC-SIDX-07 [T0] a nota de outra empresa não marca a venda, e cada empresa só vê as suas faturadas', function () {
    pnfLogin($this, $this->estranho);
    $json = pnfJson($this, '&fiscal_status=autorizada');
    expect(pnfSufixos($json, $this->prefixo))->toBe(['xft']);

    // Controle positivo do mesmo filtro na empresa do teste (já coberto acima, repetido
    // aqui para o caso ficar legível sozinho): 'xno' tem nota autorizada só da outra empresa.
    pnfLogin($this, $this->user);
    $json = pnfJson($this, '&fiscal_status=autorizada');
    expect(pnfSufixos($json, $this->prefixo))->toBe(['fat']);
});
