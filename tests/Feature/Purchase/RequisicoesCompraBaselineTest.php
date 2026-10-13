<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EstoqueFixture;

/**
 * Baseline F2 (MWART, ADR 0104) de /purchase-requisition — o que a Blade lista ANTES da tela React.
 *
 * Thread compras/playbook/07 (Requisições de compra). Trava o ramo DataTable de
 * `PurchaseRequisitionController::index` como ele é hoje, para a F3 (tela React atrás de flag)
 * provar que não mudou o que a lista devolve. Sem mudança de código de produção.
 *
 * Diferenças da lista de pedidos (thread 06) que este teste fixa: não há linhas nem fornecedor
 * na lista, o JOIN com `users` é INNER (requisição sem autor válido não aparece) e existe o filtro
 * "necessário até" (`delivery_date`).
 *
 * Por que cada isolamento roda em PAR (controle positivo + contrato): sem a linha própria
 * aparecendo, "a alheia não aparece" fica verde com lista vazia ou 403 (§5 2026-07-24).
 *
 * Tenant do usuário = 98 (ADR 0358). O outro negócio é o 99 com location própria: a lista
 * faz INNER join em `business_locations`. NUNCA biz=4.
 *
 * Prefixo `reqBase` nas funções (o escopo global do Pest é compartilhado).
 * Mapa de paridade: memory/requisitos/Compras/_telas/purchase-requisition-parity.md.
 *
 * @see app/Http/Controllers/PurchaseRequisitionController.php::index
 * @see memory/requisitos/Compras/_telas/RUNBOOK-purchase-requisition-index.md
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('transactions') || ! Schema::hasTable('business_locations')) {
        $this->markTestSkipped('Schema UltimatePOS ausente (sqlite memory) — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->local = EstoqueFixture::locationId($this->business->id, '-REQ');
    $this->user = $this->usuarioComPermissoes(['purchase_requisition.view_all', 'access_all_locations'], $this->business);
    $this->actingAs($this->user);
    reqBaseSessao($this, $this->user);
    $this->ajax = ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'];
});

/** Sessão como o SetSessionData monta: as colunas de data leem `business.*`. */
function reqBaseSessao($teste, $user): void
{
    session([
        'user.id' => $user->id, 'user.business_id' => $teste->business->id,
        'business' => ['id' => $teste->business->id, 'quantity_precision' => 2, 'date_format' => 'd/m/Y', 'time_format' => 24],
        'currency' => ['id' => 1, 'code' => 'BRL', 'symbol' => 'R$', 'thousand_separator' => '.', 'decimal_separator' => ','],
    ]);
}

/** Requisição crua na `transactions`. */
function reqBaseRequisicao(int $businessId, int $localId, int $criadoPor, array $campos = []): int
{
    return DB::table('transactions')->insertGetId(array_merge([
        'business_id' => $businessId, 'location_id' => $localId, 'created_by' => $criadoPor,
        'type' => 'purchase_requisition', 'status' => 'ordered',
        'ref_no' => 'RQ-'.uniqid(), 'transaction_date' => now(), 'delivery_date' => null,
        'total_before_tax' => 0, 'final_total' => 0, 'tax_amount' => 0, 'discount_amount' => 0,
        'essentials_duration' => 0, 'created_at' => now(), 'updated_at' => now(),
    ], $campos));
}

/** Linhas da DataTable, por `ref_no`. */
function reqBaseLista($teste, string $query = ''): \Illuminate\Support\Collection
{
    $r = $teste->withHeaders($teste->ajax)->get('/purchase-requisition'.($query !== '' ? '?'.$query : ''));
    $r->assertOk();

    return collect($r->json('data'))->keyBy(fn ($l) => strip_tags((string) $l['ref_no']));
}

test('baseline: a lista traz as requisições do negócio e não as de outro negócio', function () {
    $outro = $this->seededSupportClientTenant();
    $minha = 'RQ-MINHA-'.uniqid();
    $alheia = 'RQ-ALHEIA-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $minha]);
    reqBaseRequisicao($outro->id, EstoqueFixture::locationId($outro->id, '-REQ'), $this->user->id, ['ref_no' => $alheia]);

    $refs = reqBaseLista($this)->keys()->all();

    expect($refs)->toContain($minha);      // controle positivo
    expect($refs)->not->toContain($alheia);
});

