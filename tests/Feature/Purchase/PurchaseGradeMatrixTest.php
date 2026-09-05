<?php

declare(strict_types=1);

use App\Services\Purchase\GradeLayoutBuilder;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * US-COM-005 — grade tam×cor plugada em /purchases/create.
 *
 * Cobertura:
 *  - GradeLayoutBuilder (lógica pura, driver-agnostic): auto-detect 2D vs 1-eixo vs single.
 *  - Estrutural (CI, sem DB): rota + Tier 0 scope no gradeMatrix + hardening no store + wiring frontend.
 *  - Cross-tenant Tier 0 (MySQL real): bate no ENDPOINT — grade-matrix cross-tenant devolve 404,
 *    e o store() recusa `variation_id` forjado com 422 sem gravar linha. Cada um com o par
 *    positivo, senão um abort incondicional passaria no teste.
 *
 * O bloco cross-tenant montava `products`/`variations` à mão e pulava fora de sqlite — ou seja,
 * não executava em lane nenhuma (a suíte real é MySQL; a allowlist sqlite não tem uma linha
 * Purchase). Foi reescrito em 2026-09-05 contra o schema semeado por
 * `.github/actions/pest-mysql-setup`.
 *
 * @covers-uc UC-PURCRE-02
 * @covers-uc UC-PURCRE-03
 *
 * @see resources/js/Pages/Purchase/Create.casos.md (UC-PURCRE-02 · UC-PURCRE-03)
 *
 * ADRs: 0093 (Tier 0 IRREVOGÁVEL), 0104 (MWART), 0105 (Larissa sinal), 0358 (tenant 98/99),
 * C1 (convergência).
 */
const GM_CONTROLLER = 'app/Http/Controllers/PurchaseController.php';
const GM_ROUTES = 'routes/web.php';
const GM_PAGE = 'resources/js/Pages/Purchase/Create.tsx';
const GM_COMPONENT = 'resources/js/Pages/Purchase/_components/GradeMatrixInput.tsx';
const GM_COMBOBOX = 'resources/js/Pages/Purchase/_components/GradeProductCombobox.tsx';

function gmVar(int $id, string $name): array
{
    return ['id' => $id, 'name' => $name];
}

// ─── GradeLayoutBuilder — auto-detect (lógica pura) ──────────────────────────

it('2D: nomes compostos "P/Preto" viram grade tam×cor', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(11, 'P/Preto'), gmVar(12, 'M/Preto'), gmVar(13, 'P/Branco'),
    ]);

    expect($layout['mode'])->toBe('2d');
    expect(array_column($layout['rows'], 'label'))->toBe(['P', 'M']);
    expect(array_column($layout['cols'], 'label'))->toBe(['Preto', 'Branco']);
    expect($layout['cellVariationMap']['P__Preto'])->toBe(11);
    expect($layout['cellVariationMap']['P__Branco'])->toBe(13);
    // Célula esparsa (M/Branco não existe) → não mapeada (grade desabilita a célula).
    expect($layout['cellVariationMap'])->not->toHaveKey('M__Branco');
});

it('2D: hífen também monta a grade ("P-Preto")', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P-Preto'), gmVar(2, 'G-Preto'), gmVar(3, 'P-Azul'),
    ]);

    expect($layout['mode'])->toBe('2d');
    expect(array_column($layout['rows'], 'label'))->toBe(['P', 'G']);
});

it('1 eixo: nomes simples "P","M","G" caem pra matrix-1d (1 coluna Qtd)', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P'), gmVar(2, 'M'), gmVar(3, 'G'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
    expect($layout['cols'])->toBe([['id' => 'qtd', 'label' => 'Qtd']]);
    expect($layout['cellVariationMap']['2__qtd'])->toBe(2);
});

it('single: produto não-variável vira input único', function () {
    $layout = (new GradeLayoutBuilder())->build('single', [gmVar(7, 'DUMMY')]);

    expect($layout['mode'])->toBe('single');
    expect($layout['cellVariationMap']['single__qtd'])->toBe(7);
});

it('ambíguo (combinação duplicada) cai pra 1 eixo — nunca grade quebrada', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P/Preto'), gmVar(2, 'P/Preto'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
});

