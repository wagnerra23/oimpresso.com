<?php

namespace Modules\Grow\Tests\Feature;

use Modules\Grow\Http\Controllers\InstallController;

/**
 * Smoke tests para Modules/Grow/Http/Controllers/InstallController.
 *
 * Garante que:
 *   - As rotas /grow/install* existem e exigem autenticação (redirect /login)
 *   - InstallController estende BaseModuleInstallController (padrão ADR 0024)
 *   - Os métodos abstratos retornam strings esperadas
 *
 * Métodos prefixados com `test_` para PHPUnit 12 (anotação @test foi removida).
 */
class InstallControllerTest extends GrowTestCase
{
    public function test_install_index_exige_autenticacao(): void
    {
        $this->skipIfModuleDisabled();
        $this->assertRedirectsToLogin($this->get('/grow/install'));
    }

    public function test_install_post_exige_autenticacao(): void
    {
        $this->skipIfModuleDisabled();
        $response = $this->post('/grow/install');
        $this->assertContains($response->status(), [302, 401, 405, 419]);
    }

    public function test_install_uninstall_exige_autenticacao(): void
    {
        $this->skipIfModuleDisabled();
        $this->assertRedirectsToLogin($this->get('/grow/install/uninstall'));
    }

    public function test_install_update_exige_autenticacao(): void
    {
        $this->skipIfModuleDisabled();
        $this->assertRedirectsToLogin($this->get('/grow/install/update'));
    }

    public function test_install_controller_estende_base_module_install_controller(): void
    {
        $reflection = new \ReflectionClass(InstallController::class);
        $parent = $reflection->getParentClass();

        $this->assertNotFalse($parent, 'InstallController deveria ter classe pai');
        $this->assertSame(
            'App\\Http\\Controllers\\BaseModuleInstallController',
            $parent->getName(),
            'Grow/InstallController deve estender BaseModuleInstallController (ADR 0024).'
        );
    }

    public function test_install_controller_expoe_module_metadata(): void
    {
        $reflection = new \ReflectionClass(InstallController::class);

        $methodName = $reflection->getMethod('moduleName');
        $methodName->setAccessible(true);
        $methodKey = $reflection->getMethod('moduleSystemKey');
        $methodKey->setAccessible(true);

        $instance = $reflection->newInstanceWithoutConstructor();

        $this->assertSame('Grow', $methodName->invoke($instance));
        $this->assertSame('grow', $methodKey->invoke($instance));
    }
}
