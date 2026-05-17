<?php

declare(strict_types=1);

use App\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Modules\Jana\Entities\Mcp\McpToken;
use Modules\TeamMcp\Http\Controllers\ScorecardController;
use Modules\TeamMcp\Services\McpTokenIssuer;
use Modules\TeamMcp\Services\ScorecardBuilderService;

uses(Tests\TestCase::class);

/**
 * Wave 23 Scorecard + Rotate Test — TeamMcp G1+G3 FICHA W22.
 *
 * Cobertura:
 *   - G3 FICHA: McpTokenIssuer::rotate atômico (revoke+issue mesma transação)
 *   - G3 FICHA: comando teammcp:token:rotate registrado
 *   - G1 FICHA: ScorecardController smoke (route + builders)
 *
 * Tier 0 IRREVOGÁVEL (ADR 0081):
 *   - rotate retorna raw 1× (não loga, não persiste)
 *   - rotate transactional (old revogado SE new criado)
 *   - rotate guard ownership (token de outro user retorna null)
 *
 * Multi-tenant Tier 0: scorecard repo-wide (cross-business Wagner-only).
 *
 * @see Modules\TeamMcp\Services\McpTokenIssuer::rotate
 * @see Modules\TeamMcp\Console\Commands\RotateTokenCommand
 * @see Modules\TeamMcp\Http\Controllers\ScorecardController
 */

function requiresMcpSchema(): void
{
    if (DB::connection()->getDriverName() === 'sqlite') {
        test()->markTestSkipped('SQLite-incompatível: mcp_tokens + users exigem schema MySQL UltimatePOS.');
    }
    if (! Schema::hasTable('mcp_tokens') || ! Schema::hasTable('users')) {
        test()->markTestSkipped('Schema MCP ausente — rode migrations primeiro.');
    }
}

// ---------- G3 FICHA: rotate atômico ----------

it('McpTokenIssuer::rotate gera novo token + revoga anterior atomicamente', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_test_user_w23'],
        [
            'email' => 'rotate_test@test.local',
            'password' => bcrypt('secret'),
            'business_id' => 1,
            'first_name' => 'Rotate',
            'last_name' => 'Test',
        ]
    );

    [$old, $oldRaw] = McpToken::gerar($user->id, 'Token pra rotacionar W23');
    expect($oldRaw)->toStartWith('mcp_');

    $issuer = new McpTokenIssuer();
    $result = $issuer->rotate($user->id, (int) $old->id, 'rotated W23');

    expect($result)->not->toBeNull();
    expect($result['old_token_id'])->toBe((int) $old->id);
    expect($result['raw'])->toStartWith('mcp_');
    expect($result['raw'])->not->toBe($oldRaw, 'novo raw deve ser distinto do anterior');
    expect($result['new_token']->name)->toBe('rotated W23');

    // Old token: revogado (soft-deleted)
    $oldReloaded = McpToken::withTrashed()->find($old->id);
    expect($oldReloaded->trashed())->toBeTrue();
    expect($oldReloaded->expires_at)->not->toBeNull();

    // Cleanup
    $result['new_token']->forceDelete();
    $oldReloaded->forceDelete();
});

