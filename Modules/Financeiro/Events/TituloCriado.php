<?php

declare(strict_types=1);

namespace Modules\Financeiro\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Financeiro\Models\Titulo;

/**
 * Event disparado quando um Titulo financeiro é criado manualmente via
 * UnificadoController::store (Onda 25 — US-FIN-021).
 *
 * PR F (2026-05-25) — G9 da auditoria. Abre caminho de extensão pra:
 *   - NotificarFornecedorJob (e-mail/WhatsApp aviso boleto disponível)
 *   - RecalcularCacheKPIsJob (invalida cache business_id:caixa_projetado)
 *   - SincronizarAccountingJob (futuro — quando módulo Accounting reativar)
 *
 * NÃO disparado por:
 *   - TituloAutoService::sincronizarDeTransacao (auto-criação via venda/compra)
 *     → usa `Modules\Financeiro\Events\TituloSincronizado` (futuro, US separada)
 *   - BackfillPlanoContaCommand (mutação batch retrospectiva — silenciosa)
 *
 * Multi-tenant Tier 0 (ADR 0093): Titulo vem com business_id próprio.
 * Listener herda contexto.
 */
final class TituloCriado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Titulo $titulo,
    ) {}
}
