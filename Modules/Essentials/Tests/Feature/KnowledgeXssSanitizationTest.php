<?php

declare(strict_types=1);

use App\Util\HtmlSanitizer;
use Modules\Essentials\Entities\KnowledgeBase;
use Modules\Essentials\Http\Controllers\KnowledgeBaseController;

uses(\Modules\Essentials\Tests\Feature\EssentialsTestCase::class);

/**
 * Defesa-em-profundidade XSS — Base de Conhecimento (Essentials/Knowledge).
 *
 * O fix de sanitização server-side chegou em #2895 (HtmlSanitizer::clean nos 3
 * payloads renderizados via dangerouslySetInnerHTML) SEM testes. Este arquivo é a
 * cobertura de regressão que faltou — a última peça da classe dSIH (#2891/#2893/#2894).
 *
 * Contexto do risco: autoria de KB NÃO é admin-only — qualquer user do tenant com
 * assinatura `essentials_module` autora; com share_with=public o HTML roda pra todos
 * os viewers do business (incl. admins). Por isso o `content` é sanitizado (não
 * escapado — KB precisa de HTML rico) antes do Inertia.
 *
 * 3 camadas de prova:
 *   1. (pura, qualquer driver) política HtmlSanitizer::clean correta
 *   2. (source-level, qualquer driver) Controller realmente aplica a política (anti-regressão)
 *   3. (DB real, skip SQLite) payload Inertia do show() vem sanitizado ponta-a-ponta
 *
 * @see app/Util/HtmlSanitizer.php
 * @see scripts/dsih-gate.mjs
 */

function knowledgeXssIsSqlite(): bool
{
    $default = config('database.default');

    return $default === 'sqlite'
        || config("database.connections.{$default}.driver") === 'sqlite';
}

// ------------------------------------------------------------------
// 1) Política de sanitização (pura — não toca DB nem rota)
// ------------------------------------------------------------------

it('HtmlSanitizer::clean remove sinks de XSS e preserva HTML rico', function () {
    // <script> some por completo (tag + conteúdo)
    expect(HtmlSanitizer::clean('<script>alert(1)</script>'))->toBe('');

    // handler inline on* é removido
    expect(HtmlSanitizer::clean('<img src=x onerror="alert(1)">'))
        ->not->toContain('onerror');

    // esquema javascript: em href é neutralizado
    expect(HtmlSanitizer::clean('<a href="javascript:alert(1)">clique</a>'))
        ->not->toContain('javascript:');

    // markup de texto rico legítimo é PRESERVADO (KB precisa de HTML, não vira texto)
    $rico = HtmlSanitizer::clean(
        '<h2>Procedimento</h2><p><strong>Passo 1</strong></p>'
        .'<ul><li>item</li></ul><a href="https://exemplo.com">link</a>'
    );
    expect($rico)
        ->toContain('<h2>')
        ->and($rico)->toContain('<strong>')
        ->and($rico)->toContain('<ul>')
        ->and($rico)->toContain('<li>')
        ->and($rico)->toContain('href="https://exemplo.com"');
});

it('HtmlSanitizer::clean é null-safe e idempotente', function () {
    expect(HtmlSanitizer::clean(null))->toBe('');
    expect(HtmlSanitizer::clean(''))->toBe('');

    $sujo = '<p>ok</p><script>alert(1)</script><b onclick="x()">b</b>';
    $umaVez = HtmlSanitizer::clean($sujo);
    expect(HtmlSanitizer::clean($umaVez))->toBe($umaVez); // idempotente
});

// ------------------------------------------------------------------
// 2) Controller aplica a política (source-level — anti-regressão dSIH)
// ------------------------------------------------------------------

it('KnowledgeBaseController roteia todo content por HtmlSanitizer::clean', function () {
    $src = file_get_contents((new \ReflectionClass(KnowledgeBaseController::class))->getFileName());

    // importa a política canônica
    expect($src)->toContain('use App\\Util\\HtmlSanitizer;');

    // item.content (show) + book.content + section.content (toBookShape) = 3 sinks sanitizados
    expect(substr_count($src, 'HtmlSanitizer::clean('))->toBeGreaterThanOrEqual(3);

    // não pode voltar a serializar content cru (regressão silenciosa)
    expect($src)->not->toMatch('/=>\s*\$obj->content\b/');
    expect($src)->not->toMatch('/=>\s*\$k->content\b/');
    expect($src)->not->toMatch('/=>\s*\$section->content\b/');
});

// ------------------------------------------------------------------
// 3) End-to-end: payload Inertia do show() vem sanitizado (skip SQLite)
// ------------------------------------------------------------------

it('show() entrega item.content sanitizado no payload Inertia', function () {
    if (knowledgeXssIsSqlite()) {
        $this->markTestSkipped('SQLite incompatível com schema UltimatePOS legacy (triggers MySQL).');
    }

    $admin = $this->actAsAdmin();

    $kb = KnowledgeBase::create([
        'business_id' => $admin->business_id,
        'created_by'  => $admin->id,
        'title'       => 'dSIH XSS regression book',
        'content'     => '<p><strong>conteúdo seguro</strong></p>'
            .'<script>alert(document.cookie)</script>'
            .'<img src=x onerror="fetch(\'//evil\')">',
        'kb_type'     => 'knowledge_base',
        'share_with'  => 'public',
    ]);

    try {
        $response = $this->inertiaGet("/essentials/knowledge-base/{$kb->id}");
        $response->assertStatus(200);

        $content = $response->json('props.item.content');

        expect($content)->not->toContain('<script')
            ->and($content)->not->toContain('onerror')
            ->and($content)->toContain('<strong>'); // HTML rico preservado
    } finally {
        $kb->delete();
    }
});
