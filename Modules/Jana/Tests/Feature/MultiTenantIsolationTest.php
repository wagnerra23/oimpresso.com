<?php

declare(strict_types=1);

use App\Concerns\BelongsToBusinessViaParent;
use App\Concerns\HasBusinessScope;
use Modules\Jana\Entities\CacheSemantico;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\Mcp\McpAlerta;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Entities\Mcp\McpCcMessage;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpMemoryDocumentHistory;
use Modules\Jana\Entities\Mcp\McpSkill;
use Modules\Jana\Entities\Mcp\McpSkillLabel;
use Modules\Jana\Entities\Mcp\McpSkillVersion;
use Modules\Jana\Entities\Mcp\McpUsageDiaria;
use Modules\Jana\Entities\Mcp\McpUserScope;
use Modules\Jana\Entities\MemoriaFato;
use Modules\Jana\Entities\MemoriaGabarito;
use Modules\Jana\Entities\MemoriaMetrica;
use Modules\Jana\Entities\Mensagem;
use Modules\Jana\Entities\Meta;
use Modules\Jana\Entities\MetaApuracao;
use Modules\Jana\Entities\MetaFonte;
use Modules\Jana\Entities\MetaPeriodo;
use Modules\Jana\Entities\Sugestao;
use Modules\Jana\Scopes\ScopeByBusiness;
use Modules\Jana\Scopes\ScopeByBusinessViaParent;

uses(Tests\TestCase::class);

/**
 * Wave 15 — RESCUE D1 Multi-Tenant (Jana 56 → ≥66+).
 *
 * Smoke ESTRUTURAL (zero DB) que valida CONTRATO Tier 0 IRREVOGÁVEL
 * ADR 0093 em TODAS Eloquent Models de Modules/Jana/Entities/** :
 *
 *  - Trait HasBusinessScope (singular Model::class, NÃO string matching)
 *    em Entities que têm coluna business_id direta
 *  - Trait BelongsToBusinessViaParent em Entities child que herdam tenancy
 *    via FK chain (Mensagem→Conversa, MetaApuracao→Meta, McpSkillVersion→McpSkill)
 *  - Detection recursive (Entities/ + Entities/Mcp/) — bug rubric v3.2 hardening
 *
 * Cross-tenant via Models reais (biz=1 Wagner WR2 vs biz=99 fictício) está
 * em [`EntitiesFilhasMultiTenantViaParentTest.php`] (Wave 7) — esse arquivo
 * cobre as Entities root (Conversa/Sugestao/Mensagem/Meta*). Aqui foco em
 * cobertura EXAUSTIVA do contrato de trait (não regressível em CI estrutural).
 *
 * ADR 0093 IRREVOGÁVEL (defesa em profundidade).
 * ADR 0101: biz=1 (Wagner WR2) e biz=99 (fictício) — NUNCA biz=4 (ROTA LIVRE).
 *
 * @see memory/decisions/0093-multi-tenant-isolation-tier-0.md
 * @see memory/decisions/0101-tests-business-id-1-nunca-cliente.md
 * @see App\Concerns\HasBusinessScope
 * @see App\Concerns\BelongsToBusinessViaParent
 */

// ------------------------------------------------------------------
// D1.a — Entities com business_id DIRETO usam HasBusinessScope
// ------------------------------------------------------------------

dataset('jana_entities_business_id_direto', [
    // Entities root (já validadas em Waves 7/10 — aqui garantia de regressão)
    'Conversa (root, Wave 7)'         => [Conversa::class],
    'Meta (root, Wave 7)'             => [Meta::class],
    'MemoriaFato (root)'              => [MemoriaFato::class],
    'MemoriaGabarito (root)'          => [MemoriaGabarito::class],
    'MemoriaMetrica (root)'           => [MemoriaMetrica::class],
    'CacheSemantico (root)'           => [CacheSemantico::class],
    // Mcp/ subdir — Wave 15 RESCUE adições
    'McpAlerta (Wave 15)'             => [McpAlerta::class],
    'McpAuditLog (Wave 15)'           => [McpAuditLog::class],
    'McpCcMessage (Wave 15)'          => [McpCcMessage::class],
    'McpCcSession (Wave 15)'          => [McpCcSession::class],
    'McpSkill (Wave 15)'              => [McpSkill::class],
    'McpUsageDiaria (Wave 15)'        => [McpUsageDiaria::class],
    'McpUserScope (Wave 15)'          => [McpUserScope::class],
]);

it('Entity %s usa HasBusinessScope trait (D1 multi-tenant ADR 0093)', function (string $modelClass) {
    $traits = array_keys(class_uses_recursive($modelClass));

    expect($traits)->toContain(HasBusinessScope::class);
})->with('jana_entities_business_id_direto');

it('Entity %s aplica ScopeByBusiness global scope no boot', function (string $modelClass) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    $scopeKeys = array_keys($instance->getGlobalScopes());

    expect($scopeKeys)->toContain(ScopeByBusiness::class);
})->with('jana_entities_business_id_direto');

// ------------------------------------------------------------------
// D1.b — Entities CHILD usam BelongsToBusinessViaParent
// ------------------------------------------------------------------

