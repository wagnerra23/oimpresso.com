<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Services\HandoffIngestService;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * HandoffIngestCommand — PR-1 Loop de Handoff Zero-Paste (Fase 0 · ADR 0283).
 *
 * Parseia handoffs de design assinados, VALIDA a assinatura
 * `HMAC-SHA256(body, HANDOFF_SECRET)` e cria registros 'pending' em
 * `cowork_handoffs`.
 *
 * **Definição do `body` assinado (contrato com o pipeline de export):** o arquivo
 * é `---\n<yaml>\n---\n<body>`. O `sig` cobre **apenas o `<body>`** (tudo após o
 * frontmatter), com CRLF normalizado pra LF — determinístico cross-OS
 * ({@see lição CRLF em writes}). Assinar só o corpo (não o frontmatter) é o que
 * permite embutir o próprio `sig` no frontmatter.
 *
 * **Proveniência (A1 do adversário [AH]):** handoff sem `sig` válida é REJEITADO
 * (logado, não inserido). Comparação timing-safe (`hash_equals`). O SECRET vive
 * só no env do servidor / pipeline de export — NUNCA versionado, nunca no Cowork,
 * nunca no Code.
 *
 * **Append-only (A6 · {@see ADR 0130}/0003):** revisão de um slug já aplicado vira
 * NOVA version 'pending' + a anterior 'superseded'. Re-ingest idêntico (mesmo
 * `source_hash`) é no-op.
 *
 * **Onde roda:** server-side, onde o DB existe (cron ou webhook git→DB espelhando
 * `SyncMemoryWebhookController`). NÃO roda num runner de CI — o runner não alcança
 * o DB de produção (decisão consciente, não "gate de mentira"). O trigger é wiring
 * de deploy, fora do escopo do PR-1.
 *
 * Uso:
 *   php artisan handoff:ingest                    # ingere prototipo-ui/handoffs/*.md
 *   php artisan handoff:ingest --path=outro/dir   # caminho alternativo (abs ou relativo)
 *   php artisan handoff:ingest --dry-run          # valida + mostra plano, não persiste
 *   php artisan handoff:ingest --detail           # motivo de cada rejeição/skip
 *
 * Convenções ({@see .claude/rules/commands.md}): `--detail`/`--dry-run` (não
 * `--verbose`), output PT-BR, exit 0 sucesso / 1 erro.
 *
 * @see Modules\TeamMcp\Entities\CoworkHandoff
 * @see memory/decisions/0283-handoff-loop-zero-paste.md
 */
final class HandoffIngestCommand extends Command
{
    protected $signature = 'handoff:ingest
        {--path=prototipo-ui/handoffs : Diretório dos handoffs assinados (abs ou relativo à base do app)}
        {--dry-run : Valida assinatura e mostra o plano, não persiste}
        {--detail : Imprime o motivo de cada rejeição/skip}';

    protected $description = 'Ingere handoffs de design assinados (HMAC) → cowork_handoffs pending. Rejeita sem sig válida. Append-only por slug/version.';

    public function __construct(private readonly HandoffIngestService $ingestService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! Schema::hasTable('cowork_handoffs')) {
            $this->error('Tabela cowork_handoffs não existe. Rode php artisan migrate primeiro.');

            return self::FAILURE;
        }

        $secret = (string) config('teammcp.handoff_secret', '');
        if ($secret === '') {
            $this->error('HANDOFF_SECRET não configurado (config teammcp.handoff_secret). Ingest assinado exige o segredo — abortando.');

            return self::FAILURE;
        }

