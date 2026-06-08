<?php

declare(strict_types=1);

namespace Modules\Jana\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * JANA Pro — paywall / upgrade cliente-facing (ADR 0140).
 *
 * Renderiza a tela de ativação do plano Pro (`/ia/pro`). É a tradução F3
 * (Cowork → Inertia/React) do design aprovado `Jana Pro - Paywall CC.html`
 * (gate F1.5 PASS 90 — `prototipos/jana-pro/critique-score.json`).
 *
 * Distinto de `Admin\JanaProController` (preview JSON do brief, superadmin).
 * Este é o upsell que QUALQUER usuário autenticado do business vê — auth já
 * garantida pelo grupo de rotas `/ia` (web + auth + SetSessionData + ...).
 *
 * Multi-tenant Tier 0 (ADR 0093): businessId sempre da sessão, nunca de input.
 *
 * Escopo desta entrega (F3 design): tela + dados representativos. O billing
 * real (assinatura Asaas, lifecycle trial→pago) é Sprint JANA-B do roadmap
 * (ADR 0140 §Roadmap, US-COPI-211/212). A CTA "Ativar" hoje fecha o loop de
 * feedback no client (mock), como no protótipo aprovado.
 *
 * @see memory/decisions/0140-jana-pro-produto-comercial-saas.md
 * @see Modules/Jana/Services/BriefDiarioService.php (fonte real do card de prova — Onda B)
 */
class ProController extends Controller
{
    public function index(Request $request): Response
    {
        $businessId = $request->session()->get('user.business_id');
        $business   = $request->session()->get('business');

        return Inertia::render('Jana/Pro', [
            // Plano atual do business. Sprint B (ADR 0140) deriva de assinatura
            // Asaas real; hoje a tela é paywall → assume 'free' (estado mock A1,
            // mesmo padrão de PainelController/Dashboard que shipam mock + comentário).
            'plan' => 'free',

            'pricing' => [
                'monthly'   => 49,   // R$/mês por empresa (ADR 0140 tier Pro entry)
                'trialDays' => 14,
            ],

            // Card de prova ("a Jana lendo seu ERP") — 3 ângulos de faturamento.
            // Valores representativos do protótipo aprovado. Onda B substitui por
            // BriefDiarioService::snapshot() (faturamento real do business, mês corrente)
            // — mesma estratégia de ondas do PainelController (mock A1 → query real B).
            'proof' => [
                'bruto'   => 84320,
                'liquido' => 79110,
                'caixa'   => 71480,
            ],

            'business' => [
                'id'   => $businessId,
                'name' => $business['name'] ?? 'Sua empresa',
            ],
        ]);
    }
}
