<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

/**
 * Snapshot test — garante que o procedure `refresh_brief_inputs_cache`
 * deployed em MySQL bate com a migration canônica mais recente.
 *
 * Por quê existe: auditoria 2026-05-07 (US-COPI-088) revelou que o
 * procedure em prod tinha divergido do arquivo SQL em memória. Política
 * dura (ADR 0094 §5 SoC brutal): DDL só via migration. Qualquer edição
 * direta em prod sem migration quebra este teste.
 *
 * Em CI (SQLite): teste é marcado skipped (SQLite não suporta procedures).
 * Em staging/prod (MySQL): valida hash deployed vs migration.
 *
 * Se falhar: crie nova migration que re-cria o procedure com o SQL correto.
 * NUNCA edite o procedure diretamente via SQL prompt.
 *
 * Refs: US-COPI-092, ADR 0094 §5, memory/proibicoes.md
 */

/** Normaliza SQL para comparação: remove DEFINER + backticks, colapsa espaços, lowercase. */
function normalizeProcSql(string $sql): string
{
    // Strip DEFINER first (its regex anchors on backticks), then drop all remaining
    // backticks: MySQL's SHOW CREATE backtick-quotes the routine name + identifiers,
    // the migration source declares them bare. Quoting is never semantic drift —
    // leaving the backticks made an unchanged procedure read as drift (US-COPI-092).
    $sql = preg_replace('/DEFINER\s*=\s*`[^`]*`@`[^`]*`\s*/i', '', $sql);
    $sql = str_replace('`', '', $sql);

    return preg_replace('/\s+/', ' ', strtolower(trim($sql)));
}

/** Extrai o bloco SQL do heredoc <<<'SQL' ... SQL) da migration. */
function canonicalProcSql(): string
{
    $file = base_path(
        'database/migrations/2026_05_07_120000_fix_brief_aggregator_in_flight_adrs_activity.php'
    );

    expect(file_exists($file))->toBeTrue("Migration canônica não encontrada: {$file}");

    $content = file_get_contents($file);

    // Captura o segundo bloco heredoc (CREATE PROCEDURE, não o DROP)
    preg_match_all("/<<<'SQL'\n(.+?)SQL\)/s", $content, $matches);

    // Índice 1 = CREATE PROCEDURE (o DROP não tem conteúdo relevante)
    $createBlock = collect($matches[1] ?? [])
        ->first(fn ($block) => str_contains(strtolower($block), 'create procedure'));

    expect($createBlock)->not->toBeNull('Bloco CREATE PROCEDURE não encontrado na migration');

    return $createBlock;
}

test('procedure refresh_brief_inputs_cache existe no banco', function () {
    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Drift check requer MySQL — skipped em SQLite/CI');
    }

    $rows = \Illuminate\Support\Facades\DB::select(
        "SELECT routine_name FROM information_schema.routines
         WHERE routine_schema = DATABASE()
           AND routine_type = 'PROCEDURE'
           AND routine_name = 'refresh_brief_inputs_cache'"
    );

    expect($rows)->not->toBeEmpty(
        'Procedure refresh_brief_inputs_cache não existe — rode: php artisan migrate'
    );
});

test('refresh_brief_inputs_cache nao divergiu da migration canonica', function () {
    if (\Illuminate\Support\Facades\DB::getDriverName() !== 'mysql') {
        $this->markTestSkipped('Drift check requer MySQL — skipped em SQLite/CI');
    }

    $canonical = normalizeProcSql(canonicalProcSql());

    $rows = \Illuminate\Support\Facades\DB::select(
        'SHOW CREATE PROCEDURE refresh_brief_inputs_cache'
    );
    $deployed = normalizeProcSql($rows[0]->{'Create Procedure'} ?? '');

    expect(md5($deployed))->toBe(
        md5($canonical),
        "Procedure divergiu da migration canônica.\n" .
        "Política dura (ADR 0094 §5): DDL só via migration.\n" .
        'Crie nova migration re-criando o procedure e rode php artisan migrate.'
    );
});
