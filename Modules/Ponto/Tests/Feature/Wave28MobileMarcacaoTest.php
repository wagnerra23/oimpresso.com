<?php

declare(strict_types=1);

use Modules\Ponto\Entities\Marcacao;
use Modules\Ponto\Http\Controllers\Api\MobileMarcacaoController;
use Modules\Ponto\Services\MobileMarcacaoService;

uses(Tests\TestCase::class);

/**
 * Wave 28-8 MOBILE MARCACAO — Tangerino-like Ponto mobile API.
 *
 * Cobre:
 *   - Contrato do Service (assinatura, constantes, helpers)
 *   - Anti-cheat: GPS accuracy alto, timestamp drift
 *   - Geofence (opt-in por business — sem config = permite)
 *   - Multi-tenant Tier 0 ([ADR 0093]) — businessId explicito em todos metodos
 *   - APPEND-ONLY Portaria 671/2021 — Service delega ao MarcacaoService canonico
 *   - LGPD: SEM biometria (decisao [W] 2026-08-27) — guard dedicado
 *
 * Source-level + reflexao + unit puro (sem MySQL, sem Sanctum boot) —
 * Pest local-runnable. Pattern Wave 25/26 saturation.
 *
 * Tier 0 IRREVOGAVEL:
 *   - APPEND-ONLY Portaria MTP 671/2021 (Art. 85)
 *   - business_id global scope ADR 0093
 *   - LGPD: nenhuma imagem facial entra no fluxo
 *   - NUNCA biz=4 (Larissa ROTA LIVRE) — biz=1 (Wagner WR2) ou biz=99 (ficticio)
 *
 * @see Modules/Ponto/Services/MobileMarcacaoService.php
 * @see Modules/Ponto/Http/Controllers/Api/MobileMarcacaoController.php
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see Portaria MTP 671/2021 Art. 85 + REP-P
 */

// ============================================================================
// CONTRATO Service — assinaturas + constantes (sem boot)
// ============================================================================

it('Service expoe metodos publicos contratados W28-8', function () {
    $metodos = [
        'registrarMarcacaoMobile',
        'validarGeolocation',
        'listarMarcacoesMobilePendentesValidacao',
    ];
    foreach ($metodos as $m) {
        expect(method_exists(MobileMarcacaoService::class, $m))->toBeTrue("Metodo {$m} ausente");
        $ref = new ReflectionMethod(MobileMarcacaoService::class, $m);
        expect($ref->isPublic())->toBeTrue("Metodo {$m} deve ser public");
    }
});

it('Service constantes anti-cheat declaradas (GPS/drift/geofence)', function () {
    expect(MobileMarcacaoService::GPS_ACCURACY_MAX_METROS)->toBe(500.0);
    expect(MobileMarcacaoService::TIMESTAMP_DRIFT_MAX_SEG)->toBe(30);
    expect(MobileMarcacaoService::GEOFENCE_RAIO_DEFAULT_METROS)->toBe(1000.0);
});

it('Service depende de MarcacaoService canonico via construtor (DI append-only)', function () {
    $ref = new ReflectionMethod(MobileMarcacaoService::class, '__construct');
    $params = $ref->getParameters();
    expect($params)->toHaveCount(1);
    expect($params[0]->getType()->getName())
        ->toBe(\Modules\Ponto\Services\MarcacaoService::class);
});

// ============================================================================
// ANTI-CHEAT — GPS alto / timestamp drift (+ GUARD LGPD anti-biometria)
// ============================================================================

it('GUARD LGPD · o Service NAO conhece biometria — sem selfie, sem hash, sem stub', function () {
    // Decisao [W] 2026-08-27: o ponto INTERNO nao coleta imagem facial.
    // Dado biometrico e dado pessoal SENSIVEL (LGPD Art. 5o, II) e seu
    // tratamento exige hipotese propria (Art. 11). Este guard existe pra que
    // reintroduzir a captura QUEBRE o CI em vez de passar silenciosa.
    $fonte = file_get_contents((new ReflectionClass(MobileMarcacaoService::class))->getFileName());

    // O termo aparece SO nos comentarios que registram a decisao (o "porque"),
    // nunca em codigo executavel: sem parametro, sem hash, sem constante.
    expect($fonte)->not->toContain('selfie_base64');
    expect($fonte)->not->toContain('SELFIE_MIN_BYTES');
    expect($fonte)->not->toContain('$selfieB64');

    // E o metodo de biometria nao existe mais.
    expect(method_exists(MobileMarcacaoService::class, 'verificarBiometria'))->toBeFalse();

    // O dispositivo_id nao pode carregar derivado de biometria.
    expect($fonte)->toContain("'mobile:%s'");
});

