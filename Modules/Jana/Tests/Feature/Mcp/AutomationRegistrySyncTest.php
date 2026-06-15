<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Mcp\Request as McpRequest;
use Modules\Jana\Entities\Mcp\McpAutomation;
use Modules\Jana\Mcp\Tools\AutomationsListTool;
use Modules\Jana\Services\Mcp\AutomationRegistrySync;

uses(Tests\TestCase::class);

/**
 * ADR 0234 (Onda 1.1) — Registry de Automações.
 *
 * Cobre: (a) sync faz upsert de hooks/crons/rotinas; (b) drift detectado
 * (orphan_file + missing_file); (c) automations-list retorna estado + filtros;
 * (d) idempotência (rodar 2× não duplica).
 *
 * Multi-tenant: mcp_automations é GLOBAL by-design (business_id NULL = infra de
 * plataforma, ADR 0093 exceção, igual mcp_skills / mcp_governance_rules). O
 * registry NUNCA lê dados de tenant — só varre arquivos do repo. Por isso NÃO
 * há teste cross-tenant: não existe scope de business pra vazar. As rows são
 * criadas sempre com business_id=null e a tool não filtra por business.
 *
 * Os coletores são apontados pra um diretório fixture (comRepoBasePath) pra
 * controlar drift de forma determinística — mesmo precedente de
 * StalenessDetectorService::comRepoBasePath. SQLite :memory: (phpunit.xml),
 * enums viram string (SQLite não suporta ENUM).
 */
beforeEach(function () {
    // era-sqlite: cria schema mcp_*/jana_* manual (sqlite-friendly). No MySQL persistente
    // do nightly isso corrompe os testes irmãos (lever do floor SDD). Cobertura real é
    // na lane sqlite (per-PR); pula no MySQL.
    if (config('database.default') !== 'sqlite') {
        $this->markTestSkipped('era-sqlite: corruptor de schema compartilhado no MySQL — sqlite-only no burn-down do floor SDD.');
    }

    // ── Schema mínimo (replica as migrations; enum→string no SQLite). ──
    Schema::dropIfExists('mcp_automation_runs');
    Schema::dropIfExists('mcp_automations');
    Schema::dropIfExists('mcp_alertas_eventos');

    Schema::create('mcp_automations', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('slug', 100)->unique();
        $t->unsignedBigInteger('business_id')->nullable();
        $t->string('tipo', 30);
        $t->string('gatilho', 255);
        $t->text('descricao')->nullable();
        $t->string('arquivo', 300);
        $t->string('owner', 100)->nullable();
        $t->string('governed_by_adr', 100)->nullable();
        $t->boolean('enabled')->default(true);
        $t->timestamp('last_run_at')->nullable();
        $t->string('last_status', 10)->nullable();
        $t->text('last_detail')->nullable();
        $t->timestamps();
    });

    Schema::create('mcp_automation_runs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('automation_id');
        $t->timestamp('ran_at');
        $t->string('status', 10);
        $t->text('detail')->nullable();
        $t->string('actor', 100)->nullable();
        $t->timestamps();
    });

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
        $t->string('status', 20)->default('aberto');
        $t->timestamp('criado_em')->nullable();
        $t->timestamp('notificado_em')->nullable();
        $t->timestamp('ack_em')->nullable();
        $t->unsignedInteger('ack_by_user_id')->nullable();
        $t->timestamps();
    });

    // ── Fixture filesystem: um repo de mentira com as 3 classes de fonte. ──
    $this->fixtureRoot = sys_get_temp_dir() . '/automation-registry-test-' . uniqid();
    mkfix($this->fixtureRoot . '/.claude/hooks');
    mkfix($this->fixtureRoot . '/app/Console');

    // (a) hooks — 1 PreToolUse (block) + 1 SessionStart + 1 PostToolUse (.mjs) + 1 .test (ignorado)
    filefix($this->fixtureRoot . '/.claude/hooks/block-fake.ps1', "# block-fake - bloqueador de teste (ADR 0234)\nexit 0\n");
    filefix($this->fixtureRoot . '/.claude/hooks/banner-fake.ps1', "# banner-fake - banner SessionStart de teste\nWrite-Host 'oi'\n");
    filefix($this->fixtureRoot . '/.claude/hooks/audit-fake.mjs', "// audit-fake - hook node PostToolUse de teste\nprocess.exit(0)\n");
    filefix($this->fixtureRoot . '/.claude/hooks/block-fake.test.ps1', "# teste do block-fake (NAO deve ser coletado)\n");

    // settings.json mapeia os hooks pros eventos
    filefix($this->fixtureRoot . '/.claude/settings.json', json_encode([
        'hooks' => [
            'PreToolUse' => [[
                'matcher' => 'Write|Edit',
                'hooks'   => [['type' => 'command', 'command' => 'powershell -File .claude/hooks/block-fake.ps1']],
            ]],
            'SessionStart' => [[
                'matcher' => '*',
                'hooks'   => [['type' => 'command', 'command' => 'powershell -File .claude/hooks/banner-fake.ps1']],
            ]],
            'PostToolUse' => [[
                'matcher' => 'Write|Edit',
                'hooks'   => [['type' => 'command', 'command' => 'node .claude/hooks/audit-fake.mjs']],
            ]],
        ],
    ], JSON_PRETTY_PRINT));

    // (b) crons — Kernel.php de mentira com 2 schedules + 1 closure (->call, ignorado)
    filefix($this->fixtureRoot . '/app/Console/Kernel.php', <<<'PHP'
<?php
class Kernel {
    protected function schedule($schedule) {
        $schedule->command('fake:health-check --notify')->dailyAt('06:00');
        $schedule->command('fake:sync')->everyFiveMinutes();
        $schedule->call(function () { /* closure sem slug */ })->hourly();
    }
}
PHP);

    // (c) rotina — manifesto com marcador _automation_registry + check existente
    filefix($this->fixtureRoot . '/.claude/hooks/loop-fake-check.ps1', "# loop-fake-check - rotina de teste\n");
    filefix($this->fixtureRoot . '/.claude/loop-fake.json', json_encode([
        '_automation_registry' => true,
        'slug'                 => 'loop-fake',
        'tipo'                 => 'routine',
        'gatilho'              => 'SessionStart pos brief-fetch',
        'descricao'            => 'Rotina fake de teste',
        'arquivo'              => '.claude/hooks/loop-fake-check.ps1',
        'owner'                => 'wagner',
        'governed_by_adr'      => 'automation-registry-mcp',
        'enabled'              => true,
    ], JSON_PRETTY_PRINT));
});

