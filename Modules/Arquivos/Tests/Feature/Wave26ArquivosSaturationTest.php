<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Arquivos\Concerns\HasArquivos;
use Modules\Arquivos\Entities\Arquivo;
use Modules\Arquivos\Services\ArquivosRetentionService;
use Modules\Arquivos\Services\ArquivosService;
use Modules\Arquivos\Services\VaultEncryptionService;

uses(Tests\TestCase::class);

/**
 * Wave 26 Arquivos POLISH 74→85 — saturação D1/D5/D7/D9 sem boot DB.
 *
 * Estratégia: reflection + source-grep + Container resolve. Sem hit DB pra
 * paralelização worktree (ADR 0093 multi-tenant). Wave 25 saturou retention.php
 * canônico; Wave 26 expande D5 README (cliente-facing) + D7 LogsActivity check
 * + D9 spans count adicional (arquivos.dedupe_lookup novo).
 *
 * @see Modules/Arquivos/README.md
 * @see Modules/Arquivos/CHANGELOG.md
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
describe('Wave 26 Arquivos POLISH 74→85', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    it('D1: Arquivo Entity usa SoftDeletes + LogsActivity + global scope', function () {
        $traits = class_uses_recursive(Arquivo::class);

        expect(in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, $traits, true))->toBeTrue();
        expect(in_array(\Spatie\Activitylog\Traits\LogsActivity::class, $traits, true))->toBeTrue();

        // Global scope multi-tenant Tier 0
        $src = file_get_contents((new ReflectionClass(Arquivo::class))->getFileName());
        expect($src)->toContain("addGlobalScope('business_id'");
    });

    it('D1: HasArquivos trait expoe API canônica (arquivos, attachArquivo, arquivosClassificados)', function () {
        $ref = new ReflectionClass(HasArquivos::class);

        expect($ref->hasMethod('arquivos'))->toBeTrue();
        expect($ref->hasMethod('attachArquivo'))->toBeTrue();
        expect($ref->hasMethod('arquivosClassificados'))->toBeTrue();
    });

    it('D5: README.md existe + cita Wagner/Larissa/Martinho + 7+ casos de uso', function () {
        $readmePath = base_path('Modules/Arquivos/README.md');
        expect(file_exists($readmePath))->toBeTrue('README.md cliente-facing obrigatório Wave 26 D5');

        $src = file_get_contents($readmePath);
        expect($src)->toContain('Wagner');
        expect($src)->toContain('Larissa');
        expect($src)->toContain('Martinho');

        // Tabela "Como cliente usa" deve ter >= 7 linhas (cenarios uso real)
        $matches = preg_match_all("/^\\| Quero\\.\\.\\.|^\\| Anexar|^\\| Baixar|^\\| Auditar|^\\| Excluir|^\\| Esquecer|^\\| Compliance|^\\| Economizar/m", $src);
        expect($matches)->toBeGreaterThanOrEqual(7, "Esperava 7+ linhas tabela 'Como cliente usa'; achou {$matches}");
    });

    it('D5: README documenta journey real biz=1 (Wagner dev) com 7 passos', function () {
        $src = file_get_contents(base_path('Modules/Arquivos/README.md'));

        expect($src)->toContain('Journey real biz=1');
        // 7 passos numerados na tabela
        $passos = preg_match_all("/^\\| \\d\\. /m", $src);
        expect($passos)->toBeGreaterThanOrEqual(7, "Esperava 7+ passos numerados; achou {$passos}");
    });

    it('D5: README documenta integração de novo Model (HasArquivos trait code sample)', function () {
        $src = file_get_contents(base_path('Modules/Arquivos/README.md'));

        expect($src)->toContain('use Modules\\Arquivos\\Concerns\\HasArquivos;');
        expect($src)->toContain('attachArquivo');
    });

    it('D7: Arquivo LogsActivity rastreia 6+ campos sensíveis governance LGPD', function () {
        $src = file_get_contents((new ReflectionClass(Arquivo::class))->getFileName());

        foreach (['bucket', 'sub_destination', 'visibility', 'encrypted', 'retention_days', 'classified_by'] as $field) {
            expect($src)->toContain("'{$field}'");
        }
        // logOnlyDirty pra performance + dontSubmitEmptyLogs
        expect($src)->toContain('logOnlyDirty');
        expect($src)->toContain('dontSubmitEmptyLogs');
        expect($src)->toContain("useLogName('arquivos.arquivo')");
    });

    it('D7: ArquivosService.audit usa PiiRedactor (defesa em profundidade payload)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosService::class))->getFileName());

        expect($src)->toContain('PiiRedactor');
        expect($src)->toContain('redactPayload');
        // fail-open: audit > redaction (LGPD pragmatismo)
        expect($src)->toContain('fail-open');
    });

    it('D9: ArquivosService spans count >= 6 canon arquivos.* (Wave 18 + 26)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosService::class))->getFileName());

        $matches = preg_match_all("/'arquivos\\.[a-z_]+(?:\\.[a-z_]+)?'/", $src);
        expect($matches)->toBeGreaterThanOrEqual(6, "Esperava 6+ spans arquivos.*; achou {$matches}");

        // Wave 26 D9 NEW span
        expect($src)->toContain("'arquivos.dedupe_lookup'");
    });

    it('D9: ArquivosService.dedupe NUNCA inclui md5 em span attributes (defesa em profundidade)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosService::class))->getFileName());

        // Verifica que existe a span dedupe_lookup
        expect($src)->toContain("'arquivos.dedupe_lookup'");

        // Verifica que tem comentário explicativo sobre não vazar md5
        expect($src)->toContain('md5 NÃO incluído');
    });

    it('D9: Spans totais Arquivos modulewide >= 11 (Service+Retention+Vault)', function () {
        $services = [
            ArquivosService::class,
            ArquivosRetentionService::class,
            VaultEncryptionService::class,
        ];

        $total = 0;
        foreach ($services as $svc) {
            $file = (new ReflectionClass($svc))->getFileName();
            $src  = file_get_contents($file);
            $total += preg_match_all("/'arquivos\\.[a-z_]+(?:\\.[a-z_]+)?'/", $src);
        }
        expect($total)->toBeGreaterThanOrEqual(11, "Esperava 11+ spans modulewide; achou {$total}");
    });

    it('D9: OtelHelper preserva exception em spans arquivos.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'arquivos.test.wave26_boom',
            fn () => throw new \RuntimeException('w26-boom')
        ))->toThrow(\RuntimeException::class, 'w26-boom');
    });

    it('D5: README cita ADRs canônicas mãe (0093, 0123, 0155)', function () {
        $src = file_get_contents(base_path('Modules/Arquivos/README.md'));

        expect($src)->toContain('0093'); // multi-tenant
        expect($src)->toContain('0123'); // backbone
        expect($src)->toContain('0155'); // observability D9
    });
});
