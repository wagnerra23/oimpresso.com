<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Whatsapp\Http\Controllers\Admin\InboxController;

uses(Tests\TestCase::class);

/**
 * US-WA-VOZ-003 — Auto-prefix sender name no outbound body.
 *
 * Garante:
 *   1. Body humano freeform ganha `*FirstName:* ` prefix
 *   2. Idempotente — body já com `*Nome:*` NÃO duplica
 *   3. Skip nota interna (slash commands formatam próprio)
 *   4. Skip template (HSM tem variáveis)
 *   5. Skip body vazio/null
 *   6. Skip userId inválido
 *   7. Sanitização — caracteres não-alfa removidos do nome
 */
beforeEach(function () {
    Schema::dropIfExists('users');
    Schema::create('users', function ($table) {
        $table->bigIncrements('id');
        $table->string('username', 60);
        $table->string('first_name', 60)->nullable();
        $table->string('last_name', 60)->nullable();
        $table->timestamps();
    });

    DB::table('users')->insert([
        ['id' => 10, 'username' => 'maiara', 'first_name' => 'Maiara', 'last_name' => 'Souza', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 11, 'username' => 'luiz', 'first_name' => 'Luiz', 'last_name' => 'Santos', 'created_at' => now(), 'updated_at' => now()],
        ['id' => 12, 'username' => 'noname', 'first_name' => null, 'last_name' => null, 'created_at' => now(), 'updated_at' => now()],
    ]);
});

/**
 * Helper pra invocar método protected via Reflection.
 */
function callPrefix(?string $body, int $userId, bool $isInternal = false, bool $isTemplate = false): ?string
{
    $controller = new InboxController();
    $method = new ReflectionMethod($controller, 'maybeAutoPrefixSenderName');
    $method->setAccessible(true);
    return $method->invoke($controller, $body, $userId, $isInternal, $isTemplate);
}

it('prefixa body humano freeform com *FirstName:*', function () {
    $r = callPrefix('Tudo certo? Vou conectar.', 10);
    expect($r)->toBe('*Maiara:* Tudo certo? Vou conectar.');
});

it('idempotente — body já com *Nome:* NÃO duplica', function () {
    $r = callPrefix('*Maiara:* Já estou aqui', 10);
    expect($r)->toBe('*Maiara:* Já estou aqui');

    // Outro nome também — atendente assinou manualmente como "Wagner"
    $r2 = callPrefix('*Wagner:* mensagem assinada manual', 10);
    expect($r2)->toBe('*Wagner:* mensagem assinada manual');
});

it('skip nota interna', function () {
    $r = callPrefix('lembrar amanhã reabrir', 10, isInternal: true);
    expect($r)->toBe('lembrar amanhã reabrir');
});

it('skip template', function () {
    $r = callPrefix('Bem-vindo {{1}}!', 10, isInternal: false, isTemplate: true);
    expect($r)->toBe('Bem-vindo {{1}}!');
});

it('skip body vazio / null', function () {
    expect(callPrefix(null, 10))->toBeNull();
    expect(callPrefix('', 10))->toBe('');
    expect(callPrefix('   ', 10))->toBe('   ');
});

it('skip userId 0 ou inválido', function () {
    $r = callPrefix('mensagem sem user', 0);
    expect($r)->toBe('mensagem sem user');

    $r2 = callPrefix('mensagem sem user', -1);
    expect($r2)->toBe('mensagem sem user');
});

it('user sem first_name usa username', function () {
    $r = callPrefix('teste sem first name', 12);
    expect($r)->toBe('*noname:* teste sem first name');
});

it('sanitização — emojis e símbolos removidos do nome (defensivo)', function () {
    DB::table('users')->insert([
        'id' => 13, 'username' => 'evil', 'first_name' => 'João 🎉', 'last_name' => '*',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $r = callPrefix('texto', 13);
    expect($r)->toBe('*João:* texto');
});

it('user_id inexistente passa direto sem prefix', function () {
    $r = callPrefix('mensagem com user fantasma', 99999);
    expect($r)->toBe('mensagem com user fantasma');
});

it('preserva texto trimmed após prefix (single space)', function () {
    $r = callPrefix('   texto com whitespace inicial', 10);
    expect($r)->toBe('*Maiara:* texto com whitespace inicial');
});
