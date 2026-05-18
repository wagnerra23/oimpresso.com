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

        $htmlFile = $mapping[$routeName] ?? 'Financeiro Unificado.html';
        $path = public_path('cowork-preview/' . $htmlFile);

        if (! file_exists($path)) {
            return null; // fallback pra Inertia real
        }

        $html = (string) file_get_contents($path);

        // Injeção CRÍTICA: <base href> faz paths relativos (styles.css,
        // financeiro-app.jsx, etc) resolverem pra /cowork-preview/<file>
        // em vez de /financeiro/<file> (que dá 404). Sem isso, mock NÃO
        // carrega assets quando servido fora de /cowork-preview/.
        $baseTag = '<base href="/cowork-preview/" />';
        if (str_contains($html, '<head>')) {
            $html = preg_replace(
                '/<head>/i',
                "<head>\n  {$baseTag}",
                $html,
                1
            );
        } elseif (str_contains($html, '<head ')) {
            $html = preg_replace(
                '/<head([^>]*)>/i',
                "<head$1>\n  {$baseTag}",
                $html,
                1
            );
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Mock-Cowork' => '1',
            'X-Mock-Source' => $htmlFile,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