afterEach(function () {
    if (config('database.default') !== 'sqlite') {
        return;
    }

    Schema::dropIfExists('mcp_automation_runs');
    Schema::dropIfExists('mcp_automations');
    Schema::dropIfExists('mcp_alertas_eventos');

    if (isset($this->fixtureRoot) && is_dir($this->fixtureRoot)) {
        rmfix($this->fixtureRoot);
    }
});

// ── helpers de fixture filesystem ──
function mkfix(string $dir): void
{
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
}
function filefix(string $path, string $content): void
{
    mkfix(dirname($path));
    file_put_contents($path, $content);
}
function rmfix(string $dir): void
{
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') {
            continue;
        }
        $p = $dir . '/' . $f;
        is_dir($p) ? rmfix($p) : @unlink($p);
    }
    @rmdir($dir);
}
function sync(): array
{
    return (new AutomationRegistrySync())
        ->comRepoBasePath(test()->fixtureRoot)
        ->run();
}

// ───────────────────────────── (a) UPSERT ─────────────────────────────

test('(a) sync faz upsert de hooks, crons e rotinas', function () {
    $r = sync();

    // Hooks coletados (.ps1/.mjs em .claude/hooks, exceto *.test.*):
    //   block-fake, banner-fake, audit-fake, loop-fake-check = 4
    //   (loop-fake-check.ps1 é o check da rotina; como vive em .claude/hooks/
    //    também entra como hook — slug distinto do manifesto 'loop-fake', sem colisão).
    // Crons: fake:health-check + fake:sync = 2 (closure ->call ignorada).
    // Rotina: loop-fake (do manifesto) = 1.
    // Total = 4 + 2 + 1 = 7.
    expect(McpAutomation::count())->toBe(7)
        ->and($r['created'])->toBe(7)
        ->and($r['updated'])->toBe(0);

    // o check da rotina é coletado como hook (slug próprio), sem colidir com a rotina
    expect(McpAutomation::where('slug', 'loop-fake-check')->exists())->toBeTrue()
        ->and(McpAutomation::where('slug', 'loop-fake')->first()->tipo)->toBe('routine');

    // hook PreToolUse com tipo + gatilho inferidos do settings.json
    $block = McpAutomation::where('slug', 'block-fake')->first();
    expect($block)->not->toBeNull()
        ->and($block->tipo)->toBe('hook_pretooluse')
        ->and($block->gatilho)->toContain('PreToolUse')
        ->and($block->arquivo)->toBe('.claude/hooks/block-fake.ps1')
        ->and($block->governed_by_adr)->toBe('0234'); // extraído do header "ADR 0234"

    // hook SessionStart
    expect(McpAutomation::where('slug', 'banner-fake')->first()->tipo)->toBe('hook_sessionstart');

    // hook .mjs PostToolUse
    expect(McpAutomation::where('slug', 'audit-fake')->first()->tipo)->toBe('hook_posttooluse');

    // .test.ps1 NÃO virou automação
    expect(McpAutomation::where('slug', 'block-fake.test')->exists())->toBeFalse();

    // crons: slug = comando base, gatilho resolvido
    $cron = McpAutomation::where('slug', 'fake:health-check')->first();
    expect($cron)->not->toBeNull()
        ->and($cron->tipo)->toBe('cron')
        ->and($cron->gatilho)->toBe('dailyAt 06:00')
        ->and($cron->arquivo)->toBe('app/Console/Kernel.php');
    expect(McpAutomation::where('slug', 'fake:sync')->first()->gatilho)->toBe('a cada 5 min');

    // closure ->call() sem slug NÃO vira automação (não há 7ª row de cron)
    expect(McpAutomation::where('tipo', 'cron')->count())->toBe(2);

    // rotina lida do manifesto
    $rot = McpAutomation::where('slug', 'loop-fake')->first();
    expect($rot)->not->toBeNull()
        ->and($rot->tipo)->toBe('routine')
        ->and($rot->owner)->toBe('wagner')
        ->and($rot->governed_by_adr)->toBe('automation-registry-mcp');
});

