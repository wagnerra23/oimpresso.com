<?php

declare(strict_types=1);
// Cobre UC-PEDIT-05, UC-PEDIT-06, UC-PEDIT-07 (resources/js/Pages/Produto/Edit.casos.md)
// — G-2 rastreabilidade caso↔teste (ADR 0264).

use App\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * Contrato do PAYLOAD de Editar Produto (`PUT /products/{id}`).
 *
 * ÂNCORA (contrato, NÃO implementação)
 * ─────────────────────────────────────────────────────────────────────────────
 * - `AR-PROD-051` / `AR-PROD-056` — paridade Delphi (Office Comercial 2026.1.1.38):
 *   o cadastro guarda "controla estoque" como atributo do produto; editar a ficha
 *   NÃO é o gesto que liga/desliga controle de estoque.
 * - `proibicoes.md` §REGRA MESTRE (CÁLCULO DE VALOR ou ESTOQUE) — toda alteração que
 *   possa mexer em ESTOQUE precisa ser deliberada e provada. Uma edição de NOME que
 *   apaga o controle de estoque é o oposto: muda estoque em silêncio.
 * - `Edit.charter.md` §Goals — "editar a ficha do produto"; desligar estoque não é Goal.
 * - `CU-PROD-02` (SDD §6.1) pro ramo `variable`.
 *
 * Os asserts saem daí — **não** do `ProductController@update`. Teste derivado do código
 * é tautológico: passa mesmo com o comportamento errado e trava o desvio em vez de
 * pegá-lo (`proibicoes.md` §5, entrada 2026-06-05).
 *
 * POR QUE ESTE TESTE EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Achado do B1-controle 2026-07-24 (1º run real do agent `sdd-from-source`, ADR 0351 —
 * evidência em `memory/requisitos/Produto/_b1-controle-Edit.casos.agent.md`), verificado
 * de forma independente por leitura: o `useForm` de `Edit.tsx` manda **18 chaves**; o
 * `update()` lê **33+** (via `$request->only([...])` mais `$request->input(...)`), e o
 * padrão do controller é **ausência → zero**:
 *
 *   ProductController@update  L76-79   enable_stock     ausente → 0
 *                             L82      not_for_selling  ausente → 0
 *                             L101-104 enable_sr_no     ausente → 0
 *                             L71      sub_unit_ids     ausente → null
 *                             L1111-12 single_variation_id ausente → Variation::find(null)
 *                                      → atribuição em null → \Error (o catch (\Exception) não pega) → 500
 *
 * ⚠️ **Não é incidente de produção.** As telas React do Produto são duais
 * (`if (request()->header('X-Inertia'))` — `ProductController:342/909`) e **inalcançáveis
 * hoje**: a sidebar do cockpit usa `<a href>` puro (`Sidebar.tsx:489`), que não manda o
 * header, e todos os `<Link href="/products">` vivem dentro das próprias páginas do
 * Produto (circular, sem porta de entrada). O que roda em prod é o Blade, que manda o
 * payload completo — confirmado por [F] 2026-07-24. Isto é **bloqueador de migração**
 * (MWART F5 — [ADR 0104]): define quando a tela React pode ser ligada.
 *
 * ⚠️ Failing-first (padrão #4300 / #4417): se nascer vermelho, o vermelho É o achado.
 * NÃO se ajusta o teste ao código. E como o eixo é ESTOQUE, o fix é decisão [W] sob a
 * REGRA MESTRE (2 caminhos + tabela antes→depois), não conserto silencioso aqui.
 *
 * ⚠️ São TRÊS defeitos independentes, não uma raiz (`proibicoes.md` §5, 2026-07-15):
 * contrato do payload (o que a tela manda) · contrato do writer (ausência→zero) ·
 * ausência de validação. Consertar um não conserta os outros, e as correções podem
 * brigar entre si — por isso cada UC tem seu próprio teste.
 *
 * ⛔ Multi-tenant Tier 0 (ADR 0101): biz=1 canônico. NUNCA biz=4 (ROTA LIVRE, cliente real).
 */
uses(DatabaseTransactions::class);

/**
 * Payload EXATO do `useForm` de `resources/js/Pages/Produto/Edit.tsx` (18 chaves).
 * Reproduz o que a tela React envia hoje — é o INPUT do contrato, não o assert.
 */
function payloadDaTelaReact(Product $produto): array
{
    return [
        'name' => $produto->name . ' (editado)',
        'sku' => $produto->sku,
        'brand_id' => $produto->brand_id ?? '',
        'unit_id' => $produto->unit_id,
        'category_id' => $produto->category_id ?? '',
        'sub_category_id' => $produto->sub_category_id ?? '',
        'tax' => $produto->tax ?? '',
        'tax_type' => $produto->tax_type,
        'barcode_type' => $produto->barcode_type,
        'alert_quantity' => $produto->alert_quantity ?? '',
        'weight' => $produto->weight ?? '',
        'product_description' => $produto->product_description ?? '',
        'product_locations' => [],
        'warranty_id' => $produto->warranty_id ?? '',
        'product_custom_field1' => $produto->product_custom_field1 ?? '',
        'product_custom_field2' => $produto->product_custom_field2 ?? '',
        'product_custom_field3' => $produto->product_custom_field3 ?? '',
        'product_custom_field4' => $produto->product_custom_field4 ?? '',
    ];
}

/**
 * Payload da tela + a chave MÍNIMA que destrava o `save()`.
 *
 * MEDIDO na 1ª corrida (run 30122144831, 2026-07-24): com o payload puro da tela, o
 * `update()` **aborta antes de salvar**. `ProductController:1037` faz
 * `$product_details['preparation_time_in_minutes']` **sem `??`** — diferente dos
 * `product_custom_field*` logo acima (L1030-1033), que têm. A chave não vem no `only()`,
 * o acesso vira `ErrorException`, e o `catch (\Exception)` genérico a engole → redirect
 * **sem 500 e sem gravar**. O log da lane registra 3× `EMERGENCY … Undefined array key
 * "preparation_time_in_minutes"` na rota `products.update`.
 *
 * POR QUE ISSO OBRIGA A MUDAR O DESENHO DO TESTE (e não só o assert):
 * na 1ª corrida os UC-PEDIT-05/07 passaram **VERDE — no vácuo**. `enable_stock` "sobreviveu"
 * porque **nada foi escrito**, não porque o writer o preserva. Verde por não-execução é o
 * verde tautológico que este projeto bane (`proibicoes.md` §5, 2026-06-05): passa mesmo com
 * o comportamento errado, e trava o desvio em vez de pegá-lo.
 *
 * Correção: os UCs de preservação passam a (a) usar um payload que **chega no save** e
 * (b) carregar uma **pré-condição explícita** — se a persistência não aconteceu, o teste
 * falha DIZENDO isso, em vez de mentir verde. Assim cada UC isola UMA variável.
 */
function payloadQueChegaNoSave(Product $produto): array
{
    return payloadDaTelaReact($produto) + ['preparation_time_in_minutes' => ''];
}

/**
 * Pré-condição anti-vácuo: o PUT realmente persistiu?
 * Sem isto, "o campo X sobreviveu" não distingue *preservado* de *nunca escrito*.
 */
function exigeQueTenhaPersistido(int $productId, string $nomeEsperado, string $uc): void
{
    expect(Product::findOrFail($productId)->name)->toBe(
        $nomeEsperado,
        "PRÉ-CONDIÇÃO do {$uc}: o PUT não persistiu — então este UC NÃO foi exercido. "
        . 'Verde aqui seria vácuo (§5 2026-06-05). Provável aborto antes do save '
        . '(ver `preparation_time_in_minutes`, ProductController:1037).'
    );
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    try {
        $this->business = $this->seededTenant(); // biz=1 (ADR 0101 — nunca biz=4).
    } catch (\Throwable $e) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql no CT 100.');
    }

    $this->user = \App\User::where('business_id', $this->business->id)->first();
    if (! $this->user) {
        $this->markTestSkipped('Sem user no business seeded.');
    }

    $this->actingAs($this->user);
    session([
        'user.business_id' => $this->business->id,
        'user.id' => $this->user->id,
    ]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('product.update', 'web');
    $this->user->givePermissionTo(['product.update']);
});

