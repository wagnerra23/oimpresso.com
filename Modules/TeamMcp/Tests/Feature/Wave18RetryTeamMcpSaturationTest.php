<?php

declare(strict_types=1);

use Modules\TeamMcp\Http\Requests\CcIngestRequest;
use Modules\TeamMcp\Http\Requests\StoreActorRequest;
use Modules\TeamMcp\Services\CcIngestService;
use Modules\TeamMcp\Services\McpActorRepository;

uses(Tests\TestCase::class);

/**
 * Wave 18 RETRY — TeamMcp D4 + D8 SATURATION (2026-05-16).
 *
 * Smoke + reflection contract das NOVAS Services/Repos/FormRequests:
 *   D4: CcIngestService (3 métodos públicos), McpActorRepository (4 lookups)
 *   D8: StoreActorRequest, CcIngestRequest
 *
 * Tier 0 ({@see ADR 0093}/{@see ADR 0081}):
 *   - mcp_actors NUNCA tem business_id (cross-tenant by design)
 *   - tests usam reflection (não criam actors reais — repo é seedado em migration)
 *
 * Pareia com Wave18ServicesExtractionTest (não substitui).
 *
 * @see Modules\TeamMcp\Services\CcIngestService
 * @see Modules\TeamMcp\Services\McpActorRepository
 */
describe('Wave 18 RETRY — TeamMcp Services novas (D4)', function () {
    it('CcIngestService carrega via container + 3 métodos públicos', function () {
        $svc = app(CcIngestService::class);
        expect($svc)->toBeInstanceOf(CcIngestService::class);

        $ref = new ReflectionClass($svc);
        expect($ref->hasMethod('upsertSession'))->toBeTrue();
        expect($ref->hasMethod('ingestMessages'))->toBeTrue();
        expect($ref->hasMethod('recalcSessionCounters'))->toBeTrue();
    });

    it('McpActorRepository carrega via container + 4 lookups', function () {
        $svc = app(McpActorRepository::class);
        expect($svc)->toBeInstanceOf(McpActorRepository::class);

        $ref = new ReflectionClass($svc);
        expect($ref->hasMethod('findActiveBySlug'))->toBeTrue();
        expect($ref->hasMethod('listHumansByTrust'))->toBeTrue();
        expect($ref->hasMethod('listAiChildren'))->toBeTrue();
        expect($ref->hasMethod('revokedSince'))->toBeTrue();
    });

    it('McpActorRepository::findActiveBySlug retorna null pra slug inexistente (Tier 0 seguro)', function () {
        if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_actors')) {
            $this->markTestSkipped('Tabela mcp_actors ausente (SQLite/MySQL sem migrate). Cobertura reflection já validada acima.');
        }
        $repo = app(McpActorRepository::class);
        $actor = $repo->findActiveBySlug('slug-fictício-wave18-' . uniqid());

        expect($actor)->toBeNull();
    });

    it('McpActorRepository::revokedSince retorna Collection vazia em horizonte futuro', function () {
        if (! \Illuminate\Support\Facades\Schema::hasTable('mcp_actors')) {
            $this->markTestSkipped('Tabela mcp_actors ausente — cobertura reflection já validada.');
        }
        $repo = app(McpActorRepository::class);
        $since = (new DateTimeImmutable())->modify('+10 years');

        $col = $repo->revokedSince($since);
        expect($col)->toBeInstanceOf(\Illuminate\Support\Collection::class);
        expect($col->count())->toBe(0);
    });

    it('CcIngestService usa OtelHelper canônico (3 spans declarados)', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/CcIngestService.php');

        expect($source)->toContain('use App\Util\OtelHelper');
        expect($source)->toContain("OtelHelper::spanBiz('teammcp.cc.ingest_session'");
        expect($source)->toContain("OtelHelper::spanBiz('teammcp.cc.ingest_messages'");
    });

    it('McpActorRepository usa OtelHelper em findActiveBySlug', function () {
        $source = file_get_contents(__DIR__ . '/../../Services/McpActorRepository.php');

        expect($source)->toContain("OtelHelper::spanBiz('teammcp.actor.find_active_by_slug'");
    });
});

describe('Wave 18 RETRY — TeamMcp FormRequests novos (D8)', function () {
    it('StoreActorRequest expõe rules cobrindo Identity Mesh', function () {
        expect(class_exists(StoreActorRequest::class))->toBeTrue();

        $req = new StoreActorRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('slug');
        expect($rules)->toHaveKey('type');
        expect($rules)->toHaveKey('trust_level');
        expect($rules['type'])->toContain('in:human,ai');
        expect($rules['trust_level'])->toContain('between:0,4');
    });

    it('StoreActorRequest valida slug kebab-case regex (hardening)', function () {
        $req = new StoreActorRequest();
        $rules = $req->rules();

        expect($rules['slug'])->toContain('regex:/^[a-z0-9][a-z0-9\-]*$/');
        expect($rules['slug'])->toContain('unique:mcp_actors,slug');
    });

    it('CcIngestRequest expõe rules cobrindo session + messages', function () {
        expect(class_exists(CcIngestRequest::class))->toBeTrue();

        $req = new CcIngestRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('session');
        expect($rules)->toHaveKey('session.uuid');
        expect($rules)->toHaveKey('messages');
        expect($rules['messages'])->toContain('max:5000');
    });

    it('CcIngestRequest valida messages.* cobertura uuid+type', function () {
        $req = new CcIngestRequest();
        $rules = $req->rules();

        expect($rules)->toHaveKey('messages.*.uuid');
        expect($rules)->toHaveKey('messages.*.type');
    });
});
