<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Modules\Governance\Services\Checkers\AdrLinksChecker;
use Modules\Governance\Services\Checkers\ChartersFreshnessChecker;
use Modules\Governance\Services\Checkers\ComposerAuditChecker;
use Modules\Governance\Services\Checkers\DesignDocsFreshnessChecker;
use Modules\Governance\Services\Checkers\MeilisearchSettingsDriftChecker;
use Modules\Governance\Services\Checkers\NpmAuditChecker;
use Modules\Governance\Services\Checkers\RoutesZombieChecker;
use Modules\Governance\Contracts\DriftChecker;
use Modules\Governance\Services\DriftCheckerRegistry;
use Modules\Governance\Services\DriftCheckResult;
use Modules\Governance\Services\ScopedScorecardEvaluator;

uses(Tests\TestCase::class);

/**
 * Estado "não medido" do DriftCheckResult (lote L2 da auditoria Governance, 2026-11-19).
 *
 * Antes, 9 dos 13 checkers devolviam clean() quando não conseguiam medir (binário ausente,
 * JSON inválido, tabela/config inexistente): verde sem ter medido (§5 proibicoes 2026-07-29,
 * LC-33). Cada caso aqui simula o erro e exige 'nao_medido' — nunca 'clean' — e, onde o
 * checker permite, um controle positivo de drift real exige 'drifted' (prova que o teste
 * não passa por o checker ter ficado mudo).
 *
 * IngestLiveness e PlanDrift têm os seus casos nos próprios arquivos de teste.
 */

function naoMedidoTmpBase(): string
{
    $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'gov-nao-medido-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($dir);

    return $dir;
}

/** Roda $fn com base_path() apontando pra $dir e restaura sempre. */
function comBasePath(string $dir, callable $fn): DriftCheckResult
{
    $original = base_path();
    app()->setBasePath($dir);
    try {
        return $fn();
    } finally {
        app()->setBasePath($original);
        File::deleteDirectory($dir);
    }
}

it('DTO: notMeasured nunca é verde nem drift, e o JSON declara o motivo', function () {
    $r = DriftCheckResult::notMeasured('x', 'npm ausente (exit 127)');

    expect($r->ok)->toBeFalse()
        ->and($r->measured)->toBeFalse()
        ->and($r->drift_count)->toBe(0)
        ->and($r->status())->toBe('nao_medido')
        ->and($r->toArray()['status'])->toBe('nao_medido')
        ->and($r->toArray()['motivo_nao_medido'])->toBe('npm ausente (exit 127)')
        ->and(DriftCheckResult::clean('x')->status())->toBe('clean')
        ->and(DriftCheckResult::drifted('x', [])->status())->toBe('drifted');
});

it('adr_link_rot: memory/decisions ausente → não medido; ref quebrada → drifted', function () {
    expect(comBasePath(naoMedidoTmpBase(), fn () => (new AdrLinksChecker())->check())->status())
        ->toBe('nao_medido');

    $dir = naoMedidoTmpBase();
    File::ensureDirectoryExists($dir.'/memory/decisions');
    File::put($dir.'/memory/decisions/0001-teste.md', "---\nslug: 0001-teste\nsupersedes: [9999]\n---\n# teste\n");
    expect(comBasePath($dir, fn () => (new AdrLinksChecker())->check())->status())->toBe('drifted');
});

it('design_docs_freshness: nenhum doc de design → não medido', function () {
    expect(comBasePath(naoMedidoTmpBase(), fn () => (new DesignDocsFreshnessChecker())->check())->status())
        ->toBe('nao_medido');
});

it('charters_freshness: JSON inválido → não medido', function () {
    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn('Fatal: não é JSON');
    expect((new ChartersFreshnessChecker())->check()->status())->toBe('nao_medido');
});

it('charters_freshness: charter:audit lança → não medido com o motivo', function () {
    Artisan::shouldReceive('call')->once()->andThrow(new RuntimeException('charter:audit quebrou'));
    $r = (new ChartersFreshnessChecker())->check();
    expect($r->status())->toBe('nao_medido')->and($r->motivo_nao_medido)->toContain('charter:audit quebrou');
});

it('charters_freshness: controle positivo — tsx sem charter → drifted', function () {
    Artisan::shouldReceive('call')->once()->andReturn(0);
    Artisan::shouldReceive('output')->once()->andReturn(json_encode(['tsx_without_charter' => ['resources/js/Pages/X/Y.tsx']]));
    expect((new ChartersFreshnessChecker())->check()->status())->toBe('drifted');
});

