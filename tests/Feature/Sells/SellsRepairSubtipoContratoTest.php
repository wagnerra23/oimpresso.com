<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\Support\EstoqueFixture;

/**
 * UC-S03 (resources/js/Pages/Sells/Create.casos.md) — reparo é um TIPO de venda.
 *
 * Decisão [W] 2026-10-01: a tela de reparo é venda, com o mesmo cálculo e o mesmo estoque
 * de uma venda comum. O PDV React (`Sells/Create`, aberto por `?sub_type=repair`) não
 * carregava o tipo no envio; a onda 1 passa a carregar. Este arquivo prova o lado do
 * servidor com o MESMO formato de payload que o `transform` do Create.tsx monta.
 * O lado do envio está em tests/js/sells-subtipo-venda.test.ts.
 *
 * Dupla prova da REGRA MESTRE (valor/estoque): as duas vendas abaixo diferem SÓ no tipo.
 * Se o tipo entrasse em algum cálculo, total, linhas ou baixa de estoque divergiriam.
 *
 * Tenant 98 (ADR 0358). Nunca biz=4.
 */
uses(DatabaseTransactions::class);

/** Payload no formato do transform do Sells/Create.tsx (POST /pos). */
function repairSubtipoPayload(int $locationId, int $contactId, int $productId, int $variationId, array $extra = []): array
{
    return array_merge([
        'location_id' => $locationId,
        'contact_id' => $contactId,
        'transaction_date' => now()->format('d/m/Y H:i'),
        'status' => 'final',
        'is_direct_sale' => 1,
        'is_save_and_print' => 0,
        'discount_type' => 'percentage',
        'discount_amount' => 0,
        'tax_rate_id' => null,
        'final_total' => 100,
        'payment' => [['amount' => 0, 'method' => 'cash', 'paid_on' => '', 'account_id' => null, 'note' => '']],
        'products' => [[
            'product_id' => $productId,
            'variation_id' => $variationId,
            'quantity' => 2,
            'unit_price' => 50,
            'unit_price_inc_tax' => 50,
            'item_tax' => 0,
            'tax_id' => null,
            'line_discount_type' => 'fixed',
            'line_discount_amount' => 0,
            'imei_number' => '',
            'enable_stock' => 1,
            'product_type' => 'single',
        ]],
    ], $extra);
}