        $dir = $this->resolveDir((string) $this->option('path'));
        if (! is_dir($dir)) {
            $this->warn("Diretório não existe: {$dir} — nada a ingerir.");

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $detail = (bool) $this->option('detail');

        $files = glob(rtrim($dir, '/\\') . '/*.md') ?: [];
        $stats = ['rejeitado' => 0, 'sem_mudanca' => 0, 'novo' => 0, 'revisao' => 0];

        foreach ($files as $file) {
            try {
                $this->processFile($file, $secret, $dryRun, $detail, $stats);
            } catch (Throwable $e) {
                $stats['rejeitado']++;
                $this->warn('REJEITADO (erro de parse): ' . basename($file) . ($detail ? " — {$e->getMessage()}" : ''));
            }
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d arquivo(s): %d novo · %d revisão · %d sem-mudança · %d rejeitado',
            $dryRun ? '[DRY RUN]' : 'Ingest:',
            count($files),
            $stats['novo'],
            $stats['revisao'],
            $stats['sem_mudanca'],
            $stats['rejeitado'],
        ));

        return self::SUCCESS;
    }

    /**
     * Resolve o diretório: aceita caminho absoluto (unix `/...` ou Windows `C:`)
     * ou relativo à base do app.
     */
    private function resolveDir(string $path): string
    {
        $isAbsolute = str_starts_with($path, '/') || preg_match('#^[A-Za-z]:#', $path) === 1;

        return $isAbsolute ? $path : base_path($path);
    }

    /**
     * Processa um arquivo: parse frontmatter, delega a validação/persistência ao
     * {@see HandoffIngestService} (mesma lógica do tool HTTP) e traduz o desfecho
     * pra stats + output PT-BR. O service é a fonte única — aqui só há I/O de arquivo.
     *
     * @param  array<string,int>  $stats  acumulador (by-ref)
     */
    private function processFile(string $file, string $secret, bool $dryRun, bool $detail, array &$stats): void
    {
        [$fm, $body] = $this->parseFrontmatter((string) file_get_contents($file));

        $slug = (string) ($fm['handoff_id'] ?? $fm['slug'] ?? '');
        $base = basename($file);

        $result = $this->ingestService->ingest([
            'slug'            => $slug,
            'body_md'         => $body,
            'sig'             => (string) ($fm['sig'] ?? ''),
            'files'           => (array) ($fm['files'] ?? []),
            'tela'            => (string) ($fm['tela'] ?? ''),
            'created_by'      => (string) ($fm['created_by'] ?? 'CC'),
            'audited_against' => $fm['audited_against'] ?? null,
        ], $secret, $dryRun);

        switch ($result['outcome']) {
            case 'rejected':
                $stats['rejeitado']++;
                // A1: sig inválida (default) ou handoff_id ausente.
                $this->warn($result['reason'] === 'slug'
                    ? "REJEITADO (sem handoff_id): {$base}"
                    : "REJEITADO (sig inválida): {$base}");

                return;

            case 'no_op':
                $stats['sem_mudanca']++;
                if ($detail) {
                    $this->line("sem mudança: {$slug} v{$result['version']}");
                }

                return;

            default: // 'created' | 'revised'
                $isRevisao = $result['outcome'] === 'revised';
                $stats[$isRevisao ? 'revisao' : 'novo']++;

                if ($dryRun) {
                    $this->line(sprintf(
                        '[plano] %s v%d (%s)%s',
                        $slug,
                        $result['version'],
                        $isRevisao ? 'revisão' : 'novo',
                        $result['supersede'] ? ' + supersede anterior' : '',
                    ));

                    return;
                }

                $this->info(($isRevisao
                    ? "revisão {$slug} v{$result['version']}"
                    : "novo {$slug} v{$result['version']}") . ' → pending');

                return;
        }
    }

    /**
     * Separa o frontmatter YAML do corpo markdown.
     *
     * Normaliza CRLF→LF ANTES de qualquer coisa pra o `body` (e portanto o HMAC)
     * ser determinístico cross-OS. Sem frontmatter válido → ([], rawNormalizado).
     *
     * @return array{0: array<string,mixed>, 1: string}
     */
    private function parseFrontmatter(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);

        if (! preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $m)) {
            return [[], $raw];
        }

        $fm = Yaml::parse($m[1]);

        return [is_array($fm) ? $fm : [], $m[2]];
    }
}
