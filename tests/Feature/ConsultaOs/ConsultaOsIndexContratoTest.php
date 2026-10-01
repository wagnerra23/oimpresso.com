<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/**
 * Contrato da tela pública /consulta-os — resources/js/Pages/ConsultaOs/Index.casos.md.
 *
 * Os UC derivam do charter (Index.charter.md: Goals, Non-Goals, Anti-hooks), nunca do .tsx.
 * Cada `it()` cita o UC que defende (casos-gate G-2).
 *
 * Sem banco: o portal é mock-only (MockConsultaOsRepository, US-CONSULTA-001 pendente) e a
 * rota é pública, sem business_id. Por isso roda na lane sqlite sem markTestSkipped —
 * teste que pula não prova nada.
 *
 * Asserts de ausência usam assertArrayNotHasKey, nunca `->not->toHaveKey($k, $msg)`:
 * o 2º argumento do toHaveKey é o VALOR esperado, e a forma negada passa sempre
 * (proibicoes §5 2026-09-22, LC-31).
 */

/** Chaves que o portal público nunca pode devolver (charter §Non-Goals: dado interno/financeiro). */
const CONSULTA_OS_CHAVES_PROIBIDAS = [
    'business_id', 'total', 'total_final', 'final_total', 'preco', 'price', 'valor',
    'custo', 'cost', 'lucro', 'margem', 'cpf', 'cnpj', 'cliente_cpf', 'cliente_cnpj',
];

function consultaOsChavesProibidasEm(array $dados): array
{
    $achadas = [];
    foreach ($dados as $chave => $valor) {
        if (is_string($chave) && in_array(strtolower($chave), CONSULTA_OS_CHAVES_PROIBIDAS, true)) {
            $achadas[] = $chave;
        }
        if (is_array($valor)) {
            $achadas = array_merge($achadas, consultaOsChavesProibidasEm($valor));
        }
    }

    return $achadas;
}

it('UC-COS-01 portal abre sem login e renderiza a tela ConsultaOs/Index', function () {
    $this->assertGuest();

    // Visita Inertia (X-Inertia): o servidor devolve o page object em JSON sem renderizar o
    // blade raiz — que lê a tabela `system` e não existe no sqlite :memory: desta lane.
    // A versão vem do próprio HandleInertiaRequests::version() — senão o middleware devolve 409.
    $versao = (string) app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $response = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => $versao])
        ->get('/consulta-os');

    $response->assertOk();
    $response->assertHeader('X-Inertia', 'true');
    // Lê o page object direto: nesta lane o assertInertia reprovou com "Not a valid Inertia
    // response" (ele exige component/props/url/version) com status 200 e X-Inertia presentes.
    // O contrato do UC é a tela renderizada, então é ela que se confere.
    $response->assertJsonPath('component', 'ConsultaOs/Index');
    expect($response->json('props'))->toBeArray();
});

it('UC-COS-02 busca por número devolve o status daquela OS', function () {
    $a = $this->getJson('/consulta-os/buscar?numero=4821');
    $b = $this->getJson('/consulta-os/buscar?numero=4819');

    $a->assertOk()->assertJsonPath('found', true)->assertJsonPath('os.id', '4821');
    $b->assertOk()->assertJsonPath('found', true)->assertJsonPath('os.id', '4819');

    $stageA = $a->json('os.stage');
    $stageB = $b->json('os.stage');
    expect($stageA)->toBeString()->not->toBe('');
    // Caso discriminante: duas OS em estágios diferentes devolvem estágios diferentes.
    // Um status fixo (sem vínculo com o número) faria os dois iguais.
    expect($stageA)->not->toBe($stageB);
});

it('UC-COS-03 OS não encontrada devolve 404 só com found=false, sem pista nem texto técnico', function () {
    $response = $this->getJson('/consulta-os/buscar?numero=00000');

    $response->assertNotFound();
    $response->assertExactJson(['found' => false]);
});

it('UC-COS-04 payload público não carrega dado interno nem financeiro', function () {
    // Controle positivo: a varredura acha chave proibida aninhada (senão o assert abaixo é vácuo).
    expect(consultaOsChavesProibidasEm(['os' => ['items' => [['custo' => 1]]]]))->toBe(['custo']);

    foreach (['4821', '4819', '4817', '4815'] as $numero) {
        $response = $this->getJson('/consulta-os/buscar?numero='.$numero);
        $response->assertOk();

        $os = $response->json('os');
        expect($os)->toBeArray()->not->toBeEmpty();
        expect(consultaOsChavesProibidasEm($os))->toBe([]);
    }
});

it('UC-COS-05 sem número não há busca nem lista; com número volta uma OS só', function () {
    $this->getJson('/consulta-os/buscar')->assertStatus(422);
    $this->getJson('/consulta-os/buscar?numero=')->assertStatus(422);

    $os = $this->getJson('/consulta-os/buscar?numero=4821')->assertOk()->json('os');
    // Uma OS é um mapa com `id`, não uma lista de OS.
    expect(array_is_list($os))->toBeFalse();
    $this->assertArrayHasKey('id', $os);
});

it('UC-COS-06 as rotas públicas do portal são só leitura (GET)', function () {
    $publicas = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($r) => str_starts_with((string) $r->getName(), 'consulta-os.'));

    // Anti-vácuo: as duas rotas do charter existem.
    expect($publicas->map->getName()->sort()->values()->all())
        ->toBe(['consulta-os.buscar', 'consulta-os.index']);

    foreach ($publicas as $rota) {
        expect(array_values(array_diff($rota->methods(), ['GET', 'HEAD'])))->toBe([]);
    }
});

it('UC-COS-07 anti-enumeração: throttle nas duas rotas e número fora do formato é recusado', function () {
    foreach (['consulta-os.index', 'consulta-os.buscar'] as $nome) {
        $middleware = Route::getRoutes()->getByName($nome)->gatherMiddleware();
        $temThrottle = collect($middleware)->contains(fn ($m) => is_string($m) && str_starts_with($m, 'throttle:'));
        expect($temThrottle)->toBeTrue();
    }

    $this->getJson('/consulta-os/buscar?numero='.urlencode("1' OR '1'='1"))->assertStatus(422);
    $this->getJson('/consulta-os/buscar?numero='.str_repeat('9', 21))->assertStatus(422);
    // Controle: o mesmo endpoint aceita um número no formato válido.
    $this->getJson('/consulta-os/buscar?numero=4821')->assertOk();
});
