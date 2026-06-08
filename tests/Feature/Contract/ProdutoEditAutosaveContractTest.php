<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Contract\AutosaveContractRunner;

/**
 * Contract test da tela Produto/Edit (UPOS legacy ProductController::update).
 *
 * Pattern espelha ServiceOrderEditAutosaveContractTest — PUT form submit
 * (redirect 302, não JSON) + roundtrip via DB direto (pq Product::show via
 * X-Inertia expõe apenas subset dos campos que update aceita; ler do DB
 * é a fonte de verdade pra "o que persistiu de verdade").
 *
 * Ver fixture `tests/Contract/Fixtures/produto_edit.php` pra detalhe dos
 * campos cobertos + justificativa do que NÃO está aqui (variations,
 * locations, expiry, image upload).
 *
 * Setup (3 fases):
 *   1. setupContext: pega Business + User reais, autentica, popula session
 *   2. Cria Unit no business (foreign key required em products.unit_id)
 *   3. Cria Product base + ProductVariation dummy + Variation dummy (o
 *      controller update() em produto type=single faz Variation::find($single_variation_id)
 *      e dá save() — se o ID não existir, NPE. Mesmo enviando só campos
 *      cadastrais, o controller SEMPRE atravessa o branch single).
 *
 * Multi-tenant Tier 0 (ADR 0093 IRREVOGÁVEL):
 *   - Product Eloquent ::where('business_id', $bid) hardcoded no controller §952
 *   - Session user.business_id populada via setupContext
 *   - Unit/Variation/ProductVariation criados no mesmo business
 *
 * Skip graceful: sqlite memory OU schema UPOS ausente. Permission
 * `product.update` precisa estar atribuída ao user — tentamos via
 * Permission::firstOrCreate + assignRole se Spatie estiver disponível.
 *
 * @see app/Http/Controllers/ProductController::update §940
 * @see tests/Contract/Fixtures/produto_edit.php
 * @see ADR 0205 — contract tests autosave canon
 * @see ADR 0093 — multi-tenant Tier 0 IRREVOGÁVEL
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    // Pré-flight 1: DB driver — schema UPOS exige MySQL.
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: requer schema MySQL UltimatePOS (products+variations+units)');
    }
    // Pré-flight 2: tabelas UPOS.
    foreach (['products', 'variations', 'product_variations', 'units'] as $tbl) {
        if (! Schema::hasTable($tbl)) {
            $this->markTestSkipped("Schema UPOS ausente: tabela {$tbl} não existe");
        }
    }

    // Setup multi-tenant (autentica user + session business_id) — reusa runner helper.
    $ctx = AutosaveContractRunner::setupContext($this);
    $this->business = $ctx['business'];
    $this->user = $ctx['user'];

    // Garante que user tem permission product.update — sem isso, controller aborta 403.
    // Tentamos via Spatie Permission se disponível; fallback silencioso (test pode
    // ainda passar se user for super-admin via outro mecanismo).
    try {
        if (class_exists(\Spatie\Permission\Models\Permission::class)) {
            $perm = \Spatie\Permission\Models\Permission::firstOrCreate(
                ['name' => 'product.update', 'guard_name' => 'web']
            );
            $this->user->givePermissionTo($perm);
        }
    } catch (\Throwable $e) {
        // Best-effort — se Spatie não carregar ou role/permission models não disponíveis,
        // pula sem quebrar (test pode skip-ar com 403 mensagem clara).
    }

    // Cria Unit no business — products.unit_id é foreign key NOT NULL.
    $this->unitId = DB::table('units')->insertGetId([
        'business_id' => $this->business->id,
        'actual_name' => 'CT Unit ' . substr((string) microtime(true), -4),
        'short_name' => 'CTU',
        'allow_decimal' => 1,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Cria Product base (type=single, tax_type=exclusive, barcode_type=C128).
    // Schema NOT NULL: name, business_id, type, unit_id, tax_type, sku, barcode_type, created_by.
    $this->productId = DB::table('products')->insertGetId([
        'business_id' => $this->business->id,
        'name' => 'CT Setup Product ' . substr((string) microtime(true), -4),
        'type' => 'single',
        'unit_id' => $this->unitId,
        'tax_type' => 'exclusive',
        'sku' => 'CT-SETUP-' . substr((string) microtime(true), -4),
        'barcode_type' => 'C128',
        'enable_stock' => 0,
        'alert_quantity' => 0,
        'created_by' => $this->user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Cria ProductVariation dummy (1×1 com product) — controller update() lê
    // ->product_variations no with() e a Variation dummy aponta pra ela.
    $this->productVariationId = DB::table('product_variations')->insertGetId([
        'product_id' => $this->productId,
        'name' => 'DUMMY',
        'is_dummy' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Cria Variation dummy — controller §1073 faz Variation::find($single_variation_id)
    // e ->save(). Se ID inexistente -> erro de catch silencia (output success=0).
    $this->variationId = DB::table('variations')->insertGetId([
        'name' => 'DUMMY',
        'product_id' => $this->productId,
        'product_variation_id' => $this->productVariationId,
        'sub_sku' => 'CT-SETUP-VAR-' . substr((string) microtime(true), -4),
        'default_purchase_price' => 0,
        'dpp_inc_tax' => 0,
        'profit_percent' => 0,
        'default_sell_price' => 0,
        'sell_price_inc_tax' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('Produto/Edit — PUT /products/{id} persiste TODOS os campos cadastrais do fixture (roundtrip via DB)', function () {
    $fixture = require __DIR__ . '/../../Contract/Fixtures/produto_edit.php';
    $stamp = 'CT' . substr((string) microtime(true), -4);
    $passed = 0;
    $total = 0;
    $failures = [];

    foreach ($fixture as $tabName => $tabSpec) {
        $endpoint = str_replace('{id}', (string) $this->productId, $tabSpec['endpoint']);

        foreach ($tabSpec['fields'] as $field) {
            $total++;
            $sendKey = $field['send'];
            $recvKey = $field['recv'];
            $match = $field['match'] ?? 'equals';

            // {stamp} substitution — único por iteração pra evitar
            // falso-positivo de cache HTTP/Eloquent retornar estado anterior.
            $sent = is_string($field['value'])
                ? str_replace('{stamp}', $stamp, $field['value'])
                : $field['value'];

            // PUT precisa de payload completo (name + unit_id required). Mergeamos
            // base + 1 campo alterado por iteração — outros ficam neutros e
            // não interferem na verificação per-campo.
            //
            // single_variation_id + single_dpp/dsp são required pelo branch
            // single (controller §1071). Valores zero são aceitos pelo num_uf
            // e não rompem o Variation::find($single_variation_id)->save().
            $base = [
                'name' => 'CT base name',
                'unit_id' => $this->unitId,
                'tax_type' => 'exclusive',
                'barcode_type' => 'C128',
                'sku' => 'CT-BASE-' . $stamp,
                'enable_stock' => 0,
                'product_description' => 'CT base desc',
                'single_variation_id' => $this->variationId,
                'single_dpp' => '0',
                'single_dpp_inc_tax' => '0',
                'single_dsp' => '0',
                'single_dsp_inc_tax' => '0',
                'profit_percent' => '0',
            ];
            $payload = array_merge($base, [$sendKey => $sent]);

            // 1) PUT — controller redireciona pro /products (302) em sucesso
            // ou 422 em validation fail. Catch interno transforma exceções em
            // output success=0 mas ainda retorna 302 — daí precisarmos do
            // DB roundtrip pra detectar persistência real.
            $putResponse = $this->put($endpoint, $payload);
            $putStatus = $putResponse->status();
            $putOk = in_array($putStatus, [200, 302], true);

            // 2) DB lookup — fonte de verdade. Bypassamos cache Eloquent
            // usando query builder direto. Se controller silenciosamente
            // dropou a chave (regressão UPOS 6.7 do cpf_cnpj), o valor não bate.
            $received = DB::table('products')
                ->where('id', $this->productId)
                ->value($recvKey);

            $valueOk = matchesProdutoValue($sent, $received, $match);

            if (! $putOk || ! $valueOk) {
                $failures[] = [
                    'tab' => $tabName,
                    'endpoint' => $endpoint,
                    'method' => 'put',
                    'send' => $sendKey,
                    'value_sent' => is_string($sent) ? substr($sent, 0, 60) : $sent,
                    'recv' => $recvKey,
                    'value_received' => is_string($received) ? substr((string) $received, 0, 60) : $received,
                    'put_status' => $putStatus,
                    'match_mode' => $match,
                ];
            } else {
                $passed++;
            }
        }
    }

    // Falha legível pra dev — espelha ClienteDrawerAutosaveContractTest pattern.
    if ($passed !== $total) {
        $msg = "❌ Contract test FALHOU — {$passed}/{$total} OK.\n\n";
        $msg .= "Bugs silenciosos detectados em Produto/Edit (PUT 302 mas valor não persistiu):\n";
        foreach ($failures as $f) {
            $msg .= sprintf(
                "  [%s] %s %s · send=%s · value_sent=%s · recv=%s · value_received=%s · put=%d · match=%s\n",
                $f['tab'], strtoupper($f['method']), $f['endpoint'], $f['send'], var_export($f['value_sent'], true),
                $f['recv'], var_export($f['value_received'], true), $f['put_status'], $f['match_mode']
            );
        }
        $msg .= "\nADR 0205 — todo PR que regrida contract test bloqueia merge.\n";
        $msg .= "Fix comum: alinhar \$request->only() em ProductController::update §948 + colunas products schema.\n";

        expect($failures)->toBeEmpty($msg);
    }

    expect($passed)->toBe($total);
});

/**
 * Helper inline pra match modes — mesma semântica de
 * AutosaveContractRunner::matches() (private). Duplicado aqui pq runner default
 * só suporta patchJson e leitura via response->json(); nós precisamos DB direto.
 * Pattern espelha matchesValue() em ServiceOrderEditAutosaveContractTest.
 */
function matchesProdutoValue(mixed $sent, mixed $received, string $match): bool
{
    return match ($match) {
        'partial' => $received !== null && is_string($received)
            && str_contains((string) $received, is_string($sent) ? $sent : (string) $sent),
        'bool' => (bool) $received === (bool) $sent,
        'int' => (int) $received === (int) $sent,
        'array_eq' => json_encode($received) === json_encode($sent),
        default => $received === $sent || (string) $received === (string) $sent,
    };
}
