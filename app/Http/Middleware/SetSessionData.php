<?php

namespace App\Http\Middleware;

use App\Business;
use App\Utils\BusinessUtil;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SetSessionData
{
    /**
     * Checks if session data is set or not for a user. If data is not set then set it.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        // Sessão meia-populada (bloco `user` presente mas sem business_id) faz o app
        // inteiro operar como "business 0" em silêncio — Tier 0 multi-tenant (ADR 0093).
        // Vetores conhecidos: sessão stale pós-deploy, login social (Auth::login sem
        // popular sessão UPOS), superadmin "Sign in as user". Reconstruir sempre.
        $sessionUserStale = $request->session()->has('user')
            && empty($request->session()->get('user.business_id'));

        if (! $request->session()->has('user') || $sessionUserStale) {
            if ($sessionUserStale) {
                Log::warning('SetSessionData: sessão com bloco user sem business_id — reconstruindo a partir de auth()', [
                    'user_id' => Auth::id(),
                    'path' => $request->path(),
                ]);
            }

            $business_util = new BusinessUtil;

            $user = Auth::user();
            $session_data = ['id' => $user->id,
                'surname' => $user->surname,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'business_id' => $user->business_id,
                'language' => $user->language,
            ];
            $business = Business::findOrFail($user->business_id);

            $currency = $business->currency;
            $currency_data = ['id' => $currency->id,
                'code' => $currency->code,
                'symbol' => $currency->symbol,
                'thousand_separator' => $currency->thousand_separator,
                'decimal_separator' => $currency->decimal_separator,
            ];

            $request->session()->put('user', $session_data);
            $request->session()->put('business', $business);
            $request->session()->put('business_timezone', $business->time_zone);
            $request->session()->put('currency', $currency_data);

            //set current financial year to session
            $financial_year = $business_util->getCurrentFinancialYear($business->id);
            $request->session()->put('financial_year', $financial_year);
        }

        return $next($request);
    }
}
