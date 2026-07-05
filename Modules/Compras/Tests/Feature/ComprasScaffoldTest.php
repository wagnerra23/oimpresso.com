<?php

declare(strict_types=1);

namespace Modules\Compras\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Nwidart\Modules\Facades\Module;
use Tests\TestCase;

/**
 * Scaffold test canônico Modules/Compras (padrão D2.b — ADR 0155/0157).
 *
 * Contrato de não-regressão ancorado em:
 *  - ADR 0011 (padrão Jana/Repair/Project) + skill criar-modulo (8 peças obrigatórias)
 *  - ADR 0024 (3 rotas Install obrigatórias — sem elas botão Install fica sem ação)
 *  - memory/requisitos/Compras/SPEC.md (US-COM-001 Wave 1 scaffold)
 *  - module.json governance (bucket process_horizontal + retention fiscal 1825d)
 *
 * Complementa ComprasIndexTest (HTTP smoke) — aqui só estrutura do módulo,
 * sem DB. Espelha Modules/ConsultaOs/Tests/Feature/ScaffoldTest.php.
 */
class ComprasScaffoldTest extends TestCase
{
    public function test_modulo_compras_registrado_em_nwidart(): void
    {
        $module = Module::find('Compras');

        $this->assertNotNull($module, 'Modules/Compras deveria estar registrado no nWidart');
        $this->assertSame('Compras', $module->getName());
    }

    public function test_rotas_nomeadas_compras_index_e_show_existem(): void
    {
        $this->assertTrue(
            Route::has('compras.index'),
            'Rota compras.index deveria existir per Routes/web.php (US-COM-001)'
        );
        $this->assertTrue(
            Route::has('compras.show'),
            'Rota compras.show deveria existir per Routes/web.php (Wave 5 DrawerView)'
        );
    }

    public function test_3_rotas_install_registradas_adr_0024(): void
    {
        $uris = collect(Route::getRoutes())->map(fn ($r) => $r->uri())->all();

        foreach (['compras/install', 'compras/install/uninstall', 'compras/install/update'] as $uri) {
            $this->assertContains(
                $uri,
                $uris,
                "Rota {$uri} deveria estar registrada (ADR 0024 — 3 rotas Install obrigatórias)"
            );
        }
    }

    public function test_module_json_declara_bucket_e_retention_fiscal(): void
    {
        $manifest = json_decode(
            (string) file_get_contents(base_path('Modules/Compras/module.json')),
            true
        );

        $this->assertIsArray($manifest, 'module.json deve ser JSON válido');
        $this->assertSame(
            'process_horizontal',
            $manifest['governance']['bucket'] ?? null,
            'bucket governance atribuído por [W] 2026-05-21 não pode sumir'
        );

        // Catraca da política de retenção fiscal (D7.c): documentos de compra
        // (transactions type purchase*) têm retenção legal mínima de 5 anos.
        // Reduzir exige decisão consciente (ADR) — nunca silenciosa.
        $this->assertArrayHasKey('retention_days', $manifest, 'retention_days deve estar declarado no module.json');
        $this->assertGreaterThanOrEqual(
            1825,
            $manifest['retention_days'],
            'Retenção de documentos fiscais de entrada nunca abaixo de 5 anos (1825d)'
        );
    }

    public function test_service_provider_do_modulo_existe(): void
    {
        $this->assertTrue(
            class_exists(\Modules\Compras\Providers\ComprasServiceProvider::class),
            'ComprasServiceProvider deve existir (peça obrigatória skill criar-modulo)'
        );
    }
}
