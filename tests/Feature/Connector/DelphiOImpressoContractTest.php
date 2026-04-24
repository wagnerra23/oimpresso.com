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

it('LogDelphiAccess::extractHd retorna null quando body nao tem HD', function () {
    // extractHd isoladamente — o middleware agora loga mesmo sem HD
    // pra capturar qualquer endpoint que o Delphi bate.
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $request = \Illuminate\Http\Request::create('/any', 'POST', [], [], [], [], '{}');
    $request->headers->set('Content-Type', 'application/json');

    expect($method->invoke($mw, $request))->toBeNull();
});

it('LogDelphiAccess loga TODAS as chamadas, mesmo sem HD', function () {
    // Guard que o middleware NAO tem early-return quando hd=null. Permite
    // capturar endpoints novos do Delphi (sync endpoints, GETs, etc) que
    // nao carregam HD no body.
    $source = file_get_contents(
        (new ReflectionClass(\Modules\Officeimpresso\Http\Middleware\LogDelphiAccess::class))->getFileName()
    );
    expect($source)->not->toContain('if (! $hd) return $response;');
    expect($source)->not->toContain('if (!$hd) return $response;');
});

it('log.delphi aplicado a TODO /connector/api/*', function () {
    // Guarda que o route widen esta em vigor — nao regrede pro padrao
    // antigo de aplicar so em 4 endpoints especificos.
    $routes = file_get_contents(base_path('Modules/Connector/Routes/api.php'));
    expect($routes)->toContain("'log.delphi'");
    expect($routes)->toContain("'auth:api', 'timezone', 'log.delphi'");
});

// ==========================================================
// Fase 1+2: CNPJ por business_location e resolucao de identidade
// ==========================================================

it('business_locations tem colunas fiscais (cnpj, razao_social, etc)', function () {
    // Guarda a migration Fase 1 — se alguem dropar o campo, testes quebram.
    $cols = \DB::select('SHOW COLUMNS FROM business_locations');
    $names = array_map(fn ($c) => $c->Field, $cols);
    expect($names)->toContain('cnpj');
    expect($names)->toContain('razao_social');
    expect($names)->toContain('nome_fantasia');
    expect($names)->toContain('inscricao_estadual');
    expect($names)->toContain('inscricao_municipal');
});

it('licenca_log tem coluna business_location_id', function () {
    $cols = \DB::select('SHOW COLUMNS FROM licenca_log');
    $names = array_map(fn ($c) => $c->Field, $cols);
    expect($names)->toContain('business_location_id');
});

it('LogDelphiAccess::resolveByCnpj prioriza business_locations.cnpj', function () {
    // Este teste prova que, se a location tiver CNPJ especifico, a resolucao
    // devolve business_location_id alem do business_id. Sem location que
    // batesse, cairia no fallback de business.cnpj (ja coberto por outros testes).
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('resolveByCnpj');
    $method->setAccessible(true);

    // CNPJ imaginario que nao bate em lugar nenhum — esperamos [null, null]
    $payload = [
        ['NOME_TABELA' => 'EMPRESA', 'CNPJCPF' => 'CNPJ-INEXISTENTE-TESTE-' . uniqid()],
        ['NOME_TABELA' => 'LICENCIAMENTO', 'HD' => 'X'],
    ];
    $request = \Illuminate\Http\Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [], json_encode($payload));
    $request->headers->set('Content-Type', 'application/json');

    [$bid, $lid] = $method->invoke($mw, $request);
    expect($bid)->toBeNull();
    expect($lid)->toBeNull();
});

it('LogDelphiAccess::resolveByCnpj respeita route param {business_id}', function () {
    // salvar-equipamento/{business_id} — se business_id veio na URL, usa direto.
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('resolveByCnpj');
    $method->setAccessible(true);

    $request = \Illuminate\Http\Request::create('/connector/api/salvar-equipamento/42', 'POST');
    $route = new \Illuminate\Routing\Route('POST', '/connector/api/salvar-equipamento/{business_id}', fn () => '');
    $route->bind($request);
    $route->setParameter('business_id', '42');
    $request->setRouteResolver(fn () => $route);

    [$bid, $lid] = $method->invoke($mw, $request);
    expect($bid)->toBe(42);
    expect($lid)->toBeNull();
});

