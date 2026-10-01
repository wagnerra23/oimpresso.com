<?php

namespace Modules\Arquivos\Http\Controllers;

use App\Http\Controllers\BaseModuleInstallController;
use Illuminate\Support\Facades\Blade;

/**
 * InstallController — Modules/Arquivos.
 *
 * Acesso: /arquivos/install (superadmin → Manage Modules → Install).
 * Roda 3 migrations: arquivos + arquivos_audit_log + arquivos_dedupe.
 *
 * Thread 06 (2026-10-01): instalar, desinstalar e atualizar NÃO rodam mais por GET.
 * Um GET pode ser disparado por prefetch de navegador, link em e-mail ou <img> de outra
 * página; desinstalar por GET desativava o backbone DMS de todos os negócios sem clique.
 * O GET continua existindo só para a tela /manage-modules (que monta <a href> para estas
 * rotas e fica fora desta thread) não cair em 405: ele mostra uma confirmação SEM efeito,
 * com um formulário POST + CSRF. A ação só roda no POST. Mesmo padrão do Connector (CONN-O2).
 *
 * @see memory/decisions/0123-modules-arquivos-backbone.md
 */
class InstallController extends BaseModuleInstallController
{
    protected function moduleName(): string
    {
        return 'Arquivos';
    }

    protected function moduleSystemKey(): string
    {
        return 'arquivos';
    }

    protected function moduleVersion(): string
    {
        return '0.1.0';
    }

    protected function successMessage(): string
    {
        return 'Módulo Arquivos instalado. Backbone DMS pronto. Outros módulos podem adotar trait HasArquivos opt-in. Storage disks: configure ARQUIVOS_DISK_DEFAULT e ARQUIVOS_DISK_VAULT em config/filesystems.php (Sprint 1 dia 4).';
    }

    public function index()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install', 'Instalar o módulo Arquivos',
                'Instalar roda as migrations do módulo (arquivos, arquivos_audit_log, arquivos_dedupe) e marca o módulo como ativo.');
        }

        return parent::index();
    }

    public function uninstall()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/uninstall', 'Desinstalar o módulo Arquivos',
                'Desinstalar desativa o módulo. As tabelas e os arquivos ficam preservados.');
        }

        return parent::uninstall();
    }

    public function update()
    {
        if (! request()->isMethod('post')) {
            return $this->confirmar('install/update', 'Atualizar o módulo Arquivos',
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
            'url' => url('arquivos/'.$acao),
            'voltar' => action([\App\Http\Controllers\Install\ModulesController::class, 'index']),
        ]);

        return response($html);
    }
}
