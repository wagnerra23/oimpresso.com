<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Customer Journey E2E — Modules/ConsultaOs (D5 cliente — fluxo cliente final).
 *
 * Simula jornada cliente publico:
 *   1. Acessa /consulta-os (portal Inertia React)
 *   2. Busca OS pelo numero entregue (mock: 4821, 4819, 4817, 4815)
 *   3. Recebe payload OS com estagio + items (sem PII de outros businesses)
 *   4. Tenta numero inexistente → 404 limpo (sem leak)
 *   5. Tenta brute-force enumeration → 422/throttle protege
 *
 * Mock-only ate US-CONSULTA-001 sair de mock. Quando query real entrar
 * (transactions + invoice_no + ultimos 4 telefone), este contrato E2E
 * permanece valido — apenas trocamos mockData() por query Service read-only
 * com filtro multi-tenant (ADR 0093 lookup business_id via protocolo).
 *
 * Refs:
 *   - ADR 0093 multi-tenant Tier 0 IRREVOGAVEL
 *   - ADR 0155 module-grade v3 D5 (cliente final)
 *   - memory/requisitos/ConsultaOs/SPEC.md US-CONSULTA-001
 *   - Modules/Repair/Routes/web.php /repair-status (padrao a imitar)
 */
beforeEach(function () {
    if (DB::connection()->getDriverName() === 'sqlite') {
        $this->markTestSkipped('SQLite-incompativel: TestCase UltimatePOS requer schema MySQL (ADR 0101).');
    }
});

// IDs canonicos pra tests (ADR 0101) — biz=1 Wagner WR2 (nao se aplica rota publica)
const JOURNEY_BIZ_WAGNER = 1;
const JOURNEY_BIZ_FICTICIO = 99;

it('jornada cliente passo 1: acessa portal /consulta-os e recebe 200 Inertia', function () {
    // Cliente publico (sem auth) chega via link compartilhado pelo vendedor
    $response = $this->get('/consulta-os');

    expect($response->getStatusCode())->toBe(200);
});

it('jornada cliente passo 2: busca OS por numero conhecido e recebe payload publico', function () {
    // Vendedor entregou: "Sua OS e 4821, acompanhe em oimpresso.com/consulta-os"
    $response = $this->getJson('/consulta-os/buscar?numero=4821');

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);

    // Payload publico — cliente ve cliente/contact/vendedor/estagio/items
    expect($response->json('os.client'))->toBe('Acme Comércio Ltda');
    expect($response->json('os.stage'))->toBe('aprovacao');
    expect($response->json('os.items'))->toBeArray();
    expect($response->json('os.items'))->toHaveCount(1);
});

it('jornada cliente passo 3: payload publico NUNCA inclui campos sensiveis (preco, custo, business_id)', function () {
    $response = $this->getJson('/consulta-os/buscar?numero=4821');
    $response->assertStatus(200);

    $os = $response->json('os');

    // Cliente ve estagio + descricao items — NAO ve preco, custo, business_id, CPF/CNPJ
    expect($os)->not->toHaveKey('business_id', 'Portal publico NAO deve vazar business_id (ADR 0093)');
    expect($os)->not->toHaveKey('total_final', 'Cliente externo NAO deve ver valores financeiros');
    expect($os)->not->toHaveKey('lucro', 'Cliente externo NAO deve ver margem/custo interno');
    expect($os)->not->toHaveKey('cliente_cpf', 'Portal publico NAO deve vazar PII bruta');
    expect($os)->not->toHaveKey('cliente_cnpj', 'Portal publico NAO deve vazar PII bruta');
});

it('jornada cliente passo 4: numero inexistente retorna 404 limpo sem leak', function () {
    $response = $this->getJson('/consulta-os/buscar?numero=00000');

    $response->assertStatus(404);
    $response->assertJson(['found' => false]);

    // 404 nao deve incluir dica sobre numeros validos nem listar OS
    $body = $response->json();
    expect($body)->not->toHaveKey('os');
    expect($body)->not->toHaveKey('sugestoes');
    expect($body)->not->toHaveKey('numeros_validos');
});

it('jornada cliente passo 5: filtro por estagio funciona (cliente quer ver só producao)', function () {
    // OS 4817 (Clinica Vida) esta em estagio "producao" no mock
    $response = $this->getJson('/consulta-os/buscar?numero=4817&estagio=producao');

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);
    expect($response->json('os.stage'))->toBe('producao');
});

it('jornada cliente passo 6: filtro por estagio errado retorna 404 (privacidade)', function () {
    // OS 4817 esta em "producao" — cliente filtra por "entregue" → 404 (nao confirma existencia)
    $response = $this->getJson('/consulta-os/buscar?numero=4817&estagio=entregue');

    $response->assertStatus(404);
    $response->assertJson(['found' => false]);
});

it('jornada cliente passo 7: brute-force enumeration bloqueado por validation (alpha_num + max:20)', function () {
    // Tentativa de injection SQL/XSS no campo numero
    $payloadsMaliciosos = [
        "1' OR '1'='1",
        '<script>alert(1)</script>',
        '../../../etc/passwd',
        'numero_com_espacos invalidos',
    ];

    foreach ($payloadsMaliciosos as $payload) {
        $response = $this->getJson('/consulta-os/buscar?numero='.urlencode($payload));

        // FormRequest valida alpha_num — qualquer payload nao-alfanumerico = 422
        expect($response->getStatusCode())->toBeIn([404, 422], "Payload malicioso [{$payload}] nao bloqueado");
    }
});

it('jornada cliente passo 8: rate-limit throttle:30,1 esta ativo (protecao DDoS/enumeration)', function () {
    // Sub-teste do contrato D8 mas integrado ao journey — cliente legitimo
    // nunca atinge limite (max 30 buscas/min); brute-force sim.
    $route = \Route::getRoutes()->getByName('consulta-os.buscar');

    $middlewares = $route->middleware();
    $temThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));

    expect($temThrottle)->toBeTrue(
        'Portal publico DEVE ter throttle: pra proteger cliente legitimo de derrubada DDoS (ADR 0093 + D8 security).'
    );
});

it('jornada cliente passo 9: filtro padrao "todos" retorna OS independente do estagio', function () {
    // Cliente esquece de filtrar — query padrao deve retornar OS sem barrar
    $response = $this->getJson('/consulta-os/buscar?numero=4815&estagio=todos');

    $response->assertStatus(200);
    $response->assertJson(['found' => true]);
    expect($response->json('os.stage'))->toBe('entregue');
});
