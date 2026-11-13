<?php

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\Associacoes\Services\AssociacoesAcesso;

/**
 * DataController — Modules/Associacoes (ADR 0451).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}).
 *
 * Papéis (D4 da ADR 0451): `associacoes.rede` é da diretoria da rede (vê todas as associações);
 * sem ela, quem tem `associacoes.access` vê só as associações em que é representante
 * (`assoc_representantes`). A regra mora em {@see AssociacoesAcesso}, usada por menu, rota e lista.
 */
class DataController extends Controller
{
    public const PACOTE = AssociacoesAcesso::PACOTE;

    public function superadmin_package(): array
    {
        return [
            ['name' => self::PACOTE, 'label' => 'Modulo Associacoes (rede de associacoes)', 'default' => false],
        ];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => 'associacoes.access', 'label' => 'Associacoes: acessar o modulo', 'default' => false],
            ['value' => 'associacoes.rede', 'label' => 'Associacoes: ver e editar toda a rede', 'default' => false],
            ['value' => 'associacoes.editar', 'label' => 'Associacoes: cadastrar associacoes, documentos e associadas', 'default' => false],
        ];
    }

    /**
     * Dropdown "Associações". A mesma regra do middleware das rotas ({@see AssociacoesAcesso::liberado}),
     * para menu e rota nunca discordarem. A onda 3 (US-ASSOC-002) é dona do dropdown e acrescenta
     * as linhas dela; a onda 4 (US-ASSOC-003) é dona só da linha "Associadas".
     */
    public function modifyAdminMenu(): void
    {
        if (! app(AssociacoesAcesso::class)->liberado(auth()->user(), session()->get('user.business_id'))) {
            return;
        }

        Menu::modify('admin-sidebar-menu', function ($menu) {
            $menu->dropdown('Associações', function ($sub) {
                // ── onda 4 · US-ASSOC-003 ──
                $sub->url(url('/associacoes/associadas'), 'Associadas', [
                    'icon' => 'fa fa-users',
                    'active' => request()->segment(1) === 'associacoes' && request()->segment(2) === 'associadas',
                ]);
            }, ['icon' => 'fa fa-hands-helping', 'active' => request()->segment(1) === 'associacoes']);
        });
    }
}
