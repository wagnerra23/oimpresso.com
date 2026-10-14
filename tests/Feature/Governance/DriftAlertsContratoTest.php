<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Modules\Governance\Http\Controllers\DriftAlertsController;
use Modules\Governance\Services\DriftAlertService;

/**
 * Governance · /governance/drift — contrato do scan SCOPE.md × filesystem.
 *
 * Casos: resources/js/Pages/governance/DriftAlerts.casos.md (UC-GDRF-*).
 * Fonte dos casos: DriftAlerts.charter.md + SPEC US-GOV-004 + RUNBOOK-drift-alerts.md —
 * o charter prometia "tests/Feature/Governance/DriftDetectionTest.php — módulo fixture
 * com controller undeclared", que nunca existiu; este arquivo cumpre a promessa.
 *
 * COMO MEDE: o DriftAlertService varre `base_path('Modules')` e
 * `base_path('memory/requisitos/<X>/SCOPE.md')`. Cada caso monta uma árvore FIXTURE num
 * diretório temporário e aponta o `base_path` do container para ela — nunca o repo real,
 * cujo estado de drift muda a cada PR (um assert sobre o repo real seria vermelho de
 * terceiro). Nada aqui toca banco nem tenant: o scan é de filesystem, cross-tenant por
 * construção (biz=4 não aparece em lugar nenhum).
 *
 * Lane: sqlite (sem migrate, sem actingAs) — é por isso que nada usa DB.
 */

/** Cria a árvore fixture e devolve a raiz. $modulos: nome => [controllers(rel), scope|null]. */
function gdrfFixture(array $modulos): string
{
    $raiz = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'gdrf-' . bin2hex(random_bytes(6));

    foreach ($modulos as $nome => [$controllers, $scope]) {
        foreach ($controllers as $rel) {
            $arq = $raiz . '/Modules/' . $nome . '/' . $rel;
            @mkdir(dirname($arq), 0777, true);
            file_put_contents($arq, "<?php\n");
        }
        @mkdir($raiz . '/Modules/' . $nome, 0777, true);

        if ($scope !== null) {
            $dir = $raiz . '/memory/requisitos/' . $nome;
            @mkdir($dir, 0777, true);
            file_put_contents($dir . '/SCOPE.md', $scope);
        }
    }

    return $raiz;
}

function gdrfScope(array $contains, string $extra = ''): string
{
    $linhas = array_map(fn ($c) => '  - "' . $c . ' — fixture"', $contains);

    return "---\nmodule: Fx\ncontains:\n" . implode("\n", $linhas) . "\n" . $extra . "---\n\n# SCOPE fixture\n";
}

function gdrfRm(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($dir);
}

beforeEach(function () {
    $this->gdrfBaseOriginal = app()->basePath();
    $this->gdrfRaiz = null;
});

afterEach(function () {
    app()->setBasePath($this->gdrfBaseOriginal);
    if ($this->gdrfRaiz) {
        gdrfRm($this->gdrfRaiz);
    }
});

function gdrfUsar(array $modulos): void
{
    $raiz = gdrfFixture($modulos);
    test()->gdrfRaiz = $raiz;
    app()->setBasePath($raiz);
}

function gdrfLinha(iterable $report, string $modulo): ?array
{
    foreach ($report as $l) {
        if ($l['module'] === $modulo) {
            return $l;
        }
    }

    return null;
}