// =============================================================================
// UC-PEDIT-05 `[V0]` — AR-PROD-051/056 + REGRA MESTRE:
//   editar a ficha NÃO altera o controle de estoque do produto.
//   Usa `variable` de propósito: é o ramo que (per B1) SALVA — logo o assert mede
//   o cabeçalho, não é mascarado pelo 500 do ramo `single` (UC-PEDIT-06).
// =============================================================================

it('UC-PEDIT-05 · editar produto não desliga o controle de estoque (enable_stock)', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);

    $produto = Product::findOrFail($p->productId);
    $produto->enable_stock = 1;
    $produto->save();

    expect((int) Product::findOrFail($p->productId)->enable_stock)
        ->toBe(1, 'pré-condição: o produto nasce controlando estoque');

    $payload = payloadQueChegaNoSave($produto);
    $this->put("/products/{$p->productId}", $payload);

    // anti-vácuo: sem isto, "enable_stock continua 1" não distingue PRESERVADO de NUNCA-ESCRITO
    exigeQueTenhaPersistido($p->productId, $payload['name'], 'UC-PEDIT-05');

    expect((int) Product::findOrFail($p->productId)->enable_stock)->toBe(
        1,
        'AR-PROD-051/056 + REGRA MESTRE: editar a ficha não pode desligar o controle de estoque. '
        . 'A tela não manda `enable_stock`, e o writer trata ausência como 0 (update() L76-79).'
    );
});