// ──────────────────────────── (d) IDEMPOTÊNCIA ────────────────────────────

test('(d) rodar sync 2x não duplica (idempotente)', function () {
    sync();
    $apos1 = McpAutomation::count();

    $r2 = sync();
    $apos2 = McpAutomation::count();

    expect($apos2)->toBe($apos1)
        ->and($r2['created'])->toBe(0)
        ->and($r2['unchanged'])->toBe($apos1); // tudo inalterado na 2ª passada
});

test('(d) sync detecta mudança de campo descritivo (update, não duplicata)', function () {
    sync();
    $count = McpAutomation::count();

    // altera o gatilho do manifesto da rotina
    filefix(test()->fixtureRoot . '/.claude/loop-fake.json', json_encode([
        '_automation_registry' => true,
        'slug'                 => 'loop-fake',
        'tipo'                 => 'routine',
        'gatilho'              => 'GATILHO MUDADO',
        'arquivo'              => '.claude/hooks/loop-fake-check.ps1',
    ]));

    $r = sync();

    expect(McpAutomation::count())->toBe($count) // não duplicou
        ->and($r['updated'])->toBe(1)
        ->and(McpAutomation::where('slug', 'loop-fake')->first()->gatilho)->toBe('GATILHO MUDADO');
});

// ───────────────────────────── (b) DRIFT ─────────────────────────────

test('(b) drift missing_file: arquivo da rotina sumiu → alerta high', function () {
    sync(); // popula tudo, incluindo loop-fake (arquivo presente)

    // remove o check da rotina do filesystem (zumbi)
    @unlink(test()->fixtureRoot . '/.claude/hooks/loop-fake-check.ps1');

    // 2ª passada: o coletor de rotinas ainda lê o manifesto (que aponta pro
    // arquivo), mas o detector de ausentes vê que o arquivo sumiu do disco.
    $r = sync();

    expect($r['missing_files'])->toContain('.claude/hooks/loop-fake-check.ps1')
        ->and($r['alerts_created'])->toBeGreaterThanOrEqual(1);

    $alerta = DB::table('mcp_alertas_eventos')
        ->where('tipo', 'automation_drift')
        ->where('severidade', 'high')
        ->first();
    expect($alerta)->not->toBeNull()
        ->and($alerta->business_id)->toBeNull() // registry global
        ->and($alerta->titulo)->toContain('loop-fake');
});

test('(b) drift orphan_file: slug no DB sem fonte no FS → alerta medium', function () {
    // cria uma automação zumbi que nenhuma fonte do fixture reproduz
    McpAutomation::create([
        'slug'    => 'orfa-zumbi',
        'tipo'    => 'cron',
        'gatilho' => 'dailyAt 09:00',
        'arquivo' => 'app/Console/Kernel.php',
        'enabled' => true,
    ]);

    $r = sync();

    expect($r['orphan_files'])->toContain('orfa-zumbi');

    $alerta = DB::table('mcp_alertas_eventos')
        ->where('tipo', 'automation_drift')
        ->where('severidade', 'medium')
        ->first();
    expect($alerta)->not->toBeNull()
        ->and($alerta->titulo)->toContain('orfa-zumbi');
});

