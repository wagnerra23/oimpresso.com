<?php

use App\Product;
use App\VariationLocationDetails;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Auditoria\Services\AuditEntryService;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\EstoqueFixture;

/**
 * US-AUDIT-002 — trait LogsActivity em App\Product + App\VariationLocationDetails.
 *
 * Valida (per SPEC + ADR 0127):
 *   - Product: alterar nome/sku gera entry em activity_log com log_name='inventory.product'
 *     e diff old/new
 *   - VariationLocationDetails: alterar qty_available gera entry com
 *     log_name='inventory.stock' (ajuste de estoque rastreavel)
 *   - Multi-tenant Tier 0: o log fica no tenant do produto, nao no da sessao (ADR 0093)
 *
 * O produto nasce aqui (EstoqueFixture, dentro da transacao): o seed do CI nao tem
 * produto, e procurar "o primeiro do tenant" fazia estes testes pularem em todo run.
 * Tenants ficticios 98 x 99 (ADR 0358), nunca biz=4.
 *
 * Refs: ADR 0127 §F1 padronizacao, ADR 0093 Tier 0
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS ausente — estes testes rodam na lane MySQL (auditoria-pest).');
    }

    $this->business = $this->seededTenant();
    $this->user = \App\User::where('business_id', $this->business->id)->firstOrFail();
    $this->fixture = EstoqueFixture::singleProduct((int) $this->business->id);
    $this->product = Product::findOrFail($this->fixture->productId);

    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id]);
});

function auditProdutoUltimoLog(string $subjectType, int $subjectId, string $logName): ?Activity
{
    return Activity::query()
        ->where('subject_type', $subjectType)
        ->where('subject_id', $subjectId)
        ->where('log_name', $logName)
        ->where('event', 'updated')
        ->latest('id')
        ->first();
}

it('cenario 1: update Product.name gera activity_log entry com log_name=inventory.product', function () {
    $nomeAntigo = $this->product->name;
    $nomeNovo = $nomeAntigo.' editado';

    $this->product->name = $nomeNovo;
    $this->product->save();

    $log = auditProdutoUltimoLog(Product::class, $this->product->id, 'inventory.product');

    expect($log)->not->toBeNull('LogsActivity deveria ter gravado entry on update');
    expect((int) $log->business_id)->toBe((int) $this->business->id);
    expect($log->properties['old']['name'] ?? null)->toBe($nomeAntigo);
    expect($log->properties['attributes']['name'] ?? null)->toBe($nomeNovo);
});

it('cenario 2: update Product em campo NAO logado (image) NAO gera entry duplicado', function () {
    $countBefore = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    // 'image' NAO esta no logOnly — alteracao nao deve criar entry
    $this->product->image = 'test-img-'.uniqid().'.png';
    $this->product->save();

    $countAfter = Activity::query()
        ->where('subject_type', Product::class)
        ->where('subject_id', $this->product->id)
        ->count();

    expect($countAfter)->toBe($countBefore, 'campo fora do logOnly nao deve gerar nova entry');
});

it('cenario 3: update VariationLocationDetails.qty_available gera entry com log_name=inventory.stock', function () {
    $local = EstoqueFixture::locationId((int) $this->business->id);
    EstoqueFixture::setStock($this->fixture, 0, $local, 10.0);
    $vld = VariationLocationDetails::where('product_id', $this->product->id)
        ->where('location_id', $local)
        ->firstOrFail();

    $vld->qty_available = 15.0;
    $vld->save();

    $log = auditProdutoUltimoLog(VariationLocationDetails::class, $vld->id, 'inventory.stock');

    expect($log)->not->toBeNull('VLD update deveria gerar entry inventory.stock');
    expect((float) ($log->properties['old']['qty_available'] ?? 0))->toBe(10.0);
    expect((float) ($log->properties['attributes']['qty_available'] ?? 0))->toBe(15.0);
});

it('cenario 4: multi-tenant Tier 0 — log do Product fica no tenant dele e nao aparece no outro', function () {
    $outro = $this->seededSupportClientTenant();
    $produtoOutro = Product::findOrFail(EstoqueFixture::singleProduct((int) $outro->id)->productId);

    // A sessao continua no tenant principal: quem define o tenant do log e o produto.
    $this->product->name .= ' editado';
    $this->product->save();
    $produtoOutro->name .= ' editado';
    $produtoOutro->save();

    $meu = auditProdutoUltimoLog(Product::class, $this->product->id, 'inventory.product');
    $alheio = auditProdutoUltimoLog(Product::class, $produtoOutro->id, 'inventory.product');

    expect((int) $meu->business_id)->toBe((int) $this->business->id);
    expect((int) $alheio->business_id)->toBe((int) $outro->id);

    $ids = collect(app(AuditEntryService::class)->list((int) $this->business->id, ['event' => 'updated'], 200)->items())
        ->pluck('id')->all();

    expect(in_array($meu->id, $ids, true))->toBeTrue();
    expect(in_array($alheio->id, $ids, true))->toBeFalse();
});
