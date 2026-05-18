<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers\Concerns;

use Illuminate\Http\Response;

/**
 * Trait — serve HTML mock canon Cowork em vez de Inertia/Blade real
 * quando `config('financeiro.mock_cowork_mode')` é true.
 *
 * Wagner regra 2026-05-18: "coloque em produção ele por favor. na integra
 * todo financeiro". Cherry-pick + adaptar Inertia falhou 4× (PR #1085 →
 * #1091 → #1092 → #1094 → #1095). Adaptação errou o dia inteiro.
 *
 * Solução: cada controller método index() chama tryRenderMockCowork()
 * no topo. Se mock_cowork_mode true → response() com conteúdo HTML mock
 * + injeção `<base href="/cowork-preview/">` pra paths relativos resolverem
 * pros assets corretos (styles.css, financeiro-app.jsx, etc).
 *
 * Tier 0 multi-tenant preservado:
 *   - Middleware auth + Spatie permissions continuam no __construct
 *   - Só usuário logado com permission acessa o mock
 *   - POST de baixa/edit continuam funcionando (não chamam este trait)
 *   - business_id session preservado (mock não toca DB)
 *
 * Reversível: setar FINANCEIRO_MOCK_COWORK=false no .env volta pra
 * comportamento Inertia normal.
 */
trait RendersMockCowork
{
    /**
     * Se `financeiro.mock_cowork_mode` true, retorna Response com o HTML
     * mock canon mapeado pela rota atual, com `<base href>` injetado pros
     * assets relativos resolverem corretamente.
     *
     * Uso típico no controller:
     *
     *   public function index(Request $request): Response
     *   {
     *       if ($mock = $this->tryRenderMockCowork()) {
     *           return $mock;
     *       }
     *       // ... código Inertia normal ...
     *   }
     *
     * @return Response|null  null = mock desligado, segue Inertia
     */
    protected function tryRenderMockCowork(): ?Response
    {
        if (! config('financeiro.mock_cowork_mode')) {
            return null;
        }

        $routeName = optional(request()->route())->getName() ?? '';
        $mapping = (array) config('financeiro.mock_route_map', []);

        // Cada entry agora é array ['html' => ..., 'cowork_route' => ...].
        // Back-compat: aceita string simples (cai em cowork_route='financeiro').
        $entry = $mapping[$routeName] ?? ['html' => 'Oimpresso ERP - Chat.html', 'cowork_route' => 'financeiro'];
        if (is_string($entry)) {
            $entry = ['html' => $entry, 'cowork_route' => 'financeiro'];
        }

        $htmlFile = $entry['html'] ?? 'Oimpresso ERP - Chat.html';
        $coworkRoute = $entry['cowork_route'] ?? 'financeiro';
        $path = public_path('cowork-preview/' . $htmlFile);

        if (! file_exists($path)) {
            return null; // fallback pra Inertia real
        }

        $html = (string) file_get_contents($path);

        // Injeção 1: <base href> faz paths relativos (styles.css,
        // financeiro-app.jsx, etc) resolverem pra /cowork-preview/<file>
        // em vez de /financeiro/<file> (que dá 404).
        //
        // Injeção 2: <script> setando localStorage["oimpresso.route"] pra
        // que app.jsx Cowork abra direto na tela correta (financeiro,
        // fin-fluxo, fin-dre, boletos) em vez do default "chat".
        $coworkRouteEscaped = htmlspecialchars($coworkRoute, ENT_QUOTES);
        $injection = <<<HTML
<base href="/cowork-preview/" />
  <script>
    // Mock Cowork Mode (Wagner 2026-05-18): abre tela direto baseada na URL Laravel
    try {
      localStorage.setItem('oimpresso.route', '{$coworkRouteEscaped}');
    } catch (e) {}
  </script>
HTML;

        if (str_contains($html, '<head>')) {
            $html = preg_replace(
                '/<head>/i',
                "<head>\n  {$injection}",
                $html,
                1
            );
        } elseif (str_contains($html, '<head ')) {
            $html = preg_replace(
                '/<head([^>]*)>/i',
                "<head$1>\n  {$injection}",
                $html,
                1
            );
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Mock-Cowork' => '1',
            'X-Mock-Source' => $htmlFile,
            'X-Mock-Route' => $coworkRoute,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
