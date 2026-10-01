<?php

namespace App\Http\Controllers\Concerns;

/**
 * Para InstallController que estende BaseModuleInstallController: index/uninstall/update só
 * executam no POST; no GET devolvem a confirmação de ConfirmaInstalacaoPorPost.
 *
 * O `install()` do base chama `$this->index()` e o `update()` do base também — ambos passam
 * por aqui, então no POST a cadeia segue igual a antes; no GET nada roda.
 * As rotas do módulo precisam aceitar POST nas três URLs (senão o formulário dá 405).
 */
trait InstalacaoSoPorPost
{
    use ConfirmaInstalacaoPorPost;

    public function index()
    {
        if ($confirmacao = $this->confirmacaoSeNaoForPost('install')) {
            return $confirmacao;
        }

        return parent::index();
    }

    public function uninstall()
    {
        if ($confirmacao = $this->confirmacaoSeNaoForPost('uninstall')) {
            return $confirmacao;
        }

        return parent::uninstall();
    }

    public function update()
    {
        if ($confirmacao = $this->confirmacaoSeNaoForPost('update')) {
            return $confirmacao;
        }

        return parent::update();
    }
}
