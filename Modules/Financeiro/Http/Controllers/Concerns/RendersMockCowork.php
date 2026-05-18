<?php

declare(strict_types=1);

namespace Modules\Financeiro\Http\Controllers\Concerns;

use Illuminate\Http\Response;
use Modules\Financeiro\Services\CoworkDataMapper;

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
        // que app.jsx Cowork abra direto na tela correta.
        //
        // Injeção 3 (Integração #1 Wagner 2026-05-18): coleta dados REAIS
        // do business via CoworkDataMapper + injeta como window.__OIMPRESSO_FIN_REAL__
        // ANTES dos scripts babel. Outro script DEPOIS de financeiro-data.jsx
        // sobrescreve window.FIN_ROWS com os reais (mock continua como fallback).
        $coworkRouteEscaped = htmlspecialchars($coworkRoute, ENT_QUOTES);

        // Tier 0: business_id da session — sem business logado, mantém mock
        $businessId = (int) (session('user.business_id') ?? session('business.id') ?? 0);
        $realDataJson = 'null';
        if ($businessId > 0) {
            try {
                $real = CoworkDataMapper::collect($businessId);
                $realDataJson = json_encode($real, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
            } catch (\Throwable $e) {
                // Fallback gracioso pro mock; logamos mas não bloqueia
                logger()->warning('CoworkDataMapper failed, falling back to mock', [
                    'business_id' => $businessId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Integração #2: CSRF token meta tag pra bridge-posts.js fazer POST autenticado
        $csrfToken = csrf_token();
        $injectionHead = <<<HTML
<base href="/cowork-preview/" />
  <meta name="csrf-token" content="{$csrfToken}" />
  <script>
    // Mock Cowork Mode (Wagner 2026-05-18 + Integrações #1+#2):
    // - localStorage.route abre tela correta
    // - __OIMPRESSO_FIN_REAL__ carrega dados Eloquent do business logado
    // - meta csrf-token pra bridge-posts.js fazer POST baixa
    try {
      localStorage.setItem('oimpresso.route', '{$coworkRouteEscaped}');
    } catch (e) {}
    window.__OIMPRESSO_FIN_REAL__ = {$realDataJson};
  </script>
  <!-- Integração #2 oimpresso: bridge POSTs reais (Recebi/Paguei → /financeiro/unificado/{id}/baixar) -->
  <script src="/cowork-preview/_oimpresso-bridge-posts.js"></script>
HTML;

        if (str_contains($html, '<head>')) {
            $html = preg_replace(
                '/<head>/i',
                "<head>\n  {$injectionHead}",
                $html,
                1
            );
        } elseif (str_contains($html, '<head ')) {
            $html = preg_replace(
                '/<head([^>]*)>/i',
                "<head$1>\n  {$injectionHead}",
                $html,
                1
            );
        }

        // Injeção 4: script polling INLINE DEPOIS de financeiro-data.jsx que
        // sobrescreve window.FIN_ROWS / FIN_TODAY com dados reais (se disponível).
        // Polling porque Babel Standalone é assíncrono — financeiro-data.jsx
        // pode não ter rodado quando esse script começar.
        $overrideScript = <<<'HTML'
<script>
    // Integração #1 Wagner 2026-05-18 — substitui FIN_ROWS mock pelos dados Eloquent.
    // Fallback IMPORTANTE: se business logado tem 0 títulos no período, MANTÉM
    // mock template visual (caso contrário tela fica vazia e Wagner pensa que
    // mock quebrou). Só substitui quando há dados REAIS no business.
    (function pollOverride() {
      if (!window.FIN_ROWS) { setTimeout(pollOverride, 30); return; }
      var real = window.__OIMPRESSO_FIN_REAL__;
      if (!real || !Array.isArray(real.FIN_ROWS) || real.FIN_ROWS.length === 0) {
        console.log('[oimpresso] Mock Cowork: business sem títulos no período, mantém mock template (%d rows)', window.FIN_ROWS.length);
        return;
      }
      window.FIN_ROWS = real.FIN_ROWS.map(function (r) {
        return Object.assign({}, r, {
          due: r.due ? new Date(r.due + 'T12:00:00') : null,
          paid_at: r.paid_at ? new Date(r.paid_at + 'T12:00:00') : null,
        });
      });
      if (real.FIN_TODAY) {
        window.FIN_TODAY = new Date(real.FIN_TODAY + 'T12:00:00');
      }
      console.log('[oimpresso] Mock Cowork: dados reais injetados, %d rows', window.FIN_ROWS.length);
    })();
  </script>
HTML;
        // Insere DEPOIS de <script type="text/babel" src="financeiro-data.jsx"></script>
        $html = preg_replace(
            '/(<script\s+type="text\/babel"\s+src="financeiro-data\.jsx"><\/script>)/i',
            "$1\n  {$overrideScript}",
            $html,
            1
        );

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'X-Mock-Cowork' => '1',
            'X-Mock-Source' => $htmlFile,
            'X-Mock-Route' => $coworkRoute,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
