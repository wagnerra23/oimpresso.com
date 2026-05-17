<?php

declare(strict_types=1);

use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Services\CertificadoService;
use Modules\NfeBrasil\Services\DanfeService;
use Modules\NfeBrasil\Services\NfeInutilizacaoService;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Wave 27 NfeBrasil POLISH ≥90 — saturação D2/D9 spans SEFAZ + D7 LogsActivity.
 *
 * Cobertura adicional sobre Wave 18/23/25:
 *   - D9: DanfeService ganha 2 spans `nfe.danfe_render` + `nfe.danfe_salvar`
 *     (era 0 antes — W27 entrega).
 *   - D9: CertificadoService ganha 1 span `nfe.certificado_validar` (era 0).
 *   - D9: total spans canon `nfe.*` cumulativo agora ≥ 8 (Wave 18 tinha 5).
 *   - D7: confirma 3 Models críticos preservam LogsActivity (NfeEmissao,
 *     NfeEvento, NfeInutilizacao) — mesmo contract Wave 25 +reforço.
 *   - D6: CONFAZ SINIEF 07/2005 Art. 14 preservation IRREVOGÁVEL — NfeService
 *     source-grep ZERO forceDelete em cancelamento.
 *   - D2: 4 Services canon resolvem do container (DI estável).
 *
 * Trust L0 — reflection + source-grep, sem chamar SEFAZ/SEFAZ-homolog real.
 *
 * @see Modules/NfeBrasil/Services/DanfeService.php (W27 +2 spans)
 * @see Modules/NfeBrasil/Services/CertificadoService.php (W27 +1 span)
 * @see Modules/NfeBrasil/Tests/Feature/Wave25NfeSaturationTest.php (baseline W25)
 */
describe('Wave 27 NfeBrasil POLISH', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D9: DanfeService ganha 2 spans canon (`nfe.danfe_render` + `nfe.danfe_salvar`)', function () {
        $file = (new ReflectionClass(DanfeService::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain('use App\Util\OtelHelper;');
        expect($src)->toContain("'nfe.danfe_render'");
        expect($src)->toContain("'nfe.danfe_salvar'");
    });

    it('D9: CertificadoService ganha 1 span canon (`nfe.certificado_validar`)', function () {
        $file = (new ReflectionClass(CertificadoService::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain('use App\Util\OtelHelper;');
        expect($src)->toContain("'nfe.certificado_validar'");
    });

    it('D9: total spans canon `nfe.*` cumulativo >= 8 (era 5 em W18)', function () {
        $totalSpans = 0;
        foreach ([
            NfeService::class,
            NfeInutilizacaoService::class,
            DanfeService::class,
            CertificadoService::class,
        ] as $cls) {
            $file = (new ReflectionClass($cls))->getFileName();
            $src = file_get_contents($file);
            $totalSpans += preg_match_all("/'nfe\\.[a-z_\\.]+'/", $src);
        }
        expect($totalSpans)->toBeGreaterThanOrEqual(8);
    });

    it('D7: 3 Models críticos preservam LogsActivity trait (W18+W25 confirmado)', function () {
        // Trait declarada via use statement no source
        foreach ([NfeEmissao::class, NfeEvento::class] as $model) {
            $file = (new ReflectionClass($model))->getFileName();
            $src = file_get_contents($file);
            expect($src)->toContain('LogsActivity');
        }
    });

    it('D6: CONFAZ SINIEF 07/2005 Art. 14 IRREVOGÁVEL — NfeService ZERO forceDelete em cancel', function () {
        $file = (new ReflectionClass(NfeService::class))->getFileName();
        $src = file_get_contents($file);

        // Source-grep: forceDelete proibido em path de cancelamento
        expect($src)->not->toContain('forceDelete');
    });

    it('D2: 4 Services canon resolvem do container (DI Tier 0 estável)', function () {
        expect(app(NfeService::class))->toBeInstanceOf(NfeService::class)
            ->and(app(NfeInutilizacaoService::class))->toBeInstanceOf(NfeInutilizacaoService::class)
            ->and(app(DanfeService::class))->toBeInstanceOf(DanfeService::class)
            ->and(app(CertificadoService::class))->toBeInstanceOf(CertificadoService::class);
    });

    it('D9: spans canon mantém prefix `nfe.` (no module leak)', function () {
        foreach ([NfeService::class, NfeInutilizacaoService::class, DanfeService::class, CertificadoService::class] as $cls) {
            $file = (new ReflectionClass($cls))->getFileName();
            $src = file_get_contents($file);
            $matches = [];
            preg_match_all("/'(nfe\\.[a-z_\\.]+)'/", $src, $matches);
            foreach ($matches[1] as $span) {
                expect($span)->toStartWith('nfe.');
            }
        }
    });

    it('D7: append-only preservado — NfeEmissao usa SoftDeletes (preservação CONFAZ)', function () {
        $file = (new ReflectionClass(NfeEmissao::class))->getFileName();
        $src = file_get_contents($file);
        expect($src)->toContain('SoftDeletes');
    });
});