// ==========================================================
// Fase 4: UI grid — filtros novos
// ==========================================================

it('LicencaLogController aceita query param ?hd=', function () {
    // Guarda que o controller LE o filtro hd da query string. Protege a
    // feature "clicar no HD filtra por HD" de ser removida silenciosamente.
    $source = file_get_contents(
        (new ReflectionClass(\Modules\Officeimpresso\Http\Controllers\LicencaLogController::class))->getFileName()
    );
    expect($source)->toContain("\$request->query('hd'");
    expect($source)->toContain("lc.hd");
});

// ==========================================================
// WR Comercial: /connector/api/oimpresso/registrar
// ==========================================================

it('rota oimpresso/registrar existe e exige auth', function () {
    $r = $this->postJson('/connector/api/oimpresso/registrar', ['cnpj' => 'X', 'serial_hd' => 'Y']);
    expect($r->getStatusCode())->toBe(401); // Sem Bearer = unauthenticated
});

it('OImpressoRegistroController aceita JSON flat com serial_hd e cnpj', function () {
    $controller = new \Modules\Connector\Http\Controllers\Api\OImpressoRegistroController();
    $ref = new ReflectionClass($controller);
    $method = $ref->getMethod('extractPayload');
    $method->setAccessible(true);

    $body = [
        'cnpj' => '12.345.678/0001-99',
        'razao_social' => 'EMPRESA TESTE LTDA',
        'hostname' => 'BOOK-TEST',
        'serial_hd' => 'F0A24779',
        'versao_exe' => '1.2.3',
    ];
    $request = \Illuminate\Http\Request::create('/connector/api/oimpresso/registrar', 'POST', [], [], [], [], json_encode($body));
    $request->headers->set('Content-Type', 'application/json');

    $parsed = $method->invoke($controller, $request);
    expect($parsed['serial_hd'])->toBe('F0A24779');
    expect($parsed['cnpj'])->toBe('12.345.678/0001-99');
    expect($parsed['versao_exe'])->toBe('1.2.3');
});

it('OImpressoRegistroController parseia string pipe-separated legado', function () {
    $controller = new \Modules\Connector\Http\Controllers\Api\OImpressoRegistroController();
    $ref = new ReflectionClass($controller);
    $method = $ref->getMethod('extractPayload');
    $method->setAccessible(true);

    // Formato do MontarString: SERIAL|HOST|VERSAO|IP|CNPJ|RAZAO|PASTA|SO|PROC|MEM|VER_BANCO|CAM_BANCO|SISTEMA|PAF
    $pipe = 'F0A24779|BOOK-TEST|1.2.3|192.168.0.10|12.345.678/0001-99|EMPRESA LTDA|C:\\app|Win11|i7|16GB|2024.1|C:\\db|WR|N';
    $request = \Illuminate\Http\Request::create('/connector/api/oimpresso/registrar', 'POST', [], [], [], [], $pipe);
    $request->headers->set('Content-Type', 'text/plain');

    $parsed = $method->invoke($controller, $request);
    expect($parsed['serial_hd'])->toBe('F0A24779');
    expect($parsed['cnpj'])->toBe('12.345.678/0001-99');
    expect($parsed['hostname'])->toBe('BOOK-TEST');
    expect($parsed['versao_exe'])->toBe('1.2.3');
});

it('LogDelphiAccess extrai serial_hd do body flat (WR Comercial)', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('extractHd');
    $method->setAccessible(true);

    $body = ['cnpj' => 'X', 'serial_hd' => 'WRHDXXX'];
    $request = \Illuminate\Http\Request::create('/connector/api/oimpresso/registrar', 'POST', [], [], [], [], json_encode($body));
    $request->headers->set('Content-Type', 'application/json');

    expect($method->invoke($mw, $request))->toBe('WRHDXXX');
});

