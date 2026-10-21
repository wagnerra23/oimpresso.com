<?php
// @covers-us US-SELL-070

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Termômetro de acesso da proposta NA TELA — US-SELL-070 aceites 2 e 5 · UC-FLX-04 (catálogo da
 * tela de fluxos). O backend (gravar a abertura) é o QuoteAccessTermometroContratoTest; aqui é o
 * que o vendedor vê:
 *   UC-VSHOW-20 (Show.casos.md)  — o detalhe do orçamento diz se o cliente abriu, quando e quantas vezes
 *   UC-VSHOW-21 (Show.casos.md)  — [T0] outro business pede o detalhe → 404, e linha de acesso de
 *                                   outro business não é lida como abertura deste orçamento
 *   UC-QUO-10  (Quotations.casos.md) — a lista de cotações traz a coluna Aberto
 *
 * Conta à mão (linhas gravadas direto na tabela-ponte, como o QuoteAccessRecorder deixa):
 *   orçamento A: first 2026-10-20 10:00:00 · last 2026-10-20 11:30:00 · 2 aberturas
 *     → detalhe {aberto: true, primeira 10:00, ultima 11:30, vezes 2} · lista "20/10 10:00 · 2×"
 *   orçamento B: sem linha → {aberto: false, primeira null, vezes 0} · lista "Não aberto"
 *   venda final / rascunho comum → acesso null (não é orçamento; o recorder também não grava)
 *
 * Tenants: 98 (canônico, ADR 0358) × 99 (adversário). Nunca biz=4.
 * Não roda local (proibicoes.md). Lane: sells-pest.yml (MySQL).
 */
uses(DatabaseTransactions::class, \Tests\Support\WithSeededTenant::class);

function qtelaVenda(int $bizId, string $status, ?string $subStatus): int
{
    $produto = EstoqueFixture::singleProduct($bizId);
    $venda = EstoqueFixture::saleWithLine($produto, 0, EstoqueFixture::locationId($bizId), 1.0, 100.0);
    DB::table('transactions')->where('id', $venda['transaction_id'])->update([
        'status' => $status,
        'sub_status' => $subStatus,
        'is_quotation' => $subStatus === 'quotation' ? 1 : 0,
    ]);

    return (int) $venda['transaction_id'];
}

function qtelaAbertura(int $bizId, int $txId, string $primeira, string $ultima, int $vezes): void
{
    DB::table('transaction_quote_accesses')->insert([
        'business_id' => $bizId,
        'transaction_id' => $txId,
        'first_opened_at' => $primeira,
        'last_opened_at' => $ultima,
        'open_count' => $vezes,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function qtelaEntrar(object $test, User $user): void
{
    $test->actingAs($user);
    // `currency` como o SetSessionData grava: a lista formata quantidade com @format_quantity.
    session([
        'user.business_id' => (int) $user->business_id,
        'user.id' => $user->id,
        'currency' => ['decimal_separator' => ',', 'thousand_separator' => '.'],
    ]);
}

/** detail.acesso do /sells/{id}, como o cliente Inertia pede o payload deferido. */
function qtelaDetalhe(object $test, int $saleId)
{
    $manifest = public_path('build-inertia/manifest.json');

    return $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Sells/Show',
        'X-Inertia-Partial-Data' => 'detail',
    ])->get("/sells/{$saleId}");
}

/** Linhas da lista de cotações indexadas pelo id (DT_RowId). */
function qtelaLista(object $test): array
{
    $json = $test->withHeaders(['Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/sells/draft-dt?is_quotation=1')
        ->assertOk()
        ->json();

    // Anti-vácuo: erro de render vira `data: []` com HTTP 200.
    expect($json['error'] ?? null)->toBeNull();

    $linhas = [];
    foreach ($json['data'] as $row) {
        $linhas[(int) $row['DT_RowId']] = $row;
    }

    return $linhas;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady() || ! Schema::hasTable('transaction_quote_accesses')) {
        $this->markTestSkipped('Schema UltimatePOS/tabela de acesso ausente — roda na lane MySQL / CT 100.');
    }

    $this->bizId = (int) $this->seededTenant()->id;
    $this->outroBizId = (int) $this->seededSupportClientTenant()->id;
    // PRÉ-CONDIÇÃO: empresas distintas (senão o [T0] é tautológico).
    expect($this->outroBizId)->not->toBe($this->bizId);

    $this->vendedor = $this->usuarioComPermissoes(
        ['sell.view', 'quotation.view_all', 'access_all_locations'],
        \App\Business::findOrFail($this->bizId)
    );
    qtelaEntrar($this, $this->vendedor);

    $this->orcA = qtelaVenda($this->bizId, 'draft', 'quotation');
    $this->orcB = qtelaVenda($this->bizId, 'draft', 'quotation');
    qtelaAbertura($this->bizId, $this->orcA, '2026-10-20 10:00:00', '2026-10-20 11:30:00', 2);
});

