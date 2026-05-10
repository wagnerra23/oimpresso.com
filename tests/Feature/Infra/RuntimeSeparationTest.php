<?php

declare(strict_types=1);

namespace Tests\Feature\Infra;

use Tests\TestCase;

/**
 * US-INFRA-009 — guards de runtime separation Hostinger ≠ CT 100 (ADR 0062).
 *
 * Hostinger é shared hosting; daemons (Octane, MCP exposed, Reverb, Centrifugo,
 * Horizon) NÃO podem rodar lá. Decisão arquitetural canônica:
 *   - composer.json mantém laravel/octane + laravel/mcp (CT 100 precisa)
 *   - HOSTINGER controla via .env: MCP_TOOLS_EXPOSED=false (default false)
 *   - CT 100 .env: MCP_TOOLS_EXPOSED=true
 *
 * Este test valida que os gates existem e default é safe.
 *
 * @see memory/decisions/0062-separacao-runtime-hostinger-ct100.md
 * @see memory/decisions/0053-mcp-server-governanca-como-produto.md
 */
class RuntimeSeparationTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function mcp_tools_exposed_config_existe_e_default_false(): void
    {
        $this->assertTrue(
            function_exists('config') && config('mcp') !== null,
            'config/mcp.php deve existir (gate canônico ADR 0062 §4)'
        );

        // Sem env override, default deve ser false (Hostinger safe-default).
        // Em test env, .env.testing pode setar TRUE — mas raw config sem env deve ser false.
        $configFile = require config_path('mcp.php');
        $this->assertArrayHasKey('tools_exposed', $configFile);

        // Verifica que a chave usa env() com default false (não hardcode true).
        $rawSrc = file_get_contents(config_path('mcp.php'));
        $this->assertStringContainsString(
            "env('MCP_TOOLS_EXPOSED', false)",
            $rawSrc,
            'config/mcp.php tools_exposed deve ter default false (safe pra Hostinger). ' .
            'ADR 0062 §4 + ADR 0053.'
        );
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function composer_json_documenta_runtime_packages(): void
    {
        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        // Packages que SÓ rodam em CT 100 (ADR 0062). Em Hostinger são instalados
        // mas inertes (gate via env). Esta lista documenta intenção.
        $ct100Only = ['laravel/octane', 'laravel/mcp'];

        foreach ($ct100Only as $package) {
            $this->assertArrayHasKey(
                $package,
                $composer['require'] ?? [],
                "{$package} deve estar em composer.json (CT 100 precisa). " .
                "Hostinger controla via env (MCP_TOOLS_EXPOSED=false). " .
                "ADR 0062 §runtime-separation."
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function adr_0062_existe_e_aceita(): void
    {
        $adrPath = base_path('memory/decisions/0062-separacao-runtime-hostinger-ct100.md');

        $this->assertFileExists(
            $adrPath,
            'ADR 0062 mãe da regra runtime separation. Não pode sumir sem supersessão explícita.'
        );

        $body = file_get_contents($adrPath);
        $this->assertMatchesRegularExpression(
            '/status:\s*(accepted|aceito|active|ativo|live)/i',
            $body,
            'ADR 0062 deve estar status accepted/aceito/active. Se virou superseded, atualizar este test.'
        );
    }
}
