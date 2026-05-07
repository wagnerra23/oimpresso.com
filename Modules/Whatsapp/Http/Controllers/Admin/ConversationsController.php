<?php

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * ConversationsController — placeholder Lote 2a.
 *
 * Implementação real virá em Lote 2c (Inertia/React Cockpit pattern):
 * - resources/js/Pages/Whatsapp/Conversations/Index.tsx (lista esquerda + chat painel)
 * - resources/js/Pages/Whatsapp/Conversations/Show.tsx (real-time Centrifugo)
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-012
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §7
 */
class ConversationsController extends Controller
{
    public function index(Request $request)
    {
        return view('whatsapp::placeholder', [
            'titulo' => 'Conversas Whatsapp',
            'mensagem' => 'Inbox Cockpit pattern será implementada em Lote 2c.',
        ]);
    }
}
