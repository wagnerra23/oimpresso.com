<?php

declare(strict_types=1);

use App\Util\OtelHelper;
use Modules\Officeimpresso\Http\Requests\BulkRevokeLicencaRequest;
use Modules\Officeimpresso\Http\Requests\RevokeLicencaRequest;
use Modules\Officeimpresso\Http\Requests\StoreLicencaRequest;
use Modules\Officeimpresso\Http\Requests\UpdateEmpresaConfigRequest;
use Modules\Officeimpresso\Http\Requests\UpdateLicencaRequest;
use Modules\Officeimpresso\Services\LicencaAuditService;
use Modules\Officeimpresso\Services\LicencaService;

uses(Tests\TestCase::class);

/**
 * Wave 27 Officeimpresso POLISH FINAL ≥88 — D2 Pest LicencaService+AuditService
 * + D8 FormRequests adicionais + D9 spans Services.
 *
 * Estratégia: reflection + source-grep + FormRequest rules read, ZERO hit DB
 * (paralelização worktree W27 com RecurringBilling + NFSe simultâneos).
 *
 * Cobre adicional sobre Waves 18-25:
 *   - D2 LicencaService API completa (8 métodos canon) + retorno tipos
 *   - D2 LicencaAuditService append-only (Lei 9.609/98) + extrairEMascarar
 *   - D8 FormRequest adicional UpdateEmpresaConfigRequest (regex versão + path traversal)
 *   - D8 Audit canon: BulkRevoke motivo obrigatório (LGPD)
 *   - D9 spans LicencaService (≥7 atual) + LicencaAuditService (1 canon)
 *
 * Tier 0 IRREVOGÁVEIS preservados:
 *   - Bridge Delphi WR Comercial (StoreLicencaRequest preserva schema legacy)
 *   - Lei Software 9.609/98 retention 5y (LicencaLog audit append-only)
 *   - Multi-tenant Tier 0 (ADR 0093): Controller filtra IDs antes bulk
 *   - LGPD Art. 6º IX (PiiRedactor em LicencaAuditService)
 *
 * @see Modules/Officeimpresso/CHANGELOG.md Wave 27
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0094-constituicao-v2-7-camadas-8-principios.md §5 SoC
 */
