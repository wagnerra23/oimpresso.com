<?php

namespace App\Services;

use App\Models\System;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * Gerencia o ciclo de vida dos módulos nwidart instalados em `Modules/`.
 *
 * Fonte de verdade:
 *  - `modules_statuses.json` na raiz — flag ativo/inativo por módulo
 *  - `Modules/<Nome>/module.json` — metadata (nome, alias, versão, descrição)
 *  - `Modules/<Nome>/Database/Migrations/` — detecta se há migrations
 *
 * Operações:
 *  - list()      : lista TODOS os módulos + estado
 *  - setActive() : flipa modules_statuses.json (toggle simples)
 *  - install()   : ativa + roda migrations
 *  - uninstall() : desativa (sem drop de tabelas — seguro por default)
 *
 * Segurança: todos os métodos mutator devem ser chamados apenas por users
 * admin (verificação no Controller, não aqui).
 */
class ModuleManagerService
{
    protected string $modulesDir;
    protected string $statusesFile;

    /**
     * Classificação por área para agrupar na UI. Palavra-chave no nome do
     * módulo → área. Fallback: "Outros".
     */
    protected array $areaMap = [
        'ponto'       => 'Recursos Humanos',
        'hms'         => 'Recursos Humanos',
        'essentials'  => 'Recursos Humanos',
        'crm'         => 'Comercial',
        'ecommerce'   => 'Comercial',
        'woocommerce' => 'Comercial',
        'project'     => 'Operações',
        'repair'      => 'Operações',
        'manufactur'  => 'Operações',
        'iproduction' => 'Operações',
        'asset'       => 'Operações',
        'fieldforce'  => 'Operações',
        'accounting'  => 'Financeiro',
        'boleto'      => 'Financeiro',
        'fiscal'      => 'Financeiro',
        'nfe'         => 'Financeiro',
        'aiassistance'=> 'IA',
        'jana'        => 'IA',
        'chat'        => 'Comunicação',
        'inboxreport' => 'Comunicação',
        'connector'   => 'Integrações',
        'spreadsheet' => 'Integrações',
        'productcatalogue' => 'Catálogo',
        'catalogue'   => 'Catálogo',
        'customdashboard' => 'Administração',
        'superadmin'  => 'Administração',
        'cms'         => 'Conteúdo',
        'officeimpresso' => 'Office Impresso',
        'grow'        => 'Office Impresso',
    ];

    public function __construct()
    {
        $this->modulesDir = base_path('Modules');
        $this->statusesFile = base_path('modules_statuses.json');
    }

