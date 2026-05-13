<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Modules\Jana\Services\Backlinks\AdrGraphBuilder;

uses(Tests\TestCase::class);

/**
 * Testes do AdrGraphBuilder — gap G5 (P1) auditoria 2026-05-13.
 *
 * Estratégia: criar fixtures de ADRs em tmp dir, instanciar builder com paths
 * customizados, validar grafo + 4 detecções.
 */

beforeEach(function () {
    $this->tmpDecisions = sys_get_temp_dir() . '/backlinks-test-decisions-' . uniqid();
    $this->tmpRequisitos = sys_get_temp_dir() . '/backlinks-test-requisitos-' . uniqid();
    File::makeDirectory($this->tmpDecisions, 0755, true);
    File::makeDirectory($this->tmpRequisitos, 0755, true);
});

afterEach(function () {
    if (is_dir($this->tmpDecisions)) {
        File::deleteDirectory($this->tmpDecisions);
    }
    if (is_dir($this->tmpRequisitos)) {
        File::deleteDirectory($this->tmpRequisitos);
    }
});

function makeAdrFixture(string $dir, int $number, string $title, array $frontmatter = [], string $body = ''): void
{
    $slug = sprintf('%04d-%s', $number, str_replace(' ', '-', strtolower($title)));
    $fm = array_merge([
        'slug' => $slug,
        'number' => $number,
        'title' => $title,
        'type' => 'adr',
        'status' => 'aceito',
        'authority' => 'canonical',
        'lifecycle' => 'ativo',
        'decided_by' => ['W'],
        'decided_at' => '2026-05-13',
    ], $frontmatter);

    $yaml = "---\n";
    foreach ($fm as $key => $value) {
        if (is_array($value)) {
            $yaml .= "{$key}: [" . implode(', ', array_map(fn ($v) => is_int($v) ? $v : "'$v'", $value)) . "]\n";
        } elseif (is_string($value)) {
            $yaml .= "{$key}: '{$value}'\n";
        } else {
            $yaml .= "{$key}: {$value}\n";
        }
    }
    $yaml .= "---\n\n# ADR {$number} — {$title}\n\n{$body}\n";

    file_put_contents($dir . "/{$slug}.md", $yaml);
}

it('parseia frontmatter de ADR e popula nós', function () {
    makeAdrFixture($this->tmpDecisions, 100, 'Teste base');
    makeAdrFixture($this->tmpDecisions, 101, 'Teste filho', ['related_adrs' => [100]]);

    $builder = new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos);
    $builder->build();

    expect($builder->nodes())->toHaveCount(2)
        ->and($builder->nodes()[100]['title'])->toBe('Teste base')
        ->and($builder->nodes()[101]['title'])->toBe('Teste filho');
});

it('agrega inbound a partir de outbound related_adrs', function () {
    makeAdrFixture($this->tmpDecisions, 200, 'Pai');
    makeAdrFixture($this->tmpDecisions, 201, 'Filho A', ['related_adrs' => [200]]);
    makeAdrFixture($this->tmpDecisions, 202, 'Filho B', ['related_adrs' => [200, 201]]);

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $inbound = $builder->inbound();
    expect($inbound[200])->toContain(201)
        ->and($inbound[200])->toContain(202)
        ->and($inbound[201])->toContain(202);
});

it('detecta orfãs (ADR aceita sem inbound)', function () {
    makeAdrFixture($this->tmpDecisions, 300, 'Orfã isolada');
    makeAdrFixture($this->tmpDecisions, 301, 'Conectada A', ['related_adrs' => [302]]);
    makeAdrFixture($this->tmpDecisions, 302, 'Conectada B');

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $orphans = $builder->findOrphans();
    expect($orphans)->toHaveKey(300)
        ->and($orphans)->toHaveKey(301)
        ->and($orphans)->not->toHaveKey(302); // 301 referencia 302
});