it('misto (uma variação sem delimitador) cai pra 1 eixo', function () {
    $layout = (new GradeLayoutBuilder())->build('variable', [
        gmVar(1, 'P/Preto'), gmVar(2, 'M'),
    ]);

    expect($layout['mode'])->toBe('matrix-1d');
});

// ─── Estrutural — Tier 0 + wiring (roda em CI sem DB) ────────────────────────

it('rota purchases.grade-matrix registrada', function () {
    $routes = file_get_contents(base_path(GM_ROUTES));
    expect($routes)->toContain("'/purchases/grade-matrix'");
    expect($routes)->toContain("'gradeMatrix'");
});

it('gradeMatrix resolve produto com scope business_id + firstOrFail (Tier 0 → 404 cross-tenant)', function () {
    $src = file_get_contents(base_path(GM_CONTROLLER));
    expect($src)->toContain('public function gradeMatrix');
    expect($src)->toMatch("/Product::where\\('business_id', \\\$business_id\\)\\s*->where\\('id', \\\$product_id\\)\\s*->firstOrFail\\(\\)/");
    // Canon UPOS — session, NÃO auth()->user()->business_id (T-AP-8).
    expect($src)->toContain("session()->get('user.business_id')");
    expect($src)->toContain('GradeLayoutBuilder');
});

it('store() valida ownership Tier 0 das variations (anti payload forjado cross-tenant)', function () {
    $src = file_get_contents(base_path(GM_CONTROLLER));
    expect($src)->toContain('assertPurchaseVariationsOwnership');
    expect($src)->toContain("whereHas('product'");
    expect($src)->toMatch('/abort_if\\(/');
    expect($src)->toContain('422');
});

it('Create.tsx pluga o modo grade (imports + fetch grade-matrix + expande células)', function () {
    $src = file_get_contents(base_path(GM_PAGE));
    expect($src)->toContain('@/Pages/Purchase/_components/GradeMatrixInput');
    expect($src)->toContain('@/Pages/Purchase/_components/GradeProductCombobox');
    expect($src)->toContain('/purchases/grade-matrix');
    expect($src)->toContain('adicionarLinhasGrade');
    expect($src)->toContain('purchase_line_tax_id');
});

it('Create.tsx preserva o fluxo manual (invariantes do Wave2)', function () {
    $src = file_get_contents(base_path(GM_PAGE));
    foreach (['Itens da compra', 'adicionarLinhaVazia', 'removerLinha', "form.post('/purchases'"] as $needle) {
        expect($src)->toContain($needle);
    }
});

it('componentes da grade existem em Components/purchase', function () {
    expect(file_exists(base_path(GM_COMPONENT)))->toBeTrue();
    expect(file_exists(base_path(GM_COMBOBOX)))->toBeTrue();
});

// ─── Cross-tenant Tier 0 — comportamento REAL, MySQL semeado ─────────────────
//
// Este bloco montava `products`/`variations` à mão com `Schema::create` e pulava fora de
// sqlite — ou seja, não rodava em lane nenhuma (a suíte real é MySQL, e a allowlist sqlite
// não tem uma linha Purchase). E o que ele exercitava era o PADRÃO `Product::where(...)
// ->firstOrFail()` copiado do controller, não o controller: um teste que prova que o
// Eloquent funciona (§5 2026-06-05 — derivar do código é tautológico).
//
// Agora usa o schema real semeado por .github/actions/pest-mysql-setup e bate no ENDPOINT.
// Tenants (ADR 0358): 98 = tenant canônico (o usuário logado) · 99 = a outra empresa
// fictícia (a vítima). biz=1 (WR2, real) e biz=4 (ROTA LIVRE) não aparecem.

