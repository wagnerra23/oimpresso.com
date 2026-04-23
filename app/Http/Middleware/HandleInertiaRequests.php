<?php

namespace App\Http\Middleware;

use App\Services\ShellMenuBuilder;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Blade view carregado como root de respostas Inertia.
     */
    protected $rootView = 'layouts.inertia';

    /**
     * Versão de assets — usada pelo Inertia para invalidar cache do cliente
     * quando o bundle muda.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props compartilhadas em toda resposta Inertia.
     *
     * Regras críticas UltimatePOS:
     *  - business_id é lido da SESSÃO (nunca do cliente)
     *  - nada aqui deve vazar dados de outro tenant
     *  - permissões são avaliadas no servidor (can()) — cliente apenas recebe booleanos
     */
    public function share(Request $request): array
    {
        $session = $request->session();
        $user = $request->user();

        $businessId = $session->get('user.business_id');

        $businessPayload = null;
        if ($businessId) {
            $businessRaw = $session->get('business');
            $businessPayload = [
                'id'                 => (int) $businessId,
                'name'               => $businessRaw['name']               ?? $session->get('business.name'),
                'currency_symbol'    => $businessRaw['currency_symbol']    ?? $session->get('business.currency_symbol'),
                'default_sales_tax'  => $businessRaw['default_sales_tax']  ?? null,
            ];
        }

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'                    => $user->id,
                    'name'                  => $user->first_name . ' ' . ($user->last_name ?? ''),
                    'email'                 => $user->email,
                    'business_id'           => (int) $businessId,
                    'is_admin'              => (bool) $session->get('is_admin', false),
                    'ui_theme'              => $user->ui_theme,               // 'light'|'dark'|null
                    'ui_sidebar_collapsed'  => (bool) ($user->ui_sidebar_collapsed ?? false),
                ] : null,
                'can' => $user ? $this->userPermissions($user) : [],
            ],
            'business' => $businessPayload,
            'ai' => [
                'enabled'                      => (bool) env('AI_ENABLED', false),
                'classificacao_intercorrencia' => (bool) env('AI_CLASSIFICACAO_INTERCORRENCIA', false),
                'explicacao_divergencia'       => (bool) env('AI_EXPLICACAO_DIVERGENCIA', false),
                'geracao_justificativa'        => (bool) env('AI_GERACAO_JUSTIFICATIVA', false),
            ],
            'flash' => [
                'success' => fn () => $session->get('status.success') ?? $session->get('success'),
                'error'   => fn () => $session->get('status.error')   ?? $session->get('error'),
                'info'    => fn () => $session->get('status.info')    ?? $session->get('info'),
            ],
            'shell' => [
                // Menu lazy: só computa quando a página precisa
                'menu'    => fn () => $user ? app(ShellMenuBuilder::class)->build($request) : [],
                // TopNavs declarativos por módulo (ADR arq/0009) — lazy também
                'topnavs' => fn () => $user ? app(ShellMenuBuilder::class)->buildTopNavs($request) : [],
            ],
            'locale'     => app()->getLocale(),
            'csrf_token' => fn () => csrf_token(),
        ]);
    }

    /**
     * Seleção curta de permissões compartilhadas com o cliente.
     *
     * Mantenha ENXUTO — evita vazar surface-area de permissões.
     * Acrescente chaves conforme telas Inertia forem criadas.
     */
    protected function userPermissions($user): array
    {
        $checks = [
            'ponto.access',
            'ponto.colaboradores.manage',
            'ponto.aprovacoes.manage',
            'ponto.relatorios.view',
            'ponto.configuracoes.manage',
        ];

        $can = [];
        foreach ($checks as $permission) {
            $can[$permission] = (bool) $user->can($permission);
        }

        return $can;
    }
}
