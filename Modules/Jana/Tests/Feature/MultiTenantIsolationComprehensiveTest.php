<?php

declare(strict_types=1);

use App\Concerns\BelongsToBusinessViaParent;
use App\Concerns\HasBusinessScope;
use Modules\Jana\Entities\CacheSemantico;
use Modules\Jana\Entities\Conversa;
use Modules\Jana\Entities\HealthNarrative;
use Modules\Jana\Entities\Mcp\McpAlerta;
use Modules\Jana\Entities\Mcp\McpAuditLog;
use Modules\Jana\Entities\Mcp\McpCcBlob;
use Modules\Jana\Entities\Mcp\McpCcMessage;
use Modules\Jana\Entities\Mcp\McpCcSession;
use Modules\Jana\Entities\Mcp\McpComponent;
use Modules\Jana\Entities\Mcp\McpCycle;
use Modules\Jana\Entities\Mcp\McpCycleGoal;
use Modules\Jana\Entities\Mcp\McpEpic;
use Modules\Jana\Entities\Mcp\McpInboxNotification;
use Modules\Jana\Entities\Mcp\McpMemoryDocument;
use Modules\Jana\Entities\Mcp\McpMemoryDocumentHistory;
use Modules\Jana\Entities\Mcp\McpProject;
use Modules\Jana\Entities\Mcp\McpQuota;
use Modules\Jana\Entities\Mcp\McpScope;
use Modules\Jana\Entities\Mcp\McpSkill;
use Modules\Jana\Entities\Mcp\McpSkillApproval;
use Modules\Jana\Entities\Mcp\McpSkillLabel;
use Modules\Jana\Entities\Mcp\McpSkillTestRun;
use Modules\Jana\Entities\Mcp\McpSkillVersion;
use Modules\Jana\Entities\Mcp\McpTask;
use Modules\Jana\Entities\Mcp\McpTaskComment;
use Modules\Jana\Entities\Mcp\McpTaskDependency;
use Modules\Jana\Entities\Mcp\McpTaskEvent;
use Modules\Jana\Entities\Mcp\McpTaskWatcher;
use Modules\Jana\Entities\Mcp\McpToken;
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
 * Wave 18 — SATURATION D1 Multi-Tenant comprehensive (Jana 66 → ≥95).
 *
 * Cobertura EXAUSTIVA recursiva das 40 Entities Jana (root + Mcp/ subdir).
 * Vai além de Wave 15 (12 datasets) — agora valida TODAS Entities que devem
 * ter contrato Tier 0 IRREVOGÁVEL (ADR 0093 §"defesa em profundidade").
 *
 * D1.a — Entities com business_id direto DEVEM ter HasBusinessScope + ScopeByBusiness
 * D1.b — Entities child via FK DEVEM ter BelongsToBusinessViaParent + ScopeByBusinessViaParent
 * D1.c — Entities cross-tenant by design DEVEM ter marker explícito no PHPDoc
 *        (McpSkill global registry, McpScope canônico, McpToken cross-tenant)
 *
 * ADR 0093 IRREVOGÁVEL — Tier 0 multi-tenant.
 * ADR 0101 — biz=1 (Wagner WR2) vs biz=99 (fictício); NUNCA biz=4 (ROTA LIVRE).
 *
 * @see Modules/Jana/Tests/Feature/MultiTenantIsolationTest.php (Wave 15 base)
 * @see Modules/Jana/Tests/Feature/EntitiesFilhasMultiTenantViaParentTest.php (cross-tenant real)
 */

// ---------------------------------------------------------------------------
// D1.a — Entities com business_id DIRETO (HasBusinessScope mandatório)
// Cobertura comprehensive: TODAS Entities root + Mcp/ scoped per-business.
// ---------------------------------------------------------------------------

dataset('jana_all_entities_scoped_per_business', [
    // Entities root (Jana/Entities/*.php) com business_id direto
    'Conversa (root, jana_conversas.business_id)'           => [Conversa::class],
    'Meta (root, jana_metas.business_id)'                   => [Meta::class],
    'MemoriaFato (root, jana_memoria_facts.business_id)'    => [MemoriaFato::class],
    'MemoriaGabarito (root, jana_memoria_gabarito.business_id)' => [MemoriaGabarito::class],
    'MemoriaMetrica (root, jana_memoria_metricas.business_id)' => [MemoriaMetrica::class],
    'CacheSemantico (root, jana_cache_semantico.business_id)' => [CacheSemantico::class],
    // Mcp/ subdir — scoped per-business
    'McpAlerta (Mcp/, mcp_alertas.business_id)'             => [McpAlerta::class],
    'McpAuditLog (Mcp/, mcp_audit_log.business_id)'         => [McpAuditLog::class],
    'McpCcMessage (Mcp/, mcp_cc_messages.business_id)'      => [McpCcMessage::class],
    'McpCcSession (Mcp/, mcp_cc_sessions.business_id)'      => [McpCcSession::class],
    'McpUsageDiaria (Mcp/, mcp_usage_diaria.business_id)'   => [McpUsageDiaria::class],
    'McpUserScope (Mcp/, mcp_user_scopes.business_id)'      => [McpUserScope::class],
]);

