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
 * UCs: 05, 07, 11, 12, 13, 14, 17, 18, 19, 20.
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

it('UC-MOD-13 - install que falha no migrate NAO deixa o modulo marcado como ativo', function () {
    // Era caracterizacao do defeito ate 2026-08-19 (P1). Agora exige o comportamento certo:
    // migrate de modulo que o nWidart nao conhece falha, e a flag tem que voltar ao que era.
    fakeModule($this->modulesDir, 'ModuloFantasma', ['alias' => 'modulofantasma'], 1);
    File::put($this->statuses, json_encode(['ModuloFantasma' => false]));

    $result = ($this->svc)()->install('ModuloFantasma', null);

    expect($result['success'])->toBeFalse('migrate de modulo nao registrado tem que falhar')
        ->and(json_decode(File::get($this->statuses), true)['ModuloFantasma'])
        ->toBeFalse('install que falhou nao pode deixar o modulo marcado como ativo');
})->group('modules');

it('UC-MOD-13 - reinstalar que falha preserva o modulo ATIVO, nao o derruba', function () {
    // O patch [CC] propunha setActive(false) no catch. Isso conserta o caso "instalar" e
    // QUEBRA o caso "reinstalar": um modulo que ja estava ativo e funcionando seria
    // desativado por causa de um migrate que falhou. O certo e restaurar o estado ANTERIOR.
    fakeModule($this->modulesDir, 'ModuloFantasma', ['alias' => 'modulofantasma'], 1);
    File::put($this->statuses, json_encode(['ModuloFantasma' => true]));

    $result = ($this->svc)()->install('ModuloFantasma', null);

    expect($result['success'])->toBeFalse()
        ->and(json_decode(File::get($this->statuses), true)['ModuloFantasma'])
        ->toBeTrue('reinstalar que falha nao pode derrubar modulo que ja estava ativo');
})->group('modules');

it('UC-MOD-20 - modulo sem module.json aparece Com erro, nao silenciosamente OK', function () {
    fakeModule($this->modulesDir, 'SemJson', []); // sem module.json
    File::put($this->statuses, json_encode(['SemJson' => true]));

    $row = collect(($this->svc)()->list())->firstWhere('name', 'SemJson');

    expect($row['error'])->toContain('module.json ausente');
})->group('modules');

it('UC-MOD-20 - module.json malformado aparece Com erro (json_decode nao lanca)', function () {
    $path = $this->modulesDir . DIRECTORY_SEPARATOR . 'JsonQuebrado';
    File::makeDirectory($path, 0777, true);
    File::put($path . DIRECTORY_SEPARATOR . 'module.json', '{ "alias": "quebrado", ');
    File::put($this->statuses, json_encode(['JsonQuebrado' => true]));

    $row = collect(($this->svc)()->list())->firstWhere('name', 'JsonQuebrado');

    // prefixo sem acento: a mensagem do Service e 'module.json invalido: <motivo>' com acento
    // no 'a'; casar pelo prefixo evita depender do encoding do assert.
    expect($row['error'])->toContain('module.json inv');
})->group('modules');

it('UC-MOD-20 - module.json sem providers[] aparece Com erro (o modulo nao carrega)', function () {
    fakeModule($this->modulesDir, 'SemProviders', ['alias' => 'semproviders']);
    fakeModule($this->modulesDir, 'ComProviders', ['alias' => 'comproviders', 'providers' => ['X\Y\Z']]);
    File::put($this->statuses, json_encode(['SemProviders' => true, 'ComProviders' => true]));

    $byName = collect(($this->svc)()->list())->keyBy('name');

    expect($byName['SemProviders']['error'])->toContain('providers')
        ->and($byName['ComProviders']['error'])->toBeNull();
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
