<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Modules\Jana\Ai\Agents\PrUiJudgeAgent;

/**
 * `ui:judge-pr` — Onda 4.1 do AUTOMATION-ROADMAP (Constituição UI v2).
 *
 * Avalia um PR contra a Constituição UI v2 usando agente LLM (Anthropic Claude
 * Sonnet 4.5) e — opcionalmente — posta comentário inline no PR via `gh`.
 *
 * Workflow:
 *   1. Pega metadata do PR (título · descrição · arquivos modificados) via gh CLI
 *   2. Pega diff filtrado (.tsx, .jsx, .css)
 *   3. Manda pro PrUiJudgeAgent (anthropic / claude-sonnet-4-5)
 *   4. Parse output JSON
 *   5. Print score + violações no console
 *   6. (Opcional) `--post-comment` posta no PR via gh
 *   7. Exit code 0 (approve) | 1 (request_changes) | 0 (comment)
 *
 * Uso típico:
 *   php artisan ui:judge-pr 1438                # avaliar local (stdout only)
 *   php artisan ui:judge-pr 1438 --post-comment # postar comentário no PR
 *   php artisan ui:judge-pr 1438 --strict       # exit 1 se verdict=request_changes
 *
 * Custo estimado por run: ~$0.034 (Claude Sonnet 4.5) · com prompt caching
 * cai pra ~$0.005 após primeiro PR do dia.
 *
 * @see Modules\Jana\Ai\Agents\PrUiJudgeAgent
 * @see memory/requisitos/_DesignSystem/AUTOMATION-ROADMAP.md (Onda 4)
 */
class UiJudgePrCommand extends Command
{
    protected $signature = 'ui:judge-pr
                            {pr_number : Número do PR (ex: 1438)}
                            {--post-comment : Posta comentário inline no PR via gh CLI}
                            {--strict : Exit 1 se verdict for request_changes (uso CI)}
                            {--repo= : Repo no formato owner/name (default detecta via gh)}
                            {--save-to= : Salva output JSON em arquivo}';

    protected $description = 'Avalia PR contra Constituição UI v2 via LLM Brain B (PrUiJudgeAgent)';

    /**
     * Tamanho máximo de diff enviado ao LLM (em bytes).
     * Diff grande é truncado pra evitar token explosion.
     */
    private const MAX_DIFF_BYTES = 60_000;