// =============================================================================
// UC-PEDIT-07 — AR-PROD-003/042: editar não apaga o que a tela não toca.
//   Flags/campos ausentes do payload devem ser PRESERVADOS, não zerados.
// =============================================================================

it('UC-PEDIT-07 · editar não apaga flags que a tela não envia', function () {
    $p = EstoqueFixture::variableProduct($this->business->id, 2);

    $produto = Product::findOrFail($p->productId);
    $produto->not_for_selling = 1;
    $produto->enable_sr_no = 1;
    $produto->save();

    $payload = payloadQueChegaNoSave($produto);
    $this->put("/products/{$p->productId}", $payload);

    // anti-vácuo (mesma razão do UC-PEDIT-05)
    exigeQueTenhaPersistido($p->productId, $payload['name'], 'UC-PEDIT-07');

    $depois = Product::findOrFail($p->productId);

    expect((int) $depois->not_for_selling)->toBe(
        1,
        'AR-PROD-003/042: `not_for_selling` não está no payload da tela; ausência não é "desmarcar" (update() L82).'
    );
    expect((int) $depois->enable_sr_no)->toBe(
        1,
        'AR-PROD-003/042: `enable_sr_no` não está no payload da tela; ausência não é "desmarcar" (update() L101-104).'
    );
});

// =============================================================================
// UC-PEDIT-06 — Edit.charter §Goals: "Salvar" persiste.
//
// ⚠️ O MECANISMO PREVISTO ESTAVA ERRADO — o teste corrigiu (run 30122144831).
//   Previsto: `Variation::find(null)` → atribuição em null → `\Error` → 500.
//   MEDIDO:   aborta ANTES, em `preparation_time_in_minutes` (L1037, sem `??`), e o
//             `catch (\Exception)` engole → **redirect, sem 500 e sem gravar**.
//   O 1º assert (não-500) PASSOU; o que reprovou foi o `name` intacto no banco.
//   Ou seja: o desfecho é PIOR que 500 — falha SILENCIOSA. O usuário é redirecionado
//   como se tivesse salvo. 500 ao menos grita.
//
//   Este UC usa de propósito o payload PURO da tela (sem a chave que destrava o save):
//   é exatamente esse payload que a tela manda hoje, e é ele que precisa persistir.
// =============================================================================