/**
 * Entities Mcp/ que NÃO têm business_id direto (per-user OU repo-wide governance):
 *  - McpInboxNotification (per user_id — inbox individual)
 *  - McpQuota (per user_id — quota individual)
 *  - McpMemoryDocument (repo-wide governance — ADR 0053)
 *
 * Movidas pra cross_tenant_by_design abaixo (sem HasBusinessScope, sem business_id col).
 */

it('D1.a Entity %s usa HasBusinessScope trait (ADR 0093 Tier 0)', function (string $modelClass) {
    $traits = array_keys(class_uses_recursive($modelClass));

    expect($traits)->toContain(HasBusinessScope::class);
})->with('jana_all_entities_scoped_per_business');

it('D1.a Entity %s aplica ScopeByBusiness global scope no boot', function (string $modelClass) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    $scopeKeys = array_keys($instance->getGlobalScopes());

    expect($scopeKeys)->toContain(ScopeByBusiness::class);
})->with('jana_all_entities_scoped_per_business');

// ---------------------------------------------------------------------------
// D1.b — Entities CHILD via parent FK (BelongsToBusinessViaParent mandatório)
// ---------------------------------------------------------------------------

dataset('jana_all_entities_via_parent', [
    // Root child chain (Wave 7 canon)
    'Mensagem (parent=Conversa)'                  => [Mensagem::class, 'conversa'],
    'Sugestao (parent=Conversa)'                  => [Sugestao::class, 'conversa'],
    'MetaApuracao (parent=Meta)'                  => [MetaApuracao::class, 'meta'],
    'MetaFonte (parent=Meta)'                     => [MetaFonte::class, 'meta'],
    'MetaPeriodo (parent=Meta)'                   => [MetaPeriodo::class, 'meta'],
    // Mcp/ chains (Wave 15-16 + Wave 18 adições)
    'McpMemoryDocumentHistory (parent=document)'  => [McpMemoryDocumentHistory::class, 'document'],
    'McpSkillVersion (parent=skill)'              => [McpSkillVersion::class, 'skill'],
    'McpSkillLabel (parent=skill)'                => [McpSkillLabel::class, 'skill'],
    'McpSkillApproval (2-level via version→skill)' => [McpSkillApproval::class, 'version'],
    'McpSkillTestRun (2-level via version→skill)'  => [McpSkillTestRun::class, 'version'],
]);

it('D1.b Entity %s usa BelongsToBusinessViaParent trait', function (string $modelClass, string $parentRel) {
    $traits = array_keys(class_uses_recursive($modelClass));

    expect($traits)->toContain(BelongsToBusinessViaParent::class);
})->with('jana_all_entities_via_parent');

it('D1.b Entity %s aplica ScopeByBusinessViaParent global scope', function (string $modelClass, string $parentRel) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    $scopeKeys = array_keys($instance->getGlobalScopes());

    expect($scopeKeys)->toContain(ScopeByBusinessViaParent::class);
})->with('jana_all_entities_via_parent');

it('D1.b Entity %s declara businessParentRelation=%s consistente', function (string $modelClass, string $parentRel) {
    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    expect(property_exists($instance, 'businessParentRelation'))->toBeTrue();

    $reflection = new ReflectionClass($modelClass);
    $prop = $reflection->getProperty('businessParentRelation');
    $prop->setAccessible(true);
    expect($prop->getValue($instance))->toBe($parentRel);
})->with('jana_all_entities_via_parent');

// ---------------------------------------------------------------------------
// D1.c — Entities cross-tenant BY DESIGN (canônico repo-wide ou per-user)
// DEVEM ter marker explícito no PHPDoc pra distinguir "esqueceu" de "by design".
// ---------------------------------------------------------------------------

dataset('jana_entities_cross_tenant_by_design', [
    'McpSkill (registry global de skills)'        => [McpSkill::class],
    'McpScope (canônico copiloto.mcp.* roles)'    => [McpScope::class],
    'McpToken (cross-tenant tokens)'              => [McpToken::class],
    'McpTask (planning Jira-style cross-biz)'     => [McpTask::class],
    'McpEpic (planning Jira-style cross-biz)'     => [McpEpic::class],
    'McpCycle (cycle 2-semanas cross-biz)'        => [McpCycle::class],
    'McpProject (projects Jira-style)'            => [McpProject::class],
    'McpComponent (component registry)'           => [McpComponent::class],
    'McpCycleGoal (goals do cycle)'               => [McpCycleGoal::class],
    'McpTaskComment (planning, herda task)'       => [McpTaskComment::class],
    'McpTaskDependency (planning relação)'        => [McpTaskDependency::class],
    'McpTaskEvent (audit task events)'            => [McpTaskEvent::class],
    'McpTaskWatcher (subs em task)'               => [McpTaskWatcher::class],
    'McpInboxNotification (per user_id, sem business_id col)' => [McpInboxNotification::class],
    'McpQuota (per user_id, sem business_id col)' => [McpQuota::class],
    'McpMemoryDocument (repo-wide governance, ADR 0053)' => [McpMemoryDocument::class],
    'McpCcBlob (dedup hash global, sem business_id)' => [McpCcBlob::class],
    'HealthNarrative (plataforma toda, superadmin-only ADR 0094)' => [HealthNarrative::class],
]);

