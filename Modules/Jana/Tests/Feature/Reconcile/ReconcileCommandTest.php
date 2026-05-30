<?php

declare(strict_types=1);

/**
 * ReconcileCommandTest — orquestrador jana:reconcile (ADR 0237).
 *
 * Determinístico, SEM rede / SEM DB: registra Reconcilers FALSOS (classes nomeadas
 * implementando o contrato Modules\Jana\Contracts\Reconciler) via
 * config(['copiloto.reconcilers' => [...]]) em runtime — independe do registry real
 * e do merge do JanaServiceProvider. Cada fake é configurável por static props pra
 * exercitar inSync / drift / erro / healable sem I/O.
 *
 * Cobre os invariantes do orquestrador:
 *   001. --check exit 1 quando QUALQUER drift > 0; exit 0 quando tudo inSync.
 *   002. --check NÃO cura (ignora --heal): healedCount fica 0 mesmo com --heal junto.
 *   003. --heal passa heal=true → reconciler cura o healable; reporta healedCount.
 *   004. default (sem flags) só reporta, não cura, exit 0 mesmo com drift.
 *   005. --json imprime array determinístico de ->toArray() (shape canon).
 *   006. --only filtra por name().
 *   007. class_exists-guard: FQCN inexistente é tolerado (não derruba o loop).
 *   008. resiliência: reconciler que lança vira linha de erro + exit 1, demais rodam.
 *
 * @see Modules\Jana\Console\Commands\ReconcileCommand
 * @see Modules\Jana\Contracts\Reconciler
 */

use Illuminate\Support\Facades\Artisan;
use Modules\Jana\Contracts\Reconciler;
use Modules\Jana\Services\Reconcile\ReconcileDrift;
use Modules\Jana\Services\Reconcile\ReconcileResult;
use Tests\TestCase;

uses(TestCase::class);

// Local-dev worktree autoload fix (mesmo padrão de
// JanaCyclesAutoCloseExpiredCommandTest): em worktree o `vendor` é JUNCTION pra
// main repo, e o autoloader composer (gerado no main) mapeia `Modules\Jana\` pro
// main repo Modules/. As classes da ADR 0237 (Reconciler + DTOs Reconcile + o
// ReconcileCommand) existem SÓ nesta worktree → o autoload composer falha. Aqui
// registramos um autoloader que resolve esses símbolos a partir do path da
// worktree. No CI/prod (já mergeado em main) o composer resolve nativo e este
// closure só confirma o mesmo arquivo (idempotente).
spl_autoload_register(function (string $class): void {
    $worktreeOnlyPrefixes = [
        'Modules\\Jana\\Contracts\\Reconciler',
        'Modules\\Jana\\Services\\Reconcile\\',
        'Modules\\Jana\\Console\\Commands\\ReconcileCommand',
    ];
    $match = false;
    foreach ($worktreeOnlyPrefixes as $prefix) {
        if (str_starts_with($class, $prefix)) {
            $match = true;
            break;
        }
    }
    if (! $match) {
        return;
    }
    // dirname(__DIR__, 3) = .../Modules/Jana (a partir de Tests/Feature/Reconcile).
    $relative = str_replace(['Modules\\Jana\\', '\\'], ['', DIRECTORY_SEPARATOR], $class) . '.php';
    $candidate = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . $relative;
    if (is_file($candidate)) {
        require_once $candidate;
    }
}, true, true);

/**
 * Fake base: lê seu "plano" de FakeReconcilerState por name(). Sem I/O.
 */
abstract class FakeReconcilerBase implements Reconciler
{
    public function description(): string
    {
        return 'fake reconciler de teste — ' . $this->name();
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['tier_test', 'fake'];
    }

