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
     * Texto do sino para o sinal de alerta do portal (US-FISIO-007b, UC-FPP-13). Só avisa e
     * leva à ficha: não sugere conduta (ADR 0437 §5).
     *
     * @return array<string, mixed>
     */
    public function parse_notification($notification): array
    {
        if ($notification->type !== \Modules\Fisio\Notifications\SinalDeAlertaNotification::class) {
            return [];
        }
        $data = (array) $notification->data;
        $pacienteId = (int) ($data['patient_id'] ?? 0);

        return [
            'msg' => 'Fisio: paciente marcou sinal de alerta no registro do dia.',
            'icon_class' => 'fas fa-exclamation-triangle bg-yellow',
            'link' => url('/fisio/pacientes/'.$pacienteId),
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at?->diffForHumans(),
        ];
    }

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