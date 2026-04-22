<?php

namespace App\Services;

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
        'writebot'    => 'IA',
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
                if (File::exists($moduleJsonPath)) {
                    $moduleJson = json_decode(File::get($moduleJsonPath), true) ?? [];
                }

                $migrationsDir = $modulePath . DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations';
                $migrationCount = File::isDirectory($migrationsDir)
                    ? count(File::glob($migrationsDir . DIRECTORY_SEPARATOR . '*.php'))
                    : 0;

                $hasDataController = File::exists(
                    $modulePath . DIRECTORY_SEPARATOR . 'Http' . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . 'DataController.php'
                );

                $modules[] = [
                    'name'               => $name,
                    'alias'              => $moduleJson['alias']       ?? strtolower($name),
                    'version'            => (string) ($moduleJson['version'] ?? '0.0'),
                    'description'        => $moduleJson['description'] ?? '',
                    'area'               => $this->guessArea($name),
                    'active'             => $statuses[$name] ?? false,
                    'registered'         => array_key_exists($name, $statuses),
                    'has_migrations'     => $migrationCount > 0,
                    'migration_count'    => $migrationCount,
                    'has_datacontroller' => $hasDataController,
                    'error'              => null,
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
     * Ativa + roda migrations (first-time install).
     *
     * @return array{success: bool, output: string}
     */
    public function install(string $name): array
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

            return [
                'success' => true,
                'output'  => Artisan::output(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'output'  => $e->getMessage(),
            ];
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
