<?php

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Routing\Controller;
use Menu;
use Modules\Associacoes\Services\AssociacoesAcesso;

/**
 * DataController — Modules/Associacoes (ADR 0451).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}). O dropdown "Associações" nasce na onda 3
 * com a lista de associações; as ondas 4 e 5 acrescentam uma linha cada dentro dele.
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

    public function modifyAdminMenu(): void
    {
        // Mesma regra de rota e lista (AssociacoesAcesso): pacote + `associacoes.access`.
        if (! app(AssociacoesAcesso::class)->liberado(auth()->user(), session()->get('user.business_id'))) {
            return;
        }

        $ativo = request()->segment(1) === 'associacoes';

        Menu::modify('admin-sidebar-menu', function ($menu) use ($ativo) {
            $menu->dropdown('Associações', function ($sub) {
                // onda 3 · US-ASSOC-002
                $sub->url(url('/associacoes/lista'), 'Associações', [
                    'icon' => 'fa fa-sitemap',
                    'active' => request()->segment(1) === 'associacoes' && request()->segment(2) === 'lista',
                ]);
                // onda 4 · associadas (US-ASSOC-003) — uma linha aqui.
                // onda 5 · painel da rede em /associacoes (US-ASSOC-004) — uma linha aqui.
            }, ['icon' => 'fa fa-hands-helping', 'active' => $ativo]);
        });
    }
}