/** Cria produto com 10 em estoque, posta a venda e devolve a venda gravada + o saldo depois. */
function repairSubtipoVender(object $test, array $extra = []): array
{
    $produto = EstoqueFixture::singleProduct($test->bizId);
    EstoqueFixture::setStock($produto, 0, $test->locationId, 10);

    // LASTRO de compra (receita do EstoqueTransferenciaIdempotenciaTest): o `store()` chama
    // `mapPurchaseSell`, que sem purchase_lines na location lança PurchaseSellMismatch — e o
    // catch engole em `back()->withErrors(['venda'])`. Medido no 1º run do CI: as duas vendas
    // voltavam null por isto. Em produção o saldo sempre veio de uma entrada.
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $test->bizId,
        'type' => 'purchase',
        'status' => 'received',
        'location_id' => $test->locationId,
        'payment_status' => 'paid',
        'transaction_date' => now()->subDay(),
        'total_before_tax' => 0,
        'final_total' => 0,
        'created_by' => $test->user->id,
        'essentials_duration' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId,
        'product_id' => $produto->productId,
        'variation_id' => $produto->variations[0]['variation_id'],
        'quantity' => 10,
        'quantity_sold' => 0,
        'quantity_adjusted' => 0,
        'quantity_returned' => 0,
        'purchase_price' => 0,
        'purchase_price_inc_tax' => 0,
        'item_tax' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    // O catch do store() troca a exceção por "Something went wrong" e só a registra em
    // Log::emergency. Medido no 2º run do CI: com lastro, a recusa seguiu genérica — então a
    // causa real é capturada aqui e vai pra mensagem de falha abaixo.
    $logs = [];
    \Illuminate\Support\Facades\Event::listen(
        \Illuminate\Log\Events\MessageLogged::class,
        function ($e) use (&$logs) {
            if (in_array($e->level, ['emergency', 'error', 'critical'], true)) {
                $logs[] = mb_substr($e->message, 0, 400);
            }
        }
    );

    $response = $test->post('/pos', repairSubtipoPayload(
        $test->locationId,
        $test->contactId,
        $produto->productId,
        (int) $produto->variations[0]['variation_id'],
        $extra,
    ));

    $transactionId = DB::table('transaction_sell_lines')
        ->where('product_id', $produto->productId)
        ->value('transaction_id');

    // Diagnóstico: venda não gravada = o store() recusou. A causa vai pra sessão ('venda'),
    // então ela entra na mensagem — sem isto o CI só mostra "null".
    \PHPUnit\Framework\Assert::assertNotNull(
        $transactionId,
        'store() não gravou a venda. status HTTP='.$response->status()
            .' erros='.json_encode(session('errors')?->getBag('default')->all() ?? [])
            .' log='.json_encode($logs, JSON_UNESCAPED_UNICODE)
    );

    return [
        'response' => $response,
        'venda' => $transactionId ? DB::table('transactions')->where('id', $transactionId)->first() : null,
        'linhas' => $transactionId ? DB::table('transaction_sell_lines')->where('transaction_id', $transactionId)->get() : collect(),
        'saldo' => EstoqueFixture::currentStock($produto, 0, $test->locationId),
    ];
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente (sqlite :memory: ou DB vazio) — roda na lane MySQL / CT 100.');
    }

    if (! Schema::hasColumn('transactions', 'repair_serial_no')) {
        $this->markTestSkipped('Colunas do Repair ausentes em transactions — rode as migrations do módulo.');
    }

    $this->bizId = EstoqueFixture::businessId(); // tenant 98 (ADR 0358 — nunca biz=4).
    $this->locationId = EstoqueFixture::locationId($this->bizId);
    $this->contactId = (int) DB::table('contacts')->where('business_id', $this->bizId)->orderBy('id')->value('id');

    $this->user = User::where('business_id', $this->bizId)->orderBy('id')->first();
    if (! $this->user || $this->contactId === 0) {
        $this->markTestSkipped('Sem user ou contato no business seeded.');
    }

    $this->actingAs($this->user);
    // `business` na sessão é o MODELO, como o SetSessionData:59 grava em produção — não array.
    // O store() lê `$business->enable_rp` como propriedade (TransactionUtil::calculateRewardPoints):
    // com array, lança "Attempt to read property on array" e o catch devolve "Something went
    // wrong" (medido no 3º run do CI). Formato de data fixado no objeto, sem salvar, pra bater
    // com o `transaction_date` do payload.
    $business = \App\Business::findOrFail($this->bizId);
    $business->date_format = 'd/m/Y';
    $business->time_format = 24;
    session([
        'user.business_id' => $this->bizId,
        'user.id' => $this->user->id,
        'business' => $business,
    ]);

    $currency = DB::table('currencies')
        ->where('id', DB::table('business')->where('id', $this->bizId)->value('currency_id'))
        ->first();
    session(['currency' => [
        'id' => (int) ($currency->id ?? 1),
        'code' => (string) ($currency->code ?? 'BRL'),
        'symbol' => (string) ($currency->symbol ?? 'R$'),
        'thousand_separator' => (string) ($currency->thousand_separator ?? '.'),
        'decimal_separator' => (string) ($currency->decimal_separator ?? ','),
    ]]);

    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('sell.create', 'web');
    $this->user->givePermissionTo('sell.create');
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
});

