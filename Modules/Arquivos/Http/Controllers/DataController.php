<?php

namespace Modules\Arquivos\Http\Controllers;

use App\Utils\ModuleUtil;
use Illuminate\Routing\Controller;
use Menu;

/**
 * DataController — Arquivos (DMS backbone).
 *
 * Hooks UltimatePOS pra Manage Modules (superadmin_package, user_permissions,
 * modifyAdminMenu).
 *
 * Arquivos e modulo backbone — outros modulos consomem via trait HasArquivos — E TEM
 * tela propria desde a Sprint 2: `resources/js/Pages/Arquivos/Index.tsx` (US-ARQ-013),
 * servida por `ArquivosAdminController` em `/arquivos`.
 *
 * Ate 2026-08-25 este bloco dizia que a UI viria "integrada no Admin Center". Ficou falso
 * duas vezes: o Admin Center foi deprecado pela ADR 0360, e [W] decidiu em 2026-07-29 que
 * a tela mora no proprio modulo (`SPEC.md:97` — "pode ser dentro do arquivo mesmo").
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class DataController extends Controller
{
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'arquivos_module',
                'label'   => 'Módulo Arquivos (DMS backbone — ADR 0123)',
                'default' => true,
            ],
        ];
    }

    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'arquivos.access',
                // Texto que o humano LE em `/roles/{id}/edit` ao marcar. Dizia "via Admin
                // Center", deprecado pela ADR 0360 — descreve o PODER, nao o lugar.
                'label'   => 'Arquivos: ver o acervo administrativo (/arquivos) — prazo de retencao, base legal e cofre',
                'default' => false,
            ],
        ];
    }

    /**
     * Entrada da tela do acervo no sidebar.
     *
     * Era `no-op` até 2026-08-25, com um comentário que descrevia um mundo que deixou de
     * existir: dizia que o módulo *"não tem tela própria"* e que a UI entraria *"via
     * Modules/Admin/Pages/Arquivos"*. As duas coisas ficaram falsas — a tela nasceu em
     * `resources/js/Pages/Arquivos/Index.tsx` (US-ARQ-013), e o destino em `Modules/Admin`
     * foi descartado por decisão [W] em 2026-07-29 (`SPEC.md:97` — *"pode ser dentro do
     * arquivo mesmo"*), depois que a ADR 0360 deprecou o Admin Center.
     *
     * Sintoma: a rota `/arquivos` respondia 200 em produção e **ninguém a alcançava pelo
     * menu** — só por URL direta. Pego pelo [W] no smoke, olhando o sidebar.
     *
     * As TRÊS camadas de habilitação são respeitadas aqui, na ordem que o
     * `feedback-habilitar-modulo-por-business` fixa — e nenhuma vira `if (business_id === N)`,
     * que é proibição Tier 0:
     *   1. módulo no pacote do business (`arquivos_module`) — UI do superadmin;
     *   2. permission por função (`arquivos.access`, default `false`) — `/roles/{id}/edit`;
     *   3. superadmin sempre vê.
     */
    public function modifyAdminMenu(): void
    {
        $module_util = new ModuleUtil();

        if (auth()->user()->can('superadmin')) {
            $is_enabled = $module_util->isModuleInstalled('Arquivos');
        } else {
            $business_id = session()->get('user.business_id');
            $is_enabled  = (bool) $module_util->hasThePermissionInSubscription(
                $business_id,
                'arquivos_module',
                'superadmin_package'
            );
        }

        if (! $is_enabled) {
            return;
        }

        $usuario_pode_ver = auth()->user()->can('superadmin')
            || auth()->user()->can('arquivos.access');

        if (! $usuario_pode_ver) {
            return;
        }

        $segmento_ativo = request()->segment(1) === 'arquivos';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($segmento_ativo) {
            $menu->url(url('/arquivos'), 'Arquivos', [
                'icon'   => 'fa fa-archive',
                'active' => $segmento_ativo,
            ]);
        });
    }
}
