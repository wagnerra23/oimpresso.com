<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;

/**
 * Contrato Tier 0 e de VALOR do detalhe de compra (`PurchaseController@show/showInertia`).
 *
 * UC-PURSHW-01 `[T0]` — o detalhe nunca resolve compra de outro tenant.
 * UC-PURSHW-05 `[V0]` — a tela formata, não recalcula: os totais vêm prontos do controller.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * POR QUE ESTE ARQUIVO EXISTE
 * ─────────────────────────────────────────────────────────────────────────────
 * Os dois UCs estavam marcados `⚠️ 🧪 estrutural` no `Show.casos.md`, defendidos só por
 * `file_get_contents` + `toContain`. O próprio arquivo dizia do UC-01: *"é o UC mais caro
 * do arquivo e o menos defendido"*. `App\Transaction` não tem global scope (o isolamento é
 * manual em cada query), e foi essa ausência que produziu o IDOR de escrita real no
 * `update` desta MESMA controller.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMO O [V0] É PROVADO SEM BROWSER (o ponto fino deste arquivo)
 * ─────────────────────────────────────────────────────────────────────────────
 * "A TELA não recalcula" é contrato do `.tsx` — só Playwright provaria isso, e este é um
 * teste de request. O que ESTE teste prova é a metade que decide o resultado: **o valor
 * que chega à tela é o do BANCO, não uma derivação**.
 *
 * A prova é uma discrepância deliberada: a compra nasce com `final_total` no banco
 * DIFERENTE do que qualquer agregação das linhas daria (aqui não há `purchase_lines`,
 * então `net_total` é 0 e o total é 1234.56). Se o payload trouxer 1234.56, o número
 * atravessou intacto; se trouxer 0 — ou o net_total — alguém passou a derivá-lo, e é
 * exatamente a fronteira de recálculo que a REGRA MESTRE proíbe.
 * Um assert que só comparasse payload×banco ficaria verde mesmo com `final_total = $net_total`
 * numa compra bem-comportada, onde os dois coincidem. A discrepância é o que discrimina.
 *
 * Segundo caminho (a REGRA MESTRE exige dois): antes → depois. Mudar o `final_total` no
 * banco move o payload na MESMA medida, com número concreto — logo o payload segue o banco,
 * e não uma conta própria.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ANTI-VÁCUO
 * ─────────────────────────────────────────────────────────────────────────────
 * O UC-01 roda em par: sem o controle positivo (200 no próprio), um `abort()` incondicional
 * — ou uma rota quebrada — daria 404 em tudo e passaria como "isolamento funcionando".
 * O `Show.casos.md` já pedia esse par no texto do aceite; aqui ele vira código.
 *
 * TENANTS (ADR 0358): usuário no 98 (fictício canônico). Nunca biz=1 (WR2 real, e no CT 100
 * a base é clone de prod que não se limpa), nunca biz=99 (SUPPORT_CLIENT_TENANT_ID), nunca
 * biz=4 (Larissa/ROTA LIVRE). Adversário descoberto em runtime, não fixado.
 *
 * @covers-uc UC-PURSHW-01  detalhe escopado por business_id (404 cross-tenant)
 * @covers-uc UC-PURSHW-05  totais vêm prontos do controller, não recalculados
 *
 * @see app/Http/Controllers/PurchaseController.php::show
 * @see resources/js/Pages/Purchase/Show.casos.md (UC-PURSHW-01 · UC-PURSHW-05)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/proibicoes.md (REGRA MESTRE — CÁLCULO DE VALOR ou ESTOQUE)
 */

// `Tests\TestCase` NÃO se declara aqui: `tests/Pest.php` já faz
// `uses(TestCase::class)->in('Feature')` e repetir dá "already uses the test case".
uses(DatabaseTransactions::class);

const PURSHW_TENANT = 98;

/** Total gravado no banco, deliberadamente distinto de qualquer agregação das linhas. */
const PURSHW_TOTAL_GRAVADO = 1234.56;

