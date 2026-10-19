<?php

// Tests\TestCase já é aplicado globalmente em tests/Pest.php — não redeclarar.

/**
 * Departamento (grupo do sidebar) de 3 módulos — decisão [W] 2026-10-19:
 * "a Produção deve ir para PRODUÇÃO, a Fisio deve ir para VENDAS, o Arquivos deve ir para SISTEMA".
 *
 * VENDAS = o grupo COMERCIAL do sidebar (key 'comercial', "CRM · Vendas" no GROUP_META do
 * protótipo `prototipo-ui/cowork/Wagner/data.jsx`); não existe grupo com label VENDAS.
 *
 * Regra 2026-05-19: o DataController DECLARA o grupo e o frontend lê (`findGroupKey` passo 1).
 * Sem `group`, 'Produção' casava a whitelist de CADASTRO por label e Fisio/Arquivos caíam no
 * fallback MAIS. O teste exige a declaração dentro do `modifyAdminMenu`, onde ela vale.
 */
dataset('departamentos', [
    'Produção → PRODUÇÃO' => ['Producao', 'producao'],
    'Fisio → COMERCIAL (Vendas)' => ['Fisio', 'comercial'],
    'Arquivos → SISTEMA' => ['Arquivos', 'sistema'],
]);

it('o módulo declara o grupo do sidebar no modifyAdminMenu', function (string $modulo, string $grupo) {
    $src = file_get_contents(__DIR__ . "/../../../Modules/{$modulo}/Http/Controllers/DataController.php");
    expect($src)->toBeString();

    $inicio = strpos($src, 'function modifyAdminMenu');
    expect($inicio)->not->toBeFalse();
    $corpo = substr($src, $inicio);

    $this->assertMatchesRegularExpression(
        "/^\s*'group'\s*=>\s*'" . $grupo . "',/m",
        $corpo,
        "Modules/{$modulo} deveria declarar 'group' => '{$grupo}' no modifyAdminMenu"
    );
})->with('departamentos');

it('os grupos declarados existem no SIDEBAR_GROUPS do cockpit', function (string $modulo, string $grupo) {
    $tsx = file_get_contents(__DIR__ . '/../../../resources/js/Components/cockpit/Sidebar.tsx');
    $this->assertStringContainsString("key: '{$grupo}'", $tsx, "grupo '{$grupo}' ausente em SIDEBAR_GROUPS");
})->with('departamentos');