it('rejeita GPS accuracy > 500m (sinal ruim / spoof)', function () {
    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    $payload = [
        'tipo'             => Marcacao::TIPO_ENTRADA,
        'lat'              => -28.336,
        'lng'              => -48.926,
        'accuracy'         => 999.0, // acima de 500m
        'device_uuid'      => 'dev-uuid',
        'timestamp_device' => now()->toIso8601String(),
        'usuario_criador_id' => 1,
    ];

    expect(fn () => $svc->registrarMarcacaoMobile(1, 100, $payload))
        ->toThrow(RuntimeException::class, 'GPS accuracy');
});

it('rejeita timestamp_device com drift > 30s (anti-cheat clock)', function () {
    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    $payload = [
        'tipo'             => Marcacao::TIPO_ENTRADA,
        'lat'              => -28.336,
        'lng'              => -48.926,
        'accuracy'         => 10.0,
        'device_uuid'      => 'dev-uuid',
        'timestamp_device' => now()->subMinutes(5)->toIso8601String(), // 300s drift
        'usuario_criador_id' => 1,
    ];

    expect(fn () => $svc->registrarMarcacaoMobile(1, 100, $payload))
        ->toThrow(RuntimeException::class, 'fora de sincronia');
});

it('rejeita tipo de marcacao invalido', function () {
    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    $payload = [
        'tipo'             => 'INTERCORRENCIA', // valido em Marcacao, mas nao via mobile
        'lat'              => -28.336,
        'lng'              => -48.926,
        'accuracy'         => 10.0,
        'device_uuid'      => 'dev-uuid',
        'timestamp_device' => now()->toIso8601String(),
        'usuario_criador_id' => 1,
    ];

    expect(fn () => $svc->registrarMarcacaoMobile(1, 100, $payload))
        ->toThrow(RuntimeException::class, 'Tipo invalido');
});

it('rejeita payload com campo obrigatorio ausente', function () {
    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    expect(fn () => $svc->registrarMarcacaoMobile(1, 100, [
        'tipo' => Marcacao::TIPO_ENTRADA,
        // sem lat, lng, etc.
    ]))->toThrow(RuntimeException::class, 'Campo obrigatorio');
});

// ============================================================================
// GEOFENCE — opt-in por business (sem config = permite)
// ============================================================================

it('geofence sem config retorna true (opt-in por business)', function () {
    config()->set('pontowr2.geofence.business_99', null);

    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    expect($svc->validarGeolocation(-28.336, -48.926, 99))->toBeTrue();
});

it('geofence configurado valida raio 1km (haversine)', function () {
    // Centro Termas do Gravatal/SC (ficticio)
    config()->set('pontowr2.geofence.business_99', [
        'lat' => -28.336,
        'lng' => -48.926,
        'raio_metros' => 1000.0,
    ]);

    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    // Dentro do raio (mesmo ponto)
    expect($svc->validarGeolocation(-28.336, -48.926, 99))->toBeTrue();

    // Muito longe (Florianopolis ~150km) — fora
    expect($svc->validarGeolocation(-27.595, -48.548, 99))->toBeFalse();
});


// ============================================================================
// MULTI-TENANT TIER 0 — business_id explicito (sem session)
// ============================================================================

it('registrarMarcacaoMobile exige businessId explicito (Tier 0 ADR 0093)', function () {
    $ref = new ReflectionMethod(MobileMarcacaoService::class, 'registrarMarcacaoMobile');
    $params = $ref->getParameters();

    expect($params[0]->getName())->toBe('businessId');
    expect($params[0]->getType()->getName())->toBe('int');
    expect($params[0]->allowsNull())->toBeFalse();
});

it('listarMarcacoesMobilePendentesValidacao exige businessId explicito', function () {
    $ref = new ReflectionMethod(MobileMarcacaoService::class, 'listarMarcacoesMobilePendentesValidacao');
    $params = $ref->getParameters();

    expect($params[0]->getName())->toBe('businessId');
    expect($params[0]->getType()->getName())->toBe('int');
});

it('Service source-level: where business_id presente em todas queries (multi-tenant)', function () {
    $source = file_get_contents(
        (new ReflectionClass(MobileMarcacaoService::class))->getFileName()
    );

    expect($source)->toContain("Marcacao::where('business_id', \$businessId)");
});

// ============================================================================
// APPEND-ONLY Portaria 671/2021 — Service delega ao MarcacaoService canonico
// ============================================================================

