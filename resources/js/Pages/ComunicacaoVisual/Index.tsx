/**
 * Pages/ComunicacaoVisual/Index — landing do vertical gráfica rápida BR.
 *
 * STATUS: stub Sprint 2 — UI Inertia ainda em construção (BRIEFING.md §3).
 *  - Sprint 1 entregou: API JSON (OrcamentoController + ApontamentoController)
 *  - Sprint 2 TODO: pages Inertia próprias (orçamento, PCP Kanban, apontamento)
 *
 * Wave 25 — placeholder com charter ao lado (.charter.md) pra MWART F1.5 gate
 * visual quando UI for ativada (ROTA LIVRE ainda usa endpoint legado).
 *
 * @see Modules/ComunicacaoVisual/README.md §3 "Como o cliente usa"
 * @see resources/js/Pages/ComunicacaoVisual/Index.charter.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-005
 */
import React from 'react';
import { Head } from '@inertiajs/react';

interface Props {
    bizName?: string;
}

export default function Index({ bizName = 'oimpresso' }: Props) {
    return (
        <>
            <Head title="Comunicação Visual — ComVis" />
            <div className="p-6 space-y-4">
                <h1 className="text-2xl font-semibold">Comunicação Visual</h1>
                <p className="text-sm text-zinc-600">
                    Vertical gráfica rápida ({bizName}) — orçamento por m² · PCP gráfico · apontamento.
                </p>
                <p className="text-xs text-amber-700">
                    UI em construção (Sprint 2). Use endpoints API legados em /comunicacao-visual/api/*.
                </p>
            </div>
        </>
    );
}
