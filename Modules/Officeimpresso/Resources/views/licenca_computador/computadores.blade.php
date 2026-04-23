@extends('layouts.app')

@section('title', __('officeimpresso::lang.licenca_officeimpresso'))

@section('content')
<!-- Content Header (Page header) -->
<section class="content-header">
    <h1>@lang('officeimpresso::lang.licenca_officeimpresso')</h1>
</section>

<!-- Main content -->
<section class="content no-print">
    <div class="row">
        <!-- Bloco de Dados da Empresa -->
        <div class="col-md-12 col-lg-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('officeimpresso::lang.dados_empresa')])
            <div class="box box-success hvr-grow-shadow">
                <div class="box-header with-border text-center">
                    <h2 class="box-title">{{ $empresa->name }}</h2>
                </div>           
                    
                <div class="box-body text-center">                  
                    <p><i class="fa fa-user mr-2"></i> {{ $empresa->razao_social }}</p>
                    <p><i class="fa fa-map-marker mr-2"></i> {{ $empresa->rua }} </p>
                    <p><i class="fa fa-phone mr-2"></i> {{ $empresa->telefone }} </p>
                    <p><i class="fa fa-credit-card mr-2"></i> Versão Obrigatória: <strong>{{ $empresa->versao_obrigatoria }}</strong></p>
                    <p><i class="fa fa-credit-card mr-2"></i> Versão Disponível: <strong>{{ $empresa->versao_disponivel }}</strong></p>
                    <p><i class="fa fa-database mr-2"></i> Caminho Banco do Servidor: <strong>{{ $empresa->caminho_banco_servidor }} </strong></p>
                    <p><i class="fa fa-calendar mr-2"></i> Último Acesso: <strong>{{ $empresa->dt_ultimo_acesso }}</strong></p>   
                                       
                    @if(isset($package) && !empty($package))
                        <i class="fa fa-desktop mr-2"></i> @lang('superadmin::lang.officeimpresso_limitemaquinas') : 
                        <strong>{{ $package->officeimpresso_limitemaquinas == 0 ? 'Ilimitado' : $package->officeimpresso_limitemaquinas }}</strong><br/>
                    @endif

                    @if(isset($active) && !empty($active))
                        @lang('superadmin::lang.end_date') : {{ @format_date($active->end_date) }} <br/>
                        @lang('superadmin::lang.remaining', ['days' => \Carbon::today()->diffInDays($active->end_date)])
                    @endif                    

                </div>
                
                <!-- Botões de ação -->
                <div class="text-center mt-3">
                    <a href="{{ action('\Modules\Superadmin\Http\Controllers\SubscriptionController@index', [$empresa->id]) }}" class="btn btn-primary mx-2">
                        <i class="fa fa-box"></i> Ver pacote
                    </a>
                
                    <a href="{{ action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@businessupdate', [$empresa->id]) }}" class="btn btn-primary mx-2" data-toggle="modal" data-target="#editBusinessModal">
                        <i class="fa fa-edit"></i> Editar
                    </a>
                    
                    @if($empresa->officeimpresso_bloqueado)
                        <a href="{{ route('business.bloqueado', $empresa->id) }}" class="btn btn-danger mx-2">
                            <i class="fa fa-lock"></i> Bloqueado
                        </a>
                    @else
                        <a href="{{ route('business.bloqueado', $empresa->id) }}" class="btn btn-success mx-2">
                            <i class="fa fa-unlock"></i> Liberado
                        </a>
                    @endif
                </div>

            </div>
            @endcomponent
        </div>

        <!-- Bloco de Computadores -->
        <div class="col-md-12 col-lg-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('officeimpresso::lang.computadores')])
            <div class="table-responsive">
                <table class="table table-bordered table-striped" id="computadores_table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>@lang('officeimpresso::lang.dt_cadastro')</th>
                            <th>@lang('officeimpresso::lang.descricao')</th>
                            <th>@lang('officeimpresso::lang.exe_path')</th>
                            <th>@lang('officeimpresso::lang.exe_versao')</th>
                            <th>@lang('officeimpresso::lang.ip_interno')</th>
                            <th>@lang('officeimpresso::lang.exe_caminho_banco')</th>
                            <th>@lang('officeimpresso::lang.token')</th>
                            <th>@lang('officeimpresso::lang.dt_ultimoacesso')</th>
                            <th>@lang('messages.action')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($licencas as $licenca)
                            <tr>
                                <td>{{ $licenca->id }}</td>
                                <td>{{ $licenca->dt_cadastro }}</td>
                                <td>{{ $licenca->user_win }}</td>
                                <td>{{ $licenca->pasta_instalacao }}</td>
                                <td>{{ $licenca->versao_exe }}</td>
                                <td>{{ $licenca->ip_interno }}</td>
                                <td>{{ $licenca->caminho_banco }}</td>
                                <td>{{ $licenca->token }}</td>
                                <td>{{ $licenca->dt_ultimo_acesso }}</td>
                                <td>
                                    @if($licenca->bloqueado)
                                        {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@toggleBlock', [$licenca->id]), 'method' => 'GET', 'id' => 'toggle_bloqueado_form_' . $licenca->id]) !!}
                                            <button type="submit" class="btn btn-danger btn-xs">
                                                <i class="fas fa-lock"></i> @lang('officeimpresso::lang.bloqueado')
                                            </button>
                                        {!! Form::close() !!}
                                    @else
                                        {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@toggleBlock', [$licenca->id]), 'method' => 'GET', 'id' => 'toggle_liberado_form_' . $licenca->id]) !!}
                                            <button type="submit" class="btn btn-success btn-xs">
                                                <i class="fas fa-unlock"></i> @lang('officeimpresso::lang.liberado')
                                            </button>
                                        {!! Form::close() !!}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center">@lang('officeimpresso::lang.no_records_found')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @endcomponent
        </div>
    </div>
</section>

@stop

<!-- Modal de Edição de Dados da Empresa -->
<div class="modal fade" id="editBusinessModal" tabindex="-1" role="dialog" aria-labelledby="editBusinessModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {!! Form::open(['url' => action('\Modules\Officeimpresso\Http\Controllers\LicencaComputadorController@businessupdate', [$empresa->id]), 'method' => 'POST', 'id' => 'editBusinessForm']) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editBusinessModalLabel">Editar Dados da Empresa</h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    {!! Form::label('caminho_banco_servidor', 'Caminho Banco do Servidor') !!}
                    {!! Form::text('caminho_banco_servidor', $empresa->caminho_banco_servidor, ['class' => 'form-control', 'placeholder' => 'Digite o caminho do banco']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('versao_obrigatoria', 'Versão Obrigatória') !!}
                    {!! Form::text('versao_obrigatoria', $empresa->versao_obrigatoria, ['class' => 'form-control', 'placeholder' => 'Digite a versão obrigatória']) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('versao_disponivel', 'Versão Disponível') !!}
                    {!! Form::text('versao_disponivel', $empresa->versao_disponivel, ['class' => 'form-control', 'placeholder' => 'Digite a versão disponível']) !!}
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>



@section('javascript')
<script type="text/javascript">

    $(document).ready(function() {
        $('#licencas_table').DataTable();
        $('#empresa_table').DataTable();
    });

    @if(session('status'))
        $('<div class="alert alert-info">{{ session('status') }}</div>').insertBefore('.content-header');
    @endif

    $('#editBusinessModal').on('hidden.bs.modal', function () {
        $(this).find('form')[0].reset();
    });
</script>
@endsection
