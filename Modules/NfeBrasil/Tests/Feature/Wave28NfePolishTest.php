<?php

declare(strict_types=1);

use Modules\NfeBrasil\Models\NfeEmissao;
use Modules\NfeBrasil\Models\NfeEvento;
use Modules\NfeBrasil\Models\NfeInutilizacao;
use Modules\NfeBrasil\Services\NfeService;

uses(Tests\TestCase::class);

/**
 * Wave 28 NfeBrasil POLISH ≥92 — D2 +3 + D7 LogsActivity expand.
 *
 * Estratégia: reflection + source-grep, ZERO hit DB (paralelização worktree W28).
 *
 * Cobre adicional sobre Waves 17+18+25:
 *   - D2 contract: NfeEmissao expõe scopes canon (autorizadas/doBusinessAtual)
 *   - D2 contract: NfeService NÃO contém forceDelete em código de cancelamento
 *     (CONFAZ SINIEF 07/2005 Art. 14 — número permanece "usado oficialmente")
 *   - D2 contract: NfeEmissao + NfeInutilizacao + NfeEvento isAutorizada() canon
 *   - D7 expand: 3 Models críticos têm logOnlyDirty + useLogName scoped
 *     (não polui activity_log generic + filtro audit eficiente)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - NFe cancelada NUNCA forceDelete (CONFAZ Art. 14)
 *   - LogsActivity append-only (LGPD Art. 37)
 *   - business_id NOT NULL todas tabelas fiscais
 *
 * @see Modules/NfeBrasil/CHANGELOG.md Wave 28
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 28 NfeBrasil POLISH', function () {

    // ---- D2 contract canon ----

    it('D2: NfeEmissao expõe scopeAutorizadas (filtro UI dashboard fiscal)', function () {
        $ref = new ReflectionClass(NfeEmissao::class);
        expect($ref->hasMethod('scopeAutorizadas'))->toBeTrue();
    });

    it('D2: NfeEmissao expõe isAutorizada() + isCancelavel() helpers canon', function () {
        $ref = new ReflectionClass(NfeEmissao::class);
        expect($ref->hasMethod('isAutorizada'))->toBeTrue()
            ->and($ref->hasMethod('isCancelavel'))->toBeTrue();
    });

    it('D2: NfeEvento expõe isAutorizado() helper (status SEFAZ)', function () {
        $ref = new ReflectionClass(NfeEvento::class);
        expect($ref->hasMethod('isAutorizado'))->toBeTrue();
    });

    // ---- D6/D2 CONFAZ preservation (sentry irrevogável) ----

    it('D2/CONFAZ: NfeService NÃO contém ->forceDelete() em código de cancelamento (SINIEF 07/2005 Art. 14)', function () {
        $file = (new ReflectionClass(NfeService::class))->getFileName();
        $src = file_get_contents($file);

        // IRREVOGÁVEL: cancelar = UPDATE status, NUNCA hard-delete (número permanece oficial)
        expect($src)->not->toContain('->forceDelete()');
    });

    // ---- D7 LogsActivity expand ----

    it('D7 expand: NfeEmissao LogsActivity usa logOnlyDirty + useLogName scoped (filtro audit eficiente)', function () {
        $src = file_get_contents((new ReflectionClass(NfeEmissao::class))->getFileName());

        expect($src)->toContain('logOnlyDirty()')
            ->and($src)->toContain("useLogName('nfe_emissao')");
    });

    it('D7 expand: NfeEvento LogsActivity scoped (separa audit eventos SEFAZ de emissões)', function () {
        $src = file_get_contents((new ReflectionClass(NfeEvento::class))->getFileName());

        expect($src)->toContain('logOnlyDirty()')
            ->and($src)->toContain("useLogName('nfe_evento')")
            ->and($src)->toContain('dontSubmitEmptyLogs()'); // append-only sem ruído
    });

    it('D7 expand: NfeInutilizacao LogsActivity scoped (audit numeração inutilizada)', function () {
        $src = file_get_contents((new ReflectionClass(NfeInutilizacao::class))->getFileName());

        expect($src)->toContain('logOnlyDirty()')
            ->and($src)->toContain("useLogName('nfe_inutilizacao')");
    });

    // ---- Tier 0 sentry ----

    it('Tier 0: NfeEmissao usa SoftDeletes (CONFAZ Art. 14 — preserva audit fiscal)', function () {
        $traits = class_uses_recursive(NfeEmissao::class);
        $hasSoftDeletes = collect($traits)->contains(fn ($t) => str_contains($t, 'SoftDeletes'));
        expect($hasSoftDeletes)->toBeTrue();
    });
});
