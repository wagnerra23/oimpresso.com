<?php

namespace Modules\Officeimpresso\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Blade;

/**
 * InstallController — Modules/Officeimpresso.
 *
 * Thread Officeimpresso/04 (2026-10-01): instalar, desinstalar e atualizar NÃO rodam mais
 * por GET. Um GET pode ser disparado por prefetch de navegador, link em e-mail ou <img> de
 * outra página; desinstalar por GET desativava o módulo de licenças desktop sem clique.
 * O GET continua existindo só para um <a href> antigo não cair em 405: ele mostra uma
 * confirmação SEM efeito, com um formulário POST + CSRF. A ação só roda no POST.
 * Mesmo padrão do Connector (#8344) e do Arquivos (#8359).
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Officeimpresso';
    }

    protected function moduleSystemKey(): string
    {
        return 'officeimpresso';
    }

    protected function moduleVersion(): string
    {
        return (string) config('officeimpresso.module_version', '1.0');
    }

    public function index()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install', 'Instalar o módulo Office Impresso',
                'Instalar roda as migrations do módulo e marca o módulo como ativo.');
        }

        return parent::index();
    }

    public function uninstall()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/uninstall', 'Desinstalar o módulo Office Impresso',
                'Desinstalar desativa o módulo. As tabelas e as licenças ficam preservadas.');
        }

        return parent::uninstall();
    }

    public function update()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/update', 'Atualizar o módulo Office Impresso',
                'Atualizar roda as migrations pendentes do módulo.');
        }

        return parent::update();
    }

    /**
     * Tela de confirmação do GET — não executa nada, só oferece o POST.
     */
    private function confirmar(string $acao, string $titulo, string $consequencia)
    {
        if (! auth()->user()->can('superadmin')) {
            abort(403, 'Apenas superadmin pode instalar módulos.');
        }

        $html = Blade::render(<<<'BLADE'
@extends('layouts.app')
@section('title', $titulo)
@section('content')
<section class="content">
    <h3>{{ $titulo }}</h3>
    <p>{{ $consequencia }}</p>
    <form method="POST" action="{{ $url }}" style="display:inline">
        @csrf
        <button type="submit" class="btn btn-danger">Confirmar</button>
    </form>
    <a href="{{ $voltar }}" class="btn btn-default">Cancelar</a>
</section>
@endsection
BLADE, [
            'titulo' => $titulo,
            'consequencia' => $consequencia,
            'url' => url('officeimpresso/'.$acao),
            'voltar' => action([\App\Http\Controllers\Install\ModulesController::class, 'index']),
        ]);

        return response($html);
    }
}