it('UC-PEDIT-06 · editar produto single persiste em vez de estourar 500', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);

    $produto = Product::findOrFail($p->productId);
    $nomeNovo = $produto->name . ' (editado)';

    $resposta = $this->put("/products/{$p->productId}", payloadDaTelaReact($produto));

    expect($resposta->getStatusCode())->not->toBe(
        500,
        'Edit.charter §Goals: "Salvar" é um Goal da tela. O payload da tela React não manda '
        . '`single_variation_id`; o writer o lê de um `only()` que não o contém (update() L1111-12).'
    );

    expect(Product::findOrFail($p->productId)->name)->toBe(
        $nomeNovo,
        'Edit.charter §Goals: se a tela diz que salvou, o banco tem que refletir.'
    );
});

// =============================================================================
// UC-PEDIT-08 `[V0]` — A COMPENSAÇÃO: desligar as 3 flags continua possível pela Blade.
//
//   POR QUE ESTE UC EXISTE (e por que ele é o par obrigatório do UC-PEDIT-05/07):
//   os UC-05/07 exigem que AUSÊNCIA preserve. Sozinho, esse contrato tornaria
//   IMPOSSÍVEL desligar as flags na tela que roda em produção — checkbox desmarcado
//   não envia chave nenhuma (`spatie/laravel-html::checkbox()` não emite hidden, ao
//   contrário do Laravel Collective). A correção só é completa com o par:
//     (a) o writer preserva quando a chave está AUSENTE  → UC-PEDIT-05/07
//     (b) a tela DECLARA o desligamento com `hidden 0`   → ESTE UC
//   Sem (b), o operador clica em desmarcar, salva, e a caixa volta marcada.
//
//   ⚠️ O que este UC NÃO cobre: a camada JS do navegador (o plugin `input-icheck`
//   embrulha o checkbox). Isso é browser real — smoke em biz=1 pós-merge, não Pest.
// =============================================================================

