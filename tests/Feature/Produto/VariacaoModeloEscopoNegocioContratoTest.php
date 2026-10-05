<?php

declare(strict_types=1);

use App\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\EstoqueFixture;

/**
 * Tier 0 (ADR 0093) — `POST /products/get_variation_template` só devolve modelo de variação
 * do PRÓPRIO negócio. Antes a busca era só por `id`: o 98 passava o `template_id` do 99 e
 * recebia o nome e os valores do modelo alheio (e o HTML da linha renderizado com eles).
 *
 * ÂNCORA (contrato, não implementação):
 *   - ADR 0093: nenhuma rota lê dado de negócio de outro tenant.
 *   - Regra de produto: recurso de outro negócio = 404 (mesmo desfecho de id inexistente).
 *
 * ⛔ Tenant 98 (ADR 0358) contra o cliente fictício 99. NUNCA biz=4.
 * ⚠️ SKIP sem schema MySQL: leia assertions, não "0 failed" (LC-13).
 *
 * @see app/Http/Controllers/ProductController.php getVariationTemplate()
 */
uses(DatabaseTransactions::class);

const VMODELO_TAG = '[vmodelo-t0]';

function vmodeloUsuario(int $bizId): User
{
    $id = DB::table('users')->insertGetId([
        'first_name' => 'Modelo Variação T0', 'username' => 'vmodelo_' . uniqid(), 'password' => bcrypt('ci'),
        'business_id' => $bizId, 'user_type' => 'user', 'allow_login' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    return User::findOrFail($id);
}

function vmodeloModelo(int $bizId, string $nome, array $valores): int
{
    $id = (int) DB::table('variation_templates')->insertGetId([
        'name' => $nome . ' ' . VMODELO_TAG, 'business_id' => $bizId,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ($valores as $v) {
        DB::table('variation_value_templates')->insert([
            'name' => $v, 'variation_template_id' => $id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    return $id;
}

function vmodeloPedir(object $test, User $user, int $templateId): \Illuminate\Testing\TestResponse
{
    session(['user.business_id' => (int) $user->business_id, 'user.id' => $user->id]);

    return $test->actingAs($user)
        ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
        ->post('/products/get_variation_template', ['template_id' => $templateId, 'row_index' => 0]);
}

beforeEach(function () {
    if (! EstoqueFixture::schemaReady()) {
        $this->markTestSkipped('Schema UltimatePOS/seed ausente — roda na lane MySQL / CT 100.');
    }
    $this->biz = $this->seededTenant();
    $this->vizinho = $this->seededSupportClientTenant();
});

it('modelo do próprio negócio devolve os valores e o HTML da linha', function () {
    $proprio = vmodeloModelo($this->biz->id, 'Tamanho', ['P-98', 'M-98']);

    $resp = vmodeloPedir($this, vmodeloUsuario($this->biz->id), $proprio);

    $resp->assertOk();
    expect(collect($resp->json('values'))->pluck('text')->all())->toEqualCanonicalizing(['P-98', 'M-98']);
    $this->assertStringContainsString('P-98', (string) $resp->json('html'), 'HTML da linha deve trazer os valores do modelo próprio');
});

it('modelo do vizinho devolve 404 e não vaza nome nem valores', function () {
    // anti-vácuo: o próprio funciona na mesma requisição-forma (prova que o 404 é do escopo, não da rota)
    $proprio = vmodeloModelo($this->biz->id, 'Cor', ['AZUL-98']);
    $alheio = vmodeloModelo($this->vizinho->id, 'Segredo 99', ['VERMELHO-99', 'VERDE-99']);
    $user = vmodeloUsuario($this->biz->id);

    vmodeloPedir($this, $user, $proprio)->assertOk();

    $resp = vmodeloPedir($this, $user, $alheio);

    $resp->assertNotFound();
    $corpo = (string) $resp->getContent();
    $this->assertStringNotContainsString('VERMELHO-99', $corpo, 'valor do modelo alheio não pode vazar');
    $this->assertStringNotContainsString('Segredo 99', $corpo, 'nome do modelo alheio não pode vazar');
});

it('id inexistente devolve 404 em vez de erro 500', function () {
    $inexistente = (int) DB::table('variation_templates')->max('id') + 1000;

    vmodeloPedir($this, vmodeloUsuario($this->biz->id), $inexistente)->assertNotFound();
});