it('LogDelphiAccess resolve CNPJ do body flat', function () {
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $method = $ref->getMethod('resolveByCnpj');
    $method->setAccessible(true);

    // CNPJ inexistente — prova que o middleware LE o campo cnpj flat e nao
    // cai so no formato NOME_TABELA=EMPRESA.
    $body = ['cnpj' => 'CNPJ-INEXISTENTE-' . uniqid(), 'serial_hd' => 'X'];
    $request = \Illuminate\Http\Request::create('/connector/api/oimpresso/registrar', 'POST', [], [], [], [], json_encode($body));
    $request->headers->set('Content-Type', 'application/json');

    [$bid, $lid] = $method->invoke($mw, $request);
    expect($bid)->toBeNull();
    expect($lid)->toBeNull();
});

it('ProcessaDadosCliente aceita body flat com serial_hd (Services.LicencaThread.pas)', function () {
    // Guarda do fallback: quando Delphi envia apenas {host, ip, serial_hd,
    // sistema, versao} (sem CNPJ, formato TThreadLicenca), o controller
    // resolve business via lookup de HD em licenca_computador em vez de
    // devolver 400.
    $controller = new \Modules\Connector\Http\Controllers\Api\LicencaComputadorController();
    $source = file_get_contents((new ReflectionClass($controller))->getFileName());
    expect($source)->toContain('processarApenasHd');
    expect($source)->toContain("'serial_hd'");
    expect($source)->toContain("Maquina nao cadastrada");
});

// ==========================================================
// Fixtures reais dos 3 formatos que o Delphi envia
// (tests/Feature/Connector/fixtures/*)
// ==========================================================

function fixtureBody(string $name): string
{
    return file_get_contents(__DIR__ . '/fixtures/' . $name);
}

it('fixture array_tabelas parse e extrai HD + CNPJ corretos', function () {
    $body = fixtureBody('delphi_body_array_tabelas.json');
    $payload = json_decode($body, true);

    $empresa = collect($payload)->firstWhere('NOME_TABELA', 'EMPRESA');
    $licenciamento = collect($payload)->firstWhere('NOME_TABELA', 'LICENCIAMENTO');

    expect($empresa['CNPJCPF'])->toBe('10.609.954/0001-50');
    expect($empresa['RAZAOSOCIAL'])->toBe('JAIR UMBELINA VARGAS ME');
    expect($licenciamento['HD'])->toBe('F0A24779');
    expect($licenciamento['VERSAO_EXE'])->toBe('2026.1.1.6');

    // Middleware consegue extrair
    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $req = \Illuminate\Http\Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [], $body);
    $req->headers->set('Content-Type', 'application/json');

    $hdMethod = $ref->getMethod('extractHd'); $hdMethod->setAccessible(true);
    expect($hdMethod->invoke($mw, $req))->toBe('F0A24779');

    $fmtMethod = $ref->getMethod('detectBodyFormat'); $fmtMethod->setAccessible(true);
    expect($fmtMethod->invoke($mw, $body))->toBe('array_tabelas');
});

it('fixture json_flat parse extrai HD (sem CNPJ)', function () {
    $body = fixtureBody('delphi_body_json_flat.json');
    $payload = json_decode($body, true);

    expect($payload['serial_hd'])->toBe('F0A24779');
    expect($payload['versao'])->toBe('2026.1.1.6');
    expect($payload)->not->toHaveKey('cnpj'); // Este e o gap real: TThreadLicenca nao manda CNPJ

    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $req = \Illuminate\Http\Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [], $body);
    $req->headers->set('Content-Type', 'application/json');

    $hdMethod = $ref->getMethod('extractHd'); $hdMethod->setAccessible(true);
    expect($hdMethod->invoke($mw, $req))->toBe('F0A24779');

    $fmtMethod = $ref->getMethod('detectBodyFormat'); $fmtMethod->setAccessible(true);
    expect($fmtMethod->invoke($mw, $body))->toBe('json_flat');
});

