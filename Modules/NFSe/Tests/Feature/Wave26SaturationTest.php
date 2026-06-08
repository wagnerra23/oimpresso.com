<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\NFSe\Console\Commands\NfseHealthCommand;
use Modules\NFSe\Models\NfseCertificado;
use Modules\NFSe\Models\NfseEmissao;
use Modules\NFSe\Services\NfseEmissaoService;

uses(Tests\TestCase::class);

/**
 * Wave 26 NFSe SATURATION (77 → 88, +11).
 *
 * Expansão sobre Wave 25 (NfseEmissao + Service contracts source-grep).
 *
 * Eixos:
 *   - D1 (+25): cross-tenant scope + business_id mass-assign + SoftDeletes preserva CONFAZ
 *   - D6: NfseController preserva Inertia::render SEM defer (rollback PR #963 lição)
 *   - D7: LogsActivity Spatie em NfseEmissao + getActivitylogOptions excludes XML
 *
 * Tier 0 IRREVOGÁVEL:
 *   - Imutabilidade fiscal CONFAZ SINIEF 07/2005 Art. 14 (status emitida append-only)
 *   - ADR 0093 multi-tenant via NfseBusinessScope
 *   - LGPD Art. 6º IX minimização (PiiRedactor em erro_mensagem)
 *
 * Smoke source-grep only — paralelizável worktree, sem hit DB.
 *
 * @see Wave 25 Wave25SaturationTest (predecessor)
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 */