    /**
     * @return int Exit code (0 ok · 1 strict failure)
     */
    public function handle(): int
    {
        $prNumber = (int) $this->argument('pr_number');
        $postComment = (bool) $this->option('post-comment');
        $strict = (bool) $this->option('strict');
        $repo = (string) ($this->option('repo') ?? '');
        $saveTo = (string) ($this->option('save-to') ?? '');

        if ($prNumber <= 0) {
            $this->error('PR number inválido');

            return self::FAILURE;
        }

        $this->info("ui:judge-pr · avaliando PR #{$prNumber}");

        // 1. Metadata do PR via gh CLI
        $prData = $this->fetchPrMetadata($prNumber, $repo);
        if ($prData === null) {
            return self::FAILURE;
        }

        $this->line("  Título: {$prData['title']}");
        $this->line('  Arquivos: '.count($prData['files']).' modificado(s)');

        // 2. Filtrar só UI files
        $uiFiles = array_filter(
            $prData['files'],
            fn (string $f): bool => preg_match('/\.(tsx|jsx|css)$/', $f) === 1
        );

        if ($uiFiles === []) {
            $this->info('✓ PR não afeta UI · skip judge (score 100)');

            return self::SUCCESS;
        }

        // 3. Diff filtrado
        $diff = $this->fetchPrDiff($prNumber, $repo, array_values($uiFiles));
        if ($diff === null) {
            return self::FAILURE;
        }

        $this->line('  Diff UI: '.strlen($diff).' bytes');

        // 4. Mandar pro agent
        $this->info('Enviando pra PrUiJudgeAgent (Claude Sonnet 4.5)...');
        $output = $this->runAgent($prData, $diff);
        if ($output === null) {
            return self::FAILURE;
        }

        // 5. Parse JSON
        $review = $this->parseReview($output);
        if ($review === null) {
            $this->warn('Output do LLM não é JSON válido · imprimindo raw:');
            $this->line($output);

            return $strict ? self::FAILURE : self::SUCCESS;
        }

        // 6. Render no console
        $this->renderReview($review);

        // 7. Save to file
        if ($saveTo !== '') {
            file_put_contents(base_path($saveTo), json_encode($review, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Salvo em {$saveTo}");
        }

        // 8. Post comment
        if ($postComment) {
            $this->postPrComment($prNumber, $repo, $review);
        }

        // 9. Exit code
        $verdict = $review['verdict'] ?? 'comment';
        if ($strict && $verdict === 'request_changes') {
            $this->error('Verdict: request_changes · CI strict mode → exit 1');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Pega metadata do PR via gh CLI.
     *
     * @return array{number:int, title:string, body:string, files:array<int, string>}|null
     */
    private function fetchPrMetadata(int $prNumber, string $repo): ?array
    {
        $repoFlag = $repo !== '' ? "--repo {$repo}" : '';
        $cmd = "gh pr view {$prNumber} {$repoFlag} --json number,title,body,files";

        $result = Process::run($cmd);
        if ($result->failed()) {
            $this->error("gh pr view falhou: {$result->errorOutput()}");

            return null;
        }

        $data = json_decode($result->output(), true);
        if (! is_array($data)) {
            $this->error('gh pr view retornou JSON inválido');

            return null;
        }

        return [
            'number' => (int) ($data['number'] ?? 0),
            'title' => (string) ($data['title'] ?? ''),
            'body' => (string) ($data['body'] ?? ''),
            'files' => array_map(
                fn ($f) => (string) ($f['path'] ?? ''),
                $data['files'] ?? []
            ),
        ];
    }

    /**
     * Pega diff filtrado do PR (apenas arquivos UI).
     */
    private function fetchPrDiff(int $prNumber, string $repo, array $files): ?string
    {
        $repoFlag = $repo !== '' ? "--repo {$repo}" : '';
        $cmd = "gh pr diff {$prNumber} {$repoFlag}";

        $result = Process::run($cmd);
        if ($result->failed()) {
            $this->error("gh pr diff falhou: {$result->errorOutput()}");

            return null;
        }

        $fullDiff = $result->output();

        // Filtrar diff pra incluir apenas arquivos UI
        $filtered = $this->filterDiffByFiles($fullDiff, $files);

        // Truncar se passar do limite
        if (strlen($filtered) > self::MAX_DIFF_BYTES) {
            $filtered = substr($filtered, 0, self::MAX_DIFF_BYTES)
                ."\n\n[... diff truncado em ".self::MAX_DIFF_BYTES.' bytes pra economia de tokens ...]';
        }

        return $filtered;
    }

    /**
     * Filtra `git diff` mantendo apenas blocos `diff --git a/<file>` que estão em $files.
     */
    private function filterDiffByFiles(string $diff, array $files): string
    {
        $blocks = preg_split('/^diff --git /m', $diff);
        if (! is_array($blocks)) {
            return $diff;
        }

        $keep = [];
        foreach ($blocks as $block) {
            if ($block === '') {
                continue;
            }
            // Primeira linha do bloco: `a/path/to/file b/path/to/file`
            $firstLine = strtok($block, "\n");
            $matches = [];
            if (preg_match('#a/(\S+)#', (string) $firstLine, $matches)) {
                $path = $matches[1];
                if (in_array($path, $files, true)) {
                    $keep[] = 'diff --git '.$block;
                }
            }
        }

        return implode('', $keep);
    }

    private function runAgent(array $prData, string $diff): ?string
    {
        $userPrompt = sprintf(
            "Avalie este PR contra a Constituição UI v2.\n\n## Metadata\nPR #%d · %s\n\n## Descrição\n%s\n\n## Diff UI (.tsx/.jsx/.css)\n```diff\n%s\n```\n\nRetorne JSON estrito conforme schema do system prompt.",
            $prData['number'],
            $prData['title'],
            substr($prData['body'], 0, 2000),
            $diff
        );

        try {
            $agent = new PrUiJudgeAgent;
            $response = $agent->prompt($userPrompt);

            return (string) $response;
        } catch (\Throwable $e) {
            $this->error("PrUiJudgeAgent falhou: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * Parse JSON · tolera ```json ... ``` wrapping.
     *
     * @return array<string, mixed>|null
     */
    private function parseReview(string $output): ?array
    {
        // Strip markdown code fences se LLM envolveu
        $clean = trim($output);
        $clean = preg_replace('/^```(?:json)?\s*\n/', '', $clean) ?? $clean;
        $clean = preg_replace('/\n```\s*$/', '', $clean) ?? $clean;

        $data = json_decode($clean, true);
        if (! is_array($data) || ! isset($data['score'])) {
            return null;
        }

        return $data;
    }

    private function renderReview(array $review): void
    {
        $score = (int) ($review['score'] ?? 0);
        $verdict = (string) ($review['verdict'] ?? '?');

        $this->newLine();
        $color = $score >= 80 ? 'info' : ($score >= 60 ? 'comment' : 'error');
        $this->{$color}("Score: {$score}/100 · Verdict: {$verdict}");
        $this->newLine();

        if (isset($review['dimensoes']) && is_array($review['dimensoes'])) {
            $this->line('<comment>Dimensões:</comment>');
            foreach ($review['dimensoes'] as $dim => $data) {
                if (is_array($data)) {
                    $s = (int) ($data['score'] ?? 0);
                    $nota = (string) ($data['nota'] ?? '');
                    $this->line("  {$dim}: {$s}/10 · {$nota}");
                }
            }
            $this->newLine();
        }

        if (isset($review['violacoes_estruturais']) && is_array($review['violacoes_estruturais'])) {
            $count = count($review['violacoes_estruturais']);
            if ($count > 0) {
                $this->warn("Violações estruturais ({$count}):");
                foreach ($review['violacoes_estruturais'] as $v) {
                    $tipo = (string) ($v['tipo'] ?? '?');
                    $arquivo = (string) ($v['arquivo'] ?? '?');
                    $linha = (string) ($v['linha'] ?? '?');
                    $det = (string) ($v['detalhe'] ?? '');
                    $sev = (string) ($v['severidade'] ?? 'info');
                    $this->line("  [{$sev}] {$arquivo}:{$linha} · {$tipo}");
                    $this->line("    {$det}");
                }
                $this->newLine();
            }
        }

        if (isset($review['sugestoes']) && is_array($review['sugestoes'])) {
            $this->line('<comment>Sugestões:</comment>');
            foreach ($review['sugestoes'] as $s) {
                $this->line("  - {$s}");
            }
        }
    }

    private function postPrComment(int $prNumber, string $repo, array $review): void
    {
        $body = $this->renderMarkdownComment($review);
        $repoFlag = $repo !== '' ? "--repo {$repo}" : '';

        $tmp = tempnam(sys_get_temp_dir(), 'pr-ui-judge-');
        file_put_contents($tmp, $body);

        $cmd = "gh pr comment {$prNumber} {$repoFlag} --body-file ".escapeshellarg($tmp);
        $result = Process::run($cmd);
        @unlink($tmp);

        if ($result->failed()) {
            $this->error("gh pr comment falhou: {$result->errorOutput()}");

            return;
        }

        $this->info("Comentário postado no PR #{$prNumber}");
    }

    private function renderMarkdownComment(array $review): string
    {
        $score = (int) ($review['score'] ?? 0);
        $verdict = (string) ($review['verdict'] ?? '?');

        $out = ["## PR UI Judge · score {$score}/100 · verdict `{$verdict}`", ''];

        if (isset($review['dimensoes']) && is_array($review['dimensoes'])) {
            $out[] = '### Dimensões';
            $out[] = '';
            $out[] = '| Dimensão | Score | Nota |';
            $out[] = '|---|---|---|';
            foreach ($review['dimensoes'] as $dim => $data) {
                if (is_array($data)) {
                    $s = (int) ($data['score'] ?? 0);
                    $nota = (string) ($data['nota'] ?? '');
                    $out[] = "| `{$dim}` | {$s}/10 | {$nota} |";
                }
            }
            $out[] = '';
        }

        if (! empty($review['violacoes_estruturais']) && is_array($review['violacoes_estruturais'])) {
            $out[] = '### Violações estruturais';
            $out[] = '';
            foreach ($review['violacoes_estruturais'] as $v) {
                $sev = (string) ($v['severidade'] ?? 'info');
                $arq = (string) ($v['arquivo'] ?? '?');
                $linha = (string) ($v['linha'] ?? '?');
                $tipo = (string) ($v['tipo'] ?? '?');
                $det = (string) ($v['detalhe'] ?? '');
                $out[] = "- **[{$sev}]** `{$arq}:{$linha}` — {$tipo}";
                $out[] = "  - {$det}";
            }
            $out[] = '';
        }

        if (! empty($review['sugestoes']) && is_array($review['sugestoes'])) {
            $out[] = '### Sugestões';
            $out[] = '';
            foreach ($review['sugestoes'] as $s) {
                $out[] = "- {$s}";
            }
            $out[] = '';
        }

        if (! empty($review['lembretes']) && is_array($review['lembretes'])) {
            $out[] = '### Lembretes Constituição UI v2';
            $out[] = '';
            foreach ($review['lembretes'] as $l) {
                $out[] = "- {$l}";
            }
            $out[] = '';
        }

        $out[] = '---';
        $out[] = '_Gerado por `php artisan ui:judge-pr` · [Constituição UI v2 · ADR UI-0013](memory/requisitos/_DesignSystem/adr/ui/0013-constituicao-ui-v2-camadas.md)_';

        return implode("\n", $out);
    }
}