/** Produto do tenant. Colunas obrigatórias conferidas no schema real, não supostas. */
function gmProduto(int $bizId, int $userId, string $nome): int
{
    return (int) DB::table('products')->insertGetId([
        'business_id' => $bizId, 'name' => $nome, 'type' => 'variable',
        'tax_type' => 'inclusive', 'sku' => 'GM' . uniqid(), 'created_by' => $userId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Variação nomeada (o `name` é o que o GradeLayoutBuilder parseia pra montar a grade). */
function gmVariacao(int $produtoId, string $nome, float $custo = 22.5): int
{
    // `variations.product_variation_id` é NOT NULL sem default — o UltimatePOS guarda o
    // eixo em `product_variations`, então a linha-pai vem junto.
    $pvId = DB::table('product_variations')->insertGetId([
        'product_id' => $produtoId, 'name' => 'Padrao', 'is_dummy' => 0,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return (int) DB::table('variations')->insertGetId([
        'product_id' => $produtoId, 'product_variation_id' => $pvId, 'name' => $nome,
        'default_purchase_price' => $custo, 'dpp_inc_tax' => $custo,
        'profit_percent' => 0, 'default_sell_price' => $custo, 'sell_price_inc_tax' => $custo,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/**
 * Payload mínimo de `POST /purchases` carregando UMA linha de grade.
 *
 * Só o `purchases[].variation_id` importa pro gate de ownership — ele roda ANTES do resto
 * do store(). O que vem depois existe pro controle positivo não morrer por validação antes
 * de chegar no gate. A data vai em `m/d/Y H:i` porque `Util::uf_date` monta o formato de
 * `session('business.date_format')`, populado pelo `SetSessionData` — daí `actingAs()` puro,
 * sem `withSession(['user' => ...])`, que faria o middleware pular a população inteira.
 */
function gmPayloadCompra(int $variationId, int $produtoId): array
{
    return [
        'location_id' => DB::table('business_locations')->value('id'),
        'transaction_date' => now()->format('m/d/Y H:i'),
        'status' => 'received', 'ref_no' => 'GM-' . uniqid(),
        'total_before_tax' => '22,50', 'discount_type' => 'fixed', 'discount_amount' => '0',
        'tax_amount' => '0', 'shipping_charges' => '0', 'final_total' => '22,50',
        'exchange_rate' => 1,
        'purchases' => [[
            'product_id' => $produtoId, 'variation_id' => $variationId,
            'quantity' => '1', 'purchase_price' => '22,50', 'purchase_price_inc_tax' => '22,50',
        ]],
    ];
}

/** Usuário do tenant com `purchase.create` (o gate de gradeMatrix e de store). */
function gmUsuario(int $bizId): User
{
    $perm = Permission::firstOrCreate(['name' => 'purchase.create', 'guard_name' => 'web']);
    // roles.business_id é NOT NULL + FK (proibicoes §FSM) — sufixo #biz é convenção UPOS.
    $role = Role::firstOrCreate(
        ['name' => 'gm-purchase-test#' . $bizId, 'guard_name' => 'web'],
        ['business_id' => $bizId]
    );
    $role->givePermissionTo($perm);

    // Username determinístico: a base do CT 100 persiste entre runs e users viram FK em
    // várias tabelas — user novo a cada execução vazaria linha impossível de limpar.
    $user = User::where('business_id', $bizId)->where('username', 'gm_purchase_' . $bizId)->first()
        ?? User::factory()->create([
            'business_id' => $bizId, 'username' => 'gm_purchase_' . $bizId,
            'user_type' => 'user', 'allow_login' => 1,
        ]);
    $user->assignRole($role);

    return $user;
}

describe('Tier 0 cross-tenant (MySQL real)', function () {
    beforeEach(function () {
        if (DB::connection()->getDriverName() !== 'mysql' || ! Schema::hasTable('business')) {
            test()->markTestSkipped(
                'Precisa do schema UltimatePOS em MySQL real. Lane dona: compras-pest.yml '
                . '(.github/actions/pest-mysql-setup). Não roda em sqlite :memory:.'
            );
        }

        $this->gmProdutos = [];

        // biz=99 — o seed do CI só cria 1/2/98; o helper é idempotente e resolve o FK
        // circular business.owner_id <-> users.business_id.
        $this->gmBizVitima = $this->seededSupportClientTenant();
        $this->gmBizProprio = $this->seededTenant();

        $this->gmUser = gmUsuario((int) $this->gmBizProprio->id);
        $this->gmUserVitima = gmUsuario((int) $this->gmBizVitima->id);
    });

    afterEach(function () {
        foreach ($this->gmProdutos ?? [] as $produtoId) {
            DB::table('purchase_lines')->where('product_id', $produtoId)->delete();
            DB::table('variations')->where('product_id', $produtoId)->delete();
            DB::table('product_variations')->where('product_id', $produtoId)->delete();
            DB::table('products')->where('id', $produtoId)->delete();
        }
    });

    // ── UC-PURCRE-02 — o endpoint da grade ──────────────────────────────────

    it('UC-PURCRE-02 · cross-tenant — grade-matrix de produto do biz=99 devolve 404 pro usuário do biz=98', function () {
        $produtoVitima = gmProduto((int) $this->gmBizVitima->id, (int) $this->gmUserVitima->id, 'Camiseta vítima');
        $this->gmProdutos[] = $produtoVitima;
        gmVariacao($produtoVitima, 'P/Preto');

        $resposta = $this->actingAs($this->gmUser)
            ->getJson('/purchases/grade-matrix?product_id=' . $produtoVitima);

        // 404 de verdade aqui (diferente do update, onde um catch genérico converte em 302):
        // o gradeMatrix não está dentro de try/catch, então o ModelNotFoundException do
        // firstOrFail escopado chega ao handler do Laravel.
        $resposta->assertStatus(404);
    });

    it('UC-PURCRE-02 · controle positivo — grade-matrix do produto do próprio negócio devolve a grade 2D montada', function () {
        $produto = gmProduto((int) $this->gmBizProprio->id, (int) $this->gmUser->id, 'Camiseta');
        $this->gmProdutos[] = $produto;
        foreach (['P/Preto', 'M/Preto', 'P/Branco'] as $nome) {
            gmVariacao($produto, $nome);
        }

        $resposta = $this->actingAs($this->gmUser)
            ->getJson('/purchases/grade-matrix?product_id=' . $produto);

        $resposta->assertStatus(200);
        $payload = $resposta->json();

        expect($payload['mode'])->toBe('2d');
        expect(array_column($payload['rows'], 'label'))->toBe(['P', 'M']);
        expect(array_column($payload['cols'], 'label'))->toBe(['Preto', 'Branco']);
        // Célula esparsa (M/Branco não existe) não é mapeada — a grade desabilita a célula.
        expect($payload['cellVariationMap'])->toHaveCount(3);
    });

    // ── UC-PURCRE-03 — o caminho de ESCRITA (valor + estoque) ───────────────

    it('UC-PURCRE-03 · store() recusa variation_id forjado de outro tenant (422) e não grava linha', function () {
        $produtoVitima = gmProduto((int) $this->gmBizVitima->id, (int) $this->gmUserVitima->id, 'Produto alheio');
        $this->gmProdutos[] = $produtoVitima;
        $variacaoAlheia = gmVariacao($produtoVitima, 'P/Preto');

        $resposta = $this->actingAs($this->gmUser)
            ->post('/purchases', gmPayloadCompra($variacaoAlheia, $produtoVitima));

        // `assertPurchaseVariationsOwnership` roda FORA do try do store() — o próprio código
        // comenta que é pra o 422 não ser engolido pelo catch genérico.
        $resposta->assertStatus(422);

        expect(DB::table('purchase_lines')->where('variation_id', $variacaoAlheia)->count())->toBe(
            0,
            'VAZAMENTO TIER 0: o payload forjado gravou purchase_line com variação de outro negócio.'
        );
    });

    it('UC-PURCRE-03 · controle positivo — a mesma submissão com variação do PRÓPRIO negócio não é recusada por ownership', function () {
        $produto = gmProduto((int) $this->gmBizProprio->id, (int) $this->gmUser->id, 'Produto próprio');
        $this->gmProdutos[] = $produto;
        $variacaoPropria = gmVariacao($produto, 'P/Preto');

        $resposta = $this->actingAs($this->gmUser)
            ->post('/purchases', gmPayloadCompra($variacaoPropria, $produto));

        // O par com o teste acima é o que prova que o 422 vem do OWNERSHIP e não de um
        // abort incondicional: mesma rota, mesmo payload, só muda o dono da variação.
        //
        // ⚠️ O bar é "NÃO é 422", e é deliberado — não confundir com happy-path. Medido no
        // CT 100: com este payload mínimo o same-tenant devolve 302 pra /purchases com
        // `success:0 / "Something went wrong, please try again later"` e NÃO cria a compra
        // (falta fornecedor/unidade, que este UC não cobre). O que este teste afirma é só o
        // que ele mede: o gate de ownership deixa passar a variação do próprio negócio.
        // A criação completa é outro UC — quem for cobri-la escreve o teste dela, não
        // aperta este assert.
        expect($resposta->status())->not->toBe(
            422,
            'O gate de ownership recusou uma variação do PRÓPRIO negócio — o 422 do teste acima não prova scope.'
        );
    });
});