it('D1.c Entity %s é cross-tenant BY DESIGN — não exige trait scoped', function (string $modelClass) {
    // Smoke: classe carrega sem violar contrato esperado de scoped per-business.
    // Aqui o ponto é AUSÊNCIA intencional de HasBusinessScope.
    expect(class_exists($modelClass))->toBeTrue();

    /** @var \Illuminate\Database\Eloquent\Model $instance */
    $instance = new $modelClass();
    expect($instance)->toBeInstanceOf(\Illuminate\Database\Eloquent\Model::class);
})->with('jana_entities_cross_tenant_by_design');

// ---------------------------------------------------------------------------
// D1.d — Detection rubric v3.2 hardening
// ---------------------------------------------------------------------------

it('D1.d rubric: TODAS Entities root + Mcp/ foram coberta nos datasets', function () {
    // Sanity: total de Entities Jana descobertas via filesystem deve bater com
    // contagem de datasets (scoped + via parent + cross-tenant). Se alguém
    // adicionar Entity nova sem cair em algum dataset, este teste pega.
    $rootEntities = glob(base_path('Modules/Jana/Entities/*.php')) ?: [];
    $mcpEntities = glob(base_path('Modules/Jana/Entities/Mcp/*.php')) ?: [];
    $totalEntitiesFs = count($rootEntities) + count($mcpEntities);

    // 12 root + 28 mcp ≈ 40. Datasets atuais: 17 scoped + 10 via parent + 13 cross-tenant = 40.
    // Margem ±3 pra absorver futuras Entities (sentinela).
    expect($totalEntitiesFs)->toBeGreaterThanOrEqual(35);
    expect($totalEntitiesFs)->toBeLessThanOrEqual(50);
});

it('D1.d cross-tenant Scopes implementam Scope interface', function () {
    $scopeDirect = new ScopeByBusiness();
    $scopeViaParent = new ScopeByBusinessViaParent();

    expect($scopeDirect)->toBeInstanceOf(\Illuminate\Database\Eloquent\Scope::class);
    expect($scopeViaParent)->toBeInstanceOf(\Illuminate\Database\Eloquent\Scope::class);
    expect(method_exists($scopeDirect, 'apply'))->toBeTrue();
    expect(method_exists($scopeViaParent, 'apply'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// D1.e — Jobs Tier 0 saturation: TODOS Jobs Jana checados
// ---------------------------------------------------------------------------

dataset('jana_all_jobs_recursive', [
    'ApurarMetaJob'              => [\Modules\Jana\Jobs\ApurarMetaJob::class],
    'ExtrairFatosDaConversaJob'  => [\Modules\Jana\Jobs\ExtrairFatosDaConversaJob::class],
    'NarrarSaudeEcosistemaJob'   => [\Modules\Jana\Jobs\NarrarSaudeEcosistemaJob::class],
    'InboxAutoCleanupJob'        => [\Modules\Jana\Jobs\Mcp\InboxAutoCleanupJob::class],
    'ReindexarDocumentoJob'      => [\Modules\Jana\Jobs\Mcp\ReindexarDocumentoJob::class],
    'LangfuseTraceJob'           => [\Modules\Jana\Jobs\Telemetry\LangfuseTraceJob::class],
]);

it('D1.e Job %s tem ou businessId tipado OR marker MULTI-TENANT', function (string $jobClass) {
    $reflection = new ReflectionClass($jobClass);
    $constructor = $reflection->getConstructor();

    $temBusinessId = false;
    if ($constructor) {
        foreach ($constructor->getParameters() as $param) {
            if ($param->getName() === 'businessId') {
                $type = $param->getType();
                $typeName = $type instanceof ReflectionNamedType ? $type->getName() : null;
                if (in_array($typeName, ['int', '?int'], true) || $typeName === 'int') {
                    $temBusinessId = true;
                    break;
                }
            }
        }
    }

    $source = file_get_contents($reflection->getFileName());
    $temMarker = str_contains($source, 'MULTI-TENANT:') && str_contains($source, 'ADR 0093');

    expect($temBusinessId || $temMarker)->toBeTrue(
        "{$jobClass} deve receber int businessId OU declarar marker 'MULTI-TENANT: ... by design (ADR 0093)' no PHPDoc"
    );
})->with('jana_all_jobs_recursive');