test('baseline: a lista traz só requisição — pedido de compra do mesmo negócio fica fora', function () {
    $requisicao = 'RQ-TIPO-'.uniqid();
    $pedido = 'PO-TIPO-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $requisicao]);
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $pedido, 'type' => 'purchase_order']);

    $refs = reqBaseLista($this)->keys()->all();

    expect($refs)->toContain($requisicao);
    expect($refs)->not->toContain($pedido);
});

test('baseline: "necessário até" sai formatado como a data da empresa, e filtra pelo intervalo', function () {
    $dentro = 'RQ-PRAZO-OK-'.uniqid();
    $fora = 'RQ-PRAZO-NAO-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $dentro, 'delivery_date' => '2031-03-10 14:30:00']);
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $fora, 'delivery_date' => '2031-05-20 09:00:00']);

    // A expressão é a da diretiva `@format_datetime` (AppServiceProvider), que carrega o shift de fuso
    // preservado de propósito (ADR 0066) — por isso a hora não é fixada à mão; o dia é.
    $esperado = \Carbon::createFromTimestamp(strtotime('2031-03-10 14:30:00'))->format('d/m/Y H:i');
    $todas = reqBaseLista($this);
    $celula = trim(strip_tags((string) $todas[$dentro]['delivery_date']));
    expect($celula)->toBe($esperado);
    expect($celula)->toStartWith('10/03/2031');

    $refs = reqBaseLista($this, 'required_by_start=2031-03-01&required_by_end=2031-03-31')->keys()->all();
    expect($refs)->toContain($dentro);
    expect($refs)->not->toContain($fora);
});

test('baseline: o filtro de status traz só o status pedido', function () {
    $parcial = 'RQ-PARC-'.uniqid();
    $concluida = 'RQ-CONC-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $parcial, 'status' => 'partial']);
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $concluida, 'status' => 'completed']);

    $refs = reqBaseLista($this, 'status=partial')->keys()->all();

    expect($refs)->toContain($parcial);
    expect($refs)->not->toContain($concluida);
});

test('baseline: com só "ver as próprias", a lista traz só as requisições que o usuário criou', function () {
    $propria = $this->usuarioComPermissoes(['purchase_requisition.view_own', 'access_all_locations'], $this->business);
    $minha = 'RQ-PROPRIA-'.uniqid();
    $doColega = 'RQ-COLEGA-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $propria->id, ['ref_no' => $minha]);
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $doColega]);

    $this->actingAs($propria);
    reqBaseSessao($this, $propria);
    $refs = reqBaseLista($this)->keys()->all();

    expect($refs)->toContain($minha);
    expect($refs)->not->toContain($doColega);
});

test('baseline: sem acesso a todos os locais, só aparecem requisições dos locais permitidos', function () {
    $outroLocal = EstoqueFixture::locationId($this->business->id, '-REQ2');
    $restrito = $this->usuarioComPermissoes(['purchase_requisition.view_all'], $this->business);
    // `permitted_locations()` lê as permissões DIRETAS do usuário, não as do papel.
    \Spatie\Permission\Models\Permission::findOrCreate("location.{$this->local}", 'web');
    $restrito->givePermissionTo("location.{$this->local}");
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $restrito = \App\User::findOrFail($restrito->id);

    $permitida = 'RQ-LOC-OK-'.uniqid();
    $vetada = 'RQ-LOC-NAO-'.uniqid();
    reqBaseRequisicao($this->business->id, $this->local, $this->user->id, ['ref_no' => $permitida]);
    reqBaseRequisicao($this->business->id, $outroLocal, $this->user->id, ['ref_no' => $vetada]);

    $this->actingAs($restrito);
    reqBaseSessao($this, $restrito);
    $refs = reqBaseLista($this)->keys()->all();

    expect($refs)->toContain($permitida);
    expect($refs)->not->toContain($vetada);
});

test('baseline: sem permissão de ver requisições, a lista responde 403', function () {
    $semAcesso = $this->usuarioComPermissoes(['access_all_locations'], $this->business);
    $this->actingAs($semAcesso);
    reqBaseSessao($this, $semAcesso);

    $this->withHeaders($this->ajax)->get('/purchase-requisition')->assertForbidden();
});
