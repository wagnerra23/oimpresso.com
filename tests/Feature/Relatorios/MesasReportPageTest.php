<?php

declare(strict_types=1);
// Cobre UC-RME-01, UC-RME-02, UC-RME-03, UC-RME-04 (resources/js/Pages/Relatorios/Mesas/Index.casos.md).

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

// Total por mesa por três caminhos: JSON do DataTable da Blade × props da Page × soma direta em transactions, mais a
// conta à mão. O relatório só filtra por local: cada teste usa um local próprio. Tenant 98 × 99 (ADR 0358).

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasColumn('transactions', 'essentials_duration')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['purchase_n_sell_report.view', 'access_all_locations'], $this->business);
    $this->versaoInertia = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $this->inertia = ['X-Inertia' => 'true', 'X-Inertia-Version' => $this->versaoInertia, 'X-Requested-With' => 'XMLHttpRequest'];
    $this->periodo = ['start_date' => '2099-12-01', 'end_date' => '2099-12-31'];

    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function rmeMesa(int $businessId, int $local, int $criador, string $nome): int
{
    return DB::table('res_tables')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'name' => $nome, 'created_by' => $criador,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rmeVenda(int $businessId, int $local, int $criador, int $mesa, float $total, string $data = '2099-12-05 10:00:00', string $status = 'final'): int
{
    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId, 'location_id' => $local, 'type' => 'sell', 'status' => $status, 'payment_status' => 'paid',
        'res_table_id' => $mesa, 'transaction_date' => $data, 'final_total' => $total, 'total_before_tax' => $total,
        'created_by' => $criador, 'essentials_duration' => 0, 'invoice_no' => 'RME-'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function rmePage($teste, int $local, int $pagina = 1): array
{
    $r = $teste->withHeaders($teste->inertia)->get('/reports/table-report?'.http_build_query(['tela' => 'nova', 'location_id' => $local, 'page' => $pagina] + $teste->periodo));
    $r->assertOk();
    expect($r->json('component'))->toBe('Relatorios/Mesas/Index');

    return $r->json('props');
}

test('UC-RME-01 valor — total da mesa = JSON do DataTable da Blade = soma direta', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RME-'.uniqid());
    $mesa = rmeMesa($this->business->id, $local, $this->user->id, 'Mesa A');
    $v1 = rmeVenda($this->business->id, $local, $this->user->id, $mesa, 100);
    $v2 = rmeVenda($this->business->id, $local, $this->user->id, $mesa, 50);
    rmeVenda($this->business->id, $local, $this->user->id, $mesa, 999, '2099-12-06 10:00:00', 'draft');
    rmeVenda($this->business->id, $local, $this->user->id, $mesa, 777, '2099-11-20 10:00:00');

    // Caminho 1: o JSON que o DataTable da Blade pede, com as colunas do table_report.blade.php.
    $colunas = [
        ['data' => 'table', 'name' => 'res_tables.name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
        ['data' => 'total_sell', 'name' => 'total_sell', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
    ];
    $dt = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/reports/table-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => $colunas,
            'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false'], 'location_id' => $local] + $this->periodo));
    $dt->assertOk();
    expect($dt->json('error'))->toBeNull();
    expect($dt->json('data'))->toHaveCount(1);
    $totalBlade = (float) trim(strip_tags((string) $dt->json('data.0.total_sell')));

    // Caminho 2: a Page.
    $props = rmePage($this, $local);
    expect($props['linhas'])->toHaveCount(1);
    expect((float) $props['linhas'][0]['total'])->toEqual($totalBlade);

    // Caminho 3: soma direta nas vendas da mesa no período.
    expect((float) $props['linhas'][0]['total'])->toEqual((float) DB::table('transactions')->whereIn('id', [$v1, $v2])->sum('final_total'));

    // Conta à mão: 100 + 50 = 150; o rascunho (999) e a venda fora do período (777) não entram.
    expect([$props['linhas'][0]['mesa'], (float) $props['linhas'][0]['total']])->toBe(['Mesa A', 150.0]);
});

