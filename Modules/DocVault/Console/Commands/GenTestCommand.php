<?php

namespace Modules\DocVault\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\DocVault\Services\RequirementsFileReader;

/**
 * Gera stub de teste Pest (ou PHPUnit) a partir do bloco Gherkin de uma regra
 * R-XXX-NNN definida em SPEC.md.
 *
 * Uso:
 *   php artisan docvault:gen-test R-PONTO-002
 *   php artisan docvault:gen-test R-DOCVAULT-001 --style=phpunit
 *   php artisan docvault:gen-test R-DOCVAULT-001 --out=custom/path.php
 */
class GenTestCommand extends Command
{
    protected $signature = 'docvault:gen-test
                            {rule : ID da regra (ex: R-PONTO-002)}
                            {--style=pest : Estilo de teste (pest ou phpunit)}
                            {--out= : Caminho custom pro arquivo de saída}
                            {--force : Sobrescreve arquivo existente}';

    protected $description = 'Gera stub de teste a partir do Gherkin de uma regra R-XXX-NNN';

    public function handle(RequirementsFileReader $reader): int
    {
        $ruleId = strtoupper($this->argument('rule'));
        if (! preg_match('/^R-([A-Z]+)-\d+$/', $ruleId, $m)) {
            $this->error("ID de regra inválido: {$ruleId}. Use formato R-AREA-NNN (ex: R-PONTO-002).");
            return 1;
        }
        $area = $m[1];

        // Descobre o módulo pelo prefixo da regra
        $moduleName = $this->findModuleByArea($reader, $area);
        if (! $moduleName) {
            $this->error("Nenhum módulo encontrado que contenha a regra {$ruleId}.");
            return 1;
        }

        $data = $reader->readModule($moduleName);
        $rule = collect($data['rules'])->firstWhere('id', $ruleId);
        if (! $rule) {
            $this->error("Regra {$ruleId} não encontrada em {$moduleName}/SPEC.md.");
            return 1;
        }

        // Extrai o bloco Gherkin (`gherkin` block na seção da regra)
        $gherkin = $this->extractGherkin($data['raw'], $ruleId);

        $style = $this->option('style');
        $className = $this->className($ruleId);
        $outPath = $this->option('out')
            ?: base_path("Modules/{$moduleName}/Tests/Feature/{$className}.php");

        if (File::exists($outPath) && ! $this->option('force')) {
            $this->error("Arquivo {$outPath} já existe. Use --force pra sobrescrever.");
            return 1;
        }

        File::ensureDirectoryExists(dirname($outPath));

        $stub = $style === 'phpunit'
            ? $this->phpunitStub($moduleName, $ruleId, $rule['title'], $gherkin, $className)
            : $this->pestStub($moduleName, $ruleId, $rule['title'], $gherkin);

        File::put($outPath, $stub);

        $this->info("✓ Stub gerado: {$outPath}");
        $this->line("  Próximos passos:");
        $this->line("  1. Abra o arquivo e implemente o corpo do teste.");
        $this->line("  2. No SPEC.md, atualize **Testado em:** `{$this->testReference($moduleName, $className, $style)}`");
        $this->line("  3. Rode: vendor/bin/pest Modules/{$moduleName}/Tests/Feature/{$className}.php");

        return 0;
    }

    protected function findModuleByArea(RequirementsFileReader $reader, string $area): ?string
    {
        foreach ($reader->listModules() as $m) {
            $data = $reader->readModule($m['name']);
            if (! $data) continue;
            foreach ($data['rules'] as $r) {
                if (str_starts_with($r['id'], "R-{$area}-")) {
                    return $m['name'];
                }
            }
        }
        return null;
    }

    protected function extractGherkin(string $body, string $ruleId): string
    {
        if (preg_match('/###\s+' . preg_quote($ruleId, '/') . '\s+·.*?```gherkin\s+(.+?)```/s', $body, $m)) {
            return trim($m[1]);
        }
        return "# (sem bloco gherkin explícito — defina o cenário manualmente)";
    }

    protected function className(string $ruleId): string
    {
        // R-PONTO-002 → RulePonto002Test
        $parts = explode('-', $ruleId);
        return 'Rule' . ucfirst(strtolower($parts[1])) . str_pad($parts[2], 3, '0', STR_PAD_LEFT) . 'Test';
    }

    protected function testReference(string $module, string $class, string $style): string
    {
        $path = "Modules/{$module}/Tests/Feature/{$class}";
        return $style === 'phpunit' ? "{$path}::test_rule_holds" : $path;
    }

    protected function pestStub(string $module, string $ruleId, string $title, string $gherkin): string
    {
        $gherkinLines = implode("\n * ", explode("\n", $gherkin));

        return <<<PHP
<?php

/**
 * Stub gerado por docvault:gen-test pra regra {$ruleId}.
 *
 * **Regra**: {$title}
 *
 * **Gherkin**:
 * {$gherkinLines}
 *
 * TODO: implementar o corpo do teste e remover markTestIncomplete.
 * Depois, atualizar o campo **Testado em:** no SPEC.md do módulo {$module}.
 */

test('{$ruleId} · {$title}', function () {
    \$this->markTestIncomplete('Implementar cenário Gherkin acima.');

    // Exemplo de estrutura (ajuste ao seu caso):
    // 1. Arrange: prepare o estado (Dado que...)
    // 2. Act: execute a ação (Quando...)
    // 3. Assert: verifique o resultado (Então...)
});

PHP;
    }

    protected function phpunitStub(string $module, string $ruleId, string $title, string $gherkin, ?string $className = null): string
    {
        $className = $className ?? $this->className($ruleId);
        $gherkinLines = implode("\n     * ", explode("\n", $gherkin));

        return <<<PHP
<?php

namespace Modules\\{$module}\\Tests\\Feature;

use Tests\\TestCase;

/**
 * Stub gerado por docvault:gen-test pra regra {$ruleId}.
 *
 * **Regra**: {$title}
 *
 * **Gherkin**:
 * {$gherkinLines}
 */
class {$className} extends TestCase
{
    public function test_rule_holds(): void
    {
        \$this->markTestIncomplete('Implementar cenário Gherkin acima.');

        // 1. Arrange: prepare o estado (Dado que...)
        // 2. Act: execute a ação (Quando...)
        // 3. Assert: verifique o resultado (Então...)
    }
}

PHP;
    }
}
