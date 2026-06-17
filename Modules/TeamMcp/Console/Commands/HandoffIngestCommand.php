<?php

declare(strict_types=1);

namespace Modules\TeamMcp\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Modules\TeamMcp\Entities\CoworkHandoff;
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
     * Processa um arquivo: parse frontmatter, valida HMAC, aplica append-only.
     *
     * @param  array<string,int>  $stats  acumulador (by-ref)
     */
    private function processFile(string $file, string $secret, bool $dryRun, bool $detail, array &$stats): void
    {
        [$fm, $body] = $this->parseFrontmatter((string) file_get_contents($file));

        // A1: assinatura obrigatória — rejeita unsigned/forjado (timing-safe).
        $expected = hash_hmac('sha256', $body, $secret);
        $provided = (string) ($fm['sig'] ?? '');
        if ($provided === '' || ! hash_equals($expected, $provided)) {
            $stats['rejeitado']++;
            $this->warn('REJEITADO (sig inválida): ' . basename($file));

            return;
        }

        $slug = (string) ($fm['handoff_id'] ?? $fm['slug'] ?? '');
        if ($slug === '') {
            $stats['rejeitado']++;
            $this->warn('REJEITADO (sem handoff_id): ' . basename($file));

            return;
        }

        $hash = hash('sha256', $body);
        $existing = CoworkHandoff::where('slug', $slug)->orderByDesc('version')->first();

        // Re-ingest idêntico = no-op (dedup por source_hash).
        if ($existing && $existing->source_hash === $hash) {
            $stats['sem_mudanca']++;
            if ($detail) {
                $this->line("sem mudança: {$slug} v{$existing->version}");
            }

            return;
        }

        $version = $existing ? ((int) $existing->version + 1) : 1;
        $isRevisao = (bool) $existing;
        $supersede = $existing && $existing->status === 'applied';

        if ($dryRun) {
            $stats[$isRevisao ? 'revisao' : 'novo']++;
            $this->line(sprintf(
                '[plano] %s v%d (%s)%s',
                $slug,
                $version,
                $isRevisao ? 'revisão' : 'novo',
                $supersede ? ' + supersede anterior' : '',
            ));

            return;
        }

        // A6: revisão de algo já aplicado = lápide na anterior (append-only).
        if ($existing !== null && $existing->status === 'applied') {
            CoworkHandoff::where('id', $existing->id)->update(['status' => 'superseded']);
        }

        CoworkHandoff::create([
            'slug'            => $slug,
            'version'         => $version,
            'tela'            => (string) ($fm['tela'] ?? ''),
            'status'          => 'pending',
            'audited_against' => $fm['audited_against'] ?? null,
            'body_md'         => $body,
            'files_json'      => (array) ($fm['files'] ?? []),
            'source_hash'     => $hash,
            'sig'             => $provided,
            'created_by'      => (string) ($fm['created_by'] ?? 'CC'),
            'created_at'      => now(),
        ]);

        $stats[$isRevisao ? 'revisao' : 'novo']++;
        $this->info(($isRevisao ? "revisão {$slug} v{$version}" : "novo {$slug} v{$version}") . ' → pending');
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
