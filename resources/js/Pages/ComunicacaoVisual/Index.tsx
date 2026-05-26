/**
 * Pages/ComunicacaoVisual/Index — hub stub do vertical gráfica rápida BR.
 *
 * STATUS: hub stub Sprint 2 — UI Inertia ainda em construção.
 *  - Sprint 1 entregou: API JSON (OrcamentoController + ApontamentoController)
 *  - Sprint 2 TODO: pages Inertia próprias (orçamento, PCP Kanban, apontamento)
 *
 * 2026-05-26 (Wagner reportou módulo quebrado): substituí stub puro por hub
 * lista 4 áreas como cards "em breve". Antes o sidebar dropdown apontava pra
 * URLs /comunicacao-visual/admin/* INEXISTENTES — todos cliques davam 404.
 * Agora o sidebar tem 1 entry top-level apontando pra esta Page que mostra
 * o estado real do módulo.
 *
 * @see Modules/ComunicacaoVisual/README.md §3 "Como o cliente usa"
 * @see resources/js/Pages/ComunicacaoVisual/Index.charter.md
 * @see memory/requisitos/ComunicacaoVisual/SPEC.md US-COMVIS-005
 */
import React from 'react';
import { Head } from '@inertiajs/react';
import { FileText, ClipboardList, Layers, Timer, ExternalLink } from 'lucide-react';

interface Props {
    bizName?: string;
}

const AREAS_EM_BREVE = [
    {
        key: 'orcamentos',
        label: 'Orçamentos',
        descricao: 'Cálculo por m² (banner, adesivo, lona) — frente de loja Larissa.',
        icon: FileText,
        api_hint: 'POST /comunicacao-visual/api/calcular',
        sprint: 'Sprint 1 ✅ API · Sprint 2 ⏳ UI',
    },
    {
        key: 'os',
        label: 'Ordens de Serviço',
        descricao: 'PCP Kanban (Aguardando · Em produção · Pronto) por OS.',
        icon: ClipboardList,
        api_hint: 'GET /comunicacao-visual/api/orcamentos/{id}',
        sprint: 'Sprint 2 ⏳ UI Kanban',
    },
    {
        key: 'materiais',
        label: 'Materiais',
        descricao: 'Cadastro de matéria-prima (m² · tributação · custo).',
        icon: Layers,
        api_hint: '(roadmap Sprint 3)',
        sprint: 'Sprint 3 ⏳ CRUD',
    },
    {
        key: 'apontamentos',
        label: 'Apontamentos',
        descricao: 'Spool de produção — iniciar/finalizar etapa por operador.',
        icon: Timer,
        api_hint: 'POST /comunicacao-visual/api/apontamentos/iniciar',
        sprint: 'Sprint 1 ✅ API · Sprint 2 ⏳ UI',
    },
];

export default function Index({ bizName = 'oimpresso' }: Props) {
    return (
        <>
            <Head title="Comunicação Visual — Hub" />
            <div className="p-6 max-w-5xl">
                <header className="mb-6">
                    <h1 className="text-2xl font-semibold text-zinc-900">Comunicação Visual</h1>
                    <p className="mt-1 text-sm text-zinc-600">
                        Vertical gráfica rápida ({bizName}) — orçamento por m² · PCP gráfico · apontamento.
                    </p>
                </header>

                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 mb-6">
                    <p className="text-sm text-amber-900">
                        <strong>UI Inertia em construção (Sprint 2).</strong> APIs JSON da Sprint 1 já estão
                        ativas em <code className="rounded bg-amber-100 px-1.5 py-0.5 text-xs">/comunicacao-visual/api/*</code>
                        {' '}— integráveis externamente. Telas próprias (Kanban, CRUD materiais, drawer de OS)
                        chegam quando cliente piloto CV ativar.
                    </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                    {AREAS_EM_BREVE.map((area) => {
                        const Icon = area.icon;
                        return (
                            <div
                                key={area.key}
                                className="rounded-lg border border-zinc-200 bg-white p-4 hover:border-zinc-300 transition-colors"
                            >
                                <div className="flex items-start gap-3">
                                    <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-zinc-100">
                                        <Icon size={18} className="text-zinc-700" />
                                    </div>
                                    <div className="flex-1 min-w-0">
                                        <h2 className="text-sm font-medium text-zinc-900">{area.label}</h2>
                                        <p className="mt-0.5 text-xs text-zinc-600">{area.descricao}</p>
                                        <div className="mt-2 flex items-center gap-2 text-[11px] text-zinc-500">
                                            <span className="inline-flex items-center gap-1 rounded bg-zinc-50 px-1.5 py-0.5 font-mono">
                                                <ExternalLink size={10} />
                                                {area.api_hint}
                                            </span>
                                        </div>
                                        <p className="mt-1.5 text-[11px] text-zinc-500">{area.sprint}</p>
                                    </div>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <footer className="mt-6 border-t border-zinc-100 pt-4 text-xs text-zinc-500">
                    Quando o cliente piloto CV ativar, este hub vira home com KPIs
                    (m² produzidos hoje, OS abertas, tempo médio etapa). Por enquanto,
                    use APIs JSON ou contate Wagner.
                </footer>
            </div>
        </>
    );
}