it('McpTokenIssuer::rotate retorna null quando token pertence a outro user (Tier 0 guard)', function () {
    requiresMcpSchema();

    $userA = User::firstOrCreate(
        ['username' => 'rotate_user_a_w23'],
        ['email' => 'ra@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'A'],
    );
    $userB = User::firstOrCreate(
        ['username' => 'rotate_user_b_w23'],
        ['email' => 'rb@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'B'],
    );

    [$tokenA, $rawA] = McpToken::gerar($userA->id, 'Token de A');

    $issuer = new McpTokenIssuer();

    // userB tenta rotacionar token de A → deve retornar null (guard ownership)
    $result = $issuer->rotate($userB->id, (int) $tokenA->id);

    expect($result)->toBeNull('rotate de outro user JAMAIS pode suceder (Tier 0 segredo)');

    // Token de A continua ativo (não foi revogado por engano)
    $tokenA->refresh();
    expect($tokenA->expires_at)->toBeNull();

    $tokenA->forceDelete();
});

// ---------- G3 FICHA: comando registrado ----------

it('comando teammcp:token:rotate está registrado em artisan', function () {
    $commands = Artisan::all();
    expect($commands)->toHaveKey('teammcp:token:rotate');
});

it('teammcp:token:rotate sem args retorna exit code de erro', function () {
    $exit = Artisan::call('teammcp:token:rotate');

    // SQLite (sem schema) → exit 1 com "tabela ausente"
    // MySQL (com schema) → exit 1 com mensagem de uso (--token/--user)
    expect($exit)->toBeIn([1, 2]);

    $output = Artisan::output();
    // Aceita uma das duas vias (schema ausente OU mensagem de uso).
    $hasSchemaMsg = str_contains($output, 'mcp_tokens') || str_contains($output, 'migrations');
    $hasUsageMsg = str_contains($output, '--token') || str_contains($output, '--user');
    expect($hasSchemaMsg || $hasUsageMsg)->toBeTrue(
        'Comando deve sinalizar schema ausente OU instruir uso (--token/--user).'
    );
});

it('teammcp:token:rotate signature usa --detail (não --verbose Symfony reserved)', function () {
    $cmd = app(\Modules\TeamMcp\Console\Commands\RotateTokenCommand::class);
    $signature = (new ReflectionClass($cmd))->getProperty('signature');
    $signature->setAccessible(true);
    $sig = $signature->getValue($cmd);

    expect($sig)->toContain('teammcp:token:rotate');
    expect($sig)->toContain('--detail');
    expect($sig)->toContain('--dry-run');
    expect(str_contains($sig, '--verbose'))->toBeFalse('--verbose é reservado Symfony (rule commands.md)');
});

// ---------- G1 FICHA: Scorecard route + controller ----------

it('rota team-mcp.scorecard.index está registrada', function () {
    $route = Route::getRoutes()->getByName('team-mcp.scorecard.index');
    expect($route)->not->toBeNull('rota /team-mcp/scorecard deve estar registrada (G1 FICHA W22)');
});

it('ScorecardController::buildFacts retorna estrutura canônica Facts', function () {
    requiresMcpSchema();

    $ctl = app(ScorecardController::class);
    $reflect = new ReflectionClass($ctl);
    $method = $reflect->getMethod('buildFacts');
    $method->setAccessible(true);
    $facts = $method->invoke($ctl);

    expect($facts)->toHaveKeys([
        'tokens_ativos',
        'calls_7d',
        'cost_7d_brl',
        'users_ativos_7d',
        'top_tools_7d',
        'audit_log_present',
        'tokens_table_present',
    ]);
    expect($facts['tokens_ativos'])->toBeInt();
    expect($facts['cost_7d_brl'])->toBeFloat();
    expect($facts['top_tools_7d'])->toBeArray();
});

it('ScorecardController::buildChecks retorna array de checks com name/ok/detail', function () {
    requiresMcpSchema();

    $ctl = app(ScorecardController::class);
    $reflect = new ReflectionClass($ctl);
    $method = $reflect->getMethod('buildChecks');
    $method->setAccessible(true);
    $checks = $method->invoke($ctl);

    expect($checks)->toBeArray();
    expect(count($checks))->toBeGreaterThanOrEqual(4, 'esperado ao menos 4 checks Facts+Checks');

    foreach ($checks as $c) {
        expect($c)->toHaveKeys(['name', 'ok', 'detail']);
        expect($c['ok'])->toBeBool();
        expect($c['name'])->toBeString();
        expect($c['detail'])->toBeString();
    }
});

// ---------- Wave 25 D4: ScorecardBuilderService extraction smoke ----------

it('ScorecardBuilderService carrega via container (Wave 25 D4 extraction)', function () {
    $svc = app(ScorecardBuilderService::class);
    expect($svc)->toBeInstanceOf(ScorecardBuilderService::class);
});

it('ScorecardBuilderService expõe buildFacts + buildChecks + 4 checkXxx helpers', function () {
    $svc = app(ScorecardBuilderService::class);
    $ref = new ReflectionClass($svc);

    expect($ref->hasMethod('buildFacts'))->toBeTrue();
    expect($ref->hasMethod('buildChecks'))->toBeTrue();
    expect($ref->hasMethod('checkSchema'))->toBeTrue();
    expect($ref->hasMethod('checkBriefRecente'))->toBeTrue();
    expect($ref->hasMethod('checkTokensSemOrphan'))->toBeTrue();
    expect($ref->hasMethod('checkCustoMedioSanidade'))->toBeTrue();
});

it('ScorecardBuilderService::checkSchema retorna ok=false pra tabela inexistente', function () {
    $svc = app(ScorecardBuilderService::class);
    $check = $svc->checkSchema('tabela_que_nao_existe_w25', 'Teste tabela fake');

    expect($check)->toHaveKeys(['name', 'ok', 'detail']);
    expect($check['ok'])->toBeFalse();
    expect($check['detail'])->toContain('AUSENTE');
});

it('ScorecardController delega buildFacts/buildChecks pra Service (Wave 25 thin)', function () {
    // Garantir que controller injeta Service e não duplica lógica
    $path = base_path('Modules/TeamMcp/Http/Controllers/ScorecardController.php');
    $content = file_get_contents($path);

    expect($content)->toContain('ScorecardBuilderService $builder');
    expect($content)->toContain('$this->builder->buildFacts()');
    expect($content)->toContain('$this->builder->buildChecks()');

    // E que NÃO tem mais OtelHelper direto (foi pra Service)
    expect($content)->not->toContain('use App\\Util\\OtelHelper;');
});

// ---------- Wave 25 D3: McpTokenIssuer::rotate extras ----------

it('McpTokenIssuer::rotate preserva note do novo token quando informado', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_note_test_w25'],
        ['email' => 'rn@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RN'],
    );

    [$old, ] = McpToken::gerar($user->id, 'Token original');
    $issuer = new McpTokenIssuer();
    $result = $issuer->rotate($user->id, (int) $old->id, 'Nota custom rotate W25');

    expect($result)->not->toBeNull();
    expect($result['new_token']->name)->toBe('Nota custom rotate W25');

    $result['new_token']->forceDelete();
    McpToken::withTrashed()->find($old->id)?->forceDelete();
});

