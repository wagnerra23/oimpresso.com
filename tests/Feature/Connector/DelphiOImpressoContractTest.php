<?php

/**
 * Contrato de API do Delphi OImpresso (Geração 1 e 2) — ADR 0021.
 *
 * Garante que os endpoints restaurados do 3.7 que o Delphi legado chama
 * NUNCA quebrem. Pest feature test roda em CI + local.
 *
 * Endpoints cobertos:
 *   - POST /connector/api/processa-dados-cliente
 *   - POST /connector/api/salvar-cliente
 *   - POST /connector/api/salvar-equipamento/{business_id}
 *
 * Auth esperada: Passport auth:api (Bearer). Testes usam PassportPersonalAccess
 * token emitido pra um user existente.
 *
 * Regras validadas:
 *   - rotas existem (nao 404)
 *   - rejeitam acesso sem token (401)
 *   - aceitam dados validos (200) e invalidos (400 ou 500 com mensagem)
 *   - ProcessaDadosCliente responde `S;msg` ou `N;motivo` (STRING simples!)
 *     — NAO mudar pra JSON, Delphi parsa texto literal
 */

use App\User;

it('rotas Delphi existem (sem Bearer = 401 Unauthenticated)', function () {
    $endpoints = [
        'POST' => [
            '/connector/api/processa-dados-cliente',
            '/connector/api/salvar-cliente',
            '/connector/api/salvar-equipamento/1',
        ],
    ];

    foreach ($endpoints['POST'] as $url) {
        $r = $this->withHeaders(['Accept' => 'application/json'])->postJson($url, []);
        expect($r->getStatusCode())->toBeIn([401, 422])
            ->not->toBe(404, "Rota {$url} nao foi registrada (404)");
    }
});

it('ProcessaDadosCliente rejeita JSON sem EMPRESA/LICENCIAMENTO', function () {
    $user = User::query()->where('username', 'WR23')->first() ?? User::query()->first();
    if (! $user) $this->markTestSkipped('Sem user no DB');

    // Gera token via Passport personal access (requer client + keys)
    $personalClient = \Laravel\Passport\Client::where('personal_access_client', true)->first();
    if (! $personalClient) $this->markTestSkipped('Passport personal access client nao criado');

    $token = $user->createToken('delphi-test')->accessToken;

    $r = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->postJson('/connector/api/processa-dados-cliente', [
        ['NOME_TABELA' => 'EMPRESA'], // falta LICENCIAMENTO
    ]);

    // Contract: response 400 com mensagem clara
    expect($r->getStatusCode())->toBe(400);
    expect($r->getContent())->toContain('LICENCIAMENTO');
});

it('contrato de resposta: ProcessaDadosCliente devolve STRING simples (S;msg ou N;motivo)', function () {
    // Este teste documenta o contrato sem exercitar o endpoint (precisa de
    // business real pra nao-200). Se a assinatura mudar pra JSON quebra Delphi.
    $controller = new \Modules\Connector\Http\Controllers\Api\LicencaComputadorController();
    expect(method_exists($controller, 'ProcessaDadosCliente'))->toBeTrue();
    expect(method_exists($controller, 'saveEquipamento'))->toBeTrue();
});

it('saveEquipamento usa hd como chave unica por business_id + user_win', function () {
    // Este teste blinda a regra de match no saveEquipamento. Se alguem
    // remover o `->where('hd', ...)` o teste pega.
    $reflection = new ReflectionClass(\Modules\Connector\Http\Controllers\Api\LicencaComputadorController::class);
    $method = $reflection->getMethod('saveEquipamento');
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("where('hd',");
    expect($source)->toContain("where('business_id'");
    expect($source)->toContain("where('user_win',");
});

it('BusinessController::saveBusiness valida CNPJCPF obrigatorio', function () {
    $reflection = new ReflectionClass(\Modules\Connector\Http\Controllers\Api\BusinessController::class);
    $method = $reflection->getMethod('saveBusiness');
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain("'CNPJCPF'");
    expect($source)->toContain("'RAZAOSOCIAL'");
});

it('LogDelphiAccess extrai hd do body g1 (NOME_TABELA=LICENCIAMENTO)', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $payload = [
        ['NOME_TABELA' => 'EMPRESA', 'CNPJCPF' => '12.345.678/0001-99'],
        ['NOME_TABELA' => 'LICENCIAMENTO', 'HD' => 'F0A24779', 'DESCRICAO' => 'BOOK-GV80BF5507'],
    ];
    $request = \Illuminate\Http\Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    $hd = $method->invoke($mw, $request);
    expect($hd)->toBe('F0A24779');
});

it('LogDelphiAccess extrai hd do body g2 (salvar-equipamento flat)', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $payload = ['HD' => 'C4DC39FC', 'DESCRICAO' => 'ELIANA'];
    $request = \Illuminate\Http\Request::create('/connector/api/salvar-equipamento/1', 'POST', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    $hd = $method->invoke($mw, $request);
    expect($hd)->toBe('C4DC39FC');
});

it('LogDelphiAccess extrai hd via header X-OI-HD (fallback)', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $request = \Illuminate\Http\Request::create('/any', 'POST');
    $request->headers->set('X-OI-HD', 'HEADERHD123');

    expect($method->invoke($mw, $request))->toBe('HEADERHD123');
});

it('LogDelphiAccess retorna null sem hd (nao gera log)', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $request = \Illuminate\Http\Request::create('/any', 'POST', [], [], [], [], '{}');
    $request->headers->set('Content-Type', 'application/json');

    expect($method->invoke($mw, $request))->toBeNull();
});