function purshwCriarCompra(int $businessId, int $locationId, int $userId, float $total): int
{
    $agora = now();

    return DB::table('transactions')->insertGetId([
        'business_id' => $businessId,
        'location_id' => $locationId,
        'created_by' => $userId,
        'type' => 'purchase',
        'status' => 'received',
        'payment_status' => 'due',
        'ref_no' => 'CT-PURSHW-' . uniqid(),
        'transaction_date' => $agora,
        'total_before_tax' => $total,
        'final_total' => $total,
        'tax_amount' => 0,
        'discount_amount' => 0,
        'essentials_duration' => 0,
        'created_at' => $agora,
        'updated_at' => $agora,
    ]);
}

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompatível: exige schema MySQL UltimatePOS (ADR 0062).');
    }
    foreach (['transactions', 'business', 'business_locations', 'invoice_schemes', 'invoice_layouts'] as $t) {
        if (! Schema::hasTable($t)) {
            $this->markTestSkipped("Schema UltimatePOS ausente ({$t}).");
        }
    }

    $this->tenant = \App\Business::find(PURSHW_TENANT);
    if (! $this->tenant) {
        $this->markTestSkipped('Lane sem tenant 98 semeado (ADR 0358) — contrato não exercitável.');
    }

    // business_locations tem 3 FKs NOT NULL; o seed da lane só popula invoice_schemes/layouts
    // pros tenants 1 e 2, então o 98 precisa das suas aqui ou o insert morre com 23000.
    $schemeId = DB::table('invoice_schemes')->value('id')
        ?: DB::table('invoice_schemes')->insertGetId([
            'business_id' => PURSHW_TENANT, 'name' => 'CT Scheme', 'scheme_type' => 'blank',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    $layoutId = DB::table('invoice_layouts')->value('id')
        ?: DB::table('invoice_layouts')->insertGetId([
            'business_id' => PURSHW_TENANT, 'name' => 'CT Layout',
            'created_at' => now(), 'updated_at' => now(),
        ]);

    $this->location = DB::table('business_locations')->insertGetId([
        'business_id' => PURSHW_TENANT, 'name' => 'CT Filial Show',
        'location_id' => strtoupper(substr(md5('show' . microtime()), 0, 8)),
        'country' => 'BR', 'state' => 'SC', 'city' => 'CT City', 'zip_code' => '00000000',
        'invoice_scheme_id' => $schemeId, 'invoice_layout_id' => $layoutId, 'is_active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->user = \App\User::factory()->create([
        'business_id' => PURSHW_TENANT,
        'username' => 'ct_purshw_' . uniqid(),
        'user_type' => 'user',
        'allow_login' => 1,
    ]);

    // O gate do show() aceita purchase.view OU view_own_purchase.
    Permission::firstOrCreate(['name' => 'purchase.view', 'guard_name' => 'web']);
    $this->user->givePermissionTo('purchase.view');

    $this->abrirDetalhe = fn (int $id) => $this->actingAs($this->user)
        ->withSession(['user' => ['business_id' => PURSHW_TENANT, 'id' => $this->user->id]])
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => file_exists(public_path('build-inertia/manifest.json'))
                ? md5_file(public_path('build-inertia/manifest.json'))
                : '1',
        ])
        ->get("/purchases/{$id}");
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PURSHW-01 — o detalhe não resolve compra de outro tenant
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PURSHW-01 (A · controle positivo) o detalhe da PROPRIA compra responde 200', function () {
    $id = purshwCriarCompra(PURSHW_TENANT, $this->location, $this->user->id, PURSHW_TOTAL_GRAVADO);

    $r = ($this->abrirDetalhe)($id);

    // Sem esta perna, o 404 do contrato (B) passaria mesmo com a rota quebrada
    // ou com um abort() incondicional — 404 em tudo pareceria isolamento.
    $this->assertSame(
        200,
        $r->status(),
        'O detalhe da PROPRIA compra deveria responder 200 (got ' . $r->status() . ') — '
        . 'setup ou rota quebrados; sem isso o contrato (B) fica verde por vácuo.'
    );

    $payload = $r->json();
    $this->assertSame(
        $id,
        (int) ($payload['props']['purchase']['id'] ?? 0),
        'O payload deveria trazer a compra pedida.'
    );
});

