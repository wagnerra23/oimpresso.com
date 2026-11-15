<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Página inicial da Uniafibro (União das Associações de Fibromiálgicos de Santa Catarina), servida em
 * https://uniafibro.com.br pelo mesmo app do oimpresso.com.
 *
 * Entrega um HTML estático (resources/uniafibro/home.html): não lê banco, sessão nem tenant, e
 * não carrega JavaScript.
 *
 * Não é página Inertia de propósito: o AppServiceProvider fixa a raiz das URLs em
 * config('app.url') (oimpresso.com), então os scripts Vite de uma página Inertia viriam de outra
 * origem e o navegador os bloquearia por CORS (o mesmo motivo do 301 de www.oimpresso.com no
 * .htaccess). Também não é Blade, para não somar rota nova na catraca da migração Blade→React
 * (ADR 0277): o conteúdo é estático.
 *
 * Ver memory/requisitos/Site/RUNBOOK-uniafibro.md.
 */
class UniafibroSiteController extends Controller
{
    public function home(): BinaryFileResponse
    {
        return response()->file(resource_path('uniafibro/home.html'), [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
