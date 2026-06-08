<?php

namespace Modules\ADS\Contracts;

/**
 * T12 — Tool interface canônica do ADS.
 *
 * Toda Tool é uma ação concreta que agentes podem invocar — diferente de
 * "instrução" (texto) que Brain B gera. Pattern: Anthropic tool use.
 *
 * Cada Tool declara:
 *   - name(): identificador estável (snake_case)
 *   - description(): pra LLM entender quando chamar
 *   - inputSchema(): JSON schema (Anthropic-compatible)
 *   - execute(): implementação real
 *
 * Tools são registradas em ToolRegistry e expostas em /ads/admin/tools.
 */
interface Tool
{
    /** Identificador único snake_case (ex: 'log_reader', 'metrics_query') */
    public function name(): string;

    /** Descrição em PT-BR de quando usar essa tool */
    public function description(): string;

    /** JSON Schema dos parâmetros aceitos */
    public function inputSchema(): array;

    /**
     * Executa a tool com parâmetros validados.
     *
     * @return array{ok:bool, output:mixed, error:?string}
     */
    public function execute(array $input): array;

    /**
     * Categoria para agrupamento na UI (ex: 'leitura', 'escrita', 'análise').
     */
    public function category(): string;

    /**
     * É segura pra chamar sem aprovação? Tools de leitura = true.
     * Tools que modificam estado = false (exigem Brain B + Wagner).
     */
    public function isReadOnly(): bool;
}
