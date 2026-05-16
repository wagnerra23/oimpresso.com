<?php

namespace Modules\ADS\Services;

use Modules\ADS\Contracts\Tool;
use Modules\ADS\Tools\BoostToolAdapter;
use Modules\ADS\Tools\GitCommitWipTool;
use Modules\ADS\Tools\GitInspectTool;
use Modules\ADS\Tools\LogReaderTool;
use Modules\ADS\Tools\MetricsQueryTool;
use Modules\ADS\Tools\RunTestTool;
use Modules\ADS\Tools\WriteFileTool;

/**
 * Observabilidade D9.a (ADR 0155): registry lookup hashmap; Tracer via
 * `OtelHelper::span(` reside em cada Tool concreta executada.
 *
 * Catálogo de Tools registradas (Anthropic tool use compatible).
 *
 * Estrutura por categoria:
 *   - 'leitura (Laravel Boost)' — 8 tools nativas Boost (preferência Wagner)
 *   - 'leitura'                 — 2 tools customizadas (GitInspect, MetricsQuery — Boost não cobre direto)
 *   - 'análise'                 — (legado) MetricsQueryTool
 *   - 'escrita'                 — 3 tools customizadas (Write/RunTest/GitWip)
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct()
    {
        // ─── Tools nativas Laravel Boost (preferência Wagner) ───
        foreach (BoostToolAdapter::listKeys() as $key) {
            $this->register(new BoostToolAdapter($key));
        }

        // ─── Tools customizadas read-only que Boost NÃO cobre ───
        $this->register(new GitInspectTool());     // Boost não tem git inspect
        $this->register(new MetricsQueryTool());   // Boost db-query é mais raw; nossa é específica ADS

        // LogReaderTool removido — Boost::read-log-entries cobre

        // ─── Tools de ESCRITA (custom, exigem aprovação Wagner) ───
        $this->register(new WriteFileTool());
        $this->register(new RunTestTool());
        $this->register(new GitCommitWipTool());
    }

    public function register(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }

    /** @return Tool[] */
    public function all(): array
    {
        return array_values($this->tools);
    }

    /** @return Tool[] */
    public function readOnly(): array
    {
        return array_filter($this->all(), fn (Tool $t) => $t->isReadOnly());
    }

    /** @return Tool[] */
    public function writeOnly(): array
    {
        return array_filter($this->all(), fn (Tool $t) => ! $t->isReadOnly());
    }

    /** @return Tool[] */
    public function byCategory(string $category): array
    {
        return array_filter($this->all(), fn (Tool $t) => $t->category() === $category);
    }

    /**
     * Schema Anthropic-compatible pra todas (ou só safe) tools.
     */
    public function schemasForLlm(bool $readOnlyOnly = true): array
    {
        $tools = $readOnlyOnly ? $this->readOnly() : $this->all();
        return array_map(fn (Tool $t) => [
            'name'         => $t->name(),
            'description'  => $t->description(),
            'input_schema' => $t->inputSchema(),
        ], $tools);
    }

    /**
     * Executa uma tool por nome com audit log.
     */
    public function execute(string $name, array $input): array
    {
        $tool = $this->get($name);
        if (! $tool) {
            return ['ok' => false, 'output' => null, 'error' => "tool_not_found: {$name}"];
        }

        try {
            $started = microtime(true);
            $result = $tool->execute($input);
            $duration = (int) ((microtime(true) - $started) * 1000);

            // Audit log
            \Illuminate\Support\Facades\Log::channel('single')->info('ads.tool.executed', [
                'tool_name'  => $name,
                'is_read_only' => $tool->isReadOnly(),
                'ok'         => $result['ok'] ?? false,
                'error'      => $result['error'] ?? null,
                'duration_ms' => $duration,
            ]);

            return $result;
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => null, 'error' => 'tool_exception: ' . $e->getMessage()];
        }
    }
}