it('UC-S03 · venda aberta como reparo é gravada como reparo, com o mesmo valor e a mesma baixa de estoque da venda comum', function () {
    $comum = repairSubtipoVender($this);
    $reparo = repairSubtipoVender($this, [
        'sub_type' => 'repair',
        'print_label' => 0,
        'repair_serial_no' => 'SN-UC-S03',
    ]);

    // As duas foram gravadas (sem isto, todo o resto compararia null com null).
    expect($comum['venda'])->not->toBeNull();
    expect($reparo['venda'])->not->toBeNull();

    // O tipo e o campo de reparo chegaram (o hook after_sale_saved do Repair rodou).
    expect($comum['venda']->sub_type)->toBeNull();
    expect($reparo['venda']->sub_type)->toBe('repair');
    expect($reparo['venda']->repair_serial_no)->toBe('SN-UC-S03');

    // Mesmo valor: o tipo não entra no cálculo.
    foreach (['final_total', 'total_before_tax', 'tax_amount', 'discount_amount'] as $campo) {
        expect((float) $reparo['venda']->{$campo})->toBe((float) $comum['venda']->{$campo});
    }
    expect((float) $reparo['venda']->final_total)->toBe(100.0);

    // Mesmas linhas.
    expect($reparo['linhas'])->toHaveCount(1);
    expect($comum['linhas'])->toHaveCount(1);
    expect((float) $reparo['linhas'][0]->quantity)->toBe((float) $comum['linhas'][0]->quantity);
    expect((float) $reparo['linhas'][0]->unit_price_inc_tax)->toBe((float) $comum['linhas'][0]->unit_price_inc_tax);

    // Mesma baixa de estoque: 10 − 2 nos dois.
    expect($comum['saldo'])->toBe(8.0);
    expect($reparo['saldo'])->toBe(8.0);

    // Cada uma volta pra sua listagem.
    $comum['response']->assertRedirect(action([\App\Http\Controllers\SellController::class, 'index']));
    $reparo['response']->assertRedirect(action([\Modules\Repair\Http\Controllers\RepairController::class, 'index']));
});

it('UC-S03 · reparo enviado sem print_label grava e redireciona, sem erro depois do commit', function () {
    $reparo = repairSubtipoVender($this, ['sub_type' => 'repair']);

    expect($reparo['venda'])->not->toBeNull();
    expect($reparo['venda']->sub_type)->toBe('repair');
    $reparo['response']->assertRedirect(action([\Modules\Repair\Http\Controllers\RepairController::class, 'index']));
    $reparo['response']->assertSessionHasNoErrors();
});

