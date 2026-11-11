<?php

namespace Modules\Financeiro\Http\Controllers\Advisor;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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
    /** Erros de senha por conta e IP antes de travar (UC-ADVL-04). */
    private const MAX_ERROS_POR_CONTA = 5;

    /** Erros por IP, somando todas as contas: barra a varredura de e-mails. */
    private const MAX_ERROS_POR_IP = 20;

    private const JANELA_SEGUNDOS = 60;

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
     * Conta só erro de senha (acerto zera a conta), por isso o limite mora aqui e não no
     * middleware `throttle`, que contaria também os logins certos. Travado, responde
     * com o aviso na própria tela em vez de 429, que o Inertia mostraria como erro cru.
     */
    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        $chaveConta = 'advisor-login:conta:'.sha1(Str::lower($credentials['email']).'|'.$request->ip());
        $chaveIp = 'advisor-login:ip:'.sha1((string) $request->ip());

        if (RateLimiter::tooManyAttempts($chaveConta, self::MAX_ERROS_POR_CONTA)
            || RateLimiter::tooManyAttempts($chaveIp, self::MAX_ERROS_POR_IP)) {
            $segundos = max(1, RateLimiter::availableIn($chaveConta), RateLimiter::availableIn($chaveIp));
            Log::warning('Onda 31: advisor login travado por tentativas', ['ip' => $request->ip()]);

            return back()
                ->withInput($request->only('email'))
                ->with('error', "Muitas tentativas. Tente de novo em {$segundos} segundos.");
        }

        $advisor = Advisor::where('email', $credentials['email'])
            ->where('ativo', true)
            ->first();

        if (! $advisor || ! $advisor->password_hash || ! Hash::check($credentials['password'], $advisor->password_hash)) {
            RateLimiter::hit($chaveConta, self::JANELA_SEGUNDOS);
            RateLimiter::hit($chaveIp, self::JANELA_SEGUNDOS);

            Log::warning('Onda 31: advisor login falhou', [
                'email' => $credentials['email'],
                'ip' => $request->ip(),
            ]);
            return back()
                ->withInput($request->only('email'))
                ->with('error', 'Email ou senha incorretos.');
        }

        RateLimiter::clear($chaveConta);
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