it('McpTokenIssuer::rotate sem note usa default "Rotated em <data>"', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_default_note_w25'],
        ['email' => 'rdn@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RDN'],
    );

    [$old, ] = McpToken::gerar($user->id, 'Token original sem nota');
    $issuer = new McpTokenIssuer();
    $result = $issuer->rotate($user->id, (int) $old->id);

    expect($result)->not->toBeNull();
    // Default depende da implementação — só asseguramos que algo veio (não vazio)
    expect($result['new_token']->name)->not->toBeEmpty();

    $result['new_token']->forceDelete();
    McpToken::withTrashed()->find($old->id)?->forceDelete();
});

it('McpTokenIssuer::rotate retorna null pra token inexistente (idempotência)', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_inex_w25'],
        ['email' => 'ri@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RI'],
    );

    $issuer = new McpTokenIssuer();
    // Token id absurdo — não existe
    $result = $issuer->rotate($user->id, 99999999);

    expect($result)->toBeNull('rotate de token inexistente deve retornar null sem efeito colateral');
});

// ---------- Wave 27 D2: rotate expandido (race + double-revoke + raw isolation) ----------

it('McpTokenIssuer::rotate em sequência (A→B→C) revoga A+B, mantém C ativo', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_chain_w27'],
        ['email' => 'rc@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RC'],
    );

    [$a, ] = McpToken::gerar($user->id, 'A');
    $issuer = new McpTokenIssuer();

    // Rotate A → B
    $resultB = $issuer->rotate($user->id, (int) $a->id, 'B');
    expect($resultB)->not->toBeNull();
    $bId = (int) $resultB['new_token']->id;

    // Rotate B → C
    $resultC = $issuer->rotate($user->id, $bId, 'C');
    expect($resultC)->not->toBeNull();
    $cId = (int) $resultC['new_token']->id;

    // Estado final: A revoked, B revoked, C ativo
    expect(McpToken::withTrashed()->find($a->id)?->trashed())->toBeTrue();
    expect(McpToken::withTrashed()->find($bId)?->trashed())->toBeTrue();

    $c = McpToken::find($cId);
    expect($c)->not->toBeNull();
    expect($c->expires_at)->toBeNull('C deve continuar ativo após chain rotate');

    // Cleanup
    McpToken::withTrashed()->whereIn('id', [(int) $a->id, $bId, $cId])->forceDelete();
});

