<?php

declare(strict_types=1);

use App\Services\ModuleManagerService;
use Illuminate\Support\Facades\File;

// Tests\TestCase já aplicado globalmente em tests/Pest.php. NÃO redeclarar.

/**
 * ModuleManagerService — ciclo de vida dos módulos nWidart (US-SUPER-006).
 *
 * Roda 100% em SANDBOX (diretório temporário + statuses próprio): nenhum teste daqui toca o
 * `Modules/` nem o `modules_statuses.json` REAIS do repo. É por isso que o construtor recebe
 * os dois paths — o rascunho [CC] fazia backup/restore do arquivo de verdade, o que deixa o
 * repo com módulo desligado se um teste morrer no meio.
 *
 * Sem banco: o service é filesystem-only. Nenhum tenant envolvido (a tela é app-wide
 * cross-tenant por desenho — ver Index.charter.md).
 *
 * FICA EM tests/Feature/ de propósito: `tests/Pest.php` só liga `Tests\TestCase` em
 * Feature/Browser/KB. Teste estilo Pest em `tests/Unit/` roda sem container Laravel — os 22
 * arquivos PHP de lá que precisam do app usam classe clássica (`extends Tests\TestCase`),
 * nenhum usa `uses(TestCase::class)`. Como este arquivo usa `storage_path()` e a facade
 * `File`, em `tests/Unit/Services/` ele quebraria.
 *
 * UCs: 05, 07, 11, 12, 13, 14, 17, 18, 19.
 *
 * @see app/Services/ModuleManagerService.php
 * @see resources/js/Pages/Modules/Index.casos.md
 */

/** Cria um módulo fake no sandbox. */
function fakeModule(string $dir, string $name, array $json = [], int $migrations = 0, bool $dataController = false): void
{
    $path = $dir . DIRECTORY_SEPARATOR . $name;
    File::makeDirectory($path, 0777, true);

    if ($json !== []) {
        File::put($path . DIRECTORY_SEPARATOR . 'module.json', json_encode($json));
    }

    if ($migrations > 0) {
        $mig = $path . '/Database/Migrations';
        File::makeDirectory($mig, 0777, true);
        for ($i = 1; $i <= $migrations; $i++) {
            File::put($mig . "/2026_01_0{$i}_000000_criar_tabela.php", '<?php // fixture');
        }
    }

    if ($dataController) {
        $ctrl = $path . '/Http/Controllers';
        File::makeDirectory($ctrl, 0777, true);
        File::put($ctrl . '/DataController.php', '<?php // fixture');
    }
}

beforeEach(function () {
    $this->sandbox = storage_path('framework/testing/modmgr-' . uniqid());
    $this->statuses = $this->sandbox . DIRECTORY_SEPARATOR . 'modules_statuses.json';
    File::makeDirectory($this->sandbox . DIRECTORY_SEPARATOR . 'Modules', 0777, true);
    $this->modulesDir = $this->sandbox . DIRECTORY_SEPARATOR . 'Modules';

    $this->svc = fn () => new ModuleManagerService($this->modulesDir, $this->statuses);
});

afterEach(function () {
    File::deleteDirectory($this->sandbox);
});

it('UC-MOD-05 · ordena ativos primeiro, depois área, depois nome', function () {
    // guessArea: 'crm' => Comercial · 'repair' => Operações · 'jana' => IA · sem keyword => Outros
    fakeModule($this->modulesDir, 'Repair', ['alias' => 'repair']);   // Operações, ativo
    fakeModule($this->modulesDir, 'Crm', ['alias' => 'crm']);         // Comercial, ativo
    fakeModule($this->modulesDir, 'Jana', ['alias' => 'jana']);       // IA, INATIVO
    File::put($this->statuses, json_encode(['Repair' => true, 'Crm' => true, 'Jana' => false]));

    $rows = ($this->svc)()->list();

    // ativos antes de inativos
    $ativos = array_map(fn ($m) => $m['active'], $rows);
    expect($ativos)->toBe([true, true, false]);

    // entre os ativos, área alfabética: Comercial (Crm) < Operações (Repair)
    expect(array_column($rows, 'name'))->toBe(['Crm', 'Repair', 'Jana']);
})->group('modules');

it('UC-MOD-07 · módulo com pasta mas ausente do statuses vem como não registrado e inativo', function () {
    fakeModule($this->modulesDir, 'Orfao', ['alias' => 'orfao']);
    File::put($this->statuses, json_encode(['OutroQualquer' => true]));

    $row = collect(($this->svc)()->list())->firstWhere('name', 'Orfao');

    expect($row['registered'])->toBeFalse()
        ->and($row['active'])->toBeFalse();
})->group('modules');