it('Service NAO faz UPDATE/DELETE em Marcacao (append-only Portaria 671)', function () {
    $source = file_get_contents(
        (new ReflectionClass(MobileMarcacaoService::class))->getFileName()
    );

    // Nenhuma chamada update() ou delete() direta em Marcacao
    expect($source)->not->toMatch('/Marcacao::.*->update\(/');
    expect($source)->not->toMatch('/Marcacao::.*->delete\(/');
    expect($source)->not->toContain('forceDelete');

    // Delega ao MarcacaoService canonico (NSR + hash chain)
    expect($source)->toContain('marcacaoService->registrar(');
    expect($source)->toContain('ORIGEM_REP_P');
});

it('Service usa server now() como momento (server-authoritative — anti-cheat)', function () {
    $source = file_get_contents(
        (new ReflectionClass(MobileMarcacaoService::class))->getFileName()
    );

    // momento vem do server, NAO do payload timestamp_device (anti-cheat)
    expect($source)->toContain("'momento'               => now()");
});

// ============================================================================
// CONTROLLER API — contrato + validacao + sanitization PII
// ============================================================================

it('Controller expoe metodos registrar() + pendentesValidacao()', function () {
    expect(method_exists(MobileMarcacaoController::class, 'registrar'))->toBeTrue();
    expect(method_exists(MobileMarcacaoController::class, 'pendentesValidacao'))->toBeTrue();
});

it('Controller injeta MobileMarcacaoService via DI', function () {
    $ref = new ReflectionMethod(MobileMarcacaoController::class, '__construct');
    $params = $ref->getParameters();
    expect($params)->toHaveCount(1);
    expect($params[0]->getType()->getName())->toBe(MobileMarcacaoService::class);
});

it('Controller source-level: validacao Laravel + LGPD (sem PII em log de erro)', function () {
    $source = file_get_contents(
        (new ReflectionClass(MobileMarcacaoController::class))->getFileName()
    );

    // Validacao Laravel basica antes de chamar service
    expect($source)->toContain('$request->validate([');

    // GUARD LGPD (decisao [W] 2026-08-27): o endpoint NAO recebe imagem facial.
    // Dado biometrico e sensivel (LGPD Art. 5o, II; tratamento pelo Art. 11) e o
    // anti-fraude nao depende dele. Reintroduzir a captura quebra aqui.
    expect($source)->not->toContain('selfie_base64');
    expect($source)->not->toContain('min:100000');

    // Response retorna apenas IDs + hash truncado (sem PII)
    expect($source)->toContain('substr((string) $marcacao->hash, 0, 16)');

    // 422 pra anti-cheat, 401 pra auth, 500 pra erro inesperado
    expect($source)->toContain('], 422)');
    expect($source)->toContain('], 401)');
    expect($source)->toContain('], 201)');
});

it('Controller deduz business_id do user autenticado (Sanctum) — Tier 0 ADR 0093', function () {
    $source = file_get_contents(
        (new ReflectionClass(MobileMarcacaoController::class))->getFileName()
    );

    expect($source)->toContain('(int) $user->business_id');
});

// ============================================================================
// MULTI-TENANT CROSS-TENANT — biz=1 vs biz=99 (ADR 0101 — nunca biz=4 cliente)
// ============================================================================

it('Service haversine retorna 0 pra mesmo ponto (validacao matematica geofence)', function () {
    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    // Reflexion pra acessar metodo protected
    $ref = new ReflectionMethod($svc, 'haversineMetros');
    $ref->setAccessible(true);

    $dist = $ref->invoke($svc, -28.336, -48.926, -28.336, -48.926);
    expect($dist)->toBe(0.0);
});

it('multi-tenant biz=1 vs biz=99 geofence independente (sem cross-tenant leak)', function () {
    config()->set('pontowr2.geofence.business_1', [
        'lat' => -23.55, 'lng' => -46.63, 'raio_metros' => 500.0, // SP
    ]);
    config()->set('pontowr2.geofence.business_99', [
        'lat' => -28.336, 'lng' => -48.926, 'raio_metros' => 500.0, // SC
    ]);

    $svc = new MobileMarcacaoService(
        $this->createMock(\Modules\Ponto\Services\MarcacaoService::class)
    );

    // Funcionario biz=1 marcando em SC: fora geofence biz=1
    expect($svc->validarGeolocation(-28.336, -48.926, 1))->toBeFalse();
    // Funcionario biz=99 marcando em SC: dentro
    expect($svc->validarGeolocation(-28.336, -48.926, 99))->toBeTrue();
});
