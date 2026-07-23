<?php

declare(strict_types=1);

/**
 * KbCodeScanTest — Fase B (ADR 0350).
 *
 * kb:code-scan lê a estrutura de um arquivo PHP (AST via nikic/php-parser) e
 * gera/atualiza um KbNode type=reference idempotente. Cobre:
 *   - gera nó com FQCN, resumo do docblock e lista de métodos públicos
 *   - ignora __construct e métodos privados
 *   - idempotência por (business_id, slug)
 *   - --dry-run não grava
 *   - multi-tenant Tier 0: grava no business informado (ADR 0093/0101)
 *
 * sqlite-safe: usa kbBootstrapSchema + DB::table; php-parser vem das dev deps.
 */

use Illuminate\Support\Facades\DB;

beforeEach(function () {
    kbBootstrapSchema();
    kbCreateBusinessRow(1);
    kbCreateBusinessRow(99);
});

afterEach(function () {
    kbTeardownSchema();
    $dir = sys_get_temp_dir().'/kb-code-scan-test';
    if (is_dir($dir)) {
        foreach (glob($dir.'/*.php') ?: [] as $f) {
            @unlink($f);
        }
    }
});

/** Escreve um .php temporário e devolve o caminho. */
function kbWriteTempPhp(string $code): string
{
    $dir = sys_get_temp_dir().'/kb-code-scan-test';
    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    $file = $dir.'/Sample'.uniqid().'.php';
    file_put_contents($file, $code);

    return $file;
}

function kbSampleClassCode(): string
{
    return <<<'PHP'
<?php
namespace App\Sample;

/**
 * Serviço de exemplo pro teste.
 *
 * @internal
 */
class WidgetService
{
    public function __construct() {}

    /** Faz a coisa principal. */
    public function doThing(): void {}

    public function outra(): int { return 1; }

    private function secreto(): void {}
}
PHP;
}

it('gera KbNode reference a partir de uma classe PHP', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1])
        ->assertExitCode(0);

    $node = DB::table('kb_nodes')
        ->where('business_id', 1)
        ->where('title', 'App\\Sample\\WidgetService')
        ->first();

    expect($node)->not->toBeNull();
    expect($node->type)->toBe('reference');
    expect((string) $node->slug)->toStartWith('code-');
    expect((string) $node->excerpt)->toContain('Serviço de exemplo');

    $blocks = json_decode((string) $node->body_blocks, true);
    $list = collect($blocks)->firstWhere('kind', 'list');
    $itemsStr = implode(' | ', $list['items'] ?? []);

    expect($itemsStr)->toContain('doThing()');
    expect($itemsStr)->toContain('Faz a coisa principal');
    expect($itemsStr)->toContain('outra()');
    // __construct e privados NÃO entram
    expect($itemsStr)->not->toContain('secreto');
    expect($itemsStr)->not->toContain('__construct');
});

it('é idempotente por (business_id, slug)', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1])->assertExitCode(0);
    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1])->assertExitCode(0);

    $count = DB::table('kb_nodes')
        ->where('business_id', 1)
        ->where('title', 'App\\Sample\\WidgetService')
        ->count();

    expect($count)->toBe(1);
});

it('--dry-run não grava nada', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1, '--dry-run' => true])
        ->assertExitCode(0);

    expect(DB::table('kb_nodes')->where('business_id', 1)->count())->toBe(0);
});

it('multi-tenant Tier 0: grava no business informado, não no outro', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 99])->assertExitCode(0);

    expect(DB::table('kb_nodes')->where('business_id', 99)->where('title', 'App\\Sample\\WidgetService')->exists())->toBeTrue();
    expect(DB::table('kb_nodes')->where('business_id', 1)->where('title', 'App\\Sample\\WidgetService')->exists())->toBeFalse();
});

it('exige --business-id (Tier 0)', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file])->assertExitCode(1);
});

// ─── Fase C — multi-repo consciente (--project namespaça o slug) ───

it('multi-repo: mesma FQCN em 2 projetos NÃO colide (namespace por --project)', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1, '--project' => 'repo-a'])->assertExitCode(0);
    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1, '--project' => 'repo-b'])->assertExitCode(0);

    // 2 nós distintos pra MESMA FQCN — um por projeto, sem sobrescrever.
    $nodes = DB::table('kb_nodes')->where('business_id', 1)->where('title', 'App\\Sample\\WidgetService')->get();
    expect($nodes)->toHaveCount(2);

    $slugs = $nodes->pluck('slug')->all();
    expect($slugs[0])->not->toBe($slugs[1]);
    expect(collect($slugs)->every(fn ($s) => str_starts_with($s, 'code-repo-')))->toBeTrue();

    // tag de projeto gravada em cada nó.
    $nodeA = $nodes->first(fn ($n) => str_starts_with($n->slug, 'code-repo-a'));
    expect(json_decode((string) $nodeA->tags, true))->toContain('projeto:repo-a');
});

it('sem --project mantém o slug legado code-{fqcn} (back-compat)', function () {
    $file = kbWriteTempPhp(kbSampleClassCode());

    $this->artisan('kb:code-scan', ['--path' => $file, '--business-id' => 1])->assertExitCode(0);

    $slug = DB::table('kb_nodes')->where('business_id', 1)->where('title', 'App\\Sample\\WidgetService')->value('slug');
    expect((string) $slug)->toStartWith('code-app-sample-widgetservice');
});
