<?php

namespace Modules\Captacao\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Captacao\Services\CaptacaoAcesso;

/**
 * DataController — Modules/Captacao (ADR 0452).
 *
 * Habilitação é SEMPRE por UI (pacote do superadmin + permissão do papel), nunca por if de
 * business no código ({@see memory/proibicoes.md}). Sem item de menu até existir a primeira
 * tela (onda 2).
 */
class DataController extends Controller
{
    public const PACOTE = CaptacaoAcesso::PACOTE;

    public function superadmin_package(): array
    {
        return [
            ['name' => self::PACOTE, 'label' => 'Modulo Captacao (projetos de captacao de recursos)', 'default' => false],
        ];
    }

    public function user_permissions(): array
    {
        return [
            ['value' => CaptacaoAcesso::PERMISSAO, 'label' => 'Captacao: acessar o modulo', 'default' => false],
            ['value' => CaptacaoAcesso::PERMISSAO_EDITAR, 'label' => 'Captacao: cadastrar projetos, fontes, aportes e comprovantes', 'default' => false],
        ];
    }

    public function modifyAdminMenu(): void
    {
        // A onda 2 da ADR 0452 cria a tela de projetos e o item de menu.
    }
}
