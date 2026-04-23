@extends('layouts.app')

@section('title', __('officeimpresso::lang.officeimpresso'))

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Cadastrar Novo Computador</h1>
        <div class="subtitle">Registrar uma nova máquina Delphi manualmente</div>
    </div>

    <div class="oi-card" style="max-width: 720px;">
        <div class="hdr">
            <h3><i class="fa fa-plus-circle"></i> Dados da Máquina</h3>
        </div>
        <div class="body">
            <form action="{{ route('licenca_computador.store') }}" method="POST">
                @csrf

                <div class="form-group">
                    <label for="licenca_id">Licença (Business ID)</label>
                    <input type="text" name="licenca_id" id="licenca_id" class="form-control" required>
                    <small class="help-block text-muted">ID da empresa dona desta máquina</small>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="hd">HD (Serial)</label>
                            <input type="text" name="hd" id="hd" class="form-control text-mono" required>
                            <small class="help-block text-muted">Serial único do disco</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="user_win">Nome da Máquina (hostname)</label>
                            <input type="text" name="user_win" id="user_win" class="form-control" placeholder="Ex: BOOK-GV80BF5507">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="processador">Processador</label>
                            <input type="text" name="processador" id="processador" class="form-control" placeholder="13th Gen Intel Core i9..." required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="memoria">Memória (MB)</label>
                            <input type="text" name="memoria" id="memoria" class="form-control text-mono" placeholder="2048" required>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="versao_exe">Versão do Executável</label>
                    <input type="text" name="versao_exe" id="versao_exe" class="form-control text-mono" placeholder="2026.1.1.6" required>
                </div>

                <div style="display: flex; gap: 8px; justify-content: flex-end; border-top: 1px solid #f3f4f6; padding-top: 16px; margin-top: 8px;">
                    <a href="{{ route('licenca_computador.index') }}" class="oi-btn oi-btn-ghost">Cancelar</a>
                    <button type="submit" class="oi-btn oi-btn-primary">
                        <i class="fa fa-save"></i> Cadastrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="oi-card" style="max-width: 720px; background: #fefce8; border-color: #fde047;">
        <div class="body">
            <p style="margin: 0; color: #713f12; font-size: 13px;">
                <i class="fa fa-info-circle"></i>
                <strong>Dica:</strong> normalmente o próprio Delphi faz o registro automaticamente no primeiro login.
                Use este formulário apenas para casos especiais (migração manual, teste).
            </p>
        </div>
    </div>
</div>
@endsection
