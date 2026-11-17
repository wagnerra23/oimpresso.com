<?php

declare(strict_types=1);
// Cobre UC-CIDX-19 (Index.casos.md) — "só os próprios" vale ao abrir o cliente pelo número.

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quem tem só `customer.view_own` vê na lista apenas os clientes que cadastrou ou que foram
 * compartilhados com ele (PessoaEscopo::viewOwn, ContatosViewOwnTest). Os endereços que a
 * ficha chama para cada cliente (/cliente/{id}/sales-json, anexos, IA, auditoria, cadastro,
 * endereços de entrega) só conferiam se o usuário tinha ALGUMA permissão de ver: trocando o
 * número no endereço ele lia o cliente de outro vendedor.
 *
 * Cada caso de recusa tem o controle do lado aceito: o mesmo usuário abre o próprio cliente
 * (200), e quem tem `customer.view` abre o do outro (200). Sem isso um 403 em tudo passaria.
 *
 * Também: as 8 rotas GET /cliente/{id}/{ledger,sales,…} do ClienteOssDataController, que não
 * conferiam permissão nenhuma e nenhuma tela chamava, foram removidas — respondem 404.
 *
 * Tenant de teste (ADR 0358). Transação revertida por caso.
 */

uses(DatabaseTransactions::class);

beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('user_contact_access')) {
        $this->markTestSkipped('Schema UltimatePOS + Spatie exigem MySQL (ADR 0358).');
    }

    $this->business = $this->seededTenant();
    $biz = (int) $this->business->id;

    $this->vendedor = $this->usuarioComPermissoes(['customer.view_own', 'customer.update'], $this->business);
    $this->outro = $this->usuarioComPermissoes(['customer.view'], $this->business);

    $novo = function (int $criadoPor) use ($biz): int {
        $linha = [
            'business_id' => $biz, 'type' => 'customer', 'name' => 'CSP-' . uniqid(),
            'mobile' => '00000000000', 'contact_status' => 'active',
            'created_by' => $criadoPor, 'created_at' => now(), 'updated_at' => now(),
        ];
        if (Schema::hasColumn('contacts', 'is_customer')) {
            $linha['is_customer'] = 1;
        }

        return (int) DB::table('contacts')->insertGetId($linha);
    };
    $this->meu = $novo((int) $this->vendedor->id);
    $this->doOutro = $novo((int) $this->outro->id);

    Config::set('copiloto.cliente_ia.force_mock', true);
});

function cspEntrar($test, $user): void
{
    $test->actingAs($user);
    session([
        'user.business_id' => $test->business->id,
        'user.id' => $user->id,
        'business.id' => $test->business->id,
    ]);
}

/** Endereços de leitura que a ficha chama para um cliente. */
function cspLeituras(int $id): array
{
    return [
        "/cliente/{$id}/sales-json",
        "/cliente/{$id}/payments-json",
        "/cliente/{$id}/rewards-json",
        "/cliente/{$id}/subscriptions-json",
        "/cliente/{$id}/anexos",
        "/cliente/{$id}/ia/score-risco",
        "/cliente/{$id}/auditoria",
    ];
}

test('UC-CIDX-19 só-próprios: cliente de outro vendedor é recusado em todas as leituras da ficha', function () {
    cspEntrar($this, $this->vendedor);

    foreach (cspLeituras($this->doOutro) as $url) {
        expect($this->getJson($url)->status())->toBe(403, "deveria recusar: {$url}");
    }
});

test('UC-CIDX-19 só-próprios: o próprio cliente abre normalmente (controle do lado aceito)', function () {
    cspEntrar($this, $this->vendedor);

    foreach (cspLeituras($this->meu) as $url) {
        expect($this->getJson($url)->status())->toBe(200, "deveria abrir: {$url}");
    }
});

test('UC-CIDX-19 cliente compartilhado com o vendedor (user_contact_access) abre', function () {
    DB::table('user_contact_access')->insert(['user_id' => $this->vendedor->id, 'contact_id' => $this->doOutro]);
    cspEntrar($this, $this->vendedor);

    expect($this->getJson("/cliente/{$this->doOutro}/sales-json")->status())->toBe(200)
        ->and($this->getJson("/cliente/{$this->doOutro}/auditoria")->status())->toBe(200);
});

test('UC-CIDX-19 quem tem customer.view abre o cliente de qualquer vendedor', function () {
    cspEntrar($this, $this->outro);

    expect($this->getJson("/cliente/{$this->meu}/sales-json")->status())->toBe(200)
        ->and($this->getJson("/cliente/{$this->meu}/ia/score-risco")->status())->toBe(200);
});

test('UC-CIDX-19 só-próprios não edita o cliente de outro pelo número (cadastro e endereços)', function () {
    cspEntrar($this, $this->vendedor);
    $antes = DB::table('contacts')->where('id', $this->doOutro)->value('name');

    $r = $this->patchJson("/cliente/{$this->doOutro}/identificacao", ['name' => 'Alterado por outro']);
    expect($r->status())->toBe(403)
        ->and(DB::table('contacts')->where('id', $this->doOutro)->value('name'))->toBe($antes);

    if (Schema::hasTable('contact_addresses')) {
        expect($this->getJson("/cliente/{$this->doOutro}/enderecos")->status())->toBe(403)
            ->and($this->getJson("/cliente/{$this->meu}/enderecos")->status())->toBe(200);
    }
});

test('UC-CIDX-19 só-próprios não manda por e-mail o extrato do cliente de outro', function () {
    cspEntrar($this, $this->vendedor);

    $r = $this->postJson('/contacts/send-ledger', [
        'contact_id' => $this->doOutro,
        'to_email' => 'teste@example.invalid',
    ]);

    expect($r->status())->toBe(403);
});

test('UC-CIDX-19 as 8 rotas sem permissão do ClienteOssDataController não existem mais', function () {
    cspEntrar($this, $this->outro);

    foreach (['ledger', 'sales', 'payments', 'documents', 'activities', 'persons', 'subscriptions', 'rewards'] as $aba) {
        expect($this->getJson("/cliente/{$this->meu}/{$aba}")->status())->toBe(404, "deveria ser 404: {$aba}");
    }
    // Controle: a rota que a aba de vendas usa de verdade continua respondendo.
    expect($this->getJson("/cliente/{$this->meu}/sales-json")->status())->toBe(200);
});
