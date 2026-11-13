<?php

namespace Modules\Associacoes\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Associacoes\Services\AssociacoesAcesso;

/**
 * DataController — Modules/Associacoes (ADR 0451).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}). Sem item de menu até existir a primeira
 * tela (onda 3).
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
        // A onda 3 da ADR 0451 cria a tela de associações e o item de menu.
    }
}
