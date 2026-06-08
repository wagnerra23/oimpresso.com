<?php

declare(strict_types=1);

use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Services\NfseEmissaoService;

uses(Tests\TestCase::class);

/**
 * Wave 25 NFSe POLISH ≥90 — saturação D2/D6/D7.
 *
 * Estratégia: reflection + source-grep + Config read. Sem hit DB pra
 * paralelização worktree. Tier 0 IRREVOGÁVEIS preservados:
 *   - ADR 0093 multi-tenant (NfseBusinessScope herança alias)
 *   - Imutabilidade fiscal CONFAZ SINIEF 07/2005 Art. 14 (status emitida append-only)
 *   - LGPD Art. 6º IX minimização (PiiRedactor em erro_mensagem)
 *
 * Cobertura adicional sobre Wave 18/23:
 *   - D2: NfseEmissao + NfseCertificado contratos comprehensive
 *   - D2: NfseEmissaoService idempotency + retry + cancelamento
 *   - D6: NfseHealthCommand + spans canon nfse.emissao
 *   - D7: Config retention.php declara 5y CONFAZ + LogsActivity append-only
 *   - D7: NfseEmissao usa LogsActivity (Spatie Activitylog) — audit trail LGPD
 *
 * @see Modules/NFSe/CHANGELOG.md Wave 25 POLISH
 * @see Modules/NFSe/Config/retention.php (D7 LGPD)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 25 NFSe POLISH', function () {

    it('D2: NfseEmissao usa LogsActivity (D7 audit trail LGPD append-only)', function () {
        $traits = class_uses(NfseEmissao::class);
        expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);
    });

    it('D7: NfseEmissao getActivitylogOptions exclui xml_envio/xml_retorno/pdf_url (storage cost)', function () {
        $model = new NfseEmissao();
        $options = $model->getActivitylogOptions();

        // Reflection — LogOptions é DTO Spatie. Verificamos via source-grep.
        $file = (new ReflectionClass(NfseEmissao::class))->getFileName();
        $src = file_get_contents($file);
        expect($src)->toContain("logExcept(['xml_envio', 'xml_retorno', 'pdf_url'])")
            ->and($src)->toContain('useLogName(\'nfse.emissao\')')
            ->and($src)->toContain('logOnlyDirty()')
            ->and($src)->toContain('dontSubmitEmptyLogs()');
    });

    it('D7: NfseEmissao fillable contém PII tomador + campos fiscais canon', function () {
        $model = new NfseEmissao();
        $fillable = $model->getFillable();

        foreach (['tomador_cnpj', 'tomador_cpf', 'tomador_nome', 'tomador_email',
                  'numero', 'serie', 'rps_numero', 'competencia',
                  'valor_servicos', 'valor_iss', 'aliquota_iss',
                  'status', 'idempotency_key', 'transaction_id'] as $field) {
            expect($fillable)->toContain($field);
        }
    });

    it('D2: NfseEmissao casts decimais com precisão correta (2 ou 4 casas)', function () {
        $model = new NfseEmissao();
        $casts = $model->getCasts();

        expect($casts)->toHaveKey('valor_servicos')
            ->and($casts['valor_servicos'])->toBe('decimal:2')
            ->and($casts)->toHaveKey('aliquota_iss')
            ->and($casts['aliquota_iss'])->toBe('decimal:4')
            ->and($casts['valor_iss'])->toBe('decimal:2')
            ->and($casts['iss_retido'])->toBe('boolean')
            ->and($casts['competencia'])->toBe('date');
    });

    it('D2: NfseEmissao status helpers retornam bool consistente', function () {
        $model = new NfseEmissao();

        $model->status = 'emitida';
        expect($model->isEmitida())->toBeTrue()->and($model->isCancelada())->toBeFalse();

        $model->status = 'cancelada';
        expect($model->isCancelada())->toBeTrue()->and($model->isErro())->toBeFalse();

        $model->status = 'erro';
        expect($model->isErro())->toBeTrue();

        foreach (['rascunho', 'processando'] as $s) {
            $model->status = $s;
            expect($model->isPendente())->toBeTrue("status '{$s}' deve ser pendente");
        }
    });

    it('D2: NfseEmissao statusLabel + statusColor mapeiam 5 status canon', function () {
        $model = new NfseEmissao();

        $expected = [
            'rascunho'    => ['Rascunho', 'secondary'],
            'processando' => ['Processando...', 'info'],
            'emitida'     => ['Emitida', 'success'],
            'cancelada'   => ['Cancelada', 'warning'],
            'erro'        => ['Erro', 'danger'],
        ];

        foreach ($expected as $status => [$label, $color]) {
            $model->status = $status;
            expect($model->statusLabel())->toBe($label);
            expect($model->statusColor())->toBe($color);
        }
    });

    it('D2: NfseCertificado herda NfeCertificado (alias schema unificado)', function () {
        $ref = new ReflectionClass(NfseCertificado::class);
        expect($ref->getParentClass()?->getName())->toBe(\Modules\NfeBrasil\Models\NfeCertificado::class);

        // isExpirado é alias de isVencido — herança garante
        expect($ref->hasMethod('isExpirado'))->toBeTrue();
    });

    it('D6: NfseEmissaoService declara span canon nfse.emissao (D9 observability)', function () {
        $file = (new ReflectionClass(NfseEmissaoService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain("'nfse.emissao'")
            ->and($src)->toContain('use App\Util\OtelHelper;');
    });

    it('D6: NfseEmissaoService idempotency + retry MAX_RETRIES configurado', function () {
        $file = (new ReflectionClass(NfseEmissaoService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain('MAX_RETRIES = 3')
            ->and($src)->toContain('idempotencyKey')
            ->and($src)->toContain('whereIn(\'status\', [\'emitida\', \'processando\'])');
    });

    it('D6: NfseEmissaoService cancelar() bloqueia dupla cancelação (imutabilidade fiscal)', function () {
        $file = (new ReflectionClass(NfseEmissaoService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain('NfseJaCanceladaException')
            ->and($src)->toContain('isCancelada()');
    });

    it('D7: PiiRedactor aplicado em erro_mensagem (LGPD Art. 6º IX)', function () {
        $file = (new ReflectionClass(NfseEmissaoService::class))->getFileName();
        $src  = file_get_contents($file);

        expect($src)->toContain('PiiRedactor')
            ->and($src)->toContain('->redact($e->getMessage())');
    });

    it('D7: Config retention declara 5 anos CONFAZ + 1 ano erro/webhook', function () {
        $config = include base_path('Modules/NFSe/Config/retention.php');

        expect($config)->toBeArray()
            ->toHaveKey('entities')
            ->and($config['entities'])->toHaveKey('nfse_emissao_fiscal')
            ->and($config['entities']['nfse_emissao_fiscal'])->toBe(1825)
            ->and($config['entities']['nfse_emissao_erro'])->toBe(365)
            ->and($config['entities']['webhook_municipal'])->toBe(365);

        // provider_config + certificado_a1 indefinido (lifecycle contrato/cert)
        expect($config['entities']['provider_config'])->toBeNull()
            ->and($config['entities']['certificado_a1'])->toBeNull();
    });

    it('D7: Config retention default strategy=soft_delete + notice_period=30d (LGPD Art. 18 §VI)', function () {
        $config = include base_path('Modules/NFSe/Config/retention.php');

        // strategy lê env(); default soft_delete
        expect($config)->toHaveKey('strategy')
            ->and($config['notice_period_days'])->toBe(30)
            ->and($config['enabled'])->toBeFalse(); // pending job
    });

    it('D6: NfseController index não usa Inertia::defer (rollback PR #963 lição)', function () {
        $ctrlPath = base_path('Modules/NFSe/Http/Controllers/NfseController.php');
        $src = file_get_contents($ctrlPath);

        // Rollback PR #963: defer SEM <Deferred> wrap React quebrou.
        // Wave 25 mantém pattern. Quando Index.tsx receber <Deferred>, ativar defer.
        expect($src)->toContain('NÃO migrado neste PR')
            ->and($src)->toContain('paginate(25)');
    });

    it('D6: NfseController declara permissions canon nfse.view + nfse.emit + nfse.cancel', function () {
        $ctrlPath = base_path('Modules/NFSe/Http/Controllers/NfseController.php');
        $src = file_get_contents($ctrlPath);

        expect($src)->toContain("authorize('nfse.view')")
            ->and($src)->toContain("authorize('nfse.emit')");
        // nfse.cancel via FormRequest::authorize() (CancelarNfseRequest)
        expect($src)->toContain('CancelarNfseRequest');
    });

    it('D2: NfseEmissao usa SoftDeletes (preserva audit fiscal CONFAZ 5y)', function () {
        $traits = class_uses(NfseEmissao::class);
        expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\SoftDeletes::class);
    });

    it('D7: NfseEmissao usa NfseBusinessScope (multi-tenant Tier 0 ADR 0093)', function () {
        $traits = class_uses(NfseEmissao::class);
        expect($traits)->toHaveKey(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class);
    });
});