describe('Wave 27 Officeimpresso POLISH FINAL', function () {

    // ---- D2 LicencaService API completa ----

    it('D2 LicencaService: expõe 8 métodos públicos canônicos', function () {
        $methods = collect((new ReflectionClass(LicencaService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor())
            ->pluck('name')
            ->toArray();

        $canonicos = [
            'listarPorEmpresa',
            'buscarParaEdit',
            'criar',
            'atualizar',
            'remover',
            'alternarBloqueio',
            'atualizarEmpresa',
            'alternarBloqueioEmpresa',
            'listarEmpresasComDesktop',
        ];

        foreach ($canonicos as $m) {
            expect(in_array($m, $methods, true))->toBeTrue("LicencaService deve expor método público {$m}");
        }
    });

    it('D2 LicencaService: businessId é int explícito em métodos multi-tenant (Tier 0)', function () {
        $ref = new ReflectionClass(LicencaService::class);

        // Métodos que recebem businessId explícito (ADR 0093 — Jobs/callers sem session)
        $methodsComBiz = [
            'listarPorEmpresa'        => 0, // index 0 = primeiro param
            'buscarParaEdit'          => 1, // segundo (id, businessId)
            'atualizarEmpresa'        => 0,
            'alternarBloqueioEmpresa' => 0,
        ];

        foreach ($methodsComBiz as $method => $bizIdx) {
            $params = $ref->getMethod($method)->getParameters();
            expect($params[$bizIdx]->getName())->toBe('businessId',
                "LicencaService::{$method} deve receber businessId explicito (Tier 0)");
            expect((string) $params[$bizIdx]->getType())->toBe('int');
        }
    });

    it('D2 LicencaService: criar retorna Licenca_Computador (tipo concreto)', function () {
        $ref = (new ReflectionClass(LicencaService::class))->getMethod('criar');
        expect((string) $ref->getReturnType())->toContain('Licenca_Computador');
    });

    it('D2 LicencaService: atualizar retorna nullable (404 sem find)', function () {
        $ref = (new ReflectionClass(LicencaService::class))->getMethod('atualizar');
        $type = $ref->getReturnType();

        expect($type?->allowsNull())->toBeTrue('atualizar deve aceitar null (model not found)');
    });

    it('D2 LicencaService: remover retorna bool (true se removeu, false sem find)', function () {
        $ref = (new ReflectionClass(LicencaService::class))->getMethod('remover');
        expect((string) $ref->getReturnType())->toBe('bool');
    });

    // ---- D2 LicencaAuditService (Lei 9.609/98 append-only) ----

    it('D2 AuditService: expõe APENAS método registrar (append-only Lei 9.609/98)', function () {
        $methods = collect((new ReflectionClass(LicencaAuditService::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->reject(fn ($m) => $m->isConstructor() || $m->isDestructor())
            ->pluck('name')
            ->toArray();

        expect($methods)->toContain('registrar');

        // Lei Software 9.609/98 — append-only: SEM update/delete/destroy
        foreach ($methods as $m) {
            expect(strtolower($m))->not->toStartWith('update');
            expect(strtolower($m))->not->toStartWith('delete');
            expect(strtolower($m))->not->toStartWith('destroy');
            expect(strtolower($m))->not->toStartWith('remov');
        }
    });

    it('D2 AuditService: CAMPOS_CONHECIDOS canon 8 keys (event/licenca/error/endpoint/http)', function () {
        $ref = new ReflectionClass(LicencaAuditService::class);
        $const = $ref->getReflectionConstant('CAMPOS_CONHECIDOS');

        expect($const)->not->toBeFalse();

        $campos = $const->getValue();
        foreach (['event', 'licenca_id', 'error_code', 'error_message',
                  'endpoint', 'http_method', 'http_status', 'duration_ms'] as $f) {
            expect(in_array($f, $campos, true))->toBeTrue("CAMPOS_CONHECIDOS deve incluir {$f}");
        }
    });

    it('D2 AuditService: PiiRedactor opcional via constructor (DI flexível)', function () {
        $ref = (new ReflectionClass(LicencaAuditService::class))->getConstructor();
        $params = $ref->getParameters();

        expect($params)->toHaveCount(1);
        expect($params[0]->getName())->toBe('piiRedactor');
        expect($params[0]->allowsNull())->toBeTrue('PiiRedactor deve ser nullable (fallback redacted)');
    });

    it('D2 AuditService: fallback REDACTED quando PiiRedactor null (defense in depth)', function () {
        $src = file_get_contents((new ReflectionClass(LicencaAuditService::class))->getFileName());

        expect($src)->toContain('[REDACTED:PII_FALLBACK]')
            ->and($src)->toContain('[REDACTED:METADATA_PII_FALLBACK]');
    });

    // ---- D8 FormRequests adicionais ----

    it('D8 FormRequest: 5 FormRequests canon carregam (Store/Update/Revoke/Bulk/UpdateEmpresaConfig)', function () {
        $classes = [
            StoreLicencaRequest::class,
            UpdateLicencaRequest::class,
            RevokeLicencaRequest::class,
            BulkRevokeLicencaRequest::class,
            UpdateEmpresaConfigRequest::class, // NOVO Wave 27
        ];

        foreach ($classes as $c) {
            expect(class_exists($c))->toBeTrue("FormRequest {$c} deve existir");
            expect(is_subclass_of($c, \Illuminate\Foundation\Http\FormRequest::class))->toBeTrue();
        }
    });

    it('D8 FormRequest: UpdateEmpresaConfigRequest valida path traversal (anti-LFI)', function () {
        $req = new UpdateEmpresaConfigRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('caminho_banco_servidor');
        // not_regex bloqueia ".." e "~" — anti path traversal Delphi
        $rule = is_array($rules['caminho_banco_servidor'])
            ? implode('|', $rules['caminho_banco_servidor'])
            : $rules['caminho_banco_servidor'];
        expect($rule)->toContain('not_regex');
        expect($rule)->toMatch('/\\.\\.|~/');
    });

    it('D8 FormRequest: UpdateEmpresaConfigRequest valida versão regex semver-like', function () {
        $req = new UpdateEmpresaConfigRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('versao_obrigatoria');
        expect($rules)->toHaveKey('versao_disponivel');

        $ruleObr = implode('|', $rules['versao_obrigatoria']);
        expect($ruleObr)->toContain('regex');
        expect($ruleObr)->toContain('max:20'); // formato legado WR
    });

    it('D8 FormRequest: UpdateEmpresaConfigRequest cap numerodemaquinas 9999 (anti-fraud)', function () {
        $rules = (new UpdateEmpresaConfigRequest())->rules();

        expect($rules)->toHaveKey('officeimpresso_numerodemaquinas');
        $rule = implode('|', $rules['officeimpresso_numerodemaquinas']);

        expect($rule)->toContain('integer');
        expect($rule)->toContain('min:1');
        expect($rule)->toContain('max:9999');
    });

    it('D8 FormRequest: UpdateEmpresaConfigRequest todos campos sometimes (PATCH-friendly)', function () {
        $rules = (new UpdateEmpresaConfigRequest())->rules();

        // PATCH-friendly: Wagner pode atualizar 1 campo só (ex: liberar versao_disponivel)
        foreach (['caminho_banco_servidor', 'versao_obrigatoria', 'versao_disponivel', 'officeimpresso_numerodemaquinas'] as $field) {
            $rule = implode('|', $rules[$field]);
            expect(str_contains($rule, 'sometimes'))->toBeTrue("Campo {$field} deve ser 'sometimes' (PATCH-friendly)");
        }
    });

    it('D8 FormRequest: BulkRevokeLicencaRequest cap 100 IDs + motivo obrigatório (LGPD)', function () {
        $rules = (new BulkRevokeLicencaRequest())->rules();

        expect($rules)->toHaveKey('licenca_ids');
        $idsRule = implode('|', $rules['licenca_ids']);
        expect($idsRule)->toContain('max:100'); // defesa-em-profundidade vs IDOR/DoS

        expect($rules)->toHaveKey('motivo');
        $motivoRule = implode('|', $rules['motivo']);
        expect($motivoRule)->toContain('required'); // audit LGPD
        expect($motivoRule)->toContain('min:5');
    });

    it('D8 FormRequest: StoreLicencaRequest bridge Delphi preserva schema legacy', function () {
        $rules = (new StoreLicencaRequest())->rules();

        // Campos contrato Delphi (NÃO renomear — sincronização HTTP)
        foreach (['licenca_id', 'hd', 'processador', 'memoria', 'versao_exe'] as $field) {
            expect(array_key_exists($field, $rules))->toBeTrue("StoreLicencaRequest deve preservar campo Delphi {$field}");
        }
    });

    it('D8 FormRequest: UpdateLicencaRequest hd unique ignore própria licenca (rota id)', function () {
        $src = file_get_contents((new ReflectionClass(UpdateLicencaRequest::class))->getFileName());

        expect($src)->toContain('Rule::unique')
            ->and($src)->toContain('->ignore($id)') // permite trocar processador sem conflict
            ->and($src)->toContain("\$this->route('licenca_computador')");
    });

    // ---- D9 spans Services ----

    it('D9 spans: LicencaService ≥7 OtelHelper::spanBiz (8 métodos públicos)', function () {
        $src = file_get_contents((new ReflectionClass(LicencaService::class))->getFileName());

        $count = substr_count($src, 'OtelHelper::spanBiz');
        expect($count)->toBeGreaterThanOrEqual(7,
            "LicencaService deve ter ≥7 spans canon — atual {$count} (target 8 métodos = 8 spans)");
    });

    it('D9 spans: LicencaService prefix officeimpresso.* canon', function () {
        $src = file_get_contents((new ReflectionClass(LicencaService::class))->getFileName());

        // Todos spans usam prefix officeimpresso.*
        foreach ([
            'officeimpresso.licenca.listar',
            'officeimpresso.licenca.buscar',
            'officeimpresso.licenca.criar',
            'officeimpresso.licenca.atualizar',
            'officeimpresso.licenca.remover',
            'officeimpresso.licenca.alternar_bloqueio',
            'officeimpresso.empresa.atualizar',
            'officeimpresso.empresa.alternar_bloqueio',
        ] as $span) {
            expect(str_contains($src, "'{$span}'"))->toBeTrue("Span canon {$span} ausente");
        }
    });

    it('D9 spans: LicencaService attributes incluem module=Officeimpresso', function () {
        $src = file_get_contents((new ReflectionClass(LicencaService::class))->getFileName());

        expect($src)->toContain("'module' => 'Officeimpresso'");
    });

    it('D9 spans: LicencaService licenca_id propagado em spans single-record', function () {
        $src = file_get_contents((new ReflectionClass(LicencaService::class))->getFileName());

        // Spans buscar/atualizar/remover/alternar_bloqueio propagam licenca_id pra correlação
        expect($src)->toContain("'licenca_id' => \$id");
    });

    it('D9 spans: LicencaAuditService span registrar com attributes canon', function () {
        $src = file_get_contents((new ReflectionClass(LicencaAuditService::class))->getFileName());

        expect($src)->toContain("OtelHelper::spanBiz('officeimpresso.licenca_audit.registrar'");

        // Atributos canon com tolerância a alinhamento de espaços/padding
        expect(preg_match("/['\"]module['\"]\s*=>\s*['\"]Officeimpresso['\"]/", $src))->toBe(1,
            'LicencaAuditService deve declarar module => Officeimpresso em span attributes');
        expect($src)->toContain("'event'")
            ->and($src)->toContain("'has_error'")
            ->and($src)->toContain("'http_status'");
    });

    it('D9 spans: OtelHelper canon (App\Util — não fork dentro do módulo)', function () {
        foreach ([LicencaService::class, LicencaAuditService::class] as $serviceClass) {
            $src = file_get_contents((new ReflectionClass($serviceClass))->getFileName());

            expect($src)->toContain('use App\Util\OtelHelper;');
            expect($src)->not->toContain('use Modules\\Officeimpresso\\Util\\OtelHelper');
        }
    });

    it('D9 spans: OtelHelper no-op preserva retorno arbitrário (CI sem otel)', function () {
        config()->set('otel.enabled', false);

        // Smoke pra garantir que spans não quebram retorno
        $arr = OtelHelper::spanBiz('officeimpresso.w27.test.array', fn () => ['ok' => true, 'gateway' => 'delphi']);
        expect($arr)->toBe(['ok' => true, 'gateway' => 'delphi']);

        $bool = OtelHelper::spanBiz('officeimpresso.w27.test.bool', fn () => false);
        expect($bool)->toBeFalse();
    });

    // ---- Tier 0 Lei 9.609/98 lock-in ----

    it('Tier 0 Lei 9.609/98: LicencaLog Model SEM SoftDeletes (retention 5y hard preserva audit)', function () {
        $traits = class_uses(\Modules\Officeimpresso\Entities\LicencaLog::class);

        // SoftDeletes permitiria deleted_at — Lei 9.609/98 exige retention 5y hard
        expect($traits)->not->toHaveKey(\Illuminate\Database\Eloquent\SoftDeletes::class);
    });

    it('Tier 0 Lei 9.609/98: LicencaAuditService NÃO loga payload bruto sem redactor', function () {
        $src = file_get_contents((new ReflectionClass(LicencaAuditService::class))->getFileName());

        // PiiRedactor SEMPRE aplicado em error_message + metadata
        expect($src)->toContain('$this->piiRedactor->redact')
            ->and($src)->toContain('$this->piiRedactor->redactArray');
    });

    it('Tier 0: BulkRevoke motivo obrigatório (audit retention 5y)', function () {
        $rules = (new BulkRevokeLicencaRequest())->rules();
        expect(implode('|', $rules['motivo']))->toContain('required');
    });
});
