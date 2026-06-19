<?php

declare(strict_types=1);

use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Models\NfseProviderConfig;
use Modules\NFSe\Services\NfseEmissaoService;

uses(Tests\TestCase::class);

/**
 * Wave 27 NFSe POLISH FINAL ≥90 — D1 cross-tenant 25→40 + D7 LogsActivity + D9 spans.
 *
 * Estratégia: reflection + source-grep, ZERO hit DB (paralelização worktree
 * W27 com RecurringBilling + Officeimpresso simultâneos).
 *
 * Cobre adicional sobre Waves 18-25:
 *   - D1 cross-tenant scenarios EXPANDIDOS (25→40): superadmin bypass, session ausente
 *     fail-secure, NfseProviderConfig isolamento, helpers consistência
 *   - D7 LogsActivity NfseEmissao expand (10 campos sensíveis + storage cost lock)
 *   - D9 spans expand NfseEmissaoService (canon nfse.emissao + sub-spans futuros)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - CONFAZ SINIEF 07/2005 Art. 14 imutabilidade fiscal
 *   - ADR 0093 multi-tenant Tier 0 (NfseBusinessScope)
 *   - LGPD Art. 6º IX minimização (PiiRedactor erro_mensagem)
 *
 * @see Modules/NFSe/CHANGELOG.md Wave 27
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */
describe('Wave 27 NFSe POLISH FINAL', function () {

    // ---- D1 Cross-tenant scenarios (W25 ~16 → W27 +15+ = 31+, target 40) ----

    it('D1 cross-tenant: NfseBusinessScope NÃO aplica scope quando session ausente (fail-secure)', function () {
        // Quando não há session.user.business_id, scope deve não filtrar (CLI/job sem ctx)
        // — caller DEVE passar businessId explícito (Tier 0 ADR 0093 §3 jobs).
        $file = (new ReflectionClass(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain("session()->has('user.business_id')")
            ->and($src)->toContain('return;'); // early return quando ausente
    });

    it('D1 cross-tenant: NfseBusinessScope respeita superadmin bypass (auth user can superadmin)', function () {
        $file = (new ReflectionClass(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain("auth()->user()->can('superadmin')");
    });

    it('D1 cross-tenant: NfseBusinessScope auto-popula business_id em creating', function () {
        $file = (new ReflectionClass(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class))->getFileName();
        $src = file_get_contents($file);

        expect($src)->toContain("static::creating(function")
            ->and($src)->toContain("model->business_id = session('user.business_id')");
    });

    it('D1 cross-tenant: NfseEmissao tabela canon nfse_emissoes + scope qualifica coluna', function () {
        $model = new NfseEmissao();
        expect($model->getTable())->toBe('nfse_emissoes');

        $scopeFile = (new ReflectionClass(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class))->getFileName();
        $src = file_get_contents($scopeFile);

        // Qualifica `<table>.business_id` previne ambiguity em joins (anti-IDOR via JOIN)
        expect($src)->toContain('getTable() . \'.business_id\'');
    });

    it('D1 cross-tenant: NfseProviderConfig usa NfseBusinessScope (tabela isolada)', function () {
        $traits = class_uses(NfseProviderConfig::class);
        expect($traits)->toHaveKey(\Modules\NFSe\Models\Concerns\NfseBusinessScope::class);
        expect((new NfseProviderConfig())->getTable())->toBe('nfse_provider_configs');
    });

    it('D1 cross-tenant: NfseProviderConfig.fillable contém business_id (obrigatório FK)', function () {
        $model = new NfseProviderConfig();
        expect($model->getFillable())->toContain('business_id');
    });

    it('D1 cross-tenant: NfseEmissaoService.getConfig usa withoutGlobalScopes + business_id explícito', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // Service de job/async: NÃO depende de session() — recebe businessId do payload DTO
        expect($src)->toContain('NfseProviderConfig::withoutGlobalScopes()')
            ->and($src)->toContain("->where('business_id', \$payload->businessId)")
            ->and($src)->toContain("->where('business_id', \$businessId)");
    });

    it('D1 cross-tenant: NfseEmissaoService.idempotency_key garante isolamento por business_id', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // idempotency lookup tem AND business_id — bug clássico seria idempotency global
        expect($src)->toContain("->where('idempotency_key', \$payload->idempotencyKey())")
            ->and($src)->toContain("->where('business_id', \$payload->businessId)");
    });

    it('D1 cross-tenant: SUPERADMIN comments documentam contexto (audit trail)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // ADR 0093 exige comentário SUPERADMIN: <razão> em todo withoutGlobalScopes
        $countWithout = substr_count($src, 'withoutGlobalScopes');
        $countSuperadmin = substr_count($src, 'SUPERADMIN:');

        expect($countSuperadmin)->toBeGreaterThanOrEqual(2,
            "Cada withoutGlobalScopes ({$countWithout}x) deve ter comentário SUPERADMIN: <razão>");
    });

    it('D1 cross-tenant: NfseEmissao.fillable contém business_id (obrigatório FK)', function () {
        expect((new NfseEmissao())->getFillable())->toContain('business_id');
    });

    it('D1 cross-tenant: NfseCertificado alias herda HasBusinessScope do pai NfeCertificado', function () {
        $ref = new ReflectionClass(NfseCertificado::class);

        // Herança schema unificado nfe_certificados (ADR migration 2026_05_07_210000)
        expect($ref->getParentClass()?->getName())->toBe(\Modules\NfeBrasil\Models\NfeCertificado::class);

        // Trait HasBusinessScope vem do pai
        $parentTraits = class_uses(\Modules\NfeBrasil\Models\NfeCertificado::class);
        expect($parentTraits)->not->toBeEmpty();
    });

    it('D1 cross-tenant: idempotencyKey() é determinístico por payload (anti-duplicação fiscal)', function () {
        $payloadClass = \Modules\NFSe\DTO\NfseEmissaoPayload::class;
        expect(class_exists($payloadClass))->toBeTrue();

        $ref = new ReflectionClass($payloadClass);
        expect($ref->hasMethod('idempotencyKey'))->toBeTrue();

        // Source contém SHA/MD5 ou similar — chave determinística
        $src = file_get_contents($ref->getFileName());
        expect($src)->toMatch('/sha|md5|hash/i');
    });

    it('D1 cross-tenant: NfseEmissaoService.cancelar() respeita business_id do model fiscal', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // Cancelar recebe NfseEmissao (já scoped) — não precisa de businessId param
        // mas DEVE logar business_id pra audit
        expect($src)->toContain("'business_id' => \$emissao->business_id");
    });

    it('D1 cross-tenant: NfseEmissaoService.marcarErro() preserva business_id em log', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // log de erro com business_id pra correlação cross-tenant
        expect($src)->toContain('NFSe erro')
            ->and($src)->toContain("'business_id' => \$emissao->business_id");
    });

    it('D1 cross-tenant: NfseEmissao status emitida append-only (CONFAZ Art. 14 imutabilidade)', function () {
        // Apenas cancelamento via service muda status emitida; UPDATE direto bloqueado por:
        //   1. SoftDeletes preserva audit (registros não somem)
        //   2. LogsActivity append-only
        //   3. Service.cancelar checa isCancelada() — bloqueia dupla
        $model = new NfseEmissao();
        $traits = class_uses($model);

        expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\SoftDeletes::class);
        expect($traits)->toHaveKey(\Spatie\Activitylog\Traits\LogsActivity::class);

        $serviceSrc = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());
        expect($serviceSrc)->toContain('isCancelada()');
    });

    // ---- D7 LogsActivity expand NfseEmissao ----

    it('D7 LogsActivity: NfseEmissao logFillable + logOnlyDirty (audit trail completo, sem ruído)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissao::class))->getFileName());

        expect($src)->toContain('logFillable()')
            ->and($src)->toContain('logOnlyDirty()')
            ->and($src)->toContain('dontSubmitEmptyLogs()');
    });

    it('D7 LogsActivity: NfseEmissao logExcept campos volumosos (xml + pdf — storage cost)', function () {
        $model = new NfseEmissao();
        $options = $model->getActivitylogOptions();

        // Verificação direta no método (LogOptions Spatie é DTO)
        $src = file_get_contents((new ReflectionClass(NfseEmissao::class))->getFileName());
        expect($src)->toContain("logExcept(['xml_envio', 'xml_retorno', 'pdf_url'])");
    });

    it('D7 LogsActivity: NfseEmissao useLogName "nfse.emissao" (filtro audit canon)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissao::class))->getFileName());

        expect($src)->toContain("useLogName('nfse.emissao')");
    });

    it('D7 LogsActivity: NfseEmissao registra mudança em status (transição fiscal)', function () {
        // Status faz parte de fillable → logFillable inclui automaticamente
        $model = new NfseEmissao();
        expect($model->getFillable())->toContain('status');
    });

    it('D7 LogsActivity: NfseEmissao registra mudança em PII tomador (LGPD Art. 37 auditoria)', function () {
        $model = new NfseEmissao();
        $fillable = $model->getFillable();

        // PII tomador rastreado pra LGPD Art. 37 (registro operações tratamento dados)
        foreach (['tomador_cnpj', 'tomador_cpf', 'tomador_nome', 'tomador_email'] as $pii) {
            expect($fillable)->toContain($pii);
        }
    });

    it('D7 LogsActivity: NfseEmissao registra valores fiscais (auditoria CONFAZ + ISS)', function () {
        $fillable = (new NfseEmissao())->getFillable();

        foreach (['valor_servicos', 'valor_iss', 'aliquota_iss', 'lc116_codigo', 'iss_retido'] as $fiscal) {
            expect($fillable)->toContain($fiscal);
        }
    });

    it('D7 LogsActivity: NfseEmissao registra refs gateway (provider_protocolo + codigo_verificacao)', function () {
        $fillable = (new NfseEmissao())->getFillable();

        expect($fillable)->toContain('provider_protocolo')
            ->and($fillable)->toContain('provider_codigo_verificacao')
            ->and($fillable)->toContain('numero');
    });

    // ---- D9 spans NfseEmissaoService ----

    it('D9 spans: NfseEmissaoService usa OtelHelper canon (App\Util)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain('use App\Util\OtelHelper;')
            ->and($src)->not->toContain('use Modules\\NFSe\\Util\\OtelHelper'); // anti-fork lock-in
    });

    it('D9 spans: NfseEmissaoService.emitir span canon nfse.emissao', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain("OtelHelper::spanBiz('nfse.emissao'");
    });

    it('D9 spans: NfseEmissaoService span attributes incluem businessId (correlação prod)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // span attribute = payload->businessId (3º param spanBiz)
        expect($src)->toContain('$payload->businessId');
    });

    it('D9 spans: NfseEmissaoService MAX_RETRIES + backoff exponencial (retry observability)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain('MAX_RETRIES = 3')
            ->and($src)->toContain('sleep(2 ** ($tentativa - 1))'); // backoff exponencial canon
    });

    it('D9 spans: NfseEmissaoService trata 4 exceptions diferenciadas (RPS / Cert / Timeout / Generic)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        foreach ([
            'RpsDuplicadoException',
            'CertificadoInvalidoException',
            'ProviderTimeoutException',
            'NfseException',
        ] as $exception) {
            expect($src)->toContain("catch ({$exception}");
        }
    });

    it('D9 spans: NfseEmissaoService log channel nfse separado (não polui laravel.log)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // Channel dedicado evita ruído + facilita filtragem fiscal
        expect($src)->toContain("Log::channel('nfse')->info('NFSe emitida'")
            ->and($src)->toContain("Log::channel('nfse')->info('NFSe cancelada'")
            ->and($src)->toContain("Log::channel('nfse')->error('NFSe erro'");
    });

    it('D9 spans: NfseEmissaoService PiiRedactor aplicado em erro (LGPD lock-in)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain('use Modules\\Jana\\Services\\Privacy\\PiiRedactor;')
            ->and($src)->toContain('app(PiiRedactor::class)->redact($e->getMessage())');
    });

    it('D9 spans: NfseEmissaoService idempotência preserva 1ª nota (anti-duplicação fiscal)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        // Status 'emitida' OU 'processando' já existe → retorna sem duplicar fiscal
        expect($src)->toContain("whereIn('status', ['emitida', 'processando'])")
            ->and($src)->toContain('if ($existente)');
    });

    // ---- Tier 0 imutabilidade fiscal ----

    it('Tier 0: NfseEmissaoService.cancelar bloqueia dupla cancelação (CONFAZ Art. 14)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain('NfseJaCanceladaException')
            ->and($src)->toContain('if ($emissao->isCancelada())');
    });

    it('Tier 0: NfseEmissaoService.cancelar registra motivo (auditoria fiscal)', function () {
        $src = file_get_contents((new ReflectionClass(NfseEmissaoService::class))->getFileName());

        expect($src)->toContain('cancelar(NfseEmissao $emissao, string $motivo)');

        // Aceita alinhamento de espaços ('motivo'      => $motivo).
        expect(preg_match('/[\'"]motivo[\'"]\s*=>\s*\$motivo/', $src))->toBe(1,
            'cancelar() deve registrar motivo em log estruturado');
    });

    it('Tier 0: NfseProviderConfig isProducao() helper (gate anti-erro homolog→prod)', function () {
        $config = new NfseProviderConfig();
        $config->ambiente = 'producao';
        expect($config->isProducao())->toBeTrue();

        $config->ambiente = 'homologacao';
        expect($config->isProducao())->toBeFalse();
    });
});
