<?php

namespace Modules\Producao\Http\Controllers;

use Illuminate\Routing\Controller;

/**
 * DataController — Modules/Producao (ADR 0431).
 *
 * Convenção UltimatePOS: `superadmin_package` e `user_permissions` são lidos nas
 * telas de Pacotes e de Papéis; `modifyAdminMenu` roda a cada request da sidebar.
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca
 * por `if ($business_id === N)` no código — Tier 0 pareado com o `business_id`
 * global scope ({@see memory/proibicoes.md}).
 */
class DataController extends Controller
{
    /**
     * `default => false`: Produção é add-on contratado. O piloto (biz=1, D-PILOTO)
     * entra marcando o pacote, não por código.
     */
    public function superadmin_package(): array
    {
        return [
            [
                'name'    => 'producao_module',
                'label'   => 'Modulo Producao (PCP)',
                'default' => false,
            ],
        ];
    }

    /**
     * As duas últimas foram aprovadas por [W] em 2026-10-15 (D-PERM). Sem
     * `producao.desempenho.ver`, produtividade só aparece por setor, nunca por
     * pessoa (UC-PCV-27).
     */
    public function user_permissions(): array
    {
        return [
            [
                'value'   => 'producao.access',
                'label'   => 'Producao: acessar o modulo',
                'default' => false,
            ],
            [
                'value'   => 'producao.etapa.mover',
                'label'   => 'Producao: mover etapa da OS no quadro',
                'default' => false,
            ],
            [
                'value'   => 'producao.desempenho.ver',
                'label'   => 'Producao: ver produtividade por pessoa',
                'default' => false,
            ],
        ];
    }

    /**
     * Sem item de sidebar enquanto o módulo não tem tela (esqueleto). A thread do
     * Painel/Quadro acrescenta o item junto com a primeira Page.
     */
    public function modifyAdminMenu(): void
    {
    }
}
