<?php

namespace Modules\ADS\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * ARQ-0011 — Endpoints consultados pelo Brain A daemon (CT 100) via HTTP poll.
 *
 * Substituem os watchers v1 que liam filesystem direto, agora que daemon
 * está em CT 100 e app está em Hostinger.
 */
class RecentEventsController extends Controller
{
    private const LOG_LINE_RE = '/^\[(?P<datetime>[^\]]+)\]\s+\w+\.(?P<level>[A-Z]+):\s+(?P<message>.+?)(?:\s+\{.*\})?$/';

    /**
     * GET /api/ads/recent-commits?since=<sha>&limit=20
     *
     * Retorna commits do HEAD até `since` (exclusivo). Se `since` ausente, retorna só HEAD.
     * Limite máximo: 100 commits para evitar payloads pesados.
     */
    public function commits(Request $request): JsonResponse
    {
        $since = $request->query('since');
        $limit = min((int) $request->query('limit', 20), 100);

        try {
            $headSha = $this->git(['rev-parse', 'HEAD']);

            if (! $since) {
                $info = $this->commitInfo($headSha);
                return response()->json(['head' => $headSha, 'commits' => [$info]]);
            }

            if ($since === $headSha) {
                return response()->json(['head' => $headSha, 'commits' => []]);
            }

            // git log "$since..HEAD" — do mais recente ao mais antigo; reverse para cronológico
            $log = $this->git(['log', "{$since}..HEAD", '--pretty=format:%H', "--max-count={$limit}"]);
            $shas = array_filter(array_map('trim', explode("\n", $log)));
            $shas = array_reverse($shas);

            $commits = [];
            foreach ($shas as $sha) {
                $commits[] = $this->commitInfo($sha);
            }

            return response()->json(['head' => $headSha, 'commits' => $commits]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ads.recent_commits.failed', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'git_error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/ads/recent-errors?since=<offset>&limit=50
     *
     * Retorna últimas linhas ERROR/CRITICAL/EMERGENCY do laravel.log com nível >= ERROR.
     * `since` é o byte offset retornado pela última chamada (paginação simples).
     * Se `since` for >= tamanho atual do arquivo, retorna lista vazia.
     */
    public function errors(Request $request): JsonResponse
    {
        $since = (int) $request->query('since', 0);
        $limit = min((int) $request->query('limit', 50), 200);

        $logPath = storage_path('logs/laravel.log');

        if (! is_file($logPath)) {
            return response()->json(['offset' => 0, 'errors' => []]);
        }

        try {
            $size = filesize($logPath);
            if ($size === false) {
                return response()->json(['offset' => 0, 'errors' => []]);
            }

            // Truncação detectada (rotação de log) — reseta offset
            if ($since > $size) {
                $since = 0;
            }
            if ($since === $size) {
                return response()->json(['offset' => $size, 'errors' => []]);
            }

            $fp = fopen($logPath, 'rb');
            if ($fp === false) {
                return response()->json(['error' => 'log_open_failed'], 500);
            }
            fseek($fp, $since);
            $chunk = fread($fp, $size - $since);
            fclose($fp);

            if ($chunk === false) {
                return response()->json(['error' => 'log_read_failed'], 500);
            }

            $errors = [];
            $lines = preg_split('/\r?\n/', $chunk);
            foreach ($lines as $line) {
                if ($line === '') continue;
                if (! preg_match('/\.(ERROR|CRITICAL|EMERGENCY|ALERT):/i', $line)) continue;

                if (preg_match(self::LOG_LINE_RE, $line, $m)) {
                    $errors[] = [
                        'datetime' => $m['datetime'],
                        'level'    => $m['level'],
                        'message'  => mb_strimwidth($m['message'], 0, 800, '…'),
                    ];
                } else {
                    $errors[] = [
                        'datetime' => null,
                        'level'    => 'ERROR',
                        'message'  => mb_strimwidth($line, 0, 800, '…'),
                    ];
                }

                if (count($errors) >= $limit) break;
            }

            return response()->json(['offset' => $size, 'errors' => $errors]);
        } catch (\Throwable $e) {
            Log::channel('single')->error('ads.recent_errors.failed', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'log_error', 'message' => $e->getMessage()], 500);
        }
    }

    private function git(array $args): string
    {
        $process = new Process(array_merge(['git'], $args), base_path());
        $process->setTimeout(10);
        $process->mustRun();
        return trim($process->getOutput());
    }

    private function commitInfo(string $sha): array
    {
        $subject = $this->git(['log', '-1', '--pretty=format:%s', $sha]);
        $author  = $this->git(['log', '-1', '--pretty=format:%an', $sha]);
        $when    = $this->git(['log', '-1', '--pretty=format:%cI', $sha]);
        $files   = array_filter(array_map('trim', explode("\n", $this->git([
            'diff-tree', '--no-commit-id', '--name-only', '-r', $sha,
        ]))));

        return [
            'sha'           => $sha,
            'subject'       => $subject,
            'author'        => $author,
            'committed_at'  => $when,
            'files'         => array_slice($files, 0, 30),
        ];
    }
}
