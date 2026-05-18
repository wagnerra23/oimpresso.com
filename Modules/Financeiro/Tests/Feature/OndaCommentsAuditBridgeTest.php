<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Mock Onda Comments + Audit DB — Bridges + Endpoints (Wagner 2026-05-18)
 *
 * Cobre:
 *   - Arquivos bridge JS existem em public/cowork-preview/
 *   - Bridge comments faz POST /comments + GET /comments com CSRF + same-origin
 *   - Bridge audit faz GET /audit com same-origin
 *   - Eventos canon dispatchados: oimpresso:fin-comment-add, oimpresso:fin-drawer-open
 *   - UnificadoController tem métodos comments(), addComment(), auditTrail()
 *   - routes/web.php registra as 3 rotas novas
 *   - Migration cria fin_titulo_comments com business_id NOT NULL + FK + index
 *   - Model TituloComment usa BusinessScope trait
 *
 * Padrão: smoke estrutural (file_get_contents + toContain) — não boota app,
 * Tier 0 safe, sobrevive DB greenfield. Espelha MockOndaConferidoBridgeTest.php.
 */

const FIN_BRIDGE_COMMENTS = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-bridge-comments.js';
const FIN_BRIDGE_AUDIT = __DIR__ . '/../../../../public/cowork-preview/_oimpresso-bridge-audit.js';
const FIN_CURATION_JSX_ONDA = __DIR__ . '/../../../../public/cowork-preview/financeiro-curation.jsx';
const FIN_APP_JSX_ONDA = __DIR__ . '/../../../../public/cowork-preview/financeiro-app.jsx';
const FIN_UNIFICADO_CONTROLLER = __DIR__ . '/../../Http/Controllers/UnificadoController.php';
const FIN_ROUTES_WEB = __DIR__ . '/../../Routes/web.php';
const FIN_TITULO_COMMENT_MODEL = __DIR__ . '/../../Models/TituloComment.php';
const FIN_COMMENTS_MIGRATION = __DIR__ . '/../../Database/Migrations/2026_05_18_190000_create_fin_titulo_comments_table.php';

describe('Mock Onda Comments DB — Bridge comments (estrutural)', function () {
    it('arquivo _oimpresso-bridge-comments.js existe', function () {
        expect(file_exists(FIN_BRIDGE_COMMENTS))->toBeTrue();
    });

    it('escuta event canon oimpresso:fin-comment-add (POST trigger)', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain("'oimpresso:fin-comment-add'");
        expect($src)->toContain('addEventListener');
    });

    it('escuta event canon oimpresso:fin-drawer-open (GET pre-fetch)', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain("'oimpresso:fin-drawer-open'");
    });

    it('faz POST pra /financeiro/unificado/{id}/comments com CSRF', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain('/financeiro/unificado/');
        expect($src)->toContain('/comments');
        expect($src)->toContain("'POST'");
        expect($src)->toContain('X-CSRF-TOKEN');
    });

    it('faz GET pra /financeiro/unificado/{id}/comments same-origin', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain("'GET'");
        expect($src)->toContain("credentials: 'same-origin'");
    });

    it('extrai id Laravel via regex /^[RP]-(\\d+)$/ (CoworkDataMapper format)', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain('/^[RP]-(\d+)$/');
        expect($src)->toContain('extractLaravelId');
    });

    it('graceful skip pra id não-numérico (mock template)', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain('SKIP');
        expect($src)->toContain('mock template');
    });

    it('popula window.__OIMPRESSO_COMMENTS_DB__ (overlay-first)', function () {
        $src = file_get_contents(FIN_BRIDGE_COMMENTS);
        expect($src)->toContain('__OIMPRESSO_COMMENTS_DB__');
    });
});

