<?php

use App\Http\Controllers\BaseModuleInstallController;

/**
 * Trava regressão do bug 2026-05-13 (OficinaAuto + ComunicacaoVisual).
 *
 * `app/Utils/ModuleUtil.php:31` faz `strtolower($moduleName).'_version'` pra
 * resolver isModuleInstalled(). Se moduleSystemKey() retornar kebab-case (com
 * hífen), Install grava chave errada no `system` table → sidebar nunca monta.
 *
 * Convention: moduleSystemKey() === strtolower(moduleName()) — lowercase,
 * sem hífen, sem underscore.
 */
it('todo InstallController retorna moduleSystemKey === strtolower(moduleName)', function () {
    $installControllers = glob(base_path('Modules/*/Http/Controllers/InstallController.php'));

    expect($installControllers)->not->toBeEmpty();

    $violations = [];

    foreach ($installControllers as $file) {
        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);

        // Extrai classe via namespace + nome
        preg_match('/namespace\s+([^;]+);/', file_get_contents($file), $nsMatch);
        if (!isset($nsMatch[1])) continue;

        $class = trim($nsMatch[1]) . '\\InstallController';
        if (!class_exists($class)) continue;

        // Só checa quem extends BaseModuleInstallController
        $reflection = new ReflectionClass($class);
        if (!$reflection->isSubclassOf(BaseModuleInstallController::class)) continue;
        if ($reflection->isAbstract()) continue;

        $instance = $reflection->newInstanceWithoutConstructor();

        $moduleNameMethod = $reflection->getMethod('moduleName');
        $moduleNameMethod->setAccessible(true);
        $name = $moduleNameMethod->invoke($instance);

        $moduleKeyMethod = $reflection->getMethod('moduleSystemKey');
        $moduleKeyMethod->setAccessible(true);
        $key = $moduleKeyMethod->invoke($instance);

        $expectedKey = strtolower($name);

        if ($key !== $expectedKey) {
            $violations[] = "{$relative}: moduleName='{$name}' but moduleSystemKey='{$key}' (esperado: '{$expectedKey}')";
        }
    }

    expect($violations)->toBeEmpty(
        "InstallControllers com moduleSystemKey inconsistente:\n" . implode("\n", $violations)
    );
});