it('UC-VSHOW-20 o orçamento aberto mostra a primeira abertura, a última e quantas vezes (UC-FLX-04)', function () {
    $r = qtelaDetalhe($this, $this->orcA);
    $r->assertOk();
    $page = json_decode($r->getContent(), true);

    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail']['acesso'])->toBe([
        'aberto' => true,
        'primeira' => '2026-10-20 10:00:00',
        'ultima' => '2026-10-20 11:30:00',
        'vezes' => 2,
    ]);
});

it('UC-VSHOW-20 orçamento nunca aberto diz não aberto; venda final e rascunho comum não trazem o bloco', function () {
    $page = json_decode(qtelaDetalhe($this, $this->orcB)->assertOk()->getContent(), true);
    expect($page['props']['detail']['acesso'])->toBe([
        'aberto' => false,
        'primeira' => null,
        'ultima' => null,
        'vezes' => 0,
    ]);

    $final = qtelaVenda($this->bizId, 'final', null);
    $page = json_decode(qtelaDetalhe($this, $final)->assertOk()->getContent(), true);
    expect($page['props'])->toHaveKey('detail');
    expect($page['props']['detail']['acesso'])->toBeNull();

    $rascunho = qtelaVenda($this->bizId, 'draft', null);
    $page = json_decode(qtelaDetalhe($this, $rascunho)->assertOk()->getContent(), true);
    expect($page['props']['detail']['acesso'])->toBeNull();
});

it('UC-VSHOW-21 [T0] outro business recebe 404 e linha de acesso de outro business não vira abertura', function () {
    // Usuário do 99 com a mesma permissão pede o orçamento aberto do 98.
    $estranho = $this->usuarioComPermissoes(
        ['sell.view', 'quotation.view_all', 'access_all_locations'],
        \App\Business::findOrFail($this->outroBizId)
    );
    qtelaEntrar($this, $estranho);
    $r = qtelaDetalhe($this, $this->orcA);
    $r->assertNotFound();
    expect($r->getContent())->not->toContain('2026-10-20 11:30:00');

    // Linha de acesso gravada com o business do 99 apontando para o orçamento B do 98:
    // a leitura é pelo business da venda, então B continua "não aberto" no detalhe e na lista.
    qtelaAbertura($this->outroBizId, $this->orcB, '2026-10-20 09:00:00', '2026-10-20 09:00:00', 5);
    qtelaEntrar($this, $this->vendedor);

    $page = json_decode(qtelaDetalhe($this, $this->orcB)->assertOk()->getContent(), true);
    expect($page['props']['detail']['acesso']['aberto'])->toBeFalse()
        ->and($page['props']['detail']['acesso']['vezes'])->toBe(0);

    $linhas = qtelaLista($this);
    expect($linhas)->toHaveKey($this->orcB);
    expect((int) $linhas[$this->orcB]['quote_open_count'])->toBe(0)
        ->and($linhas[$this->orcB]['quote_first_opened_at'])->toBeNull();

    // Controle positivo: o mesmo pedido do dono no orçamento A lê a abertura.
    $page = json_decode(qtelaDetalhe($this, $this->orcA)->assertOk()->getContent(), true);
    expect($page['props']['detail']['acesso']['vezes'])->toBe(2);
});

it('UC-QUO-10 a lista de cotações traz quando o cliente abriu e quantas vezes', function () {
    $linhas = qtelaLista($this);

    expect($linhas)->toHaveKey($this->orcA)->toHaveKey($this->orcB);
    expect((string) $linhas[$this->orcA]['quote_first_opened_at'])->toBe('2026-10-20 10:00:00')
        ->and((string) $linhas[$this->orcA]['quote_last_opened_at'])->toBe('2026-10-20 11:30:00')
        ->and((int) $linhas[$this->orcA]['quote_open_count'])->toBe(2);
    expect($linhas[$this->orcB]['quote_first_opened_at'])->toBeNull()
        ->and((int) $linhas[$this->orcB]['quote_open_count'])->toBe(0);
});
