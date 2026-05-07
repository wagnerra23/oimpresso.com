<?php

namespace Modules\Whatsapp\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * SettingsController — placeholder Lote 2a.
 *
 * Lote 2b implementa wizard 2 passos obrigatórios (Z-API hoje + Meta Cloud em paralelo):
 * - FormRequest cross-field: driver=zapi exige meta_* preenchidos como fallback
 * - Termo LGPD obrigatório quando driver=zapi (lgpd_acknowledged_at)
 * - Tokens cifrados em DB via encrypted cast Laravel
 * - Botão "Testar conexão" → Driver::ping()
 *
 * @see memory/requisitos/Whatsapp/SPEC.md US-WA-001
 * @see memory/requisitos/Whatsapp/ARCHITECTURE.md §14 onboarding wizard
 */
class SettingsController extends Controller
{
    public function show()
    {
        return view('whatsapp::placeholder', [
            'titulo' => 'Configurações Whatsapp',
            'mensagem' => 'Wizard 2 passos (Z-API hoje + Meta Cloud em paralelo) será implementado em Lote 2b.',
        ]);
    }

    public function update(Request $request)
    {
        // Lote 2b: FormRequest com gating duro fallback Meta Cloud
        return back()->with('status', 'Lote 2b — não implementado ainda');
    }
}