describe('Governance /governance/drift — contrato do scan', function () {

    it('UC-GDRF-01 — controller que existe e não está no contains[] vira drift do módulo', function () {
        gdrfUsar([
            'Fx' => [[
                'Http/Controllers/FooController.php',
                'Http/Controllers/BarController.php',
            ], gdrfScope(['FooController'])],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);
        $linha = gdrfLinha($d->get('report'), 'Fx');

        $this->assertNotNull($linha, 'módulo com controller não declarado deveria aparecer em report');
        $this->assertSame(['BarController'], $linha['undeclared']);
        $this->assertSame(1, $linha['undeclared_count']);
        $this->assertSame(2, $linha['total_actual']);
    });

    it('UC-GDRF-01 — controle negativo: tudo declarado não gera drift', function () {
        gdrfUsar([
            'Fx' => [['Http/Controllers/FooController.php'], gdrfScope(['FooController'])],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);

        $this->assertNull(gdrfLinha($d->get('report'), 'Fx'));
        $this->assertSame(0, $d->get('total_drift'));
    });

    it('UC-GDRF-02 — boilerplate (Data/Install/Superadmin/Controller) nunca é drift', function () {
        gdrfUsar([
            'Fx' => [[
                'Http/Controllers/DataController.php',
                'Http/Controllers/InstallController.php',
                'Http/Controllers/SuperadminController.php',
                'Http/Controllers/Controller.php',
                'Http/Controllers/FooController.php',
            ], gdrfScope(['FooController'])],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);

        $this->assertNull(gdrfLinha($d->get('report'), 'Fx'), 'boilerplate não declarado não pode virar drift');
        $this->assertSame(0, $d->get('total_drift'));
    });

    it('UC-GDRF-03 — controller declarado em drift_alerts[] conta como declarado (transitório)', function () {
        $extra = "drift_alerts:\n  - controller: \"BarController\"\n    eta_migracao: \"Fase X\"\n";
        gdrfUsar([
            'Fx' => [[
                'Http/Controllers/FooController.php',
                'Http/Controllers/BarController.php',
            ], gdrfScope(['FooController'], $extra)],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);

        $this->assertNull(gdrfLinha($d->get('report'), 'Fx'), 'controller em drift_alerts[] não deveria ser drift');
    });

    it('UC-GDRF-04 — módulo sem SCOPE.md vai pra lista própria e não conta como drift', function () {
        gdrfUsar([
            'Fx' => [['Http/Controllers/FooController.php'], gdrfScope(['FooController'])],
            'Orfao' => [['Http/Controllers/QualquerController.php'], null],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);

        $this->assertSame(['Orfao'], $d->get('modules_without_scope'));
        $this->assertNull(gdrfLinha($d->get('report'), 'Orfao'));
        $this->assertSame(2, $d->get('modules_total'));
    });

    it('UC-GDRF-05 — SCOPE.md com YAML inválido não derruba o scan e vai pro log estruturado', function () {
        Log::spy();
        gdrfUsar([
            'Quebrado' => [['Http/Controllers/FooController.php'], "---\ncontains: [ \"aberto\n  : : :\n---\n"],
            'Fx' => [['Http/Controllers/FooController.php'], gdrfScope(['FooController'])],
        ]);

        $d = (new DriftAlertService())->getActiveDrifts(500);

        // O scan terminou e respondeu pelos OUTROS módulos.
        $this->assertSame(2, $d->get('modules_total'));
        $this->assertNull(gdrfLinha($d->get('report'), 'Fx'));

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($msg, $ctx = []) => is_string($msg) && str_contains($msg, 'YAML parse falhou'))
            ->atLeast()->once();
    });

    it('UC-GDRF-06 — os 4 KPIs derivam do MESMO scan que a lista', function () {
        gdrfUsar([
            'Alfa' => [[
                'Http/Controllers/AController.php',
                'Http/Controllers/BController.php',
                'Http/Controllers/CController.php',
            ], gdrfScope(['AController'])],
            'Beta' => [[
                'Http/Controllers/DController.php',
                'Http/Controllers/EController.php',
            ], gdrfScope(['DController'])],
            'Limpo' => [['Http/Controllers/FController.php'], gdrfScope(['FController'])],
            'SemEscopo' => [['Http/Controllers/GController.php'], null],
        ]);

        $controller = app(DriftAlertsController::class);
        $metodo = new ReflectionMethod($controller, 'buildKpisPayload');
        $metodo->setAccessible(true);
        $kpis = $metodo->invoke($controller);

        // Alfa: 2 fora (B, C) · Beta: 1 fora (E) → 3 controllers em 2 módulos.
        $this->assertSame([
            'total_drift'           => 3,
            'modules_with_drift'    => 2,
            'modules_without_scope' => 1,
            'modules_total'         => 4,
        ], $kpis);
    });

    it('UC-GDRF-07 — a tela é só leitura: uma rota GET, throttle 20/min e gate da sidebar', function () {
        $rota = Route::getRoutes()->getByName('governance.drift.index');
        $this->assertNotNull($rota, 'rota governance.drift.index não registrada');

        $this->assertSame(['GET', 'HEAD'], $rota->methods());
        $mw = $rota->gatherMiddleware();
        $this->assertContains('throttle:20,1', $mw);
        $this->assertContains('can:governance.dashboard.view', $mw);

        // Sem auto-fix, sem snooze, sem edit inline: nenhuma outra rota sob /governance/drift.
        $outras = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => str_starts_with(ltrim($r->uri(), '/'), 'governance/drift'))
            ->reject(fn ($r) => $r->getName() === 'governance.drift.index')
            ->map(fn ($r) => implode('|', $r->methods()) . ' ' . $r->uri())
            ->values()
            ->all();
        $this->assertSame([], $outras, 'rota de escrita sob /governance/drift viola os Non-Goals do charter');
    });

    it('UC-GDRF-08 — enquanto o cron não persiste, o histórico chega vazio', function () {
        $this->assertSame([], (new DriftAlertService())->persistedAlerts());
    });
});