describe('Mock Onda Audit DB — Bridge audit (estrutural)', function () {
    it('arquivo _oimpresso-bridge-audit.js existe', function () {
        expect(file_exists(FIN_BRIDGE_AUDIT))->toBeTrue();
    });

    it('escuta event canon oimpresso:fin-drawer-open (GET trigger)', function () {
        $src = file_get_contents(FIN_BRIDGE_AUDIT);
        expect($src)->toContain("'oimpresso:fin-drawer-open'");
        expect($src)->toContain('addEventListener');
    });

    it('faz GET pra /financeiro/unificado/{id}/audit', function () {
        $src = file_get_contents(FIN_BRIDGE_AUDIT);
        expect($src)->toContain('/financeiro/unificado/');
        expect($src)->toContain('/audit');
        expect($src)->toContain('fetch(');
        expect($src)->toContain("credentials: 'same-origin'");
    });

    it('extrai id Laravel via regex /^[RP]-(\\d+)$/ + graceful skip', function () {
        $src = file_get_contents(FIN_BRIDGE_AUDIT);
        expect($src)->toContain('/^[RP]-(\d+)$/');
        expect($src)->toContain('SKIP');
    });

    it('popula window.__OIMPRESSO_AUDIT_DB__ (overlay-first) + dispatch event loaded', function () {
        $src = file_get_contents(FIN_BRIDGE_AUDIT);
        expect($src)->toContain('__OIMPRESSO_AUDIT_DB__');
        expect($src)->toContain("'oimpresso:fin-audit-loaded'");
    });
});

describe('Mock Onda Comments + Audit — JSX dispatch events (modificações mínimas)', function () {
    it('financeiro-curation.jsx FinCommentsThread dispatch oimpresso:fin-comment-add', function () {
        $src = file_get_contents(FIN_CURATION_JSX_ONDA);
        expect($src)->toContain("'oimpresso:fin-comment-add'");
        // Dispatch tem que estar DENTRO da função submit (após comments.add)
        expect($src)->toMatch('/comments\.add\(rowId.*\n.*oimpresso:fin-comment-add/s');
    });

    it('financeiro-app.jsx dispatch oimpresso:fin-drawer-open em useEffect[drawerRow]', function () {
        $src = file_get_contents(FIN_APP_JSX_ONDA);
        expect($src)->toContain("'oimpresso:fin-drawer-open'");
        // useEffect com dependency drawerRow
        expect($src)->toContain('[drawerRow]');
    });

    it('useFinComments continua usando localStorage como fonte primária (overlay-first)', function () {
        // Anti-regressão: bridge é OVERLAY, hook localStorage não muda.
        $src = file_get_contents(FIN_CURATION_JSX_ONDA);
        expect($src)->toContain('oimpresso.financeiro.comments');
        expect($src)->toContain('function useFinComments()');
        // Nenhuma chamada fetch direta dentro do JSX (bridge faz isso)
        expect($src)->not->toContain('fetch(');
    });
});

describe('Mock Onda Comments + Audit — Backend Laravel (endpoints)', function () {
    it('UnificadoController tem 3 métodos novos: comments + addComment + auditTrail', function () {
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

    it('routes/web.php registra GET/POST /comments + GET /audit', function () {
        $src = file_get_contents(FIN_ROUTES_WEB);
        expect($src)->toContain("Route::get('/unificado/{tituloId}/comments'");
        expect($src)->toContain("Route::post('/unificado/{tituloId}/comments'");
        expect($src)->toContain("Route::get('/unificado/{tituloId}/audit'");
        expect($src)->toContain("unificado.comments");
        expect($src)->toContain("unificado.audit");
    });
});

describe('Mock Onda Comments + Audit — Model + Migration (multi-tenant Tier 0)', function () {
    it('Model TituloComment existe + usa BusinessScope trait', function () {
        expect(file_exists(FIN_TITULO_COMMENT_MODEL))->toBeTrue();
        $src = file_get_contents(FIN_TITULO_COMMENT_MODEL);
        expect($src)->toContain('use BusinessScope');
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

describe('Mock Onda Comments + Audit — Endpoints funcionais (HTTP smoke)', function () {
    it('GET /comments retorna 401/302 sem auth (gate funciona)', function () {
        // Sem session/login — middleware auth bloqueia.
        $response = $this->get('/financeiro/unificado/1/comments');
        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('GET /audit retorna 401/302 sem auth (gate funciona)', function () {
        $response = $this->get('/financeiro/unificado/1/audit');
        expect($response->status())->toBeIn([302, 401, 403, 404]);
    });

    it('POST /comments retorna 401/302/419 sem CSRF + auth', function () {
        $response = $this->post('/financeiro/unificado/1/comments', ['body' => 'teste']);
        // 419 = CSRF, 401/302 = auth, 404 = módulo não instalado
        expect($response->status())->toBeIn([302, 401, 403, 404, 419, 422]);
    });
});
