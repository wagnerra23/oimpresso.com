@extends('layouts.app')
@section('title', __('officeimpresso::lang.licencas'))

@section('css')
@include('officeimpresso::layouts.partials.design-system')
@endsection

@section('content')
@include('officeimpresso::layouts.nav')

<div class="oi-page">
    <div class="oi-page-header">
        <h1>Clientes OAuth</h1>
        <div class="subtitle">Credenciais Passport password grant — cada Delphi autentica com 1 destas</div>
    </div>

    @if(empty($is_demo))
    <div class="oi-card">
        <div class="hdr">
            <h3><i class="fa fa-key"></i> Clientes cadastrados <small style="color:#6b7280;">({{ count($clients) }})</small></h3>
            <div style="display: flex; gap: 8px;">
                @can('superadmin')
                    <a href="{{ action([\Modules\Officeimpresso\Http\Controllers\ClientController::class, 'regenerate']) }}"
                       class="oi-btn oi-btn-ghost">
                        <i class="fas fa-sync"></i> @lang('officeimpresso::lang.regenerate_doc')
                    </a>
                @endcan
                <button type="button" class="oi-btn oi-btn-primary" data-toggle="modal" data-target="#create_client_modal">
                    <i class="fas fa-plus"></i> @lang('officeimpresso::lang.create_client')
                </button>
            </div>
        </div>
        <div class="body no-pad">
            <div class="table-responsive">
                <table class="oi-table" id="licencas_table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nome</th>
                            <th>Client ID</th>
                            <th>Secret</th>
                            <th>Tipo</th>
                            <th style="width: 110px;">Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $licenca)
                            <tr>
                                <td class="text-mono">{{ $licenca->id }}</td>
                                <td><strong>{{ $licenca->name }}</strong></td>
                                <td class="text-mono">{{ $licenca->id }}</td>
                                <td class="text-mono" title="{{ $licenca->secret }}" style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <span class="client-secret-value" data-secret="{{ $licenca->secret }}">••••••••••</span>
                                    <button type="button" class="oi-btn oi-btn-ghost oi-btn-xs toggle-secret" style="padding: 1px 6px;">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                                <td>
                                    @if($licenca->password_client)
                                        <span class="oi-pill oi-pill-neutral">password</span>
                                    @elseif($licenca->personal_access_client)
                                        <span class="oi-pill oi-pill-neutral">personal</span>
                                    @else
                                        <span class="oi-pill oi-pill-neutral">authz_code</span>
                                    @endif
                                </td>
                                <td>
                                    {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\ClientController@destroy', [$licenca->id]), 'method' => 'delete', 'id' => 'delete_client_form_' . $licenca->id ]) !!}
                                        <button type="submit" class="oi-btn oi-btn-danger oi-btn-xs" onclick="return confirm('Remover este cliente OAuth?')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    {!! Form::close() !!}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px; color: #9ca3af;">
                                    Nenhum cliente OAuth cadastrado. Clique em "Criar Cliente" para gerar credenciais para o Delphi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="oi-card" style="background: #fefce8; border-color: #fde047;">
        <div class="body">
            <p style="margin: 0; color: #713f12;">
                <i class="fa fa-info-circle"></i> Este recurso está desabilitado em modo demo.
            </p>
        </div>
    </div>
    @endif
</div>

{{-- Modal criar cliente --}}
<div class="modal fade" id="create_client_modal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\ClientController@store'), 'method' => 'post', 'id' => 'create_client_form']) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
                <h4 class="modal-title">@lang('officeimpresso::lang.create_client')</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="name">Nome do Cliente</label>
                    <input type="text" name="name" id="name" class="form-control" placeholder="Ex: Desktop Eliana - Loja 1" required>
                    <small class="help-block text-muted">Identificação da instalação Delphi</small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Criar</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection

@section('javascript')
<script>
$(document).ready(function () {
    $('#licencas_table').DataTable({
        pageLength: 25,
        language: {
            search: 'Buscar:',
            lengthMenu: 'Mostrar _MENU_',
            info: 'Mostrando _START_ a _END_ de _TOTAL_',
            zeroRecords: 'Nenhum cliente encontrado',
            paginate: { first: '«', previous: '‹', next: '›', last: '»' }
        }
    });

    $(document).on('click', '.toggle-secret', function () {
        var span = $(this).siblings('.client-secret-value');
        var s = span.attr('data-secret');
        var visible = span.attr('data-visible') === '1';
        if (visible) {
            span.text('••••••••••').attr('data-visible', '0');
            $(this).find('i').removeClass('fa-eye-slash').addClass('fa-eye');
        } else {
            span.text(s).attr('data-visible', '1');
            $(this).find('i').removeClass('fa-eye').addClass('fa-eye-slash');
        }
    });
});
</script>
@endsection
