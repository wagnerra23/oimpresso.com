<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Modules\Jana\Ai\Agents\PrUiJudgeAgent;
use Modules\Jana\Ai\UiDeterministicScorer;
use Modules\Jana\Ai\UiJudgeConsensus;
use Modules\Jana\Entities\UiJudgeRun;

/**
 * `ui:judge-pr` — Onda 4.1 do AUTOMATION-ROADMAP (Constituição UI v2).
 *
 * Avalia um PR contra a Constituição UI v2 usando agente LLM (OpenAI gpt-4o-mini)
 * e — opcionalmente — posta comentário inline no PR via `gh`.
 *
 * Workflow:
 *   1. Pega metadata do PR (título · descrição · arquivos modificados) via gh CLI
 *   2. Pega diff filtrado (.tsx, .jsx, .css)
 *   3. Manda pro PrUiJudgeAgent N vezes (UiJudgeConsensus · self-consistency)
 *   4. Agrega: mediana das amostras + confiança (variância) → mata o "alucina ok"
 *   5. Print score + confiança + violações no console
 *   6. (Opcional) `--post-comment` posta no PR via gh
 *   7. Exit code 0 (approve) | 1 (request_changes) | 0 (comment/zona-cinza)
 *
 * Uso típico:
 *   php artisan ui:judge-pr 1438                # avaliar local (stdout only)
 *   php artisan ui:judge-pr 1438 --post-comment # postar comentário no PR
 *   php artisan ui:judge-pr 1438 --strict       # exit 1 se verdict=request_changes
 *
 * Custo estimado por run: ~$0.002 × N amostras (OpenAI gpt-4o-mini · default N=3).
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

        // 4. Self-consistency (dossiê 2026-06-23 §3b): roda o juiz N vezes e agrega
        // a MEDIANA — o single-shot com sorte que alucina "ok" é regredido pra mediana
        // das N amostras, e a variância entre elas vira o sinal de confiança. Anúncio
        // lê o modelo REAL por reflexão (nunca "anuncia X, roda Y").
        [$annProvider, $annModel] = $this->agentProviderModel();
        if (! $this->ensureProviderKey($annProvider)) {
            return self::FAILURE;
        }

        $samples = (int) config('copiloto.ui_judge.samples', 3);
        $abstainBelow = (float) config('copiloto.ui_judge.abstain_below', 0.6);
        $this->info("Enviando pra PrUiJudgeAgent ({$annProvider}/{$annModel}) · {$samples} amostras (self-consistency)...");

        $review = (new UiJudgeConsensus($samples, $abstainBelow))->collect(
            function () use ($prData, $diff): ?array {
                $raw = $this->runAgent($prData, $diff);

                return $raw === null ? null : $this->parseReview($raw);
            }
        );

        if ((int) ($review['samples'] ?? 0) === 0) {
            $this->error('Nenhuma amostra válida do juiz · nada a avaliar');

            return $strict ? self::FAILURE : self::SUCCESS;
        }

        $this->line("  Amostras válidas: {$review['samples']}/{$samples} · confiança ".
            number_format((float) ($review['confianca'] ?? 0), 2));

        // 5.5. Onda 1 (LLM-judge → determinístico · ADR 0255): mescla as 6 dimensões
        // DETERMINÍSTICAS (regex · UiDeterministicScorer) com as 3 SEMÂNTICAS do LLM → 9 dims,
        // computa o score total (0-100) + verdict AQUI (não mais no LLM). O juiz não pontua
        // mais as 6 → sem custo/viés/flakiness nelas.
        $review = $this->mergeDeterministic($review, $diff);

        // 5.6. Gate de confiança (self-consistency · 2026-06-23 §3b/§3c): se os N
        // juízes discordaram (confiança < limiar), um "approve" não é confiável —
        // rebaixa pra "comment" e marca zona cinza (defer humano). Anti-"alucina ok":
        // nota alta só vira aprovação automática se as amostras concordaram. Seguro
        // pro CI (comment = exit 0 · não endurece, só tira o carimbo de approve duvidoso).
        $review = $this->applyConfidenceGate($review);

        // 6. Render no console
        $this->renderReview($review);

        // 6.5. Medição (append-only · jana_ui_judge_runs). Sem isto o juiz era
        // fire-and-forget: postava o comentário e o score evaporava. Best-effort —
        // medição NUNCA derruba o julgamento.
        $this->recordRun($prNumber, $repo, $review);

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
     * Persiste 1 linha de medição (append-only) por julgamento.
     *
     * Provider/model são lidos por reflexão dos atributos do PrUiJudgeAgent —
     * assim a medição reflete o modelo REAL em uso (nunca mais "anuncia X, roda
     * Y"). Best-effort: qualquer falha aqui é avisada mas não derruba o run.
     */
    private function recordRun(int $prNumber, string $repo, array $review): void
    {
        try {
            [$provider, $model] = $this->agentProviderModel();

            $verdict = $review['verdict'] ?? 'comment';
            if (! in_array($verdict, ['approve', 'request_changes', 'comment'], true)) {
                $verdict = 'comment';
            }

            UiJudgeRun::create([
                'pr_number' => $prNumber,
                'repo' => $repo !== '' ? $repo : null,
                'provider' => $provider,
                'model' => $model,
                'score' => (int) ($review['score'] ?? 0),
                'verdict' => $verdict,
                'violacoes_count' => is_array($review['violacoes_estruturais'] ?? null)
                    ? count($review['violacoes_estruturais'])
                    : 0,
                'dimensoes' => $review['dimensoes'] ?? null,
                'confidence' => isset($review['confianca']) ? (float) $review['confianca'] : null,
                'samples' => isset($review['samples']) ? (int) $review['samples'] : null,
                'custo_usd_estimado' => $this->estimateCostUsd($model, (int) ($review['samples'] ?? 1)),
                'judged_at' => now(),
            ]);

            $this->line('  Medição registrada em jana_ui_judge_runs (jana:ui-judge-trend)');
        } catch (\Throwable $e) {
            $this->warn('  Medição não registrada (não-bloqueante): '.$e->getMessage());
        }
    }

    /**
     * Provider + model declarados nos atributos do PrUiJudgeAgent (reflexão).
     *
     * @return array{0:string, 1:string}
     */
    private function agentProviderModel(): array
    {
        $ref = new \ReflectionClass(PrUiJudgeAgent::class);

        $provider = ($ref->getAttributes(\Laravel\Ai\Attributes\Provider::class)[0] ?? null)
            ?->getArguments()[0] ?? 'unknown';
        $model = ($ref->getAttributes(\Laravel\Ai\Attributes\Model::class)[0] ?? null)
            ?->getArguments()[0] ?? 'unknown';

        return [(string) $provider, (string) $model];
    }

    /**
     * Estimativa grosseira de custo por PR (~10k tokens in + ~1k out).
     * NÃO é billing real — só pra dar ordem de grandeza no trend.
     */
    private function estimateCostUsd(string $model, int $samples = 1): ?float
    {
        $per = match (true) {
            str_contains($model, 'opus') => 0.165,
            str_contains($model, 'sonnet') => 0.034,
            str_contains($model, 'haiku') => 0.003,
            str_contains($model, 'gpt-4o-mini') => 0.002,
            str_contains($model, 'gpt-4o') => 0.050,
            default => null,
        };

        // self-consistency: custo cresce ×N amostras
        return $per === null ? null : round($per * max(1, $samples), 4);
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

    /**
     * Pré-flight ÚNICO da API key do provider do PrUiJudgeAgent (lido por reflexão).
     *
     * Roda 1× ANTES do loop de N amostras (não por amostra) — pra não cuspir o erro
     * N vezes. Wagner troca provider editando o #[Provider] do agent; o check segue
     * o atributo, sem hardcode.
     */
    private function ensureProviderKey(string $provider): bool
    {
        $envVar = strtoupper($provider).'_API_KEY';
        $providerKey = (string) (config("ai.providers.{$provider}.key") ?? env($envVar) ?? '');

        if ($providerKey === '') {
            $this->error("{$envVar} não configurada (provider '{$provider}' do PrUiJudgeAgent)");
            $this->line("  Adicionar em .env: {$envVar}=...");
            $this->line('  Depois: php artisan config:clear');

            return false;
        }

        return true;
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
            $msg = $e->getMessage();

            // Diagnóstico amigável pra erros comuns
            if (str_contains($msg, '401') || str_contains($msg, 'x-api-key') || str_contains($msg, 'authentication') || str_contains($msg, 'Incorrect API key')) {
                $this->error('API key do provider inválida ou expirada (HTTP 401)');
                $this->line('  Verificar valor em .env · regenerar key no dashboard do provider');
                $this->line('  Depois: php artisan config:clear');
            } elseif (str_contains($msg, '429') || str_contains($msg, 'rate_limit')) {
                $this->error('Rate limit do provider (HTTP 429) · aguardar 60s e tentar novamente');
            } elseif (str_contains($msg, '529') || str_contains($msg, 'overloaded')) {
                $this->error('Provider overloaded (HTTP 529) · tentar novamente em alguns minutos');
            } else {
                $this->error("PrUiJudgeAgent falhou: {$msg}");
            }

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
        // Onda 1: o LLM agora retorna `dimensoes` (3 semânticas) — sem `score` (calculado
        // no command após mesclar as 6 determinísticas). Exigimos `dimensoes`, não `score`.
        if (! is_array($data) || ! isset($data['dimensoes'])) {
            return null;
        }

        return $data;
    }

    /**
     * Onda 1 (LLM-judge → determinístico · ADR 0255): mescla as 6 dimensões determinísticas
     * (regex · UiDeterministicScorer) com as 3 semânticas julgadas pelo LLM, computa o score
     * total 0-100 (soma das 9 dims · cada 0-10) e deriva o verdict.
     *
     * @param  array<string, mixed>  $review
     * @return array<string, mixed>
     */
    private function mergeDeterministic(array $review, string $diff): array
    {
        $deterministic = (new UiDeterministicScorer)->score($diff); // 6 dims

        $llm = is_array($review['dimensoes'] ?? null) ? $review['dimensoes'] : [];
        // só as 3 dimensões semânticas do LLM entram (ignora qualquer dim que o LLM
        // tenha pontuado fora do contrato).
        $semantic = array_intersect_key($llm, array_flip(UiDeterministicScorer::SEMANTIC_DIMENSIONS));

        $dimensoes = array_merge($deterministic, $semantic); // 6 + 3 = 9
        $review['dimensoes'] = $dimensoes;

        $sum = 0;
        foreach ($dimensoes as $d) {
            $sum += is_array($d) ? (int) ($d['score'] ?? 0) : 0;
        }
        $maxPts = max(count($dimensoes), 1) * 10;
        $score = (int) round($sum / $maxPts * 100);

        $review['score'] = $score;
        $review['verdict'] = $score < 60 ? 'request_changes' : ($score < 85 ? 'comment' : 'approve');

        return $review;
    }

    /**
     * Gate de confiança (self-consistency · dossiê 2026-06-23 §3b/§3c).
     *
     * Se o juiz ABSTÉM (confiança geral < limiar — os N amostras discordaram numa
     * dim semântica), um "approve" não é confiável: rebaixa pra "comment" e marca
     * `gray_zone` (defer humano · a tela sobe pra fila do Wagner, não passa batido).
     * É o anti-"alucina ok": nota alta só vira aprovação automática se as amostras
     * concordaram. NÃO endurece o CI — comment continua exit 0; só remove o carimbo
     * de approve quando o próprio juiz está inseguro.
     *
     * @param  array<string, mixed>  $review
     * @return array<string, mixed>
     */
    private function applyConfidenceGate(array $review): array
    {
        if (($review['abstem'] ?? false) === true && ($review['verdict'] ?? '') === 'approve') {
            $review['verdict'] = 'comment';
            $review['gray_zone'] = true;
            $review['confianca_nota'] = sprintf(
                'Baixa confiança (%.2f < %.2f) entre %d amostras → approve rebaixado pra comment (zona cinza · defer humano)',
                (float) ($review['confianca'] ?? 0),
                (float) config('copiloto.ui_judge.abstain_below', 0.6),
                (int) ($review['samples'] ?? 0),
            );
        }

        return $review;
    }

    private function renderReview(array $review): void
    {
        $score = (int) ($review['score'] ?? 0);
        $verdict = (string) ($review['verdict'] ?? '?');

        $this->newLine();
        $color = $score >= 80 ? 'info' : ($score >= 60 ? 'comment' : 'error');
        $this->{$color}("Score: {$score}/100 · Verdict: {$verdict}");

        if (isset($review['confianca'])) {
            $conf = (float) $review['confianca'];
            $nSamples = (int) ($review['samples'] ?? 0);
            $tag = ($review['gray_zone'] ?? false) ? ' · ZONA CINZA (defer humano)' : '';
            $this->line('  Confiança self-consistency: '.number_format($conf, 2)." ({$nSamples} amostras){$tag}");
            if (! empty($review['confianca_nota'])) {
                $this->line('  '.(string) $review['confianca_nota']);
            }
        }
        $this->newLine();

        if (isset($review['dimensoes']) && is_array($review['dimensoes'])) {
            $this->line('<comment>Dimensões:</comment>');
            foreach ($review['dimensoes'] as $dim => $data) {
                if (is_array($data)) {
                    $s = (int) ($data['score'] ?? 0);
                    $nota = (string) ($data['rationale'] ?? $data['nota'] ?? '');
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

        if (isset($review['confianca'])) {
            $conf = number_format((float) $review['confianca'], 2);
            $nSamples = (int) ($review['samples'] ?? 0);
            $gz = ($review['gray_zone'] ?? false) ? ' · **zona cinza** (defer humano)' : '';
            $out[] = "_Self-consistency: confiança {$conf} · {$nSamples} amostras{$gz}_";
            $out[] = '';
        }

        if (isset($review['dimensoes']) && is_array($review['dimensoes'])) {
            $out[] = '### Dimensões';
            $out[] = '';
            $out[] = '| Dimensão | Score | Nota |';
            $out[] = '|---|---|---|';
            foreach ($review['dimensoes'] as $dim => $data) {
                if (is_array($data)) {
                    $s = (int) ($data['score'] ?? 0);
                    $nota = (string) ($data['rationale'] ?? $data['nota'] ?? '');
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
