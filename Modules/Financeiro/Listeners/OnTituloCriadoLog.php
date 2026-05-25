<?php

declare(strict_types=1);

namespace Modules\Financeiro\Listeners;

use Illuminate\Support\Facades\Log;
use Modules\Financeiro\Events\TituloCriado;

/**
 * Listener canônico do evento TituloCriado — log INFO com payload mínimo
 * (compliance LGPD: sem valor real nem nome contraparte, só métricas).
 *
 * PR F (2026-05-25) — G9 da auditoria. Listener "stub didático" pra:
 *   (1) provar que o event funciona end-to-end (Pest valida);
 *   (2) abrir caminho pra próximos listeners (notificar, cache, accounting);
 *   (3) deixar audit trail no `laravel.log` que permite contar volume diário
 *       de inserts manuais vs auto-criação observers (analytics gratuito).
 *
 * Multi-tenant Tier 0 (ADR 0093): biz_id no payload pra filtrar logs por tenant.
 */
final class OnTituloCriadoLog
{
    public function handle(TituloCriado $event): void
    {
        $titulo = $event->titulo;

        Log::info('financeiro.titulo.criado', [
            'business_id' => $titulo->business_id,
            'titulo_id'   => $titulo->id,
            'numero'      => $titulo->numero,
            'tipo'        => $titulo->tipo,
            'origem'      => $titulo->origem,
            'tem_plano'   => $titulo->plano_conta_id !== null,
            'tem_categoria' => $titulo->categoria_id !== null,
            'created_by'  => $titulo->created_by,
        ]);
    }
}
