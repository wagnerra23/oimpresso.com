<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Busca de cliente da tela de venda (`GET /contacts/customers?q=`) com mais de uma palavra.
 *
 * Pedido da ROTA LIVRE (2026-11-10): o cliente aparecia ao digitar só o primeiro nome e
 * sumia ao digitar o primeiro nome mais o começo do sobrenome. O termo inteiro ia num
 * único LIKE, então qualquer coisa entre as palavras no cadastro (espaço duplo, nome do
 * meio, espaço não-separável de importação) fazia o cliente não casar.
 *
 * Os nomes abaixo são fictícios. Tenant de teste 98 × adversário 99 (ADR 0358).
 */
uses(DatabaseTransactions::class);

beforeEach(function () {
    if (! Schema::hasTable('contacts')) {
        $this->markTestSkipped('Schema UltimatePOS ausente — rode com DB_CONNECTION=mysql.');
    }

    $this->business = $this->seededTenant();
    $this->user = $this->usuarioComPermissoes(['customer.view'], $this->business);
    $this->actingAs($this->user);
    session(['user.business_id' => $this->business->id, 'user.id' => $this->user->id, 'business.id' => $this->business->id]);
});

function bcvpCliente(int $businessId, int $userId, string $nome): int
{
    return (int) DB::table('contacts')->insertGetId([
        'business_id' => $businessId, 'type' => 'customer', 'name' => $nome,
        'created_by' => $userId, 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

/** Ids que a busca da venda devolve para o termo. */
function bcvpBusca($teste, string $termo): array
{
    $r = $teste->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
        ->getJson('/contacts/customers?'.http_build_query(['q' => $termo]));
    $r->assertOk();

    return collect($r->json())->pluck('id')->map(fn ($id) => (int) $id)->all();
}

test('nome e começo do sobrenome acham o cliente mesmo com espaço duplo no cadastro', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $id = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA  {$marca}TEIXEIRA");

    // Controle positivo: o primeiro nome sozinho sempre achou.
    expect(bcvpBusca($this, "{$marca}MARINA"))->toContain($id);
    // O caso do relato: nome + começo do sobrenome.
    expect(bcvpBusca($this, "{$marca}MARINA {$marca}TEI"))->toContain($id);
});

test('nome do meio entre as palavras digitadas não esconde o cliente', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $id = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA DE SOUZA {$marca}TEIXEIRA");

    expect(bcvpBusca($this, "{$marca}MARINA {$marca}TEI"))->toContain($id);
});

test('espaço não-separável vindo de importação não esconde o cliente', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $id = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA\u{00A0}{$marca}TEIXEIRA");

    expect(bcvpBusca($this, "{$marca}MARINA {$marca}TEI"))->toContain($id);
});

test('cada palavra precisa casar: sobrenome diferente fica de fora', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $certa = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA {$marca}TEIXEIRA");
    $outra = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA {$marca}LOPES");

    $achados = bcvpBusca($this, "{$marca}MARINA {$marca}TEI");
    expect($achados)->toContain($certa)
        ->and($achados)->not->toContain($outra);
});

test('Tier 0 — cliente de outra empresa não volta na busca', function () {
    $marca = 'ZQX'.random_int(1000, 9999);
    $outro = $this->seededSupportClientTenant();
    $alheio = bcvpCliente($outro->id, $this->user->id, "{$marca}MARINA {$marca}TEIXEIRA");
    $meu = bcvpCliente($this->business->id, $this->user->id, "{$marca}MARINA {$marca}TEIXEIRA");

    $achados = bcvpBusca($this, "{$marca}MARINA {$marca}TEI");
    expect($achados)->toContain($meu)
        ->and($achados)->not->toContain($alheio);
});

/*
 * Lista de clientes (/cliente, prop `customers` carregada em parcial — headers do @inertiajs/core,
 * §5 2026-09-08). Mesma regra: cada palavra casa em separado (pedido da ROTA LIVRE, 2026-11-11).
 */

function bcvpListaCliente(int $businessId, int $userId, string $nome): int
{
    $linha = [
        'business_id' => $businessId, 'type' => 'customer', 'name' => $nome,
        'created_by' => $userId, 'contact_status' => 'active',
        'created_at' => now(), 'updated_at' => now(),
    ];
    if (Schema::hasColumn('contacts', 'is_customer')) {
        $linha['is_customer'] = 1;
    }

    return (int) DB::table('contacts')->insertGetId($linha);
}

/** Ids que a lista /cliente devolve para a busca. */
function bcvpLista($teste, string $termo): array
{
    $versao = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());
    $r = $teste->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $versao,
        'X-Requested-With' => 'XMLHttpRequest',
        'X-Inertia-Partial-Component' => 'Cliente/Index',
        'X-Inertia-Partial-Data' => 'customers',
        'Accept' => 'text/html, application/xhtml+xml',
    ])->get('/cliente?' . http_build_query(['type' => 'customer', 'q' => $termo]));
    $r->assertOk();

    return collect($r->json('props.customers.data'))->pluck('id')->map(fn ($id) => (int) $id)->all();
}

test('lista de clientes: nome e começo do sobrenome acham o cadastro com nome do meio', function () {
    $marca = 'ZQX' . random_int(1000, 9999);
    $id = bcvpListaCliente($this->business->id, $this->user->id, "{$marca}MARINA DE SOUZA {$marca}TEIXEIRA");
    $outra = bcvpListaCliente($this->business->id, $this->user->id, "{$marca}MARINA {$marca}LOPES");

    // Controle positivo: o primeiro nome sozinho acha as duas.
    $soNome = bcvpLista($this, "{$marca}MARINA");
    expect($soNome)->toContain($id)->and($soNome)->toContain($outra);

    $achados = bcvpLista($this, "{$marca}MARINA {$marca}TEI");
    expect($achados)->toContain($id)
        ->and($achados)->not->toContain($outra);
});

test('lista de clientes: Tier 0 — cliente de outra empresa não volta na busca', function () {
    $marca = 'ZQX' . random_int(1000, 9999);
    $outro = $this->seededSupportClientTenant();
    $alheio = bcvpListaCliente($outro->id, $this->user->id, "{$marca}MARINA {$marca}TEIXEIRA");
    $meu = bcvpListaCliente($this->business->id, $this->user->id, "{$marca}MARINA {$marca}TEIXEIRA");

    $achados = bcvpLista($this, "{$marca}MARINA {$marca}TEI");
    expect($achados)->toContain($meu)
        ->and($achados)->not->toContain($alheio);
});
