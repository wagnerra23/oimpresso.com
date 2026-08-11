<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

/**
 * ONDA 5 S1 — Schema rígido CI híbrido.
 *
 * Testes Pest do artisan `jana:validate-memory`. Cria fixtures temporárias
 * em diretório staging, roda command com --path apontando pra eles, asserta:
 *   - frontmatter válido passa
 *   - frontmatter faltando required field falha
 *   - frontmatter com tipo errado falha
 *   - flag --strict bloqueia (exit 1)
 *   - sem --strict (grace period) emite warning mas exit 0
 *   - --schema=adr filtra só ADR
 *
 * NOTA: usa diretório fixture dentro do repo (gitignored seria ideal mas pra Pest funcionar
 * em CI usamos base_path tmp + cleanup tearDown).
 */

beforeEach(function () {
    $this->stagingDir = base_path('storage/framework/testing/memory-schema-stage');
    $this->adrDir = $this->stagingDir . '/memory/decisions';
    File::ensureDirectoryExists($this->adrDir);
});

afterEach(function () {
    if (File::isDirectory($this->stagingDir)) {
        File::deleteDirectory($this->stagingDir);
    }
});

/**
 * Helper — cria .md de teste num path relativo ao repo root,
 * dado o nome (4-digit prefix) + frontmatter array + corpo.
 */
function writeAdrFixture(string $dir, string $filename, ?array $frontmatter, string $body): string
{
    $yaml = '';
    if ($frontmatter !== null) {
        $yaml = "---\n";
        foreach ($frontmatter as $k => $v) {
            if (is_array($v)) {
                $yaml .= "{$k}:\n";
                foreach ($v as $item) {
                    $yaml .= "  - " . (is_string($item) ? "\"{$item}\"" : json_encode($item)) . "\n";
                }
            } else {
                $val = is_string($v) ? "\"{$v}\"" : json_encode($v);
                $yaml .= "{$k}: {$val}\n";
            }
        }
        $yaml .= "---\n";
    }
    $path = $dir . '/' . $filename;
    File::put($path, $yaml . "\n" . $body);
    return $path;
}

it('comando registrado em artisan list', function () {
    $output = Artisan::call('list');
    $rendered = Artisan::output();
    expect($rendered)->toContain('jana:validate-memory');
});

it('schemas canônicos existem em scripts/memory-schemas/', function () {
    foreach (['adr', 'spec', 'runbook', 'session', 'handoff', 'charter'] as $type) {
        $path = base_path("scripts/memory-schemas/{$type}.schema.json");
        expect(File::exists($path))->toBeTrue("Schema {$type}.schema.json deve existir em scripts/memory-schemas/");

        $json = json_decode(File::get($path), true);
        expect($json)->toBeArray()
            ->and($json['$schema'] ?? null)->toContain('json-schema.org/draft/2020-12');
    }
});

it('aceita ADR válido com todos campos required', function () {
    writeAdrFixture($this->adrDir, '0099-test-valido.md', [
        'slug' => '0099-test-valido',
        'number' => 99,
        'title' => 'ADR de teste válido com todos os campos required',
        'type' => 'adr',
        'status' => 'aceito',
        'authority' => 'canonical',
        'lifecycle' => 'ativo',
        'decided_by' => ['W'],
        'decided_at' => '2026-05-13',
    ], "# ADR teste\n\nCorpo válido.");

    $exitCode = Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
        '--json' => true,
    ]);

    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($exitCode)->toBe(0)
        ->and($result['buckets']['adr']['errors_count'])->toBe(0)
        ->and($result['buckets']['adr']['files_count'])->toBe(1);
});

it('detecta ADR com required field faltando', function () {
    // Falta `lifecycle` (required)
    writeAdrFixture($this->adrDir, '0098-test-faltando-field.md', [
        'slug' => '0098-test-faltando-field',
        'number' => 98,
        'title' => 'ADR sem lifecycle (faltando required)',
        'type' => 'adr',
        'status' => 'aceito',
        'authority' => 'canonical',
        // 'lifecycle' AUSENTE — deve falhar
        'decided_by' => ['W'],
        'decided_at' => '2026-05-13',
    ], "# corpo");

    Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
        '--strict' => true,
        '--json' => true,
    ]);

    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($result['buckets']['adr']['errors_count'])->toBe(1)
        ->and($result['total_errors'])->toBe(1);
});

