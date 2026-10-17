<?php

declare(strict_types=1);

use App\Services\Purchase\GradeLayoutBuilder;
use App\User;
use Inertia\Testing\AssertableInertia;
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
 * O bloco cross-tenant montava `products`/`variations` a mao e pulava fora de sqlite — ou seja,
 * nao executava em lane nenhuma (a suite real e MySQL; a allowlist sqlite nao tem uma linha
 * Purchase). Foi reescrito em 2026-09-05 contra o schema semeado por
 * `.github/actions/pest-mysql-setup` — o describe agora e `Tier 0 cross-tenant (MySQL real)`
 * e o skip inverteu (pula FORA do MySQL), entao roda na lane real.
 *
 * ── Rastreabilidade (casos-gate G-2 · ADR 0264) ────────────────────────────
 * Contrato: resources/js/Pages/Purchase/Create.casos.md
 *   @covers-uc UC-PURCRE-02  endpoint da grade recusa produto de outro tenant
 *   @covers-uc UC-PURCRE-03  store() recusa variation_id forjado (anti payload cross-tenant)
 *   @covers-uc UC-PURCRE-04  a grade nunca abre vazia — degrada 2D -> 1 eixo -> single
 *   @covers-uc UC-PURCRE-09  "Comprar" da lista de produtos abre a compra com o produto (so o do proprio negocio)
 *
 * COBERTURA POR BLOCO (medida 2026-09-04, atualizada 2026-09-05):
 *   - 6 casos do parser (GradeLayoutBuilder, logica pura): COMPORTAMENTO REAL, EXECUTAM.
 *     Sustentam o UC-PURCRE-04.
 *   - 6 casos estruturais (rota + scope no fonte + wiring da Page): executam, mas sao
 *     casamento de texto (classe LC-11).
 *   - describe Tier 0 cross-tenant: SAIU DA QUARENTENA em 2026-09-05 (#6820) — bate no
 *     ENDPOINT em MySQL real e sustenta UC-PURCRE-02 e UC-PURCRE-03. O `estrutural
 *     (comportamento em quarentena)` do casos.md deixa de valer para esses dois.
 *
 * @see resources/js/Pages/Purchase/Create.casos.md (UC-PURCRE-02 · UC-PURCRE-03 · UC-PURCRE-04)
 *
 * ADRs: 0093 (Tier 0 IRREVOGAVEL), 0104 (MWART), 0105 (Larissa sinal), 0358 (tenant 98/99),
 * C1 (convergencia).
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
    // purchase.update: os UC-PUREDT-08 editam a compra que o próprio teste criou.
    $permUpdate = Permission::firstOrCreate(['name' => 'purchase.update', 'guard_name' => 'web']);
    // roles.business_id é NOT NULL + FK (proibicoes §FSM) — sufixo #biz é convenção UPOS.
    $role = Role::firstOrCreate(
        ['name' => 'gm-purchase-test#' . $bizId, 'guard_name' => 'web'],
        ['business_id' => $bizId]
    );
    $role->givePermissionTo($perm, $permUpdate);

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
    // ── UC-PURCRE-10 — salvar com o payload que a tela React manda ──────────

    it('UC-PURCRE-10 · store() grava a compra com o payload da tela React (sem `payment`, data ISO)', function () {
        // Tudo dentro de uma transação revertida no fim: o store() abre a dele (vira savepoint)
        // e a base persistente do CT 100 não fica com a compra (§5 2026-09-18).
        DB::beginTransaction();
        try {
            $bizId = (int) $this->gmBizProprio->id;
            $produto = gmProduto($bizId, (int) $this->gmUser->id, 'Produto compra React');
            $variacao = gmVariacao($produto, 'P/Preto');
            $fornecedor = (int) DB::table('contacts')->insertGetId([
                'business_id' => $bizId, 'type' => 'supplier', 'name' => 'Fornecedor UC-PURCRE-10',
                'contact_id' => 'UCP10' . uniqid(), 'created_by' => (int) $this->gmUser->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $refNo = 'UCP10-' . uniqid();

            // Espelha o `enviar()` do Create.tsx: números crus, data do datetime-local e
            // NENHUMA chave `payment` — a tela não tem bloco de pagamento.
            $resposta = $this->actingAs($this->gmUser)->post('/purchases', [
                'ref_no' => $refNo, 'status' => 'received', 'contact_id' => $fornecedor,
                'transaction_date' => now()->format('Y-m-d H:i'),
                'location_id' => DB::table('business_locations')->where('business_id', $bizId)->value('id'),
                'discount_type' => 'fixed', 'discount_amount' => '0', 'tax_id' => '', 'tax_amount' => '0',
                'shipping_charges' => '0', 'additional_notes' => '', 'exchange_rate' => '1',
                'total_before_tax' => '45', 'final_total' => '45',
                'purchases' => [[
                    'product_name' => 'Produto compra React P/Preto', 'product_id' => $produto,
                    'variation_id' => $variacao, 'unit_id' => '', 'unit_name' => 'un',
                    'quantity' => '2', 'pp_without_discount' => '22.5', 'discount_percent' => '0',
                    'item_tax' => '0', 'tax_id' => '', 'purchase_price' => '22.5',
                    'purchase_price_inc_tax' => '22.5', 'purchase_line_tax_id' => '',
                ]],
            ]);

            $resposta->assertRedirect('/purchases');
            expect(session('status.success'))->toBe(1, 'store() caiu no catch genérico: ' . json_encode(session('status')));

            $compra = DB::table('transactions')->where('business_id', $bizId)
                ->where('type', 'purchase')->where('ref_no', $refNo)->first();
            expect($compra)->not->toBeNull();
            expect((float) $compra->final_total)->toBe(45.0);
            expect($compra->payment_status)->toBe('due');
            expect((float) DB::table('purchase_lines')->where('transaction_id', $compra->id)->sum('quantity'))->toBe(2.0);
        } finally {
            DB::rollBack();
        }
    });

    it('UC-PURCRE-11 · fornecedor de outro negócio ou item sem produto voltam como erro do campo, sem gravar', function () {
        DB::beginTransaction();
        try {
            $bizId = (int) $this->gmBizProprio->id;
            $fornecedorAlheio = (int) DB::table('contacts')->insertGetId([
                'business_id' => (int) $this->gmBizVitima->id, 'type' => 'supplier', 'name' => 'Fornecedor alheio',
                'contact_id' => 'UCP11' . uniqid(), 'created_by' => (int) $this->gmUserVitima->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $refNo = 'UCP11-' . uniqid();

            // Linha digitada à mão na tela (sem produto do cadastro) + fornecedor de outro tenant.
            $resposta = $this->actingAs($this->gmUser)->from('/purchases/create')->post('/purchases', [
                'ref_no' => $refNo, 'status' => 'received', 'contact_id' => $fornecedorAlheio,
                'transaction_date' => now()->format('Y-m-d H:i'),
                'location_id' => DB::table('business_locations')->where('business_id', $bizId)->value('id'),
                'discount_type' => 'fixed', 'discount_amount' => '0', 'tax_amount' => '0',
                'shipping_charges' => '0', 'exchange_rate' => '1', 'total_before_tax' => '10', 'final_total' => '10',
                'purchases' => [[
                    'product_name' => 'digitado', 'product_id' => '', 'variation_id' => '',
                    'quantity' => '1', 'pp_without_discount' => '10', 'discount_percent' => '0', 'item_tax' => '0',
                    'purchase_price' => '10', 'purchase_price_inc_tax' => '10', 'purchase_line_tax_id' => '',
                ]],
            ]);

            // Antes: 302 pra /purchases com "algo deu errado". Agora: volta pro form com o erro de cada campo.
            $resposta->assertRedirect('/purchases/create');
            $resposta->assertSessionHasErrors(['contact_id', 'purchases.0.product_id', 'purchases.0.variation_id']);
            expect(DB::table('transactions')->where('ref_no', $refNo)->count())->toBe(0);
        } finally {
            DB::rollBack();
        }
    });

    it('UC-PUREDT-08 · editar pela tela React atualiza a linha existente (não duplica) e acerta o estoque', function () {
        DB::beginTransaction();
        try {
            $bizId = (int) $this->gmBizProprio->id;
            $local = DB::table('business_locations')->where('business_id', $bizId)->value('id');
            $produto = gmProduto($bizId, (int) $this->gmUser->id, 'Produto edição React');
            // Sem enable_stock o ProductUtil não movimenta estoque (gmProduto nasce com o default 0).
            DB::table('products')->where('id', $produto)->update(['enable_stock' => 1]);
            $variacao = gmVariacao($produto, 'P/Preto');
            $fornecedor = (int) DB::table('contacts')->insertGetId([
                'business_id' => $bizId, 'type' => 'supplier', 'name' => 'Fornecedor UC-PUREDT-08',
                'contact_id' => 'UCE08' . uniqid(), 'created_by' => (int) $this->gmUser->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $refNo = 'UCE08-' . uniqid();
            $cabecalho = [
                'ref_no' => $refNo, 'status' => 'received', 'contact_id' => $fornecedor,
                'transaction_date' => now()->format('Y-m-d H:i'), 'location_id' => $local,
                'discount_type' => 'fixed', 'discount_amount' => '0', 'tax_id' => '', 'tax_amount' => '0',
                'shipping_charges' => '0', 'additional_notes' => '', 'exchange_rate' => '1',
            ];
            $linha = fn (string $qtd) => [
                'product_name' => 'Produto edição React P/Preto', 'product_id' => $produto,
                'variation_id' => $variacao, 'unit_id' => '', 'unit_name' => 'un', 'quantity' => $qtd,
                'pp_without_discount' => '22.5', 'discount_percent' => '0', 'item_tax' => '0', 'tax_id' => '',
                'purchase_price' => '22.5', 'purchase_price_inc_tax' => '22.5', 'purchase_line_tax_id' => '',
            ];
            $estoque = fn () => (float) DB::table('variation_location_details')
                ->where('variation_id', $variacao)->where('location_id', $local)->sum('qty_available');

            $this->actingAs($this->gmUser)->post('/purchases', $cabecalho + [
                'total_before_tax' => '45', 'final_total' => '45', 'purchases' => [$linha('2')],
            ]);
            $compra = DB::table('transactions')->where('business_id', $bizId)->where('ref_no', $refNo)->first();
            expect($compra)->not->toBeNull();
            $linhaId = (int) DB::table('purchase_lines')->where('transaction_id', $compra->id)->value('id');
            expect($estoque())->toBe(2.0);

            // O que o Edit.tsx manda: _method PUT + a linha com purchase_line_id (era `id`).
            $resposta = $this->actingAs($this->gmUser)->post('/purchases/' . $compra->id, $cabecalho + [
                '_method' => 'PUT', 'total_before_tax' => '67.5', 'final_total' => '67.5',
                'purchases' => [$linha('3') + ['purchase_line_id' => $linhaId]],
            ]);

            $resposta->assertRedirect('/purchases');
            expect(session('status.success'))->toBe(1, 'update() falhou: ' . json_encode(session('status')));
            $linhas = DB::table('purchase_lines')->where('transaction_id', $compra->id)->get();
            expect($linhas)->toHaveCount(1);
            expect((int) $linhas[0]->id)->toBe($linhaId);
            expect((float) $linhas[0]->quantity)->toBe(3.0);
            expect((float) DB::table('transactions')->where('id', $compra->id)->value('final_total'))->toBe(67.5);
            // Estoque: 2 da criação + 1 da edição — não 2 + 3 (linha nova) nem 3 − 2.
            expect($estoque())->toBe(3.0);
        } finally {
            DB::rollBack();
        }
    });

    it('UC-PUREDT-08 · purchase_line_id de outra compra é recusado (422) antes de gravar', function () {
        $resposta = $this->actingAs($this->gmUser)->post('/purchases/999999999', [
            '_method' => 'PUT',
            'purchases' => [['purchase_line_id' => (int) DB::table('purchase_lines')->max('id') ?: 1]],
        ]);

        $resposta->assertStatus(422);
    });

    // ── UC-PURCRE-09 — "Comprar" da lista de produtos ───────────────────────

    it('UC-PURCRE-09 · ?produto= de um produto do próprio negócio entra como produto_inicial', function () {
        $produto = gmProduto((int) $this->gmBizProprio->id, (int) $this->gmUser->id, 'Camiseta comprar');
        $this->gmProdutos[] = $produto;
        gmVariacao($produto, 'P/Preto');
        DB::table('products')->where('id', $produto)->update(['enable_stock' => 1]);

        $this->actingAs($this->gmUser)
            ->get('/purchases/create?produto=' . $produto)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Purchase/Create', false)
                ->where('produto_inicial', ['product_id' => $produto, 'label' => 'Camiseta comprar']));
    });

    it('UC-PURCRE-09 · cross-tenant — ?produto= de produto do biz=99 não entra na compra do biz=98', function () {
        $produtoVitima = gmProduto((int) $this->gmBizVitima->id, (int) $this->gmUserVitima->id, 'Camiseta vítima');
        $this->gmProdutos[] = $produtoVitima;
        gmVariacao($produtoVitima, 'P/Preto');
        DB::table('products')->where('id', $produtoVitima)->update(['enable_stock' => 1]);

        $this->actingAs($this->gmUser)
            ->get('/purchases/create?produto=' . $produtoVitima)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Purchase/Create', false)
                ->where('produto_inicial', null));
    });

    it('UC-PURCRE-09 · produto sem controle de estoque segue a regra da busca da compra e não entra', function () {
        $produto = gmProduto((int) $this->gmBizProprio->id, (int) $this->gmUser->id, 'Serviço sem estoque');
        $this->gmProdutos[] = $produto;
        gmVariacao($produto, 'Único');
        DB::table('products')->where('id', $produto)->update(['enable_stock' => 0]);

        $this->actingAs($this->gmUser)
            ->get('/purchases/create?produto=' . $produto)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Purchase/Create', false)
                ->where('produto_inicial', null));
    });
});
