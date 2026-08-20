<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Paths injetáveis (default = raiz do app). Existem para que o teste possa
     * exercitar o ciclo de vida num diretório sandbox — sem eles, qualquer teste
     * de `setActive`/`install` escreveria no `modules_statuses.json` REAL do repo.
     * Nenhuma mudança de comportamento: o default é exatamente o que era fixo aqui.
     */
    public function __construct(?string $modulesDir = null, ?string $statusesFile = null)
    {
        $this->modulesDir = $modulesDir ?? base_path('Modules');
        $this->statusesFile = $statusesFile ?? base_path('modules_statuses.json');
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
                // O status "Com erro" só era alcançável por Throwable na LEITURA — ou seja,
                // quase nunca: `json_decode` de um JSON malformado devolve null sem lançar.
                // Aqui as 3 causas reais viram `error` (medido 2026-08-19: 0 dos 32 módulos
                // acende hoje — o detector nasce escuro e só fala de módulo de fato quebrado).
                if (File::exists($moduleJsonPath)) {
                    $decoded = json_decode(File::get($moduleJsonPath), true);
                    if (! is_array($decoded)) {
                        $error = 'module.json inválido: ' . json_last_error_msg();
                    } else {
                        $moduleJson = $decoded;
                        if (empty($moduleJson['providers'])) {
                            // Não é fallback silencioso: o erro vai pra tela E pro log. Um módulo
                            // sem providers[] não é carregado pelo nWidart, então quem investiga
                            // "instalei e não apareceu" acha o rastro aqui (ADR 0212 Camada 2).
                            Log::warning('modulo sem providers[] no module.json', [
                                'module' => $name,
                                'path'   => $moduleJsonPath,
                            ]);
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

                $modules[] = [
                    'name'               => $name,
                    'alias'              => $moduleJson['alias']       ?? strtolower($name),
                    'version'            => $this->resolverVersao($name, $moduleJson),
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

        // O ativar precede o migrate porque o nWidart só registra módulo habilitado.
        // Guarda o estado de ANTES: se o migrate falhar, o certo é voltar pra ele — e não
        // pra `false`. Num "Reinstalar" o módulo já estava ativo, e desativá-lo por causa de
        // um migrate que falhou derrubaria um módulo que estava funcionando.
        $estadoAnterior = $this->readStatuses()[$name] ?? false;

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
            // Sem isto a tela afirma "Ativo" para um módulo com schema incompleto (UC-MOD-13).
            // ⚠️ Migrations que JÁ rodaram antes da exceção NÃO são revertidas — quem avisa
            // disso é o toast do Controller; aqui só o estado do statuses volta.
            $this->setActive($name, $estadoAnterior);

            return [
                'success' => false,
                'output'  => $e->getMessage(),
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

    /**
     * Versao a EXIBIR na linha. Ordem de fonte, da mais especifica pra mais geral:
     *
     *   1. `module.json` `version`  — o que o modulo DECLARA de si
     *   2. `system.<nome>_version`  — o que o INSTALL gravou (a convencao do
     *      `ModuleUtil::isModuleInstalled`, travada pelo InstallControllerKeyConventionTest)
     *   3. `system.<alias>_version` — a mesma coisa sob o ALIAS, que e o que salva modulo
     *      RENOMEADO. Medido 2026-08-20 em producao: `Forja` nao tem `forja_version`, mas tem
     *      `projectmgmt_version` = 0.1, e o proprio module.json dele documenta essa row como
     *      "legacy por compat". Sem este passo o modulo aparece sem versao tendo uma.
     *   4. `null` — a tela mostra "—". NAO existe mais fallback '0.0': as 32 linhas diziam
     *      v0.0 porque nenhum module.json declara `version`, e numero falso ensina o operador
     *      a ignorar a coluna (decisao D1 [W], 2026-08-20).
     *
     * ⚠️ NAO confundir com `system.project_version` = 2.1, que e do modulo "Project" ORIGINAL
     * do UltimatePOS — outro modulo, nao o Forja. A busca por alias evita justamente esse
     * tipo de atribuicao errada.
     *
     * A tabela `system` pode nao existir (base de teste sem schema): nesse caso a versao
     * instalada e INDISPONIVEL, nao ausente — o metodo devolve o que tiver de declarado.
     */
    protected function resolverVersao(string $name, array $moduleJson): ?string
    {
        $declarada = $moduleJson['version'] ?? null;
        if (is_string($declarada) && $declarada !== '') {
            return $declarada;
        }

        if (! Schema::hasTable('system')) {
            return null;
        }

        $chaves = [strtolower($name) . '_version'];

        $alias = $moduleJson['alias'] ?? null;
        if (is_string($alias) && $alias !== '' && strtolower($alias) !== strtolower($name)) {
            $chaves[] = strtolower($alias) . '_version';
        }

        foreach ($chaves as $chave) {
            $instalada = \App\System::getProperty($chave);
            if (is_string($instalada) && $instalada !== '') {
                return $instalada;
            }
        }

        return null;
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
