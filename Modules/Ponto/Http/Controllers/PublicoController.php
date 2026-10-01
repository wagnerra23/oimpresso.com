<?php

declare(strict_types=1);

namespace Modules\Ponto\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Páginas PÚBLICAS do app de ponto (sem login): o texto que as lojas de aplicativo
 * (Google Play e App Store) exigem numa URL estável. Não lê banco nem sessão — é texto.
 *
 * O conteúdo jurídico é RASCUNHO até a revisão da Eliana [E] (advogada).
 * Ver memory/requisitos/Ponto/RUNBOOK-publico.md.
 */
class PublicoController extends Controller
{
    public function privacidade(): Response
    {
        return Inertia::render('Ponto/Publico/Privacidade');
    }
}
