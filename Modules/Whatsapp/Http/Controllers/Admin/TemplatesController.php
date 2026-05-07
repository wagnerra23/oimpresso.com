<?php

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Routing\Controller;

/**
 * TemplatesController — placeholder Lote 2a.
 *
 * Lote 2c implementa: sync HSM Meta + templates locais Z-API +
 * validação contraparte (Z-API local DEVE ter HSM Meta correspondente
 * pra fallback funcionar).
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-013
 */
class TemplatesController extends Controller
{
    public function index()
    {
        return view('whatsapp::placeholder', [
            'titulo' => 'Templates Whatsapp',
            'mensagem' => 'Gerenciador de templates HSM Meta + locais Z-API será implementado em Lote 2c.',
        ]);
    }
}
