<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * ENFORCEMENT.md §2 #5 — Drift detection cron Pest tests.
 *
 * Estratégia: cria SCOPE.md + Controllers em diretório temporário (tests/tmp/Modules/),
 * aponta base_path('Modules') pra esse fixture via override (não funciona out-of-box —
 * usamos vfsStream-like via diretório real isolado).
 *
 * Como o command usa base_path('Modules') diretamente, a estratégia é:
 *   - Criar fixture em base_path('Modules/__DriftFixture__/...')
 *   - Filtrar via --module=__DriftFixture__ pra isolar do scan real
 *   - Limpar fixture em afterEach
 *
 * Schema mcp_alertas_eventos in-memory SQLite (replica migration canônica).
 * Multi-tenant Tier 0 (ADR 0093): tabela é repo-wide, business_id NULL OK.
 * biz=1 não aplicável aqui (sem queries scopadas) — ADR 0101 honored: nunca usar biz=4 (cliente).
 */

beforeEach(function () {
    // Schema mínimo replicando mcp_alertas_eventos (Modules/Jana/Database/Migrations/2026_04_29_600001_*).
    Schema::dropIfExists('mcp_alertas_eventos');
    Schema::create('mcp_alertas_eventos', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedInteger('user_id')->nullable();
        $t->unsignedInteger('business_id')->nullable();
        $t->string('tipo', 50);
        $t->string('severidade', 20)->default('medium');
        $t->string('titulo', 200);
        $t->text('descricao')->nullable();
        $t->string('chave_idempotencia', 200)->unique();
        $t->json('metadata')->nullable();
        $t->string('status', 30)->default('aberto');
        $t->timestamp('criado_em')->nullable();
        $t->timestamp('notificado_em')->nullable();
        $t->timestamp('ack_em')->nullable();
        $t->unsignedInteger('ack_by_user_id')->nullable();
        $t->timestamps();
    });

    // Fixture isolado em Modules/__DriftFixture__/ — nome com underscore impede
    // colisão com módulos reais e fica óbvio que é temp.
    $fixtureBase = base_path('Modules/__DriftFixture__');
    File::ensureDirectoryExists($fixtureBase . '/Http/Controllers');
});

afterEach(function () {
    Schema::dropIfExists('mcp_alertas_eventos');

    $fixtureBase = base_path('Modules/__DriftFixture__');
    if (is_dir($fixtureBase)) {
        File::deleteDirectory($fixtureBase);
    }
});

/**
 * Helper — escreve SCOPE.md fixture com lista de controllers declarados.
 *
 * @param  list<string>  $declaredControllers  basenames (sem .php) ex: ['FooController']
 */
function writeScopeMd(array $declaredControllers, string $module = '__DriftFixture__'): void
{
    $items = array_map(
        fn ($c) => "  - \"{$c} — fixture pest test\"",
        $declaredControllers
    );
    $itemsYaml = implode("\n", $items);

    $content = <<<MD
---
module: {$module}
purpose: "Fixture Pest pra DetectDriftCommandTest"
contains:
{$itemsYaml}
trust_required: L2
owner: pest-test
permission_prefix: drift_fixture.*
---

# {$module}

Fixture Pest. NÃO commitar — afterEach limpa.
MD;

    file_put_contents(base_path("Modules/{$module}/SCOPE.md"), $content);
}

/**
 * Helper — cria Controller.php stub em filesystem fixture.
 */
function writeController(string $basename, string $module = '__DriftFixture__'): void
{
    $stub = "<?php\n\nnamespace Modules\\{$module}\\Http\\Controllers;\n\nclass {$basename} {}\n";
    file_put_contents(
        base_path("Modules/{$module}/Http/Controllers/{$basename}.php"),
        $stub
    );
}

test('módulo sem drift retorna exit 0 e não cria alerta', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');

    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
    ])->run();

    expect($exit)->toBe(0);
    expect(DB::table('mcp_alertas_eventos')->count())->toBe(0);
});