test('UC-RME-02 — 25 por página pelo nome da mesa', function () {
    $local = EstoqueFixture::locationId($this->business->id, '-RME-'.uniqid());
    for ($i = 0; $i < 26; $i++) {
        rmeVenda($this->business->id, $local, $this->user->id, rmeMesa($this->business->id, $local, $this->user->id, sprintf('Mesa %02d', $i)), 10 + $i);
    }

    $p1 = rmePage($this, $local, 1);
    expect($p1['linhas'])->toHaveCount(25);
    expect($p1['paginacao'])->toMatchArray(['atual' => 1, 'ultima' => 2, 'total' => 26]);
    expect($p1['linhas'][0]['mesa'])->toBe('Mesa 00');

    $p2 = rmePage($this, $local, 2);
    expect(array_column($p2['linhas'], 'mesa'))->toBe(['Mesa 25']);
});

test('UC-RME-03 Tier 0 — venda do negócio 99 não aparece; permissão da Blade', function () {
    $alheio = $this->seededSupportClientTenant();
    expect($alheio->id)->not->toBe($this->business->id);
    $donoAlheio = \App\User::factory()->create(['business_id' => $alheio->id])->id;
    $localAlheio = EstoqueFixture::locationId($alheio->id, '-RME-'.uniqid());
    rmeVenda($alheio->id, $localAlheio, $donoAlheio, rmeMesa($alheio->id, $localAlheio, $donoAlheio, 'Mesa X'), 40);

    expect(rmePage($this, $localAlheio)['linhas'])->toBe([]);

    // Sem a permissão: 403 na tela nova e na Blade.
    $this->actingAs($this->usuarioComPermissoes([], $this->business));
    $this->withHeaders($this->inertia)->get('/reports/table-report?tela=nova')->assertForbidden();
    $this->withHeaders([])->get('/reports/table-report')->assertForbidden();
});

test('UC-RME-04 Tier 0 — só as mesas dos locais que o usuário pode ver', function () {
    $sufixo = uniqid();
    $localA = EstoqueFixture::locationId($this->business->id, '-RME-A-'.$sufixo);
    $localB = EstoqueFixture::locationId($this->business->id, '-RME-B-'.$sufixo);
    rmeVenda($this->business->id, $localA, $this->user->id, rmeMesa($this->business->id, $localA, $this->user->id, 'Mesa do A '.$sufixo), 60);
    rmeVenda($this->business->id, $localB, $this->user->id, rmeMesa($this->business->id, $localB, $this->user->id, 'Mesa do B '.$sufixo), 90);

    /** Mesas deste teste que o usuário vê, sem escolher local, na tela nova e no JSON da Blade. */
    $visto = function (array $locais) use ($sufixo): array {
        $u = $this->usuarioComPermissoes(['purchase_n_sell_report.view'], $this->business);
        // Local é permissão DIRETA no usuário — é só $user->permissions que o User::permitted_locations lê.
        foreach ($locais as $local) {
            $u->givePermissionTo(\Spatie\Permission\Models\Permission::findOrCreate('location.'.$local, 'web'));
        }
        $this->actingAs($u);
        session(['user.business_id' => $this->business->id, 'user.id' => $u->id, 'business.id' => $this->business->id]);
        $soDoTeste = fn (array $nomes) => array_values(array_filter($nomes, fn ($n) => str_ends_with((string) $n, $sufixo)));

        $tela = $this->withHeaders($this->inertia)->get('/reports/table-report?'.http_build_query(['tela' => 'nova'] + $this->periodo));
        $tela->assertOk();
        $blade = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->get('/reports/table-report?'.http_build_query(['draw' => 1, 'start' => 0, 'length' => -1, 'columns' => [
                ['data' => 'table', 'name' => 'res_tables.name', 'searchable' => 'true', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
                ['data' => 'total_sell', 'name' => 'total_sell', 'searchable' => 'false', 'orderable' => 'true', 'search' => ['value' => '', 'regex' => 'false']],
            ], 'order' => [['column' => 0, 'dir' => 'asc']], 'search' => ['value' => '', 'regex' => 'false']] + $this->periodo));
        $blade->assertOk();

        return [$soDoTeste(array_column($tela->json('props.linhas'), 'mesa')), $soDoTeste(array_column($blade->json('data'), 'table'))];
    };

    // Só o local A: vê só a mesa do A, nas duas telas.
    expect($visto([$localA]))->toBe([['Mesa do A '.$sufixo], ['Mesa do A '.$sufixo]]);

    // Nenhum local: não vê mesa nenhuma.
    expect($visto([]))->toBe([[], []]);
});