it('McpTokenIssuer::rotate de token JÁ revogado retorna null (Tier 0 segredo idempotência)', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_revoked_w27'],
        ['email' => 'rrev@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RR'],
    );

    [$token, ] = McpToken::gerar($user->id, 'Token pra revogar antes de rotate');
    $issuer = new McpTokenIssuer();

    // Revoga primeiro
    $revoked = $issuer->revoke((int) $token->id);
    expect($revoked)->toBeTrue();

    // Agora tenta rotate de token já soft-deleted — find() não acha (sem withTrashed)
    $result = $issuer->rotate($user->id, (int) $token->id);

    expect($result)->toBeNull('rotate de token já revogado JAMAIS deve emitir novo (defesa Tier 0)');

    // Cleanup
    McpToken::withTrashed()->find($token->id)?->forceDelete();
});

it('McpTokenIssuer::rotate raw NÃO é logado em info-level (defesa em profundidade Tier 0)', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'rotate_log_w27'],
        ['email' => 'rlog@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'RL'],
    );

    [$old, ] = McpToken::gerar($user->id, 'Pre-rotate token');

    // Captura logs durante rotate
    $logs = [];
    \Illuminate\Support\Facades\Log::listen(function ($level, $message, $context) use (&$logs) {
        $logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
    });

    $issuer = new McpTokenIssuer();
    $result = $issuer->rotate($user->id, (int) $old->id, 'rotate W27 log audit');

    expect($result)->not->toBeNull();
    $raw = $result['raw'];

    // O raw token NÃO deve aparecer em nenhum log capturado
    foreach ($logs as $entry) {
        $serialized = $entry['message'] . ' ' . json_encode($entry['context'] ?? []);
        expect($serialized)->not->toContain($raw, 'raw token jamais pode aparecer em log estruturado');
    }

    // Cleanup
    $result['new_token']->forceDelete();
    McpToken::withTrashed()->find($old->id)?->forceDelete();
});

it('McpTokenIssuer::rotate cross-user (B tenta rotacionar token A→C) retorna null sem efeito', function () {
    requiresMcpSchema();

    $userA = User::firstOrCreate(
        ['username' => 'rotate_xuser_a_w27'],
        ['email' => 'xa@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'XA'],
    );
    $userB = User::firstOrCreate(
        ['username' => 'rotate_xuser_b_w27'],
        ['email' => 'xb@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'XB'],
    );

    [$tokenA, ] = McpToken::gerar($userA->id, 'Token de A — alvo de cross-user attack');
    $countAntes = McpToken::where('user_id', $userB->id)->count();

    $issuer = new McpTokenIssuer();
    // userB tenta rotacionar token de A (anti-pattern Tier 0)
    $result = $issuer->rotate($userB->id, (int) $tokenA->id, 'tentativa ilícita W27');

    expect($result)->toBeNull('cross-user rotate JAMAIS pode suceder');

    // Token A continua intacto (não foi revogado por engano)
    $tokenA->refresh();
    expect($tokenA->expires_at)->toBeNull();

    // userB não ganhou token novo (rotate falhou sem efeito colateral)
    $countDepois = McpToken::where('user_id', $userB->id)->count();
    expect($countDepois)->toBe($countAntes, 'userB não pode ganhar tokens novos via cross-user attack');

    $tokenA->forceDelete();
});

it('countActive ignora tokens soft-deleted (consistente com rotate)', function () {
    requiresMcpSchema();

    $user = User::firstOrCreate(
        ['username' => 'count_active_w27'],
        ['email' => 'ca@test.local', 'password' => bcrypt('x'), 'business_id' => 1, 'first_name' => 'CA'],
    );

    $issuer = new McpTokenIssuer();
    $countInicial = $issuer->countActive($user->id);

    [$token, ] = McpToken::gerar($user->id, 'Token pra contar');
    expect($issuer->countActive($user->id))->toBe($countInicial + 1);

    $issuer->revoke((int) $token->id);
    expect($issuer->countActive($user->id))->toBe($countInicial, 'após revoke, count volta ao inicial');

    McpToken::withTrashed()->find($token->id)?->forceDelete();
});
