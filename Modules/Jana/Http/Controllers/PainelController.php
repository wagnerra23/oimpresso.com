<?php

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Painel — Cockpit do Analista IA (Jana V2).
 *
 * Visual canon Cowork: prototipo-ui/cowork-snapshot/chat-jana.jsx (491 ln IIFE
 * expondo window.JanaCockpit). Renderiza dashboard executivo com:
 *  - Brief diário (texto narrativo gerado por BriefDiarioAgent — Onda C)
 *  - 4 KPI cards (Receita mês, A receber vencido, Ticket médio, Frota utilização)
 *  - 6 ANÁLISES PRINCIPAIS (Inadimplência, Faturamento, Concentração, Churn, Frota, Cheques)
 *  - AÇÕES sugeridas IA (régua WhatsApp, reativação, outbound, cleanup)
 *
 * Tier 0 multi-tenant: business_id via session — ADR 0093 IRREVOGÁVEL.
 *
 * Estratégia ondas:
 *  Onda A1 (esta): esqueleto + dados mock estáticos (skeleton states)
 *  Onda A2: sub-components KPI/AnaliseCard/Sparkline/Donut
 *  Onda A3: BriefDiario + AcaoRow + RichSpan
 *  Onda A4: CSS canon copy de chat-jana.css
 *  Onda B:  queries SQL reais (Inertia::defer) substituindo mock
 *  Onda C:  BriefDiarioAgent integrado (LLM-generated narrative)
 *  Onda D:  smoke prod + handoff
 *
 * Refs: chat-jana.jsx · ADR 0093 · ADR 0114 · cycle CYCLE-06 goal #4 (Jana V2 demo)
 */