it('UC-MOD-11 · alternar grava a flag daquele módulo e não toca as outras chaves', function () {
    fakeModule($this->modulesDir, 'Alvo', ['alias' => 'alvo']);
    fakeModule($this->modulesDir, 'Vizinho', ['alias' => 'vizinho']);
    File::put($this->statuses, json_encode(['Alvo' => false, 'Vizinho' => true]));

    ($this->svc)()->setActive('Alvo', true);
    expect(json_decode(File::get($this->statuses), true))->toBe(['Alvo' => true, 'Vizinho' => true]);

    ($this->svc)()->setActive('Alvo', false);
    expect(json_decode(File::get($this->statuses), true))->toBe(['Alvo' => false, 'Vizinho' => true]);
})->group('modules');

it('UC-MOD-11 · alternar módulo inexistente é recusado antes de escrever qualquer coisa', function () {
    File::put($this->statuses, json_encode(['Alvo' => true]));

    expect(fn () => ($this->svc)()->setActive('NaoExiste', true))
        ->toThrow(InvalidArgumentException::class);

    expect(json_decode(File::get($this->statuses), true))->toBe(['Alvo' => true]);
})->group('modules');

it('UC-MOD-12 · install de módulo inexistente é recusado e não altera o JSON', function () {
    File::put($this->statuses, json_encode(['Alvo' => true]));
    $antes = File::get($this->statuses);

    expect(fn () => ($this->svc)()->install('NaoExiste', null))
        ->toThrow(InvalidArgumentException::class);

    expect(File::get($this->statuses))->toBe($antes);
})->group('modules');

it('UC-MOD-13 · [ACHADO] install que falha no migrate deixa o módulo ATIVO — a linha mente', function () {
    // `install()` chama setActive(true) ANTES do module:migrate. Migrate de um módulo que o
    // nWidart não conhece falha; a flag já foi gravada e ninguém a reverte.
    fakeModule($this->modulesDir, 'ModuloFantasma', ['alias' => 'modulofantasma'], 1);
    File::put($this->statuses, json_encode(['ModuloFantasma' => false]));

    $result = ($this->svc)()->install('ModuloFantasma', null);

    expect($result['success'])->toBeFalse('migrate de módulo não registrado tem que falhar');

    // CARACTERIZAÇÃO do defeito, não endosso: trava o comportamento ATUAL para que o patch P1
    // (reverter a flag no catch) quebre este teste de propósito e obrigue a atualizar o UC-MOD-13.
    expect(json_decode(File::get($this->statuses), true)['ModuloFantasma'])
        ->toBeTrue('hoje o install falho DEIXA o módulo ativo — é o achado que a MOD-O2/P1 corrige');
})->group('modules');

it('UC-MOD-14 · desativar preserva as tabelas: uninstall só derruba a flag', function () {
    fakeModule($this->modulesDir, 'Alvo', ['alias' => 'alvo'], 2);
    File::put($this->statuses, json_encode(['Alvo' => true]));

    ($this->svc)()->uninstall('Alvo');

    expect(json_decode(File::get($this->statuses), true))->toBe(['Alvo' => false])
        ->and(File::isDirectory($this->modulesDir . '/Alvo/Database/Migrations'))->toBeTrue();
})->group('modules');

it('UC-MOD-17 · mostra a versão declarada no module.json e cai em 0.0 quando o campo não existe', function () {
    fakeModule($this->modulesDir, 'ComVersao', ['alias' => 'comversao', 'version' => '2.1.0']);
    fakeModule($this->modulesDir, 'SemVersao', ['alias' => 'semversao']);
    File::put($this->statuses, json_encode(['ComVersao' => true, 'SemVersao' => true]));

    $byName = collect(($this->svc)()->list())->keyBy('name');

    expect($byName['ComVersao']['version'])->toBe('2.1.0')
        ->and($byName['SemVersao']['version'])->toBe('0.0');
})->group('modules');

it('UC-MOD-18 · sinaliza has_datacontroller, que é o que faz o módulo montar item na sidebar', function () {
    fakeModule($this->modulesDir, 'ComMenu', ['alias' => 'commenu'], 0, true);
    fakeModule($this->modulesDir, 'SemMenu', ['alias' => 'semmenu'], 0, false);
    File::put($this->statuses, json_encode(['ComMenu' => true, 'SemMenu' => true]));

    $byName = collect(($this->svc)()->list())->keyBy('name');

    expect($byName['ComMenu']['has_datacontroller'])->toBeTrue()
        ->and($byName['SemMenu']['has_datacontroller'])->toBeFalse();
})->group('modules');

it('UC-MOD-19 · conta as migrations do módulo e marca has_migrations', function () {
    fakeModule($this->modulesDir, 'ComMig', ['alias' => 'commig'], 3);
    fakeModule($this->modulesDir, 'SemMig', ['alias' => 'semmig'], 0);
    File::put($this->statuses, json_encode([]));

    $byName = collect(($this->svc)()->list())->keyBy('name');

    expect($byName['ComMig']['migration_count'])->toBe(3)
        ->and($byName['ComMig']['has_migrations'])->toBeTrue()
        ->and($byName['SemMig']['migration_count'])->toBe(0)
        ->and($byName['SemMig']['has_migrations'])->toBeFalse();
})->group('modules');