it('composer_audit: binário ausente (127) → não medido; JSON com CVE → drifted mesmo com exit > 1', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'composer: not found', exitCode: 127)]);
    $r = (new ComposerAuditChecker())->check();
    expect($r->status())->toBe('nao_medido')->and($r->motivo_nao_medido)->toContain('127');

    $cve = json_encode(['advisories' => ['vendor/pkg' => [['cve' => 'CVE-2026-0001', 'title' => 't', 'severity' => 'high']]]]);
    Process::fake(['*' => Process::result(output: $cve, exitCode: 3)]);
    expect((new ComposerAuditChecker())->check()->status())->toBe('drifted');

    Process::fake(['*' => Process::result(output: '', exitCode: 0)]);
    expect((new ComposerAuditChecker())->check()->status())->toBe('clean');
});

it('npm_audit: binário ausente (127) → não medido; JSON inválido → não medido; vulnerabilidade → drifted', function () {
    Process::fake(['*' => Process::result(output: '', errorOutput: 'npm: not found', exitCode: 127)]);
    expect((new NpmAuditChecker())->check()->status())->toBe('nao_medido');

    Process::fake(['*' => Process::result(output: 'npm ERR! não é JSON', exitCode: 1)]);
    expect((new NpmAuditChecker())->check()->status())->toBe('nao_medido');

    $vuln = json_encode(['vulnerabilities' => ['pkg' => ['severity' => 'high', 'range' => '<1.0.0', 'via' => ['outro']]]]);
    Process::fake(['*' => Process::result(output: $vuln, exitCode: 1)]);
    expect((new NpmAuditChecker())->check()->status())->toBe('drifted');
});

it('meilisearch_settings_drift: config de índices vazia → não medido', function () {
    config(['copiloto.meilisearch_indexes' => []]);
    expect((new MeilisearchSettingsDriftChecker())->check()->status())->toBe('nao_medido');
});

it('routes_zombie: sem system_access_log → não medido', function () {
    if (Schema::hasTable('system_access_log')) {
        $this->markTestSkipped('system_access_log existe neste banco: o ramo sem tabela não é alcançável aqui');
    }
    $r = (new RoutesZombieChecker())->check();
    expect($r->status())->toBe('nao_medido')->and($r->metadata['routes_total'])->toBeGreaterThan(0);
});

it('ci_health (ScopedScorecardEvaluator) mede a taxa real: 19/20 ≥ 0.95 → true; 9/10 < 0.95 → false; HTTP 500 → false', function () {
    $eval = new ScopedScorecardEvaluator();
    putenv('GH_TOKEN=token-de-teste');
    $_ENV['GH_TOKEN'] = $_SERVER['GH_TOKEN'] = 'token-de-teste';
    try {
        $runs = fn (int $ok, int $falha) => ['workflow_runs' => array_merge(
            array_fill(0, $ok, ['conclusion' => 'success']),
            array_fill(0, $falha, ['conclusion' => 'failure']),
            [['conclusion' => 'cancelled']], // fora do denominador
        )];

        Http::fakeSequence()
            ->push($runs(19, 1))
            ->push($runs(9, 1))
            ->push([], 500)
            ->push(['workflow_runs' => []]);

        $d = ['coverage_target' => 0.95];
        expect($eval->detectCiHealth('Vestuario', $d))->toBeTrue()
            ->and($eval->detectCiHealth('Vestuario', $d))->toBeFalse()
            ->and($eval->detectCiHealth('Vestuario', $d))->toBeFalse()
            ->and($eval->detectCiHealth('Vestuario', $d))->toBeFalse(); // sem run concluído = não medido
    } finally {
        putenv('GH_TOKEN');
        unset($_ENV['GH_TOKEN'], $_SERVER['GH_TOKEN']);
    }
});

it('governance:audit separa não medido de clean/drifted; exceção do checker vira não medido', function () {
    app()->forgetInstance(DriftCheckerRegistry::class);
    $reg = new DriftCheckerRegistry();
    app()->instance(DriftCheckerRegistry::class, $reg);
    $fake = fn (string $nome, callable $check) => new class($nome, $check) implements DriftChecker {
        public function __construct(private string $n, private $c) {}
        public function name(): string { return $this->n; }
        public function description(): string { return 'teste'; }
        public function tags(): array { return ['test']; }
        public function severity(): string { return 'low'; }
        public function enforcement(): string { return 'advisory'; }
        public function cadence(): string { return 'daily'; }
        public function check(array $opts = []): DriftCheckResult { return ($this->c)($this->n); }
    };
    $reg->register($fake('limpo', fn ($n) => DriftCheckResult::clean($n)));
    $reg->register($fake('cego', fn ($n) => DriftCheckResult::notMeasured($n, 'npm ausente')));
    $reg->register($fake('quebrou', fn ($n) => throw new RuntimeException('boom')));

    Artisan::call('governance:audit', ['--all' => true, '--json' => true, '--no-persist' => true]);
    $json = json_decode(Artisan::output(), true);

    expect($json['summary']['clean'])->toBe(1)
        ->and($json['summary']['nao_medido'])->toBe(2)
        ->and($json['summary']['drifted'])->toBe(0)
        ->and(collect($json['results'])->firstWhere('name', 'quebrou')['status'])->toBe('nao_medido');
});
