<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Política de privacidade PÚBLICA do app oimpresso (ERP no celular + registro de ponto):
 * a URL estável que as lojas de aplicativo exigem. Não lê banco nem sessão — é texto.
 *
 * O texto jurídico é RASCUNHO até a revisão da Eliana [E].
 * Ver memory/requisitos/Site/RUNBOOK-privacidade.md.
 */
class PrivacidadePublicaController extends Controller
{
    public function app(): Response
    {
        return Inertia::render('Site/Privacidade');
    }
}
