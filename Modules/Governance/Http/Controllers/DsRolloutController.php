<?php

declare(strict_types=1);

namespace Modules\Governance\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DS Rollout — plano de portar o Design System inteiro em ondas medíveis + o
 * Ledger de Conformidade que prova "tudo aplicado" mecanicamente.
 *
 * Tradução F3 (PROTOCOL §2) do protótipo Cowork `DS Rollout - Ondas e Testes.html`
 * (handoff claude.ai/design, sessão 2026-06-12). O protótipo respondeu à pergunta
 * de Wagner "quantas ondas pra portar o DS? com teste pra ver se tudo foi aplicado".
 *
 * O conteúdo do PLANO (blocos A/B/C/D, medição @main, provas) é estático e vive no
 * componente `Pages/governance/DsRollout.tsx` — é o próprio design, não dado de runtime.
 *
 * O **Ledger** (a parte "viva") entra por prop `census`, lido de
 * `governance/ds-ledger.json` — o artefato carimbado (SHA + timestamp) que o
 * `scripts/ds-ledger.mjs` gera rodando os checks DS (eslint ds/*, paleta crua,
 * conformance-gate, components-tree-guard) por Page. Sem o artefato (checkout antes
 * do 1º censo) cai no snapshot estático ROTULADO `TODO ledger` — trava de governança:
 * a tela só mostra número que veio de gate rodando, nunca palavra.
 *
 * Render estático (lê 1 JSON local, zero query) → eager, sem Inertia::defer (mesmo
 * espírito do compliance_pct do DashboardController).
 *
 * Refs: ADR 0209 (ratchet), 0235/0190 (roxo canônico), 0239 (gov DS git=SSOT),
 *       0240 (evidência fecha task), PROTOCOL §2 (F3 tradução).
 */
class DsRolloutController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(): Response
    {
        return Inertia::render('governance/DsRollout', [
            'census' => $this->loadCensus(),
        ]);
    }

    /**
     * Carrega o Ledger REAL (`governance/ds-ledger.json`, gerado por
     * `npm run ds:ledger -- --write`) — censo carimbado com SHA + timestamp. Se o
     * artefato não existir (checkout fresco, antes do 1º censo), cai no snapshot
     * estático ROTULADO `TODO ledger`, honrando a trava de governança: a tela só
     * mostra número que veio de gate rodando.
     *
     * @return array<string, mixed>
     */
    private function loadCensus(): array
    {
        $path = base_path('governance/ds-ledger.json');

        if (is_file($path)) {
            $json = json_decode((string) file_get_contents($path), true);
            if (is_array($json) && isset($json['ledger']) && is_array($json['ledger'])) {
                return $json;
            }
        }

        return $this->staticFallback();
    }

    /**
     * Snapshot estático (não-medido) — só aparece quando o censo ainda não rodou.
     * Marcado `measured=false` + label `TODO ledger` pra nunca passar por número real.
     *
     * @return array<string, mixed>
     */
    private function staticFallback(): array
    {
        $todo = ['tokens' => 'na', 'primitivos' => 'na', 'probe' => 'na', 'dark' => 'na', 'approved' => 'na'];

        return [
            'ledger' => [
                [
                    'screen' => 'Atendimento / Caixa',
                    'note' => 'o ouro · referência',
                    'reference' => true,
                    'cells' => ['tokens' => 'ref', 'primitivos' => 'ref', 'probe' => 'na', 'dark' => 'na', 'approved' => 'yes'],
                ],
                ['screen' => 'Rode `npm run ds:ledger -- --write`', 'note' => 'censo ainda não rodou', 'cells' => $todo],
            ],
            'progressPct' => 0,
            'progressLabel' => 'snapshot estático · TODO ledger',
            'measured' => false,
            'measuredAgainstSha' => null,
            'generatedAt' => null,
            'treeGuard' => null,
            'counts' => ['screens' => 0, 'done' => 0, 'references' => 1],
        ];
    }
}
