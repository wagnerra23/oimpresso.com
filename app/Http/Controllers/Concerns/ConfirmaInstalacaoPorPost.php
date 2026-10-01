<?php

namespace App\Http\Controllers\Concerns;

/**
 * Instalar / desinstalar / atualizar um módulo só por POST + CSRF.
 *
 * Um GET pode ser disparado por prefetch de navegador, link em e-mail ou <img> de outra
 * página — GET que muda estado é alvo de CSRF. O GET continua registrado (link antigo não
 * cai em 405), mas só devolve uma tela de confirmação SEM efeito, com formulário POST + CSRF
 * para a MESMA URL. Mesmo padrão do Connector (#8344), Arquivos (#8359) e Officeimpresso
 * (#8367), aqui extraído pra não duplicar a tela em cada módulo.
 *
 * Uso: no início de cada ação, `if ($c = $this->confirmacaoSeNaoForPost('uninstall')) { return $c; }`.
 * Módulo que estende BaseModuleInstallController usa o trait InstalacaoSoPorPost, que já faz isso.
 */
trait ConfirmaInstalacaoPorPost
{
    /**
     * null no POST (a ação segue); no GET, a tela de confirmação — nada é executado.
     *
     * @param  string  $acao  install | uninstall | update
     */
    protected function confirmacaoSeNaoForPost(string $acao)
    {
        if (request()->isMethod('post')) {
            return null;
        }

        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Apenas superadmin pode instalar módulos.');
        }

        return $this->telaConfirmacaoInstalacao($acao);
    }

    protected function telaConfirmacaoInstalacao(string $acao)
    {
        $modulo = $this->nomeModuloInstalacao();
        $titulos = [
            'install' => 'Instalar o módulo '.$modulo,
            'uninstall' => 'Desinstalar o módulo '.$modulo,
            'update' => 'Atualizar o módulo '.$modulo,
        ];
        $consequencias = [
            'install' => 'Instalar roda as migrations do módulo e marca o módulo como ativo.',
            'uninstall' => 'Desinstalar desativa o módulo. As tabelas ficam preservadas.',
            'update' => 'Atualizar roda as migrations pendentes do módulo.',
        ];

        return response()->view('install.modules.confirmar', [
            'titulo' => $titulos[$acao] ?? $modulo,
            'consequencia' => $consequencias[$acao] ?? '',
            'url' => request()->url(),
            'voltar' => action([\App\Http\Controllers\Install\ModulesController::class, 'index']),
        ]);
    }

    /** InstalacaoSoPorPost sobrescreve com moduleName() do BaseModuleInstallController. */
    protected function nomeModuloInstalacao(): string
    {
        // Modules\<Nome>\Http\Controllers\InstallController → <Nome>
        return explode('\\', static::class)[1] ?? class_basename(static::class);
    }
}
