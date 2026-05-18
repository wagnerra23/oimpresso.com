<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers\Concerns;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Trait — serve HTML mock canon Cowork em vez de Inertia/Blade real
 * quando `config('financeiro.mock_cowork_mode')` é true.
 *
 * Wagner regra 2026-05-18: "coloque em produção ele por favor. na integra
 * todo financeiro". Cherry-pick + adaptar Inertia falhou 4× (PR #1085 →
 * #1091 → #1092 → #1094 → #1095). Adaptação errou o dia inteiro.
 *
 * Solução: cada controller método index() chama tryRenderMockCowork()
 * no topo. Se mock_cowork_mode true → response()->file() do HTML mock
 * literal em public/cowork-preview/<tela>.html (servido com mime
 * text/html pelo Laravel/Apache, JSX carrega via Babel CDN).
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
     * Se `financeiro.mock_cowork_mode` true, retorna BinaryFileResponse com
     * o HTML mock canon mapeado pela rota atual.
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
     * @return BinaryFileResponse|null  null = mock desligado, segue Inertia
     */
    protected function tryRenderMockCowork(): ?BinaryFileResponse
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

        return response()->file($path, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Mock-Cowork' => '1',
            'X-Mock-Source' => $htmlFile,
        ]);
    }
}