class PainelController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('user.business_id');
        $business   = $request->session()->get('business');

        // D6.a (Wave 14 governance v3) — Inertia::defer no payload pesado do
        // painel. Render inicial só carrega `business` (lightweight). O closure
        // `painel` roda numa segunda requisição partial-reload, mantendo TTFB
        // baixo e permitindo skeleton no frontend (charter Painel.charter.md).
        // Referência: memory/requisitos/_DesignSystem/RUNBOOK-inertia-defer-pattern.md
        return Inertia::render('Jana/Painel', [
            'business' => [
                'id'      => $businessId,
                'name'    => $business['name'] ?? 'OIMPRESSO',
                'version' => 'v1404 legacy migrado',
            ],
            // Wagner 2026-05-25 HOTFIX: removido Inertia::defer. Painel.tsx
            // lê `painel.person` direto sem wrap <Deferred> — TypeError em
            // prod (smoke browser MCP). Mesmo padrão PR #1550/#1552.
            'painel' => $this->buildMockPayload(),
        ]);
    }

    /**
     * Mock payload da Onda A1 — replica `getJanaData()` do chat-jana.jsx.
     * Onda B substitui por Services Jana que rodam queries SQL reais.
     */
    private function buildMockPayload(): array
    {
        return [
            'person'    => ['name' => 'Jana', 'role' => 'Analista IA', 'avatar' => '🤖'],
            'updatedAt' => now()->format('H:i'),
            'today'     => now()->locale('pt_BR')->isoFormat('DD/MMMM/YYYY'),
            'brief' => [
                'greeting'   => 'Bom dia, Wagner.',
                'paragraphs' => [
                    'Brief diário será gerado pela IA quando integração Onda C estiver pronta.',
                    'Por enquanto, dados mock pra validar estrutura visual.',
                ],
                'chips' => [
                    ['tone' => 'primary', 'icon' => '📨', 'label' => 'Disparar régua 8 clientes'],
                    ['tone' => 'ghost',   'icon' => '📋', 'label' => 'Ver top 20 devedores'],
                    ['tone' => 'ghost',   'icon' => '🔍', 'label' => 'Investigar queda ticket médio'],
                    ['tone' => 'ghost',   'icon' => '💡', 'label' => 'Por que -68% MoM?'],
                ],
            ],
            'kpis' => [
                ['label' => 'Receita mês',       'value' => 'R$ 47k',  'delta' => '↓ -68% vs mai/25',     'deltaCls' => 'down', 'icon' => '💰'],
                ['label' => 'A receber vencido', 'value' => 'R$ 4,5M', 'deltaCls' => 'red big',           'icon' => '🚨', 'sub' => '4.255 títulos · 76% inadimplência', 'emphasize' => true],
                ['label' => 'Ticket médio',      'value' => 'R$ 1.890','delta' => '↓ -22% 4m',            'deltaCls' => 'down', 'icon' => '📈'],
                ['label' => 'Frota utilização',  'value' => '33%',     'deltaCls' => 'info',              'icon' => '🚚', 'sub' => '30/91 · 8 paradas >7d'],
            ],
            'analises' => [
                ['id' => 'inad',  'title' => 'Inadimplência',     'sub' => 'Top 20 devedores',     'pill' => ['tone' => 'crit',  'label' => 'CRÍTICO'],  'icon' => '🚨', 'kind' => 'buckets',   'big' => ['value' => 'R$ 4.535.636', 'color' => 'danger']],
                ['id' => 'fat',   'title' => 'Faturamento',       'sub' => 'Curva 24 meses',       'pill' => ['tone' => 'warn',  'label' => 'QUEDA'],    'icon' => '📈', 'kind' => 'sparkline', 'big' => ['value' => 'R$ 107M', 'color' => 'ok']],
                ['id' => 'conc',  'title' => 'Concentração',      'sub' => 'Top clientes Pareto',  'pill' => ['tone' => 'ok',    'label' => 'OK'],       'icon' => '🎯', 'kind' => 'bars',      'big' => ['value' => '8.856 clientes']],
                ['id' => 'churn', 'title' => 'Churn ouro',        'sub' => 'LTV alto inativos',    'pill' => ['tone' => 'react', 'label' => 'REATIVAR'], 'icon' => '⏰', 'kind' => 'list',      'big' => ['value' => '8 clientes']],
                ['id' => 'frota', 'title' => 'Frota',             'sub' => '91 caçambas avulsas',  'pill' => ['tone' => 'warn',  'label' => 'PARADAS'],  'icon' => '🚛', 'kind' => 'donut'],
                ['id' => 'cheq',  'title' => 'Cheques previsão',  'sub' => 'Na mão / a depositar', 'icon' => '🧾', 'kind' => 'text',      'big' => ['value' => '4.421 cheques']],
            ],
            'acoes' => [
                ['id' => 'a1', 'icon' => '📨', 'tone' => 'rose',   'title' => 'Régua WhatsApp · 8 clientes >90d sem contato', 'sub' => 'Potencial recuperação: R$ 287k · HITL aprovação cada msg',  'cta' => ['label' => 'Disparar', 'tone' => 'danger']],
                ['id' => 'a2', 'icon' => '❤️', 'tone' => 'violet', 'title' => 'Reativação · 8 clientes "ouro" inativos',     'sub' => 'LTV combinado R$ 612k · oferta retorno personalizada',     'cta' => ['label' => 'Preparar', 'tone' => 'violet']],
                ['id' => 'a3', 'icon' => '🚛', 'tone' => 'peach',  'title' => 'Outbound · 8 caçambas paradas há >7d',         'sub' => 'Top 3 últimos clientes mesma região · ligar HOJE',         'cta' => ['label' => 'Listar',   'tone' => 'orange']],
                ['id' => 'a4', 'icon' => '🗑️', 'tone' => 'grey',   'title' => 'Cleanup · 2.470 títulos write-off candidatos', 'sub' => 'R$ 770k incobráveis >365d · liberar dashboard',           'cta' => ['label' => 'Revisar',  'tone' => 'dark']],
            ],
        ];
    }
}