    public function reconcile(array $opts = []): ReconcileResult
    {
        FakeReconcilerState::$lastOpts[$this->name()] = $opts;

        $plan = FakeReconcilerState::$plans[$this->name()] ?? ['drift' => 0, 'healable' => 0, 'throw' => false];

        if (($plan['throw'] ?? false) === true) {
            throw new \RuntimeException('boom de teste em ' . $this->name());
        }

        $driftQty = (int) ($plan['drift'] ?? 0);
        if ($driftQty === 0) {
            return ReconcileResult::synced($this->name());
        }

        $heal = (bool) ($opts['heal'] ?? false);
        $healableQty = min((int) ($plan['healable'] ?? 0), $driftQty);

        $drifts = [];
        for ($i = 0; $i < $driftQty; $i++) {
            $isHealable = $i < $healableQty;
            $drifts[] = new ReconcileDrift(
                target: $this->name() . ":target:{$i}",
                detail: "drift sintético {$i}",
                desired: 'git',
                observed: 'vivo',
                healable: $isHealable,
                healed: $isHealable && $heal,
            );
        }

        return ReconcileResult::from($this->name(), $drifts);
    }
}

/** Estado global injetável pelos testes (reset em beforeEach). */
final class FakeReconcilerState
{
    /** @var array<string, array{drift?: int, healable?: int, throw?: bool}> */
    public static array $plans = [];

    /** @var array<string, array<string, mixed>> */
    public static array $lastOpts = [];

    public static function reset(): void
    {
        self::$plans = [];
        self::$lastOpts = [];
    }
}

final class FakeIndexReconciler extends FakeReconcilerBase
{
    public function name(): string
    {
        return 'index';
    }
}

final class FakeSettingsReconciler extends FakeReconcilerBase
{
    public function name(): string
    {
        return 'settings';
    }
}

final class FakeDeployReconciler extends FakeReconcilerBase
{
    public function name(): string
    {
        return 'deploy';
    }
}

beforeEach(function () {
    FakeReconcilerState::reset();

    // Registra o command manualmente: o JanaServiceProvider carregado via vendor
    // symlink (main repo) ainda não lista ReconcileCommand no array commands([...]),
    // e a worktree-only não é auto-discovered. Idempotente (skip se já registrado).
    // Em prod, basta 1 linha no JanaServiceProvider::boot() commands([...]) — ver
    // RETORNO da task (riscos).
    $cmdClass = \Modules\Jana\Console\Commands\ReconcileCommand::class;
    if (class_exists($cmdClass)) {
        $kernel = app(\Illuminate\Contracts\Console\Kernel::class);
        if (method_exists($kernel, 'registerCommand')) {
            $jaRegistrado = collect(Artisan::all())->keys()->contains('jana:reconcile');
            if (! $jaRegistrado) {
                $kernel->registerCommand(new $cmdClass());
            }
        }
    }
});

it('--check retorna exit 1 quando qualquer reconciler tem drift', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 2, 'healable' => 1],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true]);

    expect($exit)->toBe(1);
});

it('--check retorna exit 0 quando tudo está inSync', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true]);

    expect($exit)->toBe(0);
});

it('--check NÃO cura mesmo se --heal vier junto (gate de detecção pura)', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 3, 'healable' => 3],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true, '--heal' => true, '--json' => true]);

    expect($exit)->toBe(1);

    // heal=false foi passado ao reconciler (check sobrepõe heal).
    expect(FakeReconcilerState::$lastOpts['index']['heal'])->toBeFalse();

    $report = json_decode(trim(Artisan::output()), true);
    expect($report[0]['healed_count'])->toBe(0);
});

it('--heal passa heal=true e reporta healedCount do que é healable', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 4, 'healable' => 3],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--heal' => true, '--json' => true]);

    // heal sem --check NÃO é gate → exit 0 mesmo com drift restante.
    expect($exit)->toBe(0);
    expect(FakeReconcilerState::$lastOpts['index']['heal'])->toBeTrue();

    $report = json_decode(trim(Artisan::output()), true);
    expect($report[0]['drift_count'])->toBe(4)
        ->and($report[0]['healed_count'])->toBe(3);
});

it('--dry-run com --heal repassa dry_run=true ao reconciler', function () {
    FakeReconcilerState::$plans = ['index' => ['drift' => 1, 'healable' => 1]];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class]]);

    Artisan::call('jana:reconcile', ['--heal' => true, '--dry-run' => true]);

    expect(FakeReconcilerState::$lastOpts['index']['dry_run'])->toBeTrue()
        ->and(FakeReconcilerState::$lastOpts['index']['heal'])->toBeTrue();
});

