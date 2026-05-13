<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Backlinks\AdrGraphBuilder;

uses(Tests\TestCase::class);

/**
 * Testes do comando jana:backlinks:sweep.
 *
 * Estratégia: bind AdrGraphBuilder com paths customizados, executar comando,
 * inspecionar output stdout/JSON e arquivos gerados.
 */

beforeEach(function () {
    $this->tmpDecisions = sys_get_temp_dir() . '/sweep-cmd-decisions-' . uniqid();
    $this->tmpRequisitos = sys_get_temp_dir() . '/sweep-cmd-requisitos-' . uniqid();
    File::makeDirectory($this->tmpDecisions, 0755, true);
    File::makeDirectory($this->tmpRequisitos, 0755, true);

    // Bind no container pra comando usar caminhos custom
    $this->app->bind(AdrGraphBuilder::class, function () {
        return new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos);
    });
});

afterEach(function () {
    if (is_dir($this->tmpDecisions)) {
        File::deleteDirectory($this->tmpDecisions);
    }
    if (is_dir($this->tmpRequisitos)) {
        File::deleteDirectory($this->tmpRequisitos);
    }
    // limpa report dummy se foi escrito em memory/decisions real
    $report = base_path('memory/decisions/_BACKLINKS-REPORT-' . now()->format('Y-m-d') . '.md');
    // não removemos; faz parte do output canônico do dry-run real
});

function makeSweepFixture(string $dir, int $number, string $title, array $fm = [], string $body = ''): void
{
    $slug = sprintf('%04d-%s', $number, str_replace(' ', '-', strtolower($title)));
    $defaults = [
        'slug' => $slug,
        'number' => $number,
        'title' => $title,
        'type' => 'adr',
        'status' => 'aceito',
        'authority' => 'canonical',
        'lifecycle' => 'ativo',
        'decided_by' => ['W'],
        'decided_at' => '2026-05-13',
    ];
    $merged = array_merge($defaults, $fm);

    $yaml = "---\n";
    foreach ($merged as $k => $v) {
        if (is_array($v)) {
            $yaml .= "{$k}: [" . implode(', ', array_map(fn ($x) => is_int($x) ? $x : "'$x'", $v)) . "]\n";
        } elseif (is_string($v)) {
            $yaml .= "{$k}: '{$v}'\n";
        } else {
            $yaml .= "{$k}: {$v}\n";
        }
    }
    $yaml .= "---\n\n# ADR {$number} — {$title}\n\n{$body}\n";

    file_put_contents($dir . "/{$slug}.md", $yaml);
}

it('sai com código 0 quando grafo está limpo', function () {
    makeSweepFixture($this->tmpDecisions, 100, 'A');
    makeSweepFixture($this->tmpDecisions, 101, 'B', ['related_adrs' => [100]]);

    $code = Artisan::call('jana:backlinks:sweep', ['--no-report' => true, '--json' => true]);

    expect($code)->toBe(0);

    $output = Artisan::output();
    $json = json_decode(substr($output, strpos($output, '{')), true);
    expect($json['ok'])->toBeTrue()
        ->and($json['stats']['broken'])->toBe(0);
});

it('sai com código 1 quando há broken links', function () {
    makeSweepFixture($this->tmpDecisions, 200, 'Com broken', ['related_adrs' => [9999]]);

    $code = Artisan::call('jana:backlinks:sweep', ['--no-report' => true, '--json' => true]);

    expect($code)->toBe(1);

    $output = Artisan::output();
    $json = json_decode(substr($output, strpos($output, '{')), true);
    expect($json['stats']['broken'])->toBeGreaterThan(0);
});

it('grava JSON em storage/app/jana/backlinks-graph.json', function () {
    makeSweepFixture($this->tmpDecisions, 300, 'Solo');

    $jsonPath = storage_path('app/jana/backlinks-graph.json');
    if (file_exists($jsonPath)) {
        unlink($jsonPath);
    }

    Artisan::call('jana:backlinks:sweep', ['--no-report' => true]);

    expect(file_exists($jsonPath))->toBeTrue();

    $data = json_decode(file_get_contents($jsonPath), true);
    expect($data)->toHaveKey('nodes')
        ->and($data['total_adrs'])->toBe(1);
});

it('detecta assimétricas no output JSON', function () {
    makeSweepFixture($this->tmpDecisions, 400, 'Pai', ['supersedes' => [401]]);
    makeSweepFixture($this->tmpDecisions, 401, 'Filho');

    Artisan::call('jana:backlinks:sweep', ['--no-report' => true, '--json' => true]);
    $output = Artisan::output();
    $json = json_decode(substr($output, strpos($output, '{')), true);

    expect($json['stats']['asymmetric'])->toBeGreaterThan(0);
});

it('lista hints com --fix sem auto-aplicar', function () {
    makeSweepFixture($this->tmpDecisions, 500, 'Pai', ['supersedes' => [501]]);
    makeSweepFixture($this->tmpDecisions, 501, 'Filho');

    Artisan::call('jana:backlinks:sweep', ['--no-report' => true, '--fix' => true]);

    $output = Artisan::output();
    expect($output)->toContain('Ações sugeridas')
        ->and($output)->toContain('Assimétricas');

    // assert que NÃO modificou o arquivo
    $fixture = $this->tmpDecisions . '/0501-filho.md';
    expect(file_get_contents($fixture))->not->toContain('superseded_by: [500]');
});