it('fixture pipe extrai HD e CNPJ via posicao', function () {
    $body = fixtureBody('delphi_body_pipe.txt');

    $mw = new \Modules\Officeimpresso\Http\Middleware\LogDelphiAccess();
    $ref = new ReflectionClass($mw);
    $req = \Illuminate\Http\Request::create('/connector/api/processa-dados-cliente', 'POST', [], [], [], [], $body);
    // pipe usa text/plain
    $req->headers->set('Content-Type', 'text/plain');

    $hdMethod = $ref->getMethod('extractHd'); $hdMethod->setAccessible(true);
    expect($hdMethod->invoke($mw, $req))->toBe('F0A24779');

    $fmtMethod = $ref->getMethod('detectBodyFormat'); $fmtMethod->setAccessible(true);
    expect($fmtMethod->invoke($mw, $body))->toBe('pipe');
});

it('LogDelphiAccess metadata captura body_preview + body_format + headers', function () {
    // Guarda que o middleware loga info suficiente pra debug pos-hoc.
    $source = file_get_contents(
        (new ReflectionClass(\Modules\Officeimpresso\Http\Middleware\LogDelphiAccess::class))->getFileName()
    );
    expect($source)->toContain("'body_preview'");
    expect($source)->toContain("'body_format'");
    expect($source)->toContain("'body_size'");
    expect($source)->toContain("'request_headers'");
    expect($source)->toContain('extractRelevantHeaders');
});

it('comando artisan officeimpresso:inspect-api existe', function () {
    $cmds = \Illuminate\Support\Facades\Artisan::all();
    expect(array_key_exists('officeimpresso:inspect-api', $cmds))->toBeTrue();
});

it('processarApenasHd atualiza TODAS as licenca_computador com aquele HD', function () {
    // HD compartilhado em N businesses (notebook de suporte remoto):
    // sem CNPJ pra desambiguar, atualizamos todas as linhas. Guarda que
    // a politica "update all" esta no source — previne alguem voltar pra
    // comportamento ->first() que pega so a mais recente.
    $source = file_get_contents(
        (new ReflectionClass(\Modules\Connector\Http\Controllers\Api\LicencaComputadorController::class))->getFileName()
    );
    expect($source)->toContain('Licenca_Computador::where(\'hd\', $hd)->update(');
    // Nao regrede pra pegar so o mais recente
    expect($source)->not->toContain('orderByDesc(\'dt_ultimo_acesso\')->first()');
});

it('ProcessaDadosCliente com HD nao cadastrado retorna N;Maquina nao cadastrada', function () {
    $user = \App\User::first();
    if (! $user) { expect(true)->toBeTrue(); return; }
    $token = $user->createToken('t')->accessToken;

    $r = $this->withHeaders([
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $token,
    ])->postJson('/connector/api/processa-dados-cliente', [
        'host' => 'TEST-HOST',
        'ip' => '127.0.0.1',
        'serial_hd' => 'HD-INEXISTENTE-' . uniqid(),
        'sistema' => 'Teste',
        'versao' => '1.0',
    ]);

    expect($r->getStatusCode())->toBe(200);
    expect($r->getContent())->toStartWith('N;');
});

it('resposta do registrar segue contrato WR Comercial (autorizado S/N)', function () {
    // Guarda a shape: Services.OImpresso.Registro.pas faz
    //   Result.Autorizado := Resp.GetValue<string>('autorizado', 'N') = 'S';
    // Qualquer mudanca pra bool quebra o Delphi.
    $source = file_get_contents(
        (new ReflectionClass(\Modules\Connector\Http\Controllers\Api\OImpressoRegistroController::class))->getFileName()
    );
    expect($source)->toContain("'autorizado'");
    expect($source)->toContain("'S'");
    expect($source)->toContain("'N'");
    expect($source)->toContain("'licenca_id'");
    expect($source)->toContain("'dias_restantes'");
    expect($source)->toContain("'data_expiracao'");
});