it('UC-S03 · a porta /pos/create?sub_type=repair entrega subType=repair ao Sells/Create', function () {
    // A correção do envio só vale se a PORTA entregar o tipo. Medido 2026-10-01: o botão
    // "Nova OS" do Repair/Index.tsx abria /sells/create (SellController, lê ?sale_type=) e
    // o menu apontava /sells/pos/create (404 em prod). /pos/create é o SellPosController,
    // que lê ?sub_type= — e exige caixa aberto (senão redireciona pra abrir o caixa).
    DB::table('cash_registers')->insert([
        'business_id' => $this->bizId,
        'location_id' => $this->locationId,
        'user_id' => $this->user->id,
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $manifest = public_path('build-inertia/manifest.json');
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
    ];

    $reparo = $this->withHeaders($headers)->get('/pos/create?sub_type=repair');
    $comum = $this->withHeaders($headers)->get('/pos/create');

    \PHPUnit\Framework\Assert::assertSame(200, $reparo->status(), 'GET /pos/create?sub_type=repair não abriu a tela: HTTP '.$reparo->status().' '.mb_substr((string) $reparo->getContent(), 0, 300));
    expect($reparo->json('component'))->toBe('Sells/Create');
    expect($reparo->json('props.subType'))->toBe('repair');

    // Controle: sem o parâmetro, a mesma porta abre venda comum.
    \PHPUnit\Framework\Assert::assertSame(200, $comum->status(), 'GET /pos/create não abriu a tela: HTTP '.$comum->status());
    expect($comum->json('props.subType'))->toBeNull();

    // A porta antiga (botão "Nova OS" do Repair/Index) encaminha pra porta certa, em vez de
    // abrir venda comum. Sem X-Inertia: é o clique no <a href>, uma visita de página inteira.
    $this->flushHeaders()->get('/sells/create?sub_type=repair')->assertRedirect('/pos/create?sub_type=repair');
});

/** Status de reparo de um business (fixture própria — o seed não garante nenhum). */
function repairSubtipoStatus(int $bizId, string $nome): int
{
    return (int) DB::table('repair_statuses')->insertGetId([
        'name' => $nome,
        'color' => '#2563eb',
        'sort_order' => 1,
        'business_id' => $bizId,
        'is_completed_status' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('UC-S04 · a venda de reparo abre com as opções de reparo do PRÓPRIO business', function () {
    if (! Schema::hasTable('repair_statuses')) {
        $this->markTestSkipped('Tabela repair_statuses ausente — rode as migrations do Repair.');
    }
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('Sem 2º business semeado pro adversário cross-tenant.');
    }

    $meu = repairSubtipoStatus($this->bizId, 'Em bancada UC-S04');
    $alheio = repairSubtipoStatus($outroBiz, 'De outro business UC-S04');

    DB::table('cash_registers')->insert([
        'business_id' => $this->bizId, 'location_id' => $this->locationId, 'user_id' => $this->user->id,
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manifest = public_path('build-inertia/manifest.json');
    $headers = ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1'];

    $reparo = $this->withHeaders($headers)->get('/pos/create?sub_type=repair');
    \PHPUnit\Framework\Assert::assertSame(200, $reparo->status(), 'GET /pos/create?sub_type=repair: HTTP '.$reparo->status());

    $ids = collect($reparo->json('props.repairPos.statuses'))->pluck('id')->all();
    expect($ids)->toContain($meu);
    expect(in_array($alheio, $ids, true))->toBeFalse(); // Tier 0: status de outro business nunca aparece.
    foreach (['brands', 'devices', 'modelos', 'warranties', 'defeitosSugeridos', 'checklistPadrao'] as $chave) {
        expect(array_key_exists($chave, $reparo->json('props.repairPos')))->toBeTrue();
    }

    // Venda comum: sem seção de reparo.
    $comum = $this->withHeaders($headers)->get('/pos/create');
    expect($comum->json('props.repairPos'))->toBeNull();
});

it('UC-S04 · os campos da seção Reparo são gravados na venda, sem mudar o valor', function () {
    if (! Schema::hasTable('repair_statuses')) {
        $this->markTestSkipped('Tabela repair_statuses ausente — rode as migrations do Repair.');
    }
    $status = repairSubtipoStatus($this->bizId, 'Aguardando peça UC-S04');

    // Exatamente o que reparoVenda.camposDeReparo produz (tests/js/sells-reparo-venda.test.ts).
    $comum = repairSubtipoVender($this);
    $reparo = repairSubtipoVender($this, [
        'sub_type' => 'repair',
        'print_label' => 0,
        'repair_status_id' => $status,
        'repair_serial_no' => 'SN-UC-S04',
        'repair_due_date' => '15/10/2026 14:30',
        'repair_defects' => '[{"value":"tela"},{"value":"bateria"}]',
    ]);

    expect((int) $reparo['venda']->repair_status_id)->toBe($status);
    expect($reparo['venda']->repair_serial_no)->toBe('SN-UC-S04');
    expect((string) $reparo['venda']->repair_due_date)->toStartWith('2026-10-15 14:30');
    expect(json_decode((string) $reparo['venda']->repair_defects, true))
        ->toBe([['value' => 'tela'], ['value' => 'bateria']]);

    // Os campos do aparelho não entram no cálculo.
    expect((float) $reparo['venda']->final_total)->toBe((float) $comum['venda']->final_total);
    expect($reparo['saldo'])->toBe($comum['saldo']);
});

it('UC-S05 · modelos do PRÓPRIO business com o checklist de cada um; checklist, senha e padrão gravados', function () {
    if (! Schema::hasTable('repair_device_models')) {
        $this->markTestSkipped('Tabela repair_device_models ausente — rode as migrations do Repair.');
    }
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null) {
        $this->markTestSkipped('Sem 2º business semeado pro adversário cross-tenant.');
    }

    $novoModelo = fn (int $biz, string $nome, string $checklist) => (int) DB::table('repair_device_models')->insertGetId([
        'business_id' => $biz,
        'name' => $nome,
        'repair_checklist' => $checklist,
        'created_by' => EstoqueFixture::userId($biz),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $meu = $novoModelo($this->bizId, 'Modelo UC-S05', 'Liga|Tela trincada|');
    $alheio = $novoModelo($outroBiz, 'Modelo alheio UC-S05', 'Nao deve aparecer');

    DB::table('cash_registers')->insert([
        'business_id' => $this->bizId, 'location_id' => $this->locationId, 'user_id' => $this->user->id,
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manifest = public_path('build-inertia/manifest.json');
    $tela = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1',
    ])->get('/pos/create?sub_type=repair');
    \PHPUnit\Framework\Assert::assertSame(200, $tela->status(), 'GET /pos/create?sub_type=repair: HTTP '.$tela->status());

    $modelos = collect($tela->json('props.repairPos.modelos'))->keyBy('id');
    expect($modelos->has($meu))->toBeTrue();
    expect($modelos->has($alheio))->toBeFalse(); // Tier 0: modelo de outro business nunca aparece.
    expect($modelos[$meu]['checklist'])->toBe(['Liga', 'Tela trincada']); // vazio do "|" final descartado.

    // Envio no formato de reparoVenda.camposDeReparo (tests/js/sells-reparo-venda.test.ts).
    $this->flushHeaders();
    $comum = repairSubtipoVender($this);
    $reparo = repairSubtipoVender($this, [
        'sub_type' => 'repair',
        'print_label' => 0,
        'repair_model_id' => $meu,
        'repair_security_pwd' => '4321',
        'repair_security_pattern' => '1478',
        'repair_checklist' => ['Liga' => 'yes', 'Tela trincada' => 'not_applicable'],
    ]);

    expect((int) $reparo['venda']->repair_model_id)->toBe($meu);
    expect($reparo['venda']->repair_security_pwd)->toBe('4321');
    expect($reparo['venda']->repair_security_pattern)->toBe('1478');
    expect(json_decode((string) $reparo['venda']->repair_checklist, true))
        ->toBe(['Liga' => 'yes', 'Tela trincada' => 'not_applicable']);

    expect((float) $reparo['venda']->final_total)->toBe((float) $comum['venda']->final_total);
    expect($reparo['saldo'])->toBe($comum['saldo']);
});

/** OS (repair_job_sheets) com 1 peça — INSERT direto: estado inicial independente do fluxo sob teste. */
function repairSubtipoOs(object $test, int $statusId, int $variationId, float $qtd, ?int $bizId = null): int
{
    $bizId ??= $test->bizId;

    return (int) DB::table('repair_job_sheets')->insertGetId([
        'business_id' => $bizId,
        'location_id' => $bizId === $test->bizId ? $test->locationId : null,
        'contact_id' => $test->contactId,
        'job_sheet_no' => 'OS-UC-S07-'.bin2hex(random_bytes(3)),
        'service_type' => 'carry_in',
        'serial_no' => 'SN-OS-UC-S07',
        'status_id' => $statusId,
        'defects' => '[{"value":"tela"},{"value":"bateria"}]',
        'checklist' => json_encode(['Liga' => 'yes']),
        'parts' => json_encode([(string) $variationId => ['quantity' => $qtd]]),
        'created_by' => $test->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** Caixa aberto + headers Inertia (o /pos/create exige caixa; sem ele redireciona). */
function repairSubtipoAbrirPdv(object $test): array
{
    DB::table('cash_registers')->insert([
        'business_id' => $test->bizId, 'location_id' => $test->locationId, 'user_id' => $test->user->id,
        'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $manifest = public_path('build-inertia/manifest.json');

    return ['X-Inertia' => 'true', 'X-Inertia-Version' => file_exists($manifest) ? md5_file($manifest) : '1'];
}

it('UC-S07 · venda a partir da OS traz as peças pelo MESMO preço da adição à mão, grava o vínculo e não fatura em dobro', function () {
    foreach (['repair_job_sheets', 'repair_statuses', 'product_locations'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Tabela {$t} ausente — rode as migrations.");
        }
    }

    // Peça com preço DISTINTO do default do fixture (20): o valor esperado vem daqui, não do código.
    $produto = EstoqueFixture::singleProduct($this->bizId);
    $variationId = (int) $produto->variations[0]['variation_id'];
    DB::table('variations')->where('id', $variationId)->update(['sell_price_inc_tax' => 37.5, 'default_sell_price' => 37.5]);
    DB::table('product_locations')->insert(['product_id' => $produto->productId, 'location_id' => $this->locationId]);
    $subSku = (string) DB::table('variations')->where('id', $variationId)->value('sub_sku');

    $aberto = repairSubtipoStatus($this->bizId, 'Em bancada UC-S07');
    $os = repairSubtipoOs($this, $aberto, $variationId, 2);

    $tela = $this->withHeaders(repairSubtipoAbrirPdv($this))->get("/pos/create?sub_type=repair&job_sheet_id={$os}");
    \PHPUnit\Framework\Assert::assertSame(200, $tela->status(), "GET da venda da OS: HTTP {$tela->status()}");
    $origem = $tela->json('props.repairPos.osOrigem');
    \PHPUnit\Framework\Assert::assertNotNull($origem, 'osOrigem não veio para uma OS do próprio business');

    // Caminho 1: o que o servidor manda pra peça da OS.
    expect($origem['pecas'])->toHaveCount(1);
    $peca = $origem['pecas'][0];
    // Caminho 2: o que o autocomplete do React recebe pra mesma variação (adição à mão).
    $lista = $this->flushHeaders()->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->get('/products/list?'.http_build_query(['term' => $subSku, 'location_id' => $this->locationId]));
    $daLista = collect($lista->json())->firstWhere('variation_id', $variationId);
    \PHPUnit\Framework\Assert::assertNotNull($daLista, '/products/list não achou a variação: HTTP '.$lista->status());

    expect((float) $peca['unit_price'])->toBe(37.5);                              // = valor gravado no banco
    expect((float) $peca['unit_price'])->toBe((float) $daLista['selling_price']); // = adição à mão no React
    expect((float) $peca['quantity'])->toBe(2.0);                                 // = quantidade da OS
    expect($origem['pecasNaoEncontradas'])->toBe([]);
    expect($origem['cliente']['id'])->toBe($this->contactId);
    expect($origem['location_id'])->toBe($this->locationId);
    expect($origem['reparo']['repair_serial_no'])->toBe('SN-OS-UC-S07');
    expect($origem['reparo']['defeitos'])->toBe(['tela', 'bateria']);

    // Grava a venda como o React envia a partir desse estado (lastro de compra pro mapPurchaseSell).
    $compraId = (int) DB::table('transactions')->insertGetId([
        'business_id' => $this->bizId, 'type' => 'purchase', 'status' => 'received', 'location_id' => $this->locationId,
        'payment_status' => 'paid', 'transaction_date' => now()->subDay(), 'total_before_tax' => 0, 'final_total' => 0,
        'created_by' => $this->user->id, 'essentials_duration' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    DB::table('purchase_lines')->insert([
        'transaction_id' => $compraId, 'product_id' => $produto->productId, 'variation_id' => $variationId, 'quantity' => 10,
        'quantity_sold' => 0, 'quantity_adjusted' => 0, 'quantity_returned' => 0, 'purchase_price' => 0,
        'purchase_price_inc_tax' => 0, 'item_tax' => 0, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);
    EstoqueFixture::setStock($produto, 0, $this->locationId, 10);

    $post = $this->flushHeaders()->post('/pos', repairSubtipoPayload($this->locationId, $this->contactId, $produto->productId, $variationId, [
        'sub_type' => 'repair',
        'print_label' => 0,
        'final_total' => 75,
        'repair_status_id' => $aberto,
        'repair_job_sheet_id' => $os,
        'products' => [[
            'product_id' => $produto->productId, 'variation_id' => $variationId, 'quantity' => 2,
            'unit_price' => 37.5, 'unit_price_inc_tax' => 37.5, 'item_tax' => 0, 'tax_id' => null,
            'line_discount_type' => 'fixed', 'line_discount_amount' => 0, 'imei_number' => '',
            'enable_stock' => 1, 'product_type' => 'single',
        ]],
    ]));
    $post->assertSessionHasNoErrors();
    $venda = DB::table('transactions')->where('repair_job_sheet_id', $os)->where('type', 'sell')->first();
    \PHPUnit\Framework\Assert::assertNotNull($venda, 'venda da OS não gravou o vínculo repair_job_sheet_id');
    expect((float) $venda->final_total)->toBe(75.0); // 2 × 37,50
    expect(EstoqueFixture::currentStock($produto, 0, $this->locationId))->toBe(8.0);

    // Concluir a OS depois de faturada NÃO gera 2ª venda (JobSheetObserver, idempotente por repair_job_sheet_id).
    $concluido = repairSubtipoStatus($this->bizId, 'Concluído UC-S07');
    DB::table('repair_statuses')->where('id', $concluido)->update(['is_completed_status' => 1]);
    \Modules\Repair\Entities\JobSheet::query()->find($os)->update(['status_id' => $concluido]);
    expect(DB::table('transactions')->where('repair_job_sheet_id', $os)->count())->toBe(1);

    // Controle positivo: OS SEM fatura, ao concluir, GERA a venda — prova que o observer roda aqui.
    $semFatura = repairSubtipoOs($this, $aberto, $variationId, 1);
    \Modules\Repair\Entities\JobSheet::query()->find($semFatura)->update(['status_id' => $concluido]);
    expect(DB::table('transactions')->where('repair_job_sheet_id', $semFatura)->count())->toBe(1);
});

it('UC-S07 · OS de OUTRO business abre a venda SEM origem, e não em 500 (Tier 0)', function () {
    $outroBiz = EstoqueFixture::secondBusinessId();
    if ($outroBiz === null || ! Schema::hasTable('repair_job_sheets')) {
        $this->markTestSkipped('Sem 2º business semeado ou sem repair_job_sheets.');
    }
    $produto = EstoqueFixture::singleProduct($this->bizId);
    $alheia = repairSubtipoOs(
        $this,
        repairSubtipoStatus($outroBiz, 'Status alheio UC-S07'),
        (int) $produto->variations[0]['variation_id'],
        1,
        $outroBiz,
    );

    $tela = $this->withHeaders(repairSubtipoAbrirPdv($this))->get("/pos/create?sub_type=repair&job_sheet_id={$alheia}");

    // O provider busca a OS com where business_id: a alheia não carrega. Antes do conserto,
    // getPartsUsed() rodava sobre null e a tela caía em 500.
    \PHPUnit\Framework\Assert::assertSame(200, $tela->status(), 'venda com OS alheia: HTTP '.$tela->status());
    expect($tela->json('props.repairPos'))->not->toBeNull();
    expect($tela->json('props.repairPos.osOrigem'))->toBeNull();
});