it('default (sem flags) só reporta, não cura, exit 0 mesmo com drift', function () {
    FakeReconcilerState::$plans = ['settings' => ['drift' => 2, 'healable' => 2]];
    config(['copiloto.reconcilers' => [FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--json' => true]);

    expect($exit)->toBe(0);
    expect(FakeReconcilerState::$lastOpts['settings']['heal'])->toBeFalse();

    $report = json_decode(trim(Artisan::output()), true);
    expect($report[0]['drift_count'])->toBe(2)
        ->and($report[0]['healed_count'])->toBe(0); // não curou
});

it('--json imprime array determinístico de ->toArray() com shape canon', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 1, 'healable' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    Artisan::call('jana:reconcile', ['--json' => true]);
    $report = json_decode(trim(Artisan::output()), true);

    expect($report)->toBeArray()->toHaveCount(2);

    // Ordem determinística = ordem da config.
    expect($report[0]['name'])->toBe('index')
        ->and($report[1]['name'])->toBe('settings');

    // Shape canon do ReconcileResult::toArray().
    expect($report[0])->toHaveKeys([
        'name', 'in_sync', 'drift_count', 'healed_count', 'duration_ms', 'drifts', 'metadata',
    ]);
    expect($report[0]['in_sync'])->toBeTrue()
        ->and($report[1]['in_sync'])->toBeFalse()
        ->and($report[1]['drifts'])->toHaveCount(1);

    // Shape canon do ReconcileDrift::toArray().
    expect($report[1]['drifts'][0])->toHaveKeys([
        'target', 'detail', 'desired', 'observed', 'healable', 'healed',
    ]);
});

it('--only filtra reconcilers por name()', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 5, 'healable' => 0],
        'deploy' => ['drift' => 0],
    ];
    config(['copiloto.reconcilers' => [
        FakeIndexReconciler::class,
        FakeSettingsReconciler::class,
        FakeDeployReconciler::class,
    ]]);

    Artisan::call('jana:reconcile', ['--only' => 'index,deploy', '--json' => true]);
    $report = json_decode(trim(Artisan::output()), true);

    $names = array_map(static fn (array $r): string => $r['name'], $report);
    expect($names)->toBe(['index', 'deploy']); // settings filtrado fora

    // settings nem foi instanciado/reconciliado (não está em lastOpts).
    expect(FakeReconcilerState::$lastOpts)->not->toHaveKey('settings');
});

it('--check --only=<nome_inexistente> retorna exit 1 (typo NÃO neutraliza o gate)', function () {
    // Regressão do pior bug de um gate: um typo em --only fazer `jana:reconcile --check`
    // passar VERDE sem reconciliar nada. Antes do fix, --only que não casava caía no
    // branch "nenhum reconciler" e retornava SUCCESS, ignorando --check. Agora é input
    // inválido → FAILURE.
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true, '--only' => 'setings']); // typo

    expect($exit)->toBe(1); // FAILURE, não SUCCESS

    // Nenhum reconciler foi rodado (abortou na validação de input, antes do loop).
    expect(FakeReconcilerState::$lastOpts)->toBe([]);

    // Mensagem lista os nomes válidos pra orientar a correção do typo.
    $out = Artisan::output();
    expect($out)->toContain('setings')
        ->and($out)->toContain('index')
        ->and($out)->toContain('settings');
});

it('--only com UM nome inválido entre nomes válidos aborta tudo com exit 1', function () {
    // Garantia mais forte: basta UM nome não casar pra abortar — mesmo que `index`
    // exista. O typo `setings` não pode passar silencioso "porque index casou".
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true, '--only' => 'index,setings']);

    expect($exit)->toBe(1);

    // Como abortou na validação, NEM o `index` (válido) chegou a rodar.
    expect(FakeReconcilerState::$lastOpts)->toBe([]);
});

