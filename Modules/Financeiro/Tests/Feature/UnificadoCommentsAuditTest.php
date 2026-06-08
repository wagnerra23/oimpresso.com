<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * UnificadoController — Comments + Audit trail (multi-tenant Tier 0).
 *
 * Extraído de OndaCommentsAuditBridgeTest.php (US-FIN-053, Batch 3 — 2026-06-04).
 * O arquivo original misturava estes testes Tier 0 REAIS com smoke estrutural
 * dos assets Cowork-mock (`public/cowork-preview/_oimpresso-bridge-*.js`,
 * `financeiro-*.jsx`) que foram apagados no #1214 — aqueles asserts ficaram
 * stale (file_get_contents em arquivo inexistente). A scaffolding Cowork-mock
 * (trait RendersMockCowork + CoworkDataMapper + config mock_*) foi removida
 * junto. Aqui sobra SÓ a cobertura multi-tenant dos endpoints/model/migration
 * reais, que NÃO depende de nenhum asset Cowork.
 *
 * Cobre:
 *   - UnificadoController tem comments() / addComment() / auditTrail()
 *   - Tier 0: business_id da session em todos os endpoints
 *   - auditTrail filtra Activity por subject_type + subject_id + business_id
 *   - Routes/web.php registra GET/POST /comments + GET /audit
 *   - Model TituloComment usa BusinessScope (Tier 0) + append-only
 *   - Migration fin_titulo_comments com business_id NOT NULL + FK + index + down()
 *   - Endpoints reais respondem com gate de auth sem login (HTTP smoke)
 *
 * Padrão: smoke estrutural (file_get_contents + toContain) pros 5 primeiros
 * grupos — não boota app, sobrevive DB greenfield; + HTTP smoke real no fim.
 */

defined('FIN_UNIFICADO_CONTROLLER') || define('FIN_UNIFICADO_CONTROLLER', __DIR__ . '/../../Http/Controllers/UnificadoController.php');
defined('FIN_ROUTES_WEB') || define('FIN_ROUTES_WEB', __DIR__ . '/../../Routes/web.php');
defined('FIN_TITULO_COMMENT_MODEL') || define('FIN_TITULO_COMMENT_MODEL', __DIR__ . '/../../Models/TituloComment.php');
defined('FIN_COMMENTS_MIGRATION') || define('FIN_COMMENTS_MIGRATION', __DIR__ . '/../../Database/Migrations/2026_05_18_190000_create_fin_titulo_comments_table.php');

describe('Comments + Audit — Backend Laravel (endpoints)', function () {
    it('UnificadoController tem 3 métodos: comments + addComment + auditTrail', function () {
        $src = file_get_contents(FIN_UNIFICADO_CONTROLLER);
        expect($src)->toContain('public function comments(int $tituloId)');
        expect($src)->toContain('public function addComment(Request $request, int $tituloId)');
        expect($src)->toContain('public function auditTrail(int $tituloId)');
    });

    it('Tier 0: business_id da session em todos os 3 endpoints', function () {
        $src = file_get_contents(FIN_UNIFICADO_CONTROLLER);
        // 3 ocorrências mínimas (1 por método)
        $matches = substr_count($src, "session('user.business_id')");
        expect($matches)->toBeGreaterThanOrEqual(3);
    });

    it('auditTrail filtra Activity por subject_type=Titulo + subject_id + business_id', function () {
        $src = file_get_contents(FIN_UNIFICADO_CONTROLLER);
        expect($src)->toContain("'subject_type', Titulo::class");
        expect($src)->toContain("'subject_id', \$tituloId");
        expect($src)->toContain("'business_id', \$businessId");
    });

    it('Routes/web.php registra GET/POST /comments + GET /audit', function () {
        $src = file_get_contents(FIN_ROUTES_WEB);
        expect($src)->toContain("Route::get('/unificado/{tituloId}/comments'");
        expect($src)->toContain("Route::post('/unificado/{tituloId}/comments'");
        expect($src)->toContain("Route::get('/unificado/{tituloId}/audit'");
        expect($src)->toContain("unificado.comments");
        expect($src)->toContain("unificado.audit");
    });
});

describe('Comments + Audit — Model + Migration (multi-tenant Tier 0)', function () {
    it('Model TituloComment existe + aplica BusinessScope trait', function () {
        expect(file_exists(FIN_TITULO_COMMENT_MODEL))->toBeTrue();
        $src = file_get_contents(FIN_TITULO_COMMENT_MODEL);
        // Import do trait Tier 0 + aplicação na class
        expect($src)->toContain('use Modules\Financeiro\Models\Concerns\BusinessScope;');
        expect($src)->toMatch('/use\s+HasFactory,\s*BusinessScope;/');
        expect($src)->toContain("protected \$table = 'fin_titulo_comments'");
        // Append-only: delete bloqueado
        expect($src)->toContain('DomainException');
    });

    it('Migration cria fin_titulo_comments com business_id NOT NULL + FK + index', function () {
        expect(file_exists(FIN_COMMENTS_MIGRATION))->toBeTrue();
        $src = file_get_contents(FIN_COMMENTS_MIGRATION);
        expect($src)->toContain("Schema::create('fin_titulo_comments'");
        expect($src)->toContain("\$table->integer('business_id')->unsigned()");
        expect($src)->toContain("\$table->foreign('business_id')->references('id')->on('business')");
        expect($src)->toContain("\$table->foreign('titulo_id')->references('id')->on('fin_titulos')");
        expect($src)->toContain("idx_business_titulo");
    });

    it('Migration é idempotente (Schema::hasTable guard) + tem down()', function () {
        $src = file_get_contents(FIN_COMMENTS_MIGRATION);
        expect($src)->toContain("Schema::hasTable('fin_titulo_comments')");
        expect($src)->toContain('public function down()');
        expect($src)->toContain("Schema::dropIfExists('fin_titulo_comments')");
    });
});

describe('Comments + Audit — Endpoints funcionais (HTTP smoke)', function () {
    it('GET /comments retorna 401/302/404 sem auth (gate funciona)', function () {
        // Sem session/login — middleware auth bloqueia.
        $response = $this->get('/financeiro/unificado/1/comments');
        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('GET /audit retorna 401/302/404 sem auth (gate funciona)', function () {
        $response = $this->get('/financeiro/unificado/1/audit');
        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('POST /comments retorna 401/302/419 sem CSRF + auth', function () {
        $response = $this->post('/financeiro/unificado/1/comments', ['body' => 'teste']);
        // 419 = CSRF, 401/302 = auth, 404 = módulo não instalado
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });
});
