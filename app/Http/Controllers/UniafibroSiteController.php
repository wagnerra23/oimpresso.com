<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;

/**
 * Página inicial da Uniafibro (União das Associações de Fibromialgia de SC), servida em
 * https://uniafibro.com.br pelo mesmo app do oimpresso.com.
 *
 * Só texto e imagem: não lê banco, sessão nem tenant, e não carrega JavaScript.
 *
 * É uma página Blade sem o bundle Inertia de propósito: o AppServiceProvider fixa a raiz das
 * URLs em config('app.url') (oimpresso.com), então os scripts Vite de uma página Inertia
 * viriam de outra origem e o navegador os bloquearia por CORS (o mesmo motivo do 301 de
 * www.oimpresso.com no .htaccess). O shell do oimpresso também não é a marca da associação.
 *
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md.
 */
class UniafibroSiteController extends Controller
{
    private const INICIO = 'https://uniafibro.com.br/';

    public function home(): View
    {
        return view('uniafibro.home');
    }

    /** www.uniafibro.com.br → uniafibro.com.br (endereço único). */
    public function paraDominioPrincipal(): RedirectResponse
    {
        return redirect()->away(self::INICIO, 301);
    }

    /** Outro caminho no domínio da associação volta ao início, em vez de cair numa tela do ERP. */
    public function paraInicio(): RedirectResponse
    {
        return redirect()->away(self::INICIO, 302);
    }
}
