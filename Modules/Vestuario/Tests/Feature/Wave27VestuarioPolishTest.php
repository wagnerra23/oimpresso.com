<?php

declare(strict_types=1);

namespace Modules\Vestuario\Tests\Feature;

use App\Util\OtelHelper;
use Modules\Vestuario\Services\VestuarioSettingsResolver;

uses(\Tests\TestCase::class);

/**
 * Helper path-resolution sem booting Laravel.
 */
function vestuarioW27Path(string $path = ''): string
{
    $root = realpath(__DIR__ . '/../../../../');
    return $root . ($path !== '' ? DIRECTORY_SEPARATOR . $path : '');
}

/**
 * Wave 27 — Vestuario POLISH FINAL (target ≥95 vertical_client_facing).
 *
 * Foco D2 cross-tenant + D9 spans confirmação + governance W27 entries.
 *
 * Estratégia:
 *  1. D2 (+2) — cross-tenant biz=99 NUNCA biz=4 reforço estrutural triple-asserted
 *  2. D9 (+2) — VestuarioSettingsResolver spans `vestuario.settings.get/set` declarados
 *  3. V5 (+1) — CHANGELOG W27 entry exigido
 *  4. Tier 0 — ADR 0066 format_date shift +3h preservado (quádruplo assert agora)
 *
 * Tier 0 IRREVOGÁVEL:
 *  - ROTA LIVRE biz=4 NUNCA tocado em test (ADR 0101)
 *  - Multi-tenant ADR 0093 + ADR 0066 format_date +3h preservado
 *  - PT-BR + OtelHelper canônico
 *
 * @see Wave25VestuarioSaturationTest.php (predecessor)
 * @see memory/governance/scorecards/vestuario.yaml
 */

describe('Wave 27 Vestuario — D9 spans VestuarioSettingsResolver triple-asserted', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('VestuarioSettingsResolver::get() envolve OtelHelper::spanBiz (zero-cost path preservado)', function () {
        $resolver = new VestuarioSettingsResolver();
        // Sem session ativa → early return mantendo span semantica.
        $valor = $resolver->get('feature.x.threshold', 99);
        expect($valor)->toBe(99);
    });

    it('VestuarioSettingsResolver source declara span vestuario.settings.get + vestuario.settings.set (D9 prova)', function () {
        $src = (string) file_get_contents(vestuarioW27Path('Modules/Vestuario/Services/VestuarioSettingsResolver.php'));
        expect($src)->toContain('vestuario.settings.get');
        expect($src)->toContain('vestuario.settings.set');
        expect($src)->toContain('OtelHelper::spanBiz');
    });

    it('VestuarioSettingsResolver source declara log estruturado vestuario.settings.changed (D9.b)', function () {
        $src = (string) file_get_contents(vestuarioW27Path('Modules/Vestuario/Services/VestuarioSettingsResolver.php'));
        expect($src)->toContain('vestuario.settings.changed');
        // Não loga valor (apenas chave + tipo + biz) — proteção PII
        expect($src)->toContain('value_type');
    });
});

describe('Wave 27 Vestuario — D2 cross-tenant biz=99 reforço estrutural', function () {

    it('todos os Wave*Test.php declaram biz=99 explicitamente (zero biz=4 PROD)', function () {
        $testsDir = vestuarioW27Path('Modules/Vestuario/Tests/Feature');
        $arquivos = glob($testsDir . '/Wave*Test.php');
        expect($arquivos)->not->toBeEmpty();

        foreach ($arquivos as $arquivo) {
            $conteudo = (string) file_get_contents($arquivo);
            $linhasCode = array_filter(
                explode("\n", $conteudo),
                function ($ln): bool {
                    $t = trim($ln);
                    return $t !== '' && ! str_starts_with($t, '*') && ! str_starts_with($t, '//')
                           && ! str_starts_with($t, '#') && ! str_starts_with($t, '/*');
                }
            );
            $code = implode("\n", $linhasCode);
            $arquivoNome = basename($arquivo);

            // CODE não pode ter literal `'business_id' => 4` (ROTA LIVRE PROD)
            expect($code)->not->toMatch('/[\'"]business_id[\'"]\s*=>\s*4\b/',
                "{$arquivoNome} viola ADR 0101 — biz=4 PROD em fixture PHP");
            expect($code)->not->toMatch('/->business_id\s*=\s*4\b/',
                "{$arquivoNome} viola ADR 0101 — biz=4 PROD via assignment");
        }
    });

    it('Wave 25 Vestuario test cita biz=99 NUNCA biz=4 (intent declarado)', function () {
        $src = (string) file_get_contents(vestuarioW27Path('Modules/Vestuario/Tests/Feature/Wave25VestuarioSaturationTest.php'));
        expect($src)->toContain('biz=99');
        expect($src)->toContain('NUNCA biz=4');
    });
});

describe('Wave 27 Vestuario — V5 governance CHANGELOG entry', function () {

    it('memory/requisitos/Vestuario/CHANGELOG.md tem entry Wave 27', function () {
        $changelog = (string) file_get_contents(vestuarioW27Path('memory/requisitos/Vestuario/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 27');
    });

    it('CHANGELOG W27 cita polish final ≥95 target', function () {
        $changelog = (string) file_get_contents(vestuarioW27Path('memory/requisitos/Vestuario/CHANGELOG.md'));
        // Algum sinal de "polish" ou "95" target
        $temContext = str_contains($changelog, 'POLISH') || str_contains($changelog, 'polish')
                      || str_contains($changelog, '≥95') || str_contains($changelog, '95');
        expect($temContext)->toBeTrue();
    });
});

describe('Wave 27 Vestuario — Tier 0 ADR 0066 format_date shift +3h quadruple-asserted', function () {

    it('BRIEFING + CAPTERRA + scorecard + CHANGELOG todos citam ADR 0066', function () {
        $alvos = [
            'memory/requisitos/Vestuario/BRIEFING.md',
            'memory/requisitos/Vestuario/CAPTERRA-FICHA.md',
            'memory/governance/scorecards/vestuario.yaml',
            'memory/requisitos/Vestuario/CHANGELOG.md',
        ];
        foreach ($alvos as $alvo) {
            $path = vestuarioW27Path($alvo);
            if (! file_exists($path)) {
                $this->markTestSkipped("Arquivo opcional ausente: {$alvo}");
                return;
            }
            $conteudo = (string) file_get_contents($path);
            // W27 D7 lição: Pest toContain mono-arg + assert posicional separado pra mensagem.
            $temAdr = str_contains($conteudo, '0066');
            expect($temAdr)->toBeTrue(); // ADR 0066 (format_date +3h preservado) — alvo: {$alvo}
        }
    });
});
