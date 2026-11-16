<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Arquivos\Services\ArquivosRetentionService;

uses(Tests\TestCase::class, DatabaseTransactions::class);

/**
 * Wave 28 Arquivos SATURATION FINAL — polish 74-88 → ≥92 (+4pp).
 *
 * Esforço por dimensão:
 *  - D2 +3 Pest novos cenários Wave 28
 *  - D9 +1 span `arquivos.retention.summary` em ArquivosRetentionService (5º span do service)
 *  - D3 CHANGELOG W28 entry
 *
 * Trust L0: Reflection + source-grep sem boot DB.
 *
 * @see Modules/Arquivos/Tests/Feature/Wave26ArquivosSaturationTest.php (Wave 26 baseline)
 * @see Modules/Arquivos/Services/ArquivosRetentionService.php (D9 +1 span W28)
 */
describe('Wave 28 Arquivos POLISH 74-88 → ≥92', function () {

    beforeEach(function () {
        config()->set('otel.enabled', false);
    });

    // ------------------------------------------------------------------
    // D9 W28 — span novo arquivos.retention.summary (5º span Retention)
    // ------------------------------------------------------------------

    it('D9 W28: ArquivosRetentionService tem método summary novo (W28 D9)', function () {
        $ref = new ReflectionClass(ArquivosRetentionService::class);
        expect($ref->hasMethod('summary'))->toBeTrue('Wave 28 D9 — summary novo método público');

        $method = $ref->getMethod('summary');
        expect($method->isPublic())->toBeTrue();
        expect($method->getReturnType()?->getName())->toBe('array');
    });

    it('D9 W28: ArquivosRetentionService instrumenta arquivos.retention.summary (5º span Retention)', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosRetentionService::class))->getFileName());

        expect($src)->toContain("'arquivos.retention.summary'");

        // 4 baseline (run/scan/expire_one/purge_one) + 1 W28 = 5 spans canon
        $count = substr_count($src, 'OtelHelper::spanBiz(');
        expect($count)->toBeGreaterThanOrEqual(5, "Spans ArquivosRetentionService ≥5 (W28 +1); achou {$count}");
    });

    // ------------------------------------------------------------------
    // D2 W28 — +3 Pest cenários adicionais
    // ------------------------------------------------------------------

    it('D2 W28: summary declara businessId e retentionDays como int na assinatura', function () {
        $ref = new ReflectionMethod(ArquivosRetentionService::class, 'summary');
        $params = collect($ref->getParameters())->keyBy(fn ($p) => $p->getName());

        expect($params->has('businessId'))->toBeTrue('summary deve ter businessId Tier 0');
        expect($params->has('retentionDays'))->toBeTrue('summary deve ter retentionDays explícito');

        expect($params['businessId']->getType()?->getName())->toBe('int');
        expect($params['retentionDays']->getType()?->getName())->toBe('int');
    });

    it('D2 W28: o fonte do RetentionService tem as 4 chaves do shape do summary', function () {
        $src = file_get_contents((new ReflectionClass(ArquivosRetentionService::class))->getFileName());

        // Shape canônico no return array
        foreach (['total', 'soft_deleted', 'expired_eligible', 'business_id'] as $key) {
            expect(str_contains($src, "'{$key}'"))->toBeTrue("summary deve retornar chave canon '{$key}'");
        }
    });

    // Até 2026-11-16 este título era afirmado por grep no FONTE (o bloco não continha
    // "delete"/"update"). Agora o summary roda contra o banco: conta o que diz contar, só
    // da empresa pedida, e não muda nenhuma linha. Contrato: docblock do summary + ADR 0093.
    // Ao exercitar, apareceu um defeito: `soft_deleted` saía sempre 0 (o SoftDeletes do
    // model já filtra deleted_at IS NULL); corrigido com withTrashed() no service.
    it('D2 W28: summary é READ-ONLY e conta só a empresa pedida (total / soft_deleted / expired_eligible)', function () {
        if (DB::connection()->getDriverName() === 'sqlite' || ! Schema::hasTable('arquivos')) {
            $this->markTestSkipped('Precisa do schema MySQL do Arquivos — roda na lane arquivos-pest.');
        }
        session()->forget(['user', 'business']);

        $biz = $this->seededTenant()->id;
        $vizinho = $this->seededSupportClientTenant()->id;
        $svc = app(ArquivosRetentionService::class);

        $antes = $svc->summary($biz, 30);

        $linha = fn (int $b, string $nome, $criado, $apagado = null) => DB::table('arquivos')->insertGetId([
            'business_id'   => $b,
            'disk'          => 'arquivos',
            'storage_path'  => "biz-{$b}/w28/{$nome}",
            'original_name' => $nome,
            'mime_type'     => 'text/plain',
            'size_bytes'    => 1,
            'md5'           => md5("w28-{$b}-{$nome}"),
            'bucket'        => 'active',
            'created_at'    => $criado,
            'updated_at'    => $criado,
            'deleted_at'    => $apagado,
        ]);
        $ids = [
            $linha($biz, 'antigo.txt', now()->subDays(100)),          // total + expired_eligible
            $linha($biz, 'novo.txt', now()),                          // total
            $linha($biz, 'excluido.txt', now(), now()->subDay()),     // soft_deleted
            $linha($vizinho, 'antigo-99.txt', now()->subDays(100)),   // não pode contar na 98
        ];
        $retrato = fn () => DB::table('arquivos')->whereIn('id', $ids)->orderBy('id')
            ->get(['id', 'business_id', 'deleted_at', 'updated_at', 'bucket'])->toArray();
        $retratoAntes = $retrato();
        $totalAntes = DB::table('arquivos')->count();

        $depois = $svc->summary($biz, 30);

        expect(array_keys($depois))->toBe(['total', 'soft_deleted', 'expired_eligible', 'business_id']);
        expect($depois['business_id'])->toBe($biz);
        // Deltas: a linha da 99 não entra em nenhum dos três números da 98.
        expect($depois['total'] - $antes['total'])->toBe(2);
        expect($depois['soft_deleted'] - $antes['soft_deleted'])->toBe(1);
        expect($depois['expired_eligible'] - $antes['expired_eligible'])->toBe(1);

        // READ-ONLY: nenhuma linha mudou e nenhuma sumiu.
        expect($retrato())->toEqual($retratoAntes);
        expect(DB::table('arquivos')->count())->toBe($totalAntes);
    });

    it('D9 W28: OtelHelper preserva exception em spans arquivos.retention.* (fail-loud)', function () {
        expect(fn () => OtelHelper::spanBiz(
            'arquivos.retention.test_w28_boom',
            fn () => throw new \RuntimeException('w28-retention-boom')
        ))->toThrow(\RuntimeException::class, 'w28-retention-boom');
    });

    // ------------------------------------------------------------------
    // D3 W28 — CHANGELOG entry novo
    // ------------------------------------------------------------------

    it('D3 W28: CHANGELOG.md tem entrada Wave 28 (saturation 74-88 → ≥92)', function () {
        $changelog = file_get_contents(base_path('memory/requisitos/Arquivos/CHANGELOG.md'));
        expect($changelog)->toContain('Wave 28');
    });
});
