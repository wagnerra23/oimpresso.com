<?php

declare(strict_types=1);

namespace App\Services\Evolution\Tools;

/**
 * Contrato comum pra tools que o agent invoca. Compatível com a forma como
 * Vizra ADK exporta tools (`__invoke` puro) — quando Vizra publicar L13 support,
 * esses arquivos passam a estender VizraADK\Tool sem refactor de chamadas.
 */
interface Tool
{
    public function name(): string;

    public function description(): string;

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>|string
     */
    public function __invoke(array $args = []);
}