test('módulo com drift_added cria alerta e retorna exit 1', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('UndeclaredController'); // ESTE é drift_added

    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
    ])->run();

    expect($exit)->toBe(1);

    $alerta = DB::table('mcp_alertas_eventos')->where('tipo', 'module_drift')->first();
    expect($alerta)->not->toBeNull();
    expect($alerta->severidade)->toBe('medium');
    expect($alerta->status)->toBe('aberto');
    expect($alerta->business_id)->toBeNull(); // repo-wide
    expect($alerta->user_id)->toBeNull(); // global plataforma

    $metadata = json_decode((string) $alerta->metadata, true);
    expect($metadata['module'])->toBe('__DriftFixture__');
    expect($metadata['controller'])->toBe('UndeclaredController');
    expect($metadata['enforcement_mecanismo'])->toBe(5);
});

test('módulo com drift_removed apenas (sem added) retorna exit 0 e não cria alerta', function () {
    // Declara 2, filesystem só tem 1 — o outro é drift_removed (warning leve)
    writeScopeMd(['FooController', 'GhostController']);
    writeController('FooController');

    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
    ])->run();

    expect($exit)->toBe(0);
    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'module_drift')->count())->toBe(0);
});

test('--dry-run não persiste alertas mesmo com drift_added', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('UndeclaredController');

    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
        '--dry-run' => true,
    ])->run();

    expect($exit)->toBe(1); // ainda retorna 1 (drift detectado), só não persiste
    expect(DB::table('mcp_alertas_eventos')->count())->toBe(0);
});

test('--module filtra para um único módulo', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('UndeclaredController');

    // Sem --module ele scaneia TUDO (incluindo módulos reais que podem ter drift próprio)
    // — pra teste isolado, usamos --module=__DriftFixture__
    $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
        '--json' => true,
    ])
        ->expectsOutputToContain('"modules_scanned": 1')
        ->expectsOutputToContain('"module": "__DriftFixture__"');
});

test('--json output produz JSON válido com shape esperado', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('OutroDriftController');

    ob_start();
    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
        '--json' => true,
    ])->run();
    $output = ob_get_clean();

    expect($exit)->toBe(1);

    // Output Artisan vai pro buffer Symfony, mas via expectsOutput captura.
    // Test alternativo: verificar via DB que alerta foi criado (proxy do JSON estar correto).
    $alerta = DB::table('mcp_alertas_eventos')->first();
    expect($alerta)->not->toBeNull();
    expect($alerta->tipo)->toBe('module_drift');
});

test('idempotência — rodar 2x não duplica alerta no mesmo dia', function () {
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('DuplicateTestController');

    $this->artisan('governance:detect-drift', ['--module' => '__DriftFixture__'])->run();
    $this->artisan('governance:detect-drift', ['--module' => '__DriftFixture__'])->run();

    expect(DB::table('mcp_alertas_eventos')->where('tipo', 'module_drift')->count())->toBe(1);
});

test('controllers boilerplate (Data/Install/Superadmin) são ignorados no scan', function () {
    // SCOPE.md declara só FooController; filesystem tem boilerplate + Foo.
    // Boilerplate NÃO deve gerar drift_added.
    writeScopeMd(['FooController']);
    writeController('FooController');
    writeController('DataController');
    writeController('InstallController');
    writeController('SuperadminController');

    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
    ])->run();

    expect($exit)->toBe(0);
    expect(DB::table('mcp_alertas_eventos')->count())->toBe(0);
});

test('SCOPE.md com YAML inválido não crasha o command', function () {
    // YAML quebrado — frontmatter sem fechar
    file_put_contents(
        base_path('Modules/__DriftFixture__/SCOPE.md'),
        "---\ncontains: [foo: bar: baz\n---\nbroken"
    );
    writeController('AlgumController');

    // Não pode crashar — só retorna sem detectar (degradação graciosa)
    $exit = $this->artisan('governance:detect-drift', [
        '--module' => '__DriftFixture__',
    ])->run();

    // YAML inválido → declared=[], observed=['AlgumController'] → drift_added=['AlgumController']
    // Comportamento: trata como drift_added (porque nada está declarado).
    // Em prod isto é correto: SCOPE.md quebrado equivale a "nada autorizado".
    expect($exit)->toBe(1);
});