it('ignora ADRs arquivadas/substituidas na busca por orfãs', function () {
    makeAdrFixture($this->tmpDecisions, 400, 'Arquivada', ['lifecycle' => 'arquivado']);
    makeAdrFixture($this->tmpDecisions, 401, 'Substituida', ['lifecycle' => 'substituido']);
    makeAdrFixture($this->tmpDecisions, 402, 'Rascunho', ['status' => 'rascunho']);
    makeAdrFixture($this->tmpDecisions, 403, 'Aceita orfã');

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $orphans = $builder->findOrphans();
    expect($orphans)->toHaveCount(1)
        ->and($orphans)->toHaveKey(403);
});

it('detecta broken links (ref a ADR inexistente)', function () {
    makeAdrFixture($this->tmpDecisions, 500, 'Quebrada', ['related_adrs' => [9999, 9998]]);

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $broken = $builder->findBrokenLinks();
    expect($broken)->toHaveCount(2);

    $targets = array_column($broken, 'to');
    expect($targets)->toContain(9999)
        ->and($targets)->toContain(9998);
});

it('detecta assimetrias supersedes/superseded_by', function () {
    // 600.supersedes=[601] mas 601 não tem superseded_by
    makeAdrFixture($this->tmpDecisions, 600, 'Substituidora', ['supersedes' => [601]]);
    makeAdrFixture($this->tmpDecisions, 601, 'Substituida');

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $asym = $builder->findAsymmetric();
    expect($asym)->not->toBeEmpty();

    $found = collect($asym)->firstWhere(fn ($a) => $a['from'] === 600 && $a['to'] === 601);
    expect($found)->not->toBeNull()
        ->and($found['type'])->toBe('supersedes')
        ->and($found['expected_reverse'])->toBe('superseded_by');
});

it('NÃO detecta assimetria quando o par está fechado', function () {
    makeAdrFixture($this->tmpDecisions, 700, 'Substituidora', ['supersedes' => [701]]);
    makeAdrFixture($this->tmpDecisions, 701, 'Substituida', ['superseded_by' => [700]]);

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    expect($builder->findAsymmetric())->toBeEmpty();
});

it('extrai menções inline "ADR 0XXX" do corpo', function () {
    makeAdrFixture($this->tmpDecisions, 800, 'Pai');
    makeAdrFixture(
        $this->tmpDecisions,
        801,
        'Filho via inline',
        [],
        'Este texto menciona ADR 0800 inline sem listar no frontmatter.'
    );

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $inline = $builder->inlineRefs();
    expect($inline[801])->toContain(800)
        ->and($builder->inbound()[800])->toContain(801); // inline conta como inbound
});

it('normaliza refs: int, string padded, slug completo', function () {
    $builder = new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos);

    expect($builder->extractNumber(94))->toBe(94)
        ->and($builder->extractNumber('0094'))->toBe(94)
        ->and($builder->extractNumber('0094-constituicao-v2'))->toBe(94)
        ->and($builder->extractNumber('ADR 0094'))->toBe(94)
        ->and($builder->extractNumber('texto sem número'))->toBeNull();
});

it('rankeia top 5 ADRs mais centrais por inbound count', function () {
    makeAdrFixture($this->tmpDecisions, 900, 'Pai central');
    makeAdrFixture($this->tmpDecisions, 901, 'Filho A', ['related_adrs' => [900]]);
    makeAdrFixture($this->tmpDecisions, 902, 'Filho B', ['related_adrs' => [900]]);
    makeAdrFixture($this->tmpDecisions, 903, 'Filho C', ['related_adrs' => [900, 901]]);

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();

    $top = $builder->topCentral(3);
    expect($top[0]['number'])->toBe(900)
        ->and($top[0]['inbound_count'])->toBe(3); // 901+902+903
});

it('serializa toArray com stats', function () {
    makeAdrFixture($this->tmpDecisions, 1000, 'Base');
    makeAdrFixture($this->tmpDecisions, 1001, 'Outra', ['related_adrs' => [1000]]);

    $builder = (new AdrGraphBuilder($this->tmpDecisions, $this->tmpRequisitos))->build();
    $arr = $builder->toArray();

    expect($arr)->toHaveKeys(['generated_at', 'total_adrs', 'nodes', 'outbound', 'inbound', 'stats'])
        ->and($arr['total_adrs'])->toBe(2)
        ->and($arr['stats'])->toHaveKeys(['orphans', 'broken', 'asymmetric']);
});
