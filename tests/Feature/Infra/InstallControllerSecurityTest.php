<?php

declare(strict_types=1);

namespace Tests\Feature\Infra;

use App\Http\Controllers\Install\InstallController;
use ReflectionClass;
use Tests\TestCase;

/**
 * US-INFRA-008 — regression test: wipe-DB-via-HTTP guard.
 *
 * Bug histórico: rota POST /install/install (alternate path) chamava
 * `Artisan::call('migrate:fresh', ['--force' => true])` SEM autenticação,
 * permitindo wipe da DB de produção via curl não-autenticado.
 *
 * Fix histórico (commit anterior à US-INFRA-008): `installAlternate()`
 * agora chama `isSystemAlreadyInstalled()` ANTES do migrate:fresh — aborta
 * 403 se há users OR business no DB.
 *
 * Este test trava regressão: se alguém remover o guard, este test falha
 * em CI antes do merge.
 *
 * @see app/Http/Controllers/Install/InstallController.php:265-272
 */
class InstallControllerSecurityTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function installAlternate_method_chama_isSystemAlreadyInstalled_guard(): void
    {
        $reflection = new ReflectionClass(InstallController::class);
        $method = $reflection->getMethod('installAlternate');

        // Pega source code do método via Reflection — em PHP, requer ler arquivo.
        $file = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode(
            '',
            array_slice(file($file), $startLine - 1, $endLine - $startLine + 1)
        );

        $this->assertStringContainsString(
            'isSystemAlreadyInstalled',
            $source,
            'installAlternate() DEVE chamar $this->isSystemAlreadyInstalled() antes de migrate:fresh. ' .
            'Sem isso, qualquer POST não-autenticado em /install/install wipa a DB. ' .
            'Bug US-INFRA-008 (CYCLE-03).'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function isSystemAlreadyInstalled_metodo_existe_e_retorna_bool(): void
    {
        $reflection = new ReflectionClass(InstallController::class);

        $this->assertTrue(
            $reflection->hasMethod('isSystemAlreadyInstalled'),
            'Método isSystemAlreadyInstalled() é o guard pra US-INFRA-008. ' .
            'Não pode ser removido.'
        );

        $method = $reflection->getMethod('isSystemAlreadyInstalled');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType, 'isSystemAlreadyInstalled() deve declarar return type bool');
        $this->assertSame(
            'bool',
            $returnType->getName(),
            'isSystemAlreadyInstalled() deve retornar bool'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function runArtisanCommands_roda_migrate_fresh_destrutivo(): void
    {
        // Garante que runArtisanCommands de fato é destrutivo — se alguém remover
        // migrate:fresh, este test falha e force review (talvez bug, talvez fix
        // legítimo, mas precisa decisão consciente).
        $reflection = new ReflectionClass(InstallController::class);
        $method = $reflection->getMethod('runArtisanCommands');

        $file = $reflection->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode(
            '',
            array_slice(file($file), $startLine - 1, $endLine - $startLine + 1)
        );

        $this->assertStringContainsString(
            "migrate:fresh",
            $source,
            'runArtisanCommands() é o método destrutivo (migrate:fresh + db:seed). ' .
            'Se mudar pra migrate sem fresh, o guard isSystemAlreadyInstalled vira opcional ' .
            '— remova o test installAlternate_method_chama_isSystemAlreadyInstalled_guard.'
        );
    }
}
