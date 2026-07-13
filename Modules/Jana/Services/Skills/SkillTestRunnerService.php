<?php

namespace Modules\Jana\Services\Skills;

use App\Util\OtelHelper;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Jana\Entities\Mcp\McpSkillTestRun;
use Modules\Jana\Entities\Mcp\McpSkillVersion;
use Modules\Jana\Services\Ai\LaravelAiSdkDriver;
use Modules\Jana\Services\Telemetry\LangfuseClient;

/**
 * ADR 0076 (Fase 3) — roda uma version de skill contra prompt do user.
 *
 * Fluxo:
 *  1. Recebe input (prompt) + skill version
 *  2. Aplica PII redactor (LaravelAiSdkDriver::mascararDocumentos)
 *  3. Chama Anthropic API com body markdown da skill como system prompt
 *  4. Salva em mcp_skill_test_runs
 *
 * Em modo dry_run (config copiloto.dry_run=true): retorna fixture sem chamar API.
 *
 * Resolve gap §4.4 do mercado: testar skill contra inputs reais com PII redactor
 * obrigatório. Nenhuma ferramenta de prompt management 2026 faz.
 */
class SkillTestRunnerService
{
    public function __construct(
        private LaravelAiSdkDriver $aiDriver,
    ) {}

    /**
     * @return McpSkillTestRun
     */
    public function run(McpSkillVersion $version, string $userPrompt, ?int $businessIdScope, ?int $userId): McpSkillTestRun
    {
        // D9.a (Wave 18 SATURATION) — span skill test run; biz scope opcional.
        return OtelHelper::span('jana.skill.test_run', [
            'business_id' => $businessIdScope,
            'user_id' => $userId,
            'skill_version_id' => $version->id,
            'prompt_chars' => strlen($userPrompt),
        ], fn () => $this->runInternal($version, $userPrompt, $businessIdScope, $userId));
    }

    private function runInternal(McpSkillVersion $version, string $userPrompt, ?int $businessIdScope, ?int $userId): McpSkillTestRun
    {
        $startedAt = microtime(true);

        $piiCount = 0;
        $redactedPrompt = $this->redact($userPrompt, $piiCount);

        $systemPrompt = (string) $version->body_markdown;

        $output = null;
        $outputTokens = null;
        $errorMessage = null;

        try {
            if (config('copiloto.dry_run', false)) {
                $output = $this->fixtureOutput($version, $redactedPrompt);
                $outputTokens = mb_strlen($output);
            } else {
                [$output, $outputTokens] = $this->callAnthropic($systemPrompt, $redactedPrompt);
            }
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            Log::channel('copiloto-ai')->error('SkillTestRunner error', [
                'version_id' => $version->id,
                'error' => $errorMessage,
            ]);
            $output = "ERRO ao chamar API: {$errorMessage}";
        }

        $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

        return McpSkillTestRun::create([
            'version_id'           => $version->id,
            'input_source'         => 'manual',
            'input_json'           => [
                'prompt'        => $redactedPrompt,
                'system_chars'  => mb_strlen($systemPrompt),
                'error_message' => $errorMessage,
            ],
            'output'               => $output,
            'output_tokens'        => $outputTokens,
            'latency_ms'           => $latencyMs,
            'business_id_scope'    => $businessIdScope,
            'pii_redactions_count' => $piiCount,
            'passed'               => null,
            'executed_by'          => $userId,
            'executed_at'          => now(),
        ]);
    }

    /**
     * Conta redactions adicionando ao $count por referência.
     */
    private function redact(string $text, int &$count): string
    {
        $original = $text;
        $masked = $this->aiDriver->mascararDocumentos($text);

        // Conta quantas substituições aconteceram comparando ocorrências
        $count = max(0,
            preg_match_all('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/', $original) +
            preg_match_all('/\b\d{2}\.?\d{3}\.?\d{3}\/?0001-?\d{2}\b/', $original)
        );

        return $masked;
    }

    private function fixtureOutput(McpSkillVersion $version, string $prompt): string
    {
        return "[DRY_RUN] Skill v{$version->version} processaria o prompt:\n\n"
            ."> {$prompt}\n\n"
            ."Body da skill tem ".mb_strlen($version->body_markdown)." chars. "
            ."Em prod (sem dry_run), Anthropic Claude responderia com base nesse body como system prompt.";
    }

    /**
     * @return array{0: string, 1: ?int} [output, output_tokens]
     */
    private function callAnthropic(string $systemPrompt, string $userPrompt): array
    {
        $apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY não configurado.');
        }

        $model = config('copiloto.skill_test_model', 'claude-haiku-4-5-20251001');

        $t0 = microtime(true);

        $response = Http::withHeaders([
            'x-api-key'         => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type'      => 'application/json',
        ])
            ->timeout(60)
            ->post('https://api.anthropic.com/v1/messages', [
                'model'       => $model,
                'max_tokens'  => 1024,
                'system'      => $systemPrompt,
                'messages'    => [
                    ['role' => 'user', 'content' => $userPrompt],
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Anthropic API ".$response->status().": ".$response->body());
        }

        $body = $response->json();
        $output = collect($body['content'] ?? [])
            ->where('type', 'text')
            ->pluck('text')
            ->join("\n");

        $outputTokens = $body['usage']['output_tokens'] ?? null;

        // US-COPI-108 (reconciliação 2026-07-12): call-site Http:: direto, fora do
        // listener global Langfuse — instrumentação inline. Prompt já passou pelo
        // PII redactor upstream (runInternal). No-op se langfuse.enabled=false.
        app(LangfuseClient::class)->traceComGeneration([
            'name'     => 'skill-test-run',
            'tool'     => 'skill-test-runner',
            'metadata' => ['system_chars' => strlen($systemPrompt)],
        ], [
            'name'        => 'skill-test-call',
            'model'       => (string) $model,
            'input'       => $userPrompt,
            'output'      => $output,
            'usage'       => [
                'input'  => $body['usage']['input_tokens'] ?? null,
                'output' => $outputTokens,
            ],
            'duration_ms' => (int) round((microtime(true) - $t0) * 1000),
        ]);

        return [$output, $outputTokens];
    }
}