    /**
     * Lista todos os módulos em `Modules/` com seu estado.
     *
     * @return array<int, array{
     *   name: string,
     *   alias: string,
     *   version: string,
     *   description: string,
     *   area: string,
     *   active: bool,
     *   registered: bool,
     *   has_migrations: bool,
     *   migration_count: int,
     *   has_datacontroller: bool,
     *   error: string|null
     * }>
     */
    public function list(): array
    {
        $statuses = $this->readStatuses();

        $modules = [];
        if (!File::isDirectory($this->modulesDir)) {
            return [];
        }

        foreach (File::directories($this->modulesDir) as $modulePath) {
            $name = basename($modulePath);
            $error = null;

            try {
                $moduleJsonPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';
                $moduleJson = [];
                // P2 — 'Com erro' precisa ser alcançável. json_decode de um module.json
                // malformado devolve null SEM lançar, então o catch abaixo nunca via esse
                // caso e o badge/KPI 'Com erro' era decorativo. Alinhado com o que
                // tests/Feature/Audit/ModuleScaffoldingTest.php já reprova (providers[]).
                if (File::exists($moduleJsonPath)) {
                    $decoded = json_decode(File::get($moduleJsonPath), true);
                    if (! is_array($decoded)) {
                        $error = 'module.json inválido: ' . json_last_error_msg();
                    } else {
                        $moduleJson = $decoded;
                        if (empty($moduleJson['providers'])) {
                            $error = 'module.json sem providers[] — o módulo não é carregado.';
                        }
                    }
                } else {
                    $error = 'module.json ausente.';
                }

                $migrationsDir = $modulePath . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
                $migrationCount = File::isDirectory($migrationsDir)
                    ? count(File::glob($migrationsDir . DIRECTORY_SEPARATOR . '*.php'))
                    : 0;

                $hasDataController = File::exists(
                    $modulePath . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DataController.php'
                );

                // P4 (decisão D1) — nenhum dos 32 module.json declara "version", o que fazia a
                // tela mostrar v0.0 em toda linha. A versão que interessa ao operador é a
                // INSTALADA, gravada em system.<alias>_version (a mesma que
                // ModuleUtil::isModuleInstalled() consulta). Se [W] decidir declarar version
                // nos module.json, o primeiro ramo do ?? passa a valer sozinho.
                $installedVersion = System::getProperty(strtolower($name) . '_version');

                $modules[] = [
                    'name'               => $name,
                    'alias'              => $moduleJson['alias']       ?? strtolower($name),
                    'version'            => (string) ($moduleJson['version'] ?? $installedVersion ?? '—'),
                    'description'        => $moduleJson['description'] ?? '',
                    'area'               => $this->guessArea($name),
                    'active'             => $statuses[$name] ?? false,
                    'registered'         => array_key_exists($name, $statuses),
                    'has_migrations'     => $migrationCount > 0,
                    'migration_count'    => $migrationCount,
                    'has_datacontroller' => $hasDataController,
                    'error'              => $error,
                ];
            } catch (Throwable $e) {
                $modules[] = [
                    'name'               => $name,
                    'alias'              => strtolower($name),
                    'version'            => '?',
                    'description'        => '',
                    'area'               => 'Outros',
                    'active'             => $statuses[$name] ?? false,
                    'registered'         => array_key_exists($name, $statuses),
                    'has_migrations'     => false,
                    'migration_count'    => 0,
                    'has_datacontroller' => false,
                    'error'              => $e->getMessage(),
                ];
            }
        }

        // Ordenação: ativos primeiro, depois por área, depois por nome
        usort($modules, function ($a, $b) {
            if ($a['active'] !== $b['active']) return $a['active'] ? -1 : 1;
            if ($a['area'] !== $b['area']) return strcmp($a['area'], $b['area']);
            return strcmp($a['name'], $b['name']);
        });

        return $modules;
    }

    /**
     * Ativa/desativa um módulo em modules_statuses.json.
     * Não roda migrations — só flipa a flag.
     */
    public function setActive(string $name, bool $active): void
    {
        if (!$this->moduleExists($name)) {
            throw new \InvalidArgumentException("Módulo '{$name}' não existe em Modules/.");
        }

        $statuses = $this->readStatuses();
        $statuses[$name] = $active;
        $this->writeStatuses($statuses);

        // Limpa caches do Laravel pra refletir
        Artisan::call('cache:clear');
        Artisan::call('config:clear');
    }