it('detecta ADR com type enum errado', function () {
    // status com valor fora do enum
    writeAdrFixture($this->adrDir, '0097-status-invalido.md', [
        'slug' => '0097-status-invalido',
        'number' => 97,
        'title' => 'ADR com status fora do enum',
        'type' => 'adr',
        'status' => 'COMPLETED_WRONG', // INVÁLIDO — enum: rascunho/proposto/aceito/deprecated/superseded
        'authority' => 'canonical',
        'lifecycle' => 'ativo',
        'decided_by' => ['W'],
        'decided_at' => '2026-05-13',
    ], "# corpo");

    Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
        '--strict' => true,
        '--json' => true,
    ]);

    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($result['buckets']['adr']['errors_count'])->toBeGreaterThan(0);
});

it('flag --strict retorna exit 1 quando há violação', function () {
    writeAdrFixture($this->adrDir, '0096-invalido.md', [
        'slug' => '0096-invalido',
        // missing required fields
    ], "# corpo");

    $exitCode = Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
        '--strict' => true,
    ]);

    expect($exitCode)->toBe(1);
});

it('grace period (sem --strict) retorna exit 0 mesmo com violação', function () {
    writeAdrFixture($this->adrDir, '0095-invalido-grace.md', [
        'slug' => '0095-invalido-grace',
        // missing required fields
    ], "# corpo");

    $exitCode = Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
    ]);

    expect($exitCode)->toBe(0);
});

it('arquivo sem frontmatter conta como warning, não erro', function () {
    File::put($this->adrDir . '/0094-sem-frontmatter.md', "# ADR legacy\n\nSem frontmatter.");

    Artisan::call('jana:validate-memory', [
        '--schema' => 'adr',
        '--path' => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $this->adrDir),
        '--json' => true,
    ]);

    $output = Artisan::output();
    $result = json_decode($output, true);

    expect($result['buckets']['adr']['warnings_count'])->toBeGreaterThanOrEqual(1)
        ->and($result['buckets']['adr']['errors_count'])->toBe(0);
});

it('roda sem throwar quando schema desconhecido é solicitado', function () {
    $exitCode = Artisan::call('jana:validate-memory', [
        '--schema' => 'tipo_que_nao_existe',
    ]);

    expect($exitCode)->toBe(1); // erro controlado, não exception
});

it('expõe exatamente as famílias declaradas em familias.json quando rodado sem --schema', function () {
    // O nome antigo ("todos os 6 buckets") já mentia antes de 2026-08-11: a classe tinha
    // 7 famílias e o assert listava 6 — `toHaveKeys` é presença, não exaustividade, então
    // a 7ª sumir passaria batido. Agora o esperado é DERIVADO de familias.json (a fonte
    // única compartilhada com scripts/memory-schemas/validate.mjs): se o comando parar de
    // enxergar uma família — ou ganhar uma que o JSON não declara — este teste cai.
    // Path filter pra não varrer repo inteiro (caro em CI).
    $exitCode = Artisan::call('jana:validate-memory', [
        '--path' => 'storage/framework/testing/memory-schema-stage', // pasta vazia → 0 files todos buckets
        '--json' => true,
    ]);

    $output = Artisan::output();
    $result = json_decode($output, true);

    $esperadas = array_column(
        json_decode(file_get_contents(base_path('scripts/memory-schemas/familias.json')), true)['familias'],
        'key'
    );
    sort($esperadas);
    $obtidas = array_keys($result['buckets']);
    sort($obtidas);

    expect($exitCode)->toBe(0)
        ->and($esperadas)->not->toBeEmpty()
        ->and($obtidas)->toBe($esperadas);
});

it('marca como grace exatamente as famílias com grace:true em familias.json', function () {
    // Espelha o `grace: true` da matriz do memory-schema-gate.yml (hoje briefing +
    // reference). Sem isto, herdar as 2 famílias novas faria `--strict` bloquear sobre
    // legado que o CI deixa passar de propósito (ADR 0314).
    Artisan::call('jana:validate-memory', [
        '--path' => 'storage/framework/testing/memory-schema-stage',
        '--json' => true,
    ]);

    $result = json_decode(Artisan::output(), true);

    $familias = json_decode(file_get_contents(base_path('scripts/memory-schemas/familias.json')), true)['familias'];
    $esperadas = array_column(array_filter($familias, fn ($f) => ($f['grace'] ?? false) === true), 'key');
    sort($esperadas);

    $obtidas = array_keys(array_filter($result['buckets'], fn ($b) => ($b['grace'] ?? false) === true));
    sort($obtidas);

    expect($obtidas)->toBe($esperadas);
});