it('UC-PURSHW-01 (B · contrato T0) o detalhe de compra de OUTRO business responde 404', function () {
    $alheia = DB::table('business_locations')
        ->where('business_id', '!=', PURSHW_TENANT)
        ->orderBy('id')
        ->first();

    if (! $alheia) {
        $this->markTestSkipped('Lane sem 2o business COM location — cross-tenant nao exercitavel.');
    }

    $idAlheio = purshwCriarCompra(
        (int) $alheia->business_id,
        (int) $alheia->id,
        $this->user->id,
        999.99
    );

    $r = ($this->abrirDetalhe)($idAlheio);

    // 404 e não 403: não revelar a EXISTÊNCIA do recurso alheio (ADR 0093 defense-in-depth).
    // O controller resolve com `Transaction::where('business_id',...)->firstOrFail()`.
    $this->assertSame(
        404,
        $r->status(),
        'VAZAMENTO TIER 0 (ADR 0093): o detalhe da compra do business ' . $alheia->business_id
        . ' respondeu ' . $r->status() . ' para o usuario do tenant ' . PURSHW_TENANT
        . '. App\\Transaction nao tem global scope — conferir o where(business_id) do show().'
    );
});

// ─────────────────────────────────────────────────────────────────────────────
// UC-PURSHW-05 [V0] — os totais vêm prontos do controller, não derivados
// ─────────────────────────────────────────────────────────────────────────────

it('UC-PURSHW-05 [V0] o final_total do payload e o do BANCO, nao uma derivacao das linhas', function () {
    $id = purshwCriarCompra(PURSHW_TENANT, $this->location, $this->user->id, PURSHW_TOTAL_GRAVADO);

    $payload = ($this->abrirDetalhe)($id)->json();
    $p = $payload['props']['purchase'] ?? [];

    // Caminho 1 — o payload HTTP.
    $doPayload = (float) ($p['final_total'] ?? -1);
    // Caminho 2 — SELECT direto, independente do controller (REGRA MESTRE: dois caminhos).
    $doBanco = (float) DB::table('transactions')->where('id', $id)->value('final_total');

    $this->assertEqualsWithDelta(
        $doBanco,
        $doPayload,
        0.0001,
        "[V0] O final_total do payload ({$doPayload}) difere do gravado no banco ({$doBanco})."
    );

    // A DISCRIMINAÇÃO: esta compra não tem `purchase_lines`, então qualquer agregação das
    // linhas daria 0. Se `final_total` passar a ser derivado (de net_total, do subtotal das
    // linhas, de uma reagregação), este assert cai — e é a fronteira de recálculo que a
    // REGRA MESTRE proíbe. Sem ele, um `final_total = $net_total` ficaria verde em toda
    // compra bem-comportada, onde os dois números coincidem.
    $netTotal = (float) ($p['net_total'] ?? -1);
    $this->assertEqualsWithDelta(0.0, $netTotal, 0.0001, 'Pre-condicao: sem linhas, net_total e 0.');
    $this->assertEqualsWithDelta(
        PURSHW_TOTAL_GRAVADO,
        $doPayload,
        0.0001,
        '[V0] final_total virou DERIVADO: veio ' . $doPayload . ' com net_total ' . $netTotal
        . ', quando o banco grava ' . PURSHW_TOTAL_GRAVADO . '. A tela recebe valor pronto, '
        . 'nunca uma reagregação (charter Non-Goal 3 + REGRA MESTRE de valor).'
    );
});

it('UC-PURSHW-05 [V0] antes -> depois: mudar o total no banco move o payload na MESMA medida', function () {
    $id = purshwCriarCompra(PURSHW_TENANT, $this->location, $this->user->id, PURSHW_TOTAL_GRAVADO);

    $antes = (float) (($this->abrirDetalhe)($id)->json()['props']['purchase']['final_total'] ?? -1);

    $novo = PURSHW_TOTAL_GRAVADO + 100.00;
    DB::table('transactions')->where('id', $id)->update(['final_total' => $novo]);

    $depois = (float) (($this->abrirDetalhe)($id)->json()['props']['purchase']['final_total'] ?? -1);

    // Segundo caminho da REGRA MESTRE: a prova é o DELTA com número concreto.
    $this->assertEqualsWithDelta(
        100.00,
        $depois - $antes,
        0.0001,
        "[V0] O payload nao acompanhou o banco: antes={$antes} depois={$depois} "
        . '(delta esperado 100.00). O total exibido tem que ser o total GRAVADO.'
    );
    $this->assertEqualsWithDelta($novo, $depois, 0.0001, "[V0] O payload deveria trazer {$novo}.");
});
