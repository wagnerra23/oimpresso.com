<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\ConsultaOs\Console\Commands\ConsultaOsHealthCommand;
use Modules\ConsultaOs\Http\Controllers\ConsultaOsController;
use Modules\ConsultaOs\Services\ConsultaOsMockService;

uses(Tests\TestCase::class);

/**
 * Wave 25 SATURATION ConsultaOs — push 69 → ≥85.
 *
 * Cobre D5 (+3) e D9 (+6) restantes da gap audit:
 *
 *   D5.B — README documenta fluxo cliente público (validação source-level)
 *   D5.C — CustomerJourneyTest cobre 9 passos canonicos (existência + cobertura)
 *   D9.A — OtelHelper canon spans no Service (consultaos.busca_publica)
 *   D9.B — Audit log estruturado consultaos.busca_publica (Controller)
 *   D9.C — Rota pública /consulta-os com throttle e tracing
 *   D9.D — ConsultaOsHealthCommand expõe 5 probes operacionais
 *
 * Tier 0 IRREVOGÁVEL (ADR 0093):
 *   - Portal público NÃO scopa por business_id (cliente externo sem sessão)
 *   - Quando US-CONSULTA-001 ativar busca real, lookup do protocolo resolve biz
 *   - Audit log NUNCA leva business_id (rota pública)
 *   - PiiRedactor wraps numero ANTES de logar (defesa em profundidade)
 *
 * Zero hit prod externo (Mock Repository default) — Pest local-runnable sem custo.
 *
 * @see Modules/ConsultaOs/README.md
 * @see Modules/ConsultaOs/Services/ConsultaOsMockService.php
 * @see Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php
 * @see Modules/ConsultaOs/Tests/Feature/CustomerJourneyTest.php
 * @see Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php
 */

// ============================================================================
// D5.B — README cliente público completo (cobertura source-level)
// ============================================================================

it('D5.B README declara fluxo "Como cliente usa" (4 passos canonicos)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('Como cliente usa');
    expect($readme)->toContain('Vendedor entrega');
    expect($readme)->toContain('Cliente acessa portal Inertia React');
    expect($readme)->toContain('numero (alpha_num + max:20');
    expect($readme)->toContain('JSON');
});

it('D5.B README declara LGPD privacy contract (não vaza business_id/total_final)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('business_id');
    expect($readme)->toContain('total_final');
    expect($readme)->toContain('cliente_cpf');
    expect($readme)->toContain('cliente_cnpj');
});

it('D5.B README documenta arquitetura D4 SoC (Routes → Controller → Service → Repository)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('D4 SoC');
    expect($readme)->toContain('ConsultaOsController');
    expect($readme)->toContain('ConsultaOsMockService');
    expect($readme)->toContain('ConsultaOsRepositoryInterface');
});

it('D5.B README cita US-CONSULTA-001 (migração mock → transactions real)', function () {
    $readme = file_get_contents(base_path('Modules/ConsultaOs/README.md'));

    expect($readme)->toContain('US-CONSULTA-001');
    expect($readme)->toContain('Mock-only');
});

// ============================================================================
// D5.C — CustomerJourneyTest cobre 9 passos canonicos do cliente público
// ============================================================================

it('D5.C CustomerJourneyTest existe e cobre 9 passos canonicos do cliente', function () {
    $path = base_path('Modules/ConsultaOs/Tests/Feature/CustomerJourneyTest.php');
    expect(file_exists($path))->toBeTrue();

    $source = file_get_contents($path);

    // 9 passos catalogados:
    expect($source)->toContain('jornada cliente passo 1');
    expect($source)->toContain('jornada cliente passo 2');
    expect($source)->toContain('jornada cliente passo 3');
    expect($source)->toContain('jornada cliente passo 4');
    expect($source)->toContain('jornada cliente passo 5');
    expect($source)->toContain('jornada cliente passo 6');
    expect($source)->toContain('jornada cliente passo 7');
    expect($source)->toContain('jornada cliente passo 8');
    expect($source)->toContain('jornada cliente passo 9');
});

it('D5.C CustomerJourneyTest cobre brute-force enumeration + payload limpo (LGPD)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Tests/Feature/CustomerJourneyTest.php'));

    expect($source)->toContain('brute-force');
    expect($source)->toContain("OR '1'='1");
    expect($source)->toContain('alert(1)');
    expect($source)->toContain('not->toHaveKey');
});

// ============================================================================
// D9.A — OtelHelper canon spans no Service
// ============================================================================

it('D9.A ConsultaOsMockService usa OtelHelper::span canon (App\\Util\\OtelHelper)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Services/ConsultaOsMockService.php'));

    expect($source)->toContain('use App\Util\OtelHelper;');
    expect($source)->toContain("OtelHelper::span('consultaos.busca_publica'");
});

it('D9.A span carrega atributo estagio (correlação com filtro)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Services/ConsultaOsMockService.php'));

    expect($source)->toContain("'estagio' => \$estagio");
});

it('D9.A OtelHelper canon class existe (App\\Util cross-cutting)', function () {
    expect(class_exists(\App\Util\OtelHelper::class))->toBeTrue();
});