    /**
     * Ativa + roda migrations + chama comando <modulo>:install se existir.
     *
     * Convenção: módulos podem ter `Modules/<Name>/Console/Commands/InstallCommand.php`
     * exposto como artisan command `<alias>:install` (ex: `financeiro:install`).
     * Esse comando faz setup pós-migration: permissões Spatie nos roles, habilita
     * no package do business corrente, seedpa dados iniciais, etc.
     *
     * @param  int|null  $businessId  Business ID para passar pro install command (default: session)
     * @return array{success: bool, output: string, install_output: string|null}
     */
    public function install(string $name, ?int $businessId = null): array
    {
        if (!$this->moduleExists($name)) {
            throw new \InvalidArgumentException("Módulo '{$name}' não existe em Modules/.");
        }

        // Primeiro ativa
        $this->setActive($name, true);

        try {
            Artisan::call('module:migrate', [
                'module' => $name,
                '--force' => true,
            ]);
            $migrateOutput = Artisan::output();

            // Tenta chamar <modulo>:install pra setup pós-migrations
            $installOutput = $this->runModuleInstallCommand($name, $businessId);

            return [
                'success' => true,
                'output'  => $migrateOutput,
                'install_output' => $installOutput,
            ];
        } catch (Throwable $e) {
            // P1 (UC-MOD-13) — o setActive(true) acontece ANTES do migrate porque o nWidart
            // só registra módulo habilitado. Se o migrate falhou, o estado tem de voltar:
            // senão /modulos afirma "Ativo" para um módulo com schema incompleto.
            // Atenção: migrations já aplicadas antes da exceção NÃO são revertidas — a
            // mensagem precisa dizer isso ao operador.
            $this->setActive($name, false);

            return [
                'success' => false,
                'output'  => $e->getMessage() . ' (migrations podem ter sido parcialmente aplicadas; corrija e rode novamente)',
                'install_output' => null,
            ];
        }
    }

    /**
     * Detecta e chama `<modulo-alias>:install` se o módulo tiver tal comando.
     * Convenção: arquivo `Modules/<Name>/Console/Commands/InstallCommand.php`
     * registrado como artisan command `<alias>:install`.
     */
    protected function runModuleInstallCommand(string $name, ?int $businessId): ?string
    {
        $cmdFile = $this->modulesDir . DIRECTORY_SEPARATOR . $name
            . DIRECTORY_SEPARATOR . 'Console'
            . DIRECTORY_SEPARATOR . 'Commands'
            . DIRECTORY_SEPARATOR . 'InstallCommand.php';

        if (!File::exists($cmdFile)) {
            return null; // módulo não tem InstallCommand opcional
        }

        // Lê alias do module.json
        $moduleJsonPath = $this->modulesDir . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . 'module.json';
        $alias = strtolower($name);
        if (File::exists($moduleJsonPath)) {
            $json = json_decode(File::get($moduleJsonPath), true) ?? [];
            $alias = strtolower($json['alias'] ?? $name);
        }

        $command = "{$alias}:install";

        // Verifica se comando está realmente registrado
        if (!array_key_exists($command, Artisan::all())) {
            return "[skip] Comando '{$command}' não registrado no Artisan.";
        }

        $params = [];
        $businessId = $businessId ?? (int) session('user.business_id');
        if ($businessId > 0) {
            $params['--business'] = $businessId;
        } else {
            $params['--all'] = true;
        }

        try {
            Artisan::call($command, $params);
            return Artisan::output();
        } catch (Throwable $e) {
            return "[erro] {$command}: {$e->getMessage()}";
        }
    }

    /**
     * Apenas DESATIVA. Não derruba tabelas — seguro por default.
     * (Remoção física exige módulo-específico rollback, que é perigoso.)
     */
    public function uninstall(string $name): void
    {
        $this->setActive($name, false);
    }

    public function moduleExists(string $name): bool
    {
        return File::isDirectory($this->modulesDir . DIRECTORY_SEPARATOR . $name);
    }

    // ============================================================
    // Helpers privados
    // ============================================================

    protected function readStatuses(): array
    {
        if (!File::exists($this->statusesFile)) {
            return [];
        }
        $data = json_decode(File::get($this->statusesFile), true);
        return is_array($data) ? $data : [];
    }

    protected function writeStatuses(array $statuses): void
    {
        // Ordena alfabético para diffs estáveis em git
        ksort($statuses);
        File::put(
            $this->statusesFile,
            json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
        );
    }

    protected function guessArea(string $name): string
    {
        $lower = strtolower($name);
        foreach ($this->areaMap as $kw => $area) {
            if (str_contains($lower, $kw)) return $area;
        }
        return 'Outros';
    }
}
