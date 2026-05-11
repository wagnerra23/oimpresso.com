<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

uses(Tests\TestCase::class);

/**
 * R-WA-091 — GUARD tests pra remoção das rotas legacy `/whatsapp/conversations*`
 * (Wagner 2026-05-11 ordem: "deve ser removido, isso é uma ordem").
 *
 * Caminho único agora é `/atendimento/inbox` (ADR 0135 schema novo
 * polimórfico). Schema legacy `whatsapp_conversations`/`whatsapp_messages`
 * continua existindo no DB pra histórico, mas SEM UI exposta.
 *
 * Estes tests previnem reintrodução acidental das rotas legacy via
 * cherry-pick ou refactor.
 */
it('R-WA-091-001 — rota `whatsapp.conversations.index` foi removida', function () {
    expect(Route::has('whatsapp.conversations.index'))->toBeFalse();
});

it('R-WA-091-002 — rota `whatsapp.conversations.show` foi removida', function () {
    expect(Route::has('whatsapp.conversations.show'))->toBeFalse();
});

it('R-WA-091-003 — rota `whatsapp.conversations.send` foi removida', function () {
    expect(Route::has('whatsapp.conversations.send'))->toBeFalse();
});

it('R-WA-091-004 — rota `whatsapp.conversations.update_status` foi removida', function () {
    expect(Route::has('whatsapp.conversations.update_status'))->toBeFalse();
});

it('R-WA-091-005 — rotas `atendimento.inbox.*` (caminho único pos-US-WA-091) seguem registradas', function () {
    expect(Route::has('atendimento.inbox.index'))->toBeTrue();
    expect(Route::has('atendimento.inbox.send'))->toBeTrue();
    expect(Route::has('atendimento.inbox.update_status'))->toBeTrue();
    expect(Route::has('atendimento.inbox.update_tags'))->toBeTrue();
    expect(Route::has('atendimento.inbox.link_contact'))->toBeTrue();
    expect(Route::has('atendimento.inbox.block'))->toBeTrue();
    expect(Route::has('atendimento.inbox.contacts.search'))->toBeTrue();
});

it('R-WA-091-006 — arquivo `ConversationsController.php` legacy NAO existe mais', function () {
    // Evita confusao: arquivo removido pra garantir que cherry-pick acidental
    // recoloca tanto a rota quanto o controller juntos. Check via filesystem
    // (autoload classmap pode demorar pra invalidar em CI/local; o arquivo
    // fisico e a fonte da verdade).
    $path = base_path('Modules/Whatsapp/Http/Controllers/Admin/ConversationsController.php');
    expect(file_exists($path))->toBeFalse();
});

it('R-WA-091-007 — arquivos Inertia pages legacy `Conversations/Index.tsx` e `Show.tsx` NAO existem mais', function () {
    $indexPath = base_path('resources/js/Pages/Whatsapp/Conversations/Index.tsx');
    $showPath = base_path('resources/js/Pages/Whatsapp/Conversations/Show.tsx');
    expect(file_exists($indexPath))->toBeFalse();
    expect(file_exists($showPath))->toBeFalse();
});

it('R-WA-091-008 — migration `create_conversation_tags_tables` declara `updated_at` no pivot (anti-drift Pest schema vs prod)', function () {
    // Bug hotfix Wagner 2026-05-11: pivot `whatsapp_conversation_tags` tinha
    // só `created_at` na migration original, mas relation `belongsToMany->
    // withTimestamps()` exige AMBOS. Pest schema do beforeEach mascarou
    // (tinha as 2 colunas) → CI verde + prod broken.
    //
    // Este test le a string da migration real e garante que ambas colunas
    // estao declaradas. Se alguem remover `updated_at` da migration por
    // engano, test quebra ANTES de chegar em prod.
    $migrationPath = base_path('Modules/Whatsapp/Database/Migrations/2026_05_11_120000_create_conversation_tags_tables.php');
    expect(file_exists($migrationPath))->toBeTrue();
    $contents = file_get_contents($migrationPath);
    expect($contents)->toContain("\$table->timestamp('created_at')");
    expect($contents)->toContain("\$table->timestamp('updated_at')");
});
