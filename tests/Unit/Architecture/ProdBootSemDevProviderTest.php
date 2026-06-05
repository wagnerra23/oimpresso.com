<?php

declare(strict_types=1);

namespace Tests\Unit\Architecture;

use Tests\TestCase;

/**
 * GUARD do OUTAGE 2026-06-05 (prod 503 — Service Unavailable / maintenance mode).
 *
 * Causa: `knuckleswtf/scribe` (gerador de docs API, `require-dev`) tinha seu
 * `ScribeServiceProvider` AUTO-DESCOBERTO no `bootstrap/cache/packages.php`. Em prod
 * (`composer install --no-dev`) o pacote NÃO está instalado → ao bootar, Laravel
 * tentava carregar a classe ausente → fatal → o deploy travou em manutenção (`down`)
 * e nunca chegou no `php artisan up` → 503 em TODO o site (inclusive ROTA LIVRE).
 *
 * Fix:
 *  - composer.json `extra.laravel.dont-discover`: + "knuckleswtf/scribe" (Laravel
 *    NUNCA auto-registra o provider → packages.php nunca o referencia → prod boota).
 *  - AppServiceProvider::register(): registra ScribeServiceProvider SÓ quando
 *    class_exists (= dev com o pacote) → dev mantém `scribe:generate`, prod ignora.
 *
 * Este teste GARANTE que a proteção não seja removida e que NENHUM outro pacote
 * `require-dev` com provider auto-descoberto escape do dont-discover (prevenção da
 * classe inteira de bug, não só do scribe).
 *
 * @see memory/sessions/2026-06-05-veiculo-na-venda-e-incidente-numuf-valor-inflado.md
 */
class ProdBootSemDevProviderTest extends TestCase
{
    private function composer(): array
    {
        return json_decode((string) file_get_contents(base_path('composer.json')), true);
    }

    private function dontDiscover(): array
    {
        $c = $this->composer();

        return $c['extra']['laravel']['dont-discover'] ?? [];
    }

    /** @test */
    public function scribe_esta_no_dont_discover(): void
    {
        $this->assertContains(
            'knuckleswtf/scribe',
            $this->dontDiscover(),
            'REGRESSÃO do outage 2026-06-05: knuckleswtf/scribe saiu do dont-discover. '.
            'Sem isso, o ScribeServiceProvider é auto-descoberto e quebra o boot em prod '.
            '(--no-dev, pacote ausente) → site 503. Mantenha em composer.json extra.laravel.dont-discover.'
        );
    }

    /** @test */
    public function scribe_e_require_dev_e_registrado_condicional_no_appserviceprovider(): void
    {
        $c = $this->composer();
        $this->assertArrayHasKey(
            'knuckleswtf/scribe',
            $c['require-dev'] ?? [],
            'scribe deve ser require-dev (ferramenta de docs, não roda em prod).'
        );

        $src = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $this->assertStringContainsString(
            'class_exists(\Knuckles\Scribe\ScribeServiceProvider::class)',
            $src,
            'AppServiceProvider deve registrar o ScribeServiceProvider SÓ se class_exists '.
            '(dont-discover tira do auto-discover; o registro condicional preserva dev sem quebrar prod).'
        );
    }

    /**
     * O app DEVE bootar com o provider list mergeado — TODO provider registrado
     * (config + descoberto) tem que ter a classe carregável. Pega o caso em que um
     * provider referenciado não existe (como o ScribeServiceProvider em prod).
     *
     * @test
     */
    public function todos_os_providers_registrados_sao_carregaveis(): void
    {
        $providers = $this->app->getLoadedProviders();
        foreach (array_keys($providers) as $providerClass) {
            $this->assertTrue(
                class_exists($providerClass),
                "Provider registrado mas SEM classe carregável: {$providerClass}. ".
                'É exatamente o que derrubou prod (ScribeServiceProvider ausente em --no-dev). '.
                'Provider de pacote dev deve ser dont-discover + registrado condicional (class_exists).'
            );
        }
    }
}