it('--check com --only que isola um reconciler limpo retorna exit 0 (ignora drift do filtrado)', function () {
    FakeReconcilerState::$plans = [
        'index' => ['drift' => 0],
        'settings' => ['drift' => 9, 'healable' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true, '--only' => 'index']);

    expect($exit)->toBe(0); // settings (com drift) ficou de fora do escopo
});

it('tolera FQCN inexistente na config (class_exists-guard) sem derrubar o loop', function () {
    FakeReconcilerState::$plans = ['index' => ['drift' => 0]];
    config(['copiloto.reconcilers' => [
        'Modules\\Jana\\Services\\Reconcile\\Reconcilers\\NaoExisteReconciler',
        FakeIndexReconciler::class,
    ]]);

    $exit = Artisan::call('jana:reconcile', ['--json' => true]);

    expect($exit)->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);
    // Só o reconciler real entra; o FQCN fantasma é pulado.
    expect($report)->toHaveCount(1)
        ->and($report[0]['name'])->toBe('index');
});

it('config vazia LEGÍTIMA (sem --only) → exit 0 e nenhum reconciler rodado', function () {
    // Contraponto ao caso typo: config vazia SEM --only é "nada registrado, nada a
    // fazer" = SUCCESS honesto. O fix preserva isso — só o --only-não-casou virou
    // FAILURE; a ausência de reconcilers continua SUCCESS.
    config(['copiloto.reconcilers' => []]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true]);

    expect($exit)->toBe(0);
    expect(FakeReconcilerState::$lastOpts)->toBe([]);
});

it('config vazia + --only → exit 1 (não há nome pra casar; lista disponíveis = nenhum)', function () {
    // Borda: se NÃO há reconciler registrado mas o usuário passou --only, ainda é input
    // inválido (o nome pedido não existe) → FAILURE, e a lista de disponíveis é vazia.
    config(['copiloto.reconcilers' => []]);

    $exit = Artisan::call('jana:reconcile', ['--check' => true, '--only' => 'index']);

    expect($exit)->toBe(1);
    expect(FakeReconcilerState::$lastOpts)->toBe([]);

    $out = Artisan::output();
    expect($out)->toContain('index')
        ->and($out)->toContain('nenhum'); // "Reconcilers disponíveis: (nenhum)."
});

it('é resiliente: reconciler que lança vira linha de erro + exit 1, demais rodam', function () {
    FakeReconcilerState::$plans = [
        'index' => ['throw' => true],
        'settings' => ['drift' => 0],
    ];
    config(['copiloto.reconcilers' => [FakeIndexReconciler::class, FakeSettingsReconciler::class]]);

    $exit = Artisan::call('jana:reconcile', ['--json' => true]);

    // Erro operacional → exit 1 (mesmo sem --check).
    expect($exit)->toBe(1);

    $report = json_decode(trim(Artisan::output()), true);
    expect($report)->toHaveCount(2);

    // index virou linha de erro (in_sync=false, metadata.error preenchida).
    expect($report[0]['name'])->toBe('index')
        ->and($report[0]['in_sync'])->toBeFalse()
        ->and($report[0]['metadata'])->toHaveKey('error');

    // settings rodou normal apesar do irmão ter explodido.
    expect($report[1]['name'])->toBe('settings')
        ->and($report[1]['in_sync'])->toBeTrue();

    // settings foi de fato reconciliado (loop não parou no erro do index).
    expect(FakeReconcilerState::$lastOpts)->toHaveKey('settings');
});

it('idempotência: rodar --check 2× dá o mesmo exit code e mesmo JSON', function () {
    FakeReconcilerState::$plans = ['settings' => ['drift' => 2, 'healable' => 1]];
    config(['copiloto.reconcilers' => [FakeSettingsReconciler::class]]);

    $exit1 = Artisan::call('jana:reconcile', ['--check' => true, '--json' => true]);
    $out1 = trim(Artisan::output());

    $exit2 = Artisan::call('jana:reconcile', ['--check' => true, '--json' => true]);
    $out2 = trim(Artisan::output());

    expect($exit1)->toBe($exit2)->toBe(1)
        ->and($out1)->toBe($out2);
});
