<?php

namespace Modules\Connector\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;

/**
 * Connector tem requisito especial: passport:install --force pós-migração
 * (gera client OAuth pra API REST).
 *
 * CONN-O2 (thread 02, 2026-10-01): instalar, desinstalar e atualizar NÃO rodam mais
 * por GET. No Conector isso é mais grave que nos outros módulos: instalar/atualizar
 * dispara `passport:install --force`, que regera as chaves da plataforma e derruba
 * o WR Comercial (Delphi) de todos os negócios. Um GET pode ser disparado por
 * prefetch de navegador, link em e-mail ou <img> de outra página.
 *
 * O GET continua existindo só para a tela de /manage-modules (que monta links <a>
 * para estas rotas e fica fora desta thread) não cair em 405: ele mostra uma
 * confirmação SEM efeito colateral, com um formulário POST + CSRF. A ação só roda
 * no POST.
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Connector';
    }

    protected function moduleSystemKey(): string
    {
        return 'connector';
    }

    protected function moduleVersion(): string
    {
        return (string) config('connector.module_version', '2.0');
    }

    protected function postMigrationSteps(): void
    {
        Artisan::call('passport:install', ['--force' => true]);
    }

    public function index()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install', 'Instalar o Conector',
                'Instalar roda as migrations e regera as chaves OAuth da plataforma (passport:install --force). Todo app externo que usa a API, incluindo o WR Comercial, precisa autenticar de novo.');
        }

        return parent::index();
    }

    public function uninstall()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/uninstall', 'Desinstalar o Conector',
                'Desinstalar desativa o módulo. As tabelas e as credenciais ficam preservadas.');
        }

        return parent::uninstall();
    }

    public function update()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/update', 'Atualizar o Conector',
                'Atualizar roda as migrations e regera as chaves OAuth da plataforma (passport:install --force). Todo app externo que usa a API, incluindo o WR Comercial, precisa autenticar de novo.');
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
            'url' => url('connector/'.$acao),
            'voltar' => action([\App\Http\Controllers\Install\ModulesController::class, 'index']),
        ]);

        return response($html);
    }
}