test('(b) drift alerta é idempotente no mesmo dia (chave_idempotencia)', function () {
    McpAutomation::create([
        'slug'    => 'orfa-zumbi',
        'tipo'    => 'cron',
        'gatilho' => 'dailyAt 09:00',
        'arquivo' => 'app/Console/Kernel.php',
        'enabled' => true,
    ]);

    sync();
    $apos1 = DB::table('mcp_alertas_eventos')->where('tipo', 'automation_drift')->count();

    sync(); // mesma execução no mesmo dia → não duplica alerta
    $apos2 = DB::table('mcp_alertas_eventos')->where('tipo', 'automation_drift')->count();

    expect($apos2)->toBe($apos1);
});

// ────────────────────────── (c) automations-list ──────────────────────────

test('(c) automations-list retorna automações com estado', function () {
    // seed direto no DB (a tool roda contra base_path() real, não o fixture)
    McpAutomation::create([
        'slug'            => 'lista-cron-fake',
        'tipo'            => 'cron',
        'gatilho'        => 'dailyAt 06:00',
        'arquivo'         => 'app/Console/Kernel.php',
        'enabled'         => true,
        'last_status'     => 'ok',
        'last_run_at'     => now(),
        'governed_by_adr' => '0234',
    ]);

    $tool = new AutomationsListTool();
    $out = (string) $tool->handle(new McpRequest([]))->content();

    expect($out)->toContain('lista-cron-fake')
        ->and($out)->toContain('cron')
        ->and($out)->toContain('ADR 0234')
        ->and($out)->toContain('last_status: ok');
});

test('(c) automations-list filtra por tipo', function () {
    McpAutomation::create(['slug' => 'um-cron', 'tipo' => 'cron', 'gatilho' => 'x', 'arquivo' => 'app/Console/Kernel.php', 'enabled' => true]);
    McpAutomation::create(['slug' => 'um-hook', 'tipo' => 'hook_pretooluse', 'gatilho' => 'y', 'arquivo' => 'app/Console/Kernel.php', 'enabled' => true]);

    $tool = new AutomationsListTool();
    $out = (string) $tool->handle(new McpRequest(['tipo' => 'cron']))->content();

    expect($out)->toContain('um-cron')
        ->and($out)->not->toContain('um-hook');
});

test('(c) automations-list filtra por enabled=false', function () {
    // slugs sem relação de substring (evita falso-match "ligada" ⊂ "desligada")
    McpAutomation::create(['slug' => 'auto-on-xyz', 'tipo' => 'cron', 'gatilho' => 'x', 'arquivo' => 'app/Console/Kernel.php', 'enabled' => true]);
    McpAutomation::create(['slug' => 'auto-off-abc', 'tipo' => 'cron', 'gatilho' => 'y', 'arquivo' => 'app/Console/Kernel.php', 'enabled' => false]);

    $tool = new AutomationsListTool();
    $out = (string) $tool->handle(new McpRequest(['enabled' => false]))->content();

    expect($out)->toContain('auto-off-abc')
        ->and($out)->not->toContain('auto-on-xyz');
});

test('(c) automations-list filtra drift=missing_file (zumbi)', function () {
    // arquivo inexistente → drift missing_file resolvido pela tool
    McpAutomation::create([
        'slug'    => 'zumbi-list',
        'tipo'    => 'routine',
        'gatilho' => 'x',
        'arquivo' => '.claude/hooks/nao-existe-' . uniqid() . '.ps1',
        'enabled' => true,
    ]);
    // automação saudável (arquivo cron sempre existe)
    McpAutomation::create([
        'slug'    => 'saudavel-list',
        'tipo'    => 'cron',
        'gatilho' => 'x',
        'arquivo' => 'app/Console/Kernel.php',
        'enabled' => true,
    ]);

    $tool = new AutomationsListTool();
    $out = (string) $tool->handle(new McpRequest(['drift' => 'missing_file']))->content();

    expect($out)->toContain('zumbi-list')
        ->and($out)->not->toContain('saudavel-list');
});

test('(c) automations-list vazio retorna mensagem amigável', function () {
    $tool = new AutomationsListTool();
    $out = (string) $tool->handle(new McpRequest([]))->content();

    expect($out)->toContain('Nenhuma automação encontrada');
});

// ───────────────────── multi-tenant: global by-design ─────────────────────

test('automações são criadas SEM business_id (registry global de infra)', function () {
    sync();

    expect(McpAutomation::whereNotNull('business_id')->count())->toBe(0)
        ->and(McpAutomation::count())->toBeGreaterThan(0);
});
