<?php

declare(strict_types=1);

/**
 * KbCodeGraphTest — Fase D (grafo de dependências do código).
 *
 * kb:code-graph lê os use-imports (AST) e materializa kb_edges
 * (references-data, generated_by=code_scan) entre os nós de código que o
 * kb:code-scan gerou. Cobre:
 *   - classe A que usa classe B (ambas escaneadas) → 1 aresta A→B
 *   - dependência EXTERNA (Illuminate\Support\Str) NÃO vira aresta
 *   - --dry-run não grava
 *   - sem nós (code-scan não rodou) → nenhuma aresta
 *   - idempotência (2 runs = 1 aresta)
 *   - multi-tenant Tier 0 (ADR 0093/0101)
 *
 * sqlite-safe: kbBootstrapSchema (cria kb_nodes+kb_edges) + php-parser das dev deps.
 */

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
    $dir = sys_get_temp_dir().'/kb-code-graph-test';
    if (is_dir($dir)) {
        foreach (glob($dir.'/*.php') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }
});

/** Monta um diretório com Alpha (usa Beta, externa) e Beta em namespaces distintos. */
function kbGraphFixtureDir(): string
{
    $dir = sys_get_temp_dir().'/kb-code-graph-test';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir.'/Alpha.php', <<<'PHP'
<?php
namespace App\Sample;

use App\Other\Beta;
use Illuminate\Support\Str;

class Alpha
{
    public function run(): string
    {
        (new Beta())->ok();

        return Str::slug('x');
    }
}
PHP);

    file_put_contents($dir.'/Beta.php', <<<'PHP'
<?php
namespace App\Other;

class Beta
{
    public function ok(): bool
    {
        return true;
    }
}
PHP);

    return $dir;
}

/** Roda scan (nós) + graph (arestas) pro business dado. */
function kbScanThenGraph(object $test, string $dir, int $biz): void
{
    $test->artisan('kb:code-scan', ['--path' => $dir, '--business-id' => $biz])->assertExitCode(0);
    $test->artisan('kb:code-graph', ['--path' => $dir, '--business-id' => $biz])->assertExitCode(0);
}

it('cria aresta de dependência A→B entre nós de código escaneados', function () {
    $dir = kbGraphFixtureDir();
    kbScanThenGraph($this, $dir, 1);

    $alpha = DB::table('kb_nodes')->where('business_id', 1)->where('title', 'App\\Sample\\Alpha')->value('id');
    $beta = DB::table('kb_nodes')->where('business_id', 1)->where('title', 'App\\Other\\Beta')->value('id');
    expect($alpha)->not->toBeNull();
    expect($beta)->not->toBeNull();

    $edge = DB::table('kb_edges')
        ->where('business_id', 1)
        ->where('from_node_id', $alpha)
        ->where('to_node_id', $beta)
        ->first();

    expect($edge)->not->toBeNull();
    expect($edge->edge_type)->toBe('references-data');
    expect($edge->generated_by)->toBe('code_scan');
});

it('dependência EXTERNA (Illuminate\\Support\\Str) NÃO vira aresta', function () {
    $dir = kbGraphFixtureDir();
    kbScanThenGraph($this, $dir, 1);

    // Só a aresta intra-conjunto Alpha→Beta; Str é externa (não é nó do projeto).
    expect(DB::table('kb_edges')->where('business_id', 1)->count())->toBe(1);
});

it('--dry-run não grava aresta', function () {
    $dir = kbGraphFixtureDir();
    $this->artisan('kb:code-scan', ['--path' => $dir, '--business-id' => 1])->assertExitCode(0);
    $this->artisan('kb:code-graph', ['--path' => $dir, '--business-id' => 1, '--dry-run' => true])->assertExitCode(0);

    expect(DB::table('kb_edges')->where('business_id', 1)->count())->toBe(0);
});

it('sem nós (code-scan não rodou antes) → nenhuma aresta', function () {
    $dir = kbGraphFixtureDir();
    $this->artisan('kb:code-graph', ['--path' => $dir, '--business-id' => 1])->assertExitCode(0);

    expect(DB::table('kb_edges')->where('business_id', 1)->count())->toBe(0);
});

it('é idempotente (2 runs = 1 aresta)', function () {
    $dir = kbGraphFixtureDir();
    kbScanThenGraph($this, $dir, 1);
    $this->artisan('kb:code-graph', ['--path' => $dir, '--business-id' => 1])->assertExitCode(0);

    expect(DB::table('kb_edges')->where('business_id', 1)->count())->toBe(1);
});

it('multi-tenant Tier 0: aresta no business informado, não no outro', function () {
    $dir = kbGraphFixtureDir();
    kbScanThenGraph($this, $dir, 99);

    expect(DB::table('kb_edges')->where('business_id', 99)->count())->toBe(1);
    expect(DB::table('kb_edges')->where('business_id', 1)->count())->toBe(0);
});