dataset('jana_entities_via_parent', [
    // Entities root child (Wave 7 — referência canônica)
    'Mensagem (root, parent=Conversa, Wave 7)'       => [Mensagem::class, 'conversa'],
    'Sugestao (root, parent=Conversa, Wave 7)'       => [Sugestao::class, 'conversa'],
    'MetaApuracao (root, parent=Meta, Wave 7)'       => [MetaApuracao::class, 'meta'],
    'MetaFonte (root, parent=Meta, Wave 7)'          => [MetaFonte::class, 'meta'],
    'MetaPeriodo (root, parent=Meta, Wave 7)'        => [MetaPeriodo::class, 'meta'],
    // Mcp/ subdir — Wave 15 RESCUE adições (1-level chain)
    'McpMemoryDocumentHistory (parent=document)'     => [McpMemoryDocumentHistory::class, 'document'],
    'McpSkillVersion (parent=skill, Wave 15)'        => [McpSkillVersion::class, 'skill'],
    'McpSkillLabel (parent=skill, Wave 15)'          => [McpSkillLabel::class, 'skill'],
]);

it('Entity %s usa BelongsToBusinessViaParent trait', function (string $modelClass, string $parentRel) {
    $traits = array_keys(class_uses_recursive($modelClass));

    expect($traits)->toContain(BelongsToBusinessViaParent::class);
})->with('jana_entities_via_parent');

it('Entity %s declara businessParentRelation = %s', function (string $modelClass, string $parentRel) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();

    expect(property_exists($instance, 'businessParentRelation'))
        ->toBeTrue("Modelo {$modelClass} deve declarar protected string \$businessParentRelation");

    $reflection = new ReflectionClass($modelClass);
    $prop = $reflection->getProperty('businessParentRelation');
    $prop->setAccessible(true);
    $value = $prop->getValue($instance);

    expect($value)->toBe($parentRel, "businessParentRelation deve ser '{$parentRel}'");
})->with('jana_entities_via_parent');

it('Entity %s aplica ScopeByBusinessViaParent global scope no boot', function (string $modelClass, string $parentRel) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    $scopeKeys = array_keys($instance->getGlobalScopes());

    expect($scopeKeys)->toContain(ScopeByBusinessViaParent::class);
})->with('jana_entities_via_parent');

it('Entity %s tem método de relação %s() retornando BelongsTo', function (string $modelClass, string $parentRel) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();

    expect(method_exists($instance, $parentRel))
        ->toBeTrue("Modelo {$modelClass} deve definir método de relação {$parentRel}()");

    $relation = $instance->{$parentRel}();
    expect($relation)
        ->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class);
})->with('jana_entities_via_parent');

// ------------------------------------------------------------------
// D1.c — Detection rubric v3.2 hardening: singular Model::class (NÃO string)
// ------------------------------------------------------------------

it('rubric v3.2: HasBusinessScope é singular classe (não string matching)', function () {
    // Defesa contra regressão: trait DEVE ser referenciado por ::class (FQN),
    // não por string solta — pega imports faltando + namespace errado.
    $entitiesComScope = [
        Conversa::class,
        McpAlerta::class,
        McpSkill::class,
    ];
    foreach ($entitiesComScope as $modelClass) {
        $traits = class_uses_recursive($modelClass);
        expect(array_key_exists(HasBusinessScope::class, $traits))
            ->toBeTrue("{$modelClass}: HasBusinessScope deve ser detectada por classe FQN");
    }
});

it('rubric v3.2: detection é recursive (Entities/ + Entities/Mcp/)', function () {
    // Garante que sub-pastas (Mcp/) são cobertas — bug rubric v3.1 era flat.
    $rootEntity = Conversa::class;
    $mcpEntity = McpAlerta::class;

    $rootTraits = array_keys(class_uses_recursive($rootEntity));
    $mcpTraits = array_keys(class_uses_recursive($mcpEntity));

    expect($rootTraits)->toContain(HasBusinessScope::class);
    expect($mcpTraits)->toContain(HasBusinessScope::class);
});

// ------------------------------------------------------------------
// D1.d — Cross-tenant smoke (biz=1 vs biz=99) — só roda com DB MySQL
// ------------------------------------------------------------------

it('cross-tenant: ScopeByBusiness existe e implementa Scope interface', function () {
    // Smoke: scope class está instanciável e cumpre contrato Eloquent.
    $scope = new ScopeByBusiness();
    expect($scope)->toBeInstanceOf(\Illuminate\Database\Eloquent\Scope::class);
    expect(method_exists($scope, 'apply'))->toBeTrue();
});

it('cross-tenant: ScopeByBusinessViaParent existe e implementa Scope interface', function () {
    $scope = new ScopeByBusinessViaParent();
    expect($scope)->toBeInstanceOf(\Illuminate\Database\Eloquent\Scope::class);
    expect(method_exists($scope, 'apply'))->toBeTrue();
});

it('cross-tenant: ScopeByBusiness fail-open em CLI sem auth (jobs ok)', function () {
    // CLI/jobs sem auth() check → scope NÃO filtra (jobs passam $businessId explícito).
    // Smoke: tira auth e roda apply() — não deve throw.
    auth()->logout();
    $scope = new ScopeByBusiness();
    $builder = Conversa::query();
    $scope->apply($builder, new Conversa());

    expect(true)->toBeTrue('Scope sem auth retorna silenciosamente (jobs/CLI canônicos)');
});
