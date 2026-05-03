<?php

namespace Modules\ADS\Services;

use Modules\ADS\Contracts\Tool;
use Modules\ADS\Tools\GitInspectTool;
use Modules\ADS\Tools\LogReaderTool;
use Modules\ADS\Tools\MetricsQueryTool;

/**
 * T12 — Catálogo de Tools registradas.
 *
 * Tools são singletons (sem estado). Registry expõe lista pra UI e métodos
 * de discovery pra agentes (futuramente: Brain B chama `getToolsForContext()`
 * e recebe só tools relevantes pro event_type atual).
 */
class ToolRegistry
{
    /** @var array<string, Tool> */
    private array $tools = [];

    public function __construct()
    {
        $this->register(new LogReaderTool());
        $this->register(new MetricsQueryTool());
        $this->register(new GitInspectTool());
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
    public function byCategory(string $category): array
    {
        return array_filter($this->all(), fn (Tool $t) => $t->category() === $category);
    }

    /**
     * Schema Anthropic-compatible pra todas as tools (passar pro Brain B).
     */
    public function schemasForLlm(): array
    {
        return array_map(fn (Tool $t) => [
            'name'         => $t->name(),
            'description'  => $t->description(),
            'input_schema' => $t->inputSchema(),
        ], $this->all());
    }

    /**
     * Executa uma tool por nome. Wrapper centralizado pra logging/audit.
     */
    public function execute(string $name, array $input): array
    {
        $tool = $this->get($name);
        if (! $tool) {
            return ['ok' => false, 'output' => null, 'error' => "tool_not_found: {$name}"];
        }

        try {
            return $tool->execute($input);
        } catch (\Throwable $e) {
            return ['ok' => false, 'output' => null, 'error' => 'tool_exception: ' . $e->getMessage()];
        }
    }
}
