<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Laravel\Ai\Ai;
use Laravel\Ai\AnonymousAgent;
use Modules\Jana\Services\Memoria\RawChatMiner;
use Modules\Jana\Services\Privacy\PiiRedactor;

uses(Tests\TestCase::class);

/**
 * PR-3 (opcional) — minerar o RAW (chats Cowork) em candidatos 🔍 (proposta, não lei).
 * LLM mockado via Ai::fakeAgent; gate PII; renderCandidates puro. Sem git/prod.
 */

function miner(): RawChatMiner
{
    return new RawChatMiner(new PiiRedactor());
}

function rawDoc(string $path, string $content): array
{
    return ['path' => $path, 'content' => $content];
}

beforeEach(function () {
    $dir = sys_get_temp_dir() . '/mineraw_' . uniqid();
    File::makeDirectory($dir, 0o755, recursive: true);
    test()->dir = $dir;
    test()->out = $dir . '/CANDIDATOS-vendas.md';
});

afterEach(function () {
    if (isset(test()->dir) && File::isDirectory(test()->dir)) {
        File::deleteDirectory(test()->dir);
    }
});

test('minera e escreve candidatos 🔍 (proposta, não lei) + proveniência', function () {
    Ai::fakeAgent(AnonymousAgent::class, ["🔍 Unificar tabs Visão num só controle — no chat, sugeriram simplificar."]);
    $raw = [rawDoc('prototipo-ui/cowork-x/chats/chat1.md', 'conversa sobre vendas...')];

    $r = miner()->mine('vendas', $raw, test()->out, false);

    expect($r['status'])->toBe('written');
    expect(File::exists(test()->out))->toBeTrue();
    $c = File::get(test()->out);
    expect($c)
        ->toContain('Candidatos de design (🔍) — vendas')
        ->toContain('PROPOSTA, NÃO LEI')
        ->toContain('🔍 Unificar tabs Visão')
        ->toContain('chat1.md');
});

test('dry-run não escreve', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['🔍 ideia']);
    $r = miner()->mine('vendas', [rawDoc('c.md', 'x')], test()->out, true);
    expect($r['status'])->toBe('dry');
    expect(File::exists(test()->out))->toBeFalse();
});

test('PII no output da LLM → recusa, não escreve', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['🔍 cliente CPF 123.456.789-09 citado']); // pii-allowlist (PII sintética pra testar recusa)
    $r = miner()->mine('vendas', [rawDoc('c.md', 'x')], test()->out, false);
    expect($r['status'])->toBe('refused_pii');
    expect($r['pii'])->toHaveKey('CPF');
    expect(File::exists(test()->out))->toBeFalse();
});

test('sem raw → no_raw, não chama LLM', function () {
    Ai::fakeAgent(AnonymousAgent::class, ['NUNCA']);
    $r = miner()->mine('vendas', [], test()->out, false);
    expect($r['status'])->toBe('no_raw');
    expect(File::exists(test()->out))->toBeFalse();
});

test('renderCandidates() é puro: banner + 🔍 + proveniência', function () {
    $c = RawChatMiner::renderCandidates('vendas', "🔍 ideia A\n🔍 ideia B", [rawDoc('chats/chat1.md', 'x'), rawDoc('chats/chat2.md', 'y')]);
    expect($c)
        ->toContain('PROPOSTA, NÃO LEI')
        ->toContain('Nunca auto-✅')
        ->toContain('🔍 ideia A')
        ->toContain('- `chats/chat1.md`')
        ->toContain('- `chats/chat2.md`');
    // determinístico
    expect($c)->toBe(RawChatMiner::renderCandidates('vendas', "🔍 ideia A\n🔍 ideia B", [rawDoc('chats/chat1.md', 'x'), rawDoc('chats/chat2.md', 'y')]));
});
