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
     *
     * IMPORTANTE: o default do Inertia lê `public/mix-manifest.json` (legado
     * do UltimatePOS com Laravel Mix). Esse arquivo muda quando rodamos `npm
     * run production` do core, mesmo sem mexer no bundle Inertia — causando
     * version mismatch em toda navegação → 409 + full page reload.
     *
     * Solução: usar APENAS o manifest do nosso build Inertia
     * (`public/build-inertia/manifest.json`). Assim, a versão só muda quando
     * o bundle Inertia muda de fato.
     */
    public function version(Request $request): ?string
    {
        $inertiaManifest = public_path('build-inertia/manifest.json');
        if (file_exists($inertiaManifest)) {
            return md5_file($inertiaManifest);
        }
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
                // TopNavs por módulo (ADR arq/0011) — fonte independente da sidebar
                'topnavs' => fn () => $user ? app(ShellMenuBuilder::class)->buildTopNavs($request) : (object) [],
                // Cockpit shell props (ADR UI-0008): business + user formatados pra
                // AppShellV2 consumir direto via usePage().shell.cockpit. Lazy:
                // só computa quando a página tem layout Cockpit, não impacta páginas
                // que ainda usam AppShell legado.
                'cockpit' => fn () => $user ? $this->cockpitShellProps($request, $user, $businessId) : null,
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

    /**
     * Shell props pro AppShellV2 (Cockpit) — single source of truth pra
     * business/user/businesses-disponiveis. Lazy via Inertia closure: só roda
     * quando o cliente solicitar `shell.cockpit` (ou seja, quando a página
     * usa AppShellV2). Páginas no AppShell legado não pagam custo.
     */
    protected function cockpitShellProps(Request $request, $user, $businessId): array
    {
        $isSuper = $user && ($user->user_type === 'superadmin' || $user->user_type === 'user_oimpresso');

        // Businesses disponíveis pro CompanyPicker:
        // - Superadmin/admin oimpresso: TODAS (limit 50)
        // - Outros: apenas a current
        $businessesQuery = $isSuper
            ? \App\Business::orderBy('name')->limit(50)
            : \App\Business::where('id', $businessId);

        $businesses = $businessesQuery->get(['id', 'name'])->map(fn ($b) => [
            'id'       => $b->id,
            'nome'     => $b->name,
            'iniciais' => $this->iniciais($b->name),
            'ativa'    => $b->id === (int) $businessId,
        ])->values()->all();

        $userNome = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: ($user->username ?? 'Usuário');

        return [
            'businessNome'     => $request->session()->get('business.name', 'Oimpresso Matriz'),
            'businesses'       => $businesses,
            'usuarioNome'      => $userNome,
            'usuarioNomeCurto' => $user->first_name ?? 'Usuário',
            'usuarioEmail'     => $user->email ?? '',
            'usuarioCargo'     => $isSuper ? 'Administrador' : 'Usuário',
            'usuarioIniciais'  => $this->iniciais($userNome),
        ];
    }

    /**
     * Iniciais (até 2 letras) pra avatares: "Wagner Rocha" -> "WR".
     */
    protected function iniciais(string $nome): string
    {
        $partes = preg_split('/\s+/', trim($nome)) ?: [];
        $iniciais = '';
        foreach ($partes as $p) {
            if ($p === '') continue;
            $iniciais .= mb_strtoupper(mb_substr($p, 0, 1));
            if (mb_strlen($iniciais) >= 2) break;
        }
        return $iniciais ?: '?';
    }
}
