<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\System;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Carimba `1.0` em `system.<modulo>_version` para módulo que NÃO tem versão instalada
 * em lugar nenhum — o piso da coluna Versão da tela /modulos (decisão D1 [W], 2026-08-20).
 *
 * POR QUE UM COMANDO E NÃO UM UPDATE DIRETO: escrita em banco fora de migration exige
 * seeder ou comando artisan idempotente registrado (proibicoes.md §"Mexeu, REGISTRA").
 * `tinker` ad-hoc em produção é drift por construção.
 *
 * A RESOLUÇÃO É A MESMA DO SERVICE, de propósito — nome, depois alias. Sem o passo do
 * alias este comando carimbaria `1.0` em módulo RENOMEADO que já tem versão sob a chave
 * antiga, rebaixando-o. Medido em produção 2026-08-20: o `Forja` cairia nessa armadilha
 * (`forja_version` ausente, `projectmgmt_version` = 0.1, documentado como legacy no
 * module.json dele). Com o alias ele é corretamente PULADO.
 *
 * ⚠️ Nunca sobrescreve valor existente. Só cria o que falta.
 */
class ModulosVersaoInicial extends Command
{
    protected $signature = 'modulos:versao-inicial
                            {--dry-run : Só mostra o que faria, sem escrever}
                            {--versao=1.0 : Valor a carimbar em quem não tem nenhum}';

    protected $description = 'Cria system.<modulo>_version=1.0 para módulos sem versão instalada (idempotente)';

    public function handle(): int
    {
        if (! Schema::hasTable('system')) {
            $this->error('Tabela `system` ausente — nada a fazer nesta base.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $versao = (string) $this->option('versao');
        $modulesDir = base_path('Modules');

        if (! File::isDirectory($modulesDir)) {
            $this->error("Diretório {$modulesDir} não existe.");

            return self::FAILURE;
        }

        $criados = [];
        $pulados = [];

        foreach (File::directories($modulesDir) as $modulePath) {
            $name = basename($modulePath);
            $moduleJson = [];
            $jsonPath = $modulePath . DIRECTORY_SEPARATOR . 'module.json';
            if (File::exists($jsonPath)) {
                $decoded = json_decode(File::get($jsonPath), true);
                $moduleJson = is_array($decoded) ? $decoded : [];
            }

            // MESMA cadeia do ModuleManagerService::resolverVersao — nome, depois alias.
            $chaves = [strtolower($name) . '_version'];
            $alias = $moduleJson['alias'] ?? null;
            if (is_string($alias) && $alias !== '' && strtolower($alias) !== strtolower($name)) {
                $chaves[] = strtolower($alias) . '_version';
            }

            $achou = null;
            foreach ($chaves as $chave) {
                $valor = System::getProperty($chave);
                if (is_string($valor) && $valor !== '') {
                    $achou = "{$chave}={$valor}";
                    break;
                }
            }

            if ($achou !== null) {
                $pulados[$name] = $achou;

                continue;
            }

            $criados[$name] = $chaves[0];

            if (! $dryRun) {
                System::updateOrCreate(['key' => $chaves[0]], ['value' => $versao]);
            }
        }

        $this->line(sprintf(
            '%s · %d módulo(s) já com versão · %d sem',
            $dryRun ? 'DRY-RUN (nada escrito)' : 'APLICADO',
            count($pulados),
            count($criados)
        ));

        foreach ($criados as $name => $chave) {
            $this->line(sprintf('  %s %s = %s', $dryRun ? 'criaria' : 'criado ', $chave, $versao));
        }

        if ($criados === []) {
            $this->info('Nada a criar — todo módulo já tem versão resolvível (nome ou alias).');
        }

        return self::SUCCESS;
    }
}
