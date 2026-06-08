<?php

namespace Modules\Financeiro\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Financeiro\Models\Advisor;

/**
 * AdvisorAuthController — login/logout do guard `web-advisor` (Onda 31 #57).
 *
 * NÃO confunde com user UltimatePOS. Advisor não tem business_id próprio
 * (tabela `advisors` é global). Após login, advisor vê dashboard `/advisor`
 * com lista de clientes acessíveis via grants ativos.
 *
 * Stack rotas: middleware 'web' + 'guest:web-advisor' pra login; 'auth:web-advisor'
 * pra logout + dashboard.
 */
class AdvisorAuthController extends Controller
{
    /**
     * GET /advisor/login
     */
    public function showLogin(): InertiaResponse
    {
        return Inertia::render('Financeiro/Advisor/Login', [
            'breadcrumbs' => [
                ['label' => 'Portal Contador', 'href' => null],
                ['label' => 'Entrar', 'href' => null],
            ],
        ]);
    }

    /**
     * POST /advisor/login
     *
     * Throttle não-implementado no MVP — adicionar `RateLimiter::for('advisor-login', ...)`
     * em Onda 32 quando o portal ganhar audience real.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $advisor = Advisor::where('email', $credentials['email'])
            ->where('ativo', true)
            ->first();

        if (! $advisor || ! $advisor->password_hash || ! Hash::check($credentials['password'], $advisor->password_hash)) {
            Log::warning('Onda 31: advisor login falhou', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email ou senha incorretos.');
        }

        Auth::guard('web-advisor')->login($advisor, $request->boolean('remember'));
        $request->session()->regenerate();

        Log::info('Onda 31: advisor login ok', [
            'advisor_id' => $advisor->id,
            'email' => $advisor->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->intended('/advisor');
    }

    /**
     * POST /advisor/logout
     */
    public function logout(Request $request): RedirectResponse
    {
        $advisorId = Auth::guard('web-advisor')->id();
        Auth::guard('web-advisor')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('Onda 31: advisor logout', ['advisor_id' => $advisorId]);

        return redirect('/advisor/login')->with('success', 'Você saiu do portal.');
    }
}
