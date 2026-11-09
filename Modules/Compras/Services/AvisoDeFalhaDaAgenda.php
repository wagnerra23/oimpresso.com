<?php

declare(strict_types=1);

namespace Modules\Compras\Services;

use Illuminate\Support\Facades\Log;
use Modules\Jana\Services\TaskRegistry\HitlEscalationService;

/**
 * Falha da agenda ou do job de Compras vira UMA task `blocked`/`wagner` no `mcp_tasks`, que o
 * brief lê (D6 da auditoria de Compras, 2026-11-05).
 *
 * Por que não `onFailure(Log)`: medido em produção em 2026-08-08, o log tem milhares de linhas
 * "Schedule ... FALHOU" que ninguém lê (proibicoes.md §5 2026-08-08). O transporte é o
 * `HitlEscalationService` do Jana, com `task_id` determinístico: a mesma falha amanhã atualiza a
 * mesma task, e task fechada pelo humano não reabre.
 *
 * Nunca derruba quem chama: sem o Jana ou sem `mcp_tasks`, fica só o log.
 */
final class AvisoDeFalhaDaAgenda
{
    public function avisar(string $chave, string $titulo, string $descricao, string $origem): void
    {
        Log::channel('single')->error('compras.agenda.falhou', ['chave' => $chave, 'origem' => $origem]);

        if (! class_exists(HitlEscalationService::class)) {
            return;
        }

        try {
            app(HitlEscalationService::class)->escalar(
                chave: $chave,
                titulo: $titulo,
                descricao: $descricao,
                modulo: 'Compras',
                prioridade: 'p2',
                origem: $origem,
            );
        } catch (\Throwable $e) {
            Log::channel('single')->error('compras.agenda.aviso_nao_escalado', ['chave' => $chave, 'erro' => $e->getMessage()]);
        }
    }
}