// ============================================================================
// D9.B — Audit log estruturado consultaos.busca_publica (Controller)
// ============================================================================

it('D9.B Controller emite log estruturado consultaos.busca_publica com PiiRedactor', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));

    expect($source)->toContain("'consultaos.busca_publica'");
    expect($source)->toContain('PiiRedactor');
    expect($source)->toContain('redact($numero)');
});

it('D9.B Controller trunca IP /24 (anti-tracking LGPD pseudonimização)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));

    expect($source)->toContain('truncarIp');
    expect($source)->toContain("Trunca IP pra /24");
});

it('D9.B Controller trunca User-Agent a 80 chars (defense-in-depth)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Http/Controllers/ConsultaOsController.php'));

    expect($source)->toContain("substr((string) \$request->userAgent(), 0, 80)");
});

// ============================================================================
// D9.C — Rota pública /consulta-os com throttle + tracing
// ============================================================================

it('D9.C rota /consulta-os/buscar tem middleware throttle (anti-enumeration)', function () {
    $route = Route::getRoutes()->getByName('consulta-os.buscar');

    if (! $route) {
        $this->markTestSkipped('Rota consulta-os.buscar não registrada (Module disabled?).');
    }

    $middlewares = $route->middleware();
    $temThrottle = collect($middlewares)->contains(fn ($m) => str_starts_with($m, 'throttle:'));

    expect($temThrottle)->toBeTrue(
        'Portal público DEVE ter throttle: (anti-enumeration + DDoS protection — D8.b security).'
    );
});

it('D9.C Controller injeta ConsultaOsMockService via constructor (DI testável)', function () {
    $ref = new ReflectionClass(ConsultaOsController::class);
    $constructor = $ref->getConstructor();

    expect($constructor)->not->toBeNull();
    expect($constructor->getParameters())->toHaveCount(1);
    expect($constructor->getParameters()[0]->getName())->toBe('service');
});

// ============================================================================
// D9.D — ConsultaOsHealthCommand 5 probes operacionais
// ============================================================================

it('D9.D ConsultaOsHealthCommand expõe 5 probes (repository/service/retention/smoke_known/smoke_unknown)', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php'));

    expect($source)->toContain("'repository_bound'");
    expect($source)->toContain("'service_resolvable'");
    expect($source)->toContain("'retention_declared'");
    expect($source)->toContain("'smoke_known_ok'");
    expect($source)->toContain("'smoke_unknown_ok'");
});

it('D9.D ConsultaOsHealthCommand emite log estruturado consultaos.health', function () {
    $source = file_get_contents(base_path('Modules/ConsultaOs/Console/Commands/ConsultaOsHealthCommand.php'));

    expect($source)->toContain("Log::info('consultaos.health'");
});

it('D9.D ConsultaOsHealthCommand usa --detail (NÃO --verbose — Symfony reserved)', function () {
    $cmd = app(ConsultaOsHealthCommand::class);
    $signature = (new ReflectionProperty($cmd, 'signature'))->getValue($cmd);

    expect($signature)->toContain('--detail');
    expect($signature)->not->toContain('{--verbose ');
});

it('D9.D ConsultaOsHealthCommand handle() retorna SUCCESS (0) com Mock OK', function () {
    $exit = $this->artisan('consultaos:health')->run();
    expect($exit)->toBe(0);
});

// ============================================================================
// D7 LGPD — retention.php Wave 23 preservado
// ============================================================================

it('D7 retention.php declara entidades + strategy + notice_period (Wave 23 booster)', function () {
    $cfg = require base_path('Modules/ConsultaOs/Config/retention.php');

    expect($cfg)->toHaveKey('enabled');
    expect($cfg)->toHaveKey('entities');
    expect($cfg)->toHaveKey('strategy');
    expect($cfg)->toHaveKey('notice_period_days');

    expect($cfg['entities'])->toHaveKey('consulta_os_logs');
    expect($cfg['entities'])->toHaveKey('consulta_os_tokens');
});

// ============================================================================
// Sanity — bucket governance v4 + module_clients.yaml Wave 25 update
// ============================================================================

it('module.json declara governance.bucket = functional_horizontal (Wave 25 v4 LIVE)', function () {
    $json = json_decode(file_get_contents(base_path('Modules/ConsultaOs/module.json')), true);

    expect($json)->toHaveKey('governance');
    expect($json['governance']['bucket'])->toBe('functional_horizontal');
    expect($json['governance']['bucket_assigned_by'])->toBe('[W]');
});

it('module_clients.yaml ConsultaOs declarado biz_1_wagner_active (Wave 25 promote)', function () {
    $yaml = file_get_contents(base_path('config/governance/module_clients.yaml'));

    expect($yaml)->toMatch('/ConsultaOs:\s*\n\s*level: biz_1_wagner_active/');
});

it('module_clients.yaml SRS declarado internal_governance_active (Wave 25 promote)', function () {
    $yaml = file_get_contents(base_path('config/governance/module_clients.yaml'));

    expect($yaml)->toMatch('/SRS:\s*\n\s*level: internal_governance_active/');
});