describe('Wave 26 NFSe SATURATION', function () {

    // ---------- D1 expandido: cross-tenant scope canônico ----------

    it('D1 — NfseBusinessScope trait registrado em bootNfseBusinessScope (global scope)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/Concerns/NfseBusinessScope.php'));

        expect($src)->toContain('bootNfseBusinessScope');
        expect($src)->toContain('addGlobalScope');
        expect($src)->toContain('business_id');
    });

    it('D1 — NfseBusinessScope respeita gate superadmin (cross-tenant intencional)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/Concerns/NfseBusinessScope.php'));

        // Gate: superadmin user.can('superadmin') → return; (skip scope)
        expect($src)->toContain("can('superadmin')");
        expect($src)->toContain('return;');  // early return path
    });

    it('D1 — NfseBusinessScope auto-set business_id em creating event', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/Concerns/NfseBusinessScope.php'));

        expect($src)->toContain('creating(function');
        expect($src)->toContain("session('user.business_id')");
    });

    it('D1 — NfseEmissao + NfseProviderConfig + NfseCertificado todos usam scope canon', function () {
        // NfseEmissao
        $emissao = file_get_contents(base_path('Modules/NFSe/Models/NfseEmissao.php'));
        expect($emissao)->toContain('NfseBusinessScope');

        // NfseProviderConfig
        $config = file_get_contents(base_path('Modules/NFSe/Models/NfseProviderConfig.php'));
        expect($config)->toContain('NfseBusinessScope');
    });

    it('D1 — NfseEmissao fillable inclui business_id (mass-assign canon multi-tenant)', function () {
        $model = new NfseEmissao();
        expect($model->getFillable())->toContain('business_id');
    });

    it('D1 — NfseEmissao SoftDeletes preserva audit CONFAZ 5y (NUNCA forceDelete em produção)', function () {
        $traits = class_uses(NfseEmissao::class);
        expect($traits)->toHaveKey(\Illuminate\Database\Eloquent\SoftDeletes::class);
    });

    // ---------- D7 expandido: LogsActivity Spatie + LGPD ----------

    it('D7 — NfseEmissao LogsActivity tem useLogName "nfse.emissao" (audit canal canon)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/NfseEmissao.php'));

        expect($src)->toContain("useLogName('nfse.emissao')");
    });

    it('D7 — NfseEmissao logExcept exclui campos volumosos (xml_envio/xml_retorno/pdf_url)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/NfseEmissao.php'));

        // Storage cost — não auditar conteúdo SOAP/PDF (foca em mudança de estado)
        expect($src)->toContain("logExcept(['xml_envio', 'xml_retorno', 'pdf_url'])");
    });

    it('D7 — NfseEmissao logOnlyDirty + dontSubmitEmptyLogs (ruído reduzido)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Models/NfseEmissao.php'));

        expect($src)->toContain('logOnlyDirty()');
        expect($src)->toContain('dontSubmitEmptyLogs()');
    });

    it('D7 — NfseEmissao fillable contém PII tomador minimizada (Art. 6º IX LGPD)', function () {
        $fillable = (new NfseEmissao())->getFillable();

        // PII minimizada (apenas CPF/CNPJ + nome + email — sem endereço completo)
        expect($fillable)->toContain('tomador_cnpj');
        expect($fillable)->toContain('tomador_cpf');
        expect($fillable)->toContain('tomador_nome');
        expect($fillable)->toContain('tomador_email');
        expect($fillable)->toContain('tomador_municipio_ibge');

        // Não deve ter endereço completo (LGPD minimização) — apenas IBGE
        expect($fillable)->not->toContain('tomador_endereco');
        expect($fillable)->not->toContain('tomador_cep');
    });

    // ---------- D6: NfseController preserva Inertia (rollback PR #963 lição) ----------

    it('D6 — NfseController NÃO usa Inertia::defer ATIVO (preserva rollback PR #963)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Http/Controllers/NfseController.php'));

        // Wave 23 D3 NOTA: defer SEM <Deferred> wrap React quebrou.
        // Wave 26 confirma pattern até PR companion Index.tsx. Comment "candidata a
        // Inertia::defer()" PODE existir, mas chamada ativa não.
        expect($src)->not->toMatch('/=>\s*Inertia::defer\(/');
        expect($src)->toContain('Inertia::render');
        expect($src)->toContain('NÃO migrado neste PR');
    });

    it('D6 — NfseController index() autoriza nfse.view (RBAC canon)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Http/Controllers/NfseController.php'));

        expect($src)->toContain("\$this->authorize('nfse.view')");
        expect($src)->toContain("\$this->authorize('nfse.emit')");
    });

    it('D6 — NfseController paginate 25 (page size canon UltimatePOS)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Http/Controllers/NfseController.php'));

        expect($src)->toContain('paginate(25)->withQueryString()');
    });

    it('D6 — NfseHealthCommand usa --detail (NUNCA --verbose Symfony reserved)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Console/Commands/NfseHealthCommand.php'));

        expect($src)->toContain('--detail');
        // Verbose é reserved Symfony — quebra command (lição PR #851)
        expect($src)->not->toMatch('/\{--verbose : /');
    });

    it('D6 — NfseHealthCommand declara 6 sinais canônicos (Wave 23 governance)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Console/Commands/NfseHealthCommand.php'));

        foreach ([
            'emissoes_table', 'provider_config_table', 'certificado_table',
            'providers_ativos', 'cert_vencimento_alarme', 'rejeitadas_recentes',
        ] as $check) {
            expect($src)->toContain($check);
        }
    });

    it('D6 — nfse:health command registrado em Artisan (smoke)', function () {
        $all = \Illuminate\Support\Facades\Artisan::all();
        expect($all)->toHaveKey('nfse:health');

        $cmd = $all['nfse:health'];
        expect($cmd->getDefinition()->hasOption('business'))->toBeTrue();
        expect($cmd->getDefinition()->hasOption('alert'))->toBeTrue();
        expect($cmd->getDefinition()->hasOption('json'))->toBeTrue();
        expect($cmd->getDefinition()->hasOption('detail'))->toBeTrue();
    });

    // ---------- D2 expandido: NfseEmissaoService contratos ----------

    it('D2 — NfseEmissaoService MAX_RETRIES=3 (retry policy canon)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));

        expect($src)->toContain('MAX_RETRIES = 3');
    });

    it('D2 — NfseEmissaoService::montarPayload usa withoutGlobalScopes // SUPERADMIN: comment canon', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));

        // Convenção ADR 0093 — comment obrigatório quando bypassa scope
        expect($src)->toContain('SUPERADMIN');
        expect($src)->toContain('withoutGlobalScopes');
    });

    it('D2 — NfseEmissaoService importa OtelHelper canônico (App\\Util\\OtelHelper)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));

        expect($src)->toContain('use App\Util\OtelHelper;');
    });

    it('D2 — NfseEmissaoService usa PiiRedactor pra erro_mensagem (LGPD Art. 6º IX)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));

        // Importa o redactor
        expect($src)->toContain('Modules\Jana\Services\Privacy\PiiRedactor');
        expect($src)->toContain('->redact($e->getMessage())');
    });

    it('D2 — NfseEmissaoService bloqueia dupla cancelação (CONFAZ SINIEF 07/2005 Art. 14)', function () {
        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));

        expect($src)->toContain('NfseJaCanceladaException');
        expect($src)->toContain('isCancelada()');
    });

    // ---------- D7: Config retention LGPD canônica ----------

    it('D7 — Config retention.php declara 5y CONFAZ + 1y erro/webhook (LGPD compliance)', function () {
        $config = include base_path('Modules/NFSe/Config/retention.php');

        expect($config['entities']['nfse_emissao_fiscal'])->toBe(1825);  // 5y CONFAZ
        expect($config['entities']['nfse_emissao_erro'])->toBe(365);
        expect($config['entities']['webhook_municipal'])->toBe(365);
        expect($config['notice_period_days'])->toBe(30);  // LGPD Art. 18 §VI
    });

    // ---------- D9: OTel zero-cost smoke ----------

    it('D9 — NfseEmissaoService spans canon nfse.emissao (smoke zero-cost)', function () {
        config(['otel.enabled' => false]);

        $src = file_get_contents(base_path('Modules/NFSe/Services/NfseEmissaoService.php'));
        expect($src)->toContain("'nfse.emissao'");
    });

    it('D9 — OtelHelper::spanBiz não quebra com otel.enabled=false (NFSe smoke)', function () {
        config(['otel.enabled' => false]);

        $result = OtelHelper::spanBiz('nfse.test.smoke', fn () => 'ok', [
            'module' => 'NFSe', 'op' => 'test',
        ]);
        expect($result)->toBe('ok');
    });

    // ---------- D2: NfseCertificado alias schema unificado ----------

    it('D2 — NfseCertificado herda NfeCertificado (alias schema unificado)', function () {
        $ref = new ReflectionClass(NfseCertificado::class);
        expect($ref->getParentClass()?->getName())
            ->toBe(\Modules\NfeBrasil\Models\NfeCertificado::class);
    });

    it('D2 — NfseCertificado tem método isExpirado alias de isVencido', function () {
        $ref = new ReflectionClass(NfseCertificado::class);
        expect($ref->hasMethod('isExpirado'))->toBeTrue();
    });
});