it('UC-PEDIT-08 · a tela Blade declara o desligamento das 3 flags (hidden 0)', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);

    Permission::findOrCreate('product.view', 'web');
    $this->user->givePermissionTo(['product.view']);

    // CAUSA MEDIDA em 2 corridas (30384389834 e 30385017017): o GET dava 500 com
    // `Undefined array key "REMOTE_ADDR"` em `layouts/app.blade.php`.
    // A 1ª tentativa (`withServerVariables`) NÃO resolveu, e o motivo é preciso:
    // `app.blade.php:56` lê a SUPERGLOBAL crua — `in_array($_SERVER['REMOTE_ADDR'], $whitelist)`
    // — não `request()->server()`. `withServerVariables` popula o server bag do Request,
    // que é outro objeto. Só setar a superglobal resolve.
    // ARRANGE puro, sem relação com os 3 hidden: em prod o servidor sempre popula.
    // Superglobais lidas CRUAS no caminho desta tela. Varredura contada (2 de 2) em
    // `app/Http/helpers.php` + `resources/views/layouts/` + `resources/views/product/`:
    //   $_SERVER['REMOTE_ADDR']      → app.blade.php:56  (whitelist de localhost)
    //   $_SERVER['HTTP_USER_AGENT']  → app/Http/helpers.php:72
    // Nenhum middleware alcança superglobal; em prod o servidor sempre popula as duas.
    // Enumeradas de uma vez pra parar de descobrir uma por corrida de CI.
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_USER_AGENT'] = 'Pest/CI';

    // O layout legado precisa de MUITA coisa na sessão (moeda, negócio, ano fiscal...).
    // Remendar peça por peça já custou 3 corridas de CI — e sempre faltava a próxima.
    // Causa real: o `beforeEach` monta a sessão à mão, e `SetSessionData` só reconstrói
    // quando NÃO existe o bloco `user` (SetSessionData.php:29). Esquecendo esse bloco, o
    // middleware faz o que faz num login de verdade e popula TUDO de uma vez — a mesma
    // raiz do PR #4953 (estoque inicial), resolvida no mecanismo em vez de campo a campo.
    session()->forget('user');

    $resposta = $this->get("/products/{$p->productId}/edit");
    $html = $resposta->getContent();

    // Degraus de diagnóstico: sem eles, "não achou o hidden" não distingue
    // "a Blade não declara" de "a resposta nem era a Blade". A 1ª corrida (run
    // 30383633898) devolveu uma página com `<html lang="en" class="auto">` — que não é
    // nem o `layouts.app` (Blade) nem o `layouts.inertia`; provavelmente página de erro.
    // A run 30384071699 devolveu 500 — e o número sozinho não separa "ambiente de teste
    // incompleto" de "eu quebrei a Blade". A causa vem da exceção que o handler capturou.
    $causa = $resposta->exception
        ? get_class($resposta->exception) . ': ' . $resposta->exception->getMessage()
          . ' @ ' . $resposta->exception->getFile() . ':' . $resposta->exception->getLine()
        : '(sem exceção capturada)';

    expect($resposta->getStatusCode())->toBe(
        200,
        'PRÉ-CONDIÇÃO: GET /products/{id}/edit tem que abrir a tela. Outro status = o UC não foi '
        . 'exercido. CAUSA REAL: ' . $causa
    );
    // A run 30396925504 devolveu 200 SEM o form — ou seja, a tela abriu e é OUTRA página.
    // "Não contém o form" não diz QUAL página veio; a assinatura abaixo diz.
    preg_match('/<title>(.*?)<\/title>/s', $html, $t);
    $assinatura = sprintf(
        'titulo=%s | tamanho=%d | tem_form=%s | tem_inertia=%s | tem_login=%s | tem_app_id=%s | inicio=%s',
        trim($t[1] ?? '(sem title)'),
        strlen($html),
        str_contains($html, '<form') ? 'sim' : 'nao',
        str_contains($html, 'data-page') || str_contains($html, 'id="app"') ? 'sim' : 'nao',
        str_contains($html, '/login') ? 'sim' : 'nao',
        str_contains($html, 'product_add_form') ? 'sim' : 'nao',
        str_replace(["\n", "\r"], ' ', mb_substr(strip_tags($html), 0, 200))
    );

    expect($html)->toContain(
        'product_add_form',
        'PRÉ-CONDIÇÃO: a resposta tem que ser o FORM da Blade. VEIO OUTRA PÁGINA → ' . $assinatura
    );

    foreach (['enable_stock', 'not_for_selling', 'enable_sr_no'] as $flag) {
        expect($html)->toContain(
            '<input type="hidden" name="' . $flag . '" value="0">'
        );
    }
});

it('UC-PEDIT-08 · `0` explícito DESLIGA a flag (o writer não trava em "só liga")', function () {
    $p = EstoqueFixture::singleProduct($this->business->id, enableStock: true);

    $produto = Product::findOrFail($p->productId);
    $produto->enable_stock = 1;
    $produto->not_for_selling = 1;
    $produto->enable_sr_no = 1;
    $produto->save();

    // Payload da BLADE: manda as 3 chaves com `0` explícito (é o que o hidden produz
    // quando o operador desmarca). Contrasta com o payload da React, que as omite.
    $payload = payloadQueChegaNoSave($produto) + [
        'enable_stock' => '0',
        'not_for_selling' => '0',
        'enable_sr_no' => '0',
    ];

    $this->put("/products/{$p->productId}", $payload);

    exigeQueTenhaPersistido($p->productId, $payload['name'], 'UC-PEDIT-08');

    $depois = Product::findOrFail($p->productId);

    expect((int) $depois->enable_stock)->toBe(0, 'desmarcar na Blade tem que desligar o controle de estoque');
    expect((int) $depois->not_for_selling)->toBe(0, 'desmarcar na Blade tem que desligar `not_for_selling`');
    expect((int) $depois->enable_sr_no)->toBe(0, 'desmarcar na Blade tem que desligar `enable_sr_no`');
});
