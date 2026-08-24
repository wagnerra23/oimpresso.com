<?php

declare(strict_types=1);
// Cobre UC-PUNI-17 (resources/js/Pages/Produto/Unificado/Index.casos.md) — G-2 rastreabilidade caso↔teste.

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato do MARCADOR DE GRADE da 2ª linha da célula Produto
 * (`GET /products/unificado` → prop `produtos[].grade`).
 *
 * ÂNCORA (contrato, NÃO implementação):
 *   1. HANDOFF V3 divergência #4 — em produção a linha imprimia "Tamnha p-m-g (4)", o nome do
 *      eixo como o tenant digitou (erro de digitação incluso). O marcador correto é DERIVADO
 *      do saldo por combinação: "4 de 6 com saldo"
 *   2. HANDOFF V3 §3.2 — o marcador só existe pra produto COM grade; vermelho quando há furo
 *   3. HANDOFF §"[S1] Subconjunto se declara" — dizer "4 de 6" é o que impede o número de virar
 *      subconjunto silencioso
 *   4. ADR 0093 `[T0]` — a linha de saldo pendura num LOCAL, então o escopo de tenant tem que
 *      valer no LOCAL, não só no produto
 *
 * ⚠️ ANTI-VÁCUO (proibicoes §5): todo assert de ausência tem pré-condição provando que a prop
 *    CHEGOU e que a linha semeada ESTÁ nela. Sem isso, "não tem grade" passaria só porque a prop
 *    não veio — mediria não-execução, não contrato.
 *
 * ⛔ Multi-tenant Tier 0: tenant canônico de teste (`seededTenant()`); cross-tenant contra o 2º
 *    business seedado. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

function gradeContratoProps(object $test, array $props, array $query = []): array
{
    $manifest = public_path('build-inertia/manifest.json');
    $version = file_exists($manifest) ? md5_file($manifest) : '1';

    $url = '/products/unificado' . ($query ? '?' . http_build_query($query) : '');

    $response = $test->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Partial-Component' => 'Produto/Unificado/Index',
        'X-Inertia-Partial-Data' => implode(',', $props),
    ])->get($url);

    $response->assertStatus(200);

    $page = json_decode($response->getContent(), true);
    expect($page)->toBeArray()->toHaveKey('props');

    return $page['props'];
}

/** A linha do produto semeado, dentro da prop `produtos`. FALHA (não pula) se ela não veio. */
function gradeContratoLinha(array $props, int $productId): array
{
    expect($props)->toHaveKey('produtos');

    $linha = collect($props['produtos'])->firstWhere('id', $productId);

    expect($linha)->not->toBeNull(
        "O produto semeado (id={$productId}) não apareceu na prop `produtos`. Sem ele, qualquer "
        . 'assert sobre `grade` mediria a ausência da LINHA, não a do marcador.'
    );

    return (array) $linha;
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant();
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }

    $this->user = User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.view', 'web');
    $this->user->givePermissionTo(['product.view']);
});

// =============================================================================
// UC-PUNI-17 — o marcador de grade é DERIVADO do saldo por combinação e DECLARA o
//   subconjunto. Produto sem grade não recebe a chave.
// =============================================================================

it('UC-PUNI-17 · a grade viaja como cobertura de saldo (com/total), não como nome de atributo', function () {
    $bizId = (int) $this->business->id;
    $localId = EstoqueFixture::locationId($bizId);

    // 3 combinações, 2 com saldo — é o caso que o handoff chama de FURO.
    $produto = EstoqueFixture::variableProduct($bizId, 3);
    EstoqueFixture::setStock($produto, 0, $localId, 5);
    EstoqueFixture::setStock($produto, 1, $localId, 2);
    EstoqueFixture::setStock($produto, 2, $localId, 0);

    $props = gradeContratoProps($this, ['produtos'], [
        'aba' => 'todos',
        'busca' => 'Produto Variável Estoque Fix',
        'porPagina' => 100,
    ]);

    $linha = gradeContratoLinha($props, $produto->productId);

    expect($linha)->toHaveKey('grade');
    expect((int) $linha['grade']['total'])->toBe(
        3,
        'O total da grade não contou as 3 combinações vivas. Se a variação DUMMY entrar na conta, '
        . 'todo produto simples vira "1 de 1 com saldo" — marcador em 100% das linhas não marca nada.'
    );
    expect((int) $linha['grade']['com'])->toBe(
        2,
        'A contagem de combinações COM saldo divergiu do semeado (2 de 3). O marcador "N de M com '
        . 'saldo" é o que declara o subconjunto ([S1]); errar o N é pior que não ter marcador.'
    );
});

it('UC-PUNI-17 · produto SEM grade não recebe a chave `grade`', function () {
    $bizId = (int) $this->business->id;

    $simples = EstoqueFixture::singleProduct($bizId);

    $props = gradeContratoProps($this, ['produtos'], [
        'aba' => 'todos',
        'busca' => 'Produto Estoque Fix',
        'porPagina' => 100,
    ]);

    // PRÉ-CONDIÇÃO ANTI-VÁCUO: a linha chegou. Só depois disso a ausência da chave significa algo.
    $linha = gradeContratoLinha($props, $simples->productId);

    expect(array_key_exists('grade', $linha))->toBeFalse(
        'Produto simples recebeu a chave `grade`. A variação DUMMY do UltimatePOS não é combinação — '
        . 'emitir a chave faz a tela montar um marcador que não afirma nada (handoff V3 §3.2).'
    );
});

it('UC-PUNI-17 · [T0] saldo em local de OUTRO business não conta como combinação com saldo', function () {
    $bizId = (int) $this->business->id;
    $outroBiz = EstoqueFixture::secondBusinessId();

    if ($outroBiz === null || $outroBiz === $bizId) {
        $this->markTestSkipped('Sem segundo business seedado — sem cenário cross-tenant pra provar o escopo.');
    }

    $localDoOutro = EstoqueFixture::locationId($outroBiz);

    // 2 combinações, NENHUMA com saldo no próprio tenant.
    $produto = EstoqueFixture::variableProduct($bizId, 2);

    // O vazamento que o teste persegue: linha de saldo pendurada num LOCAL de outro business,
    // apontando pra uma variação DESTE produto (cenário de restore/importação mal feita).
    DB::table('variation_location_details')->insert([
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'location_id' => $localDoOutro,
        'qty_available' => 999,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $props = gradeContratoProps($this, ['produtos'], [
        'aba' => 'todos',
        'busca' => 'Produto Variável Estoque Fix',
        'porPagina' => 100,
    ]);

    $linha = gradeContratoLinha($props, $produto->productId);

    expect($linha)->toHaveKey('grade');
    expect((int) $linha['grade']['total'])->toBe(2);
    expect((int) $linha['grade']['com'])->toBe(
        0,
        'Saldo de um local de OUTRO business entrou na contagem de combinações vendáveis. O escopo de '
        . 'tenant tem que valer no LOCAL (`business_locations.business_id`), não só no produto — ADR 0093.'
    );
});
