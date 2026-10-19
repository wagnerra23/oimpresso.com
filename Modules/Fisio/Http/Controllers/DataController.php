<?php

namespace Modules\Fisio\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\Fisio\Services\FisioAcesso;

/**
 * DataController — Modules/Fisio (ADR 0437).
 *
 * Convenção UltimatePOS: `superadmin_package` e `user_permissions` são lidos nas
 * telas de Pacotes e de Papéis; `modifyAdminMenu` roda a cada request da sidebar.
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca
 * por if de business no código — Tier 0 pareado com o `business_id` global scope
 * ({@see memory/proibicoes.md}).
 */
class DataController extends Controller
{
    /**
     * `default => false`: Fisio é add-on contratado. Empresa que não contratou não vê
     * menu nem rota (ADR 0437 §3.3 e §11).
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => FisioAcesso::PACOTE,
                'label'   => 'Modulo Fisio (fisioterapia)',
                'default' => false,
            ],
        ];
    }

    /**
     * `fisio.access` abre o módulo; `fisio.editar` grava (paciente, prontuário,
     * programa, biblioteca própria). Nenhuma nasce concedida.
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => FisioAcesso::PERMISSAO,
                'label'   => 'Fisio: acessar o modulo',
                'default' => false,
            ],
            [
                'value'   => 'fisio.editar',
                'label'   => 'Fisio: cadastrar pacientes, prontuario e programas',
                'default' => false,
            ],
        ];
    }

    /**
     * Seção "Fisio" na sidebar (ADR 0437 §3.3, aprovada por [W] em 2026-10-18), só para
     * empresa com o pacote ativo e usuário com `fisio.access` — a MESMA regra do
     * middleware das rotas (FisioAcesso), para menu e rota nunca discordarem.
     * Os subitens (Painel, Pacientes, Prescrever, Biblioteca, Agenda) entram com as telas.
     */
    public function modifyAdminMenu(): void
    {
        $liberado = app(FisioAcesso::class)->liberado(auth()->user(), session()->get('user.business_id'));

        if (! $liberado) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'fisio';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($segmento_ativo) {
            $menu->dropdown('Fisio', function ($sub) {
                // Painel (US-FISIO-010) primeiro; Pacientes (US-FISIO-005) em seguida.
                $sub->url(url('/fisio'), 'Painel', [
                    'icon' => 'fa fa-tachometer-alt',
                    'active' => request()->segment(1) === 'fisio' && request()->segment(2) === null,
                ]);
                $sub->url(url('/fisio/pacientes'), 'Pacientes', [
                    'icon' => 'fa fa-user',
                    'active' => request()->segment(2) === 'pacientes',
                ]);
            }, [
                'icon' => 'fa fa-heartbeat',
                'active' => $segmento_ativo,
            ]);
        });
    }
}